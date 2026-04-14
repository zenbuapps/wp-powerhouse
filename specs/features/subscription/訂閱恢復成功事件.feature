@ignore @event
Feature: 訂閱恢復成功事件

  Background:
    Given Powerhouse 外掛已啟用
    And WooCommerce Subscriptions 已啟用

  Rule: 後置（狀態）- 從失敗狀態轉為 active 時觸發 powerhouse_subscription_at_subscription_success action

    Example: 訂閱從 cancelled 轉為 active 時分發事件
      Given 一個 WC_Subscription 目前狀態為 "cancelled"
      When WooCommerce 觸發 woocommerce_subscription_pre_update_status hook
      And 新狀態為 "active"
      Then 系統分發 powerhouse_subscription_at_subscription_success action
      And action 第一個參數為 $subscription 物件
      And action 第二個參數包含 from_status（Status::CANCELLED）和 to_status（Status::ACTIVE）

    Example: 訂閱從 expired 轉為 active 時分發事件
      Given 一個 WC_Subscription 目前狀態為 "expired"
      When WooCommerce 觸發 woocommerce_subscription_pre_update_status hook
      And 新狀態為 "active"
      Then 系統分發 powerhouse_subscription_at_subscription_success action
      And action 第二個參數包含 from_status（Status::EXPIRED）和 to_status（Status::ACTIVE）

  Rule: 前置（狀態）- 從非失敗狀態轉變時不觸發

    Example: 訂閱從 on-hold 轉為 active 時不觸發
      Given 一個 WC_Subscription 目前狀態為 "on-hold"
      When WooCommerce 觸發 woocommerce_subscription_pre_update_status hook
      And 新狀態為 "active"
      Then 系統不分發 powerhouse_subscription_at_subscription_success action

    Example: 訂閱從 pending-cancel 轉為 active 時不觸發
      Given 一個 WC_Subscription 目前狀態為 "pending-cancel"
      When WooCommerce 觸發 woocommerce_subscription_pre_update_status hook
      And 新狀態為 "active"
      Then 系統不分發 powerhouse_subscription_at_subscription_success action

  Rule: 前置（狀態）- 從失敗狀態轉為非 active 狀態時不觸發

    Example: 訂閱從 cancelled 轉為 on-hold 時不觸發
      Given 一個 WC_Subscription 目前狀態為 "cancelled"
      When WooCommerce 觸發 woocommerce_subscription_pre_update_status hook
      And 新狀態為 "on-hold"
      Then 系統不分發 powerhouse_subscription_at_subscription_success action

    Example: 訂閱從 expired 轉為 pending-cancel 時不觸發
      Given 一個 WC_Subscription 目前狀態為 "expired"
      When WooCommerce 觸發 woocommerce_subscription_pre_update_status hook
      And 新狀態為 "pending-cancel"
      Then 系統不分發 powerhouse_subscription_at_subscription_success action

  Rule: 前置（狀態）- 非 WC_Subscription 物件時不處理

    Example: 傳入非 WC_Subscription 物件時靜默忽略
      Given woocommerce_subscription_pre_update_status hook 被觸發
      And 第三個參數不是 WC_Subscription 實例
      When LifeCycle::subscription_success 被呼叫
      Then 方法直接 return，不分發任何 action

  Rule: 前置（狀態）- 無效狀態值時不處理

    Example: 狀態值無法解析為 Status enum 時靜默忽略
      Given woocommerce_subscription_pre_update_status hook 被觸發
      And from_status 或 to_status 不是有效的 Status enum 值
      When LifeCycle::subscription_success 被呼叫
      Then 方法直接 return，不分發任何 action
