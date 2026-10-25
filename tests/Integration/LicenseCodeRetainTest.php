<?php
/**
 * 授權碼過期／停用時保留授權碼，恢復後自動回來（issue #14）
 *
 * CloudServer（power-partner-server）新版在 activate 判定失敗時回 401 + body `reason`：
 * - status_expired / status_deactivated / status_other
 *   → 授權碼還是本站的，只是暫時不能用：保留已存的碼，寫一個「未授權」transient（6 小時後再檢查）
 * - not_found / product_mismatch / invalid_request / bound_elsewhere，或沒有 reason（舊版 CloudServer）→ 照舊刪碼
 *   bound_elsewhere 代表客戶已搬站；保留的話，新網域之後釋出授權碼時，本站的系統檢查會把碼綁回自己
 *
 * lc/invalidate 帶 status：available 或沒帶 → 刪碼；其他 → 只清快取、保留碼
 * lc/refresh：碼相符 → 只清快取，讓下一次系統檢查重新 activate；不論任何情況都不刪碼
 */

declare( strict_types=1 );

namespace Tests\Integration;

use J7\Powerhouse\Domains\LC\Utils\Base as LCBase;

/**
 * Class LicenseCodeRetainTest
 *
 * @group license-code
 */
class LicenseCodeRetainTest extends TestCase {

	private const PRODUCT_SLUG = 'power-course';

	private const SAVED_CODE = 'TEST-ABCD-1234-EFGH';

	/** @var int 打 license-codes/activate 的次數 */
	private int $activate_calls = 0;

	/**
	 * 下一次 activate 的回應；callable 時以請求參數決定回應（模擬 CloudServer 依 is_system_check 判斷）
	 *
	 * @var array{status: int, body: array<string, mixed>}|\WP_Error|callable|null
	 */
	private $activate_response = null;

	/** @var array<int, array<string, mixed>> 每次打 activate 的請求參數 */
	private array $activate_requests = [];

	public function set_up(): void {
		parent::set_up();
		$this->activate_calls    = 0;
		$this->activate_response = null;
		$this->activate_requests = [];

		\add_filter(
			'pre_http_request',
			/**
			 * @param false|array<string, mixed>|\WP_Error $preempt
			 * @param array<string, mixed>                 $args
			 * @param string                               $url
			 * @return false|array<string, mixed>|\WP_Error
			 */
			function ( $preempt, array $args, string $url ) {
				if ( ! str_contains( $url, 'power-partner-server/license-codes/activate' ) ) {
					return $preempt;
				}
				++$this->activate_calls;
				$params                    = json_decode( (string) ( $args['body'] ?? '' ), true );
				$params                    = is_array( $params ) ? $params : [];
				$this->activate_requests[] = $params;

				$activate_response = is_callable( $this->activate_response ) ? ( $this->activate_response )( $params ) : $this->activate_response;
				if ( \is_wp_error( $activate_response ) ) {
					return $activate_response;
				}
				if ( null === $activate_response ) {
					return new \WP_Error( 'http_request_failed', '測試沒有設定 activate 回應' );
				}
				return [
					'response' => [
						'code'    => $activate_response['status'],
						'message' => '',
					],
					'body'     => (string) \wp_json_encode( $activate_response['body'] ),
					'headers'  => [],
				];
			},
			10,
			3
		);
	}

	public function tear_down(): void {
		$this->remove_http_stubs();
		\remove_all_filters( 'powerhouse_product_infos' );
		\delete_transient( 'lc_' . self::PRODUCT_SLUG );
		\delete_option( LCBase::KEY );
		\delete_option( LCBase::UNAUTHORIZED_KEY );
		parent::tear_down();
	}

	// ========== Helpers ==========

	/**
	 * 本站已存授權碼、transient 已過期（下一次系統檢查會打 activate）
	 */
	private function given_saved_code_without_cache(): void {
		$this->register_lc_product( self::PRODUCT_SLUG, 'Power Course' );
		\update_option( LCBase::KEY, [ self::PRODUCT_SLUG => self::SAVED_CODE ] );
		\delete_transient( 'lc_' . self::PRODUCT_SLUG );
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
	 * CloudServer 判定失敗的 401
	 *
	 * @param string|null $reason reason，null 表示舊版 CloudServer（body 沒有 reason）
	 */
	private function respond_unauthorized( ?string $reason ): void {
		$body = [ 'message' => '《' . self::SAVED_CODE . '》 授權碼無法被啟用' ];
		if ( null !== $reason ) {
			$body['reason'] = $reason;
		}
		$this->activate_response = [
			'status' => 401,
			'body'   => $body,
		];
	}

	/**
	 * activate 成功
	 */
	private function respond_activated(): void {
		$this->activate_response = [
			'status' => 200,
			'body'   => [
				'id'           => 1,
				'post_status'  => 'activated',
				'code'         => self::SAVED_CODE,
				'type'         => 'standard',
				'expire_date'  => 0,
				'domain'       => 'example.org',
				'product_id'   => 1,
				'product_slug' => self::PRODUCT_SLUG,
				'product_name' => 'Power Course',
			],
		];
	}

	/**
	 * 觸發系統檢查（GET /lc → get_lc_array），回傳本產品的授權資訊
	 *
	 * @return array<string, mixed>
	 */
	private function run_system_check(): array {
		$response = $this->rest_request_as_admin( 'GET', '/v2/powerhouse/lc' );
		$this->assertSame( 200, $response->get_status() );

		foreach ( (array) $response->get_data() as $item ) {
			if ( ( $item['product_slug'] ?? '' ) === self::PRODUCT_SLUG ) {
				return $item;
			}
		}
		$this->fail( 'GET /lc 沒有回傳 ' . self::PRODUCT_SLUG );
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
	 * transient 剩餘秒數
	 */
	private function transient_ttl(): int {
		$timeout = (int) \get_option( '_transient_timeout_lc_' . self::PRODUCT_SLUG, 0 );
		return $timeout - time();
	}

	/**
	 * 目前快取的授權狀態
	 *
	 * @return array<string, mixed>|false
	 */
	private function cached_lc() {
		$lc = \get_transient( 'lc_' . self::PRODUCT_SLUG );
		return false === $lc ? false : LCBase::decode( (string) $lc );
	}

	/**
	 * 模擬 6 小時後：未授權 transient 過期
	 */
	private function expire_cache(): void {
		\delete_transient( 'lc_' . self::PRODUCT_SLUG );
	}

	// ========== 系統檢查：依 reason 保留或刪除 ==========

	/**
	 * @return array<string, array{0: string, 1: string}>
	 */
	public function retained_reasons(): array {
		return [
			'已過期'         => [ 'status_expired', 'expired' ],
			'已停用'         => [ 'status_deactivated', 'deactivated' ],
			'其他不可用狀態' => [ 'status_other', 'unauthorized' ],
		];
	}

	/**
	 * @test
	 * @group happy
	 * @dataProvider retained_reasons
	 *
	 * @param string $reason      CloudServer 回的 reason
	 * @param string $post_status 預期寫入的 post_status
	 */
	public function 系統檢查收到可恢復的未授權時保留授權碼並標記未授權( string $reason, string $post_status ): void {
		$this->given_saved_code_without_cache();
		$this->respond_unauthorized( $reason );

		$found = $this->run_system_check();

		$this->assertSame( self::SAVED_CODE, $this->saved_codes()[ self::PRODUCT_SLUG ] ?? null, '已存的授權碼不可被刪除' );
		$this->assertSame( self::SAVED_CODE, $found['code'] );
		$this->assertSame( $post_status, $found['post_status'] );
		$this->assertSame( $reason, $found['unauthorized_reason'] ?? null );
		$this->assertFalse( LCBase::ia( self::PRODUCT_SLUG ), '保留碼的未授權狀態必須判定為未授權' );

		$cached = $this->cached_lc();
		$this->assertIsArray( $cached );
		$this->assertSame( $post_status, $cached['post_status'] );

		$ttl = $this->transient_ttl();
		$this->assertLessThanOrEqual( LCBase::UNAUTHORIZED_RECHECK_TTL, $ttl );
		$this->assertGreaterThan( LCBase::UNAUTHORIZED_RECHECK_TTL - 60, $ttl, '未授權 transient 應 6 小時後過期，再重新檢查' );
	}

	/**
	 * @return array<string, array{0: string|null}>
	 */
	public function deleted_reasons(): array {
		return [
			'碼不存在'                 => [ 'not_found' ],
			'產品不符'                 => [ 'product_mismatch' ],
			'請求無效'                 => [ 'invalid_request' ],
			'已綁定其他網域（客戶已搬站）' => [ 'bound_elsewhere' ],
			'可用的碼不綁定（已釋出）'     => [ 'released' ],
			'舊版 CloudServer 沒有 reason' => [ null ],
			'不認得的 reason'          => [ 'something_new' ],
		];
	}

	/**
	 * @test
	 * @group happy
	 * @dataProvider deleted_reasons
	 *
	 * @param string|null $reason CloudServer 回的 reason
	 */
	public function 系統檢查收到不可恢復的未授權時照舊刪除授權碼( ?string $reason ): void {
		$this->given_saved_code_without_cache();
		$this->respond_unauthorized( $reason );

		$found = $this->run_system_check();

		$this->assertArrayNotHasKey( self::PRODUCT_SLUG, $this->saved_codes() );
		$this->assertSame( '', $found['code'] );
		$this->assertSame( '', $found['post_status'] );
		$this->assertFalse( LCBase::ia( self::PRODUCT_SLUG ) );
		$this->assertArrayNotHasKey( self::PRODUCT_SLUG, (array) \get_option( LCBase::UNAUTHORIZED_KEY, [] ) );
	}

	/**
	 * activate() 要把 reason 帶出來給 get_lc_array() 判斷
	 *
	 * @test
	 * @group happy
	 */
	public function activate_收到401時_WP_Error_帶出_reason(): void {
		$this->respond_unauthorized( 'status_expired' );

		$result = LCBase::activate( self::SAVED_CODE, self::PRODUCT_SLUG, true );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'status_expired', LCBase::get_error_reason( $result ) );

		$this->respond_unauthorized( null );
		$legacy = LCBase::activate( self::SAVED_CODE, self::PRODUCT_SLUG, true );
		$this->assertInstanceOf( \WP_Error::class, $legacy );
		$this->assertSame( '', LCBase::get_error_reason( $legacy ), '舊版 CloudServer 沒有 reason' );
	}

	// ========== 保留後的重新檢查 ==========

	/**
	 * @test
	 * @group happy
	 */
	public function 保留後6小時內不重打API(): void {
		$this->given_saved_code_without_cache();
		$this->respond_unauthorized( 'status_expired' );

		$this->run_system_check();
		$this->run_system_check();
		$this->run_system_check();

		$this->assertSame( 1, $this->activate_calls, '未授權 transient 有效期間不可重打 activate' );
	}

	/**
	 * @test
	 * @group happy
	 */
	public function 保留後_transient過期重新檢查成功即恢復已啟用(): void {
		$this->given_saved_code_without_cache();
		$this->respond_unauthorized( 'status_expired' );
		$this->run_system_check();

		$this->expire_cache();
		$this->respond_activated();
		$found = $this->run_system_check();

		$this->assertSame( 2, $this->activate_calls );
		$this->assertSame( 'activated', $found['post_status'] );
		$this->assertTrue( LCBase::ia( self::PRODUCT_SLUG ), '恢復後不需要重新輸入授權碼' );
		$this->assertSame( self::SAVED_CODE, $this->saved_codes()[ self::PRODUCT_SLUG ] ?? null );
		$this->assertArrayNotHasKey( self::PRODUCT_SLUG, (array) \get_option( LCBase::UNAUTHORIZED_KEY, [] ), '恢復後要清除未授權紀錄' );
		$this->assertGreaterThan( LCBase::UNAUTHORIZED_RECHECK_TTL, $this->transient_ttl(), '恢復後回到一般的 24 小時快取' );
	}

	/**
	 * 保留後重新檢查時，碼已被刪除（not_found）→ 照舊刪碼
	 *
	 * @test
	 * @group happy
	 */
	public function 保留後重新檢查收到碼不存在時刪除授權碼(): void {
		$this->given_saved_code_without_cache();
		$this->respond_unauthorized( 'status_deactivated' );
		$this->run_system_check();

		$this->expire_cache();
		$this->respond_unauthorized( 'not_found' );
		$found = $this->run_system_check();

		$this->assertSame( '', $found['post_status'] );
		$this->assertArrayNotHasKey( self::PRODUCT_SLUG, $this->saved_codes() );
		$this->assertArrayNotHasKey( self::PRODUCT_SLUG, (array) \get_option( LCBase::UNAUTHORIZED_KEY, [] ) );
	}

	/**
	 * 保留後重新檢查時，碼已綁到其他網域（客戶搬站）→ 刪除授權碼，之後不再用這組碼重新檢查
	 *
	 * @test
	 * @group security
	 */
	public function 保留後重新檢查收到綁定其他網域時刪除授權碼且不再重打API(): void {
		$this->given_saved_code_without_cache();
		$this->respond_unauthorized( 'status_expired' );
		$this->run_system_check();

		$this->expire_cache();
		$this->respond_unauthorized( 'bound_elsewhere' );
		$found = $this->run_system_check();

		$this->assertSame( '', $found['code'] );
		$this->assertSame( '', $found['post_status'] );
		$this->assertArrayNotHasKey( self::PRODUCT_SLUG, $this->saved_codes() );
		$this->assertArrayNotHasKey( self::PRODUCT_SLUG, (array) \get_option( LCBase::UNAUTHORIZED_KEY, [] ) );

		// 新網域之後把碼釋出成可用，本站也不會再用這組碼打 activate（打了就會被綁回本站）
		$this->expire_cache();
		$this->respond_activated();
		$this->run_system_check();
		$this->assertSame( 2, $this->activate_calls );
		$this->assertFalse( LCBase::ia( self::PRODUCT_SLUG ) );
	}

	/**
	 * 保留後重新檢查時，碼已被釋出成可用（CloudServer 系統檢查不綁定可用的碼，回 released）→ 刪碼、清除未授權紀錄
	 *
	 * @test
	 * @group happy
	 */
	public function 保留後重新檢查收到_released_時刪除授權碼並清除未授權紀錄(): void {
		$this->given_saved_code_without_cache();
		$this->respond_unauthorized( 'status_expired' );
		$this->run_system_check();
		$this->assertArrayHasKey( self::PRODUCT_SLUG, (array) \get_option( LCBase::UNAUTHORIZED_KEY, [] ) );

		$this->expire_cache();
		$this->respond_unauthorized( 'released' );
		$found = $this->run_system_check();

		$this->assertSame( '', $found['code'] );
		$this->assertSame( '', $found['post_status'] );
		$this->assertArrayNotHasKey( self::PRODUCT_SLUG, $this->saved_codes() );
		$this->assertArrayNotHasKey( self::PRODUCT_SLUG, (array) \get_option( LCBase::UNAUTHORIZED_KEY, [] ) );
		$this->assertFalse( LCBase::ia( self::PRODUCT_SLUG ) );
	}

	// ========== 長期未恢復時的退避 ==========

	/**
	 * 把未授權紀錄的「第一次標記時間」改到過去
	 *
	 * @param string $reason  reason
	 * @param int    $seconds 幾秒前
	 */
	private function given_unauthorized_since( string $reason, int $seconds ): void {
		\update_option(
			LCBase::UNAUTHORIZED_KEY,
			[
				self::PRODUCT_SLUG => [
					'code'   => self::SAVED_CODE,
					'reason' => $reason,
					'at'     => time() - $seconds,
				],
			],
			false
		);
	}

	/**
	 * @return array<string, array{0: string}>
	 */
	public function backoff_reasons(): array {
		return [
			'已過期'         => [ 'status_expired' ],
			'已停用'         => [ 'status_deactivated' ],
			'其他不可用狀態' => [ 'status_other' ],
		];
	}

	/**
	 * @test
	 * @group edge
	 * @dataProvider backoff_reasons
	 *
	 * @param string $reason CloudServer 回的 reason
	 */
	public function 保留超過7天仍未恢復時_改成24小時檢查一次( string $reason ): void {
		$this->given_saved_code_without_cache();
		$this->given_unauthorized_since( $reason, LCBase::UNAUTHORIZED_BACKOFF_AFTER + HOUR_IN_SECONDS );
		$this->respond_unauthorized( $reason );

		$found = $this->run_system_check();

		$this->assertSame( self::SAVED_CODE, $found['code'], '退避期間仍保留授權碼' );
		$this->assertFalse( LCBase::ia( self::PRODUCT_SLUG ) );
		$ttl = $this->transient_ttl();
		$this->assertLessThanOrEqual( LCBase::UNAUTHORIZED_BACKOFF_TTL, $ttl );
		$this->assertGreaterThan( LCBase::UNAUTHORIZED_BACKOFF_TTL - 60, $ttl, '保留超過 7 天改成 24 小時檢查一次' );
	}

	/**
	 * @test
	 * @group edge
	 */
	public function 保留未滿7天時維持6小時檢查一次(): void {
		$this->given_saved_code_without_cache();
		$this->given_unauthorized_since( 'status_expired', LCBase::UNAUTHORIZED_BACKOFF_AFTER - HOUR_IN_SECONDS );
		$this->respond_unauthorized( 'status_expired' );

		$this->run_system_check();

		$ttl = $this->transient_ttl();
		$this->assertLessThanOrEqual( LCBase::UNAUTHORIZED_RECHECK_TTL, $ttl );
		$this->assertGreaterThan( LCBase::UNAUTHORIZED_RECHECK_TTL - 60, $ttl );
	}

	/**
	 * 退避也適用於「重新檢查遇到暫時性錯誤、依未授權紀錄維持未授權」的路徑
	 *
	 * @test
	 * @group edge
	 */
	public function 保留超過7天時_暫時性錯誤也改成24小時檢查一次(): void {
		$this->given_saved_code_without_cache();
		$this->given_unauthorized_since( 'status_other', LCBase::UNAUTHORIZED_BACKOFF_AFTER + HOUR_IN_SECONDS );
		$this->activate_response = new \WP_Error( 'http_request_failed', 'cURL error 28' );

		$found = $this->run_system_check();

		$this->assertSame( 'unauthorized', $found['post_status'] );
		$this->assertGreaterThan( LCBase::UNAUTHORIZED_BACKOFF_TTL - 60, $this->transient_ttl() );
	}

	/**
	 * 第一次標記時間不因 reason 改變而重設（例如過期 → 停用），退避照樣生效
	 *
	 * @test
	 * @group edge
	 */
	public function reason_改變時不重設第一次標記時間(): void {
		$this->given_saved_code_without_cache();
		$since = LCBase::UNAUTHORIZED_BACKOFF_AFTER + HOUR_IN_SECONDS;
		$this->given_unauthorized_since( 'status_expired', $since );
		$this->respond_unauthorized( 'status_deactivated' );

		$found = $this->run_system_check();

		$this->assertSame( 'deactivated', $found['post_status'] );
		/** @var array<string, array{code: string, reason: string, at: int}> $records */
		$records = \get_option( LCBase::UNAUTHORIZED_KEY, [] );
		$this->assertSame( 'status_deactivated', $records[ self::PRODUCT_SLUG ]['reason'] );
		$this->assertLessThanOrEqual( time() - $since, $records[ self::PRODUCT_SLUG ]['at'] );
		$this->assertGreaterThan( LCBase::UNAUTHORIZED_BACKOFF_TTL - 60, $this->transient_ttl() );
	}

	/**
	 * 恢復後再次未授權，從新的時間點重新起算（回到 6 小時）
	 *
	 * @test
	 * @group edge
	 */
	public function 恢復後再次未授權時重新起算(): void {
		$this->given_saved_code_without_cache();
		$this->given_unauthorized_since( 'status_expired', LCBase::UNAUTHORIZED_BACKOFF_AFTER + HOUR_IN_SECONDS );
		$this->respond_activated();
		$this->run_system_check();
		$this->assertTrue( LCBase::ia( self::PRODUCT_SLUG ) );

		$this->expire_cache();
		$this->respond_unauthorized( 'status_expired' );
		$this->run_system_check();

		$ttl = $this->transient_ttl();
		$this->assertLessThanOrEqual( LCBase::UNAUTHORIZED_RECHECK_TTL, $ttl );
		$this->assertGreaterThan( LCBase::UNAUTHORIZED_RECHECK_TTL - 60, $ttl );
	}

	/**
	 * @return array<string, array{0: array{status: int, body: array<string, mixed>}|\WP_Error}>
	 */
	public function transient_failures(): array {
		return [
			'連線失敗'     => [ new \WP_Error( 'http_request_failed', 'cURL error 28' ) ],
			'500'          => [
				[
					'status' => 500,
					'body'   => [ 'message' => 'Internal Server Error' ],
				],
			],
			'429 限流'     => [
				[
					'status' => 429,
					'body'   => [
						'code'    => 'rate_limited',
						'message' => 'Too many requests',
					],
				],
			],
			'API 認證 401' => [
				[
					'status' => 401,
					'body'   => [
						'code'    => 'rest_forbidden',
						'message' => 'Sorry, you are not allowed to do that.',
					],
				],
			],
		];
	}

	/**
	 * ⛔ 已判定未授權（保留碼）的授權碼，重新檢查遇到暫時性錯誤時不可被暫存成已啟用
	 *
	 * @test
	 * @group security
	 * @dataProvider transient_failures
	 *
	 * @param array{status: int, body: array<string, mixed>}|\WP_Error $failure 暫時性錯誤
	 */
	public function 保留後重新檢查遇到暫時性錯誤時維持未授權( $failure ): void {
		$this->given_saved_code_without_cache();
		$this->respond_unauthorized( 'status_expired' );
		$this->run_system_check();

		$this->expire_cache();
		$this->activate_response = $failure;
		$found = $this->run_system_check();

		$this->assertSame( 2, $this->activate_calls );
		$this->assertSame( 'expired', $found['post_status'], '過期的碼不可因 API 暫時性錯誤被當成已啟用' );
		$this->assertFalse( LCBase::ia( self::PRODUCT_SLUG ) );
		$this->assertSame( self::SAVED_CODE, $this->saved_codes()[ self::PRODUCT_SLUG ] ?? null );
		$this->assertLessThanOrEqual( LCBase::UNAUTHORIZED_RECHECK_TTL, $this->transient_ttl(), '6 小時後再檢查' );
	}

	/**
	 * 對照組：沒有未授權紀錄時，暫時性錯誤維持既有行為（暫存已啟用）
	 *
	 * @test
	 * @group happy
	 */
	public function 沒有未授權紀錄時_暫時性錯誤照舊暫存已啟用(): void {
		$this->given_saved_code_without_cache();
		$this->activate_response = new \WP_Error( 'http_request_failed', 'cURL error 28' );

		$found = $this->run_system_check();

		$this->assertSame( 'activated', $found['post_status'] );
		$this->assertTrue( LCBase::ia( self::PRODUCT_SLUG ) );
	}

	/**
	 * 未授權紀錄只對同一組碼有效
	 *
	 * @test
	 * @group edge
	 */
	public function 未授權紀錄的碼與已存的不同時不套用(): void {
		$this->given_saved_code_without_cache();
		LCBase::mark_unauthorized( self::PRODUCT_SLUG, 'OTHER-CODE-0000-0000', 'status_expired' );
		$this->activate_response = new \WP_Error( 'http_request_failed', 'cURL error 28' );

		$found = $this->run_system_check();

		$this->assertSame( 'activated', $found['post_status'] );
	}

	// ========== 使用者操作 ==========

	/**
	 * @test
	 * @group happy
	 */
	public function 保留碼後手動重新啟用成功即恢復(): void {
		$this->given_saved_code_without_cache();
		$this->respond_unauthorized( 'status_deactivated' );
		$this->run_system_check();

		$this->respond_activated();
		$response = $this->rest_request_as_admin(
			'POST',
			'/v2/powerhouse/lc/activate',
			[
				'code'         => self::SAVED_CODE,
				'product_slug' => self::PRODUCT_SLUG,
			]
		);

		$this->assertSame( 200, $response->get_status() );
		$this->assertTrue( LCBase::ia( self::PRODUCT_SLUG ) );
		$this->assertArrayNotHasKey( self::PRODUCT_SLUG, (array) \get_option( LCBase::UNAUTHORIZED_KEY, [] ) );
	}

	/**
	 * 手動重新啟用仍失敗：回錯誤給操作者，保留碼與未授權狀態不變
	 *
	 * @test
	 * @group error
	 */
	public function 保留碼後手動重新啟用失敗時維持未授權(): void {
		$this->given_saved_code_without_cache();
		$this->respond_unauthorized( 'status_deactivated' );
		$this->run_system_check();

		$response = $this->rest_request_as_admin(
			'POST',
			'/v2/powerhouse/lc/activate',
			[
				'code'         => self::SAVED_CODE,
				'product_slug' => self::PRODUCT_SLUG,
			]
		);

		$this->assertGreaterThanOrEqual( 400, $response->get_status() );
		$this->assertFalse( LCBase::ia( self::PRODUCT_SLUG ) );
		$this->assertSame( self::SAVED_CODE, $this->saved_codes()[ self::PRODUCT_SLUG ] ?? null );
	}

	/**
	 * 棄用：攔截 license-codes/deactivate 並回指定回應，送出 lc/deactivate
	 *
	 * @param int                  $status HTTP status
	 * @param array<string, mixed> $body   回應 body
	 * @return \WP_REST_Response
	 */
	private function deactivate_with( int $status, array $body ): \WP_REST_Response {
		$this->stub_http_request(
			'power-partner-server/license-codes/deactivate',
			[
				'response' => [
					'code'    => $status,
					'message' => '',
				],
				'body'     => (string) \wp_json_encode( $body ),
				'headers'  => [],
			]
		);
		return $this->rest_request_as_admin(
			'POST',
			'/v2/powerhouse/lc/deactivate',
			[
				'code'         => self::SAVED_CODE,
				'product_slug' => self::PRODUCT_SLUG,
			]
		);
	}

	/**
	 * CloudServer 對「不是已啟用」的碼棄用時的實際回應（409）
	 *
	 * @return array<string, mixed>
	 */
	private function not_activated_409_body(): array {
		return [ 'message' => '《' . self::SAVED_CODE . '》 目前不是已啟用狀態，無需棄用' ];
	}

	/**
	 * 使用者主動棄用保留中的碼：CloudServer 回 409（碼不是已啟用），本站仍刪除授權碼（含未授權紀錄）並回成功
	 *
	 * @test
	 * @group happy
	 */
	public function 保留碼後使用者棄用仍刪除授權碼(): void {
		$this->given_saved_code_without_cache();
		$this->respond_unauthorized( 'status_expired' );
		$this->run_system_check();

		$response = $this->deactivate_with( 409, $this->not_activated_409_body() );

		$this->assertSame( 200, $response->get_status(), '本站的碼已移除，對使用者來說棄用已完成' );
		$this->assertSame( 'deactivate_lc_success', $response->get_data()['code'] ?? null );
		$this->assertStringContainsString( '已從本站移除', (string) ( $response->get_data()['message'] ?? '' ) );
		$this->assertStringContainsString( '雲端的綁定未變更', (string) ( $response->get_data()['message'] ?? '' ), '不可讓使用者以為雲端的綁定也解除了' );
		$this->assertArrayNotHasKey( self::PRODUCT_SLUG, $this->saved_codes() );
		$this->assertFalse( \get_transient( 'lc_' . self::PRODUCT_SLUG ) );
		$this->assertArrayNotHasKey( self::PRODUCT_SLUG, (array) \get_option( LCBase::UNAUTHORIZED_KEY, [] ) );
	}

	/**
	 * 本站快取是已啟用、CloudServer 上卻已不是已啟用（例如剛過期、快取還沒到期）→ 409 同樣視為從本站移除成功
	 *
	 * @test
	 * @group happy
	 */
	public function 已啟用的碼棄用收到409時視為從本站移除成功(): void {
		$this->given_activated_code();

		$response = $this->deactivate_with( 409, $this->not_activated_409_body() );

		$this->assertSame( 200, $response->get_status() );
		$this->assertArrayNotHasKey( self::PRODUCT_SLUG, $this->saved_codes() );
		$this->assertFalse( LCBase::ia( self::PRODUCT_SLUG ) );
	}

	/**
	 * 對照組：409 以外的失敗（例如 500）維持既有行為，回錯誤給操作者
	 *
	 * @test
	 * @group error
	 */
	public function 棄用收到409以外的失敗時照舊回錯誤(): void {
		$this->given_activated_code();

		$response = $this->deactivate_with( 500, [ 'message' => 'Internal Server Error' ] );

		$this->assertGreaterThanOrEqual( 400, $response->get_status() );
	}

	/**
	 * 對照組：CloudServer 棄用成功（200）時維持原本的「棄用成功」訊息
	 *
	 * @test
	 * @group happy
	 */
	public function 棄用成功時訊息維持棄用成功(): void {
		$this->given_activated_code();

		$response = $this->deactivate_with( 200, [ 'message' => 'deactivated' ] );

		$this->assertSame( 200, $response->get_status() );
		$this->assertStringContainsString( '棄用成功', (string) ( $response->get_data()['message'] ?? '' ) );
		$this->assertStringNotContainsString( '雲端的綁定未變更', (string) ( $response->get_data()['message'] ?? '' ) );
	}

	// ========== 重新檢查授權（lc/recheck） ==========

	/**
	 * 模擬 CloudServer：碼已被改成「可用」
	 * 系統檢查不綁定 → 401 released；使用者主動啟用 → 綁定到請求的網域（200）
	 */
	private function given_server_code_is_available(): void {
		$this->activate_response = function ( array $params ) {
			if ( ! empty( $params['is_system_check'] ) ) {
				return [
					'status' => 401,
					'body'   => [
						'message' => '《' . self::SAVED_CODE . '》 目前為可用狀態，系統檢查不綁定',
						'reason'  => 'released',
					],
				];
			}
			return [
				'status' => 200,
				'body'   => [
					'id'           => 1,
					'post_status'  => 'activated',
					'code'         => self::SAVED_CODE,
					'type'         => 'standard',
					'expire_date'  => 0,
					'domain'       => 'example.org',
					'product_id'   => 1,
					'product_slug' => self::PRODUCT_SLUG,
					'product_name' => 'Power Course',
				],
			];
		};
	}

	/**
	 * @return \WP_REST_Response
	 */
	private function recheck(): \WP_REST_Response {
		return $this->rest_request_as_admin( 'POST', '/v2/powerhouse/lc/recheck', [ 'product_slug' => self::PRODUCT_SLUG ] );
	}

	/**
	 * @test
	 * @group smoke
	 */
	public function recheck_端點應已被註冊(): void {
		$this->assert_rest_route_registered( '/v2/powerhouse/lc/recheck' );
	}

	/**
	 * ⛔ 保留中的碼已在雲端被改成可用：重新檢查以系統檢查的語意送出 → 不綁定、本站刪碼
	 *
	 * @test
	 * @group security
	 */
	public function recheck_碼已被改成可用時不綁定且本站刪碼(): void {
		$this->given_saved_code_without_cache();
		$this->respond_unauthorized( 'status_expired' );
		$this->run_system_check();

		$this->given_server_code_is_available();
		$response = $this->recheck();

		$this->assertSame( 200, $response->get_status() );
		$this->assertTrue( (bool) ( end( $this->activate_requests )['is_system_check'] ?? false ), '重新檢查必須是系統檢查，不是使用者主動啟用' );
		$this->assertSame( '', $response->get_data()['data']['code'] ?? null );
		$this->assertArrayNotHasKey( self::PRODUCT_SLUG, $this->saved_codes(), '已釋出的碼不可留在本站' );
		$this->assertArrayNotHasKey( self::PRODUCT_SLUG, (array) \get_option( LCBase::UNAUTHORIZED_KEY, [] ) );
		$this->assertFalse( LCBase::ia( self::PRODUCT_SLUG ), '已釋出的碼不可被綁回本站' );
	}

	/**
	 * @test
	 * @group happy
	 */
	public function recheck_CloudServer_判定已恢復時恢復已啟用(): void {
		$this->given_saved_code_without_cache();
		$this->respond_unauthorized( 'status_expired' );
		$this->run_system_check();

		$this->respond_activated();
		$response = $this->recheck();

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( 'activated', $response->get_data()['data']['post_status'] ?? null );
		$this->assertSame( '授權已恢復啟用', $response->get_data()['message'] ?? null );
		$this->assertTrue( LCBase::ia( self::PRODUCT_SLUG ) );
		$this->assertArrayNotHasKey( self::PRODUCT_SLUG, (array) \get_option( LCBase::UNAUTHORIZED_KEY, [] ) );
	}

	/**
	 * @test
	 * @group happy
	 */
	public function recheck_仍然過期時繼續保留(): void {
		$this->given_saved_code_without_cache();
		$this->respond_unauthorized( 'status_expired' );
		$this->run_system_check();

		$response = $this->recheck();

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( 2, $this->activate_calls, '重新檢查要清快取、真的重打一次 activate' );
		$this->assertSame( 'expired', $response->get_data()['data']['post_status'] ?? null );
		$this->assertSame( self::SAVED_CODE, $this->saved_codes()[ self::PRODUCT_SLUG ] ?? null );
		$this->assertFalse( LCBase::ia( self::PRODUCT_SLUG ) );
	}

	/**
	 * @test
	 * @group security
	 */
	public function recheck_需要管理員權限(): void {
		$this->given_saved_code_without_cache();
		$this->respond_unauthorized( 'status_expired' );
		$this->run_system_check();

		$response = $this->rest_request_as_guest( 'POST', '/v2/powerhouse/lc/recheck', [ 'product_slug' => self::PRODUCT_SLUG ] );

		$this->assertContains( $response->get_status(), [ 401, 403 ] );
		$this->assertSame( 1, $this->activate_calls );
	}

	/**
	 * @test
	 * @group edge
	 */
	public function recheck_產品未註冊時回404(): void {
		$this->register_lc_product( self::PRODUCT_SLUG, 'Power Course' );

		$response = $this->rest_request_as_admin( 'POST', '/v2/powerhouse/lc/recheck', [ 'product_slug' => 'not-registered' ] );

		$this->assertSame( 404, $response->get_status() );
	}

	/**
	 * 對照組：手動輸入授權碼仍是使用者主動啟用（is_system_check = false）
	 *
	 * @test
	 * @group happy
	 */
	public function 手動輸入授權碼維持使用者主動啟用(): void {
		$this->register_lc_product( self::PRODUCT_SLUG, 'Power Course' );
		$this->given_server_code_is_available();

		$response = $this->rest_request_as_admin(
			'POST',
			'/v2/powerhouse/lc/activate',
			[
				'code'         => self::SAVED_CODE,
				'product_slug' => self::PRODUCT_SLUG,
			]
		);

		$this->assertSame( 200, $response->get_status() );
		$this->assertFalse( (bool) ( end( $this->activate_requests )['is_system_check'] ?? true ) );
		$this->assertTrue( LCBase::ia( self::PRODUCT_SLUG ) );
	}

	// ========== lc/invalidate 依 status 處理 ==========

	/**
	 * @return array<string, array{0: string}>
	 */
	public function invalidate_routes(): array {
		return [
			'v2 路由' => [ '/v2/powerhouse/lc/invalidate' ],
			'舊路由'  => [ '/powerhouse/lc/invalidate' ],
		];
	}

	/**
	 * @test
	 * @group happy
	 * @dataProvider invalidate_routes
	 *
	 * @param string $route 路由
	 */
	public function invalidate_status_為_available_時刪除授權碼( string $route ): void {
		$this->given_activated_code();

		$response = $this->rest_request_as_guest(
			'POST',
			$route,
			[
				'product_slug' => self::PRODUCT_SLUG,
				'code'         => self::SAVED_CODE,
				'status'       => 'available',
			]
		);

		$this->assertSame( 200, $response->get_status() );
		$this->assertArrayNotHasKey( self::PRODUCT_SLUG, $this->saved_codes() );
		$this->assertFalse( \get_transient( 'lc_' . self::PRODUCT_SLUG ) );
	}

	/**
	 * @return array<string, array{0: string, 1: string}>
	 */
	public function invalidate_retained_statuses(): array {
		return [
			'已過期'         => [ 'expired', 'expired' ],
			'已停用'         => [ 'deactivated', 'deactivated' ],
			'其他非可用狀態' => [ 'draft', 'unauthorized' ],
		];
	}

	/**
	 * @test
	 * @group happy
	 * @dataProvider invalidate_retained_statuses
	 *
	 * @param string $status      invalidate 帶來的授權碼新狀態
	 * @param string $post_status API 暫時性錯誤時預期維持的 post_status
	 */
	public function invalidate_status_非_available_時只清快取並保留授權碼( string $status, string $post_status ): void {
		$this->given_activated_code();

		$response = $this->rest_request_as_guest(
			'POST',
			'/powerhouse/lc/invalidate',
			[
				'product_slug' => self::PRODUCT_SLUG,
				'code'         => self::SAVED_CODE,
				'status'       => $status,
			]
		);

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( self::SAVED_CODE, $this->saved_codes()[ self::PRODUCT_SLUG ] ?? null, '授權碼不可被刪除' );
		$this->assertFalse( \get_transient( 'lc_' . self::PRODUCT_SLUG ), '快取應被清除，強制下次重新檢查' );

		// 下一次系統檢查遇到 API 暫時性錯誤：不可被暫存成已啟用
		$this->activate_response = new \WP_Error( 'http_request_failed', 'cURL error 28' );
		$found                   = $this->run_system_check();
		$this->assertSame( $post_status, $found['post_status'] );
		$this->assertFalse( LCBase::ia( self::PRODUCT_SLUG ) );
	}

	/**
	 * invalidate 保留碼後，CloudServer 判定碼仍有效 → 已啟用
	 *
	 * @test
	 * @group happy
	 */
	public function invalidate_保留碼後重新檢查成功即恢復(): void {
		$this->given_activated_code();
		$this->rest_request_as_guest(
			'POST',
			'/powerhouse/lc/invalidate',
			[
				'product_slug' => self::PRODUCT_SLUG,
				'code'         => self::SAVED_CODE,
				'status'       => 'expired',
			]
		);

		$this->respond_activated();
		$found = $this->run_system_check();

		$this->assertSame( 'activated', $found['post_status'] );
		$this->assertTrue( LCBase::ia( self::PRODUCT_SLUG ) );
		$this->assertArrayNotHasKey( self::PRODUCT_SLUG, (array) \get_option( LCBase::UNAUTHORIZED_KEY, [] ) );
	}

	/**
	 * 碼不符時，帶什麼 status 都只清快取、不記錄未授權
	 *
	 * @test
	 * @group security
	 */
	public function invalidate_碼不符時只清快取且不記錄未授權(): void {
		$this->given_activated_code();

		$response = $this->rest_request_as_guest(
			'POST',
			'/powerhouse/lc/invalidate',
			[
				'product_slug' => self::PRODUCT_SLUG,
				'code'         => 'WRONG-CODE-0000-0000',
				'status'       => 'expired',
			]
		);

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( self::SAVED_CODE, $this->saved_codes()[ self::PRODUCT_SLUG ] ?? null );
		$this->assertFalse( \get_transient( 'lc_' . self::PRODUCT_SLUG ) );
		$this->assertSame( [], (array) \get_option( LCBase::UNAUTHORIZED_KEY, [] ) );
	}

	// ========== lc/refresh ==========

	/**
	 * @return array<string, array{0: string}>
	 */
	public function refresh_routes(): array {
		return [
			'v2 路由' => [ '/v2/powerhouse/lc/refresh' ],
			'舊路由'  => [ '/powerhouse/lc/refresh' ],
		];
	}

	/**
	 * @test
	 * @group smoke
	 * @dataProvider refresh_routes
	 *
	 * @param string $route 路由
	 */
	public function refresh_端點應已被註冊( string $route ): void {
		$this->assert_rest_route_registered( $route );
	}

	/**
	 * @test
	 * @group happy
	 * @dataProvider refresh_routes
	 *
	 * @param string $route 路由
	 */
	public function refresh_碼相符時只清快取_下一次系統檢查恢復已啟用( string $route ): void {
		$this->given_saved_code_without_cache();
		$this->respond_unauthorized( 'status_expired' );
		$this->run_system_check();

		$response = $this->rest_request_as_guest(
			'POST',
			$route,
			[
				'product_slug' => self::PRODUCT_SLUG,
				'code'         => self::SAVED_CODE,
			]
		);

		$this->assertSame( 200, $response->get_status() );
		$this->assertFalse( \get_transient( 'lc_' . self::PRODUCT_SLUG ), '快取應被清除' );
		$this->assertSame( self::SAVED_CODE, $this->saved_codes()[ self::PRODUCT_SLUG ] ?? null );

		$this->respond_activated();
		$found = $this->run_system_check();
		$this->assertSame( 2, $this->activate_calls );
		$this->assertSame( 'activated', $found['post_status'] );
		$this->assertTrue( LCBase::ia( self::PRODUCT_SLUG ) );
	}

	/**
	 * @return array<string, array{0: array<string, mixed>}>
	 */
	public function refresh_rejected_bodies(): array {
		return [
			'碼不符'           => [
				[
					'product_slug' => self::PRODUCT_SLUG,
					'code'         => 'WRONG-CODE-0000-0000',
				],
			],
			'沒帶碼'           => [ [ 'product_slug' => self::PRODUCT_SLUG ] ],
			'沒帶產品'         => [ [ 'code' => self::SAVED_CODE ] ],
			'空 body'          => [ [] ],
			'碼不是字串'       => [
				[
					'product_slug' => self::PRODUCT_SLUG,
					'code'         => [ self::SAVED_CODE ],
				],
			],
			'其他產品的已存碼' => [
				[
					'product_slug' => 'power-shop',
					'code'         => self::SAVED_CODE,
				],
			],
		];
	}

	/**
	 * @test
	 * @group security
	 * @dataProvider refresh_rejected_bodies
	 *
	 * @param array<string, mixed> $body request body
	 */
	public function refresh_碼不符或沒帶時什麼都不做( array $body ): void {
		$this->given_activated_code();
		$before = \get_transient( 'lc_' . self::PRODUCT_SLUG );

		$response = $this->rest_request_as_guest( 'POST', '/powerhouse/lc/refresh', $body );

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( $before, \get_transient( 'lc_' . self::PRODUCT_SLUG ), '快取不可被動到' );
		$this->assertSame( [ self::PRODUCT_SLUG => self::SAVED_CODE ], $this->saved_codes() );
	}

	/**
	 * refresh 的回應不可透露授權碼是否相符：相符與不符的回應完全相同
	 *
	 * @test
	 * @group security
	 */
	public function refresh_碼相符與不符的回應相同(): void {
		$this->given_activated_code();

		$matched  = $this->rest_request_as_guest(
			'POST',
			'/powerhouse/lc/refresh',
			[
				'product_slug' => self::PRODUCT_SLUG,
				'code'         => self::SAVED_CODE,
			]
		);
		$rejected = $this->rest_request_as_guest(
			'POST',
			'/powerhouse/lc/refresh',
			[
				'product_slug' => self::PRODUCT_SLUG,
				'code'         => 'WRONG-CODE-0000-0000',
			]
		);

		$this->assertSame( $matched->get_status(), $rejected->get_status() );
		$this->assertSame( $matched->get_data(), $rejected->get_data() );
	}

	/**
	 * ⛔ refresh 不論任何情況都不刪碼
	 *
	 * @test
	 * @group security
	 */
	public function refresh_從不刪除授權碼(): void {
		$this->given_saved_code_without_cache();
		$this->respond_unauthorized( 'status_deactivated' );
		$this->run_system_check();

		foreach ( [ self::SAVED_CODE, 'WRONG-CODE-0000-0000', '' ] as $code ) {
			$this->rest_request_as_guest(
				'POST',
				'/powerhouse/lc/refresh',
				[
					'product_slug' => self::PRODUCT_SLUG,
					'code'         => $code,
					'status'       => 'available',
				]
			);
			$this->assertSame( self::SAVED_CODE, $this->saved_codes()[ self::PRODUCT_SLUG ] ?? null );
		}
	}

	/**
	 * refresh 不直接把狀態改成已啟用，一律交給下一次 activate：CloudServer 仍判定未授權時維持未授權
	 *
	 * @test
	 * @group security
	 */
	public function refresh_後_CloudServer_仍判定未授權時維持未授權(): void {
		$this->given_saved_code_without_cache();
		$this->respond_unauthorized( 'status_expired' );
		$this->run_system_check();

		$this->rest_request_as_guest(
			'POST',
			'/powerhouse/lc/refresh',
			[
				'product_slug' => self::PRODUCT_SLUG,
				'code'         => self::SAVED_CODE,
			]
		);
		$this->assertFalse( LCBase::ia( self::PRODUCT_SLUG ) );

		$found = $this->run_system_check();
		$this->assertSame( 'expired', $found['post_status'] );
		$this->assertFalse( LCBase::ia( self::PRODUCT_SLUG ) );
	}
}
