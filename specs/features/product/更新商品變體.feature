@ignore @command
Feature: 更新商品變體

  Background:
    Given Powerhouse 外掛已啟用
    And WooCommerce 已啟用
    And WooCommerce 中有 ID=201 的可變商品，且有子變體

  Rule: 前置（狀態）- id 必須為數字且商品必須存在

    Example: 商品不存在時拋出例外
      When 管理員發送 POST /wp-json/v2/powerhouse/products/update-variations/99999
      Then 應回傳 500 並包含錯誤訊息 "product not found"

  Rule: 後置（狀態）- 成功更新各變體資料及預設屬性

    Example: 更新商品變體價格及預設屬性
      When 管理員發送 POST /wp-json/v2/powerhouse/products/update-variations/201，body 為：
        | key                | value                              |
        | default_attributes | {"pa_color": "red"}                |
        | variations         | [{"id": 301, "regular_price": "100"}] |
      Then 應回傳 200
      And code 為 "update_variations_success"
      And 商品 ID=201 的預設屬性已更新
      And 變體 ID=301 的 regular_price 已更新為 "100"

  Rule: 後置（狀態）- 不存在的變體 ID 跳過不報錯

    Example: 變體 ID 不存在時跳過
      When 管理員發送 POST /wp-json/v2/powerhouse/products/update-variations/201，body 包含不存在的 variation id
      Then 應回傳 200
      And 不存在的變體被跳過（不報錯）
