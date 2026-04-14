@ignore @policy
Feature: 付款重試規則

  Background:
    Given Powerhouse 外掛已啟用
    And WooCommerce Subscriptions 已啟用

  Rule: 後置（狀態）- 覆寫 WooCommerce 預設重試規則為 3 次，每次間隔 1 小時

    Example: 重試規則被設定為 3 次
      Given WooCommerce Subscriptions 提供預設的 5 次重試規則（共 7 天）
      When Powerhouse 透過 wcs_default_retry_rules filter 覆寫規則
      Then 重試規則總共 3 次

    Example: 每次重試間隔為 1 小時
      When Powerhouse 透過 wcs_default_retry_rules filter 覆寫規則
      Then 每次重試的 retry_after_interval 為 3600 秒（HOUR_IN_SECONDS）

    Example: 重試期間不通知客戶
      When Powerhouse 透過 wcs_default_retry_rules filter 覆寫規則
      Then 每次重試的 email_template_customer 為空字串（不發送客戶通知信）

    Example: 重試期間通知管理員
      When Powerhouse 透過 wcs_default_retry_rules filter 覆寫規則
      Then 每次重試的 email_template_admin 為 "WCS_Email_Payment_Retry"

    Example: 重試期間訂單狀態設為 pending
      When Powerhouse 透過 wcs_default_retry_rules filter 覆寫規則
      Then 每次重試的 status_to_apply_to_order 為 "pending"

    Example: 重試期間訂閱狀態設為 on-hold
      When Powerhouse 透過 wcs_default_retry_rules filter 覆寫規則
      Then 每次重試的 status_to_apply_to_subscription 為 "on-hold"

  Rule: 後置（狀態）- 超過重試上限後訂閱轉為取消狀態

    Example: 3 次重試皆失敗後訂閱被取消
      Given WooCommerce Subscriptions 預設超過重試上限後訂閱停在保留狀態
      When Powerhouse 透過 woocommerce_subscription_max_failed_payments_exceeded filter 回傳 true
      Then 訂閱從 on-hold 轉為 cancelled 狀態（而非停留在 on-hold）

  Rule: 後置（狀態）- 完整重試時間軸

    Example: 3 小時內完成所有重試
      Given 訂閱付款失敗，進入重試流程
      When 系統依序執行 3 次重試
      Then 第 1 次重試在付款失敗後 1 小時執行
      And 第 2 次重試在第 1 次重試後 1 小時執行
      And 第 3 次重試在第 2 次重試後 1 小時執行
      And 若第 3 次仍失敗，訂閱轉為 cancelled

  Rule: RetryPayment::set_retry_rule 方法實作細節

    描述 Domains/Subscription/Core/RetryPayment.php 的 set_retry_rule() method。
    該 method 掛載於 wcs_default_retry_rules filter，完全覆寫 WooCommerce Subscriptions
    預設的重試規則陣列，並忽略 filter 原本帶入的參數。

    Example: set_retry_rule 接受 array 參數並回傳 array
      Given RetryPayment::__construct 已將 set_retry_rule 註冊到 wcs_default_retry_rules filter
      And WooCommerce Subscriptions 以原始預設規則陣列呼叫 filter
      When set_retry_rule($retry_rules) 被呼叫
      Then 輸入參數 $retry_rules 型別為 array<array<string, mixed>>
      And 回傳型別為 array<array<string, mixed>>

    Example: set_retry_rule 完全忽略輸入參數
      Given $retry_rules 為任意 WooCommerce Subscriptions 預設規則陣列
      When set_retry_rule($retry_rules) 被呼叫
      Then 方法內部不讀取 $retry_rules 的任何值
      And 直接建立全新的 $new_retry_rules 陣列並回傳
      # 預設規則完全被捨棄

    Example: set_retry_rule 回傳的陣列長度為 3
      When set_retry_rule($retry_rules) 被呼叫
      Then 回傳陣列元素數量為 3
      # 代表共 3 次重試機會

    Example: set_retry_rule 每個 rule 的欄位完全一致
      When set_retry_rule($retry_rules) 被呼叫
      Then 每個 rule 皆包含以下 5 個 key：
        | key                              | value                    |
        | retry_after_interval             | HOUR_IN_SECONDS (3600)   |
        | email_template_customer          | ""（空字串）             |
        | email_template_admin             | "WCS_Email_Payment_Retry"|
        | status_to_apply_to_order         | "pending"                |
        | status_to_apply_to_subscription  | "on-hold"                |
      And 三個 rule 的欄位值完全相同（無漸進式延長間隔）

    Example: set_retry_rule 使用 WordPress 常量 HOUR_IN_SECONDS
      Given WordPress 已定義全域常量 HOUR_IN_SECONDS = 3600
      When set_retry_rule($retry_rules) 被呼叫
      Then 每個 rule 的 retry_after_interval 取值為 \HOUR_IN_SECONDS
      # 透過 namespace escape (\HOUR_IN_SECONDS) 存取全域常量

    Example: RetryPayment 的 constructor 同時註冊兩個 filter
      Given RetryPayment 類別使用 SingletonTrait
      When RetryPayment::instance() 被呼叫建立實例
      Then 建構子執行 add_filter('woocommerce_subscription_max_failed_payments_exceeded', '__return_true', 100, 2)
      And 建構子執行 add_filter('wcs_default_retry_rules', [$this, 'set_retry_rule'])
      # set_retry_rule 使用 filter 預設的 priority 10 與 accepted_args 1
