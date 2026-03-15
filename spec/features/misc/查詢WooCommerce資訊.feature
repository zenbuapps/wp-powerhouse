@ignore @query
Feature: 查詢WooCommerce資訊

  Background:
    Given Powerhouse 外掛已啟用

  Rule: 前置（狀態）- WooCommerce 必須已啟用

    Example: WooCommerce 未啟用時回傳 400
      Given WooCommerce 未安裝或未啟用
      When 管理員發送 GET /wp-json/v2/powerhouse/woocommerce
      Then 應回傳 400
      And code 為 "get_woocommerce_error"
      And message 為「WooCommerce 未啟用」

  Rule: 後置（狀態）- 回傳 WooCommerce 設定資訊

    Example: 成功取得 WooCommerce 資訊
      Given WooCommerce 已啟用
      When 管理員發送 GET /wp-json/v2/powerhouse/woocommerce
      Then 應回傳 200
      And code 為 "get_woocommerce_success"
      And data 包含 WooCommerce 設定物件（貨幣、店家資訊等）
