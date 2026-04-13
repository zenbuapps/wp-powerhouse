<?php
/**
 * 整合測試基礎類別
 * 所有 Powerhouse 整合測試必須繼承此類別
 */

declare( strict_types=1 );

namespace Tests\Integration;

use J7\Powerhouse\Settings\Model\Settings;

/**
 * Class TestCase
 * 整合測試基礎類別，提供共用 helper methods
 */
abstract class TestCase extends \WP_UnitTestCase {

	/**
	 * 最後發生的錯誤（用於驗證操作是否失敗）
	 *
	 * @var \Throwable|null
	 */
	protected ?\Throwable $lastError = null;

	/**
	 * 查詢結果（用於驗證 Query 操作的回傳值）
	 *
	 * @var mixed
	 */
	protected mixed $queryResult = null;

	/**
	 * ID 映射表（名稱 → ID 等）
	 *
	 * @var array<string, int>
	 */
	protected array $ids = [];

	/**
	 * Repository 容器
	 *
	 * @var \stdClass
	 */
	protected \stdClass $repos;

	/**
	 * Service 容器
	 *
	 * @var \stdClass
	 */
	protected \stdClass $services;

	/**
	 * 設定（每個測試前執行）
	 */
	public function set_up(): void {
		parent::set_up();

		$this->lastError   = null;
		$this->queryResult = null;
		$this->ids         = [];
		$this->repos       = new \stdClass();
		$this->services    = new \stdClass();

		// 重設 Settings 單例，避免測試之間互相污染
		$this->reset_settings_singleton();

		$this->configure_dependencies();
	}

	/**
	 * 清理（每個測試後執行）
	 */
	public function tear_down(): void {
		// 清除可能存在的 LC transients
		$this->clean_lc_transients();
		parent::tear_down();
	}

	/**
	 * 初始化依賴（子類別可選擇覆寫）
	 * 在此方法中初始化 $this->repos 和 $this->services
	 */
	protected function configure_dependencies(): void {
		// 預設空實作，子類別自行覆寫
	}

	// ========== Settings Helper ==========

	/**
	 * 重設 Settings 單例（透過 Reflection 清除靜態 instance）
	 */
	protected function reset_settings_singleton(): void {
		try {
			$reflection = new \ReflectionClass( Settings::class );
			$property   = $reflection->getProperty( 'instance' );
			$property->setAccessible( true );
			$property->setValue( null, null );
		} catch ( \ReflectionException $e ) {
			// 反射失敗時靜默略過
		}
	}

	/**
	 * 設定 Powerhouse Settings 選項值
	 *
	 * @param array<string, mixed> $values 要覆蓋的設定值
	 */
	protected function set_powerhouse_settings( array $values ): void {
		$this->reset_settings_singleton();
		\update_option( Settings::SETTINGS_KEY, $values );
	}

	/**
	 * 取得目前的 Powerhouse Settings 實例
	 *
	 * @return Settings
	 */
	protected function get_powerhouse_settings(): Settings {
		return Settings::instance();
	}

	// ========== LC (License Code) Helper ==========

	/**
	 * 為指定產品設定假的已啟用 LC transient
	 *
	 * @param string $product_slug 產品 slug
	 * @param string $code         授權碼
	 */
	protected function set_activated_lc_transient( string $product_slug, string $code = 'TEST-XXXX-YYYY-ZZZZ' ): void {
		$lc_data = [
			'code'         => $code,
			'post_status'  => 'activated',
			'expire_date'  => time() + DAY_IN_SECONDS * 365,
			'type'         => 'standard',
			'product_slug' => $product_slug,
			'product_name' => "Test Product {$product_slug}",
		];
		$encoded = \J7\Powerhouse\Domains\LC\Utils\Base::encode( $lc_data );
		\set_transient( "lc_{$product_slug}", $encoded, HOUR_IN_SECONDS );
	}

	/**
	 * 清除所有 LC transients（防止測試污染）
	 */
	protected function clean_lc_transients(): void {
		global $wpdb;
		$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			"DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_lc_%' OR option_name LIKE '_transient_timeout_lc_%'"
		);
	}

	// ========== WooCommerce Helper ==========

	/**
	 * 建立測試 WooCommerce 訂單
	 *
	 * @param array<string, mixed> $args 訂單設定
	 * @return \WC_Order 訂單物件
	 */
	protected function create_wc_order( array $args = [] ): \WC_Order {
		$order = \wc_create_order();

		if ( isset( $args['status'] ) ) {
			$order->update_status( $args['status'] );
		}

		if ( isset( $args['total'] ) ) {
			$order->set_total( $args['total'] );
		}

		if ( isset( $args['customer_id'] ) ) {
			$order->set_customer_id( $args['customer_id'] );
		}

		$order->save();
		return $order;
	}

	/**
	 * 建立測試 WooCommerce 商品
	 *
	 * @param array<string, mixed> $args 商品設定
	 * @return \WC_Product_Simple 商品物件
	 */
	protected function create_wc_product( array $args = [] ): \WC_Product_Simple {
		$product = new \WC_Product_Simple();
		$product->set_name( $args['name'] ?? '測試商品' );
		$product->set_regular_price( (string) ( $args['price'] ?? 100 ) );
		$product->set_status( $args['status'] ?? 'publish' );

		if ( isset( $args['sku'] ) ) {
			$product->set_sku( $args['sku'] );
		}

		$product->save();
		return $product;
	}

	// ========== REST API Helper ==========

	/**
	 * 以管理員身份發送 REST 請求
	 *
	 * @param string               $method HTTP 方法
	 * @param string               $route  路由（如 /v2/powerhouse/posts）
	 * @param array<string, mixed> $params 請求參數
	 * @return \WP_REST_Response 回應物件
	 */
	protected function rest_request_as_admin( string $method, string $route, array $params = [] ): \WP_REST_Response {
		$admin_id = $this->factory()->user->create( [ 'role' => 'administrator' ] );
		\wp_set_current_user( $admin_id );

		$request = new \WP_REST_Request( strtoupper( $method ), $route );

		if ( in_array( strtoupper( $method ), [ 'POST', 'PUT', 'PATCH' ], true ) ) {
			$request->set_body( \wp_json_encode( $params ) );
			$request->set_header( 'Content-Type', 'application/json' );
		} else {
			$request->set_query_params( $params );
		}

		/** @var \WP_REST_Response $response */
		$response = \rest_do_request( $request );
		return $response;
	}

	/**
	 * 以訂閱者身份發送 REST 請求（權限測試用）
	 *
	 * @param string               $method HTTP 方法
	 * @param string               $route  路由
	 * @param array<string, mixed> $params 請求參數
	 * @return \WP_REST_Response 回應物件
	 */
	protected function rest_request_as_subscriber( string $method, string $route, array $params = [] ): \WP_REST_Response {
		$user_id = $this->factory()->user->create( [ 'role' => 'subscriber' ] );
		\wp_set_current_user( $user_id );

		$request = new \WP_REST_Request( strtoupper( $method ), $route );

		if ( in_array( strtoupper( $method ), [ 'POST', 'PUT', 'PATCH' ], true ) ) {
			$request->set_body( \wp_json_encode( $params ) );
			$request->set_header( 'Content-Type', 'application/json' );
		} else {
			$request->set_query_params( $params );
		}

		/** @var \WP_REST_Response $response */
		$response = \rest_do_request( $request );
		return $response;
	}

	/**
	 * 以未登入身份發送 REST 請求
	 *
	 * @param string               $method HTTP 方法
	 * @param string               $route  路由
	 * @param array<string, mixed> $params 請求參數
	 * @return \WP_REST_Response 回應物件
	 */
	protected function rest_request_as_guest( string $method, string $route, array $params = [] ): \WP_REST_Response {
		\wp_set_current_user( 0 );

		$request = new \WP_REST_Request( strtoupper( $method ), $route );

		if ( in_array( strtoupper( $method ), [ 'POST', 'PUT', 'PATCH' ], true ) ) {
			$request->set_body( \wp_json_encode( $params ) );
			$request->set_header( 'Content-Type', 'application/json' );
		} else {
			$request->set_query_params( $params );
		}

		/** @var \WP_REST_Response $response */
		$response = \rest_do_request( $request );
		return $response;
	}

	// ========== 斷言 Helper ==========

	/**
	 * 斷言操作成功（$this->lastError 應為 null）
	 */
	protected function assert_operation_succeeded(): void {
		$this->assertNull(
			$this->lastError,
			sprintf( '預期操作成功，但發生錯誤：%s', $this->lastError?->getMessage() )
		);
	}

	/**
	 * 斷言操作失敗（$this->lastError 不應為 null）
	 */
	protected function assert_operation_failed(): void {
		$this->assertNotNull( $this->lastError, '預期操作失敗，但沒有發生錯誤' );
	}

	/**
	 * 斷言操作失敗且錯誤訊息包含指定文字
	 *
	 * @param string $msg 期望錯誤訊息包含的文字
	 */
	protected function assert_operation_failed_with_message( string $msg ): void {
		$this->assertNotNull( $this->lastError, '預期操作失敗' );
		$this->assertStringContainsString(
			$msg,
			$this->lastError->getMessage(),
			"錯誤訊息不包含 \"{$msg}\"，實際訊息：{$this->lastError->getMessage()}"
		);
	}

	/**
	 * 斷言 action hook 被觸發
	 *
	 * @param string $action_name action 名稱
	 */
	protected function assert_action_fired( string $action_name ): void {
		$this->assertGreaterThan(
			0,
			\did_action( $action_name ),
			"Action '{$action_name}' 未被觸發"
		);
	}

	/**
	 * 斷言 REST 路由已被註冊
	 *
	 * @param string $route 路由名稱（完整路徑，如 /v2/powerhouse/posts）
	 */
	protected function assert_rest_route_registered( string $route ): void {
		$routes = \rest_get_server()->get_routes();
		$this->assertArrayHasKey(
			$route,
			$routes,
			"REST 路由 '{$route}' 未被註冊"
		);
	}

	/**
	 * 斷言訂單狀態符合預期
	 *
	 * @param \WC_Order $order           訂單物件
	 * @param string    $expected_status 期望狀態（不含 wc- 前綴）
	 */
	protected function assert_order_status( \WC_Order $order, string $expected_status ): void {
		$fresh_order = \wc_get_order( $order->get_id() );
		$actual      = $fresh_order ? $fresh_order->get_status() : $order->get_status();
		$this->assertSame(
			$expected_status,
			$actual,
			"訂單狀態不符，期望 '{$expected_status}'，實際為 '{$actual}'"
		);
	}
}
