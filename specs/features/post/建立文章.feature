@ignore @command
Feature: 建立文章

  Background:
    Given Powerhouse 外掛已啟用

  Rule: 後置（狀態）- 成功建立文章並回傳新文章 ID

    Example: 建立單篇文章
      When 管理員發送 POST /wp-json/v2/powerhouse/posts，body 為：
        | key        | value   |
        | post_title | 測試文章 |
        | post_type  | post    |
      Then 應回傳 200
      And code 為 "create_success"
      And data 陣列包含新建文章的 ID

  Rule: 後置（狀態）- qty 參數可批次建立多篇文章

    Example: 批次建立 3 篇文章
      When 管理員發送 POST /wp-json/v2/powerhouse/posts，body 包含 qty=3
      Then 應回傳 200
      And data 陣列包含 3 個新建文章 ID

  Rule: 後置（狀態）- 支援 meta_data 寫入 post meta

    Example: 建立文章時附帶 meta 資料
      When 管理員發送 POST /wp-json/v2/powerhouse/posts，body 包含自訂 meta key
      Then 應回傳 200
      And 新文章的 post meta 已包含對應的 meta key/value

  Rule: 後置（狀態）- 支援子外掛透過 powerhouse/post/separator_body_params filter 擴展參數

    Example: 子外掛透過 filter 修改 body_params
      Given 子外掛已掛載 powerhouse/post/separator_body_params filter
      When 管理員發送 POST /wp-json/v2/powerhouse/posts，body 包含子外掛自訂欄位
      Then 應回傳 200
      And 子外掛 filter 已被觸發並套用

  Rule: 後置（狀態）- 支援圖片上傳（images 欄位）

    Example: 建立文章時上傳縮圖
      When 管理員發送 POST /wp-json/v2/powerhouse/posts，body 包含 images 檔案
      Then 應回傳 200
      And 新文章的 _thumbnail_id 已設置

  Rule: 後置（狀態）- images 設為 delete 時清除縮圖

    Example: 建立文章時 images=delete 清除縮圖
      When 管理員發送 POST /wp-json/v2/powerhouse/posts，body 包含 images=delete
      Then 應回傳 200
      And 新文章的 _thumbnail_id 為空
