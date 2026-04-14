@ignore @system-behavior
Feature: Powerhouse 載入器

  mu-plugin 層級的早期載入機制。在 muplugins_loaded 階段預先載入
  Powerhouse 的 vendor autoloader 和核心 Trait/Class，確保 Powerhouse
  的基礎設施在所有其他外掛之前可用，解決外掛載入順序依賴問題。

  Background:
    Given powerhouse-loader.php 已安裝至 wp-content/mu-plugins/

  # ---------------------------------------------------------------------------
  # 核心載入邏輯
  # ---------------------------------------------------------------------------

  Rule: 系統行為 - 在 muplugins_loaded 階段載入 Powerhouse vendor

    Example: vendor autoloader 被成功載入
      Given wp-content/plugins/powerhouse/vendor/autoload.php 存在
      When muplugins_loaded hook 觸發（priority 100）
      Then Powerhouse 的 vendor/autoload.php 被 require_once
      And Composer autoloader 可用於後續的類別解析

    Example: vendor 檔案不存在時靜默跳過
      Given wp-content/plugins/powerhouse/vendor/autoload.php 不存在
      When muplugins_loaded hook 觸發
      Then 不會拋出錯誤或警告
      And WordPress 繼續正常載入

  # ---------------------------------------------------------------------------
  # Trait 預載入
  # ---------------------------------------------------------------------------

  Rule: 系統行為 - 預先載入核心 Trait 確保可用性

    Example: PluginTrait 被預先載入
      When Loader 執行完成
      Then J7\WpUtils\Traits\PluginTrait 已載入
      And 任何外掛可以使用 use PluginTrait

    Example: SingletonTrait 被預先載入
      When Loader 執行完成
      Then J7\WpUtils\Traits\SingletonTrait 已載入
      And 任何外掛可以使用 use SingletonTrait

    Example: LogTableCreationTrait 被預先載入
      When Loader 執行完成
      Then J7\WpUtils\Traits\LogTableCreationTrait 已載入

  # ---------------------------------------------------------------------------
  # Class 預載入
  # ---------------------------------------------------------------------------

  Rule: 系統行為 - 預先載入核心 Class 確保可用性

    Example: ApiBase 被預先載入
      When Loader 執行完成
      Then J7\WpUtils\Classes\ApiBase 已載入
      And Power 外掛的 API 類別可以繼承此基底類別

    Example: DTO 被預先載入
      When Loader 執行完成
      Then J7\WpUtils\Classes\DTO 已載入

    Example: 所有列舉的 Class 都被預先載入
      When Loader 執行完成
      Then 以下 Class 已可用：
        | class                           |
        | J7\WpUtils\Classes\ApiBase      |
        | J7\WpUtils\Classes\Auth         |
        | J7\WpUtils\Classes\DB           |
        | J7\WpUtils\Classes\DTO          |
        | J7\WpUtils\Classes\ErrorLog     |
        | J7\WpUtils\Classes\File         |
        | J7\WpUtils\Classes\General      |
        | J7\WpUtils\Classes\Log          |
        | J7\WpUtils\Classes\LogService   |
        | J7\WpUtils\Classes\Meta         |
        | J7\WpUtils\Classes\Point        |
        | J7\WpUtils\Classes\PointService |
        | J7\WpUtils\Classes\Statement    |
        | J7\WpUtils\Classes\UniqueArray  |
        | J7\WpUtils\Classes\WC           |
        | J7\WpUtils\Classes\WC\Product   |
        | J7\WpUtils\Classes\WP           |

  # ---------------------------------------------------------------------------
  # 載入時機
  # ---------------------------------------------------------------------------

  Rule: 系統行為 - 載入順序確保最早可用

    Example: mu-plugin 在一般外掛之前載入
      When WordPress 啟動
      Then mu-plugins 在 plugins_loaded 之前載入
      And Powerhouse 的 vendor 在所有一般外掛的 __construct 之前可用

    Example: 使用 trait_exists 和 class_exists 觸發按需載入
      Given Composer 使用按需載入（lazy loading）
      When Loader 對每個 Trait 呼叫 trait_exists()
      And 對每個 Class 呼叫 class_exists()
      Then Composer 的 autoloader 實際載入這些類別定義
      # 沒有實際使用的話 Composer 不會主動載入

  # ---------------------------------------------------------------------------
  # mu-plugin 安裝機制
  # ---------------------------------------------------------------------------

  Rule: 系統行為 - mu-plugin 檔案由 MuPluginsLoader 自動安裝

    Example: Powerhouse 版本更新時自動複製 mu-plugin
      Given Powerhouse 外掛已啟用
      When powerhouse_compatibility_action_scheduler 排程執行
      Then 系統複製 powerhouse-loader.php 到 mu-plugins 目錄

    Example: 目標檔案已存在時先刪除再複製
      Given wp-content/mu-plugins/powerhouse-loader.php 已存在（舊版本）
      When MuPluginsLoader.move_file 執行
      Then 系統先刪除舊檔案
      And 再複製新版本檔案
      And 記錄複製成功 log
