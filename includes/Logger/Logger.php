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
	private function sanitize_context( array $context ): array {
		$clean = array();

		foreach ( $context as $key => $value ) {
			$key = (string) $key;

			if ( is_scalar( $value ) || null === $value ) {
				$clean[ $key ] = $value;
				continue;
			}

			if ( is_array( $value ) ) {
				$encoded = wp_json_encode( $value );
				$clean[ $key ] = is_string( $encoded ) ? $encoded : '';
				continue;
			}

			if ( is_object( $value ) && method_exists( $value, '__toString' ) ) {
				$clean[ $key ] = (string) $value;
			}
		}

		return $clean;
	}
}
