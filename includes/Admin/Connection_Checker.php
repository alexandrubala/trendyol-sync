<?php
/**
 * Handler AJAX „Check API Status” – test conexiune Trendyol.
 *
 * @package TrendyolSync\Admin
 */

namespace TrendyolSync\Admin;

use TrendyolSync\API\Auth;
use TrendyolSync\API\Endpoints\Brands;
use TrendyolSync\API\Environment;

defined( 'ABSPATH' ) || exit;

/**
 * Class Connection_Checker
 */
class Connection_Checker {

	public const AJAX_ACTION = 'trendyol_check_connection';
	public const NONCE_ACTION = 'trendyol_check_connection';

	/**
	 * @var Settings
	 */
	private $settings;

	/**
	 * @param Settings $settings Handler setări.
	 */
	public function __construct( Settings $settings ) {
		$this->settings = $settings;
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
	 * Procesează cererea AJAX de test conexiune.
	 *
	 * @return void
	 */
	public function handle(): void {
		check_ajax_referer( self::NONCE_ACTION, 'nonce' );

		if ( ! current_user_can( TRENDYOL_SYNC_CAPABILITY ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'Nu ai permisiunea de a efectua acest test.', 'trendyol-sync-for-woocommerce' ),
				),
				403
			);
		}

		if ( ! $this->settings->has_credentials() ) {
			wp_send_json_error(
				array(
					'message' => __( 'Completează Supplier ID, API Key și API Secret înainte de test.', 'trendyol-sync-for-woocommerce' ),
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

		$plugin      = trendyol_sync();
		$environment = new Environment( $this->settings );
		$brands      = new Brands( $plugin->api_client(), $plugin->cache() );

		// Apel ușor: prima pagină, un singur brand, fără cache.
		$result = $brands->get_brands( 0, 1, false );

		if ( $result['success'] ) {
			wp_send_json_success(
				array(
					'message'     => __( 'Conexiunea cu API Trendyol este funcțională.', 'trendyol-sync-for-woocommerce' ),
					'environment' => $environment->get_label(),
					'base_url'    => $environment->get_base_url(),
				)
			);
		}

		wp_send_json_error(
			array(
				'message'     => (string) ( $result['error'] ?? __( 'Eroare necunoscută la testarea conexiunii.', 'trendyol-sync-for-woocommerce' ) ),
				'status_code' => (int) ( $result['status_code'] ?? 0 ),
				'error_type'  => (string) ( $result['error_type'] ?? 'http' ),
				'environment' => $environment->get_label(),
			)
		);
	}
}
