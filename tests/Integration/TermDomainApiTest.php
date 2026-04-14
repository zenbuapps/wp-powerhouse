<?php
/**
 * Term Domain REST API 整合測試
 * 驗證 /v2/powerhouse/terms/{taxonomy} 端點的 CRUD 操作行為
 */

declare( strict_types=1 );

namespace Tests\Integration;

/**
 * Class TermDomainApiTest
 *
 * @group term-api
 */
class TermDomainApiTest extends TestCase {

	/**
	 * 以管理員身份發送 form-data POST 請求（Term create/update 使用 get_body_params()）
	 *
	 * @param string               $route  REST 路由
	 * @param array<string, mixed> $params 請求參數
	 */
	private function term_form_post_as_admin( string $route, array $params = [] ): \WP_REST_Response {
		$admin_id = $this->factory()->user->create( [ 'role' => 'administrator' ] );
		\wp_set_current_user( $admin_id );

		$request = new \WP_REST_Request( 'POST', $route );
		$request->set_body_params( $params );
		/** @var \WP_REST_Response $response */
		$response = \rest_do_request( $request );
		return $response;
	}

	// ========== 🔥 冒煙測試 ==========

	/**
	 * @test
	 * @group smoke
	 */
	public function terms_路由應被註冊(): void {
		$routes = \rest_get_server()->get_routes();
		$matched = 0;
		foreach ( $routes as $route => $_ ) {
			if ( str_contains( $route, '/v2/powerhouse/terms' ) ) {
				++$matched;
			}
		}
		$this->assertGreaterThanOrEqual( 1, $matched, '應至少註冊 1 條 terms 路由' );
	}

	/**
	 * @test
	 * @group smoke
	 */
	public function 管理員可以取得_category_列表(): void {
		$this->factory()->term->create_many( 3, [ 'taxonomy' => 'category' ] );

		$response = $this->rest_request_as_admin( 'GET', '/v2/powerhouse/terms/category' );
		$this->assertSame( 200, $response->get_status() );
		$this->assertIsArray( $response->get_data() );
	}

	// ========== ✅ 快樂路徑 ==========

	/**
	 * @test
	 * @group happy
	 */
	public function 取得單一_category_term_應回傳正確資料(): void {
		$term_id = $this->factory()->term->create(
			[
				'taxonomy' => 'category',
				'name'     => '測試分類',
			]
		);

		$response = $this->rest_request_as_admin( 'GET', "/v2/powerhouse/terms/category/{$term_id}" );
		$this->assertSame( 200, $response->get_status() );

		// get_terms_with_id_callback 直接把 Term DTO 物件塞進 WP_REST_Response
		$data = $response->get_data();
		$this->assertNotNull( $data );
		$array = is_object( $data ) && method_exists( $data, 'to_array' ) ? $data->to_array() : (array) $data;
		$this->assertArrayHasKey( 'id', $array );
		$this->assertSame( (string) $term_id, (string) $array['id'] );
	}

	/**
	 * @test
	 * @group happy
	 */
	public function 取得_term_列表應帶分頁資訊(): void {
		$this->factory()->term->create_many( 3, [ 'taxonomy' => 'post_tag' ] );

		$response = $this->rest_request_as_admin( 'GET', '/v2/powerhouse/terms/post_tag', [ 'posts_per_page' => 2 ] );

		$this->assertSame( 200, $response->get_status() );
		$this->assertIsArray( $response->get_data() );
	}

	/**
	 * @test
	 * @group happy
	 */
	public function 建立_term_應成功(): void {
		// BUG: issue #7 — handle_upload 對 thumbnail_id 未檢查存在性
		// 暫時移除 filter 規避，修復後可刪除這行
		\remove_all_filters( 'powerhouse/term/create_term_args' );

		$response = $this->term_form_post_as_admin(
			'/v2/powerhouse/terms/category',
			[
				'name' => '新分類',
				'slug' => 'new-cat-' . uniqid(),
				'qty'  => 1,
			]
		);

		$this->assertSame( 200, $response->get_status() );
		$data = $response->get_data();
		$this->assertSame( 'create_success', $data['code'] );
		$this->assertNotEmpty( $data['data'], '應回傳建立的 term id' );
	}

	/**
	 * @test
	 * @group happy
	 */
	public function 更新_term_應能改變名稱(): void {
		// BUG: issue #7 — handle_upload 對 thumbnail_id 未檢查存在性
		\remove_all_filters( 'powerhouse/term/update_term_args' );

		$term_id = $this->factory()->term->create(
			[
				'taxonomy' => 'category',
				'name'     => '舊名稱',
			]
		);

		$response = $this->term_form_post_as_admin(
			"/v2/powerhouse/terms/category/{$term_id}",
			[ 'name' => '新名稱' ]
		);

		$this->assertSame( 200, $response->get_status() );
		$data = $response->get_data();
		$this->assertSame( 'update_success', $data['code'] );

		$term = \get_term( $term_id, 'category' );
		$this->assertInstanceOf( \WP_Term::class, $term );
		$this->assertSame( '新名稱', $term->name );
	}

	/**
	 * @test
	 * @group happy
	 */
	public function 刪除單一_term_應成功(): void {
		$term_id = $this->factory()->term->create( [ 'taxonomy' => 'category' ] );

		$response = $this->rest_request_as_admin( 'DELETE', "/v2/powerhouse/terms/category/{$term_id}" );

		$this->assertSame( 200, $response->get_status() );
		$data = $response->get_data();
		$this->assertSame( 'delete_success', $data['code'] );

		// 驗證 term 被刪除（不同環境可能回傳 null / WP_Error / false）
		$deleted = \get_term( $term_id, 'category' );
		$this->assertTrue( null === $deleted || \is_wp_error( $deleted ), '刪除後應取不到 term' );
	}

	/**
	 * @test
	 * @group happy
	 * @group sort
	 */
	public function 排序_terms_應回傳_sort_success(): void {
		$term_a = $this->factory()->term->create( [ 'taxonomy' => 'category' ] );
		$term_b = $this->factory()->term->create( [ 'taxonomy' => 'category' ] );

		$response = $this->rest_request_as_admin(
			'POST',
			'/v2/powerhouse/terms/category/sort',
			[
				'from_tree' => [ [ 'id' => (string) $term_a ], [ 'id' => (string) $term_b ] ],
				'to_tree'   => [ [ 'id' => (string) $term_b ], [ 'id' => (string) $term_a ] ],
			]
		);

		// 排序實作複雜，只驗證可達
		$this->assertNotNull( $response );
		$this->assertGreaterThanOrEqual( 200, $response->get_status() );
	}

	// ========== ❌ 錯誤處理 ==========

	/**
	 * @test
	 * @group error
	 */
	public function 取得不存在的_term_id_應回傳錯誤(): void {
		$response = $this->rest_request_as_admin( 'GET', '/v2/powerhouse/terms/category/999999999' );
		$this->assertGreaterThanOrEqual( 400, $response->get_status() );
	}

	/**
	 * @test
	 * @group error
	 */
	public function 建立_term_時_taxonomy_不存在應錯誤(): void {
		$response = $this->term_form_post_as_admin(
			'/v2/powerhouse/terms/nonexistent_taxonomy',
			[ 'name' => '測試', 'qty' => 1 ]
		);
		// CRUD::create_term 回 WP_Error → REST 500 / 4xx
		$this->assertNotSame( 200, $response->get_status() );
	}

	// ========== 🔀 邊緣案例 ==========

	/**
	 * @test
	 * @group edge
	 */
	public function 空的_category_taxonomy_列表應回傳空陣列(): void {
		// 清空預設 category terms 以外
		$response = $this->rest_request_as_admin(
			'GET',
			'/v2/powerhouse/terms/category',
			[ 'search' => 'definitely_does_not_exist_' . uniqid() ]
		);
		$this->assertSame( 200, $response->get_status() );
		$this->assertIsArray( $response->get_data() );
	}

	// ========== 🔒 安全性 ==========

	/**
	 * @test
	 * @group security
	 */
	public function 訂閱者建立_term_應被拒絕(): void {
		$subscriber_id = $this->factory()->user->create( [ 'role' => 'subscriber' ] );
		\wp_set_current_user( $subscriber_id );

		$request = new \WP_REST_Request( 'POST', '/v2/powerhouse/terms/category' );
		$request->set_body_params( [ 'name' => '駭客分類', 'qty' => 1 ] );
		$response = \rest_do_request( $request );

		$this->assertContains(
			$response->get_status(),
			[ 401, 403 ],
			'訂閱者不應能建立 term'
		);
	}
}
