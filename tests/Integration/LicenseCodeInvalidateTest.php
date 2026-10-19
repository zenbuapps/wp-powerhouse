<?php
/**
 * 授權碼 invalidate：以授權碼本身驗證請求
 *
 * CloudServer（power-partner-server）在授權碼狀態改變時呼叫客戶站的 lc/invalidate。
 * 這個端點不需登入，所以不能只憑「有人呼叫」就刪除已存的授權碼：
 * - 帶來的 code 與已存的相同 → 刪除授權碼與快取（舊行為）
 *   授權碼被釋出成「可用」時，CloudServer 的 activate 會把可用的碼綁到請求的網域，
 *   所以必須刪，否則下一次系統檢查會把剛釋出的碼重新綁回本站
 * - code 不符、或本站沒有已存的碼 → 只清快取，下一次系統檢查交給 CloudServer 判定
 * - 沒帶 code → 照舊刪除（舊版 CloudServer 不帶 code）
 */

declare( strict_types=1 );

namespace Tests\Integration;

use J7\Powerhouse\Domains\LC\Utils\Base as LCBase;

/**
 * Class LicenseCodeInvalidateTest
 *
 * @group license-code
 */
class LicenseCodeInvalidateTest extends TestCase {

	private const PRODUCT_SLUG = 'power-course';

	private const SAVED_CODE = 'TEST-ABCD-1234-EFGH';

	/** @var int 系統檢查打 activate 的次數 */
	private int $activate_calls = 0;

	public function tear_down(): void {
		$this->remove_http_stubs();
		\remove_all_filters( 'powerhouse_product_infos' );
		\delete_transient( 'lc_' . self::PRODUCT_SLUG );
		\delete_option( LCBase::KEY );
		parent::tear_down();
	}

	/**
	 * 本站已啟用授權碼：已存的碼 + 有效快取
	 */
	private function given_activated_code(): void {
		$this->register_lc_product( self::PRODUCT_SLUG, 'Power Course' );
		\update_option( LCBase::KEY, [ self::PRODUCT_SLUG => self::SAVED_CODE ] );
		$this->set_activated_lc_transient( self::PRODUCT_SLUG, self::SAVED_CODE );
	}

	/**
	 * @param array<string, mixed> $body request body
	 * @param string               $route 路由
	 * @return \WP_REST_Response
	 */
	private function invalidate( array $body, string $route = '/v2/powerhouse/lc/invalidate' ): \WP_REST_Response {
		return $this->rest_request_as_guest( 'POST', $route, $body );
	}

	/**
	 * 攔截系統檢查的 activate，計算次數並回指定的回應
	 *
	 * @param int                  $status HTTP status
	 * @param array<string, mixed> $body   回應 body
	 */
	private function stub_activate( int $status, array $body ): void {
		\add_filter(
			'pre_http_request',
			/**
			 * @param false|array<string, mixed>|\WP_Error $preempt
			 * @param array<string, mixed>                 $args
			 * @param string                               $url
			 * @return false|array<string, mixed>|\WP_Error
			 */
			function ( $preempt, array $args, string $url ) use ( $status, $body ) {
				if ( ! str_contains( $url, 'power-partner-server/license-codes/activate' ) ) {
					return $preempt;
				}
				++$this->activate_calls;
				return [
					'response' => [
						'code'    => $status,
						'message' => '',
					],
					'body'     => (string) \wp_json_encode( $body ),
					'headers'  => [],
				];
			},
			10,
			3
		);
	}

	/**
	 * 觸發系統檢查（GET /lc → get_lc_array）
	 */
	private function run_system_check(): void {
		$response = $this->rest_request_as_admin( 'GET', '/v2/powerhouse/lc' );
		$this->assertSame( 200, $response->get_status() );
	}

	/**
	 * @return array<string, string>
	 */
	private function saved_codes(): array {
		/** @var array<string, string> $codes */
		$codes = \get_option( LCBase::KEY, [] );
		return $codes;
	}

	/**
	 * @test
	 * @group happy
	 */
	public function 帶正確的_code_應刪除授權碼與快取(): void {
		$this->given_activated_code();

		$response = $this->invalidate(
			[
				'product_slug' => self::PRODUCT_SLUG,
				'code'         => self::SAVED_CODE,
			]
		);

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( 'invalidate_lc_cache_success', $response->get_data()['code'] );
		$this->assertArrayNotHasKey( self::PRODUCT_SLUG, $this->saved_codes() );
		$this->assertFalse( \get_transient( 'lc_' . self::PRODUCT_SLUG ) );
	}

	/**
	 * @test
	 * @group security
	 */
	public function 帶錯誤的_code_應保留授權碼_只清快取(): void {
		$this->given_activated_code();

		$response = $this->invalidate(
			[
				'product_slug' => self::PRODUCT_SLUG,
				'code'         => 'WRONG-CODE-0000-0000',
			]
		);

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( self::SAVED_CODE, $this->saved_codes()[ self::PRODUCT_SLUG ] ?? null, '授權碼不可被刪除' );
		$this->assertFalse( \get_transient( 'lc_' . self::PRODUCT_SLUG ), '快取應被清除，強制下次重新檢查' );
	}

	/**
	 * 舊路由（/powerhouse/lc/invalidate，CloudServer 目前呼叫的）也走同一個 callback
	 *
	 * @test
	 * @group security
	 */
	public function 舊路由帶錯誤的_code_同樣保留授權碼(): void {
		$this->given_activated_code();

		$response = $this->invalidate(
			[
				'product_slug' => self::PRODUCT_SLUG,
				'code'         => 'WRONG-CODE-0000-0000',
			],
			'/powerhouse/lc/invalidate'
		);

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( self::SAVED_CODE, $this->saved_codes()[ self::PRODUCT_SLUG ] ?? null );
	}

	/**
	 * 舊版 CloudServer 不帶 code，必須維持舊行為，否則釋出授權碼後舊站會把碼重新綁回自己
	 *
	 * @test
	 * @group happy
	 */
	public function 不帶_code_應照舊刪除授權碼與快取(): void {
		$this->given_activated_code();

		$response = $this->invalidate( [ 'product_slug' => self::PRODUCT_SLUG ] );

		$this->assertSame( 200, $response->get_status() );
		$this->assertArrayNotHasKey( self::PRODUCT_SLUG, $this->saved_codes() );
		$this->assertFalse( \get_transient( 'lc_' . self::PRODUCT_SLUG ) );
	}

	/**
	 * @test
	 * @group edge
	 */
	public function 本站沒有已存的碼_只清快取(): void {
		$this->register_lc_product( self::PRODUCT_SLUG, 'Power Course' );
		\update_option( LCBase::KEY, [ 'power-shop' => 'OTHER-CODE' ] );
		$this->set_activated_lc_transient( self::PRODUCT_SLUG, self::SAVED_CODE );

		$response = $this->invalidate(
			[
				'product_slug' => self::PRODUCT_SLUG,
				'code'         => self::SAVED_CODE,
			]
		);

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( [ 'power-shop' => 'OTHER-CODE' ], $this->saved_codes(), '其他產品的授權碼不受影響' );
		$this->assertFalse( \get_transient( 'lc_' . self::PRODUCT_SLUG ) );
	}

	/**
	 * 釋出授權碼的整段流程：CloudServer 把碼改成「可用」→ 帶正確 code 呼叫 invalidate
	 * → 舊站不再以這個碼打 activate（打了就會被 CloudServer 重新綁回舊站）
	 *
	 * @test
	 * @group happy
	 */
	public function 釋出授權碼後_舊站不會再打_activate(): void {
		$this->given_activated_code();
		$this->stub_activate(
			200,
			[
				'id'           => 1,
				'post_status'  => 'activated',
				'code'         => self::SAVED_CODE,
				'type'         => 'standard',
				'expire_date'  => 0,
				'domain'       => 'old-site.example',
				'product_id'   => 1,
				'product_slug' => self::PRODUCT_SLUG,
				'product_name' => 'Power Course',
			]
		);

		$this->invalidate(
			[
				'product_slug' => self::PRODUCT_SLUG,
				'code'         => self::SAVED_CODE,
			]
		);
		$this->run_system_check();

		$this->assertSame( 0, $this->activate_calls, '已刪除授權碼，系統檢查不可再打 activate' );
		$this->assertArrayNotHasKey( self::PRODUCT_SLUG, $this->saved_codes() );
	}

	/**
	 * 只清快取之後，下一次系統檢查交給 CloudServer 判定：碼仍有效 → 保留並重新快取
	 *
	 * @test
	 * @group happy
	 */
	public function 只清快取後_碼仍有效時_系統檢查保留並重新快取(): void {
		$this->given_activated_code();
		$this->stub_activate(
			200,
			[
				'id'           => 1,
				'post_status'  => 'activated',
				'code'         => self::SAVED_CODE,
				'type'         => 'standard',
				'expire_date'  => 0,
				'domain'       => 'example.org',
				'product_id'   => 1,
				'product_slug' => self::PRODUCT_SLUG,
				'product_name' => 'Power Course',
			]
		);

		$this->invalidate(
			[
				'product_slug' => self::PRODUCT_SLUG,
				'code'         => 'WRONG-CODE-0000-0000',
			]
		);
		$this->run_system_check();

		$this->assertSame( 1, $this->activate_calls );
		$this->assertSame( self::SAVED_CODE, $this->saved_codes()[ self::PRODUCT_SLUG ] ?? null );
		$this->assertNotFalse( \get_transient( 'lc_' . self::PRODUCT_SLUG ), '應重新快取' );
		$this->assertTrue( LCBase::ia( self::PRODUCT_SLUG ) );
	}

	/**
	 * 只清快取之後，CloudServer 判定碼無效（授權判定 401，body 只有 message）→ 照舊刪碼
	 *
	 * @test
	 * @group happy
	 */
	public function 只清快取後_碼已失效時_系統檢查照舊刪碼(): void {
		$this->given_activated_code();
		$this->stub_activate( 401, [ 'message' => '《TEST-ABCD-1234-EFGH》 授權碼目前狀態為已過期，無法被啟用' ] );

		$this->invalidate(
			[
				'product_slug' => self::PRODUCT_SLUG,
				'code'         => 'WRONG-CODE-0000-0000',
			]
		);
		$this->run_system_check();

		$this->assertSame( 1, $this->activate_calls );
		$this->assertArrayNotHasKey( self::PRODUCT_SLUG, $this->saved_codes() );
		$this->assertFalse( LCBase::ia( self::PRODUCT_SLUG ) );
	}
}
