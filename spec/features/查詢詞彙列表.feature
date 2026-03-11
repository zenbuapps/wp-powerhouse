@ignore
Feature: 查詢詞彙列表

  Background:
    Given 系統中有以下用戶：
      | userId | name   | email              | role          |
      | 1      | Admin  | admin@example.com  | administrator |
    And 系統中有以下詞彙：
      | term_id | name     | taxonomy    | parent |
      | 10      | 分類 A   | product_cat | 0      |
      | 11      | 子分類 B | product_cat | 10     |
      | 12      | 標籤 A   | product_tag | 0      |

  # ========== 前置（參數）==========
  Rule: 前置（參數）- taxonomy 為必填路徑參數
    Example: 查詢 product_cat 分類
      When Admin 發送 GET /wp-json/v2/powerhouse/terms/product_cat
      Then 應回傳 200
      And 結果應為 product_cat 分類的詞彙

  Rule: 前置（參數）- 預設值 posts_per_page=20, parent=0
    Example: 預設只查詢頂層詞彙
      When Admin 發送 GET /wp-json/v2/powerhouse/terms/product_cat
      Then 結果應只包含 parent=0 的頂層詞彙

  # ========== 後置（回應）==========
  Rule: 後置（回應）- 回應包含分頁 headers
    Example: 查詢結果包含分頁資訊
      When Admin 發送 GET /wp-json/v2/powerhouse/terms/product_cat
      Then Response Header X-WP-Total 應大於 0
      And Response Header X-WP-TotalPages 應大於 0

  Rule: 後置（回應）- 按 order meta 排序
    Example: 詞彙應按 order meta 值升序排列
      Given 詞彙 10 的 order meta 為 "2"
      Given 詞彙 13 的 order meta 為 "1"
      When Admin 發送 GET /wp-json/v2/powerhouse/terms/product_cat
      Then 詞彙 13 應排在詞彙 10 之前
