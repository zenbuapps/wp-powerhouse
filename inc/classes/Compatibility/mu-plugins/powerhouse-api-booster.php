<?php
/**
 * Plugin Name:       API Booster | Powerhouse
 * Plugin URI:        https://www.powerhouse.cloud
 * Description:       API 加速器，根據 API 請求路徑載入必要的插件，減少不必要的資源載入。
 * Version:           1.0.0
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
 * ApiBooster
 * 在特定的請求路徑下，只載入必要的插件
 */
final class ApiBooster {


	/** @var array<array{name: string, enabled: string, url_rules: array<string>, plugins: array<string>}> 已啟用的規則 */
	protected array $api_booster_rules = [];

	/** Constructor */
	public function __construct() {
		// 獲取當前請求的 URI
		$request_uri = (string) ( $_SERVER['REQUEST_URI'] ?? '' ); // phpcs:ignore
		// 如果包含 /wp-json/v2/powerhouse/plugins ，就跳過，因為設定項要拿到最精準的資料
		if (strpos($request_uri, '/wp-json/v2/powerhouse/plugins') !== false) {
			return;
		}

		$this->api_booster_rules = $this->get_enabled_api_booster_rules();

		\add_action('muplugins_loaded', [ $this, 'apply_rules' ], 100);
	}

	/**
	 * 取得 api booster 規則
	 *
	 * @return array<array{name: string, enabled: string, url_rules: array<string>, plugins: array<string>}>
	 */
	protected function get_enabled_api_booster_rules(): array {
		$powerhouse_settings = \get_option('powerhouse_settings', []);
		$powerhouse_settings = is_array($powerhouse_settings) ? $powerhouse_settings : [];

		$api_booster_rules = $powerhouse_settings['api_booster_rules'] ?? [];

		$api_booster_rules = is_array($api_booster_rules) ? $api_booster_rules : [];

		// 重新整理規則，剔除未啟用的規則，也把 url_rules 整理成 array
		$enabled_api_booster_rules = [];
		foreach ($api_booster_rules as $rule) {
			/** @var array{enabled: string, rules: string, name: string, plugins: array<string>} $rule */
			@[
				'enabled' => $enabled,
				'rules'   => $url_rules_string,
			] = $rule;

			if ('yes' !== $enabled) {
				continue;
			}

			$url_rules = explode("\n", (string) $url_rules_string);
			$url_rules = array_map(fn( $url_rule ) => trim($url_rule), $url_rules); // 移除空白
			$url_rules = array_filter($url_rules); // 移除 falsy value

			if (!$url_rules) {
				continue;
			}

			$enabled_api_booster_rules[] = [
				'name'      => $rule['name'],
				'enabled'   => $rule['enabled'],
				'url_rules' => array_values($url_rules),
				'plugins'   => $rule['plugins'],
			];
		}

		return $enabled_api_booster_rules;
	}

	/**
	 * 應用 API 規則
	 *
	 * @return void
	 */
	public function apply_rules(): void {
		foreach ($this->api_booster_rules as $index => $api_booster_rule) {
			$this->only_load_required_plugins($api_booster_rule, (int) $index);
		}
	}

	/**
	 * Only Load Required Plugins
	 * 只載入必要的插件
	 *
	 * @param array{name: string, enabled: string, url_rules: array<string>, plugins: array<string>} $api_booster_rule  API 規則
	 * @param int                                                                                    $index 規則的索引
	 * @return void
	 */
	public function only_load_required_plugins( array $api_booster_rule, int $index ): void {
		$url_rules = $api_booster_rule['url_rules'];
		$plugins   = $api_booster_rule['plugins'];

		// ----- ▼ 檢查 API 請求是否包含 "/{$url_rule}" 字串 ----- //
		$some_strpos = false;
		foreach ($url_rules as $url_rule) {
			if ($this->match_url_pattern($url_rule)) {
				$some_strpos = true;
				break;
			}
		}
		if (!$some_strpos) {
			return;
		}

		// 修改 option 的 value ，當要 get_option('active_plugins') 時，覆寫
		\add_filter('option_active_plugins', fn() => $plugins, 100 + $index);
	}

	/**
	 * Remove Unnecessary Hooks
	 * 移除不必要的 WordPress 功能
	 *
	 * @return void
	 */
	protected function remove_unnecessary_hooks(): void {
		// 移除不必要的 WordPress 功能
		$hooks_to_remove = [
			'widgets_init',
			'register_sidebar',
			'wp_register_sidebar_widget',
			'wp_default_scripts',
			'wp_default_styles',
			'admin_bar_init',
			'add_admin_bar_menus',
		];

		foreach ($hooks_to_remove as $hook) {
			\add_action(
				$hook,
				function () use ( $hook ) {
					\remove_all_actions($hook);
				},
				-999999
			);
		}
	}


	/**
	 * 檢查當前請求 URI 是否符合指定的規則模式
	 *
	 * @param string $pattern 規則字串，* 代表任意數量的 [0-9a-zA-Z/] 字符
	 * @return bool 是否匹配
	 */
	protected function match_url_pattern( string $pattern ): bool {
		// 獲取當前請求的 URI
		$request_uri = (string) ( $_SERVER['REQUEST_URI'] ?? '' ); // phpcs:ignore

		// 移除查詢參數部分（? 後面的內容）
		$request_uri = (string) parse_url($request_uri, PHP_URL_PATH);

		// 將規則中的 * 轉換為正則表達式
		// * 代表任意數量的 [0-9a-zA-Z] 字符
		$regex_pattern = preg_quote($pattern, '/');
		$regex_pattern = str_replace('\*', '[0-9a-zA-Z\/]*', $regex_pattern);

		// 添加開始和結束錨點，確保完全匹配
		$regex_pattern = '/^' . $regex_pattern . '$/';

		// 執行匹配
		return preg_match($regex_pattern, $request_uri) === 1;
	}
}

new ApiBooster();
