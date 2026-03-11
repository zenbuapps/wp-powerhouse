<?php

declare(strict_types=1);

namespace J7\Powerhouse\Domains\Plugin\Core;

use J7\WpUtils\Classes\ApiBase;
use J7\Powerhouse\Domains\Plugin\Model\Plugin;

/** Class V2Api */
final class V2Api extends ApiBase {
	use \J7\WpUtils\Traits\SingletonTrait;

	/** @var string Namespace */
	protected $namespace = 'v2/powerhouse';

	/** @var array{endpoint:string,method:string,permission_callback: ?callable }[] APIs */
	protected $apis = [
		[
			'endpoint'            => 'plugins',
			'method'              => 'get',
			'permission_callback' => null,
		],
	];

	/**
	 * Get plugins callback 取得插件列表
	 *
	 * @param \WP_REST_Request $request Request.
	 *
	 * @return \WP_REST_Response|\WP_Error
	 * @phpstan-ignore-next-line
	 */
	public function get_plugins_callback( $request ) { // phpcs:ignore
		$all_plugins    = \get_plugins();
		$active_plugins = \get_option('active_plugins', []);
		/** @var array<string> $active_plugins */
		$active_plugins = is_array($active_plugins) ? $active_plugins : [];

		$formatted_plugins = [];
		foreach ( $all_plugins as $plugin_path => $plugin_data ) {
			$plugin_data['key']       = $plugin_path;
			$plugin_data['is_active'] = \in_array( $plugin_path, $active_plugins, true );
			$formatted_plugins[]      = Plugin::instance( $plugin_data )->to_array();
		}

		$response = new \WP_REST_Response( $formatted_plugins );

		// set pagination in header
		$response->header( 'X-WP-Total', (string) count( $formatted_plugins ) );
		$response->header( 'X-WP-TotalPages', (string) 1 );
		$response->header( 'X-WP-CurrentPage', (string) 1 );
		$response->header( 'X-WP-PageSize', (string) count( $formatted_plugins ) );

		return $response;
	}
}
