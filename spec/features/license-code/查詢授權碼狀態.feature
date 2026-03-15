@ignore @query
Feature: 查詢授權碼狀態

  Background:
    Given Powerhouse 外掛已啟用
    And wp_options 中有 powerhouse_license_codes 記錄
    And 子外掛已透過 powerhouse_product_infos filter 註冊產品資訊：
      | product_slug | name         | link |
      | power-course | Power Course | https://example.com |

  Rule: 後置（狀態）- 若 transient 存在則直接回傳快取

    Example: transient 存在時直接回傳解密後的狀態
      Given transient lc_power-course 存在且為有效加密值
      When 管理員發送 GET /wp-json/v2/powerhouse/lc
      Then 應回傳 200
      And data 陣列包含 power-course 的授權資訊：
        | 欄位         | 值          |
        | product_slug | power-course |
        | post_status  | activated    |
        | code         | TEST-CODE-01 |

  Rule: 後置（狀態）- 若 transient 不存在且無 saved_code 則回傳預設空狀態

    Example: 沒有 transient 也沒有 saved_code 時回傳預設空值
      Given transient lc_power-course 不存在
      And powerhouse_license_codes 中無 power-course 記錄
      When 管理員發送 GET /wp-json/v2/powerhouse/lc
      Then 應回傳 200
      And data 陣列包含 power-course 的預設授權資訊：
        | 欄位         | 值          |
        | product_slug | power-course |
        | post_status  |              |
        | code         |              |

  Rule: 後置（狀態）- 若 transient 不存在但有 saved_code 則重新向 CloudAPI 驗證

    Example: transient 過期時自動重新驗證授權碼
      Given transient lc_power-course 不存在
      And powerhouse_license_codes 中有 power-course 的 saved_code "TEST-CODE-01"
      And CloudAPI 回傳 200 且授權有效
      When 管理員發送 GET /wp-json/v2/powerhouse/lc
      Then 應回傳 200
      And data 中 power-course 的 post_status 為 "activated"
      And transient lc_power-course 已重新設置

  Rule: 後置（狀態）- 若重新驗證失敗則維持啟用狀態（容錯）

    Example: CloudAPI 無法連線時維持原啟用狀態
      Given transient lc_power-course 不存在
      And powerhouse_license_codes 中有 power-course 的 saved_code "TEST-CODE-01"
      And CloudAPI 連線失敗（拋出例外）
      When 管理員發送 GET /wp-json/v2/powerhouse/lc
      Then 應回傳 200
      And data 中 power-course 的 post_status 為 "activated"
      And 臨時 transient 以預設啟用值設置（等待下次到期再重驗）

  Rule: 後置（狀態）- CloudAPI 回傳 401 時清除授權狀態

    Example: CloudAPI 回傳 401 時授權狀態被清除
      Given transient lc_power-course 不存在
      And powerhouse_license_codes 中有 power-course 的 saved_code "INVALID-CODE"
      And CloudAPI 回傳 401
      When 管理員發送 GET /wp-json/v2/powerhouse/lc
      Then 應回傳 200
      And data 中 power-course 的 post_status 為 ""
      And transient lc_power-course 已被清除
