@ignore
Feature: 查詢文章列表

  Background:
    Given 系統中有以下用戶：
      | userId | name   | email              | role          |
      | 1      | Admin  | admin@example.com  | administrator |
    And 系統中有以下文章：
      | post_id | post_title | post_type | post_status |
      | 100     | 文章 A     | post      | publish     |
      | 101     | 文章 B     | post      | draft       |
      | 102     | 頁面 A     | page      | publish     |

  # ========== 前置（參數）==========
  Rule: 前置（參數）- 預設值應為 post_type=post, posts_per_page=20, paged=1, post_status=any
    Example: 不帶參數時應使用預設值查詢
      When Admin 發送 GET /wp-json/v2/powerhouse/posts
      Then 應回傳 200
      And 結果應包含 post_type 為 "post" 的文章
      And Response Header X-WP-PageSize 應為 "20"
      And Response Header X-WP-CurrentPage 應為 "1"

  Rule: 前置（參數）- 可指定 post_type 查詢不同類型
    Example: 查詢 page 類型
      When Admin 發送 GET /wp-json/v2/powerhouse/posts?post_type=page
      Then 結果應只包含 post_type 為 "page" 的文章

  # ========== 後置（回應）==========
  Rule: 後置（回應）- 回應包含分頁 headers
    Example: 查詢結果包含分頁資訊
      When Admin 發送 GET /wp-json/v2/powerhouse/posts?posts_per_page=1&paged=1
      Then Response Header X-WP-Total 應大於 0
      And Response Header X-WP-TotalPages 應大於 0
      And Response Header X-WP-CurrentPage 應為 "1"
      And Response Header X-WP-PageSize 應為 "1"

  Rule: 後置（回應）- 支援 meta_keys 暴露指定 meta
    Example: 傳入 meta_keys 應在結果中包含 meta 值
      Given 文章 100 有 meta "custom_field" = "custom_value"
      When Admin 發送 GET /wp-json/v2/powerhouse/posts?meta_keys[]=custom_field
      Then 結果中文章 100 應包含 "custom_field" 欄位
