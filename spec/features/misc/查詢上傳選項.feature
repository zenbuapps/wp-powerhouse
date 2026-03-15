@ignore @query
Feature: 查詢上傳選項

  Background:
    Given Powerhouse 外掛已啟用

  Rule: 後置（狀態）- 回傳 WordPress 允許的 MIME 類型清單

    Example: 成功取得允許的 MIME 類型
      When 管理員發送 GET /wp-json/v2/powerhouse/upload/options
      Then 應回傳 200
      And data.allowed_mime_types 為 MIME 類型物件，包含如：
        | extension | mime_type  |
        | jpg       | image/jpeg |
        | png       | image/png  |
        | pdf       | application/pdf |
