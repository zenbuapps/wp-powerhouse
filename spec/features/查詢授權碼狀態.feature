@ignore
Feature: 查詢授權碼狀態

  Background:
    Given 系統中有以下用戶：
      | userId | name   | email              | role          |
      | 1      | Admin  | admin@example.com  | administrator |
    And 系統中已註冊以下產品資訊（透過 powerhouse_product_infos filter）：
      | product_slug | product_name |
      | power-course | Power Course |
      | power-shop   | Power Shop   |

  # ========== 前置（參數）==========
  Rule: 前置（參數）- 無特殊參數要求
    Example: 直接查詢即可
      When Admin 發送 GET /wp-json/v2/powerhouse/lc
      Then 應回傳 200

  # ========== 後置（回應）==========
  Rule: 後置（回應）- 回傳所有已註冊產品的授權狀態
    Example: 有已啟用的授權碼
      Given power-course 有 transient 且狀態為 "activated"
      When Admin 發送 GET /wp-json/v2/powerhouse/lc
      Then 結果應為陣列，每項包含：
        | 欄位         | 說明             |
        | code         | 授權碼           |
        | post_status  | 授權狀態         |
        | expire_date  | 到期日           |
        | type         | 授權類型         |
        | product_slug | 產品 slug        |
        | product_name | 產品名稱         |

    Example: 未啟用的產品應回傳空狀態
      Given power-shop 沒有 transient 也沒有 saved_code
      When Admin 發送 GET /wp-json/v2/powerhouse/lc
      Then 結果中 power-shop 的 code 應為空字串
      And 結果中 power-shop 的 post_status 應為空字串
