<?php
/**
 * Cache transient pentru date statice Trendyol (categorii, branduri).
 *
 * @package TrendyolSync\Cache
 */

namespace TrendyolSync\Cache;

use TrendyolSync\API\Market_Context;

defined( 'ABSPATH' ) || exit;

/**
 * Class Transient_Cache
 */
class Transient_Cache {

	private const KEY_CATEGORY_TREE   = 'trendyol_sync_cache_categories';
	private const KEY_BRAND_OPTIONS   = 'trendyol_sync_cache_brand_options';
	private const KEY_CATEGORY_OPTIONS = 'trendyol_sync_cache_category_options';
	private const KEY_CATEGORY_ATTRIBUTES = 'trendyol_sync_cache_category_attributes';

	private const TTL_CATEGORY_TREE = 7 * DAY_IN_SECONDS;
	private const TTL_BRANDS        = DAY_IN_SECONDS;
	private const TTL_OPTIONS       = 7 * DAY_IN_SECONDS;
	private const TTL_CATEGORY_ATTRIBUTES = 7 * DAY_IN_SECONDS;

	/**
	 * @var Market_Context
	 */
	private $market;

	/**
	 * @var string
	 */
	private $suffix;

	/**
	 * @param Market_Context|null $market Context piață (implicit: detectat din site).
	 */
	public function __construct( ?Market_Context $market = null ) {
		$this->market = $market ?? Market_Context::for_site();
		$this->suffix = $this->market->is_supported()
			? $this->market->get_cache_suffix()
			: 'unsupported';
	}

	/**
	 * @return Market_Context
	 */
	public function market(): Market_Context {
		return $this->market;
	}

	/**
	 * Citește arborele de categorii din cache.
	 *
	 * @return array<string, mixed>|null Null dacă lipsește sau a expirat.
	 */
	public function get_category_tree(): ?array {
		$cached = get_transient( $this->scoped_key( self::KEY_CATEGORY_TREE ) );

		return is_array( $cached ) ? $cached : null;
	}

	/**
	 * Salvează arborele de categorii (TTL 7 zile).
	 *
	 * @param array<string, mixed> $data Date API getCategoryTree.
	 * @return void
	 */
	public function set_category_tree( array $data ): void {
		set_transient( $this->scoped_key( self::KEY_CATEGORY_TREE ), $data, self::TTL_CATEGORY_TREE );
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
	 * Opțiuni brand pre-procesate [ id => name ].
	 *
	 * @return array<int, string>|null
	 */
	public function get_brand_options(): ?array {
		$cached = get_transient( $this->scoped_key( self::KEY_BRAND_OPTIONS ) );

		return is_array( $cached ) ? $cached : null;
	}

	/**
	 * @param array<int, string> $options Opțiuni brand.
	 * @return void
	 */
	public function set_brand_options( array $options ): void {
		set_transient( $this->scoped_key( self::KEY_BRAND_OPTIONS ), $options, self::TTL_OPTIONS );
	}

	/**
	 * Opțiuni categorie pre-procesate [ id => label ].
	 *
	 * @return array<int, string>|null
	 */
	public function get_category_options(): ?array {
		$cached = get_transient( $this->scoped_key( self::KEY_CATEGORY_OPTIONS ) );

		return is_array( $cached ) ? $cached : null;
	}

	/**
	 * @param array<int, string> $options Opțiuni categorie.
	 * @return void
	 */
	public function set_category_options( array $options ): void {
		set_transient( $this->scoped_key( self::KEY_CATEGORY_OPTIONS ), $options, self::TTL_OPTIONS );
	}

	/**
	 * Șterge cache-ul de categorii și opțiunile flatten pentru piața curentă.
	 *
	 * @return void
	 */
	public function delete_category_tree(): void {
		delete_transient( $this->scoped_key( self::KEY_CATEGORY_TREE ) );
		delete_transient( $this->scoped_key( self::KEY_CATEGORY_OPTIONS ) );
	}

	/**
	 * Șterge cache-ul branduri (toate paginile cunoscute) și opțiunile flatten.
	 *
	 * @return void
	 */
	public function delete_all_brands(): void {
		delete_transient( $this->scoped_key( self::KEY_BRAND_OPTIONS ) );

		for ( $page = 0; $page < 50; ++$page ) {
			delete_transient( $this->get_brands_key( $page, 1000 ) );
		}
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
	 * @param int $category_id ID categorie Trendyol.
	 * @return array<string, mixed>|null
	 */
	public function get_category_attributes( int $category_id ): ?array {
		$cached = get_transient( $this->scoped_key( self::KEY_CATEGORY_ATTRIBUTES . '_' . $category_id ) );

		return is_array( $cached ) ? $cached : null;
	}

	/**
	 * @param int                  $category_id ID categorie Trendyol.
	 * @param array<string, mixed> $data Atribute categorie.
	 * @return void
	 */
	public function set_category_attributes( int $category_id, array $data ): void {
		if ( $category_id <= 0 ) {
			return;
		}

		set_transient(
			$this->scoped_key( self::KEY_CATEGORY_ATTRIBUTES . '_' . $category_id ),
			$data,
			self::TTL_CATEGORY_ATTRIBUTES
		);
	}

	/**
	 * @param int $page Pagină.
	 * @param int $size Mărime pagină.
	 * @return string
	 */
	private function get_brands_key( int $page, int $size ): string {
		return $this->scoped_key( 'trendyol_sync_cache_brands_' . $page . '_' . $size );
	}

	/**
	 * @param string $base Cheie de bază.
	 * @return string
	 */
	private function scoped_key( string $base ): string {
		return $base . '_' . $this->suffix;
	}
}
