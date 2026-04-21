<?php
/**
 * User Utils
 */

declare(strict_types=1);

namespace J7\Powerhouse\Domains\User\Utils;

use J7\WpUtils\Classes\WP;
use J7\WpUtils\Classes\WC;
use J7\Powerhouse\Domains\Comment\Model\Comment;
use J7\Powerhouse\Domains\Post\Service\MetaQueryBuilder;


/**
 * Class CRUD
 */
abstract class CRUD {

	/**
	 * Create a new user
	 *
	 * @see https://developer.wordpress.org/reference/functions/wp_insert_user/
	 *
	 * 簡單的新增，沒有太多參數，所以不使用 Converter
	 *
	 * @param array<string, mixed> $args Arguments.
	 *
	 * @return int|\WP_Error
	 */
	public static function create_user( array $args = [] ): int|\WP_Error {

		[
			'data'      => $data,
			'meta_data' => $meta_data,
		] = WP::separator( $args, 'user' );

		$data['meta_input'] = $meta_data;

		/** @var array{ID?: int, user_pass?: string, user_login?: string, user_nicename?: string, user_url?: string, user_email?: string, display_name?: string, nickname?: string, ...}|object $data */
		return \wp_insert_user($data);
	}


	/**
	 * Update a user
	 *
	 * @see https://developer.wordpress.org/reference/functions/wp_insert_user/
	 *
	 * 簡單的新增，沒有太多參數，所以不使用 Converter
	 * wp_update_user 接受 role 參數，可以直接修改用戶角色，但只接受小寫
	 *
	 * @param array<string, mixed> $args Arguments.
	 *
	 * @return int|\WP_Error
	 */
	public static function update_user( array $args = [] ): int|\WP_Error {

		[
			'data'      => $data,
			'meta_data' => $meta_data,
		] = WP::separator( $args, 'user' );

		// 如果有 other_meta_data 就額外做處理，直接用 umeta_id 更新
		$other_meta_data = $data['other_meta_data'] ?? [];
		$other_meta_data = is_array($other_meta_data) ? $other_meta_data : [];
		unset( $data['other_meta_data'] );

		/** @var array<array{umeta_id:string, meta_key:string, meta_value:string}> $other_meta_data */
		foreach ($other_meta_data as $other_meta_data_record) {
			[
				'umeta_id' => $umeta_id,
				'meta_key' => $meta_key,
				'meta_value' => $meta_value,
			] = $other_meta_data_record;
			\update_metadata_by_mid('user', (int) $umeta_id, $meta_value, $meta_key );
		}
		// END other_meta_data 儲存處理

		$data['meta_input'] = $meta_data;

		/** @var array{ID?: int, user_pass?: string, user_login?: string, user_nicename?: string, user_url?: string, user_email?: string, display_name?: string, nickname?: string, ...}|object $data */
		return \wp_update_user($data);
	}

	/**
	 * 取得 meta keys array
	 *
	 * @param \WP_User      $user 用戶.
	 * @param array<string> $meta_keys 要暴露出來的 meta keys.
	 * @return array<string, mixed>
	 */
	public static function get_meta_keys_array( \WP_User $user, array $meta_keys = [] ): array {
		$meta_keys_array = [];
		foreach ($meta_keys as $meta_key) {
			$meta_keys_array[ $meta_key ] = \get_user_meta( $user->ID, $meta_key, true );
		}

		// 可以改寫 meta_keys
		/** @var array<string, mixed> */
		return \apply_filters( 'powerhouse/user/get_meta_keys_array', $meta_keys_array, $user );
	}


	/**
	 * 將的參數拆成 data 與 meta_data
	 * data 可以直接給 WP_User_Query 查詢，其他的參數是 meta_data 查詢
	 *
	 * @see https://developer.wordpress.org/reference/classes/wp_user_query/
	 *
	 * @param array<string, mixed> $args Arguments.
	 * @return array{data: array<string, mixed>, meta_data: array<string, mixed>}
	 */
	public static function query_separator( array $args ): array {
		$args['number'] = $args['posts_per_page'] ?? 20;
		unset( $args['posts_per_page'] );

		$default_args = [
			'search_columns' => [ 'ID', 'user_login', 'user_email', 'user_nicename', 'display_name' ],
			'orderby'        => 'ID',
			'order'          => 'DESC',
			'count_total'    => true,
		];

		$args = \wp_parse_args( $args, $default_args );

		// 將資料拆成 data 與 meta_data
		$data      = [];
		$meta_data = [];

		$data_fields = [
			'role',
			'role__in',
			'role__not_in',
			'include',
			'exclude',
			'blog_id',
			'search',
			'search_columns',
			'number',
			'offset',
			'paged',
			'orderby',
			'order',
			'date_query',
			'who',
			'count_total', // 是否計算總數
			'has_published_posts',
			'fields', // return 的 fields
		];
		foreach ( $args as $key => $value ) {
			if ('search' === $key) {
				$data[ $key ] = "*{$value}*";
				continue;
			}

			if ( \in_array( $key, $data_fields, true ) ) {
				$data[ $key ] = $value;
			} else {
				$meta_data[ $key ] = $value;
			}
		}

		return [
			'data'      => $data,
			'meta_data' => $meta_data,
		];
	}


	/**
	 * 準備查詢參數
	 *
	 * @param array<string, mixed> $args Arguments.
	 * @return array<string, mixed>
	 */
	public static function prepare_query_args( array $args ): array {

		[
			'data'      => $data,
			'meta_data' => $meta_data,
		] = self::query_separator($args);

		if ($meta_data) {
			$data['meta_query'] = [
				'relation' => 'AND',
			];
			foreach ( $meta_data as $key => $value ) {
				$data['meta_query'][] = [
					'key'   => $key,
					'value' => $value,
				];
			}

			/** @var array<string, mixed> $meta_query_array */
			$meta_query_array = $data['meta_query'];
			$builder          = new MetaQueryBuilder( $meta_query_array );
			/** @var MetaQueryBuilder $builder */
			$builder = \apply_filters( 'powerhouse/user/prepare_query_args/meta_query_builder', $builder );

			$data['meta_query'] = $builder->get_meta_query();
		}

		return $data;
	}

	/**
	 * 取得指定用戶 ID 的購物車內容
	 *
	 * 此方法會檢查用戶的購物車會話，並返回購物車中的所有商品資訊
	 *
	 * @param int $user_id 用戶 ID
	 *
	 * @return array<int, array<string, mixed>> 購物車內容陣列，如果購物車為空或發生錯誤則返回空陣列
	 */
	public static function get_user_cart_items( int $user_id ): array {

		// 檢查用戶是否存在
		if (!\get_user_by('id', $user_id)) {
			return [];
		}

		// 取得用戶的購物車會話
		$session_handler = new \WC_Session_Handler();
		$session         = $session_handler->get_session( (string) $user_id);

		if (!$session || !is_array($session) || empty($session['cart'])) {
			return [];
		}

		// 解析購物車資料
		$cart_items   = \maybe_unserialize( (string) $session['cart']);
		$cart_content = [];

		if (!empty($cart_items) && is_array($cart_items)) {
			foreach ($cart_items as $cart_item_key => $values) {
				if (!is_array($values)) {
					continue;
				}
				// 如果 cart_item 是變體，則使用變體的 product_id
				$product_id = !empty($values['variation_id']) ? $values['variation_id'] : $values['product_id'];
				$product    = \wc_get_product($product_id);

				if (!$product) {
					continue;
				}

				$cart_content[] = [
					'product_id'    => (string) $product_id,
					'product_name'  => $product->get_name(),
					'quantity'      => $values['quantity'],
					'price'         => (float) $product->get_price(),
					'line_total'    => (float) $values['line_total'],
					'product_image' => WC::get_image_url_by_product( $product ),
				];
			}
		}

		return $cart_content;
	}

	/**
	 * 取得指定用戶 ID 的訂單資料
	 *
	 * @param int                  $user_id 用戶 ID
	 * @param array<string, mixed> $args 查詢參數
	 * @param string               $format OBJECT or ARRAY_A
	 * @return array<int, mixed>
	 */
	public static function get_user_orders( int $user_id, array $args = [], string $format = OBJECT ): array {
		$default_args = [
			'customer_id' => $user_id,
			// 'status' => ['wc-processing', 'wc-on-hold'],
			'limit'       => 5, // 預設拿5個訂單
			'page'        => 1,
			'orderby'     => 'date',
			'order'       => 'DESC',
		];

		$args = \wp_parse_args( $args, $default_args );

		/** @var \WC_Order[] $orders */
		$orders = \wc_get_orders( $args );

		if (OBJECT === $format) {
			return $orders;
		}

		$orders_data = [];
		foreach ($orders as $order) {
			$items = $order->get_items();

			$order_items = [];
			foreach ($items as $item) {
				/** @var \WC_Order_Item_Product $item */
				$product_id = $item->get_variation_id() ?: $item->get_product_id();
				$product    = \wc_get_product( $product_id );
				if (!$product) {
					continue;
				}
				$order_items[] = [
					'product_id'    => (string) $product_id,
					'product_name'  => $item->get_name(),
					'quantity'      => $item->get_quantity(),
					'price'         => (float) $product->get_price(),
					'line_total'    => (float) $item->get_total(),
					'product_image' => WC::get_image_url_by_product( $product ),
				];
			}

			$orders_data[] = [
				'order_id'     => (string) $order->get_id(),
				'order_date'   => $order->get_date_created()?->date('Y-m-d H:i:s'),
				'order_total'  => (float) $order->get_total(),
				'order_status' => $order->get_status(),
				'order_items'  => $order_items,
			];
		}

		return $orders_data;
	}

	/**
	 * 取得指定用戶 ID 的聯絡註記
	 *
	 * @param int $user_id 用戶 ID
	 * @return array<int, array<string, mixed>>
	 */
	public static function get_contact_remarks( int $user_id ): array {

		$user = \get_user_by( 'id', $user_id );
		if (!$user) {
			return [];
		}

		$comment_args = [
			'type'       => 'contact_remark',
			'status'     => 'approve',
			'fields'     => 'ids',
			'meta_key'   => 'commented_user_id',
			'meta_value' => (string) $user_id,
		];

		/** @var array<int> $comment_ids */
		$comment_ids = \get_comments( $comment_args );
		$comments    = [];
		foreach ($comment_ids as $comment_id) {
			$comment = Comment::instance( (int) $comment_id );
			if ($comment) {
				$comments[] = $comment->to_array();
			}
		}
		return $comments;
	}

	/**
	 * 取得新註冊用戶數量
	 *
	 * @param \DateTime $start_date 開始日期
	 * @param \DateTime $end_date 結束日期
	 * @return int
	 */
	public static function get_new_registration_in_range( \DateTime $start_date, \DateTime $end_date ): int {
		global $wpdb;

		// 格式化為 MySQL 日期格式
		$start = $start_date->format('Y-m-d H:i:s');
		$end   = $end_date->format('Y-m-d H:i:s');

		// 查詢時間區間內的新註冊用戶數量
		$sql = $wpdb->prepare(
		"SELECT COUNT(ID) FROM {$wpdb->users} WHERE user_registered BETWEEN %s AND %s",
		$start,
		$end
		);

		$total = $wpdb->get_var($sql); // phpcs:ignore

		return (int) $total;
	}
}
