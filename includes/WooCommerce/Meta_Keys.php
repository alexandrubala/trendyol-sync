<?php
/**
 * Chei post meta WooCommerce folosite de Trendyol Sync.
 *
 * @package TrendyolSync\WooCommerce
 */

namespace TrendyolSync\WooCommerce;

defined( 'ABSPATH' ) || exit;

/**
 * Class Meta_Keys
 */
final class Meta_Keys {

	public const BARCODE             = '_trendyol_barcode';
	public const BRAND_ID            = '_trendyol_brand_id';
	public const CATEGORY_ID         = '_trendyol_category_id';
	public const PRODUCT_MAIN_ID     = '_trendyol_product_main_id';
	public const VAT_RATE            = '_trendyol_vat_rate';
	public const DIMENSIONAL_WEIGHT  = '_trendyol_dimensional_weight';
	public const ATTRIBUTES          = '_trendyol_attributes';
	public const SYNC_STATUS         = '_trendyol_sync_status';

	/** Valori permise pentru {@see self::SYNC_STATUS}. */
	public const SYNC_ENABLED  = 'enabled';
	public const SYNC_DISABLED = 'disabled';
	public const SYNC_PENDING  = 'pending';
	public const SYNC_ERROR    = 'error';

	/**
	 * Toate cheile meta gestionate de plugin (pentru export / curățare).
	 *
	 * @return string[]
	 */
	public static function all(): array {
		return array(
			self::BARCODE,
			self::BRAND_ID,
			self::CATEGORY_ID,
			self::PRODUCT_MAIN_ID,
			self::VAT_RATE,
			self::DIMENSIONAL_WEIGHT,
			self::ATTRIBUTES,
			self::SYNC_STATUS,
		);
	}

	/**
	 * Citește o valoare meta scalară de pe produs.
	 *
	 * @param int    $product_id ID produs WooCommerce.
	 * @param string $key        Cheie meta (folosește constantele clasei).
	 * @return string
	 */
	public static function get_string( int $product_id, string $key ): string {
		$value = get_post_meta( $product_id, $key, true );

		return is_scalar( $value ) ? (string) $value : '';
	}

	/**
	 * Citește atributele Trendyol salvate ca JSON.
	 *
	 * @param int $product_id ID produs.
	 * @return array<int, array<string, mixed>>
	 */
	public static function get_attributes( int $product_id ): array {
		$raw = get_post_meta( $product_id, self::ATTRIBUTES, true );

		if ( is_array( $raw ) ) {
			return $raw;
		}

		if ( is_string( $raw ) && '' !== $raw ) {
			$decoded = json_decode( $raw, true );

			return is_array( $decoded ) ? $decoded : array();
		}

		return array();
	}

	/**
	 * Persistă atributele Trendyol (array → JSON în meta).
	 *
	 * @param int                      $product_id ID produs.
	 * @param array<int, array<string, mixed>> $attributes Listă atribute API.
	 * @return void
	 */
	public static function set_attributes( int $product_id, array $attributes ): void {
		update_post_meta( $product_id, self::ATTRIBUTES, wp_json_encode( $attributes ) );
	}

	/**
	 * Verifică dacă sincronizarea este activată pentru produs.
	 *
	 * @param int $product_id ID produs.
	 * @return bool
	 */
	public static function is_sync_enabled( int $product_id ): bool {
		return self::SYNC_ENABLED === self::get_string( $product_id, self::SYNC_STATUS );
	}
}
