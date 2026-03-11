<?php
/**
 * Plugin Name:       Disable Features | Powerhouse
 * Plugin URI:        https://www.powerhouse.cloud
 * Description:       安全性考量，禁用一些功能
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
 * DisableFeatures
 * 禁用 xmlrpc、rest api users 功能
 */
final class DisableFeatures {


	const PRIORITY = 999;

	/** Constructor */
	public function __construct() {
		// 停用 XML-RPC
		\add_filter('xmlrpc_enabled', '__return_false', self::PRIORITY);

		// 停用 REST API 的使用者端點
		\add_filter(
			'rest_endpoints',
			function ( $endpoints ) {
				// 移除所有使用者清單的 GET 請求
				if (isset($endpoints['/wp/v2/users'])) {
					unset($endpoints['/wp/v2/users']);
				}
				// 移除單一使用者資訊的 GET 請求
				if (isset($endpoints['/wp/v2/users/(?P<id>[\d]+)'])) {
					unset($endpoints['/wp/v2/users/(?P<id>[\d]+)']);
				}
				return $endpoints;
			},
			self::PRIORITY
			);

		// 圖片  移除所有圖片尺寸
		\add_filter('intermediate_image_sizes_advanced', static fn() => []);
		\add_filter('big_image_size_threshold', static fn () => 20000);
		\add_filter('jpeg_quality', static fn () => 100);
	}
}

new DisableFeatures();
