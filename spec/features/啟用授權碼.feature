@ignore
Feature: 啟用授權碼

  Background:
    Given 系統中有以下用戶：
      | userId | name   | email              | role          |
      | 1      | Admin  | admin@example.com  | administrator |
    And 系統中已註冊以下產品資訊（透過 powerhouse_product_infos filter）：
      | product_slug    | product_name   |
      | power-course    | Power Course   |
      | power-shop      | Power Shop     |

  # ========== 前置（參數）==========
  Rule: 前置（參數）- code 和 product_slug 為必填
    Example: 缺少 code 時應回傳錯誤
      When Admin 發送 POST /wp-json/v2/powerhouse/lc/activate：
        | product_slug | power-course |
      Then 應回傳 400 錯誤

    Example: 缺少 product_slug 時應回傳錯誤
      When Admin 發送 POST /wp-json/v2/powerhouse/lc/activate：
        | code | ABC-123-DEF |
      Then 應回傳 400 錯誤

  # ========== 後置（狀態）==========
  Rule: 後置（狀態）- 啟用成功後應設定 transient 和 option
    Example: 成功啟用授權碼
      Given Cloud API 會回應 200 對於 code "ABC-123-DEF" 和 product_slug "power-course"
      When Admin 發送 POST /wp-json/v2/powerhouse/lc/activate：
        | code         | ABC-123-DEF  |
        | product_slug | power-course |
      Then 應回傳 200 且 code 為 "activate_lc_success"
      And transient "lc_power-course" 應存在且為 AES 加密字串
      And wp_options "powerhouse_license_codes" 中 "power-course" 應為 "ABC-123-DEF"

  Rule: 後置（狀態）- Cloud API 回應 401 時不應設定 transient
    Example: 授權碼無效
      Given Cloud API 會回應 401 對於 code "INVALID-CODE"
      When Admin 發送 POST /wp-json/v2/powerhouse/lc/activate：
        | code         | INVALID-CODE |
        | product_slug | power-course |
      Then 應回傳錯誤
      And transient "lc_power-course" 應不存在
