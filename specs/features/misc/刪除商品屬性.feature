@ignore @command
Feature: 刪除商品屬性

  Background:
    Given Powerhouse 外掛已啟用
    And WooCommerce 已啟用
    And 系統中有以下全局商品屬性：
      | attribute_id | attribute_name | attribute_label |
      | 1            | pa_color       | 顏色            |

  Rule: 前置（狀態）- id 必須為數字

    Example: id 非數字時拋出例外
      When 管理員發送 DELETE /wp-json/v2/powerhouse/product-attributes/abc
      Then 操作失敗，錯誤為「term id format not match #abc」

  Rule: 後置（狀態）- 成功刪除商品屬性

    Example: 刪除指定商品屬性
      When 管理員發送 DELETE /wp-json/v2/powerhouse/product-attributes/1
      Then 應回傳 200
      And code 為 "delete_success"
      And data.id 為 "1"

  Rule: 後置（狀態）- 屬性不存在時回傳 WP_Error

    Example: 刪除不存在的屬性
      When 管理員發送 DELETE /wp-json/v2/powerhouse/product-attributes/999
      Then 回傳 WP_Error
