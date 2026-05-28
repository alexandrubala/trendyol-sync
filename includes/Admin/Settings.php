<?php
/**
 * Settings API – înregistrare, sanitizare și acces la setări.
 *
 * @package TrendyolSync\Admin
 */

namespace TrendyolSync\Admin;

use TrendyolSync\Security\Encryption;

defined( 'ABSPATH' ) || exit;

/**
 * Class Settings
 */
class Settings {

	/**
	 * Tab-uri disponibile în pagina de setări.
	 */
	public const TAB_CREDENTIALS = 'credentials';
	public const TAB_ENVIRONMENT = 'environment';

	/**
	 * Valori permise pentru environment.
	 */
	private const ENVIRONMENTS = array( 'stage', 'production' );

	/**
	 * Înregistrează setarea și secțiunile.
	 *
	 * @return void
	 */
	public function register(): void {
		register_setting(
			'trendyol_sync_settings_group',
			TRENDYOL_SYNC_OPTION_KEY,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize' ),
				'default'           => $this->get_defaults(),
			)
		);

		$this->register_sections_and_fields();
	}

	/**
	 * Sanitizează și persistă setările la salvare.
	 *
	 * @param array<string, mixed>|mixed $input Date brute din formular.
	 * @return array<string, mixed>
	 */
	public function sanitize( $input ): array {
		if ( ! is_array( $input ) ) {
			$input = array();
		}

		$existing = $this->get_stored_settings();
		$output   = $this->get_defaults();

		$output['supplier_id'] = isset( $input['supplier_id'] )
			? sanitize_text_field( wp_unslash( (string) $input['supplier_id'] ) )
			: (string) ( $existing['supplier_id'] ?? '' );

		$output['integrator_name'] = isset( $input['integrator_name'] )
			? $this->sanitize_integrator_name( (string) $input['integrator_name'] )
			: (string) ( $existing['integrator_name'] ?? $this->get_defaults()['integrator_name'] );

		if ( isset( $input['environment'] ) ) {
			$environment = sanitize_key( wp_unslash( (string) $input['environment'] ) );
			$output['environment'] = in_array( $environment, self::ENVIRONMENTS, true )
				? $environment
				: (string) ( $existing['environment'] ?? 'stage' );
		} else {
			$output['environment'] = (string) ( $existing['environment'] ?? 'stage' );
		}

		$output['api_key']    = $this->sanitize_secret_field(
			isset( $input['api_key'] ) ? (string) $input['api_key'] : '',
			$existing['api_key'] ?? ''
		);

		$output['api_secret'] = $this->sanitize_secret_field(
			isset( $input['api_secret'] ) ? (string) $input['api_secret'] : '',
			$existing['api_secret'] ?? ''
		);

		if ( '' !== $output['supplier_id'] && ! ctype_digit( $output['supplier_id'] ) ) {
			add_settings_error(
				TRENDYOL_SYNC_OPTION_KEY,
				'invalid_supplier_id',
				__( 'Supplier ID trebuie să conțină doar cifre.', 'trendyol-sync' ),
				'error'
			);
		}

		if ( ! Encryption::is_available() && ( '' !== $output['api_key'] || '' !== $output['api_secret'] ) ) {
			add_settings_error(
				TRENDYOL_SYNC_OPTION_KEY,
				'openssl_missing',
				__( 'Extensia PHP OpenSSL este necesară pentru salvarea securizată a cheilor API.', 'trendyol-sync' ),
				'error'
			);
		}

		return $output;
	}

	/**
	 * Returnează setările implicite.
	 *
	 * @return array<string, string>
	 */
	public function get_defaults(): array {
		return array(
			'supplier_id'      => '',
			'api_key'          => '',
			'api_secret'       => '',
			'environment'      => 'stage',
			'integrator_name'  => 'SelfIntegration',
		);
	}

	/**
	 * Citește setările din baza de date (valori criptate pentru secrete).
	 *
	 * @return array<string, string>
	 */
	public function get_stored_settings(): array {
		$stored = get_option( TRENDYOL_SYNC_OPTION_KEY, array() );

		if ( ! is_array( $stored ) ) {
			$stored = array();
		}

		return array_merge( $this->get_defaults(), $stored );
	}

	/**
	 * Setări pentru afișare în formular (fără expunerea secretelor).
	 *
	 * @return array<string, mixed>
	 */
	public function get_settings_for_display(): array {
		$stored = $this->get_stored_settings();

		return array(
			'supplier_id'     => $stored['supplier_id'],
			'environment'     => $stored['environment'],
			'integrator_name' => $stored['integrator_name'],
			'has_api_key'     => '' !== ( $stored['api_key'] ?? '' ),
			'has_api_secret'  => '' !== ( $stored['api_secret'] ?? '' ),
		);
	}

	/**
	 * Decriptează API Key (pentru fazele viitoare – client API).
	 *
	 * @return string
	 */
	public function get_decrypted_api_key(): string {
		$stored = $this->get_stored_settings();

		return Encryption::decrypt( $stored['api_key'] ?? '' );
	}

	/**
	 * Decriptează API Secret.
	 *
	 * @return string
	 */
	public function get_decrypted_api_secret(): string {
		$stored = $this->get_stored_settings();

		return Encryption::decrypt( $stored['api_secret'] ?? '' );
	}

	/**
	 * Verifică dacă credențialele minime sunt configurate.
	 *
	 * @return bool
	 */
	public function has_credentials(): bool {
		$stored = $this->get_stored_settings();

		return '' !== $stored['supplier_id']
			&& '' !== ( $stored['api_key'] ?? '' )
			&& '' !== ( $stored['api_secret'] ?? '' );
	}

	/**
	 * URL-ul paginii de setări.
	 *
	 * @param string $tab Tab activ opțional.
	 * @return string
	 */
	public function get_page_url( string $tab = self::TAB_CREDENTIALS ): string {
		return add_query_arg(
			array(
				'page' => 'trendyol-sync-settings',
				'tab'  => $tab,
			),
			admin_url( 'admin.php' )
		);
	}

	/**
	 * Tab-ul activ din query string.
	 *
	 * @return string
	 */
	public function get_active_tab(): string {
		$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( (string) $_GET['tab'] ) ) : self::TAB_CREDENTIALS;

		$allowed = array( self::TAB_CREDENTIALS, self::TAB_ENVIRONMENT );

		return in_array( $tab, $allowed, true ) ? $tab : self::TAB_CREDENTIALS;
	}

	/**
	 * @return void
	 */
	private function register_sections_and_fields(): void {
		add_settings_section(
			'trendyol_sync_credentials',
			__( 'Credențiale API', 'trendyol-sync' ),
			'__return_false',
			'trendyol-sync-settings-credentials'
		);

		add_settings_field(
			'trendyol_sync_supplier_id',
			__( 'Supplier ID', 'trendyol-sync' ),
			array( $this, 'render_supplier_id_field' ),
			'trendyol-sync-settings-credentials',
			'trendyol_sync_credentials'
		);

		add_settings_field(
			'trendyol_sync_api_key',
			__( 'API Key', 'trendyol-sync' ),
			array( $this, 'render_api_key_field' ),
			'trendyol-sync-settings-credentials',
			'trendyol_sync_credentials'
		);

		add_settings_field(
			'trendyol_sync_api_secret',
			__( 'API Secret', 'trendyol-sync' ),
			array( $this, 'render_api_secret_field' ),
			'trendyol-sync-settings-credentials',
			'trendyol_sync_credentials'
		);

		add_settings_field(
			'trendyol_sync_integrator_name',
			__( 'Integrator Name', 'trendyol-sync' ),
			array( $this, 'render_integrator_name_field' ),
			'trendyol-sync-settings-credentials',
			'trendyol_sync_credentials'
		);

		add_settings_section(
			'trendyol_sync_environment',
			__( 'Mediu API', 'trendyol-sync' ),
			'__return_false',
			'trendyol-sync-settings-environment'
		);

		add_settings_field(
			'trendyol_sync_environment_field',
			__( 'Environment', 'trendyol-sync' ),
			array( $this, 'render_environment_field' ),
			'trendyol-sync-settings-environment',
			'trendyol_sync_environment'
		);
	}

	/**
	 * @return void
	 */
	public function render_supplier_id_field(): void {
		$settings = $this->get_settings_for_display();
		$name     = TRENDYOL_SYNC_OPTION_KEY . '[supplier_id]';

		printf(
			'<input type="text" name="%1$s" id="trendyol_supplier_id" value="%2$s" class="regular-text" inputmode="numeric" pattern="[0-9]*" autocomplete="off" />',
			esc_attr( $name ),
			esc_attr( $settings['supplier_id'] )
		);
		echo '<p class="description">';
		esc_html_e( 'Supplier ID (Seller ID) din panoul Trendyol → Entegrasyon Bilgileri.', 'trendyol-sync' );
		echo '</p>';
	}

	/**
	 * @return void
	 */
	public function render_api_key_field(): void {
		$this->render_password_secret_field( 'api_key', 'trendyol_api_key', 'has_api_key' );
	}

	/**
	 * @return void
	 */
	public function render_api_secret_field(): void {
		$this->render_password_secret_field( 'api_secret', 'trendyol_api_secret', 'has_api_secret' );
	}

	/**
	 * @return void
	 */
	public function render_integrator_name_field(): void {
		$settings = $this->get_settings_for_display();
		$name     = TRENDYOL_SYNC_OPTION_KEY . '[integrator_name]';

		printf(
			'<input type="text" name="%1$s" id="trendyol_integrator_name" value="%2$s" class="regular-text" maxlength="30" autocomplete="off" />',
			esc_attr( $name ),
			esc_attr( $settings['integrator_name'] )
		);
		echo '<p class="description">';
		esc_html_e( 'Folosit în header-ul User-Agent (max. 30 caractere alfanumerice). Ex.: SelfIntegration', 'trendyol-sync' );
		echo '</p>';
	}

	/**
	 * @return void
	 */
	public function render_environment_field(): void {
		$settings = $this->get_settings_for_display();
		$name     = TRENDYOL_SYNC_OPTION_KEY . '[environment]';
		$options  = array(
			'stage'      => __( 'Stage (stageapigw.trendyol.com)', 'trendyol-sync' ),
			'production' => __( 'Production (apigw.trendyol.com)', 'trendyol-sync' ),
		);

		echo '<fieldset>';
		foreach ( $options as $value => $label ) {
			printf(
				'<label><input type="radio" name="%1$s" value="%2$s" %3$s /> %4$s</label><br />',
				esc_attr( $name ),
				esc_attr( $value ),
				checked( $settings['environment'], $value, false ),
				esc_html( $label )
			);
		}
		echo '</fieldset>';
		echo '<p class="description">';
		esc_html_e( 'Credențialele Stage și Production sunt diferite. Folosește perechea corectă pentru mediul selectat.', 'trendyol-sync' );
		echo '</p>';
	}

	/**
	 * Renderează câmp password pentru secrete mascate.
	 *
	 * @param string $field_key Cheia din array-ul de setări.
	 * @param string $input_id  ID HTML.
	 * @param string $has_flag  Cheia boolean din display settings.
	 * @return void
	 */
	private function render_password_secret_field( string $field_key, string $input_id, string $has_flag ): void {
		$settings = $this->get_settings_for_display();
		$name     = TRENDYOL_SYNC_OPTION_KEY . '[' . $field_key . ']';
		$has      = ! empty( $settings[ $has_flag ] );

		printf(
			'<input type="password" name="%1$s" id="%2$s" value="" class="regular-text" autocomplete="new-password" placeholder="%3$s" />',
			esc_attr( $name ),
			esc_attr( $input_id ),
			esc_attr__( 'Lasă gol pentru a păstra valoarea existentă', 'trendyol-sync' )
		);

		if ( $has ) {
			echo '<p class="description">';
			printf(
				/* translators: %s: masked placeholder */
				esc_html__( 'Salvat: %s — completează doar dacă dorești să schimbi cheia.', 'trendyol-sync' ),
				esc_html( '••••••' )
			);
			echo '</p>';
		}
	}

	/**
	 * Sanitizează câmp secret: criptează valoare nouă sau păstrează cea existentă.
	 *
	 * @param string $submitted Valoare trimisă din formular.
	 * @param string $existing  Valoare criptată existentă.
	 * @return string
	 */
	private function sanitize_secret_field( string $submitted, string $existing ): string {
		$submitted = trim( wp_unslash( $submitted ) );

		if ( '' === $submitted ) {
			return $existing;
		}

		if ( ! Encryption::is_available() ) {
			return $existing;
		}

		$encrypted = Encryption::encrypt( $submitted );

		return '' !== $encrypted ? $encrypted : $existing;
	}

	/**
	 * Sanitizează numele integratorului conform restricțiilor API.
	 *
	 * @param string $name Nume brut.
	 * @return string
	 */
	private function sanitize_integrator_name( string $name ): string {
		$name = sanitize_text_field( wp_unslash( $name ) );
		$name = preg_replace( '/[^a-zA-Z0-9]/', '', $name );

		if ( '' === $name ) {
			return 'SelfIntegration';
		}

		return substr( $name, 0, 30 );
	}
}
