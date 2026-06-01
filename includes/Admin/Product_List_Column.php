<?php
/**
 * Coloană "Trendyol Sync" în lista de produse WooCommerce.
 *
 * @package TrendyolSync\Admin
 */

namespace TrendyolSync\Admin;

use TrendyolSync\WooCommerce\Platform_Status;

defined( 'ABSPATH' ) || exit;

/**
 * Class Product_List_Column
 */
class Product_List_Column {

	private const COLUMN_KEY = 'trendyol_sync';

	/**
	 * @var Platform_Status
	 */
	private $platform_status;

	/**
	 * @var bool
	 */
	private $has_prefetched = false;

	/**
	 * @param Platform_Status|null $platform_status Resolver status platformă.
	 */
	public function __construct( ?Platform_Status $platform_status = null ) {
		$this->platform_status = $platform_status ?? new Platform_Status();
	}

	/**
	 * @return void
	 */
	public function register_hooks(): void {
		add_filter( 'manage_edit-product_columns', array( $this, 'add_column' ) );
		add_action( 'manage_product_posts_custom_column', array( $this, 'render_column' ), 10, 2 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	/**
	 * @param array<string, string> $columns Coloane existente.
	 * @return array<string, string>
	 */
	public function add_column( array $columns ): array {
		$updated = array();

		foreach ( $columns as $key => $label ) {
			$updated[ $key ] = $label;

			if ( 'is_in_stock' === $key ) {
				$updated[ self::COLUMN_KEY ] = __( 'Trendyol Sync', 'trendyol-sync-for-woocommerce' );
			}
		}

		if ( ! isset( $updated[ self::COLUMN_KEY ] ) ) {
			$updated[ self::COLUMN_KEY ] = __( 'Trendyol Sync', 'trendyol-sync-for-woocommerce' );
		}

		return $updated;
	}

	/**
	 * @param string $column  Cheia coloanei curente.
	 * @param int    $post_id ID produs.
	 * @return void
	 */
	public function render_column( string $column, int $post_id ): void {
		if ( self::COLUMN_KEY !== $column ) {
			return;
		}

		$this->prefetch_current_page_products();

		$product = wc_get_product( $post_id );

		if ( ! $product instanceof \WC_Product ) {
			echo '&mdash;';
			return;
		}

		$status  = $this->platform_status->resolve( $post_id, $product );
		$icon    = $this->icon_for_state( $status['state'] );
		$classes = 'trendyol-sync-col trendyol-sync-col--' . sanitize_html_class( $status['state'] );
		$tooltip = '' !== $status['tooltip'] ? $status['tooltip'] : $status['label'];

		printf(
			'<span class="%1$s" title="%2$s" aria-label="%3$s"><span class="dashicons %4$s" aria-hidden="true"></span><span class="screen-reader-text">%3$s</span></span>',
			esc_attr( $classes ),
			esc_attr( $tooltip ),
			esc_attr( $status['label'] ),
			esc_attr( $icon )
		);
	}

	/**
	 * @param string $hook_suffix Hook admin.
	 * @return void
	 */
	public function enqueue_assets( string $hook_suffix ): void {
		if ( 'edit.php' !== $hook_suffix ) {
			return;
		}

		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

		if ( ! $screen || 'product' !== $screen->post_type ) {
			return;
		}

		wp_enqueue_style(
			'trendyol-sync-product-list',
			TRENDYOL_SYNC_URL . 'assets/css/admin-product-list.css',
			array(),
			TRENDYOL_SYNC_VERSION
		);
	}

	/**
	 * @return void
	 */
	private function prefetch_current_page_products(): void {
		if ( $this->has_prefetched ) {
			return;
		}

		$this->has_prefetched = true;

		global $wp_query;

		if ( ! isset( $wp_query->posts ) || ! is_array( $wp_query->posts ) ) {
			return;
		}

		$product_ids = array();

		foreach ( $wp_query->posts as $post ) {
			if ( ! $post instanceof \WP_Post ) {
				continue;
			}

			if ( 'product' !== $post->post_type ) {
				continue;
			}

			$product_ids[] = (int) $post->ID;
		}

		$this->platform_status->preload_for_product_ids( $product_ids );
	}

	/**
	 * @param string $state Stare resolver.
	 * @return string
	 */
	private function icon_for_state( string $state ): string {
		if ( Platform_Status::STATE_LIVE === $state ) {
			return 'dashicons-yes-alt';
		}

		if ( Platform_Status::STATE_PENDING === $state ) {
			return 'dashicons-update';
		}

		if ( Platform_Status::STATE_ERROR === $state ) {
			return 'dashicons-warning';
		}

		if ( Platform_Status::STATE_PARTIAL === $state ) {
			return 'dashicons-minus-alt';
		}

		return 'dashicons-no-alt';
	}
}
