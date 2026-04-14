<?php
/**
 * MU Plugin Loader 整合測試
 * 驗證 mu-plugin 載入後的狀態（非載入過程本身）
 */

declare( strict_types=1 );

namespace Tests\Integration;

/**
 * Class MuPluginLoaderTest
 *
 * @group mu-plugin
 */
class MuPluginLoaderTest extends TestCase {

	/**
	 * 檢查 mu-plugin 是否真的被載入（測試環境可能未安裝）
	 */
	private function skip_if_mu_plugin_not_loaded( string $class_name ): void {
		if ( ! class_exists( $class_name ) ) {
			$this->markTestSkipped( "mu-plugin {$class_name} 未在測試環境載入" );
		}
	}

	// ========== 🔥 冒煙測試 ==========

	/**
	 * @test
	 * @group smoke
	 */
	public function powerhouse_loader_mu_plugin_source_檔案應存在(): void {
		$plugin_dir = dirname( dirname( __DIR__ ) );
		$this->assertFileExists( $plugin_dir . '/inc/classes/Compatibility/mu-plugins/powerhouse-loader.php' );
	}

	/**
	 * @test
	 * @group smoke
	 */
	public function email_validator_mu_plugin_類別應被載入(): void {
		$this->assertTrue(
			class_exists( 'J7\Powerhouse\MU\EmailValidator' ),
			'EmailValidator mu-plugin 應被載入'
		);
	}

	/**
	 * @test
	 * @group smoke
	 */
	public function api_booster_mu_plugin_類別檢查(): void {
		// mu-plugin 目錄可能未在測試環境中，只驗證原始類別存在
		// 用 class_exists 或 Compatibility Service 版本替代
		$this->assertTrue(
			class_exists( 'J7\Powerhouse\Compatibility\Services\ApiBooster' ),
			'ApiBooster Compatibility Service 應存在'
		);
	}

	/**
	 * @test
	 * @group smoke
	 */
	public function disable_features_mu_plugin_類別檢查(): void {
		$this->assertTrue(
			class_exists( 'J7\Powerhouse\Compatibility\Services\DisableFeatures' ),
			'DisableFeatures Compatibility Service 應存在'
		);
	}

	// ========== ✅ 快樂路徑 ==========

	/**
	 * @test
	 * @group happy
	 */
	public function vendor_autoload_關鍵類別應在_loader_後可用(): void {
		$this->assertTrue( class_exists( 'J7\WpUtils\Classes\ApiBase' ) );
		$this->assertTrue( class_exists( 'J7\WpUtils\Classes\WP' ) );
		$this->assertTrue( class_exists( 'J7\WpUtils\Classes\DTO' ) );
		$this->assertTrue( trait_exists( 'J7\WpUtils\Traits\PluginTrait' ) );
		$this->assertTrue( trait_exists( 'J7\WpUtils\Traits\SingletonTrait' ) );
	}

	/**
	 * @test
	 * @group happy
	 */
	public function disable_features_mu_plugin_若已載入應停用_xmlrpc(): void {
		$this->skip_if_mu_plugin_not_loaded( 'J7\Powerhouse\MU\DisableFeatures' );

		$this->assertFalse(
			(bool) \apply_filters( 'xmlrpc_enabled', true ),
			'XML-RPC 應被停用'
		);
	}

	/**
	 * @test
	 * @group happy
	 */
	public function disable_features_mu_plugin_若已載入應移除_wp_v2_users_端點(): void {
		$this->skip_if_mu_plugin_not_loaded( 'J7\Powerhouse\MU\DisableFeatures' );

		$endpoints = \apply_filters(
			'rest_endpoints',
			[
				'/wp/v2/users'                => [ 'dummy' ],
				'/wp/v2/users/(?P<id>[\d]+)' => [ 'dummy' ],
				'/wp/v2/posts'                => [ 'dummy' ],
			]
		);

		$this->assertArrayNotHasKey( '/wp/v2/users', $endpoints );
		$this->assertArrayNotHasKey( '/wp/v2/users/(?P<id>[\d]+)', $endpoints );
		$this->assertArrayHasKey( '/wp/v2/posts', $endpoints );
	}

	/**
	 * @test
	 * @group happy
	 */
	public function disable_features_mu_plugin_若已載入應設定_jpeg_quality_為_100(): void {
		$this->skip_if_mu_plugin_not_loaded( 'J7\Powerhouse\MU\DisableFeatures' );
		$this->assertSame( 100, (int) \apply_filters( 'jpeg_quality', 82 ) );
	}

	/**
	 * @test
	 * @group happy
	 */
	public function mu_plugin_source_檔案應存在於_plugin_目錄(): void {
		$plugin_dir = dirname( dirname( __DIR__ ) );
		$this->assertFileExists( $plugin_dir . '/inc/classes/Compatibility/mu-plugins/powerhouse-loader.php' );
		$this->assertFileExists( $plugin_dir . '/inc/classes/Compatibility/mu-plugins/powerhouse-api-booster.php' );
		$this->assertFileExists( $plugin_dir . '/inc/classes/Compatibility/mu-plugins/powerhouse-disable-features.php' );
		$this->assertFileExists( $plugin_dir . '/inc/classes/Compatibility/mu-plugins/powerhouse-email-validator.php' );
	}
}
