<?php
/**
 * Orchestrator principal al plugin-ului (Singleton).
 *
 * @package TrendyolSync
 */

namespace TrendyolSync;

use TrendyolSync\Activator;
use TrendyolSync\Admin\Admin;
use TrendyolSync\Admin\Settings;
use TrendyolSync\API\Auth;
use TrendyolSync\API\Client;
use TrendyolSync\API\Environment;
use TrendyolSync\Cache\Transient_Cache;
use TrendyolSync\Sync\Batch_Poller;
use TrendyolSync\Sync\Payload_Validator;
use TrendyolSync\Sync\Product_Mapper;
use TrendyolSync\Sync\Sync_Runner;

defined( 'ABSPATH' ) || exit;

/**
 * Class Plugin
 */
final class Plugin {

	/**
	 * Instanța unică.
	 *
	 * @var Plugin|null
	 */
	private static $instance = null;

	/**
	 * Modul admin.
	 *
	 * @var Admin|null
	 */
	private $admin = null;

	/**
	 * Setări plugin (lazy).
	 *
	 * @var Settings|null
	 */
	private $settings = null;

	/**
	 * Client API Trendyol (lazy).
	 *
	 * @var Client|null
	 */
	private $api_client = null;

	/**
	 * Cache transient API (lazy).
	 *
	 * @var Transient_Cache|null
	 */
	private $cache = null;

	/**
	 * Mapper produs → payload Trendyol (lazy).
	 *
	 * @var Product_Mapper|null
	 */
	private $product_mapper = null;

	/**
	 * Validator pre-flight (lazy).
	 *
	 * @var Payload_Validator|null
	 */
	private $payload_validator = null;

	/**
	 * @return Plugin
	 */
	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Constructor privat – folosește instance().
	 */
	private function __construct() {
		$this->register_hooks();
	}

	/**
	 * Înregistrează hook-urile de bază.
	 *
	 * @return void
	 */
	private function register_hooks(): void {
		add_action( 'plugins_loaded', array( $this, 'on_plugins_loaded' ), 20 );
		add_action( 'init', array( $this, 'on_init' ) );
	}

	/**
	 * Verifică dependențele și încarcă modulele.
	 *
	 * @return void
	 */
	public function on_plugins_loaded(): void {
		if ( ! Activator::is_woocommerce_active() ) {
			add_action( 'admin_notices', array( $this, 'woocommerce_missing_notice' ) );
			return;
		}

		Sync_Runner::register_hooks();
		Batch_Poller::register_hooks();

		if ( is_admin() ) {
			$this->admin = new Admin();
			$this->admin->register_hooks();
		}
	}

	/**
	 * Inițializări generale (textdomain etc.).
	 *
	 * @return void
	 */
	public function on_init(): void {
		load_plugin_textdomain(
			'trendyol-sync',
			false,
			dirname( plugin_basename( TRENDYOL_SYNC_FILE ) ) . '/languages'
		);
	}

	/**
	 * Afișează notificare dacă WooCommerce lipsește.
	 *
	 * @return void
	 */
	public function woocommerce_missing_notice(): void {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}

		printf(
			'<div class="notice notice-error"><p>%s</p></div>',
			esc_html__(
				'Trendyol Sync necesită WooCommerce. Activează WooCommerce pentru a folosi acest plugin.',
				'trendyol-sync'
			)
		);
	}

	/**
	 * @return Admin|null
	 */
	public function admin(): ?Admin {
		return $this->admin;
	}

	/**
	 * Handler Settings API (partajat între admin și client API).
	 *
	 * @return Settings
	 */
	public function settings(): Settings {
		if ( null === $this->settings ) {
			$this->settings = new Settings();
		}

		return $this->settings;
	}

	/**
	 * Client HTTP Trendyol configurat cu credențialele salvate.
	 *
	 * @return Client
	 */
	public function api_client(): Client {
		if ( null === $this->api_client ) {
			$settings = $this->settings();
			$this->api_client = new Client(
				new Environment( $settings ),
				new Auth( $settings )
			);
		}

		return $this->api_client;
	}

	/**
	 * Strat cache transient pentru date API statice.
	 *
	 * @return Transient_Cache
	 */
	public function cache(): Transient_Cache {
		if ( null === $this->cache ) {
			$this->cache = new Transient_Cache();
		}

		return $this->cache;
	}

	/**
	 * Mapper WooCommerce → JSON Product Create v2.
	 *
	 * @return Product_Mapper
	 */
	public function product_mapper(): Product_Mapper {
		if ( null === $this->product_mapper ) {
			$this->product_mapper = new Product_Mapper();
		}

		return $this->product_mapper;
	}

	/**
	 * Validator pre-flight înainte de coada Action Scheduler.
	 *
	 * @return Payload_Validator
	 */
	public function payload_validator(): Payload_Validator {
		if ( null === $this->payload_validator ) {
			$this->payload_validator = new Payload_Validator();
		}

		return $this->payload_validator;
	}

	/**
	 * Previne clonarea.
	 */
	private function __clone() {}

	/**
	 * Previne deserializarea.
	 *
	 * @throws \Exception Întotdeauna.
	 */
	public function __wakeup() {
		throw new \Exception( 'Cannot unserialize singleton.' );
	}
}
