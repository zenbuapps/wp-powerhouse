@ignore @system-behavior
Feature: 管理員帳號

  依據 Powerhouse Settings 的 last_name_optional 設定，決定 WooCommerce
  會員中心「編輯我的帳號」頁面上的姓氏（last name）欄位是否為必填。
  僅在 WooCommerce 啟用時由 Bootstrap 載入。

  Background:
    Given WooCommerce 已啟用
    And Powerhouse 外掛已啟用

  # ---------------------------------------------------------------------------
  # 條件載入
  # ---------------------------------------------------------------------------

  Rule: 系統行為 - 依設定決定是否掛載 filter

    Example: last_name_optional = yes 時掛載 filter
      Given Settings::instance()->last_name_optional 經 wc_string_to_bool 轉換為 true
      When Admin\Account::__construct 執行
      Then woocommerce_save_account_details_required_fields filter 被註冊
      And filter callback 為 set_last_name_optional

    Example: last_name_optional = no 時不掛載 filter
      Given Settings::instance()->last_name_optional 經 wc_string_to_bool 轉換為 false
      When Admin\Account::__construct 執行
      Then 不註冊任何 filter
      And 不改變 WooCommerce 預設必填邏輯

  # ---------------------------------------------------------------------------
  # 姓氏非必填
  # ---------------------------------------------------------------------------

  Rule: 系統行為 - 從必填欄位中移除 account_last_name

    Example: 移除 account_last_name 欄位
      Given woocommerce_save_account_details_required_fields filter 觸發
      And $required_fields 陣列包含 'account_last_name' key
      When set_last_name_optional 執行
      Then 回傳的陣列不包含 'account_last_name' key
      And 其他必填欄位維持不變
      # 對應前台 my-account/edit-account/ 頁面的姓氏欄位
