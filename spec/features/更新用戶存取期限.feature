@ignore
Feature: 更新用戶存取期限

  Background:
    Given 系統中有以下用戶：
      | userId | name   | email              | role          |
      | 1      | Admin  | admin@example.com  | administrator |
      | 10     | User1  | user1@example.com  | subscriber    |
    And 用戶 10 已被授權存取項目 100（expire_date = 1735689600）

  # ========== 前置（參數）==========
  Rule: 前置（參數）- user_ids、item_ids、timestamp 為必填
    Example: 缺少 timestamp 時應回傳錯誤
      When Admin 發送 POST /wp-json/v2/powerhouse/limit/update-users：
        | user_ids | [10]  |
        | item_ids | [100] |
      Then 應回傳 400 錯誤

  # ========== 後置（狀態）==========
  Rule: 後置（狀態）- 更新成功後 expire_date 被更新
    Example: 成功延長用戶存取期限
      When Admin 發送 POST /wp-json/v2/powerhouse/limit/update-users：
        | user_ids  | [10]       |
        | item_ids  | [100]      |
        | timestamp | 1767225600 |
      Then 應回傳 200 且 code 為 "update_users_success"
      And ph_access_itemmeta 表中 post_id=100 且 user_id=10 的 expire_date 應為 1767225600

    Example: 設定為無期限
      When Admin 發送 POST /wp-json/v2/powerhouse/limit/update-users：
        | user_ids  | [10]  |
        | item_ids  | [100] |
        | timestamp | 0     |
      Then 應回傳 200
      And ph_access_itemmeta 表中 post_id=100 且 user_id=10 的 expire_date 應為 0
