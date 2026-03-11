<?php

declare(strict_types=1);

namespace J7\Powerhouse\Domains\Subscription\Core;

/**
 * 重新嘗試付款
 *  */
final class RetryPayment {
	use \J7\WpUtils\Traits\SingletonTrait;

	/** Constructor */
	public function __construct() {
		/** @category 超過付款重試上限後，訂閱轉為取消狀態 (原本會停在保留狀態) */
		\add_filter('woocommerce_subscription_max_failed_payments_exceeded', '__return_true', 100, 2);

		/** @category 修改預設的 5 次，共 7 天 的重試付款設定 */
		\add_filter('wcs_default_retry_rules', [ $this, 'set_retry_rule' ]);
	}

	/**
	 * 修改預設的 5 次，共 7 天 的重試付款設定
	 *
	 * @param array<array<string, mixed>> $retry_rules 重試規則
	 * @return array<array<string, mixed>> 重試規則
	 */
	public function set_retry_rule( array $retry_rules ): array {
		$new_retry_rules = [
			[
				'retry_after_interval'            => \HOUR_IN_SECONDS, // how long to wait before retrying
				'email_template_customer'         => '', // don't bother the customer yet
				'email_template_admin'            => 'WCS_Email_Payment_Retry',
				'status_to_apply_to_order'        => 'pending',
				'status_to_apply_to_subscription' => 'on-hold',
			],
			[
				'retry_after_interval'            => \HOUR_IN_SECONDS, // how long to wait before retrying
				'email_template_customer'         => '', // don't bother the customer yet
				'email_template_admin'            => 'WCS_Email_Payment_Retry',
				'status_to_apply_to_order'        => 'pending',
				'status_to_apply_to_subscription' => 'on-hold',
			],
			[
				'retry_after_interval'            => \HOUR_IN_SECONDS, // how long to wait before retrying
				'email_template_customer'         => '', // don't bother the customer yet
				'email_template_admin'            => 'WCS_Email_Payment_Retry',
				'status_to_apply_to_order'        => 'pending',
				'status_to_apply_to_subscription' => 'on-hold',
			],
		];
		return $new_retry_rules;
	}
}
