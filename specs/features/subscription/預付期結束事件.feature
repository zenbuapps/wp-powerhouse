@ignore @event
Feature: 預付期結束事件

  Background:
    Given Powerhouse 外掛已啟用
    And WooCommerce Subscriptions 已啟用

  Rule: 後置（狀態）- 預付期結束時觸發 powerhouse_subscription_at_end_of_prepaid_term action

    Example: 訂閱預付期結束時分發事件
      Given 一個 WC_Subscription 處於 "cancelled" 或 "pending-cancel" 狀態
      And 該訂閱設有預付期結束時間（end_of_prepaid_term）
      When WooCommerce 排程觸發 woocommerce_scheduled_subscription_end_of_prepaid_term hook
      And 傳入的 subscription_id 對應有效的 WC_Subscription
      Then 系統分發 powerhouse_subscription_at_end_of_prepaid_term action
      And action 第一個參數為 $subscription 物件
      And action 第二個參數為空陣列 []

  Rule: 前置（狀態）- subscription_id 無效時不觸發

    Example: subscription_id 無法取得有效訂閱時不觸發
      Given woocommerce_scheduled_subscription_end_of_prepaid_term hook 被觸發
      And wcs_get_subscription 回傳 false（訂閱不存在）
      When 系統嘗試處理事件
      Then 系統不分發 powerhouse_subscription_at_end_of_prepaid_term action
