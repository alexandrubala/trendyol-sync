<?php
/**
 * Grupează variațiile sub același productMainId (cerință Trendyol).
 *
 * @package TrendyolSync\Sync
 */

namespace TrendyolSync\Sync;

use TrendyolSync\WooCommerce\Meta_Keys;
use TrendyolSync\WooCommerce\Product_Adapter;

defined( 'ABSPATH' ) || exit;

/**
 * Class Variant_Grouper
 */
class Variant_Grouper {

	private const MAIN_ID_PREFIX = 'ty-main-';

	/**
	 * @var Product_Adapter
	 */
	private $adapter;

	/**
	 * @param Product_Adapter|null $adapter Adaptor produse (injectabil pentru teste).
	 */
	public function __construct( ?Product_Adapter $adapter = null ) {
		$this->adapter = $adapter ?? new Product_Adapter();
	}

	/**
	 * Rezolvă productMainId pentru un produs (simple sau variație).
	 *
	 * Prioritate: meta salvată → SKU părinte → SKU propriu → ID generat stabil.
	 *
	 * @param \WC_Product $product Produs WooCommerce.
	 * @return string
	 */
	public function resolve_product_main_id( \WC_Product $product ): string {
		$stored = Meta_Keys::get_string( $product->get_id(), Meta_Keys::PRODUCT_MAIN_ID );

		if ( '' !== $stored ) {
			return $this->sanitize_main_id( $stored );
		}

		if ( $product->is_type( 'variation' ) && $product->get_parent_id() > 0 ) {
			$parent = wc_get_product( $product->get_parent_id() );

			if ( $parent instanceof \WC_Product ) {
				$parent_stored = Meta_Keys::get_string( $parent->get_id(), Meta_Keys::PRODUCT_MAIN_ID );

				if ( '' !== $parent_stored ) {
					return $this->sanitize_main_id( $parent_stored );
				}

				$parent_sku = $parent->get_sku();

				if ( is_string( $parent_sku ) && '' !== trim( $parent_sku ) ) {
					return $this->sanitize_main_id( trim( $parent_sku ) );
				}

				return $this->generated_main_id( $parent->get_id() );
			}
		}

		$sku = $product->get_sku();

		if ( is_string( $sku ) && '' !== trim( $sku ) ) {
			return $this->sanitize_main_id( trim( $sku ) );
		}

		return $this->generated_main_id( $product->get_id() );
	}

	/**
	 * Grupează variațiile unui produs variabil după productMainId.
	 *
	 * @param \WC_Product $variable_product Produs de tip variable.
	 * @return array<string, mixed> Structură: product_main_id, parent_id, items[].
	 */
	public function group_variable_product( \WC_Product $variable_product ): array {
		if ( ! $variable_product->is_type( 'variable' ) ) {
			return array(
				'product_main_id' => $this->resolve_product_main_id( $variable_product ),
				'parent_id'       => $variable_product->get_id(),
				'items'           => array( $this->adapter->extract( $variable_product ) ),
			);
		}

		$main_id    = $this->resolve_product_main_id( $variable_product );
		$parent_id  = $variable_product->get_id();
		$variation_ids = $variable_product->get_children();
		$items      = array();

		foreach ( $variation_ids as $variation_id ) {
			$variation = wc_get_product( $variation_id );

			if ( ! $variation instanceof \WC_Product || ! $variation->is_type( 'variation' ) ) {
				continue;
			}

			$item = $this->adapter->extract( $variation );
			$item['product_main_id'] = $main_id;
			$items[] = $item;
		}

		if ( empty( $items ) ) {
			$fallback = $this->adapter->extract( $variable_product );
			$fallback['product_main_id'] = $main_id;
			$items[] = $fallback;
		}

		return array(
			'product_main_id' => $main_id,
			'parent_id'       => $parent_id,
			'items'           => $items,
		);
	}

	/**
	 * Grupează o listă arbitrară de produse WooCommerce pentru export.
	 *
	 * Produsele variabile returnează câte un grup; simplele sunt grupuri de 1 element.
	 *
	 * @param \WC_Product[] $products Lista produse.
	 * @return array<int, array<string, mixed>>
	 */
	public function group_products( array $products ): array {
		$groups       = array();
		$processed_parents = array();

		foreach ( $products as $product ) {
			if ( ! $product instanceof \WC_Product ) {
				continue;
			}

			if ( $product->is_type( 'variation' ) ) {
				continue;
			}

			if ( $product->is_type( 'variable' ) ) {
				$parent_id = $product->get_id();

				if ( isset( $processed_parents[ $parent_id ] ) ) {
					continue;
				}

				$processed_parents[ $parent_id ] = true;
				$groups[] = $this->group_variable_product( $product );
				continue;
			}

			$extracted = $this->adapter->extract( $product );
			$main_id   = $this->resolve_product_main_id( $product );
			$extracted['product_main_id'] = $main_id;

			$groups[] = array(
				'product_main_id' => $main_id,
				'parent_id'       => $product->get_id(),
				'items'           => array( $extracted ),
			);
		}

		return $groups;
	}

	/**
	 * Persistă productMainId pe părinte și toate variațiile.
	 *
	 * @param int $parent_id ID produs variabil.
	 * @return string productMainId aplicat.
	 */
	public function persist_group_main_id( int $parent_id ): string {
		$product = wc_get_product( $parent_id );

		if ( ! $product instanceof \WC_Product ) {
			return '';
		}

		$main_id = $this->resolve_product_main_id( $product );

		update_post_meta( $parent_id, Meta_Keys::PRODUCT_MAIN_ID, $main_id );

		if ( $product->is_type( 'variable' ) ) {
			foreach ( $product->get_children() as $variation_id ) {
				update_post_meta( (int) $variation_id, Meta_Keys::PRODUCT_MAIN_ID, $main_id );
			}
		}

		return $main_id;
	}

	/**
	 * Trunchiază la 40 caractere (limită API Trendyol).
	 *
	 * @param string $main_id Cod model.
	 * @return string
	 */
	private function sanitize_main_id( string $main_id ): string {
		$main_id = preg_replace( '/\s+/', '', $main_id );

		if ( ! is_string( $main_id ) || '' === $main_id ) {
			return '';
		}

		return mb_substr( $main_id, 0, 40 );
	}

	/**
	 * @param int $product_id ID WooCommerce.
	 * @return string
	 */
	private function generated_main_id( int $product_id ): string {
		return self::MAIN_ID_PREFIX . $product_id;
	}
}
