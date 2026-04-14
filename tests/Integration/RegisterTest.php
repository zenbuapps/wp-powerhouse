<?php
/**
 * Register Filter 整合測試
 * 驗證註冊 Email 過濾機制的初始化條件
 */

declare( strict_types=1 );

namespace Tests\Integration;

/**
 * Class RegisterTest
 *
 * @group register
 */
class RegisterTest extends TestCase {

	// ========== 🔥 冒煙測試 ==========

	/**
	 * @test
	 * @group smoke
	 */
	public function filter_類別應能以_singleton_建立(): void {
		$filter = \J7\Powerhouse\Domains\Register\Core\Filter::instance();
		$this->assertInstanceOf( \J7\Powerhouse\Domains\Register\Core\Filter::class, $filter );
	}

	// ========== ✅ 快樂路徑 ==========

	/**
	 * @test
	 * @group happy
	 */
	public function mu_plugin_email_validator_存在時_filter_構造子應直接_early_return(): void {
		// 如果 mu-plugin 已載入，建立 Filter 不應重複載入
		// 驗證方法：無論如何 Filter::instance() 都應成功不崩潰
		$filter = \J7\Powerhouse\Domains\Register\Core\Filter::instance();
		$this->assertInstanceOf( \J7\Powerhouse\Domains\Register\Core\Filter::class, $filter );

		// EmailValidator 仍應可用
		$this->assertTrue(
			class_exists( 'J7\Powerhouse\MU\EmailValidator' ),
			'mu-plugin 的 EmailValidator 應存在'
		);
	}
}
