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
	 * 不掛登入驗證碼的 REQUEST_URI（子字串比對，同一組內的字串都出現才算符合）
	 *
	 * 這三條是 power-partner-server 的 server 對 server 真實密碼驗證：由經銷商站的 PHP 送出，
	 * 不是人在登入頁登入，帶不了驗證碼；server 端已有自己的失敗限流（CredentialThrottle）
	 * - identity：經銷商登入（舊流程）
	 * - v3/connect：以經銷商帳密換取專屬應用程式密碼
	 * - logs/{id}/detail：點數明細（以經銷商帳密驗證）
	 *
	 * ⛔ 不可擴大到其他路徑，也不可整個 power-partner-server namespace 一起豁免
	 *
	 * @var array<int, array<int, string>>
	 */
	const EXEMPT_REQUEST_URIS = [
		[ 'power-partner-server/identity' ],
		[ 'power-partner-server/v3/connect' ],
		[ 'power-partner-server/logs/', '/detail' ],
	];

	/** Constructor */
	public function __construct() {
		$settings = Settings::instance();

		$request_url = (string) ( $_SERVER['REQUEST_URI'] ?? '' ); // phpcs:ignore
		if (self::is_exempt_request_uri($request_url)) {
			return;
		}

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
	 * 這次請求是否不掛登入驗證碼，見 EXEMPT_REQUEST_URIS
	 *
	 * @param string $request_uri REQUEST_URI
	 * @return bool
	 */
	public static function is_exempt_request_uri( string $request_uri ): bool {
		foreach (self::EXEMPT_REQUEST_URIS as $needles) {
			$matched = true;
			foreach ($needles as $needle) {
				if (!\str_contains($request_uri, $needle)) {
					$matched = false;
					break;
				}
			}
			if ($matched) {
				return true;
			}
		}
		return false;
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
