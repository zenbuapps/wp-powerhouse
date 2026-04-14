@ignore @system-behavior
Feature: 延遲發送 Email

  WooCommerce 訂單狀態變更時的 Email 延遲發送機制。
  將原本同步觸發的 Email 改為透過 Action Scheduler 非同步發送，
  避免訂單處理時因 Email 發送阻塞回應。

  Background:
    Given Powerhouse 外掛已啟用
    And WooCommerce 外掛已啟用
    And powerhouse_settings.delay_email 為 "yes"

  # =========================================================
  # 啟用/停用條件
  # =========================================================

  Rule: 前置（狀態）- delay_email 為 "no" 時不啟用延遲發信

    Example: 停用延遲發信
      Given powerhouse_settings.delay_email 為 "no"
      When 系統初始化 DelayEmail
      Then WooCommerce 原始 Email 觸發機制不被修改
      And Email 在訂單狀態變更時同步發送

  Rule: 前置（狀態）- delay_email 值使用 wc_string_to_bool 解析

    Example: delay_email 為 "yes" 啟用
      Given powerhouse_settings.delay_email 為 "yes"
      When 系統初始化 DelayEmail
      Then 延遲發信功能啟用

    Example: delay_email 為 "1" 啟用
      Given powerhouse_settings.delay_email 為 "1"
      When 系統初始化 DelayEmail
      Then 延遲發信功能啟用

    Example: delay_email 為 "no" 停用
      Given powerhouse_settings.delay_email 為 "no"
      When 系統初始化 DelayEmail
      Then 延遲發信功能不啟用

  # =========================================================
  # 移除原始 Email 觸發（remove_origin_email_sending）
  # =========================================================

  Rule: 系統行為 - remove_origin_email_sending 於 init hook（priority 100）執行

    Example: init hook 觸發時執行移除
      Given delay_email 為 "yes"
      When WordPress init hook 以 priority 100 觸發
      Then remove_origin_email_sending 被呼叫

    Example: remove_origin_email_sending 對每個 class_name/hook 組合執行替換
      When remove_origin_email_sending 執行
      Then 對 class_name_and_hooks 定義的每個組合：
        | 步驟 | 動作                                                                                          |
        | 1    | remove_action($hook, [WC()->mailer()->emails[$class_name], 'trigger']) 移除原始同步觸發       |
        | 2    | add_action($hook, closure 以 as_enqueue_async_action('powerhouse_delay_email', [...]) 重新綁) |
      And closure 執行時將 class_name 與原始 args 一併傳入 as_enqueue_async_action

  Rule: 後置（狀態）- 移除 WC_Email_New_Order 的原始觸發

    Example: 移除新訂單 Email 的同步觸發
      When init hook 觸發（priority 100）
      Then 移除以下 hook 上的 WC_Email_New_Order::trigger：
        | hook                                                                  |
        | woocommerce_order_status_pending_to_processing_notification           |
        | woocommerce_order_status_pending_to_completed_notification            |
        | woocommerce_order_status_pending_to_on-hold_notification              |
        | woocommerce_order_status_failed_to_processing_notification            |
        | woocommerce_order_status_failed_to_completed_notification             |
        | woocommerce_order_status_failed_to_on-hold_notification               |
        | woocommerce_order_status_cancelled_to_processing_notification         |
        | woocommerce_order_status_cancelled_to_completed_notification          |
        | woocommerce_order_status_cancelled_to_on-hold_notification            |

  Rule: 後置（狀態）- 移除 WC_Email_Customer_Completed_Order 的原始觸發

    Example: 移除已完成訂單客戶 Email 的同步觸發
      When init hook 觸發（priority 100）
      Then 移除以下 hook 上的 WC_Email_Customer_Completed_Order::trigger：
        | hook                                                     |
        | woocommerce_order_status_completed_notification          |

  Rule: 後置（狀態）- 移除 WC_Email_Customer_Processing_Order 的原始觸發

    Example: 移除處理中訂單客戶 Email 的同步觸發
      When init hook 觸發（priority 100）
      Then 移除以下 hook 上的 WC_Email_Customer_Processing_Order::trigger：
        | hook                                                                  |
        | woocommerce_order_status_cancelled_to_processing_notification         |
        | woocommerce_order_status_failed_to_processing_notification            |
        | woocommerce_order_status_on-hold_to_processing_notification           |
        | woocommerce_order_status_pending_to_processing_notification           |

  # =========================================================
  # 非同步排程取代
  # =========================================================

  Rule: 後置（狀態）- 訂單狀態變更時排程非同步 Email

    Example: 訂單從 pending 變為 processing 時排程 Email
      Given 訂單 #100 狀態為 "pending"
      When 訂單 #100 狀態變更為 "processing"
      Then as_enqueue_async_action 被呼叫
      And action 為 "powerhouse_delay_email"
      And 參數包含 class_name "WC_Email_New_Order" 與原始 hook 參數
      And Email 不會立即發送

    Example: 訂單從 pending 變為 completed 時排程多封 Email
      Given 訂單 #100 狀態為 "pending"
      When 訂單 #100 狀態變更為 "completed"
      Then WC_Email_New_Order 的 Email 透過 as_enqueue_async_action 排程
      And WC_Email_Customer_Completed_Order 的 Email 透過 as_enqueue_async_action 排程

  # =========================================================
  # 非同步 Email 發送
  # =========================================================

  Rule: 後置（狀態）- Action Scheduler 觸發 powerhouse_delay_email 時發送 Email

    Example: 成功發送延遲 Email
      Given Action Scheduler 執行 powerhouse_delay_email action
      And 參數為 class_name "WC_Email_New_Order" 與原始 args
      When schedule_email 被呼叫
      Then 取得 WC()->mailer()->emails["WC_Email_New_Order"] 實例
      And 呼叫該實例的 trigger() 方法，傳入原始 args
      And Email 成功發送

    Example: Email class 不存在 trigger 方法時靜默跳過
      Given Action Scheduler 執行 powerhouse_delay_email action
      And 參數中的 class_name 對應的 Email 實例沒有 trigger 方法
      When schedule_email 被呼叫
      Then 不發送任何 Email
      And 不拋出錯誤

  # =========================================================
  # 邊界條件
  # =========================================================

  Rule: 前置（狀態）- 依賴 WooCommerce Action Scheduler

    Example: Action Scheduler 未安裝時
      Given WooCommerce 未啟用或 as_enqueue_async_action 函式不存在
      When 訂單狀態變更觸發 Email
      Then 應拋出 Fatal Error（as_enqueue_async_action 未定義）

  Rule: 後置（狀態）- 多個訂單狀態變更產生獨立的排程任務

    Example: 兩張訂單同時變更狀態
      Given 訂單 #100 和 #101 同時從 "pending" 變為 "processing"
      When 各自觸發 woocommerce_order_status_pending_to_processing_notification
      Then 各自產生獨立的 as_enqueue_async_action 排程
      And 兩封 Email 分別在非同步佇列中處理
