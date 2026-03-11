<?php

declare(strict_types=1);

namespace J7\Powerhouse\Domains\Post\Service;

use J7\WpUtils\Classes\DTO;

/**
 * Meta Query Clause的類
 */
final class MetaQueryClause extends DTO {

	/** @var string 查詢的 key */
	public string $key;

	/** @var mixed 查詢的值 */
	public mixed $value;

	/** @var string 查詢的比較方式 */
	public string $compare = '=';

	/**
	 * 修改查詢的參數
	 *
	 * @param array<string, mixed> $arr 查詢的參數
	 * @return self
	 */
	public function set( array $arr ): self {
		$this->key     = isset($arr['key']) ? (string) $arr['key'] : $this->key;
		$this->value   = $arr['value'] ?? $this->value;
		$this->compare = isset($arr['compare']) ? (string) $arr['compare'] : $this->compare;
		return $this;
	}

	/**
	 * 格式化查詢的值
	 *
	 * @param string $value 查詢的值，例如: -{value}-
	 * @return self
	 */
	public function format_value( string $value ): self {
		$arr         = explode('{value}', $value);
		$this->value = $arr[0] . (string) $this->value . ( $arr[1] ?? '' );
		return $this;
	}

	/** Setter */
	public function __set( string $property, $value ): void {
		$this->$property = $value;
	}
}
