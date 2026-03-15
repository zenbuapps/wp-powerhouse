@ignore @command
Feature: 更新用戶存取期限

  Background:
    Given Powerhouse 外掛已啟用
    And ph_access_itemmeta 資料表已建立
    And 用戶已有存取記錄

  Rule: 前置（狀態）- user_ids、item_ids、timestamp 為必填

    Example: 缺少 timestamp 時拋出例外
      When 管理員發送 POST /wp-json/v2/powerhouse/limit/update-users，body 缺少 timestamp
      Then 應回傳 400 或拋出例外錯誤

  Rule: 後置（狀態）- 批次更新用戶存取期限

    Example: 成功更新多個用戶對多個項目的存取期限
      When 管理員發送 POST /wp-json/v2/powerhouse/limit/update-users，body 為：
        | key       | value      |
        | user_ids  | [101, 102] |
        | item_ids  | [201]      |
        | timestamp | 1900000000 |
      Then 應回傳 200
      And code 為 "update_users_success"
      And ph_access_itemmeta 中 user_id=101, post_id=201 的 expire_date 更新為 1900000000
      And ph_access_itemmeta 中 user_id=102, post_id=201 的 expire_date 更新為 1900000000

  Rule: 後置（狀態）- timestamp 為 0 表示無期限

    Example: timestamp 為 0 時設為無期限存取
      When 管理員發送 POST /wp-json/v2/powerhouse/limit/update-users，timestamp 為 0
      Then 應回傳 200
      And ph_access_itemmeta 中 meta_value 為 "0"（無期限）

  Rule: 後置（狀態）- 觸發 powerhouse/limit/after_update_user_from_item action

    Example: 更新後觸發 after_update 事件供子外掛擴展
      Given 子外掛已監聽 powerhouse/limit/after_update_user_from_item action
      When 管理員發送 POST /wp-json/v2/powerhouse/limit/update-users，body 包含有效參數
      Then 子外掛的 after_update action 被觸發
