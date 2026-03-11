@ignore
Feature: 產生商品變體

  Background:
    Given 系統中有以下用戶：
      | userId | name   | email              | role          |
      | 1      | Admin  | admin@example.com  | administrator |
    And WooCommerce 已啟用
    And 系統中有以下可變商品：
      | product_id | name   | type     |
      | 50         | 商品 A | variable |
    And 商品 50 有以下屬性（用於變體）：
      | attribute_name | options       |
      | pa_color       | red, blue     |
      | pa_size        | S, M          |

  # ========== 前置（參數）==========
  Rule: 前置（參數）- id 必須是數字且商品必須存在
    Example: id 不是數字時應回傳錯誤
      When Admin 發送 POST /wp-json/v2/powerhouse/products/create-variations/abc
      Then 應回傳錯誤 "product id format not match"

    Example: 商品不存在時應回傳錯誤
      When Admin 發送 POST /wp-json/v2/powerhouse/products/create-variations/99999
      Then 應回傳錯誤 "product not found"

  # ========== 後置（狀態）==========
  Rule: 後置（狀態）- 根據屬性組合產生所有變體
    Example: 產生所有可能的變體（2x2 = 4 個）
      When Admin 發送 POST /wp-json/v2/powerhouse/products/create-variations/50
      Then 應回傳 200 且 code 為 "update_attributes_success"
      And data.created_variation_ids 應包含 4 個 ID

  Rule: 後置（狀態）- 重複的變體應被刪除
    Example: 已有重複變體時應清理
      Given 商品 50 已有一個重複的 red+S 變體
      When Admin 發送 POST /wp-json/v2/powerhouse/products/create-variations/50
      Then data.deleted_variation_ids 應包含被刪除的重複變體 ID

  Rule: 後置（狀態）- 不在組合內的舊變體應被刪除
    Example: 屬性變更後多餘的變體應被清理
      Given 商品 50 原本有 red+XL 變體但 XL 已從屬性移除
      When Admin 發送 POST /wp-json/v2/powerhouse/products/create-variations/50
      Then data.deleted_variation_ids 應包含 red+XL 的變體 ID
