<?php
/**
 * Rezolvă statusul de afișat pentru coloana Trendyol Sync din lista de produse.
 *
 * @package TrendyolSync\WooCommerce
 */

namespace TrendyolSync\WooCommerce;

defined( 'ABSPATH' ) || exit;

/**
 * Class Platform_Status
 */
class Platform_Status {

	public const STATE_NOT_LIVE = 'not_live';
	public const STATE_LIVE     = 'live';
	public const STATE_PENDING  = 'pending';
	public const STATE_ERROR    = 'error';
	public const STATE_PARTIAL  = 'partial';

	/**
	 * Cache per request: product_id => payload status.
	 *
	 * @var array<int, array{state: string, label: string, tooltip: string}>
	 */
	private $resolved = array();

	/**
	 * Cache per request: parent_id => variation_ids[].
	 *
	 * @var array<int, int[]>
	 */
	private $children_map = array();

	/**
	 * @param int[] $product_ids ID-uri produse afișate în listă.
	 * @return void
	 */
	public function preload_for_product_ids( array $product_ids ): void {
		$product_ids = array_values(
			array_unique(
				array_filter(
					array_map( 'absint', $product_ids )
				)
			)
		);

		if ( empty( $product_ids ) ) {
			return;
		}

		update_meta_cache( 'post', $product_ids );

		$variation_ids = get_posts(
			array(
				'post_type'      => 'product_variation',
				'post_status'    => 'any',
				'post_parent__in' => $product_ids,
				'fields'         => 'ids',
				'nopaging'       => true,
				'no_found_rows'  => true,
				'orderby'        => 'ID',
				'order'          => 'ASC',
			)
		);

		if ( empty( $variation_ids ) ) {
			return;
		}

		$variation_ids = array_values(
			array_unique(
				array_filter(
					array_map( 'absint', $variation_ids )
				)
			)
		);

		update_meta_cache( 'post', $variation_ids );

		foreach ( $variation_ids as $variation_id ) {
			$parent_id = (int) wp_get_post_parent_id( $variation_id );

			if ( $parent_id <= 0 ) {
				continue;
			}

			if ( ! isset( $this->children_map[ $parent_id ] ) ) {
				$this->children_map[ $parent_id ] = array();
			}

			$this->children_map[ $parent_id ][] = $variation_id;
		}
	}

	/**
	 * @param int         $product_id ID produs.
	 * @param \WC_Product $product    Obiect produs.
	 * @return array{state: string, label: string, tooltip: string}
	 */
	public function resolve( int $product_id, \WC_Product $product ): array {
		if ( isset( $this->resolved[ $product_id ] ) ) {
			return $this->resolved[ $product_id ];
		}

		if ( $product->is_type( 'variable' ) ) {
			$result = $this->resolve_variable( $product_id, $product );
		} else {
			$result = $this->resolve_single( $product_id );
		}

		$this->resolved[ $product_id ] = $result;

		return $result;
	}

	/**
	 * @param int $product_id ID produs simplu/variație.
	 * @return array{state: string, label: string, tooltip: string}
	 */
	private function resolve_single( int $product_id ): array {
		$sync_status = Meta_Keys::get_string( $product_id, Meta_Keys::SYNC_STATUS );
		$error_text  = Meta_Keys::get_last_sync_error( $product_id );

		if ( Meta_Keys::SYNC_PENDING === $sync_status ) {
			return array(
				'state'   => self::STATE_PENDING,
				'label'   => __( 'În curs de sincronizare', 'trendyol-sync' ),
				'tooltip' => __( 'Produsul este în curs de sincronizare cu Trendyol.', 'trendyol-sync' ),
			);
		}

		if ( Meta_Keys::SYNC_ERROR === $sync_status ) {
			return array(
				'state'   => self::STATE_ERROR,
				'label'   => __( 'Eroare la sincronizare', 'trendyol-sync' ),
				'tooltip' => '' !== $error_text
					? $error_text
					: __( 'Ultima încercare de sincronizare a eșuat.', 'trendyol-sync' ),
			);
		}

		if ( Meta_Keys::is_platform_live( $product_id ) ) {
			return array(
				'state'   => self::STATE_LIVE,
				'label'   => __( 'Pe Trendyol', 'trendyol-sync' ),
				'tooltip' => $this->build_live_tooltip( $product_id ),
			);
		}

		return array(
			'state'   => self::STATE_NOT_LIVE,
			'label'   => __( 'Nu este pe Trendyol', 'trendyol-sync' ),
			'tooltip' => __( 'Produsul nu este încă pe platforma Trendyol.', 'trendyol-sync' ),
		);
	}

	/**
	 * @param int         $product_id ID produs variabil.
	 * @param \WC_Product $product    Obiect produs.
	 * @return array{state: string, label: string, tooltip: string}
	 */
	private function resolve_variable( int $product_id, \WC_Product $product ): array {
		$children = $this->get_child_ids( $product );

		if ( empty( $children ) ) {
			return $this->resolve_single( $product_id );
		}

		$total          = count( $children );
		$live_count     = 0;
		$pending_count  = 0;
		$error_count    = 0;
		$first_error    = '';

		foreach ( $children as $variation_id ) {
			$status = $this->resolve_single( $variation_id );

			if ( self::STATE_LIVE === $status['state'] ) {
				++$live_count;
			} elseif ( self::STATE_PENDING === $status['state'] ) {
				++$pending_count;
			} elseif ( self::STATE_ERROR === $status['state'] ) {
				++$error_count;

				if ( '' === $first_error && '' !== $status['tooltip'] ) {
					$first_error = $status['tooltip'];
				}
			}
		}

		if ( $pending_count > 0 ) {
			return array(
				'state'   => self::STATE_PENDING,
				'label'   => __( 'În curs de sincronizare', 'trendyol-sync' ),
				'tooltip' => sprintf(
					/* translators: 1: pending variations, 2: total variations */
					__( '%1$d din %2$d variații sunt în curs de sincronizare.', 'trendyol-sync' ),
					$pending_count,
					$total
				),
			);
		}

		if ( $error_count > 0 ) {
			$tooltip = sprintf(
				/* translators: 1: errored variations, 2: total variations */
				__( '%1$d din %2$d variații au erori la sincronizare.', 'trendyol-sync' ),
				$error_count,
				$total
			);

			if ( '' !== $first_error ) {
				$tooltip .= ' ' . $first_error;
			}

			return array(
				'state'   => self::STATE_ERROR,
				'label'   => __( 'Eroare la sincronizare', 'trendyol-sync' ),
				'tooltip' => $tooltip,
			);
		}

		if ( $live_count === $total ) {
			return array(
				'state'   => self::STATE_LIVE,
				'label'   => __( 'Pe Trendyol', 'trendyol-sync' ),
				'tooltip' => sprintf(
					/* translators: %d: total variations */
					__( 'Toate cele %d variații sunt pe Trendyol.', 'trendyol-sync' ),
					$total
				),
			);
		}

		if ( $live_count > 0 ) {
			return array(
				'state'   => self::STATE_PARTIAL,
				'label'   => __( 'Parțial pe Trendyol', 'trendyol-sync' ),
				'tooltip' => sprintf(
					/* translators: 1: live variations, 2: total variations */
					__( '%1$d din %2$d variații sunt pe Trendyol.', 'trendyol-sync' ),
					$live_count,
					$total
				),
			);
		}

		return array(
			'state'   => self::STATE_NOT_LIVE,
			'label'   => __( 'Nu este pe Trendyol', 'trendyol-sync' ),
			'tooltip' => __( 'Nicio variație nu este pe Trendyol încă.', 'trendyol-sync' ),
		);
	}

	/**
	 * @param \WC_Product $product Produs variabil.
	 * @return int[]
	 */
	private function get_child_ids( \WC_Product $product ): array {
		$product_id = $product->get_id();

		if ( isset( $this->children_map[ $product_id ] ) ) {
			return $this->children_map[ $product_id ];
		}

		$children = array_filter(
			array_map( 'absint', $product->get_children() )
		);

		$this->children_map[ $product_id ] = array_values( $children );

		return $this->children_map[ $product_id ];
	}

	/**
	 * @param int $product_id ID produs.
	 * @return string
	 */
	private function build_live_tooltip( int $product_id ): string {
		$last_sync_at = Meta_Keys::get_last_sync_at( $product_id );

		if ( '' === $last_sync_at ) {
			return __( 'Produsul este pe Trendyol.', 'trendyol-sync' );
		}

		$timestamp = strtotime( $last_sync_at . ' UTC' );

		if ( false === $timestamp ) {
			return __( 'Produsul este pe Trendyol.', 'trendyol-sync' );
		}

		$formatted = wp_date(
			get_option( 'date_format' ) . ' ' . get_option( 'time_format' ),
			$timestamp
		);

		return sprintf(
			/* translators: %s: sync datetime */
			__( 'Produs pe Trendyol (ultimul sync: %s).', 'trendyol-sync' ),
			$formatted
		);
	}
}
