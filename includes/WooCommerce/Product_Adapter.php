<?php
/**
 * Extrage date brute din obiectul WooCommerce Product (simple sau variație).
 *
 * @package TrendyolSync\WooCommerce
 */

namespace TrendyolSync\WooCommerce;

defined( 'ABSPATH' ) || exit;

/**
 * Class Product_Adapter
 */
class Product_Adapter {

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
			'barcode'             => $this->get_barcode( $product_id, $meta_source_id ),
			'brand_id'            => (int) Meta_Keys::get_string( $meta_source_id, Meta_Keys::BRAND_ID ),
			'category_id'         => (int) Meta_Keys::get_string( $meta_source_id, Meta_Keys::CATEGORY_ID ),
			'product_main_id'     => Meta_Keys::get_string( $product_id, Meta_Keys::PRODUCT_MAIN_ID ),
			'vat_rate'            => $this->get_vat_rate( $product_id, $meta_source_id ),
			'dimensional_weight'  => $this->get_dimensional_weight( $product_id, $meta_source_id ),
			'attributes'          => Meta_Keys::get_attributes( $product_id ),
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
	private function get_barcode( int $product_id, int $meta_source_id ): string {
		$barcode = Meta_Keys::get_string( $product_id, Meta_Keys::BARCODE );

		if ( '' === $barcode && $meta_source_id !== $product_id ) {
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
		$rate = Meta_Keys::get_string( $product_id, Meta_Keys::VAT_RATE );

		if ( '' === $rate ) {
			$rate = Meta_Keys::get_string( $meta_source_id, Meta_Keys::VAT_RATE );
		}

		return '' !== $rate ? (int) $rate : 0;
	}

	/**
	 * Greutate dimensională din meta.
	 *
	 * @param int $product_id     ID produs.
	 * @param int $meta_source_id ID meta partajată.
	 * @return float
	 */
	private function get_dimensional_weight( int $product_id, int $meta_source_id ): float {
		$weight = Meta_Keys::get_string( $product_id, Meta_Keys::DIMENSIONAL_WEIGHT );

		if ( '' === $weight ) {
			$weight = Meta_Keys::get_string( $meta_source_id, Meta_Keys::DIMENSIONAL_WEIGHT );
		}

		return '' !== $weight ? $this->to_float( $weight ) : 0.0;
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
