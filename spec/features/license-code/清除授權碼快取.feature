@ignore @command
Feature: 清除授權碼快取

  Background:
    Given Powerhouse 外掛已啟用

  Rule: 前置（狀態）- product_slug 為必填

    Example: 缺少 product_slug 時回傳 400
      When CloudAPI 發送 POST /wp-json/v2/powerhouse/lc/invalidate，body 缺少 product_slug
      Then 應回傳 400
      And code 為 "invalidate_lc_cache_failed"
      And message 為 "產品 Slug 不能為空"

  Rule: 後置（狀態）- 清除快取成功（公開端點，無需認證）

    Example: CloudAPI 主動回呼清除快取
      Given transient lc_power-course 存在
      When CloudAPI 發送 POST /wp-json/v2/powerhouse/lc/invalidate，body 為：
        | key          | value        |
        | product_slug | power-course |
      Then 應回傳 200
      And code 為 "invalidate_lc_cache_success"
      And transient lc_power-course 已被刪除
      And wp_options powerhouse_license_codes 中 power-course 記錄已移除
      And data.product_slug 為 "power-course"

  Rule: 後置（狀態）- 此端點為公開端點（permission_callback 恆為 true）

    Example: 未認證的請求也可成功清除快取
      When 未認證的請求發送 POST /wp-json/v2/powerhouse/lc/invalidate，body 包含有效 product_slug
      Then 應回傳 200
