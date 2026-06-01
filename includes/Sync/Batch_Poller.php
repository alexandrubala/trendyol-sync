<?php
/**
 * Polling asincron pentru batchRequestId (getBatchRequestResult).
 *
 * @package TrendyolSync\Sync
 */

namespace TrendyolSync\Sync;

use TrendyolSync\API\Endpoints\Batch;
use TrendyolSync\Data\Batch_Repository;
use TrendyolSync\Data\Sync_Job_Repository;
use TrendyolSync\Logger\Logger;
use TrendyolSync\WooCommerce\Meta_Keys;

defined( 'ABSPATH' ) || exit;

/**
 * Class Batch_Poller
 */
class Batch_Poller {

	public const ACTION_POLL_BATCH = 'trendyol_sync_poll_batch';

	/**
	 * @var Batch
	 */
	private $batch_api;

	/**
	 * @var Batch_Repository
	 */
	private $batches;

	/**
	 * @var Sync_Job_Repository
	 */
	private $jobs;

	/**
	 * @var Logger
	 */
	private $logger;

	/**
	 * @param Batch|null               $batch_api Endpoint batch.
	 * @param Batch_Repository|null    $batches     Repository batch.
	 * @param Sync_Job_Repository|null $jobs        Repository job-uri.
	 * @param Logger|null              $logger      Logger.
	 */
	public function __construct(
		?Batch $batch_api = null,
		?Batch_Repository $batches = null,
		?Sync_Job_Repository $jobs = null,
		?Logger $logger = null
	) {
		$plugin          = trendyol_sync();
		$this->batch_api = $batch_api ?? new Batch( $plugin->api_client(), $plugin->settings() );
		$this->batches   = $batches ?? new Batch_Repository();
		$this->jobs      = $jobs ?? new Sync_Job_Repository();
		$this->logger    = $logger ?? new Logger();
	}

	/**
	 * @return void
	 */
	public static function register_hooks(): void {
		add_action( self::ACTION_POLL_BATCH, array( new self(), 'handle_poll_batch' ), 10, 1 );
	}

	/**
	 * Programează polling după TRENDYOL_SYNC_POLL_INTERVAL secunde.
	 *
	 * @param int $batch_id ID batch intern.
	 * @return void
	 */
	public function schedule_poll( int $batch_id ): void {
		if ( $batch_id <= 0 ) {
			return;
		}

		$timestamp = time() + (int) TRENDYOL_SYNC_POLL_INTERVAL;
		$args      = array( 'batch_id' => $batch_id );

		if ( function_exists( 'as_schedule_single_action' ) ) {
			as_schedule_single_action(
				$timestamp,
				self::ACTION_POLL_BATCH,
				array( $args ),
				TRENDYOL_SYNC_AS_GROUP
			);
			return;
		}

		wp_schedule_single_event( $timestamp, self::ACTION_POLL_BATCH, array( $args ) );
	}

	/**
	 * Callback pentru trendyol_sync_poll_batch.
	 *
	 * @param array<string, mixed>|int $args batch_id sau array cu batch_id.
	 * @return void
	 */
	public function handle_poll_batch( $args ): void {
		$batch_id = 0;

		if ( is_array( $args ) ) {
			$batch_id = (int) ( $args['batch_id'] ?? 0 );
		} elseif ( is_numeric( $args ) ) {
			$batch_id = (int) $args;
		}

		if ( $batch_id <= 0 ) {
			return;
		}

		$batch = $this->batches->find( $batch_id );

		if ( null === $batch ) {
			$this->logger->warning(
				__( 'Batch inexistent la polling.', 'trendyol-sync' ),
				array( 'batch_id' => $batch_id )
			);
			return;
		}

		$batch_request_id = (string) ( $batch['batch_request_id'] ?? '' );
		$job_id           = (int) ( $batch['job_id'] ?? 0 );

		if ( '' === $batch_request_id ) {
			$this->logger->error(
				__( 'batch_request_id lipsă în DB.', 'trendyol-sync' ),
				array( 'batch_id' => $batch_id )
			);
			return;
		}

		try {
			$response = $this->batch_api->get_batch_result( $batch_request_id );
		} catch ( \Throwable $e ) {
			$this->logger->error(
				__( 'Excepție la polling batch.', 'trendyol-sync' ),
				array(
					'batch_id'         => $batch_id,
					'batch_request_id' => $batch_request_id,
					'message'          => $e->getMessage(),
				)
			);
			$this->schedule_poll( $batch_id );
			return;
		}

		$http_code = (int) ( $response['status_code'] ?? 0 );

		if ( empty( $response['success'] ) ) {
			$this->logger->error(
				(string) ( $response['error'] ?? __( 'Eroare la polling batch.', 'trendyol-sync' ) ),
				array(
					'batch_id'         => $batch_id,
					'batch_request_id' => $batch_request_id,
					'http_code'        => $http_code,
					'api_response'     => is_array( $response['data'] ) ? wp_json_encode( $response['data'] ) : '',
				)
			);
			$this->schedule_poll( $batch_id );
			return;
		}

		$data   = is_array( $response['data'] ) ? $response['data'] : array();
		$status = strtoupper( (string) ( $data['status'] ?? Batch_Repository::STATUS_IN_PROGRESS ) );

		if ( Batch_Repository::STATUS_IN_PROGRESS === $status || '' === $status ) {
			$this->batches->update_poll_result( $batch_id, Batch_Repository::STATUS_IN_PROGRESS, $data, false );
			$this->logger->debug(
				__( 'Batch încă în procesare – reprogramare polling.', 'trendyol-sync' ),
				array(
					'batch_id'         => $batch_id,
					'batch_request_id' => $batch_request_id,
					'http_code'        => $http_code,
				)
			);
			$this->schedule_poll( $batch_id );
			return;
		}

		$completed = in_array( $status, array( Batch_Repository::STATUS_COMPLETED, Batch_Repository::STATUS_FAILED ), true );

		$this->batches->update_poll_result( $batch_id, $status, $data, $completed );

		$request_data = $this->batches->decode_request( $batch );
		$product_map  = isset( $request_data['product_map'] ) && is_array( $request_data['product_map'] )
			? $request_data['product_map']
			: array();

		$this->apply_item_results( $data, $product_map, $batch_id, $job_id );

		$this->logger->info(
			__( 'Polling batch finalizat.', 'trendyol-sync' ),
			array(
				'batch_id'         => $batch_id,
				'batch_request_id' => $batch_request_id,
				'status'           => $status,
				'http_code'        => $http_code,
				'api_response'     => wp_json_encode( $data ),
			)
		);

		if ( $job_id > 0 ) {
			$this->maybe_finalize_job( $job_id );
		}
	}

	/**
	 * Actualizează meta produs și loghează erorile per item.
	 *
	 * @param array<string, mixed> $data        Răspuns API batch.
	 * @param array<string, int>   $product_map barcode → product_id.
	 * @param int                  $batch_id    ID batch.
	 * @param int                  $job_id      ID job.
	 * @return void
	 */
	private function apply_item_results( array $data, array $product_map, int $batch_id, int $job_id ): void {
		$items = isset( $data['items'] ) && is_array( $data['items'] ) ? $data['items'] : array();

		if ( empty( $items ) ) {
			return;
		}

		foreach ( $items as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}

			$barcode = $this->extract_barcode_from_item( $item );

			if ( '' === $barcode || ! isset( $product_map[ $barcode ] ) ) {
				continue;
			}

			$product_id     = (int) $product_map[ $barcode ];
			$item_status    = strtoupper( (string) ( $item['status'] ?? '' ) );
			$failure_reasons = isset( $item['failureReasons'] ) && is_array( $item['failureReasons'] )
				? $item['failureReasons']
				: array();

			if ( 'SUCCESS' === $item_status ) {
				update_post_meta( $product_id, Meta_Keys::SYNC_STATUS, Meta_Keys::SYNC_ENABLED );
				Meta_Keys::set_platform_live( $product_id, true );
				Meta_Keys::touch_last_sync_at( $product_id );
				Meta_Keys::set_last_sync_error( $product_id, '' );
				$this->logger->info(
					__( 'Produs sincronizat cu succes pe Trendyol.', 'trendyol-sync' ),
					array(
						'product_id' => $product_id,
						'batch_id'   => $batch_id,
						'barcode'    => $barcode,
						'job_id'     => $job_id,
					)
				);
				continue;
			}

			update_post_meta( $product_id, Meta_Keys::SYNC_STATUS, Meta_Keys::SYNC_ERROR );
			Meta_Keys::set_last_sync_error( $product_id, $this->format_failure_reasons( $failure_reasons ) );

			$this->logger->error(
				__( 'Produs respins de Trendyol în batch.', 'trendyol-sync' ),
				array(
					'product_id'      => $product_id,
					'batch_id'        => $batch_id,
					'barcode'         => $barcode,
					'job_id'          => $job_id,
					'failure_reasons' => wp_json_encode( $failure_reasons ),
					'item_status'     => $item_status,
				)
			);
		}
	}

	/**
	 * @param array<int, mixed> $failure_reasons Motive brute din API.
	 * @return string
	 */
	private function format_failure_reasons( array $failure_reasons ): string {
		$messages = array();

		foreach ( $failure_reasons as $reason ) {
			if ( is_array( $reason ) ) {
				$message = isset( $reason['message'] ) ? trim( (string) $reason['message'] ) : '';

				if ( '' === $message && isset( $reason['failureReason'] ) ) {
					$message = trim( (string) $reason['failureReason'] );
				}

				if ( '' !== $message ) {
					$messages[] = $message;
				}
				continue;
			}

			$message = trim( (string) $reason );

			if ( '' !== $message ) {
				$messages[] = $message;
			}
		}

		if ( empty( $messages ) ) {
			return '';
		}

		return implode( '; ', array_values( array_unique( $messages ) ) );
	}

	/**
	 * Extrage barcode din structura item Trendyol (ProductCreate sau Inventory).
	 *
	 * @param array<string, mixed> $item Element items[] din răspuns batch.
	 * @return string
	 */
	private function extract_barcode_from_item( array $item ): string {
		if ( ! empty( $item['barcode'] ) ) {
			return (string) $item['barcode'];
		}

		if ( isset( $item['requestItem'] ) && is_array( $item['requestItem'] ) ) {
			$request = $item['requestItem'];

			if ( ! empty( $request['barcode'] ) ) {
				return (string) $request['barcode'];
			}
		}

		return '';
	}

	/**
	 * Finalizează job-ul când nu mai există batch-uri în așteptare.
	 *
	 * @param int $job_id ID job.
	 * @return void
	 */
	private function maybe_finalize_job( int $job_id ): void {
		if ( $this->batches->count_pending_for_job( $job_id ) > 0 ) {
			return;
		}

		$job = $this->jobs->find( $job_id );

		if ( null === $job ) {
			return;
		}

		$failed = (int) ( $job['failed'] ?? 0 );

		$status = $failed > 0
			? Sync_Job_Repository::STATUS_COMPLETED
			: Sync_Job_Repository::STATUS_COMPLETED;

		$this->jobs->update_status( $job_id, $status );

		$this->logger->info(
			__( 'Job de sincronizare finalizat (după polling batch).', 'trendyol-sync' ),
			array(
				'job_id' => $job_id,
				'status' => $status,
			)
		);
	}
}
