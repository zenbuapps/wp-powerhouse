<?php
/**
 * Subscription RetryPayment 整合測試
 * 驗證 RetryPayment 類別的重試規則設定
 */

declare( strict_types=1 );

namespace Tests\Integration;

/**
 * Class SubscriptionRetryTest
 *
 * @group subscription
 */
class SubscriptionRetryTest extends TestCase {

	public function set_up(): void {
		parent::set_up();
		$this->skipIfSubscriptionsMissing();
	}

	// ========== 🔥 冒煙測試 ==========

	/**
	 * @test
	 * @group smoke
	 */
	public function retrypayment_類別應在訂閱載入時初始化(): void {
		\J7\Powerhouse\Domains\Subscription\Core\RetryPayment::instance();

		// 驗證 filter 被註冊
		$this->assertGreaterThan(
			0,
			\has_filter( 'woocommerce_subscription_max_failed_payments_exceeded' ),
			'max_failed_payments_exceeded filter 應被註冊'
		);
		$this->assertGreaterThan(
			0,
			\has_filter( 'wcs_default_retry_rules' ),
			'wcs_default_retry_rules filter 應被註冊'
		);
	}

	// ========== ✅ 快樂路徑 ==========

	/**
	 * @test
	 * @group happy
	 */
	public function set_retry_rule_應回傳_3_筆規則(): void {
		$retry = \J7\Powerhouse\Domains\Subscription\Core\RetryPayment::instance();
		$rules = $retry->set_retry_rule( [] );

		$this->assertCount( 3, $rules, '應回傳 3 筆重試規則' );
	}

	/**
	 * @test
	 * @group happy
	 */
	public function 重試規則應使用_HOUR_IN_SECONDS_作為間隔(): void {
		$retry = \J7\Powerhouse\Domains\Subscription\Core\RetryPayment::instance();
		$rules = $retry->set_retry_rule( [] );

		foreach ( $rules as $rule ) {
			$this->assertSame(
				\HOUR_IN_SECONDS,
				$rule['retry_after_interval'],
				'每筆重試規則間隔應為 HOUR_IN_SECONDS'
			);
		}
	}

	/**
	 * @test
	 * @group happy
	 */
	public function 重試規則應設定正確的狀態(): void {
		$retry = \J7\Powerhouse\Domains\Subscription\Core\RetryPayment::instance();
		$rules = $retry->set_retry_rule( [] );

		foreach ( $rules as $rule ) {
			$this->assertSame( 'pending', $rule['status_to_apply_to_order'] );
			$this->assertSame( 'on-hold', $rule['status_to_apply_to_subscription'] );
			$this->assertSame( 'WCS_Email_Payment_Retry', $rule['email_template_admin'] );
			$this->assertSame( '', $rule['email_template_customer'] );
		}
	}

	/**
	 * @test
	 * @group happy
	 */
	public function 重試規則應覆蓋原有規則(): void {
		$retry = \J7\Powerhouse\Domains\Subscription\Core\RetryPayment::instance();

		// 即使傳入舊規則，也應全部覆蓋
		$old_rules = [
			[ 'retry_after_interval' => 100 ],
			[ 'retry_after_interval' => 200 ],
		];
		$new_rules = $retry->set_retry_rule( $old_rules );

		$this->assertCount( 3, $new_rules );
		$this->assertNotSame( 100, $new_rules[0]['retry_after_interval'] );
	}

	/**
	 * @test
	 * @group happy
	 */
	public function max_failed_payments_exceeded_filter_應回傳_true(): void {
		// 註冊 filter 後，套用即回 true
		\J7\Powerhouse\Domains\Subscription\Core\RetryPayment::instance();

		$result = \apply_filters( 'woocommerce_subscription_max_failed_payments_exceeded', false );
		$this->assertTrue( $result, 'filter 應強制回傳 true（以便訂閱轉取消）' );
	}
}
