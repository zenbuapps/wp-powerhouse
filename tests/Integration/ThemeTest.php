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
}
