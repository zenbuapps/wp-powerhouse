@ignore
Feature: 查詢商品列表

  Background:
    Given 系統中有以下用戶：
      | userId | name   | email              | role          |
      | 1      | Admin  | admin@example.com  | administrator |
    And WooCommerce 已啟用
    And 系統中有以下商品：
      | product_id | name   | status  | regular_price |
      | 50         | 商品 A | publish | 100           |
      | 51         | 商品 B | draft   | 200           |
      | 52         | 商品 C | publish | 300           |

  # ========== 前置（參數）==========
  Rule: 前置（參數）- 預設值應為 status=[publish,draft,pending], posts_per_page=20
    Example: 不帶參數時應使用預設值
      When Admin 發送 GET /wp-json/v2/powerhouse/products
      Then 應回傳 200
      And 結果應包含 publish 和 draft 狀態的商品

  # ========== 後置（回應）==========
  Rule: 後置（回應）- 回應包含分頁 headers
    Example: 查詢結果包含分頁資訊
      When Admin 發送 GET /wp-json/v2/powerhouse/products?posts_per_page=1
      Then Response Header X-WP-Total 應大於 0
      And Response Header X-WP-TotalPages 應大於 0

  Rule: 後置（回應）- 支援 meta_keys 暴露指定 meta
    Example: 傳入 meta_keys 應在商品結果中包含指定 meta
      Given 商品 50 有 meta "custom_key" = "custom_val"
      When Admin 發送 GET /wp-json/v2/powerhouse/products?meta_keys[]=custom_key
      Then 結果中商品 50 應包含 "custom_key" 欄位
