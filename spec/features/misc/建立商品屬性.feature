@ignore @command
Feature: 建立商品屬性

  Background:
    Given Powerhouse 外掛已啟用
    And WooCommerce 已啟用

  Rule: 前置（狀態）- name 和 slug 為必填

    Example: 缺少必填參數時拋出例外
      When 管理員發送 POST /wp-json/v2/powerhouse/product-attributes，body 缺少 name
      Then 操作失敗，錯誤包含必填參數提示

  Rule: 後置（狀態）- 成功建立全局商品屬性

    Example: 建立新的商品屬性
      When 管理員發送 POST /wp-json/v2/powerhouse/product-attributes，body 為：
        | key  | value  |
        | name | 材質   |
        | slug | material |
      Then 應回傳 200
      And code 為 "create_success"
      And data 為新建屬性的 ID

  Rule: 後置（狀態）- 建立失敗時回傳 WP_Error

    Example: slug 已存在時建立失敗
      Given 系統中已有 slug 為 "color" 的商品屬性
      When 管理員發送 POST /wp-json/v2/powerhouse/product-attributes，body 包含 slug=color
      Then 回傳 WP_Error
