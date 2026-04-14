@ignore @system-behavior
Feature: Email 網域驗證器

  mu-plugin 層級的 Email 網域 MX 記錄驗證機制。在用戶註冊和系統發送
  Email 之前，驗證收件者 Email 網域是否設有郵件伺服器（MX 記錄），
  阻擋無效網域的註冊和發信，減少退信率。

  Background:
    Given powerhouse-email-validator.php 已安裝至 wp-content/mu-plugins/
    And powerhouse_settings option 已存在

  # ---------------------------------------------------------------------------
  # 設定初始化
  # ---------------------------------------------------------------------------

  Rule: 前置（狀態）- 根據 powerhouse_settings 決定驗證範圍

    Example: 預設同時啟用註冊驗證和發信驗證
      Given powerhouse_settings 中不存在 enable_email_domain_check_register 設定
      And powerhouse_settings 中不存在 enable_email_domain_check_wp_mail 設定
      When mu-plugin 初始化
      Then 註冊驗證功能啟用（預設 yes）
      And 發信驗證功能啟用（預設 yes）

    Example: 停用註冊驗證
      Given powerhouse_settings.enable_email_domain_check_register 為 "no"
      When mu-plugin 初始化
      Then registration_errors filter 不被註冊
      And WooCommerce 註冊驗證不被註冊

    Example: 停用發信驗證
      Given powerhouse_settings.enable_email_domain_check_wp_mail 為 "no"
      When mu-plugin 初始化
      Then pre_wp_mail filter 不被註冊

  # ---------------------------------------------------------------------------
  # 白名單
  # ---------------------------------------------------------------------------

  Rule: 前置（狀態）- 白名單網域跳過 MX 檢查

    Example: 預設白名單包含常見郵件服務商
      Given powerhouse_settings 中不存在 email_domain_check_white_list 設定
      When mu-plugin 初始化
      Then 白名單包含 gmail.com, yahoo.com, hotmail.com, outlook.com, icloud.com

    Example: 自訂白名單
      Given powerhouse_settings.email_domain_check_white_list 為：
        | domain        |
        | company.com   |
        | partner.org   |
      When mu-plugin 初始化
      Then 白名單僅包含 company.com 和 partner.org

    Example: 白名單比對不區分大小寫
      Given 白名單包含 "gmail.com"
      When 驗證 Email "user@Gmail.COM"
      Then 白名單匹配成功
      And 跳過 MX 記錄檢查

  # ---------------------------------------------------------------------------
  # 註冊驗證
  # ---------------------------------------------------------------------------

  Rule: 前置（狀態）- 前台註冊時驗證 Email 網域

    Example: Email 網域有 MX 記錄時註冊成功
      Given 註冊驗證功能已啟用
      And "valid-domain.com" 網域有 MX 記錄
      When 用戶以 Email "user@valid-domain.com" 進行 WordPress 標準註冊
      Then 不添加 registration_errors 錯誤
      And 註冊流程繼續

    Example: Email 網域無 MX 記錄時阻擋註冊
      Given 註冊驗證功能已啟用
      And "no-mx.example" 網域沒有 MX 記錄
      When 用戶以 Email "user@no-mx.example" 進行 WordPress 標準註冊
      Then registration_errors 添加 "invalid_email_domain" 錯誤
      And 錯誤訊息為「該 Email 網域未設置郵件伺服器，請使用有效的郵件網域」

    Example: WooCommerce My Account 註冊也受到驗證
      Given 註冊驗證功能已啟用
      And "no-mx.example" 網域沒有 MX 記錄
      When 用戶以 Email "user@no-mx.example" 進行 WooCommerce 前台註冊
      Then woocommerce_registration_errors 添加 "invalid_email_domain" 錯誤

    Example: 白名單網域的 Email 直接通過
      Given 註冊驗證功能已啟用
      And 白名單包含 "gmail.com"
      When 用戶以 Email "user@gmail.com" 進行註冊
      Then 不進行 MX 記錄檢查
      And 註冊流程繼續

  # ---------------------------------------------------------------------------
  # 發信驗證
  # ---------------------------------------------------------------------------

  Rule: 前置（狀態）- 發送 Email 前驗證收件者網域

    Example: 收件者網域有效時正常發送
      Given 發信驗證功能已啟用
      And "valid-domain.com" 網域有 MX 記錄
      When 系統透過 wp_mail 發送 Email 給 "user@valid-domain.com"
      Then pre_wp_mail 回傳 null（不中斷）
      And Email 正常發送

    Example: 收件者網域無效時中斷發送
      Given 發信驗證功能已啟用
      And "no-mx.example" 網域沒有 MX 記錄
      When 系統透過 wp_mail 發送 Email 給 "user@no-mx.example"
      Then pre_wp_mail 回傳 false
      And Email 不被發送

    Example: 多個收件者中有一個無效則全部不發送
      Given 發信驗證功能已啟用
      And "valid.com" 網域有 MX 記錄
      And "no-mx.example" 網域沒有 MX 記錄
      When 系統透過 wp_mail 發送 Email 給 "user@valid.com, bad@no-mx.example"
      Then pre_wp_mail 回傳 false
      And 所有收件者的 Email 都不被發送

    Example: 收件者為陣列格式
      Given 發信驗證功能已啟用
      And "valid.com" 網域有 MX 記錄
      When 系統透過 wp_mail 發送 Email，to 為 ["user1@valid.com", "user2@valid.com"]
      Then 每個 Email 都通過驗證
      And Email 正常發送

    Example: to 欄位為空時中斷發送
      Given 發信驗證功能已啟用
      When 系統透過 wp_mail 發送 Email，to 為空字串
      Then pre_wp_mail 回傳 false

  # ---------------------------------------------------------------------------
  # Email 格式處理
  # ---------------------------------------------------------------------------

  Rule: 邊界條件 - 支援多種 Email 格式

    Example: 標準 Email 格式
      When 驗證 Email "user@example.com"
      Then 解析出網域 "example.com"
      And 進行 MX 記錄檢查

    Example: 帶顯示名稱的 Email 格式
      When 驗證 Email "Test User <test@example.com>"
      Then 從格式中提取出 "test@example.com"
      And 解析出網域 "example.com"

    Example: 無效的 Email 格式
      When 驗證 Email "not-an-email"
      Then 拋出例外「無效的 Email 格式」

    Example: 缺少 @ 符號的 Email
      When 驗證 Email "userexample.com"
      Then 拋出例外「無效的 Email 格式」

  # ---------------------------------------------------------------------------
  # 邊界條件
  # ---------------------------------------------------------------------------

  Rule: 邊界條件 - powerhouse_settings 不存在或格式錯誤

    Example: powerhouse_settings option 不存在
      Given wp_options 中不存在 powerhouse_settings
      When mu-plugin 初始化
      Then 使用預設值：註冊驗證啟用、發信驗證啟用
      And 白名單使用預設的 5 個網域

    Example: email_domain_check_white_list 不是陣列
      Given powerhouse_settings.email_domain_check_white_list 為字串 "gmail.com"
      When mu-plugin 初始化
      Then 白名單被轉換為空陣列

  # ---------------------------------------------------------------------------
  # mu-plugin 安裝機制
  # ---------------------------------------------------------------------------

  Rule: 系統行為 - mu-plugin 檔案由 MuPluginsLoader 自動安裝

    Example: Powerhouse 版本更新時自動複製 mu-plugin
      Given Powerhouse 外掛已啟用
      When powerhouse_compatibility_action_scheduler 排程執行
      Then 系統複製 powerhouse-email-validator.php 到 mu-plugins 目錄

  # ---------------------------------------------------------------------------
  # 內部實作：registration_errors_validate / check_admin_email_domain / pre_wp_mail_validate
  # ---------------------------------------------------------------------------

  Rule: 內部實作 - registration_errors_validate() 以 try/catch 包裝驗證並聚合錯誤

    Example: 驗證通過時原封不動回傳 $errors
      Given $errors 為空的 WP_Error
      And $user_email 為 "user@valid-domain.com"
      And "valid-domain.com" 有 MX 記錄
      When registration_errors_validate($errors, "user1", "user@valid-domain.com") 被呼叫
      Then validate_email_domain 回傳 true
      And 不新增任何 error 到 $errors
      And 回傳原本的 WP_Error 物件

    Example: 驗證失敗時以 invalid_email_domain code 新增 error
      Given $errors 為空的 WP_Error
      And $user_email 為 "user@no-mx.example"
      And "no-mx.example" 沒有 MX 記錄
      When registration_errors_validate($errors, "user1", "user@no-mx.example") 被呼叫
      Then validate_email_domain 拋出 \Exception
      And $errors->add("invalid_email_domain", "該 Email 網域未設置郵件伺服器，請使用有效的郵件網域") 被呼叫
      And 回傳含錯誤的 WP_Error 物件

    Example: Email 格式錯誤時新增 "無效的 Email 格式" 錯誤
      Given $user_email 為 "not-an-email"
      When registration_errors_validate($errors, "user1", "not-an-email") 被呼叫
      Then validate_email_domain 拋出 \Exception("無效的 Email 格式")
      And $errors->add("invalid_email_domain", "無效的 Email 格式") 被呼叫

    Example: 綁定至 registration_errors 與 woocommerce_registration_errors
      When EmailValidator 初始化且 $this->settings->register 為 true
      Then add_filter("registration_errors", [$this, "registration_errors_validate"], 10, 3) 被呼叫
      And add_filter("woocommerce_registration_errors", [$this, "registration_errors_validate"], 10, 3) 被呼叫

  Rule: 內部實作 - check_admin_email_domain() 僅在新建用戶時觸發驗證

    Example: $update 為 true 時直接 return
      Given $errors 為空的 WP_Error
      And $update 為 true
      And $user->user_email 為 "user@no-mx.example"
      When check_admin_email_domain($errors, true, $user) 被呼叫
      Then 函式提早 return
      And 不呼叫 registration_errors_validate
      And $errors 維持不變

    Example: $update 為 false 且 user_email 存在時委派給 registration_errors_validate
      Given $errors 為空的 WP_Error
      And $update 為 false
      And $user->user_login 為 "admin1"
      And $user->user_email 為 "admin@example.com"
      When check_admin_email_domain($errors, false, $user) 被呼叫
      Then 內部呼叫 registration_errors_validate($errors, "admin1", "admin@example.com")

    Example: $user 物件無 user_email 屬性時不執行驗證
      Given $errors 為空的 WP_Error
      And $update 為 false
      And $user 物件未設置 user_email 屬性
      When check_admin_email_domain($errors, false, $user) 被呼叫
      Then isset($user->user_email) 為 false
      And 不呼叫 registration_errors_validate

    Example: check_admin_email_domain 目前被註解掉不掛載 hook
      When EmailValidator 初始化
      Then add_action("user_profile_update_errors", ...) 被註解掉
      And check_admin_email_domain method 實際上不會被 WordPress 觸發
      But method 本身仍為 public 可被直接呼叫（例如測試）

  Rule: 內部實作 - pre_wp_mail_validate() 全部收件者通過才放行

    Example: to 為空字串時回傳 false
      Given $atts["to"] 為 ""
      When pre_wp_mail_validate($return, $atts) 被呼叫
      Then 函式偵測 !$to 為 true
      And 回傳 false（中斷發送）

    Example: to 為單一字串時以逗號拆分逐一驗證
      Given $atts["to"] 為 "user1@valid.com, user2@valid.com"
      And "valid.com" 有 MX 記錄
      When pre_wp_mail_validate($return, $atts) 被呼叫
      Then explode(",", $to) 產出 ["user1@valid.com", " user2@valid.com"]
      And 對每一封 Email 呼叫 validate_email_domain
      And 全部通過後回傳原本的 $return（通常為 null）

    Example: to 為陣列時雙層遍歷驗證
      Given $atts["to"] 為 ["user1@valid.com", "user2@valid.com, user3@valid.com"]
      When pre_wp_mail_validate($return, $atts) 被呼叫
      Then 對每個陣列元素再以 explode(",") 拆分
      And 對拆分後的每一封 Email 呼叫 validate_email_domain
      And 全部通過後回傳 $return

    Example: 任一收件者驗證失敗即 catch 例外並回傳 false
      Given $atts["to"] 為 "user@valid.com, bad@no-mx.example"
      And "valid.com" 有 MX 記錄
      And "no-mx.example" 沒有 MX 記錄
      When pre_wp_mail_validate($return, $atts) 被呼叫
      Then validate_email_domain("bad@no-mx.example") 拋出 \Exception
      And try/catch 捕捉 \Throwable
      And 回傳 false（中斷所有收件者的發送）

    Example: 僅在 $this->settings->wp_mail 為 true 時掛載 pre_wp_mail filter
      Given powerhouse_settings.enable_email_domain_check_wp_mail 為 "no"
      When EmailValidator 初始化
      Then add_filter("pre_wp_mail", ...) 不被呼叫
      And pre_wp_mail_validate 方法不會被 WordPress 觸發
