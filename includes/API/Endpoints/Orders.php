<?php
/**
 * Endpoint API (stub): shipment packages.
 *
 * @package TrendyolSync\API\Endpoints
 */

namespace TrendyolSync\API\Endpoints;

defined( 'ABSPATH' ) || exit;

/**
 * Class Orders
 */
class Orders {
	/**
	 * GET /integration/product/v2/shipment-packages
	 *
	 * Pentru faza 2 returnează doar request-ul mapat (fără apel la API).
	 *
	 * @param array{
	 *     status?: string,
	 *     startDate?: string,
	 *     endDate?: string,
	 *     page?: int,
	 *     size?: int
	 * } $args
	 *
	 * @return array{
	 *     method: 'GET',
	 *     path: string,
	 *     query: array<string, mixed>
	 * }
	 */
	public function get_shipment_packages( array $args ): array {
		$query = array();

		if ( isset( $args['status'] ) ) {
			$status = sanitize_text_field( wp_unslash( (string) $args['status'] ) );
			if ( '' !== $status ) {
				$query['status'] = $status;
			}
		}

		if ( isset( $args['startDate'] ) ) {
			$start = sanitize_text_field( wp_unslash( (string) $args['startDate'] ) );
			if ( $this->is_valid_date_ymd( $start ) ) {
				$query['startDate'] = $start;
			}
		}

		if ( isset( $args['endDate'] ) ) {
			$end = sanitize_text_field( wp_unslash( (string) $args['endDate'] ) );
			if ( $this->is_valid_date_ymd( $end ) ) {
				$query['endDate'] = $end;
			}
		}

		if ( isset( $args['page'] ) ) {
			$page = (int) $args['page'];
			$page = max( 0, $page );
			$query['page'] = $page;
		}

		if ( isset( $args['size'] ) ) {
			$size = (int) $args['size'];
			$size = max( 1, $size );
			// Nu impunem cap; îl lăsăm pentru faza următoare.
			$query['size'] = $size;
		}

		return array(
			'method' => 'GET',
			'path'   => '/integration/product/v2/shipment-packages',
			'query'  => $query,
		);
	}

	/**
	 * Validare date format YYYY-MM-DD.
	 *
	 * @param string $value Date.
	 * @return bool
	 */
	private function is_valid_date_ymd( string $value ): bool {
		if ( '' === $value ) {
			return false;
		}

		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $value ) ) {
			return false;
		}

		// Validare suplimentară: existența datei reale.
		$parts = explode( '-', $value );
		$year  = (int) $parts[0];
		$month = (int) $parts[1];
		$day   = (int) $parts[2];

		return checkdate( $month, $day, $year );
	}
}

