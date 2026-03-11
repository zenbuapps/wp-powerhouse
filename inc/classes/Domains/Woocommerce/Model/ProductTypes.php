<?php

declare(strict_types=1);

namespace J7\Powerhouse\Domains\Woocommerce\Model;

use J7\WpUtils\Classes\DTO;

/**
 * ProductTypes DTO
 *
 * @see https://github.com/woocommerce/woocommerce/blob/trunk/plugins/woocommerce/src/Enums/ProductType.php
 * */
class ProductTypes extends DTO {
	/** @var array<array{value: string, label: string, color: string}> $product_types */
	public array $product_types;

	/** @var array<string, array{value: string, label: string, color: string}> $product_types_mapper */
	protected static array $product_types_mapper = [
		'simple' => [
			'value' => 'simple',
			'label' => '簡單商品',
			'color' => 'processing', // 藍色
		],
		'variable' => [
			'value' => 'variable',
			'label' => '可變商品',
			'color' => 'magenta', // 紅色
		],
		'variation' => [
			'value' => 'variation',
			'label' => '商品變體',
			'color' => 'magenta', // 紅色
		],
		'subscription' => [
			'value' => 'subscription',
			'label' => '簡易訂閱',
			'color' => 'cyan', // 紫色
		],
		'variable-subscription' => [
			'value' => 'variable-subscription',
			'label' => '可變訂閱',
			'color' => 'purple', // 青色
		],
		'subscription_variation' => [
			'value' => 'subscription_variation',
			'label' => '訂閱變體',
			'color' => 'purple',
		],
		'grouped' => [
			'value' => 'grouped',
			'label' => '組合商品',
			'color' => 'orange', // 綠色
		],
		'external' => [
			'value' => 'external',
			'label' => '外部商品',
			'color' => 'lime', // 橘色
		],

	];

	/** 取得 ProductTypes @return self */
	public static function instance(): self {
		$product_type_array = \wc_get_product_types(); // key => name 的 array
		$product_types      = [];
		foreach ( $product_type_array as $product_type => $product_type_name ) {
			if ( isset( self::$product_types_mapper[ $product_type ] ) ) {
				$product_types[] = self::$product_types_mapper[ $product_type ];
				continue;
			}
			$product_types[] = [
				'value' => $product_type,
				'label' => $product_type_name,
				'color' => 'default',
			];
		}

		return new self(
			[
				'product_types' => $product_types,
			]
			);
	}
}
