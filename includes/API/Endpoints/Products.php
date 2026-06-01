<?php
/**
 * Endpoint API: createProducts v2.
 *
 * @package TrendyolSync\API\Endpoints
 */

namespace TrendyolSync\API\Endpoints;

use TrendyolSync\Admin\Settings;
use TrendyolSync\API\Client;

defined( 'ABSPATH' ) || exit;

/**
 * Class Products
 */
class Products {

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
	 * POST createProducts v2 – încarcă produse în Trendyol.
	 *
	 * @param array{items: array<int, array<string, mixed>>} $payload Corp { items: [...] }.
	 * @return array<string, mixed> Răspuns normalizat Client.
	 */
	public function create_products( array $payload ): array {
		$supplier_id = $this->get_supplier_id();

		if ( '' === $supplier_id ) {
			return array(
				'success'     => false,
				'status_code' => 0,
				'data'        => null,
				'error'       => __( 'Supplier ID lipsește din setări.', 'trendyol-sync-for-woocommerce' ),
				'error_type'  => 'config',
			);
		}

		$path = sprintf(
			'/integration/product/sellers/%s/v2/products',
			rawurlencode( $supplier_id )
		);

		return $this->client->post( $path, $payload );
	}

	/**
	 * @return string
	 */
	private function get_supplier_id(): string {
		$stored = $this->settings->get_stored_settings();

		return isset( $stored['supplier_id'] ) ? trim( (string) $stored['supplier_id'] ) : '';
	}
}
