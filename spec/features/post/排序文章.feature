@ignore @command
Feature: 排序文章

  Background:
    Given Powerhouse 外掛已啟用
    And WordPress 中有多篇文章

  Rule: 後置（狀態）- 成功更新文章排序（menu_order）

    Example: 成功排序文章
      When 管理員發送 POST /wp-json/v2/powerhouse/posts/sort，body 為：
        | key       | value                          |
        | from_tree | [{"id": "101"}, {"id": "102"}] |
        | to_tree   | [{"id": "102"}, {"id": "101"}] |
      Then 應回傳 200
      And code 為 "sort_success"
      And WordPress 中文章的 menu_order 已按 to_tree 順序更新
