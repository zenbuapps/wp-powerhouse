@ignore @event
Feature: 續訂訂單建立事件

  Background:
    Given Powerhouse 外掛已啟用
    And WooCommerce Subscriptions 已啟用

  Rule: 後置（狀態）- 續訂訂單建立後觸發 powerhouse_subscription_at_renewal_order_created action

    Example: 訂閱續訂訂單建立時分發事件
      Given 一個 WC_Subscription 已建立且正在續訂
      When WooCommerce 觸發 wcs_renewal_order_created filter
      And 傳入 $renewal_order（WC_Order）和 $subscription（WC_Subscription 或 int）
      Then 系統分發 powerhouse_subscription_at_renewal_order_created action
      And action 第一個參數為 $subscription 物件
      And action 第二個參數包含 renewal_order（WC_Order 物件）
      And filter 回傳原始的 $renewal_order 物件（不修改）

  Rule: 後置（狀態）- 不影響原始續訂訂單

    Example: 事件分發後仍回傳原始續訂訂單
      Given wcs_renewal_order_created filter 被觸發
      When LifeCycle::renewal_order_created 處理完畢
      Then 回傳值為傳入的 $renewal_order 物件（原封不動）
