@ignore @model
Feature: 訂單資訊聚合

  描述 Order Domain 內部的 Info 工具類別（Domains/Order/Utils/Info.php）。
  因 WooCommerce billing/shipping 欄位數量眾多，此 abstract class 提供統一方法
  一次取得所有相關欄位並組合成結構化陣列，用於訂單 API 回應與使用者地址讀取。

  Background:
    Given Powerhouse 外掛已啟用
    And WooCommerce 已啟用

  Rule: Info 定義固定的 billing/shipping 欄位集合

    Example: billing 與 shipping 共用 11 個基本欄位
      Given Info 類別的 $fields 私有屬性
      Then 包含以下欄位：first_name, last_name, email, phone, company, postcode, country, state, city, address_1, address_2

    Example: types 僅包含 billing 與 shipping
      Given Info 類別的 $types 私有屬性
      Then 為 ["billing", "shipping"]

  Rule: get_billing_fields 依照 prefix 參數決定是否加前綴

    Example: 預設加上 "billing_" 前綴
      When 呼叫 Info::get_billing_fields()
      Then 回傳 ["billing_first_name", "billing_last_name", "billing_email", "billing_phone", "billing_company", "billing_postcode", "billing_country", "billing_state", "billing_city", "billing_address_1", "billing_address_2"]

    Example: prefix 為 false 時回傳原始欄位名
      When 呼叫 Info::get_billing_fields(false)
      Then 回傳 ["first_name", "last_name", ..., "address_2"]（不含前綴，共 11 個）

  Rule: get_shipping_fields 排除 company 欄位

    Example: shipping 欄位不含 company
      When 呼叫 Info::get_shipping_fields(false)
      Then 回傳陣列不包含 "company"
      And 共 10 個欄位

    Example: 預設加上 "shipping_" 前綴
      When 呼叫 Info::get_shipping_fields()
      Then 回傳以 "shipping_" 為前綴的 10 個欄位
      And 不含 "shipping_company"

  Rule: to_order_array 從 WC_Order 聚合 billing/shipping 資訊

    Example: 訂單存在時回傳雙層 billing/shipping 結構
      Given 訂單 ID 為 123 且 wc_get_order(123) 回傳有效 WC_Order
      And 該訂單具有 get_billing_first_name、get_shipping_first_name 等 getter
      When 呼叫 Info::to_order_array(123)
      Then 回傳結構為 ["billing" => [...], "shipping" => [...]]
      And billing 陣列包含 11 個欄位（含 company）
      And shipping 陣列包含 10 個欄位（不含 company）
      And 各欄位值透過 $order->get_{type}_{field}() 動態呼叫取得

    Example: 訂單不存在時回傳空陣列
      Given wc_get_order(999) 回傳 false
      When 呼叫 Info::to_order_array(999)
      Then 回傳空陣列 []

    Example: 訂單缺少某個 getter method 時略過該欄位
      Given 訂單物件不存在 get_billing_company 方法
      When 呼叫 to_order_array
      Then billing 陣列不包含 "company" key
      And 其他有 getter 的欄位照常填入

  Rule: to_user_array 從 user meta 聚合 billing/shipping 資訊

    Example: 從 user meta 讀取 billing/shipping 欄位
      Given 用戶 ID 為 5
      When 呼叫 Info::to_user_array(5)
      Then 回傳結構為 ["billing" => [...], "shipping" => [...]]
      And billing 陣列包含 11 個欄位（含 company）
      And shipping 陣列包含 10 個欄位（不含 company）
      And 各欄位值透過 get_user_meta(5, "{type}_{field}", true) 取得
