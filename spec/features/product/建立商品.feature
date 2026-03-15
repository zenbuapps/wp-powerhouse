@ignore @command
Feature: 建立商品

  Background:
    Given Powerhouse 外掛已啟用
    And WooCommerce 已啟用

  Rule: 後置（狀態）- 批次建立商品並回傳新商品 ID

    Example: 建立單個商品
      When 管理員發送 POST /wp-json/v2/powerhouse/products，body 為：
        | key  | value |
        | name | 課程A |
      Then 應回傳 200
      And code 為 "create_success"
      And data 陣列包含新商品的 ID

  Rule: 後置（狀態）- qty 參數可批次建立多個商品

    Example: 批次建立 3 個商品
      When 管理員發送 POST /wp-json/v2/powerhouse/products，body 包含 qty=3
      Then 應回傳 200
      And data 陣列包含 3 個新商品 ID

  Rule: 後置（狀態）- action=update-many 時批次更新商品

    Example: 批次更新多個商品
      Given WooCommerce 中有 ID=201 和 ID=202 的商品
      When 管理員發送 POST /wp-json/v2/powerhouse/products，body 包含 action=update-many 及 ids=[201,202]
      Then 應回傳 200
      And code 為 "update_success"
      And data 陣列包含 201 和 202

  Rule: 前置（狀態）- action=update-many 時 ids 為必填

    Example: action=update-many 缺少 ids 時拋出例外
      When 管理員發送 POST /wp-json/v2/powerhouse/products，body 包含 action=update-many 但無 ids
      Then 應回傳 500 並包含錯誤訊息 "ids is required"

  Rule: 後置（狀態）- 支援子外掛透過 powerhouse/product/separator_body_params filter 擴展參數

    Example: 子外掛透過 filter 修改 body_params
      Given 子外掛已掛載 powerhouse/product/separator_body_params filter
      When 管理員發送 POST /wp-json/v2/powerhouse/products，body 包含子外掛自訂欄位
      Then 應回傳 200
      And 子外掛 filter 已被觸發並套用
