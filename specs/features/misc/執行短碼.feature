@ignore @query
Feature: 執行短碼

  Background:
    Given Powerhouse 外掛已啟用

  Rule: 後置（狀態）- 回傳短碼執行後的 HTML 內容

    Example: 成功執行短碼
      Given WordPress 中已註冊 [test_shortcode] 短碼
      When 管理員發送 GET /wp-json/v2/powerhouse/shortcode?shortcode=[test_shortcode]
      Then 應回傳 200
      And code 為 "get_shortcode_success"
      And data 為短碼渲染後的 HTML 內容

  Rule: 後置（狀態）- 短碼不存在時回傳原始短碼字串

    Example: 不存在的短碼
      When 管理員發送 GET /wp-json/v2/powerhouse/shortcode?shortcode=[nonexistent]
      Then 應回傳 200
      And data 包含原始短碼文字
