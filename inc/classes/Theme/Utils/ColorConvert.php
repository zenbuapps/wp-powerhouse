<?php
/**
 * ColorConvert — Hex → OKLCH 色彩轉換
 *
 * 純函式靜態類別，對齊前端 culori（utils.tsx hexToOklch）的輸出格式與數值。
 * 公式：sRGB → linear RGB → LMS → OKLab → OKLCH（Björn Ottosson OKLab 定義，culori 同源）。
 */

declare(strict_types=1);

namespace J7\Powerhouse\Theme\Utils;

/** ColorConvert 色彩轉換工具 */
abstract class ColorConvert {

	/**
	 * 將 #RRGGBB hex 轉成 OKLCH 字串 "L% C H"
	 *
	 * 輸出格式對齊前端 utils.tsx hexToOklch：L 為百分比（0-100）、C 原值、H 角度（0-360）。
	 * 非法 hex 回 null，由呼叫端略過該 token（部分成功優於全有全無）。
	 *
	 * @param string $hex 形如 #RRGGBB（大小寫皆可，可含前後空白）。
	 * @return string|null 合法時回 "L% C H"，非法回 null。
	 */
	public static function hex_to_oklch( string $hex ): ?string {
		$rgb = self::hex_to_rgb($hex);
		if (null === $rgb) {
			return null;
		}
		[ $r, $g, $b ] = $rgb;

		// sRGB (0-1) → linear RGB（反伽馬）
		$lr = self::srgb_to_linear($r);
		$lg = self::srgb_to_linear($g);
		$lb = self::srgb_to_linear($b);

		// linear RGB → LMS（OKLab M1 矩陣）
		$l = 0.4122214708 * $lr + 0.5363325363 * $lg + 0.0514459929 * $lb;
		$m = 0.2119034982 * $lr + 0.6806995451 * $lg + 0.1073969566 * $lb;
		$s = 0.0883024619 * $lr + 0.2817188376 * $lg + 0.6299787005 * $lb;

		// 立方根
		$l_ = self::cbrt($l);
		$m_ = self::cbrt($m);
		$s_ = self::cbrt($s);

		// LMS' → OKLab（M2 矩陣）
		$ok_l = 0.2104542553 * $l_ + 0.7936177850 * $m_ - 0.0040720468 * $s_;
		$ok_a = 1.9779984951 * $l_ - 2.4285922050 * $m_ + 0.4505937099 * $s_;
		$ok_b = 0.0259040371 * $l_ + 0.7827717662 * $m_ - 0.8086757660 * $s_;

		// OKLab → OKLCH
		$c = sqrt($ok_a * $ok_a + $ok_b * $ok_b);
		$h = rad2deg(atan2($ok_b, $ok_a));
		if ($h < 0.0) {
			$h += 360.0;
		}
		// 近乎無彩（C 極小）時色相無意義，且浮點殘差會在純灰階（如白色）產生雜訊；
		// 統一歸零以對齊 culori 對灰階的輸出（白色 → "L% 0 0"）。
		// 閾值 1e-4 遠小於任何可辨識彩度（Blocksy 最淡彩 color7 C≈1.7e-3），不影響真實色。
		if ($c < 1e-4) {
			$c = 0.0;
			$h = 0.0;
		}

		$l_pct = $ok_l * 100.0;

		return sprintf('%s%% %s %s', self::num($l_pct), self::num($c), self::num($h));
	}

	/**
	 * 解析 #RRGGBB → 正規化 RGB（0-1）
	 *
	 * @param string $hex hex 字串。
	 * @return array{0:float,1:float,2:float}|null 合法回 [r,g,b]（0-1），非法回 null。
	 */
	private static function hex_to_rgb( string $hex ): ?array {
		$hex = strtolower(trim($hex));
		if (!preg_match('/^#[0-9a-f]{6}$/', $hex)) {
			return null;
		}
		$r = (float) hexdec(substr($hex, 1, 2)) / 255.0;
		$g = (float) hexdec(substr($hex, 3, 2)) / 255.0;
		$b = (float) hexdec(substr($hex, 5, 2)) / 255.0;
		return [ $r, $g, $b ];
	}

	/**
	 * 將 sRGB 單一通道反伽馬轉為 linear
	 *
	 * @param float $c 通道值（0-1）。
	 * @return float linear 值。
	 */
	private static function srgb_to_linear( float $c ): float {
		return $c <= 0.04045 ? $c / 12.92 : pow(( $c + 0.055 ) / 1.055, 2.4);
	}

	/**
	 * 立方根（含負值處理；LMS 理論非負，仍對浮點極小負值防護）
	 *
	 * @param float $x 輸入值。
	 * @return float 立方根。
	 */
	private static function cbrt( float $x ): float {
		if ($x >= 0.0) {
			return pow($x, 1.0 / 3.0);
		}
		return -pow(-$x, 1.0 / 3.0);
	}

	/**
	 * 將 float 轉成字串：固定小數格式避免科學記號（極小彩度用 %g 會輸出 "3.7e-8"，
	 * CSS 雖合法但不可靠），再去除尾零與多餘小數點。12 位小數精度遠超色彩可辨識需求。
	 *
	 * @param float $v 數值。
	 * @return string 格式化字串。
	 */
	private static function num( float $v ): string {
		$s = rtrim(rtrim(sprintf('%.12f', $v), '0'), '.');
		return '' === $s ? '0' : $s;
	}
}
