@ignore
Feature: 複製文章/商品

  Background:
    Given 系統中有以下用戶：
      | userId | name   | email              | role          |
      | 1      | Admin  | admin@example.com  | administrator |
    And 系統中有以下文章：
      | post_id | post_title | post_type |
      | 100     | 文章 A     | post      |
      | 101     | 子文章 A1  | post      |

  # ========== 前置（參數）==========
  Rule: 前置（參數）- id 為必填且必須是數字
    Example: 缺少 id 時應回傳錯誤
      When Admin 發送 POST /wp-json/v2/powerhouse/copy/abc
      Then 應回傳錯誤 "id is required"

  # ========== 後置（狀態）==========
  Rule: 後置（狀態）- 成功複製後回傳新 ID
    Example: 成功複製文章（含子文章）
      When Admin 發送 POST /wp-json/v2/powerhouse/copy/100
      Then 應回傳 200 且 code 為 "post_copy_success"
      And data 應為新文章的 ID
      And 新文章的標題應與原文章相同
