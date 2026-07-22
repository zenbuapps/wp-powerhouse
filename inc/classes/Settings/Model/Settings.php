<?php

declare(strict_types=1);

namespace J7\Powerhouse\Settings\Model;

use J7\WpUtils\Classes\DTO as BaseDTO;
use J7\Powerhouse\Theme\Model\Theme;
use J7\Powerhouse\Settings\Core\ApiBoosterRule;

/** Powerhouse Settings */
class Settings extends BaseDTO {

	// mu-plugins 裡面的 api booster 使用此 key ，請勿修改
	const SETTINGS_KEY = 'powerhouse_settings';

	// ----- ▼ 一般設定 ----- //

	/** @var string $enable_manual_send_email 允許用戶手動發信 */
	public string $enable_manual_send_email = 'no';

	/** @var string $enable_captcha_login 啟用登入驗證碼 */
	public string $enable_captcha_login = 'no';

	/** @var array<string> $captcha_role_list 驗證碼角色列表 */
	public array $captcha_role_list = [ 'administrator' ];

	/** @var string $enable_captcha_register 啟用註冊驗證碼 */
	public string $enable_captcha_register = 'no';

	/** @var string $enable_email_domain_check_register 註冊前驗證用戶的 Email 網域是否設置郵件伺服器 */
	public string $enable_email_domain_check_register = 'yes';

	/** @var string $enable_email_domain_check_wp_mail 發信前驗證用戶的 Email 網域是否設置郵件伺服器 */
	public string $enable_email_domain_check_wp_mail = 'yes';

	/** @var array<string> $email_domain_check_white_list Email 網域驗證的白名單，名單內就不檢查 */
	public array $email_domain_check_white_list = [
		'gmail.com',
		'yahoo.com',
		'hotmail.com',
		'outlook.com',
		'icloud.com',
	];

	// ----- ▼ Woocommerce 相關 ----- //

	/** @var string $delay_email 延遲寄信 */
	public string $delay_email = 'yes';

	/** @var string $last_name_optional 姓氏可選 */
	public string $last_name_optional = 'yes';

	// ----- ▼ 主題顏色 ----- //

	/** @var string $theme 選擇主題 */
	public string $theme = 'power';

	/** @var string $enable_theme_changer 啟用主題切換器 */
	public string $enable_theme_changer = 'no';

	/** @var string $enable_theme 啟用主題 */
	public string $enable_theme = 'yes';

	/**
	 * 當選擇 custom 主題時，使用自訂的 css
	 *
	 * 此屬性僅作為 partial_update() 寫回 DB 時的欄位載體，**不是**權威值。
	 * 實際值一律於 to_array() 當下向 Theme::instance() 取得（延遲取值，理由見 to_array()）。
	 *
	 * @var array<string, string> $theme_css
	 */
	public array $theme_css = [];


	// ----- ▼ 實驗性功能 ----- //

	/** @var array<string, mixed> $api_booster_rules API 加速器規則 */
	public array $api_booster_rules = [];

	/** @var array<string, mixed> $api_booster_rule_recipes API 加速器模板 */
	public array $api_booster_rule_recipes = [];

	// ----- ▼ BunnyCDN 相關 ----- //

	/** @var string $bunny_library_id BunnyCDN 圖庫 ID */
	public string $bunny_library_id = '';

	/** @var string $bunny_cdn_hostname BunnyCDN 主機名稱 */
	public string $bunny_cdn_hostname = '';

	/** @var string $bunny_stream_api_key BunnyCDN 串流 API 金鑰 */
	public string $bunny_stream_api_key = '';

	/** @var self 實例 */
	protected static $instance = null;

	/**
	 * Constructor.
	 *
	 * @param array<string, mixed> $input Input values.
	 */
	public function __construct( array $input = [] ) {
		parent::__construct($input);
		self::$instance = $this;
		// 刻意不在此取 theme_css，避免提前催生 Theme singleton，理由見 to_array()。
	}

	/**
	 * 取得公開的屬性 array
	 *
	 * 延遲取值：theme_css 於此刻才向 Theme 取得，**不在 constructor 取**。
	 *
	 * 原因：Settings::instance() 會在 plugins_loaded（priority -10）就被 Bootstrap 的
	 * Admin\Account / Admin\DelayEmail / Captcha\Core\Login 等模組的 constructor 催生，
	 * 而該階段早於 WordPress 載入主題 functions.php。若在 constructor 呼叫 Theme::instance()，
	 * 「跟隨 Blocksy」模式會因 blocksy_manager() 尚未定義而拿到空覆寫，
	 * 空結果又被 Theme singleton 快取整個請求，導致前台永遠輸出 Powerhouse 預設色。
	 *
	 * 延遲到 to_array()（僅由 REST API 與 partial_update() 呼叫，皆在主題載入後）即可取到正確調色盤。
	 *
	 * @return array<string, mixed>
	 */
	public function to_array(): array {
		$array              = parent::to_array();
		$array['theme_css'] = Theme::instance()->to_array();
		return $array;
	}

	/**
	 * 取得單一實例
	 *
	 * @return self
	 */
	public static function instance():self { // phpcs:ignore
		if ( null === self::$instance ) {
			$setting_array = \get_option(self::SETTINGS_KEY, []);
			$setting_array = is_array($setting_array) ? $setting_array : [];

			/** @var array<string, mixed> $setting_array */
			unset($setting_array['theme_css']); // theme_css 獨立初始化

			unset($setting_array['enable_api_booster']);

			return new self($setting_array);
		}
		return self::$instance;
	}

	/**
	 * 部分更新
	 * 保留原本 array 上的值，只對新的 key-value 更新
	 *
	 * @param array<string, mixed> $values 更新值
	 */
	public function partial_update( array $values ): void {
		$from_setting_array = $this->to_array();
		$to_setting_array   = \wp_parse_args($values, $from_setting_array);
		foreach ($to_setting_array as $key => $value) {
			if (!property_exists($this, $key)) {
				unset($to_setting_array[ $key ]);
			}
		}
		\update_option(self::SETTINGS_KEY, $to_setting_array);
	}

	/** 初始化後執行 */
	protected function after_init(): void {
		$this->api_booster_rule_recipes = ApiBoosterRule::instance()->get_recipes();
	}
}
