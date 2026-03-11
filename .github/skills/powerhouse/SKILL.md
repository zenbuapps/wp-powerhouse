---
name: powerhouse
description: "Powerhouse — WordPress 核心基礎外掛開發指引。所有 power-* 外掛的依賴核心，提供 REST API 框架、授權碼系統、存取控制、主題系統、驗證碼保護。React 18 + Refine.dev 管理介面。使用 /powerhouse 觸發。"
origin: project-analyze
---

# powerhouse — 開發指引

> WordPress 核心基礎外掛（"library as plugin"），所有 `power-*` 外掛的共用依賴。提供 `J7\WpUtils` 工具庫、REST API 框架、授權碼管理、存取控制（`ph_access_itemmeta`）、主題系統與驗證碼保護。

## When to Activate

當使用者在此專案中：
- 修改 `inc/classes/**/*.php`（核心功能：REST API、授權碼、存取控制）
- 修改 `js/src/**/*.tsx`（React/Refine.dev 管理介面）
- 新增或修改 `ph_access_itemmeta` 存取控制邏輯
- 詢問 `J7\WpUtils`、授權碼 AES 加密、Refine.dev 多資料來源相關問題

## 架構概覽

**技術棧：**
- **語言**: PHP 8.1+（`declare(strict_types=1)`）
- **框架**: WordPress 5.7+、WooCommerce（選用增強）
- **關鍵依賴**: `j7-dev/wp-utils ^0.3`、`brainfoolong/js-aes-php`（AES 加密）、`gregwar/captcha`、`symfony/finder`
- **前端**: React 18 + TypeScript + Refine.dev + Ant Design 5 + TailwindCSS + daisyUI + TanStack Query
- **建置**: Vite（開發 port **5179**）
- **代碼風格**: PHPCS（WordPress-Core）、PHPStan、ESLint + Prettier

## 目錄結構

```
powerhouse/
├── plugin.php                                      # 主入口（PluginTrait + SingletonTrait）
├── inc/
│   ├── classes/
│   │   ├── Bootstrap.php                           # 初始化所有子系統
│   │   ├── Api/
│   │   │   └── V2/
│   │   │       ├── Base.php                        # REST API 基類（v2/powerhouse 命名空間）
│   │   │       ├── Posts.php                       # 通用文章 CRUD API
│   │   │       ├── Users.php                       # 用戶管理 API
│   │   │       ├── Terms.php                       # 分類/標籤 API
│   │   │       └── Options.php                     # WordPress Options API
│   │   ├── Domains/
│   │   │   ├── LicenseCode/
│   │   │   │   ├── LC.php                          # 授權碼核心（AES 加解密）
│   │   │   │   ├── Api.php                         # 授權碼 REST API
│   │   │   │   └── Checker.php                     # 授權碼驗證邏輯
│   │   │   ├── Limit/
│   │   │   │   ├── Api.php                         # ph_access_itemmeta 存取控制 API
│   │   │   │   └── Manager.php                     # 存取限制管理
│   │   │   ├── Theme/
│   │   │   │   ├── Manager.php                     # daisyUI 主題注入（<html data-theme="">）
│   │   │   │   └── Api.php                         # 主題設定 API
│   │   │   ├── Captcha/
│   │   │   │   └── Handler.php                     # 登入/註冊驗證碼保護
│   │   │   └── WooCommerce/
│   │   │       ├── DelayedEmail.php                # 延遲發送訂單 Email
│   │   │       └── DebugLog.php                    # WC Debug Log 檢視器
│   │   ├── Admin/
│   │   │   └── Entry.php                           # 全螢幕管理頁面渲染器
│   │   └── Shared/
│   │       └── Utils/                              # 內部工具方法
├── js/src/
│   ├── main.tsx                                    # React 掛載入口
│   ├── App1.tsx                                    # Refine 應用 Shell（多 dataProvider）
│   ├── resources/index.tsx                         # Refine 資源定義
│   ├── api/resources/                              # CRUD API 函數
│   ├── components/                                 # 共用元件
│   ├── hooks/
│   │   └── useEnv.tsx                              # 環境變數訪問
│   ├── pages/admin/
│   │   ├── LicenseCodes/                           # 授權碼管理頁
│   │   ├── Limits/                                 # 存取控制管理頁
│   │   ├── Theme/                                  # 主題設定頁
│   │   └── Settings/                               # 外掛設定頁
│   └── types/
│       └── global.d.ts                             # window.powerhouse_data 等型別
```

## REST API 框架（v2/powerhouse）

Powerhouse 提供通用 REST API，所有 `power-*` 外掛可透過此框架存取 WordPress 資源：

```php
// 命名空間：v2/powerhouse
// 支援的資源：posts, users, terms, options, media
// 所有端點需 X-WP-Nonce 認證

// 前端透過 Refine.dev dataProvider 使用
const defaultDataProvider = dataProvider('/v2/powerhouse');
```

| 方法 | 端點 | 說明 |
|------|------|------|
| GET | `/v2/powerhouse/posts` | 取得文章列表（支援 CPT、過濾、分頁） |
| GET/POST/PUT/DELETE | `/v2/powerhouse/posts/{id}` | 文章 CRUD |
| GET | `/v2/powerhouse/users` | 用戶列表 |
| GET | `/v2/powerhouse/terms` | 分類/標籤列表 |
| GET/POST | `/v2/powerhouse/options` | WordPress Options CRUD |

## 授權碼系統（AES 加密）

```php
// AES-256-CBC 加密儲存授權碼
// 依賴 brainfoolong/js-aes-php（PHP 與 JS 雙端相容）

class LC {
    public static function encrypt(string $data): string { ... }
    public static function decrypt(string $encrypted): string { ... }
    public static function validate(string $lc, string $domain): bool { ... }
}

// 授權碼格式：product_id + site_domain + expiry + random_salt → AES 加密
// 驗證：解密後核對 domain 是否匹配
```

## ph_access_itemmeta 存取控制

自訂資料表，儲存用戶對特定資源（課程、文章等）的存取權限：

```sql
-- 表結構
CREATE TABLE ph_access_itemmeta (
    meta_id     BIGINT(20) AUTO_INCREMENT PRIMARY KEY,
    user_id     BIGINT(20) NOT NULL,
    item_id     BIGINT(20) NOT NULL,        -- post_id 或 product_id
    item_type   VARCHAR(50) NOT NULL,        -- 'course', 'doc', etc.
    meta_key    VARCHAR(255) NOT NULL,
    meta_value  LONGTEXT,
    created_at  DATETIME NOT NULL
);
```

```php
// 存取控制 API
$has_access = Limit\Manager::check_access($user_id, $item_id, 'course');
Limit\Manager::grant_access($user_id, $item_id, 'course', $meta);
Limit\Manager::revoke_access($user_id, $item_id, 'course');
```

## 主題系統（daisyUI）

```php
// 在 <html> 標籤注入 data-theme 屬性
// 支援 daisyUI 主題切換（light/dark/custom）
add_filter('language_attributes', function ($output) {
    $theme = Theme\Manager::get_current_theme();
    return $output . ' data-theme="' . esc_attr($theme) . '"';
});
```

## Refine.dev DataProvider 配置

```typescript
// App1.tsx — Powerhouse 自身的 Refine App
const dataProviders = {
    default:      dataProvider('/v2/powerhouse'),    // Powerhouse REST API
    'wp-rest':    dataProvider('/wp/v2'),            // WordPress Core REST API
    'wc-rest':    dataProvider('/wc/v3'),            // WooCommerce REST API
}
```

## PHP → JS 資料橋接

```typescript
// SettingTabService — 透過 wp_localize_script 傳遞
window.powerhouse_data = {
    env: {
        SITE_URL, API_URL, NONCE, CURRENT_USER_ID,
        APP_NAME, KEBAB, SNAKE, IS_LOCAL,
    }
}
```

## 命名慣例

| 類型 | 慣例 | 範例 |
|------|------|------|
| PHP Namespace | PascalCase | `J7\Powerhouse\Domains\LicenseCode` |
| PHP 類別 | PascalCase（final） | `final class LC` |
| DB 表前綴 | `ph_` | `ph_access_itemmeta` |
| React 元件 | PascalCase | `LicenseCodeList` |
| Text Domain | snake_case | `powerhouse` |

## 開發規範

1. Powerhouse 是其他所有外掛的依賴核心，**API 必須向後兼容**
2. `ph_access_itemmeta` 表的 schema 變更需透過 dbDelta 遷移，不可直接 ALTER
3. 授權碼 AES 加解密必須 PHP + JS 雙端一致（使用 `brainfoolong/js-aes-php`）
4. REST API 端點(`v2/powerhouse`)需支援所有 power-* 外掛的資料需求
5. 主題系統只注入 data-theme 屬性，CSS 由各外掛自行引入 daisyUI

## 常用指令

```bash
composer install           # 安裝 PHP 依賴
pnpm install               # 安裝 Node 依賴
pnpm dev                   # Vite 開發伺服器（port 5179）
pnpm build                 # 建置到 js/dist/
vendor/bin/phpcs           # PHP 代碼風格檢查
vendor/bin/phpstan analyse # PHPStan 靜態分析
pnpm release               # 發佈 patch 版本
```

## 相關 SKILL

- `wordpress-master` — WordPress Plugin 開發通用指引
- `react-master` — React 前端開發指引
- `refine` — Refine.dev 框架使用指引
- `wp-rest-api` — REST API 設計規範
