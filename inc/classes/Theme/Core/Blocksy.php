<?php
/**
 * Blocksy — 偵測 Blocksy 主題並讀取其調色盤
 *
 * 集中所有對 Blocksy 主題的耦合與容錯。任何偵測/讀取失敗一律安全降級
 * （回空陣列 → 呼叫端保留 Powerhouse 預設色），永不 fatal。
 */

declare(strict_types=1);

namespace J7\Powerhouse\Theme\Core;

use J7\Powerhouse\Theme\Utils\ColorConvert;

/** Blocksy 主題色橋接服務 */
final class Blocksy {
	use \J7\WpUtils\Traits\SingletonTrait;

	/**
	 * Blocksy palette 色槽 → Powerhouse Theme 屬性名（不含 dash）的對應表。
	 * 此表為前後端單一事實來源（前端預覽映射須一致）。
	 * color5（邊框色）無對應 daisyUI token，刻意略過。
	 *
	 * @var array<string, string>
	 */
	private const COLOR_MAP = [
		'color1' => 'p',   // primary
		'color2' => 's',   // secondary
		'color3' => 'bc',  // base-content
		'color4' => 'n',   // neutral
		'color6' => 'b3',  // base-300
		'color7' => 'b2',  // base-200
		'color8' => 'b1',  // base-100
	];

	/**
	 * 當前啟用主題是否為 Blocksy（含 Blocksy 子主題）
	 *
	 * 子主題的 template 仍回父主題 slug 'blocksy'，故此判斷一行涵蓋父/子主題。
	 *
	 * @return bool
	 */
	public function is_blocksy(): bool {
		return \get_template() === 'blocksy';
	}

	/**
	 * 讀取當前 Blocksy 調色盤，攤平為 ['color1'=>'#hex', ...]（僅含格式合法者）
	 *
	 * 多重守衛：blocksy_manager 函式存在 → colors 物件存在 → method 存在 → 逐色 hex 驗證。
	 * 任何環節失敗或例外一律回空陣列。
	 *
	 * @return array<string, string> 鍵為 color1..color8，值為 #RRGGBB；缺色或非法則不含該鍵。
	 */
	public function get_palette(): array {
		if (!\function_exists('blocksy_manager')) {
			return [];
		}

		try {
			$manager = \blocksy_manager();
			if (!\is_object($manager) || !isset($manager->colors) || !\is_object($manager->colors) || !\method_exists($manager->colors, 'get_color_palette')) {
				return [];
			}

			$palette = $manager->colors->get_color_palette();
			if (!\is_array($palette)) {
				return [];
			}

			$flat = [];
			for ($i = 1; $i <= 8; $i++) {
				$key   = "color{$i}";
				$entry = $palette[ $key ] ?? null;
				$color = \is_array($entry) ? ( $entry['color'] ?? null ) : null;
				if (\is_string($color) && \preg_match('/^#[0-9a-fA-F]{6}$/', $color)) {
					$flat[ $key ] = $color;
				}
			}
			return $flat;
		} catch (\Throwable $e) {
			return [];
		}
	}

	/**
	 * 將當前 Blocksy 調色盤轉成 Powerhouse Theme 屬性的 OKLCH 覆寫值
	 *
	 * 依 COLOR_MAP 逐項轉換；缺色或轉換失敗則略過該項（部分成功優於全有全無）。
	 *
	 * @return array<string, string> ['p'=>'L% C H', 's'=>..., 'b1'=>...]，鍵為 Theme 屬性名。
	 */
	public function get_oklch_overrides(): array {
		$palette   = $this->get_palette();
		$overrides = [];
		foreach (self::COLOR_MAP as $color_key => $prop) {
			$hex = $palette[ $color_key ] ?? null;
			if (!\is_string($hex)) {
				continue;
			}
			$oklch = ColorConvert::hex_to_oklch($hex);
			if (null !== $oklch) {
				$overrides[ $prop ] = $oklch;
			}
		}
		return $overrides;
	}

	/**
	 * 取得 8 色 hex 供前端預覽（index 0..7 對應 color1..color8）
	 *
	 * 缺色補空字串以維持 index 對齊，前端自行略過空值。
	 *
	 * @return list<string> 長度 8 的 hex 陣列（缺色為空字串）。
	 */
	public function get_palette_hex_for_preview(): array {
		$palette = $this->get_palette();
		$hex     = [];
		for ($i = 1; $i <= 8; $i++) {
			$hex[] = $palette[ "color{$i}" ] ?? '';
		}
		return $hex;
	}
}
