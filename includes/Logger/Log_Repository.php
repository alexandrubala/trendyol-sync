<?php
/**
 * Persistență intrări log în wp_trendyol_logs.
 *
 * @package TrendyolSync\Logger
 */

namespace TrendyolSync\Logger;

use TrendyolSync\Data\Schema;

defined( 'ABSPATH' ) || exit;

/**
 * Class Log_Repository
 */
class Log_Repository {

	/**
	 * @var string
	 */
	private $table;

	/**
	 * Niveluri permise.
	 *
	 * @var string[]
	 */
	private const LEVELS = array( 'debug', 'info', 'warning', 'error' );

	/**
	 * Constructor.
	 */
	public function __construct() {
		$tables      = Schema::get_table_names();
		$this->table = $tables['logs'];
	}

	/**
	 * Inserează o intrare de log.
	 *
	 * @param string               $level   debug|info|warning|error.
	 * @param string               $message Mesaj uman.
	 * @param array<string, mixed> $context Context JSON (product_id, batch_id, etc.).
	 * @return int ID inserat sau 0 la eșec.
	 */
	public function insert( string $level, string $message, array $context = array() ): int {
		global $wpdb;

		$level = $this->normalize_level( $level );

		$inserted = $wpdb->insert(
			$this->table,
			array(
				'level'      => $level,
				'message'    => $message,
				'context'    => empty( $context ) ? null : wp_json_encode( $context ),
				'created_at' => current_time( 'mysql', true ),
			),
			array( '%s', '%s', '%s', '%s' )
		);

		if ( false === $inserted ) {
			return 0;
		}

		return (int) $wpdb->insert_id;
	}

	/**
	 * @param string $level Nivel solicitat.
	 * @return string
	 */
	private function normalize_level( string $level ): string {
		$level = strtolower( trim( $level ) );

		return in_array( $level, self::LEVELS, true ) ? $level : 'info';
	}
}
