<?php
/**
 * Product CRUD API
 * 可以用 filter 來 filter 參數
 */

declare(strict_types=1);

namespace J7\Powerhouse\Domains\Product\Core;

use J7\WpUtils\Classes\WP;
use J7\WpUtils\Classes\WC;
use J7\WpUtils\Classes\General;
use J7\WpUtils\Classes\ApiBase;
use J7\Powerhouse\Domains\Post\Utils\CRUD as PostCRUD;
use J7\Powerhouse\Domains\Limit\Models\Limit;
use J7\Powerhouse\Domains\Limit\Models\BoundItemsData;
use J7\Powerhouse\Domains\Product\Utils\CRUD;
use J7\Powerhouse\Domains\Product\Model\Product;
use J7\Powerhouse\Domains\ProductAttribute\Utils\CRUD as AttributeCRUD;


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
			'endpoint' => 'products',
			'method'   => 'get',
		],
		[
			'endpoint' => 'products/select',
			'method'   => 'get',
		],
		[
			'endpoint' => 'products/(?P<id>\d+)',
			'method'   => 'get',
		],
		[
			'endpoint' => 'products',
			'method'   => 'post',
		],
		[
			'endpoint' => 'products/(?P<id>\d+)',
			'method'   => 'post',
		],
		[
			'endpoint' => 'products/attributes/(?P<id>\d+)',
			'method'   => 'post',
		],
		[
			'endpoint' => 'products/create-variations/(?P<id>\d+)', // 產生變體
			'method'   => 'post',
		],
		[
			'endpoint' => 'products/update-variations/(?P<id>\d+)', // 更新變體
			'method'   => 'post',
		],
		[
			'endpoint' => 'products',
			'method'   => 'delete',
		],
		[
			'endpoint' => 'products/(?P<id>\d+)',
			'method'   => 'delete',
		],
		[
			'endpoint' => 'products/options',
			'method'   => 'get',
		],
		[
			'endpoint' => 'products/bind-items', // 綁定觀看權限項目到商品上
			'method'   => 'post',
		],
		[
			'endpoint' => 'products/unbind-items', // 解除綁定觀看權限項目到商品上
			'method'   => 'post',
		],
		[
			'endpoint' => 'products/update-bound-items', // 更新綁定觀看權限項目到商品上
			'method'   => 'post',
		],
		[
			'endpoint' => 'products/update-bound-items', // 更新綁定觀看權限項目到商品上
			'method'   => 'post',
		],

		// TODO 商品排序

		// [
		// 'endpoint'            => 'products/sort',
		// 'method'              => 'post',
		// 'permission_callback' => null,
		// ],
	];

	/**
	 * Constructor.
	 */
	public function __construct() {
		parent::__construct();
		// 擴展 wc_get_products 的 meta_query
		\add_filter('woocommerce_product_data_store_cpt_get_products_query', [ CRUD::class, 'extend_meta_query' ], 10, 2,);
	}

	/**
	 * Get products callback 取得商品列表
	 *
	 * @param \WP_REST_Request $request Request.
	 *
	 * @return \WP_REST_Response|\WP_Error
	 * @phpstan-ignore-next-line
	 */
	public function get_products_callback( $request ) { // phpcs:ignore

		$params = $request->get_query_params();

		$params = WP::sanitize_text_field_deep( $params, false );

		$default_args = [
			'status'         => [ 'publish', 'draft', 'pending' ],
			'paginate'       => true,
			'posts_per_page' => 20,
			'paged'          => 1,
			'orderby'        => 'date',
			'order'          => 'DESC',
		];

		$args = \wp_parse_args(
			$params,
			$default_args,
		);

		// 將 '[]' 轉為 [], 'true' 轉為 true, 'false' 轉為 false
		$args = General::parse( $args );

		$handled   = PostCRUD::handle_args($args);
		$args      = $handled['args'];
		$meta_keys = $handled['meta_keys'];
		/** @var array<string>|null $partials */
		$partials = $handled['partials'] ?? null;

		if (isset($args['fields'])) {
			unset($args['fields']);// 確保只返回 \WC_Product
		}

		/** @var object{total:int, max_num_pages:int, products:array<int, \WC_Product>} $results */
		$results     = \wc_get_products( $args );
		$total       = $results->total;
		$total_pages = $results->max_num_pages;
		$products    = $results->products;

		$formatted_products = [];
		foreach ($products as $product) {
			$formatted_products[] = Product::instance( $product, $meta_keys )->to_array($partials);
		}
		$formatted_products = array_filter( $formatted_products );

		$response = new \WP_REST_Response( $formatted_products );

		// set pagination in header
		$response->header( 'X-WP-Total', (string) $total );
		$response->header( 'X-WP-TotalPages', (string) $total_pages );
		$response->header( 'X-WP-CurrentPage', (string) $args['paged'] );
		$response->header( 'X-WP-PageSize', (string) $args['posts_per_page'] );

		return $response;
	}

	/**
	 * Get products tree select callback
	 * 用 WP Query 而不是 wc_get_products
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function get_products_select_callback( $request ) { // phpcs:ignore

		$params = $request->get_query_params();

		$params = WP::sanitize_text_field_deep( $params, false );

		$default_args = [
			'post_status'    => [ 'publish' ],
			'post_type'      => 'product',
			'posts_per_page' => 20,
			'orderby'        => 'date',
			'order'          => 'DESC',
			'fields'         => 'ids',
		];

		$args = \wp_parse_args(
			$params,
			$default_args,
		);

		// 如果搜尋的是數字，就會查找 id
		if (is_numeric($args['s'])) {
			$args['p'] = $args['s'];
			unset($args['s']);
		}

		// 將 post__in 提取出來
		$post__in = $args['post__in'] ?? [];
		unset($args['post__in']);

		$post__in_results = new \WP_Query(
			\wp_parse_args(
			[
				'post__in' => $post__in,
			],
			$default_args,
		)
			);

		$results = new \WP_Query( $args );

		$total       = $results->found_posts;
		$total_pages = $results->max_num_pages;

		$product_ids        = [ ...$post__in_results->posts, ...$results->posts ];
		$products           = array_map(fn( $product_id ) => \wc_get_product( $product_id ), $product_ids);
		$products           = array_filter($products);
		$formatted_products = [];
		foreach ($products as $product) {
			$formatted_products[] = CRUD::format_select( $product );
		}

		$response = new \WP_REST_Response( $formatted_products );

		// set pagination in header
		$response->header( 'X-WP-Total', (string) $total );
		$response->header( 'X-WP-TotalPages', (string) $total_pages );

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
	public function get_products_with_id_callback( $request ) { // phpcs:ignore
		$id = $request['id'] ?? null;
		if (!is_numeric($id)) {
			throw new \Exception('id 格式不符合');
		}

		/** @var array<string, mixed>|null $params */
		$params = $request->get_query_params();
		$params = is_array($params) ? $params : [];
		/** @var array<string, mixed> $params */
		$params = WP::sanitize_text_field_deep( $params, false );

		// 將 '[]' 轉為 [], 'true' 轉為 true, 'false' 轉為 false
		$params = General::parse( $params );

		$handled   = PostCRUD::handle_args($params);
		$meta_keys = $handled['meta_keys'];
		/** @var array<string>|null $partials */
		$partials = $handled['partials'] ?? null;

		$product = \wc_get_product( (int) $id );
		if (!$product) {
			throw new \Exception(
				sprintf(
				__('product not found #%s', 'powerhouse'),
				(string) $id
				)
				);
		}

		$product_array = Product::instance( $product, $meta_keys )->to_array($partials);

		$response = new \WP_REST_Response( $product_array );

		return $response;
	}


	/**
	 * 處理並分離產品資訊
	 *
	 * 根據請求分離產品資訊，並處理描述欄位。
	 *
	 * @param \WP_REST_Request $request 包含產品資訊的請求對象。
	 * @param bool             $require_id 是否需要 id
	 * @throws \Exception 當找不到商品時拋出異常。.
	 * @return array{product: \WC_Product|null, data: array<string, mixed>, meta_data: array<string, mixed>} 包含產品對象、資料和元數據的陣列。
	 */
	private function separator( $request, $require_id = true ): array {
		$product = null; // 初始值，下面會判斷是否需要 id 塞入 product
		if ($require_id) {
			$id = $request['id'] ?? null;
			if (!is_numeric($id)) {
				throw new \Exception(
				sprintf(
				__('product id format not match #%s', 'powerhouse'),
				(string) $id
				)
				);
			}

			$product = \wc_get_product( (int) $id );
			if (!$product) {
				throw new \Exception(
				sprintf(
				__('product not found #%s', 'powerhouse'),
				(string) $id
				)
				);
			}
		}

		$body_params = $request->get_body_params();
		$file_params = $request->get_file_params();

		// 將前端傳過來的欄位轉換成 wp_update_post 能吃的參數
		// $body_params = CRUD::converter( $body_params );

		$skip_keys = [
			'description',
			'short_description',
			'slug',
		];
		/** @var array<string, mixed> $body_params 過濾字串，防止 XSS 攻擊 */
		$body_params = WP::sanitize_text_field_deep($body_params, true, $skip_keys);

		// 將 '[]' 轉為 [], 'true' 轉為 true, 'false' 轉為 false
		$body_params = General::parse( $body_params );

		/** @var array<string, mixed> $body_params */
		$body_params = \apply_filters('powerhouse/product/separator_body_params', $body_params, $request);

		$separated_data = WP::separator( $body_params, 'product', $file_params['images'] ?? [] );

		$separated_data['product'] = $product;

		return $separated_data;
	}

	/**
	 * Post post callback
	 * 批量創建 或 批量更新 商品 (用 action 來區分，有帶 action update-many 就是批量更新)
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 * @throws \Exception 當新增商品失敗時拋出異常
	 */
	public function post_products_callback( $request ): \WP_REST_Response|\WP_Error {

		$body_params = $request->get_body_params();

		if (( $body_params['action'] ?? '' ) === 'update-many') {
			return $this->update_many( $request );
		}

		return $this->create_many( $request );
	}


	/**
	 * 批量創建
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 * @throws \Exception 當新增商品失敗時拋出異常
	 */
	private function create_many( $request ): \WP_REST_Response {
		[
				'data'      => $data,
				'meta_data' => $meta_data,
			] = $this->separator( $request, false );

		$qty = (int) ( $meta_data['qty'] ?? 1 );
		unset($meta_data['qty']);

		$success_ids = [];

		for ($i = 0; $i < $qty; $i++) {
			$product_id    = CRUD::create_product( $data, $meta_data );
			$success_ids[] = $product_id;
		}

		return new \WP_REST_Response(
				[
					'code'    => 'create_success',
					'message' => __('create products success', 'powerhouse'),
					'data'    => $success_ids,
				],
			);
	}

	/**
	 * 批量更新
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 * @throws \Exception 當新增商品失敗時拋出異常
	 * @phpstan-ignore-next-line
	 */
	private function update_many( $request ): \WP_REST_Response|\WP_Error {
		[
				'data'      => $data,
				'meta_data' => $meta_data,
			] = $this->separator( $request, false );

		// action, ids 不用存入 db
		$ids = $meta_data['ids'] ?? [];
		$ids = is_array($ids) ? $ids : [];
		unset($meta_data['action']);
		unset($meta_data['ids']);

		if (!$ids) {
			throw new \Exception( __('ids is required', 'powerhouse') );
		}

		$success_ids = [];

		foreach ($ids as $id) {
			$product = \wc_get_product( (int) $id );
			if (!$product) {
				continue;
			}
			CRUD::update_product( $product, $data, $meta_data );
			$success_ids[] = $id;
		}

		return new \WP_REST_Response(
				[
					'code'    => 'update_success',
					'message' => __('update products success', 'powerhouse'),
					'data'    => $success_ids,
				],
			);
	}

	/**
	 * Patch post callback
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 * @throws \Exception 當更新文章失敗時拋出異常
	 * @phpstan-ignore-next-line
	 */
	public function post_products_with_id_callback( $request ): \WP_REST_Response|\WP_Error {

		[
				'product' => $product,
				'data'      => $data,
				'meta_data' => $meta_data,
			] = $this->separator( $request );

		/** @var \WC_Product $product */
		CRUD::update_product( $product, $data, $meta_data );

		return new \WP_REST_Response(
			[
				'code'    => 'update_success',
				'message' => __('update product success', 'powerhouse'),
				'data'    => [
					'id' => $product->get_id(),
				],
			]
			);
	}


	/**
	 * 更新商品屬性
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 * @throws \Exception 當更新文章失敗時拋出異常
	 * @phpstan-ignore-next-line
	 */
	public function post_products_attributes_with_id_callback( $request ): \WP_REST_Response|\WP_Error {

		$id = $request['id'] ?? null;
		if (!is_numeric($id)) {
			throw new \Exception(
				sprintf(
				__('product id format not match #%s', 'powerhouse'),
				(string) $id
			)
			);
		}

		$product = \wc_get_product( (int) $id );
		if (!$product) {
			throw new \Exception(
				sprintf(
				__('product not found #%s', 'powerhouse'),
				(string) $id
			)
			);
		}

		$body_params = $request->get_body_params();
		// TODO 待確認 因為會把 中文的 url encode 過濾掉，就不 sanitize 了
		$body_params = WP::sanitize_text_field_deep( $body_params, false );

		/** @var array<array<string, mixed>> $new_attributes */
		$new_attributes = $body_params['new_attributes'] ?? [];

		$attributes = [];

		foreach ($new_attributes as $index => $new_attribute) {

			$attribute_id = $new_attribute['id'] ?? null;
			$name         = (string) ( $new_attribute['name'] ?? '' );
			$options      = is_array($new_attribute['options'] ?? null) ? $new_attribute['options'] : [];
			$taxonomy     = (string) ( $new_attribute['taxonomy'] ?? '' );
			$is_taxonomy  = (string) ( $new_attribute['is_taxonomy'] ?? '' ); // 創建為全局屬性
			$position     = $new_attribute['position'] ?? 0;
			$visible      = (string) ( $new_attribute['visible'] ?? '' );
			$variation    = (string) ( $new_attribute['variation'] ?? '' );

			// ----- ▼ 如果是創建新的全局屬性，則需要先創建屬性，再將屬性 id 塞入 $attribute_id ----- //
			if (\wc_string_to_bool($is_taxonomy)) {
				$created_attribute_id = AttributeCRUD::create_product_attribute(
					[
						'name'         => $name,
						'slug'         => $taxonomy,
						'type'         => 'select',
						'order_by'     => 'menu_order',
						'has_archives' => \wc_string_to_bool($visible),
					]
					);
				if (\is_wp_error($created_attribute_id)) {
					throw new \Exception($created_attribute_id->get_error_message());
				}
				$attribute_id = $created_attribute_id;
				$taxonomy     = "pa_{$taxonomy}"; // 複寫掉 taxonomy，如果不是 pa_ 開頭，會無法加入到商品屬性
				// 手動添加terms
				foreach ($options as $option) {
					$insert_term = \wp_insert_term( (string) $option, $taxonomy);
					if (\is_wp_error($insert_term)) {
						throw new \Exception($insert_term->get_error_message());
					}
				}
			}

			$attribute = new \WC_Product_Attribute();
			if ($attribute_id) {
				$attribute->set_id( (int) $attribute_id);
			}
			$attribute->set_name( (string) ( $taxonomy ?: $name )); // 如果 taxonomy 有值，則使用 taxonomy，否則使用 name

			$attribute->set_options( (array) $options);
			$attribute->set_position( (int) $position);
			$attribute->set_visible(\wc_string_to_bool($visible));
			$attribute->set_variation(\wc_string_to_bool($variation));
			$attributes[] = $attribute;
		}

		$product->set_attributes( $attributes );
		$product->save();

		return new \WP_REST_Response(
			[
				'code'    => 'update_attributes_success',
				'message' => __('update product attributes success', 'powerhouse'),
				'data'    => [
					'id' => $product->get_id(),
				],
			]
			);
	}


	/**
	 * 產生變體商品
	 *
	 * @see WC_AJAX::link_all_variations
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 * @throws \Exception 當更新文章失敗時拋出異常
	 * @phpstan-ignore-next-line
	 */
	public function post_products_create_variations_with_id_callback( $request ): \WP_REST_Response|\WP_Error {

		$id = $request['id'] ?? null;
		if (!is_numeric($id)) {
			throw new \Exception(
				sprintf(
				__('product id format not match #%s', 'powerhouse'),
				(string) $id
			)
			);
		}

		$product = \wc_get_product( (int) $id );
		if (!$product) {
			throw new \Exception(
				sprintf(
				__('product not found #%s', 'powerhouse'),
				(string) $id
			)
			);
		}
		\wc_set_time_limit( 0 );

		/** @var array<string,array<string>> $attributes 用於變體的屬性 */
		$attributes = \wc_list_pluck( array_filter( $product->get_attributes(), 'wc_attributes_array_filter_variation' ), 'get_slugs' );

		/** @var array<array<string, string>> $possible_attributes 所有可能的屬性 */
		$possible_attributes = array_reverse( \wc_array_cartesian( $attributes ) );

		/** @var array<\WC_Product_Variable> $existing_variations 現有的變體 */
		$existing_variations = array_map( 'wc_get_product', $product->get_children() );

		/** @var array<array<string, string>> $existing_attributes 所有現有的屬性 */
		$existing_attributes = [];

		$deleted_variation_ids = [];

		foreach ( $existing_variations as $existing_variation ) {
			/** @var array<string, string> $existing_attribute 當前變體的屬性 */
			$existing_attribute = $existing_variation->get_attributes();

			// 如果目前變體已經重複存在於 $existing_attributes 裡面，則刪除變體
			if (\in_array($existing_attribute, $existing_attributes, true)) {
				$deleted_variation_ids[] = $existing_variation->get_id();
				$existing_variation->delete(true);
				continue;
			}

			// 如果目前變體存在於 possible_attributes 中，就加入到 existing_attributes，不然就刪除變體商品
			if (\in_array($existing_attribute, $possible_attributes, true)) {
				$existing_attributes[] = $existing_attribute;
			} else {
				$deleted_variation_ids[] = $existing_variation->get_id();
				$existing_variation->delete(true);
			}
		}

		$created_variation_ids = [];

		// TODO 可以分批

		foreach ( $possible_attributes as $possible_attribute ) {
			// Allow any order if key/values -- do not use strict mode.
			if ( in_array( $possible_attribute, $existing_attributes ) ) { // phpcs:ignore WordPress.PHP.StrictInArray.MissingTrueStrict
				continue;
			}
			/** @var \WC_Product_Variation $variation */
			$variation = \wc_get_product_object( 'variation' );
			$variation->set_props( [] );
			$variation->set_parent_id( $product->get_id() );
			$variation->set_attributes( $possible_attribute );
			$variation_id            = $variation->save();
			$created_variation_ids[] = $variation_id;
			\do_action( 'product_variation_linked', $variation_id );
		}

		return new \WP_REST_Response(
			[
				'code'    => 'update_attributes_success',
				'message' => __('update product attributes success', 'powerhouse'),
				'data'    => [
					'created_variation_ids' => $created_variation_ids,
					'deleted_variation_ids' => $deleted_variation_ids,
				],
			]
			);
	}

	/**
	 * 產生變體商品
	 *
	 * @see WC_AJAX::link_all_variations
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 * @throws \Exception 當更新文章失敗時拋出異常
	 * @phpstan-ignore-next-line
	 */
	public function post_products_update_variations_with_id_callback( $request ): \WP_REST_Response|\WP_Error {

		$id = $request['id'] ?? null;
		if (!is_numeric($id)) {
			throw new \Exception(
				sprintf(
				__('product id format not match #%s', 'powerhouse'),
				(string) $id
			)
			);
		}

		$product = \wc_get_product( (int) $id );
		if (!$product) {
			throw new \Exception(
				sprintf(
				__('product not found #%s', 'powerhouse'),
				(string) $id
			)
			);
		}

		$body_params        = $request->get_body_params();
		$default_attributes = $body_params['default_attributes'] ?? [];

		$product->set_default_attributes( $default_attributes );
		$product->save();

		$variations = $body_params['variations'] ?? [];

		foreach ($variations as $variation_data) {
			$variation_id = $variation_data['id'] ?? null;
			unset($variation_data['id']);

			$variation_product = \wc_get_product( (int) $variation_id );
			if (!$variation_product) {
				continue;
			}

			[
				'data' => $data,
				'meta_data' => $meta_data,
			] = WP::separator( $variation_data, 'product' );

			CRUD::update_product( $variation_product, $data, $meta_data );
		}

		return new \WP_REST_Response(
			[
				'code'    => 'update_variations_success',
				'message' => \__('update product variations success', 'powerhouse'),
				'data'    => [],
			]
			);
	}

	/**
	 * 批量刪除文章資料
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 * @throws \Exception 當刪除文章資料失敗時拋出異常
	 * @phpstan-ignore-next-line
	 */
	public function delete_products_callback( $request ): \WP_REST_Response|\WP_Error {

		$body_params = $request->get_json_params();

		/** @var array<string, mixed> $body_params */
		$body_params = WP::sanitize_text_field_deep( $body_params, false );

		$ids = $body_params['ids'] ?? [];
		/** @var array<string> $ids */
		$ids          = is_array( $ids ) ? $ids : [];
		$force_delete = $body_params['force_delete'] ?? false;

		foreach ($ids as $id) {
			$product = \wc_get_product( (int) $id );

			if (!$product) {
				throw new \Exception(
					sprintf(
					__('product not found #%s', 'powerhouse'),
					$id
				)
				);
			}

			$result = $product->delete( (bool) $force_delete );
			if (!$result) {
				throw new \Exception(
					sprintf(
					__('delete product failed #%s', 'powerhouse'),
					$id
				)
				);
			}
		}

		return new \WP_REST_Response(
				[
					'code'    => 'delete_success',
					'message' => __('delete product success', 'powerhouse'),
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
	public function delete_products_with_id_callback( $request ): \WP_REST_Response {
		$id = $request['id'] ?? null;
		if (!is_numeric($id)) {
			throw new \Exception(
				sprintf(
				__('product id format not match #%s', 'powerhouse'),
				(string) $id
			)
			);
		}

		$product = \wc_get_product( (int) $id );

		if (!$product) {
			throw new \Exception(
				sprintf(
				__('product not found #%s', 'powerhouse'),
				(string) $id
			)
			);
		}

		$body_params  = $request->get_json_params();
		$force_delete = $body_params['force_delete'] ?? false;
		$result       = $product->delete( (bool) $force_delete );
		if (!$result) {
			throw new \Exception(
				sprintf(
				__('delete product failed #%s', 'powerhouse'),
				(string) $id
			)
			);
		}

		return new \WP_REST_Response(
			[
				'code'    => 'delete_success',
				'message' => __('delete product success', 'powerhouse'),
				'data'    => [
					'id' => $id,
				],
			]
			);
	}


	/**
	 * Get options callback
	 *
	 * @param \WP_REST_Request $request Request.
	 *
	 * @return \WP_REST_Response<array{
	 *  product_cats: array{id: string, name: string, slug: string}[],
	 *  product_tags: array{id: string, name: string, slug: string}[],
	 *  top_sales_products: array{id: string, name: string, slug: string}[],
	 *  max_price: float,
	 *  min_price: float,
	 * ...
	 * }>
	 * @phpstan-ignore-next-line
	 */
	public function get_products_options_callback( $request ) { // phpcs:ignore
		$formatted_cats = PostCRUD::get_term_options(
			[
				'taxonomy' => 'product_cat',
			]
			);
		$formatted_tags = PostCRUD::get_term_options(
			[
				'taxonomy' => 'product_tag',
			]
			);

		$formatted_shipping_classes = PostCRUD::get_term_options(
			[
				'taxonomy' => 'product_shipping_class',
			]
			);

		$top_sales_products = WC::get_top_sales_products( 5 );

		[
			'max_price' => $max_price,
			'min_price' => $min_price,
		] = CRUD::get_max_min_prices();

		/** @var array{
		 *  product_cats: array{value: string, label: string}[],
		 *  product_tags: array{value: string, label: string}[],
		 *  product_shipping_classes: array{value: string, label: string}[],
		 *  top_sales_products: array{id: string, name: string, slug: string}[],
		 *  max_price: float,
		 *  min_price: float,
		 * ...
		 * } $options
		*/
		$options = \apply_filters(
			'powerhouse/product/get_options',
			[
				'product_cats'             => $formatted_cats,
				'product_tags'             => $formatted_tags,
				'product_shipping_classes' => $formatted_shipping_classes,
				'top_sales_products'       => $top_sales_products,
				'max_price'                => $max_price,
				'min_price'                => $min_price,
			],
			$request
			);

		return new \WP_REST_Response($options);
	}

	/**
	 * 綁定項目到商品上
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 * @throws \Exception 當綁定項目失敗時拋出異常
	 */
	public function post_products_bind_items_callback( $request ) {
		$body_params = $request->get_body_params();
		WP::include_required_params( $body_params, [ 'product_ids', 'item_ids', 'limit_type', 'meta_key' ] );

		$body_params = WP::sanitize_text_field_deep( $body_params );

		/** @var array{product_ids: array<int|string>, item_ids: array<int|string>, limit_type: string, limit_value: int|null, limit_unit: string, meta_key: string} $body_params */
		$product_ids = $body_params['product_ids'];
		$item_ids    = $body_params['item_ids'];
		$limit       = new Limit( $body_params['limit_type'], (int) $body_params['limit_value'], $body_params['limit_unit'] );

		$meta_key = $body_params['meta_key'];

		foreach ($product_ids as $product_id) {
			$bind_items_data_instance = new BoundItemsData( (int) $product_id, $meta_key );

			foreach ($item_ids as $item_id) {
				$bind_items_data_instance->add_item_data(
				(int) $item_id,
				$limit
				);
			}
			$bind_items_data_instance->save();
		}

		return new \WP_REST_Response(
			[
				'code'    => 'success',
				'message' => '綁定成功',
				'data'    => [
					'product_ids' => $product_ids,
					'item_ids'    => $item_ids,
				],
			]
			);
	}


	/**
	 * 更新已綁定項目權限到商品上
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 * @throws \Exception 當更新項目失敗時拋出異常
	 * @phpstan-ignore-next-line
	 */
	public function post_products_update_bound_items_callback( $request ) {
		$body_params = $request->get_body_params();

		WP::include_required_params( $body_params, [ 'product_ids', 'item_ids', 'limit_type', 'meta_key' ] );

		$body_params = WP::sanitize_text_field_deep( $body_params );

		/** @var array{product_ids: array<int|string>, item_ids: array<int|string>, limit_type: string, limit_value: int|null, limit_unit: string, meta_key: string} $body_params */
		$product_ids = $body_params['product_ids'];
		$item_ids    = $body_params['item_ids'];
		$limit       = new Limit( $body_params['limit_type'], (int) $body_params['limit_value'], $body_params['limit_unit'] );
		$meta_key    = $body_params['meta_key'];

		foreach ($product_ids as $product_id) {
			$bind_items_data_instance = new BoundItemsData( (int) $product_id, $meta_key);
			foreach ($item_ids as $item_id) {
				$bind_items_data_instance->update_item_data( (int) $item_id, $limit );
			}
			$bind_items_data_instance->save();
		}

		return new \WP_REST_Response(
			[
				'code'    => 'success',
				'message' => '修改成功',
				'data'    => [
					'product_ids' => $product_ids,
					'item_ids'    => $item_ids,
				],
			]
			);
	}



	/**
	 * 解除綁定項目到商品上
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 * @throws \Exception 當解除綁定失敗時拋出異常
	 */
	public function post_products_unbind_items_callback( $request ) {

		$body_params = $request->get_body_params();

		WP::include_required_params( $body_params, [ 'product_ids', 'item_ids', 'meta_key' ] );

		$body_params = WP::sanitize_text_field_deep( $body_params );

		/** @var array{product_ids: array<int|string>, item_ids: array<int|string>, meta_key: string} $body_params */
		$product_ids = $body_params['product_ids'];
		$item_ids    = $body_params['item_ids'];
		$meta_key    = $body_params['meta_key'];

		foreach ($product_ids as $product_id) {
			$bind_items_data_instance = new BoundItemsData( (int) $product_id, $meta_key );
			foreach ($item_ids as $item_id) {
				$bind_items_data_instance->remove_item_data( (int) $item_id );
			}
			$bind_items_data_instance->save();
			$success_ids[] = $product_id;
		}

		return new \WP_REST_Response(
			[
				'code'    => 'success',
				'message' => '解除綁定成功',
				'data'    => [
					'product_ids' => $product_ids,
					'item_ids'    => $item_ids,
				],
			],
			200
			);
	}
}
