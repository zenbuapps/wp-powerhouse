<?php

declare(strict_types=1);

namespace J7\Powerhouse\Contracts\DTOs;

use J7\WpUtils\Classes\DTO;

/** 用來描述表單欄位 */
class FormFieldDTO extends DTO {

	/** @var string 表單種類 */
	public string $element = '';

	/** @var array<string, mixed> 元素 attribute */
	public array $attributes = [];

	// region NodeDefinition 擴充屬性

	/** @var string 欄位 key，對應 NodeDTO.args 的 key */
	public string $name = '';

	/** @var string 顯示標籤 */
	public string $label = '';

	/**
	 * 欄位類型
	 *
	 * @var string text|number|select|textarea|template_editor|switch|date|json
	 */
	public string $type = 'text';

	/** @var bool 是否必填 */
	public bool $required = false;

	/** @var mixed 預設值 */
	public mixed $default_value = '';

	/** @var string placeholder 文字 */
	public string $placeholder = '';

	/** @var string 欄位說明（tooltip） */
	public string $description = '';

	/**
	 * select 類型的選項
	 *
	 * @var array<int, array{value: string, label: string}> 選項列表
	 */
	public array $options = [];

	/**
	 * 額外驗證規則
	 *
	 * @var array<int, array{rule: string, value: mixed, message: string}> 驗證規則列表
	 */
	public array $validation = [];

	/** @var int 欄位排序 */
	public int $sort = 0;

	/**
	 * 條件顯示規則
	 *
	 * @var array<int, array{field: string, operator: string, value: mixed}> 條件顯示規則列表
	 */
	public array $depends_on = [];

	// endregion NodeDefinition 擴充屬性
}
