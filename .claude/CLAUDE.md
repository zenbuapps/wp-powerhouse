# Powerhouse

> **Last synced:** 2026-04-09 | **Version:** 3.3.48 | **PHP Namespace:** `J7\Powerhouse`

## 1. What This Plugin Does

Power 系列外掛的基礎架構平台（Foundation Plugin）。提供統一 REST API 層、DDD Domain 架構、共用工具、授權碼管理、主題系統，以及 WooCommerce 深度整合。所有 Power 外掛都依賴此外掛。

**Core capabilities:**
- **Unified REST API**: 20+ domain API（Post / User / Order / Product / Term / Comment / Upload 等）
- **License Code System**: 多外掛授權碼統一管理
- **Theme System**: 跨 Power 生態系的主題色彩系統
- **Admin SPA**: React + Refine 管理介面（Settings / License Codes）
- **API Booster**: mu-plugin 級 API 效能優化
- **Email System**: 延遲發送、domain 驗證、CAPTCHA
- **WooCommerce**: 訂單 / 商品 / 訂閱 / 報表 API

---

## 2. Tech Stack

| Layer | Technology |
|-------|-----------|
| PHP | 8.1+, WordPress 5.7+, `declare(strict_types=1)` |
| Frontend | React 18 + Refine v4 + Ant Design 5 |
| State | Jotai, TanStack Query v4 |
| Editor | BlockNote v0.30 |
| Build | Vite 6 + `@kucrut/vite-for-wp` |
| CSS | TailwindCSS 3 + SCSS + antd-style |
| Routing | React Router v7 |
| Testing | PHPStan level 9, PHPCS, Playwright E2E |
| WP Env | wp-env |

---

## 3. Architecture

```
J7\Powerhouse\
├── Plugin                          # Singleton + PluginTrait 入口
├── Bootstrap                       # 初始化、admin menu、script enqueue
│
├── Domains/                        # DDD Core — 每個 domain 自帶 CRUD + REST API
│   ├── Post\Core\V2Api             # Post CRUD (all post types)
│   ├── Comment\Core\V2Api          # Comment CRUD
│   ├── Term\Core\V2Api             # Taxonomy term CRUD
│   ├── User\Core\V2Api             # User CRUD
│   ├── Option\Core\V2Api           # WP Options read/write
│   ├── Upload\Core\V2Api           # File upload
│   ├── Shortcode\Core\V2Api        # Shortcode listing
│   ├── Plugin\Core\V2Api           # Plugin listing
│   ├── Copy\Core\V2Api             # Post copying with metadata
│   ├── LC\Core\V2Api               # License Code activate/deactivate
│   ├── Limit\Core\V2Api            # Usage limit grant/revoke
│   ├── Report\Revenue\V2Api        # Revenue report
│   ├── Order\Core\V2Api            # WooCommerce Order CRUD
│   ├── Product\Core\V2Api          # WooCommerce Product CRUD + variations
│   ├── ProductAttribute\Core\V2Api # Product attribute management
│   ├── Subscription\Core\Loader    # WC Subscriptions integration
│   ├── Woocommerce\Core\V2Api      # WC countries/settings
│   ├── Register\Core\              # User registration hooks
│   ├── Email\Services\             # Email handling
│   ├── MessageTemplate\            # Message template CPT
│   └── AsSchedulerHandler\         # Async task handler
│
├── Admin\                          # Admin-specific features
│   ├── Entry, Debug, DelayEmail
│   ├── OrderDetail, OrderList, Account
│
├── Api\                            # External API (cloud.luke.cafe)
│   ├── Base                        # Base API client
│   └── LC                          # License Code API (deprecated)
│
├── Captcha\Core\                   # CAPTCHA (login + register)
│
├── Compatibility\                  # System-wide services
│   ├── mu-plugins/                 # Must-use plugins
│   │   ├── powerhouse-api-booster.php
│   │   ├── powerhouse-disable-features.php
│   │   ├── powerhouse-email-validator.php
│   │   └── powerhouse-loader.php
│   └── Services\
│       ├── ApiBooster, AutoUpdate
│       ├── DisableFeatures, Scheduler
│
├── Contracts\                      # Interfaces & DTOs
│   ├── DTOs\{CallableDTO, FormFieldDTO, MessageTemplateDTO}
│   └── Interfaces\
│
├── Infrustructures\Repositories\   # Data persistence
│   └── MessageTemplate\Register    # CPT: ph_message_tpl
│
├── Settings\Model\Settings         # Main settings DTO (powerhouse_settings)
├── Theme\Core\                     # Theme color system
├── Shared\{Enums, Helpers}         # Shared enums & helpers
└── Utils\                          # Utilities
    ├── Base (encryption, templates, plugin links)
    ├── Compare, DateTimeHandler, ExportCSV
```

---

## 4. REST API

**Namespace:** `v2/powerhouse`

### Core APIs
| Endpoint | Methods | Domain |
|----------|---------|--------|
| `posts`, `posts/{id}` | GET, POST, DELETE | Post CRUD (all CPTs) |
| `posts/sort` | POST | Reorder (menu_order) |
| `comments`, `comments/{id}` | GET, POST | Comment CRUD |
| `terms/{taxonomy}` | GET, POST, DELETE | Term CRUD |
| `users`, `users/{id}` | GET, POST, DELETE | User CRUD |
| `options` | GET, POST | WP Options |
| `upload` | POST | File upload |
| `shortcodes` | GET | List shortcodes |
| `plugins` | GET | List active plugins |
| `copy/{id}` | POST | Copy post + metadata |

### License Code
| Endpoint | Method | Purpose |
|----------|--------|---------|
| `lc` | GET | List license codes |
| `lc/activate` | POST | Activate LC |
| `lc/deactivate` | POST | Deactivate LC |
| `lc/invalidate` | POST | Clear LC cache |

### WooCommerce APIs
| Endpoint | Methods | Purpose |
|----------|---------|---------|
| `orders`, `orders/{id}` | GET, POST, DELETE | Order CRUD |
| `orders/options` | GET | Order options |
| `order-notes`, `order-notes/{id}` | GET, POST, DELETE | Order notes |
| `products`, `products/{id}` | GET, POST, DELETE | Product CRUD |
| `products/select` | GET | Product select (optimized) |
| `products/attributes/{id}` | GET, POST | Attribute management |
| `products/create-variations/{id}` | POST | Generate variations |
| `products/bind-items` | POST | Bind viewing permissions |
| `product-attributes` | GET, POST, DELETE | Attribute CRUD |
| `woocommerce/countries` | GET | Countries list |
| `limit/grant-users` | POST | Grant access limits |
| `report/revenue` | GET | Revenue report |

---

## 5. Custom Post Types

### `ph_message_tpl` (Message Template)
- **Public:** true, has_archive: true
- **Supports:** title, custom-fields

---

## 6. Settings

**Option Key:** `powerhouse_settings`

| Category | Key Settings |
|----------|-------------|
| **General** | enable_manual_send_email, enable_captcha_login/register, email_domain_check |
| **WooCommerce** | delay_email, last_name_optional |
| **Theme** | theme, enable_theme, enable_theme_changer, theme_css |
| **Lab** | api_booster_rules, api_booster_rule_recipes |
| **BunnyCDN** | bunny_library_id, bunny_cdn_hostname, bunny_stream_api_key |

---

## 7. Frontend (React Admin SPA)

**Mount:** `#powerhouse_settings`
**Framework:** Refine v4 + Ant Design 5

**Pages:**
- Settings → General / Theme / Lab / WooCommerce
- License Codes → Activate/Deactivate

**Data Providers:**
- Powerhouse REST API (`/v2/powerhouse`)
- WordPress REST API (`/wp/v2`)
- WooCommerce REST API (`/wc/v3`, `/wc/store/v1`)
- BunnyCDN Stream API (conditional)

---

## 8. Extensibility Hooks

| Hook | Type | Purpose |
|------|------|---------|
| `powerhouse_product_infos` | filter | 註冊 Power 外掛產品資訊 |
| `powerhouse/option/allowed_fields` | filter | 允許的 option fields |
| `powerhouse/option/skip_sanitize_keys` | filter | 跳過 sanitize 的 keys |
| `powerhouse/options/get_options` | filter | 攔截 option 讀取 |
| `powerhouse_after_copy_post` | action | Post 複製後事件 |
| `powerhouse_delay_email` | action | 延遲發送 email |

---

## 9. Shared Infrastructure (for Power Ecosystem)

Powerhouse 是 Power 外掛生態系的共用基礎：

- **ApiBase**: REST API 自動註冊框架（所有 Power 外掛的 API 都繼承此類）
- **SingletonTrait / PluginTrait**: 外掛生命週期管理
- **DTO Base**: 型別安全的資料傳輸物件
- **License Code System**: 統一授權碼管理
- **Theme System**: 跨外掛主題色彩同步
- **Plugin Links**: `get_plugin_links()` 提供 Power 外掛間導航
- **cloud.luke.cafe API**: 遠端 API 通訊基底

---

## 10. Commands

```bash
# Development
pnpm dev                    # Vite dev server (port 5179)
pnpm build                  # Build React app → js/dist/

# CSS (separate pipeline)
pnpm build-css:admin        # Admin SCSS → CSS + Tailwind
pnpm build-css:front        # Frontend SCSS → CSS + Tailwind
pnpm build-css:blocknote    # BlockNote editor styles
pnpm watch-css:admin        # Watch mode

# Code Quality
pnpm lint                   # ESLint + PHPCS
pnpm lint:fix               # Auto-fix
vendor/bin/phpstan analyse  # PHPStan level 9

# Release
pnpm release:patch          # Patch release
pnpm sync:version           # Sync version
pnpm i18n                   # Generate POT

# Setup
pnpm bootstrap              # composer install
```

---

## 11. Dependencies

**PHP:** j7-dev/wp-utils ^0.3, kucrut/vite-for-wp ^0.11, brainfoolong/js-aes-php ^1.0, gregwar/captcha ^1.2, symfony/finder 6.0
**JS (key):** react 18, @refinedev/core 4, antd 5, @tanstack/react-query 4, jotai, @blocknote/react 0.30, react-router 7, tailwindcss 3, vite 6

---

## 12. mu-plugins

系統級功能，在 WordPress 載入時最早執行：

| File | Purpose |
|------|---------|
| `powerhouse-api-booster.php` | API 效能優化（條件式停用不需要的 hook） |
| `powerhouse-disable-features.php` | 停用 WP 預設功能（emoji、embed 等） |
| `powerhouse-email-validator.php` | Email domain 白名單驗證 |
| `powerhouse-loader.php` | Powerhouse 早期載入 |
