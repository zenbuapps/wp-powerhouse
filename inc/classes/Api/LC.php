<?php
/**
 * LC Api
 */

declare( strict_types=1 );

namespace J7\Powerhouse\Api;

use J7\Powerhouse\Domains\LC\Core\V2Api as LC_V2_Api;
use J7\WpUtils\Classes\ApiBase;

/**
 * Class LC
 *
 * @deprecated 使用 Domains\LC\V2Api 取代
 */
final class LC extends ApiBase {
	use \J7\WpUtils\Traits\SingletonTrait;

	/** @var string 命名空間 */
	protected $namespace = 'powerhouse';

	/** @var array{endpoint:string,method:string,permission_callback: ?callable }[] APIs */
	protected $apis = [
		[
			'endpoint'            => 'lc/invalidate',
			'method'              => 'post',
			'permission_callback' => '__return_true',
		],
	];

	/**
	 * 清除 LC 快取
	 *
	 * @param \WP_REST_Request $request 包含新增用戶所需資料的REST請求對象。
	 * @return \WP_REST_Response 返回包含操作結果的REST響應對象。成功時返回用戶資料，失敗時返回錯誤訊息。
	 */
	public function post_lc_invalidate_callback( \WP_REST_Request $request ): \WP_REST_Response {
		return LC_V2_Api::instance()->post_lc_invalidate_callback( $request );
	}
}
