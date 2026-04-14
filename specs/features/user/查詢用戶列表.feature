@ignore @query
Feature: 查詢用戶列表

  Background:
    Given Powerhouse 外掛已啟用
    And WordPress 中有用戶

  Rule: 後置（狀態）- 回傳分頁用戶列表

    Example: 以預設參數查詢用戶列表
      When 管理員發送 GET /wp-json/v2/powerhouse/users
      Then 應回傳 200
      And response body 為用戶陣列
      And response header X-WP-Total 包含用戶總數
      And response header X-WP-TotalPages 包含總頁數

  Rule: 後置（狀態）- 支援 meta_keys 參數回傳指定 meta 欄位

    Example: 傳入 meta_keys 取得用戶 meta 資料
      When 管理員發送 GET /wp-json/v2/powerhouse/users?meta_keys[]=_custom_meta
      Then 應回傳 200
      And 每個用戶包含 _custom_meta 欄位值

  Rule: 後置（狀態）- 支援 role 篩選

    Example: 篩選 subscriber 角色用戶
      When 管理員發送 GET /wp-json/v2/powerhouse/users?role=subscriber
      Then 應回傳 200
      And 結果中所有用戶角色為 "subscriber"
