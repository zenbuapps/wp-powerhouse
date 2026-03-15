@ignore @command
Feature: 刪除文章

  Background:
    Given Powerhouse 外掛已啟用
    And WordPress 中有 ID=101 和 ID=102 的文章

  Rule: 後置（狀態）- 批次刪除文章（移入回收桶）

    Example: 成功批次刪除文章
      When 管理員發送 DELETE /wp-json/v2/powerhouse/posts，body 為：
        | key | value      |
        | ids | [101, 102] |
      Then 應回傳 200
      And code 為 "delete_success"
      And ID=101 的文章 post_status 為 "trash"
      And ID=102 的文章 post_status 為 "trash"

    Example: 批次刪除中有 id 不存在時拋出例外
      When 管理員發送 DELETE /wp-json/v2/powerhouse/posts，body ids 包含不存在的 ID
      Then 應回傳 500 並包含錯誤訊息 "delete post data failed"

  Rule: 後置（狀態）- 刪除單篇文章（移入回收桶）

    Example: 成功刪除單篇文章
      When 管理員發送 DELETE /wp-json/v2/powerhouse/posts/101
      Then 應回傳 200
      And code 為 "delete_success"
      And data.id 為 "101"
      And ID=101 的文章 post_status 為 "trash"

  Rule: 前置（狀態）- 單篇刪除時 id 必須為數字

    Example: id 非數字時拋出例外
      When 管理員發送 DELETE /wp-json/v2/powerhouse/posts/abc
      Then 應回傳 500 並包含錯誤訊息 "post id format not match"
