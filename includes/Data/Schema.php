<?php
/**
 * Definiții schema DB pentru tabelele custom ale plugin-ului.
 *
 * @package TrendyolSync\Data
 */

namespace TrendyolSync\Data;

defined( 'ABSPATH' ) || exit;

/**
 * Class Schema
 */
class Schema {

	/**
	 * Returnează numele tabelelor cu prefix WordPress.
	 *
	 * @return array<string, string>
	 */
	public static function get_table_names(): array {
		global $wpdb;

		return array(
			'jobs'    => $wpdb->prefix . 'trendyol_sync_jobs',
			'batches' => $wpdb->prefix . 'trendyol_batches',
			'logs'    => $wpdb->prefix . 'trendyol_logs',
		);
	}

	/**
	 * Creează sau actualizează tabelele via dbDelta.
	 *
	 * @return void
	 */
	public static function create_tables(): void {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$tables   = self::get_table_names();
		$charset  = $wpdb->get_charset_collate();

		$sql_jobs = "CREATE TABLE {$tables['jobs']} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			status varchar(20) NOT NULL DEFAULT 'pending',
			total int(11) unsigned NOT NULL DEFAULT 0,
			processed int(11) unsigned NOT NULL DEFAULT 0,
			failed int(11) unsigned NOT NULL DEFAULT 0,
			created_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
			updated_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
			created_by bigint(20) unsigned NOT NULL DEFAULT 0,
			PRIMARY KEY  (id),
			KEY status (status),
			KEY created_at (created_at)
		) $charset;";

		$sql_batches = "CREATE TABLE {$tables['batches']} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			job_id bigint(20) unsigned NOT NULL DEFAULT 0,
			batch_request_id varchar(255) NOT NULL DEFAULT '',
			status varchar(20) NOT NULL DEFAULT 'pending',
			request_json longtext NULL,
			response_json longtext NULL,
			polled_at datetime NULL,
			completed_at datetime NULL,
			PRIMARY KEY  (id),
			KEY job_id (job_id),
			KEY batch_request_id (batch_request_id),
			KEY status (status)
		) $charset;";

		$sql_logs = "CREATE TABLE {$tables['logs']} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			level varchar(20) NOT NULL DEFAULT 'info',
			message text NOT NULL,
			context longtext NULL,
			created_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
			PRIMARY KEY  (id),
			KEY level (level),
			KEY created_at (created_at)
		) $charset;";

		dbDelta( $sql_jobs );
		dbDelta( $sql_batches );
		dbDelta( $sql_logs );

		update_option( 'trendyol_sync_db_version', TRENDYOL_SYNC_VERSION );
	}
}
