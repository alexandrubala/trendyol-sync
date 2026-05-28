<?php
/**
 * Normalizează URL-urile imaginilor pentru payload-ul Trendyol.
 *
 * @package TrendyolSync\Sync
 */

namespace TrendyolSync\Sync;

defined( 'ABSPATH' ) || exit;

/**
 * Class Image_Normalizer
 */
class Image_Normalizer {

	public const MAX_IMAGES = 8;

	/**
	 * Transformă URL-uri brute în structura images[] cerută de API.
	 *
	 * @param string[] $urls Lista URL-uri (featured + galerie).
	 * @return array<int, array{url: string}>
	 */
	public function normalize( array $urls ): array {
		$normalized = array();
		$seen       = array();

		foreach ( $urls as $url ) {
			if ( count( $normalized ) >= self::MAX_IMAGES ) {
				break;
			}

			if ( ! is_string( $url ) || '' === trim( $url ) ) {
				continue;
			}

			$secure = $this->ensure_https( trim( $url ) );

			if ( ! $this->is_valid_url( $secure ) ) {
				continue;
			}

			if ( isset( $seen[ $secure ] ) ) {
				continue;
			}

			$seen[ $secure ] = true;
			$normalized[]    = array( 'url' => $secure );
		}

		return $normalized;
	}

	/**
	 * Forțează schema HTTPS (cerință Trendyol).
	 *
	 * @param string $url URL sursă.
	 * @return string
	 */
	public function ensure_https( string $url ): string {
		if ( 0 === stripos( $url, 'https://' ) ) {
			return $url;
		}

		if ( 0 === stripos( $url, 'http://' ) ) {
			return 'https://' . substr( $url, 7 );
		}

		if ( '//' === substr( $url, 0, 2 ) ) {
			return 'https:' . $url;
		}

		return $url;
	}

	/**
	 * Validare best-effort a URL-ului.
	 *
	 * @param string $url URL de verificat.
	 * @return bool
	 */
	public function is_valid_url( string $url ): bool {
		if ( ! filter_var( $url, FILTER_VALIDATE_URL ) ) {
			return false;
		}

		$parts = wp_parse_url( $url );

		if ( empty( $parts['scheme'] ) || 'https' !== strtolower( $parts['scheme'] ) ) {
			return false;
		}

		return ! empty( $parts['host'] );
	}
}
