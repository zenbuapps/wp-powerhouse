<?php

declare(strict_types=1);

namespace J7\Powerhouse\Domains\Product\Service;

/**
 * 訂閱商品週期標籤
 * $10/天
 * $10/2天
 * $70/7天 => $70/週
 * $70/週
 * $140/2週 => $70/雙週
 * $100/月
 * $100/2個月
 * $100/3個月 => $100/季
 * $100/6個月 => $100/半年
 * $100/12個月 => $100/年
 * $1000/年
 * $1000/2年
 */
final class PeriodLabel {

	/**
	 * Period label
	 *
	 * @var string
	 */
	public string $period_label;

	/**
	 * @param 'day'|'week'|'month'|'year' $period          訂閱週期
	 * @param int                         $period_interval 訂閱週期間隔
	 */
	public function __construct(
		private string $period,
		private int $period_interval = 1
	) {
		$this->period_label = match ($this->period) {
			'day' => '天',
			'week' => '週',
			'month' => '月',
			'year' => '年',
		};
	}

	/**
	 * 取得訂閱週期標籤
	 *
	 * @param string $addon_before 前置字串
	 * @param string $addon_after 後置字串
	 *
	 * @return string 訂閱週期標籤
	 */
	public function get_label( string $addon_before = '', string $addon_after = '' ): string {

		if ($this->period_interval <= 1) {
			return "{$addon_before}{$this->period_label}{$addon_after}";
		}

		if ('day' === $this->period) {
			return match ($this->period_interval) {
				7 => "{$addon_before}週{$addon_after}",
				14 => "{$addon_before}雙週{$addon_after}",
				default => "{$addon_before}{$this->period_label}{$addon_after}"
			};
		}

		if ('week' === $this->period) {
			return match ($this->period_interval) {
				2 => "{$addon_before}雙週{$addon_after}",
				default => "{$addon_before}{$this->period_label}{$addon_after}"
			};
		}

		if ('month' === $this->period) {
			return match ($this->period_interval) {
				3 => "{$addon_before}季{$addon_after}",
				6 => "{$addon_before}半年{$addon_after}",
				9 => "{$addon_before}3季{$addon_after}",
				12 => "{$addon_before}年{$addon_after}",
				default => "{$addon_before}個{$this->period_label}{$addon_after}"
			};
		}

		return "{$addon_before}{$this->period_label}{$addon_after}";
	}
}
