@ignore
Feature: 重設密碼

  Background:
    Given 系統中有以下用戶：
      | userId | name   | email              | role          |
      | 1      | Admin  | admin@example.com  | administrator |
      | 10     | User1  | user1@example.com  | subscriber    |
      | 11     | User2  | user2@example.com  | subscriber    |

  # ========== 前置（參數）==========
  Rule: 前置（參數）- ids 不可為空
    Example: 缺少 ids 時應回傳錯誤
      When Admin 發送 POST /wp-json/v2/powerhouse/users/resetpassword：
        ```json
        { "ids": [] }
        ```
      Then 應回傳錯誤 "ids is required"

  # ========== 後置（狀態）==========
  Rule: 後置（狀態）- 成功寄出重設密碼信
    Example: 批量寄送重設密碼信
      When Admin 發送 POST /wp-json/v2/powerhouse/users/resetpassword：
        ```json
        { "ids": ["10", "11"] }
        ```
      Then 應回傳 200 且 code 為 "resetpassword_success"
      And 重設密碼信應已寄送給 user1@example.com 和 user2@example.com
