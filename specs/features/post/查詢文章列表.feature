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

  # ---------------------------------------------------------------------------
  # Tree 操作：get_flatten_post_ids
  # ---------------------------------------------------------------------------

  Rule: 系統行為 - get_flatten_post_ids 遞迴取得文章的所有子孫 ID（不包含自己）

    Example: 單一文章無子文章時回傳空陣列
      Given 文章 ID 100 存在
      And 沒有任何文章的 post_parent 為 100
      When get_flatten_post_ids(100) 被呼叫
      Then 回傳 []

    Example: 文章有多層子文章時打平全部子孫
      Given 文章 ID 100 有子文章 101, 102
      And 文章 101 有子文章 103
      When get_flatten_post_ids(100) 被呼叫
      Then 回傳陣列包含 101, 102, 103
      And 回傳陣列不包含 100（不包含頂層 ID）

    Example: 文章不存在時回傳空陣列
      Given 文章 ID 999 不存在（get_post 回傳 null）
      When get_flatten_post_ids(999) 被呼叫
      Then 回傳 []

    Example: format_post_details 回傳的 children 非陣列時回傳空陣列
      Given 文章 ID 100 存在但沒有任何 children
      When get_flatten_post_ids(100) 被呼叫
      Then 回傳 []

    Example: 支援自訂 recursive_args
      Given 文章 ID 100 有多個子文章（不同 post_type）
      When get_flatten_post_ids(100, ["post_type" => "page"]) 被呼叫
      Then 只回傳 post_type 為 "page" 的子孫 ID

  # ---------------------------------------------------------------------------
  # Tree 操作：get_top_post_id
  # ---------------------------------------------------------------------------

  Rule: 系統行為 - get_top_post_id 取得最頂層祖先文章 ID

    Example: 文章無祖先時回傳自己
      Given 文章 ID 100 存在，post_parent 為 0
      When get_top_post_id(100) 被呼叫
      Then 回傳 100

    Example: 文章有祖先時回傳最上層祖先
      Given 文章 ID 103 的 post_parent 為 101
      And 文章 101 的 post_parent 為 100
      And 文章 100 的 post_parent 為 0
      When get_top_post_id(103) 被呼叫
      Then 回傳 100（get_post_ancestors 回傳 [101, 100]，取最後一個）

    Example: 結果被快取避免重複查詢
      Given 文章 ID 103 的 top_post_id 為 100
      When get_top_post_id(103) 第一次被呼叫
      Then 結果被寫入 wp_cache_set("top_post_id_103", 100)

    Example: 快取命中時直接回傳
      Given wp_cache_get("top_post_id_103") 回傳 100
      When get_top_post_id(103) 被呼叫
      Then 直接回傳 100
      And 不執行 get_post_ancestors
