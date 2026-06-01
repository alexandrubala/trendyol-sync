<?php
/**
 * Execută push-ul unui chunk către createProducts v2.
 *
 * @package TrendyolSync\Sync
 */

namespace TrendyolSync\Sync;

use TrendyolSync\API\Endpoints\Products;
use TrendyolSync\Data\Batch_Repository;
use TrendyolSync\Data\Sync_Job_Repository;
use TrendyolSync\Logger\Logger;
use TrendyolSync\WooCommerce\Meta_Keys;

defined( 'ABSPATH' ) || exit;

/**
 * Class Sync_Runner
 */
class Sync_Runner {

	/**
	 * @var Products
	 */
	private $products_api;

	/**
	 * @var Sync_Job_Repository
	 */
	private $jobs;

	/**
	 * @var Batch_Repository
	 */
	private $batches;

	/**
	 * @var Logger
	 */
	private $logger;

	/**
	 * @var Batch_Poller
	 */
	private $poller;

	/**
	 * @param Products|null            $products_api Endpoint produse.
	 * @param Sync_Job_Repository|null $jobs         Repository job-uri.
	 * @param Batch_Repository|null    $batches      Repository batch-uri.
	 * @param Logger|null              $logger       Logger.
	 * @param Batch_Poller|null        $poller       Poller batch.
	 */
	public function __construct(
		?Products $products_api = null,
		?Sync_Job_Repository $jobs = null,
		?Batch_Repository $batches = null,
		?Logger $logger = null,
		?Batch_Poller $poller = null
	) {
		$plugin             = trendyol_sync();
		$this->products_api = $products_api ?? new Products( $plugin->api_client(), $plugin->settings() );
		$this->jobs         = $jobs ?? new Sync_Job_Repository();
		$this->batches      = $batches ?? new Batch_Repository();
		$this->logger       = $logger ?? new Logger();
		$this->poller       = $poller ?? new Batch_Poller();
	}

	/**
	 * Înregistrează handler-ul Action Scheduler / WP-Cron.
	 *
	 * @return void
	 */
	public static function register_hooks(): void {
		add_action( Sync_Queue::ACTION_PUSH_CHUNK, array( new self(), 'handle_push_chunk' ), 10, 1 );
	}

	/**
	 * Callback pentru trendyol_sync_push_chunk.
	 *
	 * @param array<string, mixed> $args job_id, chunk_index, items, product_map.
	 * @return void
	 */
	public function handle_push_chunk( $args ): void {
		if ( ! is_array( $args ) ) {
			return;
		}

		$job_id      = (int) ( $args['job_id'] ?? 0 );
		$chunk_index = (int) ( $args['chunk_index'] ?? 0 );
		$items       = isset( $args['items'] ) && is_array( $args['items'] ) ? $args['items'] : array();
		$product_map = isset( $args['product_map'] ) && is_array( $args['product_map'] ) ? $args['product_map'] : array();

		if ( $job_id <= 0 || empty( $items ) ) {
			$this->logger->warning(
				__( 'Chunk invalid ignorat.', 'trendyol-sync' ),
				array(
					'job_id'      => $job_id,
					'chunk_index' => $chunk_index,
				)
			);
			return;
		}

		$payload = array( 'items' => $items );

		try {
			$response = $this->products_api->create_products( $payload );
		} catch ( \Throwable $e ) {
			$this->handle_chunk_failure(
				$job_id,
				$chunk_index,
				count( $items ),
				$product_map,
				0,
				$e->getMessage(),
				null
			);
			return;
		}

		$http_code = (int) ( $response['status_code'] ?? 0 );

		if ( empty( $response['success'] ) ) {
			$this->handle_chunk_failure(
				$job_id,
				$chunk_index,
				count( $items ),
				$product_map,
				$http_code,
				(string) ( $response['error'] ?? __( 'Eroare API necunoscută.', 'trendyol-sync' ) ),
				$response['data'] ?? null
			);
			return;
		}

		$data             = is_array( $response['data'] ) ? $response['data'] : array();
		$batch_request_id = (string) ( $data['batchRequestId'] ?? '' );

		if ( '' === $batch_request_id ) {
			$this->handle_chunk_failure(
				$job_id,
				$chunk_index,
				count( $items ),
				$product_map,
				$http_code,
				__( 'Răspuns API fără batchRequestId.', 'trendyol-sync' ),
				$data
			);
			return;
		}

		$batch_id = $this->batches->create(
			$job_id,
			$batch_request_id,
			array(
				'payload'     => $payload,
				'product_map' => $product_map,
			),
			$data
		);

		$this->jobs->increment_processed( $job_id, count( $items ) );

		$this->logger->info(
			__( 'Chunk trimis cu succes către Trendyol.', 'trendyol-sync' ),
			array(
				'job_id'           => $job_id,
				'chunk_index'      => $chunk_index,
				'batch_id'         => $batch_id,
				'batch_request_id' => $batch_request_id,
				'http_code'        => $http_code,
				'api_response'     => wp_json_encode( $data ),
			)
		);

		if ( $batch_id > 0 ) {
			$this->poller->schedule_poll( $batch_id );
		}

		$this->maybe_finalize_job( $job_id );
	}

	/**
	 * @param int                  $job_id       ID job.
	 * @param int                  $chunk_index  Index chunk.
	 * @param int                  $item_count   Număr produse în chunk.
	 * @param array<string, int>   $product_map  Mapare barcode → product_id.
	 * @param int                  $http_code    Cod HTTP.
	 * @param string               $message      Mesaj eroare.
	 * @param mixed                $api_data     Date API.
	 * @return void
	 */
	private function handle_chunk_failure(
		int $job_id,
		int $chunk_index,
		int $item_count,
		array $product_map,
		int $http_code,
		string $message,
		$api_data
	): void {
		$this->jobs->increment_failed( $job_id, $item_count );

		foreach ( $product_map as $product_id ) {
			update_post_meta( (int) $product_id, Meta_Keys::SYNC_STATUS, Meta_Keys::SYNC_ERROR );
			Meta_Keys::set_last_sync_error( (int) $product_id, $message );
		}

		$this->logger->error(
			$message,
			array(
				'job_id'       => $job_id,
				'chunk_index'  => $chunk_index,
				'http_code'    => $http_code,
				'api_response' => is_array( $api_data ) ? wp_json_encode( $api_data ) : (string) $api_data,
			)
		);

		$this->maybe_finalize_job( $job_id );
	}

	/**
	 * Marchează job-ul ca finalizat dacă toate chunk-urile au fost procesate.
	 *
	 * @param int $job_id ID job.
	 * @return void
	 */
	private function maybe_finalize_job( int $job_id ): void {
		$job = $this->jobs->find( $job_id );

		if ( null === $job ) {
			return;
		}

		$total     = (int) ( $job['total'] ?? 0 );
		$processed = (int) ( $job['processed'] ?? 0 );
		$failed    = (int) ( $job['failed'] ?? 0 );

		if ( ( $processed + $failed ) < $total ) {
			return;
		}

		if ( $this->batches->count_pending_for_job( $job_id ) > 0 ) {
			return;
		}

		$status = $failed > 0 && $processed === 0
			? Sync_Job_Repository::STATUS_FAILED
			: Sync_Job_Repository::STATUS_COMPLETED;

		$this->jobs->update_status( $job_id, $status );

		$this->logger->info(
			__( 'Job de sincronizare finalizat (push).', 'trendyol-sync' ),
			array(
				'job_id'    => $job_id,
				'status'    => $status,
				'processed' => $processed,
				'failed'    => $failed,
			)
		);
	}
}
