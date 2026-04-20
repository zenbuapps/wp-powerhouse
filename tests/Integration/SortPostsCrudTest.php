<?php
/**
 * SortPosts CRUD 共用層測試
 *
 * 驗證 `J7\Powerhouse\Domains\Post\Utils\CRUD::sort_posts()` 在批次更新時：
 *   1. menu_order / post_parent 正確寫入（回歸保護）
 *   2. post_modified / post_modified_gmt 被更新為當下時間
 *   3. per-post object cache 被清除（get_post() 拿到新值）
 *
 * 背景：先前 `sort_posts()` 只用 CASE WHEN 更新 menu_order + post_parent，
 * 這條共用層路徑：
 *   A. 繞過 wp_update_post()，post_modified 維持舊值
 *   B. 僅呼叫 `wp_cache_flush_group('posts')` / `'post_meta'`，
 *      部分 persistent object cache backend（如 Redis）下無法讓 per-post cache 失效，
 *      造成 `get_post()` 回傳 stale 資料
 * 所有下游消費者（power-docs 章節拖拽、power-course 單元排序、...）都受害。
 *
 * 本測試群定義「修復後 `sort_posts()` 必須達到的契約」。
 *
 * @group post-api
 * @group sort-posts
 */

declare( strict_types=1 );

namespace Tests\Integration;

use J7\Powerhouse\Domains\Post\Utils\CRUD as PostUtils;

/**
 * Class SortPostsCrudTest
 */
class SortPostsCrudTest extends TestCase {

	/**
	 * 建立排序 fixture：一個 root + 三個 child
	 *
	 * @var array<string, int>
	 */
	private array $node_ids = [];

	/**
	 * 排序前基準時間
	 *
	 * @var string
	 */
	private string $before_sort_datetime = '';

	/**
	 * set_up：建立 root + 3 個 child，記錄基準時間
	 */
	public function set_up(): void {
		parent::set_up();

		$this->node_ids['root']    = $this->factory()->post->create(
			[
				'post_type'   => 'page',
				'post_title'  => 'Root',
				'post_status' => 'publish',
				'menu_order'  => 0,
			]
		);
		$this->node_ids['child_a'] = $this->factory()->post->create(
			[
				'post_type'   => 'page',
				'post_title'  => 'Child A',
				'post_parent' => $this->node_ids['root'],
				'post_status' => 'publish',
				'menu_order'  => 0,
			]
		);
		$this->node_ids['child_b'] = $this->factory()->post->create(
			[
				'post_type'   => 'page',
				'post_title'  => 'Child B',
				'post_parent' => $this->node_ids['root'],
				'post_status' => 'publish',
				'menu_order'  => 1,
			]
		);
		$this->node_ids['child_c'] = $this->factory()->post->create(
			[
				'post_type'   => 'page',
				'post_title'  => 'Child C',
				'post_parent' => $this->node_ids['root'],
				'post_status' => 'publish',
				'menu_order'  => 2,
			]
		);

		$this->before_sort_datetime = \current_time( 'mysql' );
		// 確保 MariaDB DATETIME（秒精度）能辨識 before/after 時間差
		sleep( 1 );
	}

	// ========== 🔥 冒煙測試（Smoke）==========

	/**
	 * @test
	 * @group smoke
	 *
	 * menu_order / post_parent 仍應正確寫入（回歸保護）
	 */
	public function sort_posts_應正確更新menu_order與post_parent(): void {
		$root    = $this->node_ids['root'];
		$child_a = $this->node_ids['child_a'];
		$child_b = $this->node_ids['child_b'];
		$child_c = $this->node_ids['child_c'];

		$to_tree = [
			[ 'id' => (string) $child_b, 'menu_order' => 0, 'parent_id' => (string) $root ],
			[ 'id' => (string) $child_c, 'menu_order' => 1, 'parent_id' => (string) $root ],
			[ 'id' => (string) $child_a, 'menu_order' => 2, 'parent_id' => (string) $root ],
		];

		$result = PostUtils::sort_posts(
			[
				'from_tree' => $to_tree,
				'to_tree'   => $to_tree,
			]
		);

		$this->assertTrue( $result === true, 'sort_posts 應回傳 true' );

		global $wpdb;
		$rows  = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT ID, post_parent, menu_order FROM {$wpdb->posts} WHERE ID IN (%d, %d, %d) ORDER BY ID ASC",
				$child_a,
				$child_b,
				$child_c
			),
			ARRAY_A
		);
		$by_id = [];
		foreach ( $rows as $row ) {
			$by_id[ (int) $row['ID'] ] = $row;
		}

		$this->assertSame( 2, (int) $by_id[ $child_a ]['menu_order'], 'child_a menu_order 應為 2' );
		$this->assertSame( 0, (int) $by_id[ $child_b ]['menu_order'], 'child_b menu_order 應為 0' );
		$this->assertSame( 1, (int) $by_id[ $child_c ]['menu_order'], 'child_c menu_order 應為 1' );
		$this->assertSame( $root, (int) $by_id[ $child_a ]['post_parent'], 'child_a post_parent 應仍指向 root' );
	}

	// ========== ✅ 快樂路徑（Happy Flow）==========

	/**
	 * @test
	 * @group happy
	 *
	 * sort_posts 後，wp_posts.post_modified 應被更新為當下時間
	 * （修復前此測試會紅燈 — CASE WHEN 沒含 post_modified）
	 */
	public function sort_posts後_post_modified應被更新為當下時間(): void {
		$root    = $this->node_ids['root'];
		$child_a = $this->node_ids['child_a'];
		$child_b = $this->node_ids['child_b'];
		$child_c = $this->node_ids['child_c'];

		$to_tree = [
			[ 'id' => (string) $child_b, 'menu_order' => 0, 'parent_id' => (string) $root ],
			[ 'id' => (string) $child_c, 'menu_order' => 1, 'parent_id' => (string) $root ],
			[ 'id' => (string) $child_a, 'menu_order' => 2, 'parent_id' => (string) $root ],
		];

		PostUtils::sort_posts(
			[
				'from_tree' => $to_tree,
				'to_tree'   => $to_tree,
			]
		);

		global $wpdb;
		$modifieds = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT post_modified FROM {$wpdb->posts} WHERE ID IN (%d, %d, %d)",
				$child_a,
				$child_b,
				$child_c
			)
		);

		foreach ( $modifieds as $modified ) {
			$this->assertGreaterThan(
				$this->before_sort_datetime,
				(string) $modified,
				"post_modified 應大於 {$this->before_sort_datetime}，實際：{$modified}"
			);
		}
	}

	/**
	 * @test
	 * @group happy
	 *
	 * sort_posts 後，post_modified_gmt 也應被更新（非零值且 >= 基準時間）
	 */
	public function sort_posts後_post_modified_gmt應被更新為當下GMT時間(): void {
		$root    = $this->node_ids['root'];
		$child_a = $this->node_ids['child_a'];
		$child_b = $this->node_ids['child_b'];
		$child_c = $this->node_ids['child_c'];

		$before_gmt = \current_time( 'mysql', 1 );

		$to_tree = [
			[ 'id' => (string) $child_b, 'menu_order' => 0, 'parent_id' => (string) $root ],
			[ 'id' => (string) $child_c, 'menu_order' => 1, 'parent_id' => (string) $root ],
			[ 'id' => (string) $child_a, 'menu_order' => 2, 'parent_id' => (string) $root ],
		];

		PostUtils::sort_posts(
			[
				'from_tree' => $to_tree,
				'to_tree'   => $to_tree,
			]
		);

		global $wpdb;
		$modifieds_gmt = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT post_modified_gmt FROM {$wpdb->posts} WHERE ID IN (%d, %d, %d)",
				$child_a,
				$child_b,
				$child_c
			)
		);

		foreach ( $modifieds_gmt as $gmt ) {
			$this->assertGreaterThanOrEqual(
				$before_gmt,
				(string) $gmt,
				"post_modified_gmt 應大於等於 {$before_gmt}，實際：{$gmt}"
			);
			$this->assertNotSame( '0000-00-00 00:00:00', (string) $gmt, 'post_modified_gmt 不應為零值' );
		}
	}

	/**
	 * @test
	 * @group happy
	 *
	 * sort_posts 後，get_post() 應回傳最新 menu_order（per-post object cache 已失效）
	 * 修復前：若 Redis persistent cache 讓 group flush 失效，get_post() 會回 stale。
	 */
	public function sort_posts後_get_post應回傳最新menu_order_無stale_cache(): void {
		$root    = $this->node_ids['root'];
		$child_a = $this->node_ids['child_a'];
		$child_b = $this->node_ids['child_b'];
		$child_c = $this->node_ids['child_c'];

		// 先 warm cache
		\get_post( $child_a );

		$to_tree = [
			[ 'id' => (string) $child_b, 'menu_order' => 0, 'parent_id' => (string) $root ],
			[ 'id' => (string) $child_c, 'menu_order' => 1, 'parent_id' => (string) $root ],
			[ 'id' => (string) $child_a, 'menu_order' => 2, 'parent_id' => (string) $root ],
		];

		PostUtils::sort_posts(
			[
				'from_tree' => $to_tree,
				'to_tree'   => $to_tree,
			]
		);

		// 不手動 flush，直接 get_post — 若 cache 正確失效，應拿到新值
		$post_after = \get_post( $child_a );
		$this->assertNotNull( $post_after, 'child_a 應能被 get_post 取到' );
		$this->assertSame(
			2,
			(int) $post_after->menu_order,
			'get_post 回傳的 menu_order 應為新值 2；若為 0 代表 per-post object cache 未清'
		);
	}

	/**
	 * @test
	 * @group happy
	 *
	 * sort_posts 後，get_post() 回傳的 post_modified 應是新值（per-post cache 同步）
	 */
	public function sort_posts後_get_post應回傳新post_modified(): void {
		$root    = $this->node_ids['root'];
		$child_a = $this->node_ids['child_a'];
		$child_b = $this->node_ids['child_b'];
		$child_c = $this->node_ids['child_c'];

		// 先 warm cache 紀錄 before 值
		$before_post = \get_post( $child_a );
		$this->assertNotNull( $before_post, 'child_a 應存在' );
		$before_modified = (string) $before_post->post_modified;

		$to_tree = [
			[ 'id' => (string) $child_b, 'menu_order' => 0, 'parent_id' => (string) $root ],
			[ 'id' => (string) $child_c, 'menu_order' => 1, 'parent_id' => (string) $root ],
			[ 'id' => (string) $child_a, 'menu_order' => 2, 'parent_id' => (string) $root ],
		];

		PostUtils::sort_posts(
			[
				'from_tree' => $to_tree,
				'to_tree'   => $to_tree,
			]
		);

		$after_post = \get_post( $child_a );
		$this->assertNotNull( $after_post, 'child_a 應仍存在於 sort 後' );
		$this->assertGreaterThan(
			$before_modified,
			(string) $after_post->post_modified,
			'get_post 回傳的 post_modified 應晚於 sort 前的值，若相同代表 cache 未清或 post_modified 未更新'
		);
	}
}
