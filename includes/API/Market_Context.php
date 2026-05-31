<?php
/**
 * Rezolvă piața Trendyol (storeFrontCode + Accept-Language) din limba site-ului.
 *
 * @package TrendyolSync\API
 */

namespace TrendyolSync\API;

defined( 'ABSPATH' ) || exit;

/**
 * Class Market_Context
 */
class Market_Context {

	/**
	 * @var array<string, array{storefront: string, languages: string[], label: string}>
	 */
	private const STOREFRONTS_BY_COUNTRY = array(
		'RO' => array(
			'storefront' => 'RO',
			'languages'  => array( 'ro' ),
			'label'      => 'România',
		),
		'GR' => array(
			'storefront' => 'GR',
			'languages'  => array( 'el' ),
			'label'      => 'Grecia',
		),
		'DE' => array(
			'storefront' => 'DE',
			'languages'  => array( 'de', 'en' ),
			'label'      => 'Germania',
		),
		'BG' => array(
			'storefront' => 'BG',
			'languages'  => array( 'bg', 'en' ),
			'label'      => 'Bulgaria',
		),
		'HU' => array(
			'storefront' => 'HU',
			'languages'  => array( 'hu', 'en' ),
			'label'      => 'Ungaria',
		),
		'CZ' => array(
			'storefront' => 'CZ',
			'languages'  => array( 'cs', 'en' ),
			'label'      => 'Cehia',
		),
		'SK' => array(
			'storefront' => 'SK',
			'languages'  => array( 'sk', 'en' ),
			'label'      => 'Slovacia',
		),
		'AZ' => array(
			'storefront' => 'AZ',
			'languages'  => array( 'az', 'en' ),
			'label'      => 'Azerbaidjan',
		),
		'SA' => array(
			'storefront' => 'SA',
			'languages'  => array( 'ar', 'en' ),
			'label'      => 'Arabia Saudită',
		),
		'AE' => array(
			'storefront' => 'AE',
			'languages'  => array( 'ar', 'en' ),
			'label'      => 'Emiratele Arabe Unite',
		),
	);

	/**
	 * @var string
	 */
	private $storefront_code;

	/**
	 * @var string
	 */
	private $accept_language;

	/**
	 * @var string
	 */
	private $label;

	/**
	 * @var bool
	 */
	private $supported;

	/**
	 * @var string
	 */
	private $resolution_source;

	/**
	 * @param string $storefront_code   Cod piață Trendyol (ex. RO).
	 * @param string $accept_language   Header Accept-Language (ex. ro).
	 * @param string $label             Etichetă umană.
	 * @param bool   $supported         Piață recunoscută.
	 * @param string $resolution_source Sursa detectării.
	 */
	private function __construct(
		string $storefront_code,
		string $accept_language,
		string $label,
		bool $supported,
		string $resolution_source
	) {
		$this->storefront_code     = $storefront_code;
		$this->accept_language     = $accept_language;
		$this->label               = $label;
		$this->supported           = $supported;
		$this->resolution_source   = $resolution_source;
	}

	/**
	 * Detectează piața pe baza țării WooCommerce și a localei WordPress.
	 *
	 * @return self
	 */
	public static function for_site(): self {
		$wp_language = self::get_wordpress_language_code();
		$wc_country  = self::get_woocommerce_base_country();

		if ( '' !== $wc_country && isset( self::STOREFRONTS_BY_COUNTRY[ $wc_country ] ) ) {
			$def = self::STOREFRONTS_BY_COUNTRY[ $wc_country ];

			return new self(
				$def['storefront'],
				self::pick_accept_language( $wp_language, $def['languages'] ),
				$def['label'],
				true,
				'woocommerce_country'
			);
		}

		foreach ( self::STOREFRONTS_BY_COUNTRY as $def ) {
			if ( in_array( $wp_language, $def['languages'], true ) ) {
				return new self(
					$def['storefront'],
					self::pick_accept_language( $wp_language, $def['languages'] ),
					$def['label'],
					true,
					'wp_locale'
				);
			}
		}

		return new self( '', 'en', '', false, 'none' );
	}

	/**
	 * @return bool
	 */
	public function is_supported(): bool {
		return $this->supported;
	}

	/**
	 * @return string
	 */
	public function get_storefront_code(): string {
		return $this->storefront_code;
	}

	/**
	 * @return string
	 */
	public function get_accept_language(): string {
		return $this->accept_language;
	}

	/**
	 * @return string
	 */
	public function get_label(): string {
		return $this->label;
	}

	/**
	 * Sufix pentru chei transient (ex. RO_ro).
	 *
	 * @return string
	 */
	public function get_cache_suffix(): string {
		return strtoupper( $this->storefront_code ) . '_' . strtolower( $this->accept_language );
	}

	/**
	 * @return string
	 */
	public function get_resolution_source(): string {
		return $this->resolution_source;
	}

	/**
	 * @return string
	 */
	private static function get_wordpress_language_code(): string {
		$locale = function_exists( 'determine_locale' ) ? determine_locale() : get_locale();
		$locale = strtolower( str_replace( '_', '-', (string) $locale ) );

		if ( preg_match( '/^([a-z]{2})/', $locale, $matches ) ) {
			return $matches[1];
		}

		return '';
	}

	/**
	 * @return string Cod ISO țară (ex. RO) sau gol.
	 */
	private static function get_woocommerce_base_country(): string {
		if ( ! function_exists( 'WC' ) ) {
			return '';
		}

		$wc = WC();

		if ( ! $wc || ! isset( $wc->countries ) ) {
			return '';
		}

		return strtoupper( (string) $wc->countries->get_base_country() );
	}

	/**
	 * @param string   $wp_language   Limba site-ului (2 litere).
	 * @param string[] $allowed_langs Limbi acceptate de piață.
	 * @return string
	 */
	private static function pick_accept_language( string $wp_language, array $allowed_langs ): string {
		if ( in_array( $wp_language, $allowed_langs, true ) ) {
			return $wp_language;
		}

		// Piețe cu traduceri locale dedicate (RO, GR, arabe).
		foreach ( array( 'ro', 'el', 'ar' ) as $localized ) {
			if ( in_array( $localized, $allowed_langs, true ) ) {
				return $localized;
			}
		}

		return in_array( 'en', $allowed_langs, true ) ? 'en' : (string) ( $allowed_langs[0] ?? 'en' );
	}
}
