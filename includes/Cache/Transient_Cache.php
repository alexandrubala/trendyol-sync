<?php
/**
 * Cache transient pentru date statice Trendyol (categorii, branduri).
 *
 * @package TrendyolSync\Cache
 */

namespace TrendyolSync\Cache;

defined( 'ABSPATH' ) || exit;

/**
 * Class Transient_Cache
 */
class Transient_Cache {

	private const KEY_CATEGORY_TREE = 'trendyol_sync_cache_categories';

	private const TTL_CATEGORY_TREE = 7 * DAY_IN_SECONDS;
	private const TTL_BRANDS        = DAY_IN_SECONDS;

	/**
	 * Citește arborele de categorii din cache.
	 *
	 * @return array<string, mixed>|null Null dacă lipsește sau a expirat.
	 */
	public function get_category_tree(): ?array {
		$cached = get_transient( self::KEY_CATEGORY_TREE );

		return is_array( $cached ) ? $cached : null;
	}

	/**
	 * Salvează arborele de categorii (TTL 7 zile).
	 *
	 * @param array<string, mixed> $data Date API getCategoryTree.
	 * @return void
	 */
	public function set_category_tree( array $data ): void {
		set_transient( self::KEY_CATEGORY_TREE, $data, self::TTL_CATEGORY_TREE );
	}

	/**
	 * Citește o pagină de branduri din cache.
	 *
	 * @param int $page Pagină.
	 * @param int $size Mărime pagină.
	 * @return array<string, mixed>|null
	 */
	public function get_brands( int $page, int $size ): ?array {
		$cached = get_transient( $this->get_brands_key( $page, $size ) );

		return is_array( $cached ) ? $cached : null;
	}

	/**
	 * Salvează o pagină de branduri (TTL 24h).
	 *
	 * @param int                  $page Pagină.
	 * @param int                  $size Mărime pagină.
	 * @param array<string, mixed> $data Date API getBrands.
	 * @return void
	 */
	public function set_brands( int $page, int $size, array $data ): void {
		set_transient( $this->get_brands_key( $page, $size ), $data, self::TTL_BRANDS );
	}

	/**
	 * Șterge cache-ul de categorii.
	 *
	 * @return void
	 */
	public function delete_category_tree(): void {
		delete_transient( self::KEY_CATEGORY_TREE );
	}

	/**
	 * Șterge cache-ul pentru o pagină de branduri.
	 *
	 * @param int $page Pagină.
	 * @param int $size Mărime pagină.
	 * @return void
	 */
	public function delete_brands( int $page, int $size ): void {
		delete_transient( $this->get_brands_key( $page, $size ) );
	}

	/**
	 * @param int $page Pagină.
	 * @param int $size Mărime pagină.
	 * @return string
	 */
	private function get_brands_key( int $page, int $size ): string {
		return 'trendyol_sync_cache_brands_' . $page . '_' . $size;
	}
}
