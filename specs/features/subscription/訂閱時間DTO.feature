@ignore @model
Feature: 訂閱時間 DTO

  描述 Subscription Domain 內部的 Times DTO（Domains/Subscription/DTOs/Times.php）。
  此 DTO 將 WC_Subscription 的多個時間點整合為單一物件，方便序列化與傳遞。
  每個欄位為 timestamp（int），0 表示該時間點未設定（WC_Subscription::get_time() 的行為）。

  Background:
    Given Powerhouse 外掛已啟用
    And WooCommerce Subscriptions plugin 已啟用

  Rule: Times DTO 包含 5 個訂閱時間戳記欄位

    Example: 欄位定義
      Given Times DTO 類別繼承 J7\WpUtils\Classes\DTO
      Then 定義 5 個 public int 屬性：
        | 屬性名稱                | 對應 WC_Subscription 時間點   |
        | trial_end               | trial_end                     |
        | next_payment            | next_payment                  |
        | last_order_date_created | last_order_date_created       |
        | end                     | end                           |
        | end_of_prepaid_term     | end_of_prepaid_term           |

  Rule: instance static factory 從 WC_Subscription 建立 DTO

    Example: 從訂閱物件讀取所有時間點
      Given WC_Subscription 物件 $subscription
      And $subscription->get_time("trial_end") 回傳 1700000000
      And $subscription->get_time("next_payment") 回傳 1702000000
      And $subscription->get_time("last_order_date_created") 回傳 1699000000
      And $subscription->get_time("end") 回傳 1710000000
      And $subscription->get_time("end_of_prepaid_term") 回傳 1711000000
      When 呼叫 Times::instance($subscription)
      Then 回傳 Times 實例
      And trial_end 為 1700000000
      And next_payment 為 1702000000
      And last_order_date_created 為 1699000000
      And end 為 1710000000
      And end_of_prepaid_term 為 1711000000

    Example: 訂閱時間點未設定時為 0
      Given $subscription->get_time("trial_end") 回傳 0
      When 呼叫 Times::instance($subscription)
      Then trial_end 為 0

  Rule: 時間點 key 使用 Action enum 的 value

    Example: trial_end / next_payment / end / end_of_prepaid_term 取自 Action enum
      Given Action enum 定義於 Domains/Subscription/Shared/Enums/Action.php
      Then Times::instance 使用 Action::TRIAL_END->value 取得 trial_end
      And 使用 Action::NEXT_PAYMENT->value 取得 next_payment
      And 使用 Action::END->value 取得 end
      And 使用 Action::END_OF_PREPAID_TERM->value 取得 end_of_prepaid_term

    Example: last_order_date_created 為字串常量非 Action enum
      Given last_order_date_created 不在 Action enum 定義中
      Then Times::instance 直接使用字串 "last_order_date_created"

  Rule: Times 可序列化為 array（繼承自 DTO 基底）

    Example: to_array 輸出 5 個欄位
      Given 已建立 Times 實例
      When 呼叫 to_array()
      Then 回傳陣列包含 keys "trial_end"、"next_payment"、"last_order_date_created"、"end"、"end_of_prepaid_term"
      And 各值為 int 型別

  Rule: Action::get_action_hook 將 enum value 轉換為 Powerhouse 自訂 hook 名稱

    描述 Domains/Subscription/Shared/Enums/Action.php 的 get_action_hook() method。
    該 method 為 Action enum 的 instance method，將 case value 串接固定前綴
    "powerhouse_subscription_at_"，產生 Powerhouse 生命週期事件的 action hook 名稱。
    Times DTO 透過 Action::TRIAL_END->value 等方式間接使用這些 key。

    Example: get_action_hook 回傳型別為 non-empty-string
      Given 任意 Action enum case
      When 呼叫 $case->get_action_hook()
      Then 回傳 non-empty-string 型別的字串
      # PHPDoc 註記 @return non-empty-string

    Example: DATE_CREATED 轉換為完整 hook 名稱
      Given Action enum case 為 DATE_CREATED（value = "date_created"）
      When get_action_hook() 被呼叫
      Then 回傳 "powerhouse_subscription_at_date_created"

    Example: INITIAL_PAYMENT_COMPLETE 轉換為完整 hook 名稱
      Given Action enum case 為 INITIAL_PAYMENT_COMPLETE（value = "initial_payment_complete"）
      When get_action_hook() 被呼叫
      Then 回傳 "powerhouse_subscription_at_initial_payment_complete"

    Example: SUBSCRIPTION_FAILED 轉換為完整 hook 名稱
      Given Action enum case 為 SUBSCRIPTION_FAILED（value = "subscription_failed"）
      When get_action_hook() 被呼叫
      Then 回傳 "powerhouse_subscription_at_subscription_failed"

    Example: SUBSCRIPTION_SUCCESS 轉換為完整 hook 名稱
      Given Action enum case 為 SUBSCRIPTION_SUCCESS（value = "subscription_success"）
      When get_action_hook() 被呼叫
      Then 回傳 "powerhouse_subscription_at_subscription_success"

    Example: PAYMENT_RETRY 轉換為完整 hook 名稱
      Given Action enum case 為 PAYMENT_RETRY（value = "payment_retry"）
      When get_action_hook() 被呼叫
      Then 回傳 "powerhouse_subscription_at_payment_retry"

    Example: TRIAL_END 轉換為完整 hook 名稱
      Given Action enum case 為 TRIAL_END（value = "trial_end"）
      When get_action_hook() 被呼叫
      Then 回傳 "powerhouse_subscription_at_trial_end"

    Example: WATCH_TRIAL_END 轉換為完整 hook 名稱
      Given Action enum case 為 WATCH_TRIAL_END（value = "watch_trial_end"）
      When get_action_hook() 被呼叫
      Then 回傳 "powerhouse_subscription_at_watch_trial_end"

    Example: NEXT_PAYMENT 轉換為完整 hook 名稱
      Given Action enum case 為 NEXT_PAYMENT（value = "next_payment"）
      When get_action_hook() 被呼叫
      Then 回傳 "powerhouse_subscription_at_next_payment"

    Example: WATCH_NEXT_PAYMENT 轉換為完整 hook 名稱
      Given Action enum case 為 WATCH_NEXT_PAYMENT（value = "watch_next_payment"）
      When get_action_hook() 被呼叫
      Then 回傳 "powerhouse_subscription_at_watch_next_payment"

    Example: RENEWAL_ORDER_CREATED 轉換為完整 hook 名稱
      Given Action enum case 為 RENEWAL_ORDER_CREATED（value = "renewal_order_created"）
      When get_action_hook() 被呼叫
      Then 回傳 "powerhouse_subscription_at_renewal_order_created"

    Example: END 轉換為完整 hook 名稱
      Given Action enum case 為 END（value = "end"）
      When get_action_hook() 被呼叫
      Then 回傳 "powerhouse_subscription_at_end"

    Example: WATCH_END 轉換為完整 hook 名稱
      Given Action enum case 為 WATCH_END（value = "watch_end"）
      When get_action_hook() 被呼叫
      Then 回傳 "powerhouse_subscription_at_watch_end"

    Example: END_OF_PREPAID_TERM 轉換為完整 hook 名稱
      Given Action enum case 為 END_OF_PREPAID_TERM（value = "end_of_prepaid_term"）
      When get_action_hook() 被呼叫
      Then 回傳 "powerhouse_subscription_at_end_of_prepaid_term"

    Example: get_action_hook 使用字串串接而非 sprintf
      Given 任意 Action enum case
      When get_action_hook() 被呼叫
      Then 實作為 "'powerhouse_subscription_at_' . \$this->value"
      # 單純字串串接，不含變數插值或 sprintf

  Rule: Base::get_last_order 從 WC_Subscription 取得最後一筆訂單

    描述 Domains/Subscription/Utils/Base.php 的 get_last_order() 靜態方法。
    此方法將 WC_Subscription::get_last_order('ids') 的原始回傳值（numeric-string|false）
    包裝為 WC_Order|null 的型別安全結果，方便呼叫端直接使用 instanceof 檢查。

    Example: get_last_order 為 abstract class 的 public static method
      Given Base 類別為 abstract class
      When 外部呼叫 Base::get_last_order($subscription)
      Then 方法可被直接呼叫（static 且 public）
      # 簽名：public static function get_last_order(\WC_Subscription $subscription): \WC_Order|null

    Example: 訂閱有最後一筆訂單時回傳 WC_Order 實例
      Given WC_Subscription 物件 $subscription
      And $subscription->get_last_order('ids') 回傳 numeric-string "123"
      And wc_get_order(123) 回傳 WC_Order 實例
      When Base::get_last_order($subscription) 被呼叫
      Then 回傳該 WC_Order 實例

    Example: 訂閱無最後一筆訂單時回傳 null
      Given WC_Subscription 物件 $subscription
      And $subscription->get_last_order('ids') 回傳 false
      When Base::get_last_order($subscription) 被呼叫
      Then 回傳 null
      # !$last_order_id 為 true 時直接 return null

    Example: get_last_order 回傳 "0" 也視為無訂單
      Given $subscription->get_last_order('ids') 回傳 "0" 或 0
      When Base::get_last_order($subscription) 被呼叫
      Then 回傳 null
      # !"0" 與 !0 在 PHP 中皆為 true

    Example: wc_get_order 回傳非 WC_Order 時回傳 null
      Given $subscription->get_last_order('ids') 回傳 "456"
      And wc_get_order(456) 回傳 false（訂單不存在）
      When Base::get_last_order($subscription) 被呼叫
      Then 回傳 null
      # 透過 instanceof \WC_Order 檢查後 return null

    Example: wc_get_order 回傳 WC_Order_Refund 時回傳 null
      Given wc_get_order($last_order_id) 回傳 WC_Order_Refund 實例
      When Base::get_last_order($subscription) 被呼叫
      Then 回傳 null
      # WC_Order_Refund 不是 WC_Order 的子類（而是 WC_Abstract_Order）

    Example: 參數型別嚴格限制為 WC_Subscription
      Given 呼叫端傳入非 WC_Subscription 物件
      When Base::get_last_order($invalid) 被呼叫
      Then PHP 拋出 TypeError
      # 方法簽名強制 \WC_Subscription 型別
