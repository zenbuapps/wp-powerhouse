<?php
/**
 * Settings（設定系統）整合測試
 * 驗證 Powerhouse Settings Model 的讀寫與預設值行為
 */

declare( strict_types=1 );

namespace Tests\Integration;

use J7\Powerhouse\Settings\Model\Settings;

/**
 * Class SettingsTest
 *
 * @group settings
 */
class SettingsTest extends TestCase {

	public function set_up(): void {
		parent::set_up();
		// 每個測試前清除 settings option，確保乾淨環境
		\delete_option( Settings::SETTINGS_KEY );
		$this->reset_settings_singleton();
	}

	// ========== ✅ 快樂路徑（Happy Flow）==========

	/**
	 * @test
	 * @group happy
	 */
	public function Settings_SETTINGS_KEY_應為_powerhouse_settings(): void {
		$this->assertSame( 'powerhouse_settings', Settings::SETTINGS_KEY );
	}

	/**
	 * @test
	 * @group happy
	 */
	public function 未設定時_Settings_應回傳預設值(): void {
		$settings = Settings::instance();

		$this->assertSame( 'no', $settings->enable_manual_send_email, '預設 enable_manual_send_email 應為 no' );
		$this->assertSame( 'no', $settings->enable_captcha_login, '預設 enable_captcha_login 應為 no' );
		$this->assertSame( 'no', $settings->enable_captcha_register, '預設 enable_captcha_register 應為 no' );
		$this->assertSame( 'yes', $settings->delay_email, '預設 delay_email 應為 yes' );
		$this->assertSame( 'yes', $settings->last_name_optional, '預設 last_name_optional 應為 yes' );
		$this->assertSame( 'power', $settings->theme, '預設 theme 應為 power' );
		$this->assertSame( 'yes', $settings->enable_theme, '預設 enable_theme 應為 yes' );
	}

	/**
	 * @test
	 * @group happy
	 */
	public function Settings_to_array_應回傳所有欄位(): void {
		$settings = Settings::instance();
		$arr      = $settings->to_array();

		$this->assertIsArray( $arr );
		$this->assertArrayHasKey( 'enable_manual_send_email', $arr );
		$this->assertArrayHasKey( 'delay_email', $arr );
		$this->assertArrayHasKey( 'theme', $arr );
		$this->assertArrayHasKey( 'enable_captcha_login', $arr );
	}

	/**
	 * @test
	 * @group happy
	 */
	public function partial_update_應只更新指定欄位(): void {
		$settings = Settings::instance();

		// 先確認預設值
		$this->assertSame( 'no', $settings->enable_manual_send_email );

		// 只更新 enable_manual_send_email
		$settings->partial_update( [ 'enable_manual_send_email' => 'yes' ] );

		// 重新讀取
		$this->reset_settings_singleton();
		$updated_settings = Settings::instance();

		$this->assertSame( 'yes', $updated_settings->enable_manual_send_email, '部分更新後應能讀取到新值' );
		$this->assertSame( 'yes', $updated_settings->delay_email, '未更新的欄位應保持預設值' );
	}

	/**
	 * @test
	 * @group happy
	 */
	public function 從_WP_Options_讀取的設定應覆蓋預設值(): void {
		$this->set_powerhouse_settings( [ 'delay_email' => 'no' ] );
		$settings = Settings::instance();

		$this->assertSame( 'no', $settings->delay_email, '從 WP Options 讀取的值應覆蓋預設值' );
	}

	/**
	 * @test
	 * @group happy
	 */
	public function 預設_Email_Domain_白名單應包含常用服務(): void {
		$settings   = Settings::instance();
		$white_list = $settings->email_domain_check_white_list;

		$this->assertIsArray( $white_list );
		$this->assertContains( 'gmail.com', $white_list );
		$this->assertContains( 'yahoo.com', $white_list );
		$this->assertContains( 'outlook.com', $white_list );
	}

	/**
	 * @test
	 * @group happy
	 */
	public function 預設_captcha_role_list_應包含_administrator(): void {
		$settings = Settings::instance();
		$this->assertContains( 'administrator', $settings->captcha_role_list );
	}

	// ========== ❌ 錯誤處理（Error Handling）==========

	/**
	 * @test
	 * @group error
	 */
	public function partial_update_不應接受不存在的欄位(): void {
		$settings = Settings::instance();
		$settings->partial_update( [ 'non_existent_field' => 'some_value' ] );

		// 重新讀取，確認不存在的欄位沒有被儲存
		$saved = \get_option( Settings::SETTINGS_KEY, [] );
		$this->assertArrayNotHasKey( 'non_existent_field', $saved, '不存在的欄位不應被儲存' );
	}

	/**
	 * @test
	 * @group error
	 */
	public function 儲存非陣列資料時_Settings_instance_應使用預設值(): void {
		// 故意儲存非陣列值（模擬資料損壞）
		\update_option( Settings::SETTINGS_KEY, 'corrupted_data' );
		$this->reset_settings_singleton();

		// 即使 option 內容異常，Settings::instance() 也應能正常建立
		$settings = Settings::instance();
		$this->assertInstanceOf( Settings::class, $settings );
		$this->assertSame( 'no', $settings->enable_manual_send_email, '資料損壞時應 fallback 為預設值' );
	}

	// ========== 🔀 邊緣案例（Edge Cases）==========

	/**
	 * @test
	 * @group edge
	 */
	public function partial_update_傳入空陣列時設定不應改變(): void {
		$settings = Settings::instance();
		$before   = $settings->to_array();

		$settings->partial_update( [] );

		$this->reset_settings_singleton();
		$after = Settings::instance()->to_array();

		// 除了 theme_css 可能因 Theme singleton 不同而有差異，其他欄位應相同
		unset( $before['theme_css'], $after['theme_css'] );
		$this->assertSame( $before, $after, '傳入空陣列時 partial_update 不應改變任何設定' );
	}

	/**
	 * @test
	 * @group edge
	 */
	public function partial_update_傳入超長字串值應能儲存(): void {
		$long_string = str_repeat( 'a', 1000 );
		$settings    = Settings::instance();
		$settings->partial_update( [ 'bunny_library_id' => $long_string ] );

		$this->reset_settings_singleton();
		$updated = Settings::instance();
		$this->assertSame( $long_string, $updated->bunny_library_id );
	}

	/**
	 * @test
	 * @group edge
	 */
	public function Settings_api_booster_rules_預設應為空陣列(): void {
		$settings = Settings::instance();
		$this->assertIsArray( $settings->api_booster_rules );
		$this->assertEmpty( $settings->api_booster_rules );
	}

	// ========== 🔀 邊緣案例補洞 ==========

	/**
	 * @test
	 * @group edge
	 */
	public function partial_update_陣列型欄位應能儲存(): void {
		$settings = Settings::instance();
		$settings->partial_update(
			[
				'email_domain_check_white_list' => [ 'custom.com', 'test.io' ],
			]
		);

		$this->reset_settings_singleton();
		$updated = Settings::instance();

		$this->assertContains( 'custom.com', $updated->email_domain_check_white_list );
		$this->assertContains( 'test.io', $updated->email_domain_check_white_list );
	}

	/**
	 * @test
	 * @group edge
	 */
	public function partial_update_布林型字串應保持為_yes_或_no(): void {
		$settings = Settings::instance();
		$settings->partial_update( [ 'delay_email' => 'no' ] );

		$this->reset_settings_singleton();
		$updated = Settings::instance();

		$this->assertSame( 'no', $updated->delay_email );
	}

	/**
	 * @test
	 * @group edge
	 */
	public function singleton_reset_後_應重新從_option_讀取(): void {
		$settings = Settings::instance();
		$this->assertSame( 'yes', $settings->delay_email );

		// 直接修改 option
		\update_option(
			Settings::SETTINGS_KEY,
			[ 'delay_email' => 'no' ]
		);

		// 沒 reset 不會讀到新值
		$stale = Settings::instance();
		$this->assertSame( 'yes', $stale->delay_email, 'singleton 未 reset 時應保持快取' );

		// reset 後應讀到新值
		$this->reset_settings_singleton();
		$fresh = Settings::instance();
		$this->assertSame( 'no', $fresh->delay_email );
	}

	/**
	 * @test
	 * @group edge
	 */
	public function partial_update_多個欄位應同時生效(): void {
		$settings = Settings::instance();
		$settings->partial_update(
			[
				'enable_captcha_login'    => 'yes',
				'enable_captcha_register' => 'yes',
				'delay_email'             => 'no',
			]
		);

		$this->reset_settings_singleton();
		$updated = Settings::instance();

		$this->assertSame( 'yes', $updated->enable_captcha_login );
		$this->assertSame( 'yes', $updated->enable_captcha_register );
		$this->assertSame( 'no', $updated->delay_email );
	}

	/**
	 * @test
	 * @group edge
	 */
	public function Settings_enable_theme_changer_預設應為_no(): void {
		$settings = Settings::instance();
		$this->assertSame( 'no', $settings->enable_theme_changer );
	}
}
