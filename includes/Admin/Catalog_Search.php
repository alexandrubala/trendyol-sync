<?php
/**
 * AJAX căutare brand / categorie în catalogul Trendyol (cache local).
 *
 * @package TrendyolSync\Admin
 */

namespace TrendyolSync\Admin;

use TrendyolSync\API\Market_Context;

defined( 'ABSPATH' ) || exit;

/**
 * Class Catalog_Search
 */
class Catalog_Search {

	public const AJAX_ACTION  = 'trendyol_sync_search_catalog';
	public const NONCE_ACTION = 'trendyol_sync_search_catalog';

	/**
	 * @var Catalog_Options
	 */
	private $catalog;

	/**
	 * @param Catalog_Options|null $catalog Opțiuni catalog.
	 */
	public function __construct( ?Catalog_Options $catalog = null ) {
		$this->catalog = $catalog ?? new Catalog_Options();
	}

	/**
	 * @return void
	 */
	public function register_hooks(): void {
		add_action( 'wp_ajax_' . self::AJAX_ACTION, array( $this, 'handle' ) );
	}

	/**
	 * @return void
	 */
	public function handle(): void {
		check_ajax_referer( self::NONCE_ACTION, 'nonce' );

		if ( ! current_user_can( 'edit_products' ) && ! current_user_can( TRENDYOL_SYNC_CAPABILITY ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'Nu ai permisiunea de a căuta în catalog.', 'trendyol-sync-for-woocommerce' ),
				),
				403
			);
		}

		$market = Market_Context::for_site();

		if ( ! $market->is_supported() ) {
			wp_send_json_success(
				array(
					'results'    => array(),
					'pagination' => array( 'more' => false ),
				)
			);
		}

		$type = isset( $_REQUEST['type'] ) ? sanitize_key( wp_unslash( (string) $_REQUEST['type'] ) ) : '';
		$term = isset( $_REQUEST['term'] ) ? sanitize_text_field( wp_unslash( (string) $_REQUEST['term'] ) ) : '';
		$page = isset( $_REQUEST['page'] ) ? absint( wp_unslash( $_REQUEST['page'] ) ) : 1;

		if ( 'brand' === $type ) {
			$payload = $this->catalog->search_brands( $term, $page );
			wp_send_json_success( $payload );
		}

		if ( 'category' === $type ) {
			$payload = $this->catalog->search_categories( $term, $page );
			wp_send_json_success( $payload );
		}

		wp_send_json_error(
			array(
				'message' => __( 'Tip de căutare invalid.', 'trendyol-sync-for-woocommerce' ),
			),
			400
		);
	}

	/**
	 * Date Select2 pentru categorii (încărcate în pagină, fără AJAX).
	 *
	 * @return array<int, array{id: int, text: string}>
	 */
	public function get_category_select2_data(): array {
		$options = $this->catalog->get_category_options();
		$results = array();

		foreach ( $options as $id => $label ) {
			$id = (int) $id;

			if ( $id <= 0 || '' === (string) $label ) {
				continue;
			}

			$results[] = array(
				'id'   => $id,
				'text' => (string) $label,
			);
		}

		return $results;
	}
}
