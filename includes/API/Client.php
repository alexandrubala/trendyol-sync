<?php
/**
 * Client HTTP pentru API Trendyol (wp_remote_request + rate limiting).
 *
 * @package TrendyolSync\API
 */

namespace TrendyolSync\API;

defined( 'ABSPATH' ) || exit;

/**
 * Class Client
 */
class Client {

	public const RATE_LIMIT_MAX    = 50;
	public const RATE_LIMIT_WINDOW = 10;

	private const RATE_LIMIT_TRANSIENT = 'trendyol_sync_api_rate_window';

	/**
	 * @var Environment
	 */
	private $environment;

	/**
	 * @var Auth
	 */
	private $auth;

	/**
	 * @param Environment $environment Rezolvare URL mediu.
	 * @param Auth        $auth        Header-e autentificare.
	 */
	public function __construct( Environment $environment, Auth $auth ) {
		$this->environment = $environment;
		$this->auth        = $auth;
	}

	/**
	 * Cerere GET.
	 *
	 * @param string               $path  Path relativ API.
	 * @param array<string, mixed> $query Parametri query string.
	 * @return array<string, mixed> Răspuns normalizat.
	 */
	public function get( string $path, array $query = array() ): array {
		return $this->request( 'GET', $path, $query );
	}

	/**
	 * Cerere POST.
	 *
	 * @param string               $path Path relativ API.
	 * @param array<string, mixed> $body Corp JSON.
	 * @return array<string, mixed>
	 */
	public function post( string $path, array $body = array() ): array {
		return $this->request( 'POST', $path, array(), $body );
	}

	/**
	 * Execută o cerere HTTP către API.
	 *
	 * @param string                    $method GET|POST|PUT|DELETE.
	 * @param string                    $path   Path relativ.
	 * @param array<string, mixed>      $query  Query params.
	 * @param array<string, mixed>|null $body   Corp JSON (null = fără body).
	 * @return array<string, mixed> {
	 *     @type bool        $success
	 *     @type int         $status_code
	 *     @type mixed|null  $data
	 *     @type string|null $error
	 *     @type string|null $error_type auth|forbidden|rate_limit|http|network|config
	 * }
	 */
	public function request( string $method, string $path, array $query = array(), ?array $body = null ): array {
		$headers = $this->auth->get_request_headers();

		if ( null === $headers ) {
			return $this->error_response(
				__( 'Credențialele API nu sunt configurate sau nu pot fi decriptate.', 'trendyol-sync-for-woocommerce' ),
				0,
				'config'
			);
		}

		if ( ! $this->acquire_rate_limit_slot() ) {
			return $this->error_response(
				__( 'Limita de 50 cereri / 10 secunde a fost atinsă. Încearcă din nou peste câteva secunde.', 'trendyol-sync-for-woocommerce' ),
				429,
				'rate_limit'
			);
		}

		$url = $this->environment->build_url( $path );

		if ( ! empty( $query ) ) {
			$url = add_query_arg( $query, $url );
		}

		$args = array(
			'method'  => strtoupper( $method ),
			'headers' => $headers,
			'timeout' => 30,
		);

		if ( null !== $body ) {
			$args['body'] = wp_json_encode( $body );
		}

		$response = wp_remote_request( $url, $args );

		return $this->normalize_response( $response );
	}

	/**
	 * Sliding window: înregistrează cererea și refuză dacă s-a depășit plafonul.
	 *
	 * @return bool True dacă slotul a fost rezervat.
	 */
	private function acquire_rate_limit_slot(): bool {
		$now      = microtime( true );
		$cutoff   = $now - (float) self::RATE_LIMIT_WINDOW;
		$requests = get_transient( self::RATE_LIMIT_TRANSIENT );

		if ( ! is_array( $requests ) ) {
			$requests = array();
		}

		$requests = array_values(
			array_filter(
				$requests,
				static function ( $timestamp ) use ( $cutoff ) {
					return is_numeric( $timestamp ) && (float) $timestamp > $cutoff;
				}
			)
		);

		if ( count( $requests ) >= self::RATE_LIMIT_MAX ) {
			return false;
		}

		$requests[] = $now;

		set_transient(
			self::RATE_LIMIT_TRANSIENT,
			$requests,
			self::RATE_LIMIT_WINDOW + 1
		);

		return true;
	}

	/**
	 * @param array<string, mixed>|\WP_Error $response Răspuns wp_remote_*.
	 * @return array<string, mixed>
	 */
	private function normalize_response( $response ): array {
		if ( is_wp_error( $response ) ) {
			return $this->error_response(
				$response->get_error_message(),
				0,
				'network'
			);
		}

		$status_code = (int) wp_remote_retrieve_response_code( $response );
		$raw_body    = (string) wp_remote_retrieve_body( $response );
		$decoded     = json_decode( $raw_body, true );

		if ( $status_code >= 200 && $status_code < 300 ) {
			return array(
				'success'     => true,
				'status_code' => $status_code,
				'data'        => is_array( $decoded ) ? $decoded : array(),
				'error'       => null,
				'error_type'  => null,
			);
		}

		$error_type = $this->map_status_to_error_type( $status_code );
		$message    = $this->extract_error_message( $decoded, $raw_body, $status_code );

		return $this->error_response( $message, $status_code, $error_type, is_array( $decoded ) ? $decoded : null );
	}

	/**
	 * @param int $status_code Cod HTTP.
	 * @return string
	 */
	private function map_status_to_error_type( int $status_code ): string {
		switch ( $status_code ) {
			case 401:
				return 'auth';
			case 403:
				return 'forbidden';
			case 429:
				return 'rate_limit';
			default:
				return 'http';
		}
	}

	/**
	 * Extrage mesajul de eroare din corpul JSON Trendyol.
	 *
	 * @param mixed  $decoded     JSON decodat.
	 * @param string $raw_body    Corp brut.
	 * @param int    $status_code Cod HTTP.
	 * @return string
	 */
	private function extract_error_message( $decoded, string $raw_body, int $status_code ): string {
		if ( is_array( $decoded ) ) {
			foreach ( array( 'message', 'exception', 'error', 'errors' ) as $key ) {
				if ( ! empty( $decoded[ $key ] ) ) {
					return $this->stringify_error_value( $decoded[ $key ] );
				}
			}
		}

		if ( '' !== trim( $raw_body ) && strlen( $raw_body ) <= 500 ) {
			return $raw_body;
		}

		switch ( $status_code ) {
			case 401:
				return __( 'Autentificare eșuată (401). Verifică API Key, API Secret și mediul selectat.', 'trendyol-sync-for-woocommerce' );
			case 403:
				return __( 'Acces refuzat (403). Verifică header-ul User-Agent și permisiunile contului.', 'trendyol-sync-for-woocommerce' );
			case 429:
				return __( 'Prea multe cereri (429). Așteaptă câteva secunde înainte de a reîncerca.', 'trendyol-sync-for-woocommerce' );
			default:
				/* translators: %d: HTTP status code */
				return sprintf( __( 'Eroare API HTTP %d.', 'trendyol-sync-for-woocommerce' ), $status_code );
		}
	}

	/**
	 * @param mixed $value Valoare eroare din JSON.
	 * @return string
	 */
	private function stringify_error_value( $value ): string {
		if ( is_string( $value ) ) {
			return $value;
		}

		if ( is_scalar( $value ) ) {
			return (string) $value;
		}

		$encoded = wp_json_encode( $value );

		return is_string( $encoded ) ? $encoded : __( 'Eroare API necunoscută.', 'trendyol-sync-for-woocommerce' );
	}

	/**
	 * @param string      $message     Mesaj eroare.
	 * @param int         $status_code Cod HTTP.
	 * @param string      $error_type  Tip eroare normalizat.
	 * @param mixed|null  $data        Date brute opționale.
	 * @return array<string, mixed>
	 */
	private function error_response( string $message, int $status_code, string $error_type, $data = null ): array {
		return array(
			'success'     => false,
			'status_code' => $status_code,
			'data'        => $data,
			'error'       => $message,
			'error_type'  => $error_type,
		);
	}
}
