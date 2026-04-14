@ignore @query
Feature: 查詢單一用戶

  Background:
    Given Powerhouse 外掛已啟用
    And WordPress 中有 ID=101 的用戶

  Rule: 前置（狀態）- id 必須為數字

    Example: id 非數字時拋出例外
      When 管理員發送 GET /wp-json/v2/powerhouse/users/abc
      Then 應回傳 500 並包含錯誤訊息 "user id format not match"

  Rule: 後置（狀態）- 回傳用戶詳細資料（edit 格式）

    Example: 成功查詢單一用戶
      When 管理員發送 GET /wp-json/v2/powerhouse/users/101
      Then 應回傳 200
      And response body 包含用戶欄位（ID、user_login、user_email、roles 等）

  Rule: 後置（狀態）- 支援 meta_keys 參數回傳指定 meta 欄位

    Example: 傳入 meta_keys 取得用戶 meta 資料
      When 管理員發送 GET /wp-json/v2/powerhouse/users/101?meta_keys[]=_my_meta
      Then 應回傳 200
      And response body 包含 _my_meta 欄位值
