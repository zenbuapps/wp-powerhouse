@ignore @query
Feature: 查詢詞彙列表

  Background:
    Given Powerhouse 外掛已啟用
    And WordPress 中有 product_cat taxonomy 的詞彙

  Rule: 後置（狀態）- 回傳指定 taxonomy 的詞彙列表（依 order meta 排序）

    Example: 查詢 product_cat 詞彙列表
      When 管理員發送 GET /wp-json/v2/powerhouse/terms/product_cat
      Then 應回傳 200
      And response body 為詞彙陣列
      And response header X-WP-Total 包含頂層詞彙總數
      And response header X-WP-TotalPages 包含總頁數

  Rule: 後置（狀態）- 預設排序依 termmeta order 的數值 ASC，再依 term_id DESC

    Example: 詞彙按 order meta 排序
      When 管理員發送 GET /wp-json/v2/powerhouse/terms/product_cat
      Then 應回傳 200
      And 結果按 order ASC 排序，相同 order 按 term_id DESC

  Rule: 後置（狀態）- 預設只回傳頂層詞彙（parent=0）

    Example: 預設只回傳 parent=0 的詞彙
      When 管理員發送 GET /wp-json/v2/powerhouse/terms/product_cat
      Then 應回傳 200
      And 所有結果的 parent 為 0

  Rule: 後置（狀態）- hide_empty 預設為 false（包含空詞彙）

    Example: 包含沒有文章的詞彙
      When 管理員發送 GET /wp-json/v2/powerhouse/terms/product_cat
      Then 應回傳 200
      And 結果包含 count=0 的詞彙

  # ---------------------------------------------------------------------------
  # Tree 操作：get_flatten_term_ids
  # ---------------------------------------------------------------------------

  Rule: 系統行為 - get_flatten_term_ids 取得所有子孫詞彙 ID

    Example: 詞彙無子孫時回傳空陣列
      Given product_cat 詞彙 ID 500 存在，無子詞彙
      When get_flatten_term_ids(500, "product_cat") 被呼叫
      Then 回傳 []

    Example: 詞彙有多層子孫時打平全部
      Given product_cat 詞彙 ID 500 有子詞彙 501、502
      And 詞彙 501 有子詞彙 503
      When get_flatten_term_ids(500, "product_cat") 被呼叫
      Then 回傳陣列包含 501、502、503（由 get_terms 的 child_of 遞迴展開）

    Example: get_terms 回傳 WP_Error 時回傳空陣列
      Given taxonomy 不存在或 get_terms 回傳 WP_Error
      When get_flatten_term_ids(500, "invalid_tax") 被呼叫
      Then 回傳 []

    Example: 使用 get_terms 的 child_of 與 fields=ids 參數
      When get_flatten_term_ids($term_id, $taxonomy) 被呼叫
      Then 內部呼叫 get_terms(["taxonomy" => $taxonomy, "child_of" => $term_id, "fields" => "ids"])

  # ---------------------------------------------------------------------------
  # Tree 操作：get_top_term_id
  # ---------------------------------------------------------------------------

  Rule: 系統行為 - get_top_term_id 取得最頂層祖先詞彙 ID

    Example: 詞彙無祖先時回傳自己
      Given product_cat 詞彙 ID 500 的 parent 為 0
      When get_top_term_id(500, "product_cat") 被呼叫
      Then 回傳 500

    Example: 詞彙有祖先時回傳最上層
      Given product_cat 詞彙 ID 503 的 parent 為 501
      And 詞彙 501 的 parent 為 500
      And 詞彙 500 的 parent 為 0
      When get_top_term_id(503, "product_cat") 被呼叫
      Then 回傳 500（get_ancestors 回傳 [501, 500]，取最後一個）

    Example: 結果被快取
      Given 詞彙 503 的 top_term_id 為 500
      When get_top_term_id(503, "product_cat") 第一次被呼叫
      Then 結果被寫入 wp_cache_set("top_term_id_503", 500)

    Example: 快取命中時直接回傳
      Given wp_cache_get("top_term_id_503") 回傳 500
      When get_top_term_id(503, "product_cat") 被呼叫
      Then 直接回傳 500
      And 不執行 get_ancestors
