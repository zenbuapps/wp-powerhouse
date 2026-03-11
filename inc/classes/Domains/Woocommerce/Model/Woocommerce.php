<?php

declare(strict_types=1);

namespace J7\Powerhouse\Domains\Woocommerce\Model;

use J7\WpUtils\Classes\DTO;
use J7\Powerhouse\Utils\Base as PowerhouseUtils;

/** Get Woocommerce API DTO */
class Woocommerce extends DTO {

	/** @var array<string, string> $countries TW: 台灣 */
	public array $countries;

	/** @var array{slug: string, symbol: string} $currency 貨幣 */
	public array $currency;

	/** @var array<array{value: string, label: string, hierarchical: bool, publicly_queryable: bool}> $product_taxonomies 商品分類 */
	public array $product_taxonomies;

	/** @var int $notify_low_stock_amount 低庫存通知數量 */
	public int $notify_low_stock_amount;

	/** @var string $dimension_unit 尺寸單位 */
	public string $dimension_unit;

	/** @var string $weight_unit 重量單位 */
	public string $weight_unit;

	/** @var array{
	 * "product_base": string,
	 * "category_base": string,
	 * "tag_base": string,
	 * "attribute_base": string,
	 * "use_verbose_page_rules": bool,
	 * "product_rewrite_slug": string,
	 * "category_rewrite_slug": string,
	 * "tag_rewrite_slug": string,
	 * "attribute_rewrite_slug": string} $permalinks 永久連結 */
	public array $permalinks;

	/** @var bool $manage_stock 管理庫存 */
	public bool $manage_stock;

	/** @var array<array{value: string, label: string, color: string}> $product_types 商品類型 */
	public array $product_types;

	/** @var array<array{value: string, label: string, color: string}> $order_statuses 訂單狀態 */
	public array $order_statuses;

	/** @var array<array{value: string, label: string, color: string}> $post_statuses 文章狀態 */
	public array $post_statuses;

	/** @var array<array{value: string, label: string, color: string}> $product_stock_statuses 商品庫存狀態 */
	public array $product_stock_statuses;

	/** 取得 ProductTypes @return self */
	public static function instance(): self {
		$countries = \WC()->countries->get_countries();
		$currency  = (string) \get_option( 'woocommerce_currency', 'TWD' );

		$wc_settings = Settings::instance();

		$product_taxonomies = PowerhouseUtils::get_taxonomy_options();

		$args = array_merge(
			[
				'countries'               => $countries,
				'currency'                => [
					'slug'   => $currency,
					'symbol' => html_entity_decode( \get_woocommerce_currency_symbol($currency) ),
				],
				'product_taxonomies'      => $product_taxonomies,
				'notify_low_stock_amount' => (int) $wc_settings->notify_low_stock_amount,
				'dimension_unit'          => $wc_settings->dimension_unit,
				'weight_unit'             => $wc_settings->weight_unit,
				'permalinks'              => \wc_get_permalink_structure(),
				'manage_stock'            => Settings::instance()->manage_stock,
			],
			ProductTypes::instance()->to_array(),
			OrderStatuses::instance()->to_array(),
			PostStatuses::instance()->to_array(),
			ProductStockStatuses::instance()->to_array(),
		);

		return new self($args);
	}
}
