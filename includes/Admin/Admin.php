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
	 * Test conexiune AJAX.
	 *
	 * @var Connection_Checker
	 */
	private $connection_checker;

	/**
	 * Endpoints AJAX pentru sincronizare.
	 *
	 * @var Sync_Ajax
	 */
	private $sync_ajax;

	/**
	 * Tab Product Data WooCommerce.
	 *
	 * @var Product_Data_Tab
	 */
	private $product_data_tab;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->settings           = trendyol_sync()->settings();
		$this->settings_page      = new Settings_Page( $this->settings );
		$this->connection_checker = new Connection_Checker( $this->settings );
		$this->sync_ajax          = new Sync_Ajax();
		$this->product_data_tab   = new Product_Data_Tab();
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
		$this->connection_checker->register_hooks();
		$this->sync_ajax->register_hooks();
		$this->product_data_tab->register_hooks();
		( new Updater() )->register_hooks();
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

		wp_enqueue_script(
			'trendyol-sync-admin-settings',
			TRENDYOL_SYNC_URL . 'assets/js/admin-settings.js',
			array( 'jquery' ),
			TRENDYOL_SYNC_VERSION,
			true
		);

		wp_localize_script(
			'trendyol-sync-admin-settings',
			'trendyolSyncAdmin',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'action'  => Connection_Checker::AJAX_ACTION,
				'nonce'   => wp_create_nonce( Connection_Checker::NONCE_ACTION ),
				'i18n'    => array(
					'checking' => __( 'Se testează conexiunea…', 'trendyol-sync' ),
					'button'   => __( 'Check API Status', 'trendyol-sync' ),
				),
			)
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
