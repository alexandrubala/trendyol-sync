<?php
/**
 * Bootstrap modul Admin.
 *
 * @package TrendyolSync\Admin
 */

namespace TrendyolSync\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Class Admin
 */
class Admin {

	/**
	 * Pagina de setări.
	 *
	 * @var Settings_Page
	 */
	private $settings_page;

	/**
	 * Handler Settings API.
	 *
	 * @var Settings
	 */
	private $settings;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->settings      = new Settings();
		$this->settings_page = new Settings_Page( $this->settings );
	}

	/**
	 * Înregistrează hook-urile admin.
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_init', array( $this->settings, 'register' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	/**
	 * Încarcă stiluri doar pe pagina de setări.
	 *
	 * @param string $hook_suffix Hook-ul paginii curente.
	 * @return void
	 */
	public function enqueue_assets( string $hook_suffix ): void {
		if ( 'woocommerce_page_trendyol-sync-settings' !== $hook_suffix ) {
			return;
		}

		wp_enqueue_style(
			'trendyol-sync-admin-settings',
			TRENDYOL_SYNC_URL . 'assets/css/admin-settings.css',
			array(),
			TRENDYOL_SYNC_VERSION
		);
	}

	/**
	 * Adaugă submeniul sub WooCommerce.
	 *
	 * @return void
	 */
	public function register_menu(): void {
		add_submenu_page(
			'woocommerce',
			__( 'Trendyol Sync', 'trendyol-sync' ),
			__( 'Trendyol Sync', 'trendyol-sync' ),
			TRENDYOL_SYNC_CAPABILITY,
			'trendyol-sync-settings',
			array( $this->settings_page, 'render' )
		);
	}
}
