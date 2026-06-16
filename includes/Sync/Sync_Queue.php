<?php
/**
 * Pornește job-uri de sincronizare și programează chunk-uri în Action Scheduler.
 *
 * @package TrendyolSync\Sync
 */

namespace TrendyolSync\Sync;

use TrendyolSync\Data\Sync_Job_Repository;
use TrendyolSync\Logger\Logger;
use TrendyolSync\WooCommerce\Meta_Keys;

defined( 'ABSPATH' ) || exit;

/**
 * Class Sync_Queue
 */
class Sync_Queue {

	public const ACTION_PUSH_CHUNK = 'trendyol_sync_push_chunk';

	/**
	 * @var Sync_Job_Repository
	 */
	private $jobs;

	/**
	 * @var Product_Mapper
	 */
	private $mapper;

	/**
	 * @var Payload_Validator
	 */
	private $validator;

	/**
	 * @var Logger
	 */
	private $logger;

	/**
	 * @var Barcode_Resolver
	 */
	private $barcode_resolver;

	/**
	 * @var Category_Attribute_Service
	 */
	private $category_attribute_service;

	/**
	 * @param Sync_Job_Repository|null $jobs      Repository job-uri.
	 * @param Product_Mapper|null      $mapper    Mapper produse.
	 * @param Payload_Validator|null   $validator Validator payload.
	 * @param Logger|null              $logger    Logger.
	 */
	public function __construct(
		?Sync_Job_Repository $jobs = null,
		?Product_Mapper $mapper = null,
		?Payload_Validator $validator = null,
		?Logger $logger = null,
		?Barcode_Resolver $barcode_resolver = null,
		?Category_Attribute_Service $category_attribute_service = null
	) {
		$this->jobs      = $jobs ?? new Sync_Job_Repository();
		$this->mapper    = $mapper ?? new Product_Mapper();
		$this->validator = $validator ?? new Payload_Validator();
		$this->logger    = $logger ?? new Logger();
		$this->barcode_resolver = $barcode_resolver ?? new Barcode_Resolver();
		$this->category_attribute_service = $category_attribute_service ?? new Category_Attribute_Service();
	}

	/**
	 * Creează job, validează produsele și programează chunk-urile.
	 *
	 * @return array{success: bool, job_id?: int, message?: string, total?: int, chunks?: int, validation_failed?: int}
	 */
	public function start(): array {
		if ( ! trendyol_sync()->settings()->has_credentials() ) {
			return array(
				'success' => false,
				'message' => __( 'Completează credențialele API înainte de sincronizare.', 'trendyol-sync-for-woocommerce' ),
			);
		}

		$active_job = $this->jobs->find_active();

		if ( null !== $active_job ) {
			return array(
				'success' => false,
				'job_id'  => (int) $active_job['id'],
				'message' => __( 'Există deja o sincronizare în curs. Așteaptă finalizarea înainte de a porni una nouă.', 'trendyol-sync-for-woocommerce' ),
			);
		}

		$products = $this->get_sync_enabled_products();

		if ( empty( $products ) ) {
			return array(
				'success' => false,
				'message' => __( 'Nu există produse cu sincronizarea activată.', 'trendyol-sync-for-woocommerce' ),
			);
		}

		$job_id = $this->jobs->create( 0, get_current_user_id() );

		if ( $job_id <= 0 ) {
			return array(
				'success' => false,
				'message' => __( 'Nu s-a putut crea job-ul de sincronizare.', 'trendyol-sync-for-woocommerce' ),
			);
		}

		try {
			$prepared = $this->prepare_items( $products );
		} catch ( \Throwable $e ) {
			$this->jobs->update_status( $job_id, Sync_Job_Repository::STATUS_FAILED );
			$this->logger->error(
				__( 'Eroare la pregătirea payload-ului de sincronizare.', 'trendyol-sync-for-woocommerce' ),
				array(
					'job_id'  => $job_id,
					'message' => $e->getMessage(),
				)
			);

			return array(
				'success' => false,
				'job_id'  => $job_id,
				'message' => $e->getMessage(),
			);
		}

		$valid_items   = $prepared['items'];
		$product_map   = $prepared['product_map'];
		$failed_count  = $prepared['failed_count'];

		if ( empty( $valid_items ) ) {
			$this->jobs->set_totals( $job_id, 0, $failed_count );
			$this->jobs->update_status( $job_id, Sync_Job_Repository::STATUS_FAILED );

			return array(
				'success'            => false,
				'job_id'             => $job_id,
				'message'            => __( 'Niciun produs nu a trecut validarea pre-flight.', 'trendyol-sync-for-woocommerce' ),
				'validation_failed'  => $failed_count,
			);
		}

		$this->jobs->set_totals( $job_id, count( $valid_items ), $failed_count );

		$chunks = $this->chunk_items( $valid_items, $product_map );
		$scheduled = 0;

		foreach ( $chunks as $index => $chunk ) {
			$args = array(
				'job_id'       => $job_id,
				'chunk_index'  => $index,
				'items'        => $chunk['items'],
				'product_map'  => $chunk['product_map'],
			);

			if ( $this->schedule_push_chunk( $args ) ) {
				++$scheduled;
			}
		}

		if ( 0 === $scheduled ) {
			$this->jobs->update_status( $job_id, Sync_Job_Repository::STATUS_FAILED );

			return array(
				'success' => false,
				'job_id'  => $job_id,
				'message' => __( 'Nu s-au putut programa acțiunile de sincronizare.', 'trendyol-sync-for-woocommerce' ),
			);
		}

		$this->logger->info(
			__( 'Sincronizare pornită.', 'trendyol-sync-for-woocommerce' ),
			array(
				'job_id'  => $job_id,
				'total'   => count( $valid_items ),
				'chunks'  => $scheduled,
				'failed'  => $failed_count,
			)
		);

		return array(
			'success'           => true,
			'job_id'            => $job_id,
			'total'             => count( $valid_items ),
			'chunks'            => $scheduled,
			'validation_failed' => $failed_count,
			'message'           => __( 'Sincronizarea a fost programată în fundal.', 'trendyol-sync-for-woocommerce' ),
		);
	}

	/**
	 * @return \WC_Product[]
	 */
	private function get_sync_enabled_products(): array {
		if ( ! function_exists( 'wc_get_products' ) ) {
			return array();
		}

		$products = wc_get_products(
			array(
				'status'  => array( 'publish' ),
				'type'    => array( 'simple', 'variable' ),
				'limit'   => -1,
				'return'  => 'objects',
				'orderby' => 'ID',
				'order'   => 'ASC',
				'meta_query' => array(
					array(
						'key'   => Meta_Keys::SYNC_STATUS,
						'value' => Meta_Keys::SYNC_ENABLED,
					),
				),
			)
		);

		$settings = trendyol_sync()->settings()->get_stored_settings();
		if ( isset( $settings['sync_only_modified'] ) && 'yes' === $settings['sync_only_modified'] ) {
			$products = array_filter(
				$products,
				static function ( $product ) {
					if ( ! $product instanceof \WC_Product ) {
						return false;
					}
					$last_sync = Meta_Keys::get_last_sync_at( $product->get_id() );
					if ( '' === $last_sync ) {
						return true;
					}
					$last_sync_ts = strtotime( $last_sync . ' UTC' );
					$modified_ts  = strtotime( $product->get_date_modified( 'edit' ) ? $product->get_date_modified( 'edit' )->date( 'Y-m-d H:i:s' ) . ' UTC' : '' );

					return false === $last_sync_ts || false === $modified_ts || $modified_ts > $last_sync_ts;
				}
			);
		}

		return array_filter(
			$products,
			static function ( $product ) {
				return $product instanceof \WC_Product;
			}
		);
	}

	/**
	 * Mapează și validează produsele; construiește product_map (barcode → product_id).
	 *
	 * @param \WC_Product[] $products Lista produse WooCommerce.
	 * @return array{items: array<int, array<string, mixed>>, product_map: array<string, int>, failed_count: int}
	 */
	private function prepare_items( array $products ): array {
		$this->ensure_barcodes( $products );

		$payload       = $this->mapper->map_products( $products );
		$this->hydrate_category_required_attributes( $payload['items'] );
		$valid_items   = array();
		$product_map   = array();
		$failed_count  = 0;

		$barcode_index_result = $this->build_barcode_product_index( $products );
		$barcode_to_product   = $barcode_index_result['index'];

		if ( ! empty( $barcode_index_result['duplicates'] ) ) {
			throw new \RuntimeException(
				sprintf(
					/* translators: %s list of duplicate barcodes */
					__( 'Există barcode-uri duplicate: %s', 'trendyol-sync-for-woocommerce' ),
					implode( ', ', $barcode_index_result['duplicates'] )
				)
			);
		}

		foreach ( $payload['items'] as $item ) {
			$validation = $this->validator->validate_item( $item );

			if ( ! $validation['valid'] ) {
				++$failed_count;
				$barcode = (string) ( $item['barcode'] ?? '' );
				$product_id = $barcode_to_product[ $barcode ] ?? 0;

				$this->logger->warning(
					__( 'Produs respins la validare pre-flight.', 'trendyol-sync-for-woocommerce' ),
					array(
						'product_id' => $product_id,
						'barcode'    => $barcode,
						'errors'     => $validation['errors'],
					)
				);

				if ( $product_id > 0 ) {
					update_post_meta( $product_id, Meta_Keys::SYNC_STATUS, Meta_Keys::SYNC_ERROR );
					Meta_Keys::set_last_sync_error( $product_id, implode( '; ', $validation['errors'] ) );
				}

				continue;
			}

			$barcode = (string) $item['barcode'];
			$product_id = $barcode_to_product[ $barcode ] ?? 0;

			if ( $product_id > 0 ) {
				$product_map[ $barcode ] = $product_id;
				update_post_meta( $product_id, Meta_Keys::SYNC_STATUS, Meta_Keys::SYNC_PENDING );
				Meta_Keys::set_last_sync_error( $product_id, '' );
			}

			$valid_items[] = $item;
		}

		return array(
			'items'        => $valid_items,
			'product_map'  => $product_map,
			'failed_count' => $failed_count,
		);
	}

	/**
	 * Index barcode → product_id pentru toate liniile mapate.
	 *
	 * @param \WC_Product[] $products Produse sursă.
	 * @return array{index: array<string, int>, duplicates: string[]}
	 */
	private function build_barcode_product_index( array $products ): array {
		$index   = array();
		$duplicates = array();
		$adapter = new \TrendyolSync\WooCommerce\Product_Adapter();
		$grouper = new Variant_Grouper( $adapter );

		foreach ( $products as $product ) {
			if ( ! $product instanceof \WC_Product ) {
				continue;
			}

			$group = $grouper->group_variable_product( $product );

			foreach ( $group['items'] as $adapted ) {
				$barcode = (string) ( $adapted['barcode'] ?? '' );

				if ( '' !== $barcode ) {
					if ( isset( $index[ $barcode ] ) && (int) $index[ $barcode ] !== (int) ( $adapted['product_id'] ?? 0 ) ) {
						$duplicates[] = $barcode;
					}
					$index[ $barcode ] = (int) ( $adapted['product_id'] ?? 0 );
				}
			}
		}

		return array(
			'index'      => $index,
			'duplicates' => array_values( array_unique( $duplicates ) ),
		);
	}

	/**
	 * @param \WC_Product[] $products Produse.
	 * @return void
	 */
	private function ensure_barcodes( array $products ): void {
		foreach ( $products as $product ) {
			if ( ! $product instanceof \WC_Product ) {
				continue;
			}

			$this->barcode_resolver->persist_missing_for_product( $product );

			if ( $product->is_type( 'variable' ) ) {
				foreach ( $product->get_children() as $variation_id ) {
					$variation = wc_get_product( (int) $variation_id );
					if ( $variation instanceof \WC_Product ) {
						$this->barcode_resolver->persist_missing_for_product( $variation );
					}
				}
			}
		}
	}

	/**
	 * @param array<int, array<string, mixed>> $items Linii payload.
	 * @return void
	 */
	private function hydrate_category_required_attributes( array $items ): void {
		$category_ids = array();

		foreach ( $items as $item ) {
			$category_id = isset( $item['categoryId'] ) ? absint( $item['categoryId'] ) : 0;
			if ( $category_id > 0 ) {
				$category_ids[] = $category_id;
			}
		}

		$category_ids = array_values( array_unique( $category_ids ) );

		foreach ( $category_ids as $category_id ) {
			$schema = $this->category_attribute_service->get_required_attribute_schema( $category_id );
			if ( ! empty( $schema ) ) {
				$this->validator->set_required_attributes_for_category( $category_id, $schema );
			}
		}
	}

	/**
	 * Împarte items în chunk-uri de TRENDYOL_SYNC_CHUNK_SIZE.
	 *
	 * @param array<int, array<string, mixed>> $items       Elemente API.
	 * @param array<string, int>               $product_map Mapare barcode → product_id.
	 * @return array<int, array{items: array<int, array<string, mixed>>, product_map: array<string, int>}>
	 */
	private function chunk_items( array $items, array $product_map ): array {
		$chunk_size = max( 1, (int) TRENDYOL_SYNC_CHUNK_SIZE );
		$chunks     = array();

		$item_chunks = array_chunk( $items, $chunk_size );

		foreach ( $item_chunks as $item_chunk ) {
			$map = array();

			foreach ( $item_chunk as $item ) {
				$barcode = (string) ( $item['barcode'] ?? '' );

				if ( '' !== $barcode && isset( $product_map[ $barcode ] ) ) {
					$map[ $barcode ] = $product_map[ $barcode ];
				}
			}

			$chunks[] = array(
				'items'       => $item_chunk,
				'product_map' => $map,
			);
		}

		return $chunks;
	}

	/**
	 * Programează acțiunea push chunk în grupul trendyol-sync.
	 *
	 * @param array<string, mixed> $args Argumente hook.
	 * @return bool
	 */
	private function schedule_push_chunk( array $args ): bool {
		if ( function_exists( 'as_enqueue_async_action' ) ) {
			as_enqueue_async_action(
				self::ACTION_PUSH_CHUNK,
				array( $args ),
				TRENDYOL_SYNC_AS_GROUP
			);

			return true;
		}

		if ( function_exists( 'as_schedule_single_action' ) ) {
			as_schedule_single_action(
				time(),
				self::ACTION_PUSH_CHUNK,
				array( $args ),
				TRENDYOL_SYNC_AS_GROUP
			);

			return true;
		}

		return wp_schedule_single_event( time(), self::ACTION_PUSH_CHUNK, array( $args ) );
	}
}
