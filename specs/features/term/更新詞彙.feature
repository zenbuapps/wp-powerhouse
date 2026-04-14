@ignore @command
Feature: 更新詞彙

  Background:
    Given Powerhouse 外掛已啟用
    And WordPress 中 product_cat taxonomy 有 ID=501 的詞彙

  Rule: 前置（狀態）- id 必須為數字

    Example: id 非數字時拋出例外
      When 管理員發送 POST /wp-json/v2/powerhouse/terms/product_cat/abc
      Then 應回傳 500 並包含錯誤訊息 "term id format not match"

  Rule: 後置（狀態）- 成功更新詞彙

    Example: 成功更新詞彙名稱
      When 管理員發送 POST /wp-json/v2/powerhouse/terms/product_cat/501，body 為：
        | key  | value    |
        | name | 更新分類 |
      Then 應回傳 200
      And code 為 "update_success"
      And data.id 為 "501"

  Rule: 後置（狀態）- 支援子外掛透過 powerhouse/term/update_term_args filter 擴展參數

    Example: 子外掛透過 filter 修改更新參數
      Given 子外掛已掛載 powerhouse/term/update_term_args filter
      When 管理員發送 POST /wp-json/v2/powerhouse/terms/product_cat/501
      Then 應回傳 200
      And 子外掛 filter 已被觸發
