<?php
/**
 * Tab „Trendyol Sync” în panoul Product Data WooCommerce.
 *
 * @package TrendyolSync\Admin
 */

namespace TrendyolSync\Admin;

use TrendyolSync\API\Market_Context;
use TrendyolSync\Sync\Barcode_Resolver;
use TrendyolSync\Sync\Variant_Grouper;
use TrendyolSync\WooCommerce\Meta_Keys;

defined( 'ABSPATH' ) || exit;

/**
 * Class Product_Data_Tab
 */
class Product_Data_Tab {

	public const TAB_ID = 'trendyol_sync';

	public const AJAX_SEARCH_ACTION  = Catalog_Search::AJAX_ACTION;
	public const SEARCH_NONCE_ACTION = Catalog_Search::NONCE_ACTION;

	/**
	 * @var Catalog_Options
	 */
	private $catalog;

	/**
	 * @var Variant_Grouper
	 */
	private $grouper;

	/**
	 * @var Category_Mapper
	 */
	private $category_mapper;

	/**
	 * @var Barcode_Resolver
	 */
	private $barcode_resolver;

	/**
	 * @param Catalog_Options|null $catalog Opțiuni brand/categorie.
	 * @param Variant_Grouper|null $grouper  Grupare variații.
	 */
	public function __construct(
		?Catalog_Options $catalog = null,
		?Variant_Grouper $grouper = null,
		?Category_Mapper $category_mapper = null,
		?Barcode_Resolver $barcode_resolver = null
	) {
		$this->catalog          = $catalog ?? new Catalog_Options();
		$this->grouper          = $grouper ?? new Variant_Grouper();
		$this->category_mapper  = $category_mapper ?? new Category_Mapper();
		$this->barcode_resolver = $barcode_resolver ?? new Barcode_Resolver();
	}

	/**
	 * Înregistrează hook-urile WooCommerce product edit.
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		add_filter( 'woocommerce_product_data_tabs', array( $this, 'add_product_tab' ) );
		add_action( 'woocommerce_product_data_panels', array( $this, 'render_product_panel' ) );
		add_action( 'save_post_product', array( $this, 'save_product_meta' ), 10, 2 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	/**
	 * Adaugă tab-ul în Product Data.
	 *
	 * @param array<string, array<string, mixed>> $tabs Tab-uri existente.
	 * @return array<string, array<string, mixed>>
	 */
	public function add_product_tab( array $tabs ): array {
		$tabs[ self::TAB_ID ] = array(
			'label'    => __( 'Trendyol Sync', 'trendyol-sync-for-woocommerce' ),
			'target'   => 'trendyol_sync_product_data',
			'class'    => array( 'show_if_simple', 'show_if_variable' ),
			'priority' => 80,
		);

		return $tabs;
	}

	/**
	 * Conținutul panoului tab-ului.
	 *
	 * @return void
	 */
	public function render_product_panel(): void {
		global $post;

		if ( ! $post instanceof \WP_Post ) {
			return;
		}

		$product_id = (int) $post->ID;
		$market     = Market_Context::for_site();
		$cache_empty = ! $market->is_supported() || ! $this->catalog->has_cached_catalog();

		$barcode        = Meta_Keys::get_string( $product_id, Meta_Keys::BARCODE );
		$brand_id       = (int) Meta_Keys::get_string( $product_id, Meta_Keys::BRAND_ID );
		$category_id    = (int) Meta_Keys::get_string( $product_id, Meta_Keys::CATEGORY_ID );
		$brand_label    = $this->catalog->get_brand_label( $brand_id );
		$category_label = $this->catalog->get_category_label( $category_id );
		$sync_enabled   = Meta_Keys::is_sync_enabled( $product_id );
		$main_id        = Meta_Keys::get_string( $product_id, Meta_Keys::PRODUCT_MAIN_ID );
		$vat_rate       = (int) Meta_Keys::get_string( $product_id, Meta_Keys::VAT_RATE );
		$dim_weight     = (float) Meta_Keys::get_string( $product_id, Meta_Keys::DIMENSIONAL_WEIGHT );

		wp_nonce_field( 'trendyol_sync_product_meta', 'trendyol_sync_product_nonce' );
		?>
		<div id="trendyol_sync_product_data" class="panel woocommerce_options_panel hidden">
			<?php if ( ! $market->is_supported() ) : ?>
				<p class="trendyol-sync-cache-notice">
					<?php esc_html_e( 'Piața Trendyol nu corespunde setărilor site-ului. Setează țara magazinului WooCommerce (ex. România) sau limba site-ului la română.', 'trendyol-sync-for-woocommerce' ); ?>
				</p>
			<?php elseif ( $cache_empty ) : ?>
				<p class="trendyol-sync-cache-notice">
					<?php
					echo wp_kses(
						sprintf(
							/* translators: %s: settings page URL */
							__( 'Listele de branduri și categorii nu sunt în cache. Rulează o sincronizare din <a href="%s">setările Trendyol Sync</a>.', 'trendyol-sync-for-woocommerce' ),
							esc_url( trendyol_sync()->settings()->get_page_url() )
						),
						array(
							'a' => array(
								'href' => array(),
							),
						)
					);
					?>
				</p>
			<?php endif; ?>
			<?php
			woocommerce_wp_text_input(
				array(
					'id'          => Meta_Keys::BARCODE,
					'label'       => __( 'Barcode', 'trendyol-sync-for-woocommerce' ),
					'value'       => $barcode,
					'desc_tip'    => true,
					'description' => __( 'Cod de bare unic pentru Trendyol (max. 40 caractere).', 'trendyol-sync-for-woocommerce' ),
					'custom_attributes' => array(
						'maxlength' => '40',
					),
				)
			);
			?>

			<p class="form-field <?php echo esc_attr( Meta_Keys::BRAND_ID ); ?>_field">
				<label for="<?php echo esc_attr( Meta_Keys::BRAND_ID ); ?>">
					<?php esc_html_e( 'Brand Trendyol', 'trendyol-sync-for-woocommerce' ); ?>
				</label>
				<select
					id="<?php echo esc_attr( Meta_Keys::BRAND_ID ); ?>"
					name="<?php echo esc_attr( Meta_Keys::BRAND_ID ); ?>"
					class="trendyol-sync-select"
					data-placeholder="<?php esc_attr_e( 'Caută brandul…', 'trendyol-sync-for-woocommerce' ); ?>"
					<?php echo $cache_empty ? ' disabled' : ''; ?>
				>
					<option value=""><?php esc_html_e( '— Selectează —', 'trendyol-sync-for-woocommerce' ); ?></option>
					<?php if ( $brand_id > 0 && '' !== $brand_label ) : ?>
						<option value="<?php echo esc_attr( (string) $brand_id ); ?>" selected="selected">
							<?php echo esc_html( $brand_label ); ?>
						</option>
					<?php endif; ?>
				</select>
			</p>

			<p class="form-field <?php echo esc_attr( Meta_Keys::CATEGORY_ID ); ?>_field">
				<label for="<?php echo esc_attr( Meta_Keys::CATEGORY_ID ); ?>">
					<?php esc_html_e( 'Categorie Trendyol', 'trendyol-sync-for-woocommerce' ); ?>
				</label>
				<select
					id="<?php echo esc_attr( Meta_Keys::CATEGORY_ID ); ?>"
					name="<?php echo esc_attr( Meta_Keys::CATEGORY_ID ); ?>"
					class="trendyol-sync-select"
					data-placeholder="<?php esc_attr_e( 'Caută categoria…', 'trendyol-sync-for-woocommerce' ); ?>"
					<?php echo $cache_empty ? ' disabled' : ''; ?>
				>
					<option value=""><?php esc_html_e( '— Selectează —', 'trendyol-sync-for-woocommerce' ); ?></option>
					<?php if ( $category_id > 0 && '' !== $category_label ) : ?>
						<option value="<?php echo esc_attr( (string) $category_id ); ?>" selected="selected">
							<?php echo esc_html( $category_label ); ?>
						</option>
					<?php endif; ?>
				</select>
			</p>

			<?php
			woocommerce_wp_select(
				array(
					'id'          => Meta_Keys::VAT_RATE,
					'label'       => __( 'TVA Trendyol', 'trendyol-sync-for-woocommerce' ),
					'value'       => $vat_rate,
					'options'     => array(
						''  => __( '— Selectează —', 'trendyol-sync-for-woocommerce' ),
						'0' => '0',
						'1' => '1',
						'10' => '10',
						'18' => '18',
						'20' => '20',
					),
					'description' => __( 'Dacă nu este completat, se folosește default-ul din tab-ul Automation.', 'trendyol-sync-for-woocommerce' ),
					'desc_tip'    => true,
				)
			);
			woocommerce_wp_text_input(
				array(
					'id'                => Meta_Keys::DIMENSIONAL_WEIGHT,
					'label'             => __( 'Greutate dimensională', 'trendyol-sync-for-woocommerce' ),
					'value'             => $dim_weight > 0 ? (string) $dim_weight : '',
					'type'              => 'number',
					'custom_attributes' => array(
						'step' => '0.1',
						'min'  => '0.1',
					),
					'description'       => __( 'Dacă lipsește, pluginul aplică valoarea implicită din Automation.', 'trendyol-sync-for-woocommerce' ),
					'desc_tip'          => true,
				)
			);
			?>

			<?php
			woocommerce_wp_checkbox(
				array(
					'id'          => 'trendyol_sync_enabled',
					'label'       => __( 'Enable Trendyol Sync', 'trendyol-sync-for-woocommerce' ),
					'value'       => $sync_enabled ? 'yes' : 'no',
					'description' => __( 'Include acest produs (și variațiile) la următoarea sincronizare în coadă.', 'trendyol-sync-for-woocommerce' ),
				)
			);
			?>

			<?php if ( '' !== $main_id ) : ?>
				<p class="form-field trendyol-sync-readonly">
					<label><?php esc_html_e( 'Product Main ID', 'trendyol-sync-for-woocommerce' ); ?></label>
					<span class="description"><code><?php echo esc_html( $main_id ); ?></code></span>
				</p>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Salvează meta-urile la save_post_product.
	 *
	 * @param int      $post_id ID post.
	 * @param \WP_Post $post    Obiect post.
	 * @return void
	 */
	public function save_product_meta( int $post_id, $post ): void {
		if ( ! $post instanceof \WP_Post || 'product' !== $post->post_type ) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( ! isset( $_POST['trendyol_sync_product_nonce'] )
			|| ! wp_verify_nonce(
				sanitize_text_field( wp_unslash( (string) $_POST['trendyol_sync_product_nonce'] ) ),
				'trendyol_sync_product_meta'
			) ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$barcode = isset( $_POST[ Meta_Keys::BARCODE ] )
			? sanitize_text_field( wp_unslash( (string) $_POST[ Meta_Keys::BARCODE ] ) )
			: '';

		$barcode = preg_replace( '/\s+/', '', $barcode );

		if ( is_string( $barcode ) && '' !== $barcode ) {
			$barcode = function_exists( 'mb_substr' ) ? mb_substr( $barcode, 0, 40 ) : substr( $barcode, 0, 40 );
		} else {
			$barcode = '';
		}

		update_post_meta( $post_id, Meta_Keys::BARCODE, $barcode );

		$brand_id = isset( $_POST[ Meta_Keys::BRAND_ID ] )
			? absint( wp_unslash( $_POST[ Meta_Keys::BRAND_ID ] ) )
			: 0;

		$category_id = isset( $_POST[ Meta_Keys::CATEGORY_ID ] )
			? absint( wp_unslash( $_POST[ Meta_Keys::CATEGORY_ID ] ) )
			: 0;

		update_post_meta( $post_id, Meta_Keys::BRAND_ID, $brand_id > 0 ? (string) $brand_id : '' );
		update_post_meta( $post_id, Meta_Keys::CATEGORY_ID, $category_id > 0 ? (string) $category_id : '' );

		$vat_rate = isset( $_POST[ Meta_Keys::VAT_RATE ] )
			? absint( wp_unslash( $_POST[ Meta_Keys::VAT_RATE ] ) )
			: 0;
		$vat_allowed = array( 0, 1, 10, 18, 20 );
		update_post_meta( $post_id, Meta_Keys::VAT_RATE, in_array( $vat_rate, $vat_allowed, true ) ? (string) $vat_rate : '' );

		$dim_weight = isset( $_POST[ Meta_Keys::DIMENSIONAL_WEIGHT ] )
			? sanitize_text_field( wp_unslash( (string) $_POST[ Meta_Keys::DIMENSIONAL_WEIGHT ] ) )
			: '';
		if ( '' !== $dim_weight && is_numeric( $dim_weight ) && (float) $dim_weight > 0 ) {
			update_post_meta( $post_id, Meta_Keys::DIMENSIONAL_WEIGHT, (string) (float) $dim_weight );
		} else {
			delete_post_meta( $post_id, Meta_Keys::DIMENSIONAL_WEIGHT );
		}

		$product = wc_get_product( $post_id );
		if ( $product instanceof \WC_Product ) {
			if ( $category_id <= 0 ) {
				$resolved_category = $this->category_mapper->resolve_category_for_product( $product );
				if ( $resolved_category > 0 ) {
					update_post_meta( $post_id, Meta_Keys::CATEGORY_ID, (string) $resolved_category );
				}
			}

			if ( $brand_id <= 0 ) {
				$resolved_brand = $this->category_mapper->resolve_brand_for_product( $product );
				if ( $resolved_brand > 0 ) {
					update_post_meta( $post_id, Meta_Keys::BRAND_ID, (string) $resolved_brand );
				}
			}

			if ( '' === $barcode ) {
				$this->barcode_resolver->persist_missing_for_product( $product );
			}
		}

		$enabled = isset( $_POST['trendyol_sync_enabled'] )
			&& 'yes' === sanitize_text_field( wp_unslash( (string) $_POST['trendyol_sync_enabled'] ) );

		update_post_meta(
			$post_id,
			Meta_Keys::SYNC_STATUS,
			$enabled ? Meta_Keys::SYNC_ENABLED : Meta_Keys::SYNC_DISABLED
		);

		if ( $product instanceof \WC_Product ) {
			$settings = trendyol_sync()->settings()->get_stored_settings();
			$auto_enable = isset( $settings['auto_enable_sync'] ) && 'yes' === $settings['auto_enable_sync'];

			if ( $auto_enable ) {
				$has_brand = '' !== Meta_Keys::get_string( $post_id, Meta_Keys::BRAND_ID );
				$has_category = '' !== Meta_Keys::get_string( $post_id, Meta_Keys::CATEGORY_ID );
				$has_barcode = '' !== Meta_Keys::get_string( $post_id, Meta_Keys::BARCODE );
				$has_sku = '' !== trim( (string) $product->get_sku() );
				$has_price = (float) $product->get_price() > 0;
				$has_image = $product->get_image_id() > 0;
				if ( $has_brand && $has_category && $has_barcode && $has_sku && $has_price && $has_image ) {
					update_post_meta( $post_id, Meta_Keys::SYNC_STATUS, Meta_Keys::SYNC_ENABLED );
				}
			}

			$this->grouper->persist_group_main_id( $post_id );
		}
	}

	/**
	 * Stiluri pe ecranul de editare produs.
	 *
	 * @param string $hook_suffix Hook curent.
	 * @return void
	 */
	public function enqueue_assets( string $hook_suffix ): void {
		if ( ! in_array( $hook_suffix, array( 'post.php', 'post-new.php' ), true ) ) {
			return;
		}

		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

		if ( ! $screen || 'product' !== $screen->post_type ) {
			return;
		}

		wp_enqueue_style(
			'trendyol-sync-product-data',
			TRENDYOL_SYNC_URL . 'assets/css/admin-product-data.css',
			array(),
			TRENDYOL_SYNC_VERSION
		);

		Select_Woo_Assets::enqueue();

		wp_enqueue_script(
			'trendyol-sync-product-data',
			TRENDYOL_SYNC_URL . 'assets/js/admin-product-data.js',
			array( 'jquery', 'selectWoo' ),
			TRENDYOL_SYNC_VERSION,
			true
		);

		$catalog_search = new Catalog_Search( $this->catalog );

		wp_localize_script(
			'trendyol-sync-product-data',
			'trendyolSyncProductData',
			array(
				'ajaxUrl'      => admin_url( 'admin-ajax.php' ),
				'searchAction' => Catalog_Search::AJAX_ACTION,
				'nonce'        => wp_create_nonce( Catalog_Search::NONCE_ACTION ),
				'categories'   => $catalog_search->get_category_select2_data(),
			)
		);
	}
}
