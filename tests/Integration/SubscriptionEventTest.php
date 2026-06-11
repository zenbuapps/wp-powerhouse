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

	/**
	 * @test
	 * @group smoke
	 */
	public function lifecycle_應綁定_status_updated_而非_pre_update_status(): void {
		$life_cycle = \J7\Powerhouse\Domains\Subscription\Core\LifeCycle::instance();

		$this->assertNotFalse(
			\has_action( 'woocommerce_subscription_status_updated', [ $life_cycle, 'subscription_failed' ] ),
			'subscription_failed 應綁定 woocommerce_subscription_status_updated'
		);
		$this->assertNotFalse(
			\has_action( 'woocommerce_subscription_status_updated', [ $life_cycle, 'subscription_success' ] ),
			'subscription_success 應綁定 woocommerce_subscription_status_updated'
		);
		// pre_update_status 在 can_be_updated_to 驗證前觸發，會對被 WCS 拒絕的轉換誤發事件
		$this->assertFalse(
			\has_action( 'woocommerce_subscription_pre_update_status', [ $life_cycle, 'subscription_failed' ] ),
			'subscription_failed 不應綁定 woocommerce_subscription_pre_update_status'
		);
		$this->assertFalse(
			\has_action( 'woocommerce_subscription_pre_update_status', [ $life_cycle, 'subscription_success' ] ),
			'subscription_success 不應綁定 woocommerce_subscription_pre_update_status'
		);
	}

	/**
	 * @test
	 * @group smoke
	 */
	public function status_is_recoverable_應只認_pending_cancel_cancelled_expired(): void {
		$this->assertTrue( Status::PENDING_CANCEL->is_recoverable() );
		$this->assertTrue( Status::CANCELLED->is_recoverable() );
		$this->assertTrue( Status::EXPIRED->is_recoverable() );
		$this->assertFalse( Status::ACTIVE->is_recoverable() );
		$this->assertFalse( Status::ON_HOLD->is_recoverable() );
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

		$fired_payload = null;
		\add_action(
			Action::SUBSCRIPTION_FAILED->get_action_hook(),
			static function ( $subscription, $args ) use ( &$fired_payload ): void {
				$fired_payload = [ $subscription, $args ];
			},
			10,
			2
		);

		$sub_stub = $this->getMockBuilder( '\WC_Subscription' )
			->disableOriginalConstructor()
			->getMock();

		// active → cancelled（成功 → 失敗）→ 觸發
		$life_cycle->subscription_failed( $sub_stub, 'cancelled', 'active' );
		$this->assertNotNull( $fired_payload, 'active → cancelled 應觸發' );
		$this->assertSame( Status::ACTIVE, $fired_payload[1]['from_status'] );
		$this->assertSame( Status::CANCELLED, $fired_payload[1]['to_status'] );

		// active → on-hold（保留不算失敗）→ 不觸發
		$fired_payload = null;
		$life_cycle->subscription_failed( $sub_stub, 'on-hold', 'active' );
		$this->assertNull( $fired_payload, 'active → on-hold 不應觸發' );

		// cancelled → expired（本來就是失敗狀態）→ 不觸發
		$life_cycle->subscription_failed( $sub_stub, 'expired', 'cancelled' );
		$this->assertNull( $fired_payload, 'cancelled → expired 不應觸發' );

		// non-subscription → 不觸發
		$life_cycle->subscription_failed( new \stdClass(), 'cancelled', 'active' );
		$this->assertNull( $fired_payload, '非 WC_Subscription 不應觸發' );

		// invalid status → 不觸發
		$life_cycle->subscription_failed( $sub_stub, 'also-nonsense', 'nonsense' );
		$this->assertNull( $fired_payload, '無效狀態不應觸發' );
	}

	/**
	 * @test
	 * @group happy
	 */
	public function subscription_success_method_在可恢復狀態轉為_active_時觸發_action(): void {
		$life_cycle = \J7\Powerhouse\Domains\Subscription\Core\LifeCycle::instance();

		$fired_payload = null;
		\add_action(
			Action::SUBSCRIPTION_SUCCESS->get_action_hook(),
			static function ( $subscription, $args ) use ( &$fired_payload ): void {
				$fired_payload = [ $subscription, $args ];
			},
			10,
			2
		);

		$sub_stub = $this->getMockBuilder( '\WC_Subscription' )
			->disableOriginalConstructor()
			->getMock();

		// pending-cancel → active（cancelled 復活路徑的最後一跳）→ 觸發
		$life_cycle->subscription_success( $sub_stub, 'active', 'pending-cancel' );
		$this->assertNotNull( $fired_payload, 'pending-cancel → active 應觸發' );
		$this->assertSame( Status::PENDING_CANCEL, $fired_payload[1]['from_status'] );
		$this->assertSame( Status::ACTIVE, $fired_payload[1]['to_status'] );

		// cancelled → active（stock WCS 禁止直達，但可被 filter 解鎖或經 set_status 直寫）→ 觸發
		$fired_payload = null;
		$life_cycle->subscription_success( $sub_stub, 'active', 'cancelled' );
		$this->assertNotNull( $fired_payload, 'cancelled → active 應觸發' );
		$this->assertSame( Status::CANCELLED, $fired_payload[1]['from_status'] );

		// expired → active → 觸發
		$fired_payload = null;
		$life_cycle->subscription_success( $sub_stub, 'active', 'expired' );
		$this->assertNotNull( $fired_payload, 'expired → active 應觸發' );
		$this->assertSame( Status::EXPIRED, $fired_payload[1]['from_status'] );
	}

	/**
	 * @test
	 * @group happy
	 */
	public function subscription_success_method_不在催繳補款或非恢復轉換時觸發(): void {
		$life_cycle = \J7\Powerhouse\Domains\Subscription\Core\LifeCycle::instance();

		$fired = 0;
		\add_action(
			Action::SUBSCRIPTION_SUCCESS->get_action_hook(),
			static function () use ( &$fired ): void {
				++$fired;
			}
		);

		$sub_stub = $this->getMockBuilder( '\WC_Subscription' )
			->disableOriginalConstructor()
			->getMock();

		// on-hold → active（催繳補款的日常震盪，非恢復）→ 不觸發
		$life_cycle->subscription_success( $sub_stub, 'active', 'on-hold' );
		$this->assertSame( 0, $fired, 'on-hold → active 不應觸發' );

		// pending → active（初次啟用，歸 INITIAL_PAYMENT_COMPLETE 管）→ 不觸發
		$life_cycle->subscription_success( $sub_stub, 'active', 'pending' );
		$this->assertSame( 0, $fired, 'pending → active 不應觸發' );

		// cancelled → pending-cancel（復活第一跳，尚未 active）→ 不觸發
		$life_cycle->subscription_success( $sub_stub, 'pending-cancel', 'cancelled' );
		$this->assertSame( 0, $fired, 'cancelled → pending-cancel 不應觸發' );

		// non-subscription → 不觸發
		$life_cycle->subscription_success( new \stdClass(), 'active', 'cancelled' );
		$this->assertSame( 0, $fired, '非 WC_Subscription 不應觸發' );
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
