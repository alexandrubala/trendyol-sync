<?php
/**
 * Înregistrează și încarcă selectWoo (Select2) de la WooCommerce pe ecrane admin custom.
 *
 * @package TrendyolSync\Admin
 */

namespace TrendyolSync\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Class Select_Woo_Assets
 */
class Select_Woo_Assets {

	/**
	 * Înregistrează scripturile dacă WooCommerce nu le-a încărcat deja pe ecranul curent.
	 *
	 * @return void
	 */
	public static function register(): void {
		if ( ! function_exists( 'WC' ) || wp_script_is( 'selectWoo', 'registered' ) ) {
			return;
		}

		$suffix  = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';
		$version = WC()->version;

		wp_register_script(
			'selectWoo',
			WC()->plugin_url() . '/assets/js/selectWoo/selectWoo.full' . $suffix . '.js',
			array( 'jquery' ),
			'1.0.9-wc.' . $version,
			true
		);

		wp_register_style(
			'select2',
			WC()->plugin_url() . '/assets/css/select2.css',
			array(),
			'4.0.3-wc.' . $version
		);
	}

	/**
	 * @return void
	 */
	public static function enqueue(): void {
		self::register();
		wp_enqueue_style( 'select2' );
		wp_enqueue_script( 'selectWoo' );
	}
}
