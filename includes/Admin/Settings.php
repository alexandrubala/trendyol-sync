<?php
/**
 * Settings API – înregistrare, sanitizare și acces la setări.
 *
 * @package TrendyolSync\Admin
 */

namespace TrendyolSync\Admin;

use TrendyolSync\API\Vat_Rates;
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
	public const TAB_AUTOMATION  = 'automation';

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

		$output['default_trendyol_category_id'] = isset( $input['default_trendyol_category_id'] )
			? absint( wp_unslash( $input['default_trendyol_category_id'] ) )
			: absint( $existing['default_trendyol_category_id'] ?? 0 );

		$output['default_trendyol_brand_id'] = isset( $input['default_trendyol_brand_id'] )
			? absint( wp_unslash( $input['default_trendyol_brand_id'] ) )
			: absint( $existing['default_trendyol_brand_id'] ?? 0 );

		$vat_rates = Vat_Rates::for_site();
		$vat_rate  = isset( $input['default_vat_rate'] )
			? absint( wp_unslash( $input['default_vat_rate'] ) )
			: absint( $existing['default_vat_rate'] ?? $vat_rates->get_default_rate() );
		$output['default_vat_rate'] = $vat_rates->sanitize( $vat_rate );

		$default_weight = isset( $input['default_dimensional_weight'] )
			? sanitize_text_field( wp_unslash( (string) $input['default_dimensional_weight'] ) )
			: (string) ( $existing['default_dimensional_weight'] ?? '1' );
		$output['default_dimensional_weight'] = is_numeric( $default_weight ) ? (string) max( 0.1, (float) $default_weight ) : '1';

		$allowed_barcode_strategies = array( 'internal', 'sku_based', 'ean13_internal' );
		$barcode_strategy = isset( $input['barcode_strategy'] )
			? sanitize_key( wp_unslash( (string) $input['barcode_strategy'] ) )
			: (string) ( $existing['barcode_strategy'] ?? 'internal' );
		$output['barcode_strategy'] = in_array( $barcode_strategy, $allowed_barcode_strategies, true ) ? $barcode_strategy : 'internal';

		$output['barcode_prefix'] = isset( $input['barcode_prefix'] )
			? sanitize_text_field( wp_unslash( (string) $input['barcode_prefix'] ) )
			: (string) ( $existing['barcode_prefix'] ?? 'TY-' );

		$ean_prefix = isset( $input['barcode_ean_prefix'] )
			? absint( wp_unslash( $input['barcode_ean_prefix'] ) )
			: absint( $existing['barcode_ean_prefix'] ?? 200 );
		$output['barcode_ean_prefix'] = max( 200, min( 299, $ean_prefix ) );

		$output['auto_enable_sync'] = isset( $input['auto_enable_sync'] ) && 'yes' === sanitize_text_field( wp_unslash( (string) $input['auto_enable_sync'] ) )
			? 'yes'
			: 'no';

		$allowed_intervals = array( 'none', 'hourly', 'twicedaily', 'daily' );
		$interval = isset( $input['scheduled_sync_interval'] )
			? sanitize_key( wp_unslash( (string) $input['scheduled_sync_interval'] ) )
			: (string) ( $existing['scheduled_sync_interval'] ?? 'none' );
		$output['scheduled_sync_interval'] = in_array( $interval, $allowed_intervals, true ) ? $interval : 'none';

		$output['sync_only_modified'] = isset( $input['sync_only_modified'] ) && 'yes' === sanitize_text_field( wp_unslash( (string) $input['sync_only_modified'] ) )
			? 'yes'
			: 'no';

		$category_defaults_json = isset( $input['category_attribute_defaults_json'] )
			? trim( (string) wp_unslash( $input['category_attribute_defaults_json'] ) )
			: (string) ( $existing['category_attribute_defaults_json'] ?? '{}' );
		$decoded_category_defaults = json_decode( $category_defaults_json, true );
		if ( is_array( $decoded_category_defaults ) ) {
			update_option( 'trendyol_sync_category_attribute_defaults', $decoded_category_defaults );
			$output['category_attribute_defaults_json'] = wp_json_encode( $decoded_category_defaults, JSON_PRETTY_PRINT );
		} else {
			$output['category_attribute_defaults_json'] = '{}';
		}

		$wc_attr_map_json = isset( $input['wc_attribute_map_json'] )
			? trim( (string) wp_unslash( $input['wc_attribute_map_json'] ) )
			: (string) ( $existing['wc_attribute_map_json'] ?? '{}' );
		$decoded_wc_attr_map = json_decode( $wc_attr_map_json, true );
		if ( is_array( $decoded_wc_attr_map ) ) {
			update_option( 'trendyol_sync_wc_attribute_map', $decoded_wc_attr_map );
			$output['wc_attribute_map_json'] = wp_json_encode( $decoded_wc_attr_map, JSON_PRETTY_PRINT );
		} else {
			$output['wc_attribute_map_json'] = '{}';
		}

		$default_tax_map_json = Vat_Rates::for_site()->get_default_tax_class_map_json();
		$tax_class_map_json   = isset( $input['tax_class_map_json'] )
			? trim( (string) wp_unslash( $input['tax_class_map_json'] ) )
			: (string) ( $existing['tax_class_map_json'] ?? $default_tax_map_json );
		$decoded_tax_map = json_decode( $tax_class_map_json, true );
		if ( is_array( $decoded_tax_map ) ) {
			update_option( 'trendyol_sync_tax_class_map', $decoded_tax_map );
			$output['tax_class_map_json'] = wp_json_encode( $decoded_tax_map, JSON_PRETTY_PRINT );
		} else {
			$output['tax_class_map_json'] = $default_tax_map_json;
		}

		if ( '' !== $output['supplier_id'] && ! ctype_digit( $output['supplier_id'] ) ) {
			add_settings_error(
				TRENDYOL_SYNC_OPTION_KEY,
				'invalid_supplier_id',
				__( 'Supplier ID trebuie să conțină doar cifre.', 'trendyol-sync-for-woocommerce' ),
				'error'
			);
		}

		if ( ! Encryption::is_available() && ( '' !== $output['api_key'] || '' !== $output['api_secret'] ) ) {
			add_settings_error(
				TRENDYOL_SYNC_OPTION_KEY,
				'openssl_missing',
				__( 'Extensia PHP OpenSSL este necesară pentru salvarea securizată a cheilor API.', 'trendyol-sync-for-woocommerce' ),
				'error'
			);
		}

		return $output;
	}

	/**
	 * Returnează setările implicite.
	 *
	 * @return array<string, mixed>
	 */
	public function get_defaults(): array {
		$vat_rates = Vat_Rates::for_site();

		return array(
			'supplier_id'                   => '',
			'api_key'                       => '',
			'api_secret'                    => '',
			'environment'                   => 'stage',
			'integrator_name'               => 'SelfIntegration',
			'default_trendyol_category_id'  => 0,
			'default_trendyol_brand_id'     => 0,
			'default_vat_rate'              => $vat_rates->get_default_rate(),
			'default_dimensional_weight'    => '1',
			'barcode_strategy'              => 'internal',
			'barcode_prefix'                => 'TY-',
			'barcode_ean_prefix'            => 200,
			'auto_enable_sync'              => 'no',
			'scheduled_sync_interval'       => 'none',
			'sync_only_modified'            => 'no',
			'category_attribute_defaults_json' => '{}',
			'wc_attribute_map_json'         => '{}',
			'tax_class_map_json'            => $vat_rates->get_default_tax_class_map_json(),
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
		$stored    = $this->get_stored_settings();
		$vat_rates = Vat_Rates::for_site();

		return array(
			'supplier_id'     => $stored['supplier_id'],
			'environment'     => $stored['environment'],
			'integrator_name' => $stored['integrator_name'],
			'has_api_key'     => '' !== ( $stored['api_key'] ?? '' ),
			'has_api_secret'  => '' !== ( $stored['api_secret'] ?? '' ),
			'default_trendyol_category_id' => absint( $stored['default_trendyol_category_id'] ?? 0 ),
			'default_trendyol_brand_id'    => absint( $stored['default_trendyol_brand_id'] ?? 0 ),
			'default_vat_rate'             => $vat_rates->sanitize(
				absint( $stored['default_vat_rate'] ?? $vat_rates->get_default_rate() )
			),
			'default_dimensional_weight'   => (string) ( $stored['default_dimensional_weight'] ?? '1' ),
			'barcode_strategy'             => (string) ( $stored['barcode_strategy'] ?? 'internal' ),
			'barcode_prefix'               => (string) ( $stored['barcode_prefix'] ?? 'TY-' ),
			'barcode_ean_prefix'           => absint( $stored['barcode_ean_prefix'] ?? 200 ),
			'auto_enable_sync'             => (string) ( $stored['auto_enable_sync'] ?? 'no' ),
			'scheduled_sync_interval'      => (string) ( $stored['scheduled_sync_interval'] ?? 'none' ),
			'sync_only_modified'           => (string) ( $stored['sync_only_modified'] ?? 'no' ),
			'category_attribute_defaults_json' => (string) ( $stored['category_attribute_defaults_json'] ?? '{}' ),
			'wc_attribute_map_json'        => (string) ( $stored['wc_attribute_map_json'] ?? '{}' ),
			'tax_class_map_json'           => (string) ( $stored['tax_class_map_json'] ?? $vat_rates->get_default_tax_class_map_json() ),
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

		$allowed = array( self::TAB_CREDENTIALS, self::TAB_ENVIRONMENT, self::TAB_AUTOMATION );

		return in_array( $tab, $allowed, true ) ? $tab : self::TAB_CREDENTIALS;
	}

	/**
	 * @return void
	 */
	private function register_sections_and_fields(): void {
		add_settings_section(
			'trendyol_sync_credentials',
			__( 'Credențiale API', 'trendyol-sync-for-woocommerce' ),
			'__return_false',
			'trendyol-sync-settings-credentials'
		);

		add_settings_field(
			'trendyol_sync_supplier_id',
			__( 'Supplier ID', 'trendyol-sync-for-woocommerce' ),
			array( $this, 'render_supplier_id_field' ),
			'trendyol-sync-settings-credentials',
			'trendyol_sync_credentials'
		);

		add_settings_field(
			'trendyol_sync_api_key',
			__( 'API Key', 'trendyol-sync-for-woocommerce' ),
			array( $this, 'render_api_key_field' ),
			'trendyol-sync-settings-credentials',
			'trendyol_sync_credentials'
		);

		add_settings_field(
			'trendyol_sync_api_secret',
			__( 'API Secret', 'trendyol-sync-for-woocommerce' ),
			array( $this, 'render_api_secret_field' ),
			'trendyol-sync-settings-credentials',
			'trendyol_sync_credentials'
		);

		add_settings_field(
			'trendyol_sync_integrator_name',
			__( 'Integrator Name', 'trendyol-sync-for-woocommerce' ),
			array( $this, 'render_integrator_name_field' ),
			'trendyol-sync-settings-credentials',
			'trendyol_sync_credentials'
		);

		add_settings_section(
			'trendyol_sync_environment',
			__( 'Mediu API', 'trendyol-sync-for-woocommerce' ),
			'__return_false',
			'trendyol-sync-settings-environment'
		);

		add_settings_field(
			'trendyol_sync_environment_field',
			__( 'Environment', 'trendyol-sync-for-woocommerce' ),
			array( $this, 'render_environment_field' ),
			'trendyol-sync-settings-environment',
			'trendyol_sync_environment'
		);

		add_settings_section(
			'trendyol_sync_automation',
			__( 'Automatizare', 'trendyol-sync-for-woocommerce' ),
			'__return_false',
			'trendyol-sync-settings-automation'
		);

		add_settings_field(
			'trendyol_sync_default_category_id',
			__( 'Categorie implicită Trendyol', 'trendyol-sync-for-woocommerce' ),
			array( $this, 'render_default_category_id_field' ),
			'trendyol-sync-settings-automation',
			'trendyol_sync_automation'
		);

		add_settings_field(
			'trendyol_sync_default_brand_id',
			__( 'Brand implicit Trendyol', 'trendyol-sync-for-woocommerce' ),
			array( $this, 'render_default_brand_id_field' ),
			'trendyol-sync-settings-automation',
			'trendyol_sync_automation'
		);

		add_settings_field(
			'trendyol_sync_default_vat_rate',
			__( 'TVA implicit Trendyol', 'trendyol-sync-for-woocommerce' ),
			array( $this, 'render_default_vat_rate_field' ),
			'trendyol-sync-settings-automation',
			'trendyol_sync_automation'
		);

		add_settings_field(
			'trendyol_sync_default_dimensional_weight',
			__( 'Greutate dimensională implicită', 'trendyol-sync-for-woocommerce' ),
			array( $this, 'render_default_dimensional_weight_field' ),
			'trendyol-sync-settings-automation',
			'trendyol_sync_automation'
		);

		add_settings_field(
			'trendyol_sync_barcode_strategy',
			__( 'Strategie barcode', 'trendyol-sync-for-woocommerce' ),
			array( $this, 'render_barcode_strategy_field' ),
			'trendyol-sync-settings-automation',
			'trendyol_sync_automation'
		);

		add_settings_field(
			'trendyol_sync_auto_enable_sync',
			__( 'Auto enable sync', 'trendyol-sync-for-woocommerce' ),
			array( $this, 'render_auto_enable_sync_field' ),
			'trendyol-sync-settings-automation',
			'trendyol_sync_automation'
		);

		add_settings_field(
			'trendyol_sync_scheduled_sync_interval',
			__( 'Sincronizare programată', 'trendyol-sync-for-woocommerce' ),
			array( $this, 'render_scheduled_sync_interval_field' ),
			'trendyol-sync-settings-automation',
			'trendyol_sync_automation'
		);

		add_settings_field(
			'trendyol_sync_category_attribute_defaults_json',
			__( 'Atribute implicite per categorie (JSON)', 'trendyol-sync-for-woocommerce' ),
			array( $this, 'render_category_attribute_defaults_json_field' ),
			'trendyol-sync-settings-automation',
			'trendyol_sync_automation'
		);

		add_settings_field(
			'trendyol_sync_wc_attribute_map_json',
			__( 'Mapare WC attributes -> Trendyol (JSON)', 'trendyol-sync-for-woocommerce' ),
			array( $this, 'render_wc_attribute_map_json_field' ),
			'trendyol-sync-settings-automation',
			'trendyol_sync_automation'
		);

		add_settings_field(
			'trendyol_sync_tax_class_map_json',
			__( 'Mapare tax class -> TVA Trendyol (JSON)', 'trendyol-sync-for-woocommerce' ),
			array( $this, 'render_tax_class_map_json_field' ),
			'trendyol-sync-settings-automation',
			'trendyol_sync_automation'
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
		esc_html_e( 'Supplier ID (Seller ID) din panoul Trendyol → Entegrasyon Bilgileri.', 'trendyol-sync-for-woocommerce' );
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
		esc_html_e( 'Folosit în header-ul User-Agent (max. 30 caractere alfanumerice). Ex.: SelfIntegration', 'trendyol-sync-for-woocommerce' );
		echo '</p>';
	}

	/**
	 * @return void
	 */
	public function render_environment_field(): void {
		$settings = $this->get_settings_for_display();
		$name     = TRENDYOL_SYNC_OPTION_KEY . '[environment]';
		$options  = array(
			'stage'      => __( 'Stage (stageapigw.trendyol.com)', 'trendyol-sync-for-woocommerce' ),
			'production' => __( 'Production (apigw.trendyol.com)', 'trendyol-sync-for-woocommerce' ),
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
		esc_html_e( 'Credențialele Stage și Production sunt diferite. Folosește perechea corectă pentru mediul selectat.', 'trendyol-sync-for-woocommerce' );
		echo '</p>';
	}

	/**
	 * @return void
	 */
	public function render_default_category_id_field(): void {
		$settings = $this->get_settings_for_display();
		$name     = TRENDYOL_SYNC_OPTION_KEY . '[default_trendyol_category_id]';
		printf(
			'<input type="number" min="0" step="1" name="%1$s" value="%2$d" class="small-text" />',
			esc_attr( $name ),
			(int) $settings['default_trendyol_category_id']
		);
		echo '<p class="description">';
		esc_html_e( 'Fallback global când produsul nu are categorie explicită și nu se găsește mapare pe product_cat.', 'trendyol-sync-for-woocommerce' );
		echo '</p>';
	}

	/**
	 * @return void
	 */
	public function render_default_brand_id_field(): void {
		$settings = $this->get_settings_for_display();
		$name     = TRENDYOL_SYNC_OPTION_KEY . '[default_trendyol_brand_id]';
		printf(
			'<input type="number" min="0" step="1" name="%1$s" value="%2$d" class="small-text" />',
			esc_attr( $name ),
			(int) $settings['default_trendyol_brand_id']
		);
	}

	/**
	 * @return void
	 */
	public function render_default_vat_rate_field(): void {
		$settings = $this->get_settings_for_display();
		$name     = TRENDYOL_SYNC_OPTION_KEY . '[default_vat_rate]';
		$options  = Vat_Rates::for_site()->get_allowed_rates();
		echo '<select name="' . esc_attr( $name ) . '">';
		foreach ( $options as $value ) {
			printf(
				'<option value="%1$d" %2$s>%1$d</option>',
				(int) $value,
				selected( (int) $settings['default_vat_rate'], (int) $value, false )
			);
		}
		echo '</select>';
	}

	/**
	 * @return void
	 */
	public function render_default_dimensional_weight_field(): void {
		$settings = $this->get_settings_for_display();
		$name     = TRENDYOL_SYNC_OPTION_KEY . '[default_dimensional_weight]';
		printf(
			'<input type="number" min="0.1" step="0.1" name="%1$s" value="%2$s" class="small-text" />',
			esc_attr( $name ),
			esc_attr( (string) $settings['default_dimensional_weight'] )
		);
	}

	/**
	 * @return void
	 */
	public function render_barcode_strategy_field(): void {
		$settings        = $this->get_settings_for_display();
		$strategy_name   = TRENDYOL_SYNC_OPTION_KEY . '[barcode_strategy]';
		$prefix_name     = TRENDYOL_SYNC_OPTION_KEY . '[barcode_prefix]';
		$ean_prefix_name = TRENDYOL_SYNC_OPTION_KEY . '[barcode_ean_prefix]';
		$options         = array(
			'internal'       => __( 'internal: ty-{product_id}', 'trendyol-sync-for-woocommerce' ),
			'sku_based'      => __( 'sku_based: prefix + SKU', 'trendyol-sync-for-woocommerce' ),
			'ean13_internal' => __( 'ean13_internal: prefix 200-299 + check digit', 'trendyol-sync-for-woocommerce' ),
		);

		echo '<select name="' . esc_attr( $strategy_name ) . '">';
		foreach ( $options as $value => $label ) {
			printf(
				'<option value="%1$s" %2$s>%3$s</option>',
				esc_attr( $value ),
				selected( (string) $settings['barcode_strategy'], $value, false ),
				esc_html( $label )
			);
		}
		echo '</select>';
		echo '<p class="description">';
		printf(
			'<label>%1$s <input type="text" name="%2$s" value="%3$s" class="small-text" /></label> ',
			esc_html__( 'Prefix SKU:', 'trendyol-sync-for-woocommerce' ),
			esc_attr( $prefix_name ),
			esc_attr( (string) $settings['barcode_prefix'] )
		);
		printf(
			'<label>%1$s <input type="number" min="200" max="299" step="1" name="%2$s" value="%3$d" class="small-text" /></label>',
			esc_html__( 'Prefix EAN:', 'trendyol-sync-for-woocommerce' ),
			esc_attr( $ean_prefix_name ),
			(int) $settings['barcode_ean_prefix']
		);
		echo '</p>';
	}

	/**
	 * @return void
	 */
	public function render_auto_enable_sync_field(): void {
		$settings = $this->get_settings_for_display();
		$name     = TRENDYOL_SYNC_OPTION_KEY . '[auto_enable_sync]';
		printf(
			'<label><input type="checkbox" name="%1$s" value="yes" %2$s /> %3$s</label>',
			esc_attr( $name ),
			checked( (string) $settings['auto_enable_sync'], 'yes', false ),
			esc_html__( 'Activează automat sync când produsul are câmpurile minime completate.', 'trendyol-sync-for-woocommerce' )
		);
	}

	/**
	 * @return void
	 */
	public function render_scheduled_sync_interval_field(): void {
		$settings      = $this->get_settings_for_display();
		$interval_name = TRENDYOL_SYNC_OPTION_KEY . '[scheduled_sync_interval]';
		$modified_name = TRENDYOL_SYNC_OPTION_KEY . '[sync_only_modified]';
		$intervals     = array(
			'none'       => __( 'Dezactivat', 'trendyol-sync-for-woocommerce' ),
			'hourly'     => __( 'Din oră în oră', 'trendyol-sync-for-woocommerce' ),
			'twicedaily' => __( 'De două ori pe zi', 'trendyol-sync-for-woocommerce' ),
			'daily'      => __( 'Zilnic', 'trendyol-sync-for-woocommerce' ),
		);

		echo '<select name="' . esc_attr( $interval_name ) . '">';
		foreach ( $intervals as $value => $label ) {
			printf(
				'<option value="%1$s" %2$s>%3$s</option>',
				esc_attr( $value ),
				selected( (string) $settings['scheduled_sync_interval'], $value, false ),
				esc_html( $label )
			);
		}
		echo '</select>';
		echo '<p class="description">';
		printf(
			'<label><input type="checkbox" name="%1$s" value="yes" %2$s /> %3$s</label>',
			esc_attr( $modified_name ),
			checked( (string) $settings['sync_only_modified'], 'yes', false ),
			esc_html__( 'Trimite doar produsele modificate de la ultimul sync.', 'trendyol-sync-for-woocommerce' )
		);
		echo '</p>';
	}

	/**
	 * @return void
	 */
	public function render_category_attribute_defaults_json_field(): void {
		$settings = $this->get_settings_for_display();
		$name     = TRENDYOL_SYNC_OPTION_KEY . '[category_attribute_defaults_json]';
		printf(
			'<textarea name="%1$s" rows="8" cols="80" class="large-text code">%2$s</textarea>',
			esc_attr( $name ),
			esc_textarea( (string) $settings['category_attribute_defaults_json'] )
		);
		echo '<p class="description">';
		esc_html_e( 'Format: { "123": { "338": 456, "339": "Custom" } } (categoryId -> attributeId -> value/valueId).', 'trendyol-sync-for-woocommerce' );
		echo '</p>';
	}

	/**
	 * @return void
	 */
	public function render_wc_attribute_map_json_field(): void {
		$settings = $this->get_settings_for_display();
		$name     = TRENDYOL_SYNC_OPTION_KEY . '[wc_attribute_map_json]';
		printf(
			'<textarea name="%1$s" rows="10" cols="80" class="large-text code">%2$s</textarea>',
			esc_attr( $name ),
			esc_textarea( (string) $settings['wc_attribute_map_json'] )
		);
		echo '<p class="description">';
		esc_html_e( 'Format: { "pa_color": { "attribute_id": 47, "values": { "red": 12 }, "allow_custom": true } }.', 'trendyol-sync-for-woocommerce' );
		echo '</p>';
	}

	/**
	 * @return void
	 */
	public function render_tax_class_map_json_field(): void {
		$settings = $this->get_settings_for_display();
		$name     = TRENDYOL_SYNC_OPTION_KEY . '[tax_class_map_json]';
		printf(
			'<textarea name="%1$s" rows="6" cols="80" class="large-text code">%2$s</textarea>',
			esc_attr( $name ),
			esc_textarea( (string) $settings['tax_class_map_json'] )
		);
		echo '<p class="description">';
		esc_html_e( 'Format: { "standard":20, "reduced-rate":10, "zero-rate":0 }.', 'trendyol-sync-for-woocommerce' );
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
			esc_attr__( 'Lasă gol pentru a păstra valoarea existentă', 'trendyol-sync-for-woocommerce' )
		);

		if ( $has ) {
			echo '<p class="description">';
			printf(
				/* translators: %s: masked placeholder */
				esc_html__( 'Salvat: %s — completează doar dacă dorești să schimbi cheia.', 'trendyol-sync-for-woocommerce' ),
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
