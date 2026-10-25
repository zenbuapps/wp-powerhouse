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
			'endpoint'            => 'lc/recheck',
			'method'              => 'post',
			'permission_callback' => null,
		],
		[
			'endpoint'            => 'lc/invalidate',
			'method'              => 'post',
			'permission_callback' => '__return_true',
		],
		[
			'endpoint'            => 'lc/refresh',
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
				'message' => empty( $result['local_only'] ) ? "授權碼 《{$code}》 棄用成功" : "授權碼 《{$code}》 已從本站移除（雲端的綁定未變更）",
				'data'    => $result,
			],
			200
			);
	}

	/**
	 * 重新檢查授權（授權頁面的「重新檢查授權」）
	 *
	 * 以系統檢查的語意重跑一次，見 Base::recheck()；不是使用者主動啟用
	 *
	 * @param \WP_REST_Request $request REST請求對象。
	 * @return \WP_REST_Response 返回包含重新檢查結果的REST響應對象。
	 */
	public function post_lc_recheck_callback( \WP_REST_Request $request ): \WP_REST_Response {
		$body_params  = (array) $request->get_json_params();
		$product_slug = \is_string( $body_params['product_slug'] ?? null ) ? $body_params['product_slug'] : '';

		$lc = '' === $product_slug ? null : Base::recheck( $product_slug );
		if ( null === $lc ) {
			return new \WP_REST_Response(
				[
					'code'    => 'recheck_lc_failed',
					'message' => '找不到這個產品的授權',
				],
				404
			);
		}

		if ( 'activated' === $lc['post_status'] ) {
			$message = '授權已恢復啟用';
		} elseif ( '' !== $lc['code'] ) {
			$message = '授權碼目前仍無法在本站使用，恢復後系統會自動重新啟用';
		} else {
			$message = '授權碼已無法在本站使用，已從本站移除';
		}

		return new \WP_REST_Response(
			[
				'code'    => 'recheck_lc_success',
				'message' => $message,
				'data'    => $lc,
			],
			200
		);
	}


	/**
	 * 清除 LC 快取
	 *
	 * CloudServer 在授權碼狀態改變時呼叫，body 為 {product_slug, code, status}。
	 * 只有 code 與本站已存的授權碼相同、且 status 為 available（或沒帶）才會刪除授權碼，見 Base::invalidate()
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
		$code                    = \is_string( $body_params['code'] ?? null ) ? $body_params['code'] : '';
		$status                  = \is_string( $body_params['status'] ?? null ) ? \sanitize_key( $body_params['status'] ) : '';
		$delete_transient_result = Base::invalidate( (string) $product_slug, $code, $status );

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

	/**
	 * 授權碼恢復成已啟用時重新檢查
	 *
	 * CloudServer 在授權碼從過期／停用恢復成已啟用時呼叫，body 為 {product_slug, code}。
	 * code 與本站已存的相同才清快取，讓下一次系統檢查重新 activate；不論任何情況都不刪授權碼，見 Base::refresh()
	 * 回應一律相同（200 {ok: true}），不透露 code 是否相符
	 *
	 * @param \WP_REST_Request $request REST請求對象。
	 * @return \WP_REST_Response {ok: true}
	 */
	public function post_lc_refresh_callback( \WP_REST_Request $request ): \WP_REST_Response {
		$body_params  = (array) $request->get_json_params();
		$product_slug = \is_string( $body_params['product_slug'] ?? null ) ? $body_params['product_slug'] : '';
		$code         = \is_string( $body_params['code'] ?? null ) ? $body_params['code'] : '';

		Base::refresh( $product_slug, $code );

		return new \WP_REST_Response(
			[
				'ok' => true,
			],
			200
		);
	}
}
