<?php
/**
 * Admin 整合測試
 * 驗證 Admin 相關 singleton 的初始化與 hook 註冊
 */

declare( strict_types=1 );

namespace Tests\Integration;

/**
 * Class AdminIntegrationTest
 *
 * @group admin
 */
class AdminIntegrationTest extends TestCase {

	// ========== 🔥 冒煙測試 ==========

	/**
	 * @test
	 * @group smoke
	 */
	public function entry_類別應能以_singleton_建立(): void {
		$entry = \J7\Powerhouse\Admin\Entry::instance();
		$this->assertInstanceOf( \J7\Powerhouse\Admin\Entry::class, $entry );
	}

	/**
	 * @test
	 * @group smoke
	 */
	public function debug_類別應能以_singleton_建立(): void {
		$debug = \J7\Powerhouse\Admin\Debug::instance();
		$this->assertInstanceOf( \J7\Powerhouse\Admin\Debug::class, $debug );
	}

	/**
	 * @test
	 * @group smoke
	 */
	public function delay_email_類別應能以_singleton_建立(): void {
		$delay = \J7\Powerhouse\Admin\DelayEmail::instance();
		$this->assertInstanceOf( \J7\Powerhouse\Admin\DelayEmail::class, $delay );
	}

	/**
	 * @test
	 * @group smoke
	 */
	public function account_類別應能以_singleton_建立(): void {
		$account = \J7\Powerhouse\Admin\Account::instance();
		$this->assertInstanceOf( \J7\Powerhouse\Admin\Account::class, $account );
	}

	// ========== ✅ 快樂路徑 ==========

	/**
	 * @test
	 * @group happy
	 */
	public function entry_應註冊_current_screen_hook(): void {
		\J7\Powerhouse\Admin\Entry::instance();
		$this->assertGreaterThan(
			0,
			\has_action( 'current_screen' ),
			'Entry 應在 current_screen 註冊 hook'
		);
	}

	/**
	 * @test
	 * @group happy
	 */
	public function singleton_instance_應該在多次呼叫時回傳同一物件(): void {
		$a = \J7\Powerhouse\Admin\Entry::instance();
		$b = \J7\Powerhouse\Admin\Entry::instance();
		$this->assertSame( $a, $b );
	}

	/**
	 * @test
	 * @group happy
	 */
	public function order_list_與_order_detail_類別應存在_若_wc_已載入(): void {
		if ( ! class_exists( '\WooCommerce' ) ) {
			$this->markTestSkipped( 'WooCommerce 未啟用' );
			return;
		}
		$this->assertTrue( class_exists( '\J7\Powerhouse\Admin\OrderList' ) );
		$this->assertTrue( class_exists( '\J7\Powerhouse\Admin\OrderDetail' ) );
	}
}
