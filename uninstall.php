<?php
/**
 * Uninstall script.
 *
 * Runs when the user deletes the plugin from WP Admin.
 *
 * @package TrendyolSync
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;
defined( 'ABSPATH' ) || exit;

global $wpdb;

$settings   = get_option( 'trendyol_sync_settings', array() );
$purge_all  = is_array( $settings )
	&& ! empty( $settings['purge_data_on_uninstall'] )
	&& 'yes' === $settings['purge_data_on_uninstall'];

delete_option( 'trendyol_sync_settings' );
delete_option( 'trendyol_sync_category_map' );
delete_option( 'trendyol_sync_brand_map' );
delete_option( 'trendyol_sync_category_attribute_defaults' );
delete_option( 'trendyol_sync_wc_attribute_map' );
delete_option( 'trendyol_sync_tax_class_map' );
delete_option( 'trendyol_sync_db_version' );

$transient_like = $wpdb->esc_like( '_transient_trendyol_' ) . '%';
$timeout_like   = $wpdb->esc_like( '_transient_timeout_trendyol_' ) . '%';

$wpdb->query(
	$wpdb->prepare(
		"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
		$transient_like,
		$timeout_like
	)
);

if ( ! $purge_all ) {
	return;
}

$tables = array(
	$wpdb->prefix . 'trendyol_sync_jobs',
	$wpdb->prefix . 'trendyol_batches',
	$wpdb->prefix . 'trendyol_logs',
);

foreach ( $tables as $table ) {
	if ( ! is_string( $table ) || '' === $table ) {
		continue;
	}

	if ( ! preg_match( '/^[A-Za-z0-9_]+$/', $table ) ) {
		continue;
	}

	$wpdb->query( 'DROP TABLE IF EXISTS `' . $table . '`' ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
}
