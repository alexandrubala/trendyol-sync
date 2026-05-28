<?php
/**
 * Criptare simetrică pentru secretele API (openssl).
 *
 * @package TrendyolSync\Security
 */

namespace TrendyolSync\Security;

defined( 'ABSPATH' ) || exit;

/**
 * Class Encryption
 */
class Encryption {

	private const CIPHER = 'AES-256-CBC';

	/**
	 * Criptează un șir de caractere.
	 *
	 * @param string $plaintext Text clar.
	 * @return string Payload base64 (IV + ciphertext) sau șir gol.
	 */
	public static function encrypt( string $plaintext ): string {
		if ( '' === $plaintext ) {
			return '';
		}

		if ( ! self::is_available() ) {
			return '';
		}

		$key       = self::get_key();
		$iv_length = openssl_cipher_iv_length( self::CIPHER );
		$iv        = openssl_random_pseudo_bytes( $iv_length );
		$encrypted = openssl_encrypt( $plaintext, self::CIPHER, $key, OPENSSL_RAW_DATA, $iv );

		if ( false === $encrypted ) {
			return '';
		}

		return base64_encode( $iv . $encrypted );
	}

	/**
	 * Decriptează un payload produs de encrypt().
	 *
	 * @param string $payload Payload base64.
	 * @return string Text clar sau șir gol la eșec.
	 */
	public static function decrypt( string $payload ): string {
		if ( '' === $payload ) {
			return '';
		}

		if ( ! self::is_available() ) {
			return '';
		}

		$raw = base64_decode( $payload, true );

		if ( false === $raw ) {
			return '';
		}

		$iv_length = openssl_cipher_iv_length( self::CIPHER );

		if ( strlen( $raw ) <= $iv_length ) {
			return '';
		}

		$iv         = substr( $raw, 0, $iv_length );
		$ciphertext = substr( $raw, $iv_length );
		$decrypted  = openssl_decrypt( $ciphertext, self::CIPHER, self::get_key(), OPENSSL_RAW_DATA, $iv );

		return false !== $decrypted ? $decrypted : '';
	}

	/**
	 * Verifică dacă extensia OpenSSL este disponibilă.
	 *
	 * @return bool
	 */
	public static function is_available(): bool {
		return function_exists( 'openssl_encrypt' ) && function_exists( 'openssl_decrypt' );
	}

	/**
	 * Derivă cheia de 32 de octeți din salt-ul WordPress.
	 *
	 * @return string
	 */
	private static function get_key(): string {
		return hash( 'sha256', wp_salt( 'auth' ), true );
	}
}
