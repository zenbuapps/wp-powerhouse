@ignore @system-behavior
Feature: 停用功能服務（Service 層）

  Compatibility\Services\DisableFeatures 是 mu-plugin「powerhouse-disable-features.php」
  的安裝服務。繼承自 Compatibility\Shared\MuPluginsLoader，本身只宣告 $file_name。
  注意：此 Service 層僅負責「檔案複製」，實際停用的 WordPress 功能
  （emoji、embed 等）由 mu-plugin 層在早期載入時執行。

  Background:
    Given Powerhouse 外掛已啟用
    And Scheduler 已註冊 AS_COMPATIBILITY_ACTION 排程

  # ---------------------------------------------------------------------------
  # 類別結構
  # ---------------------------------------------------------------------------

  Rule: 系統行為 - DisableFeatures 是 MuPluginsLoader 的薄殼子類

    Example: 類別宣告為 final、使用 SingletonTrait、指定檔名
      Given DisableFeatures 繼承 MuPluginsLoader
      When 檢視類別定義
      Then 類別為 final
      And 使用 J7\WpUtils\Traits\SingletonTrait
      And 指定 $file_name 為 "powerhouse-disable-features.php"
      And 不覆寫父類的 move_file() 方法

  # ---------------------------------------------------------------------------
  # 安裝時機
  # ---------------------------------------------------------------------------

  Rule: 前置（流程）- 版本升級時由 Scheduler 觸發安裝

    Example: DisableFeatures 的 move_file 掛載到 AS_COMPATIBILITY_ACTION
      Given Powerhouse 即將升版
      When DisableFeatures::instance() 被呼叫
      Then constructor 註冊 move_file() 為 AS_COMPATIBILITY_ACTION 的 callback
      And 當排程執行時，原始 powerhouse-disable-features.php 被複製到 wp-content/mu-plugins/

    Note:
      Scheduler::compatibility() 目前沒有顯式呼叫 DisableFeatures::instance()，
      該服務需由外部（例如 Bootstrap 或其他服務）負責實例化以完成 hook 註冊。
