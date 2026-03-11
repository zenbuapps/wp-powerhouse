<?php
/**
 * LC API
 */

declare(strict_types=1);

namespace J7\Powerhouse\Domains\LC\Core;

use J7\WpUtils\Classes\ApiBase;
use J7\WpUtils\Classes\WP;
use J7\Powerhouse\Domains\LC\Utils\Base;

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

	/** @var array{endpoint:string,method:string,permission_callback: ?callable }[] APIs */
	protected $apis = [
		[
			'endpoint'            => 'lc',
			'method'              => 'get',
			'permission_callback' => null,
		],
		[
			'endpoint'            => 'lc/activate',
			'method'              => 'post',
			'permission_callback' => null,
		],
		[
			'endpoint'            => 'lc/deactivate',
			'method'              => 'post',
			'permission_callback' => null,
		],
		[
			'endpoint'            => 'lc/invalidate',
			'method'              => 'post',
			'permission_callback' => '__return_true',
		],
	];

	/**
	 * 取得 LC 資料
	 *
	 * @param \WP_REST_Request $request REST請求對象。
	 * @return \WP_REST_Response 返回包含選項資料的REST響應對象。
	 * @throws \Exception 如果啟用失敗，則拋出例外。
	 */
	public function get_lc_callback( \WP_REST_Request $request ): \WP_REST_Response {
		return new \WP_REST_Response(Base::get_lc_array());
	}


	/**
	 * 啟用
	 *
	 * @param \WP_REST_Request $request REST請求對象。
	 * @return \WP_REST_Response 返回包含選項資料的REST響應對象。
	 * @throws \Exception 如果啟用失敗，則拋出例外。
	 */
	public function post_lc_activate_callback( \WP_REST_Request $request ): \WP_REST_Response {
		$body_params = $request->get_json_params();
		WP::include_required_params(
				$body_params,
				[
					'code',
					'product_slug',
				]
			);

		[
				'code'         => $code,
				'product_slug' => $product_slug,
			] = $body_params;

		$result = Base::activate( $code, $product_slug );

		if ( \is_wp_error( $result ) ) {
			throw new \Exception( $result->get_error_message() );
		}

		return new \WP_REST_Response(
			[
				'code'    => 'activate_lc_success',
				'message' => "授權碼 《{$code}》 啟用成功",
				'data'    => $result,
			]
			);
	}

	/**
	 * 棄用
	 *
	 * @param \WP_REST_Request $request REST請求對象。
	 * @return \WP_REST_Response 返回包含選項資料的REST響應對象。
	 * @throws \Exception 如果棄用失敗，則拋出例外。
	 */
	public function post_lc_deactivate_callback( \WP_REST_Request $request ): \WP_REST_Response {

		$body_params = $request->get_json_params();

		WP::include_required_params(
			$body_params,
			[
				'code',
				'product_slug',
			]
			);

		[
			'code'         => $code,
			'product_slug' => $product_slug,
			] = $body_params;

		$result = Base::deactivate( $code, $product_slug );

		return new \WP_REST_Response(
			[
				'code'    => 'deactivate_lc_success',
				'message' => "授權碼 《{$code}》 棄用成功",
				'data'    => $result,
			],
			200
			);
	}


	/**
	 * 清除 LC 快取
	 *
	 * @param \WP_REST_Request $request 包含新增用戶所需資料的REST請求對象。
	 * @return \WP_REST_Response 返回包含操作結果的REST響應對象。成功時返回用戶資料，失敗時返回錯誤訊息。
	 */
	public function post_lc_invalidate_callback( \WP_REST_Request $request ): \WP_REST_Response {
		$body_params  = $request->get_json_params();
		$product_slug = $body_params['product_slug'];
		if ( ! $product_slug ) {
			return new \WP_REST_Response(
				[
					'code'    => 'invalidate_lc_cache_failed',
					'message' => '產品 Slug 不能為空',
				],
				400
			);
		}

		// TEST 印出 WC Logger 記得移除 ---- //
		\J7\WpUtils\Classes\WC::log($product_slug, 'delete_lc_transient from post_lc_invalidate_callback');
		// ---------- END TEST ---------- //
		$delete_transient_result = Base::delete_lc_transient( $product_slug );

		return new \WP_REST_Response(
			[
				'code'    => 'invalidate_lc_cache_success',
				'message' => '清除快取成功',
				'data'    => [
					'delete_transient_result' => $delete_transient_result,
					'product_slug'            => $product_slug,
				],
			],
			200
			);
	}
}
