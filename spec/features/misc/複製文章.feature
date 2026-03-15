@ignore @command
Feature: 複製文章

  Background:
    Given Powerhouse 外掛已啟用

  Rule: 前置（狀態）- id 為必填且必須為數字

    Example: id 不存在或非數字時拋出例外
      When 管理員發送 POST /wp-json/v2/powerhouse/copy/abc
      Then 操作失敗，錯誤為「id is required」

  Rule: 後置（狀態）- 一般文章複製 post meta 和 terms

    Example: 複製一般文章
      Given 系統中有文章 #101（post_type=post）含 meta 和 terms
      When 管理員發送 POST /wp-json/v2/powerhouse/copy/101
      Then 應回傳 200
      And code 為 "post_copy_success"
      And data 為新複製文章的 ID
      And 新文章包含原文章的 post meta
      And 新文章包含原文章的 terms

  Rule: 後置（狀態）- 商品（product）使用 WC_Admin_Duplicate_Product 複製

    Example: 複製 WooCommerce 商品
      Given 系統中有商品 #201（post_type=product）
      When 管理員發送 POST /wp-json/v2/powerhouse/copy/201
      Then 應回傳 200
      And code 為 "post_copy_success"
      And data 為新複製商品的 ID
      And 使用 WC_Admin_Duplicate_Product 進行複製

  Rule: 後置（狀態）- 遞迴複製子文章

    Example: 複製含子文章的文章
      Given 系統中有文章 #101 且其下有子文章 #102、#103
      When 管理員發送 POST /wp-json/v2/powerhouse/copy/101
      Then 應回傳 200
      And 新文章下也包含已複製的子文章
      And 觸發 powerhouse_after_copy_post action
