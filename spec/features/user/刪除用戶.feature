@ignore @command
Feature: 刪除用戶

  Background:
    Given Powerhouse 外掛已啟用
    And WordPress 中有 ID=101 和 ID=102 的用戶

  Rule: 後置（狀態）- 批次刪除用戶

    Example: 成功批次刪除用戶
      When 管理員發送 DELETE /wp-json/v2/powerhouse/users，body 為：
        | key | value      |
        | ids | [101, 102] |
      Then 應回傳 200
      And code 為 "delete_success"
      And ID=101 的用戶已被刪除
      And ID=102 的用戶已被刪除

    Example: ids 中某個用戶不存在時拋出例外
      When 管理員發送 DELETE /wp-json/v2/powerhouse/users，body ids 包含不存在的用戶 ID
      Then 應回傳 500 並包含錯誤訊息 "delete user failed"

  Rule: 後置（狀態）- 刪除單一用戶

    Example: 成功刪除單一用戶
      When 管理員發送 DELETE /wp-json/v2/powerhouse/users/101
      Then 應回傳 200
      And code 為 "delete_success"
      And data.id 為 "101"
      And ID=101 的用戶已被刪除

  Rule: 前置（狀態）- 單筆刪除時 id 必須為數字

    Example: id 非數字時拋出例外
      When 管理員發送 DELETE /wp-json/v2/powerhouse/users/abc
      Then 應回傳 500 並包含錯誤訊息 "user id format not match"
