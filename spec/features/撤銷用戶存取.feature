@ignore
Feature: 撤銷用戶存取

  Background:
    Given 系統中有以下用戶：
      | userId | name   | email              | role          |
      | 1      | Admin  | admin@example.com  | administrator |
      | 10     | User1  | user1@example.com  | subscriber    |
    And 用戶 10 已被授權存取項目 100（expire_date = 0）

  # ========== 前置（參數）==========
  Rule: 前置（參數）- user_ids 和 item_ids 為必填
    Example: 缺少 user_ids 時應回傳錯誤
      When Admin 發送 POST /wp-json/v2/powerhouse/limit/revoke-users：
        | item_ids | [100] |
      Then 應回傳 400 錯誤

  # ========== 後置（狀態）==========
  Rule: 後置（狀態）- 撤銷成功後 ph_access_itemmeta 表中對應記錄被刪除
    Example: 成功撤銷用戶存取
      When Admin 發送 POST /wp-json/v2/powerhouse/limit/revoke-users：
        | user_ids | [10]  |
        | item_ids | [100] |
      Then 應回傳 200 且 code 為 "revoke_users_success"
      And ph_access_itemmeta 表中不應有 post_id=100 且 user_id=10 的記錄
