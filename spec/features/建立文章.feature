@ignore
Feature: 建立文章

  Background:
    Given 系統中有以下用戶：
      | userId | name   | email              | role          |
      | 1      | Admin  | admin@example.com  | administrator |

  # ========== 前置（參數）==========
  Rule: 前置（參數）- qty 預設為 1
    Example: 不帶 qty 時應建立 1 篇文章
      When Admin 發送 POST /wp-json/v2/powerhouse/posts：
        | post_type  | post    |
        | post_title | 測試文章 |
      Then 應回傳 200 且 code 為 "create_success"
      And data 應為包含 1 個 ID 的陣列

  # ========== 後置（狀態）==========
  Rule: 後置（狀態）- 批量建立多篇文章
    Example: 帶 qty=3 應建立 3 篇文章
      When Admin 發送 POST /wp-json/v2/powerhouse/posts：
        | post_type  | post    |
        | post_title | 批量文章 |
        | qty        | 3       |
      Then 應回傳 200 且 code 為 "create_success"
      And data 應為包含 3 個 ID 的陣列

  Rule: 後置（狀態）- 支援 meta_data 寫入
    Example: 建立文章時同時寫入 meta
      When Admin 發送 POST /wp-json/v2/powerhouse/posts：
        | post_type    | post        |
        | post_title   | 含 meta 文章 |
        | custom_field | custom_val  |
      Then 應回傳 200
      And 新建文章的 meta "custom_field" 應為 "custom_val"
