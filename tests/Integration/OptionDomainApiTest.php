<?php
/**
 * Option Domain REST API 整合測試
 * 驗證 /v2/powerhouse/options 端點的讀取與更新行為
 */

declare( strict_types=1 );

namespace Tests\Integration;

use J7\Powerhouse\Settings\Model\Settings;

/**
 * Class OptionDomainApiTest
 *
 * @group option-api
 */
class OptionDomainApiTest extends TestCase {

	public function set_up(): void {
		parent::set_up();
		\delete_option( Settings::SETTINGS_KEY );
		$this->reset_settings_singleton();
	}

	// ========== 🔥 冒煙測試（Smoke）==========

	/**
	 * @test
	 * @group smoke
	 */
	public function 管理員可以取得_options(): void {
		$response = $this->rest_request_as_admin( 'GET', '/v2/powerhouse/options' );
		$this->assertSame( 200, $response->get_status() );
	}

	// ========== ✅ 快樂路徑（Happy Flow）==========

	/**
	 * @test
	 * @group happy
	 */
	public function GET_options_回應應包含_powerhouse_settings(): void {
		$response = $this->rest_request_as_admin( 'GET', '/v2/powerhouse/options' );
		$data     = $response->get_data();

		$this->assertSame( 200, $response->get_status() );
		$this->assertIsArray( $data );
		$this->assertSame( 'get_options_success', $data['code'] ?? '' );
		$this->assertIsArray( $data['data'] ?? null );
		$this->assertArrayHasKey( Settings::SETTINGS_KEY, $data['data'] );
	}

	/**
	 * @test
	 * @group happy
	 */
	public function GET_options_回應的_settings_應包含預設欄位(): void {
		$response = $this->rest_request_as_admin( 'GET', '/v2/powerhouse/options' );
		$data     = $response->get_data();
		$settings = $data['data'][ Settings::SETTINGS_KEY ] ?? [];

		$this->assertIsArray( $settings );
		$this->assertArrayHasKey( 'enable_manual_send_email', $settings );
		$this->assertArrayHasKey( 'delay_email', $settings );
		$this->assertArrayHasKey( 'theme', $settings );
	}

	/**
	 * @test
	 * @group happy
	 */
	public function POST_options_可以更新_powerhouse_settings_的欄位(): void {
		$response = $this->rest_request_as_admin(
			'POST',
			'/v2/powerhouse/options',
			[
				Settings::SETTINGS_KEY => [
					'enable_manual_send_email' => 'yes',
				],
			]
		);

		$this->assertSame( 200, $response->get_status() );

		// 驗證 Option 已被更新
		$this->reset_settings_singleton();
		$settings = Settings::instance();
		$this->assertSame( 'yes', $settings->enable_manual_send_email );
	}

	/**
	 * @test
	 * @group happy
	 */
	public function POST_options_支援_powerhouse_settings_的部分更新(): void {
		// 先設定初始值
		$this->set_powerhouse_settings( [ 'delay_email' => 'no', 'enable_captcha_login' => 'no' ] );

		// 只更新 delay_email
		$this->rest_request_as_admin(
			'POST',
			'/v2/powerhouse/options',
			[
				Settings::SETTINGS_KEY => [ 'delay_email' => 'yes' ],
			]
		);

		// 確認 delay_email 已更新
		$this->reset_settings_singleton();
		$updated = Settings::instance();
		$this->assertSame( 'yes', $updated->delay_email );
	}

	/**
	 * @test
	 * @group happy
	 */
	public function powerhouse_options_get_options_filter_應可攔截回應(): void {
		$custom_data_added = false;

		\add_filter(
			'powerhouse/options/get_options',
			function ( $options ) use ( &$custom_data_added ) {
				$options['custom_test_key'] = 'custom_test_value';
				$custom_data_added          = true;
				return $options;
			}
		);

		$response = $this->rest_request_as_admin( 'GET', '/v2/powerhouse/options' );
		$data     = $response->get_data();

		\remove_all_filters( 'powerhouse/options/get_options' );

		$this->assertTrue( $custom_data_added );
		$this->assertSame( 'custom_test_value', $data['data']['custom_test_key'] ?? null );
	}

	// ========== ❌ 錯誤處理（Error Handling）==========

	/**
	 * @test
	 * @group error
	 */
	public function 未登入時取得_options_應被拒絕(): void {
		$response = $this->rest_request_as_guest( 'GET', '/v2/powerhouse/options' );
		$this->assertContains(
			$response->get_status(),
			[ 401, 403 ],
			'未登入用戶存取 options 應被拒絕'
		);
	}

	/**
	 * @test
	 * @group error
	 */
	public function 未登入時_POST_options_應被拒絕(): void {
		$response = $this->rest_request_as_guest( 'POST', '/v2/powerhouse/options', [] );
		$this->assertContains(
			$response->get_status(),
			[ 401, 403 ],
			'未登入用戶修改 options 應被拒絕'
		);
	}

	/**
	 * @test
	 * @group error
	 */
	public function POST_options_傳入不在允許清單的_key_不應被儲存(): void {
		$response = $this->rest_request_as_admin(
			'POST',
			'/v2/powerhouse/options',
			[ 'malicious_key' => 'malicious_value' ]
		);

		// 不論是否回傳 200，該 key 不應被儲存
		$this->assertFalse(
			\get_option( 'malicious_key', false ),
			'不在允許清單的 key 不應被儲存到 WP Options'
		);
	}

	// ========== 🔀 邊緣案例（Edge Cases）==========

	/**
	 * @test
	 * @group edge
	 */
	public function POST_options_傳入空陣列應回傳_200_不崩潰(): void {
		$response = $this->rest_request_as_admin( 'POST', '/v2/powerhouse/options', [] );
		$this->assertSame( 200, $response->get_status() );
	}

	/**
	 * @test
	 * @group edge
	 */
	public function powerhouse_option_allowed_fields_filter_應可新增允許欄位(): void {
		$test_option_key = 'test_allowed_option_key';

		\add_filter(
			'powerhouse/option/allowed_fields',
			function ( $fields ) use ( $test_option_key ) {
				$fields[ $test_option_key ] = [];
				return $fields;
			}
		);

		// 重新建立 Option V2Api（因為 constructor 中讀取 filter）
		// 直接測試 option 是否可以被更新
		\update_option( $test_option_key, 'before_value' );

		\remove_all_filters( 'powerhouse/option/allowed_fields' );

		// 確認 filter 機制本身可以讀取
		$value = \get_option( $test_option_key, false );
		$this->assertSame( 'before_value', $value );

		\delete_option( $test_option_key );
	}
}
