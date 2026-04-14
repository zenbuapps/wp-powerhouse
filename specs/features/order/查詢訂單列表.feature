@ignore @query
Feature: 查詢訂單列表

  Background:
    Given Powerhouse 外掛已啟用
    And WooCommerce 已啟用
    And WooCommerce 中有訂單

  Rule: 後置（狀態）- 回傳分頁訂單列表

    Example: 以預設參數查詢訂單列表
      When 管理員發送 GET /wp-json/v2/powerhouse/orders
      Then 應回傳 200
      And response body 為訂單陣列
      And response header X-WP-Total 包含訂單總數
      And response header X-WP-TotalPages 包含總頁數
      And response header X-WP-CurrentPage 為 "1"
      And 預設每頁 30 筆，type 為 shop_order

  Rule: 後置（狀態）- 支援 status 篩選

    Example: 篩選指定狀態訂單
      When 管理員發送 GET /wp-json/v2/powerhouse/orders?status[]=wc-processing
      Then 應回傳 200
      And 結果中所有訂單狀態為 "wc-processing"

  Rule: 後置（狀態）- 支援 customer_id 篩選

    Example: 查詢特定客戶的訂單
      When 管理員發送 GET /wp-json/v2/powerhouse/orders?customer_id=101
      Then 應回傳 200
      And 結果中所有訂單的 customer_id 為 101
