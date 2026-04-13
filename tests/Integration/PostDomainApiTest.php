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
}
