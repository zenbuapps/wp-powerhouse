<?php
/**
 * Theme 整合測試
 * 驗證主題色彩系統與 CSS 注入行為
 */

declare( strict_types=1 );

namespace Tests\Integration;

/**
 * Class ThemeTest
 *
 * @group theme
 */
class ThemeTest extends TestCase {

	// ========== 🔥 冒煙測試 ==========

	/**
	 * @test
	 * @group smoke
	 */
	public function frontend_類別應能以_singleton_建立(): void {
		$front = \J7\Powerhouse\Theme\Core\FrontEnd::instance();
		$this->assertInstanceOf( \J7\Powerhouse\Theme\Core\FrontEnd::class, $front );
	}

	/**
	 * @test
	 * @group smoke
	 */
	public function frontend_應註冊_language_attributes_filter(): void {
		\J7\Powerhouse\Theme\Core\FrontEnd::instance();
		$this->assertGreaterThan(
			0,
			\has_filter( 'language_attributes' ),
			'language_attributes filter 應被註冊'
		);
	}

	// ========== ✅ 快樂路徑 ==========

	/**
	 * @test
	 * @group happy
	 */
	public function enable_theme_關閉時_add_html_attr_應加上_tailwind_class_但不加_data_theme(): void {
		$this->set_powerhouse_settings( [ 'enable_theme' => 'no' ] );

		$front  = \J7\Powerhouse\Theme\Core\FrontEnd::instance();
		$output = $front->add_html_attr( 'lang="zh-TW"', 'html' );

		$this->assertStringContainsString( 'id="tw"', $output );
		$this->assertStringContainsString( 'tailwind', $output );
		$this->assertStringNotContainsString( 'data-theme', $output );
	}

	/**
	 * @test
	 * @group happy
	 */
	public function enable_theme_啟用時_add_html_attr_應加上_data_theme(): void {
		$this->set_powerhouse_settings(
			[
				'enable_theme' => 'yes',
				'theme'        => 'light',
			]
		);

		$front  = \J7\Powerhouse\Theme\Core\FrontEnd::instance();
		$output = $front->add_html_attr( 'lang="zh-TW"', 'html' );

		$this->assertStringContainsString( 'data-theme="light"', $output );
	}

	// ========== 🔀 邊緣案例 ==========

	/**
	 * @test
	 * @group edge
	 */
	public function 已有_data_theme_的_html_attr_不應被覆寫(): void {
		$this->set_powerhouse_settings(
			[
				'enable_theme' => 'yes',
				'theme'        => 'dark',
			]
		);

		$front    = \J7\Powerhouse\Theme\Core\FrontEnd::instance();
		$original = 'lang="zh-TW" data-theme="existing"';
		$output   = $front->add_html_attr( $original, 'html' );

		$this->assertSame( $original, $output );
	}

	/**
	 * @test
	 * @group happy
	 */
	public function custom_theme_color_停用時應不輸出(): void {
		$this->set_powerhouse_settings( [ 'enable_theme' => 'no' ] );

		$front = \J7\Powerhouse\Theme\Core\FrontEnd::instance();
		ob_start();
		$front->custom_theme_color();
		$output = (string) ob_get_clean();

		$this->assertSame( '', $output, '未啟用時應不輸出任何內容' );
	}

	// ========== 🎨 跟隨 Blocksy 模式 ==========

	/**
	 * 重置 Theme singleton，使下次 instance() 依當前 settings 重新組裝。
	 */
	private function reset_theme_singleton(): void {
		$ref = new \ReflectionProperty( \J7\Powerhouse\Theme\Model\Theme::class, 'instance' );
		$ref->setAccessible( true );
		$ref->setValue( null, null );
	}

	/**
	 * @test
	 * @group happy
	 */
	public function blocksy_服務應以_get_template_判斷當前是否為_blocksy(): void {
		\add_filter( 'template', fn() => 'blocksy' );
		$this->assertTrue( \J7\Powerhouse\Theme\Core\Blocksy::instance()->is_blocksy() );
	}

	/**
	 * @test
	 * @group happy
	 */
	public function 非_blocksy_主題_is_blocksy_應為_false(): void {
		\add_filter( 'template', fn() => 'twentytwentyfour' );
		$this->assertFalse( \J7\Powerhouse\Theme\Core\Blocksy::instance()->is_blocksy() );
	}

	/**
	 * @test
	 * @group error
	 */
	public function blocksy_未啟用時_get_palette_與_overrides_應安全回空陣列不_fatal(): void {
		// 測試環境無 blocksy_manager 函式 → 多重守衛應回空陣列
		$blocksy = \J7\Powerhouse\Theme\Core\Blocksy::instance();
		$this->assertSame( [], $blocksy->get_palette() );
		$this->assertSame( [], $blocksy->get_oklch_overrides() );
	}

	/**
	 * @test
	 * @group happy
	 */
	public function blocksy_模式_theme_應正規化為_power_供_selector 使用(): void {
		$this->reset_theme_singleton();
		$this->set_powerhouse_settings(
			[
				'enable_theme' => 'yes',
				'theme'        => 'blocksy',
			]
		);

		$theme = \J7\Powerhouse\Theme\Model\Theme::instance();
		$this->assertSame( 'power', $theme->theme, 'blocksy 模式應正規化為 power（D1）' );
	}

	/**
	 * @test
	 * @group happy
	 */
	public function blocksy_模式_print_css_的_selector_應為_power 而非_blocksy(): void {
		$this->reset_theme_singleton();
		$this->set_powerhouse_settings(
			[
				'enable_theme' => 'yes',
				'theme'        => 'blocksy',
			]
		);

		\ob_start();
		\J7\Powerhouse\Theme\Model\Theme::instance()->print_css();
		$output = (string) \ob_get_clean();

		$this->assertStringContainsString( "data-theme='power'", $output );
		$this->assertStringNotContainsString( "data-theme='blocksy'", $output );
	}

	/**
	 * @test
	 * @group happy
	 */
	public function blocksy_模式_add_html_attr_應輸出正規化後的_data_theme_power(): void {
		$this->reset_theme_singleton();
		$this->set_powerhouse_settings(
			[
				'enable_theme' => 'yes',
				'theme'        => 'blocksy',
			]
		);

		$front  = \J7\Powerhouse\Theme\Core\FrontEnd::instance();
		$output = $front->add_html_attr( 'lang="zh-TW"', 'html' );

		$this->assertStringContainsString( 'data-theme="power"', $output );
	}

	/**
	 * @test
	 * @group edge
	 */
	public function blocksy_未啟用時_模式仍不_fatal_且色彩退回_powerhouse_預設(): void {
		$this->reset_theme_singleton();
		$this->set_powerhouse_settings(
			[
				'enable_theme' => 'yes',
				'theme'        => 'blocksy',
			]
		);

		$theme = \J7\Powerhouse\Theme\Model\Theme::instance();
		// 無 blocksy_manager → overrides 空 → primary 維持 Powerhouse 預設值
		$this->assertSame( '59.865739207996604% 0.21935054351796926 259.03952196623266', $theme->p );
	}
}
