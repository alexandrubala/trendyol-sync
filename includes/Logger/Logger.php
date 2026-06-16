<?php
/**
 * Logger structurat cu persistență în wp_trendyol_logs.
 *
 * @package TrendyolSync\Logger
 */

namespace TrendyolSync\Logger;

defined( 'ABSPATH' ) || exit;

/**
 * Class Logger
 */
class Logger {

	/**
	 * @var Log_Repository
	 */
	private $repository;

	/**
	 * @param Log_Repository|null $repository Repository injectabil.
	 */
	public function __construct( ?Log_Repository $repository = null ) {
		$this->repository = $repository ?? new Log_Repository();
	}

	/**
	 * @param string               $message Mesaj.
	 * @param array<string, mixed> $context Context opțional.
	 * @return int
	 */
	public function debug( string $message, array $context = array() ): int {
		return $this->log( 'debug', $message, $context );
	}

	/**
	 * @param string               $message Mesaj.
	 * @param array<string, mixed> $context Context opțional.
	 * @return int
	 */
	public function info( string $message, array $context = array() ): int {
		return $this->log( 'info', $message, $context );
	}

	/**
	 * @param string               $message Mesaj.
	 * @param array<string, mixed> $context Context opțional.
	 * @return int
	 */
	public function warning( string $message, array $context = array() ): int {
		return $this->log( 'warning', $message, $context );
	}

	/**
	 * @param string               $message Mesaj.
	 * @param array<string, mixed> $context Context opțional.
	 * @return int
	 */
	public function error( string $message, array $context = array() ): int {
		return $this->log( 'error', $message, $context );
	}

	/**
	 * Scrie o intrare de log cu context JSON (product_id, batch_id, http_code, api_response).
	 *
	 * @param string               $level   Nivel.
	 * @param string               $message Mesaj.
	 * @param array<string, mixed> $context Context.
	 * @return int ID log sau 0.
	 */
	public function log( string $level, string $message, array $context = array() ): int {
		$context = $this->sanitize_context( $context );

		return $this->repository->insert( $level, $message, $context );
	}

	/**
	 * Normalizează contextul pentru JSON (scalarizare, chei string).
	 *
	 * @param array<string, mixed> $context Context brut.
	 * @return array<string, mixed>
	 */
	/**
	 * Chei de context care nu trebuie scrise niciodată în log.
	 *
	 * @var string[]
	 */
	private const REDACTED_KEYS = array(
		'api_key',
		'api_secret',
		'authorization',
		'password',
		'secret',
		'token',
		'github_token',
	);

	private function sanitize_context( array $context ): array {
		$clean = array();

		foreach ( $context as $key => $value ) {
			$key = (string) $key;

			if ( $this->is_redacted_key( $key ) ) {
				$clean[ $key ] = '[REDACTED]';
				continue;
			}

			if ( is_scalar( $value ) || null === $value ) {
				$clean[ $key ] = $this->redact_scalar_value( $key, $value );
				continue;
			}

			if ( is_array( $value ) ) {
				$encoded = wp_json_encode( $this->sanitize_context( $value ) );
				$clean[ $key ] = is_string( $encoded ) ? $encoded : '';
				continue;
			}

			if ( is_object( $value ) && method_exists( $value, '__toString' ) ) {
				$clean[ $key ] = $this->redact_scalar_value( $key, (string) $value );
			}
		}

		return $clean;
	}

	/**
	 * @param string $key Cheie context.
	 * @return bool
	 */
	private function is_redacted_key( string $key ): bool {
		$normalized = strtolower( $key );

		foreach ( self::REDACTED_KEYS as $redacted ) {
			if ( $normalized === $redacted || false !== strpos( $normalized, $redacted ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * @param string       $key   Cheie context.
	 * @param scalar|null  $value Valoare.
	 * @return scalar|null
	 */
	private function redact_scalar_value( string $key, $value ) {
		if ( ! is_string( $value ) || '' === $value ) {
			return $value;
		}

		if ( preg_match( '/^ghp_[A-Za-z0-9]+$/', $value ) ) {
			return '[REDACTED]';
		}

		if ( 'authorization' === strtolower( $key ) || 0 === stripos( $value, 'basic ' ) || 0 === stripos( $value, 'bearer ' ) ) {
			return '[REDACTED]';
		}

		return $value;
	}
}
