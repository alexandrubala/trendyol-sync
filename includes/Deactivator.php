<?php
/**
 * Logică la dezactivarea plugin-ului.
 *
 * @package TrendyolSync
 */

namespace TrendyolSync;

defined( 'ABSPATH' ) || exit;

/**
 * Class Deactivator
 */
class Deactivator {

	/**
	 * Hook-uri Action Scheduler gestionate de plugin (faze ulterioare).
	 *
	 * @var string[]
	 */
	private const AS_HOOKS = array(
		'trendyol_sync_push_chunk',
		'trendyol_sync_poll_batch',
		'trendyol_sync_finalize_job',
	);

	/**
	 * Rulează la register_deactivation_hook.
	 *
	 * @return void
	 */
	public static function deactivate(): void {
		self::cancel_action_scheduler_jobs();
		self::clear_wp_cron_fallbacks();
		flush_rewrite_rules();
	}

	/**
	 * Anulează acțiunile Action Scheduler din grupul plugin-ului.
	 *
	 * @return void
	 */
	private static function cancel_action_scheduler_jobs(): void {
		if ( ! function_exists( 'as_unschedule_all_actions' ) ) {
			return;
		}

		as_unschedule_all_actions( '', array(), TRENDYOL_SYNC_AS_GROUP );

		foreach ( self::AS_HOOKS as $hook ) {
			as_unschedule_all_actions( $hook, array(), TRENDYOL_SYNC_AS_GROUP );
		}
	}

	/**
	 * Curăță evenimentele WP-Cron de rezervă (dacă au fost programate).
	 *
	 * @return void
	 */
	private static function clear_wp_cron_fallbacks(): void {
		foreach ( self::AS_HOOKS as $hook ) {
			wp_clear_scheduled_hook( $hook );
		}

		wp_clear_scheduled_hook( 'trendyol_sync_cron_push' );
	}
}
