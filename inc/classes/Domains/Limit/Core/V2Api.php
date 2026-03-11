<?php
/**
 * Limit API
 */

declare(strict_types=1);

namespace J7\Powerhouse\Domains\Limit\Core;

use J7\WpUtils\Classes\WP;
use J7\WpUtils\Classes\ApiBase;
use J7\Powerhouse\Domains\Limit\Models;


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
		[
			'endpoint'            => 'limit/grant-users',
			'method'              => 'post',
			'permission_callback' => null,
		],
		[
			'endpoint'            => 'limit/update-users',
			'method'              => 'post',
			'permission_callback' => null,
		],
		[
			'endpoint'            => 'limit/revoke-users',
			'method'              => 'post',
			'permission_callback' => null,
		],
	];

	/**
	 * Constructor
	 */
	public function __construct() {
		parent::__construct();
		Models\LifeCycle::instance();
	}


	/**
	 * 授權用戶到項目上
	 *
	 * @param \WP_REST_Request $request Request.
	 *
	 * @return \WP_REST_Response
	 * @throws \Exception 缺少 user_ids, item_ids, expire_date, meta_key
	 */
	public function post_limit_grant_users_callback( \WP_REST_Request $request ): \WP_REST_Response {

		$body_params = $request->get_body_params();
		WP::include_required_params( $body_params, [ 'user_ids', 'item_ids', 'expire_date' ] );
		$body_params = WP::sanitize_text_field_deep($body_params, false );

		/** @var array<string, mixed> $body_params */
		$user_ids    = \is_array( $body_params['user_ids'] ) ? $body_params['user_ids'] : [];
		$item_ids    = \is_array( $body_params['item_ids'] ) ? $body_params['item_ids'] : [];
		$expire_date = $body_params['expire_date'] ?? 0;

		if (!$user_ids || !$item_ids) {
			throw new \Exception(__('Failed to add users, missing user_ids or item_ids', 'powerhouse'));
		}

		foreach ($item_ids as $item_id) {
			foreach ($user_ids as  $user_id) {
				\do_action( Models\LifeCycle::GRANT_USER_TO_ITEM_ACTION, (int) $user_id, (int) $item_id, $expire_date, null );
			}
		}

		return new \WP_REST_Response(
			[
				'code'    => 'grant_users_success',
				'message' => __('Users granted successfully', 'powerhouse'),
				'data'    => [
					'user_ids'    => \implode(',', $user_ids),
					'item_ids'    => \implode(',', $item_ids),
					'expire_date' => $expire_date,
				],
			],
			200
			);
	}

	/**
	 * 更新用戶觀看時間
	 *
	 * @param \WP_REST_Request $request Request.
	 *
	 * @return \WP_REST_Response
	 * @throws \Exception 缺少 user_ids, item_ids, timestamp
	 */
	public function post_limit_update_users_callback( \WP_REST_Request $request ): \WP_REST_Response {
		$body_params = $request->get_body_params();
		WP::include_required_params( $body_params, [ 'user_ids', 'item_ids', 'timestamp' ] );
		$body_params = WP::sanitize_text_field_deep( $body_params, false );

		/** @var array<string, mixed> $body_params */
		$user_ids  = \is_array( $body_params['user_ids'] ) ? $body_params['user_ids'] : [];
		$timestamp = (int) ( $body_params['timestamp'] ?? 0 ); // 一般為 10 位數字，如果是0就是無期限 //TODO 可能會跟隨訂閱!?
		$item_ids  = \is_array( $body_params['item_ids'] ) ? $body_params['item_ids'] : [];

		foreach ($item_ids as $item_id) {
			foreach ($user_ids as  $user_id) {
				\do_action(Models\LifeCycle::AFTER_UPDATE_USER_FROM_ITEM_ACTION, $user_id, $item_id, $timestamp);
			}
		}

		return new \WP_REST_Response(
			[
				'code'    => 'update_users_success',
				'message' => __('Batch update successfully', 'powerhouse'),
				'data'    => [
					'user_ids'  => \implode(',', $user_ids),
					'item_ids'  => \implode(',', $item_ids),
					'timestamp' => $timestamp,
				],

			]
			);
	}


	/**
	 * 撤銷用戶
	 *
	 * @param \WP_REST_Request $request Request.
	 *
	 * @return \WP_REST_Response
	 * @throws \Exception 缺少 user_ids, item_ids
	 */
	public function post_limit_revoke_users_callback( \WP_REST_Request $request ): \WP_REST_Response {
		$body_params = $request->get_body_params();
		WP::include_required_params( $body_params, [ 'user_ids', 'item_ids' ] );
		$body_params = WP::sanitize_text_field_deep( $body_params, false );
		/** @var array<string, mixed> $body_params */
		$user_ids = \is_array( $body_params['user_ids'] ) ? $body_params['user_ids'] : [];
		$item_ids = \is_array( $body_params['item_ids'] ) ? $body_params['item_ids'] : [];

		if (!$user_ids || !$item_ids) {
			throw new \Exception(__('Failed to revoke users, missing user_ids or item_ids', 'powerhouse'));
		}

		foreach ($item_ids as $item_id) {
			foreach ($user_ids as $user_id) {
				\do_action(Models\LifeCycle::AFTER_REVOKE_USER_FROM_ITEM_ACTION, $user_id, $item_id);
			}
		}

		return new \WP_REST_Response(
				[
					'code'    => 'revoke_users_success',
					'message' => __('Users revoked successfully', 'powerhouse'),
					'data'    => [
						'user_ids' => \implode(',', $user_ids),
						'item_ids' => \implode(',', $item_ids),
					],

				],
				200
			);
	}
}
