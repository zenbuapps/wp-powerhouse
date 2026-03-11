<?php

declare(strict_types=1);

namespace J7\Powerhouse\Captcha\Core;

use J7\Powerhouse\Settings\Model\Settings;

/**
 * Class Register
 *
 * @see https://packagist.org/packages/gregwar/captcha
 */
final class Register extends Base {
	use \J7\WpUtils\Traits\SingletonTrait;

	/** @var string 驗證碼容器 class 用來區分是 login 還是 register */
	protected string $container_class = 'register';

	/** Constructor */
	public function __construct() {
		$settings = Settings::instance();
		if ($settings->enable_captcha_register !== 'yes') {
			return;
		}

		parent::__construct();
		\add_filter( 'wp_pre_insert_user_data', [ $this, 'authenticate' ], 10, 4 );
		// render phase
		\add_action('woocommerce_register_form', [ $this, 'render_captcha_field' ]);
	}

	/** 驗證碼驗證
	 *
	 * @param array<string, mixed> $data 用戶資料
	 * @param bool                 $update 是否更新
	 * @param int|null             $user_id 用戶ID
	 * @param array<string, mixed> $userdata 用戶資料
	 * @return array<string, mixed>
	 * @throws \Exception 驗證碼錯誤時拋出例外
	 */
	public function authenticate( array $data, bool $update, int|null $user_id, array $userdata ): array {
		if ($update) {
			return $data;
		}

		try {
		$user_input = (string) ( $_POST[ $this->captcha_name ] ?? '' ); // phpcs:ignore

			if (!$user_input) {
				throw new \Exception('缺少驗證碼，註冊已被取消。');
			}

			if (!$this->test_phrase($user_input)) {
				throw new \Exception('驗證碼錯誤，註冊已被取消。');
			}

			return $data;
		} catch (\Throwable $th) {
			if (\wp_is_serving_rest_request() || \wp_doing_ajax()) {
				\wp_send_json_error([ 'message' => $th->getMessage() ]);
			}
			\wp_die($th->getMessage());
		}
	}
}
