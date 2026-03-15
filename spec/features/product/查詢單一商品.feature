@ignore @query
Feature: 查詢單一商品

  Background:
    Given Powerhouse 外掛已啟用
    And WooCommerce 已啟用
    And WooCommerce 中有 ID=201 的商品

  Rule: 前置（狀態）- id 必須為數字

    Example: id 非數字時拋出例外
      When 管理員發送 GET /wp-json/v2/powerhouse/products/abc
      Then 應回傳 500 並包含錯誤訊息 "id 格式不符合"

  Rule: 前置（狀態）- 商品必須存在

    Example: 商品不存在時拋出例外
      When 管理員發送 GET /wp-json/v2/powerhouse/products/99999
      Then 應回傳 500 並包含錯誤訊息 "product not found"

  Rule: 後置（狀態）- 回傳單一商品詳細資料

    Example: 成功查詢單一商品
      When 管理員發送 GET /wp-json/v2/powerhouse/products/201
      Then 應回傳 200
      And response body 包含商品欄位（id、name、status、price 等）

  Rule: 後置（狀態）- 支援 partials 和 meta_keys 參數

    Example: 傳入 partials 只取部分欄位
      When 管理員發送 GET /wp-json/v2/powerhouse/products/201?partials[]=id&partials[]=name
      Then 應回傳 200
      And response body 只包含 id 和 name 欄位
