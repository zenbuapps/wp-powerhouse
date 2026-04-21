<?php
/**
 * User Domain REST API 整合測試
 * 驗證 /v2/powerhouse/users 端點的 CRUD 操作行為
 */

declare( strict_types=1 );

namespace Tests\Integration;

/**
 * Class UserDomainApiTest
 *
 * @group user-api
 */
class UserDomainApiTest extends TestCase {

	/**
	 * 以管理員身份發送 form-data POST 請求
	 * User domain 的 create/update 使用 get_body_params()（form-data）
	 *
	 * @param string               $route  REST 路由
	 * @param array<string, mixed> $params 請求參數
	 */
	private function user_form_post_as_admin( string $route, array $params = [] ): \WP_REST_Response {
		$admin_id = $this->factory()->user->create( [ 'role' => 'administrator' ] );
		\wp_set_current_user( $admin_id );

		$request = new \WP_REST_Request( 'POST', $route );
		$request->set_body_params( $params );
		/** @var \WP_REST_Response $response */
		$response = \rest_do_request( $request );
		return $response;
	}

	// ========== 🔥 冒煙測試（Smoke）==========

	/**
	 * @test
	 * @group smoke
	 */
	public function users_路由應被註冊(): void {
		$this->assert_rest_route_registered( '/v2/powerhouse/users' );
		$this->assert_rest_route_registered( '/v2/powerhouse/users/options' );
		$this->assert_rest_route_registered( '/v2/powerhouse/users/resetpassword' );
	}

	/**
	 * @test
	 * @group smoke
	 */
	public function 管理員可以取得用戶列表(): void {
		$this->factory()->user->create_many( 3, [ 'role' => 'subscriber' ] );

		$response = $this->rest_request_as_admin( 'GET', '/v2/powerhouse/users' );
		$this->assertSame( 200, $response->get_status() );
		$this->assertIsArray( $response->get_data() );
	}

	// ========== ✅ 快樂路徑（Happy Flow）==========

	/**
	 * @test
	 * @group happy
	 */
	public function 取得單筆用戶應回傳正確資料(): void {
		$user_id = $this->factory()->user->create(
			[
				'user_login' => 'phtester01',
				'role'       => 'subscriber',
				'user_email' => 'phtester01@example.com',
			]
		);

		$response = $this->rest_request_as_admin( 'GET', "/v2/powerhouse/users/{$user_id}" );

		$this->assertSame( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertIsArray( $data );
		$this->assertArrayHasKey( 'id', $data );
		$this->assertSame( $user_id, (int) $data['id'] );
	}

	/**
	 * @test
	 * @group happy
	 */
	public function 取得用戶列表應帶分頁標頭(): void {
		$this->factory()->user->create_many( 4, [ 'role' => 'subscriber' ] );

		$response = $this->rest_request_as_admin( 'GET', '/v2/powerhouse/users', [ 'number' => 2 ] );
		$this->assertSame( 200, $response->get_status() );

		$headers = $response->get_headers();
		$this->assertArrayHasKey( 'X-WP-Total', $headers );
		$this->assertArrayHasKey( 'X-WP-TotalPages', $headers );
		$this->assertArrayHasKey( 'X-WP-CurrentPage', $headers );
		$this->assertArrayHasKey( 'X-WP-PageSize', $headers );
	}

	/**
	 * @test
	 * @group happy
	 */
	public function 取得用戶選項應回傳可編輯角色清單(): void {
		$response = $this->rest_request_as_admin( 'GET', '/v2/powerhouse/users/options' );
		$this->assertSame( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertIsArray( $data );
		$this->assertArrayHasKey( 'code', $data );
		$this->assertSame( 'get_success', $data['code'] );
		$this->assertArrayHasKey( 'data', $data );
		$this->assertArrayHasKey( 'roles', $data['data'] );
		$this->assertNotEmpty( $data['data']['roles'], '應至少包含一個角色' );
	}

	/**
	 * @test
	 * @group happy
	 */
	public function 建立用戶應返回新用戶_id(): void {
		$response = $this->user_form_post_as_admin(
			'/v2/powerhouse/users',
			[
				'user_login' => 'phtester_new',
				'user_email' => 'phtester_new@example.com',
				'user_pass'  => 'verySecret123!',
				'role'       => 'subscriber',
				'qty'        => 1,
			]
		);

		$this->assertSame( 200, $response->get_status() );
		$data = $response->get_data();
		$this->assertIsArray( $data );
		$this->assertSame( 'create_success', $data['code'] );
		$this->assertNotEmpty( $data['data'] );

		// 驗證用戶真的被建立
		$created_user = \get_user_by( 'login', 'phtester_new' );
		$this->assertInstanceOf( \WP_User::class, $created_user );
	}

	/**
	 * @test
	 * @group happy
	 */
	public function 更新用戶資料應成功(): void {
		$user_id = $this->factory()->user->create(
			[
				'user_login' => 'phtester_update',
				'role'       => 'subscriber',
			]
		);

		$response = $this->user_form_post_as_admin(
			"/v2/powerhouse/users/{$user_id}",
			[
				'display_name' => '新暱稱',
			]
		);

		$this->assertSame( 200, $response->get_status() );
		$data = $response->get_data();
		$this->assertSame( 'update_success', $data['code'] );

		$updated_user = \get_user_by( 'ID', $user_id );
		$this->assertInstanceOf( \WP_User::class, $updated_user );
		$this->assertSame( '新暱稱', $updated_user->display_name );
	}

	/**
	 * @test
	 * @group happy
	 */
	public function 刪除單一用戶應成功(): void {
		$user_id = $this->factory()->user->create( [ 'role' => 'subscriber' ] );

		$response = $this->rest_request_as_admin( 'DELETE', "/v2/powerhouse/users/{$user_id}" );

		$this->assertSame( 200, $response->get_status() );
		$data = $response->get_data();
		$this->assertSame( 'delete_success', $data['code'] );

		$this->assertFalse( \get_user_by( 'ID', $user_id ) );
	}

	/**
	 * @test
	 * @group happy
	 */
	public function 重設密碼應對多個用戶寄送信件(): void {
		$user_id_a = $this->factory()->user->create( [ 'role' => 'subscriber', 'user_email' => 'ra@example.com' ] );
		$user_id_b = $this->factory()->user->create( [ 'role' => 'subscriber', 'user_email' => 'rb@example.com' ] );

		// 攔截 wp_mail，避免實際寄信 + 避免 retrieve_password 回傳 WP_Error
		\add_filter(
			'pre_wp_mail',
			static fn() => true,
			10,
			0
		);

		$response = $this->rest_request_as_admin(
			'POST',
			'/v2/powerhouse/users/resetpassword',
			[ 'ids' => [ $user_id_a, $user_id_b ] ]
		);

		\remove_all_filters( 'pre_wp_mail' );

		$this->assertSame( 200, $response->get_status(), '重設密碼 API 應回傳 200' );
		$data = $response->get_data();
		$this->assertSame( 'resetpassword_success', $data['code'] );
	}

	// ========== ❌ 錯誤處理（Error Handling）==========

	/**
	 * @test
	 * @group error
	 */
	public function 取得不存在的用戶應回傳錯誤(): void {
		$response = $this->rest_request_as_admin( 'GET', '/v2/powerhouse/users/999999999' );
		$this->assertNotNull( $response );
		$this->assertNotSame( 200, $response->get_status(), '不存在 ID 不應回 200' );
	}

	/**
	 * @test
	 * @group error
	 */
	public function 刪除不存在的用戶應回傳錯誤(): void {
		$response = $this->rest_request_as_admin( 'DELETE', '/v2/powerhouse/users/888888888' );
		// delete_users_with_id_callback 會丟 \Exception → REST 500
		$this->assertGreaterThanOrEqual( 400, $response->get_status() );
	}

	/**
	 * @test
	 * @group error
	 */
	public function 重設密碼未提供_ids_應回傳錯誤(): void {
		$response = $this->rest_request_as_admin( 'POST', '/v2/powerhouse/users/resetpassword', [] );
		// post_users_resetpassword_callback 拋 \Exception → 500
		$this->assertGreaterThanOrEqual( 400, $response->get_status() );
	}

	// ========== 🔒 安全性（Security）==========

	/**
	 * @test
	 * @group security
	 */
	public function 未登入用戶取得用戶列表應被拒絕(): void {
		$response = $this->rest_request_as_guest( 'GET', '/v2/powerhouse/users' );
		$this->assertContains(
			$response->get_status(),
			[ 401, 403 ],
			'未登入用戶存取用戶列表應被拒絕'
		);
	}

	/**
	 * @test
	 * @group security
	 */
	public function 訂閱者不應能建立用戶(): void {
		$subscriber_id = $this->factory()->user->create( [ 'role' => 'subscriber' ] );
		\wp_set_current_user( $subscriber_id );

		$request = new \WP_REST_Request( 'POST', '/v2/powerhouse/users' );
		$request->set_body_params( [ 'user_login' => 'hacker', 'user_email' => 'h@example.com', 'qty' => 1 ] );
		$response = \rest_do_request( $request );

		$this->assertContains(
			$response->get_status(),
			[ 401, 403 ],
			'訂閱者不應能建立用戶'
		);
	}
}
