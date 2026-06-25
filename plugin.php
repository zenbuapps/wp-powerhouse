<?php
/**
 * Plugin Name:       Powerhouse
 * Plugin URI:        https://www.powerhouse.cloud
 * Description:       方便開發 WordPress 的工具包，以及優化功能，提供通用的 API
 * Version:           3.5.1
 * Requires at least: 5.7
 * Requires PHP:      8.1
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

declare ( strict_types=1 );

namespace J7\Powerhouse;

if ( \class_exists( 'J7\Powerhouse\Plugin' ) ) {
	return;
}

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/inc/classes/Domains/LC/Utils/Base.php';

/** Class Plugin */
final class Plugin {
	use \J7\WpUtils\Traits\PluginTrait;
	use \J7\WpUtils\Traits\SingletonTrait;

	/** Constructor */
	public function __construct() {

		$this->init(
		[
			'app_name'     => 'Powerhouse',
			'github_repo'  => 'https://github.com/zenbuapps/wp-powerhouse',
			'callback'     => [ Bootstrap::class, 'instance' ],
			'priority'     => -10,
			'lc'           => false,
			'hide_submenu' => true,
		]
		);

		self::$template_page_names = [ 'admin', 'settings', 'license-codes', 'powerhouse', 'admin-layout' ];
	}

	/**
	 * 從指定的模板路徑讀取模板文件並渲染數據
	 *
	 * @param string $name 指定路徑裡面的文件名
	 * @param mixed  $args 要渲染到模板中的數據
	 * @param bool   $output 是否輸出
	 * @param bool   $load_once 是否只載入一次
	 *
	 * @return string|false|null
	 * @throws \Exception 如果模板文件不存在.
	 */
	public static function load_template(
		string $name,
		mixed $args = null,
		?bool $output = true,
		?bool $load_once = false,
	) {
		$result = self::safe_load_template( $name, $args, $output, $load_once );

		if ( ' ' === $result ) {
			throw new \Exception( "模板文件 {$name} 不存在" );
		}

		return $result;
	}

	/**
	 * 從指定的模板路徑讀取模板文件並渲染數據
	 *
	 * @param string $name 指定路徑裡面的文件名
	 * @param mixed  $args 要渲染到模板中的數據
	 * @param bool   $echo 是否輸出
	 * @param bool   $load_once 是否只載入一次
	 *
	 * @return string|false|null
	 * @throws \Exception 如果模板文件不存在.
	 */
	public static function safe_load_template(
		string $name,
		mixed $args = null,
		?bool $echo = true,
		?bool $load_once = false,
	): string|false|null {

		// 如果 $name 是以 page name 開頭的，那就去 page folder 裡面找
		$is_page = false;
		foreach ( self::$template_page_names as $page_name ) {
			if ( \str_starts_with( $name, $page_name ) ) {
				$is_page = true;
				break;
			}
		}

		$folder = $is_page ? 'pages' : 'components';

		$template_path = self::$dir . self::$template_path . "/templates/{$folder}/{$name}";

		// 檢查模板文件是否存在
		$load_once_bool = (bool) $load_once;
		$args_array     = is_array($args) ? $args : [];

		if ( file_exists( "{$template_path}.php" ) ) {
			if ( $echo ) {
				\load_template( "{$template_path}.php", $load_once_bool, $args_array );

				return null;
			}
			ob_start();
			\load_template( "{$template_path}.php", $load_once_bool, $args_array );

			return ob_get_clean();
		} elseif ( file_exists( "{$template_path}/index.php" ) ) {
			if ( $echo ) {
				\load_template( "{$template_path}/index.php", $load_once_bool, $args_array );

				return null;
			}
			ob_start();
			\load_template( "{$template_path}/index.php", $load_once_bool, $args_array );

			return ob_get_clean();
		}

		return ' ';
	}

	/**
	 * 印出 WC Logger
	 *
	 * @param string                  $message 訊息
	 * @param string                  $level 等級
	 * @param array<array-key, mixed> $context 上下文
	 *
	 * @return void
	 */
	public static function logger( string $message, string $level = 'debug', array $context = [] ) {
		\J7\WpUtils\Classes\WC::logger(
			$message,
			$level,
			$context,
			'powerhouse'
			);
	}

	/**
	 * Activate
	 * 啟用時創建 access_itemmeta table
	 *
	 * @return void
	 * @throws \Exception Exception.
	 */
	public function activate(): void {
		require_once __DIR__ . '/inc/classes/Domains/Limit/Utils/CreateTable.php';
		Domains\Limit\Utils\CreateTable::create_itemmeta_table();
	}
}

Plugin::instance();
