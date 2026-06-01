<?php
/**
 * Endpoint API: atribute categorie Trendyol.
 *
 * @package TrendyolSync\API\Endpoints
 */

namespace TrendyolSync\API\Endpoints;

use TrendyolSync\API\Client;
use TrendyolSync\Cache\Transient_Cache;

defined( 'ABSPATH' ) || exit;

/**
 * Class Category_Attributes
 */
class Category_Attributes {

	/**
	 * @var Client
	 */
	private $client;

	/**
	 * @var Transient_Cache
	 */
	private $cache;

	/**
	 * @param Client          $client Client API.
	 * @param Transient_Cache $cache Cache transient.
	 */
	public function __construct( Client $client, Transient_Cache $cache ) {
		$this->client = $client;
		$this->cache  = $cache;
	}

	/**
	 * @param int  $category_id ID categorie.
	 * @param bool $use_cache   Folosește cache.
	 * @return array<string, mixed>
	 */
	public function get_attributes( int $category_id, bool $use_cache = true ): array {
		$category_id = absint( $category_id );

		if ( $category_id <= 0 ) {
			return array(
				'success'     => false,
				'status_code' => 400,
				'data'        => null,
				'error'       => __( 'ID categorie invalid.', 'trendyol-sync' ),
				'error_type'  => 'validation',
			);
		}

		if ( $use_cache ) {
			$cached = $this->cache->get_category_attributes( $category_id );
			if ( null !== $cached ) {
				return array(
					'success'     => true,
					'status_code' => 200,
					'data'        => $cached,
					'error'       => null,
					'error_type'  => null,
					'from_cache'  => true,
				);
			}
		}

		$response = $this->client->get( '/integration/product/product-categories/' . $category_id . '/attributes' );

		if ( ! empty( $response['success'] ) && ! empty( $response['data'] ) && is_array( $response['data'] ) ) {
			$this->cache->set_category_attributes( $category_id, $response['data'] );
		}

		$response['from_cache'] = false;

		return $response;
	}
}
