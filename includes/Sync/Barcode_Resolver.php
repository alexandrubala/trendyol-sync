<?php
/**
 * Resolver barcode cu fallback-uri și generare automată.
 *
 * @package TrendyolSync\Sync
 */

namespace TrendyolSync\Sync;

use TrendyolSync\Utils\Ean13;
use TrendyolSync\WooCommerce\Meta_Keys;

defined( 'ABSPATH' ) || exit;

/**
 * Class Barcode_Resolver
 */
class Barcode_Resolver {

	public const STRATEGY_INTERNAL      = 'internal';
	public const STRATEGY_SKU_BASED     = 'sku_based';
	public const STRATEGY_EAN13_INTERNAL = 'ean13_internal';

	/**
	 * @param \WC_Product $product Produs simplu sau variație.
	 * @return string
	 */
	public function resolve_for_product( \WC_Product $product ): string {
		$product_id = $product->get_id();

		$stored = $this->sanitize_barcode( Meta_Keys::get_string( $product_id, Meta_Keys::BARCODE ) );
		if ( '' !== $stored ) {
			return $stored;
		}

		$global_unique = $this->sanitize_barcode( (string) $product->get_meta( '_global_unique_id', true ) );
		if ( '' !== $global_unique ) {
			return $global_unique;
		}

		$custom_meta_candidates = apply_filters(
			'trendyol_sync_barcode_meta_keys',
			array( '_ean', '_barcode', '_barcode_number', '_gtin', '_upc', '_ean13' ),
			$product
		);

		if ( is_array( $custom_meta_candidates ) ) {
			foreach ( $custom_meta_candidates as $meta_key ) {
				if ( ! is_string( $meta_key ) || '' === $meta_key ) {
					continue;
				}
				$candidate = $this->sanitize_barcode( (string) $product->get_meta( $meta_key, true ) );
				if ( '' !== $candidate ) {
					return $candidate;
				}
			}
		}

		$sku = $this->sanitize_barcode( (string) $product->get_sku() );
		if ( '' !== $sku && $this->is_unique_barcode( $sku, $product_id ) ) {
			return $sku;
		}

		return $this->generate_barcode( $product );
	}

	/**
	 * Generează și persistă barcode dacă produsul nu are unul.
	 *
	 * @param \WC_Product $product Produs WooCommerce.
	 * @return string
	 */
	public function persist_missing_for_product( \WC_Product $product ): string {
		$product_id = $product->get_id();
		$stored     = $this->sanitize_barcode( Meta_Keys::get_string( $product_id, Meta_Keys::BARCODE ) );

		if ( '' !== $stored ) {
			return $stored;
		}

		$resolved = $this->resolve_for_product( $product );

		if ( '' !== $resolved ) {
			update_post_meta( $product_id, Meta_Keys::BARCODE, $resolved );
		}

		return $resolved;
	}

	/**
	 * @param \WC_Product $product Produs sursă.
	 * @return string
	 */
	private function generate_barcode( \WC_Product $product ): string {
		$settings = trendyol_sync()->settings()->get_stored_settings();
		$strategy = isset( $settings['barcode_strategy'] )
			? (string) $settings['barcode_strategy']
			: self::STRATEGY_INTERNAL;

		$product_id = $product->get_id();

		if ( self::STRATEGY_EAN13_INTERNAL === $strategy ) {
			$prefix = isset( $settings['barcode_ean_prefix'] ) ? absint( $settings['barcode_ean_prefix'] ) : 200;
			return Ean13::generate_internal( $product_id, $prefix );
		}

		if ( self::STRATEGY_SKU_BASED === $strategy ) {
			$prefix = isset( $settings['barcode_prefix'] ) ? (string) $settings['barcode_prefix'] : 'TY-';
			$sku    = $this->sanitize_barcode( (string) $product->get_sku() );

			if ( '' !== $sku ) {
				$generated = $this->sanitize_barcode( $prefix . $sku );
				if ( '' !== $generated && $this->is_unique_barcode( $generated, $product_id ) ) {
					return $generated;
				}
			}
		}

		return 'ty-' . $product_id;
	}

	/**
	 * @param string $barcode Barcode candidate.
	 * @param int    $product_id ID produs curent.
	 * @return bool
	 */
	private function is_unique_barcode( string $barcode, int $product_id ): bool {
		$barcode = $this->sanitize_barcode( $barcode );

		if ( '' === $barcode ) {
			return false;
		}

		$query = new \WP_Query(
			array(
				'post_type'      => array( 'product', 'product_variation' ),
				'post_status'    => 'any',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'post__not_in'   => array( $product_id ),
				'meta_query'     => array(
					array(
						'key'   => Meta_Keys::BARCODE,
						'value' => $barcode,
					),
				),
			)
		);

		return ! $query->have_posts();
	}

	/**
	 * @param string $value Valoare brută.
	 * @return string
	 */
	private function sanitize_barcode( string $value ): string {
		$value = trim( preg_replace( '/\s+/', '', $value ) );

		if ( '' === $value ) {
			return '';
		}

		if ( function_exists( 'mb_substr' ) ) {
			return mb_substr( $value, 0, 40 );
		}

		return substr( $value, 0, 40 );
	}
}
