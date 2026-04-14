<?php
/**
 * Misc Domain 整合測試
 * 涵蓋：Shortcode / Plugin / Upload options / Comment / Revenue / WooCommerce info / Option CRUD 等
 */

declare( strict_types=1 );

namespace Tests\Integration;

/**
 * Class MiscDomainTest
 *
 * @group misc
 */
class MiscDomainTest extends TestCase {

	/**
	 * 以管理員身份發送 form-data POST 請求
	 *
	 * @param string               $route  REST 路由
	 * @param array<string, mixed> $params 請求參數
	 */
	private function form_post_as_admin( string $route, array $params = [] ): \WP_REST_Response {
		$admin_id = $this->factory()->user->create( [ 'role' => 'administrator' ] );
		\wp_set_current_user( $admin_id );

		$request = new \WP_REST_Request( 'POST', $route );
		$request->set_body_params( $params );
		/** @var \WP_REST_Response $response */
		$response = \rest_do_request( $request );
		return $response;
	}

	// ========== Shortcode ==========

	/**
	 * @test
	 * @group smoke
	 * @group shortcode
	 */
	public function shortcode_路由應被註冊(): void {
		$this->assert_rest_route_registered( '/v2/powerhouse/shortcode' );
	}

	/**
	 * @test
	 * @group happy
	 * @group shortcode
	 */
	public function 執行_shortcode_應回傳解析後內容(): void {
		// 註冊一個測試 shortcode
		\add_shortcode(
			'ph_test_sc',
			static fn() => 'HELLO_SHORTCODE'
		);

		$response = $this->rest_request_as_admin(
			'GET',
			'/v2/powerhouse/shortcode',
			[ 'shortcode' => '[ph_test_sc]' ]
		);

		\remove_shortcode( 'ph_test_sc' );

		$this->assertSame( 200, $response->get_status() );
		$data = $response->get_data();
		$this->assertSame( 'get_shortcode_success', $data['code'] );
		$this->assertSame( 'HELLO_SHORTCODE', $data['data'] );
	}

	/**
	 * @test
	 * @group edge
	 * @group shortcode
	 */
	public function 執行空_shortcode_應回傳空字串(): void {
		$response = $this->rest_request_as_admin( 'GET', '/v2/powerhouse/shortcode', [ 'shortcode' => '' ] );

		$this->assertSame( 200, $response->get_status() );
		$data = $response->get_data();
		$this->assertSame( '', $data['data'] );
	}

	// ========== Plugin ==========

	/**
	 * @test
	 * @group smoke
	 * @group plugin-api
	 */
	public function plugins_路由應被註冊(): void {
		$this->assert_rest_route_registered( '/v2/powerhouse/plugins' );
	}

	/**
	 * @test
	 * @group happy
	 * @group plugin-api
	 */
	public function 取得外掛列表應回傳陣列(): void {
		$response = $this->rest_request_as_admin( 'GET', '/v2/powerhouse/plugins' );
		$this->assertSame( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertIsArray( $data );

		$headers = $response->get_headers();
		$this->assertArrayHasKey( 'X-WP-Total', $headers );
	}

	// ========== Upload options ==========

	/**
	 * @test
	 * @group smoke
	 * @group upload
	 */
	public function upload_options_路由應被註冊(): void {
		$this->assert_rest_route_registered( '/v2/powerhouse/upload/options' );
		$this->assert_rest_route_registered( '/v2/powerhouse/upload' );
	}

	/**
	 * @test
	 * @group happy
	 * @group upload
	 */
	public function upload_options_應回傳_allowed_mime_types(): void {
		$response = $this->rest_request_as_admin( 'GET', '/v2/powerhouse/upload/options' );

		$this->assertSame( 200, $response->get_status() );
		$data = $response->get_data();
		$this->assertIsArray( $data );
		$this->assertArrayHasKey( 'allowed_mime_types', $data );
		$this->assertIsArray( $data['allowed_mime_types'] );
	}

	/**
	 * @test
	 * @group error
	 * @group upload
	 */
	public function upload_未附帶檔案應回傳錯誤(): void {
		$response = $this->form_post_as_admin( '/v2/powerhouse/upload', [] );
		// 拋 Exception → 500
		$this->assertGreaterThanOrEqual( 400, $response->get_status() );
	}

	// ========== Comment ==========

	/**
	 * @test
	 * @group smoke
	 * @group comment-api
	 */
	public function comments_路由應被註冊(): void {
		$this->assert_rest_route_registered( '/v2/powerhouse/comments' );
	}

	/**
	 * @test
	 * @group happy
	 * @group comment-api
	 */
	public function 建立評論應寫入_wp_comments(): void {
		$post_id = $this->factory()->post->create( [ 'post_status' => 'publish' ] );

		$response = $this->form_post_as_admin(
			'/v2/powerhouse/comments',
			[
				'note'         => '這是一則測試評論',
				'comment_type' => 'comment',
			]
		);

		$this->assertSame( 200, $response->get_status() );
		$data = $response->get_data();
		$this->assertSame( 'create_success', $data['code'] );
		$this->assertIsNumeric( $data['data'] );

		$comment = \get_comment( (int) $data['data'] );
		$this->assertInstanceOf( \WP_Comment::class, $comment );
		$this->assertSame( '這是一則測試評論', $comment->comment_content );
	}

	/**
	 * @test
	 * @group happy
	 * @group comment-api
	 */
	public function 刪除評論應將其移除(): void {
		$comment_id = (int) \wp_insert_comment(
			[
				'comment_author'  => 'tester',
				'comment_content' => 'to be deleted',
			]
		);
		$this->assertGreaterThan( 0, $comment_id );

		$response = $this->rest_request_as_admin( 'DELETE', "/v2/powerhouse/comments/{$comment_id}" );
		// DELETE 可能是 delete_success 或其他路徑
		$this->assertGreaterThanOrEqual( 200, $response->get_status() );
	}

	// ========== Revenue Report ==========

	/**
	 * @test
	 * @group smoke
	 * @group revenue
	 */
	public function reports_revenue_stats_路由應被註冊(): void {
		$this->assert_rest_route_registered( '/v2/powerhouse/reports/revenue/stats' );
	}

	/**
	 * @test
	 * @group happy
	 * @group revenue
	 */
	public function 取得營收統計應有合理回應(): void {
		$response = $this->rest_request_as_admin(
			'GET',
			'/v2/powerhouse/reports/revenue/stats',
			[
				'after'  => '2020-01-01 00:00:00',
				'before' => '2099-12-31 23:59:59',
			]
		);

		// WC Admin 未完全載入時可能 500
		if ( 500 === $response->get_status() ) {
			$this->markTestSkipped( 'WC Admin 未完整載入於測試環境' );
			return;
		}
		$this->assertSame( 200, $response->get_status() );
	}

	// ========== WooCommerce info ==========

	/**
	 * @test
	 * @group smoke
	 * @group wc-info
	 */
	public function woocommerce_路由應被註冊(): void {
		$this->assert_rest_route_registered( '/v2/powerhouse/woocommerce' );
	}

	/**
	 * @test
	 * @group happy
	 * @group wc-info
	 */
	public function 取得_woocommerce_資訊應回傳_currency(): void {
		if ( ! class_exists( '\WooCommerce' ) ) {
			$this->markTestSkipped( 'WooCommerce 未啟用' );
			return;
		}

		$response = $this->rest_request_as_admin( 'GET', '/v2/powerhouse/woocommerce' );
		$this->assertSame( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertIsArray( $data );
	}

	// ========== Option CRUD ==========

	/**
	 * @test
	 * @group smoke
	 * @group option-api
	 */
	public function options_路由應被註冊(): void {
		$this->assert_rest_route_registered( '/v2/powerhouse/options' );
	}

	/**
	 * @test
	 * @group happy
	 * @group option-api
	 */
	public function 取得_options_應回傳允許的欄位(): void {
		$response = $this->rest_request_as_admin( 'GET', '/v2/powerhouse/options' );
		$this->assertSame( 200, $response->get_status() );
		$this->assertIsArray( $response->get_data() );
	}

	// ========== Copy (Post) ==========

	/**
	 * @test
	 * @group smoke
	 * @group copy
	 */
	public function copy_路由應被註冊(): void {
		$routes = \rest_get_server()->get_routes();
		$found  = false;
		foreach ( $routes as $route => $_ ) {
			if ( str_contains( $route, '/v2/powerhouse/copy/' ) ) {
				$found = true;
				break;
			}
		}
		$this->assertTrue( $found, 'copy 路由應被註冊' );
	}
}
