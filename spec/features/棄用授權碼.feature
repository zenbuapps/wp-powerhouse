@ignore
Feature: 棄用授權碼

  Background:
    Given 系統中有以下用戶：
      | userId | name   | email              | role          |
      | 1      | Admin  | admin@example.com  | administrator |
    And 系統中已啟用以下授權碼：
      | product_slug | code        | post_status |
      | power-course | ABC-123-DEF | activated   |

  # ========== 前置（參數）==========
  Rule: 前置（參數）- code 和 product_slug 為必填
    Example: 缺少 code 時應回傳錯誤
      When Admin 發送 POST /wp-json/v2/powerhouse/lc/deactivate：
        | product_slug | power-course |
      Then 應回傳 400 錯誤

  # ========== 後置（狀態）==========
  Rule: 後置（狀態）- 棄用成功後應清除 transient 和 saved_code
    Example: 成功棄用授權碼
      Given Cloud API 會回應 200 對於棄用請求
      When Admin 發送 POST /wp-json/v2/powerhouse/lc/deactivate：
        | code         | ABC-123-DEF  |
        | product_slug | power-course |
      Then 應回傳 200 且 code 為 "deactivate_lc_success"
      And transient "lc_power-course" 應不存在
      And wp_options "powerhouse_license_codes" 中不應包含 "power-course"
