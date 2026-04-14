@ignore @event
Feature: 訂閱建立事件

  Background:
    Given Powerhouse 外掛已啟用
    And WooCommerce Subscriptions 已啟用

  Rule: 後置（狀態）- 訂閱建立後觸發 powerhouse_subscription_at_date_created action

    Example: 新訂閱建立時分發事件
      Given 一個新的 WC_Subscription 已建立
      When WooCommerce 觸發 wcs_create_subscription hook
      Then 系統分發 powerhouse_subscription_at_date_created action
      And action 第一個參數為 $subscription 物件
      And action 第二個參數為空陣列 []

  Rule: 前置（狀態）- WooCommerce Subscriptions 未啟用時不註冊

    Example: WC_Subscriptions 類別不存在時不載入
      Given WooCommerce Subscriptions 未安裝
      When Powerhouse 初始化 Subscription Loader
      Then LifeCycle 不被實例化
      And 不註冊任何訂閱相關 hook
