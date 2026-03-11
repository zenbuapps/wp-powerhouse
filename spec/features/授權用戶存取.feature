@ignore
Feature: 授權用戶存取

  Background:
    Given 系統中有以下用戶：
      | userId | name   | email              | role          |
      | 1      | Admin  | admin@example.com  | administrator |
      | 10     | User1  | user1@example.com  | subscriber    |
      | 11     | User2  | user2@example.com  | subscriber    |
    And 系統中有以下內容項目：
      | post_id | post_title |
      | 100     | 課程 A     |
      | 101     | 課程 B     |

  # ========== 前置（參數）==========
  Rule: 前置（參數）- user_ids、item_ids、expire_date 為必填
    Example: 缺少 user_ids 時應回傳錯誤
      When Admin 發送 POST /wp-json/v2/powerhouse/limit/grant-users：
        | item_ids    | [100]      |
        | expire_date | 0          |
      Then 應回傳 400 錯誤

    Example: 缺少 item_ids 時應回傳錯誤
      When Admin 發送 POST /wp-json/v2/powerhouse/limit/grant-users：
        | user_ids    | [10]       |
        | expire_date | 0          |
      Then 應回傳 400 錯誤

  # ========== 後置（狀態）==========
  Rule: 後置（狀態）- 授權成功後 ph_access_itemmeta 表應有對應記錄
    Example: 成功授權用戶存取項目（無期限）
      When Admin 發送 POST /wp-json/v2/powerhouse/limit/grant-users：
        | user_ids    | [10, 11]   |
        | item_ids    | [100]      |
        | expire_date | 0          |
      Then 應回傳 200 且 code 為 "grant_users_success"
      And ph_access_itemmeta 表中應有記錄：
        | post_id | user_id | meta_key    | meta_value |
        | 100     | 10      | expire_date | 0          |
        | 100     | 11      | expire_date | 0          |

    Example: 成功授權用戶存取項目（指定到期日）
      When Admin 發送 POST /wp-json/v2/powerhouse/limit/grant-users：
        | user_ids    | [10]           |
        | item_ids    | [100, 101]     |
        | expire_date | 1735689600     |
      Then 應回傳 200
      And ph_access_itemmeta 表中應有記錄：
        | post_id | user_id | meta_key    | meta_value |
        | 100     | 10      | expire_date | 1735689600 |
        | 101     | 10      | expire_date | 1735689600 |
