<?php
/**
 * Tab „Trendyol Sync” în panoul Product Data WooCommerce.
 *
 * @package TrendyolSync\Admin
 */

namespace TrendyolSync\Admin;

use TrendyolSync\Sync\Variant_Grouper;
use TrendyolSync\WooCommerce\Meta_Keys;

defined( 'ABSPATH' ) || exit;

/**
 * Class Product_Data_Tab
 */
class Product_Data_Tab {

	public const TAB_ID = 'trendyol_sync';

	/**
	 * @var Catalog_Options
	 */
	private $catalog;

	/**
	 * @var Variant_Grouper
	 */
	private $grouper;

	/**
	 * @param Catalog_Options|null $catalog Opțiuni brand/categorie.
	 * @param Variant_Grouper|null $grouper  Grupare variații.
	 */
	public function __construct( ?Catalog_Options $catalog = null, ?Variant_Grouper $grouper = null ) {
		$this->catalog = $catalog ?? new Catalog_Options();
		$this->grouper = $grouper ?? new Variant_Grouper();
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
			'label'    => __( 'Trendyol Sync', 'trendyol-sync' ),
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
		$brands     = $this->catalog->get_brand_options();
		$categories = $this->catalog->get_category_options();

		$barcode     = Meta_Keys::get_string( $product_id, Meta_Keys::BARCODE );
		$brand_id    = (int) Meta_Keys::get_string( $product_id, Meta_Keys::BRAND_ID );
		$category_id = (int) Meta_Keys::get_string( $product_id, Meta_Keys::CATEGORY_ID );
		$sync_enabled = Meta_Keys::is_sync_enabled( $product_id );
		$main_id     = Meta_Keys::get_string( $product_id, Meta_Keys::PRODUCT_MAIN_ID );

		wp_nonce_field( 'trendyol_sync_product_meta', 'trendyol_sync_product_nonce' );
		?>
		<div id="trendyol_sync_product_data" class="panel woocommerce_options_panel hidden">
			<?php if ( empty( $brands ) || empty( $categories ) ) : ?>
				<p class="form-field trendyol-sync-cache-notice">
					<?php
					esc_html_e(
						'Listele de branduri și categorii nu sunt în cache. Rulează o sincronizare din setările Trendyol Sync (Sprint 2) înainte de a mapa produsele.',
						'trendyol-sync'
					);
					?>
				</p>
			<?php endif; ?>

			<?php
			woocommerce_wp_text_input(
				array(
					'id'          => Meta_Keys::BARCODE,
					'label'       => __( 'Barcode', 'trendyol-sync' ),
					'value'       => $barcode,
					'desc_tip'    => true,
					'description' => __( 'Cod de bare unic pentru Trendyol (max. 40 caractere).', 'trendyol-sync' ),
					'custom_attributes' => array(
						'maxlength' => '40',
					),
				)
			);
			?>

			<p class="form-field <?php echo esc_attr( Meta_Keys::BRAND_ID ); ?>_field">
				<label for="<?php echo esc_attr( Meta_Keys::BRAND_ID ); ?>">
					<?php esc_html_e( 'Brand Trendyol', 'trendyol-sync' ); ?>
				</label>
				<select
					id="<?php echo esc_attr( Meta_Keys::BRAND_ID ); ?>"
					name="<?php echo esc_attr( Meta_Keys::BRAND_ID ); ?>"
					class="wc-enhanced-select trendyol-sync-select"
					data-placeholder="<?php esc_attr_e( 'Selectează brandul…', 'trendyol-sync' ); ?>"
				>
					<option value=""><?php esc_html_e( '— Selectează —', 'trendyol-sync' ); ?></option>
					<?php foreach ( $brands as $id => $name ) : ?>
						<option value="<?php echo esc_attr( (string) $id ); ?>" <?php selected( $brand_id, $id ); ?>>
							<?php echo esc_html( $name ); ?>
						</option>
					<?php endforeach; ?>
				</select>
			</p>

			<p class="form-field <?php echo esc_attr( Meta_Keys::CATEGORY_ID ); ?>_field">
				<label for="<?php echo esc_attr( Meta_Keys::CATEGORY_ID ); ?>">
					<?php esc_html_e( 'Categorie Trendyol', 'trendyol-sync' ); ?>
				</label>
				<select
					id="<?php echo esc_attr( Meta_Keys::CATEGORY_ID ); ?>"
					name="<?php echo esc_attr( Meta_Keys::CATEGORY_ID ); ?>"
					class="wc-enhanced-select trendyol-sync-select"
					data-placeholder="<?php esc_attr_e( 'Selectează categoria…', 'trendyol-sync' ); ?>"
				>
					<option value=""><?php esc_html_e( '— Selectează —', 'trendyol-sync' ); ?></option>
					<?php foreach ( $categories as $id => $label ) : ?>
						<option value="<?php echo esc_attr( (string) $id ); ?>" <?php selected( $category_id, $id ); ?>>
							<?php echo esc_html( $label ); ?>
						</option>
					<?php endforeach; ?>
				</select>
			</p>

			<?php
			woocommerce_wp_checkbox(
				array(
					'id'          => 'trendyol_sync_enabled',
					'label'       => __( 'Enable Trendyol Sync', 'trendyol-sync' ),
					'value'       => $sync_enabled ? 'yes' : 'no',
					'description' => __( 'Include acest produs (și variațiile) la următoarea sincronizare în coadă.', 'trendyol-sync' ),
				)
			);
			?>

			<?php if ( '' !== $main_id ) : ?>
				<p class="form-field trendyol-sync-readonly">
					<label><?php esc_html_e( 'Product Main ID', 'trendyol-sync' ); ?></label>
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

		$enabled = isset( $_POST['trendyol_sync_enabled'] )
			&& 'yes' === sanitize_text_field( wp_unslash( (string) $_POST['trendyol_sync_enabled'] ) );

		update_post_meta(
			$post_id,
			Meta_Keys::SYNC_STATUS,
			$enabled ? Meta_Keys::SYNC_ENABLED : Meta_Keys::SYNC_DISABLED
		);

		$product = wc_get_product( $post_id );

		if ( $product instanceof \WC_Product ) {
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
	}
}
