@ignore @event
Feature: 訂閱失敗事件

  Background:
    Given Powerhouse 外掛已啟用
    And WooCommerce Subscriptions 已啟用

  Rule: 後置（狀態）- 從非失敗狀態轉為失敗狀態時觸發 powerhouse_subscription_at_subscription_failed action

    Example: 訂閱從 active 轉為 cancelled 時分發事件
      Given 一個 WC_Subscription 目前狀態為 "active"
      When WooCommerce 觸發 woocommerce_subscription_pre_update_status hook
      And 新狀態為 "cancelled"
      Then 系統分發 powerhouse_subscription_at_subscription_failed action
      And action 第一個參數為 $subscription 物件
      And action 第二個參數包含 from_status（Status::ACTIVE）和 to_status（Status::CANCELLED）

    Example: 訂閱從 active 轉為 expired 時分發事件
      Given 一個 WC_Subscription 目前狀態為 "active"
      When WooCommerce 觸發 woocommerce_subscription_pre_update_status hook
      And 新狀態為 "expired"
      Then 系統分發 powerhouse_subscription_at_subscription_failed action
      And action 第二個參數包含 from_status（Status::ACTIVE）和 to_status（Status::EXPIRED）

    Example: 訂閱從 on-hold 轉為 cancelled 時分發事件
      Given 一個 WC_Subscription 目前狀態為 "on-hold"
      When WooCommerce 觸發 woocommerce_subscription_pre_update_status hook
      And 新狀態為 "cancelled"
      Then 系統分發 powerhouse_subscription_at_subscription_failed action

    Example: 訂閱從 pending-cancel 轉為 expired 時分發事件
      Given 一個 WC_Subscription 目前狀態為 "pending-cancel"
      When WooCommerce 觸發 woocommerce_subscription_pre_update_status hook
      And 新狀態為 "expired"
      Then 系統分發 powerhouse_subscription_at_subscription_failed action

  Rule: 前置（狀態）- 原本已是失敗狀態時不觸發

    Example: 訂閱從 cancelled 轉為 expired 時不觸發
      Given 一個 WC_Subscription 目前狀態為 "cancelled"
      When WooCommerce 觸發 woocommerce_subscription_pre_update_status hook
      And 新狀態為 "expired"
      Then 系統不分發 powerhouse_subscription_at_subscription_failed action

    Example: 訂閱從 expired 轉為 cancelled 時不觸發
      Given 一個 WC_Subscription 目前狀態為 "expired"
      When WooCommerce 觸發 woocommerce_subscription_pre_update_status hook
      And 新狀態為 "cancelled"
      Then 系統不分發 powerhouse_subscription_at_subscription_failed action

  Rule: 前置（狀態）- 轉為非失敗狀態時不觸發

    Example: 訂閱從 active 轉為 on-hold 時不觸發
      Given 一個 WC_Subscription 目前狀態為 "active"
      When WooCommerce 觸發 woocommerce_subscription_pre_update_status hook
      And 新狀態為 "on-hold"
      Then 系統不分發 powerhouse_subscription_at_subscription_failed action

    Example: 訂閱從 active 轉為 pending-cancel 時不觸發
      Given 一個 WC_Subscription 目前狀態為 "active"
      When WooCommerce 觸發 woocommerce_subscription_pre_update_status hook
      And 新狀態為 "pending-cancel"
      Then 系統不分發 powerhouse_subscription_at_subscription_failed action

  Rule: 前置（狀態）- 非 WC_Subscription 物件時不處理

    Example: 傳入非 WC_Subscription 物件時靜默忽略
      Given woocommerce_subscription_pre_update_status hook 被觸發
      And 第三個參數不是 WC_Subscription 實例
      When LifeCycle::subscription_failed 被呼叫
      Then 方法直接 return，不分發任何 action

  Rule: 前置（狀態）- 無效狀態值時不處理

    Example: 狀態值無法解析為 Status enum 時靜默忽略
      Given woocommerce_subscription_pre_update_status hook 被觸發
      And from_status 或 to_status 不是有效的 Status enum 值
      When LifeCycle::subscription_failed 被呼叫
      Then 方法直接 return，不分發任何 action

  Rule: Status::is_failed 判斷訂閱狀態是否為失敗

    描述 Domains/Subscription/Shared/Enums/Status.php 的 is_failed() instance method。
    此方法是 LifeCycle::subscription_failed 決定是否分發事件的核心判斷依據 —
    「從非失敗狀態轉為失敗狀態」才算真正的訂閱失敗。
    判斷邏輯：只有 CANCELLED 與 EXPIRED 被視為失敗；ON_HOLD 代表「正在嘗試付款」不算失敗；
    PENDING_CANCEL 代表「待取消」也不算失敗。

    Example: is_failed 回傳型別為 bool
      Given 任意 Status enum case
      When 呼叫 $status->is_failed()
      Then 回傳型別為 bool
      # PHPDoc 註記 @return bool

    Example: is_failed 實作使用 in_array 嚴格比對
      Given Status enum case
      When is_failed() 被呼叫
      Then 內部使用 in_array($this, [CANCELLED, EXPIRED], true) 判斷
      # 第三參數 true 代表嚴格比對（type + value）

    Example: ACTIVE 狀態不算失敗
      Given Status enum case 為 ACTIVE
      When is_failed() 被呼叫
      Then 回傳 false

    Example: ON_HOLD 狀態不算失敗
      Given Status enum case 為 ON_HOLD
      When is_failed() 被呼叫
      Then 回傳 false
      # ON_HOLD 代表「保留，正在嘗試付款中」

    Example: PENDING_CANCEL 狀態不算失敗
      Given Status enum case 為 PENDING_CANCEL
      When is_failed() 被呼叫
      Then 回傳 false
      # PENDING_CANCEL 代表「待取消」，使用者已選擇取消但尚未生效

    Example: CANCELLED 狀態為失敗
      Given Status enum case 為 CANCELLED
      When is_failed() 被呼叫
      Then 回傳 true

    Example: EXPIRED 狀態為失敗
      Given Status enum case 為 EXPIRED
      When is_failed() 被呼叫
      Then 回傳 true

    Example: is_failed 被 LifeCycle::subscription_failed 用於判斷「從非失敗轉為失敗」
      Given LifeCycle::subscription_failed 接收到狀態變化事件
      When 判斷是否分發 powerhouse_subscription_at_subscription_failed action
      Then 條件為 from_status->is_failed() === false 且 to_status->is_failed() === true
      # 從非失敗轉為失敗才分發事件，避免重複觸發
