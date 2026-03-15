@ignore @command
Feature: 啟用授權碼

  Background:
    Given Powerhouse 外掛已啟用
    And 子外掛已透過 powerhouse_product_infos filter 註冊產品資訊

  Rule: 前置（狀態）- code 和 product_slug 為必填

    Example: 缺少 code 參數時拋出例外
      When 管理員發送 POST /wp-json/v2/powerhouse/lc/activate，body 缺少 code
      Then 應回傳 400 或拋出例外錯誤

    Example: 缺少 product_slug 參數時拋出例外
      When 管理員發送 POST /wp-json/v2/powerhouse/lc/activate，body 缺少 product_slug
      Then 應回傳 400 或拋出例外錯誤

  Rule: 後置（狀態）- 啟用成功時儲存授權碼並設置 transient

    Example: 成功啟用授權碼
      Given CloudAPI 對 license-codes/activate 回傳 200 且授權有效
      When 管理員發送 POST /wp-json/v2/powerhouse/lc/activate，body 為：
        | key          | value        |
        | code         | TEST-CODE-01 |
        | product_slug | power-course |
      Then 應回傳 200
      And code 為 "activate_lc_success"
      And message 包含 "TEST-CODE-01"
      And wp_options powerhouse_license_codes 中 power-course 的 code 為 "TEST-CODE-01"
      And transient lc_power-course 已設置（加密後的授權資訊）

  Rule: 後置（狀態）- CloudAPI 回傳 401 時回傳 WP_Error

    Example: 授權碼無效時回傳錯誤
      Given CloudAPI 回傳 401
      When 管理員發送 POST /wp-json/v2/powerhouse/lc/activate，body 為 code "INVALID-CODE", product_slug "power-course"
      Then 應回傳 500 並包含錯誤訊息

  Rule: 後置（狀態）- CloudAPI 非 200/401 時拋出例外

    Example: CloudAPI 回傳 500 時拋出例外
      Given CloudAPI 回傳 500
      When 管理員發送 POST /wp-json/v2/powerhouse/lc/activate，body 為 code "TEST-CODE-01", product_slug "power-course"
      Then 應回傳 500 並包含錯誤訊息
