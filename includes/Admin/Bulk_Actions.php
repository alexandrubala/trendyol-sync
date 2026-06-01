<?php
/**
 * Acțiuni bulk WooCommerce pentru automatizări Trendyol.
 *
 * @package TrendyolSync\Admin
 */

namespace TrendyolSync\Admin;

use TrendyolSync\Sync\Barcode_Resolver;
use TrendyolSync\Sync\Variant_Grouper;
use TrendyolSync\WooCommerce\Meta_Keys;

defined( 'ABSPATH' ) || exit;

/**
 * Class Bulk_Actions
 */
class Bulk_Actions {

	/**
	 * @var Category_Mapper
	 */
	private $category_mapper;

	/**
	 * @var Barcode_Resolver
	 */
	private $barcode_resolver;

	/**
	 * @var Variant_Grouper
	 */
	private $variant_grouper;

	/**
	 * @param Category_Mapper|null $category_mapper Mapper categorie/brand.
	 * @param Barcode_Resolver|null $barcode_resolver Resolver barcode.
	 * @param Variant_Grouper|null  $variant_grouper Persist productMainId.
	 */
	public function __construct(
		?Category_Mapper $category_mapper = null,
		?Barcode_Resolver $barcode_resolver = null,
		?Variant_Grouper $variant_grouper = null
	) {
		$this->category_mapper  = $category_mapper ?? new Category_Mapper();
		$this->barcode_resolver = $barcode_resolver ?? new Barcode_Resolver();
		$this->variant_grouper  = $variant_grouper ?? new Variant_Grouper();
	}

	/**
	 * @return void
	 */
	public function register_hooks(): void {
		add_filter( 'bulk_actions-edit-product', array( $this, 'register_actions' ) );
		add_filter( 'handle_bulk_actions-edit-product', array( $this, 'handle_actions' ), 10, 3 );
		add_action( 'admin_notices', array( $this, 'render_notice' ) );
	}

	/**
	 * @param array<string, string> $actions Acțiuni existente.
	 * @return array<string, string>
	 */
	public function register_actions( array $actions ): array {
		$actions['trendyol_enable_sync']     = __( 'Enable Trendyol Sync', 'trendyol-sync-for-woocommerce' );
		$actions['trendyol_disable_sync']    = __( 'Disable Trendyol Sync', 'trendyol-sync-for-woocommerce' );
		$actions['trendyol_apply_mapping']   = __( 'Aplică mapare Trendyol', 'trendyol-sync-for-woocommerce' );
		$actions['trendyol_generate_barcodes'] = __( 'Generează barcode-uri Trendyol', 'trendyol-sync-for-woocommerce' );
		$actions['trendyol_prepare_all']     = __( 'Pregătește pentru Trendyol', 'trendyol-sync-for-woocommerce' );

		return $actions;
	}

	/**
	 * @param string $redirect_to URL redirect.
	 * @param string $doaction    Acțiune selectată.
	 * @param int[]  $post_ids    Produse selectate.
	 * @return string
	 */
	public function handle_actions( string $redirect_to, string $doaction, array $post_ids ): string {
		if ( empty( $post_ids ) ) {
			return $redirect_to;
		}

		$processed = 0;

		foreach ( $post_ids as $post_id ) {
			$post_id = absint( $post_id );
			if ( $post_id <= 0 ) {
				continue;
			}

			$product = wc_get_product( $post_id );
			if ( ! $product instanceof \WC_Product ) {
				continue;
			}

			if ( 'trendyol_enable_sync' === $doaction ) {
				update_post_meta( $post_id, Meta_Keys::SYNC_STATUS, Meta_Keys::SYNC_ENABLED );
				++$processed;
				continue;
			}

			if ( 'trendyol_disable_sync' === $doaction ) {
				update_post_meta( $post_id, Meta_Keys::SYNC_STATUS, Meta_Keys::SYNC_DISABLED );
				++$processed;
				continue;
			}

			if ( 'trendyol_generate_barcodes' === $doaction ) {
				$this->generate_product_barcodes( $product );
				++$processed;
				continue;
			}

			if ( 'trendyol_apply_mapping' === $doaction || 'trendyol_prepare_all' === $doaction ) {
				$this->apply_product_mapping( $product );

				if ( 'trendyol_prepare_all' === $doaction ) {
					$this->generate_product_barcodes( $product );
					$this->apply_defaults( $product );
					update_post_meta( $post_id, Meta_Keys::SYNC_STATUS, Meta_Keys::SYNC_ENABLED );
				}

				++$processed;
			}
		}

		return add_query_arg(
			array(
				'trendyol_bulk_action' => $doaction,
				'trendyol_bulk_done'   => $processed,
			),
			$redirect_to
		);
	}

	/**
	 * @return void
	 */
	public function render_notice(): void {
		if ( ! isset( $_GET['trendyol_bulk_action'], $_GET['trendyol_bulk_done'] ) ) {
			return;
		}

		$count = absint( wp_unslash( $_GET['trendyol_bulk_done'] ) );

		if ( $count <= 0 ) {
			return;
		}

		echo '<div class="notice notice-success is-dismissible"><p>';
		printf(
			/* translators: %d number of products */
			esc_html__( 'Acțiunea Trendyol a fost aplicată pentru %d produse.', 'trendyol-sync-for-woocommerce' ),
			$count
		);
		echo '</p></div>';
	}

	/**
	 * @param \WC_Product $product Produs.
	 * @return void
	 */
	private function apply_product_mapping( \WC_Product $product ): void {
		$product_id = $product->get_id();

		if ( '' === Meta_Keys::get_string( $product_id, Meta_Keys::CATEGORY_ID ) ) {
			$category_id = $this->category_mapper->resolve_category_for_product( $product );
			if ( $category_id > 0 ) {
				update_post_meta( $product_id, Meta_Keys::CATEGORY_ID, (string) $category_id );
			}
		}

		if ( '' === Meta_Keys::get_string( $product_id, Meta_Keys::BRAND_ID ) ) {
			$brand_id = $this->category_mapper->resolve_brand_for_product( $product );
			if ( $brand_id > 0 ) {
				update_post_meta( $product_id, Meta_Keys::BRAND_ID, (string) $brand_id );
			}
		}
	}

	/**
	 * @param \WC_Product $product Produs.
	 * @return void
	 */
	private function generate_product_barcodes( \WC_Product $product ): void {
		$this->barcode_resolver->persist_missing_for_product( $product );
		$this->variant_grouper->persist_group_main_id( $product->get_id() );

		if ( $product->is_type( 'variable' ) ) {
			foreach ( $product->get_children() as $child_id ) {
				$child = wc_get_product( (int) $child_id );
				if ( $child instanceof \WC_Product ) {
					$this->barcode_resolver->persist_missing_for_product( $child );
				}
			}
		}
	}

	/**
	 * @param \WC_Product $product Produs.
	 * @return void
	 */
	private function apply_defaults( \WC_Product $product ): void {
		$settings = trendyol_sync()->settings()->get_stored_settings();
		$product_id = $product->get_id();

		if ( '' === Meta_Keys::get_string( $product_id, Meta_Keys::VAT_RATE ) && isset( $settings['default_vat_rate'] ) ) {
			update_post_meta( $product_id, Meta_Keys::VAT_RATE, (string) absint( $settings['default_vat_rate'] ) );
		}

		if ( '' === Meta_Keys::get_string( $product_id, Meta_Keys::DIMENSIONAL_WEIGHT ) && isset( $settings['default_dimensional_weight'] ) ) {
			update_post_meta( $product_id, Meta_Keys::DIMENSIONAL_WEIGHT, (string) (float) $settings['default_dimensional_weight'] );
		}
	}
}
