<?php
/**
 * Repository pentru wp_trendyol_batches.
 *
 * @package TrendyolSync\Data
 */

namespace TrendyolSync\Data;

defined( 'ABSPATH' ) || exit;

/**
 * Class Batch_Repository
 */
class Batch_Repository {

	public const STATUS_PENDING     = 'pending';
	public const STATUS_IN_PROGRESS = 'IN_PROGRESS';
	public const STATUS_COMPLETED   = 'COMPLETED';
	public const STATUS_FAILED      = 'FAILED';

	/**
	 * @var string
	 */
	private $table;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$tables      = Schema::get_table_names();
		$this->table = $tables['batches'];
	}

	/**
	 * Creează un batch după răspunsul createProducts.
	 *
	 * @param int                  $job_id           ID job.
	 * @param string               $batch_request_id batchRequestId Trendyol.
	 * @param array<string, mixed> $request_data     Payload + product_map.
	 * @param array<string, mixed> $response_data    Răspuns API brut.
	 * @return int ID batch sau 0.
	 */
	public function create(
		int $job_id,
		string $batch_request_id,
		array $request_data,
		array $response_data = array()
	): int {
		global $wpdb;

		$inserted = $wpdb->insert(
			$this->table,
			array(
				'job_id'           => max( 0, $job_id ),
				'batch_request_id' => sanitize_text_field( $batch_request_id ),
				'status'           => self::STATUS_IN_PROGRESS,
				'request_json'     => wp_json_encode( $request_data ),
				'response_json'    => wp_json_encode( $response_data ),
				'polled_at'        => null,
				'completed_at'     => null,
			),
			array( '%d', '%s', '%s', '%s', '%s', '%s', '%s' )
		);

		if ( false === $inserted ) {
			return 0;
		}

		return (int) $wpdb->insert_id;
	}

	/**
	 * @param int $batch_id ID batch intern.
	 * @return array<string, mixed>|null
	 */
	public function find( int $batch_id ): ?array {
		global $wpdb;

		if ( $batch_id <= 0 ) {
			return null;
		}

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$this->table} WHERE id = %d",
				$batch_id
			),
			ARRAY_A
		);

		return is_array( $row ) ? $row : null;
	}

	/**
	 * @param int    $job_id ID job.
	 * @param string $status Status batch.
	 * @return int
	 */
	public function count_by_job_and_status( int $job_id, string $status ): int {
		global $wpdb;

		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$this->table} WHERE job_id = %d AND status = %s",
				$job_id,
				$status
			)
		);
	}

	/**
	 * Numără batch-urile încă în așteptare (IN_PROGRESS sau pending).
	 *
	 * @param int $job_id ID job.
	 * @return int
	 */
	public function count_pending_for_job( int $job_id ): int {
		global $wpdb;

		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$this->table}
				WHERE job_id = %d AND status IN (%s, %s)",
				$job_id,
				self::STATUS_IN_PROGRESS,
				self::STATUS_PENDING
			)
		);
	}

	/**
	 * Actualizează statusul și răspunsul după polling.
	 *
	 * @param int                  $batch_id      ID batch.
	 * @param string               $status        Status API.
	 * @param array<string, mixed> $response_data Răspuns GET batch.
	 * @param bool                 $completed     Marchează completed_at.
	 * @return bool
	 */
	public function update_poll_result(
		int $batch_id,
		string $status,
		array $response_data,
		bool $completed = false
	): bool {
		global $wpdb;

		$fields = array(
			'status'        => $status,
			'response_json' => wp_json_encode( $response_data ),
			'polled_at'     => current_time( 'mysql', true ),
		);

		if ( $completed ) {
			$fields['completed_at'] = current_time( 'mysql', true );
		}

		$updated = $wpdb->update(
			$this->table,
			$fields,
			array( 'id' => $batch_id ),
			null,
			array( '%d' )
		);

		return false !== $updated;
	}

	/**
	 * Decodează request_json salvat la creare.
	 *
	 * @param array<string, mixed> $batch Rând batch.
	 * @return array<string, mixed>
	 */
	public function decode_request( array $batch ): array {
		$raw = $batch['request_json'] ?? '';

		if ( ! is_string( $raw ) || '' === $raw ) {
			return array();
		}

		$decoded = json_decode( $raw, true );

		return is_array( $decoded ) ? $decoded : array();
	}
}
