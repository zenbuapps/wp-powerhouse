<?php

declare(strict_types=1);

namespace J7\Powerhouse\Domains\Post\Core;

use J7\WpUtils\Classes\WP;
use J7\WpUtils\Classes\General;
use J7\WpUtils\Classes\ApiBase;
use J7\Powerhouse\Domains\Post\Utils\CRUD;
use J7\Powerhouse\Domains\Post\Model\Attachment;

/**
 * Post CRUD API
 * 可以用 filter 來 filter 參數
 */
final class V2Api extends ApiBase {
	use \J7\WpUtils\Traits\SingletonTrait;

	/** @var string Namespace */
	protected $namespace = 'v2/powerhouse';

	/** @var array{endpoint:string,method:string,permission_callback: ?callable }[] APIs */
	protected $apis = [
		[
			'endpoint'            => 'posts',
			'method'              => 'get',
			'permission_callback' => null,
		],
		[
			'endpoint'            => 'posts/(?P<id>\d+)',
			'method'              => 'get',
			'permission_callback' => null,
		],
		[
			'endpoint'            => 'posts/(?P<id>\d+)/field/(?P<field_name>[a-zA-Z_-]+)',
			'method'              => 'get',
			'permission_callback' => null,
			'callback'            => [ __CLASS__, 'get_post_field_name_with_id_callback' ],
		],
		[
			'endpoint'            => 'posts',
			'method'              => 'post',
			'permission_callback' => null,
		],
		[
			'endpoint'            => 'posts/(?P<id>\d+)',
			'method'              => 'post',
			'permission_callback' => null,
		],
		[
			'endpoint'            => 'posts',
			'method'              => 'delete',
			'permission_callback' => null,
		],
		[
			'endpoint'            => 'posts/(?P<id>\d+)',
			'method'              => 'delete',
			'permission_callback' => null,
		],
		[
			'endpoint'            => 'posts/sort',
			'method'              => 'post',
			'permission_callback' => null,
		],
	];

	/**
	 * Get posts callback 取得文章列表
	 * 傳入 post_type 可以取得特定文章類型
	 *
	 * @param \WP_REST_Request $request Request.
	 *
	 * @return \WP_REST_Response|\WP_Error
	 * @phpstan-ignore-next-line
	 */
	public function get_posts_callback( $request ) { // phpcs:ignore

		$params = $request->get_query_params();

		$params = WP::sanitize_text_field_deep( $params, false );

		$default_args = [
			'post_type'      => 'post',
			'posts_per_page' => 20,
			'paged'          => 1,
			'post_parent'    => 0,
			'post_status'    => 'any',
			'orderby'        => [
				'menu_order' => 'ASC',
				'ID'         => 'DESC',
				'date'       => 'DESC',
			],
		];

		$args = \wp_parse_args(
		$params,
		$default_args,
		);

		if ( $args['post_parent'] === 'unset' ) {
			unset($args['post_parent']);
		}

		// 將 '[]' 轉為 [], 'true' 轉為 true, 'false' 轉為 false
		$args = General::parse( $args );

		[
		'args' => $args,
		'meta_keys' => $meta_keys,
		'with_description' => $with_description,
		'depth' => $depth,
		'recursive_args' => $recursive_args,
		] = CRUD::handle_args($args);

		$query       = new \WP_Query($args);
		$posts       = $query->posts;
		$total       = $query->found_posts;
		$total_pages = $query->max_num_pages;

		$formatted_posts = [];
		if ( $args['post_type'] === 'attachment' ) {
			foreach ($posts as $post) {
				/** @var \WP_Post $post */
				$formatted_posts[] = Attachment::instance( $post )->to_array();
			}
		} else {
			foreach ($posts as $post) {
				/** @var \WP_Post $post */
				$formatted_posts[] = CRUD::format_post_details( $post, $with_description, $depth, $recursive_args, $meta_keys );
			}
		}

		$response = new \WP_REST_Response( $formatted_posts );

		// set pagination in header
		$response->header( 'X-WP-Total', (string) $total );
		$response->header( 'X-WP-TotalPages', (string) $total_pages );
		$response->header( 'X-WP-CurrentPage', (string) $args['paged'] );
		$response->header( 'X-WP-PageSize', (string) $args['posts_per_page'] );

		return $response;
	}

	/**
	 * Get posts callback
	 *
	 * @param \WP_REST_Request $request Request.
	 *
	 * @return \WP_REST_Response|\WP_Error
	 * @throws \Exception 當文章不存在時拋出異常
	 * @phpstan-ignore-next-line
	 */
	public function get_posts_with_id_callback( $request ) { // phpcs:ignore
		$id = $request['id'] ?? null;
		if (!is_numeric($id)) {
			throw new \Exception(
				sprintf(
				\__('post id format not match #%s', 'powerhouse'),
				(string) $id
			)
			);
		}

		$post = \get_post( (int) $id );

		if (!$post) {
			throw new \Exception(
				sprintf(
				\__('post not found #%s', 'powerhouse'),
				(string) $id
			)
			);
		}

		/** @var array<string, mixed>|null $params */
		$params = $request->get_query_params();
		$params = is_array($params) ? $params : [];
		/** @var array<string, mixed> $params */
		$params = WP::sanitize_text_field_deep( $params, false );

		// 將 '[]' 轉為 [], 'true' 轉為 true, 'false' 轉為 false
		$params = General::parse( $params );

		[
				'meta_keys' => $meta_keys,
				'with_description' => $with_description,
				'depth' => $depth,
				'recursive_args' => $recursive_args,
			] = CRUD::handle_args($params);

		/** @var \WP_Post $post */
		$post_array = CRUD::format_post_details( $post, $with_description, $depth, $recursive_args, $meta_keys );

		$response = new \WP_REST_Response( $post_array );

		return $response;
	}


	/**
	 * 取得文章的指定單一欄位
	 *
	 * @param \WP_REST_Request $request Request.
	 *
	 * @return \WP_REST_Response
	 * @throws \Exception 當文章不存在時拋出異常
	 */
	public static function get_post_field_name_with_id_callback( $request ) { // phpcs:ignore
		$id = $request['id'] ?? null;
		if (!is_numeric($id)) {
			throw new \Exception(
				sprintf(
				\__('post id format not match #%s', 'powerhouse'),
				(string) $id
			)
			);
		}

		$post = \get_post( (int) $id );

		if (!$post) {
			throw new \Exception(
				sprintf(
				\__('post not found #%s', 'powerhouse'),
				(string) $id
			)
			);
		}

		$field_name = $request['field_name'] ?? null;
		if (!is_string($field_name) || $field_name === '') {
			throw new \Exception(
				__('field name is required', 'powerhouse')
			);
		}
		$field_name   = \sanitize_text_field( $field_name );
		$post_fields  = WP::get_data_fields('post');
		$is_meta_data = !\in_array( $field_name, $post_fields, true );

		$field_value = $is_meta_data ? \get_post_meta( (int) $id, $field_name, true ) : $post->$field_name;

		$response = new \WP_REST_Response(
			[
				'code'    => 'get_field_success',
				'message' => \sprintf(\__('get post field success "%s"', 'powerhouse'), $field_name),
				'data'    => $field_value,
			]
			);

		return $response;
	}

	/**
	 * 處理並分離產品資訊
	 *
	 * 根據請求分離產品資訊，並處理描述欄位。
	 *
	 * @param \WP_REST_Request $request 包含產品資訊的請求對象。
	 * @throws \Exception 當找不到商品時拋出異常。.
	 * @return array{data: array<string, mixed>, meta_data: array<string, mixed>} 包含產品對象、資料和元數據的陣列。
	 */
	private function separator( $request ): array {
		$body_params = $request->get_body_params();
		$file_params = $request->get_file_params();

		// 將前端傳過來的欄位轉換成 wp_update_post 能吃的參數
		$body_params = CRUD::converter( $body_params );

		$skip_keys = [
			'post_content',
			'post_excerpt',
		];
		/** @var array<string, mixed> $body_params 過濾字串，防止 XSS 攻擊 */
		$body_params = WP::sanitize_text_field_deep($body_params, true, $skip_keys);

		// 將 '[]' 轉為 [], 'true' 轉為 true, 'false' 轉為 false
		$body_params = General::parse( $body_params );

		/** @var array<string, mixed> $body_params */
		$body_params = \apply_filters('powerhouse/post/separator_body_params', $body_params, $request);

		$separated_data = WP::separator( $body_params, 'post', $file_params['images'] ?? [] );

		if ('delete' === ( $separated_data['meta_data']['images'] ?? '' )) {
			$separated_data['meta_data']['_thumbnail_id'] = '';
		}
		unset($separated_data['meta_data']['images']);

		return $separated_data;
	}

	/**
	 * Post post callback
	 * 創建文章
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 * @throws \Exception 當新增文章失敗時拋出異常
	 * @phpstan-ignore-next-line
	 */
	public function post_posts_callback( $request ): \WP_REST_Response|\WP_Error {
		[
				'data'      => $data,
				'meta_data' => $meta_data,
			] = $this->separator( $request );

		$qty = (int) ( $meta_data['qty'] ?? 1 );
		unset($meta_data['qty']);

		$data['meta_input'] = $meta_data;

		$success_ids = [];

		for ($i = 0; $i < $qty; $i++) {
			$post_id = CRUD::create_post( $data );
			if (is_numeric($post_id)) {
				$success_ids[] = $post_id;
			} else {
				throw new \Exception(
					sprintf(
					__('create post failed, %s', 'powerhouse'),
					$post_id->get_error_message()
				)
				);
			}
		}

		return new \WP_REST_Response(
				[
					'code'    => 'create_success',
					'message' => __('create post success', 'powerhouse'),
					'data'    => $success_ids,
				],
			);
	}

	/**
	 * Post post sort callback
	 * 處理排序
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function post_posts_sort_callback( $request ): \WP_REST_Response|\WP_Error {

		$body_params = $request->get_json_params();

		$body_params = WP::sanitize_text_field_deep( $body_params, false );

		/** @var array{from_tree: array<array{id: string}>, to_tree: array<array{id: string}>} $body_params */
		$sort_result = CRUD::sort_posts( $body_params );

		if ( $sort_result !== true ) {
			return $sort_result;
		}

		return new \WP_REST_Response(
			[
				'code'    => 'sort_success',
				'message' => __('update post sort success', 'powerhouse'),
				'data'    => null,
			]
		);
	}

	/**
	 * Patch post callback
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 * @throws \Exception 當更新文章失敗時拋出異常
	 */
	public function post_posts_with_id_callback( $request ): \WP_REST_Response|\WP_Error {
		$id = $request['id'] ?? null;
		if (!is_numeric($id)) {
			throw new \Exception(
				sprintf(
				__('post id format not match #%s', 'powerhouse'),
				(string) $id
			)
			);
		}

		[
			'data'      => $data,
			'meta_data' => $meta_data,
			] = $this->separator( $request );

		$data['meta_input'] = $meta_data;

		$update_result = CRUD::update_post(
				(int) $id,
				$data
			);

		/** @var int|\WP_Error $update_result */
		if ( !is_numeric( $update_result ) ) {
			return $update_result;
		}

		return new \WP_REST_Response(
			[
				'code'    => 'update_success',
				'message' => __('update post success', 'powerhouse'),
				'data'    => [
					'id' => $id,
				],
			]
			);
	}

	/**
	 * 批量刪除文章資料
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 * @throws \Exception 當刪除文章資料失敗時拋出異常
	 */
	public function delete_posts_callback( $request ): \WP_REST_Response {

		$body_params = $request->get_json_params();

		/** @var array<string, mixed> $body_params */
		$body_params = WP::sanitize_text_field_deep( $body_params, false );

		$ids = $body_params['ids'] ?? [];
		/** @var array<string> $ids */
		$ids = is_array( $ids ) ? $ids : [];

		foreach ($ids as $id) {
			$result = \wp_trash_post( (int) $id );
			if (!$result) {
				throw new \Exception(
					sprintf(
					__('delete post data failed #%s', 'powerhouse'),
					$id
				)
				);
			}
		}

		return new \WP_REST_Response(
				[
					'code'    => 'delete_success',
					'message' => __('delete post data success', 'powerhouse'),
					'data'    => $ids,
				]
			);
	}

	/**
	 * Delete post callback
	 * 刪除文章
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 * @throws \Exception 當刪除文章失敗時拋出異常
	 */
	public function delete_posts_with_id_callback( $request ): \WP_REST_Response {
		$id = $request['id'] ?? null;
		if (!is_numeric($id)) {
			throw new \Exception(
				sprintf(
				__('post id format not match #%s', 'powerhouse'),
				(string) $id
			)
			);
		}
		$result = \wp_trash_post( (int) $id );
		if (!$result) {
			throw new \Exception(
				sprintf(
				__('delete post failed #%s', 'powerhouse'),
				$id
			)
			);
		}

		return new \WP_REST_Response(
			[
				'code'    => 'delete_success',
				'message' => __('delete post success', 'powerhouse'),
				'data'    => [
					'id' => $id,
				],
			]
			);
	}
}
