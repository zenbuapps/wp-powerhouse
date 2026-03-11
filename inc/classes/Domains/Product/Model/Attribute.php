<?php

declare(strict_types=1);

namespace J7\Powerhouse\Domains\Product\Model;

/**
 * 商品屬性 DTO
 * 注意：屬性不一定都有變體，屬性也不一定全都是 taxonomy
 * 全局範圍的商品屬性才會是 taxonomy
 */
final class Attribute extends DTO {

	/** @var array<array{
	 * id: string,
	 * name: string,
	 * taxonomy: string,
	 * variation: 'yes' | 'no',
	 * visible: 'yes' | 'no',
	 * options: array<array{value: string, label: string}>,
	 * position: int,
	 * }> | array<string, string> $attributes
	 *
	 * 如果是商品(variable/simple product)存取此屬性就會拿到可選的規格 array
	 * 如果用變體(variation)存取此屬性，會拿到 array<string, string>
	 * */
	public array $attributes;

	/** @var array<string, string> $default_attributes 預設屬性 */
	public array $default_attributes;

	/** @var string $attribute_summary 變體屬性的名稱 */
	public string $attribute_summary = '';

	/**
	 * 取得實例
	 *
	 * @param \WC_Product $product 商品
	 */
	public static function instance( \WC_Product $product ): self {
		// 組合商品屬性 $attributes_arr
		$attributes = $product->get_attributes(); // get attributes object
		/** @var array<string, string> $default_attributes */
		$default_attributes = $product->get_default_attributes();

		$attributes_arr = [];
		foreach ( $attributes as $key => $attribute ) {
			// 如果是 "可變商品" 會顯示選項
			if ( $attribute instanceof \WC_Product_Attribute ) {

				$id               = $attribute?->get_id();
				$attributes_arr[] = [
					'id'        => $id ? (string) $id : '',
					'name'      => \wc_attribute_label( $attribute?->get_name() ),
					'taxonomy'  => $attribute->get_taxonomy(),
					'variation' => \wc_bool_to_string( $attribute->get_variation() ), // 是否用於變體
					'visible'   => \wc_bool_to_string( $attribute->get_visible() ), // 是否可見
					'options'   => self::get_options( $attribute ),
					'position'  => $attribute->get_position(),
				];
			}

			// 如果是 "變體" 會顯示 屬性 => 屬性值 array
			if ( is_string( $key ) && is_string( $attribute ) ) {
				$decoded_key                    = urldecode( $key );
				$attributes_arr[ $decoded_key ] = $attribute;
			}
		}

		$attributes_string = '';
		if (method_exists($product, 'get_attribute_summary')) {
			/** @var \WC_Product_Variation $product */
			$attributes_string = $product->get_attribute_summary();
		}

		$args = [
			'attributes'         => $attributes_arr,
			'default_attributes' => $default_attributes,
			'attribute_summary'  => $attributes_string,
		];

		$instance = new self( $args );
		return $instance;
	}

	/**
	 * 取得屬性選項
	 *
	 * @param \WC_Product_Attribute $attribute 商品屬性
	 * @return array<array{value: string, label: string}>
	 */
	public static function get_options( \WC_Product_Attribute $attribute ): array {
		$options = [];
		// 先判斷是不是 taxonomy
		if ( !$attribute->is_taxonomy() ) {
			// 如果不是，就用 slugs 來組 slug=>slug
			$slugs = $attribute->get_slugs();

			foreach ( $slugs as $slug ) {
				$options[] = [
					'value' => $slug,
					'label' => $slug,
				];
			}
			return $options;
		}

		// 如果是 taxonomy，則用 term 來組 id=>name
		$terms = $attribute->get_terms() ?? [];
		foreach ( $terms as $term ) {
			// 這邊沒打錯，因為 WC 後台創建是可以創建中文 slug 的，即使 db 是 encoded 的 slug
			// 但如果直接拿 encoded 後的 slug 會與該屬性既有的 term mapping 不到，相當的神奇
			$options[] = [
				'value' => $term->name,
				'label' => $term->name,
			];
		}

		return $options;
	}
}
