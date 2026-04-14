@ignore @system-behavior
Feature: BoundItemData 授權原語

  # BoundItemData 是「商品 ↔ 授權項目」綁定資料的單一項目模型，
  # 繼承自 Limit，額外攜帶 id 與 name。
  # 下游外掛（例如 PowerCourse 在訂單完成時）會從商品的 BoundItemsData 取出每個 BoundItemData，
  # 呼叫 ->grant_user($user_id, $order, $meta_key) 來授權用戶存取該項目。
  # 本 feature 描述 BoundItemData 的授權原語行為（含 expire_date 計算與 subscription 綁定）。

  Background:
    Given Powerhouse 外掛已啟用
    And ph_access_itemmeta 資料表已建立

  Rule: 前置（資料）- BoundItemData 建構時從 item_id 取得 post title

    Example: 建構 BoundItemData 會填入 name
      Given 項目 ID 200 的 post_title 為 "範例課程"
      When 建立 new BoundItemData(200, 'unlimited', 0, 'timestamp')
      Then 物件的 id 為 200
      And 物件的 name 為 "範例課程"
      And 物件的 limit_type 為 "unlimited"

  Rule: 後置（狀態）- grant_user 成功時寫入 ph_access_itemmeta 並分發 grant_user_success

    Example: unlimited 授權寫入 meta_value=0
      Given BoundItemData($item_id=200, limit_type='unlimited', limit_value=0, limit_unit='timestamp')
      When 呼叫 $bound_item_data->grant_user($user_id=50, $order=null, $meta_key='expire_date')
      Then MetaCRUD::update(200, 50, 'expire_date', 0) 被呼叫
      And ph_access_itemmeta 中存在：
        | post_id | user_id | meta_key    | meta_value |
        | 200     | 50      | expire_date | 0          |
      And powerhouse/limit/grant_user_success action 被觸發
      And action 參數依序為 ($user_id=50, $order=null, $bound_item_data, $meta_key='expire_date')

    Example: assigned 授權使用 limit_value 作為 timestamp
      Given BoundItemData($item_id=200, limit_type='assigned', limit_value=1800000000, limit_unit='timestamp')
      When 呼叫 $bound_item_data->grant_user(50, null)
      Then MetaCRUD::update 的 $meta_value 參數為 1800000000

    Example: fixed 授權從現在時間往後加上 limit_value limit_unit
      Given BoundItemData($item_id=200, limit_type='fixed', limit_value=30, limit_unit='day')
      And 當下時間為任意時刻
      When 呼叫 $bound_item_data->grant_user(50, null)
      Then MetaCRUD::update 的 $meta_value 為 "+30 day" 之後那一天的 15:59:00 對應的 timestamp

    Example: fixed 授權固定時間點為當天 15:59:00
      Given BoundItemData($item_id=200, limit_type='fixed', limit_value=1, limit_unit='month')
      When 呼叫 $bound_item_data->grant_user(50, null)
      Then 寫入的 timestamp 對應日期格式為 "Y-m-d 15:59:00"

    Example: 可指定自訂 meta_key（非預設 expire_date）
      Given BoundItemData($item_id=200, limit_type='unlimited', limit_value=0, limit_unit='timestamp')
      When 呼叫 $bound_item_data->grant_user(50, null, 'custom_access_key')
      Then MetaCRUD::update(200, 50, 'custom_access_key', 0) 被呼叫
      And powerhouse/limit/grant_user_success action 第四個參數為 "custom_access_key"

  Rule: 後置（狀態）- grant_user 在 follow_subscription 模式下解析出唯一訂閱 ID

    Example: follow_subscription + 訂單有唯一訂閱 → expire_date 為 "subscription_{id}"
      Given BoundItemData($item_id=200, limit_type='follow_subscription', limit_value=0, limit_unit='timestamp')
      And WC_Order ID 500 透過 wcs_get_subscriptions_for_order 取得唯一一個訂閱，ID 為 123
      When 呼叫 $bound_item_data->grant_user(50, $order)
      Then MetaCRUD::update 的 $meta_value 為 "subscription_123"

    Example: follow_subscription 但訂單沒有訂閱 → expire_date 回退為 0
      Given BoundItemData($item_id=200, limit_type='follow_subscription', limit_value=0, limit_unit='timestamp')
      And WC_Order ID 500 沒有任何對應的 WC_Subscription
      When 呼叫 $bound_item_data->grant_user(50, $order)
      Then 寫入的 expire_date 為 0

    Example: follow_subscription 但訂單有多個訂閱 → expire_date 回退為 0
      Given BoundItemData($item_id=200, limit_type='follow_subscription', limit_value=0, limit_unit='timestamp')
      And WC_Order ID 500 取得的訂閱陣列長度 > 1
      When 呼叫 $bound_item_data->grant_user(50, $order)
      Then 寫入的 expire_date 為 0

    Example: follow_subscription 但沒傳入 $order → expire_date 回退為 0
      Given BoundItemData($item_id=200, limit_type='follow_subscription', limit_value=0, limit_unit='timestamp')
      When 呼叫 $bound_item_data->grant_user(50, null)
      Then 寫入的 expire_date 為 0

    Example: follow_subscription 但 WC_Subscription class 不存在 → 記錄 log 並回退為 0
      Given WooCommerce Subscriptions 外掛未啟用
      And BoundItemData($item_id=200, limit_type='follow_subscription', limit_value=0, limit_unit='timestamp')
      When 呼叫 $bound_item_data->grant_user(50, $order)
      Then 系統記錄 log 包含 "的 expire_date 計算失敗，因為 WC_Subscription 不存在"
      And 寫入的 expire_date 為 0

  Rule: 前置（錯誤）- grant_user 失敗時分發 grant_user_failed 並拋出例外

    Example: MetaCRUD::update 回傳 false 時拋出例外
      Given MetaCRUD::update 會回傳 false
      And BoundItemData($item_id=200, limit_type='unlimited', limit_value=0, limit_unit='timestamp')
      When 呼叫 $bound_item_data->grant_user(50, null)
      Then powerhouse/limit/grant_user_failed action 被觸發
      And 拋出 Exception
      And 例外訊息包含 "Grant user access failed"
      And 例外訊息包含 "item id: 200"
      And 例外訊息包含 "user id: #50"
      And 例外訊息包含 "meta_key: expire_date"

    Example: 有 order 時例外訊息包含 order_id
      Given MetaCRUD::update 會回傳 false
      And WC_Order ID 為 500
      When 呼叫 $bound_item_data->grant_user(50, $order)
      Then 例外訊息包含 "order id: #500"

    Example: 無 order 時例外訊息 order id 段落為空
      Given MetaCRUD::update 會回傳 false
      When 呼叫 $bound_item_data->grant_user(50, null)
      Then 例外訊息的 "order id:" 段落後為空字串

  Rule: 後置（狀態）- revoke_user 成功時刪除 ph_access_itemmeta 並分發 revoke_user_success

    Example: revoke_user 刪除對應的 meta 記錄
      Given ph_access_itemmeta 已存在 post_id=200, user_id=50, meta_key='expire_date' 的記錄
      And BoundItemData($item_id=200, limit_type='unlimited', limit_value=0, limit_unit='timestamp')
      When 呼叫 $bound_item_data->revoke_user(50, null, 'expire_date')
      Then MetaCRUD::delete(200, 50, 'expire_date') 被呼叫
      And ph_access_itemmeta 中該記錄已被刪除
      And powerhouse/limit/revoke_user_success action 被觸發
      And action 參數依序為 ($user_id=50, $order=null, $bound_item_data, $meta_key='expire_date')

    Example: 可指定自訂 meta_key 的 revoke
      Given ph_access_itemmeta 已存在 meta_key='custom_access_key' 的記錄
      When 呼叫 $bound_item_data->revoke_user(50, null, 'custom_access_key')
      Then MetaCRUD::delete(200, 50, 'custom_access_key') 被呼叫

  Rule: 前置（錯誤）- revoke_user 失敗時分發 revoke_user_failed 並拋出例外

    Example: MetaCRUD::delete 回傳 false 時拋出例外
      Given MetaCRUD::delete 會回傳 false
      And BoundItemData($item_id=200, ...)
      When 呼叫 $bound_item_data->revoke_user(50, null)
      Then powerhouse/limit/revoke_user_failed action 被觸發
      And 拋出 Exception
      And 例外訊息包含 "Revoke user access failed"
      And 例外訊息包含 "item id: 200"
      And 例外訊息包含 "user id: #50"
      And 例外訊息包含 "meta_key: expire_date"

  Rule: 後置（狀態）- to_array 序列化

    Example: to_array 回傳固定欄位結構
      Given BoundItemData(200, 'fixed', 30, 'day')
      And 項目 200 的 post_title 為 "範例課程"
      When 呼叫 ->to_array()
      Then 回傳陣列為：
        | key         | value    |
        | id          | 200      |
        | name        | 範例課程 |
        | limit_type  | fixed    |
        | limit_value | 30       |
        | limit_unit  | day      |
