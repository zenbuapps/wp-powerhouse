@ignore @system-behavior
Feature: 建立存取權限資料表

  # ph_access_itemmeta 是 Powerhouse Limit Domain 的核心自訂資料表，
  # 紀錄「用戶對內容項目」的存取權限（expire_date / 其他自訂 meta）。
  # 此表在兩個時機被建立：
  #   1. 外掛啟用時（activate hook 經 PluginTrait 呼叫 Plugin::activate()）
  #   2. Compatibility Scheduler 執行（每次版本變動時重新檢查並補建）
  # 本 feature 描述 CreateTable::create_itemmeta_table 的建立行為、idempotency 與 schema。

  Background:
    Given Powerhouse 外掛已載入
    And WordPress 已提供 $wpdb 全域實例

  Rule: 前置（狀態）- 外掛啟用時觸發建表

    Example: register_activation_hook 觸發 Plugin::activate 建立資料表
      Given wp_{prefix}ph_access_itemmeta 表尚未存在
      When 使用者於 WP Admin 啟用 Powerhouse 外掛
      Then Plugin::activate 被呼叫
      And 載入 inc/classes/Domains/Limit/Utils/CreateTable.php
      And CreateTable::create_itemmeta_table() 被呼叫
      And wp_{prefix}ph_access_itemmeta 表已建立

    Example: Compatibility Scheduler 於版本變動後也會建表
      Given powerhouse_compatibility_action_scheduled option 與目前 Plugin::$version 不同
      When 系統於 init hook 排程執行 powerhouse_compatibility_action_scheduler
      Then Scheduler::compatibility 被呼叫
      And CreateTable::create_itemmeta_table() 被呼叫

  Rule: 前置（狀態）- 表已存在時直接 return（idempotent）

    Example: 重複呼叫 create_itemmeta_table 不重複建表
      Given wp_{prefix}ph_access_itemmeta 表已存在
      When 呼叫 CreateTable::create_itemmeta_table()
      Then WP::is_table_exists 回傳 true
      And 方法立即 return，不執行 dbDelta
      And 方法不拋出例外

    Example: 連續呼叫兩次不拋出例外
      When 連續呼叫 CreateTable::create_itemmeta_table() 兩次
      Then 兩次均正常返回，不拋出例外

  Rule: 後置（狀態）- dbDelta 建立的 schema 結構

    Example: 建表後 schema 包含所有必要欄位
      Given wp_{prefix}ph_access_itemmeta 表尚未存在
      When 呼叫 CreateTable::create_itemmeta_table()
      Then wp_{prefix}ph_access_itemmeta 表被建立
      And 表包含以下欄位：
        | column     | type         | null | default | extra          |
        | meta_id    | bigint(20)   | NO   | NULL    | AUTO_INCREMENT |
        | post_id    | bigint(20)   | NO   | NULL    |                |
        | user_id    | bigint(20)   | NO   | NULL    |                |
        | meta_key   | varchar(255) | YES  | NULL    |                |
        | meta_value | longtext     | YES  | NULL    |                |

    Example: PRIMARY KEY 為 meta_id
      When 呼叫 CreateTable::create_itemmeta_table()
      Then 表的 PRIMARY KEY 為 meta_id

    Example: 建立必要的 index
      When 呼叫 CreateTable::create_itemmeta_table()
      Then 表建立以下 index：
        | key_name  | column             |
        | post_id   | post_id            |
        | user_id   | user_id            |
        | meta_key  | meta_key(191)      |

    Example: 使用 $wpdb->get_charset_collate() 作為 charset collate
      When 呼叫 CreateTable::create_itemmeta_table()
      Then CREATE TABLE 語句尾端包含 $wpdb->get_charset_collate() 的結果

  Rule: 後置（狀態）- 全域 $wpdb->access_itemmeta 設定

    Example: 建表時將 full table name 暫存到 $wpdb->access_itemmeta
      Given wp_{prefix}ph_access_itemmeta 表尚未存在
      When 呼叫 CreateTable::create_itemmeta_table()
      Then $wpdb->access_itemmeta 等於 "$wpdb->prefix + 'ph_access_itemmeta'"

  Rule: 前置（常數）- 資料表名稱常數

    Example: ACCESS_ITEMMETA_TABLE_NAME 常數為 "ph_access_itemmeta"
      When 讀取 CreateTable::ACCESS_ITEMMETA_TABLE_NAME
      Then 回傳字串 "ph_access_itemmeta"

    Example: MetaCRUD::$table_name 等於 CreateTable::ACCESS_ITEMMETA_TABLE_NAME
      When 讀取 MetaCRUD::$table_name
      Then 值為 "ph_access_itemmeta"

  Rule: 前置（相容）- AbstractTable 已存在時直接 return（避免重複定義）

    Example: 若 AbstractTable 類別已於 composer 其他套件定義則不載入本檔
      Given class_exists('AbstractTable') 為 true
      When 載入 inc/classes/Domains/Limit/Utils/CreateTable.php
      Then 檔案 require 後直接 return，不定義 CreateTable class

  Rule: 前置（錯誤）- dbDelta 拋出例外時 wrap 後往外拋

    Example: dbDelta 失敗時重新拋出 Exception
      Given dbDelta 因 SQL 錯誤拋出 Throwable
      When 呼叫 CreateTable::create_itemmeta_table()
      Then 捕捉到 Throwable
      And 以 new Exception($throwable->getMessage()) 形式重新拋出
