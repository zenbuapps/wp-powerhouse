@ignore
Feature: 查詢訂單列表

  Background:
    Given 系統中有以下用戶：
      | userId | name   | email              | role          |
      | 1      | Admin  | admin@example.com  | administrator |
    And WooCommerce 已啟用
    And 系統中有以下訂單：
      | order_id | status        | customer_id |
      | 200      | wc-processing | 10          |
      | 201      | wc-completed  | 11          |

  # ========== 前置（參數）==========
  Rule: 前置（參數）- 預設值應為 limit=30, paged=1, type=shop_order
    Example: 不帶參數時應使用預設值
      When Admin 發送 GET /wp-json/v2/powerhouse/orders
      Then 應回傳 200
      And 結果應為訂單陣列

  # ========== 後置（回應）==========
  Rule: 後置（回應）- 回應包含分頁 headers
    Example: 查詢結果包含分頁資訊
      When Admin 發送 GET /wp-json/v2/powerhouse/orders
      Then Response Header X-WP-Total 應大於 0
      And Response Header X-WP-TotalPages 應大於 0

  Rule: 後置（回應）- 支援狀態篩選
    Example: 篩選特定狀態的訂單
      When Admin 發送 GET /wp-json/v2/powerhouse/orders?status=wc-completed
      Then 結果應只包含 status 為 "wc-completed" 的訂單
