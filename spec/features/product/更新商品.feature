@ignore @command
Feature: 更新商品

  Background:
    Given Powerhouse 外掛已啟用
    And WooCommerce 已啟用
    And WooCommerce 中有 ID=201 的商品

  Rule: 前置（狀態）- id 必須為數字

    Example: id 非數字時拋出例外
      When 管理員發送 POST /wp-json/v2/powerhouse/products/abc，body 包含更新資料
      Then 應回傳 500 並包含錯誤訊息 "product id format not match"

  Rule: 前置（狀態）- 商品必須存在

    Example: 商品不存在時拋出例外
      When 管理員發送 POST /wp-json/v2/powerhouse/products/99999，body 包含更新資料
      Then 應回傳 500 並包含錯誤訊息 "product not found"

  Rule: 後置（狀態）- 成功更新商品

    Example: 成功更新商品名稱
      When 管理員發送 POST /wp-json/v2/powerhouse/products/201，body 為：
        | key  | value  |
        | name | 課程A2 |
      Then 應回傳 200
      And code 為 "update_success"
      And data.id 為商品 ID

  Rule: 後置（狀態）- description、short_description、slug 跳過 sanitize

    Example: description 包含 HTML 不被 sanitize
      When 管理員發送 POST /wp-json/v2/powerhouse/products/201，body 包含含 HTML 的 description
      Then 應回傳 200
      And 商品的 description 包含原始 HTML
