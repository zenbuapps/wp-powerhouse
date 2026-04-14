@ignore @model
Feature: WooCommerce 選項模型

  描述 Domains/Woocommerce/ 提供的全局選項資料 Models。
  這些 Models 將 WooCommerce 原生資料格式包裝為前端 Ant Design 元件需要的格式
  （value / label / color），供下拉選單、標籤（Tag）等元件直接使用。
  AntdOption 是其他 5 個 Model 所共用的選項單元 DTO 基底概念。

  Background:
    Given Powerhouse 外掛已啟用
    And WooCommerce 已啟用

  Rule: AntdOption - 通用 Ant Design 選項格式 DTO

    AntdOption 是一個通用的選項 DTO，實際類別位於
    J7\Powerhouse\Domains\Product\Model\AntdOption（非 Woocommerce namespace），
    但被其他 WooCommerce 選項 Models（OrderStatuses / PostStatuses /
    ProductStockStatuses / ProductTypes）作為單一選項的資料結構概念使用。
    每個選項由 value / label / color 三個字串欄位組成。

    Example: AntdOption 的欄位定義
      Given AntdOption 繼承自 J7\WpUtils\Classes\DTO
      Then 類別包含 public string $value 屬性
      And 包含 public string $label 屬性
      And 包含 public string $color 屬性

    Example: 單一選項的序列化形態
      Given value 為 "publish"
      And label 為 "已發佈"
      And color 為 "blue"
      Then 對應的選項陣列為 {"value": "publish", "label": "已發佈", "color": "blue"}

    Example: 其他 Models 以 AntdOption 格式作為元素單位
      Given OrderStatuses / PostStatuses / ProductStockStatuses / ProductTypes 的資料陣列
      Then 每個元素的結構皆為 {value, label, color}
      And 符合 AntdOption 的欄位定義

  Rule: Countries - 國家與台灣縣市列表

    Countries 類別透過 SingletonTrait 實例化，並在建構時註冊
    woocommerce_states filter，將台灣（TW）的縣市列表擴充到
    WooCommerce 原生的國家/州省資料中。

    Example: 類別使用 Singleton 模式
      Given Countries 類別使用 J7\WpUtils\Traits\SingletonTrait
      When 建構子被呼叫
      Then 會透過 add_filter 註冊 woocommerce_states filter
      And callback 為 [Countries::class, 'extend_tw_states']

    Example: 擴充台灣縣市到 states 陣列
      Given woocommerce_states filter 被觸發
      When extend_tw_states($states) 被呼叫
      Then $states['TW'] 會被設為 Countries::$states
      And 回傳擴充後的 $states 陣列

    Example: 台灣縣市清單涵蓋 22 個縣市
      Given Countries::$states 為 public static 陣列
      Then 包含 "基隆市" => "基隆市"
      And 包含 "臺北市" => "臺北市"
      And 包含 "新北市" => "新北市"
      And 包含 "桃園市" => "桃園市"
      And 包含 "新竹市" / "新竹縣"
      And 包含 "苗栗縣"
      And 包含 "臺中市" / "彰化縣" / "南投縣"
      And 包含 "雲林縣" / "嘉義市" / "嘉義縣"
      And 包含 "臺南市" / "高雄市" / "屏東縣"
      And 包含 "臺東縣" / "花蓮縣" / "宜蘭縣"
      And 包含 "澎湖縣" / "金門縣" / "連江縣"
      And 總計 22 個縣市

    Example: 縣市 key 與 value 相同
      Given Countries::$states 的每個元素
      Then key 等於 value（例如 "基隆市" => "基隆市"）

    Example: 類別為 final 不可被繼承
      Given Countries 類別宣告
      Then 為 final class

  Rule: OrderStatuses - 訂單狀態列表

    從 wc_get_order_statuses() 取得 WooCommerce 註冊的所有訂單狀態，
    自動移除 WooCommerce 的 "wc-" 前綴，並透過內建 mapper 套用預設的
    中文 label 與 Ant Design color。若狀態不在 mapper 中，
    則使用 WooCommerce 回傳的原始名稱並套用 "default" 顏色。

    Example: 從 WooCommerce 取得訂單狀態
      When OrderStatuses::instance() 被呼叫
      Then 內部呼叫 wc_get_order_statuses() 取得 key => name 陣列
      And 回傳 OrderStatuses DTO 實例
      And DTO 的 order_statuses 屬性為陣列，每個元素為 {value, label, color}

    Example: 移除 "wc-" 前綴
      Given wc_get_order_statuses 回傳包含 "wc-processing" 的 key
      When OrderStatuses::instance() 被呼叫
      Then 該狀態的 value 為 "processing"（"wc-" 已被 str_replace 移除）

    Example: 套用 mapper 中的預設狀態對應 - processing
      Given key "processing" 存在於 $order_statuses_mapper
      Then 回傳 {"value": "processing", "label": "處理中", "color": "#108ee9"}

    Example: 套用 mapper 中的預設狀態對應 - pending
      Given key "pending" 存在於 mapper
      Then 回傳 {"value": "pending", "label": "等待付款中", "color": "volcano"}

    Example: 套用 mapper 中的預設狀態對應 - completed
      Given key "completed" 存在於 mapper
      Then 回傳 {"value": "completed", "label": "已完成", "color": "#87d068"}

    Example: 套用 mapper 中的預設狀態對應 - on-hold
      Given key "on-hold" 存在於 mapper
      Then 回傳 {"value": "on-hold", "label": "保留", "color": "gold"}

    Example: 套用 mapper 中的預設狀態對應 - cancelled
      Given key "cancelled" 存在於 mapper
      Then 回傳 {"value": "cancelled", "label": "已取消", "color": "orange"}

    Example: 套用 mapper 中的預設狀態對應 - refunded
      Given key "refunded" 存在於 mapper
      Then 回傳 {"value": "refunded", "label": "已退款", "color": "volcano"}

    Example: 套用 mapper 中的預設狀態對應 - failed
      Given key "failed" 存在於 mapper
      Then 回傳 {"value": "failed", "label": "失敗訂單", "color": "magenta"}

    Example: 套用 mapper 中的預設狀態對應 - checkout-draft
      Given key "checkout-draft" 存在於 mapper
      Then 回傳 {"value": "checkout-draft", "label": "未完成結帳", "color": "gold"}

    Example: 涵蓋第三方延伸狀態 - WMP 配送中
      Given key "wmp-in-transit" 存在於 mapper
      Then 回傳 {"value": "wmp-in-transit", "label": "配送中", "color": "#2db7f5"}

    Example: 涵蓋第三方延伸狀態 - WMP 已出貨
      Given key "wmp-shipped" 存在於 mapper
      Then 回傳 {"value": "wmp-shipped", "label": "已出貨", "color": "green"}

    Example: 涵蓋第三方延伸狀態 - RY 超商撿貨
      Given key "ry-at-cvs" 存在於 mapper
      Then 回傳 {"value": "ry-at-cvs", "label": "RY 等待撿貨中", "color": "cyan"}

    Example: 涵蓋第三方延伸狀態 - RY 超商過期
      Given key "ry-out-cvs" 存在於 mapper
      Then 回傳 {"value": "ry-out-cvs", "label": "RY 訂單過期", "color": "purple"}

    Example: 未知狀態使用預設顏色
      Given wc_get_order_statuses 回傳 key "wc-custom-status"，名稱為 "Custom Status"
      And "custom-status" 不存在於 mapper 中
      When OrderStatuses::instance() 被呼叫
      Then 該元素為 {"value": "custom-status", "label": "Custom Status", "color": "default"}

    Example: 保留 WooCommerce 的順序
      Given wc_get_order_statuses 回傳 key 順序為 [wc-pending, wc-processing, wc-completed]
      Then 回傳的 order_statuses 陣列順序為 [pending, processing, completed]

  Rule: PostStatuses - 文章狀態列表

    從 get_post_statuses() 取得 WordPress 註冊的文章狀態，
    透過內建 mapper 套用預設的中文 label 與 Ant Design color。
    與 OrderStatuses 不同，此處不移除任何前綴。

    Example: 從 WordPress 取得文章狀態
      When PostStatuses::instance() 被呼叫
      Then 內部呼叫 get_post_statuses() 取得 key => name 陣列
      And 回傳 PostStatuses DTO 實例
      And DTO 的 post_statuses 屬性為陣列，每個元素為 {value, label, color}

    Example: 套用 mapper 中的預設狀態對應 - publish
      Given key "publish" 存在於 $post_statuses_mapper
      Then 回傳 {"value": "publish", "label": "已發佈", "color": "blue"}

    Example: 套用 mapper 中的預設狀態對應 - pending
      Given key "pending" 存在於 mapper
      Then 回傳 {"value": "pending", "label": "送交審閱", "color": "volcano"}

    Example: 套用 mapper 中的預設狀態對應 - draft
      Given key "draft" 存在於 mapper
      Then 回傳 {"value": "draft", "label": "草稿", "color": "orange"}

    Example: 套用 mapper 中的預設狀態對應 - private
      Given key "private" 存在於 mapper
      Then 回傳 {"value": "private", "label": "私密", "color": "purple"}

    Example: 套用 mapper 中的預設狀態對應 - trash
      Given key "trash" 存在於 mapper
      Then 回傳 {"value": "trash", "label": "回收桶", "color": "red"}

    Example: 未知狀態使用預設顏色
      Given get_post_statuses 回傳 key "future"，名稱為 "Scheduled"
      And "future" 不存在於 mapper 中
      When PostStatuses::instance() 被呼叫
      Then 該元素為 {"value": "future", "label": "Scheduled", "color": "default"}

    Example: 不移除任何前綴
      Given PostStatuses::instance 的 foreach 邏輯
      Then 不執行 str_starts_with 或 str_replace 的前綴處理
      And key 原樣作為 value

  Rule: ProductStockStatuses - 商品庫存狀態列表

    從 wc_get_product_stock_status_options() 取得 WooCommerce 的商品庫存狀態，
    透過內建 mapper 套用預設的中文 label 與 Ant Design color。

    Example: 從 WooCommerce 取得商品庫存狀態
      When ProductStockStatuses::instance() 被呼叫
      Then 內部呼叫 wc_get_product_stock_status_options() 取得 key => name 陣列
      And 回傳 ProductStockStatuses DTO 實例
      And DTO 的 product_stock_statuses 屬性為陣列，每個元素為 {value, label, color}

    Example: 套用 mapper 中的預設狀態對應 - instock
      Given key "instock" 存在於 $product_stock_statuses_mapper
      Then 回傳 {"value": "instock", "label": "有庫存", "color": "blue"}

    Example: 套用 mapper 中的預設狀態對應 - outofstock
      Given key "outofstock" 存在於 mapper
      Then 回傳 {"value": "outofstock", "label": "缺貨", "color": "magenta"}

    Example: 套用 mapper 中的預設狀態對應 - onbackorder
      Given key "onbackorder" 存在於 mapper
      Then 回傳 {"value": "onbackorder", "label": "預定", "color": "cyan"}

    Example: 未知狀態使用預設顏色
      Given wc_get_product_stock_status_options 回傳 key "preorder"，名稱為 "Pre-order"
      And "preorder" 不存在於 mapper 中
      When ProductStockStatuses::instance() 被呼叫
      Then 該元素為 {"value": "preorder", "label": "Pre-order", "color": "default"}

  Rule: ProductTypes - 商品類型列表

    從 wc_get_product_types() 取得 WooCommerce 註冊的商品類型（含 WooCommerce
    Subscriptions 延伸的訂閱類型），透過內建 mapper 套用預設的中文 label
    與 Ant Design color。mapper 亦涵蓋 variation / subscription_variation
    這類通常不由 wc_get_product_types 回傳的變體類型。

    Example: 從 WooCommerce 取得商品類型
      When ProductTypes::instance() 被呼叫
      Then 內部呼叫 wc_get_product_types() 取得 key => name 陣列
      And 回傳 ProductTypes DTO 實例
      And DTO 的 product_types 屬性為陣列，每個元素為 {value, label, color}

    Example: 套用 mapper 中的預設商品類型 - simple
      Given key "simple" 存在於 $product_types_mapper
      Then 回傳 {"value": "simple", "label": "簡單商品", "color": "processing"}

    Example: 套用 mapper 中的預設商品類型 - variable
      Given key "variable" 存在於 mapper
      Then 回傳 {"value": "variable", "label": "可變商品", "color": "magenta"}

    Example: 套用 mapper 中的預設商品類型 - variation
      Given key "variation" 存在於 mapper
      Then 回傳 {"value": "variation", "label": "商品變體", "color": "magenta"}

    Example: 套用 mapper 中的預設商品類型 - grouped
      Given key "grouped" 存在於 mapper
      Then 回傳 {"value": "grouped", "label": "組合商品", "color": "orange"}

    Example: 套用 mapper 中的預設商品類型 - external
      Given key "external" 存在於 mapper
      Then 回傳 {"value": "external", "label": "外部商品", "color": "lime"}

    Example: 套用 mapper 中的預設商品類型 - subscription
      Given key "subscription" 存在於 mapper
      Then 回傳 {"value": "subscription", "label": "簡易訂閱", "color": "cyan"}

    Example: 套用 mapper 中的預設商品類型 - variable-subscription
      Given key "variable-subscription" 存在於 mapper
      Then 回傳 {"value": "variable-subscription", "label": "可變訂閱", "color": "purple"}

    Example: 套用 mapper 中的預設商品類型 - subscription_variation
      Given key "subscription_variation" 存在於 mapper
      Then 回傳 {"value": "subscription_variation", "label": "訂閱變體", "color": "purple"}

    Example: 未知商品類型使用預設顏色
      Given wc_get_product_types 回傳 key "bundle"，名稱為 "Product Bundle"
      And "bundle" 不存在於 mapper 中
      When ProductTypes::instance() 被呼叫
      Then 該元素為 {"value": "bundle", "label": "Product Bundle", "color": "default"}
