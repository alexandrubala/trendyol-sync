<?php
/**
 * Uninstall script.
 *
 * Rulează automat când utilizatorul șterge plugin-ul din WP Admin.
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;
defined( 'ABSPATH' ) || exit;

global $wpdb;

// Opțiunea principală de setări.
delete_option( 'trendyol_sync_settings' );

// Șterge toate transient-urile cu prefixul: trendyol_*
$transient_like = $wpdb->esc_like( '_transient_trendyol_' ) . '%';
$timeout_like   = $wpdb->esc_like( '_transient_timeout_trendyol_' ) . '%';

$wpdb->query(
	$wpdb->prepare(
		"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
		$transient_like,
		$timeout_like
	)
);

// Drop tabele custom (cu prefix WordPress).
$tables = array(
	$wpdb->prefix . 'trendyol_sync_jobs',
	$wpdb->prefix . 'trendyol_batches',
	$wpdb->prefix . 'trendyol_logs',
);

foreach ( $tables as $table ) {
	if ( ! is_string( $table ) || '' === $table ) {
		continue;
	}

	// Hardening: asigură-te că nu se introduce SQL nevalid.
	if ( ! preg_match( '/^[A-Za-z0-9_]+$/', $table ) ) {
		continue;
	}

	$wpdb->query( 'DROP TABLE IF EXISTS `' . $table . '`' ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
}

