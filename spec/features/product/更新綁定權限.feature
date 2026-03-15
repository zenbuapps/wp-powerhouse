@ignore @command
Feature: 更新綁定權限

  Background:
    Given Powerhouse 外掛已啟用
    And WooCommerce 已啟用
    And 系統中有以下商品：
      | ID  | name   |
      | 201 | 商品 A |
    And 商品 #201 已綁定權限項目 #301（limit_type=fixed, limit_value=30, limit_unit=day）

  Rule: 前置（狀態）- product_ids、item_ids、limit_type、meta_key 為必填

    Example: 缺少必填參數時拋出例外
      When 管理員發送 POST /wp-json/v2/powerhouse/products/update-bound-items，body 缺少 product_ids
      Then 操作失敗，錯誤包含必填參數提示

  Rule: 後置（狀態）- 成功更新已綁定項目的權限設定

    Example: 更新綁定項目的期限類型
      When 管理員發送 POST /wp-json/v2/powerhouse/products/update-bound-items，body 為：
        | key         | value      |
        | product_ids | [201]      |
        | item_ids    | [301]      |
        | limit_type  | unlimited  |
        | limit_value | 0          |
        | limit_unit  | timestamp  |
        | meta_key    | _bound_items |
      Then 應回傳 200
      And code 為 "success"
      And message 為「修改成功」
      And 商品 #201 的綁定項目 #301 的 limit_type 已更新為 "unlimited"
