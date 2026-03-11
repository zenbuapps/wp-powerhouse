<?php
/**
 * Order CRUD API
 * 可以用 filter 來 filter 參數
 */

declare(strict_types=1);

namespace J7\Powerhouse\Domains\Order\Core;

use J7\WpUtils\Classes\WP;
use J7\WpUtils\Classes\General;
use J7\WpUtils\Classes\ApiBase;
use J7\Powerhouse\Domains\Order\Utils\CRUD;

/** Class V2Api */
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
			'endpoint'            => 'orders',
			'method'              => 'get',
			'permission_callback' => null,
		],
		[
			'endpoint'            => 'orders/(?P<id>\d+)',
			'method'              => 'get',
			'permission_callback' => null,
		],
		[
			'endpoint'            => 'orders/options',
			'method'              => 'get',
			'permission_callback' => null,
		],
		[
			'endpoint'            => 'orders',
			'method'              => 'post',
			'permission_callback' => null,
		],
		[
			'endpoint'            => 'orders/(?P<id>\d+)',
			'method'              => 'post',
			'permission_callback' => null,
		],
		[
			'endpoint'            => 'orders',
			'method'              => 'delete',
			'permission_callback' => null,
		],
		[
			'endpoint'            => 'orders/(?P<id>\d+)',
			'method'              => 'delete',
			'permission_callback' => null,
		],
		[
			'endpoint'            => 'order-notes',
			'method'              => 'post',
			'permission_callback' => null,
		],
		[
			'endpoint'            => 'order-notes/(?P<id>\d+)',
			'method'              => 'delete',
			'permission_callback' => null,
		],
	];

	/**
	 * Get orders callback 取得訂單列表
	 *
	 * @see https://github.com/woocommerce/woocommerce/wiki/wc_get_orders-and-WC_Order_Query
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 * @phpstan-ignore-next-line
	 */
	public function get_orders_callback( $request ) { // phpcs:ignore

		$params = $request->get_query_params();

		$params = WP::sanitize_text_field_deep( $params, false );

		$default_args = [
			'paginate' => true,
			'limit'    => 30,
			'paged'    => 1,
			'type'     => 'shop_order',
			// ------ 其他參數 ------
			// 'type' => 'shop_order_refund', // shop_order | shop_order_refund
			// 'created_via' => 'checkout',
			// 'parent' => 20,
			// 'parent_exclude' => array( 20, 21 ),
			// 'exclude' => array( $order->get_id() ),
			// 'status' => array('wc-processing', 'wc-on-hold'),
			// 'orderby' => 'modified',
			// 'order' => 'DESC',
			// 'return' => 'ids', // object  ids
			// 'currency' => 'USD',
			// 'prices_include_tax' => 'yes',
			// 'payment_method' => 'cheque',
			// 'payment_method_title' => 'Check payments',
			// 'discount_total' => 20.00
			// 'customer' => 'woocommerce@woocommerce.com', // 接受 billing email or customer id.
			// 'customer_id' => 12,
			// 'billing_country' => 'US',
			// 'billing_first_name' => 'Claudio',
			// 'billing_last_name' => 'Sanches',
			// 'date_paid' => '2016-02-12',
			// 'date_created' => '<' . ( time() - HOUR_IN_SECONDS ), // 最近 1小時 內的訂單
			// 'date_completed' => '1494971177...1494938777',
		];

		$args = \wp_parse_args(
			$params,
			$default_args,
		);

		// 將 '[]' 轉為 [], 'true' 轉為 true, 'false' 轉為 false
		$args = General::parse( $args );

		$query = \wc_get_orders($args);
		if (!( $query instanceof \stdClass )) {
			return new \WP_REST_Response([]);
		}
		$orders      = $query->orders;
		$total       = $query->total;
		$total_pages = $query->max_num_pages;

		$formatted_orders = [];
		foreach ($orders as $order) {
			/** @var \WC_Order $order */
			$formatted_orders[] = CRUD::format_order_details($order );
		}

		$response = new \WP_REST_Response( $formatted_orders );

		// set pagination in header
		$response->header( 'X-WP-Total', (string) $total );
		$response->header( 'X-WP-TotalPages', (string) $total_pages );
		$response->header( 'X-WP-CurrentPage', (string) $args['paged'] );
		$response->header( 'X-WP-PageSize', (string) $args['posts_per_page'] );

		return $response;
	}




	/**
	 * Get order callback
	 *
	 * @param \WP_REST_Request $request Request.
	 *
	 * @return \WP_REST_Response|\WP_Error
	 * @throws \Exception 當文章不存在時拋出異常
	 * @phpstan-ignore-next-line
	 */
	public function get_orders_with_id_callback( $request ) { // phpcs:ignore
		$id = $request['id'] ?? null;
		if (!is_numeric($id)) {
			throw new \Exception(
				sprintf(
				__('post id format not match #%s', 'powerhouse'),
				(string) $id
			)
			);
		}

		$order = \wc_get_order( (int) $id );

		if (!( $order instanceof \WC_Order )) {
			throw new \Exception(
				sprintf(
				__('order not found #%s', 'powerhouse'),
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

		/** @var \WC_Order $order */
		$formatted_order = CRUD::format_order_details( $order, true );

		$response = new \WP_REST_Response( $formatted_order );

		return $response;
	}

	/**
	 * Get orders options callback
	 *
	 * @param \WP_REST_Request $request Request.
	 *
	 * @return \WP_REST_Response<array{
	 *  statuses: array<string, string>,
	 * ...
	 * }>
	 * @phpstan-ignore-next-line
	 */
	public function get_orders_options_callback( $request ) { // phpcs:ignore

		/** @var array{
		 *  statuses: array<string, string>,
		 * ...
		 * } $options
		*/
		$options = \apply_filters(
			'powerhouse/order/get_options',
			[
				'statuses' => \wc_get_order_statuses(),
			],
			$request
			);

		return new \WP_REST_Response($options);
	}


	/**TODO
	 * 處理並分離產品資訊
	 *
	 * 根據請求分離產品資訊，並處理描述欄位。
	 *
	 * @param \WP_REST_Request $request 包含產品資訊的請求對象。
	 * @throws \Exception 當找不到商品時拋出異常。.
	 * @return array{data: array<string, mixed>, meta_data: array<string, mixed>} 包含產品對象、資料和元數據的陣列。
	 * @phpstan-ignore-next-line
	 */
	private function separator( $request ): array {
		$body_params = $request->get_body_params();
		$file_params = $request->get_file_params();

		// 將前端傳過來的欄位轉換成 wp_update_post 能吃的參數
		$body_params = CRUD::converter( $body_params );

		$skip_keys = [
			'post_content',
		];
		/** @var array<string, mixed> $body_params 過濾字串，防止 XSS 攻擊 */
		$body_params = WP::sanitize_text_field_deep($body_params, true, $skip_keys);

		// 將 '[]' 轉為 [], 'true' 轉為 true, 'false' 轉為 false
		$body_params = General::parse( $body_params );

		/** @var array<string, mixed> $body_params */
		$body_params = \apply_filters('powerhouse/order/separator_body_params', $body_params, $request);

		$separated_data = WP::separator( $body_params, 'post', $file_params['images'] ?? [] );

		if ('delete' === ( $separated_data['meta_data']['images'] ?? '' )) {
			$separated_data['meta_data']['_thumbnail_id'] = '';
		}
		unset($separated_data['meta_data']['images']);

		return $separated_data;
	}

	/**
	 * Post order callback
	 * 創建訂單
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 * @throws \Exception 當新增訂單失敗時拋出異常
	 */
	public function post_orders_callback( $request ): \WP_REST_Response|\WP_Error {
		$order = \wc_create_order();
		if ($order instanceof \WP_Error) {
			return $order;
		}
		$order->set_status('pending');
		$order->save();

		return new \WP_REST_Response(
				[
					'code'    => 'create_success',
					'message' => __('create order success', 'powerhouse'),
					'data'    => $order,
				],
			);
	}


	/**TODO
	 * Patch post callback
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 * @throws \Exception 當更新文章失敗時拋出異常
	 * @phpstan-ignore-next-line
	 */
	public function post_orders_with_id_callback( $request ): \WP_REST_Response|\WP_Error {
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
	 * 批量刪除訂單資料
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 * @throws \Exception 當刪除訂單資料失敗時拋出異常
	 * @phpstan-ignore-next-line
	 */
	public function delete_orders_callback( $request ): \WP_REST_Response|\WP_Error {

		$body_params = $request->get_json_params();

		/** @var array<string, mixed> $body_params */
		$body_params = WP::sanitize_text_field_deep( $body_params, false );

		$ids = $body_params['ids'] ?? [];
		/** @var array<string> $ids */
		$ids = is_array( $ids ) ? $ids : [];

		foreach ($ids as $id) {
			$order = \wc_get_order( (int) $id );
			if (!$order) {
				throw new \Exception(
					sprintf(
					__('order not found #%s', 'powerhouse'),
					$id
				)
				);
			}
			$delete_result = $order->delete();
			if (!$delete_result) {
				throw new \Exception(
				sprintf(
					__('delete order failed #%s', 'powerhouse'),
					$id
				)
				);
			}
		}

		return new \WP_REST_Response(
				[
					'code'    => 'delete_success',
					'message' => __('delete order data success', 'powerhouse'),
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
	public function delete_orders_with_id_callback( $request ): \WP_REST_Response {
		$id = $request['id'] ?? null;
		if (!is_numeric($id)) {
			throw new \Exception(
				sprintf(
				__('post id format not match #%s', 'powerhouse'),
				(string) $id
			)
			);
		}
		$order = \wc_get_order( (int) $id );
		if (!$order) {
			throw new \Exception(
				sprintf(
				__('order not found #%s', 'powerhouse'),
				(string) $id
			)
			);
		}
		$delete_result = $order->delete();
		if (!$delete_result) {
			throw new \Exception(
				sprintf(
				__('delete order failed #%s', 'powerhouse'),
				(string) $id
			)
			);
		}
		return new \WP_REST_Response(
			[
				'code'    => 'delete_success',
				'message' => __('delete order success', 'powerhouse'),
				'data'    => [
					'id' => $id,
				],
			]
			);
	}


	/**
	 * 新增訂單備註
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 * @throws \Exception 當新增訂單備註失敗時拋出異常
	 */
	public function post_order_notes_callback( $request ): \WP_REST_Response {
		$body_params = $request->get_body_params();
		WP::include_required_params( $body_params, [ 'order_id', 'note', 'is_customer_note' ] );
		/** @var array<string, string> $sanitized_params */
		$sanitized_params = WP::sanitize_text_field_deep( $body_params );
		$order_id         = $sanitized_params['order_id'] ?? '';
		$note             = $sanitized_params['note'] ?? '';
		$is_customer_note = $sanitized_params['is_customer_note'] ?? '0';

		$order = \wc_get_order( (int) $order_id );
		if (!( $order instanceof \WC_Order )) {
			throw new \Exception(
				sprintf(
					__('order not found #%s', 'powerhouse'),
					(string) $order_id
				)
			);
		}
		$comment_id = $order->add_order_note( (string) $note, (int) $is_customer_note, true);

		return new \WP_REST_Response(
			[
				'code'    => 'create_success',
				'message' => __('create order note success', 'powerhouse'),
				'data'    => $comment_id,
			],
		);
	}

	/**
	 * 刪除訂單備註
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 * @throws \Exception 當刪除訂單備註失敗時拋出異常
	 */
	public function delete_order_notes_with_id_callback( $request ): \WP_REST_Response {
		$id = $request['id'] ?? null;
		if (!is_numeric($id)) {
			throw new \Exception(
				sprintf(
					__('order note id format not match #%s', 'powerhouse'),
					(string) $id
				)
			);
		}
		$success = \wc_delete_order_note( (int) $id );

		return new \WP_REST_Response(
			[
				'code'    => 'delete_success',
				'message' => __('delete order note success', 'powerhouse'),
				'data'    => $success,
			]
		);
	}
}
