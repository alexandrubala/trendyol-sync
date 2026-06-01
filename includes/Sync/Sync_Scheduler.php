<?php
/**
 * Programare sincronizare automată pe interval WP-Cron.
 *
 * @package TrendyolSync\Sync
 */

namespace TrendyolSync\Sync;

defined( 'ABSPATH' ) || exit;

/**
 * Class Sync_Scheduler
 */
class Sync_Scheduler {

	public const CRON_HOOK = 'trendyol_sync_scheduled_start';

	/**
	 * @return void
	 */
	public static function register_hooks(): void {
		add_action( self::CRON_HOOK, array( __CLASS__, 'run_scheduled_sync' ) );
		add_action( 'init', array( __CLASS__, 'maybe_reschedule' ), 30 );
	}

	/**
	 * @return void
	 */
	public static function run_scheduled_sync(): void {
		$queue = new Sync_Queue();
		$queue->start();
	}

	/**
	 * @return void
	 */
	public static function maybe_reschedule(): void {
		$settings = trendyol_sync()->settings()->get_stored_settings();
		$interval = isset( $settings['scheduled_sync_interval'] ) ? (string) $settings['scheduled_sync_interval'] : 'none';
		$current  = wp_get_schedule( self::CRON_HOOK );

		if ( 'none' === $interval || '' === $interval ) {
			if ( $current ) {
				wp_clear_scheduled_hook( self::CRON_HOOK );
			}
			return;
		}

		if ( $current !== $interval ) {
			wp_clear_scheduled_hook( self::CRON_HOOK );
			wp_schedule_event( time() + 60, $interval, self::CRON_HOOK );
		}
	}
}
