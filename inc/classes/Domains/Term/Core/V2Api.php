<?php
/**
 * Term CRUD API
 * 可以用 filter 來 filter 參數
 */

declare(strict_types=1);

namespace J7\Powerhouse\Domains\Term\Core;

use J7\WpUtils\Classes\WP;
use J7\WpUtils\Classes\General;
use J7\WpUtils\Classes\ApiBase;
use J7\Powerhouse\Domains\Term\Utils\CRUD;
use J7\Powerhouse\Plugin;
use J7\Powerhouse\Domains\Term\Model\Term;

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
			'endpoint'            => 'terms/(?P<taxonomy>[a-zA-Z0-9_-]+)',
			'method'              => 'get',
			'callback'            => [ __CLASS__, 'get_terms_callback' ],
			'permission_callback' => null,
		],
		[
			'endpoint'            => 'terms/(?P<taxonomy>[a-zA-Z0-9_-]+)/(?P<id>\d+)',
			'method'              => 'get',
			'callback'            => [ __CLASS__, 'get_terms_with_id_callback' ],
			'permission_callback' => null,
		],
		[
			'endpoint'            => 'terms/(?P<taxonomy>[a-zA-Z0-9_-]+)',
			'method'              => 'post',
			'callback'            => [ __CLASS__, 'post_terms_callback' ],
			'permission_callback' => null,
		],
		[
			'endpoint'            => 'terms/(?P<taxonomy>[a-zA-Z0-9_-]+)/(?P<id>\d+)',
			'method'              => 'post',
			'callback'            => [ __CLASS__, 'post_terms_with_id_callback' ],
			'permission_callback' => null,
		],
		[
			'endpoint'            => 'terms/(?P<taxonomy>[a-zA-Z0-9_-]+)',
			'method'              => 'delete',
			'callback'            => [ __CLASS__, 'delete_terms_callback' ],
			'permission_callback' => null,
		],
		[
			'endpoint'            => 'terms/(?P<taxonomy>[a-zA-Z0-9_-]+)/(?P<id>\d+)',
			'method'              => 'delete',
			'callback'            => [ __CLASS__, 'delete_terms_with_id_callback' ],
			'permission_callback' => null,
		],
		[
			'endpoint'            => 'terms/(?P<taxonomy>[a-zA-Z0-9_-]+)/sort',
			'method'              => 'post',
			'callback'            => [ __CLASS__, 'post_terms_sort_callback' ],
			'permission_callback' => null,
		],
	];

	/** Constructor */
	public function __construct() {
		parent::__construct();
		\add_filter('powerhouse/term/create_term_args', [ $this, 'handle_upload' ], 10, 2);
		\add_filter('powerhouse/term/update_term_args', [ $this, 'handle_upload' ], 10, 2);
	}

	/**
	 * 處理上傳圖片
	 *
	 * @param array<string, mixed> $body_params Arguments.
	 * @param \WP_REST_Request     $request Request.

	 * @return array<string, mixed>
	 */
	public function handle_upload( array $body_params, $request ): array {
		$image_names = [ 'thumbnail_id' ];
		$file_params = $request->get_file_params();

		foreach ($image_names as $image_name) {
			if (isset($file_params[ $image_name ])) {
				try {
					$upload_results = WP::upload_files( $file_params[ $image_name ] );
					// db 儲存 image id
					$body_params[ $image_name ] = $upload_results[0]['id'];
				} catch (\Throwable $th) {
					Plugin::logger(
						'handle_upload error',
						'error',
						[
							'error_message' =>$th->getMessage(),
						]
						);
				}
			}
		}

		foreach ($image_names as $image_name) {
			// 如果前端傳 delete 過來，則刪除 db 的 image id
			if ('delete' === ( $body_params[ $image_name ] ?? '' )) {
				$body_params[ $image_name ] = '';
				continue;
			}
			// 如果不是 delete 也不是數字，那代表沒有動作，那也不用傳給 db
			if (!\is_numeric($body_params[ $image_name ])) {
				unset($body_params[ $image_name ]);
			}
		}
		return $body_params;
	}

	/**
	 * 取得 term 列表
	 * WordPress 原本的 get_terms 函數很難支援多重篩選功能，所以自己實現
	 *
	 * @param array<string, mixed> $args 參數.
	 *
	 * @return array<\WP_Term>
	 */
	private static function get_terms_by_order( array $args ): array {
		// 將 posts_per_page, paged 轉換為 number offset
		$args['number'] = (int) $args['posts_per_page'] <= 0 ? 0 : (int) $args['posts_per_page'];
		$args['offset'] = ( (int) $args['paged'] - 1 ) * (int) $args['number'];
		unset($args['posts_per_page']);
		unset($args['paged']);

		global $wpdb;
		/**
		 * Example:
		 * SELECT tt.*, COALESCE(tm.meta_value, '0') AS `order`
		 * FROM wp_term_taxonomy tt
		 * LEFT JOIN wp_termmeta tm
		 * ON tt.term_id = tm.term_id AND tm.meta_key = 'order'
		 * ORDER BY CAST(COALESCE(tm.meta_value, '0') AS SIGNED) ASC, tt.term_id DESC
		 * LIMIT 20 OFFSET 0
		 */
		// phpcs:disable
		$prepare = $wpdb->prepare(
			"SELECT tt.*, COALESCE(tm.meta_value, '0') AS `order` FROM {$wpdb->term_taxonomy} tt LEFT JOIN {$wpdb->termmeta} tm ON tt.term_id = tm.term_id AND tm.meta_key = 'order' WHERE tt.taxonomy = '%s' AND tt.parent = %d ORDER BY CAST(COALESCE(tm.meta_value, '0') AS SIGNED) ASC, tt.term_id DESC",
			(string) $args['taxonomy'],
			(int) $args['parent']
		);
		// phpcs:enable

		if ($args['number'] > 0) {
			$prepare .= $wpdb->prepare(
				' LIMIT %d OFFSET %d',
				(int) $args['number'],
				(int) $args['offset']
			);
		}

		/**
		 * @var array<object{
		 * term_taxonomy_id: string,
		 * term_id: string,
		 * taxonomy: string,
		 * description: string,
		 * parent: string,
		 * count: string,
		 * order: string,
		 * }>
		 */
		$records = $wpdb->get_results( $prepare ); // phpcs:ignore

		$terms = [];
		foreach ($records as $record) {
			$term = \get_term( (int) $record->term_id, $record->taxonomy );
			if ($term instanceof \WP_Term) {
				$terms[] = $term;
			}
			if (\is_wp_error($term)) {
				Plugin::logger(
					'get_terms_by_order error',
					'error',
					[
						'error_message' => $term->get_error_message(),
					]
					);
			}
		}

		return $terms;
	}

	/**
	 * Get terms callback 取得 term 列表
	 * 傳入 taxonomy 可以取得特定 taxonomy 的 term 列表
	 *
	 * @param \WP_REST_Request $request Request.
	 *
	 * @see https://developer.wordpress.org/reference/classes/wp_term_query/
	 * @return \WP_REST_Response|\WP_Error
	 * @phpstan-ignore-next-line
	 */
	public static function get_terms_callback( $request ) { // phpcs:ignore

		$params = $request->get_query_params();

		$params = WP::sanitize_text_field_deep( $params, false );

		$taxonomy = (string) ( $request['taxonomy'] ?? 'product_cat' );

		$default_args = [
			'taxonomy'       => $taxonomy,
			'hide_empty'     => false,
			'posts_per_page' => 20,
			'paged'          => 1,
			'parent'         => 0,
		];

		$args = \wp_parse_args(
			$params,
			$default_args,
		);

		// 將 '[]' 轉為 [], 'true' 轉為 true, 'false' 轉為 false
		$args = General::parse( $args );

		/** @var \WP_Term[] $terms */
		$terms = self::get_terms_by_order($args);

		// 取得總數
		$count_args           = $args;
		$count_args['fields'] = 'count';
		$count_args['parent'] = 0; // 只計算頂層，用頂層去做分頁
		/** @var int $total */
		$total = \get_terms($count_args);

		$total_pages = (int) $args['posts_per_page'] > 0 ? \ceil( (int) $total / (int) $args['posts_per_page']) : 1;

		$formatted_terms = [];
		foreach ($terms as $term) {
			$formatted_terms[] = Term::instance( $term );
		}

		$response = new \WP_REST_Response( $formatted_terms );

		// set pagination in header
		$response->header( 'X-WP-Total', (string) $total );
		$response->header( 'X-WP-TotalPages', (string) $total_pages );
		$current_page = $args['paged'];
		$response->header( 'X-WP-CurrentPage', (string) $current_page );
		$response->header( 'X-WP-PageSize', (string) $args['posts_per_page'] );

		return $response;
	}

	/**
	 * Get term callback
	 *
	 * @param \WP_REST_Request $request Request.
	 *
	 * @return \WP_REST_Response|\WP_Error
	 * @throws \Exception 當 term 不存在時拋出異常
	 * @phpstan-ignore-next-line
	 */
	public static function get_terms_with_id_callback( $request ) { // phpcs:ignore
		$id = $request['id'] ?? null;
		if (!is_numeric($id)) {
			throw new \Exception(
				sprintf(
				__('term id format not match #%s', 'powerhouse'),
				(string) $id
			)
			);
		}
		$params   = $request->get_query_params();
		$params   = WP::sanitize_text_field_deep( $params, false );
		$taxonomy = (string) ( $request['taxonomy'] ?? '' );

		$term = \get_term( (int) $id, $taxonomy );

		if (!$term) {
			throw new \Exception(
				sprintf(
				__('term not found #%s', 'powerhouse'),
				(string) $id
			)
			);
		}

		if (\is_wp_error($term)) {
			throw new \Exception($term->get_error_message());
		}

		if (!( $term instanceof \WP_Term )) {
			throw new \Exception(
				sprintf(
				__('term not found #%s', 'powerhouse'),
				(string) $id
			)
			);
		}

		$term_array = Term::instance( $term );

		$response = new \WP_REST_Response( $term_array );

		return $response;
	}

	/**
	 * Post term callback
	 * 批量創建 term
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 * @throws \Exception 當新增 term 失敗時拋出異常
	 * @phpstan-ignore-next-line
	 */
	public static function post_terms_callback( $request ): \WP_REST_Response|\WP_Error {
		$body_params = $request->get_body_params();
		$body_params = WP::sanitize_text_field_deep( $body_params, false );
		$taxonomy    = (string) ( $request['taxonomy'] ?? '' );

		$qty = (int) ( $body_params['qty'] ?? 1 );
		unset($body_params['qty']);

		$success_ids = [];

		/** @var array<string, mixed> $body_params */
		$body_params = \apply_filters('powerhouse/term/create_term_args', $body_params, $request);

		for ($i = 0; $i < $qty; $i++) {
			$term_id = CRUD::create_term( $taxonomy, $body_params );
			if (is_numeric($term_id)) {
				$success_ids[] = $term_id;
			} else {
				throw new \Exception($term_id->get_error_message());
			}
		}

		return new \WP_REST_Response(
				[
					'code'    => 'create_success',
					'message' => __('create term success', 'powerhouse'),
					'data'    => $success_ids,
				],
			);
	}

	/**
	 * Post term sort callback
	 * 處理排序
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function post_terms_sort_callback( $request ): \WP_REST_Response|\WP_Error {

		$body_params = $request->get_json_params();

		$body_params = WP::sanitize_text_field_deep( $body_params, false );

		$taxonomy = (string) ( $request['taxonomy'] ?? '' );

		/** @var array{from_tree: array<array{id: string}>, to_tree: array<array{id: string}>} $body_params */
		$sort_result = CRUD::sort_terms( $taxonomy, $body_params );

		if ( $sort_result !== true ) {
			return $sort_result;
		}

		return new \WP_REST_Response(
			[
				'code'    => 'sort_success',
				'message' => __('update term sort success', 'powerhouse'),
				'data'    => null,
			]
		);
	}

	/**
	 * 修改 term
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 * @throws \Exception 當更新 term 失敗時拋出異常
	 */
	public static function post_terms_with_id_callback( $request ): \WP_REST_Response|\WP_Error {
		$id = $request['id'] ?? null;
		if (!is_numeric($id)) {
			throw new \Exception(
				sprintf(
				__('term id format not match #%s', 'powerhouse'),
				(string) $id
			)
			);
		}

		$body_params = $request->get_body_params();
		$body_params = WP::sanitize_text_field_deep( $body_params );
		$taxonomy    = (string) ( $request['taxonomy'] ?? '' );

		/** @var array<string, mixed> $body_params */
		$body_params = \apply_filters('powerhouse/term/update_term_args', $body_params, $request);

		$update_result = CRUD::update_term(
				(int) $id,
				$taxonomy,
				$body_params
			);

		/** @var int|\WP_Error $update_result */
		if ( !is_numeric( $update_result ) ) {
			return $update_result;
		}

		return new \WP_REST_Response(
			[
				'code'    => 'update_success',
				'message' => __('update term success', 'powerhouse'),
				'data'    => [
					'id' => $id,
				],
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
	public static function delete_terms_callback( $request ): \WP_REST_Response|\WP_Error {

		$body_params = $request->get_json_params();

		/** @var array<string, mixed> $body_params */
		$body_params = WP::sanitize_text_field_deep( $body_params, false );

		$ids = $body_params['ids'] ?? [];
		/** @var array<string> $ids */
		$ids      = is_array( $ids ) ? $ids : [];
		$taxonomy = (string) ( $request['taxonomy'] ?? '' );

		foreach ($ids as $id) {
			CRUD::delete_term( (int) $id, $taxonomy );
		}

		return new \WP_REST_Response(
				[
					'code'    => 'delete_success',
					'message' => __('delete term data success', 'powerhouse'),
					'data'    => $ids,
				]
			);
	}

	/**
	 * Delete term callback
	 * 刪除 term
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 * @throws \Exception 當刪除 term 失敗時拋出異常
	 */
	public static function delete_terms_with_id_callback( $request ): \WP_REST_Response {
		$id = $request['id'] ?? null;
		if (!is_numeric($id)) {
			throw new \Exception(
				sprintf(
				__('term id format not match #%s', 'powerhouse'),
				(string) $id
			)
			);
		}

		$taxonomy = (string) ( $request['taxonomy'] ?? '' );

		CRUD::delete_term( (int) $id, $taxonomy );

		return new \WP_REST_Response(
			[
				'code'    => 'delete_success',
				'message' => __('delete term success', 'powerhouse'),
				'data'    => [
					'id' => $id,
				],
			]
			);
	}
}
