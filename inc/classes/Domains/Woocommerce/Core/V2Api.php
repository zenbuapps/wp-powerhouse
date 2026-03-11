<?php

declare(strict_types=1);

namespace J7\Powerhouse\Domains\Woocommerce\Core;

use J7\WpUtils\Classes\ApiBase;
use J7\Powerhouse\Domains\Woocommerce\Model;

/**
 * Class V2Api
 */
final class V2Api extends ApiBase {
	use \J7\WpUtils\Traits\SingletonTrait;

	/** @var string Namespace */
	protected $namespace = 'v2/powerhouse';

	/** @var array{endpoint:string,method:string,permission_callback: ?callable }[] */
	protected $apis = [
		[
			'endpoint'            => 'woocommerce',
			'method'              => 'get',
			'permission_callback' => null,
		],
	];


	/**
	 * 獲取選項
	 *
	 * @param \WP_REST_Request $request REST請求對象。
	 * @return \WP_REST_Response 返回包含選項資料的REST響應對象。
	 */
	public function get_woocommerce_callback( \WP_REST_Request $request ): \WP_REST_Response {
		if ( ! function_exists( '\WC' ) ) {
			return new \WP_REST_Response(
				[
					'code'    => 'get_woocommerce_error',
					'message' => 'WooCommerce 未啟用',
					'data'    => [],
				],
				400
			);
		}

		$woocommerce = Model\Woocommerce::instance();

		return new \WP_REST_Response(
			[
				'code'    => 'get_woocommerce_success',
				'message' => '獲取 WooCommerce 資料成功',
				'data'    => $woocommerce->to_array(),
			],
			200
		);
	}
}
