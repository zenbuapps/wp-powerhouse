<?php
/**
 * Comment CRUD
 */

declare(strict_types=1);

namespace J7\Powerhouse\Domains\Comment\Core;

use J7\WpUtils\Classes\WP;
use J7\WpUtils\Classes\General;
use J7\WpUtils\Classes\ApiBase;
use J7\Powerhouse\Domains\User\Utils\CRUD;
use J7\Powerhouse\Domains\User\Model\User;

/**
 * Class V2Api
 */
final class V2Api extends ApiBase {
	use \J7\WpUtils\Traits\SingletonTrait;

	/**
	 * Namespace
	 *
	 * @var string
	 */
	protected $namespace = 'v2/powerhouse';

	/**
	 * APIs
	 *
	 * @var array{endpoint:string,method:string,permission_callback: ?callable }[]
	 */
	protected $apis = [
		// [
		// 'endpoint'            => 'comments',
		// 'method'              => 'get',
		// 'permission_callback' => null,
		// ],
		// [
		// 'endpoint'            => 'comments/(?P<id>\d+)',
		// 'method'              => 'get',
		// 'permission_callback' => null,
		// ],
		[
			'endpoint'            => 'comments',
			'method'              => 'post',
			'permission_callback' => null,
		],
		// [
		// 'endpoint'            => 'comments/(?P<id>\d+)',
		// 'method'              => 'post',
		// 'permission_callback' => null,
		// ],
		// [
		// 'endpoint'            => 'comments',
		// 'method'              => 'delete',
		// 'permission_callback' => null,
		// ],
		[
			'endpoint'            => 'comments/(?P<id>\d+)',
			'method'              => 'delete',
			'permission_callback' => null,
		],
	];


	/**
	 * TODO Get comments callback
	 * 通用的評論查詢
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function get_comments_callback( \WP_REST_Request $request ): \WP_REST_Response {
		$params = $request->get_query_params();
		$params = WP::sanitize_text_field_deep( $params, false );
		/** @var array<string> $meta_keys 要暴露的 meta keys */
		$meta_keys = $params['meta_keys'] ?? [];
		unset($params['meta_keys']);

		/** @var array<string, mixed> $params */
		$args = CRUD::prepare_query_args( $params );

		/** @var array<string, mixed> $args */
		$query = new \WP_User_Query( $args ); // @phpstan-ignore argument.type

		$users = $query->get_results();

		$total          = $query->get_total();
		$posts_per_page = isset($args['number']) ? (int) $args['number'] : 10;
		$paged          = isset($args['paged']) ? (int) $args['paged'] : 1;

		$total_pages = ceil($total / $posts_per_page);

		$formatted_users = [];
		foreach ($users as $user) {
			$formatted_users[] = User::instance( (int) $user->ID )->to_array('list', $meta_keys);
		}
		$formatted_users = array_filter( $formatted_users );

		$response = new \WP_REST_Response( $formatted_users );

		// set pagination in header
		$response->header( 'X-WP-Total', (string) $total );
		$response->header( 'X-WP-TotalPages', (string) $total_pages );
		$response->header( 'X-WP-CurrentPage', (string) $paged );
		$response->header( 'X-WP-PageSize', (string) $posts_per_page );

		return $response;
	}

	/**TODO
	 * Get comment callback
	 *
	 * @param \WP_REST_Request $request Request.
	 *
	 * @return \WP_REST_Response|\WP_Error
	 * @throws \Exception 當文章不存在時拋出異常
	 * @phpstan-ignore-next-line
	 */
	public function get_comments_with_id_callback( $request ) { // phpcs:ignore
		$id = $request['id'] ?? null;
		if (!is_numeric($id)) {
			throw new \Exception(
				sprintf(
				__('user id format not match #%s', 'powerhouse'),
				$id
			)
				);
		}

		$params    = $request->get_query_params();
		$params    = WP::sanitize_text_field_deep( $params, false );
		$meta_keys = $params['meta_keys'] ?? [];

		$user_array = User::instance( (int) $id )->to_array('edit');

		$response = new \WP_REST_Response( $user_array );

		return $response;
	}

	/**
	 * 批量評論
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 * @throws \Exception 當新增用戶失敗時拋出異常
	 * @phpstan-ignore-next-line
	 */
	public function post_comments_callback( $request ): \WP_REST_Response|\WP_Error {
		$body_params = $request->get_body_params();
		$body_params = WP::sanitize_text_field_deep( $body_params, true );
		$user        = \wp_get_current_user();

		$args = [
			'comment_author'       => $user->display_name,
			'comment_author_email' => $user->user_email,
			'comment_author_IP'    => General::get_client_ip() ?? '',
			'comment_content'      => (string) ( $body_params['note'] ?? '' ),
			'comment_type'         => (string) ( $body_params['comment_type'] ?? 'comment' ),
			'user_id'              => $user->ID,
			'comment_meta'         => [],
		];

		if ( isset( $body_params['is_customer_note'] ) ) {
			$args['comment_meta']['is_customer_note'] = $body_params['is_customer_note'];
		}

		if ( isset( $body_params['commented_user_id'] ) ) {
			$args['comment_meta']['commented_user_id'] = $body_params['commented_user_id'];
		}

		$comment_id = \wp_insert_comment($args);

		if ( !$comment_id ) {
			throw new \Exception(__('create comment failed', 'powerhouse'));
		}

		return new \WP_REST_Response(
					[
						'code'    => 'create_success',
						'message' => __('create comment success', 'powerhouse'),
						'data'    => $comment_id,
					],
				);
	}



	/**
	 * 更新用戶
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 * @throws \Exception 當更新文章失敗時拋出異常
	 */
	public function post_comments_with_id_callback( $request ): \WP_REST_Response|\WP_Error {
		$id = $request['id'] ?? null;
		if (!is_numeric($id)) {
			throw new \Exception(
				sprintf(
				__('user id format not match #%s', 'powerhouse'),
				(string) $id
			)
			);
		}

		$body_params = $request->get_body_params();
		$body_params = WP::sanitize_text_field_deep( $body_params, false );
		/** @var array<string, mixed> $body_params */

		$body_params['ID'] = $id;

		$update_result = CRUD::update_user( $body_params );

		if ( !is_numeric( $update_result ) ) {
			return $update_result;
		}

		return new \WP_REST_Response(
			[
				'code'    => 'update_success',
				'message' => __('update user success', 'powerhouse'),
				'data'    => [
					'id' => $id,
				],
			]
			);
	}


	/**
	 * 批量刪除用戶
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 * @throws \Exception 當刪除用戶失敗時拋出異常
	 * @phpstan-ignore-next-line
	 */
	public function delete_comments_callback( $request ): \WP_REST_Response|\WP_Error {

		$body_params = $request->get_json_params();

		/** @var array<string, mixed> $body_params */
		$body_params = WP::sanitize_text_field_deep( $body_params, false );

		$ids = $body_params['ids'] ?? [];
		/** @var array<string> $ids */
		$ids = is_array( $ids ) ? $ids : [];

		require_once ABSPATH . 'wp-admin/includes/user.php';
		foreach ($ids as $id) {
			$result = \wp_delete_user( (int) $id );
			if (!$result) {
				throw new \Exception(
					sprintf(
					__('delete user failed #%s', 'powerhouse'),
					$id
				)
				);
			}
		}

		return new \WP_REST_Response(
				[
					'code'    => 'delete_success',
					'message' => __('delete user success', 'powerhouse'),
					'data'    => $ids,
				]
			);
	}

	/**
	 * 刪除用戶
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 * @throws \Exception 當刪除用戶失敗時拋出異常
	 */
	public function delete_comments_with_id_callback( $request ): \WP_REST_Response {
		$id = $request['id'] ?? null;
		if (!is_numeric($id)) {
			throw new \Exception(
				sprintf(
				__('comment id format not match #%s', 'powerhouse'),
				(string) $id
			)
			);
		}

		$success = \wp_delete_comment( (int) $id );
		if (!$success) {
			throw new \Exception(
				sprintf(
				__('delete comment failed #%s', 'powerhouse'),
				$id
			)
				);
		}

		return new \WP_REST_Response(
			[
				'code'    => 'delete_success',
				'message' => __('delete comment success', 'powerhouse'),
				'data'    => [
					'id' => $id,
				],
			]
			);
	}
}
