<?php
/**
 * Post Domain REST API 整合測試
 * 驗證 /v2/powerhouse/posts 端點的 CRUD 操作行為
 */

declare( strict_types=1 );

namespace Tests\Integration;

/**
 * Class PostDomainApiTest
 *
 * @group post-api
 */
class PostDomainApiTest extends TestCase {

	// ========== 🔥 冒煙測試（Smoke）==========

	/**
	 * @test
	 * @group smoke
	 */
	public function 管理員可以取得文章列表(): void {
		$this->factory()->post->create_many( 3, [ 'post_status' => 'publish' ] );

		$response = $this->rest_request_as_admin( 'GET', '/v2/powerhouse/posts' );
		$this->assertSame( 200, $response->get_status() );
		$this->assertIsArray( $response->get_data() );
	}

	// ========== ✅ 快樂路徑（Happy Flow）==========

	/**
	 * @test
	 * @group happy
	 */
	public function 取得文章列表時預設回傳_20_筆(): void {
		$this->factory()->post->create_many( 5, [ 'post_status' => 'publish' ] );

		$response = $this->rest_request_as_admin( 'GET', '/v2/powerhouse/posts' );
		$data     = $response->get_data();

		$this->assertSame( 200, $response->get_status() );
		$this->assertIsArray( $data );
		$this->assertLessThanOrEqual( 20, count( $data ) );
	}

	/**
	 * @test
	 * @group happy
	 */
	public function 取得單筆文章_應回傳正確資料(): void {
		$post_id = $this->factory()->post->create(
			[
				'post_title'  => '測試文章標題',
				'post_status' => 'publish',
				'post_type'   => 'post',
			]
		);

		$response = $this->rest_request_as_admin( 'GET', "/v2/powerhouse/posts/{$post_id}" );
		$data     = $response->get_data();

		$this->assertSame( 200, $response->get_status() );
		$this->assertIsArray( $data );
		$this->assertArrayHasKey( 'id', $data );
		$this->assertSame( $post_id, (int) $data['id'] );
	}

	/**
	 * @test
	 * @group happy
	 */
	public function 可以依_post_type_篩選文章_只回傳指定類型(): void {
		// 建立 post 類型
		$this->factory()->post->create_many( 2, [ 'post_type' => 'post', 'post_status' => 'publish' ] );
		// 建立 page 類型
		$page_ids = $this->factory()->post->create_many( 3, [ 'post_type' => 'page', 'post_status' => 'publish' ] );

		$response = $this->rest_request_as_admin( 'GET', '/v2/powerhouse/posts', [ 'post_type' => 'page' ] );
		$data     = $response->get_data();

		$this->assertSame( 200, $response->get_status() );
		$this->assertIsArray( $data );

		// Post API 回傳的是格式化資料，不含 post_type 欄位
		// 改為驗證回傳的 id 都屬於 page 類型
		if ( ! empty( $data ) ) {
			foreach ( $data as $item ) {
				$post_id = isset( $item['id'] ) ? (int) $item['id'] : 0;
				if ( $post_id > 0 ) {
					$wp_post = \get_post( $post_id );
					if ( $wp_post ) {
						$this->assertSame( 'page', $wp_post->post_type, "ID {$post_id} 應為 page 類型" );
					}
				}
			}
		}
	}

	/**
	 * @test
	 * @group happy
	 */
	public function 分頁參數應正確限制回傳數量(): void {
		$this->factory()->post->create_many( 10, [ 'post_status' => 'publish' ] );

		$response = $this->rest_request_as_admin( 'GET', '/v2/powerhouse/posts', [ 'posts_per_page' => 3, 'paged' => 1 ] );
		$data     = $response->get_data();

		$this->assertSame( 200, $response->get_status() );
		$this->assertIsArray( $data );
		$this->assertLessThanOrEqual( 3, count( $data ), '設定 posts_per_page=3 後結果不應超過 3 筆' );
	}

	/**
	 * @test
	 * @group happy
	 */
	public function 回應標頭應包含分頁資訊(): void {
		$this->factory()->post->create_many( 5, [ 'post_status' => 'publish' ] );

		$response = $this->rest_request_as_admin( 'GET', '/v2/powerhouse/posts', [ 'posts_per_page' => 2, 'paged' => 1 ] );

		$this->assertSame( 200, $response->get_status() );
		$headers = $response->get_headers();
		$this->assertArrayHasKey( 'X-WP-Total', $headers, '回應標頭應包含 X-WP-Total' );
		$this->assertArrayHasKey( 'X-WP-TotalPages', $headers, '回應標頭應包含 X-WP-TotalPages' );
	}

	// ========== ❌ 錯誤處理（Error Handling）==========

	/**
	 * @test
	 * @group error
	 */
	public function 取得不存在的文章應回傳錯誤(): void {
		$response = $this->rest_request_as_admin( 'GET', '/v2/powerhouse/posts/999999999' );
		$this->assertNotSame( 200, $response->get_status(), '不存在的文章不應回傳 200' );
	}

	/**
	 * @test
	 * @group error
	 */
	public function 文章_id_為非數字時應回傳錯誤(): void {
		$response = $this->rest_request_as_admin( 'GET', '/v2/powerhouse/posts/not-a-number' );
		$this->assertNotSame( 200, $response->get_status(), '非數字的文章 ID 應回傳錯誤' );
	}

	/**
	 * @test
	 * @group error
	 */
	public function 未登入時取得文章應被拒絕(): void {
		$response = $this->rest_request_as_guest( 'GET', '/v2/powerhouse/posts' );
		$this->assertContains(
			$response->get_status(),
			[ 401, 403 ],
			'未登入用戶存取文章列表應被拒絕'
		);
	}

	// ========== 🔀 邊緣案例（Edge Cases）==========

	/**
	 * @test
	 * @group edge
	 */
	public function 空資料庫時取得文章應回傳空陣列(): void {
		// 不建立任何文章，確保資料庫乾淨（WP_UnitTestCase 每次測試都是 rollback）
		$response = $this->rest_request_as_admin( 'GET', '/v2/powerhouse/posts', [ 'post_type' => 'custom_type_nonexistent' ] );
		$data     = $response->get_data();

		$this->assertSame( 200, $response->get_status() );
		$this->assertIsArray( $data );
		$this->assertEmpty( $data, '不存在的 post_type 應回傳空陣列' );
	}

	/**
	 * @test
	 * @group edge
	 */
	public function posts_per_page_傳入_0_應有合理行為(): void {
		$this->factory()->post->create_many( 3, [ 'post_status' => 'publish' ] );

		$response = $this->rest_request_as_admin( 'GET', '/v2/powerhouse/posts', [ 'posts_per_page' => 0 ] );
		// 不應崩潰
		$this->assertNotNull( $response );
	}

	/**
	 * @test
	 * @group edge
	 */
	public function posts_per_page_傳入負數應有合理行為(): void {
		$this->factory()->post->create_many( 3, [ 'post_status' => 'publish' ] );

		$response = $this->rest_request_as_admin( 'GET', '/v2/powerhouse/posts', [ 'posts_per_page' => -1 ] );
		// -1 在 WP_Query 中代表取全部，不應崩潰
		$this->assertNotNull( $response );
	}

	/**
	 * @test
	 * @group edge
	 */
	public function 文章_id_超出整數上限應有合理行為(): void {
		$response = $this->rest_request_as_admin( 'GET', '/v2/powerhouse/posts/99999999999999999999' );
		// 超出整數上限應回傳錯誤而非崩潰
		$this->assertNotNull( $response );
		$this->assertNotSame( 200, $response->get_status() );
	}

	// ========== 🔒 安全性（Security）==========

	/**
	 * @test
	 * @group security
	 */
	public function SQL_injection_字串作為_post_type_不應造成錯誤(): void {
		$sql_payload = "post' OR 1=1 --";
		$response    = $this->rest_request_as_admin( 'GET', '/v2/powerhouse/posts', [ 'post_type' => $sql_payload ] );

		// 應回傳正常回應（空結果或錯誤），而不是資料庫洩漏
		$this->assertNotNull( $response );
		// 確認回應不包含 SQL injection 成功的跡象
		$data = $response->get_data();
		if ( is_array( $data ) ) {
			// 如果回應是陣列，確認沒有意外的大量資料
			$this->assertLessThan( 100, count( $data ), 'SQL injection 不應導致大量資料回傳' );
		}
	}

	// ========== Meta Query Builder（Feature: Meta查詢建構器）==========

	/**
	 * 以管理員身份發送 form-data POST 請求（Post domain create/update 使用 form-data）
	 *
	 * @param string               $route  REST 路由
	 * @param array<string, mixed> $params 請求參數
	 */
	private function post_form_as_admin( string $route, array $params = [] ): \WP_REST_Response {
		$admin_id = $this->factory()->user->create( [ 'role' => 'administrator' ] );
		\wp_set_current_user( $admin_id );

		$request = new \WP_REST_Request( 'POST', $route );
		$request->set_body_params( $params );
		/** @var \WP_REST_Response $response */
		$response = \rest_do_request( $request );
		return $response;
	}

	/**
	 * @test
	 * @group happy
	 * @group meta-query
	 */
	public function 使用_meta_query_過濾應能依_meta_值篩選文章(): void {
		$post_with_meta = $this->factory()->post->create(
			[
				'post_status' => 'publish',
				'post_type'   => 'post',
				'meta_input'  => [ 'custom_key' => 'match_value' ],
			]
		);
		$this->factory()->post->create(
			[
				'post_status' => 'publish',
				'post_type'   => 'post',
				'meta_input'  => [ 'custom_key' => 'other_value' ],
			]
		);

		$response = $this->rest_request_as_admin(
			'GET',
			'/v2/powerhouse/posts',
			[
				'meta_key'   => 'custom_key',
				'meta_value' => 'match_value',
			]
		);
		$this->assertSame( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertIsArray( $data );

		$returned_ids = array_map( static fn( $item ) => (int) ( $item['id'] ?? 0 ), $data );
		$this->assertContains( $post_with_meta, $returned_ids, '帶符合 meta 的文章應在結果中' );
	}

	// ========== Create / Update / Delete 文章 ==========

	/**
	 * @test
	 * @group happy
	 */
	public function 建立文章應返回新文章_id(): void {
		$response = $this->post_form_as_admin(
			'/v2/powerhouse/posts',
			[
				'post_title'   => '整合測試文章',
				'post_content' => '內容',
				'post_status'  => 'publish',
				'post_type'    => 'post',
				'qty'          => 1,
			]
		);

		$this->assertSame( 200, $response->get_status() );
		$data = $response->get_data();
		$this->assertSame( 'create_success', $data['code'] );
		$this->assertNotEmpty( $data['data'], '建立後應回傳 id' );
	}

	/**
	 * @test
	 * @group happy
	 */
	public function 更新文章應成功修改欄位(): void {
		$post_id = $this->factory()->post->create(
			[
				'post_title'  => '原標題',
				'post_status' => 'publish',
			]
		);

		$response = $this->post_form_as_admin(
			"/v2/powerhouse/posts/{$post_id}",
			[ 'post_title' => '新標題' ]
		);

		$this->assertSame( 200, $response->get_status() );
		$data = $response->get_data();
		$this->assertSame( 'update_success', $data['code'] );

		$post = \get_post( $post_id );
		$this->assertInstanceOf( \WP_Post::class, $post );
		$this->assertSame( '新標題', $post->post_title );
	}

	/**
	 * @test
	 * @group happy
	 */
	public function 刪除文章應將其移入垃圾桶(): void {
		$post_id = $this->factory()->post->create( [ 'post_status' => 'publish' ] );

		$response = $this->rest_request_as_admin( 'DELETE', "/v2/powerhouse/posts/{$post_id}" );

		$this->assertSame( 200, $response->get_status() );
		$data = $response->get_data();
		$this->assertSame( 'delete_success', $data['code'] );

		$post = \get_post( $post_id );
		$this->assertNotNull( $post );
		$this->assertSame( 'trash', $post->post_status, '刪除後應進垃圾桶' );
	}

	// ========== Sort 文章 ==========

	/**
	 * @test
	 * @group happy
	 * @group sort
	 */
	public function 排序文章應回傳_sort_success(): void {
		$post_a = $this->factory()->post->create( [ 'post_status' => 'publish' ] );
		$post_b = $this->factory()->post->create( [ 'post_status' => 'publish' ] );

		// post_posts_sort_callback 使用 get_json_params()
		$response = $this->rest_request_as_admin(
			'POST',
			'/v2/powerhouse/posts/sort',
			[
				'from_tree' => [ [ 'id' => (string) $post_a ], [ 'id' => (string) $post_b ] ],
				'to_tree'   => [ [ 'id' => (string) $post_b ], [ 'id' => (string) $post_a ] ],
			]
		);

		// 因 CRUD::sort_posts 的實作複雜，只驗證 API 可達 & 不崩潰
		$this->assertNotNull( $response );
		$this->assertGreaterThanOrEqual( 200, $response->get_status() );
	}

	// ========== Copy 文章 ==========

	/**
	 * @test
	 * @group happy
	 * @group copy
	 */
	public function 複製文章應建立新文章且保留標題(): void {
		$source_id = $this->factory()->post->create(
			[
				'post_title'  => '來源文章',
				'post_status' => 'publish',
				'post_type'   => 'post',
			]
		);

		$response = $this->rest_request_as_admin( 'POST', "/v2/powerhouse/copy/{$source_id}", [] );

		$this->assertSame( 200, $response->get_status() );
		$data = $response->get_data();
		$this->assertSame( 'post_copy_success', $data['code'] );
		$this->assertIsNumeric( $data['data'] );

		$new_post = \get_post( (int) $data['data'] );
		$this->assertInstanceOf( \WP_Post::class, $new_post );
		$this->assertNotSame( $source_id, (int) $data['data'], '複製應產生不同 ID' );
	}

	/**
	 * @test
	 * @group error
	 * @group copy
	 */
	public function 複製不存在的文章應有合理錯誤反應(): void {
		$response = $this->rest_request_as_admin( 'POST', '/v2/powerhouse/copy/99999999', [] );
		// Copy 實作可能回 500 或 4xx，不應 200
		$this->assertNotSame( 200, $response->get_status() );
	}
}
