@ignore @command
Feature: 排序詞彙

  Background:
    Given Powerhouse 外掛已啟用
    And WordPress 中有多個 product_cat 詞彙

  Rule: 後置（狀態）- 成功更新詞彙排序（termmeta order）

    Example: 成功排序詞彙
      When 管理員發送 POST /wp-json/v2/powerhouse/terms/product_cat/sort，body 為：
        | key       | value                          |
        | from_tree | [{"id": "501"}, {"id": "502"}] |
        | to_tree   | [{"id": "502"}, {"id": "501"}] |
      Then 應回傳 200
      And code 為 "sort_success"
      And 詞彙的 termmeta order 已按 to_tree 順序更新
