<?php
/**
 * Handler AJAX „Sincronizează catalog” – branduri și categorii Trendyol în cache.
 *
 * @package TrendyolSync\Admin
 */

namespace TrendyolSync\Admin;

use TrendyolSync\API\Auth;
use TrendyolSync\API\Endpoints\Brands;
use TrendyolSync\API\Endpoints\Categories;
use TrendyolSync\API\Market_Context;

defined( 'ABSPATH' ) || exit;

/**
 * Class Catalog_Syncer
 */
class Catalog_Syncer {

	public const AJAX_ACTION  = 'trendyol_sync_catalog';
	public const NONCE_ACTION = 'trendyol_sync_catalog';

	private const BRAND_PAGE_SIZE = 1000;
	private const MAX_BRAND_PAGES = 50;

	/**
	 * @var Settings
	 */
	private $settings;

	/**
	 * @var Catalog_Options
	 */
	private $catalog;

	/**
	 * @param Settings|null        $settings Handler setări.
	 * @param Catalog_Options|null $catalog  Opțiuni brand/categorie.
	 */
	public function __construct( ?Settings $settings = null, ?Catalog_Options $catalog = null ) {
		$this->settings = $settings ?? trendyol_sync()->settings();
		$this->catalog  = $catalog ?? new Catalog_Options();
	}

	/**
	 * Înregistrează hook-ul AJAX admin.
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		add_action( 'wp_ajax_' . self::AJAX_ACTION, array( $this, 'handle' ) );
	}

	/**
	 * Procesează cererea AJAX de sincronizare catalog.
	 *
	 * @return void
	 */
	public function handle(): void {
		check_ajax_referer( self::NONCE_ACTION, 'nonce' );

		if ( ! current_user_can( TRENDYOL_SYNC_CAPABILITY ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'Nu ai permisiunea de a sincroniza catalogul.', 'trendyol-sync-for-woocommerce' ),
				),
				403
			);
		}

		if ( ! $this->settings->has_credentials() ) {
			wp_send_json_error(
				array(
					'message' => __( 'Completează Supplier ID, API Key și API Secret înainte de sincronizare.', 'trendyol-sync-for-woocommerce' ),
				)
			);
		}

		if ( ! ( new Auth( $this->settings ) )->can_authenticate() ) {
			wp_send_json_error(
				array(
					'message' => __( 'OpenSSL este necesar pentru decriptarea credențialelor.', 'trendyol-sync-for-woocommerce' ),
				)
			);
		}

		$result = $this->sync();

		if ( ! empty( $result['success'] ) ) {
			wp_send_json_success( $result );
		}

		wp_send_json_error(
			array(
				'message'     => (string) ( $result['message'] ?? __( 'Sincronizarea catalogului a eșuat.', 'trendyol-sync-for-woocommerce' ) ),
				'status_code' => (int) ( $result['status_code'] ?? 0 ),
				'error_type'  => (string) ( $result['error_type'] ?? 'http' ),
			)
		);
	}

	/**
	 * Descarcă brandurile (paginat) și arborele de categorii în cache.
	 *
	 * @return array<string, mixed>
	 */
	public function sync(): array {
		$market = Market_Context::for_site();

		if ( ! $market->is_supported() ) {
			return array(
				'success' => false,
				'message' => __(
					'Piața Trendyol nu a putut fi detectată din setările site-ului. Setează țara magazinului WooCommerce (ex. România) sau limba site-ului (ex. română) înainte de sincronizare.',
					'trendyol-sync'
				),
			);
		}

		$plugin = trendyol_sync();
		$cache  = $plugin->cache();

		$cache->delete_all_brands();
		$cache->delete_category_tree();

		$brands = new Brands( $plugin->api_client(), $cache );

		$brand_pages = 0;

		for ( $page = 0; $page < self::MAX_BRAND_PAGES; ++$page ) {
			$response = $brands->get_brands( $page, self::BRAND_PAGE_SIZE, false );

			if ( ! $response['success'] ) {
				return array(
					'success'     => false,
					'message'     => (string) ( $response['error'] ?? __( 'Eroare la descărcarea brandurilor.', 'trendyol-sync-for-woocommerce' ) ),
					'status_code' => (int) ( $response['status_code'] ?? 0 ),
					'error_type'  => (string) ( $response['error_type'] ?? 'http' ),
				);
			}

			$list = $this->extract_brands_list( is_array( $response['data'] ?? null ) ? $response['data'] : array() );

			if ( empty( $list ) ) {
				break;
			}

			++$brand_pages;

			if ( count( $list ) < self::BRAND_PAGE_SIZE ) {
				break;
			}
		}

		$categories = new Categories( $plugin->api_client(), $cache );
		$cat_result = $categories->get_category_tree( false );

		if ( ! $cat_result['success'] ) {
			return array(
				'success'     => false,
				'message'     => (string) ( $cat_result['error'] ?? __( 'Eroare la descărcarea categoriilor.', 'trendyol-sync-for-woocommerce' ) ),
				'status_code' => (int) ( $cat_result['status_code'] ?? 0 ),
				'error_type'  => (string) ( $cat_result['error_type'] ?? 'http' ),
			);
		}

		$counts = $this->catalog->rebuild_option_caches();

		return array(
			'success'        => true,
			'message'        => sprintf(
				/* translators: 1: market label, 2: brand count, 3: category count */
				__( 'Catalog sincronizat pentru %1$s: %2$d branduri, %3$d categorii.', 'trendyol-sync-for-woocommerce' ),
				$market->get_label(),
				$counts['brand_count'],
				$counts['category_count']
			),
			'brand_count'    => $counts['brand_count'],
			'category_count' => $counts['category_count'],
			'brand_pages'    => $brand_pages,
			'market'         => $market->get_label(),
			'storefront'     => $market->get_storefront_code(),
			'language'       => $market->get_accept_language(),
		);
	}

	/**
	 * @param array<string, mixed>|array<int, mixed> $data Date API.
	 * @return array<int, array<string, mixed>>
	 */
	private function extract_brands_list( array $data ): array {
		if ( isset( $data['brands'] ) && is_array( $data['brands'] ) ) {
			return $data['brands'];
		}

		if ( $this->is_list( $data ) ) {
			return $data;
		}

		return array();
	}

	/**
	 * @param array<mixed> $data Date.
	 * @return bool
	 */
	private function is_list( array $data ): bool {
		if ( function_exists( 'array_is_list' ) ) {
			return array_is_list( $data );
		}

		return array_keys( $data ) === range( 0, count( $data ) - 1 );
	}
}
