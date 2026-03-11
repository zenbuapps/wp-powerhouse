<?php
/**
 * ProductAttribute CRUD API
 * 可以用 filter 來 filter 參數
 */

declare(strict_types=1);

namespace J7\Powerhouse\Domains\ProductAttribute\Core;

use J7\WpUtils\Classes\WP;
use J7\WpUtils\Classes\General;
use J7\WpUtils\Classes\ApiBase;
use J7\Powerhouse\Domains\ProductAttribute\Utils\CRUD;

use J7\Powerhouse\Domains\ProductAttribute\Model\ProductAttribute;

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
	 * @var array{endpoint:string,method:string,permission_callback?: ?callable, callback?: ?callable }[]
	 */
	protected $apis = [
		[
			'endpoint'            => 'product-attributes',
			'method'              => 'get',
			'permission_callback' => null,
		],
		[
			'endpoint'            => 'product-attributes',
			'method'              => 'post',
			'permission_callback' => null,
		],
		[
			'endpoint'            => 'product-attributes/(?P<id>\d+)',
			'method'              => 'post',
			'permission_callback' => null,
		],
		[
			'endpoint'            => 'product-attributes/(?P<id>\d+)',
			'method'              => 'delete',
			'permission_callback' => null,
		],
	];

	/**
	 * Get product_attribute callback 取得 product_attribute 列表
	 *
	 * @param \WP_REST_Request $request Request.
	 *
	 * @see https://developer.wordpress.org/reference/classes/wp_term_query/
	 * @return \WP_REST_Response|\WP_Error
	 * @phpstan-ignore-next-line
	 */
	public static function get_product_attributes_callback( $request ) { // phpcs:ignore
		/** @var array<\stdClass> $attributes */
		$attributes = \wc_get_attribute_taxonomies();

		$formatted_attributes = [];
		foreach ($attributes as $attribute) {
			$formatted_attributes[] = ProductAttribute::instance( $attribute->attribute_id )->to_array();
		}

		// 按 id 升序排序
		usort($formatted_attributes, fn( $a, $b ) => (int) $a['id'] - (int) $b['id']);

		$response = new \WP_REST_Response( $formatted_attributes );

		// set pagination in header
		$response->header( 'X-WP-Total', (string) count($formatted_attributes) );
		$response->header( 'X-WP-TotalPages', (string) 1 );
		$response->header( 'X-WP-CurrentPage', (string) 1 );
		$response->header( 'X-WP-PageSize', (string) count($formatted_attributes) );

		return $response;
	}

	/**
	 * 創建 product attribute callback
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 * @throws \Exception 當新增 product attribute 失敗時拋出異常
	 */
	public static function post_product_attributes_callback( $request ): \WP_REST_Response|\WP_Error {
		$body_params = $request->get_body_params();
		WP::include_required_params( $body_params, [ 'name', 'slug' ] );
		/** @var array{name: string, slug: string, type: string, order_by: string, has_archives: bool} $body_params */
		$body_params = WP::sanitize_text_field_deep( $body_params, false );

		$result = CRUD::create_product_attribute($body_params);

		if ( !is_numeric( $result ) ) {
			return $result;
		}

		return new \WP_REST_Response(
				[
					'code'    => 'create_success',
					'message' => __('create product attribute success', 'powerhouse'),
					'data'    => $result,
				],
			);
	}


	/**
	 * 修改 product attribute
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 * @throws \Exception 當更新 product attribute 失敗時拋出異常
	 */
	public static function post_product_attributes_with_id_callback( $request ): \WP_REST_Response|\WP_Error {
		$id = $request['id'] ?? null;
		if (!is_numeric($id)) {
			throw new \Exception(
				sprintf(
				__('product attribute id format not match #%s', 'powerhouse'),
				(string) $id
			)
			);
		}

		$body_params = $request->get_body_params();
		/** @var array{id: int, name: string, slug: string, type: string, order_by: string, has_archives: bool} $body_params */
		$body_params = WP::sanitize_text_field_deep( $body_params );

		$result = CRUD::update_product_attribute( (int) $id, $body_params );

		if ( !is_numeric( $result ) ) {
			return $result;
		}

		return new \WP_REST_Response(
			[
				'code'    => 'update_success',
				'message' => __('update product attribute success', 'powerhouse'),
				'data'    => [
					'id' => $id,
				],
			]
			);
	}

	/**
	 * 刪除 product attribute
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 * @throws \Exception 當刪除 product attribute 失敗時拋出異常
	 */
	public static function delete_product_attributes_with_id_callback( $request ): \WP_REST_Response|\WP_Error {
		$id = $request['id'] ?? null;
		if (!is_numeric($id)) {
			throw new \Exception(
				sprintf(
				__('term id format not match #%s', 'powerhouse'),
				(string) $id
			)
			);
		}

		$result = CRUD::delete_product_attribute( (int) $id );

		if ( \is_wp_error( $result ) ) {
			return $result;
		}

		return new \WP_REST_Response(
			[
				'code'    => 'delete_success',
				'message' => __('delete product attribute success', 'powerhouse'),
				'data'    => [
					'id' => $id,
				],
			]
			);
	}
}
