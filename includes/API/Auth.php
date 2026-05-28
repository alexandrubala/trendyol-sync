<?php
/**
 * Autentificare Basic Auth și header User-Agent pentru API Trendyol.
 *
 * @package TrendyolSync\API
 */

namespace TrendyolSync\API;

use TrendyolSync\Admin\Settings;
use TrendyolSync\Security\Encryption;

defined( 'ABSPATH' ) || exit;

/**
 * Class Auth
 */
class Auth {

	/**
	 * @var Settings
	 */
	private $settings;

	/**
	 * @param Settings $settings Handler setări plugin.
	 */
	public function __construct( Settings $settings ) {
		$this->settings = $settings;
	}

	/**
	 * Header-e HTTP obligatorii pentru fiecare cerere API.
	 *
	 * @return array<string, string>|null Null dacă credențialele sunt incomplete.
	 */
	public function get_request_headers(): ?array {
		if ( ! $this->settings->has_credentials() ) {
			return null;
		}

		$api_key    = $this->settings->get_decrypted_api_key();
		$api_secret = $this->settings->get_decrypted_api_secret();

		if ( '' === $api_key || '' === $api_secret ) {
			return null;
		}

		$stored     = $this->settings->get_stored_settings();
		$supplier_id = (string) ( $stored['supplier_id'] ?? '' );
		$integrator  = (string) ( $stored['integrator_name'] ?? 'SelfIntegration' );

		return array(
			'Authorization' => $this->build_authorization_header( $api_key, $api_secret ),
			'User-Agent'    => $this->build_user_agent( $supplier_id, $integrator ),
			'Content-Type'  => 'application/json',
			'Accept'        => 'application/json',
		);
	}

	/**
	 * Generează header-ul Authorization: Basic (Base64).
	 *
	 * @param string $api_key    API Key decriptat.
	 * @param string $api_secret API Secret decriptat.
	 * @return string
	 */
	public function build_authorization_header( string $api_key, string $api_secret ): string {
		return 'Basic ' . base64_encode( $api_key . ':' . $api_secret );
	}

	/**
	 * Formatează User-Agent: `{supplierId} - {IntegratorName}` (max 30 caractere pentru nume).
	 *
	 * @param string $supplier_id   Supplier / Seller ID.
	 * @param string $integrator_name Nume integrator (alfanumeric).
	 * @return string
	 */
	public function build_user_agent( string $supplier_id, string $integrator_name ): string {
		$integrator_name = $this->sanitize_integrator_for_header( $integrator_name );

		return trim( $supplier_id ) . ' - ' . $integrator_name;
	}

	/**
	 * Verifică dacă OpenSSL este disponibil pentru decriptare.
	 *
	 * @return bool
	 */
	public function can_authenticate(): bool {
		return Encryption::is_available() && $this->settings->has_credentials();
	}

	/**
	 * @param string $integrator_name Nume integrator.
	 * @return string
	 */
	private function sanitize_integrator_for_header( string $integrator_name ): string {
		$integrator_name = preg_replace( '/[^a-zA-Z0-9]/', '', $integrator_name );

		if ( '' === $integrator_name ) {
			$integrator_name = 'SelfIntegration';
		}

		return substr( $integrator_name, 0, 30 );
	}
}
