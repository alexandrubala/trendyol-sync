<?php
/**
 * Opțiuni select (branduri / categorii) din cache-ul transient Sprint 2.
 *
 * @package TrendyolSync\Admin
 */

namespace TrendyolSync\Admin;

use TrendyolSync\Cache\Transient_Cache;

defined( 'ABSPATH' ) || exit;

/**
 * Class Catalog_Options
 */
class Catalog_Options {

	/**
	 * @var Transient_Cache
	 */
	private $cache;

	/**
	 * @param Transient_Cache|null $cache Strat cache.
	 */
	public function __construct( ?Transient_Cache $cache = null ) {
		$this->cache = $cache ?? trendyol_sync()->cache();
	}

	/**
	 * Branduri pentru &lt;select&gt;: [ id => name ].
	 *
	 * @return array<int, string>
	 */
	public function get_brand_options(): array {
		$options = array();
		$page    = 0;
		$size    = 1000;

		do {
			$data = $this->cache->get_brands( $page, $size );

			if ( null === $data ) {
				break;
			}

			$brands = $this->extract_brands_list( $data );

			if ( empty( $brands ) ) {
				break;
			}

			foreach ( $brands as $brand ) {
				if ( ! is_array( $brand ) ) {
					continue;
				}

				$id   = isset( $brand['id'] ) ? (int) $brand['id'] : 0;
				$name = isset( $brand['name'] ) ? (string) $brand['name'] : '';

				if ( $id > 0 && '' !== $name ) {
					$options[ $id ] = $name;
				}
			}

			if ( count( $brands ) < $size ) {
				break;
			}

			++$page;
		} while ( $page < 50 );

		natcasesort( $options );

		return $options;
	}

	/**
	 * Categorii leaf pentru &lt;select&gt;: [ id => "Părinte > Copil" ].
	 *
	 * @return array<int, string>
	 */
	public function get_category_options(): array {
		$tree = $this->cache->get_category_tree();

		if ( null === $tree ) {
			return array();
		}

		$nodes = $this->extract_category_nodes( $tree );
		$flat  = array();

		$this->flatten_categories( $nodes, array(), $flat );

		return $flat;
	}

	/**
	 * @param array<string, mixed>|array<int, mixed> $data Date cache/API.
	 * @return array<int, array<string, mixed>>
	 */
	private function extract_brands_list( array $data ): array {
		if ( isset( $data['brands'] ) && is_array( $data['brands'] ) ) {
			return $data['brands'];
		}

		if ( $this->is_list( $data ) ) {
			return $data;
		}

		return array();
	}

	/**
	 * @param array<string, mixed>|array<int, mixed> $data Arbore categorii.
	 * @return array<int, array<string, mixed>>
	 */
	private function extract_category_nodes( array $data ): array {
		if ( isset( $data['categories'] ) && is_array( $data['categories'] ) ) {
			return $data['categories'];
		}

		if ( $this->is_list( $data ) ) {
			return $data;
		}

		return array();
	}

	/**
	 * @param array<int, array<string, mixed>> $nodes     Noduri curente.
	 * @param string[]                         $path      Breadcrumb nume.
	 * @param array<int, string>               $collector Rezultat id => label.
	 * @return void
	 */
	private function flatten_categories( array $nodes, array $path, array &$collector ): void {
		foreach ( $nodes as $node ) {
			if ( ! is_array( $node ) ) {
				continue;
			}

			$id   = isset( $node['id'] ) ? (int) $node['id'] : 0;
			$name = isset( $node['name'] ) ? (string) $node['name'] : '';

			if ( '' === $name && isset( $node['displayName'] ) ) {
				$name = (string) $node['displayName'];
			}

			$current_path = $path;

			if ( '' !== $name ) {
				$current_path[] = $name;
			}

			$children = array();

			if ( ! empty( $node['subCategories'] ) && is_array( $node['subCategories'] ) ) {
				$children = $node['subCategories'];
			} elseif ( ! empty( $node['children'] ) && is_array( $node['children'] ) ) {
				$children = $node['children'];
			}

			if ( ! empty( $children ) ) {
				$this->flatten_categories( $children, $current_path, $collector );
			} elseif ( $id > 0 && ! empty( $current_path ) ) {
				$collector[ $id ] = implode( ' › ', $current_path );
			}
		}
	}

	/**
	 * @param array<mixed> $data Date.
	 * @return bool
	 */
	private function is_list( array $data ): bool {
		if ( function_exists( 'array_is_list' ) ) {
			return array_is_list( $data );
		}

		return array_keys( $data ) === range( 0, count( $data ) - 1 );
	}
}
