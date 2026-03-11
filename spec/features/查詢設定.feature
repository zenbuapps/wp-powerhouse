@ignore
Feature: 查詢設定

  Background:
    Given 系統中有以下用戶：
      | userId | name   | email              | role          |
      | 1      | Admin  | admin@example.com  | administrator |

  # ========== 前置（參數）==========
  Rule: 前置（參數）- 無特殊參數要求
    Example: 直接查詢即可
      When Admin 發送 GET /wp-json/v2/powerhouse/options
      Then 應回傳 200 且 code 為 "get_options_success"

  # ========== 後置（回應）==========
  Rule: 後置（回應）- 回傳完整的 powerhouse_settings 物件
    Example: 回傳包含所有設定欄位
      When Admin 發送 GET /wp-json/v2/powerhouse/options
      Then data 中 powerhouse_settings 應包含：
        | 欄位                              | 預設值        |
        | enable_captcha_login              | no            |
        | enable_captcha_register           | no            |
        | enable_email_domain_check_register| yes           |
        | delay_email                       | yes           |
        | last_name_optional                | yes           |
        | theme                             | power         |
        | enable_theme                      | yes           |
