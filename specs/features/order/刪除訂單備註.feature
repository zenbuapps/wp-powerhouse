@ignore @command
Feature: 刪除訂單備註

  Background:
    Given Powerhouse 外掛已啟用
    And WooCommerce 已啟用
    And 系統中有以下訂單備註：
      | comment_ID | comment_content |
      | 501        | 測試備註         |

  Rule: 前置（狀態）- id 必須為數字

    Example: id 非數字時拋出例外
      When 管理員發送 DELETE /wp-json/v2/powerhouse/order-notes/abc
      Then 操作失敗，錯誤為「order note id format not match #abc」

  Rule: 後置（狀態）- 成功刪除訂單備註

    Example: 刪除指定訂單備註
      When 管理員發送 DELETE /wp-json/v2/powerhouse/order-notes/501
      Then 應回傳 200
      And code 為 "delete_success"
      And data 為 true
