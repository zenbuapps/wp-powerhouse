@ignore @command
Feature: 重設密碼

  Background:
    Given Powerhouse 外掛已啟用
    And WordPress 中有用戶

  Rule: 前置（狀態）- ids 為必填

    Example: 缺少 ids 時拋出例外
      When 管理員發送 POST /wp-json/v2/powerhouse/users/resetpassword，body 缺少 ids
      Then 應回傳 500 並包含錯誤訊息 "ids is required"

  Rule: 後置（狀態）- 批次寄送重設密碼信

    Example: 成功寄送重設密碼信
      Given WordPress 中有 ID=101 的有效用戶
      When 管理員發送 POST /wp-json/v2/powerhouse/users/resetpassword，body 為：
        | key | value |
        | ids | [101] |
      Then 應回傳 200
      And code 為 "resetpassword_success"
      And message 包含用戶 ID 101
      And 重設密碼信已寄出

  Rule: 後置（狀態）- 不存在的用戶 ID 跳過不報錯

    Example: ids 中包含不存在的用戶 ID 時跳過
      When 管理員發送 POST /wp-json/v2/powerhouse/users/resetpassword，body ids 包含不存在的 ID
      Then 應回傳 200
      And 不存在的用戶被跳過（不報錯）
