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

  # =========================================================
  # 內部實作：decode / set_lc_transient / delete_lc_transient / get_default_lc
  # =========================================================

  Rule: 內部實作 - decode() 使用 JsAesPhp 搭配 Plugin::$kebab 解密 transient

    Example: 解密成功回傳授權狀態陣列
      Given 傳入的 value 為有效的加密字串（由 encode() 產出）
      When 系統呼叫 Base::decode($value)
      Then 使用 JsAesPhp::decrypt($value, Plugin::$kebab) 解密
      And 回傳陣列包含 code / post_status / expire_date / type / product_slug / product_name 欄位
      And 不觸發任何 log

    Example: 解密結果非陣列時回傳 false
      Given JsAesPhp::decrypt 解密後的結果不是陣列（例如 string）
      When 系統呼叫 Base::decode($value)
      Then 回傳 false
      And 不寫入 log

    Example: 解密拋出例外時回傳 false 並記錄 log
      Given 傳入的 value 為無效的加密字串
      When 系統呼叫 Base::decode($value)
      Then JsAesPhp::decrypt 拋出 \Exception
      And 系統以 \J7\WpUtils\Classes\WC::log 紀錄錯誤
      And log context 包含 getMessage 和 value
      And log title 為 "LC::decode error"
      And 回傳 false

  Rule: 內部實作 - set_lc_transient() 同時寫入 option 和加密後的 transient

    Example: 寫入 saved_code 到 option 並寫入加密 transient
      Given $data 為 {"product_slug": "power-course", "code": "LC-001", "post_status": "activated", ...}
      When 系統呼叫 Base::set_lc_transient($data)
      Then 讀取現有 powerhouse_license_codes option（若非陣列則轉為空陣列）
      And 將 $saved_codes["power-course"] 設為 "LC-001"
      And update_option("powerhouse_license_codes", $saved_codes) 被呼叫
      And 從 $data 中 unset logs 欄位（避免加密內容過大）
      And set_transient("lc_power-course", Base::encode($data), 24 * HOUR_IN_SECONDS) 被呼叫

    Example: $data 含 logs 欄位時會先移除再加密
      Given $data 包含 logs 欄位（陣列）
      When 系統呼叫 Base::set_lc_transient($data)
      Then unset($data["logs"]) 在呼叫 encode 之前執行
      And 最終 transient 內加密內容不含 logs 欄位

    Example: CACHE_TIME 常數為 24 小時
      When 系統呼叫 set_lc_transient 寫入 transient
      Then transient expiration 為 24 * HOUR_IN_SECONDS

  Rule: 內部實作 - delete_lc_transient() 同時刪除 option 與 transient

    Example: 刪除 saved_code 並刪除 transient
      Given powerhouse_license_codes option 為 {"power-course": "LC-001", "power-shop": "LC-002"}
      And transient lc_power-course 存在
      When 系統呼叫 Base::delete_lc_transient("power-course")
      Then unset($saved_codes["power-course"]) 被執行
      And update_option("powerhouse_license_codes", {"power-shop": "LC-002"}) 被呼叫
      And delete_transient("lc_power-course") 被呼叫
      And 回傳 delete_transient 的 bool 結果

    Example: option 不是陣列時視為空陣列處理
      Given powerhouse_license_codes option 為字串 "invalid"
      When 系統呼叫 Base::delete_lc_transient("power-course")
      Then $saved_codes 被轉為空陣列
      And update_option("powerhouse_license_codes", []) 被呼叫
      And 不拋出例外

    Example: 刪除不存在的 product_slug 仍正常完成
      Given powerhouse_license_codes option 中不存在 "nonexistent" key
      When 系統呼叫 Base::delete_lc_transient("nonexistent")
      Then unset 不拋出例外
      And update_option 仍會執行
      And delete_transient("lc_nonexistent") 被呼叫

  Rule: 內部實作 - get_default_lc() 組裝未授權狀態的預設物件

    Example: 產出空授權狀態物件
      Given product_slug 為 "power-course"
      And product_name 為 "Power Course"
      And product_info 為 {"link": "https://example.com"}
      When 系統呼叫 Base::get_default_lc("power-course", "Power Course", ["link" => "https://example.com"])
      Then 回傳陣列：
        | 欄位         | 值                  |
        | code         |                     |
        | post_status  |                     |
        | expire_date  |                     |
        | type         |                     |
        | product_slug | power-course        |
        | product_name | Power Course        |
        | link         | https://example.com |

    Example: product_info 缺少 link 時使用空字串
      Given product_info 為 []
      When 系統呼叫 Base::get_default_lc("power-course", "Power Course", [])
      Then 回傳陣列的 link 欄位為空字串 ""
      And 其他欄位維持預設空值
