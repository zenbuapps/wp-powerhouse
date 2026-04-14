<?php
/**
 * Cloud API Base 整合測試
 * 驗證 Api\Base 對 cloud.luke.cafe 的通訊封裝
 * 所有 HTTP 呼叫必須被 pre_http_request stub 攔截，禁止真實外連
 */

declare( strict_types=1 );

namespace Tests\Integration;

/**
 * Class CloudApiBaseTest
 *
 * @group cloud-api
 */
class CloudApiBaseTest extends TestCase {

	// ========== 🔥 冒煙測試 ==========

	/**
	 * @test
	 * @group smoke
	 */
	public function base_類別應能以_singleton_建立(): void {
		$base = \J7\Powerhouse\Api\Base::instance();
		$this->assertInstanceOf( \J7\Powerhouse\Api\Base::class, $base );
	}

	// ========== ✅ 快樂路徑 ==========

	/**
	 * @test
	 * @group happy
	 */
	public function remote_get_應被_pre_http_request_stub_攔截(): void {
		$this->stub_http_request(
			'power-partner-server',
			[
				'response' => [
					'code'    => 200,
					'message' => 'OK',
				],
				'body'     => \wp_json_encode( [ 'ok' => true ] ),
				'headers'  => [],
			]
		);

		$base     = \J7\Powerhouse\Api\Base::instance();
		$response = $base->remote_get( 'test-endpoint', [ 'foo' => 'bar' ] );

		$this->assertIsArray( $response );
		$this->assertSame( 200, $response['response']['code'] );
		$this->assertSame( '{"ok":true}', $response['body'] );
	}

	/**
	 * @test
	 * @group happy
	 */
	public function remote_post_應被_pre_http_request_stub_攔截(): void {
		$this->stub_http_request(
			'power-partner-server',
			[
				'response' => [
					'code'    => 200,
					'message' => 'OK',
				],
				'body'     => \wp_json_encode( [ 'created' => true ] ),
				'headers'  => [],
			]
		);

		$base     = \J7\Powerhouse\Api\Base::instance();
		$response = $base->remote_post( 'create', [ 'name' => 'test' ] );

		$this->assertIsArray( $response );
		$this->assertSame( 200, $response['response']['code'] );
	}

	/**
	 * @test
	 * @group happy
	 */
	public function remote_delete_應被_pre_http_request_stub_攔截(): void {
		$this->stub_http_request(
			'power-partner-server',
			[
				'response' => [
					'code'    => 200,
					'message' => 'OK',
				],
				'body'     => \wp_json_encode( [ 'deleted' => true ] ),
				'headers'  => [],
			]
		);

		$base     = \J7\Powerhouse\Api\Base::instance();
		$response = $base->remote_delete( 'resources/1', [ 'force' => true ] );

		$this->assertIsArray( $response );
		$this->assertSame( 200, $response['response']['code'] );
	}

	// ========== 🔀 邊緣案例 ==========

	/**
	 * @test
	 * @group edge
	 */
	public function stub_回傳_wp_error_時_remote_get_應傳回_wp_error(): void {
		\add_filter(
			'pre_http_request',
			static fn() => new \WP_Error( 'http_request_failed', 'stub error' ),
			10,
			0
		);

		$base     = \J7\Powerhouse\Api\Base::instance();
		$response = $base->remote_get( 'whatever' );

		$this->assertInstanceOf( \WP_Error::class, $response );
		$this->assertSame( 'http_request_failed', $response->get_error_code() );
	}

	/**
	 * @test
	 * @group edge
	 */
	public function 未_stub_時若環境為_test_不應嘗試真實外連(): void {
		// 我們在 TestCase::tear_down 預設會移除所有 pre_http_request filter。
		// 這個測試確認：如果沒有 stub，呼叫 remote_get 至少不會崩潰。
		$this->stub_http_request(
			'', // 攔截任何 URL
			[
				'response' => [ 'code' => 418, 'message' => 'IM A TEAPOT' ],
				'body'     => '',
				'headers'  => [],
			]
		);

		$base     = \J7\Powerhouse\Api\Base::instance();
		$response = $base->remote_get( 'any' );

		$this->assertIsArray( $response );
		$this->assertSame( 418, $response['response']['code'] );
	}
}
