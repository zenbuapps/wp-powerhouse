@ignore @system-behavior
Feature: Email 驗證服務（Service 層）

  Compatibility\Services\EmailValidator 是 mu-plugin「powerhouse-email-validator.php」
  的安裝服務。繼承自 MuPluginsLoader，除了宣告 $file_name 外還額外提供
  get_file_path() 方法，讓其他程式可以直接取得「原始 mu-plugin 檔案的絕對路徑」，
  作為 mu-plugin 尚未安裝到 wp-content/mu-plugins/ 時的 fallback 載入來源。

  Background:
    Given Powerhouse 外掛已啟用
    And Scheduler 已註冊 AS_COMPATIBILITY_ACTION 排程

  # ---------------------------------------------------------------------------
  # 類別結構
  # ---------------------------------------------------------------------------

  Rule: 系統行為 - EmailValidator 是 MuPluginsLoader 的子類並額外提供檔案路徑 API

    Example: 類別宣告為 final、使用 SingletonTrait、指定檔名
      Given EmailValidator 繼承 MuPluginsLoader
      When 檢視類別定義
      Then 類別為 final
      And 使用 J7\WpUtils\Traits\SingletonTrait
      And 指定 $file_name 為 "powerhouse-email-validator.php"

    Example: 提供 get_file_path() 回傳原始檔案絕對路徑
      Given EmailValidator::instance() 已建構
      When 呼叫 get_file_path()
      Then 回傳 wp_normalize_path("{file_dir}/powerhouse-email-validator.php")
      And file_dir 指向 inc/classes/Compatibility/mu-plugins 的絕對路徑

  # ---------------------------------------------------------------------------
  # 安裝時機
  # ---------------------------------------------------------------------------

  Rule: 前置（流程）- Scheduler::compatibility 中顯式實例化 EmailValidator

    Example: 版本升級時安裝
      Given Powerhouse 剛完成升版
      When AS_COMPATIBILITY_ACTION 被執行
      Then Scheduler::compatibility() 呼叫 EmailValidator::instance()
      And move_file() 將原始檔案複製到 wp-content/mu-plugins/powerhouse-email-validator.php

  # ---------------------------------------------------------------------------
  # Fallback 用途
  # ---------------------------------------------------------------------------

  Rule: 系統行為 - get_file_path() 提供 mu-plugin 尚未安裝時的 fallback

    Example: 其他服務可透過 get_file_path() 直接 require 原始檔案
      Given 使用者剛安裝 Powerhouse，AS_COMPATIBILITY_ACTION 尚未執行
      And wp-content/mu-plugins/powerhouse-email-validator.php 尚未存在
      When 另一個服務需要提前使用 email 驗證能力
      Then 可呼叫 EmailValidator::instance()->get_file_path() 取得原始檔案路徑
      And 直接 require 該路徑以載入驗證邏輯
