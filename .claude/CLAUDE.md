# Powerhouse

> **Last synced:** 2026-06-23 | **Version:** 3.3.48 | **PHP Namespace:** `J7\Powerhouse`

## 1. What This Plugin Does

Power 系列外掛的基礎架構平台（Foundation Plugin）。提供統一 REST API 層、DDD Domain 架構、共用工具、授權碼管理、主題系統，以及 WooCommerce 深度整合。所有 Power 外掛都依賴此外掛。

**Core capabilities:**
- **Unified REST API**: 20+ domain API（Post / User / Order / Product / Term / Comment / Upload 等）
- **License Code System**: 多外掛授權碼統一管理
- **Theme System**: 跨 Power 生態系的主題色彩系統；支援「跟隨 Blocksy」動態橋接（偵測 Blocksy 主題後即時讀取調色盤，轉換為 OKLCH 套用至 daisyUI 色彩變數）
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
│   ├── FrontEnd                    # wp_head 輸出 CSS 色彩變數（讀正規化後的 theme）
│   └── Blocksy                     # Blocksy 偵測、調色盤讀取、OKLCH 覆寫生成（Singleton）
├── Theme\Utils\ColorConvert        # 靜態工具：hex_to_oklch()，Hex→OKLCH 色彩轉換
├── Theme\Model\Theme               # Theme DTO + blocksy 分支覆寫邏輯
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
| **Theme** | theme（可選值：`power` \| `blocksy`），enable_theme, enable_theme_changer, theme_css |
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
pnpm build-css:admin        # Admin SCSS → CSS + Tailwind（含 --postcss ./postcss.tailwind.cjs，見 §13）
pnpm build-css:front        # Frontend SCSS → CSS + Tailwind（同上）
pnpm build-css:blocknote    # BlockNote editor styles
pnpm watch-css:admin        # Watch mode（同樣掛 --postcss）

# Code Quality
pnpm lint                   # ESLint + PHPCS
pnpm lint:fix               # Auto-fix
vendor/bin/phpstan analyse  # PHPStan level 9

# Release（🔴 只能在 plugins 目錄下的主 checkout、master 分支跑，worktree 會產出壞 CSS，見 .claude/rules/release.rule.md）
pnpm release:patch          # Patch release（before:init 先跑 release/assert-release-env.cjs 檢查環境）
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

---

## 13. CSS Build Pipeline（`#tw` scope + daisyUI scope 修正）

powerhouse 的 Tailwind build 是 Power 生態系主要的 CSS 產出點——`tailwind.config.cjs`
的 `content` 已把兄弟目錄 `power-*/inc` 與 `power-*/js/src` 一併納入掃描，子外掛（power-shop
等）不再有自己的 Tailwind pipeline，寫的 class 完全依賴這裡的 build 產出。
（例外：power-course 自 Issue #285 起另外自產一份 utility CSS，排在這裡的 CSS 之後。）

🔴 **掃描靠相對路徑 `../power-*`，所以 build 的正確性取決於「建置時旁邊放了什麼」**。
v3.6.0 在 worktree（`C:/Users/User/.ph-release`，旁邊沒有任何 power-* 外掛）建置，
front.min.css 少 786 條、admin.min.css 少 787 條規則，build 與 release 全部照常成功，
約 110 站的 power-course 學員「我的課程」整片空白。**發版只能在 WordPress plugins 目錄下的
主 checkout、master 分支**，由 `release/assert-release-env.cjs` 強制檢查——完整經過、自檢步驟、
依賴外掛清單見 `.claude/rules/release.rule.md`。

### `#tw` scope 前提：沒有這個 id，utility 全部靜默失效

`tailwind.config.cjs` 設 `important: '#tw'`，所有 Tailwind utility 一律輸出成
`#tw .flex{ ... }` 這種前綴選擇器——**必須有一個帶 `id="tw"` 的祖先元素，utility 才會生效**，
沒有時樣式安靜地不出現、不會有任何錯誤或警告。

| 情境 | `id="tw"` 掛在哪 |
|------|-------------------|
| Admin SPA / 任何走 admin-layout 的頁面 | `inc/templates/pages/admin-layout/index.php` 的 `<body>`（2026-08 前一直缺這個 id，`admin.min.css` 內約 900 條 utility 從未在後台生效，各 power-* 子外掛才會各自打包一份無 scope 的 Tailwind 繞過；已補上） |
| 前台一般頁面 | `Theme\Core\FrontEnd` 的 `language_attributes` filter 自動補在 `<html>` |
| 子外掛自印獨立 HTML 骨架（不走 `get_header()`，例如 power-shop 的 `PartnerPortalRenderer`） | 子外掛自己顯式印出，powerhouse 管不到 |

新增任何「不走標準 WP 頁面渲染流程」的頁面時，**必須**確認輸出的最外層容器帶有 `id="tw"`，
否則整頁 Tailwind 全滅。

### daisyUI scope 修正（2026-08-04）

**根因**：
1. daisyUI v4 的元件 class 以 specificity **(0,1,0)** 輸出（例如 `.pc-btn{}`）。
2. daisyUI v4 **沒有** `important` 之類的設定可以提高權重——config 只有 `styled` / `themes` /
   `base` / `utils` / `logs` / `darkTheme` / `prefix` / `themeRoot`。
3. Tailwind 的 `important: '#tw'` **只作用在 utilities**，不會套到外掛用 `addComponents`
   加進來的規則。
4. 子外掛頁面若被 WordPress 佈景主題以相同 specificity (0,1,0) 命中（常見於 `.button` /
   `[type="submit"]` 這類泛用選擇器），**CSS 同分時由載入順序決勝**——佈景主題排在 plugin
   CSS 之後 → **永遠贏**，daisyUI 的尺寸 / 變體 class（`pc-btn-sm` / `pc-btn-ghost` 等）因此
   被主題悄悄蓋掉，且沒有任何錯誤訊息。Tailwind utility 之所以免疫，正是因為
   `#tw .h-8` 是 (1,1,0)，嚴格大於主題那條，與載入順序無關。

**修正**：把所有 daisyUI 選擇器也收進 `#tw`，與 Tailwind utility 用同一套機制。

- 新增 `scripts/postcss-scope-daisyui.cjs`（PostCSS plugin，從 `tailwind.config.cjs` 的
  `important` 讀 scope，避免與 Tailwind 設定漂移，把選擇器前置成 `#tw .pc-btn{}`）
- 新增 `postcss.tailwind.cjs`（給 Tailwind CLI `--postcss` 旗標用的設定，內含上面的 plugin +
  `autoprefixer`）
- `build-css:admin` / `build-css:front` / `watch-css:admin` / `watch-css:front` 四個 script
  都加上 `--postcss ./postcss.tailwind.cjs`

⚠️ **Tailwind CLI 不會自動讀取 `postcss.config.cjs`**（實測：在裡面放一個會印字的 probe
plugin，跑 `npx tailwindcss ...` 完全沒有輸出；那份設定只有在有人直接呼叫 postcss——例如
Vite 的 CSS pipeline——時才生效）。CSS 建置**必須**用 `--postcss` 旗標明確指定
`postcss.tailwind.cjs`，額外的 PostCSS 步驟才會跑。**不要**以為把 plugin 加進
`postcss.config.cjs` 就會生效。

**識別方式**：靠 `daisyui.prefix: 'pc-'`。比對 `.pc-` 與 `\:pc-` 兩種形式（後者是 Tailwind
對響應式變體的轉義寫法，`sm:pc-btn-sm` → `.sm\:pc-btn-sm`），且要求 `pc-` 緊接在 `.` 或
`\:` 之後，才不會誤傷 `.upc-code` 這種剛好含 `pc-` 的第三方 class。

**🔴 根部錨定的選擇器絕對不能加 scope**：`#tw` 本身就掛在 `<html>` / `<body>`。把
`:root .pc-countdown` 變成 `#tw :root .pc-countdown` 之後，會變成「找 `#tw` 底下的
`:root` **後代**」——`:root` 永遠是 `<html>`、不可能是自己的後代，**這條規則從此永遠匹配
不到，且沒有任何錯誤訊息**。實際踩到兩條（daisyUI v4.12）：`:root .pc-countdown`（倒數
計時元件樣式整個失效）與 `:root:has(:is(.pc-modal-open, .pc-modal:target,
.pc-modal-toggle:checked + .pc-modal, .pc-modal[open]))`（Modal 開啟時鎖住背景捲動的規則
失效）。`postcss-scope-daisyui.cjs` 因此有一條 `ROOT_ANCHORED` 守門：selector 第一個
compound 若是 `:root` / `html` / `body` / `*` / `[data-theme` 一律跳過（這類規則本來就已經
是 (0,2,0) 以上、或根本沒有主題會來競爭，不加也安全）。**改動 daisyUI 版本或
`daisyui.prefix` 時，這條識別規則要跟著檢查**——build log 印出的「已把 N 條…收進 "#tw"」
數字若掉到 0，代表保護整個失效。

**不會反過來蓋掉 Tailwind utility**：轉換後 daisyUI 是 `#tw .pc-btn`（1,1,0）、Tailwind
utility 是 `#tw .h-8`（1,1,0），specificity 同分；但 Tailwind 的輸出順序是 base →
components → utilities，utility 排在後面 → utility 仍然勝出，「utility 覆寫元件樣式」這個
基本心智模型完全不變（已實測位置比對確認）。

**涵蓋不到的情況**：本轉換只能讓「daisyUI 有規則、但輸給主題」的情況翻盤。若 daisyUI
**根本沒有**對某個元素出規則，主題的樣式沒有對手，仍要由子外掛自己加 Tailwind utility
壓過去——已知案例：`.pc-modal-backdrop` 內的 `<button>`（daisyUI 只對 form 本身出規則，
完全沒管 button），主題的 `[type="submit"]` 會把它塗成整片實心色塊。這不是 powerhouse
建置層能解決的範圍。

**版本快取**：CSS enqueue 是 `?ver={Plugin::$version}`，重跑 build 只改檔案內容、**版本號
不變**，既有訪客的瀏覽器會沿用舊快取，且**沒有任何錯誤訊息**能提示這件事。部署必要條件：
重新 build CSS 之後必須 bump 版本號（或走正常 `pnpm release:patch` 流程）。

### 消費方（子外掛）驗證方式

子外掛自己沒有 CSS 檔可以 grep，驗證某個 class 是否真的產出要在 powerhouse 這邊確認：

```bash
grep -c '\.mb-2{' js/dist/css/admin.min.css     # 1 = 有產出；0 = 沒有（可能沒重跑 build，或 content 掃描沒命中）
grep -c '#tw \.pc-btn-sm{' js/dist/css/front.min.css   # 確認 daisyUI 已被正確 scope
```
