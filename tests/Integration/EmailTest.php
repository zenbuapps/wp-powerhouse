<?php
/**
 * Email 整合測試
 * 驗證 DelayEmail 與 EmailValidator mu-plugin 的行為
 */

declare( strict_types=1 );

namespace Tests\Integration;

/**
 * Class EmailTest
 *
 * @group email
 */
class EmailTest extends TestCase {

	// ========== 🔥 冒煙測試 ==========

	/**
	 * @test
	 * @group smoke
	 */
	public function delay_email_類別應存在(): void {
		$this->assertTrue( class_exists( \J7\Powerhouse\Admin\DelayEmail::class ) );
	}

	/**
	 * @test
	 * @group smoke
	 */
	public function email_validator_mu_plugin_類別應存在(): void {
		// mu-plugin 加載後類別應存在於 J7\Powerhouse\MU 命名空間
		$this->assertTrue(
			class_exists( 'J7\Powerhouse\MU\EmailValidator' ),
			'EmailValidator mu-plugin 應被載入'
		);
	}

	// ========== ✅ 快樂路徑 ==========

	/**
	 * @test
	 * @group happy
	 */
	public function delay_email_關閉時不應註冊_remove_origin_email_sending(): void {
		$this->set_powerhouse_settings( [ 'delay_email' => 'no' ] );

		// 先完全移除，避免上次 set_up 殘留
		\remove_all_actions( 'init', 100 );
		\remove_all_actions( 'powerhouse_delay_email' );

		// 重建 DelayEmail
		\J7\Powerhouse\Admin\DelayEmail::instance();

		// 在關閉狀態下不應註冊 powerhouse_delay_email action
		$this->assertFalse(
			\has_action( 'powerhouse_delay_email' ),
			'delay_email=no 時不應註冊 powerhouse_delay_email'
		);
	}

	/**
	 * @test
	 * @group happy
	 */
	public function pre_wp_mail_validate_白名單網域應通過(): void {
		if ( ! class_exists( 'J7\Powerhouse\MU\EmailValidator' ) ) {
			$this->markTestSkipped( 'EmailValidator mu-plugin 未載入' );
			return;
		}

		$validator = new \J7\Powerhouse\MU\EmailValidator();
		$result    = $validator->pre_wp_mail_validate(
			null,
			[
				'to'          => 'test@gmail.com',
				'subject'     => 'test',
				'message'     => 'test',
				'headers'     => '',
				'attachments' => '',
			]
		);

		// 白名單應通過 → 回傳 null（原 $return）
		$this->assertNull( $result );
	}

	// ========== 🔀 邊緣案例 ==========

	/**
	 * @test
	 * @group edge
	 */
	public function registration_errors_validate_無效_email_格式應累加錯誤(): void {
		if ( ! class_exists( 'J7\Powerhouse\MU\EmailValidator' ) ) {
			$this->markTestSkipped( 'EmailValidator mu-plugin 未載入' );
			return;
		}

		$validator = new \J7\Powerhouse\MU\EmailValidator();
		$errors    = new \WP_Error();
		$result    = $validator->registration_errors_validate( $errors, 'tester', 'not_an_email' );

		$this->assertSame( $errors, $result );
		$this->assertContains( 'invalid_email_domain', $result->get_error_codes() );
	}
}
