@ignore @system-behavior
Feature: 停用 WordPress 功能

  mu-plugin 層級的安全性與效能優化。在 WordPress 最早期階段停用
  XML-RPC、REST API 用戶端點，以及移除中間圖片尺寸生成，
  降低攻擊面並減少儲存空間消耗。

  Background:
    Given powerhouse-disable-features.php 已安裝至 wp-content/mu-plugins/

  # ---------------------------------------------------------------------------
  # XML-RPC 停用
  # ---------------------------------------------------------------------------

  Rule: 系統行為 - XML-RPC 協定被完全停用

    Example: XML-RPC 請求被拒絕
      When 外部系統發送 POST /xmlrpc.php 請求
      Then WordPress 回傳 xmlrpc_enabled 為 false
      And XML-RPC 方法呼叫被拒絕

    Example: xmlrpc_enabled filter 以高優先級（999）執行
      When WordPress 載入 mu-plugin
      Then xmlrpc_enabled filter 以 priority 999 註冊
      And 確保覆蓋其他外掛可能的啟用設定

  # ---------------------------------------------------------------------------
  # REST API 用戶端點停用
  # ---------------------------------------------------------------------------

  Rule: 系統行為 - REST API 的用戶列表和單一用戶端點被移除

    Example: /wp/v2/users 端點被移除
      When 外部訪客發送 GET /wp-json/wp/v2/users 請求
      Then WordPress 回傳 404 或路由不存在錯誤
      # 防止用戶名稱列舉攻擊

    Example: /wp/v2/users/{id} 端點被移除
      When 外部訪客發送 GET /wp-json/wp/v2/users/1 請求
      Then WordPress 回傳 404 或路由不存在錯誤

    Example: 其他 REST API 端點不受影響
      When 管理員發送 GET /wp-json/wp/v2/posts 請求
      Then 正常回傳文章列表

    Example: rest_endpoints filter 以高優先級（999）執行
      When WordPress 載入 REST API 端點
      Then rest_endpoints filter 以 priority 999 註冊
      And 確保在其他外掛註冊端點之後再移除

  # ---------------------------------------------------------------------------
  # 圖片處理優化
  # ---------------------------------------------------------------------------

  Rule: 系統行為 - 移除所有中間圖片尺寸生成

    Example: 上傳圖片時不生成縮圖
      When 管理員上傳一張 2000x1500 的圖片
      Then WordPress 不生成 thumbnail、medium、large 等中間尺寸
      And 只保留原始尺寸檔案
      # intermediate_image_sizes_advanced 回傳空陣列

    Example: 大圖不被縮放
      When 管理員上傳一張 8000x6000 的圖片
      Then big_image_size_threshold 為 20000 像素
      And 圖片不會被 WordPress 自動縮放
      # 預設 WordPress 會在 2560px 時縮放

    Example: JPEG 品質保持 100%
      When 管理員上傳一張 JPEG 圖片
      Then JPEG 壓縮品質為 100（無損）
      And 不降低圖片品質
      # 預設 WordPress JPEG 品質為 82

  # ---------------------------------------------------------------------------
  # mu-plugin 安裝機制
  # ---------------------------------------------------------------------------

  Rule: 系統行為 - mu-plugin 檔案由 MuPluginsLoader 自動安裝

    Example: Powerhouse 版本更新時自動複製 mu-plugin
      Given Powerhouse 外掛已啟用
      When powerhouse_compatibility_action_scheduler 排程執行
      Then 系統複製 powerhouse-disable-features.php 到 mu-plugins 目錄
