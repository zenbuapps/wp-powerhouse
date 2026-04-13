<?php
/**
 * 冒煙測試：外掛啟動與核心類別載入
 * 驗證 Powerhouse Plugin 本體能在 WordPress 測試環境中正常啟動
 */

declare( strict_types=1 );

namespace Tests\Integration;

/**
 * Class PluginBootstrapTest
 *
 * @group smoke
 */
class PluginBootstrapTest extends TestCase {

	// ========== 🔥 冒煙測試（Smoke）==========

	/**
	 * @test
	 * @group smoke
	 */
	public function 外掛核心類別應已載入(): void {
		$this->assertTrue(
			\class_exists( 'J7\Powerhouse\Plugin' ),
			'J7\Powerhouse\Plugin 類別應已被載入'
		);
	}

	/**
	 * @test
	 * @group smoke
	 */
	public function Bootstrap_類別應已載入(): void {
		$this->assertTrue(
			\class_exists( 'J7\Powerhouse\Bootstrap' ),
			'J7\Powerhouse\Bootstrap 類別應已被載入'
		);
	}

	/**
	 * @test
	 * @group smoke
	 */
	public function Settings_Model_應已載入(): void {
		$this->assertTrue(
			\class_exists( 'J7\Powerhouse\Settings\Model\Settings' ),
			'Settings Model 類別應已被載入'
		);
	}

	/**
	 * @test
	 * @group smoke
	 */
	public function Domains_Loader_應已載入(): void {
		$this->assertTrue(
			\class_exists( 'J7\Powerhouse\Domains\Loader' ),
			'Domains Loader 類別應已被載入'
		);
	}

	/**
	 * @test
	 * @group smoke
	 */
	public function REST_Server_應已啟動(): void {
		$server = \rest_get_server();
		$this->assertNotNull( $server, 'WP REST Server 應已啟動' );
	}

	/**
	 * @test
	 * @group smoke
	 */
	public function 核心_REST_命名空間應已註冊(): void {
		$routes    = \rest_get_server()->get_routes();
		$namespace = 'v2/powerhouse';
		$found     = false;
		foreach ( array_keys( $routes ) as $route ) {
			if ( str_starts_with( $route, "/{$namespace}" ) ) {
				$found = true;
				break;
			}
		}
		$this->assertTrue( $found, "REST 命名空間 '{$namespace}' 下應有已註冊的路由" );
	}

	// ========== ✅ 快樂路徑（Happy Flow）==========

	/**
	 * @test
	 * @group happy
	 */
	public function WpUtils_SingletonTrait_應正常運作(): void {
		// Plugin 使用 SingletonTrait，instance() 應回傳同一個物件
		$instance1 = \J7\Powerhouse\Plugin::instance();
		$instance2 = \J7\Powerhouse\Plugin::instance();
		$this->assertSame( $instance1, $instance2, 'SingletonTrait 應確保每次呼叫 instance() 回傳同一個物件' );
	}

	/**
	 * @test
	 * @group happy
	 */
	public function Plugin_版本號應為非空字串(): void {
		$version = \J7\Powerhouse\Plugin::$version;
		$this->assertNotEmpty( $version, 'Plugin 版本號不應為空' );
		$this->assertIsString( $version );
	}

	/**
	 * @test
	 * @group happy
	 */
	public function Plugin_Kebab_名稱應為_powerhouse(): void {
		$this->assertSame( 'powerhouse', \J7\Powerhouse\Plugin::$kebab );
	}

	/**
	 * @test
	 * @group happy
	 */
	public function Plugin_Snake_名稱應為_powerhouse(): void {
		$this->assertSame( 'powerhouse', \J7\Powerhouse\Plugin::$snake );
	}

	/**
	 * @test
	 * @group happy
	 */
	public function LC_Utils_Base_類別應已載入(): void {
		$this->assertTrue(
			\class_exists( 'J7\Powerhouse\Domains\LC\Utils\Base' ),
			'LC Utils Base 類別應已被載入'
		);
	}

	// ========== ❌ 錯誤處理（Error Handling）==========

	/**
	 * @test
	 * @group error
	 */
	public function 不存在的_REST_路由應回傳_404(): void {
		$response = $this->rest_request_as_admin( 'GET', '/v2/powerhouse/non-existent-endpoint-xyz' );
		$this->assertSame( 404, $response->get_status(), '不存在的 REST 路由應回傳 404' );
	}
}
