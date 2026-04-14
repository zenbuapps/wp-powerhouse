@ignore @command
Feature: 棄用授權碼

  Background:
    Given Powerhouse 外掛已啟用
    And wp_options powerhouse_license_codes 中有已啟用的授權碼

  Rule: 前置（狀態）- code 和 product_slug 為必填

    Example: 缺少 code 時拋出例外
      When 管理員發送 POST /wp-json/v2/powerhouse/lc/deactivate，body 缺少 code
      Then 應回傳 400 或拋出例外錯誤

    Example: 缺少 product_slug 時拋出例外
      When 管理員發送 POST /wp-json/v2/powerhouse/lc/deactivate，body 缺少 product_slug
      Then 應回傳 400 或拋出例外錯誤

  Rule: 後置（狀態）- 棄用成功時清除授權碼快取

    Example: 成功棄用授權碼
      Given CloudAPI 對 license-codes/deactivate 回傳 200
      When 管理員發送 POST /wp-json/v2/powerhouse/lc/deactivate，body 為：
        | key          | value        |
        | code         | TEST-CODE-01 |
        | product_slug | power-course |
      Then 應回傳 200
      And code 為 "deactivate_lc_success"
      And message 包含 "TEST-CODE-01"
      And wp_options powerhouse_license_codes 中 power-course 記錄已移除
      And transient lc_power-course 已被刪除

  Rule: 後置（狀態）- CloudAPI 失敗時清除本地快取但拋出例外

    Example: CloudAPI 非 200 時清除本地快取並拋出例外
      Given CloudAPI 回傳 500
      When 管理員發送 POST /wp-json/v2/powerhouse/lc/deactivate，body 為 code "TEST-CODE-01", product_slug "power-course"
      Then 應回傳 500 並包含錯誤訊息
      And transient lc_power-course 已被刪除（本地快取仍會清除）
