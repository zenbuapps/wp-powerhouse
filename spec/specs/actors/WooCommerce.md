# WooCommerce

## 描述

外部電商外掛，作為訂單完成、訂閱狀態變更等事件的觸發來源。Powerhouse 透過 WooCommerce hooks 整合存取權限生命週期。

## 相依性

- 部分 Powerhouse API 需要 WooCommerce 啟用（Product、Order、Limit、Copy、Report、Woocommerce 等域）
- WooCommerce Subscriptions 外掛：啟用後激活 `Subscription\Core\LifeCycle`，處理訂閱生命週期事件

## 關鍵觸發事件

- `woocommerce_subscription_payment_complete`（首次付款成功）
- `woocommerce_subscription_pre_update_status`（訂閱狀態變更）
- `wcs_renewal_order_created`（續訂訂單建立）
- `woocommerce_scheduled_subscription_*`（定時任務觸發）
