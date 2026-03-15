@ignore @command
Feature: 建立訂單

  Background:
    Given Powerhouse 外掛已啟用
    And WooCommerce 已啟用

  Rule: 後置（狀態）- 建立空訂單（pending 狀態）

    Example: 成功建立訂單
      When 管理員發送 POST /wp-json/v2/powerhouse/orders
      Then 應回傳 200
      And code 為 "create_success"
      And data 包含新建訂單物件
      And 訂單 status 為 "pending"
