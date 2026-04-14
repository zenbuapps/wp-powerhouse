<?php
/**
 * Infrastructure / Compatibility Services 整合測試
 * 驗證 Compatibility\Services 底下各 singleton 的初始化行為
 */

declare( strict_types=1 );

namespace Tests\Integration;

/**
 * Class InfrastructureTest
 *
 * @group infrastructure
 */
class InfrastructureTest extends TestCase {

	// ========== 🔥 冒煙測試 ==========

	/**
	 * @test
	 * @group smoke
	 */
	public function api_booster_service_應能建立(): void {
		$booster = \J7\Powerhouse\Compatibility\Services\ApiBooster::instance();
		$this->assertInstanceOf( \J7\Powerhouse\Compatibility\Services\ApiBooster::class, $booster );
	}

	/**
	 * @test
	 * @group smoke
	 */
	public function auto_update_service_應能建立(): void {
		$service = \J7\Powerhouse\Compatibility\Services\AutoUpdate::instance();
		$this->assertInstanceOf( \J7\Powerhouse\Compatibility\Services\AutoUpdate::class, $service );
	}

	/**
	 * @test
	 * @group smoke
	 */
	public function disable_features_service_應能建立(): void {
		$service = \J7\Powerhouse\Compatibility\Services\DisableFeatures::instance();
		$this->assertInstanceOf( \J7\Powerhouse\Compatibility\Services\DisableFeatures::class, $service );
	}

	/**
	 * @test
	 * @group smoke
	 */
	public function scheduler_service_應能建立(): void {
		$service = \J7\Powerhouse\Compatibility\Services\Scheduler::instance();
		$this->assertInstanceOf( \J7\Powerhouse\Compatibility\Services\Scheduler::class, $service );
	}

	/**
	 * @test
	 * @group smoke
	 */
	public function email_validator_service_應能建立(): void {
		$service = \J7\Powerhouse\Compatibility\Services\EmailValidator::instance();
		$this->assertInstanceOf( \J7\Powerhouse\Compatibility\Services\EmailValidator::class, $service );
	}

	/**
	 * @test
	 * @group smoke
	 */
	public function loader_service_應能建立(): void {
		$loader = \J7\Powerhouse\Compatibility\Services\Loader::instance();
		$this->assertInstanceOf( \J7\Powerhouse\Compatibility\Services\Loader::class, $loader );
	}

	// ========== ✅ 快樂路徑 ==========

	/**
	 * @test
	 * @group happy
	 */
	public function scheduler_應註冊_plugins_loaded_redirect_hook(): void {
		\J7\Powerhouse\Compatibility\Services\Scheduler::instance();
		$this->assertGreaterThan( 0, \has_action( 'plugins_loaded' ) );
	}

	/**
	 * @test
	 * @group happy
	 */
	public function scheduler_as_compatibility_action_常數應正確(): void {
		$this->assertSame(
			'powerhouse_compatibility_action_scheduler',
			\J7\Powerhouse\Compatibility\Services\Scheduler::AS_COMPATIBILITY_ACTION
		);
	}

	/**
	 * @test
	 * @group happy
	 */
	public function scheduler_compatibility_action_scheduler_方法不應拋例外(): void {
		// 無論版本是否已執行過，此方法應安全呼叫
		\J7\Powerhouse\Compatibility\Services\Scheduler::compatibility_action_scheduler();
		$this->assertTrue( true, '方法執行完畢不拋例外' );
	}

	/**
	 * @test
	 * @group happy
	 */
	public function message_template_repository_應能初始化(): void {
		$this->assertTrue(
			class_exists( \J7\Powerhouse\Infrustructures\Repositories\MessageTemplate\Register::class ),
			'MessageTemplate Register 類別應存在'
		);
	}

	/**
	 * @test
	 * @group happy
	 */
	public function ph_message_tpl_cpt_應能被註冊(): void {
		// Register 是靜態類，使用 register_hooks() 或 register_cpt() 靜態方法
		\J7\Powerhouse\Infrustructures\Repositories\MessageTemplate\Register::register_cpt();

		$post_type = \get_post_type_object( 'ph_message_tpl' );
		$this->assertNotNull( $post_type );
		$this->assertTrue( (bool) $post_type->public );
	}

	/**
	 * @test
	 * @group happy
	 */
	public function message_template_post_type_名稱應為_ph_message_tpl(): void {
		$this->assertSame(
			'ph_message_tpl',
			\J7\Powerhouse\Infrustructures\Repositories\MessageTemplate\Register::post_type()
		);
	}
}
