<?php

declare(strict_types=1);

namespace J7\Powerhouse\Contracts\DTOs;

use J7\WpUtils\Classes\DTO;

/** 用來描述表單欄位 */
class FormFieldDTO extends DTO {

	/** @var string 表單種類 */
	public string $element;

	/** @var array<string, mixed> 元素 attribute */
	public array $attributes = [];

	/** @var array<string, mixed> 選項 */
	public array $options = [];
}
