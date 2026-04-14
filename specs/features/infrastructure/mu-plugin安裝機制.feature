@ignore @system-behavior
Feature: mu-plugin 安裝機制

  Compatibility\Shared\MuPluginsLoader 提供的抽象基底類別。
  負責將 inc/classes/Compatibility/mu-plugins/ 目錄下的 PHP 檔案，
  複製（安裝）到 WordPress 的 wp-content/mu-plugins/ 目錄。
  各子類別（Loader、ApiBooster、DisableFeatures、EmailValidator）
  只需要宣告 $file_name，即可繼承完整的安裝流程。

  Background:
    Given Powerhouse 外掛已啟用
    And Scheduler 的 AS_COMPATIBILITY_ACTION 排程機制存在
    And 原始 mu-plugin 檔案位於 inc/classes/Compatibility/mu-plugins/

  # ---------------------------------------------------------------------------
  # 子類別註冊
  # ---------------------------------------------------------------------------

  Rule: 系統行為 - 子類別繼承 MuPluginsLoader 並定義 $file_name

    Example: 子類別透過 constructor 自動掛載到 AS_COMPATIBILITY_ACTION
      Given 子類別繼承 MuPluginsLoader 並定義 $file_name 為 "powerhouse-loader.php"
      When 子類別被實例化（new SubClass()）
      Then $file_dir 屬性被設定為 inc/classes/Compatibility/mu-plugins 的絕對路徑
      And move_file() 被註冊為 AS_COMPATIBILITY_ACTION 的 callback

    Example: 多個子類別同時註冊到同一個 Action
      Given Loader、ApiBooster、DisableFeatures、EmailValidator 子類別都已實例化
      When Scheduler 觸發 AS_COMPATIBILITY_ACTION
      Then 所有子類別的 move_file() 都會被呼叫
      And 每個子類別各自處理自己的 $file_name

  # ---------------------------------------------------------------------------
  # mu-plugins 目錄檢查與建立
  # ---------------------------------------------------------------------------

  Rule: 前置（狀態）- 執行安裝前先確保 wp-content/mu-plugins/ 目錄存在

    Example: mu-plugins 目錄已存在時直接進入檔案複製
      Given wp-content/mu-plugins/ 目錄已存在
      When move_file() 被呼叫
      Then 系統不會嘗試建立目錄
      And 直接進入檔案複製流程

    Example: mu-plugins 目錄不存在時透過 WP_Filesystem 自動建立
      Given wp-content/mu-plugins/ 目錄不存在
      When move_file() 被呼叫
      Then 系統載入 wp-admin/includes/file.php
      And 呼叫 WP_Filesystem() 初始化 $wp_filesystem
      And 呼叫 $wp_filesystem->mkdir() 以 0755 權限建立目錄
      And 記錄「成功創建 mu-plugins 目錄」log

    Example: WP_Filesystem 初始化失敗時中止並記錄 error log
      Given wp-content/mu-plugins/ 目錄不存在
      And WP_Filesystem() 回傳 false
      When move_file() 被呼叫
      Then 記錄「無法初始化 WP_Filesystem」error log
      And move_file() 提前 return，不進行檔案複製

    Example: mkdir 失敗時中止並記錄 error log
      Given wp-content/mu-plugins/ 目錄不存在
      And $wp_filesystem->mkdir() 回傳 false
      When move_file() 被呼叫
      Then 記錄「無法創建 mu-plugins 目錄」error log
      And move_file() 提前 return，不進行檔案複製

  # ---------------------------------------------------------------------------
  # 檔案複製行為
  # ---------------------------------------------------------------------------

  Rule: 核心行為 - 將原始 mu-plugin 檔案複製到目標 mu-plugins 目錄

    Example: 目標檔案不存在時直接複製
      Given inc/classes/Compatibility/mu-plugins/powerhouse-loader.php 存在
      And wp-content/mu-plugins/powerhouse-loader.php 不存在
      When move_file() 被呼叫
      Then 系統呼叫 copy() 將檔案從 source_file 複製到 target_file
      And 記錄 debug log「檔案複製成功」，context 包含 source_file 與 target_file

    Example: 目標檔案已存在時先刪除再複製（覆寫舊版）
      Given inc/classes/Compatibility/mu-plugins/powerhouse-loader.php 存在
      And wp-content/mu-plugins/powerhouse-loader.php 已存在（舊版）
      When move_file() 被呼叫
      Then 系統呼叫 unlink() 刪除既有的 target_file
      And 呼叫 copy() 寫入新版
      And 記錄 debug log「檔案複製成功」

  # ---------------------------------------------------------------------------
  # 錯誤處理
  # ---------------------------------------------------------------------------

  Rule: 錯誤處理 - 任何檔案操作失敗皆捕獲例外並記錄 log

    Example: 源檔案不存在時拋出例外並記錄
      Given inc/classes/Compatibility/mu-plugins/powerhouse-loader.php 不存在
      When move_file() 被呼叫
      Then 系統拋出 Exception「source_file 源文件不存在」
      And catch 區塊記錄 error log「檔案操作失敗」，context 包含 source_file 與 target_file

    Example: 無法刪除既有目標檔案時拋出例外
      Given wp-content/mu-plugins/powerhouse-loader.php 已存在
      And unlink() 回傳 false
      When move_file() 被呼叫
      Then 系統拋出 Exception「無法刪除現有檔案」
      And 記錄 error log「檔案操作失敗」

    Example: copy 失敗時拋出例外
      Given 源檔案存在且目標檔案不存在
      And copy() 回傳 false
      When move_file() 被呼叫
      Then 系統拋出 Exception「檔案複製失敗」
      And 記錄 error log「檔案操作失敗」
