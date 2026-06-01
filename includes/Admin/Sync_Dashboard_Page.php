<?php
/**
 * Dashboard simplu pentru coada de sincronizare și loguri.
 *
 * @package TrendyolSync\Admin
 */

namespace TrendyolSync\Admin;

use TrendyolSync\Data\Sync_Job_Repository;
use TrendyolSync\Logger\Log_Repository;

defined( 'ABSPATH' ) || exit;

/**
 * Class Sync_Dashboard_Page
 */
class Sync_Dashboard_Page {

	public const PAGE_SLUG = 'trendyol-sync-queue';

	/**
	 * @var Sync_Job_Repository
	 */
	private $jobs;

	/**
	 * @var Log_Repository
	 */
	private $logs;

	/**
	 * @param Sync_Job_Repository|null $jobs Repository job-uri.
	 * @param Log_Repository|null      $logs Repository log-uri.
	 */
	public function __construct( ?Sync_Job_Repository $jobs = null, ?Log_Repository $logs = null ) {
		$this->jobs = $jobs ?? new Sync_Job_Repository();
		$this->logs = $logs ?? new Log_Repository();
	}

	/**
	 * @return void
	 */
	public function register_hooks(): void {
		// Placeholder pentru extensii viitoare.
	}

	/**
	 * @return void
	 */
	public function render(): void {
		if ( ! current_user_can( TRENDYOL_SYNC_CAPABILITY ) ) {
			wp_die( esc_html__( 'Nu ai permisiunea de a accesa această pagină.', 'trendyol-sync-for-woocommerce' ) );
		}

		$latest_job = $this->jobs->find_latest();
		$logs       = $this->get_recent_logs();
		?>
		<div class="wrap trendyol-sync-settings-wrap">
			<h1><?php esc_html_e( 'Trendyol Sync Queue', 'trendyol-sync-for-woocommerce' ); ?></h1>
			<p class="description"><?php esc_html_e( 'Pornește sincronizarea produselor și urmărește starea job-ului curent.', 'trendyol-sync-for-woocommerce' ); ?></p>
			<p>
				<button id="trendyol-start-sync" class="button button-primary"><?php esc_html_e( 'Pornește sincronizarea', 'trendyol-sync-for-woocommerce' ); ?></button>
				<span id="trendyol-sync-status" class="trendyol-connection-status" role="status" aria-live="polite"></span>
			</p>

			<h2><?php esc_html_e( 'Ultimul job', 'trendyol-sync-for-woocommerce' ); ?></h2>
			<?php if ( is_array( $latest_job ) ) : ?>
				<table class="widefat striped">
					<tbody>
						<tr><th><?php esc_html_e( 'ID', 'trendyol-sync-for-woocommerce' ); ?></th><td><?php echo esc_html( (string) $latest_job['id'] ); ?></td></tr>
						<tr><th><?php esc_html_e( 'Status', 'trendyol-sync-for-woocommerce' ); ?></th><td><?php echo esc_html( (string) $latest_job['status'] ); ?></td></tr>
						<tr><th><?php esc_html_e( 'Total', 'trendyol-sync-for-woocommerce' ); ?></th><td><?php echo esc_html( (string) $latest_job['total'] ); ?></td></tr>
						<tr><th><?php esc_html_e( 'Procesate', 'trendyol-sync-for-woocommerce' ); ?></th><td><?php echo esc_html( (string) $latest_job['processed'] ); ?></td></tr>
						<tr><th><?php esc_html_e( 'Eșuate', 'trendyol-sync-for-woocommerce' ); ?></th><td><?php echo esc_html( (string) $latest_job['failed'] ); ?></td></tr>
						<tr><th><?php esc_html_e( 'Actualizat', 'trendyol-sync-for-woocommerce' ); ?></th><td><?php echo esc_html( (string) $latest_job['updated_at'] ); ?></td></tr>
					</tbody>
				</table>
			<?php else : ?>
				<p><?php esc_html_e( 'Nu există job-uri încă.', 'trendyol-sync-for-woocommerce' ); ?></p>
			<?php endif; ?>

			<h2><?php esc_html_e( 'Loguri recente', 'trendyol-sync-for-woocommerce' ); ?></h2>
			<table class="widefat striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Data', 'trendyol-sync-for-woocommerce' ); ?></th>
						<th><?php esc_html_e( 'Level', 'trendyol-sync-for-woocommerce' ); ?></th>
						<th><?php esc_html_e( 'Mesaj', 'trendyol-sync-for-woocommerce' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( ! empty( $logs ) ) : ?>
						<?php foreach ( $logs as $row ) : ?>
							<tr>
								<td><?php echo esc_html( (string) $row['created_at'] ); ?></td>
								<td><?php echo esc_html( (string) $row['level'] ); ?></td>
								<td><?php echo esc_html( (string) $row['message'] ); ?></td>
							</tr>
						<?php endforeach; ?>
					<?php else : ?>
						<tr><td colspan="3"><?php esc_html_e( 'Nu există loguri.', 'trendyol-sync-for-woocommerce' ); ?></td></tr>
					<?php endif; ?>
				</tbody>
			</table>
		</div>
		<?php
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	private function get_recent_logs(): array {
		global $wpdb;
		$tables = \TrendyolSync\Data\Schema::get_table_names();

		$rows = $wpdb->get_results(
			"SELECT id, level, message, created_at FROM {$tables['logs']} ORDER BY id DESC LIMIT 20",
			ARRAY_A
		);

		return is_array( $rows ) ? $rows : array();
	}
}
