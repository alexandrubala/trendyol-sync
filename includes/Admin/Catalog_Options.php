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

	private const SEARCH_LIMIT = 30;

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
		$cached = $this->cache->get_brand_options();

		if ( is_array( $cached ) && ! empty( $cached ) ) {
			return $cached;
		}

		return $this->build_brand_options_from_pages();
	}

	/**
	 * Categorii leaf pentru &lt;select&gt;: [ id => "Părinte > Copil" ].
	 *
	 * @return array<int, string>
	 */
	public function get_category_options(): array {
		$cached = $this->cache->get_category_options();

		if ( is_array( $cached ) && ! empty( $cached ) ) {
			return $cached;
		}

		return $this->build_category_options_from_tree();
	}

	/**
	 * Reconstruiește și salvează opțiunile flatten în cache.
	 *
	 * @return array{brand_count: int, category_count: int}
	 */
	public function rebuild_option_caches(): array {
		$brands     = $this->build_brand_options_from_pages();
		$categories = $this->build_category_options_from_tree();

		$this->cache->set_brand_options( $brands );
		$this->cache->set_category_options( $categories );

		return array(
			'brand_count'    => count( $brands ),
			'category_count' => count( $categories ),
		);
	}

	/**
	 * Căutare branduri pentru Select2 AJAX.
	 *
	 * @param string $term  Termen căutare.
	 * @param int    $limit Număr maxim rezultate.
	 * @return array<int, array{id: int, text: string}>
	 */
	public function search_brands( string $term, int $limit = self::SEARCH_LIMIT ): array {
		return $this->search_options( $this->get_brand_options(), $term, $limit );
	}

	/**
	 * Căutare categorii pentru Select2 AJAX.
	 *
	 * @param string $term  Termen căutare.
	 * @param int    $limit Număr maxim rezultate.
	 * @return array<int, array{id: int, text: string}>
	 */
	public function search_categories( string $term, int $limit = self::SEARCH_LIMIT ): array {
		return $this->search_options( $this->get_category_options(), $term, $limit );
	}

	/**
	 * Etichetă brand după ID (pentru valoarea selectată).
	 *
	 * @param int $brand_id ID brand.
	 * @return string
	 */
	public function get_brand_label( int $brand_id ): string {
		if ( $brand_id <= 0 ) {
			return '';
		}

		$options = $this->get_brand_options();

		return (string) ( $options[ $brand_id ] ?? '' );
	}

	/**
	 * Etichetă categorie după ID.
	 *
	 * @param int $category_id ID categorie.
	 * @return string
	 */
	public function get_category_label( int $category_id ): string {
		if ( $category_id <= 0 ) {
			return '';
		}

		$options = $this->get_category_options();

		return (string) ( $options[ $category_id ] ?? '' );
	}

	/**
	 * @return array<int, string>
	 */
	private function build_brand_options_from_pages(): array {
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
	 * @return array<int, string>
	 */
	private function build_category_options_from_tree(): array {
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
	 * @param array<int, string> $options Opțiuni id => label.
	 * @param string           $term    Termen.
	 * @param int              $limit   Limită.
	 * @return array<int, array{id: int, text: string}>
	 */
	private function search_options( array $options, string $term, int $limit ): array {
		$term    = trim( $this->to_lower( $term ) );
		$results = array();
		$limit   = max( 1, min( 100, $limit ) );

		if ( '' === $term ) {
			$slice = array_slice( $options, 0, $limit, true );

			foreach ( $slice as $id => $label ) {
				$results[] = array(
					'id'   => (int) $id,
					'text' => (string) $label,
				);
			}

			return $results;
		}

		foreach ( $options as $id => $label ) {
			if ( false === $this->contains_insensitive( (string) $label, $term ) ) {
				continue;
			}

			$results[] = array(
				'id'   => (int) $id,
				'text' => (string) $label,
			);

			if ( count( $results ) >= $limit ) {
				break;
			}
		}

		return $results;
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

		if ( isset( $data['id'], $data['name'] ) ) {
			return array( $data );
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

	/**
	 * @param string $value Text.
	 * @return string
	 */
	private function to_lower( string $value ): string {
		if ( function_exists( 'mb_strtolower' ) ) {
			return mb_strtolower( $value );
		}

		return strtolower( $value );
	}

	/**
	 * @param string $haystack Text.
	 * @param string $needle   Termen.
	 * @return bool
	 */
	private function contains_insensitive( string $haystack, string $needle ): bool {
		if ( function_exists( 'mb_stripos' ) ) {
			return false !== mb_stripos( $haystack, $needle );
		}

		return false !== stripos( $haystack, $needle );
	}
}
