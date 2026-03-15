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
