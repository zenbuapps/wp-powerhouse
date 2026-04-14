@ignore @model
Feature: 商品內部模型

  描述 Product Domain 內部使用的資料模型與服務類別。
  涵蓋 Price / Sales / Stock 三個 DTO Model、PeriodLabel Service 以及 Save Utility。
  這些類別為 V2Api 的基礎組件：V2Api 透過 CRUD::update_product 呼叫 Save，
  並透過各 Model 的 instance() 靜態方法組裝商品回應資料。

  Background:
    Given Powerhouse 外掛已啟用
    And WooCommerce 已啟用
    And 所有 Model 皆繼承 "J7\Powerhouse\Domains\Product\Model\DTO" 抽象類別
    And 該抽象類別繼承 "J7\WpUtils\Classes\DTO"

  Rule: Price model - 商品價格資料的取得與格式化

    Price 透過 CRUD::get_price_html() 產生價格 HTML（訂閱商品會附加 PeriodLabel），
    同時把 WC_Product 的原價 / 特價 / 特價期間 / 總銷售量整合為單一 DTO。

    Example: 簡單商品無特價時的價格資料
      Given 一個 WC_Product 簡單商品 ID 為 100
      And regular_price 為 "1000"
      And sale_price 為 ""
      And is_on_sale() 回傳 false
      When 呼叫 "Price::instance($product)"
      Then 回傳一個 Price DTO 實例
      And price_html 為 CRUD::get_price_html($product) 的回傳值
      And regular_price 為 "1000"
      And sale_price 為 ""
      And on_sale 為 false
      And total_sales 為 product->get_total_sales() 的回傳值

    Example: 簡單商品特價中的價格資料
      Given 一個 WC_Product 簡單商品
      And regular_price 為 "1000"
      And sale_price 為 "800"
      And is_on_sale() 回傳 true
      And date_on_sale_from 為 timestamp 1700000000
      And date_on_sale_to 為 timestamp 1705000000
      When 呼叫 "Price::instance($product)"
      Then sale_price 為 "800"
      And on_sale 為 true
      And sale_date_range 為 "[1700000000, 1705000000]"
      And date_on_sale_from 為 1700000000
      And date_on_sale_to 為 1705000000

    Example: 商品未設定特價日期時
      Given 一個 WC_Product 商品
      And get_date_on_sale_from() 回傳 null
      And get_date_on_sale_to() 回傳 null
      When 呼叫 "Price::instance($product)"
      Then sale_date_range 為 "[0, 0]"
      And date_on_sale_from 為 0
      And date_on_sale_to 為 0
      # 使用 null-safe ?->getTimestamp() 後再強制轉成 int，null 會轉成 0

    Example: 訂閱商品的 price_html 會附加週期標籤
      Given 一個 WC_Product_Subscription 商品
      And subscription_period 為 "month"
      And subscription_period_interval 為 1
      When 呼叫 "Price::instance($product)"
      Then price_html 為 "CRUD::get_price_html()" 的回傳值
      And 該 HTML 字串包含 "/月" 週期後綴
      # 實際組裝由 CRUD::get_price_html() 委派給 Subscription::get_price_html() 處理

    Example: total_sales 會被 cast 為 int
      Given 一個 WC_Product 商品 get_total_sales() 回傳 42
      When 呼叫 "Price::instance($product)"
      Then total_sales 為 42 (int)

  Rule: Sales model - 促銷、交叉銷售商品 ID 清單

    Sales DTO 僅包含 upsell 與 cross-sell 兩組商品 ID，並將原始 int 陣列一律轉為 string 陣列，
    以便前端統一處理。

    Example: 商品有 upsell 與 cross-sell 設定
      Given 一個 WC_Product 商品
      And get_upsell_ids() 回傳 "[10, 20, 30]"
      And get_cross_sell_ids() 回傳 "[40, 50]"
      When 呼叫 "Sales::instance($product)"
      Then upsell_ids 為 '["10", "20", "30"]'
      And cross_sell_ids 為 '["40", "50"]'
      # 所有 ID 透過 array_map('strval', ...) 轉為字串

    Example: 商品沒有任何 upsell 或 cross-sell
      Given 一個 WC_Product 商品
      And get_upsell_ids() 回傳空陣列
      And get_cross_sell_ids() 回傳空陣列
      When 呼叫 "Sales::instance($product)"
      Then upsell_ids 為空陣列
      And cross_sell_ids 為空陣列

    Example: 允許 null product 的 null-safe 呼叫
      Given $product 可能為 null
      When 呼叫 "Sales::instance($product)"
      Then 使用 null-safe 運算子 "$product?->get_upsell_ids()" 避免呼叫 null 方法
      # 注意：實作中使用 $product?->get_upsell_ids()，但 array_map 對 null 會觸發錯誤，
      # 因此實際呼叫仍應傳入有效的 WC_Product 實例

  Rule: Stock model - 庫存狀態判斷邏輯

    Stock DTO 整合 WooCommerce 庫存相關的 getter 方法，並將 boolean 欄位統一轉為 'yes' / 'no' 字串
    以符合前端 Ant Design Switch 的表單格式。

    Example: 管理庫存且庫存充足的商品
      Given 一個 WC_Product 商品
      And stock_status 為 "instock"
      And manage_stock 為 true
      And stock_quantity 為 100
      And backorders 為 "no"
      When 呼叫 "Stock::instance($product)"
      Then stock_status 為 "instock"
      And manage_stock 為 "yes"
      And stock_quantity 為 100
      And backorders 為 "no"
      And backorders_allowed 為 "no"
      And backordered 為 "no"

    Example: 不管理庫存的商品
      Given 一個 WC_Product 商品
      And manage_stock 為 false
      And stock_quantity 為 null
      When 呼叫 "Stock::instance($product)"
      Then manage_stock 為 "no"
      And stock_quantity 為 null
      # stock_quantity 保留 int|null 型別，不會被轉型

    Example: 允許缺貨訂購且目前缺貨中
      Given 一個 WC_Product 商品
      And backorders 為 "yes"
      And backorders_allowed() 回傳 true
      And is_on_backorder() 回傳 true
      When 呼叫 "Stock::instance($product)"
      Then backorders 為 "yes"
      And backorders_allowed 為 "yes"
      And backordered 為 "yes"

    Example: 低庫存警告數量會被強制轉為字串
      Given 一個 WC_Product 商品
      And get_low_stock_amount() 回傳 int 5
      When 呼叫 "Stock::instance($product)"
      Then low_stock_amount 為 "5"
      # 透過 (string) 強制型別轉換

    Example: 未設定低庫存警告時
      Given 一個 WC_Product 商品 get_low_stock_amount() 回傳空字串 ""
      When 呼叫 "Stock::instance($product)"
      Then low_stock_amount 為 ""

  Rule: PeriodLabel service - 訂閱週期顯示文字

    PeriodLabel 將訂閱週期 (day / week / month / year) 與間隔 (interval) 轉為人類可讀的中文標籤。
    建構時會根據 period 設定基本 period_label，get_label() 則處理特殊 interval 的簡化顯示
    （例如 3 month => 季、12 month => 年、7 day => 週）。

    Example: 建構時的基本 period_label
      Given period 為 "day"
      When 呼叫 "new PeriodLabel('day')"
      Then period_label 為 "天"

    Example: month 的基本 period_label
      Given period 為 "month"
      When 呼叫 "new PeriodLabel('month')"
      Then period_label 為 "月"

    Example: interval 為 1 時不顯示數字
      Given period 為 "month"
      And interval 為 1
      When 呼叫 "get_label('/', '')"
      Then 回傳 "/月"
      # interval <= 1 時直接回傳 "{before}{period_label}{after}"

    Example: interval 為 0 時也走 interval <= 1 分支
      Given period 為 "month"
      And interval 為 0
      When 呼叫 "get_label()"
      Then 回傳 "月"

    Example: day × 7 簡化為週
      Given period 為 "day"
      And interval 為 7
      When 呼叫 "get_label('/')"
      Then 回傳 "/週"

    Example: day × 14 簡化為雙週
      Given period 為 "day"
      And interval 為 14
      When 呼叫 "get_label('/')"
      Then 回傳 "/雙週"

    Example: day × 3 回傳原始 "天"
      Given period 為 "day"
      And interval 為 3
      When 呼叫 "get_label('/')"
      Then 回傳 "/天"
      # 非 7 / 14 時走 default 分支，但注意 interval 數字本身不會被加到輸出

    Example: week × 2 簡化為雙週
      Given period 為 "week"
      And interval 為 2
      When 呼叫 "get_label('/')"
      Then 回傳 "/雙週"

    Example: week × 3 回傳原始 "週"
      Given period 為 "week"
      And interval 為 3
      When 呼叫 "get_label('/')"
      Then 回傳 "/週"

    Example: month × 3 簡化為季
      Given period 為 "month"
      And interval 為 3
      When 呼叫 "get_label('/')"
      Then 回傳 "/季"

    Example: month × 6 簡化為半年
      Given period 為 "month"
      And interval 為 6
      When 呼叫 "get_label('/')"
      Then 回傳 "/半年"

    Example: month × 9 簡化為 3 季
      Given period 為 "month"
      And interval 為 9
      When 呼叫 "get_label('/')"
      Then 回傳 "/3季"

    Example: month × 12 簡化為年
      Given period 為 "month"
      And interval 為 12
      When 呼叫 "get_label('/')"
      Then 回傳 "/年"

    Example: month × 2 走 default 分支顯示 "個月"
      Given period 為 "month"
      And interval 為 2
      When 呼叫 "get_label('/')"
      Then 回傳 "/個月"
      # month 分支的 default 會在 period_label 前加上 "個"

    Example: year × 2 走最終 fallback
      Given period 為 "year"
      And interval 為 2
      When 呼叫 "get_label('/')"
      Then 回傳 "/年"
      # year 不進入任何特殊 match，直接回到函式末尾 return

    Example: 支援 addon_before 與 addon_after 夾住標籤
      Given period 為 "month"
      And interval 為 3
      When 呼叫 "get_label('每', '收費')"
      Then 回傳 "每季收費"

    Example: 被 Utils\Subscription 呼叫組合價格後綴
      Given 訂閱商品 subscription_period 為 "month"
      And subscription_period_interval 為 1
      When Subscription::get_price_html($product) 執行
      Then 內部使用 "new PeriodLabel('month', 1)"
      And get_label('/') 回傳 "/月"
      # 作為 price_html 的週期後綴

  Rule: Save utility - 商品儲存的中央處理邏輯

    Save 提供兩個靜態方法：data() 使用 WC_Product::set_props 批次設定欄位，
    meta_data() 則處理商品類型切換、訂閱欄位清理與 post_meta 寫入。
    兩者皆會觸發 powerhouse/product/before_save_* 與 after_save_* action。
    被 CRUD::create_product 與 CRUD::update_product 呼叫。

    Example: Save::data 觸發 before / after action 並批次設定欄位
      Given 一個 WC_Product 商品實例
      And 資料陣列為 "{'name': 'New Name', 'regular_price': '1000'}"
      When 呼叫 "Save::data($product, $data)"
      Then 先觸發 action "powerhouse/product/before_save_data" 帶 $product 與 $data
      And 呼叫 "$product->set_props($data)"
      And 最後觸發 action "powerhouse/product/after_save_data"

    Example: Save::data 不會呼叫 $product->save()
      Given 一個 WC_Product 商品
      When 呼叫 "Save::data($product, $data)"
      Then 方法不會呼叫 "$product->save()"
      # 由呼叫端（CRUD::create_product / update_product）負責 save

    Example: Save::meta_data 切換商品類型 simple => variable
      Given 一個 WC_Product 商品目前 type 為 "simple"
      And meta_data 包含 "type" => "variable"
      When 呼叫 "Save::meta_data($product, $meta_data)"
      Then 使用 "wp_remove_object_terms" 移除舊的 "simple" product_type term
      And 使用 "wp_set_object_terms" 設定新的 "variable" product_type term
      And meta_data 中的 "type" key 被 unset 不會被寫入 post_meta

    Example: Save::meta_data 切換到訂閱商品且 WC_Subscription 不存在
      Given 目前環境中 WC_Subscription class 不存在
      And meta_data 包含 "type" => "subscription"
      When 呼叫 "Save::meta_data($product, $meta_data)"
      Then 拋出 \Exception
      And 錯誤訊息為 "WC_Subscription class does not exist, please make sure WooCommerce Subscription is installed"

    Example: Save::meta_data 切換到訂閱商品且 WC_Subscription 存在
      Given WC_Subscription class 存在
      And 商品目前 type 為 "simple"
      And meta_data 為 "{'type': 'subscription', 'subscription_price': '100', 'subscription_period': 'month'}"
      When 呼叫 "Save::meta_data($product, $meta_data)"
      Then 商品的 product_type term 從 "simple" 更新為 "subscription"
      And $is_subscription 為 true
      And 訂閱相關欄位不會被刪除
      And "subscription_price" 與 "subscription_period" 透過 update_post_meta 寫入

    Example: Save::meta_data 非訂閱商品會清除所有訂閱欄位
      Given 商品目前 type 為 "simple"
      And meta_data 為 "{'type': 'simple'}"
      When 呼叫 "Save::meta_data($product, $meta_data)"
      Then 透過 "Subscription::get_fields()" 取得訂閱欄位清單
      And 逐一呼叫 "$product->delete_meta_data($field)" 刪除以下欄位:
        | _subscription_price          |
        | _subscription_period         |
        | _subscription_period_interval|
        | _subscription_length         |
        | _subscription_sign_up_fee    |
        | _subscription_trial_length   |
        | _subscription_trial_period   |

    Example: Save::meta_data 完全不傳 type 時不觸發類型切換
      Given meta_data 為 "{'custom_field': 'value'}" 不包含 "type" key
      When 呼叫 "Save::meta_data($product, $meta_data)"
      Then 不呼叫 "wp_remove_object_terms" 也不呼叫 "wp_set_object_terms"
      And $is_subscription 保持 false
      And 仍會清除所有訂閱欄位（因為 $is_subscription 為 false）
      # 注意：這是實作上的副作用 - 不傳 type 時依然會清除訂閱欄位

    Example: Save::meta_data 會過濾 images 與 files 欄位
      Given meta_data 為 "{'images': [...], 'files': [...], 'custom_field': 'value'}"
      When 呼叫 "Save::meta_data($product, $meta_data)"
      Then "images" 被 unset 不會寫入 post_meta
      And "files" 被 unset 不會寫入 post_meta
      And "custom_field" 會透過 update_post_meta 寫入
      # 註解說明：圖片僅用於顯示、檔案另行處理上傳

    Example: Save::meta_data 觸發 before / after action
      Given 一個 WC_Product 商品
      And meta_data 陣列
      When 呼叫 "Save::meta_data($product, $meta_data)"
      Then 在寫入 post_meta 之前觸發 "powerhouse/product/before_save_meta_data"
      And 在寫入 post_meta 之後觸發 "powerhouse/product/after_save_meta_data"
      And 兩個 action 皆帶 $product 與（已經過 unset 處理的）$meta_data

    Example: Save::meta_data 使用 update_post_meta 而非 update_meta_data
      Given 一個 WC_Product 商品與 meta_data
      When 呼叫 "Save::meta_data($product, $meta_data)"
      Then 內部使用 "update_post_meta($product->get_id(), $key, $value)"
      And 不使用 "$product->update_meta_data($key, $value)"
      # 註解說明：若要用 update_meta_data 需要知道 mid

  Rule: Model / Service / Save 的協作關係

    說明 5 個類別在完整請求生命週期中的互動。

    Example: 建立商品時的呼叫鏈
      Given 透過 V2Api "POST /products" 建立商品
      When Domains\Product\Core\V2Api 處理請求
      Then 呼叫 "CRUD::create_product($data, $meta_data)"
      And CRUD::create_product 建立 "new WC_Product_Simple()"
      And 接著呼叫 "Save::data($product, $data)"
      And 最後呼叫 "$product->save()"

    Example: 更新商品時的呼叫鏈
      Given 透過 V2Api "POST /products/{id}" 更新商品
      When Domains\Product\Core\V2Api 處理請求
      Then 呼叫 "CRUD::update_product($product, $data, $meta_data)"
      And 先呼叫 "Save::data($product, $data)" 處理 props
      And 呼叫 "$product->save()" 寫入資料庫
      And 再呼叫 "Save::meta_data($product, $meta_data)" 處理 meta
      And 最後呼叫 "wc_delete_product_transients()" 清除商品 transient

    Example: 查詢單一商品時組裝回應資料
      Given 透過 V2Api "GET /products/{id}" 取得商品
      When Product\Model\Product 組裝商品資料
      Then 呼叫 "Price::instance($product)" 取得價格資料
      And 呼叫 "Sales::instance($product)" 取得 upsell / cross-sell
      And 呼叫 "Stock::instance($product)" 取得庫存資料
      And 三個 DTO 的欄位被合併到 Product 回應中

    Example: Price model 委派給 Utils\Subscription 處理訂閱 price_html
      Given 一個訂閱商品
      When 呼叫 "Price::instance($product)"
      Then Price 呼叫 "CRUD::get_price_html($product)"
      And CRUD::get_price_html 委派給 "Subscription::get_price_html($product)"
      And Subscription::get_price_html 使用 "new PeriodLabel($period, $interval)"
      And PeriodLabel::get_label('/') 產生週期後綴
      # 完整呼叫鏈：Price -> CRUD -> Subscription -> PeriodLabel

  Rule: Subscription::get_subscription_meta_data 讀取訂閱商品 meta 欄位

    描述 Domains/Product/Utils/Subscription.php 的 get_subscription_meta_data() 靜態方法。
    此方法透過 WC_Product::get_meta() 批次讀取 7 個訂閱相關的 meta 欄位，
    並以底線開頭 key 的陣列形式回傳。當 WC_Subscription class 不存在時，
    所有欄位會被填為空字串。此方法為 get_meta_data_label 與 get_price_html 的資料來源。

    Example: get_subscription_meta_data 回傳陣列結構
      Given 一個 WC_Product 商品
      And WC_Subscription class 存在
      When 呼叫 "Subscription::get_subscription_meta_data($product)"
      Then 回傳陣列包含以下 7 個 key：
        | key                            |
        | _subscription_price            |
        | _subscription_period           |
        | _subscription_period_interval  |
        | _subscription_length           |
        | _subscription_sign_up_fee      |
        | _subscription_trial_length     |
        | _subscription_trial_period     |

    Example: get_subscription_meta_data 逐一呼叫 get_meta 讀取值
      Given 一個 WC_Product 商品 meta 包含以下資料：
        | meta key             | value     |
        | _subscription_price  | "199"     |
        | _subscription_period | "month"   |
      When 呼叫 "get_subscription_meta_data($product)"
      Then 回傳陣列中 "_subscription_price" 為 "199"
      And 回傳陣列中 "_subscription_period" 為 "month"
      # 實作迴圈：foreach ($fields as $field) { $values[$field] = $product->get_meta($field); }

    Example: get_subscription_meta_data 在 WC_Subscription 不存在時回傳空字串
      Given 目前環境中 "\WC_Subscription" class 不存在
      When 呼叫 "Subscription::get_subscription_meta_data($product)"
      Then 回傳陣列 7 個欄位值皆為空字串 ""
      # 透過 array_fill_keys($fields, '') 產生

    Example: get_subscription_meta_data 欄位清單由 get_fields(true) 產生
      Given get_fields 方法預設參數為 with_underline = true
      When "get_subscription_meta_data" 呼叫 "self::get_fields()"
      Then 取得的欄位皆以底線 "_" 開頭
      # 與 WooCommerce Subscriptions 儲存的實際 meta key 一致

  Rule: Subscription::get_meta_data_label 訂閱商品 meta 顯示標籤

    描述 Domains/Product/Utils/Subscription.php 的 get_meta_data_label() 靜態方法。
    此方法將訂閱商品的 meta 值轉為前端顯示用的中文標籤陣列，
    涵蓋扣款期限、首次開通費、免費試用三類資訊。

    Example: get_meta_data_label 回傳型別為 array<string>
      Given 一個訂閱 WC_Product
      When 呼叫 "get_meta_data_label($product)"
      Then 回傳 array<string>
      # 陣列中每個元素為一段中文標籤

    Example: get_meta_data_label 在 WC_Subscription 不存在時回傳空陣列
      Given "\WC_Subscription" class 不存在
      When 呼叫 "get_meta_data_label($product)"
      Then 回傳空陣列 []
      # 第一行：if (!class_exists('\WC_Subscription')) { return []; }

    Example: 訂閱有固定扣款期數時產生「扣款持續 N 月」標籤
      Given 一個訂閱商品
      And _subscription_length 為 "4"
      And _subscription_period 為 "month"
      When 呼叫 "get_meta_data_label($product)"
      Then 回傳陣列包含 "扣款持續 4月"
      # 實際輸出："扣款持續 {length}{period_label}"，其中 period_label 由 PeriodLabel 產生

    Example: _subscription_length 為 0 時不產生扣款持續標籤
      Given 一個訂閱商品
      And _subscription_length 為 "0"
      And _subscription_period 為 "month"
      When 呼叫 "get_meta_data_label($product)"
      Then 回傳陣列中不含「扣款持續」標籤
      # 條件：$subscription_length 為 truthy 才進入分支

    Example: _subscription_period 為無效值時不產生扣款持續標籤
      Given 一個訂閱商品
      And _subscription_length 為 "4"
      And _subscription_period 為 "invalid"
      When 呼叫 "get_meta_data_label($product)"
      Then 回傳陣列中不含「扣款持續」標籤
      # 只有 day / week / month / year 為有效 period

    Example: 訂閱有首次開通費時產生「首次開通 {price}」標籤
      Given 一個訂閱商品
      And _subscription_sign_up_fee 為 "500"
      When 呼叫 "get_meta_data_label($product)"
      Then 回傳陣列包含 "首次開通 " 加上 wc_price(500) 的回傳值
      # 使用 wc_price((float) $subscription_sign_up_fee) 格式化金額

    Example: _subscription_sign_up_fee 為 0 時不產生首次開通標籤
      Given 一個訂閱商品
      And _subscription_sign_up_fee 為 "0"
      When 呼叫 "get_meta_data_label($product)"
      Then 回傳陣列中不含「首次開通」標籤

    Example: 訂閱有免費試用期時產生「包含 N 天免費試用」標籤
      Given 一個訂閱商品
      And _subscription_trial_length 為 "7"
      And _subscription_trial_period 為 "day"
      When 呼叫 "get_meta_data_label($product)"
      Then 回傳陣列包含 "包含 7天 免費試用"
      # 實際輸出："包含 {length}{period_label} 免費試用"

    Example: _subscription_trial_length 為 0 時不產生試用標籤
      Given 一個訂閱商品
      And _subscription_trial_length 為 "0"
      And _subscription_trial_period 為 "day"
      When 呼叫 "get_meta_data_label($product)"
      Then 回傳陣列中不含「免費試用」標籤

    Example: _subscription_trial_period 為無效值時不產生試用標籤
      Given 一個訂閱商品
      And _subscription_trial_length 為 "7"
      And _subscription_trial_period 為 "invalid"
      When 呼叫 "get_meta_data_label($product)"
      Then 回傳陣列中不含「免費試用」標籤

    Example: 三類條件皆成立時產生完整三個標籤
      Given 一個訂閱商品
      And _subscription_length 為 "12"
      And _subscription_period 為 "month"
      And _subscription_sign_up_fee 為 "100"
      And _subscription_trial_length 為 "14"
      And _subscription_trial_period 為 "day"
      When 呼叫 "get_meta_data_label($product)"
      Then 回傳陣列包含 "扣款持續 12月"
      And 包含 "首次開通 " 加上 wc_price(100) 回傳值
      And 包含 "包含 14天 免費試用"
      And 三個標籤依照「扣款持續 → 首次開通 → 免費試用」的順序排列

    Example: get_meta_data_label 透過 get_subscription_meta_data 解構 meta
      Given get_meta_data_label 內部使用 PHP array destructuring
      When 方法被執行
      Then 先呼叫 "self::get_subscription_meta_data($product)"
      And 透過 list-like 解構提取 _subscription_period / _subscription_length 等 5 個欄位
      # _subscription_price 與 _subscription_period_interval 不在此 method 使用

  Rule: Subscription::wc_format_subscription_sale_price 格式化訂閱特價

    描述 Domains/Product/Utils/Subscription.php 的 wc_format_subscription_sale_price() 方法。
    此方法是 WooCommerce wc_format_sale_price 的訂閱版本，除了顯示原價 / 特價外，
    還會在兩個價格後各自附加週期標籤（例如「/月」），同時保留 a11y 的 screen-reader-text。

    Example: wc_format_subscription_sale_price 參數型別
      Given 方法簽名為 "wc_format_subscription_sale_price(\$regular_price, \$sale_price, \$period_label)"
      Then 三個參數皆為 string 型別（無型別宣告但回傳 string）
      And 回傳 (string) apply_filters 的結果

    Example: 數值型的原價與特價會被 wc_price 格式化
      Given regular_price 為 "1000"
      And sale_price 為 "800"
      And period_label 為 "<span class=\"text-sm\">/月</span>"
      When 呼叫 "wc_format_subscription_sale_price('1000', '800', $period_label)"
      Then formatted_regular_price 為 wc_price(1000.0) + period_label
      And formatted_sale_price 為 wc_price(800.0) + period_label
      # is_numeric 判斷為 true 時才呼叫 wc_price

    Example: 非數值型的原價會直接串接 period_label
      Given regular_price 為 "Free"（非數值字串）
      And period_label 為 "/月"
      When 呼叫 "wc_format_subscription_sale_price('Free', '800', '/月')"
      Then formatted_regular_price 為 "Free/月"
      # is_numeric("Free") 為 false 直接使用原字串

    Example: 輸出 HTML 包含 del 與 ins 標籤
      Given 任意合法參數
      When 方法執行
      Then 回傳字串包含 "<del aria-hidden=\"true\">" 原價 "</del>"
      And 包含 "<ins aria-hidden=\"true\">" 特價 "</ins>"
      # del 代表刪除線，ins 代表新插入

    Example: 輸出包含無障礙 screen-reader-text
      Given 任意合法參數
      When 方法執行
      Then 輸出 HTML 包含兩段 "<span class=\"screen-reader-text\">"
      And 第一段為 "Original price was: {price}."（原價資訊）
      And 第二段為 "Current price is: {price}."（特價資訊）
      # 透過 wp_strip_all_tags 去除標籤後再經 esc_html 輸出

    Example: screen-reader 文字透過 WooCommerce text domain 翻譯
      Given 方法內使用 __() 函式
      When 方法執行
      Then 第一段使用 "__('Original price was: %s.', 'woocommerce')"
      And 第二段使用 "__('Current price is: %s.', 'woocommerce')"
      # 使用 woocommerce textdomain 而非 powerhouse

    Example: 最終回傳值會經過 woocommerce_format_sale_price filter
      Given 方法產生完整的 HTML 字串 $price
      When return 執行
      Then 回傳 "(string) apply_filters('woocommerce_format_sale_price', \$price, \$regular_price, \$sale_price)"
      # 允許第三方 hook 再次覆寫

    Example: period_label 會在原價與特價後各自被加上
      Given period_label 為 "<span class=\"text-sm\">/月</span>"
      When 方法執行
      Then formatted_regular_price 與 formatted_sale_price 各自都附加一次 period_label
      # 兩個變數分別賦值：$formatted_regular_price = $formatted_regular_price . $period_label;

    Example: wc_format_subscription_sale_price 被 get_price_html 呼叫
      Given 一個訂閱商品且 is_on_sale() 回傳 true
      When Subscription::get_price_html($product) 執行
      Then 內部呼叫 "self::wc_format_subscription_sale_price(regular, sale, period_label)"
      And 結果再串接 "$product->get_price_suffix()"
      # 完整呼叫鏈：get_price_html → wc_format_subscription_sale_price
