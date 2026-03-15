@ignore @query
Feature: 查詢商品列表

  Background:
    Given Powerhouse 外掛已啟用
    And WooCommerce 已啟用
    And WooCommerce 中有以下商品：
      | ID  | name   | status  |
      | 201 | 課程A  | publish |
      | 202 | 課程B  | draft   |

  Rule: 後置（狀態）- 回傳分頁商品列表（預設含 publish/draft/pending）

    Example: 以預設參數查詢商品列表
      When 管理員發送 GET /wp-json/v2/powerhouse/products
      Then 應回傳 200
      And response body 為商品陣列（包含 publish 和 draft 商品）
      And response header X-WP-Total 包含商品總數
      And response header X-WP-TotalPages 包含總頁數
      And response header X-WP-CurrentPage 為 "1"
      And response header X-WP-PageSize 為 "20"

  Rule: 後置（狀態）- 支援 status 篩選

    Example: 篩選 publish 狀態商品
      When 管理員發送 GET /wp-json/v2/powerhouse/products?status=publish
      Then 應回傳 200
      And response body 中所有項目的 status 為 "publish"

  Rule: 後置（狀態）- 支援 partials 參數只回傳指定欄位

    Example: 傳入 partials 只取部分欄位
      When 管理員發送 GET /wp-json/v2/powerhouse/products?partials[]=id&partials[]=name
      Then 應回傳 200
      And 每個商品項目只包含 id 和 name 欄位

  Rule: 後置（狀態）- 支援 meta_keys 參數回傳指定 meta 欄位

    Example: 傳入 meta_keys 取得商品 meta 資料
      When 管理員發送 GET /wp-json/v2/powerhouse/products?meta_keys[]=_custom_meta
      Then 應回傳 200
      And 每個商品包含 _custom_meta 欄位值
