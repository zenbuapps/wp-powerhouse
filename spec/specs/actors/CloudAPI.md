# Cloud API

## 描述

外部授權碼驗證伺服器 `cloud.luke.cafe`（生產環境），提供授權碼的啟用、棄用、回呼清除快取等功能。

## 環境對應

| 環境 | URL |
|------|-----|
| local（辦公室）| `http://cloud.local` |
| staging | `https://cloud-staging.wpsite.pro` |
| production | `https://cloud.luke.cafe` |

## 關鍵端點

- `POST /wp-json/power-partner-server/license-codes/activate`
- `POST /wp-json/power-partner-server/license-codes/deactivate`
- 主動回呼：`POST /wp-json/v2/powerhouse/lc/invalidate`（清除快取）

## 認證方式

HTTP Basic Auth（`powerpartner` + 密碼，依環境不同）
