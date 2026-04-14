@ignore @system-behavior
Feature: Cloud API 通訊基底

  J7\Powerhouse\Api\Base 是 Powerhouse 與 cloud.luke.cafe 通訊的共用 HTTP client。
  提供環境感知（local / staging / production）、Basic Auth 憑證、預設 header 與
  timeout，並封裝 GET / POST / DELETE 三種方法。其他 Power 外掛（如 power-partner）
  也會依賴此類別，修改時需注意相容性。

  Background:
    Given Powerhouse 外掛已載入
    And J7\Powerhouse\Api\Base 類別可用

  # ---------------------------------------------------------------------------
  # 類別防衛
  # ---------------------------------------------------------------------------

  Rule: 系統行為 - 類別重複載入時不會拋出錯誤

    Example: 檔案頭部檢查類別是否已存在
      Given J7\Powerhouse\Api\Base 已經被載入過一次
      When 再次 require 或 autoload Api/Base.php
      Then 檔案頂層的 class_exists('J7\Powerhouse\Api\Base') 為 true
      And 檔案直接 return，不重新宣告類別

  # ---------------------------------------------------------------------------
  # 環境感知
  # ---------------------------------------------------------------------------

  Rule: 前置（環境）- 根據 wp_get_environment_type 與 IS_HOME 決定目標 cloud

    Example: local 環境 + IS_HOME 已定義時使用家用 cloud
      Given wp_get_environment_type() 回傳 "local"
      And defined('IS_HOME') 為 true
      When Base::instance() 被建構
      Then $base_url 為 "http://cloud.local"
      And $username 為 "j7.dev.gg"
      And $psw 為對應的 application password

    Example: local 環境 + 未定義 IS_HOME 時使用辦公室 cloud
      Given wp_get_environment_type() 回傳 "local"
      And IS_HOME 未定義
      When Base::instance() 被建構
      Then $base_url 為 "http://cloud.local"
      And $username 為 "powerpartner"
      And $psw 為辦公室對應的 application password

    Example: staging 環境使用 staging cloud
      Given wp_get_environment_type() 回傳 "staging"
      When Base::instance() 被建構
      Then $base_url 為 "https://cloud-staging.wpsite.pro"
      And $username 為 "powerpartner"

    Example: production 環境（預設）使用正式 cloud
      Given wp_get_environment_type() 回傳 "production" 或其他未匹配值
      When Base::instance() 被建構
      Then $base_url 為 "https://cloud.luke.cafe"
      And $username 為 "powerpartner"

  # ---------------------------------------------------------------------------
  # API URL 組合
  # ---------------------------------------------------------------------------

  Rule: 系統行為 - api_url 固定為 "{base_url}/wp-json/power-partner-server"

    Example: production 的 api_url
      Given $base_url 為 "https://cloud.luke.cafe"
      When Base::instance() 被建構
      Then $api_url 為 "https://cloud.luke.cafe/wp-json/power-partner-server"

  # ---------------------------------------------------------------------------
  # 預設請求參數
  # ---------------------------------------------------------------------------

  Rule: 系統行為 - default_args 包含 Basic Auth、JSON header、30 秒 timeout

    Example: default_args 結構
      Given Base::instance() 完成初始化
      When 檢視 $default_args
      Then headers.Content-Type 為 "application/json; charset=UTF-8"
      And headers.Authorization 為 "Basic " + base64_encode("{username}:{psw}")
      And headers.Origin 為 wp_parse_url(site_url(), PHP_URL_HOST) 的 host 部分
      And timeout 為 30

  # ---------------------------------------------------------------------------
  # HTTP 方法封裝
  # ---------------------------------------------------------------------------

  Rule: 核心行為 - remote_get 發送帶 Basic Auth 的 GET 請求

    Example: endpoint 被組合並附加 query 參數
      Given Base::instance() 已初始化為 production 環境
      When 呼叫 remote_get("lc/validate", ["code" => "ABC123"])
      Then 完整 URL 為 "https://cloud.luke.cafe/wp-json/power-partner-server/lc/validate?code=ABC123"
      And wp_remote_get 被呼叫並傳入 $default_args
      And 回傳 wp_remote_get 的原始結果（array 或 WP_Error）

    Example: 無 url_params 時不附加 query
      When 呼叫 remote_get("ping")
      Then URL 不含 "?"
      And 仍然使用 default_args

  Rule: 核心行為 - remote_post 發送帶 Basic Auth + JSON body 的 POST 請求

    Example: 有 body_params 時 body 被 JSON 編碼
      When 呼叫 remote_post("orders", ["order_id" => 123, "status" => "paid"])
      Then wp_remote_post 收到的 args.body 為 '{"order_id":123,"status":"paid"}'
      And args.headers 保留 default_args 的 header
      And 回傳 wp_remote_post 的原始結果

    Example: 無 body_params 時直接使用 default_args
      When 呼叫 remote_post("ping")
      Then args 等同 default_args（不附加 body 欄位）

  Rule: 核心行為 - remote_delete 透過 wp_remote_request 發送 DELETE

    Example: 有 body_params 時 body 被 JSON 編碼
      When 呼叫 remote_delete("orders/123", ["reason" => "cancel"])
      Then args.method 為 "DELETE"
      And args.body 為 JSON 字串
      And wp_remote_request 被呼叫
      And 回傳原始結果

    Example: 無 body_params 時仍設定 method 為 DELETE
      When 呼叫 remote_delete("cache")
      Then args 等同 default_args 加上 method="DELETE"

  # ---------------------------------------------------------------------------
  # 對外相依
  # ---------------------------------------------------------------------------

  Rule: 外部相容 - power-partner 外掛依賴此類別

    Example: 類別保持公開介面穩定
      Given power-partner 外掛直接呼叫 J7\Powerhouse\Api\Base::instance()
      When Powerhouse 修改此類別時
      Then 禁止移除或重新命名 remote_get / remote_post / remote_delete 的公開簽章
      And 環境感知的 switch 分支需保持回傳型別一致
