@ignore
Feature: 綁定權限項目到商品

  Background:
    Given 系統中有以下用戶：
      | userId | name   | email              | role          |
      | 1      | Admin  | admin@example.com  | administrator |
    And 系統中有以下商品：
      | product_id | name     |
      | 50         | 商品 A   |
    And 系統中有以下內容項目：
      | post_id | post_title |
      | 100     | 課程 A     |
      | 101     | 課程 B     |

  # ========== 前置（參數）==========
  Rule: 前置（參數）- product_ids、item_ids、limit_type、meta_key 為必填
    Example: 缺少 meta_key 時應回傳錯誤
      When Admin 發送 POST /wp-json/v2/powerhouse/products/bind-items：
        | product_ids | [50]       |
        | item_ids    | [100]      |
        | limit_type  | unlimited  |
      Then 應回傳 400 錯誤

  # ========== 後置（狀態）==========
  Rule: 後置（狀態）- 綁定成功後 post_meta 應有對應資料
    Example: 成功綁定項目到商品（無期限）
      When Admin 發送 POST /wp-json/v2/powerhouse/products/bind-items：
        | product_ids | [50]             |
        | item_ids    | [100, 101]       |
        | limit_type  | unlimited        |
        | meta_key    | bound_items_data |
      Then 應回傳 200 且 code 為 "success"
      And 商品 50 的 post_meta "bound_items_data" 應包含項目 100 和 101
      And 商品 50 的 post_meta "bound_items_data_ids" 應包含 100 和 101

    Example: 成功綁定項目到商品（固定期限）
      When Admin 發送 POST /wp-json/v2/powerhouse/products/bind-items：
        | product_ids | [50]             |
        | item_ids    | [100]            |
        | limit_type  | fixed            |
        | limit_value | 30               |
        | limit_unit  | day              |
        | meta_key    | bound_items_data |
      Then 應回傳 200
      And 商品 50 的 bound_items_data 中項目 100 的 limit_type 應為 "fixed"
      And 商品 50 的 bound_items_data 中項目 100 的 limit_value 應為 30
      And 商品 50 的 bound_items_data 中項目 100 的 limit_unit 應為 "day"
