@ignore @system-behavior
Feature: API 加速器服務（Service 層）

  Compatibility\Services\ApiBooster 是 mu-plugin「powerhouse-api-booster.php」
  的安裝服務。繼承自 Compatibility\Shared\MuPluginsLoader，本身只宣告
  $file_name，所有安裝邏輯由父類提供。Service 層負責「把 mu-plugin 檔案搬到
  wp-content/mu-plugins/」，而實際的 API 加速行為（規則匹配、覆寫 active_plugins）
  由 mu-plugin 層執行，詳見 `spec/features/mu-plugin/API加速器.feature`。

  Background:
    Given Powerhouse 外掛已啟用
    And Scheduler 已註冊 AS_COMPATIBILITY_ACTION 排程

  # ---------------------------------------------------------------------------
  # 類別結構
  # ---------------------------------------------------------------------------

  Rule: 系統行為 - ApiBooster 是 MuPluginsLoader 的薄殼子類

    Example: 類別宣告為 final、使用 SingletonTrait、指定檔名
      Given ApiBooster 繼承 MuPluginsLoader
      When 檢視 ApiBooster 類別定義
      Then 類別為 final
      And 使用 J7\WpUtils\Traits\SingletonTrait
      And 指定 $file_name 為 "powerhouse-api-booster.php"
      And 不覆寫父類的 move_file() 方法

  # ---------------------------------------------------------------------------
  # 安裝時機
  # ---------------------------------------------------------------------------

  Rule: 前置（流程）- 由 Scheduler 在版本升級時觸發安裝

    Example: Scheduler::compatibility() 執行時實例化並觸發安裝
      Given Powerhouse 剛完成升版
      When AS_COMPATIBILITY_ACTION 被 Scheduler 排入並執行
      Then Scheduler::compatibility() 呼叫 ApiBooster::instance()
      And constructor 註冊 move_file() 為同一個 action 的 callback
      And move_file() 將 inc/classes/Compatibility/mu-plugins/powerhouse-api-booster.php
        複製到 wp-content/mu-plugins/powerhouse-api-booster.php

    Example: 多次升版後都會重新複製，確保 mu-plugin 版本最新
      Given wp-content/mu-plugins/powerhouse-api-booster.php 已是舊版
      When Powerhouse 升版並觸發 AS_COMPATIBILITY_ACTION
      Then 舊版檔案被 unlink
      And 新版檔案被 copy
