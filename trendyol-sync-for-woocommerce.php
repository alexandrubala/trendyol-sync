<?php
/**
 * Plugin Trendyol Sync for WooCommerce – integrare WooCommerce cu API Trendyol.
 *
 * @package TrendyolSync
 *
 * @wordpress-plugin
 * Plugin Name:       Trendyol Sync for WooCommerce
 * Plugin URI:        https://github.com/alexandrubala/trendyol-sync-for-woocommerce
 * Description:       Sincronizare controlată a produselor WooCommerce cu marketplace-ul Trendyol.
 * Version:           1.2.1
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            alexandrubala
 * Author URI:        https://github.com/alexandrubala
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       trendyol-sync-for-woocommerce
 * Domain Path:       /languages
 * WC requires at least: 7.0
 * WC tested up to:   9.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Versiunea curentă a plugin-ului.
 */
define( 'TRENDYOL_SYNC_VERSION', '1.2.1' );

/**
 * Slug-ul plugin-ului (folder + identificator updater).
 */
define( 'TRENDYOL_SYNC_PLUGIN_SLUG', 'trendyol-sync-for-woocommerce' );

/**
 * Calea absolută către directorul plugin-ului (fără slash final).
 */
define( 'TRENDYOL_SYNC_PATH', plugin_dir_path( __FILE__ ) );

/**
 * URL-ul public al plugin-ului.
 */
define( 'TRENDYOL_SYNC_URL', plugin_dir_url( __FILE__ ) );

/**
 * Fișierul principal al plugin-ului (pentru activation hooks).
 */
define( 'TRENDYOL_SYNC_FILE', __FILE__ );

/**
 * Cheia opțiunii WordPress pentru setări.
 */
define( 'TRENDYOL_SYNC_OPTION_KEY', 'trendyol_sync_settings' );

/**
 * Grupul Action Scheduler pentru acțiunile de sincronizare (păstrat pentru job-uri existente).
 */
define( 'TRENDYOL_SYNC_AS_GROUP', 'trendyol-sync' );

/**
 * Număr maxim de produse per chunk la sincronizare (faze ulterioare).
 */
define( 'TRENDYOL_SYNC_CHUNK_SIZE', 50 );

/**
 * Interval inițial (secunde) pentru polling batchRequestId (faze ulterioare).
 */
define( 'TRENDYOL_SYNC_POLL_INTERVAL', 300 );

/**
 * Capability custom pentru administrarea plugin-ului.
 */
define( 'TRENDYOL_SYNC_CAPABILITY', 'manage_trendyol_sync' );

/**
 * Basename-ul plugin-ului vechi (înainte de redenumire).
 */
define( 'TRENDYOL_SYNC_LEGACY_PLUGIN_BASENAME', 'trendyol-sync/trendyol-sync.php' );

/**
 * Înregistrează autoload PSR-4 pentru namespace-ul TrendyolSync\.
 *
 * @return void
 */
function trendyol_sync_autoload_register(): void {
	spl_autoload_register(
		static function ( string $class ): void {
			$prefix = 'TrendyolSync\\';

			if ( 0 !== strncmp( $prefix, $class, strlen( $prefix ) ) ) {
				return;
			}

			$relative = substr( $class, strlen( $prefix ) );
			$file     = TRENDYOL_SYNC_PATH . 'includes/' . str_replace( '\\', '/', $relative ) . '.php';

			if ( is_readable( $file ) ) {
				require $file;
			}
		}
	);
}

trendyol_sync_autoload_register();

require_once TRENDYOL_SYNC_PATH . 'includes/Plugin.php';

register_activation_hook( __FILE__, array( 'TrendyolSync\Activator', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'TrendyolSync\Deactivator', 'deactivate' ) );

/**
 * Pornește plugin-ul.
 *
 * @return TrendyolSync\Plugin
 */
function trendyol_sync(): TrendyolSync\Plugin {
	return TrendyolSync\Plugin::instance();
}

trendyol_sync();
