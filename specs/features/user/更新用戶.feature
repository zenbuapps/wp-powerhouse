@ignore @command
Feature: 更新用戶

  Background:
    Given Powerhouse 外掛已啟用
    And WordPress 中有 ID=101 的用戶

  Rule: 前置（狀態）- id 必須為數字

    Example: id 非數字時拋出例外
      When 管理員發送 POST /wp-json/v2/powerhouse/users/abc
      Then 應回傳 500 並包含錯誤訊息 "user id format not match"

  Rule: 後置（狀態）- 成功更新用戶欄位

    Example: 成功更新用戶 display_name
      When 管理員發送 POST /wp-json/v2/powerhouse/users/101，body 為：
        | key          | value    |
        | display_name | 新顯示名稱 |
      Then 應回傳 200
      And code 為 "update_success"
      And data.id 為 "101"
      And WordPress 中 ID=101 用戶的 display_name 已更新
