@ignore @command
Feature: 產生商品變體

  Background:
    Given Powerhouse 外掛已啟用
    And WooCommerce 已啟用
    And WooCommerce 中有 ID=201 的可變商品，且已設置用於變體的屬性

  Rule: 前置（狀態）- id 必須為數字且商品必須存在

    Example: 商品不存在時拋出例外
      When 管理員發送 POST /wp-json/v2/powerhouse/products/create-variations/99999
      Then 應回傳 500 並包含錯誤訊息 "product not found"

  Rule: 後置（狀態）- 自動產生所有屬性組合的變體

    Example: 成功產生商品變體
      When 管理員發送 POST /wp-json/v2/powerhouse/products/create-variations/201
      Then 應回傳 200
      And code 為 "update_attributes_success"
      And data.created_variation_ids 包含新建變體的 ID 列表
      And data.deleted_variation_ids 包含已刪除重複或無效變體的 ID 列表

  Rule: 後置（狀態）- 重複的變體組合會被刪除

    Example: 已存在的重複變體被清除
      Given 商品 ID=201 有重複的變體
      When 管理員發送 POST /wp-json/v2/powerhouse/products/create-variations/201
      Then 應回傳 200
      And 重複的變體已被永久刪除（force delete）
