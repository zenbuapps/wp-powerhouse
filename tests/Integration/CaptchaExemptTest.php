<?php
/**
 * 登入驗證碼的豁免清單
 *
 * power-partner-server 的 identity、v3/connect、點數明細由經銷商站的 PHP 送出真實密碼驗證，
 * 帶不了驗證碼。cloud.luke.cafe 也裝了 Powerhouse，若這幾條也檢查驗證碼，
 * 角色在驗證碼清單內的經銷商會被擋下（v3/connect 換不到專屬密碼、點數明細下載失敗）。
 *
 * 豁免只看實際分派的 REST 路由，且只在 REST 分派中成立：
 * - 登入頁網址的 query 帶這些字串不能跳過驗證碼
 * - 不在 REST 分派中（例如 WooCommerce 在 wp_loaded 處理登入表單）不能跳過驗證碼
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
	 * @return array<string, array{0: string}>
	 */
	public function exempt_request_uris(): array {
		return [
			'舊流程登入 identity'                => [ '/wp-json/power-partner-server/identity' ],
			'換取專屬密碼 v3/connect'              => [ '/wp-json/power-partner-server/v3/connect' ],
			'點數明細 logs/{id}/detail'            => [ '/wp-json/power-partner-server/logs/a1b2-c3d4/detail' ],
			'rest_route 參數形式'                  => [ '/?rest_route=/power-partner-server/v3/connect' ],
			'rest_route 參數經 URL 編碼'           => [ '/index.php?rest_route=%2Fpower-partner-server%2Fidentity' ],
			'子目錄安裝'                           => [ '/blog/wp-json/power-partner-server/v3/connect' ],
			'大寫路由'                             => [ '/wp-json/Power-Partner-Server/V3/Connect' ],
		];
	}

	/**
	 * @return array<string, array{0: string}>
	 */
	public function checked_request_uris(): array {
		return [
			'登入頁'                                 => [ '/wp-login.php' ],
			'登入頁 query 帶 identity'               => [ '/wp-login.php?x=power-partner-server/identity' ],
			'登入頁 query 帶 v3/connect'             => [ '/wp-login.php?redirect_to=/wp-json/power-partner-server/v3/connect' ],
			'WooCommerce 我的帳號'                   => [ '/my-account/' ],
			'我的帳號 query 帶 v3/connect'           => [ '/my-account/?power-partner-server/v3/connect' ],
			'其他 REST 路由 query 帶 v3/connect'     => [ '/wp-json/wp/v2/users?y=power-partner-server/v3/connect' ],
			'core REST'                              => [ '/wp-json/wp/v2/users/me' ],
			'同 namespace 的其他路由'                => [ '/wp-json/power-partner-server/apps' ],
			'logs 列表（沒有 /detail）'              => [ '/wp-json/power-partner-server/logs' ],
			'logs 多一層路徑'                        => [ '/wp-json/power-partner-server/logs/a/b/detail' ],
			'授權碼啟用'                             => [ '/wp-json/power-partner-server/license-codes/activate' ],
			'v3/identity 不在清單'                   => [ '/wp-json/power-partner-server/v3/identity' ],
			'rest_route 指向其他路由'                => [ '/?rest_route=/wp/v2/users&x=power-partner-server/identity' ],
		];
	}

	/**
	 * @test
	 * @group security
	 * @dataProvider exempt_request_uris
	 */
	public function REST_分派中的三條路由豁免( string $request_uri ): void {
		$this->assertTrue( Login::is_exempt_request( $request_uri, true ) );
	}

	/**
	 * @test
	 * @group security
	 * @dataProvider checked_request_uris
	 */
	public function 其他請求照舊檢查驗證碼( string $request_uri ): void {
		$this->assertFalse( Login::is_exempt_request( $request_uri, true ) );
	}

	/**
	 * WooCommerce 在 wp_loaded 處理登入表單，比 REST 分派早：網址是豁免路由也不能跳過
	 *
	 * @test
	 * @group security
	 * @dataProvider exempt_request_uris
	 */
	public function 不在_REST_分派中_豁免路由也要檢查驗證碼( string $request_uri ): void {
		$this->assertFalse( Login::is_exempt_request( $request_uri, false ) );
	}

	/**
	 * 實際走 authenticate filter：登入頁 query 帶豁免字串，管理員仍被驗證碼擋下
	 *
	 * @test
	 * @group security
	 */
	public function 登入頁_query_帶豁免字串_authenticate_仍回驗證碼錯誤(): void {
		$_SERVER['REQUEST_URI'] = '/wp-login.php?x=power-partner-server/identity';
		$login                  = new Login();
		$admin                  = $this->factory()->user->create_and_get( [ 'role' => 'administrator' ] );

		$this->assertSame( 999, \has_filter( 'authenticate', [ $login, 'authenticate' ] ), '啟用時一律註冊 authenticate filter' );

		$result = $login->authenticate( $admin, $admin->user_login, 'pw' );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'captcha_failed', $result->get_error_code() );
	}

	/**
	 * @test
	 * @group edge
	 */
	public function rest_route_of_取出要分派的路由(): void {
		$this->assertSame( '/power-partner-server/v3/connect', Login::rest_route_of( '/wp-json/power-partner-server/v3/connect' ) );
		$this->assertSame( '/power-partner-server/v3/connect', Login::rest_route_of( '/?rest_route=/power-partner-server/v3/connect' ) );
		$this->assertSame( '/wp/v2/users', Login::rest_route_of( '/wp-json/wp/v2/users?y=power-partner-server/v3/connect' ) );
		$this->assertSame( '', Login::rest_route_of( '/wp-login.php?x=power-partner-server/identity' ) );
		$this->assertSame( '', Login::rest_route_of( '/my-account/' ) );
	}
}
