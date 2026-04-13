<?php
/**
 * Limit 資料表整合測試
 * 驗證 ph_access_itemmeta 自訂資料表的建立與資料操作
 */

declare( strict_types=1 );

namespace Tests\Integration;

use J7\Powerhouse\Domains\Limit\Utils\CreateTable;

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
}
