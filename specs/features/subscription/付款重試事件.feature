@ignore @event
Feature: 付款重試事件

  Background:
    Given Powerhouse 外掛已啟用
    And WooCommerce Subscriptions 已啟用

  Rule: 後置（狀態）- 付款重試排程觸發時分發 powerhouse_subscription_at_payment_retry action

    Example: 付款重試時分發事件
      Given 一個 WC_Subscription 的付款失敗，進入重試排程
      And 該訂閱有關聯的 WC_Order
      When WooCommerce 排程觸發 woocommerce_scheduled_subscription_payment_retry hook
      And 傳入的 order_id 對應有效的 WC_Order
      And 該 WC_Order 關聯至少一個 WC_Subscription
      Then 系統分發 powerhouse_subscription_at_payment_retry action
      And action 第一個參數為關聯訂閱中排序最後的 $subscription 物件
      And action 第二個參數包含 order（觸發重試的 WC_Order 物件）

  Rule: 前置（狀態）- order_id 無效時不觸發

    Example: order_id 無法取得有效訂單時不觸發
      Given woocommerce_scheduled_subscription_payment_retry hook 被觸發
      And wc_get_order 回傳 false（訂單不存在）
      When 系統嘗試處理事件
      Then 系統不分發 powerhouse_subscription_at_payment_retry action

  Rule: 前置（狀態）- 訂單無關聯訂閱時不觸發

    Example: 訂單無關聯的 WC_Subscription 時不觸發
      Given woocommerce_scheduled_subscription_payment_retry hook 被觸發
      And order_id 對應有效的 WC_Order
      But wcs_get_subscriptions_for_order 回傳空陣列
      When 系統嘗試處理事件
      Then 系統不分發 powerhouse_subscription_at_payment_retry action

  Rule: 後置（狀態）- 多個關聯訂閱時使用排序最後的訂閱

    Example: 訂單關聯多個訂閱時取最後一個
      Given woocommerce_scheduled_subscription_payment_retry hook 被觸發
      And order_id 對應有效的 WC_Order
      And 該訂單關聯 2 個以上 WC_Subscription（經 ksort 排序）
      When 系統處理事件
      Then action 第一個參數為 ksort 後的最後一個 $subscription
