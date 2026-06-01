<?php
/**
 * Pagina de setări din WP Admin (tab-uri Credentials / Environment).
 *
 * @package TrendyolSync\Admin
 */

namespace TrendyolSync\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Class Settings_Page
 */
class Settings_Page {

	/**
	 * @var Settings
	 */
	private $settings;

	/**
	 * @param Settings $settings Handler Settings API.
	 */
	public function __construct( Settings $settings ) {
		$this->settings = $settings;
	}

	/**
	 * Renderează pagina de setări.
	 *
	 * @return void
	 */
	public function render(): void {
		if ( ! current_user_can( TRENDYOL_SYNC_CAPABILITY ) ) {
			wp_die( esc_html__( 'Nu ai permisiunea de a accesa această pagină.', 'trendyol-sync' ) );
		}

		$active_tab = $this->settings->get_active_tab();
		$page_slug  = $this->get_settings_page_slug( $active_tab );

		?>
		<div class="wrap trendyol-sync-settings-wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

			<?php $this->render_tabs( $active_tab ); ?>

			<form method="post" action="options.php" class="trendyol-sync-settings-form">
				<?php
				settings_fields( 'trendyol_sync_settings_group' );
				do_settings_sections( $page_slug );
				submit_button( __( 'Salvează setările', 'trendyol-sync' ) );
				?>

				<?php if ( Settings::TAB_CREDENTIALS === $active_tab ) : ?>
					<hr class="trendyol-sync-settings-divider" />
					<h2><?php esc_html_e( 'Test conexiune API', 'trendyol-sync' ); ?></h2>
					<p class="description">
						<?php esc_html_e( 'Verifică dacă credențialele și mediul selectat permit accesul la API Trendyol.', 'trendyol-sync' ); ?>
					</p>
					<p>
						<button
							type="button"
							id="trendyol-check-connection"
							class="button button-secondary"
							<?php echo $this->settings->has_credentials() ? '' : ' disabled'; ?>
						>
							<?php esc_html_e( 'Check API Status', 'trendyol-sync' ); ?>
						</button>
						<span id="trendyol-connection-status" class="trendyol-connection-status" role="status" aria-live="polite"></span>
					</p>

					<hr class="trendyol-sync-settings-divider" />
					<h2><?php esc_html_e( 'Catalog Trendyol', 'trendyol-sync' ); ?></h2>
					<?php
					$market = \TrendyolSync\API\Market_Context::for_site();
					if ( $market->is_supported() ) :
						?>
						<p class="description">
							<?php
							printf(
								/* translators: 1: market label, 2: storefront code, 3: language code */
								esc_html__( 'Piață detectată: %1$s (%2$s, limba %3$s). Categoriile se descarcă în limba site-ului tău.', 'trendyol-sync' ),
								esc_html( $market->get_label() ),
								esc_html( $market->get_storefront_code() ),
								esc_html( $market->get_accept_language() )
							);
							?>
						</p>
					<?php else : ?>
						<p class="description trendyol-sync-market-warning">
							<?php esc_html_e( 'Piața Trendyol nu a putut fi detectată. Setează țara magazinului WooCommerce (ex. România) sau limba site-ului la română înainte de sincronizare.', 'trendyol-sync' ); ?>
						</p>
					<?php endif; ?>
					<p class="description">
						<?php esc_html_e( 'Descarcă listele de branduri și categorii în cache local. Necesar pentru dropdown-urile de pe pagina de produs.', 'trendyol-sync' ); ?>
					</p>
					<p>
						<button
							type="button"
							id="trendyol-sync-catalog"
							class="button button-secondary"
							<?php echo $this->settings->has_credentials() ? '' : ' disabled'; ?>
						>
							<?php esc_html_e( 'Sincronizează catalog', 'trendyol-sync' ); ?>
						</button>
						<span id="trendyol-catalog-status" class="trendyol-connection-status" role="status" aria-live="polite"></span>
					</p>

					<hr class="trendyol-sync-settings-divider" />
					<h2><?php esc_html_e( 'Sincronizare produse', 'trendyol-sync' ); ?></h2>
					<p class="description">
						<?php esc_html_e( 'Pornește sincronizarea produselor în coadă și urmărește progresul job-ului curent.', 'trendyol-sync' ); ?>
					</p>
					<p>
						<button
							type="button"
							id="trendyol-start-sync"
							class="button button-primary"
							<?php echo $this->settings->has_credentials() ? '' : ' disabled'; ?>
						>
							<?php esc_html_e( 'Pornește sincronizarea', 'trendyol-sync' ); ?>
						</button>
						<span id="trendyol-sync-status" class="trendyol-connection-status" role="status" aria-live="polite"></span>
					</p>
				<?php endif; ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Afișează nav-tab-urile.
	 *
	 * @param string $active_tab Tab activ.
	 * @return void
	 */
	private function render_tabs( string $active_tab ): void {
		$tabs = array(
			Settings::TAB_CREDENTIALS  => __( 'Credentials', 'trendyol-sync' ),
			Settings::TAB_ENVIRONMENT  => __( 'Environment', 'trendyol-sync' ),
			Settings::TAB_AUTOMATION   => __( 'Automation', 'trendyol-sync' ),
		);

		echo '<nav class="nav-tab-wrapper wp-clearfix" aria-label="' . esc_attr__( 'Setări Trendyol', 'trendyol-sync' ) . '">';

		foreach ( $tabs as $tab_id => $label ) {
			$url   = $this->settings->get_page_url( $tab_id );
			$class = ( $active_tab === $tab_id ) ? 'nav-tab nav-tab-active' : 'nav-tab';

			printf(
				'<a href="%1$s" class="%2$s">%3$s</a>',
				esc_url( $url ),
				esc_attr( $class ),
				esc_html( $label )
			);
		}

		echo '</nav>';
	}

	/**
	 * Mapare tab → slug pagină Settings API.
	 *
	 * @param string $tab Tab ID.
	 * @return string
	 */
	private function get_settings_page_slug( string $tab ): string {
		$map = array(
			Settings::TAB_CREDENTIALS  => 'trendyol-sync-settings-credentials',
			Settings::TAB_ENVIRONMENT  => 'trendyol-sync-settings-environment',
			Settings::TAB_AUTOMATION   => 'trendyol-sync-settings-automation',
		);

		return $map[ $tab ] ?? 'trendyol-sync-settings-credentials';
	}
}
