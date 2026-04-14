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

---

## Subscription Aggregate

### Subscription（訂閱）
> WooCommerce Subscription 生命週期事件管理。Powerhouse 將 WooCommerce Subscriptions 的原生 hook 統一轉換為 Powerhouse 自訂 action，提供穩定的事件介面給子外掛訂閱。

| 屬性 | 說明 |
|------|------|
| subscription_id | 訂閱 ID |
| status | 訂閱狀態（active / on-hold / pending-cancel / cancelled / expired） |
| trial_end | 試用期結束時間戳記 |
| next_payment | 下次付款時間戳記 |
| last_order_date_created | 最後訂單建立時間戳記 |
| end | 訂閱結束時間戳記 |
| end_of_prepaid_term | 預付期結束時間戳記 |

---

## Domain Events（訂閱生命週期）

### HandleSubscriptionDateCreated（處理訂閱建立事件）
- **Actor**: WooCommerce
- **Aggregate**: Subscription
- **Predecessors**: 無
- **觸發來源**: `wcs_create_subscription` hook
- **分發 Action**: `powerhouse_subscription_at_date_created`
- **Description**:
  - What: 訂閱建立後分發 Powerhouse 自訂 action
  - Why: 讓子外掛統一監聽訂閱建立事件
  - When: WooCommerce Subscriptions 建立新訂閱時

#### Rules
- 前置（狀態）: WC_Subscriptions 類別必須存在
- 後置（狀態）: 分發 action，參數為 ($subscription, [])

### HandleSubscriptionInitialPaymentComplete（處理首次付款成功事件）
- **Actor**: WooCommerce
- **Aggregate**: Subscription
- **Predecessors**: HandleSubscriptionDateCreated
- **觸發來源**: `woocommerce_subscription_payment_complete` hook
- **分發 Action**: `powerhouse_subscription_at_initial_payment_complete`
- **Description**:
  - What: 訂閱首次付款成功後分發 Powerhouse 自訂 action
  - Why: 讓子外掛在首次付款時執行初始化邏輯（如設定排程）
  - When: 訂閱首次付款完成且僅有一筆關聯訂單（parent order）

#### Rules
- 前置（狀態）: 訂閱僅有一筆關聯訂單且該訂單 ID 等於 parent order ID（防止續訂重複觸發）
- 後置（狀態）: 分發 action，參數為 ($subscription, [])

### HandleSubscriptionFailed（處理訂閱失敗事件）
- **Actor**: WooCommerce
- **Aggregate**: Subscription
- **Predecessors**: HandleSubscriptionInitialPaymentComplete
- **觸發來源**: `woocommerce_subscription_pre_update_status` hook
- **分發 Action**: `powerhouse_subscription_at_subscription_failed`
- **Description**:
  - What: 訂閱從非失敗狀態轉為失敗狀態時分發事件
  - Why: 讓子外掛執行失敗處理（如撤銷存取權限）
  - When: 訂閱狀態從 active/on-hold/pending-cancel 變為 cancelled/expired

#### Rules
- 前置（狀態）: from_status 為非失敗狀態（非 cancelled/expired）；to_status 為失敗狀態（cancelled/expired）
- 後置（狀態）: 分發 action，參數為 ($subscription, ['from_status' => Status, 'to_status' => Status])

### HandleSubscriptionSuccess（處理訂閱恢復成功事件）
- **Actor**: WooCommerce
- **Aggregate**: Subscription
- **Predecessors**: HandleSubscriptionFailed
- **觸發來源**: `woocommerce_subscription_pre_update_status` hook
- **分發 Action**: `powerhouse_subscription_at_subscription_success`
- **Description**:
  - What: 訂閱從失敗狀態恢復為 active 時分發事件
  - Why: 讓子外掛執行恢復處理（如重新授權存取權限）
  - When: 訂閱狀態從 cancelled/expired 變為 active

#### Rules
- 前置（狀態）: from_status 為失敗狀態（cancelled/expired）；to_status 必須為 active
- 後置（狀態）: 分發 action，參數為 ($subscription, ['from_status' => Status, 'to_status' => Status])

### HandleSubscriptionTrialEnd（處理試用結束事件）
- **Actor**: WooCommerce
- **Aggregate**: Subscription
- **Predecessors**: HandleSubscriptionInitialPaymentComplete
- **觸發來源**: `woocommerce_scheduled_subscription_trial_end` hook
- **分發 Action**: `powerhouse_subscription_at_trial_end`
- **Description**:
  - What: 訂閱試用期結束時分發事件
  - Why: 讓子外掛在試用結束時執行轉換邏輯
  - When: WooCommerce 排程觸發試用結束

#### Rules
- 前置（狀態）: subscription_id 必須對應有效的 WC_Subscription
- 後置（狀態）: 分發 action，參數為 ($subscription, [])

### HandleSubscriptionNextPayment（處理下次付款事件）
- **Actor**: WooCommerce
- **Aggregate**: Subscription
- **Predecessors**: HandleSubscriptionInitialPaymentComplete
- **觸發來源**: `woocommerce_scheduled_subscription_next_payment` hook
- **分發 Action**: `powerhouse_subscription_at_next_payment`
- **Description**:
  - What: 訂閱到達下次付款時間時分發事件
  - Why: 讓子外掛在付款時間點執行邏輯
  - When: WooCommerce 排程觸發下次付款

#### Rules
- 前置（狀態）: subscription_id 必須對應有效的 WC_Subscription
- 後置（狀態）: 分發 action，參數為 ($subscription, [])

### HandleSubscriptionRenewalOrderCreated（處理續訂訂單建立事件）
- **Actor**: WooCommerce
- **Aggregate**: Subscription
- **Predecessors**: HandleSubscriptionNextPayment
- **觸發來源**: `wcs_renewal_order_created` filter
- **分發 Action**: `powerhouse_subscription_at_renewal_order_created`
- **Description**:
  - What: 續訂訂單建立後分發事件
  - Why: 讓子外掛在續訂時執行額外處理
  - When: WooCommerce Subscriptions 建立續訂訂單

#### Rules
- 後置（狀態）: 分發 action，參數為 ($subscription, ['renewal_order' => WC_Order])；filter 回傳原始 $renewal_order 不被修改

### HandleSubscriptionPaymentRetry（處理付款重試事件）
- **Actor**: WooCommerce
- **Aggregate**: Subscription
- **Predecessors**: HandleSubscriptionNextPayment
- **觸發來源**: `woocommerce_scheduled_subscription_payment_retry` hook
- **分發 Action**: `powerhouse_subscription_at_payment_retry`
- **Description**:
  - What: 付款重試排程觸發時分發事件
  - Why: 讓子外掛在付款重試時執行額外處理
  - When: WooCommerce 排程觸發付款重試

#### Rules
- 前置（狀態）: order_id 對應有效 WC_Order；該訂單有關聯的 WC_Subscription
- 後置（狀態）: 分發 action，參數為 ($subscription, ['order' => WC_Order])；$subscription 為 ksort 後最後一個

### HandleSubscriptionEnd（處理訂閱結束事件）
- **Actor**: WooCommerce
- **Aggregate**: Subscription
- **Predecessors**: HandleSubscriptionInitialPaymentComplete
- **觸發來源**: `woocommerce_scheduled_subscription_end` hook
- **分發 Action**: `powerhouse_subscription_at_end`
- **Description**:
  - What: 訂閱到達結束時間時分發事件
  - Why: 讓子外掛在訂閱結束時執行清理邏輯
  - When: WooCommerce 排程觸發訂閱結束

#### Rules
- 前置（狀態）: subscription_id 必須對應有效的 WC_Subscription
- 後置（狀態）: 分發 action，參數為 ($subscription, [])

### HandleSubscriptionEndOfPrepaidTerm（處理預付期結束事件）
- **Actor**: WooCommerce
- **Aggregate**: Subscription
- **Predecessors**: HandleSubscriptionInitialPaymentComplete
- **觸發來源**: `woocommerce_scheduled_subscription_end_of_prepaid_term` hook
- **分發 Action**: `powerhouse_subscription_at_end_of_prepaid_term`
- **Description**:
  - What: 已取消/待取消的訂閱預付期結束時分發事件
  - Why: 讓子外掛在預付期結束後執行最終清理
  - When: WooCommerce 排程觸發預付期結束（訂閱為 cancelled 或 pending-cancel 狀態）

#### Rules
- 前置（狀態）: subscription_id 必須對應有效的 WC_Subscription
- 後置（狀態）: 分發 action，參數為 ($subscription, [])

### WatchSubscriptionTrialEnd（監聽試用結束時間變化）
- **Actor**: WooCommerce
- **Aggregate**: Subscription
- **觸發來源**: `woocommerce_subscription_date_updated` hook (date_type = trial_end)
- **分發 Action**: `powerhouse_subscription_at_watch_trial_end`
- **Description**: 訂閱的試用結束時間被更新時分發事件，讓子外掛在時間點變化時重新排程

#### Rules
- 前置（狀態）: date_type 必須為 "trial_end"
- 後置（狀態）: 分發 action，參數為 ($subscription, ['datetime' => string])

### WatchSubscriptionNextPayment（監聽下次付款時間變化）
- **Actor**: WooCommerce
- **Aggregate**: Subscription
- **觸發來源**: `woocommerce_subscription_date_updated` hook (date_type = next_payment)
- **分發 Action**: `powerhouse_subscription_at_watch_next_payment`
- **Description**: 訂閱的下次付款時間被更新時分發事件，讓子外掛在時間點變化時重新排程

#### Rules
- 前置（狀態）: date_type 必須為 "next_payment"
- 後置（狀態）: 分發 action，參數為 ($subscription, ['datetime' => string])

### WatchSubscriptionEnd（監聽結束時間變化）
- **Actor**: WooCommerce
- **Aggregate**: Subscription
- **觸發來源**: `woocommerce_subscription_date_updated` hook (date_type = end)
- **分發 Action**: `powerhouse_subscription_at_watch_end`
- **Description**: 訂閱的結束時間被更新時分發事件，讓子外掛在時間點變化時重新排程

#### Rules
- 前置（狀態）: date_type 必須為 "end"
- 後置（狀態）: 分發 action，參數為 ($subscription, ['datetime' => string])

### CreateAccessItemMetaTable（建立存取權限資料表）
- **Actor**: 系統
- **Aggregate**: AccessItemMeta
- **Predecessors**: 無（Plugin lifecycle root）
- **觸發來源**:
  - `register_activation_hook`（Powerhouse 外掛啟用時）→ `Plugin::activate()`
  - `Compatibility\Services\Scheduler::compatibility`（每次版本變動後 Action Scheduler 非同步執行一次）
- **Description**:
  - What: 建立 `wp_{prefix}ph_access_itemmeta` 自訂表，紀錄「用戶 × 內容項目」的存取權限 meta
  - Schema: `meta_id`(PK)、`post_id`、`user_id`、`meta_key`(varchar 255)、`meta_value`(longtext)；含 `post_id`/`user_id`/`meta_key(191)` 三個 index
  - Idempotent: 先以 `WP::is_table_exists` 檢查，若已存在則直接 return，避免重複 dbDelta
  - 副作用: 將 full table name 指派到 `$wpdb->access_itemmeta` 全域以供其他 Domain 存取
  - Class 定義保護: 若全域已有 `AbstractTable` class 則本檔整段 return（避免與其他套件衝突）
- **Error handling**: 內部 `try/catch Throwable`，捕獲後 re-throw 為 `\Exception($th->getMessage())`

### HandleGrantUserToItemAction（LifeCycle 授權處理）
- **Actor**: 系統 / 子外掛
- **Aggregate**: AccessItemMeta
- **Predecessors**: CreateAccessItemMetaTable, BindItemsToProduct
- **觸發來源**: `do_action('powerhouse/limit/grant_user_to_item', $user_id, $post_id, $expire_date, $order)`
- **Description**:
  - What: 收到 grant action 後，呼叫 `MetaCRUD::update($post_id, $user_id, 'expire_date', $expire_date)` 寫入 ph_access_itemmeta
  - `$expire_date` 支援兩型：`int`（timestamp，0 為無期限）或 `string`（`subscription_{id}`）
  - 寫入完成後**無條件**分發 `powerhouse/limit/after_grant_user_to_item` action
- **Error handling**: `MetaCRUD::update` 回傳 false 時拋出 `\Exception`，訊息包含 user_id / post_id / expire_date，若有 order 則附上 order_id

### HandleUpdateUserFromItemAction（LifeCycle 更新期限處理）
- **Actor**: 系統 / 子外掛
- **Aggregate**: AccessItemMeta
- **Predecessors**: HandleGrantUserToItemAction
- **觸發來源**: `do_action('powerhouse/limit/after_update_user_from_item', $user_id, $post_id, $timestamp)`
- **Description**: 呼叫 `MetaCRUD::update($post_id, $user_id, 'expire_date', $timestamp)`，`$timestamp=0` 視為無期限
- **Error handling**: 更新失敗時拋出 `\Exception("Failed to update user item expiration time, user_id #X, post_id #Y, timestamp #Z")`

### HandleRevokeUserFromItemAction（LifeCycle 撤銷處理）
- **Actor**: 系統 / 子外掛
- **Aggregate**: AccessItemMeta
- **Predecessors**: HandleGrantUserToItemAction
- **觸發來源**: `do_action('powerhouse/limit/after_revoke_user_from_item', $user_id, $post_id)`
- **Description**: 呼叫 `MetaCRUD::delete($post_id, $user_id)` 整筆刪除該用戶對該項目的所有 meta
- **Error handling**: 刪除失敗時拋出 `\Exception("Failed to remove user, user_id #X, post_id #Y")`

### BoundItemDataGrantUser（授權原語）
- **Actor**: 子外掛
- **Aggregate**: AccessItemMeta（透過 BoundItemData 模型）
- **Predecessors**: BindItemsToProduct（商品綁定項目後才會有 BoundItemData 可用）
- **觸發來源**: 下游外掛直接呼叫 `$bound_item_data->grant_user($user_id, $order, $meta_key)`（例如 PowerCourse 在 `woocommerce_order_status_completed` 迴圈 line items 時）
- **Description**:
  - What: 根據 BoundItemData 的 `limit_type` 計算 `expire_date` 後寫入 ph_access_itemmeta
  - `limit_type` 計算規則：
    - `unlimited` → `expire_date = 0`
    - `assigned` → `expire_date = limit_value`（literal timestamp）
    - `fixed` → `expire_date = strtotime("+{limit_value} {limit_unit}")` 轉換為當天 `15:59:00` 的 timestamp
    - `follow_subscription` → 從 `$order` 用 `wcs_get_subscriptions_for_order($order, ['order_type'=>'parent'])` 取出**唯一**訂閱，`expire_date = "subscription_{id}"`；若非唯一、無訂單、或 `WC_Subscription` class 不存在則回退為 0
  - 支援自訂 `$meta_key`（預設為 `expire_date`），允許下游外掛為同一用戶/項目寫入多種授權語義
- **Error handling**: `MetaCRUD::update` 回傳 false 時先 dispatch `powerhouse/limit/grant_user_failed` action，再拋出 `\Exception`
- **重要架構備註**: Powerhouse 本身**不訂閱** `woocommerce_order_status_completed` 或任何訂閱失敗 hook。「訂單完成 → 自動授權」的業務流程實際存在於下游外掛（PowerCourse / PowerShop 等）。Powerhouse 僅提供被動的授權原語 API。

### BoundItemDataRevokeUser（撤銷原語）
- **Actor**: 子外掛
- **Aggregate**: AccessItemMeta
- **Predecessors**: BoundItemDataGrantUser
- **觸發來源**: 下游外掛直接呼叫 `$bound_item_data->revoke_user($user_id, $order, $meta_key)`（例如退款、訂閱失敗時）
- **Description**: 呼叫 `MetaCRUD::delete($item_id, $user_id, $meta_key)` 刪除指定 meta_key 的該筆記錄
- **Error handling**: `MetaCRUD::delete` 回傳 false 時先 dispatch `powerhouse/limit/revoke_user_failed`，再拋出 `\Exception`

### OverrideRetryPaymentRules（覆寫付款重試規則）
- **Actor**: 系統
- **Aggregate**: Subscription
- **觸發來源**: `wcs_default_retry_rules` filter + `woocommerce_subscription_max_failed_payments_exceeded` filter
- **Description**:
  - What: 將 WooCommerce Subscriptions 預設的 5 次 / 7 天重試規則覆寫為 3 次 / 3 小時
  - Why: 縮短重試週期，加速失敗判定，避免長時間保留 on-hold 狀態
  - When: Powerhouse 載入時自動覆寫

#### Rules
- 後置（狀態）: 重試規則為 3 次，每次間隔 1 小時（HOUR_IN_SECONDS）
- 後置（狀態）: 重試期間訂單狀態 pending、訂閱狀態 on-hold
- 後置（狀態）: 重試期間不通知客戶、僅通知管理員（WCS_Email_Payment_Retry）
- 後置（狀態）: 超過重試上限後訂閱轉為 cancelled（而非停留在 on-hold）

---

## System Behaviors

> 非 CRUD 的系統級自動化行為，由設定開關控制，保護登入/註冊/發信流程，或提供基礎設施服務。

### Captcha（驗證碼保護）

| 行為 | 觸發條件 | 說明 |
|------|---------|------|
| 登入驗證碼顯示 | `enable_captcha_login = yes` + 用戶角色在 `captcha_role_list` 中 | 攔截登入表單提交，AJAX 判斷角色後顯示 4 位數字驗證碼 |
| 登入驗證碼驗證 | `authenticate` filter (priority 999) | 比對 `$_SESSION['powerhouse_phrase']` 與用戶輸入，失敗回傳 `WP_Error('captcha_failed')` |
| 註冊驗證碼顯示 | `enable_captcha_register = yes` | WooCommerce 註冊表單頁面載入時立即顯示驗證碼 |
| 註冊驗證碼驗證 | `wp_pre_insert_user_data` filter | 缺少或錯誤驗證碼時拋出 Exception，阻止用戶建立 |

**跳過條件：**
- `REQUEST_URI` 包含 `power-partner-server/identity` → 不載入登入驗證碼
- `REQUEST_URI` 為 `/checkout` → 跳過登入驗證碼檢查
- 用戶角色不在 `captcha_role_list` → 跳過登入驗證碼
- `$update = true` → 跳過註冊驗證碼（僅新用戶需要）

### EmailValidator（Email 網域驗證）

| 行為 | 觸發條件 | 說明 |
|------|---------|------|
| 註冊 Email 驗證 | `enable_email_domain_check_register = yes` | 掛載 `registration_errors` + `woocommerce_registration_errors`，檢查 Email 網域 MX record |
| 發信 Email 驗證 | `enable_email_domain_check_wp_mail = yes` | 掛載 `pre_wp_mail`，發信前檢查收件人網域 MX record，無效時靜默阻止 |

**白名單：** `email_domain_check_white_list`（預設 gmail.com, yahoo.com, hotmail.com, outlook.com, icloud.com），名單內跳過 MX 檢查（不區分大小寫）。

**載入層級：** mu-plugin (`J7\Powerhouse\MU\EmailValidator`) 優先；若不存在，由 `Domains\Register\Core\Filter` fallback 載入。

### DelayEmail（WooCommerce Email 延遲發送）

| 行為 | 觸發條件 | 說明 |
|------|---------|------|
| 移除同步 Email | `delay_email = yes` + `init` (priority 100) | 移除 WC_Email_New_Order / Customer_Completed_Order / Customer_Processing_Order 的 14 個 hook |
| 排程非同步 Email | 訂單狀態變更 | 以 `as_enqueue_async_action('powerhouse_delay_email', [$class_name, ...$args])` 取代同步發送 |
| 執行延遲發送 | Action Scheduler 觸發 `powerhouse_delay_email` | 取得 WC Email 實例並呼叫 `trigger()` 方法，方法不存在時靜默跳過 |

### mu-plugins（系統級服務）

| mu-plugin | 行為 | 觸發時機 |
|-----------|------|---------|
| `powerhouse-loader.php` | 在 `muplugins_loaded` (priority 100) 預載入 vendor/autoload.php 與核心 Trait/Class | WordPress 啟動最早期 |
| `powerhouse-api-booster.php` | 根據 `api_booster_rules` URL 規則覆寫 active_plugins，僅載入指定外掛 | `muplugins_loaded` 階段 |
| `powerhouse-disable-features.php` | 停用 XML-RPC、移除 REST API users 端點、移除中間圖片尺寸生成、JPEG 品質保持 100% | `init` / `rest_endpoints` filter |
| `powerhouse-email-validator.php` | mu-plugin 級 Email 網域 MX 驗證（與 EmailValidator 行為相同，但更早載入） | `muplugins_loaded` 階段 |

**安裝機制：** 透過 `MuPluginsLoader` 在 Powerhouse 版本更新時自動複製到 `wp-content/mu-plugins/` 目錄。已存在時先刪除舊版再複製新版。

### Theme（主題色彩系統）

| 行為 | 觸發條件 | 說明 |
|------|---------|------|
| HTML data-theme 注入 | `enable_theme = yes` + `language_attributes` filter (priority 20) | 在 HTML 標籤注入 `id="tw" class="tailwind" data-theme="{theme}"` |
| CSS 變數注入 | `enable_theme = yes` + `wp_head` (priority -100) | 注入 `#tw[data-theme='{theme}'] {...}` 樣式，包含 daisyUI 完整變數（--p, --s, --a, --b1~b3, --in, --su, --wa, --er 等） |
| 主題切換器腳本 | `enable_theme_changer = yes` | 注入同步 JavaScript 讀取 localStorage 的 theme 值並設定 data-theme（避免閃爍） |
| 主題切換按鈕 | `enable_theme_changer = yes` 或 `force_render = true` | 載入 theme 模板渲染前台切換按鈕 |

**Theme Model 來源：** `powerhouse_settings.theme_css` (JSON 物件) + `powerhouse_settings.theme` (主題名稱)。鍵名規則：CSS 變數 `--rounded-box` 對應 PHP 屬性 `rounded_box`。

### MessageTemplate（訊息範本 CPT）

| 屬性 | 說明 |
|------|------|
| post_type | `ph_message_tpl` |
| public | true |
| has_archive | true |
| supports | title, custom-fields |
| capability_type | post |

**用途：** 提供其他 Power 外掛（如 Power Funnel）統一的訊息範本資料模型。`MessageTemplateDTO::of($id)` 從 post 載入並回傳 DTO，包含 id, name (post_title), subject (post_meta), content (post_content), content_type (EContentType: HTML/PLAIN_TEXT/JSON/XML)。

### AutoUpdate（自動更新）

| 行為 | 觸發條件 | 說明 |
|------|---------|------|
| 觸發排程 | `upgrader_process_complete` hook + 更新的外掛包含 `power-*` 開頭外掛 | 排程一個 10 秒後執行的 `powerhouse_auto_update` action |
| 執行更新 | `powerhouse_auto_update` action | 呼叫 `wp_update_plugins()` 檢查 → `Plugin_Upgrader::upgrade()` → `activate_plugin()` |

**環境差異：**
- `production`: target_plugin = `powerhouse/plugin.php`，動態偵測 active_plugins 中所有 `power-*` 外掛
- `local`: target_plugin = `classic-editor/classic-editor.php`，避免本地開發環境意外更新

**錯誤處理：** 無可用更新、更新失敗、啟用失敗、未預期例外都記錄 error log，不影響系統正常運作。

### Bootstrap（外掛初始化）

> Powerhouse 的核心入口，負責在 Plugin callback（priority -10）階段載入所有子系統、註冊 admin menu、前後台資源 enqueue、授權碼快取檢查。

| 行為 | 觸發條件 | 說明 |
|------|---------|------|
| 核心子系統載入 | `Bootstrap::__construct` | 一律載入 Admin\Entry / Api\Base / Api\LC / Domains\Loader / Theme\Core\FrontEnd / Captcha\Core\Login / Captcha\Core\Register |
| WooCommerce 子系統載入 | `class_exists('\WooCommerce')` | 額外載入 Compatibility\Services\Scheduler / Admin\Debug / Admin\OrderList / Admin\Account / Admin\DelayEmail（Admin\OrderDetail 已註解停用） |
| 主選單註冊 | `admin_menu` (priority 10) | `add_menu_page` 註冊 Powerhouse toplevel 選單（capability `manage_options`、position 3、base64 SVG icon） |
| 子選單註冊 | `admin_menu` (priority 100) | 一律註冊「設定」子選單；若 `apply_filters('powerhouse_product_infos', [])` 非空則額外註冊「授權碼」子選單 |
| 前台資源 | `wp_enqueue_scripts` (priority -100) | 載入 `front.min.css` + `inc/assets/js/frontend.js`（async, in-footer, jQuery 相依） |
| 後台共用樣式 | `admin_enqueue_scripts` (-100) + URL 含 `power-` 或 `powerhouse` | 載入 `admin.min.css` + antd-toolkit `style.css` |
| 後台 SPA 載入 | URL 含 `admin.php?page=powerhouse` | 透過 `Vite\enqueue_asset` 載入 `js/src/main.tsx`，並以 `wp_localize_script` 注入加密後的 env 物件 |
| 授權碼快取刷新 | `plugins_loaded` (priority 999) | 呼叫 `LCUtils::get_lc_array()` 觸發 LC 陣列快取更新（side-effect） |
| Local script src 修正 | `script_loader_src` filter + `Plugin::$env === 'local'` | 修正 Vite build 後的絕對路徑問題 |

**後台環境變數加密：** enqueue_admin_assets 透過 `Base::simple_encrypt` 加密後注入 `{snake}_data.env`，包含 SITE_URL、API_URL、CURRENT_USER_ID、CURRENT_POST_ID、PERMALINK、APP_NAME、KEBAB、SNAKE、BUNNY_LIBRARY_ID、BUNNY_CDN_HOSTNAME、BUNNY_STREAM_API_KEY、NONCE、APP1_SELECTOR、ELEMENTOR_ENABLED、ROLES、WOOCOMMERCE_ENABLED 等 17 個 key。

### Admin\Entry（管理後台 SPA 入口）

| 行為 | 觸發條件 | 說明 |
|------|---------|------|
| SPA 容器渲染 | `current_screen` + `get_current_screen()->id === 'toplevel_page_powerhouse'` | 呼叫 `Bootstrap::enqueue_admin_assets()` 與 `Base::render_admin_layout`，渲染後執行 `exit` 中止 WordPress 預設 admin 輸出流程 |

**跳過條件：** `is_admin() === false` 或當前 screen id 非 `toplevel_page_powerhouse` 時 callback 直接 return。

### Admin\Debug（Debug Log 工具，僅 WC 啟用時載入）

| 行為 | 觸發條件 | 說明 |
|------|---------|------|
| 工具選單註冊 | `admin_menu` (-10) + `$submenu['tools.php']` 存在 | 在「工具」選單下新增「Debug Log」項目（slug: `debug-log-viewer`） |
| Debug Log 頁面 | 進入 `tools.php?page=debug-log-viewer` | 顯示下載 `debug.log` 連結、刪除按鈕、讀取最後 1000 行 log 以 `nl2br + esc_html` 輸出 |
| Admin Bar 快捷選單 | `admin_bar_menu` (100) + `current_user_can('manage_options')` | 新增 `debug-tools` 節點，含 `wc-logger` + `debug-log-viewer` 子節點 |
| 刪除 debug.log | `admin_post_delete_debug_log` | 驗證 `manage_options` + nonce 後 `unlink`，redirect 回 referer |
| `http_api_debug` 啟用 | Bootstrap init | `add_action('http_api_debug', '__return_true')` |

### Admin\Account（姓氏非必填，僅 WC 啟用時載入）

| 行為 | 觸發條件 | 說明 |
|------|---------|------|
| 姓氏非必填 filter | `Settings::instance()->last_name_optional` 為 true | 掛載 `woocommerce_save_account_details_required_fields` filter，從必填陣列中 `unset($required_fields['account_last_name'])` |

### Admin\OrderList（訂單列表欄位強化，僅 WC 啟用時載入）

| 行為 | 觸發條件 | 說明 |
|------|---------|------|
| 新增「訂單商品」欄位 | HPOS：`manage_woocommerce_page_wc-orders_columns`；非 HPOS：`manage_edit-shop_order_columns` | 新增 column key `elittleworld_extension_order_products` |
| 渲染欄位內容 | HPOS：`manage_woocommerce_page_wc-orders_custom_column`；非 HPOS：`manage_shop_order_posts_custom_column` | 遍歷訂單 line_item，輸出 `<a href="{edit_link}">{product_name}</a> x {quantity}` |

### Admin\OrderDetail（訂單詳情備註 — **目前已停用**）

> **狀態：** Bootstrap 中 `Admin\OrderDetail::instance()` 已被註解停用。
> **已知 BUG：** 經典 TinyMCE 編輯器在非 HPOS 模式下無法正常顯示，僅於 HPOS 模式可用。本項僅記錄原始設計意圖。

| 行為（設計意圖） | 觸發條件 | 說明 |
|------|---------|------|
| 移除原生訂單備註 | `admin_head` (100) + screen 為訂單頁 | `remove_meta_box('woocommerce-order-notes', ...)` |
| 註冊自訂訂單備註 | `admin_head` (110) + screen 為訂單頁 | 以 `advanced` context 註冊新 meta box，內含 `wp_editor` 經典編輯器 |

### MuPluginsLoader（mu-plugin 安裝機制）

> 抽象基底類別，子類只需宣告 `$file_name` 即可自動將 `inc/classes/Compatibility/mu-plugins/{file_name}` 複製到 `wp-content/mu-plugins/{file_name}`。

| 行為 | 觸發條件 | 說明 |
|------|---------|------|
| 目錄建立 | mu-plugins 目錄不存在 | 透過 `WP_Filesystem::mkdir` 以 0755 建立 |
| 檔案複製 | 目標檔案不存在 | 直接 `copy` 來源到目標 |
| 覆寫舊版 | 目標檔案已存在 | 先 `unlink` 再 `copy`（避免舊版殘留） |
| 錯誤處理 | 任何步驟失敗 | 透過 `Plugin::logger` 記錄，不中斷其他任務 |

**子類別：** `Loader`, `ApiBooster`, `DisableFeatures`, `EmailValidator`（皆為 20 行薄殼，只宣告 `$file_name`）。

### Scheduler（相容性服務排程）

> Singleton，透過 `AS_COMPATIBILITY_ACTION = 'powerhouse_compatibility_action_scheduler'` 作為 Action Scheduler hook。**不只排程** — 還處理建表、actionscheduler 表 schema 改寫、舊 admin URL 重導。

| 行為 | 觸發條件 | 說明 |
|------|---------|------|
| 版本升級偵測 | 每次 plugin 載入 | 比對 `powerhouse_compatibility_action_scheduled` option 與 `Plugin::$version`，不同時 `as_enqueue_async_action` 排入一次性任務 |
| 執行相容性動作 | Action Scheduler 觸發 | 依序：`CreateTable::create_itemmeta_table()` → 觸發 EmailValidator/Loader/ApiBooster mu-plugin 安裝 → `modify_action_scheduler_table_schema()` → `flush_rewrite_rules()` + `wp_cache_flush()` |
| ActionScheduler 表升級 | `modify_action_scheduler_table_schema` | 將 `wp_actionscheduler_actions.args` 從 `varchar(191)` 改為 `longtext` |
| 舊 admin URL 重導 | `plugins_loaded` | 把 `admin.php?page=powerhouse-license-codes` 導向新版 `admin.php?page=powerhouse#license-code` |

**注意：** `Scheduler::compatibility()` 中**不**實例化 `DisableFeatures`，僅 `EmailValidator`、`Loader`、`ApiBooster` 被建立。

### Service 層 vs mu-plugin 層職責

| 層級 | 職責 | 執行時機 |
|------|------|----------|
| Service (`Compatibility/Services/*.php`) | 檔案複製 / 排程管理 / 資料表升級 | 版本升級後由 Action Scheduler 執行 |
| mu-plugin (`wp-content/mu-plugins/*.php`) | 實際功能（API 加速、停用 WP 功能、Email 驗證、早期載入 vendor） | WordPress 最早期載入階段 |

### Cloud API 通訊基底（Api\Base）

> 與 `cloud.luke.cafe` 通訊的 HTTP client 基底。**對外被 `power-partner` 外掛直接依賴**（跨外掛公開 API，需保持穩定）。

| 環境 | base_url |
|------|---------|
| `local` + `IS_HOME` | `http://cloud.local`（j7.dev.gg 憑證） |
| `local` 辦公室 | `http://cloud.local`（powerpartner 憑證） |
| `staging` | `https://cloud-staging.wpsite.pro` |
| `production`（預設） | `https://cloud.luke.cafe` |

**API URL：** `{base_url}/wp-json/power-partner-server`

**default_args：** `Content-Type: application/json; charset=UTF-8`、`Authorization: Basic base64(user:psw)`、`Origin: site host`、`timeout: 30`

**方法：** `remote_get($endpoint, $url_params)` / `remote_post($endpoint, $body_params)` / `remote_delete($endpoint, $body_params)`，分別底層使用 `wp_remote_get`/`wp_remote_post`/`wp_remote_request`。所有 body_params 透過 `wp_json_encode` 序列化。

### Api\LC（授權碼 Cloud API — Deprecated）

> 標記 `@deprecated`，由 `Domains\LC\Core\V2Api` 取代。

| 端點 | 方法 | 說明 |
|------|------|------|
| `/wp-json/powerhouse/lc/invalidate` | POST | 舊版 namespace `powerhouse`（非 `v2/powerhouse`），`permission_callback` 為 `__return_true`（無權限檢查），完全委派給 `LC_V2_Api::instance()->post_lc_invalidate_callback($request)` |

### Domain Models 內部實作

> 各 Domain 內部使用的資料模型、DTO、Service 類別。它們的行為透過對應的 V2Api Feature 間接覆蓋，此處補充類別層級的職責說明。

#### Limit Domain

- **ExpireDate** (`Domains/Limit/Models/ExpireDate.php`) — 課程/項目觀看期限的值物件。接收 `Limit::calc_expire_date()` 產出的三種輸入（`0` = 無期限、`timestamp` = 到期日、`"subscription_{id}"` = 跟隨訂閱），解析後設定 `timestamp` / `is_subscription` / `subscription_id` / `is_expired` / `expire_date_label` 屬性。訂閱類型透過 `wcs_get_subscription()` 檢查 `active` 狀態判斷是否過期。
- **GrantedItem** (`Domains/Limit/Models/GrantedItem.php`) — 單一用戶-項目授權關係。建構時呼叫 `MetaCRUD::get($post_id, $user_id, $meta_key)` 讀取 expire_date；空字串代表無權限（`can_access = false`），否則建立 `ExpireDate` 實例。
- **GrantedItems** (`Domains/Limit/Models/GrantedItems.php`) — 批次查詢用戶已授權項目集合。SQL LEFT JOIN `wp_posts`，以 `wp_cache` 快取（key: `granted_items_{user_id}_where_{JSON}`）。**⚠️ 動態字串拼接 SQL（非 prepare），有 SQL injection 風險**。

#### Order Domain

- **Info** (`Domains/Order/Utils/Info.php`) — 訂單與使用者地址資訊聚合 abstract util。固定 11 個欄位（first_name, last_name, email, phone, company, postcode, country, state, city, address_1, address_2），shipping 排除 company。提供 `get_billing_fields(prefix)` / `get_shipping_fields(prefix)` / `to_order_array($order_id)` / `to_user_array($user_id)`。

#### Post Domain

- **MetaQueryClause** (`Domains/Post/Service/MetaQueryClause.php`) — 單一 meta_query 條件 DTO。包含 `key` / `value` / `compare`（預設 `=`）。`format_value("{prefix}{value}{suffix}")` 以 `{value}` 佔位符包裝原值。
- **MetaQueryBuilder** (`Domains/Post/Service/MetaQueryBuilder.php`) — meta_query 陣列的程式化改寫工具。提供 `find(key)` / `remove(key)` / `add(Clause|array)` / `get_meta_query()`。供 V2Api 在 `prepare_query_args` 階段與 filter hook 使用者擴充。

#### Subscription Domain

- **Times DTO** (`Domains/Subscription/DTOs/Times.php`) — 訂閱時間戳記聚合 DTO。5 個 `int` 欄位：`trial_end`、`next_payment`、`last_order_date_created`、`end`、`end_of_prepaid_term`。透過 `Times::instance(WC_Subscription)` 從訂閱物件讀取。**注意：** `last_order_date_created` 不在 `Action` enum 中，硬編碼字串呼叫。

#### User Domain

- **ExtendQuery** (`Domains/User/Core/ExtendQuery.php`) — `WP_User_Query` 的 meta_query 擴展器（Singleton）。掛載 `powerhouse/user/prepare_query_args/meta_query_builder` filter，對 `billing_phone`（改 `LIKE`）和 `user_birthday`（包裝 `-{value}-` 並改 `LIKE`）改寫查詢方式。使用 nullsafe operator，meta_query 不含這些 key 時不會出錯。

#### Product Domain

| Class | 職責 | 關鍵欄位 |
|-------|------|---------|
| `Model\Price` | 商品價格資料 | `price_html`, `regular_price`, `sale_price`, `on_sale`, `sale_date_range`, `total_sales` |
| `Model\Sales` | 促銷關聯商品 | `upsell_ids` (string[]), `cross_sell_ids` (string[]) |
| `Model\Stock` | 庫存資料 | `stock_status`, `manage_stock` (yes/no), `stock_quantity`, `backorders`, `low_stock_amount` |
| `Service\PeriodLabel` | 訂閱週期顯示文字 | day/1=天, day/7=週, month/3=季, month/6=半年, month/12=年 等 |
| `Utils\Save` | 商品儲存中央處理器 | `data()` 觸發 before/after_save_data hooks；`meta_data()` 處理類型切換 + 訂閱欄位清理 |

**⚠️ Save::meta_data 潛在 bug：** 即使未傳 `type` key 也會清除所有 `_subscription_*` 欄位（因 `$is_subscription` 預設為 false）。

**完整呼叫鏈：**
- 建立商品: V2Api → CRUD::create_product → new WC_Product_Simple → Save::data → save()
- 訂閱 price_html: Price::instance → CRUD::get_price_html → Utils\Subscription::get_price_html → PeriodLabel::get_label

### WooCommerce 選項模型

> Powerhouse 的 `Domains/Woocommerce/` 提供一組全局選項資料 Models，將 WC/WP 原生資料包裝為前端 Ant Design 元件可用的 `{value, label, color}` 結構。

| Model | 命名空間 | 資料來源 | 基底 |
|-------|---------|---------|------|
| `Countries` | `Domains\Woocommerce\Core` | `woocommerce_states` filter（擴充 TW 縣市） | `SingletonTrait` + `final` |
| `OrderStatuses` | `Domains\Woocommerce\Model` | `wc_get_order_statuses()` | `DTO` |
| `PostStatuses` | `Domains\Woocommerce\Model` | `get_post_statuses()` | `DTO` |
| `ProductStockStatuses` | `Domains\Woocommerce\Model` | `wc_get_product_stock_status_options()` | `DTO` |
| `ProductTypes` | `Domains\Woocommerce\Model` | `wc_get_product_types()` | `DTO` |
| `AntdOption` | **`Domains\Product\Model`**（歷史遺留：檔案在 Woocommerce 但 namespace 是 Product） | — | `DTO` |

**設計模式：** 各 Model 內建 `protected static $xxx_mapper` 對應表，命中則套用預設 label/color；未命中使用原始 name + `"default"` 顏色。

**特殊行為：**
- `Countries`: 唯一使用 Singleton 而非 DTO，註冊 `woocommerce_states` filter 擴充台灣 22 個縣市
- `OrderStatuses`: 唯一移除 `wc-` 前綴的 Model，mapper 涵蓋 WMP 配送與 RY 超商外掛狀態
- `ProductTypes`: mapper 涵蓋 WC Subscriptions 的 `subscription` / `variable-subscription` + 變體類型

### Shared Utilities

> 純技術工具類別，不包含業務邏輯。

#### Shared Enums

- **EObjectType** — 物件類型列舉。Array / User / Product / Order / Post / Object 六種 case，提供 `get_type(mixed $obj): self` 靜態工廠。
- **EOperater** — 比較運算子列舉。定義 30+ 個運算子字串（eq, ne, lt, gt, in, contains, between...），對齊 Refine CrudFilter 命名。

#### Shared Helpers

- **CompareHelper** — 流暢式值比較 final class。`(new CompareHelper($target, $compared))->is(EOperater::X)->match()` 串接（AND 聚合）。實際支援 11 個運算子（LESS, EQUAL, EXACT, IN, CONTAINS 等），其餘 EOperater 回傳 false。
- **NonceHelper** — 基於 transient 的**真一次性** nonce final class（與 WP 原生 `wp_create_nonce` 不同）。`random_bytes(32)` 產生 URL-safe base64，verify 時立即 `delete_transient`。常用於 Email 驗證流程。
- **ReplaceHelper** — 模板字串替換 final class。支援 `{{type.property}}` 與 `{{type[key]}}` 語法，依 `EObjectType` 決定 placeholder 前綴。支援 method chaining。

#### Utils

- **Utils\Compare** — 日期區間比較資料容器（**不是**泛用比較器，與 `CompareHelper` 不同）。建構子接受 `after / before / compare_type / compare_value`，自動計算 4 個 DateTime 屬性，用於報表同期比較。
- **Utils\DateTimeHandler** — abstract class。`parse_date_time`（以 `wp_timezone_string()` 解析）+ `get_compared_date_time(type, value)`（支援 day/week/month/year）。**特殊處理：** month 用 `min(current_day, days_in_prev_month)` 修正跨月天數問題（3/31 → 2/28 而非 2/31）。
- **Utils\ExportCSV** — abstract class，CSV 匯出基底範本。送出 header + UTF-8 BOM → 寫入欄位標籤 → 透過 `Utils\Base::batch_process` 逐筆寫入 → `exit`。

#### Settings Core

- **ApiBoosterRule** — Singleton。提供 3 組預設 recipes（Power 系列 / Power Course / Power Shop）。維護 `$base_plugins`（WC, WC Subs, Powerhouse, Elementor）與 `$power_plugins` 兩個白名單。

#### Contracts DTOs

- **CallableDTO** — **空類別**，預留檔案。無屬性、無方法、不繼承任何父類別。
- **FormFieldDTO** — 表單欄位中繼資料 DTO。13 個 public 屬性：`element / attributes / name / label / type / required / default_value / placeholder / description / options / validation / sort / depends_on`。`type` 支援 text/number/select/textarea/template_editor/switch/date/json。

### AsSchedulerHandler（非同步任務處理）

抽象基底類別 `AsSchedulerHandler\Shared\Base`，供所有 Power 外掛繼承使用。提供統一的 Action Scheduler 排程建立、查詢、取消介面。

**子類別必須定義：**
- `static $hook` (string): 排程 action hook 名稱
- `get_args()` (array): 排程參數，用於確保排程的唯一性和可查詢性

**核心方法：**
- `register()`: 註冊 hook callback 為 `action_callback`
- `schedule_single($timestamp, $group, $unique)`: 建立一次性排程
- `schedule_recurring($timestamp, $interval, $group, $unique)`: 建立週期性排程
- `cancel()`: 取消符合 hook + args 的排程
- `is_scheduled()`: 查詢排程狀態

---

## Extensibility Hooks

> 列出所有 Powerhouse 自訂的 do_action 和 apply_filters，供子外掛擴展使用。
> 完整版本以原始碼為準（`grep -r "do_action.*powerhouse" inc/classes/` / `grep -r "apply_filters.*powerhouse" inc/classes/`）。

### Actions

#### Copy Domain

| Hook | 觸發時機 | 參數 | 用途 |
|------|---------|------|------|
| `powerhouse_after_copy_post` | Post 複製完成後 | `$copy` (Copy), `$post_id` (int), `$new_id` (int), `$override_post_parent` (int\|bool), `$depth` (int) | Post 複製後的後續處理事件 |

#### Limit Domain

| Hook | 觸發時機 | 參數 | 用途 |
|------|---------|------|------|
| `powerhouse/limit/grant_user_to_item` | REST API 授權用戶存取項目時 | `$user_id` (int), `$post_id` (int), `$expire_date` (int\|string), `$order` (?WC_Order) | 觸發用戶權限開通流程 |
| `powerhouse/limit/after_grant_user_to_item` | 用戶權限寫入 DB 後 | `$user_id` (int), `$post_id` (int), `$expire_date` (int\|string), `$order` (?WC_Order) | 用戶權限開通完成後的後續處理 |
| `powerhouse/limit/after_update_user_from_item` | REST API 更新用戶存取期限時 | `$user_id` (int), `$post_id` (int), `$timestamp` (int) | 更新用戶觀看期限 |
| `powerhouse/limit/after_revoke_user_from_item` | REST API 撤銷用戶存取時 | `$user_id` (int), `$post_id` (int) | 移除用戶存取權限 |
| `powerhouse/limit/grant_user_success` | BoundItemData 授權用戶成功後 | `$user_id` (int), `$order` (?WC_Order), `$bound_item_data` (BoundItemData), `$meta_key` (string) | 單一項目授權用戶成功事件 |
| `powerhouse/limit/grant_user_failed` | BoundItemData 授權用戶失敗時 | `$user_id` (int), `$order` (?WC_Order), `$bound_item_data` (BoundItemData), `$meta_key` (string) | 單一項目授權用戶失敗事件 |
| `powerhouse/limit/revoke_user_success` | BoundItemData 撤銷用戶成功後 | `$user_id` (int), `$order` (?WC_Order), `$bound_item_data` (BoundItemData), `$meta_key` (string) | 單一項目撤銷用戶成功事件 |
| `powerhouse/limit/revoke_user_failed` | BoundItemData 撤銷用戶失敗時 | `$user_id` (int), `$order` (?WC_Order), `$bound_item_data` (BoundItemData), `$meta_key` (string) | 單一項目撤銷用戶失敗事件 |

#### Product Domain

| Hook | 觸發時機 | 參數 | 用途 |
|------|---------|------|------|
| `powerhouse/product/before_save_data` | 商品 set_props() 之前 | `$product` (WC_Product), `$data` (array) | 商品屬性儲存前的攔截處理 |
| `powerhouse/product/after_save_data` | 商品 set_props() 之後 | `$product` (WC_Product), `$data` (array) | 商品屬性儲存後的後續處理 |
| `powerhouse/product/before_save_meta_data` | 商品 meta data 寫入前 | `$product` (WC_Product), `$meta_data` (array) | 商品 meta 儲存前的攔截處理 |
| `powerhouse/product/after_save_meta_data` | 商品 meta data 寫入後 | `$product` (WC_Product), `$meta_data` (array) | 商品 meta 儲存後的後續處理 |

#### Subscription Domain

| Hook | 觸發時機 | 參數 | 用途 |
|------|---------|------|------|
| `powerhouse_subscription_at_date_created` | 訂閱創建後 | `$subscription` (WC_Subscription), `$args` (array) | 訂閱建立事件 |
| `powerhouse_subscription_at_initial_payment_complete` | 訂閱首次付款成功後（僅 parent order） | `$subscription` (WC_Subscription), `$args` (array) | 首次付款完成事件 |
| `powerhouse_subscription_at_subscription_failed` | 訂閱從成功狀態轉為失敗狀態 | `$subscription` (WC_Subscription), `$args` (from_status, to_status) | 訂閱失敗事件 |
| `powerhouse_subscription_at_subscription_success` | 訂閱從失敗狀態恢復為 active | `$subscription` (WC_Subscription), `$args` (from_status, to_status) | 訂閱恢復成功事件 |
| `powerhouse_subscription_at_payment_retry` | 訂閱付款重試 | `$subscription` (WC_Subscription), `$args` (order) | 付款重試事件 |
| `powerhouse_subscription_at_trial_end` | 試用期結束 | `$subscription` (WC_Subscription), `$args` (array) | 試用結束排程事件 |
| `powerhouse_subscription_at_next_payment` | 下次付款時間到達 | `$subscription` (WC_Subscription), `$args` (array) | 下次付款排程事件 |
| `powerhouse_subscription_at_end` | 訂閱結束 | `$subscription` (WC_Subscription), `$args` (array) | 訂閱到期結束排程事件 |
| `powerhouse_subscription_at_end_of_prepaid_term` | 預付期結束（cancelled/pending-cancel 狀態） | `$subscription` (WC_Subscription), `$args` (array) | 預付期結束排程事件 |
| `powerhouse_subscription_at_renewal_order_created` | 續訂訂單建立後 | `$subscription` (WC_Subscription\|int), `$args` (renewal_order) | 續訂訂單建立事件 |
| `powerhouse_subscription_at_watch_trial_end` | 訂閱 trial_end 日期更新時 | `$subscription` (WC_Subscription), `$args` (datetime) | 監聽試用結束日期變更 |
| `powerhouse_subscription_at_watch_next_payment` | 訂閱 next_payment 日期更新時 | `$subscription` (WC_Subscription), `$args` (datetime) | 監聽下次付款日期變更 |
| `powerhouse_subscription_at_watch_end` | 訂閱 end 日期更新時 | `$subscription` (WC_Subscription), `$args` (datetime) | 監聽訂閱結束日期變更 |

#### Email Domain

| Hook | 觸發時機 | 參數 | 用途 |
|------|---------|------|------|
| `powerhouse_delay_email` | 非同步排程觸發延遲寄送 email 時 | `$class_name` (string), `...$args` (mixed) | 延遲發送 WooCommerce email（透過 Action Scheduler） |

---

### Filters

#### Bootstrap / LC Domain

| Hook | 被過濾的值 | 參數 | 用途 |
|------|-----------|------|------|
| `powerhouse_product_infos` | `[]` (array, 預設空陣列) | 無額外參數 | 註冊 Power 外掛產品資訊（key => {name, link}），用於授權碼管理與 admin menu 顯示 |

#### Copy Domain

| Hook | 被過濾的值 | 參數 | 用途 |
|------|-----------|------|------|
| `powerhouse/copy/callback` | `$copy_callback` (callable) | `$post_id` (int), `$copy_terms` (bool), `$override_post_parent` (int\|bool), `$depth` (int) | 覆寫 Post 複製邏輯的 callback |
| `powerhouse/copy/children_post_args` | `$default_args` (array) | `$post_id` (int), `$new_id` (int), `$override_post_parent` (int), `$depth` (int) | 覆寫子文章查詢參數 |

#### Option Domain

| Hook | 被過濾的值 | 參數 | 用途 |
|------|-----------|------|------|
| `powerhouse/option/allowed_fields` | `$fields` (array) | 無額外參數 | 擴展允許的 WP Option 欄位 |
| `powerhouse/option/skip_sanitize_keys` | `$skip_sanitize_keys` (array) | 無額外參數 | 指定哪些 option key 跳過 sanitize |
| `powerhouse/options/get_options` | `$options` (array) | `$request` (WP_REST_Request) | 攔截 GET options API 回傳值 |

#### Post Domain

| Hook | 被過濾的值 | 參數 | 用途 |
|------|-----------|------|------|
| `powerhouse/post/create_post_args` | `$args` (array, wp_insert_post 的參數) | 無額外參數 | 覆寫建立文章的參數（也被 Order CRUD 使用） |
| `powerhouse/post/get_meta_keys_array` | `$meta_keys_array` (array) | `$post` (WP_Post) | 覆寫 Post 回傳的 meta keys |
| `powerhouse/post/separator_body_params` | `$body_params` (array) | `$request` (WP_REST_Request) | 在 Post API 分離資料前過濾 body 參數 |

#### Order Domain

| Hook | 被過濾的值 | 參數 | 用途 |
|------|-----------|------|------|
| `powerhouse/order/get_meta_keys_array` | `$meta_keys_array` (array) | `$post` (WP_Post) | 覆寫 Order 回傳的 meta keys |
| `powerhouse/order/separator_body_params` | `$body_params` (array) | `$request` (WP_REST_Request) | 在 Order API 分離資料前過濾 body 參數 |
| `powerhouse/order/get_options` | `$options` (statuses) | `$request` (WP_REST_Request) | 擴展 Order options API 回傳值 |

#### Product Domain

| Hook | 被過濾的值 | 參數 | 用途 |
|------|-----------|------|------|
| `powerhouse/product/get_meta_keys_array` | `$meta_keys_array` (array) | `$product` (WC_Product) | 覆寫 Product 回傳的 meta keys |
| `powerhouse/product/separator_body_params` | `$body_params` (array) | `$request` (WP_REST_Request) | 在 Product API 分離資料前過濾 body 參數 |
| `powerhouse/product/extend_meta_query` | `[]` (array, 預設空陣列) | `$query` (array), `$query_vars` (array) | 擴展 wc_get_products 的 meta_query 篩選條件 |
| `powerhouse/product/get_options` | `$options` (array) | `$request` (WP_REST_Request) | 擴展 Product options API 回傳值 |

#### Term Domain

| Hook | 被過濾的值 | 參數 | 用途 |
|------|-----------|------|------|
| `powerhouse/term/create_term_args` | `$body_params` (array) | `$request` (WP_REST_Request) | 覆寫建立 Term 的參數 |
| `powerhouse/term/update_term_args` | `$body_params` (array) | `$request` (WP_REST_Request) | 覆寫更新 Term 的參數 |

#### Upload Domain

| Hook | 被過濾的值 | 參數 | 用途 |
|------|-----------|------|------|
| `powerhouse/upload/allowed_mime_types` | `$allowed_mime_types` (array) | 無額外參數 | 擴展或限制允許上傳的檔案類型 |

#### User Domain

| Hook | 被過濾的值 | 參數 | 用途 |
|------|-----------|------|------|
| `powerhouse/user/get_meta_keys_array` | `$meta_keys_array` (array) | `$user` (WP_User) | 覆寫 User 回傳的 meta keys |
| `powerhouse/user/prepare_query_args/meta_query_builder` | `$builder` (MetaQueryBuilder) | 無額外參數 | 覆寫 User 查詢的 meta_query 建構器 |

#### Report Domain

| Hook | 被過濾的值 | 參數 | 用途 |
|------|-----------|------|------|
| `powerhouse/report/revenue/stats` | `$data` (object) | `$query_args` (array) | 過濾營收報表統計資料 |

---

**Hook 統計：** 22 Actions + 18 Filters。詳見原始碼：
- Actions: 1 Copy + 8 Limit + 4 Product + 13 Subscription + 1 Email
- Filters: 1 Bootstrap/LC + 2 Copy + 3 Option + 3 Post + 3 Order + 4 Product + 2 Term + 1 Upload + 2 User + 1 Report
