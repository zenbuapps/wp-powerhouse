@ignore @system-behavior
Feature: 非同步任務處理

  Action Scheduler 排程任務的抽象基底類別（AsSchedulerHandler\Shared\Base）。
  提供統一的排程建立、查詢、取消介面，供所有 Power 外掛繼承使用。
  透過結構化的 $hook 和 $args 確保排程的唯一性和可查詢性。

  Background:
    Given Powerhouse 外掛已啟用
    And Action Scheduler 外掛可用

  # ---------------------------------------------------------------------------
  # 繼承與註冊
  # ---------------------------------------------------------------------------

  Rule: 系統行為 - 子類別繼承 Base 並實作抽象方法

    Example: 子類別定義 hook 和實作 get_args
      Given 子類別定義 static $hook 為 "power-course/v1/enrollment/remind"
      And 子類別實作 get_args() 回傳 ["user_id" => "123", "course_id" => "456"]
      When new SubClass($item) 被建構
      Then $args 屬性被設定為 get_args() 的回傳值

    Example: 子類別透過 register() 註冊 hook callback
      Given 子類別定義 static $hook 為 "power-course/v1/enrollment/remind"
      When SubClass::register() 被呼叫
      Then WordPress add_action 註冊 hook "power-course/v1/enrollment/remind"
      And callback 為 SubClass::action_callback

  # ---------------------------------------------------------------------------
  # 單次排程
  # ---------------------------------------------------------------------------

  Rule: 後置（狀態）- schedule_single 建立一次性排程

    Example: 建立未來時間的單次排程
      Given 子類別已建構
      When schedule_single(time() + 3600) 被呼叫
      Then Action Scheduler 建立一個 1 小時後執行的排程
      And 回傳 action_id
      And after_schedule_single hook 被觸發

    Example: 帶 group 參數的排程
      When schedule_single(time() + 3600, "my-group") 被呼叫
      Then 排程的 group 為 "my-group"

    Example: unique 參數限制同 hook+group 只排一次
      When schedule_single(time() + 3600, "my-group", true) 被呼叫
      Then Action Scheduler 的 unique 參數為 true
      And 若已存在相同 hook+group 的排程則不重複建立

    Example: 自訂 priority
      When schedule_single(time() + 3600, "", false, 5) 被呼叫
      Then 排程的 priority 為 5

  # ---------------------------------------------------------------------------
  # 定期排程
  # ---------------------------------------------------------------------------

  Rule: 後置（狀態）- schedule_recurring 建立定期排程

    Example: 建立每小時執行一次的定期排程
      Given 子類別已建構
      When schedule_recurring(time(), 3600) 被呼叫
      Then Action Scheduler 建立一個每 3600 秒執行的定期排程
      And 回傳 action_id
      And after_schedule_recurring hook 被觸發

    Example: 帶 group 和 unique 參數
      When schedule_recurring(time(), 3600, "daily-group", true) 被呼叫
      Then 排程的 group 為 "daily-group"
      And unique 為 true

  # ---------------------------------------------------------------------------
  # 非同步排程
  # ---------------------------------------------------------------------------

  Rule: 後置（狀態）- schedule_async 建立立即非同步排程

    Example: 建立非同步排程
      Given 子類別已建構
      When schedule_async(time()) 被呼叫
      Then Action Scheduler 立即排入非同步佇列
      And 回傳 action_id
      And after_schedule_async hook 被觸發

  # ---------------------------------------------------------------------------
  # 排程查詢
  # ---------------------------------------------------------------------------

  Rule: 前置（狀態）- 查詢是否已有相同的排程

    Example: 存在匹配的 pending 排程
      Given Action Scheduler 中有一個 pending 狀態的排程
      And 排程的 hook、args、group 與當前實例相同
      When has_scheduled("my-group") 被呼叫
      Then 回傳 true

    Example: 沒有匹配的排程
      Given Action Scheduler 中沒有匹配的 pending 排程
      When has_scheduled() 被呼叫
      Then 回傳 false

    Example: 取得下一個排程的 action_id
      Given Action Scheduler 中有匹配的 pending 排程，action_id 為 42
      When get_next_action_id() 被呼叫
      Then 回傳 42

    Example: 沒有匹配排程時回傳 null
      Given Action Scheduler 中沒有匹配的 pending 排程
      When get_next_action_id() 被呼叫
      Then 回傳 null

    Example: 查詢按日期升序取第一筆
      Given Action Scheduler 中有多個匹配的 pending 排程
      When get_next_action_id() 被呼叫
      Then 回傳最早排程的 action_id（日期升序第一筆）

  # ---------------------------------------------------------------------------
  # 取消排程
  # ---------------------------------------------------------------------------

  Rule: 後置（狀態）- unschedule 取消下一個排程

    Example: 取消已存在的排程
      Given Action Scheduler 中有匹配的 pending 排程，action_id 為 42
      When unschedule() 被呼叫
      Then ActionScheduler_Store 刪除 action_id 42
      And after_unschedule hook 被觸發
      And 回傳 42

    Example: 沒有排程可取消時回傳 null
      Given Action Scheduler 中沒有匹配的 pending 排程
      When unschedule() 被呼叫
      Then 不呼叫 ActionScheduler_Store::delete_action
      And 回傳 null

    Example: maybe_unschedule 在 once=true 時取消排程
      Given Action Scheduler 中有匹配的 pending 排程
      When maybe_unschedule("my-group", true) 被呼叫
      Then 呼叫 unschedule("my-group")

    Example: maybe_unschedule 在 once=false 時不取消
      When maybe_unschedule("my-group", false) 被呼叫
      Then 不呼叫 unschedule

  # ---------------------------------------------------------------------------
  # 取消所有排程
  # ---------------------------------------------------------------------------

  Rule: 後置（狀態）- unschedule_all 取消所有匹配的排程

    Example: 取消所有匹配的排程
      Given Action Scheduler 中有多個匹配的排程
      When unschedule_all("my-group") 被呼叫
      Then as_unschedule_all_actions 被呼叫
      And 所有匹配 hook+args+group 的排程被取消
      And after_unschedule_all hook 被觸發

  # ---------------------------------------------------------------------------
  # 生命週期 Hook
  # ---------------------------------------------------------------------------

  Rule: 系統行為 - 子類別可覆寫生命週期 hook

    Example: after_schedule_single 預設為空操作
      When 基底類別的 after_schedule_single 被呼叫
      Then 不執行任何操作
      # 子類別可覆寫以加入 log 記錄等邏輯

    Example: after_unschedule 預設為空操作
      When 基底類別的 after_unschedule 被呼叫
      Then 不執行任何操作

    Example: after_schedule_recurring 預設為空操作
      When 基底類別的 after_schedule_recurring 被呼叫
      Then 不執行任何操作

    Example: after_schedule_async 預設為空操作
      When 基底類別的 after_schedule_async 被呼叫
      Then 不執行任何操作

    Example: after_unschedule_all 預設為空操作
      When 基底類別的 after_unschedule_all 被呼叫
      Then 不執行任何操作

  # ---------------------------------------------------------------------------
  # Args 順序重要性
  # ---------------------------------------------------------------------------

  Rule: 邊界條件 - args 順序影響排程查詢

    Example: args 順序一致才能查詢到排程
      Given 子類別的 get_args() 回傳 ["user_id" => "123", "course_id" => "456"]
      When schedule_single 建立排程後
      And 以相同的 args 順序呼叫 get_next_action_id
      Then 能成功查詢到排程
      # as_get_scheduled_actions 會序列化 args 為 JSON 做比對
      # 因此 args 的 key 順序必須一致
