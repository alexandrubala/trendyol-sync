<?php
/**
 * Cote TVA (vatRate) permise de Trendyol per storeFrontCode.
 *
 * @see https://developers.trendyol.com/v3.0/docs/5-regions-and-store-front-codes
 *
 * @package TrendyolSync\API
 */

namespace TrendyolSync\API;

defined( 'ABSPATH' ) || exit;

/**
 * Class Vat_Rates
 */
class Vat_Rates {

	/**
	 * Cote și implicit per piață documentată de Trendyol.
	 *
	 * @var array<string, array{rates: int[], default: int, tax_class_map: array<string, int>}>
	 */
	private const BY_STOREFRONT = array(
		'RO' => array(
			'rates'           => array( 0, 11, 21 ),
			'default'         => 21,
			'tax_class_map'   => array(
				'standard'      => 21,
				'reduced-rate'  => 11,
				'zero-rate'     => 0,
			),
		),
		'DE' => array(
			'rates'           => array( 0, 7, 19 ),
			'default'         => 19,
			'tax_class_map'   => array(
				'standard'      => 19,
				'reduced-rate'  => 7,
				'zero-rate'     => 0,
			),
		),
		'GR' => array(
			'rates'           => array( 0, 6, 13, 24 ),
			'default'         => 24,
			'tax_class_map'   => array(
				'standard'      => 24,
				'reduced-rate'  => 13,
				'zero-rate'     => 0,
			),
		),
		'SK' => array(
			'rates'           => array( 0, 19, 23 ),
			'default'         => 23,
			'tax_class_map'   => array(
				'standard'      => 23,
				'reduced-rate'  => 19,
				'zero-rate'     => 0,
			),
		),
		'CZ' => array(
			'rates'           => array( 0, 12, 21 ),
			'default'         => 21,
			'tax_class_map'   => array(
				'standard'      => 21,
				'reduced-rate'  => 12,
				'zero-rate'     => 0,
			),
		),
		'BG' => array(
			'rates'           => array( 0, 9, 20 ),
			'default'         => 20,
			'tax_class_map'   => array(
				'standard'      => 20,
				'reduced-rate'  => 9,
				'zero-rate'     => 0,
			),
		),
		'SA' => array(
			'rates'           => array( 15 ),
			'default'         => 15,
			'tax_class_map'   => array(
				'standard' => 15,
			),
		),
		'AE' => array(
			'rates'           => array( 5 ),
			'default'         => 5,
			'tax_class_map'   => array(
				'standard' => 5,
			),
		),
		'KW' => array(
			'rates'           => array( 0 ),
			'default'         => 0,
			'tax_class_map'   => array(
				'zero-rate' => 0,
			),
		),
	);

	/** @var int[] Cote fallback (documentație generală / piață nedetectată). */
	private const FALLBACK_RATES = array( 0, 1, 10, 18, 20 );

	private const FALLBACK_DEFAULT = 20;

	/** @var int[] */
	private $allowed_rates;

	/** @var int */
	private $default_rate;

	/** @var array<string, int> */
	private $tax_class_map;

	/**
	 * @param int[]                $allowed_rates  Cote permise.
	 * @param int                  $default_rate   Cotă implicită.
	 * @param array<string, int>   $tax_class_map  Mapare tax class WooCommerce.
	 */
	private function __construct( array $allowed_rates, int $default_rate, array $tax_class_map ) {
		$this->allowed_rates = $allowed_rates;
		$this->default_rate  = $default_rate;
		$this->tax_class_map = $tax_class_map;
	}

	/**
	 * Cote TVA pentru piața detectată pe site.
	 *
	 * @return self
	 */
	public static function for_site(): self {
		return self::for_storefront( Market_Context::for_site()->get_storefront_code() );
	}

	/**
	 * @param string $storefront_code Cod storeFrontCode (ex. RO) sau gol.
	 * @return self
	 */
	public static function for_storefront( string $storefront_code ): self {
		$code = strtoupper( trim( $storefront_code ) );

		if ( '' !== $code && isset( self::BY_STOREFRONT[ $code ] ) ) {
			$def = self::BY_STOREFRONT[ $code ];

			return new self( $def['rates'], $def['default'], $def['tax_class_map'] );
		}

		return new self(
			self::FALLBACK_RATES,
			self::FALLBACK_DEFAULT,
			array(
				'standard'     => self::FALLBACK_DEFAULT,
				'reduced-rate' => 10,
				'zero-rate'    => 0,
			)
		);
	}

	/**
	 * @return int[]
	 */
	public function get_allowed_rates(): array {
		return $this->allowed_rates;
	}

	/**
	 * @return int
	 */
	public function get_default_rate(): int {
		return $this->default_rate;
	}

	/**
	 * @return array<string, int>
	 */
	public function get_default_tax_class_map(): array {
		return $this->tax_class_map;
	}

	/**
	 * @return string JSON pentru câmpul tax_class_map din setări.
	 */
	public function get_default_tax_class_map_json(): string {
		return (string) wp_json_encode( $this->tax_class_map, JSON_PRETTY_PRINT );
	}

	/**
	 * @param int $rate Cotă de verificat.
	 * @return bool
	 */
	public function is_valid( int $rate ): bool {
		return in_array( $rate, $this->allowed_rates, true );
	}

	/**
	 * Returnează cotă validă sau implicitul pieței.
	 *
	 * @param int      $rate     Cotă propusă.
	 * @param int|null $fallback Fallback dacă invalidă (implicit: cota pieței).
	 * @return int
	 */
	public function sanitize( int $rate, ?int $fallback = null ): int {
		if ( $this->is_valid( $rate ) ) {
			return $rate;
		}

		$fallback = null !== $fallback ? $fallback : $this->default_rate;

		return $this->is_valid( $fallback ) ? $fallback : $this->default_rate;
	}

	/**
	 * Opțiuni pentru select WooCommerce (cheie string => etichetă).
	 *
	 * @param bool $include_empty Include opțiunea „Selectează”.
	 * @return array<string, string>
	 */
	public function get_select_options( bool $include_empty = true ): array {
		$options = array();

		if ( $include_empty ) {
			$options[''] = __( '— Selectează —', 'trendyol-sync-for-woocommerce' );
		}

		foreach ( $this->allowed_rates as $rate ) {
			$options[ (string) $rate ] = (string) $rate;
		}

		return $options;
	}
}
