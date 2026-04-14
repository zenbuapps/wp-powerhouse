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

  # ---------------------------------------------------------------------------
  # copy_children_post 遞迴複製子文章
  # ---------------------------------------------------------------------------

  Rule: 系統行為 - copy_children_post 於 powerhouse_after_copy_post action 被呼叫

    Example: constructor 中註冊 hook
      When Copy::instance() 被建構
      Then add_action("powerhouse_after_copy_post", [Copy, "copy_children_post"], 10, 5) 被呼叫

  Rule: 系統行為 - override_post_parent 為 falsy 時不複製子文章

    Example: override_post_parent 為 false 時跳過
      Given 文章 #101 複製為 #201
      And override_post_parent 為 false
      When copy_children_post 執行
      Then 不取得 #101 的子文章
      And 不進行任何遞迴複製

    Example: override_post_parent 為 0 時跳過
      Given override_post_parent 為 0
      When copy_children_post 執行
      Then 直接 return，不做遞迴

  Rule: 系統行為 - override_post_parent 為正整數（new_id）時遞迴複製子文章

    Example: 複製所有子文章到新父文章下
      Given 文章 #101 複製為 #201
      And 文章 #101 有子文章 #102、#103
      And override_post_parent 為 201
      When copy_children_post 執行
      Then 以 post_parent = 101 查詢子文章（預設 post_type = "post", numberposts = -1, fields = "ids"）
      And 對每個子文章 id 呼叫 $copy->process($child_id, true, 201, $depth + 1)
      And 新的子文章 post_parent 為 201
      And 遞迴 depth 遞增

    Example: children_post_args 可被 filter 改寫
      Given 外部註冊 powerhouse/copy/children_post_args filter
      When copy_children_post 執行
      Then 預設 args 被 apply_filters("powerhouse/copy/children_post_args", $args, $post_id, $new_id, $override_post_parent, $depth) 處理
      And filter 回傳值作為 get_children 的參數

  # ---------------------------------------------------------------------------
  # copy_product 商品專用複製
  # ---------------------------------------------------------------------------

  Rule: 系統行為 - copy_product 使用 WC_Admin_Duplicate_Product 複製商品

    Example: 商品不存在時拋出例外
      Given post_id 201 不是有效商品（wc_get_product 回傳 false）
      When copy_product(201) 被呼叫
      Then 拋出 \Exception("product not found #201")

    Example: 有效商品時使用 WC_Admin_Duplicate_Product
      Given 商品 #201 存在
      When copy_product(201, true, false, 0) 被呼叫
      Then 建立 WC_Admin_Duplicate_Product 實例
      And 呼叫 $copy->product_duplicate($product) 取得新商品
      And 回傳新商品的 ID（整數）

    Example: copy_terms 為 true 時複製分類
      Given 商品 #201 有 product_cat 分類
      When copy_product(201, true) 被呼叫
      Then 呼叫 copy_terms(201, $new_product_id)
      And 新商品繼承原商品的所有 taxonomy terms

    Example: copy_terms 為 false 時不複製分類
      Given 商品 #201 有 product_cat 分類
      When copy_product(201, false) 被呼叫
      Then 不呼叫 copy_terms
      And 新商品不包含原商品的 terms（除 WC_Admin_Duplicate_Product 預設行為外）

    Example: 變體、屬性由 WC_Admin_Duplicate_Product 原生處理
      Given 商品 #201 為 variable 類型，含變體與屬性
      When copy_product(201) 被呼叫
      Then 變體、屬性由 WC 原生 product_duplicate 方法處理
      And Powerhouse 不做額外的變體/屬性處理
