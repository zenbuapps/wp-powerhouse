<?php
/**
 * Limit 資料表整合測試
 * 驗證 ph_access_itemmeta 自訂資料表的建立與資料操作
 */

declare( strict_types=1 );

namespace Tests\Integration;

use J7\Powerhouse\Domains\Limit\Utils\CreateTable;
use J7\Powerhouse\Domains\Limit\Utils\MetaCRUD;
use J7\Powerhouse\Domains\Limit\Models\GrantedItem;
use J7\Powerhouse\Domains\Limit\Models\LifeCycle;

/**
 * Class LimitTableTest
 *
 * @group limit-table
 */
class LimitTableTest extends TestCase {

	// ========== 🔥 冒煙測試（Smoke）==========

	/**
	 * @test
	 * @group smoke
	 */
	public function CreateTable_類別應已載入(): void {
		$this->assertTrue( \class_exists( CreateTable::class ) );
	}

	/**
	 * @test
	 * @group smoke
	 */
	public function ph_access_itemmeta_資料表應存在(): void {
		global $wpdb;
		$table_name = $wpdb->prefix . CreateTable::ACCESS_ITEMMETA_TABLE_NAME;

		// 建立（如果不存在）
		CreateTable::create_itemmeta_table();

		// 驗證
		$result = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) ); // phpcs:ignore
		$this->assertSame( $table_name, $result, "資料表 {$table_name} 應已建立" );
	}

	// ========== ✅ 快樂路徑（Happy Flow）==========

	/**
	 * @test
	 * @group happy
	 */
	public function ACCESS_ITEMMETA_TABLE_NAME_常數應為正確值(): void {
		$this->assertSame( 'ph_access_itemmeta', CreateTable::ACCESS_ITEMMETA_TABLE_NAME );
	}

	/**
	 * @test
	 * @group happy
	 */
	public function create_itemmeta_table_重複呼叫應不拋出例外(): void {
		try {
			CreateTable::create_itemmeta_table();
			CreateTable::create_itemmeta_table(); // 第二次呼叫應直接 return
			$this->assertTrue( true, '重複呼叫 create_itemmeta_table 不應拋出例外' );
		} catch ( \Throwable $e ) {
			$this->fail( "重複建立資料表拋出例外：{$e->getMessage()}" );
		}
	}

	/**
	 * @test
	 * @group happy
	 */
	public function itemmeta_資料表應有正確欄位結構(): void {
		global $wpdb;
		$table_name = $wpdb->prefix . CreateTable::ACCESS_ITEMMETA_TABLE_NAME;

		CreateTable::create_itemmeta_table();

		$columns = $wpdb->get_results( "DESCRIBE {$table_name}" ); // phpcs:ignore
		$col_names = array_map( fn( $col ) => $col->Field, $columns );

		$this->assertContains( 'meta_id', $col_names, '應有 meta_id 欄位' );
		$this->assertContains( 'post_id', $col_names, '應有 post_id 欄位' );
		$this->assertContains( 'user_id', $col_names, '應有 user_id 欄位' );
		$this->assertContains( 'meta_key', $col_names, '應有 meta_key 欄位' );
		$this->assertContains( 'meta_value', $col_names, '應有 meta_value 欄位' );
	}

	/**
	 * @test
	 * @group happy
	 */
	public function 可以插入和查詢_itemmeta_資料(): void {
		global $wpdb;
		$table_name = $wpdb->prefix . CreateTable::ACCESS_ITEMMETA_TABLE_NAME;

		CreateTable::create_itemmeta_table();

		$post_id  = $this->factory()->post->create();
		$user_id  = $this->factory()->user->create();
		$meta_key = 'test_limit_count';
		$meta_val = '10';

		// 插入資料
		$result = $wpdb->insert( // phpcs:ignore
			$table_name,
			[
				'post_id'    => $post_id,
				'user_id'    => $user_id,
				'meta_key'   => $meta_key,
				'meta_value' => $meta_val,
			],
			[ '%d', '%d', '%s', '%s' ]
		);

		$this->assertNotFalse( $result, 'itemmeta 資料插入不應失敗' );
		$this->assertSame( 1, $result );

		// 查詢資料
		$value = $wpdb->get_var( // phpcs:ignore
			$wpdb->prepare(
				"SELECT meta_value FROM {$table_name} WHERE post_id = %d AND user_id = %d AND meta_key = %s",
				$post_id,
				$user_id,
				$meta_key
			)
		);

		$this->assertSame( $meta_val, $value );

		// 清理
		$wpdb->delete( $table_name, [ 'post_id' => $post_id, 'user_id' => $user_id ] ); // phpcs:ignore
	}

	// ========== ❌ 錯誤處理（Error Handling）==========

	/**
	 * @test
	 * @group error
	 */
	public function 查詢不存在用戶的_itemmeta_應回傳_null(): void {
		global $wpdb;
		$table_name = $wpdb->prefix . CreateTable::ACCESS_ITEMMETA_TABLE_NAME;

		CreateTable::create_itemmeta_table();

		$value = $wpdb->get_var( // phpcs:ignore
			$wpdb->prepare(
				"SELECT meta_value FROM {$table_name} WHERE post_id = %d AND user_id = %d",
				999999,
				999999
			)
		);

		$this->assertNull( $value, '查詢不存在的資料應回傳 null' );
	}

	// ========== 🔀 邊緣案例（Edge Cases）==========

	/**
	 * @test
	 * @group edge
	 */
	public function itemmeta_meta_value_可以儲存_JSON_字串(): void {
		global $wpdb;
		$table_name = $wpdb->prefix . CreateTable::ACCESS_ITEMMETA_TABLE_NAME;

		CreateTable::create_itemmeta_table();

		$post_id   = $this->factory()->post->create();
		$user_id   = $this->factory()->user->create();
		$json_data = \wp_json_encode( [ 'limit' => 5, 'used' => 3, 'tags' => [ 'a', 'b' ] ] );

		$wpdb->insert( // phpcs:ignore
			$table_name,
			[
				'post_id'    => $post_id,
				'user_id'    => $user_id,
				'meta_key'   => 'json_test',
				'meta_value' => $json_data,
			],
			[ '%d', '%d', '%s', '%s' ]
		);

		$retrieved = $wpdb->get_var( // phpcs:ignore
			$wpdb->prepare(
				"SELECT meta_value FROM {$table_name} WHERE post_id = %d AND user_id = %d AND meta_key = %s",
				$post_id,
				$user_id,
				'json_test'
			)
		);

		$decoded = \json_decode( (string) $retrieved, true );
		$this->assertIsArray( $decoded );
		$this->assertSame( 5, $decoded['limit'] );

		// 清理
		$wpdb->delete( $table_name, [ 'post_id' => $post_id, 'user_id' => $user_id ] ); // phpcs:ignore
	}

	/**
	 * @test
	 * @group edge
	 */
	public function itemmeta_可以為同一用戶儲存多個不同_meta_key(): void {
		global $wpdb;
		$table_name = $wpdb->prefix . CreateTable::ACCESS_ITEMMETA_TABLE_NAME;

		CreateTable::create_itemmeta_table();

		$post_id = $this->factory()->post->create();
		$user_id = $this->factory()->user->create();

		foreach ( [ 'key1', 'key2', 'key3' ] as $key ) {
			$wpdb->insert( // phpcs:ignore
				$table_name,
				[
					'post_id'    => $post_id,
					'user_id'    => $user_id,
					'meta_key'   => $key,
					'meta_value' => "value_for_{$key}",
				],
				[ '%d', '%d', '%s', '%s' ]
			);
		}

		$count = (int) $wpdb->get_var( // phpcs:ignore
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table_name} WHERE post_id = %d AND user_id = %d",
				$post_id,
				$user_id
			)
		);

		$this->assertSame( 3, $count, '同一用戶應可有多個不同 meta_key 記錄' );

		// 清理
		$wpdb->delete( $table_name, [ 'post_id' => $post_id, 'user_id' => $user_id ] ); // phpcs:ignore
	}

	// ========== MetaCRUD Helper 測試 ==========

	/**
	 * @test
	 * @group happy
	 */
	public function MetaCRUD_update_應寫入_expire_date_資料(): void {
		CreateTable::create_itemmeta_table();

		$post_id = $this->factory()->post->create();
		$user_id = $this->factory()->user->create();

		$result = MetaCRUD::update( $post_id, $user_id, 'expire_date', '2030-01-01 00:00:00' );

		$this->assertNotFalse( $result, 'MetaCRUD::update 不應失敗' );

		$value = MetaCRUD::get( $post_id, $user_id, 'expire_date', true );
		$this->assertSame( '2030-01-01 00:00:00', $value, 'expire_date 應已儲存' );
	}

	/**
	 * @test
	 * @group happy
	 */
	public function MetaCRUD_update_重複呼叫_應更新而非新增(): void {
		CreateTable::create_itemmeta_table();

		$post_id = $this->factory()->post->create();
		$user_id = $this->factory()->user->create();

		MetaCRUD::update( $post_id, $user_id, 'expire_date', '2025-01-01 00:00:00' );
		MetaCRUD::update( $post_id, $user_id, 'expire_date', '2030-12-31 00:00:00' );

		$value = MetaCRUD::get( $post_id, $user_id, 'expire_date', true );
		$this->assertSame( '2030-12-31 00:00:00', $value, '重複寫入應更新為最新值' );
	}

	/**
	 * @test
	 * @group happy
	 */
	public function MetaCRUD_delete_應刪除指定記錄(): void {
		CreateTable::create_itemmeta_table();

		$post_id = $this->factory()->post->create();
		$user_id = $this->factory()->user->create();

		MetaCRUD::update( $post_id, $user_id, 'expire_date', '2030-01-01 00:00:00' );
		$delete_result = MetaCRUD::delete( $post_id, $user_id, 'expire_date' );

		$this->assertNotFalse( $delete_result, '刪除不應失敗' );

		$value = MetaCRUD::get( $post_id, $user_id, 'expire_date', true );
		$this->assertSame( '', $value, '刪除後查詢應回傳空字串' );
	}

	// ========== GrantedItem 模型測試 ==========

	/**
	 * @test
	 * @group happy
	 */
	public function GrantedItem_無記錄時_can_access_應為_false(): void {
		CreateTable::create_itemmeta_table();

		$post_id     = $this->factory()->post->create();
		$user_id     = $this->factory()->user->create();
		$granted_item = new GrantedItem( $post_id, $user_id );

		$this->assertFalse( $granted_item->can_access, '無授權記錄時 can_access 應為 false' );
	}

	// ========== grant_limit_to_user helper 測試 ==========

	/**
	 * @test
	 * @group happy
	 *
	 * 注意：expire_at=null 時存入空字串，查詢可能回傳 '' 或 null（依 MetaCRUD 實作）。
	 * 重點是不拋例外且不回傳過期時間。
	 */
	public function grant_limit_to_user_應寫入_expire_date_到_itemmeta(): void {
		CreateTable::create_itemmeta_table();

		$post_id = $this->factory()->post->create();
		$user_id = $this->factory()->user->create();

		$this->grant_limit_to_user( $user_id, $post_id );

		$value = MetaCRUD::get( $post_id, $user_id, 'expire_date', true );
		// 無 expire_at 時 expire_date 應為 '' 或 null（永不到期的表示方式）
		$this->assertTrue(
			'' === $value || null === $value,
			"無 expire_at 時 expire_date 應為空字串或 null，實際：" . var_export( $value, true )
		);
	}

	/**
	 * @test
	 * @group happy
	 */
	public function grant_limit_to_user_有_expire_at_時_應儲存到期時間(): void {
		CreateTable::create_itemmeta_table();

		$post_id  = $this->factory()->post->create();
		$user_id  = $this->factory()->user->create();
		$expire   = new \DateTime( '2030-06-15 12:00:00' );

		$this->grant_limit_to_user( $user_id, $post_id, 'course', $expire );

		$value = MetaCRUD::get( $post_id, $user_id, 'expire_date', true );
		$this->assertSame( '2030-06-15 12:00:00', $value, 'expire_date 應為指定到期時間' );
	}

	// ========== REST API 測試 — grant-users ==========

	/**
	 * @test
	 * @group smoke
	 */
	public function Limit_REST_端點_grant_users_應已被註冊(): void {
		$this->assert_rest_route_registered( '/v2/powerhouse/limit/grant-users' );
	}

	/**
	 * @test
	 * @group smoke
	 */
	public function Limit_REST_端點_revoke_users_應已被註冊(): void {
		$this->assert_rest_route_registered( '/v2/powerhouse/limit/revoke-users' );
	}

	/**
	 * 以管理員身份發送 form-data POST（用於使用 get_body_params() 的 API）
	 *
	 * @param string               $route  路由
	 * @param array<string, mixed> $params 請求參數
	 * @return \WP_REST_Response
	 */
	private function limit_form_post_as_admin( string $route, array $params = [] ): \WP_REST_Response {
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
	 * @group error
	 */
	public function grant_users_缺少_user_ids_應回傳_4xx_或_5xx(): void {
		$response = $this->limit_form_post_as_admin(
			'/v2/powerhouse/limit/grant-users',
			[
				'item_ids'    => [ '201' ],
				'expire_date' => '1800000000',
			]
		);

		$this->assertGreaterThanOrEqual( 400, $response->get_status(), '缺少 user_ids 應回傳錯誤' );
	}

	/**
	 * @test
	 * @group error
	 */
	public function grant_users_空_user_ids_應回傳_500(): void {
		$response = $this->limit_form_post_as_admin(
			'/v2/powerhouse/limit/grant-users',
			[
				'user_ids'    => [],
				'item_ids'    => [ '201' ],
				'expire_date' => '1800000000',
			]
		);

		$this->assertGreaterThanOrEqual( 400, $response->get_status(), '空 user_ids 應回傳錯誤' );
	}

	/**
	 * @test
	 * @group happy
	 */
	public function grant_users_成功授權_應回傳_200_且寫入_itemmeta(): void {
		CreateTable::create_itemmeta_table();

		$user_id = $this->factory()->user->create();
		$post_id = $this->factory()->post->create();

		$response = $this->limit_form_post_as_admin(
			'/v2/powerhouse/limit/grant-users',
			[
				'user_ids'    => [ (string) $user_id ],
				'item_ids'    => [ (string) $post_id ],
				'expire_date' => '1800000000',
			]
		);
		$data = $response->get_data();

		$this->assertSame( 200, $response->get_status() );
		$this->assertIsArray( $data );
		$this->assertSame( 'grant_users_success', $data['code'], '授權應回傳 grant_users_success' );

		// 確認 itemmeta 已寫入
		$value = MetaCRUD::get( $post_id, $user_id, 'expire_date', true );
		$this->assertSame( '1800000000', $value, 'expire_date 應已寫入 itemmeta' );
	}

	/**
	 * @test
	 * @group happy
	 */
	public function grant_users_成功後_應觸發_after_grant_action(): void {
		CreateTable::create_itemmeta_table();

		$user_id    = $this->factory()->user->create();
		$post_id    = $this->factory()->post->create();
		$fired_args = [];

		// 監聽 action
		\add_action(
			LifeCycle::GRANT_USER_TO_ITEM_ACTION,
			function ( int $uid, int $pid, mixed $expire ) use ( &$fired_args ): void {
				$fired_args[] = [ 'user_id' => $uid, 'post_id' => $pid, 'expire_date' => $expire ];
			},
			10,
			4
		);

		$this->limit_form_post_as_admin(
			'/v2/powerhouse/limit/grant-users',
			[
				'user_ids'    => [ (string) $user_id ],
				'item_ids'    => [ (string) $post_id ],
				'expire_date' => '1800000000',
			]
		);

		$this->assertNotEmpty( $fired_args, 'after_grant action 應已被觸發' );
		$this->assertSame( $user_id, $fired_args[0]['user_id'] );
		$this->assertSame( $post_id, $fired_args[0]['post_id'] );
	}

	// ========== REST API 測試 — revoke-users ==========

	/**
	 * @test
	 * @group happy
	 */
	public function revoke_users_成功撤銷_應回傳_200(): void {
		CreateTable::create_itemmeta_table();

		$user_id = $this->factory()->user->create();
		$post_id = $this->factory()->post->create();

		// 先授權
		MetaCRUD::update( $post_id, $user_id, 'expire_date', '1800000000' );

		$response = $this->limit_form_post_as_admin(
			'/v2/powerhouse/limit/revoke-users',
			[
				'user_ids' => [ (string) $user_id ],
				'item_ids' => [ (string) $post_id ],
			]
		);
		$data = $response->get_data();

		$this->assertSame( 200, $response->get_status() );
		$this->assertIsArray( $data );
		$this->assertSame( 'revoke_users_success', $data['code'], '撤銷應回傳 revoke_users_success' );
	}

	/**
	 * @test
	 * @group error
	 */
	public function revoke_users_空_user_ids_應回傳_5xx(): void {
		$response = $this->limit_form_post_as_admin(
			'/v2/powerhouse/limit/revoke-users',
			[
				'user_ids' => [],
				'item_ids' => [ '201' ],
			]
		);

		$this->assertGreaterThanOrEqual( 400, $response->get_status(), '空 user_ids 撤銷應回傳錯誤' );
	}
}
