<?php
/**
 * Rezolvă maparea WooCommerce category -> Trendyol category/brand.
 *
 * @package TrendyolSync\Admin
 */

namespace TrendyolSync\Admin;

use TrendyolSync\WooCommerce\Meta_Keys;

defined( 'ABSPATH' ) || exit;

/**
 * Class Category_Mapper
 */
class Category_Mapper {

	private const OPTION_CATEGORY_MAP = 'trendyol_sync_category_map';
	private const OPTION_BRAND_MAP    = 'trendyol_sync_brand_map';

	/**
	 * @return array<int, int>
	 */
	public function get_category_map(): array {
		return $this->normalize_term_map( get_option( self::OPTION_CATEGORY_MAP, array() ) );
	}

	/**
	 * @param array<int|string, int|string> $map Mapping wc term -> trendyol category.
	 * @return void
	 */
	public function save_category_map( array $map ): void {
		update_option( self::OPTION_CATEGORY_MAP, $this->normalize_term_map( $map ) );
	}

	/**
	 * @return array<int, int>
	 */
	public function get_brand_map(): array {
		return $this->normalize_term_map( get_option( self::OPTION_BRAND_MAP, array() ) );
	}

	/**
	 * @param array<int|string, int|string> $map Mapping wc term -> trendyol brand.
	 * @return void
	 */
	public function save_brand_map( array $map ): void {
		update_option( self::OPTION_BRAND_MAP, $this->normalize_term_map( $map ) );
	}

	/**
	 * @param \WC_Product $product Produs WooCommerce.
	 * @return int
	 */
	public function resolve_category_for_product( \WC_Product $product ): int {
		$explicit = (int) Meta_Keys::get_string( $product->get_id(), Meta_Keys::CATEGORY_ID );

		if ( $explicit > 0 ) {
			return $explicit;
		}

		$resolved = $this->resolve_from_term_map( $product, $this->get_category_map() );

		if ( $resolved > 0 ) {
			return $resolved;
		}

		$settings = trendyol_sync()->settings()->get_stored_settings();

		return isset( $settings['default_trendyol_category_id'] )
			? absint( $settings['default_trendyol_category_id'] )
			: 0;
	}

	/**
	 * @param \WC_Product $product Produs WooCommerce.
	 * @return int
	 */
	public function resolve_brand_for_product( \WC_Product $product ): int {
		$explicit = (int) Meta_Keys::get_string( $product->get_id(), Meta_Keys::BRAND_ID );

		if ( $explicit > 0 ) {
			return $explicit;
		}

		$resolved = $this->resolve_from_term_map( $product, $this->get_brand_map() );

		if ( $resolved > 0 ) {
			return $resolved;
		}

		$settings = trendyol_sync()->settings()->get_stored_settings();

		return isset( $settings['default_trendyol_brand_id'] )
			? absint( $settings['default_trendyol_brand_id'] )
			: 0;
	}

	/**
	 * Aplică maparea pe produsele existente care nu au categorie/brand explicit.
	 *
	 * @return array{updated_categories: int, updated_brands: int, touched_products: int}
	 */
	public function apply_to_existing_products(): array {
		if ( ! function_exists( 'wc_get_products' ) ) {
			return array(
				'updated_categories' => 0,
				'updated_brands'     => 0,
				'touched_products'   => 0,
			);
		}

		$products = wc_get_products(
			array(
				'type'   => array( 'simple', 'variable' ),
				'status' => array( 'publish', 'draft', 'private' ),
				'limit'  => -1,
				'return' => 'objects',
			)
		);

		$updated_categories = 0;
		$updated_brands     = 0;
		$touched_products   = 0;

		foreach ( $products as $product ) {
			if ( ! $product instanceof \WC_Product ) {
				continue;
			}

			$product_id = $product->get_id();
			$has_change = false;

			if ( '' === Meta_Keys::get_string( $product_id, Meta_Keys::CATEGORY_ID ) ) {
				$category = $this->resolve_category_for_product( $product );
				if ( $category > 0 ) {
					update_post_meta( $product_id, Meta_Keys::CATEGORY_ID, (string) $category );
					++$updated_categories;
					$has_change = true;
				}
			}

			if ( '' === Meta_Keys::get_string( $product_id, Meta_Keys::BRAND_ID ) ) {
				$brand = $this->resolve_brand_for_product( $product );
				if ( $brand > 0 ) {
					update_post_meta( $product_id, Meta_Keys::BRAND_ID, (string) $brand );
					++$updated_brands;
					$has_change = true;
				}
			}

			if ( $has_change ) {
				++$touched_products;
			}
		}

		return array(
			'updated_categories' => $updated_categories,
			'updated_brands'     => $updated_brands,
			'touched_products'   => $touched_products,
		);
	}

	/**
	 * @param mixed $raw Raw option value.
	 * @return array<int, int>
	 */
	private function normalize_term_map( $raw ): array {
		if ( ! is_array( $raw ) ) {
			return array();
		}

		$normalized = array();

		foreach ( $raw as $term_id => $mapped_id ) {
			$term_id   = absint( $term_id );
			$mapped_id = absint( $mapped_id );

			if ( $term_id > 0 && $mapped_id > 0 ) {
				$normalized[ $term_id ] = $mapped_id;
			}
		}

		return $normalized;
	}

	/**
	 * @param \WC_Product    $product Produs WooCommerce.
	 * @param array<int, int> $map    Mapping term -> value.
	 * @return int
	 */
	private function resolve_from_term_map( \WC_Product $product, array $map ): int {
		if ( empty( $map ) ) {
			return 0;
		}

		$term_ids = array_filter( array_map( 'absint', $product->get_category_ids() ) );

		if ( empty( $term_ids ) ) {
			return 0;
		}

		$terms = get_terms(
			array(
				'taxonomy'   => 'product_cat',
				'include'    => $term_ids,
				'hide_empty' => false,
			)
		);

		if ( ! is_array( $terms ) || empty( $terms ) ) {
			return 0;
		}

		usort(
			$terms,
			static function ( $a, $b ) {
				$a_depth = count( get_ancestors( (int) $a->term_id, 'product_cat', 'taxonomy' ) );
				$b_depth = count( get_ancestors( (int) $b->term_id, 'product_cat', 'taxonomy' ) );

				return $b_depth <=> $a_depth;
			}
		);

		foreach ( $terms as $term ) {
			$current_id = (int) $term->term_id;

			if ( isset( $map[ $current_id ] ) ) {
				return (int) $map[ $current_id ];
			}

			$ancestors = get_ancestors( $current_id, 'product_cat', 'taxonomy' );
			foreach ( $ancestors as $ancestor_id ) {
				$ancestor_id = absint( $ancestor_id );
				if ( isset( $map[ $ancestor_id ] ) ) {
					return (int) $map[ $ancestor_id ];
				}
			}
		}

		return 0;
	}
}
