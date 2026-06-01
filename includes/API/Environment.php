<?php
/**
 * Rezolvă URL-ul de bază al API Trendyol în funcție de setările salvate.
 *
 * @package TrendyolSync\API
 */

namespace TrendyolSync\API;

use TrendyolSync\Admin\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Class Environment
 */
class Environment {

	public const ENV_STAGE      = 'stage';
	public const ENV_PRODUCTION = 'production';

	private const BASE_URLS = array(
		self::ENV_STAGE      => 'https://stageapigw.trendyol.com',
		self::ENV_PRODUCTION => 'https://apigw.trendyol.com',
	);

	/**
	 * @var Settings
	 */
	private $settings;

	/**
	 * @param Settings $settings Handler setări plugin.
	 */
	public function __construct( Settings $settings ) {
		$this->settings = $settings;
	}

	/**
	 * Mediu activ din setări (stage|production).
	 *
	 * @return string
	 */
	public function get_environment(): string {
		$stored = $this->settings->get_stored_settings();
		$env    = (string) ( $stored['environment'] ?? self::ENV_STAGE );

		return isset( self::BASE_URLS[ $env ] ) ? $env : self::ENV_STAGE;
	}

	/**
	 * URL de bază fără slash final.
	 *
	 * @return string
	 */
	public function get_base_url(): string {
		$env = $this->get_environment();

		return self::BASE_URLS[ $env ];
	}

	/**
	 * Construiește URL complet pentru un path API.
	 *
	 * @param string $path Path relativ (ex. /integration/product/brands).
	 * @return string
	 */
	public function build_url( string $path ): string {
		$path = '/' . ltrim( $path, '/' );

		return $this->get_base_url() . $path;
	}

	/**
	 * Etichetă umană pentru mediul curent (UI / loguri).
	 *
	 * @return string
	 */
	public function get_label(): string {
		return self::ENV_PRODUCTION === $this->get_environment()
			? __( 'Production', 'trendyol-sync-for-woocommerce' )
			: __( 'Stage', 'trendyol-sync-for-woocommerce' );
	}
}
