@ignore @query
Feature: 查詢單一詞彙

  Background:
    Given Powerhouse 外掛已啟用
    And WordPress 中 product_cat taxonomy 有 ID=501 的詞彙

  Rule: 前置（狀態）- id 必須為數字

    Example: id 非數字時拋出例外
      When 管理員發送 GET /wp-json/v2/powerhouse/terms/product_cat/abc
      Then 應回傳 500 並包含錯誤訊息 "term id format not match"

  Rule: 前置（狀態）- 詞彙必須存在

    Example: 詞彙不存在時拋出例外
      When 管理員發送 GET /wp-json/v2/powerhouse/terms/product_cat/99999
      Then 應回傳 500 並包含錯誤訊息 "term not found"

  Rule: 後置（狀態）- 回傳詞彙詳細資料

    Example: 成功查詢單一詞彙
      When 管理員發送 GET /wp-json/v2/powerhouse/terms/product_cat/501
      Then 應回傳 200
      And response body 包含詞彙欄位（term_id、name、slug、taxonomy 等）
