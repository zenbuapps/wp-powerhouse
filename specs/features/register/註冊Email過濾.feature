@ignore @system-behavior
Feature: 註冊 Email 過濾

  在用戶註冊時過濾 Email，確保 Email 網域設有郵件伺服器。
  透過 Domains\Register\Core\Filter 作為入口，
  載入 EmailValidator（mu-plugin 或 fallback）執行實際驗證。

  Background:
    Given Powerhouse 外掛已啟用
    And powerhouse_settings.enable_email_domain_check_register 為 "yes"

  # =========================================================
  # 載入策略
  # =========================================================

  Rule: 前置（狀態）- Filter 類別決定 EmailValidator 的載入來源

    Example: mu-plugin 類別已存在時跳過
      Given J7\Powerhouse\MU\EmailValidator 類別已載入（class_exists 為 true）
      When Domains\Register\Core\Filter 建構子執行
      Then 不執行任何額外載入
      And EmailValidator 功能由 mu-plugin 提供

    Example: mu-plugin 檔案存在但類別未載入時跳過
      Given J7\Powerhouse\MU\EmailValidator 類別未載入
      And wp-content/mu-plugins/powerhouse-email-validator.php 檔案存在
      When Domains\Register\Core\Filter 建構子執行
      Then 不執行額外載入（檔案存在表示會由 WordPress 自動載入）

    Example: mu-plugin 不存在時載入 fallback
      Given J7\Powerhouse\MU\EmailValidator 類別未載入
      And wp-content/mu-plugins/powerhouse-email-validator.php 檔案不存在
      When Domains\Register\Core\Filter 建構子執行
      Then require_once EmailValidator::instance()->get_file_path()
      And 載入 Compatibility/mu-plugins/powerhouse-email-validator.php
      And EmailValidator 功能由 plugin 內建版本提供

  # =========================================================
  # 與 EmailValidator 的關係
  # =========================================================

  Rule: 前置（狀態）- Filter 僅負責載入，驗證邏輯由 EmailValidator 處理

    Example: Filter 不直接掛載任何驗證 hook
      When Domains\Register\Core\Filter 初始化完成
      Then Filter 類別本身不掛載 registration_errors 或 woocommerce_registration_errors
      And 所有驗證 hook 由 EmailValidator 建構子掛載

  # =========================================================
  # Singleton 模式
  # =========================================================

  Rule: 前置（狀態）- Filter 使用 SingletonTrait

    Example: Filter 透過 instance() 取得唯一實例
      When 呼叫 Filter::instance()
      Then 回傳 Filter 的唯一實例
      And 建構子為 private（禁止外部 new）
