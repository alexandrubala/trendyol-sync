<?php
/**
 * Logică la activarea plugin-ului.
 *
 * @package TrendyolSync
 */

namespace TrendyolSync;

use TrendyolSync\Data\Schema;

defined( 'ABSPATH' ) || exit;

/**
 * Class Activator
 */
class Activator {

	/**
	 * Rulează la register_activation_hook.
	 *
	 * @return void
	 */
	public static function activate(): void {
		if ( ! self::is_woocommerce_active() ) {
			deactivate_plugins( plugin_basename( TRENDYOL_SYNC_FILE ) );
			wp_die(
				esc_html__(
					'Trendyol Sync for WooCommerce necesită WooCommerce activ. Activează WooCommerce și încearcă din nou.',
					'trendyol-sync-for-woocommerce'
				),
				esc_html__( 'Dependență lipsă', 'trendyol-sync-for-woocommerce' ),
				array( 'back_link' => true )
			);
		}

		self::add_capabilities();
		Schema::create_tables();

		flush_rewrite_rules();
	}

	/**
	 * Verifică dacă pluginul WooCommerce este activ.
	 *
	 * @return bool
	 */
	public static function is_woocommerce_active(): bool {
		if ( ! function_exists( 'is_plugin_active' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		return is_plugin_active( 'woocommerce/woocommerce.php' );
	}

	/**
	 * Adaugă capability-ul custom rolurilor relevante.
	 *
	 * @return void
	 */
	private static function add_capabilities(): void {
		$roles = array( 'administrator', 'shop_manager' );

		foreach ( $roles as $role_slug ) {
			$role = get_role( $role_slug );

			if ( $role ) {
				$role->add_cap( TRENDYOL_SYNC_CAPABILITY );
			}
		}
	}
}
