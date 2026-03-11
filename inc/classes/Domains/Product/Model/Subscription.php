<?php

declare(strict_types=1);

namespace J7\Powerhouse\Domains\Product\Model;

use J7\Powerhouse\Domains\Product\Utils\Subscription as SubscriptionUtils;

/** 訂閱相關 DTO */
final class Subscription extends DTO {

	/** @var string $subscription_price 訂閱價格 */
	public string $subscription_price = '';

	/** @var 'day' | 'week' | 'month' | 'year' $subscription_period 訂閱週期 日/週/月/年 */
	public string $subscription_period = 'month';

	/** @var string $subscription_period_interval 訂閱週期間隔，每 {N} 個 日/週/月/年 */
	public string $subscription_period_interval = '';

	/** @var string $subscription_length 訂閱持續時間， {N} 個 日/週/月/年 */
	public string $subscription_length = '';

	/** @var string $subscription_sign_up_fee 訂閱註冊費用 */
	public string $subscription_sign_up_fee = '';

	/** @var string $subscription_trial_length 試用期長度， {N} 個 日/週/月/年 */
	public string $subscription_trial_length = '';

	/** @var 'day' | 'week' | 'month' | 'year' $subscription_trial_period 試用期週期（天、週、月、年） */
	public string $subscription_trial_period = 'month';

	/**
	 * 轉換為陣列
	 *
	 * @return array<string, mixed>
	 */
	public function to_array(): array {
		if (!class_exists('\WC_Subscriptions')) {
			return [];
		}
		return parent::to_array();
	}

	/**
	 * 取得實例
	 *
	 * @param \WC_Product $product 商品
	 */
	public static function instance( $product ): self {
		$fields = SubscriptionUtils::get_fields(false);

		if (!class_exists('\WC_Subscription')) {
			$instance = new self();
			return $instance;
		}

		$args = [];
		foreach ($fields as $field) {
			$value = $product->get_meta("_{$field}");
			if ($value !== '') {
				$args[ $field ] = $product->get_meta("_{$field}");
			}
		}

		/**
		 * @var array{
		 *  subscription_price: string,
		 *  subscription_period: string,
		 *  subscription_period_interval: string,
		 *  subscription_length: string,
		 *  subscription_sign_up_fee: string,
		 *  subscription_trial_length: string,
		 *  subscription_trial_period: string,
		 * } $args
		 */
		$instance = new self( $args );
		return $instance;
	}

	/**
	 * 驗證欄位數據
	 *
	 * @return void
	 * @throws \InvalidArgumentException 如果驗證失敗
	 */
	protected function validate(): void {
		$this->validate_period($this->subscription_period);
		$this->validate_numeric_string($this->subscription_price);
		$this->validate_numeric_string($this->subscription_period_interval);
		$this->validate_numeric_string($this->subscription_length);
		$this->validate_numeric_string($this->subscription_sign_up_fee);
		$this->validate_numeric_string($this->subscription_trial_length);
		$this->validate_period($this->subscription_trial_period);
	}

	/**
	 * 驗證訂閱週期
	 *
	 * @param string $period 訂閱週期
	 * @throws \InvalidArgumentException 如果訂閱週期無效，則拋出異常
	 */
	private function validate_period( string $period ): void {
		if (!in_array($period, [ 'day', 'week', 'month', 'year' ], true)) {
			throw new \InvalidArgumentException("Invalid period, expected: day, week, month, year, got: {$period}");
		}
	}

	/**
	 * 驗證數值字串
	 *
	 * @param string $value 數值字串
	 * @throws \InvalidArgumentException 如果數值字串無效，則拋出異常
	 */
	private function validate_numeric_string( string $value ): void {
		if (!is_numeric($value) && '' !== $value) {
			throw new \InvalidArgumentException("Invalid numeric string, expected: numeric, got: {$value}");
		}
	}
}
