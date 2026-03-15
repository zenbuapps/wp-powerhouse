@ignore @command
Feature: 更新設定

  Background:
    Given Powerhouse 外掛已啟用
    And wp_options 中有 powerhouse_settings 記錄

  Rule: 前置（狀態）- 僅允許更新白名單欄位

    Example: 嘗試更新不在白名單的 key 被忽略
      When 管理員發送 POST /wp-json/v2/powerhouse/options，body 包含非白名單 key
      Then 應回傳 200
      And 該非白名單 key 不會被寫入 wp_options

  Rule: 後置（狀態）- 部分更新 powerhouse_settings

    Example: 成功更新部分設定欄位
      When 管理員發送 POST /wp-json/v2/powerhouse/options，body 為：
        | key                        | value |
        | powerhouse_settings        | {"enable_manual_send_email": "yes", "theme": "default"} |
      Then 應回傳 200
      And code 為 "post_user_success"
      And wp_options 中 powerhouse_settings 的 enable_manual_send_email 更新為 "yes"
      And wp_options 中 powerhouse_settings 的 theme 更新為 "default"
      And 其他未傳入的欄位保持不變

  Rule: 後置（狀態）- 子外掛可透過 filter 擴展允許更新的欄位

    Example: 子外掛透過 powerhouse/option/allowed_fields 新增白名單 key
      Given 子外掛已掛載 powerhouse/option/allowed_fields filter 並新增自訂 key
      When 管理員發送 POST /wp-json/v2/powerhouse/options，body 包含該自訂 key
      Then 應回傳 200
      And 該自訂 key 已寫入 wp_options

  Rule: 後置（狀態）- 可跳過特定 key 的 sanitize

    Example: 子外掛透過 powerhouse/option/skip_sanitize_keys 跳過 sanitize
      Given 子外掛已掛載 powerhouse/option/skip_sanitize_keys filter 並加入某 key
      When 管理員發送 POST /wp-json/v2/powerhouse/options，body 包含該 key 的原始值
      Then 應回傳 200
      And 該 key 的值不經 sanitize 直接寫入
