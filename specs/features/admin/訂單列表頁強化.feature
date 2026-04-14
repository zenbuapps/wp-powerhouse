@ignore @system-behavior
Feature: 訂單列表頁強化

  在 WooCommerce 後台訂單列表頁面新增「訂單商品」欄位，
  顯示每筆訂單內所有 line_item 商品名稱、數量與商品編輯連結。
  同時支援 HPOS 與傳統 shop_order post type 兩種模式。

  Background:
    Given WooCommerce 已啟用
    And Powerhouse 外掛已啟用
    And Admin\OrderList::instance() 已於 Bootstrap 建構時初始化

  # ---------------------------------------------------------------------------
  # Hook 註冊（依 HPOS 狀態）
  # ---------------------------------------------------------------------------

  Rule: 系統行為 - 依 HPOS 是否啟用掛載不同的 hook

    Example: HPOS 啟用時掛載 wc-orders hook
      Given WC::is_hpos_enabled() 回傳 true
      When Admin\OrderList::__construct 執行
      Then 註冊以下 hook：
        | hook                                             | callback                   | priority |
        | manage_woocommerce_page_wc-orders_columns        | add_order_column           | 20       |
        | manage_woocommerce_page_wc-orders_custom_column  | render_order_column_hpos   | 20       |

    Example: HPOS 停用時掛載傳統 shop_order hook
      Given WC::is_hpos_enabled() 回傳 false
      When Admin\OrderList::__construct 執行
      Then 註冊以下 hook：
        | hook                                   | callback                         | priority |
        | manage_edit-shop_order_columns         | add_order_column                 | 20       |
        | manage_shop_order_posts_custom_column  | render_order_column_shop_order   | 20       |

  # ---------------------------------------------------------------------------
  # 新增欄位
  # ---------------------------------------------------------------------------

  Rule: 系統行為 - 在訂單列表新增「訂單商品」欄位

    Example: 欄位以 PRODUCT_COLUMN_NAME 為 key
      When add_order_column($columns) 執行
      Then 回傳的 $columns 陣列包含新欄位：
        | key                                              | label    |
        | elittleworld_extension_order_products            | 訂單商品 |

  # ---------------------------------------------------------------------------
  # 欄位內容渲染
  # ---------------------------------------------------------------------------

  Rule: 系統行為 - 渲染訂單內每個 line_item 的商品名稱與數量

    Example: HPOS 模式下從 $order 物件取得 ID
      Given 當前渲染的 column 為 PRODUCT_COLUMN_NAME
      And $order 物件具 get_id() 方法
      When render_order_column_hpos($column, $order) 執行
      Then 呼叫 render_order_column($column, (int) $order->get_id())

    Example: 傳統模式下從全域 $post 取得 ID
      Given 當前渲染的 column 為 PRODUCT_COLUMN_NAME
      When render_order_column_shop_order($column) 執行
      Then 使用全域 $post->ID 作為 order_id 呼叫 render_order_column

    Example: 欄位非 PRODUCT_COLUMN_NAME 時不做任何輸出
      Given $column !== 'elittleworld_extension_order_products'
      When render_order_column 執行
      Then 不輸出任何內容

    Example: 訂單不存在時不輸出內容
      Given wc_get_order($order_id) 回傳非 WC_Order
      When render_order_column 執行
      Then 不輸出任何內容

    Example: 輸出每個 line_item 的商品名稱連結與數量
      Given $column === 'elittleworld_extension_order_products'
      And 訂單存在且包含數個 line_item
      When render_order_column 執行
      Then 對於每個 type 為 'line_item' 的項目：
        | 動作                                                                 |
        | 從 item->get_product() 取得 WC_Product                              |
        | 輸出 <a href="{get_edit_post_link(product_id)}">{product_name}</a>   |
        | 在名稱後輸出 " x {quantity}<br />"                                   |
      And 非 line_item 類型的項目被跳過
      And 商品不存在（非 WC_Product）時該項目被跳過
