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
	 * 保留授權碼、但目前未授權的產品紀錄（option，autoload=no）
	 *
	 * 格式 array<product_slug, array{code: string, reason: string, at: int}>
	 * transient 過期後就不知道上一次是「未授權」，所以另外記一份：
	 * 系統檢查遇到 API 暫時性錯誤時，有這筆紀錄的產品維持未授權，不會被暫存成「已啟用」
	 */
	const UNAUTHORIZED_KEY = 'powerhouse_license_codes_unauthorized';

	/** 保留授權碼的未授權狀態多久後重新檢查一次 */
	const UNAUTHORIZED_RECHECK_TTL = 6 * HOUR_IN_SECONDS;

	/** 保留超過這麼久（從第一次標記未授權起算）仍未恢復，重新檢查的間隔改成 UNAUTHORIZED_BACKOFF_TTL */
	const UNAUTHORIZED_BACKOFF_AFTER = 7 * DAY_IN_SECONDS;

	/**
	 * 長期未恢復時的重新檢查間隔
	 * 不會停止檢查：保留授權碼本來就是為了等續約，續約後要能自己恢復
	 */
	const UNAUTHORIZED_BACKOFF_TTL = 24 * HOUR_IN_SECONDS;

	/**
	 * CloudServer 判定授權失敗的 reason 中，「授權碼本身仍屬於本站、之後可能恢復」的那些
	 * → 保留已存的授權碼，只標記為未授權，恢復後下一次系統檢查自動變回已啟用
	 *
	 * 值是寫進 transient 的 post_status。不在這裡的 reason（not_found、product_mismatch、invalid_request、bound_elsewhere），
	 * 以及沒有 reason 的舊版 CloudServer，照舊刪除授權碼
	 *
	 * ⛔ bound_elsewhere（授權碼已綁到其他網域）刻意不保留：那代表客戶已搬站，保留也不會恢復；
	 * 而且本站會持續用這組碼重新檢查，一旦新網域把碼釋出成「可用」，系統檢查就會把碼綁回本站
	 */
	const RETAINED_REASONS = [
		'status_expired'     => 'expired',
		'status_deactivated' => 'deactivated',
		'status_other'       => 'unauthorized',
	];

	/**
	 * 授權碼新狀態（lc/invalidate 帶來的 status）→ 對應的 reason（不在這裡的非 available 狀態一律 status_other）
	 */
	const INVALIDATE_STATUS_REASONS = [
		'expired'     => 'status_expired',
		'deactivated' => 'status_deactivated',
	];

	/**
	 * 取得所有 licenseCode 的 array
	 * 發起檢查
	 *
	 * @return array<array{code: string, post_status: string, expire_date: string, type: string, product_slug: string, product_name: string, unauthorized_reason?: string}>
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
				// 上一次 CloudServer 已判定未授權（保留碼）→ 暫時性錯誤不可把它變成已啟用，維持未授權、6 小時後再檢查
				$unauthorized_reason = self::get_unauthorized_reason($product_slug, (string) $saved_code);
				if (null !== $unauthorized_reason) {
					$lc_array[] = self::set_unauthorized_lc_transient($default_lc, (string) $saved_code, $unauthorized_reason);
					continue;
				}

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
				// 授權碼過期／停用：碼仍是本站的，保留碼、標記未授權，恢復後自動回來
				$reason = self::get_error_reason($response);
				if (isset(self::RETAINED_REASONS[ $reason ])) {
					$lc_array[] = self::set_unauthorized_lc_transient($default_lc, (string) $saved_code, $reason);
					continue;
				}

				// 碼不存在、產品不符、請求無效、已綁定其他網域，或舊版 CloudServer 沒帶 reason → 照舊刪除授權碼
				self::delete_lc_transient($product_slug);
				$lc_array[] = $default_lc;
				continue;
			}

			// 不是 \WP_Error, 也沒有 throw，那就是 200
			// 不需要 set_lc_transient，因為 activate 裡面已經有做了
			$lc_array[] = $response;
		}

		/**
		 * @var array<array{code: string, post_status: string, expire_date: string, type: string, product_slug: string, product_name: string, unauthorized_reason?: string}> $lc_array
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
	 * 寫入「保留授權碼、但未授權」的 transient
	 *
	 * 以預設 lc 為底填入授權碼，post_status 依 reason 對應（見 RETAINED_REASONS），
	 * TTL 只有 UNAUTHORIZED_RECHECK_TTL：到期後系統檢查會再用同一組碼打 activate，成功即自動恢復成已啟用。
	 * 從第一次標記未授權起超過 UNAUTHORIZED_BACKOFF_AFTER 仍未恢復，改成 UNAUTHORIZED_BACKOFF_TTL（不分 reason）。
	 * post_status 不是 activated，所以 ia() 一律判定未授權
	 *
	 * @param array{code: string, post_status: string, expire_date: string, type: string, product_slug: string, product_name: string} $default_lc 預設 lc，見 get_default_lc()
	 * @param string                                                                                                                  $code       已存的授權碼
	 * @param string                                                                                                                  $reason     CloudServer 回的 reason，應為 RETAINED_REASONS 的 key
	 * @return array{code: string, post_status: string, expire_date: string, type: string, product_slug: string, product_name: string, unauthorized_reason: string} 寫入的 lc
	 */
	public static function set_unauthorized_lc_transient( array $default_lc, string $code, string $reason ): array {
		$lc                        = $default_lc;
		$lc['code']                = $code;
		$lc['post_status']         = self::RETAINED_REASONS[ $reason ] ?? 'unauthorized';
		$lc['unauthorized_reason'] = $reason;

		self::mark_unauthorized($lc['product_slug'], $code, $reason);
		self::set_lc_transient($lc, self::get_unauthorized_recheck_ttl($lc['product_slug']));
		return $lc;
	}

	/**
	 * 保留授權碼的未授權狀態，下一次重新檢查的間隔（退避）
	 *
	 * @param string $product_slug 產品 slug
	 * @return int 秒數
	 */
	public static function get_unauthorized_recheck_ttl( string $product_slug ): int {
		$record = self::get_unauthorized_records()[ $product_slug ] ?? null;
		if (null !== $record && $record['at'] > 0 && ( time() - $record['at'] ) >= self::UNAUTHORIZED_BACKOFF_AFTER) {
			return self::UNAUTHORIZED_BACKOFF_TTL;
		}
		return self::UNAUTHORIZED_RECHECK_TTL;
	}

	/**
	 * 從 activate() 回傳的 WP_Error 取出 CloudServer 的 reason
	 *
	 * @param \WP_Error $error activate() 回傳的 WP_Error
	 * @return string reason，舊版 CloudServer 沒帶時為空字串
	 */
	public static function get_error_reason( \WP_Error $error ): string {
		/** @var mixed $data */
		$data = $error->get_error_data();
		if (!is_array($data) || !isset($data['reason']) || !\is_string($data['reason'])) {
			return '';
		}
		return $data['reason'];
	}

	/**
	 * 取得未授權紀錄（option UNAUTHORIZED_KEY）
	 *
	 * @return array<string, array{code: string, reason: string, at: int}>
	 */
	private static function get_unauthorized_records(): array {
		$records = \get_option(self::UNAUTHORIZED_KEY, []);
		if (!is_array($records)) {
			return [];
		}

		$formatted = [];
		foreach ($records as $product_slug => $record) {
			if (!is_array($record)) {
				continue;
			}
			$formatted[ (string) $product_slug ] = [
				'code'   => (string) ( $record['code'] ?? '' ),
				'reason' => (string) ( $record['reason'] ?? '' ),
				'at'     => (int) ( $record['at'] ?? 0 ),
			];
		}
		return $formatted;
	}

	/**
	 * 記錄產品目前「保留授權碼、但未授權」
	 *
	 * 欄位 at 是這組碼第一次被標記未授權的時間（退避用）：同一組碼之後 reason 改變也不重設
	 *
	 * @param string $product_slug 產品 slug
	 * @param string $code         授權碼
	 * @param string $reason       reason，RETAINED_REASONS 的 key
	 * @return void
	 */
	public static function mark_unauthorized( string $product_slug, string $code, string $reason ): void {
		$records   = self::get_unauthorized_records();
		$record    = $records[ $product_slug ] ?? null;
		$same_code = null !== $record && $record['code'] === $code;
		if ($same_code && $record['reason'] === $reason) {
			return;
		}

		$records[ $product_slug ] = [
			'code'   => $code,
			'reason' => $reason,
			'at'     => $same_code && $record['at'] > 0 ? $record['at'] : time(),
		];
		\update_option(self::UNAUTHORIZED_KEY, $records, false);
	}

	/**
	 * 清除產品的未授權紀錄（授權碼啟用成功、或授權碼被刪除時）
	 *
	 * @param string $product_slug 產品 slug
	 * @return void
	 */
	public static function clear_unauthorized( string $product_slug ): void {
		$records = self::get_unauthorized_records();
		if (!isset($records[ $product_slug ])) {
			return;
		}
		unset($records[ $product_slug ]);
		\update_option(self::UNAUTHORIZED_KEY, $records, false);
	}

	/**
	 * 這組授權碼上一次是否被 CloudServer 判定為未授權（且保留碼）
	 *
	 * @param string $product_slug 產品 slug
	 * @param string $code         已存的授權碼
	 * @return string|null 當時的 reason；沒有紀錄、或紀錄的碼與已存的不同時為 null
	 */
	public static function get_unauthorized_reason( string $product_slug, string $code ): ?string {
		$record = self::get_unauthorized_records()[ $product_slug ] ?? null;
		if (null === $record || '' === $code || $record['code'] !== $code) {
			return null;
		}
		return isset(self::RETAINED_REASONS[ $record['reason'] ]) ? $record['reason'] : null;
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
		self::clear_unauthorized($product_slug);
		$delete_transient_result = \delete_transient("lc_{$product_slug}");
		return (bool) $delete_transient_result;
	}

	/**
	 * 只清除 LC 快取（transient），不動已存的授權碼
	 *
	 * 下一次系統檢查（Bootstrap 掛在 plugins_loaded:999 的 check_lc_array → get_lc_array）
	 * 會因 transient 不存在而以已存的授權碼重打 activate，由 CloudServer 重新判定
	 *
	 * @param string $product_slug 產品 slug
	 * @return bool 是否刪除成功
	 */
	public static function clear_lc_cache( string $product_slug ): bool {
		return (bool) \delete_transient("lc_{$product_slug}");
	}

	/**
	 * CloudServer 通知授權碼狀態改變（lc/invalidate），以授權碼本身驗證請求
	 *
	 * - 帶來的 code 與本站已存的授權碼相同 → 刪除授權碼與快取（與舊版相同）
	 *   授權碼被釋出成「可用」時，CloudServer 的 activate 會把可用的碼綁到請求的網域，
	 *   若只清快取，下一次系統檢查就會把剛釋出的碼重新綁回本站，所以必須刪
	 * - 沒帶 code、code 不符，或本站沒有已存的碼 → 只清快取，強制下次重新檢查，由 CloudServer 判定
	 *
	 * ⚠️ 前提：CloudServer（power-partner-server）已全面在 invalidate 帶 code。
	 * 舊版 CloudServer 不帶 code，對它來說釋出授權碼後舊站只會清快取，下一次系統檢查就把碼重新綁回自己
	 *
	 * code 相符時再依 status（授權碼的新狀態）區分：
	 * - `available` 或沒帶 status（舊版 CloudServer）→ 刪除授權碼（授權碼被釋出，理由同上）
	 * - 其他（expired、deactivated…）→ 只清快取、保留授權碼，並記錄未授權（見 mark_unauthorized()），
	 *   下一次系統檢查由 CloudServer 判定；恢復成已啟用後不需要重新輸入授權碼
	 *
	 * @param string $product_slug 產品 slug
	 * @param string $code         請求帶來的授權碼，空字串代表沒帶
	 * @param string $status       請求帶來的授權碼新狀態，空字串代表沒帶
	 * @return bool 快取是否刪除成功
	 */
	public static function invalidate( string $product_slug, string $code, string $status = '' ): bool {
		if ('' === $code) {
			return self::clear_lc_cache($product_slug);
		}

		$saved_code = self::get_saved_code($product_slug);

		if ('' === $saved_code) {
			return self::clear_lc_cache($product_slug);
		}

		if (\hash_equals($saved_code, $code)) {
			if ('' === $status || 'available' === $status) {
				return self::delete_lc_transient($product_slug);
			}

			// 授權碼還是本站的，只是暫時不能用：保留碼。先記下未授權，避免下一次檢查遇到 API 暫時性錯誤時被當成已啟用
			self::mark_unauthorized($product_slug, $saved_code, self::INVALIDATE_STATUS_REASONS[ $status ] ?? 'status_other');
			return self::clear_lc_cache($product_slug);
		}

		Plugin::logger(
			'lc/invalidate 帶來的授權碼與本站已存的不符，只清快取、不刪授權碼',
			'warning',
			[
				'product_slug' => $product_slug,
				'ip'           => \sanitize_text_field( \wp_unslash( (string) ( $_SERVER['REMOTE_ADDR'] ?? '' ) ) ), // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotValidated
			]
		);
		return self::clear_lc_cache($product_slug);
	}

	/**
	 * CloudServer 通知授權碼已恢復成已啟用（lc/refresh），以授權碼本身驗證請求
	 *
	 * - 帶來的 code 與本站已存的相同 → 只清快取，下一次系統檢查以已存的碼重打 activate，成功即恢復
	 * - 沒帶 code、code 不符，或本站沒有已存的碼 → 什麼都不做
	 *
	 * ⛔ 不論任何情況都不刪授權碼，也不直接把狀態改成已啟用（一律交給下一次 activate 由 CloudServer 判定）
	 *
	 * @param string $product_slug 產品 slug
	 * @param string $code         請求帶來的授權碼，空字串代表沒帶
	 * @return bool 是否已安排重新檢查（code 相符）
	 */
	public static function refresh( string $product_slug, string $code ): bool {
		if ('' === $product_slug || '' === $code) {
			return false;
		}

		$saved_code = self::get_saved_code($product_slug);
		if ('' === $saved_code || !\hash_equals($saved_code, $code)) {
			return false;
		}

		self::clear_lc_cache($product_slug);
		return true;
	}

	/**
	 * 重新檢查授權（授權頁面的「重新檢查授權」）
	 *
	 * 清除快取後重跑一次系統檢查（get_lc_array()，activate 帶 is_system_check = true），結果與定期驗證完全相同：
	 * 恢復成已啟用、繼續保留（status_*），或刪碼（released、bound_elsewhere、not_found…）。
	 * ⛔ 不可改用使用者主動啟用（is_system_check = false）：CloudServer 只有系統檢查才不綁定「可用」的碼，
	 * 用主動啟用的語意重新送出，已釋出的碼會被重新綁到本站
	 *
	 * @param string $product_slug 產品 slug
	 * @return array{code: string, post_status: string, expire_date: string, type: string, product_slug: string, product_name: string, unauthorized_reason?: string}|null 重新檢查後的授權狀態，產品未註冊時為 null
	 */
	public static function recheck( string $product_slug ): ?array {
		self::clear_lc_cache($product_slug);
		foreach (self::get_lc_array() as $lc) {
			if ($lc['product_slug'] === $product_slug) {
				return $lc;
			}
		}
		return null;
	}

	/**
	 * 取得本站已存的授權碼
	 *
	 * @param string $product_slug 產品 slug
	 * @return string 授權碼，沒有時為空字串
	 */
	private static function get_saved_code( string $product_slug ): string {
		/** @var array<string, string> $saved_codes 產品 key 和 code */
		$saved_codes = \get_option(self::KEY, []);
		$saved_codes = is_array($saved_codes) ? $saved_codes : []; // @phpstan-ignore-line
		return (string) ( $saved_codes[ $product_slug ] ?? '' );
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
			// 啟用成功，之前若因過期／停用而保留碼、標記未授權，到這裡就恢復了
			self::clear_unauthorized($product_slug);
			return $data;
		}

		// 只有「CloudServer 判定授權碼無效」的 401 才回 WP_Error，其他都直接 throw
		// 因為後續處理中 WP_Error 會掉授權，其他種類 throw 會維持原本狀態
		//
		// 401 有兩種來源，靠 body 有沒有 `code` 區分：
		// - CloudServer 的授權判定（碼無效、狀態不可啟用、網域不符）→ body 只有 `message`
		// - WP REST 的認證／權限錯誤（rest_forbidden、incorrect_password…）→ body 帶 `code`
		// 後者與授權碼本身無關，CloudServer 撤銷或更換 API 憑證時每個站都會同時收到；
		// 若照舊當成「授權無效」，所有站會在下一次系統檢查時一起掉授權、要手動重輸授權碼
		if (401 === $status_code && !empty($data['code'])) {
			throw new \Exception($data['message'] ?? 'unknown error', $status_code); // @phpstan-ignore-line
		}

		// 新版 CloudServer 在 body 帶 reason（not_found、status_expired、bound_elsewhere…），放在 WP_Error 的 data
		// 讓 get_lc_array() 決定要保留還是刪除授權碼；舊版 CloudServer 沒有 reason，reason 為空字串（照舊刪碼）
		if (401 === $status_code) {
			/** @var mixed $reason */
			$reason = $data['reason'] ?? ''; // @phpstan-ignore-line
			return new \WP_Error(
				$status_code,
				$data['message'] ?? 'unknown error',
				[
					'status' => $status_code,
					'reason' => \is_string($reason) ? $reason : '',
				]
			);
		}

		throw new \Exception($data['message'] ?? 'unknown error', $status_code); // @phpstan-ignore-line
	}

	/**
	 * 設置 LC transient
	 * 儲存 product_slug 和 code 到 option
	 *
	 * @param array{id?: int, post_status: string, code: string, type: string, expire_date: int|string, domain?: string, product_id?: int, product_slug: string, product_name: string, logs?: array<mixed>, unauthorized_reason?: string} $data 成功
	 * @param int                                                                                                                                                                                                                         $expiration transient 有效秒數，預設 CACHE_TIME
	 * @return void
	 */
	public static function set_lc_transient( array $data, int $expiration = self::CACHE_TIME ): void {
		$product_slug = $data['product_slug'];

		/**
		 * @var array<string, string> $saved_codes 產品 key 和 code
		 */
		$saved_codes                  = \get_option(self::KEY, []);
		$saved_codes                  = is_array($saved_codes) ? $saved_codes : []; // @phpstan-ignore-line
		$saved_codes[ $product_slug ] = $data['code'];

		\update_option(self::KEY, $saved_codes);

		unset($data['logs']);
		\set_transient("lc_{$product_slug}", self::encode($data), $expiration);
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
	 * @return array{message: string, local_only?: bool}|array{code: string, message: string, data: array{status: int}} $data 成功|失敗；local_only 表示只從本站移除（CloudServer 回 409）
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

		// 409：CloudServer 上這組碼目前不是已啟用（過期、停用…），無需棄用。
		// 本站的授權碼上面已經移除，仍算成功讓畫面更新；但雲端的綁定沒有變更，訊息要講清楚
		if (409 === $status_code) {
			return [
				'message'    => '授權碼已從本站移除（雲端的綁定未變更）',
				'local_only' => true,
			];
		}

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
