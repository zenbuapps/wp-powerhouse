@ignore @event
Feature: 監聽結束時間變化

  Background:
    Given Powerhouse 外掛已啟用
    And WooCommerce Subscriptions 已啟用

  Rule: 後置（狀態）- 結束時間被更新時觸發 powerhouse_subscription_at_watch_end action

    Example: 訂閱的 end 日期被更新時分發事件
      Given 一個 WC_Subscription 已建立
      When WooCommerce 觸發 woocommerce_subscription_date_updated hook
      And date_type 為 "end"
      Then 系統分發 powerhouse_subscription_at_watch_end action
      And action 第一個參數為 $subscription 物件
      And action 第二個參數包含 datetime（更新後的時間字串）

  Rule: 前置（狀態）- 其他 date_type 不觸發此事件

    Example: date_type 為 "trial_end" 時不觸發 watch_end
      Given 一個 WC_Subscription 已建立
      When WooCommerce 觸發 woocommerce_subscription_date_updated hook
      And date_type 為 "trial_end"
      Then 系統不分發 powerhouse_subscription_at_watch_end action

    Example: date_type 為 "next_payment" 時不觸發 watch_end
      Given 一個 WC_Subscription 已建立
      When WooCommerce 觸發 woocommerce_subscription_date_updated hook
      And date_type 為 "next_payment"
      Then 系統不分發 powerhouse_subscription_at_watch_end action
