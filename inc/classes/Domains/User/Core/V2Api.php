<?php

declare(strict_types=1);

namespace J7\Powerhouse\Domains\User\Core;

use J7\WpUtils\Classes\WP;
use J7\WpUtils\Classes\ApiBase;
use J7\Powerhouse\Domains\User\Utils\CRUD;
use J7\Powerhouse\Domains\User\Model\User;

/**
 * Class User CRUD  V2Api
 * TODO可以用 filter 來 filter 參數
 */
final class V2Api extends ApiBase {
	use \J7\WpUtils\Traits\SingletonTrait;

	// 批量上傳一次處理的數量
	const BATCH_SIZE = 100;

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
		[
			'endpoint'            => 'users',
			'method'              => 'get',
			'permission_callback' => null,
		],
		[
			'endpoint'            => 'users/(?P<id>\d+)',
			'method'              => 'get',
			'permission_callback' => null,
		],
		[
			'endpoint'            => 'users/options',
			'method'              => 'get',
			'permission_callback' => null,
		],
		[
			'endpoint'            => 'users',
			'method'              => 'post',
			'permission_callback' => null,
		],
		[
			'endpoint'            => 'users/(?P<id>\d+)',
			'method'              => 'post',
			'permission_callback' => null,
		],
		[
			'endpoint'            => 'users/resetpassword',
			'method'              => 'post',
			'permission_callback' => null,
		],
		[
			'endpoint'            => 'users',
			'method'              => 'delete',
			'permission_callback' => null,
		],
		[
			'endpoint'            => 'users/(?P<id>\d+)',
			'method'              => 'delete',
			'permission_callback' => null,
		],
	];

	/** Constructor */
	public function __construct() {
		parent::__construct();
		ExtendQuery::instance();
	}


	/**
	 * Get users callback
	 * 通用的用戶查詢
	 *
	 * @param \WP_REST_Request $request Request.
	 *
	 * @return \WP_REST_Response
	 */
	public function get_users_callback( $request ): \WP_REST_Response {
		$params = $request->get_query_params();
		$params = WP::sanitize_text_field_deep( $params, false );
		/** @var array<string> $meta_keys 要暴露的 meta keys */
		$meta_keys = $params['meta_keys'] ?? [];
		unset($params['meta_keys']);

		$args = CRUD::prepare_query_args( $params );

		$query = new \WP_User_Query( $args ); // @phpstan-ignore-line

		$users = $query->get_results();

		$total          = $query->get_total();
		$posts_per_page = (int) $args['number'];
		$paged          = (int) ( $args['paged'] ?? 1 );

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

	/**
	 * Get posts callback
	 *
	 * @param \WP_REST_Request $request Request.
	 *
	 * @return \WP_REST_Response
	 * @throws \Exception 當文章不存在時拋出異常
	 */
	public function get_users_with_id_callback( $request ) { // phpcs:ignore
		$id = $request['id'] ?? null;
		if (!is_numeric($id)) {
			throw new \Exception(
				sprintf(
				__('user id format not match #%s', 'powerhouse'),
				(string) $id
			)
				);
		}

		$params = $request->get_query_params();
		$params = WP::sanitize_text_field_deep( $params, false );
		/** @var array<string> $meta_keys */
		$meta_keys = $params['meta_keys'] ?? [];

		$user_array = User::instance( (int) $id )->to_array('edit', $meta_keys);

		$response = new \WP_REST_Response( $user_array );

		return $response;
	}

	/**
	 * Get users options callback
	 *
	 * @param \WP_REST_Request $request Request.
	 *
	 * @return \WP_REST_Response
	 * */
	public function get_users_options_callback( $request ): \WP_REST_Response {
		require_once ABSPATH . 'wp-admin/includes/user.php';

		$roles           = \get_editable_roles();
		$formatted_roles = [];
		foreach ($roles as $role => $role_data) {
			$formatted_roles[] = [
				'value' => $role,
				'label' => $role_data['name'],
			];
		}

		return new \WP_REST_Response(
			[
				'code'    => 'get_success',
				'message' => __('get users options success', 'powerhouse'),
				'data'    => [
					'roles' => $formatted_roles,
				],
			],
		);
	}

	/**
	 * 批量創建/更新用戶
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 * @throws \Exception 當新增用戶失敗時拋出異常
	 */
	public function post_users_callback( $request ): \WP_REST_Response {

		$body_params = $request->get_body_params();
		$body_params = WP::sanitize_text_field_deep( $body_params, false );

		/**
		 * 有 ids 就是批量更新
		 * 有 qty 就是批量新增
		 */

		/* ---------- 批量更新 ---------- */
		if (isset($body_params['ids'])) { // 批量更新
			$ids = $body_params['ids'];
			$ids = is_array( $ids ) ? $ids : [];
			unset($body_params['ids']);
			$success_ids = [];
			foreach ($ids as $id) {
				/** @var int|string $id */
				$args       = $body_params;
				$args['ID'] = $id;
				$user_id    = CRUD::update_user( $args );
				if (is_numeric($user_id)) {
					$success_ids[] = $user_id;
				} else {
					throw new \Exception(
						sprintf(
						__('update user failed #%1$s, %2$s', 'powerhouse'),
						$id,
						$user_id->get_error_message()
					)
					);
				}
			}

			return new \WP_REST_Response(
				[
					'code'    => 'update_success',
					'message' => __('update user success', 'powerhouse'),
					'data'    => $success_ids,
				],
			);
		}

		/* ---------- 批量新增 ---------- */
		$qty = (int) ( $body_params['qty'] ?? 1 );
		unset($body_params['qty']);

		$success_ids = [];

		for ($i = 0; $i < $qty; $i++) {
			$user_id = CRUD::create_user( $body_params );
			if (is_numeric($user_id)) {
				$success_ids[] = $user_id;
			} else {
				throw new \Exception(
					sprintf(
					__('create user failed, %s', 'powerhouse'),
					$user_id->get_error_message()
				)
				);
			}
		}

		return new \WP_REST_Response(
					[
						'code'    => 'create_success',
						'message' => __('create user success', 'powerhouse'),
						'data'    => $success_ids,
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
	public function post_users_with_id_callback( $request ): \WP_REST_Response|\WP_Error {
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
	 * 批量寄送重設密碼信
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 * @throws \Exception 當寄送重設密碼信失敗時拋出異常
	 */
	public function post_users_resetpassword_callback( $request ): \WP_REST_Response {

		$body_params = $request->get_json_params();

		/** @var array<string, mixed> $body_params */
		$body_params = WP::sanitize_text_field_deep( $body_params, false );

		$ids = $body_params['ids'] ?? [];
		/** @var array<string> $ids */
		$ids = is_array( $ids ) ? $ids : [];

		if (!$ids ) {
			throw new \Exception(
				__('ids is required', 'powerhouse')
			);
		}

		require_once ABSPATH . 'wp-admin/includes/user.php';
		foreach ($ids as $id) {
			$user = \get_user_by( 'ID', $id );

			if ( !$user ) {
				continue;
			}

			$result = \retrieve_password( $user->user_login );
			if (true !== $result) {
				throw new \Exception($result->get_error_message());
			}
		}

		return new \WP_REST_Response(
				[
					'code'    => 'resetpassword_success',
					'message' => \sprintf(
						__('user id %s reset password success ', 'powerhouse'),
						\implode(', ', $ids)
					),
					'data'    => $ids,
				]
			);
	}


	/**
	 * 批量刪除用戶
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 * @throws \Exception 當刪除用戶失敗時拋出異常
	 */
	public function delete_users_callback( $request ): \WP_REST_Response {

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
	public function delete_users_with_id_callback( $request ): \WP_REST_Response {
		$id = $request['id'] ?? null;
		if (!is_numeric($id)) {
			throw new \Exception(
				sprintf(
				__('user id format not match #%s', 'powerhouse'),
				(string) $id
			)
			);
		}
		require_once ABSPATH . 'wp-admin/includes/user.php';

		$result = \wp_delete_user( (int) $id );
		if (!$result) {
			throw new \Exception(
				sprintf(
				__('delete user failed #%s', 'powerhouse'),
				(string) $id
			)
				);
		}

		return new \WP_REST_Response(
			[
				'code'    => 'delete_success',
				'message' => __('delete user success', 'powerhouse'),
				'data'    => [
					'id' => $id,
				],
			]
			);
	}
}
