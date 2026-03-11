<?php

declare(strict_types=1);

namespace J7\Powerhouse\Domains\Product\Model;

/**
 * 可變商品的變體相關 DTO
 */
final class Variation extends DTO {

	/** @var array<Product>|null $children 變體的商品 DTO 資料 */
	public array|null $children = null;

	/**
	 * 轉換為陣列
	 *
	 * @param array<string>|null $partials 要包含的 partial，可以輸入 'basic', 'detail', 'price', 'stock', 'sales', 'size', 'subscription', 'taxonomy', 'attribute', 'variation'
	 * @return array<string, mixed>
	 */
	public function to_array( $partials = null ): array {
		$partials = $partials ? $partials : [
			'basic',
			// 'detail',
			'price',
			'stock',
			'sales',
			'size',
			// 'subscription',
			'taxonomy',
			'attribute',
			'variation',
		];
		if (null === $this->children) {
			return [
				'children' => null,
			];
		}

		$array = [];
		foreach ($this->children as $product_dto) {
			$array[] = $product_dto->to_array($partials);
		}

		return [
			'children' => $array,
		];
	}

	/**
	 * 取得實例
	 *
	 * @param \WC_Product   $product 商品
	 * @param array<string> $meta_keys 要包含的 meta 欄位
	 */
	public static function instance( \WC_Product $product, array $meta_keys = [] ): self {
		$type = $product->get_type();
		if ( 'grouped' === $type ) {
			$instance = new self(
				[
					'children' => null,
				]
				);
			return $instance;
		}
		// 將變體加入倒 children
		$variation_ids = $product->get_children(); // get variations
		$children      = null;
		if ( $variation_ids ) {
			$variation_product_array = [];
			foreach ($variation_ids as $variation_id) {
				$variation_product = \wc_get_product( $variation_id );
				if (!$variation_product) {
					continue;
				}
				$variation_product_array[] = Product::instance( $variation_product, $meta_keys );
			}

			$children = $variation_product_array;
		}
		$args = [
			'children' => $children,
		];

		$instance = new self( $args );
		return $instance;
	}
}
