@ignore @command
Feature: 建立訂單備註

  Background:
    Given Powerhouse 外掛已啟用
    And WooCommerce 已啟用
    And 系統中有以下訂單：
      | ID  | status       |
      | 201 | wc-completed |

  Rule: 前置（狀態）- order_id、note、is_customer_note 為必填

    Example: 缺少必填參數時拋出例外
      When 管理員發送 POST /wp-json/v2/powerhouse/order-notes，body 缺少 order_id
      Then 操作失敗，錯誤包含必填參數提示

  Rule: 前置（狀態）- 訂單必須存在

    Example: 訂單不存在時拋出例外
      When 管理員發送 POST /wp-json/v2/powerhouse/order-notes，body 為：
        | key              | value     |
        | order_id         | 999       |
        | note             | 測試備註  |
        | is_customer_note | 0         |
      Then 操作失敗，錯誤為「order not found #999」

  Rule: 後置（狀態）- 成功建立訂單備註

    Example: 建立內部備註
      When 管理員發送 POST /wp-json/v2/powerhouse/order-notes，body 為：
        | key              | value     |
        | order_id         | 201       |
        | note             | 內部備註  |
        | is_customer_note | 0         |
      Then 應回傳 200
      And code 為 "create_success"
      And data 為新建備註的 comment_id

  Rule: 後置（狀態）- is_customer_note=1 時為客戶可見備註

    Example: 建立客戶備註
      When 管理員發送 POST /wp-json/v2/powerhouse/order-notes，body 為：
        | key              | value     |
        | order_id         | 201       |
        | note             | 客戶備註  |
        | is_customer_note | 1         |
      Then 應回傳 200
      And 備註為客戶可見類型
