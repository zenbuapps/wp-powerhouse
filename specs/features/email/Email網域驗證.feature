@ignore @system-behavior
Feature: Email 網域驗證

  驗證 Email 網域是否設置郵件伺服器（MX record）。
  支援兩個獨立開關：註冊時驗證、發信時驗證。
  透過 mu-plugin 層級運行，在 WordPress 最早期載入。

  Background:
    Given Powerhouse 外掛已啟用

  # =========================================================
  # 啟用/停用條件 — 註冊驗證
  # =========================================================

  Rule: 前置（狀態）- enable_email_domain_check_register 控制註冊時驗證

    Example: 啟用註冊 Email 網域驗證（預設值）
      Given powerhouse_settings.enable_email_domain_check_register 為 "yes"
      When 系統初始化 EmailValidator
      Then registration_errors filter 被掛載
      And woocommerce_registration_errors filter 被掛載

    Example: 停用註冊 Email 網域驗證
      Given powerhouse_settings.enable_email_domain_check_register 為 "no"
      When 系統初始化 EmailValidator
      Then registration_errors filter 未被掛載
      And woocommerce_registration_errors filter 未被掛載

  # =========================================================
  # 啟用/停用條件 — 發信驗證
  # =========================================================

  Rule: 前置（狀態）- enable_email_domain_check_wp_mail 控制發信時驗證

    Example: 啟用發信 Email 網域驗證（預設值）
      Given powerhouse_settings.enable_email_domain_check_wp_mail 為 "yes"
      When 系統初始化 EmailValidator
      Then pre_wp_mail filter 被掛載

    Example: 停用發信 Email 網域驗證
      Given powerhouse_settings.enable_email_domain_check_wp_mail 為 "no"
      When 系統初始化 EmailValidator
      Then pre_wp_mail filter 未被掛載

  # =========================================================
  # 白名單機制
  # =========================================================

  Rule: 前置（狀態）- 白名單內的網域跳過 MX 檢查

    Example: gmail.com 在預設白名單中
      Given powerhouse_settings.email_domain_check_white_list 包含以下網域：
        | domain       |
        | gmail.com    |
        | yahoo.com    |
        | hotmail.com  |
        | outlook.com  |
        | icloud.com   |
      When 用戶以 "user@gmail.com" 註冊
      Then 跳過 MX record 檢查
      And 註冊流程繼續

    Example: 自訂白名單網域跳過檢查
      Given powerhouse_settings.email_domain_check_white_list 包含 "company.com"
      When 用戶以 "user@company.com" 註冊
      Then 跳過 MX record 檢查

    Example: 白名單比對不區分大小寫
      Given powerhouse_settings.email_domain_check_white_list 包含 "gmail.com"
      When 用戶以 "user@Gmail.COM" 註冊
      Then 網域轉為小寫後比對白名單
      And 跳過 MX record 檢查

  # =========================================================
  # 註冊時 Email 網域驗證
  # =========================================================

  Rule: 後置（狀態）- 註冊時 Email 網域有效則允許註冊

    Example: 網域有 MX record 時允許註冊
      Given powerhouse_settings.enable_email_domain_check_register 為 "yes"
      And "valid-domain.com" 有有效的 MX record
      When 用戶以 "user@valid-domain.com" 提交 WordPress 標準註冊
      Then registration_errors 不新增錯誤
      And 註冊流程繼續

    Example: WooCommerce 註冊網域有效
      Given powerhouse_settings.enable_email_domain_check_register 為 "yes"
      And "valid-domain.com" 有有效的 MX record
      When 用戶以 "user@valid-domain.com" 提交 WooCommerce 註冊
      Then woocommerce_registration_errors 不新增錯誤
      And 註冊流程繼續

  Rule: 後置（狀態）- 註冊時 Email 網域無效則阻止註冊

    Example: 網域無 MX record 時阻止註冊
      Given powerhouse_settings.enable_email_domain_check_register 為 "yes"
      And "no-mx-domain.xyz" 無 MX record
      When 用戶以 "user@no-mx-domain.xyz" 提交 WordPress 標準註冊
      Then registration_errors 新增錯誤碼 "invalid_email_domain"
      And 錯誤訊息為 "該 Email 網域未設置郵件伺服器，請使用有效的郵件網域"

    Example: WooCommerce 註冊網域無效
      Given powerhouse_settings.enable_email_domain_check_register 為 "yes"
      And "no-mx-domain.xyz" 無 MX record
      When 用戶以 "user@no-mx-domain.xyz" 提交 WooCommerce 註冊
      Then woocommerce_registration_errors 新增錯誤碼 "invalid_email_domain"
      And 錯誤訊息為 "該 Email 網域未設置郵件伺服器，請使用有效的郵件網域"

  # =========================================================
  # 發信時 Email 網域驗證
  # =========================================================

  Rule: 後置（狀態）- 發信前網域有效則允許發送

    Example: 收件人網域有效時允許發送
      Given powerhouse_settings.enable_email_domain_check_wp_mail 為 "yes"
      And "valid-domain.com" 有有效的 MX record
      When wp_mail 發送 Email 至 "user@valid-domain.com"
      Then pre_wp_mail filter 回傳 null（不阻擋）
      And Email 正常發送

  Rule: 後置（狀態）- 發信前網域無效則阻止發送

    Example: 收件人網域無效時阻止發送
      Given powerhouse_settings.enable_email_domain_check_wp_mail 為 "yes"
      And "no-mx-domain.xyz" 無 MX record
      When wp_mail 發送 Email 至 "user@no-mx-domain.xyz"
      Then pre_wp_mail filter 回傳 false
      And Email 不被發送
      And 不拋出錯誤（靜默失敗）

    Example: 收件人為空時阻止發送
      Given powerhouse_settings.enable_email_domain_check_wp_mail 為 "yes"
      When wp_mail 發送 Email 至空收件人
      Then pre_wp_mail filter 回傳 false

  # =========================================================
  # Email 格式解析
  # =========================================================

  Rule: 前置（狀態）- 支援多種 Email 格式

    Example: 標準 Email 格式
      When 驗證 "user@example.com"
      Then 解析出網域 "example.com"
      And 檢查 "example.com" 的 MX record

    Example: 含顯示名稱的 Email 格式
      When 驗證 "Test User <test@example.com>"
      Then 透過正則表達式 /([^<>\s]+@[^<>\s]+)/ 解析
      And 解析出 "test@example.com"
      And 檢查 "example.com" 的 MX record

    Example: 無效 Email 格式
      When 驗證 "not-an-email"
      Then 拋出 Exception "無效的 Email 格式"

    Example: Email 缺少 @ 符號
      When 驗證 "invalidemail"
      Then 拋出 Exception "無效的 Email 格式"

  # =========================================================
  # 多收件人處理（發信時）
  # =========================================================

  Rule: 後置（狀態）- 陣列形式的多收件人

    Example: 多個收件人全部有效
      Given powerhouse_settings.enable_email_domain_check_wp_mail 為 "yes"
      And wp_mail 的 to 參數為 ["user1@valid.com", "user2@valid.com"]
      And "valid.com" 有有效的 MX record
      When pre_wp_mail filter 執行
      Then 回傳 null（不阻擋），Email 正常發送

    Example: 多個收件人中有一個無效
      Given powerhouse_settings.enable_email_domain_check_wp_mail 為 "yes"
      And wp_mail 的 to 參數為 ["user1@valid.com", "user2@no-mx.xyz"]
      And "no-mx.xyz" 無 MX record
      When pre_wp_mail filter 執行
      Then 回傳 false，整封 Email 不被發送

  Rule: 後置（狀態）- 逗號分隔的多收件人

    Example: 逗號分隔的多個收件人全部有效
      Given powerhouse_settings.enable_email_domain_check_wp_mail 為 "yes"
      And wp_mail 的 to 參數為 "user1@valid.com,user2@valid.com"
      And "valid.com" 有有效的 MX record
      When pre_wp_mail filter 執行
      Then 回傳 null，Email 正常發送

    Example: 逗號分隔的多個收件人中有一個無效
      Given powerhouse_settings.enable_email_domain_check_wp_mail 為 "yes"
      And wp_mail 的 to 參數為 "user1@valid.com,user2@no-mx.xyz"
      And "no-mx.xyz" 無 MX record
      When pre_wp_mail filter 執行
      Then 回傳 false，整封 Email 不被發送

  # =========================================================
  # mu-plugin 載入機制
  # =========================================================

  Rule: 前置（狀態）- mu-plugin 優先於 plugin 內的 fallback

    Example: mu-plugin 存在時使用 mu-plugin 版本
      Given wp-content/mu-plugins/powerhouse-email-validator.php 檔案存在
      And J7\Powerhouse\MU\EmailValidator 類別已載入
      When Domains\Register\Core\Filter 初始化
      Then 跳過 fallback 載入
      And 使用 mu-plugin 版本的 EmailValidator

    Example: mu-plugin 不存在時使用 fallback
      Given wp-content/mu-plugins/powerhouse-email-validator.php 檔案不存在
      And J7\Powerhouse\MU\EmailValidator 類別未載入
      When Domains\Register\Core\Filter 初始化
      Then require_once 載入 Compatibility/mu-plugins/powerhouse-email-validator.php
      And 使用 plugin 內建版本的 EmailValidator

  # =========================================================
  # 設定初始化
  # =========================================================

  Rule: 前置（狀態）- 從 wp_options 讀取設定

    Example: wp_options 有設定值時使用設定值
      Given wp_options powerhouse_settings 包含：
        | key                                  | value |
        | enable_email_domain_check_register   | no    |
        | enable_email_domain_check_wp_mail    | yes   |
      When EmailValidator 初始化
      Then register 驗證停用
      And wp_mail 驗證啟用

    Example: wp_options 無設定值時使用預設值
      Given wp_options 不存在 powerhouse_settings
      When EmailValidator 初始化
      Then register 驗證啟用（預設 "yes"）
      And wp_mail 驗證啟用（預設 "yes"）
      And 白名單為預設列表（gmail.com, yahoo.com, hotmail.com, outlook.com, icloud.com）
