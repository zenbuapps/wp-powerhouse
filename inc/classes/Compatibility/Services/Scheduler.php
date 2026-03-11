<?php

declare (strict_types = 1);

namespace J7\Powerhouse\Compatibility\Services;

use J7\Powerhouse\Plugin;
use J7\Powerhouse\Domains\Limit\Utils\CreateTable;

require_once __DIR__ . '/deprecated.php';

/**
 * Scheduler 排程執行動作
 * Compatibility 不同版本間的相容性設定
 *
 * @since v2.0.17
 */
final class Scheduler {
	use \J7\WpUtils\Traits\SingletonTrait;

	const AS_COMPATIBILITY_ACTION = 'powerhouse_compatibility_action_scheduler';

	/** Constructor */
	public function __construct() {
		\add_action( 'plugins_loaded', [ __CLASS__ , 'redirect' ], 10 );
		\add_action( self::AS_COMPATIBILITY_ACTION, [ __CLASS__, 'compatibility' ]);
		AutoUpdate::instance();
		// 排程只執行一次的兼容設定
		\add_action( 'init', [ __CLASS__, 'compatibility_action_scheduler' ] );
	}


	/**
	 * 排程只執行一次的兼容設定
	 *
	 * @return void
	 */
	public static function compatibility_action_scheduler(): void {
		// 以下是每次版本都會執行一次
		$scheduled_version = \get_option('powerhouse_compatibility_action_scheduled');

		if ($scheduled_version === Plugin::$version) {
			return;
		}

		\as_enqueue_async_action( self::AS_COMPATIBILITY_ACTION, [] );
		\update_option('powerhouse_compatibility_action_scheduled', Plugin::$version);
	}

	/**
	 * 執行排程
	 *
	 * @return void
	 */
	public static function compatibility(): void {

		/**
		 * ============== START 相容性代碼 ==============
		 */

		// 判斷是否已經有 wp_ph_access_itemmeta 這張 table，沒有就建立
		CreateTable::create_itemmeta_table();

		EmailValidator::instance();
		Loader::instance();
		ApiBooster::instance();

		// 改寫 wp_actionscheduler_actions table schema，把 args 類型從    varchar(191) NULL 改為 longtext NULL
		self::modify_action_scheduler_table_schema();

		/**
		 * ============== END 相容性代碼 ==============
		 */

		// ❗不要刪除此行，註記已經執行過相容設定
		\flush_rewrite_rules();
		\wp_cache_flush();
	}


	/**
	 * 舊版本授權碼要 redirect 到新版本授權碼
	 */
	public static function redirect(): void {

		if (!\is_admin()) {
			return;
		}

		if (!isset($_GET['page'])) {
			return;
		}

		if ($_GET['page'] !== 'powerhouse-license-codes') {
			return;
		}
		\wp_safe_redirect(\admin_url('admin.php?page=powerhouse#license-code'));
		exit;
	}

	/**
	 * 改寫 wp_actionscheduler_actions table schema
	 * 把 args 類型從   varchar(191) NULL 改為 longtext NULL
	 *
	 * @return void
	 */
	public static function modify_action_scheduler_table_schema(): void {
		if (self::is_longtext()) {
			// 已經是 longtext 就不需要改寫
			return;
		}

		global $wpdb;

		$table_name = "{$wpdb->prefix}actionscheduler_actions";

		/** @var array<mixed> $result */
		$result = [];

		try {

			$sql_query = "SHOW CREATE TABLE `$table_name`";

			// 執行查詢，並取得結果
			// 該查詢會返回兩欄：'Table' 和 'Create Table'
			$origin_table_schema_array = $wpdb->get_row( $sql_query, ARRAY_A ); // phpcs:ignore
			$origin_table_schema       = (string) ( $origin_table_schema_array['Create Table'] ?? '' );
			if (!$origin_table_schema) {
				Plugin::logger("modify_action_scheduler_table_schema: 無法取得 {$table_name} 的表結構，請確認 Action Scheduler 是否已安裝並有執行過排程。");
				return;
			}

			// 為了使用 dbDelta，您需要進行以下調整：
			// a. 移除反引號 (backticks: `)
			$dbdelta_sql = str_replace( '`', '', $origin_table_schema );

			// b. 替換或移除 ENGINE/CHARSET/COLLATE 部分，並確保以 $wpdb->collate 結尾
			// 這是最複雜的一步，因為要確保只保留 CREATE TABLE ... ( ... ) 部分

			// 為了簡化，我們先移除整個表屬性部分 (例如 ENGINE=InnoDB ...)
			$dbdelta_sql = preg_replace( '/\s(ENGINE|DEFAULT\sCHARACTER\sSET|COLLATE)=[^;]+$/i', '', $dbdelta_sql ) ?? '';

			// 確保以分號 (;) 結尾的語句被移除，並準備添加 $wpdb->collate
			$dbdelta_sql = rtrim( $dbdelta_sql, ';' ) . " $wpdb->collate;";

			// c. 在 $dbdelta_sql 中修改 'args' 欄位定義 (例如從 TEXT NOT NULL 改為 LONGTEXT NULL)
			// 這一步通常需要使用字串替換或正則表達式來針對特定欄位進行操作。

			// 假設原本是 'args text NOT NULL,'
			$old_definition = 'args varchar(191)';
			$new_definition = 'args longtext';

			$final_dbdelta_sql = \str_replace( $old_definition, $new_definition, $dbdelta_sql );
			// 移除索引
			// 1. 移除目標索引
			$final_dbdelta_sql = \str_replace( 'KEY args (args),', '', $final_dbdelta_sql );

			// 2. 修正留下的 SQL 語法錯誤 (連續逗號)
			// 將所有「逗號後面跟著任意空白字符，再跟著一個逗號」替換成單個逗號。
			// 這可以處理 'scheduled_date_gmt(...) ,' 和 ', KEY group_id(...)' 之間的問題。
			$final_dbdelta_sql = \preg_replace( '/,\s*,/', ',', $final_dbdelta_sql ) ?? '';

			// 3. (可選但推薦) 移除多餘的空行，使語句更整潔
			$final_dbdelta_sql = \preg_replace( '/\n\s*\n/', "\n", $final_dbdelta_sql ) ?? '';
			$wpdb->query( "DROP INDEX `args` ON `{$table_name}`" );  // phpcs:ignore

			// 4. 執行 dbDelta
			if ( ! \function_exists( 'dbDelta' ) ) {
				require_once ABSPATH . 'wp-admin/includes/upgrade.php';
			}

			$result                      = \dbDelta( $final_dbdelta_sql ); // 實際執行 dbDelta
			$result['final_dbdelta_sql'] = $final_dbdelta_sql;

			// 檢查有沒有修改成功
			$column_type           = self::get_column_type( 'args' );
			$result['column_type'] = $column_type;

			$is_success = \strpos( $column_type ?? '', 'longtext' ) !== false;

			Plugin::logger(
			\sprintf(
			'modify_action_scheduler_table_schema: 已執行修改 %1$s 的表結構 %2$s。',
			$table_name,
			$is_success ? '成功✅' : '失敗❌'
			),
			'debug',
			$result
			);

		} catch (\Throwable $th) {
			Plugin::logger(
			\sprintf(
			'modify_action_scheduler_table_schema: 已執行修改 %1$s 的表結構失敗❌，%2$s',
			$table_name,
			$th->getMessage()
			),
			'debug',
			$result
			);
		}
	}

	/**
	 * 檢查 args 欄位是否為 longtext
	 *
	 * @return bool
	 */
	private static function is_longtext( string $column_name = 'args' ): bool {
		$column_type           = self::get_column_type( 'args' );
		$result['column_type'] = $column_type;
		return \strpos( $column_type ?? '', 'longtext' ) !== false;
	}

	/**
	 * 檢查指定資料表和欄位的資料類型
	 *
	 * @param string $column_name 欄位名稱。
	 * @return string|null 該欄位的資料類型 (Type)，如果查詢失敗則返回 null
	 */
	private static function get_column_type( string $column_name = 'args' ): string|null {
		global $wpdb;

		$table_name = "{$wpdb->prefix}actionscheduler_actions";

		// 執行 DESCRIBE 查詢來獲取所有欄位信息
		// $wpdb->prepare 不能用於表名，但我們假設 $table_name 是從 $wpdb->prefix 構建的，是安全的
		$sql = "DESCRIBE `$table_name`";

		// 使用 $wpdb->get_results 獲取所有欄位定義
		$schema = $wpdb->get_results( $sql, ARRAY_A );  // phpcs:ignore

		if ( empty( $schema ) ) {
			// 資料表不存在或查詢失敗
			return null;
		}

		// 遍歷結果，找到目標欄位
		foreach ( $schema as $column ) {
			if ( $column['Field'] === $column_name ) {
				// 返回欄位的 Type (例如：'longtext', 'varchar(191)', 'datetime')
				return $column['Type'];
			}
		}

		// 欄位不存在
		return null;
	}
}
