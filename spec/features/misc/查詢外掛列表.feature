@ignore @query
Feature: 查詢外掛列表

  Background:
    Given Powerhouse 外掛已啟用

  Rule: 後置（狀態）- 回傳所有已安裝外掛及其啟用狀態

    Example: 成功查詢外掛列表
      When 管理員發送 GET /wp-json/v2/powerhouse/plugins
      Then 應回傳 200
      And response body 為外掛陣列
      And 每個外掛項目包含：
        | 欄位      | 說明            |
        | key       | 外掛路徑         |
        | is_active | 是否已啟用       |
        | Name      | 外掛名稱         |
        | Version   | 版本號           |
      And response header X-WP-Total 包含外掛總數
      And response header X-WP-TotalPages 為 "1"
