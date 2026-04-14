@ignore @system-behavior
Feature: 授權碼 Cloud API（Deprecated）

  J7\Powerhouse\Api\LC 是舊版授權碼 REST API 端點，已標記為 @deprecated。
  目前僅保留一個 "lc/invalidate" POST 端點作為相容層，實際邏輯完全委派給
  Domains\LC\Core\V2Api。新程式請直接使用 v2/powerhouse namespace 的授權碼 API。

  Background:
    Given Powerhouse 外掛已啟用
    And J7\Powerhouse\Domains\LC\Core\V2Api 可用

  # ---------------------------------------------------------------------------
  # Deprecated 狀態
  # ---------------------------------------------------------------------------

  Rule: 系統行為 - 類別以 @deprecated annotation 標記，不再擴充

    Example: 類別 PHPDoc 標記 deprecated
      Given 檢視 Api/LC.php 檔案
      When 讀取類別 PHPDoc
      Then PHPDoc 包含 "@deprecated 使用 Domains\LC\V2Api 取代"
      And 類別為 final
      And 使用 J7\WpUtils\Traits\SingletonTrait
      And 繼承自 J7\WpUtils\Classes\ApiBase

  # ---------------------------------------------------------------------------
  # 路由註冊
  # ---------------------------------------------------------------------------

  Rule: 系統行為 - 僅註冊 lc/invalidate 一個 POST 端點於 "powerhouse" namespace

    Example: 端點設定
      Given LC::instance() 完成 ApiBase 的 route 註冊流程
      When 檢視 $apis 陣列
      Then 只有一個項目
      And endpoint 為 "lc/invalidate"
      And method 為 "post"
      And permission_callback 為 "__return_true"（無權限檢查）

    Example: REST namespace 為舊版 "powerhouse"
      Given $namespace 屬性為 "powerhouse"
      When ApiBase 註冊路由
      Then 完整路由為 POST /wp-json/powerhouse/lc/invalidate
      Note: 新版路由為 v2/powerhouse/lc/invalidate，由 Domains\LC\Core\V2Api 提供

  # ---------------------------------------------------------------------------
  # 呼叫委派
  # ---------------------------------------------------------------------------

  Rule: 核心行為 - post_lc_invalidate_callback 直接委派給新版 V2Api

    Example: 請求進入後轉呼叫 V2Api
      Given 使用者發送 POST /wp-json/powerhouse/lc/invalidate
      When ApiBase 自動 dispatch 到 post_lc_invalidate_callback($request)
      Then 方法內部呼叫 LC_V2_Api::instance()->post_lc_invalidate_callback($request)
      And 原封不動回傳 V2Api 的 WP_REST_Response

    Example: 任何行為變動需在 V2Api 修改
      Given 需要調整授權碼快取清除邏輯
      When 開發者修改程式
      Then 修改發生在 Domains\LC\Core\V2Api
      And Api\LC 不需變動（因為只是轉呼叫）
