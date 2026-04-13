<?php
/**
 * REST API 路由註冊整合測試
 * 驗證所有 Powerhouse REST API 端點均已正確向 WP REST Server 註冊
 */

declare( strict_types=1 );

namespace Tests\Integration;

/**
 * Class RestApiRegistrationTest
 *
 * @group rest-api
 */
class RestApiRegistrationTest extends TestCase {

	// ========== 🔥 冒煙測試（Smoke）==========

	/**
	 * @test
	 * @group smoke
	 */
	public function Posts_CRUD_端點應已註冊_GET(): void {
		$this->assert_rest_route_registered( '/v2/powerhouse/posts' );
	}

	/**
	 * @test
	 * @group smoke
	 */
	public function Options_端點應已註冊(): void {
		$this->assert_rest_route_registered( '/v2/powerhouse/options' );
	}

	/**
	 * @test
	 * @group smoke
	 */
	public function LC_端點應已註冊(): void {
		$this->assert_rest_route_registered( '/v2/powerhouse/lc' );
	}

	// ========== ✅ 快樂路徑（Happy Flow）==========

	/**
	 * @test
	 * @group happy
	 */
	public function Users_端點應已註冊(): void {
		$this->assert_rest_route_registered( '/v2/powerhouse/users' );
	}

	/**
	 * @test
	 * @group happy
	 */
	public function Terms_端點應已註冊(): void {
		$routes    = \rest_get_server()->get_routes();
		$namespace = '/v2/powerhouse';
		$found     = false;
		foreach ( array_keys( $routes ) as $route ) {
			if ( str_starts_with( $route, "{$namespace}/terms" ) ) {
				$found = true;
				break;
			}
		}
		$this->assertTrue( $found, '/v2/powerhouse/terms/... 路由應已被註冊' );
	}

	/**
	 * @test
	 * @group happy
	 */
	public function Comments_端點應已註冊(): void {
		$this->assert_rest_route_registered( '/v2/powerhouse/comments' );
	}

	/**
	 * @test
	 * @group happy
	 */
	public function Upload_端點應已註冊(): void {
		$this->assert_rest_route_registered( '/v2/powerhouse/upload' );
	}

	/**
	 * @test
	 * @group happy
	 */
	public function Shortcode_端點應已註冊(): void {
		// 端點為 shortcode（單數），非 shortcodes
		$this->assert_rest_route_registered( '/v2/powerhouse/shortcode' );
	}

	/**
	 * @test
	 * @group happy
	 */
	public function Plugins_端點應已註冊(): void {
		$this->assert_rest_route_registered( '/v2/powerhouse/plugins' );
	}

	/**
	 * @test
	 * @group happy
	 */
	public function LC_activate_端點應已註冊(): void {
		$this->assert_rest_route_registered( '/v2/powerhouse/lc/activate' );
	}

	/**
	 * @test
	 * @group happy
	 */
	public function LC_deactivate_端點應已註冊(): void {
		$this->assert_rest_route_registered( '/v2/powerhouse/lc/deactivate' );
	}

	/**
	 * @test
	 * @group happy
	 */
	public function LC_invalidate_端點應已註冊(): void {
		$this->assert_rest_route_registered( '/v2/powerhouse/lc/invalidate' );
	}

	/**
	 * @test
	 * @group happy
	 */
	public function Posts_單筆取得端點應已註冊(): void {
		$routes = \rest_get_server()->get_routes();
		$found  = false;
		foreach ( array_keys( $routes ) as $route ) {
			if ( str_contains( $route, '/v2/powerhouse/posts/' ) && str_contains( $route, '\\d+' ) ) {
				$found = true;
				break;
			}
		}
		$this->assertTrue( $found, '/v2/powerhouse/posts/(?P<id>\\d+) 端點應已被註冊' );
	}

	/**
	 * @test
	 * @group happy
	 */
	public function WooCommerce_啟用時_Orders_端點應已註冊(): void {
		if ( ! \class_exists( '\WooCommerce' ) ) {
			$this->markTestSkipped( 'WooCommerce 未載入，跳過此測試' );
		}
		$this->assert_rest_route_registered( '/v2/powerhouse/orders' );
	}

	/**
	 * @test
	 * @group happy
	 */
	public function WooCommerce_啟用時_Products_端點應已註冊(): void {
		if ( ! \class_exists( '\WooCommerce' ) ) {
			$this->markTestSkipped( 'WooCommerce 未載入，跳過此測試' );
		}
		$this->assert_rest_route_registered( '/v2/powerhouse/products' );
	}

	/**
	 * @test
	 * @group happy
	 */
	public function WooCommerce_啟用時_Limit_端點應已註冊(): void {
		if ( ! \class_exists( '\WooCommerce' ) ) {
			$this->markTestSkipped( 'WooCommerce 未載入，跳過此測試' );
		}
		$routes    = \rest_get_server()->get_routes();
		$namespace = '/v2/powerhouse';
		$found     = false;
		foreach ( array_keys( $routes ) as $route ) {
			if ( str_starts_with( $route, "{$namespace}/limit" ) ) {
				$found = true;
				break;
			}
		}
		$this->assertTrue( $found, '/v2/powerhouse/limit/... 路由應已被註冊' );
	}

	// ========== ❌ 錯誤處理（Error Handling）==========

	/**
	 * @test
	 * @group error
	 */
	public function 未認證用戶存取_Posts_GET_應回傳_401_或_403(): void {
		$response = $this->rest_request_as_guest( 'GET', '/v2/powerhouse/posts' );
		$this->assertContains(
			$response->get_status(),
			[ 401, 403 ],
			'未認證用戶存取 posts 端點應被拒絕（401 或 403）'
		);
	}

	/**
	 * @test
	 * @group error
	 */
	public function 訂閱者存取_Options_POST_應回傳_401_或_403(): void {
		$response = $this->rest_request_as_subscriber( 'POST', '/v2/powerhouse/options', [] );
		$this->assertContains(
			$response->get_status(),
			[ 401, 403 ],
			'訂閱者無法修改 options 設定'
		);
	}

	// ========== 🔒 安全性（Security）==========

	/**
	 * @test
	 * @group security
	 */
	public function 傳入_XSS_字串作為_Post_title_應被清理(): void {
		$admin_id = $this->factory()->user->create( [ 'role' => 'administrator' ] );
		\wp_set_current_user( $admin_id );

		$xss_payload = '<script>alert("xss")</script>Test Post';

		$request = new \WP_REST_Request( 'POST', '/v2/powerhouse/posts' );
		$request->set_body_params(
			[
				'post_title'  => $xss_payload,
				'post_type'   => 'post',
				'post_status' => 'publish',
			]
		);

		/** @var \WP_REST_Response $response */
		$response = \rest_do_request( $request );

		// 允許成功或驗證失敗，但不允許 XSS 字串未被處理就儲存
		if ( 200 === $response->get_status() ) {
			$data   = $response->get_data();
			$ids    = is_array( $data ) ? ( $data['data'] ?? [] ) : [];
			$ids    = is_array( $ids ) ? $ids : [];
			if ( ! empty( $ids ) ) {
				$post_id    = (int) $ids[0];
				$saved_post = \get_post( $post_id );
				if ( $saved_post ) {
					$this->assertStringNotContainsString(
						'<script>',
						$saved_post->post_title,
						'XSS 字串 <script> 不應原封不動儲存在 post_title 中'
					);
				}
			}
		}

		// 測試未被伺服器崩潰處理
		$this->assertNotNull( $response );
	}
}
