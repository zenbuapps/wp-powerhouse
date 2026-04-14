<?php
/**
 * ProductAttribute Domain REST API 整合測試
 * 驗證 /v2/powerhouse/product-attributes 端點的 CRUD 操作行為
 */

declare( strict_types=1 );

namespace Tests\Integration;

/**
 * Class ProductAttributeTest
 *
 * @group product-attribute-api
 */
class ProductAttributeTest extends TestCase {

	/** 確認 WooCommerce 已載入，否則整組 skip */
	public function set_up(): void {
		parent::set_up();
		if ( ! class_exists( '\WooCommerce' ) ) {
			$this->markTestSkipped( 'WooCommerce 未啟用' );
		}
	}

	/**
	 * 以管理員身份發送 form-data POST 請求
	 *
	 * @param string               $route  REST 路由
	 * @param array<string, mixed> $params 請求參數
	 */
	private function attr_form_post_as_admin( string $route, array $params = [] ): \WP_REST_Response {
		$admin_id = $this->factory()->user->create( [ 'role' => 'administrator' ] );
		\wp_set_current_user( $admin_id );

		$request = new \WP_REST_Request( 'POST', $route );
		$request->set_body_params( $params );
		/** @var \WP_REST_Response $response */
		$response = \rest_do_request( $request );
		return $response;
	}

	/**
	 * 清除 WC attribute taxonomies 快取，避免測試間污染
	 */
	private function flush_attribute_cache(): void {
		\delete_transient( 'wc_attribute_taxonomies' );
	}

	// ========== 🔥 冒煙測試 ==========

	/**
	 * @test
	 * @group smoke
	 */
	public function product_attributes_路由應被註冊(): void {
		$this->assert_rest_route_registered( '/v2/powerhouse/product-attributes' );
	}

	/**
	 * @test
	 * @group smoke
	 */
	public function 管理員可以取得商品屬性列表(): void {
		$response = $this->rest_request_as_admin( 'GET', '/v2/powerhouse/product-attributes' );
		$this->assertSame( 200, $response->get_status() );
		$this->assertIsArray( $response->get_data() );
	}

	// ========== ✅ 快樂路徑 ==========

	/**
	 * @test
	 * @group happy
	 */
	public function 建立商品屬性應回傳_create_success(): void {
		$this->flush_attribute_cache();

		$response = $this->attr_form_post_as_admin(
			'/v2/powerhouse/product-attributes',
			[
				'name'         => '顏色',
				'slug'         => 'pa_color_' . substr( md5( (string) mt_rand() ), 0, 5 ),
				'type'         => 'select',
				'order_by'     => 'menu_order',
				'has_archives' => false,
			]
		);

		$this->assertSame( 200, $response->get_status() );
		$data = $response->get_data();
		$this->assertSame( 'create_success', $data['code'] );
		$this->assertIsNumeric( $data['data'] );
	}

	/**
	 * @test
	 * @group happy
	 */
	public function 取得列表應帶分頁標頭(): void {
		$response = $this->rest_request_as_admin( 'GET', '/v2/powerhouse/product-attributes' );
		$this->assertSame( 200, $response->get_status() );

		$headers = $response->get_headers();
		$this->assertArrayHasKey( 'X-WP-Total', $headers );
		$this->assertArrayHasKey( 'X-WP-TotalPages', $headers );
	}

	/**
	 * @test
	 * @group happy
	 */
	public function 更新商品屬性應回傳_update_success(): void {
		$this->flush_attribute_cache();

		$attr_id = \wc_create_attribute(
			[
				'name'         => '尺寸',
				'slug'         => 'pa_size_' . substr( md5( (string) mt_rand() ), 0, 5 ),
				'type'         => 'select',
				'order_by'     => 'menu_order',
				'has_archives' => false,
			]
		);
		$this->assertIsInt( $attr_id, 'wc_create_attribute 應回傳屬性 id' );

		$response = $this->attr_form_post_as_admin(
			"/v2/powerhouse/product-attributes/{$attr_id}",
			[ 'name' => '尺寸改' ]
		);

		$this->assertSame( 200, $response->get_status() );
		$data = $response->get_data();
		$this->assertSame( 'update_success', $data['code'] );
	}

	/**
	 * @test
	 * @group happy
	 */
	public function 刪除商品屬性應成功(): void {
		$this->flush_attribute_cache();

		$attr_id = \wc_create_attribute(
			[
				'name'         => '材質',
				'slug'         => 'pa_material_' . substr( md5( (string) mt_rand() ), 0, 5 ),
				'type'         => 'select',
				'order_by'     => 'menu_order',
				'has_archives' => false,
			]
		);
		$this->assertIsInt( $attr_id );

		$response = $this->rest_request_as_admin( 'DELETE', "/v2/powerhouse/product-attributes/{$attr_id}" );
		$this->assertSame( 200, $response->get_status() );
		$data = $response->get_data();
		$this->assertSame( 'delete_success', $data['code'] );
	}

	// ========== ❌ 錯誤處理 ==========

	/**
	 * @test
	 * @group error
	 */
	public function 建立商品屬性缺少必填欄位應回傳錯誤(): void {
		$response = $this->attr_form_post_as_admin(
			'/v2/powerhouse/product-attributes',
			[] // 缺少 name / slug
		);
		// include_required_params 會拋 Exception → 500
		$this->assertGreaterThanOrEqual( 400, $response->get_status() );
	}

	/**
	 * @test
	 * @group error
	 */
	public function 刪除不存在的商品屬性應回傳錯誤(): void {
		$response = $this->rest_request_as_admin( 'DELETE', '/v2/powerhouse/product-attributes/999999999' );
		$this->assertGreaterThanOrEqual( 400, $response->get_status() );
	}

	// ========== 🔒 安全性 ==========

	/**
	 * @test
	 * @group security
	 */
	public function 訂閱者不應能建立商品屬性(): void {
		$subscriber_id = $this->factory()->user->create( [ 'role' => 'subscriber' ] );
		\wp_set_current_user( $subscriber_id );

		$request = new \WP_REST_Request( 'POST', '/v2/powerhouse/product-attributes' );
		$request->set_body_params(
			[
				'name' => '駭客屬性',
				'slug' => 'pa_hack',
			]
		);
		$response = \rest_do_request( $request );

		$this->assertContains( $response->get_status(), [ 401, 403 ] );
	}
}
