<?php
/**
 * Pagina admin pentru mapare categorii/branduri WooCommerce -> Trendyol.
 *
 * @package TrendyolSync\Admin
 */

namespace TrendyolSync\Admin;

use TrendyolSync\API\Market_Context;

defined( 'ABSPATH' ) || exit;

/**
 * Class Category_Mapping_Page
 */
class Category_Mapping_Page {

	public const PAGE_SLUG   = 'trendyol-sync-mapping';
	public const POST_ACTION = 'trendyol_sync_save_mapping';

	/**
	 * @var Category_Mapper
	 */
	private $mapper;

	/**
	 * @var Catalog_Options
	 */
	private $catalog;

	/**
	 * @param Category_Mapper|null $mapper  Serviciu mapare.
	 * @param Catalog_Options|null $catalog Catalog Trendyol.
	 */
	public function __construct( ?Category_Mapper $mapper = null, ?Catalog_Options $catalog = null ) {
		$this->mapper  = $mapper ?? new Category_Mapper();
		$this->catalog = $catalog ?? new Catalog_Options();
	}

	/**
	 * @return void
	 */
	public function register_hooks(): void {
		add_action( 'admin_post_' . self::POST_ACTION, array( $this, 'handle_submit' ) );
	}

	/**
	 * @return void
	 */
	public function render(): void {
		if ( ! current_user_can( TRENDYOL_SYNC_CAPABILITY ) ) {
			wp_die( esc_html__( 'Nu ai permisiunea de a accesa această pagină.', 'trendyol-sync-for-woocommerce' ) );
		}

		$terms = get_terms(
			array(
				'taxonomy'   => 'product_cat',
				'hide_empty' => false,
				'orderby'    => 'name',
				'order'      => 'ASC',
			)
		);

		$category_map = $this->mapper->get_category_map();
		$brand_map    = $this->mapper->get_brand_map();
		$market       = Market_Context::for_site();
		$cache_empty  = ! $market->is_supported() || ! $this->catalog->has_cached_catalog();
		?>
		<div class="wrap trendyol-sync-settings-wrap">
			<h1><?php esc_html_e( 'Mapare categorii WooCommerce -> Trendyol', 'trendyol-sync-for-woocommerce' ); ?></h1>
			<?php if ( $cache_empty ) : ?>
				<div class="notice notice-warning">
					<p>
						<?php
						echo wp_kses(
							sprintf(
								/* translators: %s: settings page URL */
								__( 'Catalogul Trendyol nu este în cache. Rulează „Sincronizează catalog” din <a href="%s">setările Trendyol Sync</a> înainte de mapare.', 'trendyol-sync-for-woocommerce' ),
								esc_url( admin_url( 'admin.php?page=' . Admin::MENU_SLUG ) )
							),
							array(
								'a' => array(
									'href' => array(),
								),
							)
						);
						?>
					</p>
				</div>
			<?php endif; ?>
			<?php if ( isset( $_GET['mapping_updated'] ) ) : ?>
				<div class="notice notice-success is-dismissible">
					<p>
						<?php
						if ( isset( $_GET['applied_products'] ) ) {
							printf(
								/* translators: %d number of products */
								esc_html__( 'Mapările au fost salvate și aplicate pe %d produse.', 'trendyol-sync-for-woocommerce' ),
								absint( wp_unslash( $_GET['applied_products'] ) )
							);
						} else {
							esc_html_e( 'Mapările au fost salvate.', 'trendyol-sync-for-woocommerce' );
						}
						?>
					</p>
				</div>
			<?php endif; ?>
			<p class="description">
				<?php esc_html_e( 'Setează o mapare globală pentru categorii și branduri. Produsele noi pot prelua automat aceste valori la sync.', 'trendyol-sync-for-woocommerce' ); ?>
			</p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="<?php echo esc_attr( self::POST_ACTION ); ?>" />
				<?php wp_nonce_field( self::POST_ACTION, 'trendyol_sync_mapping_nonce' ); ?>
				<table class="widefat striped">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Categorie WooCommerce', 'trendyol-sync-for-woocommerce' ); ?></th>
							<th><?php esc_html_e( 'Categorie Trendyol', 'trendyol-sync-for-woocommerce' ); ?></th>
							<th><?php esc_html_e( 'Brand Trendyol', 'trendyol-sync-for-woocommerce' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php if ( is_array( $terms ) && ! empty( $terms ) ) : ?>
							<?php foreach ( $terms as $term ) : ?>
								<?php
								$term_id = (int) $term->term_id;
								$mapped_category = isset( $category_map[ $term_id ] ) ? (int) $category_map[ $term_id ] : 0;
								$mapped_brand    = isset( $brand_map[ $term_id ] ) ? (int) $brand_map[ $term_id ] : 0;
								$depth           = count( get_ancestors( $term_id, 'product_cat', 'taxonomy' ) );
								$prefix          = str_repeat( '— ', max( 0, $depth ) );
								$category_label  = $this->catalog->get_category_label( $mapped_category );
								$brand_label     = $this->catalog->get_brand_label( $mapped_brand );
								?>
								<tr>
									<td><strong><?php echo esc_html( $prefix . $term->name ); ?></strong></td>
									<td>
										<select
											name="category_map[<?php echo esc_attr( (string) $term_id ); ?>]"
											class="trendyol-sync-mapping-select"
											data-type="category"
											data-placeholder="<?php esc_attr_e( 'Caută categoria…', 'trendyol-sync-for-woocommerce' ); ?>"
										>
											<option value=""><?php esc_html_e( '— Fără mapare —', 'trendyol-sync-for-woocommerce' ); ?></option>
											<?php if ( $mapped_category > 0 && '' !== $category_label ) : ?>
												<option value="<?php echo esc_attr( (string) $mapped_category ); ?>" selected="selected"><?php echo esc_html( $category_label ); ?></option>
											<?php endif; ?>
										</select>
									</td>
									<td>
										<select
											name="brand_map[<?php echo esc_attr( (string) $term_id ); ?>]"
											class="trendyol-sync-mapping-select"
											data-type="brand"
											data-placeholder="<?php esc_attr_e( 'Caută brandul…', 'trendyol-sync-for-woocommerce' ); ?>"
										>
											<option value=""><?php esc_html_e( '— Fără mapare —', 'trendyol-sync-for-woocommerce' ); ?></option>
											<?php if ( $mapped_brand > 0 && '' !== $brand_label ) : ?>
												<option value="<?php echo esc_attr( (string) $mapped_brand ); ?>" selected="selected"><?php echo esc_html( $brand_label ); ?></option>
											<?php endif; ?>
										</select>
									</td>
								</tr>
							<?php endforeach; ?>
						<?php else : ?>
							<tr>
								<td colspan="3"><?php esc_html_e( 'Nu există categorii WooCommerce.', 'trendyol-sync-for-woocommerce' ); ?></td>
							</tr>
						<?php endif; ?>
					</tbody>
				</table>
				<p>
					<button type="submit" name="submit_mode" value="save" class="button button-primary"><?php esc_html_e( 'Salvează mapările', 'trendyol-sync-for-woocommerce' ); ?></button>
					<button type="submit" name="submit_mode" value="save_apply" class="button button-secondary"><?php esc_html_e( 'Salvează și aplică pe produse existente', 'trendyol-sync-for-woocommerce' ); ?></button>
				</p>
			</form>
		</div>
		<?php
	}

	/**
	 * @return void
	 */
	public function handle_submit(): void {
		if ( ! current_user_can( TRENDYOL_SYNC_CAPABILITY ) ) {
			wp_die( esc_html__( 'Nu ai permisiunea de a salva mapările.', 'trendyol-sync-for-woocommerce' ) );
		}

		check_admin_referer( self::POST_ACTION, 'trendyol_sync_mapping_nonce' );

		$category_map = isset( $_POST['category_map'] ) && is_array( $_POST['category_map'] ) ? wp_unslash( $_POST['category_map'] ) : array();
		$brand_map    = isset( $_POST['brand_map'] ) && is_array( $_POST['brand_map'] ) ? wp_unslash( $_POST['brand_map'] ) : array();
		$submit_mode  = isset( $_POST['submit_mode'] ) ? sanitize_key( wp_unslash( (string) $_POST['submit_mode'] ) ) : 'save';

		$this->mapper->save_category_map( $category_map );
		$this->mapper->save_brand_map( $brand_map );

		$redirect = add_query_arg(
			array(
				'page'            => self::PAGE_SLUG,
				'mapping_updated' => 1,
			),
			admin_url( 'admin.php' )
		);

		if ( 'save_apply' === $submit_mode ) {
			$result = $this->mapper->apply_to_existing_products();

			$redirect = add_query_arg(
				array(
					'page'             => self::PAGE_SLUG,
					'mapping_updated'  => 1,
					'applied_products' => (int) $result['touched_products'],
				),
				admin_url( 'admin.php' )
			);
		}

		wp_safe_redirect( $redirect );
		exit;
	}
}
