<?php
/**
 * Migrare de la instalarea veche (trendyol-sync) la noul slug.
 *
 * @package TrendyolSync\Migration
 */

namespace TrendyolSync\Migration;

defined( 'ABSPATH' ) || exit;

/**
 * Class From_Legacy_Plugin
 */
class From_Legacy_Plugin {

	/**
	 * Transient pentru notificarea de migrare (o singură dată per admin).
	 */
	private const NOTICE_TRANSIENT = 'trendyol_sync_legacy_migrate_notice';

	/**
	 * @return void
	 */
	public static function register_hooks(): void {
		add_action( 'admin_init', array( self::class, 'deactivate_legacy_if_active' ) );
		add_action( 'admin_notices', array( self::class, 'maybe_show_legacy_folder_notice' ) );
	}

	/**
	 * Dezactivează pluginul vechi dacă ambele sunt active simultan.
	 *
	 * @return void
	 */
	public static function deactivate_legacy_if_active(): void {
		if ( ! is_admin() || ! current_user_can( 'activate_plugins' ) ) {
			return;
		}

		if ( ! function_exists( 'is_plugin_active' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$legacy = TRENDYOL_SYNC_LEGACY_PLUGIN_BASENAME;
		$new    = plugin_basename( TRENDYOL_SYNC_FILE );

		if ( $legacy === $new || ! is_plugin_active( $legacy ) ) {
			return;
		}

		if ( ! is_plugin_active( $new ) ) {
			return;
		}

		deactivate_plugins( $legacy, true );
		set_transient( self::NOTICE_TRANSIENT, 1, WEEK_IN_SECONDS );
	}

	/**
	 * Avertizează administratorul să șteargă folderul vechi după migrare.
	 *
	 * @return void
	 */
	public static function maybe_show_legacy_folder_notice(): void {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}

		$legacy_dir = WP_PLUGIN_DIR . '/trendyol-sync';
		if ( ! is_dir( $legacy_dir ) ) {
			return;
		}

		$show = (bool) get_transient( self::NOTICE_TRANSIENT );
		if ( ! $show && ! self::legacy_plugin_file_exists() ) {
			return;
		}

		printf(
			'<div class="notice notice-warning is-dismissible"><p>%s</p></div>',
			esc_html__(
				'Trendyol Sync for WooCommerce: poți șterge folderul vechi wp-content/plugins/trendyol-sync/ dacă nu mai este activ. Setările și datele de sync au fost păstrate.',
				'trendyol-sync-for-woocommerce'
			)
		);
	}

	/**
	 * @return bool
	 */
	private static function legacy_plugin_file_exists(): bool {
		return is_readable( WP_PLUGIN_DIR . '/trendyol-sync/trendyol-sync.php' );
	}
}
