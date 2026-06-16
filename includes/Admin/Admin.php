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
	 * Slug meniu principal (top-level) în admin.
	 */
	public const MENU_SLUG = 'trendyol-sync-settings';

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
	 * Sincronizare catalog (branduri / categorii).
	 *
	 * @var Catalog_Syncer
	 */
	private $catalog_syncer;

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
	 * Coloană status Trendyol în lista de produse.
	 *
	 * @var Product_List_Column
	 */
	private $product_list_column;

	/**
	 * @var Category_Mapping_Page
	 */
	private $category_mapping_page;

	/**
	 * @var Bulk_Actions
	 */
	private $bulk_actions;

	/**
	 * @var Sync_Dashboard_Page
	 */
	private $sync_dashboard_page;

	/**
	 * @var Onboarding_Wizard_Page
	 */
	private $onboarding_wizard_page;

	/**
	 * @var Catalog_Search
	 */
	private $catalog_search;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->settings           = trendyol_sync()->settings();
		$this->settings_page      = new Settings_Page( $this->settings );
		$this->connection_checker = new Connection_Checker( $this->settings );
		$this->catalog_syncer     = new Catalog_Syncer( $this->settings );
		$this->sync_ajax          = new Sync_Ajax();
		$this->product_data_tab   = new Product_Data_Tab();
		$this->product_list_column = new Product_List_Column();
		$this->category_mapping_page = new Category_Mapping_Page();
		$this->bulk_actions = new Bulk_Actions();
		$this->sync_dashboard_page = new Sync_Dashboard_Page();
		$this->onboarding_wizard_page = new Onboarding_Wizard_Page();
		$this->catalog_search         = new Catalog_Search();
	}

	/**
	 * Înregistrează hook-urile admin.
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_init', array( $this->settings, 'register' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ), 20 );
		$this->connection_checker->register_hooks();
		$this->catalog_syncer->register_hooks();
		$this->sync_ajax->register_hooks();
		$this->product_data_tab->register_hooks();
		$this->product_list_column->register_hooks();
		$this->category_mapping_page->register_hooks();
		$this->bulk_actions->register_hooks();
		$this->sync_dashboard_page->register_hooks();
		$this->onboarding_wizard_page->register_hooks();
		$this->catalog_search->register_hooks();

		if ( Updater::is_enabled() ) {
			( new Updater() )->register_hooks();
		}

		add_action( 'admin_init', array( Select_Woo_Assets::class, 'register' ) );
	}

	/**
	 * Încarcă stiluri doar pe pagina de setări.
	 *
	 * @param string $hook_suffix Hook-ul paginii curente.
	 * @return void
	 */
	public function enqueue_assets( string $hook_suffix ): void {
		if ( $this->is_mapping_screen( $hook_suffix ) ) {
			$this->enqueue_mapping_assets();
		}

		if ( ! $this->is_plugin_admin_screen( $hook_suffix ) ) {
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
				'ajaxUrl'    => admin_url( 'admin-ajax.php' ),
				'connection' => array(
					'action' => Connection_Checker::AJAX_ACTION,
					'nonce'  => wp_create_nonce( Connection_Checker::NONCE_ACTION ),
				),
				'catalog'    => array(
					'action' => Catalog_Syncer::AJAX_ACTION,
					'nonce'  => wp_create_nonce( Catalog_Syncer::NONCE_ACTION ),
				),
				'sync'       => array(
					'startAction'  => Sync_Ajax::ACTION_START_SYNC,
					'statusAction' => Sync_Ajax::ACTION_STATUS,
					'nonce'        => wp_create_nonce( Sync_Ajax::NONCE_ACTION ),
				),
				'i18n'       => array(
					'checkingConnection' => __( 'Se testează conexiunea…', 'trendyol-sync-for-woocommerce' ),
					'connectionButton'   => __( 'Check API Status', 'trendyol-sync-for-woocommerce' ),
					'syncingCatalog'     => __( 'Se sincronizează catalogul…', 'trendyol-sync-for-woocommerce' ),
					'catalogButton'      => __( 'Sincronizează catalog', 'trendyol-sync-for-woocommerce' ),
					'startingSync'       => __( 'Se pornește sincronizarea…', 'trendyol-sync-for-woocommerce' ),
					'syncButton'         => __( 'Pornește sincronizarea', 'trendyol-sync-for-woocommerce' ),
					'syncDone'           => __( 'Sincronizare finalizată.', 'trendyol-sync-for-woocommerce' ),
					'statusIdle'         => __( 'Nu există job activ.', 'trendyol-sync-for-woocommerce' ),
				),
			)
		);

	}

	/**
	 * Scripturi și date pentru pagina de mapare categorii.
	 *
	 * @return void
	 */
	private function enqueue_mapping_assets(): void {
		Select_Woo_Assets::enqueue();

		$script_deps = array( 'jquery' );

		if ( wp_script_is( 'wc-enhanced-select', 'registered' ) || wp_script_is( 'wc-enhanced-select', 'enqueued' ) ) {
			$script_deps[] = 'wc-enhanced-select';
		} else {
			$script_deps[] = 'selectWoo';
		}

		wp_enqueue_script(
			'trendyol-sync-category-mapping',
			TRENDYOL_SYNC_URL . 'assets/js/admin-category-mapping.js',
			$script_deps,
			TRENDYOL_SYNC_VERSION,
			true
		);

		$catalog_options  = new Catalog_Options();
		$category_options = $this->catalog_search->get_category_select2_data();

		wp_localize_script(
			'trendyol-sync-category-mapping',
			'trendyolSyncMappingData',
			array(
				'ajaxUrl'          => admin_url( 'admin-ajax.php' ),
				'searchAction'     => Catalog_Search::AJAX_ACTION,
				'nonce'            => wp_create_nonce( Catalog_Search::NONCE_ACTION ),
				'categories'       => $category_options,
				'catalogReady'     => $catalog_options->has_cached_catalog() || ! empty( $category_options ),
				'noResults'        => __( 'Niciun rezultat găsit. Sincronizează catalogul din Setări.', 'trendyol-sync-for-woocommerce' ),
				'searching'        => __( 'Se caută…', 'trendyol-sync-for-woocommerce' ),
				'selectWooMissing' => __( 'Componenta de căutare nu s-a încărcat. Verifică că WooCommerce este activ și reîncarcă pagina.', 'trendyol-sync-for-woocommerce' ),
				'catalogEmpty'     => __( 'Catalogul Trendyol nu este în cache. Rulează „Sincronizează catalog” din Setări.', 'trendyol-sync-for-woocommerce' ),
			)
		);

		$style_deps = array();

		if ( wp_style_is( 'select2', 'registered' ) ) {
			$style_deps[] = 'select2';
		}

		wp_enqueue_style(
			'trendyol-sync-product-data',
			TRENDYOL_SYNC_URL . 'assets/css/admin-product-data.css',
			$style_deps,
			TRENDYOL_SYNC_VERSION
		);
	}

	/**
	 * Ecranul de mapare categorii (hook suffix sau query ?page=).
	 *
	 * @param string $hook_suffix Hook admin curent.
	 * @return bool
	 */
	private function is_mapping_screen( string $hook_suffix ): bool {
		if ( self::MENU_SLUG . '_page_' . Category_Mapping_Page::PAGE_SLUG === $hook_suffix ) {
			return true;
		}

		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( (string) $_GET['page'] ) ) : '';

		return Category_Mapping_Page::PAGE_SLUG === $page;
	}

	/**
	 * Meniu dedicat Trendyol Sync (în afara WooCommerce).
	 *
	 * @return void
	 */
	public function register_menu(): void {
		add_menu_page(
			__( 'Trendyol Sync for WooCommerce', 'trendyol-sync-for-woocommerce' ),
			__( 'Trendyol Sync', 'trendyol-sync-for-woocommerce' ),
			TRENDYOL_SYNC_CAPABILITY,
			self::MENU_SLUG,
			array( $this->settings_page, 'render' ),
			'dashicons-update',
			58
		);

		// Același slug ca meniul principal — WordPress ascunde duplicatul din submeniu.
		add_submenu_page(
			self::MENU_SLUG,
			__( 'Trendyol Sync', 'trendyol-sync-for-woocommerce' ),
			__( 'Setări', 'trendyol-sync-for-woocommerce' ),
			TRENDYOL_SYNC_CAPABILITY,
			self::MENU_SLUG,
			array( $this->settings_page, 'render' )
		);

		add_submenu_page(
			self::MENU_SLUG,
			__( 'Trendyol Mapping', 'trendyol-sync-for-woocommerce' ),
			__( 'Mapping', 'trendyol-sync-for-woocommerce' ),
			TRENDYOL_SYNC_CAPABILITY,
			Category_Mapping_Page::PAGE_SLUG,
			array( $this->category_mapping_page, 'render' )
		);

		add_submenu_page(
			self::MENU_SLUG,
			__( 'Trendyol Sync Queue', 'trendyol-sync-for-woocommerce' ),
			__( 'Sync Queue', 'trendyol-sync-for-woocommerce' ),
			TRENDYOL_SYNC_CAPABILITY,
			Sync_Dashboard_Page::PAGE_SLUG,
			array( $this->sync_dashboard_page, 'render' )
		);

		add_submenu_page(
			self::MENU_SLUG,
			__( 'Trendyol Onboarding', 'trendyol-sync-for-woocommerce' ),
			__( 'Onboarding', 'trendyol-sync-for-woocommerce' ),
			TRENDYOL_SYNC_CAPABILITY,
			Onboarding_Wizard_Page::PAGE_SLUG,
			array( $this->onboarding_wizard_page, 'render' )
		);
	}

	/**
	 * Verifică dacă ecranul curent aparține pluginului.
	 *
	 * @param string $hook_suffix Hook-ul paginii admin.
	 * @return bool
	 */
	private function is_plugin_admin_screen( string $hook_suffix ): bool {
		if ( 'toplevel_page_' . self::MENU_SLUG === $hook_suffix ) {
			return true;
		}

		$submenu_prefix = self::MENU_SLUG . '_page_';

		return in_array(
			$hook_suffix,
			array(
				$submenu_prefix . Category_Mapping_Page::PAGE_SLUG,
				$submenu_prefix . Sync_Dashboard_Page::PAGE_SLUG,
				$submenu_prefix . Onboarding_Wizard_Page::PAGE_SLUG,
			),
			true
		);
	}
}
