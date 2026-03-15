@ignore @command
Feature: 建立詞彙

  Background:
    Given Powerhouse 外掛已啟用

  Rule: 後置（狀態）- 批次建立詞彙並回傳新 term ID

    Example: 建立單個詞彙
      When 管理員發送 POST /wp-json/v2/powerhouse/terms/product_cat，body 為：
        | key  | value  |
        | name | 新分類 |
      Then 應回傳 200
      And code 為 "create_success"
      And data 陣列包含新詞彙的 ID

  Rule: 後置（狀態）- qty 參數可批次建立多個詞彙

    Example: 批次建立 3 個詞彙
      When 管理員發送 POST /wp-json/v2/powerhouse/terms/product_cat，body 包含 qty=3
      Then 應回傳 200
      And data 陣列包含 3 個新詞彙 ID

  Rule: 後置（狀態）- 支援上傳 thumbnail_id 圖片

    Example: 建立詞彙時附帶縮圖
      When 管理員發送 POST /wp-json/v2/powerhouse/terms/product_cat，body 包含 thumbnail_id 圖片檔案
      Then 應回傳 200
      And 新詞彙的 thumbnail_id meta 已設置

  Rule: 後置（狀態）- thumbnail_id=delete 清除縮圖

    Example: 建立詞彙時 thumbnail_id=delete 清除縮圖
      When 管理員發送 POST /wp-json/v2/powerhouse/terms/product_cat，body thumbnail_id=delete
      Then 應回傳 200
      And 詞彙的 thumbnail_id meta 為空

  Rule: 後置（狀態）- 支援子外掛透過 powerhouse/term/create_term_args filter 擴展參數

    Example: 子外掛透過 filter 修改建立參數
      Given 子外掛已掛載 powerhouse/term/create_term_args filter
      When 管理員發送 POST /wp-json/v2/powerhouse/terms/product_cat，body 包含子外掛自訂欄位
      Then 應回傳 200
      And 子外掛 filter 已被觸發
