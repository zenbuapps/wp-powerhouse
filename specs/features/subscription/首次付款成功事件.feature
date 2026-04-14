@ignore @event
Feature: 首次付款成功事件

  Background:
    Given Powerhouse 外掛已啟用
    And WooCommerce Subscriptions 已啟用

  Rule: 後置（狀態）- 僅在首次付款時觸發 powerhouse_subscription_at_initial_payment_complete action

    Example: 訂閱首次付款成功時分發事件
      Given 一個 WC_Subscription 已建立
      And 該訂閱僅有一筆關聯訂單（parent order）
      And parent order ID 與唯一關聯訂單 ID 相符
      When WooCommerce 觸發 woocommerce_subscription_payment_complete hook
      Then 系統分發 powerhouse_subscription_at_initial_payment_complete action
      And action 第一個參數為 $subscription 物件
      And action 第二個參數為空陣列 []

  Rule: 前置（狀態）- 續訂付款不觸發

    Example: 訂閱已有多筆關聯訂單時不觸發
      Given 一個 WC_Subscription 已建立
      And 該訂閱有 2 筆以上關聯訂單（含續訂訂單）
      When WooCommerce 觸發 woocommerce_subscription_payment_complete hook
      Then 系統不分發 powerhouse_subscription_at_initial_payment_complete action

  Rule: 前置（狀態）- parent order 不存在時不觸發

    Example: 訂閱無 parent order 時不觸發
      Given 一個 WC_Subscription 已建立
      And 該訂閱的 parent order 不存在（get_parent 回傳 false）
      When WooCommerce 觸發 woocommerce_subscription_payment_complete hook
      Then 系統不分發 powerhouse_subscription_at_initial_payment_complete action

  Rule: 前置（狀態）- 唯一訂單 ID 與 parent order ID 不符時不觸發

    Example: 關聯訂單 ID 與 parent order ID 不一致
      Given 一個 WC_Subscription 已建立
      And 該訂閱僅有一筆關聯訂單
      But 該關聯訂單 ID 與 parent order ID 不相符
      When WooCommerce 觸發 woocommerce_subscription_payment_complete hook
      Then 系統不分發 powerhouse_subscription_at_initial_payment_complete action
