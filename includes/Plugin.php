<?php
/**
 * Orchestrator principal al plugin-ului (Singleton).
 *
 * @package TrendyolSync
 */

namespace TrendyolSync;

use TrendyolSync\Activator;
use TrendyolSync\Admin\Admin;

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
