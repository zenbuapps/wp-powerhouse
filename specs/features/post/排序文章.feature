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

  # ---------------------------------------------------------------------------
  # 核心排序邏輯（sort_posts）
  # ---------------------------------------------------------------------------

  Rule: 系統行為 - sort_posts 以 transaction + 批次 CASE WHEN 更新 menu_order 和 post_parent

    Example: 以 50 筆為單位分批更新
      Given to_tree 包含 120 筆資料
      When sort_posts($params) 被呼叫
      Then 資料被 array_chunk 分為 3 批（50、50、20）
      And 每批執行一次 UPDATE {$wpdb->posts} SET menu_order = CASE ... post_parent = CASE ... WHERE ID IN (...)

    Example: 同時更新 menu_order 與 post_parent
      Given to_tree 為 [{"id": "101", "menu_order": 2, "parent_id": 0}, {"id": "102", "menu_order": 1, "parent_id": 101}]
      When sort_posts($params) 被呼叫
      Then SQL 包含 "WHEN ID = 101 THEN 2" 與 "WHEN ID = 102 THEN 1" 的 menu_order CASE
      And SQL 包含 "WHEN ID = 101 THEN 0" 與 "WHEN ID = 102 THEN 101" 的 post_parent CASE
      And 文章 101 的 menu_order 更新為 2、post_parent 更新為 0
      And 文章 102 的 menu_order 更新為 1、post_parent 更新為 101

    Example: 包在 transaction 中，失敗會 ROLLBACK
      Given 執行批次 UPDATE 時 $wpdb->query 回傳 false
      When sort_posts 執行到該批次
      Then 執行 "ROLLBACK"
      And 拋出 \Exception("排序失敗: ...")

    Example: 成功後清除 posts 與 post_meta cache group
      Given 所有批次 UPDATE 都成功
      When sort_posts 結束前
      Then 執行 "COMMIT"
      And 呼叫 wp_cache_flush_group("posts")
      And 呼叫 wp_cache_flush_group("post_meta")

    Example: from_tree 中存在、to_tree 中不存在的節點會被丟到垃圾桶
      Given from_tree 為 [{"id": "101"}, {"id": "102"}, {"id": "103"}]
      And to_tree 為 [{"id": "101"}, {"id": "102"}]
      When sort_posts 執行結束
      Then 文章 103 被 wp_trash_post 移至垃圾桶
      And 文章 101、102 不被 trash

    Example: menu_order 預設為 0
      Given to_tree 中某個節點沒有 menu_order 欄位
      When sort_posts 執行
      Then 該節點的 menu_order 被當作 0 處理

    Example: parent_id 預設為 0
      Given to_tree 中某個節點沒有 parent_id 欄位
      When sort_posts 執行
      Then 該節點的 post_parent 被當作 0 處理
