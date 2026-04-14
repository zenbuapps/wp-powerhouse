@ignore @command
Feature: 建立用戶

  Background:
    Given Powerhouse 外掛已啟用

  Rule: 後置（狀態）- 批次建立用戶並回傳新用戶 ID

    Example: 建立單個用戶
      When 管理員發送 POST /wp-json/v2/powerhouse/users，body 為：
        | key            | value              |
        | user_login     | newuser            |
        | user_email     | newuser@example.com |
        | user_pass      | password123        |
      Then 應回傳 200
      And code 為 "create_success"
      And data 陣列包含新用戶的 ID

  Rule: 後置（狀態）- qty 參數可批次建立多個用戶

    Example: 批次建立 3 個用戶
      When 管理員發送 POST /wp-json/v2/powerhouse/users，body 包含 qty=3
      Then 應回傳 200
      And data 陣列包含 3 個新用戶 ID

  Rule: 後置（狀態）- 有 ids 參數時批次更新用戶

    Example: 批次更新多個用戶角色
      Given WordPress 中有 ID=101 和 ID=102 的用戶
      When 管理員發送 POST /wp-json/v2/powerhouse/users，body 包含 ids=[101,102] 及 role=subscriber
      Then 應回傳 200
      And code 為 "update_success"
      And data 陣列包含 101 和 102

  Rule: 後置（狀態）- 批次上傳一次處理 100 筆（BATCH_SIZE）

    Example: 批次更新超過 100 筆用戶分批處理
      Given WordPress 中有 150 個用戶
      When 管理員發送 POST /wp-json/v2/powerhouse/users，body 包含 150 個 ids
      Then 應回傳 200
      And 所有 150 個用戶均被更新
