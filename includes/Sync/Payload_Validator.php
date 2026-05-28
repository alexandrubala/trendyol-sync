<?php
/**
 * Validator pre-flight înainte de punerea în coada de sincronizare.
 *
 * @package TrendyolSync\Sync
 */

namespace TrendyolSync\Sync;

defined( 'ABSPATH' ) || exit;

/**
 * Class Payload_Validator
 */
class Payload_Validator {

	/** Cote TVA acceptate de Trendyol (RO / TR marketplace). */
	private const ALLOWED_VAT_RATES = array( 0, 1, 10, 18, 20 );

	/**
	 * Definiții atribute obligatorii per categorie (attributeId => allowCustom).
	 * Populat din serviciul Category Attribute List în sprinturi viitoare.
	 *
	 * @var array<int, array<int, bool>>
	 */
	private $required_attributes_by_category = array();

	/**
	 * @param array<int, array<int, bool>>|null $required_by_category Map categoryId → [attributeId => allowCustom].
	 */
	public function __construct( ?array $required_by_category = null ) {
		if ( null !== $required_by_category ) {
			$this->required_attributes_by_category = $required_by_category;
		}
	}

	/**
	 * Setează schema atributelor obligatorii pentru o categorie.
	 *
	 * @param int               $category_id        ID categorie Trendyol.
	 * @param array<int, bool>  $required_attributes attributeId => allowCustom.
	 * @return void
	 */
	public function set_required_attributes_for_category( int $category_id, array $required_attributes ): void {
		$this->required_attributes_by_category[ $category_id ] = $required_attributes;
	}

	/**
	 * Validează un element items[] (payload mapat).
	 *
	 * @param array<string, mixed> $item Element din Product_Mapper.
	 * @return array{valid: bool, errors: string[]}
	 */
	public function validate_item( array $item ): array {
		$errors = array();

		if ( '' === trim( (string) ( $item['barcode'] ?? '' ) ) ) {
			$errors[] = __( 'Lipsește codul de bare.', 'trendyol-sync' );
		}

		if ( '' === trim( (string) ( $item['title'] ?? '' ) ) ) {
			$errors[] = __( 'Lipsește titlul produsului.', 'trendyol-sync' );
		}

		if ( empty( $item['brandId'] ) ) {
			$errors[] = __( 'Lipsește brandul Trendyol.', 'trendyol-sync' );
		}

		if ( empty( $item['categoryId'] ) ) {
			$errors[] = __( 'Lipsește categoria Trendyol.', 'trendyol-sync' );
		}

		if ( ! isset( $item['quantity'] ) || '' === (string) $item['quantity'] ) {
			$errors[] = __( 'Lipsește cantitatea (stocul).', 'trendyol-sync' );
		} elseif ( (int) $item['quantity'] < 0 ) {
			$errors[] = __( 'Cantitatea (stocul) nu poate fi negativă.', 'trendyol-sync' );
		}

		if ( '' === trim( (string) ( $item['stockCode'] ?? '' ) ) ) {
			$errors[] = __( 'Lipsește codul de stoc (SKU).', 'trendyol-sync' );
		}

		if ( ! isset( $item['dimensionalWeight'] ) || (float) $item['dimensionalWeight'] <= 0 ) {
			$errors[] = __( 'Lipsește greutatea dimensională.', 'trendyol-sync' );
		}

		if ( '' === trim( (string) ( $item['description'] ?? '' ) ) ) {
			$errors[] = __( 'Lipsește descrierea produsului.', 'trendyol-sync' );
		}

		if ( ! isset( $item['listPrice'] ) || (float) $item['listPrice'] <= 0 ) {
			$errors[] = __( 'Lipsește prețul de listă.', 'trendyol-sync' );
		}

		if ( ! isset( $item['salePrice'] ) || (float) $item['salePrice'] <= 0 ) {
			$errors[] = __( 'Lipsește prețul de vânzare.', 'trendyol-sync' );
		}

		if ( isset( $item['listPrice'], $item['salePrice'] )
			&& (float) $item['listPrice'] < (float) $item['salePrice'] ) {
			$errors[] = __( 'Prețul de listă nu poate fi mai mic decât prețul de vânzare.', 'trendyol-sync' );
		}

		if ( ! isset( $item['vatRate'] ) || ! in_array( (int) $item['vatRate'], self::ALLOWED_VAT_RATES, true ) ) {
			$errors[] = __( 'Lipsește sau este invalid cota TVA (vatRate).', 'trendyol-sync' );
		}

		if ( '' === trim( (string) ( $item['productMainId'] ?? '' ) ) ) {
			$errors[] = __( 'Lipsește codul principal al produsului (productMainId).', 'trendyol-sync' );
		}

		$images = $item['images'] ?? array();

		if ( ! is_array( $images ) || empty( $images ) ) {
			$errors[] = __( 'Lipsește cel puțin o imagine validă (HTTPS).', 'trendyol-sync' );
		}

		$attributes = $item['attributes'] ?? array();

		if ( ! is_array( $attributes ) || empty( $attributes ) ) {
			$errors[] = __( 'Lipsesc atributele obligatorii ale categoriei.', 'trendyol-sync' );
		} else {
			$errors = array_merge( $errors, $this->validate_attribute_entries( $attributes ) );
			$errors = array_merge(
				$errors,
				$this->validate_required_category_attributes(
					(int) ( $item['categoryId'] ?? 0 ),
					$attributes
				)
			);
		}

		return array(
			'valid'  => empty( $errors ),
			'errors' => $errors,
		);
	}

	/**
	 * Validează payload complet { items: [...] }.
	 *
	 * @param array{items?: array<int, array<string, mixed>>} $payload Payload mapat.
	 * @return array{valid: bool, errors: array<int, string[]>, flat_errors: string[]}
	 */
	public function validate_payload( array $payload ): array {
		$items = $payload['items'] ?? array();

		if ( ! is_array( $items ) || empty( $items ) ) {
			return array(
				'valid'       => false,
				'errors'      => array(),
				'flat_errors' => array( __( 'Payload-ul nu conține niciun produs.', 'trendyol-sync' ) ),
			);
		}

		$all_errors   = array();
		$flat_errors  = array();
		$index        = 0;

		foreach ( $items as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}

			$result = $this->validate_item( $item );

			if ( ! $result['valid'] ) {
				$all_errors[ $index ] = $result['errors'];
				$flat_errors          = array_merge( $flat_errors, $result['errors'] );
			}

			++$index;
		}

		return array(
			'valid'       => empty( $all_errors ),
			'errors'      => $all_errors,
			'flat_errors' => array_values( array_unique( $flat_errors ) ),
		);
	}

	/**
	 * Validează structura fiecărui atribut din listă.
	 *
	 * @param array<int, array<string, mixed>> $attributes Atribute API.
	 * @return string[]
	 */
	private function validate_attribute_entries( array $attributes ): array {
		$errors = array();

		foreach ( $attributes as $position => $attribute ) {
			if ( ! is_array( $attribute ) ) {
				$errors[] = sprintf(
					/* translators: %d: index atribut (1-based) */
					__( 'Atributul #%d are format invalid.', 'trendyol-sync' ),
					$position + 1
				);
				continue;
			}

			$attribute_id = (int) ( $attribute['attributeId'] ?? 0 );

			if ( $attribute_id <= 0 ) {
				$errors[] = sprintf(
					/* translators: %d: index atribut */
					__( 'Atributul #%d nu are attributeId.', 'trendyol-sync' ),
					$position + 1
				);
				continue;
			}

			$has_value_id   = isset( $attribute['attributeValueId'] ) && (int) $attribute['attributeValueId'] > 0;
			$has_value_ids  = ! empty( $attribute['attributeValueIds'] );
			$has_custom     = isset( $attribute['customAttributeValue'] )
				&& '' !== trim( (string) $attribute['customAttributeValue'] );

			if ( ! $has_value_id && ! $has_value_ids && ! $has_custom ) {
				$errors[] = sprintf(
					/* translators: 1: index, 2: attribute id */
					__( 'Atributul #%1$d (ID %2$d) nu are valoare setată.', 'trendyol-sync' ),
					$position + 1,
					$attribute_id
				);
			}
		}

		return $errors;
	}

	/**
	 * Verifică atributele obligatorii definite pentru categorie (dacă schema este disponibilă).
	 *
	 * @param int                              $category_id ID categorie.
	 * @param array<int, array<string, mixed>> $attributes  Atribute trimise.
	 * @return string[]
	 */
	private function validate_required_category_attributes( int $category_id, array $attributes ): array {
		if ( $category_id <= 0 || empty( $this->required_attributes_by_category[ $category_id ] ) ) {
			return array();
		}

		$required = $this->required_attributes_by_category[ $category_id ];
		$present  = array();

		foreach ( $attributes as $attribute ) {
			if ( ! is_array( $attribute ) ) {
				continue;
			}

			$id = (int) ( $attribute['attributeId'] ?? 0 );

			if ( $id > 0 ) {
				$present[ $id ] = true;
			}
		}

		$errors = array();

		foreach ( $required as $attribute_id => $allow_custom ) {
			if ( isset( $present[ $attribute_id ] ) ) {
				continue;
			}

			$errors[] = sprintf(
				/* translators: %d: Trendyol attribute ID */
				__( 'Lipsește atributul obligatoriu al categoriei (ID %d).', 'trendyol-sync' ),
				(int) $attribute_id
			);
		}

		return $errors;
	}
}
