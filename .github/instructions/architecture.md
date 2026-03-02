# Powerhouse — Architecture Overview

> **Last Updated:** 2025-01-31  
> **Applies to:** All PHP and TypeScript code in this plugin

---

## System Architecture Diagram

```
┌─────────────────────────────────────────────────────────────────┐
│                     plugin.php (Entry Point)                    │
│              namespace J7\Powerhouse — Plugin class             │
│         uses PluginTrait + SingletonTrait from wp-utils         │
└──────────────────────────┬──────────────────────────────────────┘
                           │ plugins_loaded (priority -10)
                           ▼
┌─────────────────────────────────────────────────────────────────┐
│                        Bootstrap.php                            │
│   Initialises all modules & registers admin_menu, wp_enqueue   │
└─────┬────────────┬──────────────┬────────────┬─────────────────┘
      │            │              │            │
      ▼            ▼              ▼            ▼
 Admin/*      Api\Base       Domains\       Theme\
 (6 classes)  (cloud HTTP)   Loader        Core\FrontEnd
              Api\LC         (all V2Apis)
                             │
                    ┌────────┴────────┐
                    │   WP REST API   │
                    │  /v2/powerhouse │
                    └─────────────────┘
```

---

## Layer Breakdown

### Layer 1: Plugin Entry (`plugin.php`)

- Declares the `J7\Powerhouse\Plugin` final class
- Uses `J7\WpUtils\Traits\PluginTrait` — exposes `$dir`, `$url`, `$version`, `$snake`, `$kebab`, `$app_name`, `$env`, `$template_path`
- Uses `J7\WpUtils\Traits\SingletonTrait` — prevents double instantiation
- Provides `Plugin::load_template()` and `Plugin::safe_load_template()` for PHP templates
- Provides `Plugin::logger()` wrapping WC logger
- `activate()` hook creates the `ph_access_itemmeta` DB table

### Layer 2: Bootstrap (`inc/classes/Bootstrap.php`)

The orchestrator. Instantiates all sub-systems and registers core WordPress hooks:

```
admin_menu              → add_menu() + add_submenu()
admin_enqueue_scripts   → enqueue_admin_assets()   (conditional on URL)
wp_enqueue_scripts      → enqueue_frontend_assets() (always)
plugins_loaded          → check_lc_array()          (priority 999)
script_loader_src       → modify_script_src()       (local-env path fix)
```

Asset loading strategy:
- **Frontend**: always load `front.min.css` + `frontend.js` (async)
- **Admin**: load `admin.min.css` + `style.css` when URL contains `power-` or `powerhouse`
- **JS SPA**: only load `main.tsx` bundle when URL matches `admin.php?page=powerhouse`

### Layer 3: Domain Modules (`inc/classes/Domains/`)

Each domain is self-contained and follows the same sub-structure:

```
Domains/{Name}/
├── Core/
│   └── V2Api.php      ← REST API (extends ApiBase)
├── Model/             ← DTOs, entities
├── Service/           ← Business logic services (if complex)
└── Utils/             ← Static helper methods
```

The `Domains\Loader` class instantiates all domains. **WooCommerce domains are conditionally loaded** only when `class_exists('\WooCommerce')`.

### Layer 4: Compatibility / MU-Plugins (`inc/classes/Compatibility/`)

Manages files that must run before regular plugins:

```
Compatibility/
├── Services/
│   ├── Loader.php           → copies powerhouse-loader.php to mu-plugins
│   ├── ApiBooster.php       → copies powerhouse-api-booster.php to mu-plugins
│   ├── DisableFeatures.php  → copies powerhouse-disable-features.php to mu-plugins
│   ├── EmailValidator.php   → copies powerhouse-email-validator.php to mu-plugins
│   ├── AutoUpdate.php       → triggers Powerhouse update when power-* plugins update
│   └── Scheduler.php        → one-time-per-version compatibility runner via AS
├── Shared/
│   └── MuPluginsLoader.php  → base class; handles copy/update of mu-plugin file
└── mu-plugins/
    ├── powerhouse-loader.php         ← load vendor autoloader early
    ├── powerhouse-api-booster.php    ← selective plugin loading per REST route
    ├── powerhouse-disable-features.php ← security hardening
    └── powerhouse-email-validator.php  ← MX record email validation
```

`Scheduler::compatibility()` is run **once per plugin version** via Action Scheduler. It:
1. Creates `ph_access_itemmeta` table if not exists
2. Copies mu-plugin files
3. Modifies Action Scheduler `args` column from `varchar(191)` → `longtext`
4. Flushes rewrite rules and object cache

### Layer 5: Settings Model (`inc/classes/Settings/`)

`Settings extends J7\WpUtils\Classes\DTO` — a typed Data Transfer Object that reads from and writes to the `powerhouse_settings` WP option.

Pattern: `Settings::instance()` → returns singleton loaded from DB. Calling `partial_update()` saves changes.

### Layer 6: Frontend SPA (`js/src/`)

Single Page Application mounted inside WordPress admin:

```
React 18 + TypeScript
    → Vite (build) / vite-for-wp (WP asset manifest)
    → Refine.dev (admin framework)
    → Ant Design 5 (UI)
    → React Query (data fetching)
    → HashRouter (routing, no server config needed)
    → antd-toolkit (workspace shared library)
```

---

## Data Flow: Admin SPA

```
PHP: Bootstrap::enqueue_admin_assets()
  ├── wp_localize_script('powerhouse_data', { env: encrypted_json })
  └── Vite::enqueue_asset('js/src/main.tsx')

Browser: main.tsx loads
  ├── simpleDecrypt(window.powerhouse_data.env) → env object
  ├── ReactDOM.createRoot('#powerhouse_settings')
  └── <App1> renders with Refine + HashRouter

User navigates to /settings
  └── <Settings /> page
        └── useForm (Refine) → GET /wp-json/v2/powerhouse/options/powerhouse_settings
              └── renders form fields
                    └── onFinish → PATCH /wp-json/v2/powerhouse/options/powerhouse_settings
```

---

## Namespace Map

| PHP Namespace | Directory |
|---------------|-----------|
| `J7\Powerhouse` | `inc/classes/` |
| `J7\Powerhouse\Admin` | `inc/classes/Admin/` |
| `J7\Powerhouse\Api` | `inc/classes/Api/` |
| `J7\Powerhouse\Captcha` | `inc/classes/Captcha/` |
| `J7\Powerhouse\Compatibility` | `inc/classes/Compatibility/` |
| `J7\Powerhouse\Contracts` | `inc/classes/Contracts/` |
| `J7\Powerhouse\Domains\{Name}` | `inc/classes/Domains/{Name}/` |
| `J7\Powerhouse\Settings` | `inc/classes/Settings/` |
| `J7\Powerhouse\Shared` | `inc/classes/Shared/` |
| `J7\Powerhouse\Theme` | `inc/classes/Theme/` |
| `J7\Powerhouse\Utils` | `inc/classes/Utils/` |
| `J7\Powerhouse\MU` | `inc/classes/Compatibility/mu-plugins/` |

---

## Key Shared Library (`j7-dev/wp-utils`)

All classes come from `vendor/j7-dev/wp-utils/src/`:

| Class / Trait | Usage |
|---------------|-------|
| `J7\WpUtils\Traits\SingletonTrait` | Singleton pattern — used on every long-lived class |
| `J7\WpUtils\Traits\PluginTrait` | Plugin metadata (url, dir, version, snake, kebab, etc.) |
| `J7\WpUtils\Classes\ApiBase` | Base for all REST API domain classes |
| `J7\WpUtils\Classes\DTO` | Base for Settings and other data objects |
| `J7\WpUtils\Classes\General` | `json_parse()`, `array_find()`, `parse()`, `in_url()` |
| `J7\WpUtils\Classes\WP` | `sanitize_text_field_deep()`, `include_required_params()`, `is_table_exists()` |
| `J7\WpUtils\Classes\WC` | `logger()`, `log()` (WooCommerce logger wrapper) |

---

## Dependency Graph

```
powerhouse
├── j7-dev/wp-utils (^0.3)        ← shared utility library
├── kucrut/vite-for-wp (^0.11)    ← Vite asset manifest loader
├── brainfoolong/js-aes-php (^1.0) ← AES encryption for LC transients
├── gregwar/captcha (^1.2)         ← captcha image generation
└── symfony/finder (6.0.19)        ← file system utilities (locked version)

power-course, power-shop, power-docs, power-partner, power-payment...
└── powerhouse                     ← all child plugins depend on this
```

---

## Environment Handling

`Plugin::$env` is derived from `wp_get_environment_type()`:

| WordPress Env Type | Cloud API URL | Behaviour |
|--------------------|--------------|-----------|
| `local` | `http://cloud.local` | Dev mode, script path fix |
| `staging` | `https://cloud-staging.wpsite.pro` | Staging API |
| production (default) | `https://cloud.luke.cafe` | Production API |
