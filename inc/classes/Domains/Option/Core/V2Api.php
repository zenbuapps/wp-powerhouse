<?php

declare(strict_types=1);

namespace J7\Powerhouse\Domains\Option\Core;

use J7\Powerhouse\Settings\Model\Settings;
use J7\WpUtils\Classes\WP;
use J7\WpUtils\Classes\ApiBase;

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
			'endpoint'            => 'options',
			'method'              => 'get',
			'permission_callback' => null,
		],
		[
			'endpoint'            => 'options',
			'method'              => 'post',
			'permission_callback' => null,
		],
		[
			'endpoint'            => 'options/upload',
			'method'              => 'get',
			'permission_callback' => null,
		],
	];

	/** @var array<string, mixed> $fields 允許更新的欄位名稱 */
	private $fields = [
		Settings::SETTINGS_KEY => [],
	];

	/**
	 * 不需要 sanitize 的 key
	 *
	 * @var array<string>
	 */
	private $skip_sanitize_keys = [];

	/**
	 * Constructor
	 */
	public function __construct() {
		parent::__construct();

		$this->fields             = \apply_filters( 'powerhouse/option/allowed_fields', $this->fields ); // @phpstan-ignore-line
		$this->skip_sanitize_keys = \apply_filters( 'powerhouse/option/skip_sanitize_keys', $this->skip_sanitize_keys ); // @phpstan-ignore-line
	}

	/**
	 * 獲取選項
	 *
	 * @param \WP_REST_Request $request REST請求對象。
	 * @return \WP_REST_Response 返回包含選項資料的REST響應對象。
	 */
	public function get_options_callback( \WP_REST_Request $request ): \WP_REST_Response {
		$options                           = [];
		$options[ Settings::SETTINGS_KEY ] = Settings::instance()->to_array();

		return new \WP_REST_Response(
			[
				'code'    => 'get_options_success',
				'message' => '獲取選項成功',
				'data'    => \apply_filters('powerhouse/options/get_options', $options, $request),
			],
			200
		);
	}

	/**
	 * 更新選項
	 *
	 * @param \WP_REST_Request $request 包含更新選項所需資料的REST請求對象。
	 * @return \WP_REST_Response 返回包含操作結果的REST響應對象。成功時返回選項資料，失敗時返回錯誤訊息。
	 */
	public function post_options_callback( \WP_REST_Request $request ): \WP_REST_Response {
		$body_params = $request->get_json_params();

		/** @var array<string, mixed> $body_params */
		$body_params = WP::sanitize_text_field_deep( $body_params, false, $this->skip_sanitize_keys );

		$allowed_fields = array_keys( $this->fields );

		foreach ( $body_params as $key => $value ) {
			if (Settings::SETTINGS_KEY === $key) {
				/** @var array<string, mixed> $value */
				Settings::instance()->partial_update($value);
				continue;
			}
			if ( in_array( $key, $allowed_fields, true ) ) {
				\update_option( $key, $value );
			}
		}

		return new \WP_REST_Response(
			[
				'code'    => 'post_user_success',
				'message' => '修改成功',
				'data'    => $body_params,
			],
			200
			);
	}


	/**
	 * 獲取選項
	 *
	 * @deprecated 好像沒用到
	 * @param \WP_REST_Request $request REST請求對象。
	 * @return \WP_REST_Response 返回包含選項資料的REST響應對象。
	 */
	public function get_options_upload_callback( \WP_REST_Request $request ): \WP_REST_Response {
		/** @var array<string, string> $mime_types */
		$mime_types    = \get_allowed_mime_types();
		$accept_values = [];

		// 添加所有 MIME 類型
		foreach ($mime_types as $ext => $mime) {
			$accept_values[] = $mime;

			// 添加所有副檔名（以點開頭）
			$extensions = explode('|', $ext);
			foreach ($extensions as $extension) {
				$accept_values[] = '.' . $extension;
			}
		}

		// 去除重複並合併為字串
		$allowed_mime_types = implode(',', array_unique($accept_values));

		return new \WP_REST_Response(
			[
				'code'    => 'get_options_upload_success',
				'message' => '獲取上傳選項成功',
				'data'    => [
					'allowed_mime_types' => $allowed_mime_types,
				],
			],
			200
		);
	}
}
