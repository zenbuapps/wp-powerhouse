@ignore
Feature: 建立訂單備註

  Background:
    Given 系統中有以下用戶：
      | userId | name   | email              | role          |
      | 1      | Admin  | admin@example.com  | administrator |
    And WooCommerce 已啟用
    And 系統中有以下訂單：
      | order_id | status        |
      | 200      | wc-processing |

  # ========== 前置（參數）==========
  Rule: 前置（參數）- order_id、note、is_customer_note 為必填
    Example: 缺少 order_id 時應回傳錯誤
      When Admin 發送 POST /wp-json/v2/powerhouse/order-notes：
        | note             | 測試備註 |
        | is_customer_note | 0        |
      Then 應回傳 400 錯誤

  Rule: 前置（狀態）- 訂單必須存在
    Example: 訂單不存在時應回傳錯誤
      When Admin 發送 POST /wp-json/v2/powerhouse/order-notes：
        | order_id         | 99999    |
        | note             | 測試備註 |
        | is_customer_note | 0        |
      Then 應回傳錯誤 "order not found"

  # ========== 後置（狀態）==========
  Rule: 後置（狀態）- 備註成功新增到訂單
    Example: 成功新增管理員備註
      When Admin 發送 POST /wp-json/v2/powerhouse/order-notes：
        | order_id         | 200      |
        | note             | 測試備註 |
        | is_customer_note | 0        |
      Then 應回傳 200 且 code 為 "create_success"
      And 訂單 200 應有一筆新備註

    Example: 成功新增客戶備註
      When Admin 發送 POST /wp-json/v2/powerhouse/order-notes：
        | order_id         | 200        |
        | note             | 給客戶的備註 |
        | is_customer_note | 1          |
      Then 應回傳 200
      And 訂單 200 應有一筆客戶備註
