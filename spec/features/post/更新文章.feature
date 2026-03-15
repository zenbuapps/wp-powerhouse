@ignore @command
Feature: 更新文章

  Background:
    Given Powerhouse 外掛已啟用
    And WordPress 中有 ID=101 的文章

  Rule: 前置（狀態）- post id 必須為數字

    Example: id 非數字時拋出例外
      When 管理員發送 POST /wp-json/v2/powerhouse/posts/abc，body 包含更新資料
      Then 應回傳 500 並包含錯誤訊息 "post id format not match"

  Rule: 後置（狀態）- 成功更新文章並回傳 ID

    Example: 成功更新文章標題
      When 管理員發送 POST /wp-json/v2/powerhouse/posts/101，body 為：
        | key        | value     |
        | post_title | 更新後標題 |
      Then 應回傳 200
      And code 為 "update_success"
      And data.id 為 "101"
      And WordPress 中 ID=101 的文章 post_title 已更新為 "更新後標題"

  Rule: 後置（狀態）- 支援 meta_data 更新 post meta

    Example: 更新文章時附帶 meta 資料
      When 管理員發送 POST /wp-json/v2/powerhouse/posts/101，body 包含自訂 meta key
      Then 應回傳 200
      And 文章的 post meta 已更新

  Rule: 後置（狀態）- post_content 和 post_excerpt 跳過 sanitize

    Example: post_content 包含 HTML 不被 sanitize
      When 管理員發送 POST /wp-json/v2/powerhouse/posts/101，body 包含含 HTML 的 post_content
      Then 應回傳 200
      And 文章的 post_content 包含原始 HTML（未被 sanitize）
