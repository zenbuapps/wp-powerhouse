@ignore @command
Feature: 刪除訂單

  Background:
    Given Powerhouse 外掛已啟用
    And WooCommerce 已啟用
    And WooCommerce 中有 ID=401 和 ID=402 的訂單

  Rule: 後置（狀態）- 批次刪除訂單

    Example: 成功批次刪除訂單
      When 管理員發送 DELETE /wp-json/v2/powerhouse/orders，body 為：
        | key | value      |
        | ids | [401, 402] |
      Then 應回傳 200
      And code 為 "delete_success"
      And data 為 [401, 402]

    Example: ids 包含不存在的訂單 ID 時拋出例外
      When 管理員發送 DELETE /wp-json/v2/powerhouse/orders，body ids 包含不存在的 ID
      Then 應回傳 500 並包含錯誤訊息 "order not found"

  Rule: 後置（狀態）- 刪除單一訂單

    Example: 成功刪除單一訂單
      When 管理員發送 DELETE /wp-json/v2/powerhouse/orders/401
      Then 應回傳 200
      And code 為 "delete_success"
      And data.id 為 "401"

  Rule: 前置（狀態）- 單筆刪除時 id 必須為數字

    Example: id 非數字時拋出例外
      When 管理員發送 DELETE /wp-json/v2/powerhouse/orders/abc
      Then 應回傳 500 並包含錯誤訊息 "post id format not match"
