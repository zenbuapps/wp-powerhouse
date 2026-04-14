@ignore @system-behavior
Feature: 自動更新

  當 Power 系列外掛更新時，自動觸發 Powerhouse 核心外掛的更新。
  確保 Powerhouse 始終與其依賴的子外掛版本保持一致，避免
  因版本不匹配導致的相容性問題。

  Background:
    Given Powerhouse 外掛已啟用
    And Action Scheduler 可用

  # ---------------------------------------------------------------------------
  # 觸發條件
  # ---------------------------------------------------------------------------

  Rule: 前置（狀態）- Power 系列外掛更新時觸發 Powerhouse 自動更新

    Example: power- 開頭的外掛更新時觸發排程
      Given 系統環境為 production
      And active_plugins 包含 "power-course/power-course.php"
      When 管理員更新 power-course 外掛
      And upgrader_process_complete hook 觸發
      Then 系統排程一個 10 秒後執行的 powerhouse_auto_update action
      And 記錄排程 action_id 至 log

    Example: 非 power- 外掛更新時不觸發
      Given 系統環境為 production
      When 管理員更新 woocommerce 外掛
      And upgrader_process_complete hook 觸發
      Then 不排程 powerhouse_auto_update action

    Example: hook_extra 中 plugins 為空時不觸發
      Given 系統環境為 production
      When upgrader_process_complete hook 觸發且 plugins 為空陣列
      Then 不排程 powerhouse_auto_update action

  # ---------------------------------------------------------------------------
  # 自動更新流程
  # ---------------------------------------------------------------------------

  Rule: 系統行為 - process_auto_update 負責實際執行自動更新

    Example: 排程觸發時呼叫 process_auto_update
      When Action Scheduler 執行 powerhouse_auto_update action
      Then AutoUpdate::process_auto_update 被呼叫

    Example: process_auto_update 有可用更新時自動更新並啟用
      Given WordPress 更新 transient 中 target_plugin 有可用更新
      When process_auto_update 執行
      Then 呼叫 wp_update_plugins() 檢查更新
      And 從 get_site_transient('update_plugins') 讀取 plugin_updates
      And 確認 plugin_updates->response[target_plugin] 存在
      And 載入 wp-admin/includes/admin.php 與 class-wp-upgrader.php
      And 建立 Plugin_Upgrader 實例
      And 呼叫 $upgrader->upgrade($this->target_plugin) 執行更新
      And 更新完成後呼叫 activate_plugin($this->target_plugin) 重新啟用
      And 記錄「自動更新 {target_plugin} 成功」log

    Example: process_auto_update 沒有可用更新時拋出例外並記錄
      Given WordPress 更新 transient 中 target_plugin 沒有可用更新
      When process_auto_update 執行
      Then 拋出 \Exception("沒有可用的更新或插件不存在")
      And catch Throwable 後記錄 error 等級 log「自動更新失敗: 沒有可用的更新或插件不存在」

    Example: Plugin_Upgrader::upgrade 回傳 WP_Error 時拋出例外
      Given WordPress 更新 transient 中有可用更新
      But Plugin_Upgrader::upgrade() 回傳 WP_Error
      When process_auto_update 執行
      Then 拋出 \Exception("更新錯誤: ...")
      And 不嘗試啟用外掛
      And catch 後記錄 error 等級 log「自動更新失敗: 更新錯誤: ...」

    Example: activate_plugin 回傳 WP_Error 時拋出例外
      Given Plugin_Upgrader::upgrade() 成功
      But activate_plugin() 回傳 WP_Error
      When process_auto_update 執行
      Then 拋出 \Exception("啟用錯誤: ...")
      And catch 後記錄 error 等級 log「自動更新失敗: 啟用錯誤: ...」

    Example: process_auto_update 內部以 try-catch 捕捉所有 Throwable
      Given 更新過程中任何環節拋出 Throwable
      When process_auto_update 執行
      Then catch 區塊記錄 error 等級 log「自動更新失敗: {message}」
      And 不影響系統正常運作（不會中斷 Action Scheduler 其他 job）

  # ---------------------------------------------------------------------------
  # 環境差異
  # ---------------------------------------------------------------------------

  Rule: 前置（狀態）- 本地環境使用測試用外掛設定

    Example: local 環境使用替代外掛進行測試
      Given 系統環境為 local（wp_get_environment_type() 回傳 "local"）
      When AutoUpdate 初始化
      Then target_plugin 設定為 "classic-editor/classic-editor.php"
      And power_plugins 設定為 ["all-in-one-wp-migration/all-in-one-wp-migration.php"]
      # 避免在本地開發環境中意外更新 Powerhouse

    Example: production 環境使用正式外掛設定
      Given 系統環境為 production
      When AutoUpdate 初始化
      Then target_plugin 設定為 "powerhouse/plugin.php"
      And power_plugins 從 active_plugins 中篩選所有 "power-" 開頭的外掛

  # ---------------------------------------------------------------------------
  # Power 外掛偵測
  # ---------------------------------------------------------------------------

  Rule: 系統行為 - 動態偵測所有已啟用的 Power 系列外掛

    Example: 篩選 power- 開頭的外掛
      Given active_plugins 包含：
        | plugin                              |
        | powerhouse/plugin.php               |
        | power-course/power-course.php       |
        | power-shop/power-shop.php           |
        | woocommerce/woocommerce.php         |
        | classic-editor/classic-editor.php   |
      When get_power_plugins() 被呼叫
      Then 回傳 ["power-course/power-course.php", "power-shop/power-shop.php"]
      And 不包含 "powerhouse/plugin.php"（不以 power- 開頭）
      And 不包含非 Power 系列外掛

  # ---------------------------------------------------------------------------
  # 邊界條件
  # ---------------------------------------------------------------------------

  Rule: 邊界條件 - 例外處理

    Example: 更新過程中發生未預期例外
      Given WordPress 更新 transient 中有可用更新
      But 更新過程中拋出 Throwable
      When powerhouse_auto_update 排程執行
      Then 記錄 error 等級 log 包含「自動更新失敗」和例外訊息
      And 不影響系統正常運作
