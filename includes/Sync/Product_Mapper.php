<?php
/**
 * Transformă datele WooCommerce în payload JSON Product Create v2 (items[]).
 *
 * @package TrendyolSync\Sync
 */

namespace TrendyolSync\Sync;

use TrendyolSync\WooCommerce\Product_Adapter;

defined( 'ABSPATH' ) || exit;

/**
 * Class Product_Mapper
 */
class Product_Mapper {

	/**
	 * @var Product_Adapter
	 */
	private $adapter;

	/**
	 * @var Image_Normalizer
	 */
	private $image_normalizer;

	/**
	 * @var Variant_Grouper
	 */
	private $grouper;

	/**
	 * @param Product_Adapter|null  $adapter          Adaptor produse.
	 * @param Image_Normalizer|null $image_normalizer Normalizator imagini.
	 * @param Variant_Grouper|null  $grouper          Grupare variații.
	 */
	public function __construct(
		?Product_Adapter $adapter = null,
		?Image_Normalizer $image_normalizer = null,
		?Variant_Grouper $grouper = null
	) {
		$this->adapter          = $adapter ?? new Product_Adapter();
		$this->image_normalizer = $image_normalizer ?? new Image_Normalizer();
		$this->grouper          = $grouper ?? new Variant_Grouper( $this->adapter );
	}

	/**
	 * Mapează un produs WooCommerce într-un singur element items[].
	 *
	 * @param \WC_Product $product Produs sau variație.
	 * @return array<string, mixed> Element Trendyol (fără wrapper items).
	 */
	public function map_product( \WC_Product $product ): array {
		$data = $this->adapter->extract( $product );

		if ( '' === (string) ( $data['product_main_id'] ?? '' ) ) {
			$data['product_main_id'] = $this->grouper->resolve_product_main_id( $product );
		}

		return $this->map_adapted_item( $data );
	}

	/**
	 * Mapează un grup de variații (sau produs simplu) în payload complet { items: [...] }.
	 *
	 * @param \WC_Product $product Produs variabil sau simplu.
	 * @return array{items: array<int, array<string, mixed>>}
	 */
	public function map_product_group( \WC_Product $product ): array {
		$group = $this->grouper->group_variable_product( $product );
		$items = array();

		foreach ( $group['items'] as $adapted ) {
			$items[] = $this->map_adapted_item( $adapted );
		}

		return array( 'items' => $items );
	}

	/**
	 * Mapează mai multe produse WooCommerce într-un singur payload.
	 *
	 * @param \WC_Product[] $products Lista produse (simple + variable; variațiile sunt expandate).
	 * @return array{items: array<int, array<string, mixed>>}
	 */
	public function map_products( array $products ): array {
		$groups = $this->grouper->group_products( $products );
		$items  = array();

		foreach ( $groups as $group ) {
			foreach ( $group['items'] as $adapted ) {
				$items[] = $this->map_adapted_item( $adapted );
			}
		}

		return array( 'items' => $items );
	}

	/**
	 * Convertește snapshot-ul adaptorului în structura API Trendyol.
	 *
	 * @param array<string, mixed> $data Date din Product_Adapter::extract().
	 * @return array<string, mixed>
	 */
	public function map_adapted_item( array $data ): array {
		$list_price = (float) ( $data['list_price'] ?? 0 );
		$sale_price = (float) ( $data['sale_price'] ?? 0 );

		if ( $sale_price > $list_price ) {
			$list_price = $sale_price;
		}

		$item = array(
			'barcode'           => (string) ( $data['barcode'] ?? '' ),
			'title'             => $this->truncate( (string) ( $data['title'] ?? '' ), 100 ),
			'productMainId'     => $this->truncate( (string) ( $data['product_main_id'] ?? '' ), 40 ),
			'brandId'           => (int) ( $data['brand_id'] ?? 0 ),
			'categoryId'        => (int) ( $data['category_id'] ?? 0 ),
			'quantity'          => (int) ( $data['quantity'] ?? 0 ),
			'stockCode'         => $this->truncate( (string) ( $data['stock_code'] ?? '' ), 100 ),
			'dimensionalWeight' => (float) ( $data['dimensional_weight'] ?? 0 ),
			'description'       => (string) ( $data['description'] ?? '' ),
			'listPrice'         => $list_price,
			'salePrice'         => $sale_price,
			'vatRate'           => (int) ( $data['vat_rate'] ?? 0 ),
			'images'            => $this->image_normalizer->normalize( (array) ( $data['images'] ?? array() ) ),
			'attributes'        => $this->normalize_attributes( (array) ( $data['attributes'] ?? array() ) ),
		);

		return $item;
	}

	/**
	 * Normalizează lista de atribute pentru API.
	 *
	 * @param array<int, mixed> $attributes Atribute brute din meta.
	 * @return array<int, array<string, mixed>>
	 */
	private function normalize_attributes( array $attributes ): array {
		$normalized = array();

		foreach ( $attributes as $attribute ) {
			if ( ! is_array( $attribute ) ) {
				continue;
			}

			$attribute_id = isset( $attribute['attributeId'] )
				? (int) $attribute['attributeId']
				: ( isset( $attribute['attribute_id'] ) ? (int) $attribute['attribute_id'] : 0 );

			if ( $attribute_id <= 0 ) {
				continue;
			}

			$entry = array( 'attributeId' => $attribute_id );

			if ( isset( $attribute['attributeValueId'] ) ) {
				$entry['attributeValueId'] = (int) $attribute['attributeValueId'];
			} elseif ( isset( $attribute['attribute_value_id'] ) ) {
				$entry['attributeValueId'] = (int) $attribute['attribute_value_id'];
			}

			if ( isset( $attribute['attributeValueIds'] ) && is_array( $attribute['attributeValueIds'] ) ) {
				$entry['attributeValueIds'] = array_map( 'intval', $attribute['attributeValueIds'] );
			}

			if ( isset( $attribute['customAttributeValue'] ) && '' !== (string) $attribute['customAttributeValue'] ) {
				$entry['customAttributeValue'] = (string) $attribute['customAttributeValue'];
			} elseif ( isset( $attribute['custom_attribute_value'] ) && '' !== (string) $attribute['custom_attribute_value'] ) {
				$entry['customAttributeValue'] = (string) $attribute['custom_attribute_value'];
			}

			if ( count( $entry ) > 1 ) {
				$normalized[] = $entry;
			}
		}

		return $normalized;
	}

	/**
	 * @param string $value   Text.
	 * @param int    $length  Lungime maximă.
	 * @return string
	 */
	private function truncate( string $value, int $length ): string {
		$value = trim( $value );

		if ( '' === $value ) {
			return '';
		}

		if ( function_exists( 'mb_substr' ) ) {
			return mb_substr( $value, 0, $length );
		}

		return substr( $value, 0, $length );
	}
}
