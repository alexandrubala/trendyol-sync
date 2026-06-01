<?php
/**
 * Endpoint API: listă branduri Trendyol (getBrands).
 *
 * @package TrendyolSync\API\Endpoints
 */

namespace TrendyolSync\API\Endpoints;

use TrendyolSync\API\Client;
use TrendyolSync\Cache\Transient_Cache;

defined( 'ABSPATH' ) || exit;

/**
 * Class Brands
 */
class Brands {

	private const PATH = '/integration/product/brands';

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
	 * Preia lista de branduri (getBrands) cu paginare.
	 *
	 * @param int  $page      Număr pagină (0-based conform API).
	 * @param int  $size      Număr maxim branduri per pagină.
	 * @param bool $use_cache             Folosește cache transient (implicit true).
	 * @param bool $wait_for_rate_limit   Așteaptă eliberarea slotului de rate limit (sync catalog).
	 * @return array<string, mixed> Răspuns normalizat de la Client.
	 */
	public function get_brands( int $page = 0, int $size = 1000, bool $use_cache = true, bool $wait_for_rate_limit = false ): array {
		$page = max( 0, $page );
		$size = max( 1, min( 1000, $size ) );

		if ( $use_cache ) {
			$cached = $this->cache->get_brands( $page, $size );

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

		$response = $this->client->get(
			self::PATH,
			array(
				'page' => $page,
				'size' => $size,
			),
			$wait_for_rate_limit
		);

		if ( $response['success'] && ! empty( $response['data'] ) ) {
			$this->cache->set_brands( $page, $size, $response['data'] );
		}

		$response['from_cache'] = false;

		return $response;
	}
}
