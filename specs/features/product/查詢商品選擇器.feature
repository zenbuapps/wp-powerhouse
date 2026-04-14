@ignore @query
Feature: 查詢商品選擇器

  Background:
    Given Powerhouse 外掛已啟用
    And WooCommerce 已啟用
    And WooCommerce 中有 publish 狀態的商品

  Rule: 後置（狀態）- 回傳輕量商品列表（用於下拉選單）

    Example: 查詢商品選擇器列表
      When 管理員發送 GET /wp-json/v2/powerhouse/products/select
      Then 應回傳 200
      And response body 為商品陣列（每項包含 id、name 等精簡欄位）
      And response header X-WP-Total 包含總數

  Rule: 後置（狀態）- 搜尋數字時以 ID 查詢

    Example: s 參數為數字時以商品 ID 查詢
      When 管理員發送 GET /wp-json/v2/powerhouse/products/select?s=201
      Then 應回傳 200
      And 結果包含 ID=201 的商品

  Rule: 後置（狀態）- post__in 強制包含指定 ID 商品

    Example: 傳入 post__in 確保指定商品出現在結果中
      When 管理員發送 GET /wp-json/v2/powerhouse/products/select?post__in[]=201
      Then 應回傳 200
      And 結果包含 ID=201 的商品（即使不符搜尋條件）
