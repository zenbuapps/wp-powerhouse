@ignore @command
Feature: 更新商品屬性

  Background:
    Given Powerhouse 外掛已啟用
    And WooCommerce 已啟用
    And 系統中有以下全局商品屬性：
      | attribute_id | attribute_name | attribute_label |
      | 1            | pa_color       | 顏色            |

  Rule: 前置（狀態）- id 必須為數字

    Example: id 非數字時拋出例外
      When 管理員發送 POST /wp-json/v2/powerhouse/product-attributes/abc
      Then 操作失敗，錯誤為「product attribute id format not match #abc」

  Rule: 後置（狀態）- 成功更新商品屬性

    Example: 更新屬性名稱
      When 管理員發送 POST /wp-json/v2/powerhouse/product-attributes/1，body 為：
        | key  | value  |
        | name | 色彩   |
      Then 應回傳 200
      And code 為 "update_success"
      And data.id 為 "1"

  Rule: 後置（狀態）- 更新失敗時回傳 WP_Error

    Example: 屬性不存在時更新失敗
      When 管理員發送 POST /wp-json/v2/powerhouse/product-attributes/999，body 包含 name=test
      Then 回傳 WP_Error
