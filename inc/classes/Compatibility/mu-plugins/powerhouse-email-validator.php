<?php
/**
 * Plugin Name:       Email Validator | Powerhouse
 * Plugin URI:        https://www.powerhouse.cloud
 * Description:       驗證用戶的 Email 網域是否設置郵件伺服器
 * Version:           1.0.2
 * Requires at least: 5.7
 * Requires PHP:      7.4
 * Author:            J7
 * Author URI:        https://github.com/j7-dev
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       powerhouse
 * Domain Path:       /languages
 * Tags: vite, WordPress plugin
 *
 * *******************************************************************************************
 *                                                                                           *
 *   ██████╗  ██████╗ ██╗    ██╗███████╗██████╗ ██╗  ██╗ ██████╗ ██╗   ██╗███████╗███████╗   *
 *   ██╔══██╗██╔═══██╗██║    ██║██╔════╝██╔══██╗██║  ██║██╔═══██╗██║   ██║██╔════╝██╔════╝   *
 *   ██████╔╝██║   ██║██║ █╗ ██║█████╗  ██████╔╝███████║██║   ██║██║   ██║███████╗█████╗     *
 *   ██╔═══╝ ██║   ██║██║███╗██║██╔══╝  ██╔══██╗██╔══██║██║   ██║██║   ██║╚════██║██╔══╝     *
 *   ██║     ╚██████╔╝╚███╔███╔╝███████╗██║  ██║██║  ██║╚██████╔╝╚██████╔╝███████║███████╗   *
 *   ╚═╝      ╚═════╝  ╚══╝╚══╝ ╚══════╝╚═╝  ╚═╝╚═╝  ╚═╝ ╚═════╝  ╚═════╝ ╚══════╝╚══════╝   *
 *                                                                                           *
 * *********************************** www.powerhouse.cloud **********************************
 */

namespace J7\Powerhouse\MU;

/**
 * EmailValidator
 * 驗證用戶的 Email 網域是否設置郵件伺服器
 */
final class EmailValidator {


	private const SETTINGS_KEY = 'powerhouse_settings';

	private const EMAIL_PATTERN = '/([^<>\s]+@[^<>\s]+)/';

	/** @var object{register: bool, wp_mail: bool} $settings 設定 */
	private object $settings;

	/** @var array<string> $whitelist_domains 白名單，全小寫 */
	private array $whitelist_domains = [];

	/** Constructor */
	public function __construct() {
		$this->init();

		if ( $this->settings->register ) {
			/** @category [前台]  WordPress 標準註冊（如 wp-login.php?action=register） */
			\add_filter( 'registration_errors', [ $this, 'registration_errors_validate' ], 10, 3 );

			/** @category [前台] WooCommerce My Account 註冊 */
			\add_filter( 'woocommerce_registration_errors', [ $this, 'registration_errors_validate' ], 10, 3 );
		}

		/** @category [前台] 後台建立用戶時的驗證，後台創建用戶先不阻擋 */
		// \add_action('user_profile_update_errors', [ $this, 'check_admin_email_domain' ], 10, 3);

		if ( $this->settings->wp_mail ) {
			/** @category [全局] 所有發信前 */
			\add_filter( 'pre_wp_mail', [ $this, 'pre_wp_mail_validate' ], 10, 2 );
		}
	}

	/** 從 options 初始化設定 */
	private function init(): void {
		$settings       = \get_option( self::SETTINGS_KEY, [] );
		$settings       = is_array( $settings ) ? $settings : [];
		$this->settings = (object) [
			'register' => ( $settings['enable_email_domain_check_register'] ?? 'yes' ) === 'yes',
			'wp_mail'  => ( $settings['enable_email_domain_check_wp_mail'] ?? 'yes' ) === 'yes',
		];

		$whitelist_domains       = $settings['email_domain_check_white_list'] ?? [
			'gmail.com',
			'yahoo.com',
			'hotmail.com',
			'outlook.com',
			'icloud.com',
		];
		$whitelist_domains       = is_array( $whitelist_domains ) ? $whitelist_domains : [];
		$whitelist_domains       = \array_map( fn( $s ): string => strtolower( (string) $s), $whitelist_domains );
		$this->whitelist_domains = $whitelist_domains;
	}

	/**
	 * 檢查後台建立用戶時的 Email 網域
	 *
	 * @param \WP_Error $errors WP_Error 錯誤
	 * @param bool      $update 是否為更新操作
	 * @param \stdClass $user   用戶物件
	 */
	public function check_admin_email_domain( \WP_Error $errors, bool $update, \stdClass $user ): void {
		// 只在建立新用戶時檢查，不在更新時檢查
		if ( $update ) {
			return;
		}

		if ( isset( $user->user_email ) ) {
			$this->registration_errors_validate( $errors, $user->user_login, $user->user_email );
		}
	}

	/**
	 * 通用的 Email 網域驗證邏輯
	 *
	 * @param \WP_Error $errors               WP_Error 錯誤
	 * @param string    $sanitized_user_login 用戶名稱
	 * @param string    $user_email           用戶 Email
	 *
	 * @return \WP_Error WP_Error 錯誤
	 */
	public function registration_errors_validate( \WP_Error $errors, string $sanitized_user_login, string $user_email ): \WP_Error {
		try {
			$this->validate_email_domain( $user_email );
			return $errors;
		} catch ( \Throwable $th ) {
			$errors->add( 'invalid_email_domain', $th->getMessage() );
			return $errors;
		}
	}

	/**
	 * 通用的 Email 網域驗證邏輯
	 *
	 * @param string $user_email 用戶 Email
	 *
	 * @return true 如果郵件網域有效，則返回 true
	 * @throws \Exception 如果郵件網域未設置郵件伺服器，則拋出異常
	 */
	private function validate_email_domain( string $user_email ): bool {
		// 這個 Email 有可能長這樣 "test <test@example.com>"，所以要用 pattern 處理
		$is_matched = preg_match( self::EMAIL_PATTERN, $user_email, $matches );
		if ( !$is_matched ) {
			throw new \Exception( '無效的 Email 格式' );
		}
		$email = $matches[1];

		// 解析 Email 網域
		$email_parts = \explode( '@', \strtolower( $email ) );
		if ( \count( $email_parts ) !== 2 ) {
			throw new \Exception( '無效的 Email 格式' );
		}

		$domain = $email_parts[1];

		// 白名單檢查
		if ( \in_array( $domain, $this->whitelist_domains, true ) ) {
			return true; // 跳過檢查
		}

		// 檢查 MX 記錄
		if ( !checkdnsrr( $domain, 'MX' ) ) {
			throw new \Exception( '該 Email 網域未設置郵件伺服器，請使用有效的郵件網域' );
		}

		return true;
	}

	/**
	 * 是否發信前中斷發送郵件
	 *
	 * @param null|bool $return 短路返回值
	 * @param array{
	 *          to: string|string[],
	 *          subject: string,
	 *          message: string,
	 *          headers: string|string[],
	 *          attachments: string|string[],
	 *        }         $atts
	 *
	 * @return null|bool 短路返回值
	 */
	public function pre_wp_mail_validate( $return, $atts ) {
		try {
			$to = $atts['to'];
			if ( !$to ) {
				return false;
			}

			if ( is_array( $to ) ) {
				foreach ( $to as $item ) {
					$emails = explode( ',', $item );
					foreach ( $emails as $email ) {
						$this->validate_email_domain( $email );
					}
				}
			} else {
				$emails = explode( ',', $to );
				foreach ( $emails as $email ) {
					$this->validate_email_domain( $email );
				}
			}

			return $return;
		} catch ( \Throwable $th ) {
			return false;
		}
	}
}

new EmailValidator();
