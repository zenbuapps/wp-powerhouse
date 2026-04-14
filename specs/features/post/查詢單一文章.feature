@ignore @query
Feature: 查詢單一文章

  Background:
    Given Powerhouse 外掛已啟用
    And WordPress 中有 ID=101 的文章

  Rule: 前置（狀態）- post id 必須為數字

    Example: id 非數字時拋出例外
      When 管理員發送 GET /wp-json/v2/powerhouse/posts/abc
      Then 應回傳 500 並包含錯誤訊息 "post id format not match"

  Rule: 前置（狀態）- 文章必須存在

    Example: id 不存在時拋出例外
      When 管理員發送 GET /wp-json/v2/powerhouse/posts/99999
      Then 應回傳 500 並包含錯誤訊息 "post not found"

  Rule: 後置（狀態）- 回傳單一文章詳細資料

    Example: 成功查詢單一文章
      When 管理員發送 GET /wp-json/v2/powerhouse/posts/101
      Then 應回傳 200
      And response body 包含文章欄位（ID、post_title、post_content、post_status 等）

  Rule: 後置（狀態）- 支援 meta_keys 參數指定回傳的 meta 欄位

    Example: 傳入 meta_keys 取得指定 meta 欄位
      When 管理員發送 GET /wp-json/v2/powerhouse/posts/101?meta_keys[]=_thumbnail_id
      Then 應回傳 200
      And response body 包含 _thumbnail_id 欄位

  Rule: 後置（狀態）- 查詢單一欄位值

    Example: 成功查詢文章的指定欄位
      When 管理員發送 GET /wp-json/v2/powerhouse/posts/101/field/post_title
      Then 應回傳 200
      And code 為 "get_field_success"
      And data 為文章的 post_title 值

    Example: 查詢 meta 欄位值
      When 管理員發送 GET /wp-json/v2/powerhouse/posts/101/field/_my_meta_key
      Then 應回傳 200
      And data 為對應的 post meta 值
