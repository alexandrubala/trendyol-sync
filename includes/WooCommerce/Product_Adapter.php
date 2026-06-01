<?php
/**
 * Extrage date brute din obiectul WooCommerce Product (simple sau variație).
 *
 * @package TrendyolSync\WooCommerce
 */

namespace TrendyolSync\WooCommerce;

use TrendyolSync\API\Vat_Rates;

use TrendyolSync\Admin\Category_Mapper;
use TrendyolSync\Sync\Barcode_Resolver;

defined( 'ABSPATH' ) || exit;

/**
 * Class Product_Adapter
 */
class Product_Adapter {

	/**
	 * @var Category_Mapper
	 */
	private $category_mapper;

	/**
	 * @var Barcode_Resolver
	 */
	private $barcode_resolver;

	/**
	 * @param Category_Mapper|null  $category_mapper Mapper categorie/brand.
	 * @param Barcode_Resolver|null $barcode_resolver Resolver barcode.
	 */
	public function __construct( ?Category_Mapper $category_mapper = null, ?Barcode_Resolver $barcode_resolver = null ) {
		$this->category_mapper  = $category_mapper ?? new Category_Mapper();
		$this->barcode_resolver = $barcode_resolver ?? new Barcode_Resolver();
	}

	/**
	 * Extrage un snapshot normalizat al produsului pentru mapare Trendyol.
	 *
	 * @param \WC_Product $product Produs sau variație WooCommerce.
	 * @return array<string, mixed>
	 */
	public function extract( \WC_Product $product ): array {
		$product_id = $product->get_id();
		$parent_id  = $product->get_parent_id();
		$is_variation = $product->is_type( 'variation' );

		$meta_source_id = $is_variation && $parent_id > 0 ? $parent_id : $product_id;

		$brand_id    = $this->resolve_brand_id( $product, $meta_source_id );
		$category_id = $this->resolve_category_id( $product, $meta_source_id );

		return array(
			'product_id'          => $product_id,
			'parent_id'           => $parent_id,
			'is_variation'        => $is_variation,
			'product_type'        => $product->get_type(),
			'title'               => $this->get_title( $product ),
			'stock_code'          => $this->get_stock_code( $product ),
			'list_price'          => $this->get_list_price( $product ),
			'sale_price'          => $this->get_sale_price( $product ),
			'quantity'            => $this->get_quantity( $product ),
			'description'         => $this->get_description( $product ),
			'images'              => $this->get_image_urls( $product ),
			'barcode'             => $this->get_barcode( $product, $meta_source_id ),
			'brand_id'            => $brand_id,
			'category_id'         => $category_id,
			'product_main_id'     => Meta_Keys::get_string( $product_id, Meta_Keys::PRODUCT_MAIN_ID ),
			'vat_rate'            => $this->get_vat_rate( $product_id, $meta_source_id ),
			'dimensional_weight'  => $this->get_dimensional_weight( $product, $product_id, $meta_source_id ),
			'attributes'          => $this->get_attributes( $product_id, $product, $category_id ),
			'sync_enabled'        => Meta_Keys::is_sync_enabled( $meta_source_id ),
			'sync_status'         => Meta_Keys::get_string( $meta_source_id, Meta_Keys::SYNC_STATUS ),
		);
	}

	/**
	 * Titlul afișat: variația moștenește titlul părintelui dacă lipsește.
	 *
	 * @param \WC_Product $product Produs.
	 * @return string
	 */
	private function get_title( \WC_Product $product ): string {
		$title = $product->get_name();

		if ( '' !== $title ) {
			return $title;
		}

		if ( $product->is_type( 'variation' ) && $product->get_parent_id() > 0 ) {
			$parent = wc_get_product( $product->get_parent_id() );

			if ( $parent instanceof \WC_Product ) {
				return $parent->get_name();
			}
		}

		return '';
	}

	/**
	 * SKU folosit ca stockCode Trendyol.
	 *
	 * @param \WC_Product $product Produs.
	 * @return string
	 */
	private function get_stock_code( \WC_Product $product ): string {
		$sku = $product->get_sku();

		return is_string( $sku ) ? trim( $sku ) : '';
	}

	/**
	 * Preț întreg (regular price), rotunjit la 2 zecimale.
	 *
	 * @param \WC_Product $product Produs.
	 * @return float
	 */
	private function get_list_price( \WC_Product $product ): float {
		$price = $product->get_regular_price();

		if ( '' === $price || null === $price ) {
			$price = $product->get_price();
		}

		return $this->to_float( $price );
	}

	/**
	 * Preț redus; dacă lipsește, folosește prețul curent (sale sau regular).
	 *
	 * @param \WC_Product $product Produs.
	 * @return float
	 */
	private function get_sale_price( \WC_Product $product ): float {
		$sale = $product->get_sale_price();

		if ( '' !== $sale && null !== $sale ) {
			return $this->to_float( $sale );
		}

		return $this->to_float( $product->get_price() );
	}

	/**
	 * Stoc disponibil (întreg, minim 0).
	 *
	 * @param \WC_Product $product Produs.
	 * @return int
	 */
	private function get_quantity( \WC_Product $product ): int {
		if ( ! $product->managing_stock() && ! $product->is_type( 'variation' ) ) {
			return $product->is_in_stock() ? 1 : 0;
		}

		$qty = $product->get_stock_quantity();

		if ( null === $qty ) {
			return $product->is_in_stock() ? 1 : 0;
		}

		return max( 0, (int) $qty );
	}

	/**
	 * Descriere HTML (lungă, apoi scurtă).
	 *
	 * @param \WC_Product $product Produs.
	 * @return string
	 */
	private function get_description( \WC_Product $product ): string {
		$description = $product->get_description();

		if ( '' === $description ) {
			$description = $product->get_short_description();
		}

		if ( $product->is_type( 'variation' ) && '' === $description && $product->get_parent_id() > 0 ) {
			$parent = wc_get_product( $product->get_parent_id() );

			if ( $parent instanceof \WC_Product ) {
				$description = $parent->get_description();

				if ( '' === $description ) {
					$description = $parent->get_short_description();
				}
			}
		}

		return is_string( $description ) ? trim( $description ) : '';
	}

	/**
	 * URL-uri imagine: featured + galerie (fără duplicate).
	 *
	 * @param \WC_Product $product Produs.
	 * @return string[]
	 */
	private function get_image_urls( \WC_Product $product ): array {
		$urls  = array();
		$seen  = array();

		$image_id = $product->get_image_id();

		if ( ! $image_id && $product->is_type( 'variation' ) && $product->get_parent_id() > 0 ) {
			$parent = wc_get_product( $product->get_parent_id() );

			if ( $parent instanceof \WC_Product ) {
				$image_id = $parent->get_image_id();
			}
		}

		if ( $image_id ) {
			$url = wp_get_attachment_url( (int) $image_id );

			if ( $url ) {
				$urls[]       = $url;
				$seen[ $url ] = true;
			}
		}

		$gallery_ids = $product->get_gallery_image_ids();

		if ( empty( $gallery_ids ) && $product->is_type( 'variation' ) && $product->get_parent_id() > 0 ) {
			$parent = wc_get_product( $product->get_parent_id() );

			if ( $parent instanceof \WC_Product ) {
				$gallery_ids = $parent->get_gallery_image_ids();
			}
		}

		foreach ( $gallery_ids as $attachment_id ) {
			$url = wp_get_attachment_url( (int) $attachment_id );

			if ( $url && ! isset( $seen[ $url ] ) ) {
				$urls[]       = $url;
				$seen[ $url ] = true;
			}
		}

		return $urls;
	}

	/**
	 * Barcode: pe variație propriu, altfel moștenire de la părinte.
	 *
	 * @param int $product_id     ID produs/variație.
	 * @param int $meta_source_id ID părinte pentru fallback.
	 * @return string
	 */
	private function get_barcode( \WC_Product $product, int $meta_source_id ): string {
		$barcode = $this->barcode_resolver->resolve_for_product( $product );

		if ( '' === $barcode && $meta_source_id > 0 && $meta_source_id !== $product->get_id() ) {
			$barcode = Meta_Keys::get_string( $meta_source_id, Meta_Keys::BARCODE );
		}

		return $barcode;
	}

	/**
	 * Cota TVA din meta (produs, apoi părinte).
	 *
	 * @param int $product_id      ID produs/variație.
	 * @param int $meta_source_id  ID unde sunt meta-urile partajate.
	 * @return int
	 */
	private function get_vat_rate( int $product_id, int $meta_source_id ): int {
		$vat_rates = Vat_Rates::for_site();
		$rate      = Meta_Keys::get_string( $product_id, Meta_Keys::VAT_RATE );

		if ( '' === $rate ) {
			$rate = Meta_Keys::get_string( $meta_source_id, Meta_Keys::VAT_RATE );
		}

		if ( '' !== $rate ) {
			return $vat_rates->sanitize( (int) $rate );
		}

		$product = wc_get_product( $product_id );
		if ( $product instanceof \WC_Product ) {
			$tax_class = (string) $product->get_tax_class();
			$tax_map   = get_option( 'trendyol_sync_tax_class_map', array() );
			if ( is_array( $tax_map ) && isset( $tax_map[ $tax_class ] ) && is_numeric( $tax_map[ $tax_class ] ) ) {
				return $vat_rates->sanitize( (int) $tax_map[ $tax_class ] );
			}
			if ( is_array( $tax_map ) && '' === $tax_class && isset( $tax_map['standard'] ) && is_numeric( $tax_map['standard'] ) ) {
				return $vat_rates->sanitize( (int) $tax_map['standard'] );
			}
		}

		$settings = trendyol_sync()->settings()->get_stored_settings();
		$stored   = isset( $settings['default_vat_rate'] ) ? (int) $settings['default_vat_rate'] : $vat_rates->get_default_rate();

		return $vat_rates->sanitize( $stored );
	}

	/**
	 * Greutate dimensională din meta.
	 *
	 * @param int $product_id     ID produs.
	 * @param int $meta_source_id ID meta partajată.
	 * @return float
	 */
	private function get_dimensional_weight( \WC_Product $product, int $product_id, int $meta_source_id ): float {
		$weight = Meta_Keys::get_string( $product_id, Meta_Keys::DIMENSIONAL_WEIGHT );

		if ( '' === $weight ) {
			$weight = Meta_Keys::get_string( $meta_source_id, Meta_Keys::DIMENSIONAL_WEIGHT );
		}

		if ( '' !== $weight ) {
			return $this->to_float( $weight );
		}

		$length = $this->to_float( (string) $product->get_length() );
		$width  = $this->to_float( (string) $product->get_width() );
		$height = $this->to_float( (string) $product->get_height() );

		if ( $length > 0 && $width > 0 && $height > 0 ) {
			return round( ( $length * $width * $height ) / 3000, 2 );
		}

		$wc_weight = $this->to_float( (string) $product->get_weight() );
		if ( $wc_weight > 0 ) {
			return $wc_weight;
		}

		$settings = trendyol_sync()->settings()->get_stored_settings();

		if ( isset( $settings['default_dimensional_weight'] ) && is_numeric( $settings['default_dimensional_weight'] ) ) {
			return round( (float) $settings['default_dimensional_weight'], 2 );
		}

		return 1.0;
	}

	/**
	 * @param \WC_Product $product Produs.
	 * @param int         $meta_source_id Parent/meta source.
	 * @return int
	 */
	private function resolve_brand_id( \WC_Product $product, int $meta_source_id ): int {
		$brand_id = (int) Meta_Keys::get_string( $meta_source_id, Meta_Keys::BRAND_ID );

		if ( $brand_id > 0 ) {
			return $brand_id;
		}

		return $this->category_mapper->resolve_brand_for_product( $product );
	}

	/**
	 * @param \WC_Product $product Produs.
	 * @param int         $meta_source_id Parent/meta source.
	 * @return int
	 */
	private function resolve_category_id( \WC_Product $product, int $meta_source_id ): int {
		$category_id = (int) Meta_Keys::get_string( $meta_source_id, Meta_Keys::CATEGORY_ID );

		if ( $category_id > 0 ) {
			return $category_id;
		}

		return $this->category_mapper->resolve_category_for_product( $product );
	}

	/**
	 * @param int         $product_id ID produs.
	 * @param \WC_Product $product Produs curent.
	 * @param int         $category_id Categorie Trendyol.
	 * @return array<int, array<string, mixed>>
	 */
	private function get_attributes( int $product_id, \WC_Product $product, int $category_id ): array {
		$attributes = Meta_Keys::get_attributes( $product_id );

		if ( ! empty( $attributes ) ) {
			return $attributes;
		}

		$defaults = get_option( 'trendyol_sync_category_attribute_defaults', array() );

		if ( ! is_array( $defaults ) || $category_id <= 0 || empty( $defaults[ $category_id ] ) || ! is_array( $defaults[ $category_id ] ) ) {
			return array();
		}

		$resolved = array();

		foreach ( $defaults[ $category_id ] as $attribute_id => $value ) {
			$attribute_id = absint( $attribute_id );
			if ( $attribute_id <= 0 ) {
				continue;
			}

			if ( is_numeric( $value ) ) {
				$resolved[] = array(
					'attributeId'      => $attribute_id,
					'attributeValueId' => (int) $value,
				);
				continue;
			}

			$value = is_scalar( $value ) ? trim( (string) $value ) : '';

			if ( '' !== $value ) {
				$resolved[] = array(
					'attributeId'          => $attribute_id,
					'customAttributeValue' => $value,
				);
			}
		}

		$wc_map = get_option( 'trendyol_sync_wc_attribute_map', array() );

		if ( is_array( $wc_map ) && ! empty( $wc_map ) ) {
			$product_attributes = $product->get_attributes();
			foreach ( $product_attributes as $taxonomy => $raw_value ) {
				$key = wc_sanitize_taxonomy_name( (string) $taxonomy );
				if ( empty( $wc_map[ $key ] ) || ! is_array( $wc_map[ $key ] ) ) {
					continue;
				}

				$map_config = $wc_map[ $key ];
				$attribute_id = isset( $map_config['attribute_id'] ) ? absint( $map_config['attribute_id'] ) : 0;
				if ( $attribute_id <= 0 ) {
					continue;
				}

				$value_map = isset( $map_config['values'] ) && is_array( $map_config['values'] ) ? $map_config['values'] : array();
				$allow_custom = ! empty( $map_config['allow_custom'] );
				$resolved_values = array();

				if ( is_string( $raw_value ) ) {
					$resolved_values[] = $raw_value;
				} elseif ( is_array( $raw_value ) ) {
					$resolved_values = array_map( 'strval', $raw_value );
				} elseif ( $raw_value instanceof \WC_Product_Attribute ) {
					if ( $raw_value->is_taxonomy() ) {
						$terms = wc_get_product_terms( $product_id, $raw_value->get_name(), array( 'fields' => 'slugs' ) );
						$resolved_values = is_array( $terms ) ? array_map( 'strval', $terms ) : array();
					} else {
						$resolved_values = array_map( 'strval', $raw_value->get_options() );
					}
				}

				foreach ( $resolved_values as $value_slug ) {
					$value_slug = trim( (string) $value_slug );
					if ( '' === $value_slug ) {
						continue;
					}
					if ( isset( $value_map[ $value_slug ] ) && is_numeric( $value_map[ $value_slug ] ) ) {
						$resolved[] = array(
							'attributeId'      => $attribute_id,
							'attributeValueId' => (int) $value_map[ $value_slug ],
						);
						continue;
					}
					if ( $allow_custom ) {
						$resolved[] = array(
							'attributeId'          => $attribute_id,
							'customAttributeValue' => $value_slug,
						);
					}
				}
			}
		}

		if ( ! empty( $resolved ) ) {
			$resolved = array_values(
				array_map(
					'array_filter',
					$resolved
				)
			);
			Meta_Keys::set_attributes( $product_id, $resolved );
		}

		return $resolved;
	}

	/**
	 * @param mixed $value Valoare preț.
	 * @return float
	 */
	private function to_float( $value ): float {
		if ( is_numeric( $value ) ) {
			return round( (float) $value, 2 );
		}

		return 0.0;
	}
}
