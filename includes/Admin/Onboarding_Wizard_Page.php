<?php
/**
 * Wizard de onboarding și preview al catalogului.
 *
 * @package TrendyolSync\Admin
 */

namespace TrendyolSync\Admin;

use TrendyolSync\WooCommerce\Meta_Keys;

defined( 'ABSPATH' ) || exit;

/**
 * Class Onboarding_Wizard_Page
 */
class Onboarding_Wizard_Page {

	public const PAGE_SLUG = 'trendyol-sync-onboarding';
	public const ACTION_ENABLE_READY = 'trendyol_sync_enable_ready_products';

	/**
	 * @var Category_Mapper
	 */
	private $mapper;

	/**
	 * @param Category_Mapper|null $mapper Mapper categorie/brand.
	 */
	public function __construct( ?Category_Mapper $mapper = null ) {
		$this->mapper = $mapper ?? new Category_Mapper();
	}

	/**
	 * @return void
	 */
	public function register_hooks(): void {
		add_action( 'admin_post_' . self::ACTION_ENABLE_READY, array( $this, 'enable_ready_products' ) );
	}

	/**
	 * @return void
	 */
	public function render(): void {
		if ( ! current_user_can( TRENDYOL_SYNC_CAPABILITY ) ) {
			wp_die( esc_html__( 'Nu ai permisiunea de a accesa această pagină.', 'trendyol-sync-for-woocommerce' ) );
		}

		$preview = $this->compute_preview();
		?>
		<div class="wrap trendyol-sync-settings-wrap">
			<h1><?php esc_html_e( 'Wizard pregătire catalog Trendyol', 'trendyol-sync-for-woocommerce' ); ?></h1>
			<ol>
				<li><?php echo $preview['credentials_ok'] ? '[OK] ' : '[MISSING] '; ?><?php esc_html_e( 'Credențiale API configurate', 'trendyol-sync-for-woocommerce' ); ?></li>
				<li><?php echo $preview['catalog_ok'] ? '[OK] ' : '[MISSING] '; ?><?php esc_html_e( 'Catalog sincronizat (branduri/categorii)', 'trendyol-sync-for-woocommerce' ); ?></li>
				<li><?php echo $preview['mapping_ok'] ? '[OK] ' : '[MISSING] '; ?><?php esc_html_e( 'Mapare categorii/brand definită', 'trendyol-sync-for-woocommerce' ); ?></li>
				<li><?php echo $preview['ready_products'] > 0 ? '[OK] ' : '[MISSING] '; ?><?php esc_html_e( 'Produse gata de sync', 'trendyol-sync-for-woocommerce' ); ?></li>
			</ol>

			<p>
				<?php
				printf(
					/* translators: 1: ready products, 2: total */
					esc_html__( '%1$d din %2$d produse publicate sunt pregătite pentru sincronizare.', 'trendyol-sync-for-woocommerce' ),
					(int) $preview['ready_products'],
					(int) $preview['total_products']
				);
				?>
			</p>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="<?php echo esc_attr( self::ACTION_ENABLE_READY ); ?>" />
				<?php wp_nonce_field( self::ACTION_ENABLE_READY, 'trendyol_sync_wizard_nonce' ); ?>
				<p>
					<button type="submit" class="button button-primary"><?php esc_html_e( 'Activează sync pentru produsele gata', 'trendyol-sync-for-woocommerce' ); ?></button>
				</p>
			</form>
		</div>
		<?php
	}

	/**
	 * @return void
	 */
	public function enable_ready_products(): void {
		if ( ! current_user_can( TRENDYOL_SYNC_CAPABILITY ) ) {
			wp_die( esc_html__( 'Nu ai permisiunea de a executa această acțiune.', 'trendyol-sync-for-woocommerce' ) );
		}

		check_admin_referer( self::ACTION_ENABLE_READY, 'trendyol_sync_wizard_nonce' );

		$products = wc_get_products(
			array(
				'type'   => array( 'simple', 'variable' ),
				'status' => array( 'publish' ),
				'limit'  => -1,
				'return' => 'objects',
			)
		);

		$enabled = 0;

		foreach ( $products as $product ) {
			if ( ! $product instanceof \WC_Product ) {
				continue;
			}

			$product_id = $product->get_id();
			$has_brand = (int) Meta_Keys::get_string( $product_id, Meta_Keys::BRAND_ID ) > 0;
			$has_category = (int) Meta_Keys::get_string( $product_id, Meta_Keys::CATEGORY_ID ) > 0;
			$has_barcode = '' !== Meta_Keys::get_string( $product_id, Meta_Keys::BARCODE );
			$has_sku = '' !== trim( (string) $product->get_sku() );
			$has_price = (float) $product->get_price() > 0;
			$has_image = $product->get_image_id() > 0;

			if ( $has_brand && $has_category && $has_barcode && $has_sku && $has_price && $has_image ) {
				update_post_meta( $product_id, Meta_Keys::SYNC_STATUS, Meta_Keys::SYNC_ENABLED );
				++$enabled;
			}
		}

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'                  => self::PAGE_SLUG,
					'enabled_ready_products' => $enabled,
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * @return array{credentials_ok: bool, catalog_ok: bool, mapping_ok: bool, total_products: int, ready_products: int}
	 */
	private function compute_preview(): array {
		$settings = trendyol_sync()->settings();
		$products = wc_get_products(
			array(
				'type'   => array( 'simple', 'variable' ),
				'status' => array( 'publish' ),
				'limit'  => -1,
				'return' => 'objects',
			)
		);

		$total = 0;
		$ready = 0;

		foreach ( $products as $product ) {
			if ( ! $product instanceof \WC_Product ) {
				continue;
			}

			++$total;

			$product_id = $product->get_id();
			$has_brand = (int) Meta_Keys::get_string( $product_id, Meta_Keys::BRAND_ID ) > 0;
			$has_category = (int) Meta_Keys::get_string( $product_id, Meta_Keys::CATEGORY_ID ) > 0;
			$has_barcode = '' !== Meta_Keys::get_string( $product_id, Meta_Keys::BARCODE );
			$has_sku = '' !== trim( (string) $product->get_sku() );
			$has_price = (float) $product->get_price() > 0;
			$has_image = $product->get_image_id() > 0;

			if ( $has_brand && $has_category && $has_barcode && $has_sku && $has_price && $has_image ) {
				++$ready;
			}
		}

		return array(
			'credentials_ok' => $settings->has_credentials(),
			'catalog_ok'     => ( new Catalog_Options() )->has_cached_catalog(),
			'mapping_ok'     => ! empty( $this->mapper->get_category_map() ) || ! empty( $this->mapper->get_brand_map() ),
			'total_products' => $total,
			'ready_products' => $ready,
		);
	}
}
