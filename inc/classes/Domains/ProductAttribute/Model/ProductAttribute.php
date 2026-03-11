<?php

declare(strict_types=1);

namespace J7\Powerhouse\Domains\ProductAttribute\Model;

use J7\WpUtils\Classes\DTO;

/** 商品 ProductAttribute DTO */
final class ProductAttribute extends DTO {

	/** @var string $id */
	public string $id;

	/** @var string $name 名稱 */
	public string $name;

	/** @var string $slug */
	public string $slug;

	/** @var 'select'|'text' $type 類型 */
	public string $type = 'select';

	/** @var 'menu_order'|'name'|'name_num'|'id' $order_by 排序 */
	public string $order_by = 'menu_order';

	/** @var bool $has_archives 是否顯示 */
	public bool $has_archives = false;

	/**
	 * 取得實例
	 *
	 * @param int|string $id 商品屬性 ID
	 */
	public static function instance( int|string $id ): self {
		$attribute = \wc_get_attribute( (int) $id);
		if (!$attribute) {
			throw new \Exception("Attribute not found: {$id}");
		}
		$attribute->id = (string) $attribute->id;

		$args = (array) $attribute;

		$instance = new self($args);
		return $instance;
	}

	/**
	 * 自訂驗證規則
	 *
	 * @throws \Exception 驗證失敗時拋出異常
	 */
	protected function validate(): void {
		if (!\in_array($this->order_by, [ 'menu_order','name','name_num','id' ], true)) {
			throw new \Exception('Invalid order_by, expect ' . implode(', ', [ 'menu_order','name','name_num','id' ]) . ', got ' . $this->order_by);
		}

		if (!\in_array($this->type, [ 'select','text' ], true)) {
			throw new \Exception('Invalid type, expect ' . implode(', ', [ 'select','text' ]) . ', got ' . $this->type);
		}
	}
}
