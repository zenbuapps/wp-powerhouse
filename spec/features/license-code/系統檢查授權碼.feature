@ignore @query
Feature: 系統檢查授權碼

  Background:
    Given Powerhouse 外掛已啟用
    And 子外掛已透過 powerhouse_product_infos filter 註冊產品資訊：
      | product_slug | name         | link                |
      | power-course | Power Course | https://example.com |

  Rule: 後置（狀態）- transient 有效時直接解密取得授權狀態

    Example: transient 存在且有效時直接回傳
      Given transient lc_power-course 存在且為有效加密值
      When 系統呼叫 Base::get_lc_array()
      Then 回傳陣列中包含 power-course 的解密授權資訊
      And 不會發送 Cloud API 請求

  Rule: 後置（狀態）- transient 過期且有 saved_code 時重新啟用

    Example: transient 過期且有 saved_code 時觸發 activate
      Given transient lc_power-course 不存在（過期）
      And powerhouse_license_codes option 中有 power-course 的 saved_code "LC-001"
      And Cloud API 回傳 200
      When 系統呼叫 Base::get_lc_array()
      Then 回傳陣列中包含 power-course 的授權資訊
      And transient lc_power-course 已重新設置（加密後的值）

  Rule: 後置（狀態）- transient 過期且無 saved_code 時使用預設未授權狀態

    Example: transient 過期且無 saved_code 時回傳預設空值
      Given transient lc_power-course 不存在（過期）
      And powerhouse_license_codes option 中無 power-course 記錄
      When 系統呼叫 Base::get_lc_array()
      Then 回傳陣列中包含 power-course 的預設授權資訊：
        | 欄位         | 值           |
        | code         |              |
        | post_status  |              |
        | product_slug | power-course |

  Rule: 後置（狀態）- Cloud API 連線失敗時維持啟用狀態（容錯）

    Example: activate 拋出例外時仍維持 activated
      Given transient lc_power-course 不存在（過期）
      And powerhouse_license_codes option 中有 power-course 的 saved_code "LC-001"
      And Cloud API 連線失敗（拋出例外）
      When 系統呼叫 Base::get_lc_array()
      Then 回傳陣列中 power-course 的 post_status 為 "activated"
      And 暫存預設啟用值到 transient（等待下次到期再重驗）
      And 不清除 saved_code

  Rule: 後置（狀態）- Cloud API 回傳 401 時清除授權

    Example: activate 回傳 WP_Error（401）時使用預設狀態
      Given transient lc_power-course 不存在（過期）
      And powerhouse_license_codes option 中有 power-course 的 saved_code "INVALID"
      And Cloud API 回傳 401
      When 系統呼叫 Base::get_lc_array()
      Then 回傳陣列中 power-course 的 post_status 為 ""
      And transient 和 saved_code 已被清除

  Rule: 後置（狀態）- ia() 檢查單一產品是否已啟用

    Example: 產品已啟用時 ia() 回傳 true
      Given transient lc_power-course 存在且 post_status 為 "activated"
      When 系統呼叫 Base::ia("power-course")
      Then 回傳 true

    Example: 產品未啟用時 ia() 回傳 false
      Given transient lc_power-course 不存在
      When 系統呼叫 Base::ia("power-course")
      Then 回傳 false
