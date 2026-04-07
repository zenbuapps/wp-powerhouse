<?php

declare(strict_types=1);

namespace J7\Powerhouse\Shared\Helpers;

use J7\Powerhouse\Shared\Enums\EObjectType;

/**
 * 字串替換工具
 *
 * 支援範例：
 * "{{user.display_name}}" 可被 \$user->display_name 取代
 * "{{arr[display_name]}}" 可被 \$arr['display_name'] 取代
 *
 * 範例用法：
 * $replaced_template = (new ReplaceHelper($template))->replace($user)->replace($product)->replace($order)->get_replaced_template();
 */
final class ReplaceHelper {

	/** @var array<mixed>|object|null 要替換的物件或陣列，若尚未設定則為 null */
	private array|object|null $obj = null;

	/** @var string[] $all_placeholders_with_tag 所有匹配到的 placeholder（包含標籤，例如 "{{user.name}}"） */
	private array $all_placeholders_with_tag = [];
	/** @var string[] $all_placeholders 所有匹配到的 placeholder（不含標籤，例如 "user.name"） */
	private array $all_placeholders = [];

	/** @var string 經過替換後的暫存字串 */
	private string $filtered_template;


	/**
	 * Constructor
	 *
	 * @param string $raw_template 要處理的字串模板
	 * @param string $start_tag 開始標籤，預設為 '{{'
	 * @param string $end_tag 結束標籤，預設為 '}}'
	 */
	public function __construct(
		private readonly string $raw_template,
		private readonly string $start_tag = '{{',
		private readonly string $end_tag = '}}',
	) {
		$this->filtered_template = $this->raw_template;
		$this->init();
	}

	/**
	 * 傳入物件或陣列以執行替換
	 *
	 * @param mixed $obj 要替換的物件或陣列，例如 \WP_User $user 可能型別為 array|object|null
	 * @return self 回傳自身以支援 method chaining
	 */
	public function replace( mixed $obj ): self {
		if (!\is_array($obj) && !\is_object($obj)) {
			return $this;
		}
		$this->obj = $obj;
		$this->init();
		$this->filtered_template = \strtr( $this->filtered_template, $this->get_strtr_replace_array());
		return $this;
	}

	/**
	 * 取得替換後字串
	 *
	 * @return string
	 */
	public function get_replaced_template(): string {
		return $this->filtered_template;
	}


	/**
	 * 初始化
	 * 每次做完一個物件的取代可以重新初始化（會重新解析 template 中對應類型的 placeholders）
	 *
	 * @return void
	 */
	private function init(): void {
		// $this->obj 尚未設定（null）時不需要解析 placeholders
		if ($this->obj === null) {
			$this->all_placeholders_with_tag = [];
			$this->all_placeholders          = [];
			return;
		}
		// 取得目前物件的類型（由 EObjectType 決定）
		$object_type = EObjectType::get_type( $this->obj);

		// 使用 preg_quote 把 start/end tag 轉義，並只匹配此 object type 的 placeholders
		$type_value = $object_type->value ?? '';
		$pattern    = '/' . \preg_quote($this->start_tag, '/') . $type_value . '\s*(.*?)\s*' . \preg_quote($this->end_tag, '/') . '/';

		$matches = [];
		\preg_match_all( $pattern, $this->raw_template, $matches);

		$this->all_placeholders_with_tag = $matches[0] ?? [];
		$this->all_placeholders          = $matches[1] ?? [];
	}

	/**
	 * 解析 placeholder 字串為一系列 property / key 的步驟
	 * 例如解析 "arr[user].orders[0].customer.address" 將會產生 property/key 的序列
	 *
	 * @param string $placeholder 要解析的 placeholder，例: "user.display_name" 或 "arr[user]"
	 * @return array<int, array{value: string, type: string}> 解析後的陣列，type 的可能值為 'property' 或 'key'
	 */
	private function parse_placeholder( string $placeholder ): array {
		$result = [];

		// 用正則表達式匹配所有的屬性和陣列索引（允許英數底線）
		\preg_match_all('/(\w+)(?:\[(\w+)])?/', $placeholder, $matches, PREG_SET_ORDER);

		foreach ($matches as $match) {
			$property = $match[1];         // 屬性名稱
			$arrayKey = $match[2] ?? null; //phpcs:ignore // 陣列索引(如果有)

			// 每個主鍵先當作 property
			$result[] = [
				'value' => $property,
				'type'  => 'property',
			];

			// 如果有陣列索引, 加入一個 key 類型的元素
			if ($arrayKey !== null) {
				$result[] = [
					'value' => $arrayKey,
					'type'  => 'key',
				];
			}
		}

		return $result;
	}

	/**
	 * 取得該 placeholder 的字串值
	 * 若存取過程中找不到對應屬性或 key，會回傳原 placeholder
	 *
	 * @param string $placeholder 例如 "display_name" 或 "orders[0]"
	 * @return string
	 */
	private function get_placeholder_value( string $placeholder ): string {
		try {
			$parsed_array = $this->parse_placeholder( $placeholder);
			$value        = $this->obj;
			foreach ($parsed_array as $item) {
				if ($item['type'] === 'property') {
					$value = $value->{$item['value']};
				} else {
					$value = $value[ $item['value'] ];
				}
			}
			return (string) $value;
		} catch (\Throwable $th) {
			return $placeholder;
		}
	}


	/**
	 * 取得給 strtr 使用的替換陣列
	 * 取得後可以直接丟進去 strtr 做字串取代
	 *
	 * @return array<string, string>
	 */
	private function get_strtr_replace_array(): array {
		$strtr_array = [];
		foreach ($this->all_placeholders_with_tag as $index => $placeholder_with_tag) {
			$strtr_array[ $placeholder_with_tag ] = $this->get_placeholder_value($this->all_placeholders[ $index ]);
		}
		return $strtr_array;
	}
}
