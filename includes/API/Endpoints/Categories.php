<?php
/**
 * Endpoint API: arbore categorii Trendyol (getCategoryTree).
 *
 * @package TrendyolSync\API\Endpoints
 */

namespace TrendyolSync\API\Endpoints;

use TrendyolSync\API\Client;
use TrendyolSync\Cache\Transient_Cache;

defined( 'ABSPATH' ) || exit;

/**
 * Class Categories
 */
class Categories {

	private const PATH = '/integration/product/product-categories';

	/**
	 * @var Client
	 */
	private $client;

	/**
	 * @var Transient_Cache
	 */
	private $cache;

	/**
	 * @param Client          $client Client HTTP.
	 * @param Transient_Cache $cache  Strat cache transient.
	 */
	public function __construct( Client $client, Transient_Cache $cache ) {
		$this->client = $client;
		$this->cache  = $cache;
	}

	/**
	 * Preia arborele de categorii Trendyol (getCategoryTree).
	 *
	 * @param bool $use_cache Folosește cache-ul transient (implicit true).
	 * @return array<string, mixed> Răspuns normalizat de la Client.
	 */
	public function get_category_tree( bool $use_cache = true ): array {
		if ( $use_cache ) {
			$cached = $this->cache->get_category_tree();

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

		$response = $this->client->get( self::PATH );

		if ( $response['success'] && ! empty( $response['data'] ) ) {
			$this->cache->set_category_tree( $response['data'] );
		}

		$response['from_cache'] = false;

		return $response;
	}
}
