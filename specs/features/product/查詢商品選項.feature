@ignore @query
Feature: 查詢商品選項

  Background:
    Given Powerhouse 外掛已啟用
    And WooCommerce 已啟用

  Rule: 後置（狀態）- 回傳商品管理所需的選項資料

    Example: 成功查詢商品選項
      When 管理員發送 GET /wp-json/v2/powerhouse/products/options
      Then 應回傳 200
      And response body 包含以下欄位：
        | 欄位名                    | 說明                   |
        | product_cats              | 商品分類列表           |
        | product_tags              | 商品標籤列表           |
        | product_shipping_classes  | 運送分類列表           |
        | top_sales_products        | 前 5 熱銷商品          |
        | max_price                 | 最高價格               |
        | min_price                 | 最低價格               |

  Rule: 後置（狀態）- 子外掛可透過 filter 擴展回傳值

    Example: 子外掛透過 powerhouse/product/get_options filter 新增額外選項
      Given 子外掛已掛載 powerhouse/product/get_options filter
      When 管理員發送 GET /wp-json/v2/powerhouse/products/options
      Then 應回傳 200
      And 回應包含子外掛新增的欄位
