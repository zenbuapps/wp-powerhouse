<?php

declare(strict_types=1);

namespace J7\Powerhouse\Domains\Product\Model;

/**
 * 商品 Product DTO
 * 各個個子 DTO 組合而成商品 DTO
 */
final class Product extends DTO {

	/** @var string $basic 商品基本資料 Class */
	protected string $basic;

	/** @var string $detail 商品詳細資料 Class */
	protected string $detail;

	/** @var string $price 商品價格 Class */
	protected string $price;

	/** @var string $taxonomy 商品分類、標籤、品牌 Class */
	protected string $taxonomy;

	/** @var string $stock 商品庫存 Class */
	protected string $stock;

	/** @var string $sales 促銷、交叉銷售相關 Class */
	protected string $sales;

	/** @var string $size 商品尺寸 Class */
	protected string $size;

	/** @var string $attribute 商品屬性 Class */
	protected string $attribute;

	/** @var string $subscription 訂閱相關 Class */
	protected string $subscription;

	/** @var string $variation 可變商品的變體 Class */
	protected string $variation;

	/** @var array<string> $meta_keys 要包含的 meta 欄位 */
	protected array $meta_keys = [];

	/**
	 * 取得實例
	 *
	 * @param \WC_Product   $product 商品
	 * @param array<string> $meta_keys 要包含的 meta 欄位
	 */
	public static function instance(
		\WC_Product $product,
		array $meta_keys = []
	): self {

		$args = [
			'basic'        => Basic::class,
			'detail'       => Detail::class,
			'price'        => Price::class,
			'stock'        => Stock::class,
			'sales'        => Sales::class,
			'size'         => Size::class,
			'subscription' => Subscription::class,
			'taxonomy'     => Taxonomy::class,
			'attribute'    => Attribute::class,
			'variation'    => Variation::class,
			'product'      => $product,
			'meta_keys'    => $meta_keys,
		];

		$instance = new self($args);
		return $instance;
	}

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
		$array    = [];

		foreach ($partials as $partial) {
			if ('variation' === $partial) {
				$instance = $this->{$partial}::instance($this->product, $this->meta_keys);
				$array    = array_merge($array, $instance->to_array($partials));
				continue;
			}

			$instance = $this->{$partial}::instance($this->product);
			$array    = array_merge($array, $instance->to_array());
		}

		return array_merge(
			$array,
			$this->get_meta_keys_array(),
		);
	}

	/**
	 * 取得 meta keys array
	 *
	 * @return array<string, mixed>
	 */
	protected function get_meta_keys_array(): array {
		$meta_keys_array = [];
		foreach ($this->meta_keys as $meta_key) {
			$meta_keys_array[ $meta_key ] = $this->product->get_meta( $meta_key );
		}

		// 可以改寫 meta_keys
		// @phpstan-ignore-next-line
		return \apply_filters( 'powerhouse/product/get_meta_keys_array', $meta_keys_array, $this->product );
	}
}
