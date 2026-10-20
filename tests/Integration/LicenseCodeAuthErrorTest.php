<?php
/**
 * 授權碼啟用：區分「授權判定 401」與「API 認證 401」
 *
 * CloudServer（power-partner-server）回 401 有兩種來源：
 * - 授權判定（碼無效、狀態不可啟用、網域不符）→ body 只有 `message` → 應掉授權（既有行為）
 * - WP REST 認證／權限錯誤（rest_forbidden、incorrect_password…）→ body 帶 `code`
 *   → 與授權碼無關（CloudServer 撤銷或更換 API 憑證時所有站同時收到），不可掉授權
 *
 * 後者若被當成前者，CloudServer 一次憑證異動就會讓所有站在下次系統檢查時掉授權、要手動重輸授權碼。
 */

declare( strict_types=1 );

namespace Tests\Integration;

use J7\Powerhouse\Domains\LC\Utils\Base as LCBase;

/**
 * Class LicenseCodeAuthErrorTest
 *
 * @group license-code
 */
class LicenseCodeAuthErrorTest extends TestCase {

	private const PRODUCT_SLUG = 'power-course';

	private const SAVED_CODE = 'TEST-ABCD-1234-EFGH';

	public function tear_down(): void {
		$this->remove_http_stubs();
		\remove_all_filters( 'powerhouse_product_infos' );
		\delete_transient( 'lc_' . self::PRODUCT_SLUG );
		\delete_option( LCBase::KEY );
		parent::tear_down();
	}

	/**
	 * 已存授權碼、transient 過期 → 下一次 GET /lc 會發系統檢查
	 *
	 * @param int                  $status HTTP status
	 * @param array<string, mixed> $body   回應 body
	 * @return array<string, mixed>|null power-course 的授權資訊
	 */
	private function system_check_with( int $status, array $body ): ?array {
		$this->register_lc_product( self::PRODUCT_SLUG, 'Power Course' );
		\update_option( LCBase::KEY, [ self::PRODUCT_SLUG => self::SAVED_CODE ] );
		\delete_transient( 'lc_' . self::PRODUCT_SLUG );

		$this->stub_http_request(
			'power-partner-server/license-codes',
			[
				'response' => [
					'code'    => $status,
					'message' => '',
				],
				'body'     => (string) \wp_json_encode( $body ),
				'headers'  => [],
			]
		);

		$response = $this->rest_request_as_admin( 'GET', '/v2/powerhouse/lc' );
		$this->assertSame( 200, $response->get_status() );

		foreach ( (array) $response->get_data() as $item ) {
			if ( ( $item['product_slug'] ?? '' ) === self::PRODUCT_SLUG ) {
				return $item;
			}
		}
		return null;
	}

	/**
	 * @return array<string, array{0: string}>
	 */
	public function wp_auth_error_codes(): array {
		return [
			'權限不足（manage_options 被拿掉）' => [ 'rest_forbidden' ],
			'應用程式密碼已撤銷'             => [ 'incorrect_password' ],
			'API 帳號不存在'                 => [ 'invalid_username' ],
		];
	}

	/**
	 * @test
	 * @group error
	 * @dataProvider wp_auth_error_codes
	 *
	 * @param string $code WP REST 錯誤代碼
	 */
	public function 系統檢查收到API認證401時保留授權碼與啟用狀態( string $code ): void {
		$found = $this->system_check_with(
			401,
			[
				'code'    => $code,
				'message' => 'Sorry, you are not allowed to do that.',
				'data'    => [ 'status' => 401 ],
			]
		);

		$this->assertNotNull( $found );
		$this->assertSame( 'activated', $found['post_status'], 'API 認證錯誤與授權碼無關，不可掉授權' );
		$this->assertSame( self::SAVED_CODE, \get_option( LCBase::KEY )[ self::PRODUCT_SLUG ] ?? null, '已存的授權碼不可被刪除' );
	}

	/**
	 * CloudServer 的失敗限流回 429，有效碼也會被擋 —— 必須維持原狀等下次檢查
	 *
	 * @test
	 * @group error
	 */
	public function 系統檢查收到429限流時保留授權碼與啟用狀態(): void {
		$found = $this->system_check_with(
			429,
			[
				'code'    => 'rate_limited',
				'message' => 'Too many requests',
				'data'    => [
					'status'     => 429,
					'error_code' => 'rate_limited',
				],
			]
		);

		$this->assertNotNull( $found );
		$this->assertSame( 'activated', $found['post_status'] );
		$this->assertSame( self::SAVED_CODE, \get_option( LCBase::KEY )[ self::PRODUCT_SLUG ] ?? null );
	}

	/**
	 * 對照組：授權判定的 401（body 只有 message）仍照舊掉授權
	 *
	 * @test
	 * @group happy
	 */
	public function 系統檢查收到授權判定401時照舊清除授權(): void {
		$found = $this->system_check_with( 401, [ 'message' => '授權碼無效' ] );

		$this->assertNotNull( $found );
		$this->assertSame( '', $found['post_status'] );
		$this->assertArrayNotHasKey( self::PRODUCT_SLUG, (array) \get_option( LCBase::KEY, [] ) );
	}

	/**
	 * 手動啟用時遇到 API 認證 401：仍要回錯誤給操作者，且不可寫入授權
	 *
	 * @test
	 * @group error
	 */
	public function 手動啟用收到API認證401時回錯誤且不寫入授權(): void {
		$this->register_lc_product( self::PRODUCT_SLUG, 'Power Course' );
		$this->stub_http_request(
			'power-partner-server/license-codes',
			[
				'response' => [
					'code'    => 401,
					'message' => 'Unauthorized',
				],
				'body'     => (string) \wp_json_encode(
					[
						'code'    => 'rest_forbidden',
						'message' => 'Sorry, you are not allowed to do that.',
						'data'    => [ 'status' => 401 ],
					]
				),
				'headers'  => [],
			]
		);

		$response = $this->rest_request_as_admin(
			'POST',
			'/v2/powerhouse/lc/activate',
			[
				'code'         => 'ANY-CODE',
				'product_slug' => self::PRODUCT_SLUG,
			]
		);

		$this->assertGreaterThanOrEqual( 400, $response->get_status() );
		$this->assertFalse( \get_transient( 'lc_' . self::PRODUCT_SLUG ) );
		$this->assertArrayNotHasKey( self::PRODUCT_SLUG, (array) \get_option( LCBase::KEY, [] ) );
	}
}
