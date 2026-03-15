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
