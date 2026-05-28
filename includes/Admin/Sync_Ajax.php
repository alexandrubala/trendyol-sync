<?php
/**
 * AJAX endpoints pentru pornire sincronizare și status job.
 *
 * @package TrendyolSync\Admin
 */

namespace TrendyolSync\Admin;

use TrendyolSync\Data\Sync_Job_Repository;
use TrendyolSync\Sync\Sync_Queue;

defined( 'ABSPATH' ) || exit;

/**
 * Class Sync_Ajax
 */
class Sync_Ajax {

	public const ACTION_START_SYNC = 'trendyol_start_sync';
	public const ACTION_STATUS     = 'trendyol_sync_status';
	public const NONCE_ACTION      = 'trendyol_sync_ajax';

	/**
	 * @var Sync_Queue
	 */
	private $queue;

	/**
	 * @var Sync_Job_Repository
	 */
	private $jobs;

	/**
	 * @param Sync_Queue|null          $queue Coada de sincronizare.
	 * @param Sync_Job_Repository|null $jobs  Repository job-uri.
	 */
	public function __construct( ?Sync_Queue $queue = null, ?Sync_Job_Repository $jobs = null ) {
		$this->queue = $queue ?? new Sync_Queue();
		$this->jobs  = $jobs ?? new Sync_Job_Repository();
	}

	/**
	 * @return void
	 */
	public function register_hooks(): void {
		add_action( 'wp_ajax_' . self::ACTION_START_SYNC, array( $this, 'start_sync' ) );
		add_action( 'wp_ajax_' . self::ACTION_STATUS, array( $this, 'sync_status' ) );
	}

	/**
	 * AJAX: pornește job-ul și programează chunk-urile în fundal.
	 *
	 * @return void
	 */
	public function start_sync(): void {
		check_ajax_referer( self::NONCE_ACTION, 'nonce' );

		if ( ! current_user_can( TRENDYOL_SYNC_CAPABILITY ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'Nu ai permisiunea de a porni sincronizarea.', 'trendyol-sync' ),
				),
				403
			);
		}

		$result = $this->queue->start();

		if ( ! empty( $result['success'] ) ) {
			wp_send_json_success( $result );
		}

		wp_send_json_error(
			array(
				'message' => (string) ( $result['message'] ?? __( 'Nu s-a putut porni sincronizarea.', 'trendyol-sync' ) ),
				'job_id'  => (int) ( $result['job_id'] ?? 0 ),
			)
		);
	}

	/**
	 * AJAX: întoarce statusul curent al job-ului.
	 *
	 * @return void
	 */
	public function sync_status(): void {
		check_ajax_referer( self::NONCE_ACTION, 'nonce' );

		if ( ! current_user_can( TRENDYOL_SYNC_CAPABILITY ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'Nu ai permisiunea de a citi statusul sincronizării.', 'trendyol-sync' ),
				),
				403
			);
		}

		$job_id = isset( $_POST['job_id'] ) ? (int) wp_unslash( (string) $_POST['job_id'] ) : 0;
		$job    = $job_id > 0 ? $this->jobs->find( $job_id ) : $this->jobs->find_latest();

		if ( null === $job ) {
			wp_send_json_success(
				array(
					'has_job' => false,
					'message' => __( 'Nu există niciun job de sincronizare.', 'trendyol-sync' ),
				)
			);
		}

		$progress = $this->jobs->get_progress_percent( $job );

		wp_send_json_success(
			array(
				'has_job'   => true,
				'job_id'    => (int) $job['id'],
				'status'    => (string) $job['status'],
				'total'     => (int) $job['total'],
				'processed' => (int) $job['processed'],
				'failed'    => (int) $job['failed'],
				'progress'  => $progress,
			)
		);
	}
}
