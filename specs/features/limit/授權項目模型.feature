@ignore @model
Feature: 授權項目模型

  描述 Limit Domain 內部使用的資料模型：ExpireDate（過期時間計算與標籤產生）、
  GrantedItem（單一已授權項目與用戶的關係）、GrantedItems（批次查詢用戶已授權項目集合）。
  這些類別由 Limit\Core\V2Api 以及 Limit\Utils\MetaCRUD 在處理授權項目查詢時使用。

  Background:
    Given Powerhouse 外掛已啟用
    And Limit Domain 已載入

  # ============================================================
  # ExpireDate - 到期時間計算
  # ============================================================
  # ExpireDate 本身不直接使用 limit_type，而是接收 Limit::calc_expire_date()
  # 回傳的值（int 或 string）並做解析。三種輸入格式為：
  #   - 0 (int)：無期限
  #   - timestamp (int/numeric string)：到期日
  #   - "subscription_{id}" (string)：跟隨訂閱
  # ============================================================

  Rule: ExpireDate 解析 expire_date 輸入並判斷是否過期

    Example: 輸入 0 表示無期限且永不過期
      Given expire_date 為整數 0
      When 建立 ExpireDate($expire_date)
      Then is_subscription 為 false
      And subscription_id 為 null
      And timestamp 為 0
      And is_expired 為 false
      And expire_date_label 為 "無期限"

    Example: 輸入未來 timestamp 表示尚未過期
      Given expire_date 為一個大於現在時間的 timestamp
      When 建立 ExpireDate($expire_date)
      Then is_subscription 為 false
      And timestamp 為該 timestamp
      And is_expired 為 false
      And expire_date_label 為 "至" 後接格式化日期 "Y-m-d H:i:s"

    Example: 輸入過去 timestamp 表示已過期
      Given expire_date 為一個小於現在時間的 timestamp
      When 建立 ExpireDate($expire_date)
      Then is_expired 為 true
      And expire_date_label 仍為 "至" 後接格式化日期

    Example: 輸入非數字且非訂閱字串表示立即過期
      Given expire_date 為字串 "invalid"
      And WC_Subscription class 不存在（或無法識別為訂閱）
      When 建立 ExpireDate($expire_date)
      Then is_expired 為 true
      And timestamp 維持 null
      And expire_date_label 為 "無法取得時間"

  Rule: ExpireDate 能辨識並處理跟隨訂閱格式

    Example: 輸入 "subscription_123" 且訂閱為 active
      Given expire_date 為字串 "subscription_123"
      And WC_Subscription class 存在
      And wcs_get_subscription(123) 回傳狀態為 "active" 的訂閱
      When 建立 ExpireDate($expire_date)
      Then is_subscription 為 true
      And subscription_id 為 123
      And is_expired 為 false
      And expire_date_label 為 "跟隨訂閱 #123"

    Example: 輸入 "subscription_456" 且訂閱非 active
      Given expire_date 為字串 "subscription_456"
      And wcs_get_subscription(456) 回傳狀態為 "cancelled" 的訂閱
      When 建立 ExpireDate($expire_date)
      Then is_subscription 為 true
      And is_expired 為 true
      And expire_date_label 為 "訂閱 #456 已到期"

    Example: 輸入 "subscription_789" 但訂閱不存在
      Given expire_date 為字串 "subscription_789"
      And wcs_get_subscription(789) 回傳 false
      When 建立 ExpireDate($expire_date)
      Then is_subscription 為 true
      And is_expired 為 true

  Rule: ExpireDate 提供自訂日期格式的 set_label

    Example: 以自訂格式重新產生標籤
      Given 已建立 ExpireDate(timestamp 為未來某時間)
      When 呼叫 set_label("Y/m/d")
      Then expire_date_label 為 "至" 後接 "Y/m/d" 格式的日期

    Example: 傳入 null format 時退回預設格式
      Given 已建立非訂閱的 ExpireDate
      When 呼叫 set_label(null)
      Then expire_date_label 為 "至" 後接 "Y-m-d H:i:s" 格式的日期

  Rule: ExpireDate 可序列化為 array

    Example: to_array 輸出固定結構
      Given 已建立 ExpireDate(0)
      When 呼叫 to_array()
      Then 回傳陣列包含 keys "is_subscription"、"subscription_id"、"is_expired"、"timestamp"
      And is_subscription 為 false
      And subscription_id 為 null
      And is_expired 為 false
      And timestamp 為 0

  # ============================================================
  # GrantedItem - 單一已授權項目
  # ============================================================

  Rule: GrantedItem 從 MetaCRUD 讀取用戶-項目關係

    Example: 用戶對項目有授權資料
      Given 項目 ID 為 100
      And 用戶 ID 為 5
      And MetaCRUD::get(100, 5, "expire_date", true) 回傳 "0"
      When 建立 GrantedItem(100, 5)
      Then expire_date 為 ExpireDate 實例
      And expire_date 的 timestamp 為 0
      And expire_date 的 is_expired 為 false

    Example: 用戶對項目無授權資料
      Given MetaCRUD::get(200, 5, "expire_date", true) 回傳空字串 ""
      When 建立 GrantedItem(200, 5)
      Then can_access 為 false
      And expire_date 為 null

    Example: 使用自訂 meta_key
      Given MetaCRUD::get(300, 5, "custom_key", true) 回傳 "subscription_10"
      When 建立 GrantedItem(300, 5, "custom_key")
      Then expire_date 為 ExpireDate 實例
      And expire_date 的 is_subscription 為 true

  # ============================================================
  # GrantedItems - 已授權項目集合
  # ============================================================

  Rule: GrantedItems 以 JOIN 查詢用戶所有已授權項目

    Example: 無過濾條件取得所有已授權項目
      Given 用戶 ID 為 5
      And access_itemmeta 表中有 user_id=5 且 meta_key="expire_date" 的紀錄
      When 呼叫 GrantedItems(5)->get_granted_items()
      Then 執行 SQL 從 access_itemmeta LEFT JOIN wp_posts
      And 回傳陣列中每筆包含 keys "id"、"name"、"expire_date"
      And id 為字串型別
      And name 來自 get_the_title()
      And expire_date 來自 GrantedItem 的 ExpireDate::to_array()（可能為 null）

    Example: 以 where 條件過濾 post_type
      Given 用戶 ID 為 5
      When 呼叫 GrantedItems(5)->get_granted_items(["post_type" => "product"])
      Then SQL WHERE 子句附加 " AND p.post_type = 'product'"
      And 只回傳 post_type 為 product 的已授權項目

  Rule: GrantedItems 以 wp_cache 快取查詢結果

    Example: 同一查詢走快取
      Given 用戶 ID 為 5
      And 先前已呼叫 get_item_ids(["post_type" => "course"]) 且結果已寫入 wp_cache
      And cache key 為 "granted_items_5_where_" 後接 where JSON 字串
      When 再次呼叫 get_item_ids(["post_type" => "course"])
      Then 直接回傳 wp_cache 中的結果
      And 不再執行 SQL 查詢

    Example: 不同 where 使用不同 cache key
      Given 已快取 where=[] 的結果
      When 呼叫 get_item_ids(["post_type" => "product"])
      Then 因 cache key 不同而重新執行 SQL 查詢

  Rule: GrantedItems 依賴 access_itemmeta 自訂資料表

    Example: 資料表名稱來自 CreateTable 常數
      Given CreateTable::ACCESS_ITEMMETA_TABLE_NAME 定義了資料表名
      When 執行 get_item_ids()
      Then SQL 使用 "{$wpdb->prefix}" + ACCESS_ITEMMETA_TABLE_NAME 作為主表
      And LEFT JOIN "{$wpdb->prefix}posts" 以 pm.post_id = p.ID 關聯

  # ============================================================
  # Limit - 限制類型計算邏輯
  # ============================================================
  # Limit 類別負責承載「限制類型 / 限制值 / 限制單位」三個欄位，
  # 並將其翻譯為實際到期日（calc_expire_date）與人類可讀標籤（get_limit_label）。
  # 注意：Limit::get_expire_date(?\WC_Order $order) 實際上是 calc_expire_date 的
  # 包裝器，接收同樣的 order 參數並將結果包裝為 ExpireDate 物件。
  # ============================================================

  Rule: Limit::calc_expire_date 根據 limit_type 計算 expire_date

    Example: limit_type = unlimited 時回傳整數 0
      Given Limit 物件 limit_type 為 "unlimited"
      And limit_value 為 null
      And limit_unit 為 null
      When 呼叫 calc_expire_date(null)
      Then 回傳整數 0

    Example: limit_type = unlimited 時即使傳入 order 也回傳 0
      Given Limit 物件 limit_type 為 "unlimited"
      And 傳入一個 WC_Order 實例
      When 呼叫 calc_expire_date($order)
      Then 回傳整數 0

    Example: limit_type = assigned 時直接回傳 limit_value 作為 timestamp
      Given Limit 物件 limit_type 為 "assigned"
      And limit_value 為 1800000000
      When 呼叫 calc_expire_date(null)
      Then 回傳整數 1800000000

    Example: limit_type = fixed 時計算「當天 15:59:00 + N 單位」
      Given Limit 物件 limit_type 為 "fixed"
      And limit_value 為 30
      And limit_unit 為 "day"
      When 呼叫 calc_expire_date(null)
      Then 先計算 strtotime("+30 day") 得到 $expire_date_timestamp
      And 再將 $expire_date_timestamp 轉為當天日期並固定在 "15:59:00"
      And 回傳 strtotime("{Y-m-d} 15:59:00") 的整數結果

    Example: limit_type = fixed 時支援 month / year 單位
      Given Limit 物件 limit_type 為 "fixed"
      And limit_value 為 1
      And limit_unit 為 "year"
      When 呼叫 calc_expire_date(null)
      Then 回傳 strtotime("+1 year") 對應當天 15:59:00 的 timestamp

    Example: limit_type = follow_subscription 但未傳 order 時回傳 0
      Given Limit 物件 limit_type 為 "follow_subscription"
      When 呼叫 calc_expire_date(null)
      Then 回傳整數 0

    Example: limit_type = follow_subscription 但 WC_Subscription class 不存在
      Given Limit 物件 limit_type 為 "follow_subscription"
      And WC_Subscription class 不存在
      And 傳入一個 WC_Order 實例
      When 呼叫 calc_expire_date($order)
      Then 寫入 log "訂單 {id} 的 expire_date 計算失敗，因為 WC_Subscription 不存在"
      And 回傳整數 0

    Example: limit_type = follow_subscription 且訂單剛好綁一個訂閱
      Given Limit 物件 limit_type 為 "follow_subscription"
      And WC_Subscription class 存在
      And wcs_get_subscriptions_for_order($order, ["order_type" => "parent"]) 回傳長度為 1 的陣列
      And 其中訂閱的 id 為 123
      When 呼叫 calc_expire_date($order)
      Then 回傳字串 "subscription_123"

    Example: limit_type = follow_subscription 但訂單沒綁訂閱
      Given Limit 物件 limit_type 為 "follow_subscription"
      And wcs_get_subscriptions_for_order 回傳空陣列
      When 呼叫 calc_expire_date($order)
      Then 回傳整數 0

    Example: limit_type = follow_subscription 但訂單綁多個訂閱（非預期）
      Given Limit 物件 limit_type 為 "follow_subscription"
      And wcs_get_subscriptions_for_order 回傳長度為 2 的陣列
      When 呼叫 calc_expire_date($order)
      Then 回傳整數 0

  Rule: Limit::get_expire_date 將 calc_expire_date 的結果包裝為 ExpireDate

    Example: get_expire_date 回傳 ExpireDate 實例
      Given Limit 物件 limit_type 為 "unlimited"
      When 呼叫 get_expire_date(null)
      Then 回傳一個 ExpireDate 實例
      And 該 ExpireDate 的 timestamp 為 0
      And 該 ExpireDate 的 is_expired 為 false

    Example: get_expire_date 對 follow_subscription 傳遞 subscription 字串
      Given Limit 物件 limit_type 為 "follow_subscription"
      And calc_expire_date($order) 會回傳字串 "subscription_123"
      When 呼叫 get_expire_date($order)
      Then 回傳 ExpireDate 實例
      And 該 ExpireDate 的 is_subscription 為 true
      And 該 ExpireDate 的 subscription_id 為 123

  Rule: Limit::get_limit_label 產生人類可讀的 type / value 標籤

    Example: limit_type = fixed 且 unit = day
      Given Limit 物件 limit_type 為 "fixed"
      And limit_value 為 10
      And limit_unit 為 "day"
      When 呼叫 get_limit_label()
      Then 回傳 object {type: "固定時間", value: "10 天"}

    Example: limit_type = fixed 且 unit = month
      Given Limit 物件 limit_type 為 "fixed"
      And limit_value 為 3
      And limit_unit 為 "month"
      When 呼叫 get_limit_label()
      Then 回傳 object {type: "固定時間", value: "3 月"}

    Example: limit_type = fixed 且 unit = year
      Given Limit 物件 limit_type 為 "fixed"
      And limit_value 為 1
      And limit_unit 為 "year"
      When 呼叫 get_limit_label()
      Then 回傳 object {type: "固定時間", value: "1 年"}

    Example: limit_type = assigned 且 unit = timestamp 且 value 為 10 位數
      Given Limit 物件 limit_type 為 "assigned"
      And limit_value 為 1800000000
      And limit_unit 為 "timestamp"
      When 呼叫 get_limit_label()
      Then type 為 "指定日期"
      And value 為 wp_date("Y-m-d H:i", 1800000000) 的結果

    Example: limit_type = assigned 但 value 非 10 位數 timestamp
      Given Limit 物件 limit_type 為 "assigned"
      And limit_value 為 123
      And limit_unit 為 "timestamp"
      When 呼叫 get_limit_label()
      Then type 為 "指定日期"
      And value 為空字串 ""

    Example: limit_type = unlimited 時 value 強制為空字串
      Given Limit 物件 limit_type 為 "unlimited"
      And limit_value 為 null
      When 呼叫 get_limit_label()
      Then type 為 "無限制"
      And value 為空字串 ""

    Example: limit_type = follow_subscription 時 value 強制為空字串
      Given Limit 物件 limit_type 為 "follow_subscription"
      And limit_value 為 30
      And limit_unit 為 "day"
      When 呼叫 get_limit_label()
      Then type 為 "跟隨訂閱"
      And value 為空字串 "" （即使 limit_value 有值也會被強制清空）

  # ============================================================
  # BoundItemsData - 商品綁定項目集合
  # ============================================================
  # 用於商品身上 post meta ({meta_key}) 儲存的「綁定項目 + 限制」集合。
  # 建構時從 post meta 讀入陣列，每項轉為 BoundItemData 物件。
  # 內部以 $bound_items_data（array of BoundItemData）維護狀態，
  # save() 時再寫回 post meta，並額外將 ids 個別寫到 {meta_key}_ids。
  # ============================================================

  Rule: BoundItemsData::add_item_data 新增項目到集合

    Example: 集合中尚未存在該項目時新增
      Given BoundItemsData 內部 bound_items_data 為空陣列
      And Limit(limit_type="fixed", limit_value=30, limit_unit="day") 為 $limit
      When 呼叫 add_item_data(100, $limit)
      Then bound_items_data 新增一筆 BoundItemData(id=100, limit_type="fixed", limit_value=30, limit_unit="day")
      And get_ids() 回傳 [100]
      And 回傳 self（可鏈式呼叫）

    Example: 集合中已存在該項目時先移除舊資料再新增（非 docstring 描述的「跳過不動」）
      Given BoundItemsData 內部已有 BoundItemData(id=100, limit_type="unlimited")
      And Limit(limit_type="fixed", limit_value=7, limit_unit="day") 為 $limit
      When 呼叫 add_item_data(100, $limit)
      Then 舊的 BoundItemData(id=100) 被 remove_item_data 移除
      And 新的 BoundItemData(id=100, limit_type="fixed", limit_value=7, limit_unit="day") 被 append 到陣列末端
      And get_ids() 回傳 [100]

    Example: 多次 add 不同 id 會持續累積
      Given BoundItemsData 為空
      When 依序呼叫 add_item_data(100, $limit1)、add_item_data(200, $limit2)
      Then get_ids() 回傳 [100, 200]

  Rule: BoundItemsData::update_item_data 更新集合中項目

    Example: 更新已存在項目會以新資料取代
      Given BoundItemsData 內部已有 BoundItemData(id=100, limit_type="unlimited")
      And Limit(limit_type="fixed", limit_value=14, limit_unit="day") 為 $limit
      When 呼叫 update_item_data(100, $limit)
      Then 先執行 remove_item_data(100) 移除舊資料
      And 再 append BoundItemData(id=100, limit_type="fixed", limit_value=14, limit_unit="day")
      And get_ids() 回傳 [100]
      And 回傳 self（可鏈式呼叫）

    Example: 更新不存在項目等同新增（因內部先 remove 再 append）
      Given BoundItemsData 為空陣列
      When 呼叫 update_item_data(999, $limit)
      Then remove_item_data(999) 不影響任何資料
      And 新的 BoundItemData(id=999) 被 append
      And get_ids() 回傳 [999]

  Rule: BoundItemsData::remove_item_data 從集合移除項目

    Example: 移除存在的項目
      Given BoundItemsData 內部有 BoundItemData(id=100) 與 BoundItemData(id=200)
      When 呼叫 remove_item_data(100)
      Then bound_items_data 僅保留 BoundItemData(id=200)
      And get_ids() 回傳 [200]
      And 回傳 self（可鏈式呼叫）

    Example: 移除不存在的項目不會報錯
      Given BoundItemsData 內部僅有 BoundItemData(id=100)
      When 呼叫 remove_item_data(999)
      Then bound_items_data 維持僅包含 BoundItemData(id=100)
      And get_ids() 回傳 [100]

    Example: 從空集合移除
      Given BoundItemsData 為空陣列
      When 呼叫 remove_item_data(100)
      Then bound_items_data 維持為空陣列

  Rule: BoundItemsData::included 判斷項目是否在集合中

    Example: 項目存在回傳 true
      Given BoundItemsData 內部有 BoundItemData(id=100)
      When 呼叫 included(100)
      Then 回傳 true

    Example: 項目不存在回傳 false
      Given BoundItemsData 內部有 BoundItemData(id=100)
      When 呼叫 included(200)
      Then 回傳 false

    Example: 空集合永遠回傳 false
      Given BoundItemsData 為空陣列
      When 呼叫 included(100)
      Then 回傳 false

  Rule: BoundItemsData::get_data 以 OBJECT 或 ARRAY_N 輸出集合

    Example: 預設 output = OBJECT 時回傳 BoundItemData 物件陣列
      Given BoundItemsData 內部有 BoundItemData(id=100) 與 BoundItemData(id=200)
      When 呼叫 get_data()
      Then 回傳陣列長度為 2
      And 每個元素皆為 BoundItemData 實例

    Example: output = ARRAY_N 時回傳 associative array 陣列
      Given BoundItemsData 內部有 BoundItemData(id=100, limit_type="fixed", limit_value=30, limit_unit="day")
      When 呼叫 get_data(ARRAY_N)
      Then 回傳陣列長度為 1
      And 第一筆為 BoundItemData::to_array() 的結果
      And 該 array 包含 keys "id"、"name"、"limit_type"、"limit_value"、"limit_unit"

    Example: 空集合回傳空陣列
      Given BoundItemsData 為空
      When 呼叫 get_data()
      Then 回傳空陣列 []

  Rule: BoundItemsData::get_ids 取得集合中所有項目 ID

    Example: 回傳所有 id 陣列（透過 wp_list_pluck 從 get_data() 擷取）
      Given BoundItemsData 內部有 BoundItemData(id=100) 與 BoundItemData(id=200)
      When 呼叫 get_ids()
      Then 回傳陣列 [100, 200]

    Example: 空集合回傳空陣列
      Given BoundItemsData 為空
      When 呼叫 get_ids()
      Then 回傳空陣列 []

  # ============================================================
  # ExpireDate - set_is_expired 補充規則
  # ============================================================
  # set_is_expired 在 __construct 末尾被呼叫，根據 is_subscription 與 expire_date
  # 判斷是否過期，並順便設定 timestamp。
  # ============================================================

  Rule: ExpireDate::set_is_expired 依目前時間判斷是否過期

    Example: 非訂閱且 expire_date 為數字 0 時不過期
      Given is_subscription 為 false
      And expire_date 為整數 0
      When 呼叫 set_is_expired()
      Then timestamp 設為 0
      And is_expired 為 false

    Example: 非訂閱且 expire_date 為未來 timestamp 時未過期
      Given is_subscription 為 false
      And expire_date 為一個大於 time() 的整數
      When 呼叫 set_is_expired()
      Then timestamp 設為該整數
      And is_expired 為 false

    Example: 非訂閱且 expire_date 為過去 timestamp 時已過期
      Given is_subscription 為 false
      And expire_date 為一個小於 time() 的整數
      When 呼叫 set_is_expired()
      Then timestamp 設為該整數
      And is_expired 為 true

    Example: 非訂閱且 expire_date 為數字字串（is_numeric 為 true）時正常解析
      Given is_subscription 為 false
      And expire_date 為字串 "1900000000"
      When 呼叫 set_is_expired()
      Then timestamp 設為 1900000000
      And is_expired 為 (1900000000 < time())

    Example: 非訂閱且 expire_date 為非數字字串時立即過期
      Given is_subscription 為 false
      And expire_date 為字串 "not-a-number"
      When 呼叫 set_is_expired()
      Then timestamp 維持 null
      And is_expired 為 true

    Example: 訂閱情境且 wcs_get_subscription 回傳 false 時過期
      Given is_subscription 為 true
      And subscription_id 為 999
      And wcs_get_subscription(999) 回傳 false
      When 呼叫 set_is_expired()
      Then is_expired 為 true

    Example: 訂閱情境且訂閱狀態為 active 時未過期
      Given is_subscription 為 true
      And subscription_id 為 123
      And wcs_get_subscription(123) 回傳一個 has_status("active") 為 true 的訂閱
      When 呼叫 set_is_expired()
      Then is_expired 為 false

    Example: 訂閱情境且訂閱狀態非 active 時過期
      Given is_subscription 為 true
      And subscription_id 為 456
      And wcs_get_subscription(456) 回傳 has_status("active") 為 false 的訂閱
      When 呼叫 set_is_expired()
      Then is_expired 為 true
