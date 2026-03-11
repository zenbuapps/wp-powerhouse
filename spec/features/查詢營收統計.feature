@ignore
Feature: 查詢營收統計

  Background:
    Given 系統中有以下用戶：
      | userId | name   | email              | role          |
      | 1      | Admin  | admin@example.com  | administrator |
    And WooCommerce 已啟用

  # ========== 前置（參數）==========
  Rule: 前置（參數）- interval 預設為 day
    Example: 不帶 interval 時使用 day
      When Admin 發送 GET /wp-json/v2/powerhouse/reports/revenue/stats?after=2026-01-01&before=2026-01-31
      Then 應回傳 200
      And intervals 中每項的 interval 應為日期格式

  # ========== 後置（回應）==========
  Rule: 後置（回應）- 回傳 totals 和 intervals
    Example: 查詢結果包含統計摘要
      When Admin 發送 GET /wp-json/v2/powerhouse/reports/revenue/stats?after=2026-01-01&before=2026-03-01&interval=month
      Then 結果應包含 totals 物件：
        | 欄位           | 類型    |
        | orders_count   | integer |
        | total_sales    | number  |
        | net_revenue    | number  |
        | refunds        | number  |
        | gross_sales    | number  |
      And 結果應包含 intervals 陣列
      And 結果應包含 total 和 pages

  Rule: 後置（回應）- 支援按商品篩選
    Example: 查詢特定商品的營收
      When Admin 發送 GET /wp-json/v2/powerhouse/reports/revenue/stats?product_includes[]=50
      Then 結果應只包含商品 50 的營收統計
