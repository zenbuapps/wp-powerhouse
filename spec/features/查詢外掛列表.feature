@ignore
Feature: 查詢外掛列表

  Background:
    Given 系統中有以下用戶：
      | userId | name   | email              | role          |
      | 1      | Admin  | admin@example.com  | administrator |

  # ========== 前置（參數）==========
  Rule: 前置（參數）- 無特殊參數要求
    Example: 直接查詢即可
      When Admin 發送 GET /wp-json/v2/powerhouse/plugins
      Then 應回傳 200

  # ========== 後置（回應）==========
  Rule: 後置（回應）- 回傳所有已安裝外掛的資訊
    Example: 結果包含外掛 key 和啟用狀態
      When Admin 發送 GET /wp-json/v2/powerhouse/plugins
      Then 結果應為陣列
      And 每項應包含：
        | 欄位      | 說明              |
        | key       | 外掛路徑          |
        | is_active | 是否啟用          |
      And Response Header X-WP-Total 應大於 0
