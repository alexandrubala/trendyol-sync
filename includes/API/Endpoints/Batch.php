<?php
/**
 * Endpoint API: getBatchRequestResult.
 *
 * @package TrendyolSync\API\Endpoints
 */

namespace TrendyolSync\API\Endpoints;

use TrendyolSync\Admin\Settings;
use TrendyolSync\API\Client;

defined( 'ABSPATH' ) || exit;

/**
 * Class Batch
 */
class Batch {

	/**
	 * @var Client
	 */
	private $client;

	/**
	 * @var Settings
	 */
	private $settings;

	/**
	 * @param Client   $client   Client HTTP.
	 * @param Settings $settings Setări (supplier ID).
	 */
	public function __construct( Client $client, Settings $settings ) {
		$this->client   = $client;
		$this->settings = $settings;
	}

	/**
	 * GET batch-requests/{batchRequestId} – verifică starea unui batch.
	 *
	 * @param string $batch_request_id ID returnat de createProducts.
	 * @return array<string, mixed> Răspuns normalizat Client.
	 */
	public function get_batch_result( string $batch_request_id ): array {
		$supplier_id = $this->get_supplier_id();
		$batch_request_id = trim( $batch_request_id );

		if ( '' === $supplier_id ) {
			return array(
				'success'     => false,
				'status_code' => 0,
				'data'        => null,
				'error'       => __( 'Supplier ID lipsește din setări.', 'trendyol-sync-for-woocommerce' ),
				'error_type'  => 'config',
			);
		}

		if ( '' === $batch_request_id ) {
			return array(
				'success'     => false,
				'status_code' => 0,
				'data'        => null,
				'error'       => __( 'batchRequestId lipsește.', 'trendyol-sync-for-woocommerce' ),
				'error_type'  => 'config',
			);
		}

		$path = sprintf(
			'/integration/product/sellers/%s/products/batch-requests/%s',
			rawurlencode( $supplier_id ),
			rawurlencode( $batch_request_id )
		);

		return $this->client->get( $path );
	}

	/**
	 * @return string
	 */
	private function get_supplier_id(): string {
		$stored = $this->settings->get_stored_settings();

		return isset( $stored['supplier_id'] ) ? trim( (string) $stored['supplier_id'] ) : '';
	}
}
