@ignore
Feature: 執行短碼

  Background:
    Given 系統中有以下用戶：
      | userId | name   | email              | role          |
      | 1      | Admin  | admin@example.com  | administrator |

  # ========== 前置（參數）==========
  Rule: 前置（參數）- shortcode 參數為必填
    Example: 傳入 shortcode 參數
      When Admin 發送 GET /wp-json/v2/powerhouse/shortcode?shortcode=[my_shortcode]
      Then 應回傳 200 且 code 為 "get_shortcode_success"

  # ========== 後置（回應）==========
  Rule: 後置（回應）- 回傳 shortcode 執行後的 HTML
    Example: 成功執行短碼並回傳結果
      Given 系統中已註冊 [test_shortcode] 會回傳 "<p>Hello</p>"
      When Admin 發送 GET /wp-json/v2/powerhouse/shortcode?shortcode=[test_shortcode]
      Then data 應為 "<p>Hello</p>"
