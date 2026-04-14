@ignore @command
Feature: 刪除詞彙

  Background:
    Given Powerhouse 外掛已啟用
    And WordPress 中 product_cat taxonomy 有 ID=501 和 ID=502 的詞彙

  Rule: 後置（狀態）- 批次刪除詞彙

    Example: 成功批次刪除詞彙
      When 管理員發送 DELETE /wp-json/v2/powerhouse/terms/product_cat，body 為：
        | key | value      |
        | ids | [501, 502] |
      Then 應回傳 200
      And code 為 "delete_success"
      And data 為 [501, 502]
      And ID=501 的詞彙已被刪除
      And ID=502 的詞彙已被刪除

  Rule: 後置（狀態）- 刪除單一詞彙

    Example: 成功刪除單一詞彙
      When 管理員發送 DELETE /wp-json/v2/powerhouse/terms/product_cat/501
      Then 應回傳 200
      And code 為 "delete_success"
      And data.id 為 "501"

  Rule: 前置（狀態）- 單筆刪除時 id 必須為數字

    Example: id 非數字時拋出例外
      When 管理員發送 DELETE /wp-json/v2/powerhouse/terms/product_cat/abc
      Then 應回傳 500 並包含錯誤訊息 "term id format not match"
