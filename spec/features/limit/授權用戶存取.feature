@ignore @command
Feature: 授權用戶存取

  Background:
    Given Powerhouse 外掛已啟用
    And ph_access_itemmeta 資料表已建立

  Rule: 前置（狀態）- user_ids、item_ids、expire_date 為必填

    Example: 缺少 user_ids 時拋出例外
      When 管理員發送 POST /wp-json/v2/powerhouse/limit/grant-users，body 缺少 user_ids
      Then 應回傳 400 或拋出例外錯誤

    Example: 缺少 item_ids 時拋出例外
      When 管理員發送 POST /wp-json/v2/powerhouse/limit/grant-users，body 缺少 item_ids
      Then 應回傳 400 或拋出例外錯誤

    Example: 缺少 expire_date 時拋出例外
      When 管理員發送 POST /wp-json/v2/powerhouse/limit/grant-users，body 缺少 expire_date
      Then 應回傳 400 或拋出例外錯誤

    Example: user_ids 或 item_ids 為空陣列時拋出例外
      When 管理員發送 POST /wp-json/v2/powerhouse/limit/grant-users，body user_ids 為空陣列
      Then 應回傳 500 並包含錯誤訊息 "missing user_ids or item_ids"

  Rule: 後置（狀態）- 批次授權用戶存取項目並更新 ph_access_itemmeta

    Example: 成功授權多個用戶存取多個項目
      When 管理員發送 POST /wp-json/v2/powerhouse/limit/grant-users，body 為：
        | key         | value          |
        | user_ids    | [101, 102]     |
        | item_ids    | [201, 202]     |
        | expire_date | 1800000000     |
      Then 應回傳 200
      And code 為 "grant_users_success"
      And ph_access_itemmeta 中已存在以下記錄：
        | user_id | post_id | meta_key    | meta_value |
        | 101     | 201     | expire_date | 1800000000 |
        | 101     | 202     | expire_date | 1800000000 |
        | 102     | 201     | expire_date | 1800000000 |
        | 102     | 202     | expire_date | 1800000000 |
      And data.user_ids 為 "101,102"
      And data.item_ids 為 "201,202"

  Rule: 後置（狀態）- expire_date 可為 subscription_{訂閱id} 字串

    Example: 訂閱型授權使用 subscription_{id} 作為 expire_date
      When 管理員發送 POST /wp-json/v2/powerhouse/limit/grant-users，expire_date 為 "subscription_123"
      Then 應回傳 200
      And ph_access_itemmeta 中 meta_value 為 "subscription_123"

  Rule: 後置（狀態）- 觸發 powerhouse/limit/after_grant_user_to_item action

    Example: 授權後觸發 after_grant 事件供子外掛擴展
      Given 子外掛已監聽 powerhouse/limit/after_grant_user_to_item action
      When 管理員發送 POST /wp-json/v2/powerhouse/limit/grant-users，body 包含有效 user_ids 和 item_ids
      Then 子外掛的 after_grant action 被觸發
