<?php

declare(strict_types=1);

namespace J7\Powerhouse\Domains\Product\Utils;

/** Class CRUD */
abstract class CRUD {

	/**
	 * 新增商品
	 * 預設創建簡單商品
	 *
	 * @param array<string, mixed> $data 資料，可以用 set_ 方法更新
	 * @param array<string, mixed> $meta_data 元資料
	 *
	 * @return int
	 */
	public static function create_product( array $data = [], array $meta_data = [] ): int {
		$product = new \WC_Product_Simple();
		$data    = \wp_parse_args(
			$data,
			[
				'name' => __('new product', 'powerhouse'),
			]
			);
		Save::data($product, $data );
		$product->save();
		return $product->get_id();
	}




	/**
	 * 更新商品
	 *
	 * @param \WC_Product          $product 商品
	 * @param array<string, mixed> $data 資料，可以用 set_ 方法更新
	 * @param array<string, mixed> $meta_data 元資料
	 *
	 * @return void
	 */
	public static function update_product( \WC_Product $product, array $data = [], array $meta_data = [] ): void {
		Save::data($product, $data );
		$product->save();
		Save::meta_data($product, $meta_data );
		\wc_delete_product_transients($product->get_id());
	}

	/**
	 * Format product Select
	 *
	 * @param \WC_Product $product Product.
	 * @return array<string, mixed>
	 */
	public static function format_select( $product) { // phpcs:ignore

		if ( ! ( $product instanceof \WC_Product ) ) { // @phpstan-ignore-line
			return [];
		}
		$product_id = $product->get_id();

		$base_array = [
			// Get Product General Info
			'id'        => (string) $product_id,
			'type'      => $product->get_type(),
			'name'      => $product->get_name(),
			'slug'      => $product->get_slug(),
			'permalink' => \get_permalink( $product_id ),
		];

		return $base_array;
	}

	/**
	 * 取得產品價格 HTML。
	 *
	 * @param \WC_Product $product WooCommerce 產品實例。
	 *
	 * @return string 產品價格的 HTML 字串。
	 */
	public static function get_price_html( \WC_Product $product ): string {
		$product_type = $product->get_type();

		/**
		 * ▼ 強制刷新價格
		 * Woocommerce 的折價天數邏輯預設是用天數來判斷
		 * 即使資料是儲存成 timestamp 但倒數計時到的時候
		 * 價格並不會刷新回原價
		 */
		if (!$product->is_on_sale()) {
			\update_post_meta($product->get_id(), '_price', $product->get_regular_price());
			$product->save();
			$refreshed_product = \wc_get_product($product->get_id());
			if ($refreshed_product) {
				$product = $refreshed_product;
			}
		}

		return match ($product_type) {
			'subscription' => Subscription::get_price_html($product),
			'variable-subscription' => '',
			default => $product->get_price_html(),
		};
	}


	/**
	 * Get Max Min Price
	 * TODO 可能可以改 SQL 查詢更快
	 *
	 * @return array{max_price:float, min_price:float}
	 */
	public static function get_max_min_prices(): array {
		$transient_key = 'max_min_prices';

		$max_min_prices = \get_transient( $transient_key );

		if ( false !== $max_min_prices ) {
			/** @var array{max_price:float, min_price:float} */
			return $max_min_prices;
		}
		// 獲取最高價格的商品
		$max_price_products = \wc_get_products(
			[
				'order'    => 'DESC', // 遞減排序
				'orderby'  => 'meta_value_num',
				'meta_key' => '_price',
				'limit'    => 1,         // 僅獲取一個結果
				'status'   => 'publish', // 僅包含已發佈的商品
			]
		);
		$max_price          = 0;
		if ( ! empty( $max_price_products ) ) {
			$max_price_product = reset( $max_price_products );     // 獲取第一個元素
			$max_price         = $max_price_product?->get_price(); // 獲取最高價格
		}

		// 獲取最低價格的商品
		$min_price_products = \wc_get_products(
			[
				'order'    => 'ASC', // 遞增排序
				'orderby'  => 'meta_value_num',
				'meta_key' => '_price',
				'limit'    => 1,         // 僅獲取一個結果
				'status'   => 'publish', // 僅包含已發佈的商品
			]
		);

		$min_price = 0;
		if ( ! empty( $min_price_products ) ) {
			$min_price_product = reset( $min_price_products );     // 獲取第一個元素
			$min_price         = $min_price_product?->get_price(); // 獲取最低價格
		}

		$max_min_prices = [
			'max_price' => (float) $max_price,
			'min_price' => (float) $min_price,
		];

		// @phpstan-ignore-next-line
		\set_transient( $transient_key, $max_min_prices, 1 * DAY_IN_SECONDS );

		return $max_min_prices;
	}

	/**
	 * 擴展 wc_get_products 的 meta_query
	 * 例如你想要讓 wc_get_products 可以篩選某個 product meta key 的值
	 *
	 * @see https://github.com/woocommerce/woocommerce/wiki/wc_get_products-and-WC_Product_Query#adding-custom-parameter-support
	 *
	 * @param array<string, mixed> $query - Args for WP_Query.
	 * @param array<string, mixed> $query_vars - Query vars from WC_Product_Query.
	 * @return array<string, mixed> modified $query
	 */
	public static function extend_meta_query( $query, $query_vars ): array {
		/** @var array<string, array{key:string, value:string, compare:string}> $meta_keys */
		$meta_keys = \apply_filters('powerhouse/product/extend_meta_query', [], $query, $query_vars);

		foreach ($meta_keys as $meta_key => $condition) {
			if ( isset($query_vars[ $meta_key ]) ) {
				$query['meta_query'][] = $condition; // @phpstan-ignore-line
			}
		}
		return $query;
	}
}
