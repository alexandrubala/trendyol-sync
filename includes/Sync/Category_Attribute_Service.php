<?php
/**
 * Încarcă schema atributelor obligatorii per categorie Trendyol.
 *
 * @package TrendyolSync\Sync
 */

namespace TrendyolSync\Sync;

use TrendyolSync\API\Endpoints\Category_Attributes;

defined( 'ABSPATH' ) || exit;

/**
 * Class Category_Attribute_Service
 */
class Category_Attribute_Service {

	/**
	 * @var Category_Attributes
	 */
	private $endpoint;

	/**
	 * @param Category_Attributes|null $endpoint Endpoint API.
	 */
	public function __construct( ?Category_Attributes $endpoint = null ) {
		$this->endpoint = $endpoint ?? new Category_Attributes( trendyol_sync()->api_client(), trendyol_sync()->cache() );
	}

	/**
	 * @param int $category_id ID categorie Trendyol.
	 * @return array<int, bool> attributeId => allowCustom.
	 */
	public function get_required_attribute_schema( int $category_id ): array {
		$response = $this->endpoint->get_attributes( $category_id, true );

		if ( empty( $response['success'] ) || empty( $response['data'] ) || ! is_array( $response['data'] ) ) {
			return array();
		}

		$attributes = array();
		$data       = $response['data'];

		if ( isset( $data['categoryAttributes'] ) && is_array( $data['categoryAttributes'] ) ) {
			$data = $data['categoryAttributes'];
		} elseif ( isset( $data['attributes'] ) && is_array( $data['attributes'] ) ) {
			$data = $data['attributes'];
		}

		foreach ( $data as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}

			$attribute_id = isset( $row['attribute']['id'] ) ? absint( $row['attribute']['id'] ) : absint( $row['id'] ?? 0 );
			if ( $attribute_id <= 0 ) {
				continue;
			}

			$is_required = ! empty( $row['required'] ) || ! empty( $row['isRequired'] );
			if ( ! $is_required ) {
				continue;
			}

			$attributes[ $attribute_id ] = ! empty( $row['allowCustom'] ) || ! empty( $row['allowCustomValue'] );
		}

		return $attributes;
	}
}
