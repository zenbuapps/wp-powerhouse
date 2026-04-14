<?php
/**
 * Subscription Time Listener 整合測試
 * 驗證 LifeCycle 對 woocommerce_subscription_date_updated 的監聽與觸發行為
 */

declare( strict_types=1 );

namespace Tests\Integration;

use J7\Powerhouse\Domains\Subscription\Shared\Enums\Action;

/**
 * Class SubscriptionTimeListenerTest
 *
 * @group subscription
 */
class SubscriptionTimeListenerTest extends TestCase {

	public function set_up(): void {
		parent::set_up();
		$this->skipIfSubscriptionsMissing();
	}

	// ========== 🔥 冒煙測試 ==========

	/**
	 * @test
	 * @group smoke
	 */
	public function date_updated_listener_應在_lifecycle_初始化時註冊(): void {
		\J7\Powerhouse\Domains\Subscription\Core\LifeCycle::instance();

		$this->assertGreaterThan(
			0,
			\has_action( 'woocommerce_subscription_date_updated' ),
			'woocommerce_subscription_date_updated 應被監聽'
		);
	}

	// ========== ✅ 快樂路徑 ==========

	/**
	 * @test
	 * @group happy
	 */
	public function watch_trial_end_應在_trial_end_日期變更時觸發(): void {
		\J7\Powerhouse\Domains\Subscription\Core\LifeCycle::instance();

		$fired_args = null;
		\add_action(
			Action::WATCH_TRIAL_END->get_action_hook(),
			static function ( $subscription, $args ) use ( &$fired_args ): void {
				$fired_args = [ $subscription, $args ];
			},
			10,
			2
		);

		$sub_stub = $this->getMockBuilder( '\WC_Subscription' )
			->disableOriginalConstructor()
			->getMock();

		\do_action( 'woocommerce_subscription_date_updated', $sub_stub, Action::TRIAL_END->value, '2030-01-01 00:00:00' );

		$this->assertNotNull( $fired_args, 'WATCH_TRIAL_END action 應被觸發' );
		$this->assertArrayHasKey( 'datetime', $fired_args[1] );
		$this->assertSame( '2030-01-01 00:00:00', $fired_args[1]['datetime'] );
	}

	/**
	 * @test
	 * @group happy
	 */
	public function watch_next_payment_應在_next_payment_日期變更時觸發(): void {
		\J7\Powerhouse\Domains\Subscription\Core\LifeCycle::instance();

		$fired = 0;
		\add_action(
			Action::WATCH_NEXT_PAYMENT->get_action_hook(),
			static function () use ( &$fired ): void {
				++$fired;
			}
		);

		$sub_stub = $this->getMockBuilder( '\WC_Subscription' )
			->disableOriginalConstructor()
			->getMock();

		\do_action( 'woocommerce_subscription_date_updated', $sub_stub, Action::NEXT_PAYMENT->value, '2030-02-01 00:00:00' );

		$this->assertSame( 1, $fired );
	}

	/**
	 * @test
	 * @group happy
	 */
	public function watch_end_應在_end_日期變更時觸發(): void {
		\J7\Powerhouse\Domains\Subscription\Core\LifeCycle::instance();

		$fired = 0;
		\add_action(
			Action::WATCH_END->get_action_hook(),
			static function () use ( &$fired ): void {
				++$fired;
			}
		);

		$sub_stub = $this->getMockBuilder( '\WC_Subscription' )
			->disableOriginalConstructor()
			->getMock();

		\do_action( 'woocommerce_subscription_date_updated', $sub_stub, Action::END->value, '2030-03-01 00:00:00' );

		$this->assertSame( 1, $fired );
	}

	// ========== 🔀 邊緣案例 ==========

	/**
	 * @test
	 * @group edge
	 */
	public function 未被對應的_date_type_不應觸發任何_watch_action(): void {
		\J7\Powerhouse\Domains\Subscription\Core\LifeCycle::instance();

		$fired = 0;
		\add_action( Action::WATCH_TRIAL_END->get_action_hook(), static function () use ( &$fired ): void {
			++$fired;
		});
		\add_action( Action::WATCH_NEXT_PAYMENT->get_action_hook(), static function () use ( &$fired ): void {
			++$fired;
		});
		\add_action( Action::WATCH_END->get_action_hook(), static function () use ( &$fired ): void {
			++$fired;
		});

		$sub_stub = $this->getMockBuilder( '\WC_Subscription' )
			->disableOriginalConstructor()
			->getMock();

		\do_action( 'woocommerce_subscription_date_updated', $sub_stub, 'unknown_date_type', '2030-01-01 00:00:00' );

		$this->assertSame( 0, $fired, '未知 date_type 不應觸發任何 watch action' );
	}
}
