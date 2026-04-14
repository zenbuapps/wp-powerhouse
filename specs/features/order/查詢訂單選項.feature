@ignore @query
Feature: 查詢訂單選項

  Background:
    Given Powerhouse 外掛已啟用
    And WooCommerce 已啟用

  Rule: 後置（狀態）- 回傳訂單管理所需的選項資料

    Example: 成功查詢訂單選項
      When 管理員發送 GET /wp-json/v2/powerhouse/orders/options
      Then 應回傳 200
      And response body 包含以下欄位：
        | 欄位名   | 說明                        |
        | statuses | WooCommerce 所有訂單狀態列表 |

  Rule: 後置（狀態）- 子外掛可透過 filter 擴展回傳值

    Example: 子外掛透過 powerhouse/order/get_options filter 新增額外選項
      Given 子外掛已掛載 powerhouse/order/get_options filter
      When 管理員發送 GET /wp-json/v2/powerhouse/orders/options
      Then 應回傳 200
      And 回應包含子外掛新增的欄位
