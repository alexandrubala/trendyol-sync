<?php
/**
 * Repository pentru wp_trendyol_sync_jobs.
 *
 * @package TrendyolSync\Data
 */

namespace TrendyolSync\Data;

defined( 'ABSPATH' ) || exit;

/**
 * Class Sync_Job_Repository
 */
class Sync_Job_Repository {

	public const STATUS_PENDING   = 'pending';
	public const STATUS_RUNNING   = 'running';
	public const STATUS_COMPLETED = 'completed';
	public const STATUS_FAILED    = 'failed';

	/**
	 * @var string
	 */
	private $table;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$tables      = Schema::get_table_names();
		$this->table = $tables['jobs'];
	}

	/**
	 * Creează un job nou.
	 *
	 * @param int $total       Număr total produse de sincronizat.
	 * @param int $created_by  ID utilizator WordPress.
	 * @return int ID job sau 0.
	 */
	public function create( int $total, int $created_by = 0 ): int {
		global $wpdb;

		$now = current_time( 'mysql', true );

		$inserted = $wpdb->insert(
			$this->table,
			array(
				'status'     => self::STATUS_PENDING,
				'total'      => max( 0, $total ),
				'processed'  => 0,
				'failed'     => 0,
				'created_at' => $now,
				'updated_at' => $now,
				'created_by' => max( 0, $created_by ),
			),
			array( '%s', '%d', '%d', '%d', '%s', '%s', '%d' )
		);

		if ( false === $inserted ) {
			return 0;
		}

		return (int) $wpdb->insert_id;
	}

	/**
	 * @param int $job_id ID job.
	 * @return array<string, mixed>|null
	 */
	public function find( int $job_id ): ?array {
		global $wpdb;

		if ( $job_id <= 0 ) {
			return null;
		}

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$this->table} WHERE id = %d",
				$job_id
			),
			ARRAY_A
		);

		return is_array( $row ) ? $row : null;
	}

	/**
	 * Ultimul job creat (pentru status fără job_id explicit).
	 *
	 * @return array<string, mixed>|null
	 */
	public function find_latest(): ?array {
		global $wpdb;

		$row = $wpdb->get_row(
			"SELECT * FROM {$this->table} ORDER BY id DESC LIMIT 1",
			ARRAY_A
		);

		return is_array( $row ) ? $row : null;
	}

	/**
	 * @param int    $job_id ID job.
	 * @param string $status Status nou.
	 * @return bool
	 */
	public function update_status( int $job_id, string $status ): bool {
		return $this->update(
			$job_id,
			array(
				'status' => $status,
			)
		);
	}

	/**
	 * Incrementează contorul de produse procesate cu succes.
	 *
	 * @param int $job_id ID job.
	 * @param int $count  Număr de adăugat.
	 * @return bool
	 */
	public function increment_processed( int $job_id, int $count = 1 ): bool {
		global $wpdb;

		if ( $job_id <= 0 || $count <= 0 ) {
			return false;
		}

		$updated = $wpdb->query(
			$wpdb->prepare(
				"UPDATE {$this->table}
				SET processed = processed + %d, updated_at = %s
				WHERE id = %d",
				$count,
				current_time( 'mysql', true ),
				$job_id
			)
		);

		return false !== $updated;
	}

	/**
	 * Incrementează contorul de eșecuri.
	 *
	 * @param int $job_id ID job.
	 * @param int $count  Număr de adăugat.
	 * @return bool
	 */
	public function increment_failed( int $job_id, int $count = 1 ): bool {
		global $wpdb;

		if ( $job_id <= 0 || $count <= 0 ) {
			return false;
		}

		$updated = $wpdb->query(
			$wpdb->prepare(
				"UPDATE {$this->table}
				SET failed = failed + %d, updated_at = %s
				WHERE id = %d",
				$count,
				current_time( 'mysql', true ),
				$job_id
			)
		);

		return false !== $updated;
	}

	/**
	 * Setează totalul și eșecurile inițiale (validare pre-flight).
	 *
	 * @param int $job_id ID job.
	 * @param int $total  Total valid.
	 * @param int $failed Eșecuri validare.
	 * @return bool
	 */
	public function set_totals( int $job_id, int $total, int $failed = 0 ): bool {
		return $this->update(
			$job_id,
			array(
				'total'   => max( 0, $total ),
				'failed'  => max( 0, $failed ),
				'status'  => self::STATUS_RUNNING,
			)
		);
	}

	/**
	 * Calculează procentul de progres (procesate / total).
	 *
	 * @param array<string, mixed> $job Rând job.
	 * @return float 0–100.
	 */
	public function get_progress_percent( array $job ): float {
		$total = (int) ( $job['total'] ?? 0 );

		if ( $total <= 0 ) {
			return 0.0;
		}

		$processed = (int) ( $job['processed'] ?? 0 );

		return min( 100.0, round( ( $processed / $total ) * 100, 1 ) );
	}

	/**
	 * @param int                  $job_id ID job.
	 * @param array<string, mixed> $fields Câmpuri de actualizat.
	 * @return bool
	 */
	private function update( int $job_id, array $fields ): bool {
		global $wpdb;

		if ( $job_id <= 0 || empty( $fields ) ) {
			return false;
		}

		$fields['updated_at'] = current_time( 'mysql', true );

		$updated = $wpdb->update(
			$this->table,
			$fields,
			array( 'id' => $job_id ),
			null,
			array( '%d' )
		);

		return false !== $updated;
	}
}
