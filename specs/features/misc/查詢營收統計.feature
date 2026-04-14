@ignore @query
Feature: 查詢營收統計

  Background:
    Given Powerhouse 外掛已啟用
    And WooCommerce 已啟用
    And 系統中有以下訂單：
      | ID  | status       | total | date_created       |
      | 201 | wc-completed | 1000  | 2024-01-15T10:00:00 |
      | 202 | wc-refunded  | 500   | 2024-01-15T14:00:00 |
      | 203 | wc-completed | 2000  | 2024-01-16T10:00:00 |

  Rule: 後置（狀態）- 回傳收入統計的 totals 和 intervals

    Example: 查詢指定日期範圍的營收統計
      When 管理員發送 GET /wp-json/v2/powerhouse/reports/revenue/stats?after=2024-01-01&before=2024-01-31&interval=day
      Then 應回傳 200
      And data.totals 包含：
        | 欄位           | 說明         |
        | orders_count   | 訂單總數     |
        | total_sales    | 總銷售額     |
        | net_revenue    | 淨收入       |
        | refunds        | 退款金額     |
        | gross_sales    | 毛銷售額     |
      And data.intervals 為按日期分組的統計陣列

  Rule: 後置（狀態）- 額外欄位包含退款訂單數和非退款訂單數

    Example: 回傳包含自訂報表欄位
      When 管理員發送 GET /wp-json/v2/powerhouse/reports/revenue/stats?after=2024-01-01&before=2024-01-31
      Then 應回傳 200
      And data.totals 包含 refunded_orders_count
      And data.totals 包含 non_refunded_orders_count

  Rule: 後置（狀態）- 支援 product_includes 篩選特定商品的統計

    Example: 篩選特定商品的營收
      When 管理員發送 GET /wp-json/v2/powerhouse/reports/revenue/stats?product_includes=101,102
      Then 應回傳 200
      And 統計結果僅包含指定商品的營收資料

  Rule: 後置（狀態）- 支援 interval 參數（day/week/month/year）

    Example: 按月彙總營收
      When 管理員發送 GET /wp-json/v2/powerhouse/reports/revenue/stats?interval=month
      Then 應回傳 200
      And data.intervals 按月份分組

  # ---------------------------------------------------------------------------
  # 核心查詢邏輯（get_reports_revenue_stats）
  # ---------------------------------------------------------------------------

  Rule: 系統行為 - get_reports_revenue_stats 以 WooCommerce Analytics Query 執行查詢

    Example: 呼叫 get_reports_revenue_stats 使用預設參數
      Given 呼叫 get_reports_revenue_stats([]) 不傳任何參數
      When get_reports_revenue_stats 執行
      Then page 預設為 1
      And per_page 預設為 10000（一次取完所有記錄）
      And interval 預設為 "day"
      And order 預設為 "asc"
      And force_cache_refresh 預設為 false
      And fields 預設包含 net_revenue、avg_order_value、orders_count、avg_items_per_order、num_items_sold、coupons、coupons_count、total_customers、total_sales、refunds、shipping、gross_sales
      And 使用 Automattic\WooCommerce\Admin\API\Reports\Revenue\Query 執行查詢

    Example: 傳入 product_includes 時使用 GenericQuery（products-stats）
      Given 傳入 params 包含 product_includes = [101, 102]
      When get_reports_revenue_stats 執行
      Then context 被設為 "view"
      And fields 額外加入 "items_sold"
      And 使用 Automattic\WooCommerce\Admin\API\Reports\GenericQuery 查詢（report slug: products-stats）

    Example: extra_report_columns 的 keys 被加入 fields
      Given extra_report_columns 包含 refunded_orders_count 與 non_refunded_orders_count
      When get_reports_revenue_stats 執行
      Then fields 包含 refunded_orders_count
      And fields 包含 non_refunded_orders_count

    Example: 查詢結果透過 filter 可被改寫
      Given 外部註冊了 powerhouse/report/revenue/stats filter
      When get_reports_revenue_stats 回傳前
      Then 執行 apply_filters('powerhouse/report/revenue/stats', $data, $query_args)
      And 回傳 filter 處理後的結果

    Example: query_args 的空值會被 array_filter 移除
      Given 傳入 params 中 before = null
      When get_reports_revenue_stats 執行
      Then query_args 中不包含 before（被 array_filter 移除）

  # ---------------------------------------------------------------------------
  # 擴充欄位 hooks（add_report_columns / add_report_column_types）
  # ---------------------------------------------------------------------------

  Rule: 系統行為 - 僅在 power-course API 請求時註冊報表欄位擴充

    Example: 非 power-course 請求時不註冊擴充 hook
      Given REQUEST_URI 不包含 "/wp-json/power-course"
      When V2Api::__construct 執行
      Then extra_report_columns 保持為空陣列
      And woocommerce_admin_report_columns filter 不被註冊
      And woocommerce_rest_reports_column_types filter 不被註冊

    Example: power-course 請求時註冊擴充欄位與型別
      Given REQUEST_URI 包含 "/wp-json/power-course"
      When V2Api::__construct 執行
      Then extra_report_columns 包含 refunded_orders_count 的 SQL CASE 片段
      And extra_report_columns 包含 non_refunded_orders_count 的 SQL CASE 片段
      And add_report_columns 被註冊於 woocommerce_admin_report_columns filter（priority 100）
      And add_report_column_types 被註冊於 woocommerce_rest_reports_column_types filter（priority 100）
      And should_use_cache 被註冊於 woocommerce_analytics_report_should_use_cache filter（priority 100）

  Rule: 系統行為 - add_report_columns 將自訂欄位合併到 WC Analytics 查詢

    Example: 將 extra_report_columns 合併到現有 columns
      Given WC Analytics 傳入既有的 columns 陣列
      When add_report_columns($columns, $context, $table_name) 被呼叫
      Then 回傳值為 columns 與 extra_report_columns 的 array_merge 結果
      And 回傳陣列包含 refunded_orders_count 的 CASE 語句
      And 回傳陣列包含 non_refunded_orders_count 的 CASE 語句

  Rule: 系統行為 - add_report_column_types 擴展 WC Analytics 欄位型別

    Example: 將 extra_report_column_types 合併到現有 column_types
      Given WC Analytics 傳入既有的 column_types 陣列
      When add_report_column_types($column_types, $array) 被呼叫
      Then 回傳值為 column_types 與 extra_report_column_types 的 array_merge 結果
      And refunded_orders_count 的型別為 "intval"
      And non_refunded_orders_count 的型別為 "intval"

  # ---------------------------------------------------------------------------
  # 快取判斷（should_use_cache）
  # ---------------------------------------------------------------------------

  Rule: 系統行為 - should_use_cache 在 local 環境停用報表快取

    Example: local 環境不使用快取
      Given Plugin::$env === "local"
      When should_use_cache($should_cache, $cache_key) 被呼叫
      Then 回傳 false

    Example: staging 環境使用快取
      Given Plugin::$env === "staging"
      When should_use_cache($should_cache, $cache_key) 被呼叫
      Then 回傳 true

    Example: production 環境使用快取
      Given Plugin::$env === "production"
      When should_use_cache($should_cache, $cache_key) 被呼叫
      Then 回傳 true
