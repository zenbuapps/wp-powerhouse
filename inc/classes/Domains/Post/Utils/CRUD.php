<?php

declare(strict_types=1);

namespace J7\Powerhouse\Domains\Post\Utils;

use J7\WpUtils\Classes\WP;

/** Post CRUD */
abstract class CRUD {

	const TEMPLATE = '';

	/**
	 * Create a new post
	 *
	 * @see https://developer.wordpress.org/reference/functions/wp_insert_post/
	 *
	 * 簡單的新增，沒有太多參數，所以不使用 Converter
	 *
	 * @param array<string, mixed> $args Arguments.
	 *
	 * @return int|\WP_Error
	 */
	public static function create_post( array $args = [] ): int|\WP_Error {
		$default_args = [
			'post_title'    => '新文章',
			'post_name'     => 'new',
			'menu_order'    => -1, // 這樣在 sortable list 才會在最上方
			'post_status'   => 'publish',
			'post_author'   => \get_current_user_id(),
			'page_template' => self::TEMPLATE,
		];

		$args = \wp_parse_args( $args, $default_args );

		$args = \apply_filters('powerhouse/post/create_post_args', $args);

		/** @var array{ID?: int, post_author?: int, post_date?: string, post_date_gmt?: string, post_content?: string, post_content_filtered?: string, post_title?: string, post_excerpt?: string, ...} $args */
		return \wp_insert_post($args);
	}

	/**
	 * Format Post details
	 * WP_Post 轉 array
	 *
	 * @param \WP_Post                  $post             Post.
	 * @param bool                      $with_description With description.
	 * @param int                       $depth            Depth.
	 * @param array<string, mixed>|null $recursive_args 遞迴參數 預設 null 不遞迴.
	 * @param array<string>             $meta_keys        要暴露出來的 meta keys.
	 *
	 * @return array<string, mixed>
	 */
	public static function format_post_details(
		\WP_Post $post,
		bool $with_description = false,
		int $depth = 0,
		array $recursive_args = null,
		array $meta_keys = []
	) {
		$date_created  = $post->post_date;
		$date_modified = $post->post_modified;

		$image_id  = \get_post_thumbnail_id($post->ID);
		$image_ids = $image_id ? [ $image_id ] : [];
		$images    = [];
		foreach ($image_ids as $image_id) {
			$image_info = WP::get_image_info($image_id);
			if ($image_info) {
				$images[] = $image_info;
			}
		}

		$description_array = $with_description ? [
			'description'       => $post->post_content,
			'short_description' => $post->post_excerpt,
		] : [];

		$children        = self::get_recursive_array($post, $recursive_args, (int) $depth, $meta_keys, $with_description);
		$meta_keys_array = self::get_meta_keys_array($post, $meta_keys);

		$base_array = [
			// Get Product General Info
			'id'            => (string) $post->ID,
			'depth'         => $depth,
			'name'          => $post->post_title,
			'slug'          => $post->post_name,
			'date_created'  => $date_created,
			'date_modified' => $date_modified,
			'status'        => $post->post_status,
			'menu_order'    => (int) $post->menu_order,
			'permalink'     => \get_permalink($post->ID),
			'category_ids'  => self::get_term_options(
				[
					'taxonomy'   => 'category',
					'object_ids' => $post->ID,
				]
				),
			'tag_ids'       => self::get_term_options(
				[
					'taxonomy'   => 'post_tag',
					'object_ids' => $post->ID,
				]
				),
			'images'        => $images,
			'parent_id'     => (string) $post->post_parent,
		];

		$formatted_array = array_merge(
			$base_array,
			$description_array,
			$children,
			$meta_keys_array
		);

		return $formatted_array;
	}

	/**
	 * 取得 meta keys array
	 *
	 * @param \WP_Post      $post 文章.
	 * @param array<string> $meta_keys 要暴露出來的 meta keys.
	 * @return array<string, mixed>
	 */
	public static function get_meta_keys_array( \WP_Post $post, array $meta_keys = [] ): array {
		// ENHANCE 目前 $meta_keys_array 為 string[] 可能可以改成 key-value 結構 array<string, mixed> 由前端直接傳入預設值
		$meta_keys_array = [];
		foreach ($meta_keys as $meta_key) {
			$meta_keys_array[ $meta_key ] = \get_post_meta( $post->ID, $meta_key, true );
		}

		// 可以改寫 meta_keys
		/** @var array<string, mixed> */
		return \apply_filters( 'powerhouse/post/get_meta_keys_array', $meta_keys_array, $post );
	}

	/**
	 * 取得遞迴文章 array
	 *
	 * @param \WP_Post                  $post 文章.
	 * @param array<string, mixed>|null $recursive_args 遞迴參數.
	 * @param int                       $depth 深度.
	 * @param array<string>             $meta_keys 要暴露出來的 meta keys.
	 * @return array{children: array<mixed>}|array{}
	 */
	public static function get_recursive_array( \WP_Post $post, array $recursive_args = null, int $depth = 0, array $meta_keys = [], bool $with_description = false ): array {
		if (null ===$recursive_args) {
			return [];
		}

		$default_args = [
			'post_parent' => $post->ID,
			'post_type'   => $post->post_type,
			'numberposts' => -1,
			'post_status' => 'any',
			'orderby'     => [
				'menu_order' => 'ASC',
				'ID'         => 'DESC',
				'date'       => 'DESC',
			],
		];

		$args = \wp_parse_args( $recursive_args, $default_args );

		/** @var \WP_Post[] $children */
		$children = \get_children($args);

		$children_to_array = [];
		foreach ($children as $child) {
			$children_to_array[] = self::format_post_details(
				$child,
				$with_description,
				$depth + 1,
				$recursive_args,
				$meta_keys
			);
		}

		return $children_to_array ? [
			'children' => $children_to_array,
		] : [];
	}

	/**
	 * Sort posts
	 * 改變文章順序
	 *
	 * @param array{from_tree: array<array{id: string, menu_order?: int|string, parent_id?: int|string}>, to_tree: array<array{id: string, menu_order?: int|string, parent_id?: int|string}>} $params Parameters.
	 *
	 * @return true|\WP_Error
	 * @throws \Exception 排序失敗
	 */
	public static function sort_posts( array $params ): bool|\WP_Error {
		$from_tree = $params['from_tree'];
		$to_tree   = $params['to_tree'];

		// 使用 wpdb 一次更新
		global $wpdb;

		// 批量更新的大小
		$batch_size = 50; // 每次處理50筆資料

		// 分批處理資料
		$batches = array_chunk($to_tree, $batch_size);

		// 收集所有受影響的 ID，用於事務成功後清除 per-post object cache
		// （避免 Redis / Memcached 等 persistent backend 下 group flush 不生效，
		//  造成 get_post() 回 stale 值，同時確保 post_modified 反映在 cache 中）
		$all_affected_ids = [];

		// 排序操作的 post_modified 時間戳（整個批次統一為操作發起當下，一次性 prepare 為 %s）
		$modified_local = \current_time('mysql', 0);
		$modified_gmt   = \current_time('mysql', 1);

		// 開始事務處理
		$wpdb->query('START TRANSACTION');

		try {
			// 準備兩個不同的更新集合：一個用於只更新 menu_order，另一個用於同時更新 menu_order 和 post_parent
			foreach ($batches as $batch) {
				// 構建 CASE WHEN 語句
				$ids              = [];
				$menu_order_cases = [];
				$parent_cases     = [];

				foreach ($batch as $item) {
					$id         = intval($item['id']);
					$ids[]      = $id;
					$menu_order = intval($item['menu_order'] ?? 0);
					$parent_id  = $item['parent_id'] ?? 0;

					// 為每個ID準備menu_order的CASE語句
					$menu_order_cases[] = $wpdb->prepare('WHEN ID = %d THEN %d', $id, $menu_order);

					// 則準備post_parent的CASE語句
					$parent_cases[] = $wpdb->prepare('WHEN ID = %d THEN %d', $id, $parent_id);
				}

				// 合併入全部受影響 ID（後續 clean_post_cache 使用）
				foreach ($ids as $affected_id) {
					$all_affected_ids[ $affected_id ] = true;
				}

				// 構建ID列表
				$id_list = implode(',', $ids);

				// 準備 post_modified / post_modified_gmt 的 placeholder（整個批次統一值）
				$modified_sql = $wpdb->prepare(', post_modified = %s, post_modified_gmt = %s ', $modified_local, $modified_gmt);

				// 構建批量更新SQL
				$sql  = "UPDATE {$wpdb->posts} SET menu_order = CASE ";
				$sql .= implode(' ', $menu_order_cases);
				$sql .= ' ELSE menu_order END ';

				// 加入post_parent的更新語句
				$sql .= ', post_parent = CASE ';
				$sql .= implode(' ', $parent_cases);
				$sql .= ' ELSE post_parent END ';

				// 加入 post_modified / post_modified_gmt 更新（反映本次排序操作時間）
				$sql .= $modified_sql;

				// 加入WHERE條件，限制只更新需要的記錄
				$sql .= " WHERE ID IN ($id_list)";

				// 執行批量更新 wp_posts
				$result = $wpdb->query($sql);  // phpcs:ignore

				if ($result === false) {
					throw new \Exception('批量更新失敗: ' . $wpdb->last_error);
				}
			}

			// 提交事務
			$wpdb->query('COMMIT');

			// 清除文章內容快取
			\wp_cache_flush_group('posts');

			// 清除文章的中繼資料快取
			\wp_cache_flush_group('post_meta');

			// 額外清除每一筆受影響的 per-post object cache
			// Redis / Memcached 等 persistent backend 在某些環境下
			// `wp_cache_flush_group('posts')` 未必能讓 per-post entry 失效，
			// 直接呼叫 clean_post_cache 確保 get_post() 後續能拿到
			// 最新 menu_order / post_parent / post_modified。
			foreach (array_keys($all_affected_ids) as $affected_id) {
				\clean_post_cache( (int) $affected_id );
			}
		} catch (\Exception $e) {
			// 回滾事務
			$wpdb->query('ROLLBACK');
			throw new \Exception('排序失敗: ' . $e->getMessage());
		}

		$delete_ids = [];
		foreach ($from_tree as $from_node) {
			$from_id = $from_node['id'];
			$to_node = array_filter($to_tree, fn ( $node ) => $node['id'] === $from_id);
			if (!$to_node) {
				$delete_ids[] = $from_id;
			}
		}

		foreach ($delete_ids as $id) {
			\wp_trash_post( (int) $id );
		}

		return true;
	}

	/**
	 * Converter 轉換器
	 * 把 key 轉換/重新命名，將 前端傳過來的欄位轉換成 wp_update_post 能吃的參數
	 *
	 * 前端圖片欄位就傳 'image_ids' string[] 就好
	 *
	 * @param array{id?: string, depth?: int, name?: string, slug?: string, description?: string, short_description?: string, status?: string, category_ids?: string[], tag_ids?: string[], parent_id?: string} $args    Arguments.
	 *
	 * @return array{ID?: string, post_title?: string, post_name?: string, post_content?: string, post_excerpt?: string, post_status?: string, post_category?: string[], tags_input?: string[], post_parent?: string}
	 */
	public static function converter( array $args ): array {

		unset($args['id']); // 不存 id
		unset($args['depth']); // 不存 depth

		$fields_mapper = [
			'id'                => 'ID',
			'name'              => 'post_title',
			'slug'              => 'post_name',
			'description'       => 'post_content',
			'short_description' => 'post_excerpt',
			'status'            => 'post_status',
			'category_ids'      => 'post_category',
			'tag_ids'           => 'tags_input',
			'parent_id'         => 'post_parent',
		];

		$formatted_args = [];
		foreach ($args as $key => $value) {
			if (in_array($key, array_keys($fields_mapper), true)) {
				$formatted_args[ $fields_mapper[ $key ] ] = $value;
			} else {
				$formatted_args[ $key ] = $value;
			}
		}

		/** @var array{ID?: string, post_title?: string, post_name?: string, post_content?: string, post_excerpt?: string, post_status?: string, post_category?: string[], tags_input?: string[], post_parent?: string} $formatted_args */
		return $formatted_args;
	}

	/**
	 * Update a post
	 *
	 * @param string|int           $id   post id.
	 * @param array<string, mixed> $args Arguments.
	 *
	 * @return int|\WP_Error
	 */
	public static function update_post( string|int $id, array $args ): int|\WP_Error {
		$default_args = [
			'ID' => $id,
		];

		$args = \wp_parse_args( $args, $default_args );

		/** @var array{ID?: int, post_author?: int, post_date?: string, post_date_gmt?: string, post_content?: string, post_content_filtered?: string, post_title?: string, post_excerpt?: string, ...} $args */
		$update_result = \wp_update_post($args);

		return $update_result;
	}

	/**
	 * 取得最上層的文章 ID
	 *
	 * @param int $post_id 文章 ID.
	 * @return int
	 */
	public static function get_top_post_id( int $post_id ): int {

		$cache_key   = "top_post_id_{$post_id}";
		$top_post_id = \wp_cache_get( $cache_key );
		if ( $top_post_id ) {
			return (int) $top_post_id;
		}

		$ancestors = \get_post_ancestors( $post_id );
		if ( empty( $ancestors ) ) {
			// 如果沒有祖先，自己就是最上層
			return $post_id;
		}
		// 取最後一個
		$top_post_id = $ancestors[ count( $ancestors ) - 1 ];

		\wp_cache_set( $cache_key, $top_post_id );
		return (int) $top_post_id;
	}


	/**
	 * 取得扁平的子孫 post ids，不包含頂層 id
	 * 階層子孫結構都打平
	 *
	 * @param int                  $post_id 文章 ID.
	 * @param array<string, mixed> $recursive_args 遞迴參數.
	 * @return array<int>
	 */
	public static function get_flatten_post_ids( int $post_id, array $recursive_args = [] ): array {
		$post = \get_post( $post_id );
		if ( !$post ) {
			return [];
		}
		/** @var \WP_Post $post */
		$post_array = self::format_post_details(
			$post,
			false,
			0,
			$recursive_args,
			[]
			);

		if (!is_array($post_array['children'] ?? null)) {
			return [];
		}

		$flatten_post_ids = [];
		/** @var array<array<string, mixed>> $children */
		$children = $post_array['children'];
		foreach ($children as $child) {
			$flatten_post_ids[] = (int) $child['id'];
			if (is_array($child['children'] ?? null)) {
				$flatten_post_ids = [
					...$flatten_post_ids,
					...self::get_flatten_post_ids( (int) $child['id'], $recursive_args ),
				];
			}
		}
		return $flatten_post_ids;
	}

	/**
	 * 分離參數
	 * 會從前端傳入 'meta_keys', 'with_description', 'depth', 'recursive_args', 'partials' 等 array 參數
	 * 這個 function 會將這些參數分離出來，給後續 function 使用
	 *
	 * @param array<string, mixed> $args 參數.
	 * @return array{args: array<string, mixed>, meta_keys: array<string>, with_description: bool, depth: int, recursive_args: ?array<string, mixed>, partials: mixed}
	 */
	public static function handle_args( array $args ): array {
		$default = [
			'meta_keys'        => [],
			/* @deprecated with_description 即將棄用，用 partials 取代 */
			'with_description' => false,
			'depth'            => 0,
			'recursive_args'   => null,
			'partials'         => null,
		];

		$args = \wp_parse_args( $args, $default );

		[
			'meta_keys'        => $meta_keys,
			'with_description' => $with_description,
			'depth'            => $depth,
			'recursive_args'   => $recursive_args,
			'partials'         => $partials,
		] = $args;

		unset($args['meta_keys']);
		unset($args['with_description']);
		unset($args['depth']);
		unset($args['recursive_args']);
		unset($args['partials']);

		return [
			'args'             => $args,
			'meta_keys'        => $meta_keys,
			'with_description' => (bool) $with_description,
			'depth'            => $depth,
			'recursive_args'   => $recursive_args,
			'partials'         => $partials,
		];
	}

	/**
	 * Format terms, 例如 分類、標籤, product_cat, product_tag
	 * 只回簡單的欄位，通常是做 options 使用
	 *
	 * @param array<string, mixed> $params Params.
	 *
	 * @return array{value:string, label:string}[]
	 */
	public static function get_term_options( array $params = [] ): array {
		// it seems no need to add post_per_page, get_terms will return all terms
		$default_args = [
			'taxonomy'   => 'product_cat',
			'fields'     => 'id=>name',
			'hide_empty' => false,
			'orderby'    => 'name',
			'order'      => 'ASC',
		];

		$args = \wp_parse_args(
				$params,
				$default_args,
			);
		/** @var array<int, string> $terms id=>name */
		$terms = \get_terms( $args );

		$formatted_terms = [];
		foreach ($terms as $term_id => $term_name) {
			$formatted_terms[] = [
				'value' => (string) $term_id,
				'label' => $term_name,
			];
		}

		return $formatted_terms;
	}
}
