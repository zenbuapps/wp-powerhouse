@ignore @command
Feature: 更新訂單

  Background:
    Given Powerhouse 外掛已啟用
    And WooCommerce 已啟用
    And WooCommerce 中有 ID=401 的訂單

  Rule: 前置（狀態）- id 必須為數字

    Example: id 非數字時拋出例外
      When 管理員發送 POST /wp-json/v2/powerhouse/orders/abc
      Then 應回傳 500 並包含錯誤訊息 "post id format not match"

  Rule: 後置（狀態）- 成功更新訂單欄位

    Example: 成功更新訂單 meta
      When 管理員發送 POST /wp-json/v2/powerhouse/orders/401，body 包含自訂欄位
      Then 應回傳 200
      And code 為 "update_success"
      And data.id 為 "401"

  Rule: 後置（狀態）- 支援子外掛透過 powerhouse/order/separator_body_params filter 擴展參數

    Example: 子外掛透過 filter 修改 body_params
      Given 子外掛已掛載 powerhouse/order/separator_body_params filter
      When 管理員發送 POST /wp-json/v2/powerhouse/orders/401，body 包含子外掛自訂欄位
      Then 應回傳 200
      And 子外掛 filter 已被觸發
