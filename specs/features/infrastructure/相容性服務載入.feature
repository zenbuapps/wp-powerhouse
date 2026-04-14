@ignore @system-behavior
Feature: 相容性服務載入

  Compatibility\Services\Scheduler 是整個 Compatibility 層的入口。
  它以 Singleton 註冊一個 Action Scheduler 任務（AS_COMPATIBILITY_ACTION），
  在每次 Powerhouse 升版時執行一次相容性代碼：包含 mu-plugin 安裝、
  資料表建立、Action Scheduler schema 修正、以及舊版管理頁 redirect。

  Background:
    Given Powerhouse 外掛已啟用
    And Action Scheduler 可用
    And Powerhouse 目前版本為 Plugin::$version

  # ---------------------------------------------------------------------------
  # Scheduler 啟動與掛鉤
  # ---------------------------------------------------------------------------

  Rule: 系統行為 - Scheduler::instance() 初始化時註冊必要的 hook

    Example: 註冊 plugins_loaded、AS_COMPATIBILITY_ACTION 與 init
      When Scheduler::instance() 被呼叫
      Then plugins_loaded hook 註冊 Scheduler::redirect callback
      And AS_COMPATIBILITY_ACTION hook 註冊 Scheduler::compatibility callback
      And init hook 註冊 Scheduler::compatibility_action_scheduler callback
      And AutoUpdate::instance() 被初始化

  # ---------------------------------------------------------------------------
  # 版本升級觸發
  # ---------------------------------------------------------------------------

  Rule: 前置（狀態）- 透過 wp_options 記錄已執行版本，每個版本只執行一次

    Example: 首次執行（option 不存在）時排入非同步任務
      Given powerhouse_compatibility_action_scheduled option 不存在
      When init hook 觸發 compatibility_action_scheduler()
      Then as_enqueue_async_action(AS_COMPATIBILITY_ACTION) 被呼叫
      And powerhouse_compatibility_action_scheduled 被更新為 Plugin::$version

    Example: 版本未變動時略過
      Given powerhouse_compatibility_action_scheduled 等於 Plugin::$version
      When init hook 觸發 compatibility_action_scheduler()
      Then as_enqueue_async_action 不被呼叫
      And option 保持不變

    Example: 升版後（版本不匹配）重新排入任務
      Given powerhouse_compatibility_action_scheduled 為舊版本 "3.3.47"
      And Plugin::$version 為 "3.3.48"
      When init hook 觸發 compatibility_action_scheduler()
      Then as_enqueue_async_action(AS_COMPATIBILITY_ACTION) 被呼叫
      And option 被更新為 "3.3.48"

  # ---------------------------------------------------------------------------
  # 相容性代碼執行內容
  # ---------------------------------------------------------------------------

  Rule: 核心行為 - Scheduler::compatibility() 逐一執行所有相容任務

    Example: 建立 access itemmeta 資料表
      When AS_COMPATIBILITY_ACTION 被執行
      Then CreateTable::create_itemmeta_table() 被呼叫
      And wp_ph_access_itemmeta 資料表存在（若原本不存在則建立）

    Example: 實例化各 mu-plugin 安裝服務
      When AS_COMPATIBILITY_ACTION 被執行
      Then EmailValidator::instance() 被呼叫
      And Loader::instance() 被呼叫
      And ApiBooster::instance() 被呼叫

    Example: 修正 actionscheduler_actions.args 欄位型別
      When AS_COMPATIBILITY_ACTION 被執行
      Then modify_action_scheduler_table_schema() 被呼叫

    Example: 完成後清除 rewrite 與 cache
      When AS_COMPATIBILITY_ACTION 執行完畢
      Then flush_rewrite_rules() 被呼叫
      And wp_cache_flush() 被呼叫

  # ---------------------------------------------------------------------------
  # Action Scheduler schema 修正
  # ---------------------------------------------------------------------------

  Rule: 系統行為 - 將 actionscheduler_actions.args 從 varchar(191) 改為 longtext

    Example: args 已是 longtext 時略過
      Given wp_actionscheduler_actions.args 欄位型別為 longtext
      When modify_action_scheduler_table_schema() 被呼叫
      Then 直接 return，不做任何修改

    Example: args 為 varchar(191) 時改寫為 longtext
      Given wp_actionscheduler_actions.args 欄位型別為 varchar(191)
      When modify_action_scheduler_table_schema() 被呼叫
      Then 系統透過 SHOW CREATE TABLE 取得原始 schema
      And 將 "args varchar(191)" 替換為 "args longtext"
      And 移除 "KEY args (args)" 索引
      And 執行 DROP INDEX `args` ON wp_actionscheduler_actions
      And 呼叫 dbDelta() 套用新 schema
      And 驗證欄位型別已包含 "longtext"，記錄成功或失敗 log

    Example: 無法取得原始 schema 時記錄 log 並中止
      Given SHOW CREATE TABLE 回傳空結果
      When modify_action_scheduler_table_schema() 被呼叫
      Then 記錄 log「無法取得表結構，請確認 Action Scheduler 是否已安裝」
      And 不執行後續 dbDelta

    Example: dbDelta 或 SQL 過程拋出例外時捕獲並記錄
      Given 執行過程中發生 Throwable
      When modify_action_scheduler_table_schema() 被呼叫
      Then catch 區塊記錄 debug log，包含錯誤訊息與結果

  # ---------------------------------------------------------------------------
  # 舊版管理頁 redirect
  # ---------------------------------------------------------------------------

  Rule: 系統行為 - 舊版授權碼管理頁需導向新版 hash 路由

    Example: 管理後台訪問舊版 page=powerhouse-license-codes 時導向新版
      Given 使用者在後台訪問 admin.php?page=powerhouse-license-codes
      When plugins_loaded hook 觸發 Scheduler::redirect()
      Then wp_safe_redirect 導向 admin.php?page=powerhouse#license-code
      And 流程中止（exit）

    Example: 非後台請求不做 redirect
      Given 目前不在 admin 環境
      When Scheduler::redirect() 被呼叫
      Then 直接 return，不做 redirect

    Example: 後台但不是目標頁面時不做 redirect
      Given 使用者在後台且 $_GET['page'] 不是 "powerhouse-license-codes"
      When Scheduler::redirect() 被呼叫
      Then 直接 return，不做 redirect
