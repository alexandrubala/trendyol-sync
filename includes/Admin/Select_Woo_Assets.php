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
		if ( ! function_exists( 'WC' ) ) {
			return;
		}

		if ( wp_script_is( 'selectWoo', 'registered' ) ) {
			return;
		}

		$script_src = self::resolve_script_src();
		$style_src  = self::resolve_style_src();

		if ( null === $script_src ) {
			return;
		}

		$version = WC()->version;

		wp_register_script(
			'selectWoo',
			$script_src,
			array( 'jquery' ),
			'1.0.9-wc.' . $version,
			true
		);

		if ( null !== $style_src ) {
			wp_register_style(
				'select2',
				$style_src,
				array(),
				'4.0.3-wc.' . $version
			);
		}
	}

	/**
	 * @return void
	 */
	public static function enqueue(): void {
		self::register();

		if ( wp_script_is( 'selectWoo', 'registered' ) ) {
			wp_enqueue_style( 'select2' );
			wp_enqueue_script( 'selectWoo' );
		}
	}

	/**
	 * @return string|null
	 */
	private static function resolve_script_src(): ?string {
		$suffix = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';
		$paths  = array(
			'/assets/js/selectWoo/selectWoo.full' . $suffix . '.js',
			'/assets/js/selectWoo/selectWoo.full.js',
			'/assets/js/selectWoo/selectWoo' . $suffix . '.js',
			'/assets/js/select2/select2.full' . $suffix . '.js',
		);

		return self::resolve_asset_url( $paths );
	}

	/**
	 * @return string|null
	 */
	private static function resolve_style_src(): ?string {
		$paths = array(
			'/assets/css/select2.css',
			'/assets/css/select2/select2.css',
		);

		return self::resolve_asset_url( $paths );
	}

	/**
	 * @param string[] $relative_paths Căi relative în pluginul WooCommerce.
	 * @return string|null
	 */
	private static function resolve_asset_url( array $relative_paths ): ?string {
		$base_path = WC()->plugin_path();
		$base_url  = WC()->plugin_url();

		foreach ( $relative_paths as $relative_path ) {
			if ( file_exists( $base_path . $relative_path ) ) {
				return $base_url . $relative_path;
			}
		}

		return null;
	}
}
