# Powerhouse — GitHub Copilot Instructions

> **Last Updated:** 2025-01-31
> **Plugin Version:** 3.3.46
> **PHP:** 8.1+ | **WordPress:** 5.7+ | **WooCommerce:** Optional (enhanced)

---

## 1. Project Overview

**Powerhouse** is a **"library as plugin"** — the CORE foundation plugin that **all other `power-*` plugins depend on**. It provides:

- The `J7\WpUtils` shared utilities library (via Composer)
- A generic REST API layer (`v2/powerhouse`) for every WordPress resource
- A license code (LC) management system tied to `cloud.luke.cafe`
- A custom database table (`ph_access_itemmeta`) for access/limit tracking
- Theme system (daisyUI + Tailwind CSS) injected into the `<html>` tag
- Captcha protection for login/register
- WooCommerce admin enhancements (delayed emails, debug log viewer, etc.)
- A React/TypeScript admin SPA (Refine.dev + Ant Design 5) at `wp-admin → Powerhouse`

---

## 2. Directory Structure

```
powerhouse/
├── plugin.php                   # Plugin entry point — declares Plugin class, calls Plugin::instance()
├── inc/
│   ├── classes/                 # PHP source — PSR-4: J7\Powerhouse\ → here
│   │   ├── Bootstrap.php        # Boots all modules; enqueues assets
│   │   ├── Admin/               # WP admin pages (Entry, Debug, DelayEmail, Account, OrderList, OrderDetail)
│   │   ├── Api/                 # Cloud API client (Base.php for cloud.luke.cafe, LC.php)
│   │   ├── Captcha/             # Login/register captcha (gregwar/captcha)
│   │   ├── Compatibility/
│   │   │   ├── Services/        # AutoUpdate, ApiBooster, DisableFeatures, EmailValidator, Loader, Scheduler
│   │   │   ├── Shared/          # MuPluginsLoader base class
│   │   │   └── mu-plugins/      # Files copied to wp-content/mu-plugins at runtime
│   │   ├── Contracts/           # PHP interfaces
│   │   ├── Domains/             # Domain modules (each has Core/, Model/, Utils/, etc.)
│   │   │   ├── Comment/         # Comment CRUD API
│   │   │   ├── Copy/            # WC duplicate/copy
│   │   │   ├── LC/              # License Code management
│   │   │   ├── Limit/           # Access limit system + custom DB table
│   │   │   ├── Loader.php       # Instantiates all domain V2Api classes
│   │   │   ├── MessageTemplate/ # Email message templates
│   │   │   ├── Option/          # WordPress options CRUD API
│   │   │   ├── Order/           # WC order API
│   │   │   ├── Plugin/          # Plugin management API
│   │   │   ├── Post/            # Post CRUD API (reference implementation)
│   │   │   ├── Product/         # WC product API
│   │   │   ├── ProductAttribute/# WC product attribute API
│   │   │   ├── Register/        # User registration filters
│   │   │   ├── Report/Revenue/  # Revenue reporting API
│   │   │   ├── Shortcode/       # Shortcode execution API
│   │   │   ├── Subscription/    # WC Subscriptions support
│   │   │   ├── Term/            # Taxonomy term CRUD API
│   │   │   ├── Upload/          # File upload API
│   │   │   ├── User/            # User CRUD API
│   │   │   └── Woocommerce/     # Generic WC API
│   │   ├── Settings/
│   │   │   ├── Core/            # ApiBoosterRule
│   │   │   └── Model/           # Settings DTO (powerhouse_settings option)
│   │   ├── Shared/
│   │   │   ├── Enums/           # EObjectType enum
│   │   │   └── Helpers/
│   │   ├── Theme/               # daisyUI/TailwindCSS theme system
│   │   └── Utils/               # Base utility class (static helpers)
│   ├── assets/                  # Static PHP-side assets (frontend.js)
│   └── templates/
│       ├── components/          # Reusable PHP templates (breadcrumb, card, hero, theme, etc.)
│       └── pages/
│           └── admin-layout/    # Admin SPA shell (index.php + bar.php)
└── js/
    ├── src/                     # React/TypeScript frontend
    │   ├── main.tsx             # Entry point — mounts React app to DOM
    │   ├── App1.tsx             # Refine.dev app with HashRouter + all routes
    │   ├── api/                 # API resource definitions
    │   ├── hooks/               # Custom React hooks
    │   ├── pages/admin/         # Admin pages: Settings, LicenseCode
    │   ├── resources/           # Refine.dev resource definitions
    │   ├── types/               # TypeScript types
    │   └── utils/               # env decryption, constants, helpers
    └── dist/                    # Built assets (Vite output, committed)
```

---

## 3. PHP Architecture

### 3.1 Plugin Bootstrap Flow

```
plugin.php
  └── Plugin::instance()                   ← calls PluginTrait::init()
        └── Bootstrap::instance()          ← priority -10 via plugins_loaded
              ├── Admin\Entry              ← renders admin SPA shell
              ├── Admin\Debug              ← debug log viewer (WC required)
              ├── Admin\OrderList          ← custom WC order list (WC required)
              ├── Admin\Account            ← last name optional (WC required)
              ├── Admin\DelayEmail         ← WC email delay (WC required)
              ├── Api\Base                 ← cloud.luke.cafe HTTP client
              ├── Api\LC                   ← LC REST API registration
              ├── Domains\Loader           ← all domain V2Api classes
              ├── Theme\Core\FrontEnd      ← html[data-theme] injection
              ├── Captcha\Core\Login       ← login captcha
              ├── Captcha\Core\Register    ← register captcha
              └── hooks: admin_menu, wp_enqueue_scripts, admin_enqueue_scripts
```

### 3.2 Class Conventions

Every class **MUST** follow this pattern:

```php
<?php
declare(strict_types=1);

namespace J7\Powerhouse\YourDomain;

/**
 * Class YourClass
 * 繁體中文描述
 */
final class YourClass {
    use \J7\WpUtils\Traits\SingletonTrait;   // ← ALWAYS use SingletonTrait

    /** Constructor */
    public function __construct() {
        $this->register_hooks();
    }

    /**
     * 註冊 WordPress hooks
     * @return void
     */
    private function register_hooks(): void {
        \add_action('init', [ __CLASS__, 'init_callback' ]);
        \add_filter('the_content', [ __CLASS__, 'filter_content' ]);
    }

    // All methods should be static unless they rely on instance state
    public static function init_callback(): void { ... }
}

// Instantiate via singleton
YourClass::instance();
```

**Rules:**
- `declare(strict_types=1)` at top of every file
- Namespaces: `J7\Powerhouse\{Module}` or `J7\Powerhouse\Domains\{Domain}`
- Prefer `static` methods; only use instance methods for hooks that need `$this`
- Use `SingletonTrait` for all long-lived classes
- All comments/docblocks in **Traditional Chinese (繁體中文)**
- Naming: `snake_case` for methods/variables, `PascalCase` for classes, `UPPER_SNAKE_CASE` for constants

### 3.3 REST API Domain Pattern

Every domain API class **extends `J7\WpUtils\Classes\ApiBase`** and follows this convention:

```php
final class V2Api extends ApiBase {
    use \J7\WpUtils\Traits\SingletonTrait;

    protected $namespace = 'v2/powerhouse';   // ← always this namespace

    // Array of { endpoint, method, permission_callback }
    protected $apis = [
        [ 'endpoint' => 'posts',           'method' => 'get',    'permission_callback' => null ],
        [ 'endpoint' => 'posts/(?P<id>\d+)','method' => 'get',   'permission_callback' => null ],
        [ 'endpoint' => 'posts',           'method' => 'post',   'permission_callback' => null ],
        [ 'endpoint' => 'posts/(?P<id>\d+)','method' => 'post',  'permission_callback' => null ],
        [ 'endpoint' => 'posts',           'method' => 'delete', 'permission_callback' => null ],
        [ 'endpoint' => 'posts/(?P<id>\d+)','method' => 'delete','permission_callback' => null ],
    ];

    // Callback naming: {method}_{endpoint_snake}_callback
    // e.g. get_posts_callback, post_posts_callback, delete_posts_with_id_callback
    public function get_posts_callback( \WP_REST_Request $request ): \WP_REST_Response { ... }
}
```

**Callback naming rule:** Replace `/` and `(?P<id>\d+)` to form the method name:
- `GET posts` → `get_posts_callback`
- `GET posts/(?P<id>\d+)` → `get_posts_with_id_callback`
- `POST posts` → `post_posts_callback`
- `DELETE posts/(?P<id>\d+)` → `delete_posts_with_id_callback`

**Standard response pattern:**
```php
return new \WP_REST_Response([
    'code'    => 'create_success',
    'message' => __('success message', 'powerhouse'),
    'data'    => $result,
]);
```

**Pagination headers** (for list endpoints):
```php
$response = new \WP_REST_Response($formatted_posts);
$response->header('X-WP-Total',       (string) $total);
$response->header('X-WP-TotalPages',  (string) $total_pages);
$response->header('X-WP-CurrentPage', (string) $args['paged']);
$response->header('X-WP-PageSize',    (string) $args['posts_per_page']);
return $response;
```

### 3.4 Settings DTO

Settings are stored in the `powerhouse_settings` WordPress option and accessed via the `Settings` DTO:

```php
use J7\Powerhouse\Settings\Model\Settings;

// Get current settings (singleton)
$settings = Settings::instance();
$theme    = $settings->theme;            // 'power' | 'light' | 'dark' | ...
$delay    = $settings->delay_email;      // 'yes' | 'no'
$captcha  = $settings->enable_captcha_login; // 'yes' | 'no'

// Partial update (preserves existing values)
$settings->partial_update([ 'theme' => 'dark' ]);
```

**Settings DTO fields:**

| Field | Type | Default | Description |
|-------|------|---------|-------------|
| `enable_manual_send_email` | string | `'no'` | Allow manual email sending |
| `enable_captcha_login` | string | `'no'` | Login captcha |
| `captcha_role_list` | array | `['administrator']` | Roles requiring captcha |
| `enable_captcha_register` | string | `'no'` | Register captcha |
| `enable_email_domain_check_register` | string | `'yes'` | Validate email domain on register |
| `enable_email_domain_check_wp_mail` | string | `'yes'` | Validate email domain on `wp_mail` |
| `email_domain_check_white_list` | array | `['gmail.com',...]` | Whitelisted domains |
| `delay_email` | string | `'yes'` | Delay WC emails via Action Scheduler |
| `last_name_optional` | string | `'yes'` | Make WC last name field optional |
| `theme` | string | `'power'` | daisyUI theme |
| `enable_theme_changer` | string | `'no'` | Frontend theme switcher |
| `enable_theme` | string | `'yes'` | Apply theme to frontend |
| `theme_css` | array | `[]` | Custom CSS for `custom` theme |
| `api_booster_rules` | array | `[]` | API Booster plugin filter rules |
| `bunny_library_id` | string | `''` | BunnyCDN library ID |
| `bunny_cdn_hostname` | string | `''` | BunnyCDN hostname |
| `bunny_stream_api_key` | string | `''` | BunnyCDN stream API key |

### 3.5 License Code (LC) System

The LC system validates plugin licenses against `cloud.luke.cafe`:

```php
// Register a product for LC management (use this filter in your plugin)
\add_filter('powerhouse_product_infos', function(array $infos): array {
    $infos['my-plugin'] = [
        'name' => 'My Plugin',
        'link' => 'https://example.com/my-plugin',
    ];
    return $infos;
});

// Check if a product is activated
$is_activated = \J7\Powerhouse\Domains\LC\Utils\Base::ia('my-plugin');

// Manually activate/deactivate
$result = \J7\Powerhouse\Domains\LC\Utils\Base::activate('LICENSE_CODE', 'my-plugin');
$result = \J7\Powerhouse\Domains\LC\Utils\Base::deactivate('LICENSE_CODE', 'my-plugin');
```

LC statuses are **encrypted** using `JsAesPhp` with `Plugin::$kebab` as the key and cached in WordPress transients (`lc_{product_slug}`) for 24 hours.

### 3.6 Access Limit System

Custom DB table `{prefix}ph_access_itemmeta` stores per-user access metadata:

```php
use J7\Powerhouse\Domains\Limit\Models\Limit;

// Limit types: 'unlimited' | 'fixed' | 'assigned' | 'follow_subscription'
// Limit units: 'timestamp' | 'day' | 'month' | 'year'
$limit = new Limit(
    limit_type:  'fixed',
    limit_value: 30,
    limit_unit:  'day',
    prefix:      '',        // optional prefix for meta keys
);
```

Table structure (`{prefix}ph_access_itemmeta`):
- `meta_id` — PK
- `post_id` — content item ID
- `user_id` — WP user ID
- `meta_key` — arbitrary metadata key
- `meta_value` — metadata value (longtext)

### 3.7 Compatibility / MU-Plugins System

Powerhouse copies specialized PHP files into `wp-content/mu-plugins` via `MuPluginsLoader`:

| File | Purpose |
|------|---------|
| `powerhouse-loader.php` | Forces Powerhouse vendor to load first (highest priority) |
| `powerhouse-api-booster.php` | Selective plugin loading per REST route (API speed) |
| `powerhouse-disable-features.php` | Disables WP features for security |
| `powerhouse-email-validator.php` | MX record check on email domains |

These are deployed via `Compatibility\Services\Scheduler::compatibility()` which runs once per plugin version via Action Scheduler.

### 3.8 Template System

```php
// Load from inc/templates/pages/{name}.php  (if $name starts with a page name)
// Load from inc/templates/components/{name}.php  (otherwise)
Plugin::load_template('admin-layout', $args);  // pages
Plugin::load_template('theme', $args);          // components

// Page names: admin, settings, license-codes, powerhouse, admin-layout
```

### 3.9 Key Utility Methods

```php
use J7\Powerhouse\Utils\Base;

// Batch processing with cache flush between batches
Base::batch_process(
    items:    $items,
    callback: fn($item) => process($item),
    options:  ['batch_size' => 100, 'pause_ms' => 750, 'flush_cache' => true]
);

// Format SQL
Base::format_sql($sql);

// Get taxonomy options (for selects)
Base::get_taxonomy_options(['object_type' => ['product'], 'public' => true]);

// Render admin layout (used by child plugins)
Base::render_admin_layout(['title' => 'My Plugin', 'id' => 'my_plugin_app']);

// Get plugin navigation links (for admin bar)
Base::get_plugin_links();
```

---

## 4. Frontend Architecture

### 4.1 Tech Stack

| Technology | Purpose |
|-----------|---------|
| React 18 | UI framework |
| TypeScript | Type safety |
| Vite + `kucrut/vite-for-wp` | Build tool with WP integration |
| Refine.dev | CRUD admin framework |
| Ant Design 5 | UI components |
| `antd-toolkit` | Shared package (data providers, hooks) |
| React Query | Server state management |
| TailwindCSS + daisyUI | Styling (scoped to `#tw`) |
| HashRouter | Client-side routing (SPA in WP admin) |

### 4.2 Entry Point & Mounting

```tsx
// js/src/main.tsx
// APP1_SELECTOR = '#powerhouse_settings' (from encrypted env)
// Mounts React app to DOM element matching APP1_SELECTOR
```

The PHP side renders `<div id="powerhouse_settings"></div>` via `inc/templates/pages/admin-layout/index.php`.

### 4.3 Environment Variables

PHP encrypts env data and passes it via `wp_localize_script`:

```php
// PHP side (Bootstrap::enqueue_admin_assets)
$encrypt_env = Base::simple_encrypt([
    'SITE_URL', 'API_URL', 'CURRENT_USER_ID', 'CURRENT_POST_ID', 'PERMALINK',
    'APP_NAME', 'KEBAB', 'SNAKE',
    'BUNNY_LIBRARY_ID', 'BUNNY_CDN_HOSTNAME', 'BUNNY_STREAM_API_KEY',
    'NONCE', 'APP1_SELECTOR', 'ELEMENTOR_ENABLED', 'ROLES', 'WOOCOMMERCE_ENABLED',
]);
wp_localize_script(Plugin::$kebab, 'powerhouse_data', ['env' => $encrypt_env]);
```

```tsx
// TypeScript side (js/src/utils/env.tsx)
import { simpleDecrypt } from 'antd-toolkit'
const encryptedEnv = window?.powerhouse_data?.env
export const env = simpleDecrypt(encryptedEnv)
export const API_URL = env?.API_URL || '/wp-json'
export const APP1_SELECTOR = env?.APP1_SELECTOR || 'powerhouse'
```

### 4.4 Data Providers

The Refine.dev app registers multiple data providers in `App1.tsx`:

```tsx
dataProvider={{
    default:      dataProvider(`${API_URL}/v2/powerhouse`, AXIOS_INSTANCE),
    'wp-rest':    dataProvider(`${API_URL}/wp/v2`, AXIOS_INSTANCE),
    'wc-rest':    dataProvider(`${API_URL}/wc/v3`, AXIOS_INSTANCE),
    'wc-store':   dataProvider(`${API_URL}/wc/store/v1`, AXIOS_INSTANCE),
    'bunny-stream': bunny_data_provider_result,
}}
```

### 4.5 Routing

```tsx
// HashRouter routes (js/src/App1.tsx)
/settings      → <Settings />   (default, redirected from /)
/license-code  → <LicenseCode />
/*             → <ErrorComponent />
```

### 4.6 Frontend Development

```bash
# Start Vite dev server (port 5179)
pnpm dev

# Build production assets
pnpm build

# Build admin CSS only (Tailwind)
pnpm build-css:admin

# Watch admin CSS
pnpm watch-css:admin

# Build frontend CSS
pnpm build-css:front
```

Path alias: `@/` maps to `js/src/`

---

## 5. WordPress Hooks & Filters

### 5.1 Filters (for other plugins to integrate)

| Filter | Signature | Purpose |
|--------|-----------|---------|
| `powerhouse_product_infos` | `(array $infos): array` | Register products for LC management. Each key is `product_slug`, value is `['name' => '', 'link' => '']` |

### 5.2 Actions (fired by Powerhouse)

| Action | Args | Purpose |
|--------|------|---------|
| `powerhouse_delay_email` | `$class_name, ...$args` | Action Scheduler hook for delayed WC emails |
| `powerhouse_auto_update` | none | Action Scheduler hook to trigger Powerhouse auto-update |
| `powerhouse_compatibility_action_scheduler` | none | Runs one-time compatibility tasks per version |

### 5.3 Standard WordPress Hooks Used

```php
// Admin
add_action('admin_menu',              [..., 'add_menu'],      10);
add_action('admin_menu',              [..., 'add_submenu'],   100);
add_action('admin_enqueue_scripts',   [..., 'enqueue_admin_assets'], -100);
add_action('wp_enqueue_scripts',      [..., 'enqueue_frontend_assets'], -100);
add_action('current_screen',          [..., 'maybe_output_admin_page'], 10);

// Theme
add_filter('language_attributes',     [..., 'add_html_attr'], 20, 2);
add_action('wp_head',                 [..., 'custom_theme_color'], -100);

// Misc
add_filter('script_loader_src',       [..., 'modify_script_src'], 10, 2);
add_action('plugins_loaded',          [..., 'check_lc_array'], 999);
```

---

## 6. REST API Reference

All endpoints use the `v2/powerhouse` namespace (full URL: `/wp-json/v2/powerhouse/{endpoint}`).

### Core Domains (always available)

| Endpoint | Methods | Description |
|----------|---------|-------------|
| `posts` | GET, POST, DELETE | Generic post CRUD |
| `posts/{id}` | GET, POST, DELETE | Single post |
| `users` | GET, POST, DELETE | User management |
| `users/{id}` | GET, POST, DELETE | Single user |
| `options` | GET, POST, DELETE | WordPress options |
| `options/{key}` | GET, POST, DELETE | Single option |
| `terms` | GET, POST, DELETE | Taxonomy terms |
| `terms/{id}` | GET, POST, DELETE | Single term |
| `comments` | GET, POST, DELETE | Comments |
| `shortcodes` | POST | Execute shortcode |
| `upload` | POST | File upload |
| `plugins` | GET | Plugin list |
| `lc` | GET | License code status list |
| `lc/activate` | POST | Activate license code |
| `lc/deactivate` | POST | Deactivate license code |
| `lc/invalidate` | POST | Clear LC cache (open) |

### WooCommerce Domains (require WooCommerce active)

| Endpoint | Methods | Description |
|----------|---------|-------------|
| `products` | GET, POST, DELETE | WC products |
| `products/{id}` | GET, POST, DELETE | Single product |
| `product-attributes` | GET, POST | Product attributes |
| `woocommerce/*` | Various | Generic WC operations |
| `copy/*` | POST | Duplicate posts/products |
| `limit/*` | GET, POST | Access limits (ph_access_itemmeta) |
| `orders/*` | GET, POST | WC orders |
| `revenue/*` | GET | Revenue reports |
| `subscriptions/*` | GET, POST | WC Subscriptions |

---

## 7. Database

### Custom Table: `{prefix}ph_access_itemmeta`

Created on plugin activation via `Domains\Limit\Utils\CreateTable::create_itemmeta_table()`.

```sql
CREATE TABLE {prefix}ph_access_itemmeta (
    meta_id    bigint(20)   NOT NULL AUTO_INCREMENT,
    post_id    bigint(20)   NOT NULL,
    user_id    bigint(20)   NOT NULL,
    meta_key   varchar(255) DEFAULT NULL,
    meta_value longtext,
    PRIMARY KEY (meta_id),
    KEY post_id (post_id),
    KEY user_id (user_id),
    KEY meta_key (meta_key(191))
);
```

### WordPress Options Used

| Option Key | Type | Purpose |
|-----------|------|---------|
| `powerhouse_settings` | array | All plugin settings (see Settings DTO) |
| `powerhouse_license_codes` | array | `{product_slug: code}` map |
| `powerhouse_compatibility_action_scheduled` | string | Last version that ran compatibility |

### WordPress Transients

| Transient Key | TTL | Purpose |
|--------------|-----|---------|
| `lc_{product_slug}` | 24h | Encrypted LC status per product |

---

## 8. Code Quality

```bash
# PHP code style check
composer lint        # runs phpcs

# PHP static analysis (level 9)
composer analyse     # runs phpstan
# or with more memory:
vendor/bin/phpstan analyse inc --memory-limit=6G

# Frontend linting
pnpm lint

# Frontend formatting
pnpm format
```

**phpstan.neon** is set to level **9** (strictest). The following errors are ignored:
- `Access to constant .* on an unknown class`
- `Constant .* not found`
- `Function .* not found`

---

## 9. Release Process

```bash
# Release patch (auto-bumps version in package.json + plugin.php)
pnpm release:patch

# Release minor
pnpm release:minor

# Build ZIP for distribution
pnpm zip

# Create GitHub release
pnpm create:release
```

---

## 10. Development Patterns Quick Reference

### Adding a New Domain API

1. Create `inc/classes/Domains/YourDomain/Core/V2Api.php` extending `ApiBase`
2. Define `$namespace = 'v2/powerhouse'` and `$apis` array
3. Write `{method}_{endpoint}_callback()` methods
4. Instantiate in `Domains\Loader::__construct()`:
   ```php
   YourDomain\Core\V2Api::instance();
   ```

### Adding a New Admin Module

1. Create class in `inc/classes/Admin/YourModule.php` using `SingletonTrait`
2. Register hooks in constructor
3. Instantiate in `Bootstrap::__construct()`:
   ```php
   Admin\YourModule::instance();
   ```

### Adding Settings Fields

1. Add `public` property to `Settings\Model\Settings` with default value and PHPDoc
2. The DTO pattern handles serialization/deserialization automatically
3. Update frontend `Settings` page to include the new field in the form

### Child Plugin Integration Pattern

Child plugins (`power-course`, `power-shop`, etc.) integrate with Powerhouse by:

```php
// 1. Register product info for LC
add_filter('powerhouse_product_infos', fn($i) => array_merge($i, [
    'power-course' => ['name' => 'Power Course', 'link' => 'https://...']
]));

// 2. Check license status
$is_active = \J7\Powerhouse\Domains\LC\Utils\Base::ia('power-course');

// 3. Use shared admin layout
\J7\Powerhouse\Utils\Base::render_admin_layout(['title' => 'My Page', 'id' => 'my_app']);

// 4. Use shared utilities (via vendor/autoload)
use J7\WpUtils\Classes\WP;
use J7\WpUtils\Classes\General;
use J7\WpUtils\Classes\WC;
```

---

## 11. Security Checklist

When writing any PHP:
- [ ] `esc_html()` / `esc_attr()` / `esc_url()` on all output
- [ ] `sanitize_text_field()` / `wp_kses_post()` on all input
- [ ] `current_user_can('manage_options')` before admin operations
- [ ] `wp_verify_nonce()` for form submissions
- [ ] `$wpdb->prepare()` for all custom SQL
- [ ] Never echo user input directly

When writing REST API endpoints:
- [ ] Set appropriate `permission_callback` (use `null` to rely on WP nonce, not `'__return_true'` for sensitive endpoints)
- [ ] Validate required parameters with `WP::include_required_params()`
- [ ] Sanitize all request params with `WP::sanitize_text_field_deep()`
