@ignore
Feature: 更新設定

  Background:
    Given 系統中有以下用戶：
      | userId | name   | email              | role          |
      | 1      | Admin  | admin@example.com  | administrator |

  # ========== 前置（參數）==========
  Rule: 前置（參數）- 只允許已註冊的欄位可以更新
    Example: 傳入未註冊的欄位應被忽略
      When Admin 發送 POST /wp-json/v2/powerhouse/options：
        ```json
        {
          "unknown_field": "value"
        }
        ```
      Then 應回傳 200 但 "unknown_field" 不會被儲存到 wp_options

  # ========== 後置（狀態）==========
  Rule: 後置（狀態）- powerhouse_settings 支援部分更新
    Example: 只更新驗證碼設定而不影響其他設定
      Given 目前 powerhouse_settings 的 delay_email 為 "yes"
      When Admin 發送 POST /wp-json/v2/powerhouse/options：
        ```json
        {
          "powerhouse_settings": {
            "enable_captcha_login": "yes"
          }
        }
        ```
      Then 應回傳 200
      And powerhouse_settings 的 enable_captcha_login 應為 "yes"
      And powerhouse_settings 的 delay_email 應仍為 "yes"
