@ignore @query
Feature: 查詢商品屬性列表

  Background:
    Given Powerhouse 外掛已啟用
    And WooCommerce 已啟用
    And 系統中有以下全局商品屬性：
      | attribute_id | attribute_name | attribute_label |
      | 1            | pa_color       | 顏色            |
      | 2            | pa_size        | 尺寸            |

  Rule: 後置（狀態）- 回傳所有全局商品屬性及其 terms

    Example: 成功查詢商品屬性列表
      When 管理員發送 GET /wp-json/v2/powerhouse/product-attributes
      Then 應回傳 200
      And response body 為商品屬性陣列，按 id 升序排列
      And 每個屬性項目包含完整的屬性資訊及其 terms
      And response header X-WP-Total 包含屬性總數
      And response header X-WP-TotalPages 為 "1"
