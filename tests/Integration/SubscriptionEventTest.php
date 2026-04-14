<?php
/**
 * Subscription LifeCycle Event 整合測試
 * 驗證 LifeCycle 類別對 WooCommerce Subscriptions 事件的橋接行為
 */

declare( strict_types=1 );

namespace Tests\Integration;

use J7\Powerhouse\Domains\Subscription\Shared\Enums\Action;
use J7\Powerhouse\Domains\Subscription\Shared\Enums\Status;

/**
 * Class SubscriptionEventTest
 *
 * @group subscription
 */
class SubscriptionEventTest extends TestCase {

	public function set_up(): void {
		parent::set_up();
		$this->skipIfSubscriptionsMissing();
	}

	// ========== 🔥 冒煙測試 ==========

	/**
	 * @test
	 * @group smoke
	 */
	public function loader_應在_wc_subscriptions_載入時初始化_lifecycle(): void {
		// LifeCycle::__construct 會註冊多個 add_action；驗證其中一個
		$this->assertGreaterThan(
			0,
			\has_action( 'woocommerce_subscription_payment_complete' ),
			'LifeCycle 應註冊 woocommerce_subscription_payment_complete'
		);
	}

	/**
	 * @test
	 * @group smoke
	 */
	public function action_enum_的_get_action_hook_應回傳正確前綴(): void {
		$this->assertSame( 'powerhouse_subscription_at_initial_payment_complete', Action::INITIAL_PAYMENT_COMPLETE->get_action_hook() );
		$this->assertSame( 'powerhouse_subscription_at_subscription_failed', Action::SUBSCRIPTION_FAILED->get_action_hook() );
		$this->assertSame( 'powerhouse_subscription_at_end', Action::END->get_action_hook() );
	}

	// ========== ✅ 快樂路徑 ==========

	/**
	 * @test
	 * @group happy
	 */
	public function renewal_order_created_filter_應觸發_renewal_order_created_action(): void {
		$life_cycle = \J7\Powerhouse\Domains\Subscription\Core\LifeCycle::instance();

		$fired_payload = null;
		\add_action(
			Action::RENEWAL_ORDER_CREATED->get_action_hook(),
			static function ( $subscription, $args ) use ( &$fired_payload ): void {
				$fired_payload = [ $subscription, $args ];
			},
			10,
			2
		);

		// 使用 WC_Order 簡單建立 + stub 的 subscription_id
		$order = \wc_create_order();

		// 直接呼叫 class method 而非真的觸發 wcs_renewal_order_created
		$life_cycle->renewal_order_created( $order, 12345 );

		$this->assertNotNull( $fired_payload, 'action 應被觸發' );
		$this->assertSame( 12345, $fired_payload[0], 'action 第一參數應為 subscription (int)' );
		$this->assertArrayHasKey( 'renewal_order', $fired_payload[1] );
		$this->assertSame( $order, $fired_payload[1]['renewal_order'] );
	}

	/**
	 * @test
	 * @group happy
	 */
	public function subscription_failed_method_僅在成功到失敗時觸發_action(): void {
		$life_cycle = \J7\Powerhouse\Domains\Subscription\Core\LifeCycle::instance();

		$fired = 0;
		\add_action(
			Action::SUBSCRIPTION_FAILED->get_action_hook(),
			static function () use ( &$fired ): void {
				++$fired;
			}
		);

		// non-subscription → 不觸發
		$life_cycle->subscription_failed( 'active', 'on-hold', new \stdClass() );
		$this->assertSame( 0, $fired );

		// invalid status → 不觸發
		$sub_stub = $this->getMockBuilder( '\WC_Subscription' )
			->disableOriginalConstructor()
			->getMock();
		$life_cycle->subscription_failed( 'nonsense', 'also-nonsense', $sub_stub );
		$this->assertSame( 0, $fired );
	}

	/**
	 * @test
	 * @group happy
	 */
	public function subscription_success_method_僅在失敗到_active_時觸發_action(): void {
		$life_cycle = \J7\Powerhouse\Domains\Subscription\Core\LifeCycle::instance();

		$fired = 0;
		\add_action(
			Action::SUBSCRIPTION_SUCCESS->get_action_hook(),
			static function () use ( &$fired ): void {
				++$fired;
			}
		);

		// non-subscription → 不觸發
		$life_cycle->subscription_success( 'on-hold', 'active', new \stdClass() );
		$this->assertSame( 0, $fired );
	}

	// ========== 🔀 邊緣案例 ==========

	/**
	 * @test
	 * @group edge
	 */
	public function initial_payment_complete_沒有_parent_order_應不觸發(): void {
		$life_cycle = \J7\Powerhouse\Domains\Subscription\Core\LifeCycle::instance();

		$fired = 0;
		\add_action(
			Action::INITIAL_PAYMENT_COMPLETE->get_action_hook(),
			static function () use ( &$fired ): void {
				++$fired;
			}
		);

		$sub_stub = $this->getMockBuilder( '\WC_Subscription' )
			->disableOriginalConstructor()
			->onlyMethods( [ 'get_related_orders', 'get_parent' ] )
			->getMock();
		$sub_stub->method( 'get_related_orders' )->willReturn( [ 1, 2 ] );
		$sub_stub->method( 'get_parent' )->willReturn( false );

		$life_cycle->initial_payment_complete( $sub_stub );

		$this->assertSame( 0, $fired, '沒有 parent order 時不應觸發' );
	}

	/**
	 * @test
	 * @group edge
	 */
	public function initial_payment_complete_多於一筆關聯訂單時不觸發(): void {
		$life_cycle = \J7\Powerhouse\Domains\Subscription\Core\LifeCycle::instance();

		$fired = 0;
		\add_action(
			Action::INITIAL_PAYMENT_COMPLETE->get_action_hook(),
			static function () use ( &$fired ): void {
				++$fired;
			}
		);

		$parent_order = \wc_create_order();

		$sub_stub = $this->getMockBuilder( '\WC_Subscription' )
			->disableOriginalConstructor()
			->onlyMethods( [ 'get_related_orders', 'get_parent' ] )
			->getMock();
		$sub_stub->method( 'get_related_orders' )->willReturn( [ 1, 2 ] );
		$sub_stub->method( 'get_parent' )->willReturn( $parent_order );

		$life_cycle->initial_payment_complete( $sub_stub );

		$this->assertSame( 0, $fired, '多於一筆關聯訂單時不應觸發' );
	}
}
