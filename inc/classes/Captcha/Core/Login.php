<?php

declare(strict_types=1);

namespace J7\Powerhouse\Captcha\Core;

use J7\Powerhouse\Settings\Model\Settings;

/**
 * Class 登入驗證碼邏輯
 *
 * @see https://packagist.org/packages/gregwar/captcha
 */
final class Login extends Base {
	use \J7\WpUtils\Traits\SingletonTrait;

	/** @var string 驗證碼容器 class 用來區分是 login 還是 register */
	protected string $container_class = 'login';

	/**
	 * 不檢查登入驗證碼的 REST 路由（正規表示式，比對前路由一律轉小寫）
	 *
	 * 這三條是 power-partner-server 的 server 對 server 真實密碼驗證：由經銷商站的 PHP 送出，
	 * 不是人在登入頁登入，帶不了驗證碼；server 端已有自己的失敗限流（CredentialThrottle）
	 * - identity：經銷商登入（舊流程）
	 * - v3/connect：以經銷商帳密換取專屬應用程式密碼
	 * - logs/{id}/detail：點數明細（以經銷商帳密驗證）
	 *
	 * 豁免只看「實際分派的 REST 路由」，而且只在 REST 分派中才成立（見 is_exempt_request()）：
	 * - 不看整段 REQUEST_URI：否則在登入頁網址的 query 加上這些字串就能跳過驗證碼
	 * - 必須在 REST 分派中：WooCommerce 的登入表單處理掛在 wp_loaded，比 REST 分派早，
	 *   若只依網址決定，把登入表單送到這幾個網址一樣能跳過驗證碼
	 *
	 * ⛔ 不可擴大到其他路由，也不可整個 power-partner-server namespace 一起豁免
	 *
	 * @var array<int, string>
	 */
	const EXEMPT_REST_ROUTE_PATTERNS = [
		'#^/power-partner-server/identity#',
		'#^/power-partner-server/v3/connect#',
		'#^/power-partner-server/logs/[^/]+/detail/?\z#',
	];

	/** Constructor */
	public function __construct() {
		$settings = Settings::instance();

		if ($settings->enable_captcha_login !== 'yes') {
			return;
		}
		parent::__construct();

		\add_filter('authenticate', [ $this, 'authenticate' ], 999, 3);

		// render phase
		\add_action('login_form', [ $this, 'render_captcha_field' ]);
		\add_action('woocommerce_login_form', [ $this, 'render_captcha_field' ]);

		// ajax
		\add_action('wp_ajax_nopriv_need_captcha', [ $this, 'need_captcha' ]);
	}

	/**
	 * 這次請求是否不檢查登入驗證碼：正在 REST 分派中，且分派的路由符合 EXEMPT_REST_ROUTE_PATTERNS
	 *
	 * @param string    $request_uri REQUEST_URI
	 * @param bool|null $is_rest     是否在 REST 分派中，null 時依 REST_REQUEST 判斷（測試可直接指定）
	 * @return bool
	 */
	public static function is_exempt_request( string $request_uri, ?bool $is_rest = null ): bool {
		$is_rest ??= \defined('REST_REQUEST') && REST_REQUEST;
		if (!$is_rest) {
			return false;
		}
		return self::is_exempt_rest_route(self::rest_route_of($request_uri));
	}

	/**
	 * REST 路由是否在豁免清單內
	 *
	 * @param string $route REST 路由，例如 /power-partner-server/v3/connect
	 * @return bool
	 */
	public static function is_exempt_rest_route( string $route ): bool {
		if ('' === $route) {
			return false;
		}
		$route = \strtolower($route);
		foreach (self::EXEMPT_REST_ROUTE_PATTERNS as $pattern) {
			if (1 === \preg_match($pattern, $route)) {
				return true;
			}
		}
		return false;
	}

	/**
	 * 從 REQUEST_URI 取出要分派的 REST 路由
	 *
	 * 有 rest_route 查詢參數時用它的值；沒有的話取 path 中 /{rest 前綴}/ 之後的部分；都取不到回空字串
	 *
	 * @param string $request_uri REQUEST_URI
	 * @return string 例如 /power-partner-server/v3/connect，取不到時為空字串
	 */
	public static function rest_route_of( string $request_uri ): string {
		$query = (string) \wp_parse_url($request_uri, PHP_URL_QUERY);
		if ('' !== $query) {
			\parse_str($query, $query_args);
			$rest_route = $query_args['rest_route'] ?? null;
			if (\is_string($rest_route) && '' !== $rest_route) {
				return $rest_route;
			}
		}

		$path   = (string) \wp_parse_url($request_uri, PHP_URL_PATH);
		$prefix = '/' . \rest_get_url_prefix() . '/';
		$pos    = \strpos($path, $prefix);
		if (false === $pos) {
			return '';
		}
		return '/' . \substr($path, $pos + \strlen($prefix));
	}

	/** 驗證碼驗證
	 *
	 * @param null|\WP_User|\WP_Error $user    用戶資料
	 * @param string                  $username 用戶名稱
	 * @param string                  $password 用戶密碼
	 * @return null|\WP_User|\WP_Error
	 */
	public function authenticate( null|\WP_User|\WP_Error $user, string $username, string $password ) {
		// 如果是來自結帳頁面，則跳過驗證碼檢查
		if ('checkout' === \str_replace('/', '', (string) ( $_SERVER['REQUEST_URI'] ?? '' ))) { // phpcs:ignore
			return $user;
		}

		// power-partner-server 的 server 對 server 真實密碼驗證，見 EXEMPT_REST_ROUTE_PATTERNS
		if (self::is_exempt_request((string) ( $_SERVER['REQUEST_URI'] ?? '' ))) { // phpcs:ignore
			return $user;
		}

		if (!( $user instanceof \WP_User )) {
			return $user;
		}

		if (!$this->in_role_list($user)) {
			return $user;
		}

		$user_input = (string) ( $_POST[ $this->captcha_name ] ?? '' ); // phpcs:ignore
		if (!$this->test_phrase($user_input)) {
			return new \WP_Error('captcha_failed', '驗證碼錯誤');
		}
		// 以下就代表驗證成功了
		return $user;
	}

	/** AJAX 檢查要登入的用戶是否需要驗證碼 */
	public function need_captcha(): void {

		$username = (string) ( $_POST['username'] ?? '' ); // phpcs:ignore
		$pathname = (string) ( $_POST['pathname'] ?? '' ); // phpcs:ignore

		if (\str_contains($pathname, 'checkout')) {
			// 如果是來自結帳頁面，則跳過驗證碼檢查
			\wp_send_json(
				[
					'success' => true,
					'data'    => false,
				]
				);
			exit;
		}

		if (!$username) {
			\wp_send_json_error([ '缺少用戶名稱' ]);
		}

		$user = \get_user_by('login', $username);

		if (!$user) {
			$user = \get_user_by('email', $username);
			if (!$user) {
				\wp_send_json_error([ '找不到此用戶名稱' ]);
			}
		}

		\wp_send_json(
		[
			'success' => true,
			'data'    => $this->in_role_list($user),
		]
		);
		exit;
	}


	/**
	 * 檢查用戶是否在角色列表中
	 *
	 * @param \WP_User $user 用戶
	 * @return bool
	 */
	private function in_role_list( \WP_User $user ): bool {
		$settings  = Settings::instance();
		$role_list = $settings->captcha_role_list;
		// 符合直接回 true
		foreach ($role_list as $role) {
			if (in_array($role, (array) $user->roles, true)) {
				return true;
			}
		}

		return false;
	}
}
