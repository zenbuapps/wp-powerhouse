<?php
/**
 * LicenseCodes
 * 1. 先用 powerhouse_product_names filter hook 註冊 product_slug 和 product_name
 * 2. 每個 transient 會記錄各個 product 的授權狀態，加密後存為 string
 */

declare (strict_types = 1);

namespace J7\Powerhouse\Domains\LC\Utils;

use J7\Powerhouse\Plugin;
use J7\WpUtils\Classes\General;
use Nullix\JsAesPhp\JsAesPhp;
use J7\Powerhouse\Api;

if ( class_exists( 'J7\Powerhouse\Domains\LC\Utils\Base' ) ) {
	return;
}
/**
 * Class Base
 */
class Base {

	const KEY        = 'powerhouse_license_codes';
	const CACHE_TIME = 24 * HOUR_IN_SECONDS;

	/**
	 * 取得所有 licenseCode 的 array
	 * 發起檢查
	 *
	 * @return array<array{code: string, post_status: string, expire_date: string, type: string, product_slug: string, product_name: string}>
	 */
	public static function get_lc_array(): array {

		/**
		 * @var array<string, array{name?: string, link?: string}>  key,info
		 */
		$product_infos = \apply_filters( 'powerhouse_product_infos', [] );
		// 存在 db 的 product_slug 和 code
		/**
		 * @var array<string, string> $saved_codes 產品 key 和 code
		 */
		$saved_codes = \get_option(self::KEY, []);
		$saved_codes = is_array($saved_codes) ? $saved_codes : []; // @phpstan-ignore-line

		$lc_array = [];

		foreach ( $product_infos as $product_slug => $product_info ) {
			$saved_code   = $saved_codes[ $product_slug ] ?? '';
			$lc           = \get_transient("lc_{$product_slug}");
			$product_name = $product_info['name'] ?? '';
			if (!$product_name) {
				continue;
			}

			if (false !== $lc) {
				$decoded = self::decode( (string) $lc);
				// 如果 transient 存在，則直接新增到 $lc_array
				$lc_array[] = $decoded;
				continue;
			}

			$default_lc = self::get_default_lc($product_slug, $product_name, $product_info);
			// 如果 transient 不存在|過期，且 saved_code 不存在，則新增預設的 transient
			if (!$saved_code ) {
				self::delete_lc_transient($product_slug);
				$lc_array[] = $default_lc;
				continue;
			}

			// 如果 transient 不存在|過期，且 saved_code 存在，則重新發 API 獲取
			try {
				$response = self::activate( (string) $saved_code, $product_slug, true);
			} catch (\Throwable $th) {
				// 如果 API 啟用請求有問題，維持啟用狀態
				$default_lc['code']        = $saved_code;
				$default_lc['post_status'] = 'activated';
				$lc_array[]                = $default_lc;
				// 失敗的話，就先暫存一個預設啟用值，等於說跳過這次，下次到期再檢查
				self::set_lc_transient($default_lc);
				// 失敗不清除原本的 saved_code
				// self::delete_lc_transient($product_slug);
				continue;
			}

			// 如果啟用回得是 401 ，則使用預設的狀態
			if ( \is_wp_error( $response ) ) {
				self::delete_lc_transient($product_slug);
				$lc_array[] = $default_lc;
				continue;
			}

			// 不是 \WP_Error, 也沒有 throw，那就是 200
			// 不需要 set_lc_transient，因為 activate 裡面已經有做了
			$lc_array[] = $response;
		}

		/**
		 * @var array<array{code: string, post_status: string, expire_date: string, type: string, product_slug: string, product_name: string}> $lc_array
		 */
		return $lc_array;
	}

	/**
	 * 解密
	 *
	 * @param string $value 加密後的 string
	 * @return array{code: string, post_status: string, expire_date: string, type: string, product_slug: string, product_name: string}|false 單個授權狀態，false 表示解密失敗
	 */
	public static function decode( string $value ): array|false {

		try {
			/**
		 * @var array{code: string, post_status: string, expire_date: string, type: string, product_slug: string, product_name: string} $lc_status
		 */
			$lc_status = JsAesPhp::decrypt($value, Plugin::$kebab);

			if (!is_array($lc_status)) { // @phpstan-ignore-line
				return false;
			}

			return $lc_status;
		} catch ( \Exception $e ) {
			\J7\WpUtils\Classes\WC::log(
					[
						'getMessage' => $e->getMessage(),
						'value'      => $value,
					],
					'LC::decode error'
				);
			return false;
		}
	}

	/**
	 * 設置預設的 transient
	 *
	 * @param string               $product_slug 產品 key
	 * @param string               $product_name 產品名稱
	 * @param array{link?: string} $product_info 產品資訊
	 * @return array{code: string, post_status: string, expire_date: string, type: string, product_slug: string, product_name: string} 單個授權狀態
	 */
	public static function get_default_lc( string $product_slug, string $product_name, array $product_info ): array {
		$default_lc = [
			'code'        => '',
			'post_status' => '',
			'expire_date' => '',
			'type'        => '',
		];

		$lc                 = $default_lc;
		$lc['product_slug'] = $product_slug;
		$lc['product_name'] = $product_name;
		$lc['link']         = $product_info['link'] ?? '';
		return $lc;
	}

	/**
	 * 刪除 LC transient
	 * 刪除 product_slug 和 code 到 option
	 *
	 * @param string $product_slug 產品 slug
	 * @return bool 是否刪除成功
	 */
	public static function delete_lc_transient( string $product_slug ): bool {
		/** @var array<string, string> $saved_codes 產品 key 和 code */
		$saved_codes = \get_option(self::KEY, []);
		$saved_codes = is_array($saved_codes) ? $saved_codes : []; // @phpstan-ignore-line

		// TEST 印出 WC Logger 記得移除 追查 call stack 用 ---- //
		// $trace     = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 5); // 只看5層
		// $functions = array_map( fn ( $t ) => $t['function'], $trace );
		// \J7\WpUtils\Classes\WC::log(
		// [
		// 'functions'    => $functions,
		// 'product_slug' => $product_slug,
		// 'saved_codes'  => $saved_codes,
		// ],
		// 'debug_backtrace delete_lc_transient'
		// );
		// -------------------- END TEST ------------------- //

		unset($saved_codes[ $product_slug ]);
		\update_option(self::KEY, $saved_codes);
		$delete_transient_result = \delete_transient("lc_{$product_slug}");
		return (bool) $delete_transient_result;
	}

	/**
	 * 啟用授權碼
	 *
	 * @param string $code 授權碼
	 * @param string $product_slug 產品 key
	 * @param bool   $is_system_check 是否為系統檢查
	 * @return array{id: int, post_status: string, code: string, type: string, expire_date: int, domain: string, product_id: int, product_slug: string, product_name: string}|array{code: string, message: string, data: array{status: int}}|\WP_Error $data 成功|失敗，401 回傳 \WP_Error
	 * @throws \Exception API 發送失敗，或非 200 & 401，則拋出例外。
	 */
	public static function activate( string $code, string $product_slug, bool $is_system_check = false ): array|\WP_Error {
		$api      = Api\Base::instance();
		$endpoint = 'license-codes/activate';

		$params = [
			'code'            => $code,
			'product_slug'    => $product_slug,
			'is_system_check' => $is_system_check,
		];
		if ($is_system_check) {
			$params['post_status'] = [ 'available', 'activated' ];
		}
		$response = $api->remote_post(
			$endpoint,
			$params
		);

		if (\is_wp_error($response)) {
			throw new \Exception($response->get_error_message());
		}

		$body = \wp_remote_retrieve_body($response);

		/**
		 * @var array{id: int, post_status: string, code: string, type: string, expire_date: int, domain: string, product_id: int, product_slug: string, product_name: string}|array{code: string, message: string, data: array{status: int}} $data 成功|失敗
		 */
		$data = General::json_parse($body, []);

		// get header status code
		$status_code = \wp_remote_retrieve_response_code($response);

		if (200 === $status_code) {
			// @phpstan-ignore-next-line
			self::set_lc_transient($data);
			return $data;
		}

		// 只有 401 才回 WP_Error，其他都直接 throw
		// 因為後續處理中 WP_Error 會掉授權，其他種類 throw 會維持原本狀態
		if (401 === $status_code) {
			return new \WP_Error($status_code, $data['message'] ?? 'unknown error');
		}

		throw new \Exception($data['message'] ?? 'unknown error', $status_code); // @phpstan-ignore-line
	}

	/**
	 * 設置 LC transient
	 * 儲存 product_slug 和 code 到 option
	 *
	 * @param array{id?: int, post_status: string, code: string, type: string, expire_date: int|string, domain?: string, product_id?: int, product_slug: string, product_name: string, logs?: array<mixed>} $data 成功
	 * @return void
	 */
	public static function set_lc_transient( array $data ): void {
		$product_slug = $data['product_slug'];

		/**
		 * @var array<string, string> $saved_codes 產品 key 和 code
		 */
		$saved_codes                  = \get_option(self::KEY, []);
		$saved_codes                  = is_array($saved_codes) ? $saved_codes : []; // @phpstan-ignore-line
		$saved_codes[ $product_slug ] = $data['code'];

		\update_option(self::KEY, $saved_codes);

		unset($data['logs']);
		\set_transient("lc_{$product_slug}", self::encode($data), self::CACHE_TIME);
	}

	/**
	 * 加密函數
	 *
	 * @param array{id?: int, post_status: string, code: string, type: string, expire_date: int|string, domain?: string, product_id?: int, product_slug: string, product_name: string} $license_code 單個授權狀態
	 * @return string 加密後的 string
	 */
	public static function encode( array $license_code ): string {
		return JsAesPhp::encrypt($license_code, Plugin::$kebab, 1);
	}

	/**
	 * 棄用授權碼
	 *
	 * @param string $code 授權碼
	 * @param string $product_slug 產品 key
	 * @return array{message: string}|array{code: string, message: string, data: array{status: int}} $data 成功|失敗
	 * @throws \Exception 如果棄用失敗，則拋出例外。
	 */
	public static function deactivate( string $code, string $product_slug = '' ): array {
		$api      = Api\Base::instance();
		$endpoint = 'license-codes/deactivate';
		$response = $api->remote_post(
			$endpoint,
			[
				'code' => $code,
			]
			);

		if (\is_wp_error($response)) {
			throw new \Exception($response->get_error_message());
		}

		$body = \wp_remote_retrieve_body($response);

		/**
		 * @var array{message: string}|array{code: string, message: string, data: array{status: int}} $data 成功|失敗
		 */
		$data = General::json_parse($body, []);

		// get header status code
		$status_code = \wp_remote_retrieve_response_code($response);

		// TEST 印出 WC Logger 記得移除 ---- //
		\J7\WpUtils\Classes\WC::log($product_slug, 'delete_lc_transient from deactivate');
		// ---------- END TEST ---------- //
		self::delete_lc_transient($product_slug);

		if (200 !== $status_code) {
			throw new \Exception($data['message'] ?? 'unknown error'); // @phpstan-ignore-line
		}

		return $data;
	}

	/**
	 * @param string $product_slug 產品 key
	 * @return bool ia
	 */
	public static function ia( string $product_slug ): bool {
		$lc_string = \get_transient("lc_{$product_slug}");

		if (false !== $lc_string) {
			$lc = self::decode( (string) $lc_string);

			if (!is_array($lc)) {
				return false;
			}
			if ('activated' === ( $lc['post_status'] ?? '' )) { // @phpstan-ignore-line
				return true;
			}
		}
		return false;
	}
}
