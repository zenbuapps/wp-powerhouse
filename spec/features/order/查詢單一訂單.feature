@ignore @query
Feature: 查詢單一訂單

  Background:
    Given Powerhouse 外掛已啟用
    And WooCommerce 已啟用
    And WooCommerce 中有 ID=401 的訂單

  Rule: 前置（狀態）- id 必須為數字

    Example: id 非數字時拋出例外
      When 管理員發送 GET /wp-json/v2/powerhouse/orders/abc
      Then 應回傳 500 並包含錯誤訊息 "post id format not match"

  Rule: 前置（狀態）- 訂單必須存在

    Example: 訂單不存在時拋出例外
      When 管理員發送 GET /wp-json/v2/powerhouse/orders/99999
      Then 應回傳 500 並包含錯誤訊息 "order not found"

  Rule: 後置（狀態）- 回傳訂單詳細資料（含 order items）

    Example: 成功查詢單一訂單
      When 管理員發送 GET /wp-json/v2/powerhouse/orders/401
      Then 應回傳 200
      And response body 包含訂單欄位（id、status、total、line_items 等）
