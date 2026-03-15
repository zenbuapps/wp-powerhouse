@ignore @query
Feature: 查詢文章欄位

  Background:
    Given Powerhouse 外掛已啟用
    And WordPress 中有以下文章：
      | ID  | post_title | post_type | post_status |
      | 101 | 測試文章    | post      | publish     |
    And 文章 #101 有以下 post meta：
      | meta_key    | meta_value |
      | custom_key  | custom_val |

  Rule: 前置（狀態）- 文章必須存在

    Example: 文章不存在時拋出例外
      When 管理員發送 GET /wp-json/v2/powerhouse/posts/999/field/post_title
      Then 操作失敗，錯誤為「post not found #999」

  Rule: 前置（狀態）- id 必須為數字

    Example: id 非數字時拋出例外
      When 管理員發送 GET /wp-json/v2/powerhouse/posts/abc/field/post_title
      Then 操作失敗，錯誤為「post id format not match #abc」

  Rule: 前置（狀態）- field_name 為必填

    Example: field_name 為空時拋出例外
      When 管理員發送 GET /wp-json/v2/powerhouse/posts/101/field/
      Then 操作失敗，錯誤為「field name is required」

  Rule: 後置（狀態）- 查詢標準 post 欄位時直接回傳該欄位值

    Example: 查詢 post_title 欄位
      When 管理員發送 GET /wp-json/v2/powerhouse/posts/101/field/post_title
      Then 應回傳 200
      And code 為 "get_field_success"
      And data 為 "測試文章"

  Rule: 後置（狀態）- 查詢非標準欄位時回傳 post meta 值

    Example: 查詢自訂 meta key
      When 管理員發送 GET /wp-json/v2/powerhouse/posts/101/field/custom_key
      Then 應回傳 200
      And code 為 "get_field_success"
      And data 為 "custom_val"
