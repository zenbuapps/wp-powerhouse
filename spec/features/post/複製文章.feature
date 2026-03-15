@ignore @command
Feature: 複製文章

  Background:
    Given Powerhouse 外掛已啟用
    And WordPress 中有 ID=101 的文章（含子文章）

  Rule: 前置（狀態）- id 必須存在且為數字

    Example: id 不存在或非數字時拋出例外
      When 管理員發送 POST /wp-json/v2/powerhouse/copy/abc
      Then 應回傳 500 並包含錯誤訊息 "id is required"

  Rule: 後置（狀態）- 成功複製文章（含子文章）並回傳新 ID

    Example: 成功複製文章
      When 管理員發送 POST /wp-json/v2/powerhouse/copy/101
      Then 應回傳 200
      And code 為 "post_copy_success"
      And data 為新複製文章的 ID
      And 新文章的結構與原文章相同（含子文章）
