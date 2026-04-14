@ignore @command
Feature: 更新商品屬性

  Background:
    Given Powerhouse 外掛已啟用
    And WooCommerce 已啟用
    And WooCommerce 中有 ID=201 的可變商品

  Rule: 前置（狀態）- id 必須為數字且商品必須存在

    Example: 商品不存在時拋出例外
      When 管理員發送 POST /wp-json/v2/powerhouse/products/attributes/99999
      Then 應回傳 500 並包含錯誤訊息 "product not found"

  Rule: 後置（狀態）- 成功更新商品屬性

    Example: 更新商品的自訂屬性
      When 管理員發送 POST /wp-json/v2/powerhouse/products/attributes/201，body 為：
        | new_attributes 欄位 | 值          |
        | name               | 顏色        |
        | options            | [紅, 藍, 綠] |
        | visible            | true        |
        | variation          | true        |
      Then 應回傳 200
      And code 為 "update_attributes_success"
      And 商品 ID=201 已設置對應屬性

  Rule: 後置（狀態）- is_taxonomy=true 時先建立全局屬性再綁定

    Example: 建立全局屬性並綁定到商品
      When 管理員發送 POST /wp-json/v2/powerhouse/products/attributes/201，body new_attributes 包含 is_taxonomy=true 的屬性
      Then 應回傳 200
      And WooCommerce 全局屬性已建立
      And 屬性以 "pa_" 前綴的 taxonomy 形式綁定到商品
