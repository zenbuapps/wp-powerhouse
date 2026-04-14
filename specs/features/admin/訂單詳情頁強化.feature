@ignore @system-behavior
Feature: 訂單詳情頁強化

  WooCommerce 後台訂單詳情頁面的強化，將預設訂單備註（Order Notes）
  meta box 替換為使用 TinyMCE 經典編輯器的自訂版本，支援 HTML 格式的備註內容。

  【目前狀態：停用】
  Bootstrap.php 中 Admin\OrderDetail::instance() 已被註解停用，
  因為存在已知 BUG：編輯器無法正常顯示，似乎只有 HPOS 模式可以正常使用。
  本 feature 僅記錄原始設計意圖與預期行為，不代表當前實際運行狀態。

  Background:
    Given WooCommerce 已啟用
    And Admin\OrderDetail::instance() 曾被設計為於 Bootstrap 建構時初始化（目前已停用）

  # ---------------------------------------------------------------------------
  # Meta Box 替換
  # ---------------------------------------------------------------------------

  Rule: 系統行為 - 移除預設訂單備註 meta box 並加入自訂版本

    Example: 非訂單頁面跳過處理
      Given get_current_screen()->id !== 'shop_order'
      When admin_head hook 觸發
      Then remove_origin_order_note 不執行 remove_meta_box
      And add_custom_order_note 不執行 add_meta_box

    Example: 於 shop_order 頁面移除原生訂單備註
      Given get_current_screen()->id === 'shop_order'
      When admin_head hook 觸發（priority 100）
      Then remove_meta_box('woocommerce-order-notes', 'woocommerce_page_wc-orders', 'side') 被呼叫

    Example: 於 shop_order 頁面加入自訂訂單備註（HPOS 啟用時）
      Given get_current_screen()->id === 'shop_order'
      And WC::is_hpos_enabled() 回傳 true
      When admin_head hook 觸發（priority 110）
      Then add_meta_box 以 'woocommerce_page_wc-orders' 為 screen、advanced 為 context 註冊自訂 meta box

    Example: 於 shop_order 頁面加入自訂訂單備註（HPOS 停用時）
      Given get_current_screen()->id === 'shop_order'
      And WC::is_hpos_enabled() 回傳 false
      When admin_head hook 觸發
      Then add_meta_box 以 'shop_order' 為 screen、advanced 為 context 註冊自訂 meta box
      # BUG：此路徑下編輯器無法正常顯示

  # ---------------------------------------------------------------------------
  # Meta Box 輸出
  # ---------------------------------------------------------------------------

  Rule: 系統行為 - 輸出含 TinyMCE 編輯器的訂單備註區塊

    Example: 輸出現有訂單備註清單
      Given 當前訂單有 order_id
      When OrderDetail::output($post) 執行
      Then 透過 wc_get_order_notes(['order_id' => $order_id]) 取得備註
      And 引入 WooCommerce 內建的 html-order-notes.php 樣板渲染備註清單

    Example: 輸出 TinyMCE 經典編輯器
      When OrderDetail::output($post) 執行
      Then 輸出「編輯器載入中...」的 loading 區塊
      And 呼叫 wp_editor('', 'add_order_note_classic_editor', ['textarea_rows' => 10])
      And 輸出隱藏的 textarea#add_order_note 同步儲存編輯器內容
      And 輸出「Note type」select（選項：Private note / Note to customer）
      And 輸出「Add」按鈕

    Example: TinyMCE 初始化後同步內容到 textarea
      When 前端 jQuery 腳本執行
      Then tinyMCE.init 對 #add_order_note_classic_editor 進行初始化
      And tinymce-editor-init 事件觸發時隱藏 loading、顯示編輯器
      And 編輯器 change 事件同步 editor.getContent() 到 #add_order_note textarea
