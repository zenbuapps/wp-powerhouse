@ignore @system-behavior
Feature: API 加速器

  mu-plugin 層級的 API 效能優化機制。根據請求 URL 匹配預設規則，
  在 muplugins_loaded 階段覆寫 active_plugins，僅載入規則中指定的外掛，
  大幅減少不必要的外掛載入，提升 REST API 回應速度。

  Background:
    Given powerhouse-api-booster.php 已安裝至 wp-content/mu-plugins/
    And powerhouse_settings option 已存在

  # ---------------------------------------------------------------------------
  # 規則解析
  # ---------------------------------------------------------------------------

  Rule: 前置（狀態）- 僅載入已啟用（enabled=yes）且有有效 url_rules 的規則

    Example: 啟用的規則被載入
      Given powerhouse_settings.api_booster_rules 包含規則：
        | name     | enabled | rules                      | plugins                  |
        | 規則A    | yes     | /wp-json/v2/powerhouse/*   | powerhouse/plugin.php    |
      When mu-plugin 初始化
      Then 該規則被納入 api_booster_rules 陣列

    Example: 停用的規則被忽略
      Given powerhouse_settings.api_booster_rules 包含規則：
        | name     | enabled | rules                      | plugins                  |
        | 規則B    | no      | /wp-json/v2/powerhouse/*   | powerhouse/plugin.php    |
      When mu-plugin 初始化
      Then 該規則不被納入 api_booster_rules 陣列

    Example: url_rules 為空字串的規則被忽略
      Given powerhouse_settings.api_booster_rules 包含規則：
        | name     | enabled | rules | plugins               |
        | 規則C    | yes     |       | powerhouse/plugin.php |
      When mu-plugin 初始化
      Then 該規則不被納入 api_booster_rules 陣列

    Example: url_rules 包含多行時拆分為陣列並移除空白
      Given powerhouse_settings.api_booster_rules 包含規則，rules 欄位為多行字串：
        """
        /wp-json/v2/powerhouse/*
        /wp-json/wc/v3/*
        """
      When mu-plugin 初始化
      Then url_rules 被拆分為 2 個規則項目
      And 每個規則項目已去除前後空白

  # ---------------------------------------------------------------------------
  # URL 匹配邏輯
  # ---------------------------------------------------------------------------

  Rule: 前置（狀態）- 請求 URL 使用萬用字元模式匹配

    Example: 精確匹配路徑
      Given url_rules 包含 "/wp-json/v2/powerhouse/posts"
      When 收到 GET /wp-json/v2/powerhouse/posts 請求
      Then URL 匹配成功

    Example: 萬用字元匹配任意字元
      Given url_rules 包含 "/wp-json/v2/powerhouse/*"
      When 收到 GET /wp-json/v2/powerhouse/posts 請求
      Then URL 匹配成功

    Example: 萬用字元匹配多層路徑
      Given url_rules 包含 "/wp-json/v2/powerhouse/*"
      When 收到 GET /wp-json/v2/powerhouse/products/123 請求
      Then URL 匹配成功

    Example: 查詢參數不影響匹配
      Given url_rules 包含 "/wp-json/v2/powerhouse/posts"
      When 收到 GET /wp-json/v2/powerhouse/posts?per_page=10&page=1 請求
      Then URL 匹配成功（查詢參數被移除後再匹配）

    Example: 不匹配的路徑
      Given url_rules 包含 "/wp-json/v2/powerhouse/posts"
      When 收到 GET /wp-json/wp/v2/pages 請求
      Then URL 匹配失敗

  # ---------------------------------------------------------------------------
  # 外掛過濾
  # ---------------------------------------------------------------------------

  Rule: 後置（狀態）- 匹配成功時覆寫 active_plugins 僅載入指定外掛

    Example: API 請求匹配規則時僅載入指定外掛
      Given api_booster_rules 包含規則：
        | url_rules                  | plugins                                   |
        | /wp-json/v2/powerhouse/*   | powerhouse/plugin.php, woocommerce/wc.php |
      When 收到 GET /wp-json/v2/powerhouse/posts 請求
      Then option_active_plugins filter 覆寫 active_plugins 為規則中指定的外掛
      And 其他未列入的外掛不被 WordPress 載入

    Example: 多條規則按索引順序設定 filter 優先級
      Given api_booster_rules 包含 3 條規則
      When apply_rules 執行
      Then 第 0 條規則的 filter 優先級為 100
      And 第 1 條規則的 filter 優先級為 101
      And 第 2 條規則的 filter 優先級為 102

    Example: 沒有任何規則匹配時不影響外掛載入
      Given api_booster_rules 包含規則 url_rules 為 "/wp-json/custom/*"
      When 收到 GET /wp-json/v2/powerhouse/posts 請求
      Then option_active_plugins filter 不被添加
      And WordPress 正常載入所有 active_plugins

  # ---------------------------------------------------------------------------
  # 跳過條件
  # ---------------------------------------------------------------------------

  Rule: 前置（狀態）- 查詢外掛列表的 API 請求被跳過

    Example: /wp-json/v2/powerhouse/plugins 請求不套用加速規則
      Given api_booster_rules 包含規則 url_rules 為 "/wp-json/v2/powerhouse/*"
      When 收到 GET /wp-json/v2/powerhouse/plugins 請求
      Then api_booster_rules 不被載入
      And 所有外掛正常載入
      # 原因：外掛列表 API 需要精準的完整外掛資料

  # ---------------------------------------------------------------------------
  # 邊界條件
  # ---------------------------------------------------------------------------

  Rule: 邊界條件 - powerhouse_settings 不存在或格式錯誤時不套用規則

    Example: powerhouse_settings option 不存在
      Given wp_options 中不存在 powerhouse_settings
      When mu-plugin 初始化
      Then api_booster_rules 為空陣列
      And 所有外掛正常載入

    Example: api_booster_rules 不是陣列
      Given powerhouse_settings.api_booster_rules 為字串 "invalid"
      When mu-plugin 初始化
      Then api_booster_rules 被轉換為空陣列
      And 所有外掛正常載入

  # ---------------------------------------------------------------------------
  # mu-plugin 安裝機制
  # ---------------------------------------------------------------------------

  Rule: 系統行為 - mu-plugin 檔案由 MuPluginsLoader 自動安裝

    Example: Powerhouse 版本更新時自動複製 mu-plugin
      Given Powerhouse 外掛已啟用
      And Compatibility\Services\ApiBooster 繼承 MuPluginsLoader
      When powerhouse_compatibility_action_scheduler 排程執行
      Then 系統從 inc/classes/Compatibility/mu-plugins/powerhouse-api-booster.php
      And 複製到 wp-content/mu-plugins/powerhouse-api-booster.php

    Example: mu-plugins 目錄不存在時自動建立
      Given wp-content/mu-plugins/ 目錄不存在
      When MuPluginsLoader.move_file 執行
      Then 系統使用 WP_Filesystem 建立 mu-plugins 目錄
      And 然後複製檔案

    Example: 源檔案不存在時記錄錯誤
      Given inc/classes/Compatibility/mu-plugins/powerhouse-api-booster.php 不存在
      When MuPluginsLoader.move_file 執行
      Then 系統記錄 error 等級的 log
      And 不拋出例外

  # ---------------------------------------------------------------------------
  # 內部實作：only_load_required_plugins
  # ---------------------------------------------------------------------------

  Rule: 內部實作 - only_load_required_plugins() 決定是否覆寫 active_plugins

    Example: 任一 url_rule 匹配當前 URI 時覆寫 active_plugins
      Given api_booster_rule 為 {"url_rules": ["/wp-json/v2/powerhouse/*"], "plugins": ["powerhouse/plugin.php"]}
      And 當前 REQUEST_URI 為 "/wp-json/v2/powerhouse/posts"
      When only_load_required_plugins($api_booster_rule, 0) 被呼叫
      Then 系統遍歷 url_rules 呼叫 match_url_pattern
      And "/wp-json/v2/powerhouse/*" 匹配成功後 $some_strpos 為 true
      And add_filter("option_active_plugins", fn() => ["powerhouse/plugin.php"], 100) 被呼叫
      And filter 優先級為 100 + index（第 0 條規則為 100）

    Example: 所有 url_rules 皆不匹配時 early return
      Given api_booster_rule 為 {"url_rules": ["/wp-json/custom/*"], "plugins": ["custom/plugin.php"]}
      And 當前 REQUEST_URI 為 "/wp-json/v2/powerhouse/posts"
      When only_load_required_plugins($api_booster_rule, 0) 被呼叫
      Then $some_strpos 維持 false
      And 函式提早 return
      And add_filter("option_active_plugins", ...) 不被呼叫

    Example: 第一個匹配的 url_rule 即 break 迴圈
      Given api_booster_rule 的 url_rules 為 ["/wp-json/v2/powerhouse/*", "/wp-json/wc/v3/*", "/wp-json/wp/v2/*"]
      And 當前 REQUEST_URI 為 "/wp-json/v2/powerhouse/posts"
      When only_load_required_plugins($api_booster_rule, 0) 被呼叫
      Then 第一個 url_rule 匹配成功後立即 break
      And 不再檢查後續的 url_rule

    Example: 多條規則依 index 設定不同的 filter 優先級
      Given api_booster_rules 包含 3 條規則，全部皆匹配當前 URI
      When apply_rules() 依序對每條規則呼叫 only_load_required_plugins
      Then 第 0 條規則的 add_filter 優先級為 100
      And 第 1 條規則的 add_filter 優先級為 101
      And 第 2 條規則的 add_filter 優先級為 102

    Example: filter callback 以閉包回傳 plugins 陣列
      Given api_booster_rule.plugins 為 ["powerhouse/plugin.php", "woocommerce/woocommerce.php"]
      When only_load_required_plugins 註冊 filter 後
      And WordPress 呼叫 get_option("active_plugins")
      Then option_active_plugins filter 被觸發
      And 回傳 ["powerhouse/plugin.php", "woocommerce/woocommerce.php"]
      And 完全覆蓋原本的 active_plugins 陣列
