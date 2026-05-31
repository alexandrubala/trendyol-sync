<?php
/**
 * GitHub Auto-Updater (WordPress plugin updates).
 *
 * @package TrendyolSync\Admin
 */
namespace TrendyolSync\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Class Updater
 *
 * @phpstan-type ReleaseData array{
 *     version: string,
 *     tag_name: string,
 *     zipball_url: string,
 *     html_url: string,
 *     body: string
 * }
 */
class Updater {
	/**
	 * Cache TTL (în secunde).
	 */
	private const CACHE_TTL_SECONDS = 6 * HOUR_IN_SECONDS;

	/**
	 * Transient: release data normalizată.
	 */
	private const TRANSIENT_RELEASE = 'trendyol_sync_github_latest_release';

	/**
	 * Transient: lock pentru evitarea stampede-ului.
	 */
	private const TRANSIENT_LOCK = 'trendyol_sync_github_latest_release_lock';

	/**
	 * GitHub: owner/repo (override prin constante opționale).
	 */
	private const DEFAULT_GITHUB_OWNER = 'alexandrubala';
	private const DEFAULT_GITHUB_REPO  = 'trendyol-sync';

	/**
	 * Hook-urilor WordPress.
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		add_filter(
			'site_transient_update_plugins',
			array( $this, 'filter_site_transient_update_plugins' )
		);

		add_filter(
			'plugins_api',
			array( $this, 'filter_plugins_api' ),
			10,
			3
		);
	}

	/**
	 * Iniectează pachetul GitHub în transient-ul de update-uri.
	 *
	 * @param object $transient Transient update plugins.
	 * @return object
	 */
	public function filter_site_transient_update_plugins( $transient ) {
		if ( ! is_object( $transient ) ) {
			return $transient;
		}

		// Evită check-uri inutile în frontend; permite cron-ul WP (wp_version_check).
		if ( ! is_admin() && ! wp_doing_cron() ) {
			return $transient;
		}

		// Evită să facă check-uri inutile pentru utilizatori fără drepturi.
		if ( is_user_logged_in() && ! current_user_can( 'update_plugins' ) ) {
			return $transient;
		}

		$plugin_file = plugin_basename( TRENDYOL_SYNC_FILE );
		$current     = null;

		if ( ! empty( $transient->checked ) && is_array( $transient->checked ) && isset( $transient->checked[ $plugin_file ] ) ) {
			$current = (string) $transient->checked[ $plugin_file ];
		}

		if ( null === $current || '' === $current ) {
			$current = TRENDYOL_SYNC_VERSION;
		}

		$release = $this->get_latest_release_data();
		if ( null === $release ) {
			return $transient;
		}

		$new_version = (string) ( $release['version'] ?? '' );
		if ( '' === $new_version ) {
			return $transient;
		}

		if ( version_compare( $new_version, $current, '<=' ) ) {
			return $transient;
		}

		$zipball_url = (string) ( $release['zipball_url'] ?? '' );
		$download    = esc_url_raw( $zipball_url );
		if ( '' === $download ) {
			return $transient;
		}

		$html_url = esc_url_raw( (string) ( $release['html_url'] ?? '' ) );

		if ( empty( $transient->response ) || ! is_array( (array) $transient->response ) ) {
			$transient->response = array();
		}

		$transient->response[ $plugin_file ] = array(
			'slug'        => 'trendyol-sync',
			'new_version' => $new_version,
			'package'     => $download,
			'url'         => $html_url,
		);

		return $transient;
	}

	/**
	 * Oferă informații plugin-ului în WP (pagina details / update modal).
	 *
	 * @param false|object $def Def.
	 * @param string       $action Action.
	 * @param object       $args Arguments.
	 * @return false|object
	 */
	public function filter_plugins_api( $def, $action, $args ) {
		if ( 'plugin_information' !== $action ) {
			return $def;
		}

		$slug = is_object( $args ) && isset( $args->slug ) ? (string) $args->slug : '';
		if ( 'trendyol-sync' !== $slug ) {
			return $def;
		}

		$release = $this->get_latest_release_data();
		if ( null === $release ) {
			return $def;
		}

		$new_version = (string) ( $release['version'] ?? '' );
		$download    = esc_url_raw( (string) ( $release['zipball_url'] ?? '' ) );
		$homepage    = esc_url_raw( (string) ( $release['html_url'] ?? '' ) );

		if ( '' === $new_version || '' === $download ) {
			return $def;
		}

		$body = (string) ( $release['body'] ?? '' );
		$body = wp_kses_post( $body );

		$sections = array(
			'description' => $body !== '' ? $body : __( 'No description available.', 'trendyol-sync' ),
		);

		// WP afișează „changelog” separat; dacă nu există, reutilizăm body.
		$sections['changelog'] = $body !== '' ? $body : __( 'No changelog available.', 'trendyol-sync' );

		$response = new \stdClass();
		$response->name          = sanitize_text_field( __( 'Trendyol Sync', 'trendyol-sync' ) );
		$response->slug          = 'trendyol-sync';
		$response->version       = $new_version;
		$response->author        = sanitize_text_field( __( 'alexandrubala', 'trendyol-sync' ) );
		$response->homepage      = $homepage;
		$response->download_link = $download;
		$response->requires      = '6.0';
		$response->tested        = '9.0';
		$response->sections      = $sections;

		return $response;
	}

	/**
	 * Returnează release-ul latest din GitHub (cu cache).
	 *
	 * @return ReleaseData|null
	 */
	private function get_latest_release_data(): ?array {
		$cached = get_transient( self::TRANSIENT_RELEASE );
		if ( is_array( $cached ) && ! empty( $cached['version'] ) ) {
			return $cached;
		}

		// Lock pentru evitarea fetch-ului simultan de către mai multe request-uri.
		if ( get_transient( self::TRANSIENT_LOCK ) ) {
			return null;
		}

		set_transient( self::TRANSIENT_LOCK, 1, 2 * MINUTE_IN_SECONDS );

		$release = $this->fetch_latest_release_from_github();
		if ( null !== $release ) {
			set_transient( self::TRANSIENT_RELEASE, $release, self::CACHE_TTL_SECONDS );
		}

		return $release;
	}

	/**
	 * Face apel la GitHub pentru latest release.
	 *
	 * Token opțional: definește `TRENDYOL_SYNC_GITHUB_TOKEN`.
	 *
	 * @return ReleaseData|null
	 */
	private function fetch_latest_release_from_github(): ?array {
		$owner = defined( 'TRENDYOL_SYNC_GITHUB_OWNER' ) ? (string) TRENDYOL_SYNC_GITHUB_OWNER : self::DEFAULT_GITHUB_OWNER;
		$repo  = defined( 'TRENDYOL_SYNC_GITHUB_REPO' ) ? (string) TRENDYOL_SYNC_GITHUB_REPO : self::DEFAULT_GITHUB_REPO;

		$owner = trim( $owner );
		$repo  = trim( $repo );

		if ( '' === $owner || '' === $repo ) {
			return null;
		}

		$url = sprintf(
			'https://api.github.com/repos/%s/%s/releases/latest',
			rawurlencode( $owner ),
			rawurlencode( $repo )
		);

		$headers = array(
			'Accept'     => 'application/vnd.github+json',
			'User-Agent' => 'trendyol-sync/' . TRENDYOL_SYNC_VERSION . '; WordPress',
		);

		$token = defined( 'TRENDYOL_SYNC_GITHUB_TOKEN' ) ? trim( (string) TRENDYOL_SYNC_GITHUB_TOKEN ) : '';
		if ( '' !== $token ) {
			// GitHub recomandă Bearer token.
			$headers['Authorization'] = 'Bearer ' . $token;
		}

		$args = array(
			'timeout'     => 5,
			'redirection' => 3,
			'sslverify'   => true,
			'headers'     => $headers,
		);

		// Apel „asincron” (non-blocking) cu fallback pe blocking, ca să nu ratăm update-ul.
		$response = null;
		$raw_body = '';
		$status_code = 0;

		$async_args            = $args;
		$async_args['blocking'] = false;

		$async_response = wp_remote_get( $url, $async_args );

		if ( ! is_wp_error( $async_response ) ) {
			$status_code = (int) wp_remote_retrieve_response_code( $async_response );
			$raw_body    = (string) wp_remote_retrieve_body( $async_response );
		}

		$should_fallback = is_wp_error( $async_response )
			|| $status_code < 200
			|| $status_code >= 300
			|| '' === trim( $raw_body );

		if ( $should_fallback ) {
			$response = wp_remote_get( $url, $args );
			if ( is_wp_error( $response ) ) {
				return null;
			}

			$status_code = (int) wp_remote_retrieve_response_code( $response );
			if ( $status_code < 200 || $status_code >= 300 ) {
				return null;
			}

			$raw_body = (string) wp_remote_retrieve_body( $response );
		}

		$decoded  = json_decode( $raw_body, true );

		if ( ! is_array( $decoded ) ) {
			return null;
		}

		$tag      = (string) ( $decoded['tag_name'] ?? '' );
		$zipball  = (string) ( $decoded['zipball_url'] ?? '' );
		$html_url = (string) ( $decoded['html_url'] ?? '' );
		$body     = (string) ( $decoded['body'] ?? '' );
		$download = $this->resolve_release_download_url( $decoded, $zipball );

		$version = $this->normalize_tag_to_version( $tag );
		if ( '' === $version || '' === $download ) {
			return null;
		}

		return array(
			'version'      => $version,
			'tag_name'     => $tag,
			'zipball_url'  => esc_url_raw( $download ),
			'html_url'     => esc_url_raw( $html_url ),
			'body'         => $body,
		);
	}

	/**
	 * Preferă asset-ul `.zip` din release (structură corectă pentru WP) față de zipball GitHub.
	 *
	 * @param array<string, mixed> $release Răspuns JSON GitHub release.
	 * @param string               $zipball URL zipball fallback.
	 * @return string
	 */
	private function resolve_release_download_url( array $release, string $zipball ): string {
		$assets = $release['assets'] ?? array();
		if ( ! is_array( $assets ) ) {
			return trim( $zipball );
		}

		foreach ( $assets as $asset ) {
			if ( ! is_array( $asset ) ) {
				continue;
			}

			$name = (string) ( $asset['name'] ?? '' );
			$url  = (string) ( $asset['browser_download_url'] ?? '' );

			if ( '' === $url || ! preg_match( '/\.zip$/i', $name ) ) {
				continue;
			}

			return $url;
		}

		return trim( $zipball );
	}

	/**
	 * GitHub tag -> versiune WP (ex: `v1.0.1` => `1.0.1`).
	 *
	 * @param string $tag Tag GitHub.
	 * @return string
	 */
	private function normalize_tag_to_version( string $tag ): string {
		$tag = trim( $tag );
		if ( '' === $tag ) {
			return '';
		}

		$tag = preg_replace( '/^v/i', '', $tag );

		// Elimină caractere improbabile, păstrând semnalele de versiune.
		$tag = preg_replace( '/[^0-9A-Za-z\.\-\+]/', '', (string) $tag );

		return (string) $tag;
	}
}

