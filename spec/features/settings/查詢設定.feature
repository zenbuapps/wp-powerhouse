@ignore @query
Feature: 查詢設定

  Background:
    Given Powerhouse 外掛已啟用
    And wp_options 中有 powerhouse_settings 記錄

  Rule: 後置（狀態）- 回傳完整設定物件

    Example: 成功查詢設定
      When 管理員發送 GET /wp-json/v2/powerhouse/options
      Then 應回傳 200
      And code 為 "get_options_success"
      And data.powerhouse_settings 包含所有設定欄位：
        | 欄位名                              | 預設值                |
        | enable_manual_send_email            | no                  |
        | enable_captcha_login                | no                  |
        | captcha_role_list                   | [administrator]     |
        | enable_captcha_register             | no                  |
        | enable_email_domain_check_register  | yes                 |
        | enable_email_domain_check_wp_mail   | yes                 |
        | email_domain_check_white_list       | [gmail.com, ...]    |
        | delay_email                         | yes                 |
        | last_name_optional                  | yes                 |
        | theme                               | power               |
        | enable_theme_changer                | no                  |
        | enable_theme                        | yes                 |
        | theme_css                           | {}                  |
        | api_booster_rules                   | []                  |
        | api_booster_rule_recipes            | [...]               |
        | bunny_library_id                    |                     |
        | bunny_cdn_hostname                  |                     |
        | bunny_stream_api_key                |                     |

  Rule: 後置（狀態）- 子外掛可透過 filter 擴展回傳值

    Example: 子外掛透過 powerhouse/options/get_options filter 新增額外設定
      Given 子外掛已掛載 powerhouse/options/get_options filter 並新增自訂 key
      When 管理員發送 GET /wp-json/v2/powerhouse/options
      Then 回應中應包含子外掛新增的 key
