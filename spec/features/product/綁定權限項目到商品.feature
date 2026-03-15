@ignore @command
Feature: 綁定權限項目到商品

  Background:
    Given Powerhouse 外掛已啟用
    And WooCommerce 已啟用

  Rule: 前置（狀態）- product_ids、item_ids、limit_type、meta_key 為必填

    Example: 缺少 product_ids 時拋出例外
      When 管理員發送 POST /wp-json/v2/powerhouse/products/bind-items，body 缺少 product_ids
      Then 應回傳 400 或拋出例外錯誤

  Rule: 後置（狀態）- 成功綁定觀看權限項目到商品

    Example: 將課程項目綁定到商品
      When 管理員發送 POST /wp-json/v2/powerhouse/products/bind-items，body 為：
        | key         | value        |
        | product_ids | [201]        |
        | item_ids    | [301, 302]   |
        | limit_type  | unlimited    |
        | meta_key    | _course_ids  |
      Then 應回傳 200
      And code 為 "success"
      And 商品 ID=201 的 _course_ids meta 已包含 301 和 302 的綁定資料

  Rule: 後置（狀態）- 更新已綁定項目的 limit 設定

    Example: 更新已綁定項目的觀看期限
      When 管理員發送 POST /wp-json/v2/powerhouse/products/update-bound-items，body 為：
        | key         | value       |
        | product_ids | [201]       |
        | item_ids    | [301]       |
        | limit_type  | fixed_date  |
        | limit_value | 365         |
        | limit_unit  | day         |
        | meta_key    | _course_ids |
      Then 應回傳 200
      And code 為 "success"

  Rule: 後置（狀態）- 解除綁定項目

    Example: 解除商品與課程的綁定
      When 管理員發送 POST /wp-json/v2/powerhouse/products/unbind-items，body 為：
        | key         | value       |
        | product_ids | [201]       |
        | item_ids    | [301]       |
        | meta_key    | _course_ids |
      Then 應回傳 200
      And code 為 "success"
      And 商品 ID=201 的 _course_ids meta 已移除 301 的綁定資料
