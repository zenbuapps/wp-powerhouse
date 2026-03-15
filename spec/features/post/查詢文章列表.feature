@ignore @query
Feature: 查詢文章列表

  Background:
    Given Powerhouse 外掛已啟用
    And WordPress 中有以下文章：
      | ID  | post_type | post_status | post_parent | menu_order |
      | 101 | post      | publish     | 0           | 0          |
      | 102 | post      | publish     | 0           | 1          |
      | 103 | post      | draft       | 0           | 0          |

  Rule: 後置（狀態）- 回傳分頁結果並在 header 設置分頁資訊

    Example: 以預設參數查詢文章列表
      When 管理員發送 GET /wp-json/v2/powerhouse/posts
      Then 應回傳 200
      And response body 為文章陣列
      And response header X-WP-Total 包含文章總數
      And response header X-WP-TotalPages 包含總頁數
      And response header X-WP-CurrentPage 為 "1"
      And response header X-WP-PageSize 為 "20"

  Rule: 後置（狀態）- 支援 post_type 篩選

    Example: 傳入 post_type=page 查詢頁面列表
      When 管理員發送 GET /wp-json/v2/powerhouse/posts?post_type=page
      Then 應回傳 200
      And response body 中所有項目的 post_type 為 "page"

  Rule: 後置（狀態）- 預設排序為 menu_order ASC、ID DESC、date DESC

    Example: 未傳排序參數時使用預設排序
      When 管理員發送 GET /wp-json/v2/powerhouse/posts
      Then 應回傳 200
      And 結果按 menu_order ASC 排序，相同 menu_order 按 ID DESC

  Rule: 後置（狀態）- post_parent=0 為預設，傳入 "unset" 則不限父文章

    Example: 傳入 post_parent=unset 查詢所有層級文章
      When 管理員發送 GET /wp-json/v2/powerhouse/posts?post_parent=unset
      Then 應回傳 200
      And 結果包含所有層級的文章（不限 post_parent）

  Rule: 後置（狀態）- post_type=attachment 時回傳 Attachment 格式

    Example: 查詢附件列表
      When 管理員發送 GET /wp-json/v2/powerhouse/posts?post_type=attachment
      Then 應回傳 200
      And response body 中每個項目包含附件專有欄位（如 url、mime_type）

  Rule: 後置（狀態）- 支援 depth 參數取得子文章

    Example: 傳入 depth=2 取得含子文章的巢狀結構
      When 管理員發送 GET /wp-json/v2/powerhouse/posts?depth=2
      Then 應回傳 200
      And 每個文章項目包含 children 陣列（最多 2 層）
