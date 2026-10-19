<?php
/**
 * 登入驗證碼的豁免清單
 *
 * power-partner-server 的 identity、v3/connect、點數明細由經銷商站的 PHP 送出真實密碼驗證，
 * 帶不了驗證碼。cloud.luke.cafe 也裝了 Powerhouse，若這幾條也掛驗證碼，
 * 角色在驗證碼清單內的經銷商會被擋下（v3/connect 換不到專屬密碼、點數明細下載失敗）。
 * 其他路徑照舊檢查。
 */

declare( strict_types=1 );

namespace Tests\Integration;

use J7\Powerhouse\Captcha\Core\Login;

/**
 * Class CaptchaExemptTest
 *
 * @group captcha
 */
class CaptchaExemptTest extends TestCase {

	/** @var string|null 原本的 REQUEST_URI */
	private ?string $original_request_uri = null;

	public function set_up(): void {
		parent::set_up();
		$this->original_request_uri = isset( $_SERVER['REQUEST_URI'] ) ? (string) $_SERVER['REQUEST_URI'] : null;
		$this->set_powerhouse_settings(
			[
				'enable_captcha_login' => 'yes',
				'captcha_role_list'    => [ 'administrator' ],
			]
		);
	}

	public function tear_down(): void {
		if ( null === $this->original_request_uri ) {
			unset( $_SERVER['REQUEST_URI'] );
		} else {
			$_SERVER['REQUEST_URI'] = $this->original_request_uri;
		}
		parent::tear_down();
	}

	/**
	 * 以指定的 REQUEST_URI 建立 Login（singleton 只會建一次，這裡直接 new 才能測到 constructor 的分支）
	 *
	 * @param string $request_uri REQUEST_URI
	 * @return int|false authenticate filter 的優先序，沒掛則為 false
	 */
	private function authenticate_priority_for( string $request_uri ): int|false {
		$_SERVER['REQUEST_URI'] = $request_uri;
		$login                  = new Login();
		return \has_filter( 'authenticate', [ $login, 'authenticate' ] );
	}

	/**
	 * @return array<string, array{0: string}>
	 */
	public function exempt_uris(): array {
		return [
			'舊流程登入 identity'   => [ '/wp-json/power-partner-server/identity' ],
			'換取專屬密碼 v3/connect' => [ '/wp-json/power-partner-server/v3/connect' ],
			'點數明細 logs/{id}/detail' => [ '/wp-json/power-partner-server/logs/a1b2-c3d4/detail' ],
		];
	}

	/**
	 * @return array<string, array{0: string}>
	 */
	public function checked_uris(): array {
		return [
			'登入頁'                    => [ '/wp-login.php' ],
			'WooCommerce 我的帳號'      => [ '/my-account/' ],
			'core REST'                 => [ '/wp-json/wp/v2/users/me' ],
			'同 namespace 的其他路由'   => [ '/wp-json/power-partner-server/apps' ],
			'logs 列表（沒有 /detail）' => [ '/wp-json/power-partner-server/logs' ],
			'授權碼啟用'                => [ '/wp-json/power-partner-server/license-codes/activate' ],
			'v3/identity 不在清單'      => [ '/wp-json/power-partner-server/v3/identity' ],
		];
	}

	/**
	 * @test
	 * @group security
	 * @dataProvider exempt_uris
	 */
	public function server_對_server_的真實密碼驗證_不掛驗證碼( string $request_uri ): void {
		$this->assertTrue( Login::is_exempt_request_uri( $request_uri ) );
		$this->assertFalse( $this->authenticate_priority_for( $request_uri ), '不可註冊 authenticate filter' );
	}

	/**
	 * @test
	 * @group security
	 * @dataProvider checked_uris
	 */
	public function 其他路徑照舊掛驗證碼( string $request_uri ): void {
		$this->assertFalse( Login::is_exempt_request_uri( $request_uri ) );
		$this->assertSame( 999, $this->authenticate_priority_for( $request_uri ), '應註冊 authenticate filter' );
	}
}
