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
	 * 讀取當前 Theme singleton 的原始值（不觸發 instance() 的組裝邏輯）。
	 *
	 * @return \J7\Powerhouse\Theme\Model\Theme|null
	 */
	private function peek_theme_singleton(): ?\J7\Powerhouse\Theme\Model\Theme {
		$ref = new \ReflectionProperty( \J7\Powerhouse\Theme\Model\Theme::class, 'instance' );
		$ref->setAccessible( true );
		/** @var \J7\Powerhouse\Theme\Model\Theme|null $value */
		$value = $ref->getValue();
		return $value;
	}

	/** 每個測試後歸零 Theme singleton，避免跨測試污染 */
	public function tear_down(): void {
		$this->reset_theme_singleton();
		parent::tear_down();
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
	public function blocksy_模式_theme_應正規化為_power_供_selector_使用(): void {
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
	public function blocksy_模式_print_css_的_selector_應為_power_而非_blocksy(): void {
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

	// ========== 🐞 回歸：Theme singleton 過早催生（issue #257）==========
	//
	// 症狀：站台設為「跟隨 Blocksy」，前台仍輸出 Powerhouse 預設色。
	// 根因：Settings::instance() 於 plugins_loaded（priority -10）就被 Bootstrap 的
	//       Admin\Account / Admin\DelayEmail / Captcha\Core\Login constructor 催生，
	//       該階段早於主題 functions.php 載入、blocksy_manager() 未定義 → overrides 空；
	//       空結果被寫進 Theme singleton 後鎖死整個請求，wp_head 階段再也算不回正確顏色。

	/**
	 * 根治點：Settings 的 constructor 不得觸發 Theme::instance()。
	 *
	 * @test
	 * @group error
	 */
	public function settings_建立時不應提前催生_theme_singleton(): void {
		$this->reset_theme_singleton();
		$this->set_powerhouse_settings(
			[
				'enable_theme' => 'yes',
				'theme'        => 'blocksy',
			]
		);

		// 模擬 Bootstrap 於 plugins_loaded 階段催生 Settings
		\J7\Powerhouse\Settings\Model\Settings::instance();

		$this->assertNull(
			$this->peek_theme_singleton(),
			'Settings constructor 不得呼叫 Theme::instance()，否則 Blocksy 尚未載入時會鎖死空覆寫（issue #257）'
		);
	}

	/**
	 * 縱深防禦：blocksy 模式取不到調色盤時，不得把「全預設色」快取進 singleton。
	 *
	 * @test
	 * @group error
	 */
	public function blocksy_取不到調色盤時不應快取空覆寫的_theme_singleton(): void {
		$this->reset_theme_singleton();
		$this->set_powerhouse_settings(
			[
				'enable_theme' => 'yes',
				'theme'        => 'blocksy',
			]
		);

		// 測試環境無 blocksy_manager → overrides 為空
		$theme = \J7\Powerhouse\Theme\Model\Theme::instance();

		$this->assertInstanceOf( \J7\Powerhouse\Theme\Model\Theme::class, $theme, '仍應回傳可用實例（降級為預設色）' );
		$this->assertNull(
			$this->peek_theme_singleton(),
			'空覆寫不得留存於 singleton，否則後續較晚階段無法重新取值（issue #257）'
		);
	}

	/**
	 * 端到端：早期催生 Settings 之後，較晚階段的 Theme::instance() 仍須反映當下狀態。
	 *
	 * 以「設定在兩次呼叫之間改變」代理真實情境中的「Blocksy 從不可用變為可用」，
	 * 兩者失效機制相同——singleton 一旦於早期定型就永遠回舊值。
	 *
	 * @test
	 * @group edge
	 */
	public function 早期催生_settings_不得鎖死後續的_theme_取值(): void {
		$this->reset_theme_singleton();

		// 階段一：plugins_loaded——theme=blocksy 且 Blocksy 尚未載入（overrides 空）
		$this->set_powerhouse_settings(
			[
				'enable_theme' => 'yes',
				'theme'        => 'blocksy',
			]
		);
		\J7\Powerhouse\Settings\Model\Settings::instance();

		// 階段二：主題載入後——此時才有正確資料可取（不重置 Theme singleton，這正是測試重點）
		$this->set_powerhouse_settings(
			[
				'enable_theme' => 'yes',
				'theme'        => 'dark',
			]
		);

		$this->assertSame(
			'dark',
			\J7\Powerhouse\Theme\Model\Theme::instance()->theme,
			'Theme 於早期階段被催生後，較晚階段仍須能重新取值（issue #257）'
		);
	}

	/**
	 * 回歸護欄：theme_css 改為延遲取值後，to_array() 仍須帶出完整主題色。
	 *
	 * @test
	 * @group happy
	 */
	public function settings_to_array_延遲取值後仍應包含完整_theme_css(): void {
		$this->reset_theme_singleton();
		$this->set_powerhouse_settings(
			[
				'enable_theme' => 'yes',
				'theme'        => 'light',
			]
		);

		$arr = \J7\Powerhouse\Settings\Model\Settings::instance()->to_array();

		$this->assertArrayHasKey( 'theme_css', $arr );
		$this->assertIsArray( $arr['theme_css'] );
		$this->assertArrayHasKey( '--p', $arr['theme_css'], 'theme_css 應含 daisyUI 色彩 token' );
		$this->assertSame( 'light', $arr['theme_css']['theme'] ?? null );
	}
}
