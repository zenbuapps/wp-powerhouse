# Event Storming: Powerhouse

> Powerhouse 是核心基礎外掛，為所有 `power-*` 外掛提供共用的 REST API、授權碼管理、存取權限控制、主題系統、驗證碼保護等通用功能。
> **版本:** 3.3.46 | **文件日期:** 2026-03-11

---

## Actors

- **管理員** [人]: WordPress 管理員，具備 `manage_options` 權限，操作後台設定、授權碼管理、資源 CRUD
- **系統** [系統]: Powerhouse 內部排程器、生命週期鉤子觸發的自動化流程
- **子外掛** [外部系統]: power-course、power-shop 等依賴 Powerhouse 的外掛，透過 filter/action 註冊產品資訊
- **Cloud API** [外部系統]: `cloud.luke.cafe` 授權碼驗證伺服器
- **WooCommerce** [外部系統]: 訂單、商品、訂閱等電商功能的觸發來源

---

## Aggregates

### Post（文章）
> WordPress 核心 Post，透過通用 CRUD API 操作所有文章類型

| 屬性 | 說明 |
|------|------|
| id | 文章 ID |
| post_type | 文章類型（post / page / 自訂 CPT） |
| post_title | 標題 |
| post_content | 內容 |
| post_excerpt | 摘要 |
| post_status | 狀態（publish / draft / pending / any） |
| post_parent | 父文章 ID |
| menu_order | 排序 |
| meta_data | 自訂 meta 資料 |

### User（用戶）
> WordPress 用戶，透過通用 CRUD API 管理

| 屬性 | 說明 |
|------|------|
| ID | 用戶 ID |
| user_login | 登入名稱 |
| user_email | 電子郵件 |
| display_name | 顯示名稱 |
| role | 角色 |
| meta_data | 自訂 meta 資料 |

### Settings（設定）
> wp_options 中的 `powerhouse_settings`，儲存所有外掛設定

| 屬性 | 說明 |
|------|------|
| enable_captcha_login | 啟用登入驗證碼（yes/no） |
| captcha_role_list | 需要驗證碼的角色列表 |
| enable_captcha_register | 啟用註冊驗證碼（yes/no） |
| enable_email_domain_check_register | 註冊前驗證 Email 網域（yes/no） |
| enable_email_domain_check_wp_mail | 發信前驗證 Email 網域（yes/no） |
| email_domain_check_white_list | Email 網域白名單 |
| delay_email | 延遲寄信（yes/no） |
| last_name_optional | 姓氏可選（yes/no） |
| theme | 主題名稱 |
| enable_theme | 啟用主題（yes/no） |
| enable_theme_changer | 啟用主題切換器（yes/no） |
| theme_css | 自訂主題 CSS |
| api_booster_rules | API 加速器規則 |
| bunny_library_id | BunnyCDN 圖庫 ID |
| bunny_cdn_hostname | BunnyCDN 主機名稱 |
| bunny_stream_api_key | BunnyCDN 串流 API 金鑰 |

### LicenseCode（授權碼）
> wp_options 中的 `powerhouse_license_codes` + transient `lc_{product_slug}`，管理子外掛的授權狀態

| 屬性 | 說明 |
|------|------|
| code | 授權碼 |
| post_status | 授權狀態（activated / available / 空字串） |
| expire_date | 到期日 |
| type | 授權類型 |
| product_slug | 產品 slug |
| product_name | 產品名稱 |

### AccessItemMeta（存取項目 Meta）
> 自訂表 `{prefix}ph_access_itemmeta`，紀錄每位用戶對每個內容項目的存取權限

| 屬性 | 說明 |
|------|------|
| meta_id | 主鍵 |
| post_id | 內容項目 ID |
| user_id | 用戶 ID |
| meta_key | Meta 鍵（如 expire_date） |
| meta_value | Meta 值（如到期時間戳記 / subscription_{id}） |

### Product（商品）
> WooCommerce 商品，需要 WooCommerce 啟用

| 屬性 | 說明 |
|------|------|
| id | 商品 ID |
| name | 商品名稱 |
| status | 狀態（publish / draft / pending） |
| regular_price | 原價 |
| sale_price | 特價 |
| description | 商品描述 |
| short_description | 短描述 |
| attributes | 商品屬性 |
| variations | 商品變體 |
| bound_items_data | 綁定的存取權限項目 |

### Order（訂單）
> WooCommerce 訂單，需要 WooCommerce 啟用

| 屬性 | 說明 |
|------|------|
| id | 訂單 ID |
| status | 訂單狀態 |
| customer_id | 客戶 ID |
| line_items | 訂單項目 |
| order_notes | 訂單備註 |

### Term（分類法詞彙）
> WordPress 分類法詞彙，如 product_cat、product_tag 等

| 屬性 | 說明 |
|------|------|
| term_id | 詞彙 ID |
| name | 名稱 |
| slug | Slug |
| taxonomy | 分類法 |
| parent | 父詞彙 ID |
| description | 描述 |
| order | 排序 |

### ProductAttribute（商品屬性）
> WooCommerce 全局商品屬性

| 屬性 | 說明 |
|------|------|
| id | 屬性 ID |
| name | 屬性名稱 |
| slug | Slug |
| type | 類型（select） |
| order_by | 排序方式 |
| has_archives | 是否啟用彙整頁 |

### Comment（評論）
> WordPress 評論

| 屬性 | 說明 |
|------|------|
| comment_id | 評論 ID |
| comment_content | 評論內容 |
| comment_type | 評論類型 |
| user_id | 評論者用戶 ID |

---

## Commands

### CreatePost（建立文章）
- **Actor**: 管理員
- **Aggregate**: Post
- **Predecessors**: 無
- **參數**: post_type, post_title, post_content?, post_excerpt?, post_status?, post_parent?, meta_data?, qty?
- **Description**:
  - What: 批量建立一或多篇文章
  - Why: 提供通用的文章建立 API
  - When: 管理員透過 REST API 發起

#### Rules
- 前置（參數）: qty 預設為 1
- 後置（狀態）: 建立成功後回傳所有新建文章 ID 陣列

### UpdatePost（更新文章）
- **Actor**: 管理員
- **Aggregate**: Post
- **Predecessors**: CreatePost
- **參數**: id（URL 路徑）, post_title?, post_content?, post_excerpt?, post_status?, meta_data?
- **Description**:
  - What: 更新指定文章
  - Why: 修改文章內容或 meta 資料
  - When: 管理員透過 REST API 發起

#### Rules
- 前置（參數）: id 必須是數字
- 前置（狀態）: 文章必須存在
- 後置（狀態）: 更新成功後回傳文章 ID

### DeletePost（刪除文章）
- **Actor**: 管理員
- **Aggregate**: Post
- **Predecessors**: CreatePost
- **參數**: id（URL 路徑）或 ids（批量）
- **Description**:
  - What: 將文章移至垃圾桶（單一或批量）
  - Why: 移除不需要的文章
  - When: 管理員透過 REST API 發起

#### Rules
- 前置（參數）: id 必須是數字；批量時 ids 必須是陣列
- 前置（狀態）: 文章必須存在
- 後置（狀態）: 文章狀態變更為 trash

### SortPosts（文章排序）
- **Actor**: 管理員
- **Aggregate**: Post
- **Predecessors**: CreatePost
- **參數**: from_tree, to_tree
- **Description**:
  - What: 更新文章的排序順序和父子關係
  - Why: 管理內容的顯示順序
  - When: 管理員透過拖曳排序後發起

#### Rules
- 前置（參數）: from_tree 和 to_tree 必須包含 id 欄位
- 後置（狀態）: 文章的 menu_order 和 post_parent 被更新

### CreateUser（建立用戶）
- **Actor**: 管理員
- **Aggregate**: User
- **Predecessors**: 無
- **參數**: user_login?, user_email?, display_name?, role?, meta_data?, qty?
- **Description**:
  - What: 批量建立一或多個用戶；或批量更新用戶（帶 ids 參數時）
  - Why: 管理 WordPress 用戶
  - When: 管理員透過 REST API 發起

#### Rules
- 前置（參數）: qty 預設為 1；若帶 ids 則為批量更新模式
- 後置（狀態）: 建立/更新成功後回傳用戶 ID 陣列

### UpdateUser（更新用戶）
- **Actor**: 管理員
- **Aggregate**: User
- **Predecessors**: CreateUser
- **參數**: id（URL 路徑）, display_name?, role?, meta_data?
- **Description**:
  - What: 更新指定用戶資料
  - Why: 修改用戶資訊
  - When: 管理員透過 REST API 發起

#### Rules
- 前置（參數）: id 必須是數字
- 後置（狀態）: 更新成功後回傳用戶 ID

### DeleteUser（刪除用戶）
- **Actor**: 管理員
- **Aggregate**: User
- **Predecessors**: CreateUser
- **參數**: id（URL 路徑）或 ids（批量）
- **Description**:
  - What: 永久刪除用戶（單一或批量）
  - Why: 移除帳號
  - When: 管理員透過 REST API 發起

#### Rules
- 前置（參數）: id 必須是數字；批量時 ids 必須是陣列
- 後置（狀態）: 用戶從資料庫中永久刪除

### ResetPassword（重設密碼）
- **Actor**: 管理員
- **Aggregate**: User
- **Predecessors**: CreateUser
- **參數**: ids（用戶 ID 陣列）
- **Description**:
  - What: 批量寄送重設密碼信
  - Why: 讓用戶重新設定密碼
  - When: 管理員透過 REST API 發起

#### Rules
- 前置（參數）: ids 不可為空
- 前置（狀態）: 用戶必須存在
- 後置（狀態）: 重設密碼信已寄出

### UpdateSettings（更新設定）
- **Actor**: 管理員
- **Aggregate**: Settings
- **Predecessors**: 無
- **參數**: powerhouse_settings（部分更新物件）
- **Description**:
  - What: 部分更新 Powerhouse 外掛設定
  - Why: 調整外掛行為（驗證碼、主題、延遲寄信等）
  - When: 管理員在設定頁面修改後儲存

#### Rules
- 前置（參數）: 只允許已註冊的欄位（透過 `powerhouse/option/allowed_fields` filter）
- 後置（狀態）: 設定值寫入 wp_options powerhouse_settings

### ActivateLicenseCode（啟用授權碼）
- **Actor**: 管理員
- **Aggregate**: LicenseCode
- **Predecessors**: 子外掛透過 `powerhouse_product_infos` filter 註冊產品
- **參數**: code, product_slug
- **Description**:
  - What: 向 Cloud API 啟用授權碼
  - Why: 解鎖子外掛的授權功能
  - When: 管理員在授權碼頁面輸入授權碼並啟用

#### Rules
- 前置（參數）: code 和 product_slug 為必填
- 前置（狀態）: Cloud API 回應 200
- 後置（狀態）: transient `lc_{product_slug}` 被設定（加密存放）；`powerhouse_license_codes` option 中儲存 code

### DeactivateLicenseCode（棄用授權碼）
- **Actor**: 管理員
- **Aggregate**: LicenseCode
- **Predecessors**: ActivateLicenseCode
- **參數**: code, product_slug
- **Description**:
  - What: 向 Cloud API 棄用授權碼
  - Why: 釋放授權碼以便在其他網站使用
  - When: 管理員在授權碼頁面點擊棄用

#### Rules
- 前置（參數）: code 和 product_slug 為必填
- 後置（狀態）: transient 被刪除；`powerhouse_license_codes` 中移除對應 product_slug

### InvalidateLicenseCodeCache（清除授權碼快取）
- **Actor**: 系統 / Cloud API（回呼）
- **Aggregate**: LicenseCode
- **Predecessors**: ActivateLicenseCode
- **參數**: product_slug
- **Description**:
  - What: 清除指定產品的授權碼 transient 快取
  - Why: 強制下次檢查重新向 Cloud API 驗證
  - When: Cloud API 主動回呼或管理員手動觸發

#### Rules
- 前置（參數）: product_slug 不可為空
- 後置（狀態）: transient `lc_{product_slug}` 被刪除

### UploadFile（上傳檔案）
- **Actor**: 管理員
- **Aggregate**: Post（attachment）
- **Predecessors**: 無
- **參數**: files（binary 陣列）, upload_only?
- **Description**:
  - What: 上傳一或多個檔案到 WordPress 媒體庫
  - Why: 提供通用的檔案上傳 API
  - When: 管理員透過 REST API 上傳

#### Rules
- 前置（參數）: files 必須存在於 form-data；MIME 類型需符合允許清單（若有設定）
- 後置（狀態）: upload_only=0 時新增到媒體庫並回傳 attachment_id；upload_only=1 時僅上傳到 uploads 目錄

### CopyPost（複製文章/商品）
- **Actor**: 管理員
- **Aggregate**: Post / Product
- **Predecessors**: CreatePost / CreateProduct
- **參數**: id（URL 路徑）
- **Description**:
  - What: 複製指定文章或商品（含子文章）
  - Why: 快速建立類似內容
  - When: 管理員透過 REST API 發起

#### Rules
- 前置（參數）: id 必須是數字
- 前置（狀態）: 原始文章/商品必須存在
- 後置（狀態）: 新建複製文章，回傳新 ID

### CreateProduct（建立商品）
- **Actor**: 管理員
- **Aggregate**: Product
- **Predecessors**: WooCommerce 啟用
- **參數**: name?, status?, regular_price?, meta_data?, qty?
- **Description**:
  - What: 批量建立一或多個商品
  - Why: 管理 WooCommerce 商品
  - When: 管理員透過 REST API 發起

#### Rules
- 前置（參數）: qty 預設為 1；若 action=update-many 則轉為批量更新
- 後置（狀態）: 建立成功後回傳商品 ID 陣列

### UpdateProduct（更新商品）
- **Actor**: 管理員
- **Aggregate**: Product
- **Predecessors**: CreateProduct
- **參數**: id（URL 路徑）, name?, status?, regular_price?, sale_price?, description?, meta_data?
- **Description**:
  - What: 更新指定商品
  - Why: 修改商品資訊
  - When: 管理員透過 REST API 發起

#### Rules
- 前置（參數）: id 必須是數字
- 前置（狀態）: 商品必須存在
- 後置（狀態）: 商品更新成功

### DeleteProduct（刪除商品）
- **Actor**: 管理員
- **Aggregate**: Product
- **Predecessors**: CreateProduct
- **參數**: id（URL 路徑）或 ids（批量），force_delete?
- **Description**:
  - What: 刪除商品（移至垃圾桶或永久刪除）
  - Why: 移除不需要的商品
  - When: 管理員透過 REST API 發起

#### Rules
- 前置（參數）: id 必須是數字；force_delete 決定永久刪除或移至垃圾桶
- 前置（狀態）: 商品必須存在
- 後置（狀態）: 商品被刪除

### UpdateProductAttributes（更新商品屬性）
- **Actor**: 管理員
- **Aggregate**: Product / ProductAttribute
- **Predecessors**: CreateProduct
- **參數**: id（商品 ID, URL 路徑）, new_attributes
- **Description**:
  - What: 更新商品的屬性設定，可同時建立全局屬性
  - Why: 管理商品的分類屬性與變體基礎
  - When: 管理員編輯商品屬性後儲存

#### Rules
- 前置（參數）: id 必須是數字；new_attributes 為屬性陣列
- 前置（狀態）: 商品必須存在
- 後置（狀態）: 商品屬性更新；若 is_taxonomy=true 則同時建立全局屬性

### CreateProductVariations（產生變體）
- **Actor**: 管理員
- **Aggregate**: Product
- **Predecessors**: UpdateProductAttributes
- **參數**: id（商品 ID, URL 路徑）
- **Description**:
  - What: 根據商品屬性自動產生所有可能的變體組合
  - Why: 自動化變體商品建立
  - When: 管理員設定完屬性後觸發

#### Rules
- 前置（參數）: id 必須是數字
- 前置（狀態）: 商品必須存在且有 variation 屬性
- 後置（狀態）: 新變體被建立；重複或不在組合內的舊變體被刪除

### UpdateProductVariations（更新變體）
- **Actor**: 管理員
- **Aggregate**: Product
- **Predecessors**: CreateProductVariations
- **參數**: id（商品 ID, URL 路徑）, default_attributes?, variations
- **Description**:
  - What: 批量更新變體商品的價格、庫存等資訊
  - Why: 統一管理變體資料
  - When: 管理員編輯變體後儲存

#### Rules
- 前置（參數）: id 必須是數字
- 前置（狀態）: 商品與變體商品必須存在
- 後置（狀態）: 變體商品資料更新

### BindItemsToProduct（綁定權限項目到商品）
- **Actor**: 管理員
- **Aggregate**: Product / AccessItemMeta
- **Predecessors**: CreateProduct
- **參數**: product_ids, item_ids, limit_type, limit_value?, limit_unit?, meta_key
- **Description**:
  - What: 將存取權限項目綁定到商品上
  - Why: 購買商品後自動授權用戶存取指定內容
  - When: 管理員設定商品與內容的關聯

#### Rules
- 前置（參數）: product_ids, item_ids, limit_type, meta_key 為必填
- 後置（狀態）: 商品的 post_meta `{meta_key}` 更新；`{meta_key}_ids` 也同步更新

### UpdateBoundItems（更新綁定權限）
- **Actor**: 管理員
- **Aggregate**: Product / AccessItemMeta
- **Predecessors**: BindItemsToProduct
- **參數**: product_ids, item_ids, limit_type, limit_value?, limit_unit?, meta_key
- **Description**:
  - What: 更新已綁定項目的存取權限設定
  - Why: 調整觀看期限等限制
  - When: 管理員修改綁定設定後儲存

#### Rules
- 前置（參數）: product_ids, item_ids, limit_type, meta_key 為必填
- 後置（狀態）: 商品的 post_meta `{meta_key}` 中對應項目的 limit 被更新

### UnbindItemsFromProduct（解除綁定權限項目）
- **Actor**: 管理員
- **Aggregate**: Product / AccessItemMeta
- **Predecessors**: BindItemsToProduct
- **參數**: product_ids, item_ids, meta_key
- **Description**:
  - What: 解除商品與存取權限項目的綁定
  - Why: 移除商品與內容的關聯
  - When: 管理員操作解除綁定

#### Rules
- 前置（參數）: product_ids, item_ids, meta_key 為必填
- 後置（狀態）: 商品的 post_meta `{meta_key}` 中移除對應項目

### GrantUserAccess（授權用戶存取）
- **Actor**: 管理員 / 系統（訂單完成後）
- **Aggregate**: AccessItemMeta
- **Predecessors**: BindItemsToProduct
- **參數**: user_ids, item_ids, expire_date
- **Description**:
  - What: 授權用戶存取指定內容項目
  - Why: 開通用戶的觀看/使用權限
  - When: 管理員手動授權或訂單完成後系統自動授權

#### Rules
- 前置（參數）: user_ids, item_ids, expire_date 為必填
- 後置（狀態）: `ph_access_itemmeta` 表中寫入/更新 expire_date 記錄

### UpdateUserAccess（更新用戶存取期限）
- **Actor**: 管理員
- **Aggregate**: AccessItemMeta
- **Predecessors**: GrantUserAccess
- **參數**: user_ids, item_ids, timestamp
- **Description**:
  - What: 批量更新用戶的存取到期時間
  - Why: 延長或縮短觀看期限
  - When: 管理員手動調整

#### Rules
- 前置（參數）: user_ids, item_ids, timestamp 為必填
- 後置（狀態）: `ph_access_itemmeta` 表中對應記錄的 expire_date 被更新

### RevokeUserAccess（撤銷用戶存取）
- **Actor**: 管理員 / 系統（訂閱失敗後）
- **Aggregate**: AccessItemMeta
- **Predecessors**: GrantUserAccess
- **參數**: user_ids, item_ids
- **Description**:
  - What: 撤銷用戶對指定內容項目的存取權限
  - Why: 移除觀看/使用權限
  - When: 管理員手動撤銷或訂閱失敗時系統自動撤銷

#### Rules
- 前置（參數）: user_ids, item_ids 為必填
- 後置（狀態）: `ph_access_itemmeta` 表中對應記錄被刪除

### CreateOrder（建立訂單）
- **Actor**: 管理員
- **Aggregate**: Order
- **Predecessors**: WooCommerce 啟用
- **參數**: 無
- **Description**:
  - What: 建立一筆空白訂單
  - Why: 手動建立訂單
  - When: 管理員透過 REST API 發起

#### Rules
- 後置（狀態）: 新訂單建立，狀態為 pending

### UpdateOrder（更新訂單）
- **Actor**: 管理員
- **Aggregate**: Order
- **Predecessors**: CreateOrder
- **參數**: id（URL 路徑）, status?, meta_data?
- **Description**:
  - What: 更新指定訂單
  - Why: 修改訂單狀態或資料
  - When: 管理員透過 REST API 發起

#### Rules
- 前置（參數）: id 必須是數字
- 前置（狀態）: 訂單必須存在
- 後置（狀態）: 訂單資料更新

### DeleteOrder（刪除訂單）
- **Actor**: 管理員
- **Aggregate**: Order
- **Predecessors**: CreateOrder
- **參數**: id（URL 路徑）或 ids（批量）
- **Description**:
  - What: 刪除訂單（單一或批量）
  - Why: 移除不需要的訂單
  - When: 管理員透過 REST API 發起

#### Rules
- 前置（參數）: id 必須是數字；批量時 ids 必須是陣列
- 前置（狀態）: 訂單必須存在
- 後置（狀態）: 訂單被刪除

### CreateOrderNote（新增訂單備註）
- **Actor**: 管理員
- **Aggregate**: Order
- **Predecessors**: CreateOrder
- **參數**: order_id, note, is_customer_note
- **Description**:
  - What: 為訂單新增備註
  - Why: 記錄訂單相關資訊或通知客戶
  - When: 管理員透過 REST API 發起

#### Rules
- 前置（參數）: order_id, note, is_customer_note 為必填
- 前置（狀態）: 訂單必須存在
- 後置（狀態）: 訂單備註已新增

### DeleteOrderNote（刪除訂單備註）
- **Actor**: 管理員
- **Aggregate**: Order
- **Predecessors**: CreateOrderNote
- **參數**: id（備註 ID, URL 路徑）
- **Description**:
  - What: 刪除指定訂單備註
  - Why: 移除錯誤或不需要的備註
  - When: 管理員透過 REST API 發起

#### Rules
- 前置（參數）: id 必須是數字
- 後置（狀態）: 訂單備註被刪除

### CreateTerm（建立詞彙）
- **Actor**: 管理員
- **Aggregate**: Term
- **Predecessors**: 無
- **參數**: taxonomy（URL 路徑）, name, slug?, parent?, description?, qty?
- **Description**:
  - What: 批量建立一或多個分類法詞彙
  - Why: 管理分類架構
  - When: 管理員透過 REST API 發起

#### Rules
- 前置（參數）: taxonomy 為必填路徑參數
- 後置（狀態）: 建立成功後回傳詞彙 ID 陣列

### UpdateTerm（更新詞彙）
- **Actor**: 管理員
- **Aggregate**: Term
- **Predecessors**: CreateTerm
- **參數**: taxonomy（URL 路徑）, id（URL 路徑）, name?, slug?, parent?, description?
- **Description**:
  - What: 更新指定詞彙
  - Why: 修改分類名稱或結構
  - When: 管理員透過 REST API 發起

#### Rules
- 前置（參數）: taxonomy 和 id 為必填
- 後置（狀態）: 詞彙更新成功

### DeleteTerm（刪除詞彙）
- **Actor**: 管理員
- **Aggregate**: Term
- **Predecessors**: CreateTerm
- **參數**: taxonomy（URL 路徑）, id（URL 路徑）或 ids（批量）
- **Description**:
  - What: 刪除詞彙（單一或批量）
  - Why: 移除不需要的分類
  - When: 管理員透過 REST API 發起

#### Rules
- 前置（參數）: taxonomy 為必填；id 必須是數字
- 後置（狀態）: 詞彙被刪除

### SortTerms（詞彙排序）
- **Actor**: 管理員
- **Aggregate**: Term
- **Predecessors**: CreateTerm
- **參數**: taxonomy（URL 路徑）, from_tree, to_tree
- **Description**:
  - What: 更新詞彙的排序順序和父子關係
  - Why: 管理分類的顯示順序
  - When: 管理員透過拖曳排序後發起

#### Rules
- 前置（參數）: from_tree 和 to_tree 必須包含 id 欄位
- 後置（狀態）: 詞彙的 order meta 和 parent 被更新

### CreateComment（建立評論）
- **Actor**: 管理員
- **Aggregate**: Comment
- **Predecessors**: 無
- **參數**: note, comment_type?, is_customer_note?, commented_user_id?
- **Description**:
  - What: 以當前登入用戶身份建立評論
  - Why: 新增評論或備註
  - When: 管理員透過 REST API 發起

#### Rules
- 後置（狀態）: 評論已建立，回傳 comment_id

### DeleteComment（刪除評論）
- **Actor**: 管理員
- **Aggregate**: Comment
- **Predecessors**: CreateComment
- **參數**: id（URL 路徑）
- **Description**:
  - What: 刪除指定評論
  - Why: 移除不需要的評論
  - When: 管理員透過 REST API 發起

#### Rules
- 前置（參數）: id 必須是數字
- 後置（狀態）: 評論被永久刪除

### CreateProductAttribute（建立商品屬性）
- **Actor**: 管理員
- **Aggregate**: ProductAttribute
- **Predecessors**: WooCommerce 啟用
- **參數**: name, slug, type?, order_by?, has_archives?
- **Description**:
  - What: 建立全局商品屬性
  - Why: 為商品建立分類維度
  - When: 管理員透過 REST API 發起

#### Rules
- 前置（參數）: name 和 slug 為必填
- 後置（狀態）: 全局屬性已建立

### UpdateProductAttribute（更新商品屬性）
- **Actor**: 管理員
- **Aggregate**: ProductAttribute
- **Predecessors**: CreateProductAttribute
- **參數**: id（URL 路徑）, name?, slug?, type?, order_by?, has_archives?
- **Description**:
  - What: 更新指定全局商品屬性
  - Why: 修改屬性設定
  - When: 管理員透過 REST API 發起

#### Rules
- 前置（參數）: id 必須是數字
- 後置（狀態）: 屬性更新成功

### DeleteProductAttribute（刪除商品屬性）
- **Actor**: 管理員
- **Aggregate**: ProductAttribute
- **Predecessors**: CreateProductAttribute
- **參數**: id（URL 路徑）
- **Description**:
  - What: 刪除指定全局商品屬性
  - Why: 移除不需要的屬性
  - When: 管理員透過 REST API 發起

#### Rules
- 前置（參數）: id 必須是數字
- 後置（狀態）: 屬性被刪除

---

## Read Models

### GetPosts（查詢文章列表）
- **Actor**: 管理員
- **Aggregates**: Post
- **回傳欄位**: id, post_title, post_content, post_status, post_type, post_parent, menu_order, meta_data, 分頁 headers
- **Description**: 通用的文章列表查詢，支援 post_type、分頁、排序、meta_keys 暴露

#### Rules
- 前置（參數）: post_type 預設 post；posts_per_page 預設 20；paged 預設 1；post_status 預設 any
- 後置（回應）: X-WP-Total / X-WP-TotalPages / X-WP-CurrentPage / X-WP-PageSize headers

### GetPost（查詢單一文章）
- **Actor**: 管理員
- **Aggregates**: Post
- **回傳欄位**: 完整文章資料（含 meta_keys 指定的 meta）
- **Description**: 取得指定 ID 的文章詳情

#### Rules
- 前置（參數）: id 必須是數字
- 前置（狀態）: 文章必須存在

### GetPostField（查詢文章單一欄位）
- **Actor**: 管理員
- **Aggregates**: Post
- **回傳欄位**: 指定欄位的值
- **Description**: 取得指定文章的單一欄位值（支援 post 欄位或 meta）

#### Rules
- 前置（參數）: id 和 field_name 為必填
- 前置（狀態）: 文章必須存在

### GetUsers（查詢用戶列表）
- **Actor**: 管理員
- **Aggregates**: User
- **回傳欄位**: id, user_login, user_email, display_name, role, meta_data, 分頁 headers
- **Description**: 通用的用戶列表查詢

#### Rules
- 前置（參數）: number 預設 20；paged 預設 1
- 後置（回應）: X-WP-Total / X-WP-TotalPages / X-WP-CurrentPage / X-WP-PageSize headers

### GetUser（查詢單一用戶）
- **Actor**: 管理員
- **Aggregates**: User
- **回傳欄位**: 完整用戶資料（edit 模式，含 meta_keys 指定的 meta）
- **Description**: 取得指定 ID 的用戶詳情

#### Rules
- 前置（參數）: id 必須是數字

### GetUserOptions（查詢用戶選項）
- **Actor**: 管理員
- **Aggregates**: User
- **回傳欄位**: roles（value/label 格式）
- **Description**: 取得可編輯的角色列表，供前端選單使用

### GetSettings（查詢設定）
- **Actor**: 管理員
- **Aggregates**: Settings
- **回傳欄位**: powerhouse_settings 完整設定物件
- **Description**: 取得所有 Powerhouse 外掛設定

### GetLicenseCodes（查詢授權碼狀態）
- **Actor**: 管理員
- **Aggregates**: LicenseCode
- **回傳欄位**: 各產品的授權碼狀態陣列（code, post_status, expire_date, type, product_slug, product_name）
- **Description**: 取得所有已註冊產品的授權碼啟用狀態

### GetProducts（查詢商品列表）
- **Actor**: 管理員
- **Aggregates**: Product
- **回傳欄位**: 商品資料陣列, 分頁 headers
- **Description**: 通用的 WooCommerce 商品列表查詢

#### Rules
- 前置（參數）: status 預設 [publish, draft, pending]；posts_per_page 預設 20
- 後置（回應）: X-WP-Total / X-WP-TotalPages headers

### GetProduct（查詢單一商品）
- **Actor**: 管理員
- **Aggregates**: Product
- **回傳欄位**: 完整商品資料
- **Description**: 取得指定 ID 的商品詳情

#### Rules
- 前置（參數）: id 必須是數字
- 前置（狀態）: 商品必須存在

### GetProductsSelect（商品選擇器查詢）
- **Actor**: 管理員
- **Aggregates**: Product
- **回傳欄位**: 精簡的商品列表（適合下拉選單），分頁 headers
- **Description**: 用於前端選擇器的商品搜尋 API，支援 ID 搜尋

### GetProductOptions（查詢商品選項）
- **Actor**: 管理員
- **Aggregates**: Product / Term
- **回傳欄位**: product_cats, product_tags, product_shipping_classes, top_sales_products, max_price, min_price
- **Description**: 取得商品相關的選項資料，供前端篩選器使用

### GetOrders（查詢訂單列表）
- **Actor**: 管理員
- **Aggregates**: Order
- **回傳欄位**: 訂單資料陣列, 分頁 headers
- **Description**: 通用的 WooCommerce 訂單列表查詢

#### Rules
- 前置（參數）: limit 預設 30；paged 預設 1；type 預設 shop_order
- 後置（回應）: X-WP-Total / X-WP-TotalPages headers

### GetOrder（查詢單一訂單）
- **Actor**: 管理員
- **Aggregates**: Order
- **回傳欄位**: 完整訂單資料（含行項目詳情）
- **Description**: 取得指定 ID 的訂單詳情

#### Rules
- 前置（參數）: id 必須是數字
- 前置（狀態）: 訂單必須存在

### GetOrderOptions（查詢訂單選項）
- **Actor**: 管理員
- **Aggregates**: Order
- **回傳欄位**: statuses（訂單狀態列表）
- **Description**: 取得訂單相關的選項資料，供前端篩選器使用

### GetTerms（查詢詞彙列表）
- **Actor**: 管理員
- **Aggregates**: Term
- **回傳欄位**: 詞彙資料陣列, 分頁 headers
- **Description**: 通用的分類法詞彙列表查詢，支援排序

#### Rules
- 前置（參數）: taxonomy 為必填路徑參數；posts_per_page 預設 20；parent 預設 0
- 後置（回應）: X-WP-Total / X-WP-TotalPages headers

### GetTerm（查詢單一詞彙）
- **Actor**: 管理員
- **Aggregates**: Term
- **回傳欄位**: 完整詞彙資料
- **Description**: 取得指定 ID 的詞彙詳情

#### Rules
- 前置（參數）: taxonomy 和 id 為必填

### GetProductAttributes（查詢商品屬性列表）
- **Actor**: 管理員
- **Aggregates**: ProductAttribute
- **回傳欄位**: 全局屬性資料陣列
- **Description**: 取得所有 WooCommerce 全局商品屬性

### GetPlugins（查詢外掛列表）
- **Actor**: 管理員
- **Aggregates**: 無（WordPress 外掛系統）
- **回傳欄位**: key, name, version, is_active 等外掛資料
- **Description**: 取得所有已安裝的 WordPress 外掛列表

### GetShortcode（執行短碼）
- **Actor**: 管理員
- **Aggregates**: 無
- **回傳欄位**: shortcode 執行後的 HTML 內容
- **Description**: 透過 API 執行指定的 WordPress shortcode

#### Rules
- 前置（參數）: shortcode 參數為必填

### GetWoocommerce（查詢 WooCommerce 資訊）
- **Actor**: 管理員
- **Aggregates**: 無（WooCommerce 系統）
- **回傳欄位**: WooCommerce 全局設定資料
- **Description**: 取得 WooCommerce 全局設定與資訊

#### Rules
- 前置（狀態）: WooCommerce 必須啟用

### GetRevenueStats（查詢營收統計）
- **Actor**: 管理員
- **Aggregates**: Order
- **回傳欄位**: totals（orders_count, total_sales, net_revenue, refunds 等）, intervals, total, pages
- **Description**: 取得 WooCommerce 營收統計報表

#### Rules
- 前置（參數）: before?, after?, interval 預設 day
- 後置（回應）: 含時間區間的統計資料

### GetUploadOptions（查詢上傳選項）
- **Actor**: 管理員
- **Aggregates**: 無
- **回傳欄位**: allowed_mime_types
- **Description**: 取得 WordPress 允許的上傳 MIME 類型清單
