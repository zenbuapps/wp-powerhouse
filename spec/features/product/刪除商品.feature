@ignore @command
Feature: 刪除商品

  Background:
    Given Powerhouse 外掛已啟用
    And WooCommerce 已啟用
    And WooCommerce 中有 ID=201 和 ID=202 的商品

  Rule: 後置（狀態）- 批次刪除商品

    Example: 成功批次刪除商品（force_delete=false 移入回收桶）
      When 管理員發送 DELETE /wp-json/v2/powerhouse/products，body 為：
        | key          | value      |
        | ids          | [201, 202] |
        | force_delete | false      |
      Then 應回傳 200
      And code 為 "delete_success"
      And ID=201 的商品已移入回收桶

    Example: 成功批次永久刪除商品（force_delete=true）
      When 管理員發送 DELETE /wp-json/v2/powerhouse/products，body 為：
        | key          | value      |
        | ids          | [201, 202] |
        | force_delete | true       |
      Then 應回傳 200
      And ID=201 的商品已被永久刪除

    Example: ids 包含不存在的商品 ID 時拋出例外
      When 管理員發送 DELETE /wp-json/v2/powerhouse/products，body ids 包含不存在的 ID
      Then 應回傳 500 並包含錯誤訊息 "product not found"

  Rule: 後置（狀態）- 刪除單一商品

    Example: 成功刪除單一商品
      When 管理員發送 DELETE /wp-json/v2/powerhouse/products/201
      Then 應回傳 200
      And code 為 "delete_success"
      And data.id 為 "201"

    Example: 傳入 force_delete=true 時永久刪除
      When 管理員發送 DELETE /wp-json/v2/powerhouse/products/201，body 包含 force_delete=true
      Then 應回傳 200
      And ID=201 的商品已被永久刪除
