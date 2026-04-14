@ignore @command
Feature: 解除綁定權限項目

  Background:
    Given Powerhouse 外掛已啟用
    And WooCommerce 已啟用
    And 系統中有以下商品：
      | ID  | name   |
      | 201 | 商品 A |
    And 商品 #201 已綁定權限項目 #301

  Rule: 前置（狀態）- product_ids、item_ids、meta_key 為必填

    Example: 缺少必填參數時拋出例外
      When 管理員發送 POST /wp-json/v2/powerhouse/products/unbind-items，body 缺少 product_ids
      Then 操作失敗，錯誤包含必填參數提示

  Rule: 後置（狀態）- 成功解除綁定

    Example: 解除商品與權限項目的綁定
      When 管理員發送 POST /wp-json/v2/powerhouse/products/unbind-items，body 為：
        | key         | value        |
        | product_ids | [201]        |
        | item_ids    | [301]        |
        | meta_key    | _bound_items |
      Then 應回傳 200
      And code 為 "success"
      And message 為「解除綁定成功」
      And 商品 #201 的綁定項目中不再包含 #301
