@ignore @command
Feature: 撤銷用戶存取

  Background:
    Given Powerhouse 外掛已啟用
    And ph_access_itemmeta 資料表已建立
    And 用戶已有存取記錄

  Rule: 前置（狀態）- user_ids 和 item_ids 為必填

    Example: 缺少 user_ids 時拋出例外
      When 管理員發送 POST /wp-json/v2/powerhouse/limit/revoke-users，body 缺少 user_ids
      Then 應回傳 400 或拋出例外錯誤

    Example: user_ids 或 item_ids 為空陣列時拋出例外
      When 管理員發送 POST /wp-json/v2/powerhouse/limit/revoke-users，body user_ids 為空陣列
      Then 應回傳 500 並包含錯誤訊息 "missing user_ids or item_ids"

  Rule: 後置（狀態）- 批次撤銷用戶存取記錄

    Example: 成功撤銷多個用戶對多個項目的存取
      When 管理員發送 POST /wp-json/v2/powerhouse/limit/revoke-users，body 為：
        | key      | value      |
        | user_ids | [101, 102] |
        | item_ids | [201]      |
      Then 應回傳 200
      And code 為 "revoke_users_success"
      And ph_access_itemmeta 中 user_id=101, post_id=201 的記錄已刪除
      And ph_access_itemmeta 中 user_id=102, post_id=201 的記錄已刪除
      And data.user_ids 為 "101,102"
      And data.item_ids 為 "201"

  Rule: 後置（狀態）- 觸發 powerhouse/limit/after_revoke_user_from_item action

    Example: 撤銷後觸發 after_revoke 事件供子外掛擴展
      Given 子外掛已監聽 powerhouse/limit/after_revoke_user_from_item action
      When 管理員發送 POST /wp-json/v2/powerhouse/limit/revoke-users，body 包含有效參數
      Then 子外掛的 after_revoke action 被觸發
