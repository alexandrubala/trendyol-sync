<?php
/**
 * Helper pentru coduri EAN-13.
 *
 * @package TrendyolSync\Utils
 */

namespace TrendyolSync\Utils;

defined( 'ABSPATH' ) || exit;

/**
 * Class Ean13
 */
final class Ean13 {

	/**
	 * @param string $base12 Primele 12 cifre.
	 * @return string
	 */
	public static function add_check_digit( string $base12 ): string {
		$base12 = preg_replace( '/\D+/', '', $base12 );

		if ( ! is_string( $base12 ) ) {
			return '';
		}

		$base12 = substr( $base12, 0, 12 );

		if ( strlen( $base12 ) < 12 ) {
			$base12 = str_pad( $base12, 12, '0', STR_PAD_LEFT );
		}

		$sum = 0;

		for ( $i = 0; $i < 12; ++$i ) {
			$digit = (int) $base12[ $i ];
			$sum  += ( $i % 2 === 0 ) ? $digit : $digit * 3;
		}

		$check_digit = ( 10 - ( $sum % 10 ) ) % 10;

		return $base12 . (string) $check_digit;
	}

	/**
	 * Generează EAN intern (prefix 200-299 + ID numeric).
	 *
	 * @param int $numeric_id ID intern.
	 * @param int $prefix3    Prefix 3 cifre.
	 * @return string
	 */
	public static function generate_internal( int $numeric_id, int $prefix3 = 200 ): string {
		$prefix3 = max( 200, min( 299, $prefix3 ) );
		$body    = str_pad( (string) ( $numeric_id % 1000000000 ), 9, '0', STR_PAD_LEFT );

		return self::add_check_digit( (string) $prefix3 . $body );
	}
}
