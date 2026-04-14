@ignore @system-behavior
Feature: 排程服務（Compatibility Scheduler）

  Compatibility\Services\Scheduler 是 Compatibility 層的排程中樞。
  功能分為四大塊：
    1. 版本升級偵測：利用 powerhouse_compatibility_action_scheduled option 判斷是否需要執行
    2. 非同步執行：透過 Action Scheduler 的 as_enqueue_async_action 排入一次性任務
    3. 相容性代碼執行：執行資料表建立、mu-plugin 安裝、schema 修正
    4. 舊版 URL redirect：將舊版授權碼管理頁導向新版 hash 路由

  Background:
    Given Powerhouse 外掛已啟用
    And Action Scheduler 可用
    And 常數 Scheduler::AS_COMPATIBILITY_ACTION 值為 "powerhouse_compatibility_action_scheduler"

  # ---------------------------------------------------------------------------
  # Singleton 初始化
  # ---------------------------------------------------------------------------

  Rule: 系統行為 - Scheduler 以 Singleton 初始化並掛載三個主要 hook

    Example: constructor 註冊 hook
      When Scheduler::instance() 首次被呼叫
      Then plugins_loaded hook 註冊 Scheduler::redirect
      And AS_COMPATIBILITY_ACTION hook 註冊 Scheduler::compatibility
      And init hook 註冊 Scheduler::compatibility_action_scheduler
      And AutoUpdate::instance() 被初始化

    Example: AutoUpdate 的 SKILL 另行管理
      Given AutoUpdate 是獨立的 Service 類別
      When Scheduler constructor 執行
      Then AutoUpdate::instance() 被呼叫以註冊其自身的 hook
      Note: 詳細自動更新行為見 spec/features/infrastructure/自動更新.feature

  # ---------------------------------------------------------------------------
  # 版本升級偵測
  # ---------------------------------------------------------------------------

  Rule: 核心行為 - 每個版本的相容性代碼只執行一次

    Example: 新版本觸發排程
      Given powerhouse_compatibility_action_scheduled option 為 "3.3.47"
      And Plugin::$version 為 "3.3.48"
      When init hook 呼叫 compatibility_action_scheduler()
      Then as_enqueue_async_action(AS_COMPATIBILITY_ACTION, []) 被呼叫
      And powerhouse_compatibility_action_scheduled 被更新為 "3.3.48"

    Example: 同版本略過排程
      Given powerhouse_compatibility_action_scheduled option 等於 Plugin::$version
      When compatibility_action_scheduler() 被呼叫
      Then as_enqueue_async_action 不被呼叫

    Example: 首次安裝（option 不存在）也會觸發
      Given powerhouse_compatibility_action_scheduled option 不存在
      And get_option 預設回傳 false
      When compatibility_action_scheduler() 被呼叫
      Then 條件不成立（false !== version），排程被建立

  # ---------------------------------------------------------------------------
  # 相容性代碼內容
  # ---------------------------------------------------------------------------

  Rule: 核心行為 - compatibility() 依序執行所有相容任務

    Example: 完整執行順序
      When AS_COMPATIBILITY_ACTION 被 Action Scheduler 處理
      Then Scheduler::compatibility() 依序執行：
        | 步驟 | 動作                                                     |
        | 1    | CreateTable::create_itemmeta_table() 建立 itemmeta 表    |
        | 2    | EmailValidator::instance() 安裝 email validator mu-plugin |
        | 3    | Loader::instance() 安裝 powerhouse loader mu-plugin      |
        | 4    | ApiBooster::instance() 安裝 api booster mu-plugin        |
        | 5    | modify_action_scheduler_table_schema() 修正 args 欄位    |
        | 6    | flush_rewrite_rules() 清除 rewrite 快取                  |
        | 7    | wp_cache_flush() 清除 object cache                       |

  # ---------------------------------------------------------------------------
  # Action Scheduler args 欄位升級
  # ---------------------------------------------------------------------------

  Rule: 系統行為 - 將 wp_actionscheduler_actions.args 欄位升級為 longtext

    Example: 已是 longtext 時直接 return
      Given DESCRIBE wp_actionscheduler_actions 中 args 欄位 Type 為 "longtext"
      When modify_action_scheduler_table_schema() 被呼叫
      Then 函式立即 return
      And 不執行任何 DDL

    Example: 原為 varchar(191) 時執行改寫流程
      Given args 欄位 Type 為 "varchar(191)"
      When modify_action_scheduler_table_schema() 被呼叫
      Then 執行 SHOW CREATE TABLE wp_actionscheduler_actions 取得原始 schema
      And 移除反引號
      And 移除 ENGINE/CHARSET/COLLATE 尾段並改為 "$wpdb->collate"
      And 將 "args varchar(191)" 替換為 "args longtext"
      And 移除 "KEY args (args)," 索引定義
      And 修正連續逗號的 SQL 語法錯誤
      And 壓縮空行
      And 執行 "DROP INDEX `args` ON wp_actionscheduler_actions"
      And 載入 wp-admin/includes/upgrade.php
      And 呼叫 dbDelta() 套用新 schema
      And 再次查詢欄位型別驗證是否包含 "longtext"
      And 記錄 debug log 包含 dbDelta 結果與新欄位型別

    Example: 無法取得原始 schema
      Given SHOW CREATE TABLE 回傳 null 或空字串
      When modify_action_scheduler_table_schema() 被呼叫
      Then 記錄 log「無法取得表結構」
      And 不執行後續流程

    Example: 過程中拋出 Throwable
      Given dbDelta 或中間步驟拋出例外
      When modify_action_scheduler_table_schema() 被呼叫
      Then catch 區塊記錄 debug log 包含 Throwable 訊息

  # ---------------------------------------------------------------------------
  # 舊版 URL redirect
  # ---------------------------------------------------------------------------

  Rule: 系統行為 - 舊版 powerhouse-license-codes 後台頁導向新版 hash 路由

    Example: 後台訪問舊頁 → 導向新頁
      Given 使用者在 admin.php?page=powerhouse-license-codes
      When plugins_loaded hook 觸發 Scheduler::redirect()
      Then wp_safe_redirect 導向 admin.php?page=powerhouse#license-code
      And 呼叫 exit 中止後續執行

    Example: 非後台不處理
      Given is_admin() 為 false
      When Scheduler::redirect() 被呼叫
      Then 函式立即 return

    Example: $_GET['page'] 未設定時不處理
      Given is_admin() 為 true
      And $_GET['page'] 未設定
      When Scheduler::redirect() 被呼叫
      Then 函式立即 return

    Example: 其他 page 不處理
      Given is_admin() 為 true
      And $_GET['page'] 為 "powerhouse"
      When Scheduler::redirect() 被呼叫
      Then 函式立即 return

  # ---------------------------------------------------------------------------
  # Deprecated 檔案載入
  # ---------------------------------------------------------------------------

  Rule: 系統行為 - 類別載入時自動引入 deprecated 函式

    Example: deprecated.php 在類別宣告前被 require_once
      Given Scheduler.php 檔案頂層執行 "require_once __DIR__ . '/deprecated.php'"
      When Scheduler 類別被 autoload
      Then deprecated.php 被載入
      And 其中再 require_once Domains/Post/Utils/deprecated.php 與 Domains/LC/Utils/deprecated.php
      And 提供舊函式簽章作為相容層
