# Powerhouse

> **Library as Plugin** — The CORE foundation plugin that all `power-*` plugins depend on.

**Version:** 3.3.46 | **PHP:** 8.1+ | **WordPress:** 5.7+ | **License:** GPL-2.0

```
██████╗  ██████╗ ██╗    ██╗███████╗██████╗ ██╗  ██╗ ██████╗ ██╗   ██╗███████╗███████╗
██╔══██╗██╔═══██╗██║    ██║██╔════╝██╔══██╗██║  ██║██╔═══██╗██║   ██║██╔════╝██╔════╝
██████╔╝██║   ██║██║ █╗ ██║█████╗  ██████╔╝███████║██║   ██║██║   ██║███████╗█████╗
██╔═══╝ ██║   ██║██║███╗██║██╔══╝  ██╔══██╗██╔══██║██║   ██║██║   ██║╚════██║██╔══╝
██║     ╚██████╔╝╚███╔███╔╝███████╗██║  ██║██║  ██║╚██████╔╝╚██████╔╝███████║███████╗
╚═╝      ╚═════╝  ╚══╝╚══╝ ╚══════╝╚═╝  ╚═╝╚═╝  ╚═╝ ╚═════╝  ╚═════╝ ╚══════╝╚══════╝
                          www.powerhouse.cloud
```

---

## What is Powerhouse?

Powerhouse is a **developer-focused WordPress plugin** that acts as a shared library for the entire `power-*` plugin ecosystem. It provides:

- 📦 **Shared utilities** via `j7-dev/wp-utils` — SingletonTrait, ApiBase, DTO, and more
- 🔌 **Generic REST API** under `/wp-json/v2/powerhouse` for every WordPress resource (posts, users, options, terms, products, orders, etc.)
- 🔑 **License Code (LC) management** — validates plugin licenses against `cloud.luke.cafe`
- 🗄️ **Custom DB table** (`ph_access_itemmeta`) for per-user access/limit tracking
- 🎨 **Theme system** — daisyUI + Tailwind CSS injected via `<html data-theme="...">` 
- 🤖 **Captcha protection** for WP login and registration forms
- ⚡ **API Booster** mu-plugin — selective plugin loading per REST route for faster APIs
- 🛡️ **Email validator** — MX record validation via mu-plugin
- 🐛 **Debug log viewer** — accessible from WP Admin → Tools → Debug Log
- ⏳ **WooCommerce enhancements** — delayed emails, optional last name, order customizations
- 🖥️ **React/TypeScript admin SPA** — built with Refine.dev + Ant Design 5

---

## Requirements

| Requirement | Version |
|-------------|---------|
| PHP | 8.1+ |
| WordPress | 5.7+ |
| WooCommerce | Optional (unlocks additional features) |
| Node.js | 18+ (for development) |
| pnpm | 8+ (for development) |

---

## Installation

### As a WordPress Plugin

1. Download the plugin ZIP from the releases page
2. Upload to `wp-content/plugins/powerhouse/`
3. Activate via **WordPress Admin → Plugins**

### Development Setup (Turborepo Monorepo)

```bash
# From the monorepo root
pnpm install

# Install PHP dependencies
cd apps/powerhouse
composer install

# Start Vite dev server (port 5179)
pnpm dev

# Build production assets
pnpm build

# Build CSS only (faster)
pnpm build-css:admin
pnpm build-css:front
```

---

## Plugin Architecture

```
powerhouse/
├── plugin.php              ← Entry point (Plugin class with PluginTrait + SingletonTrait)
├── inc/
│   ├── classes/            ← PHP source (PSR-4: J7\Powerhouse\ → here)
│   │   ├── Bootstrap.php   ← Module orchestrator
│   │   ├── Admin/          ← Admin UI modules (Debug, DelayEmail, Entry, etc.)
│   │   ├── Api/            ← Cloud API HTTP client
│   │   ├── Captcha/        ← Login/register captcha
│   │   ├── Compatibility/  ← MU-plugin management, AutoUpdate, Scheduler
│   │   ├── Domains/        ← REST API domain modules (Post, User, LC, Limit, etc.)
│   │   ├── Settings/       ← Settings DTO (powerhouse_settings option)
│   │   ├── Theme/          ← daisyUI theme system
│   │   └── Utils/          ← Static utility helpers
│   └── templates/          ← PHP templates (admin-layout, components)
└── js/
    ├── src/                ← React/TypeScript SPA
    │   ├── main.tsx        ← Vite entry, mounts to #powerhouse_settings
    │   ├── App1.tsx        ← Refine.dev app with HashRouter
    │   └── pages/admin/    ← Settings + LicenseCode pages
    └── dist/               ← Built assets (committed)
```

---

## REST API

All endpoints are under `/wp-json/v2/powerhouse/`.

### Core Endpoints (always available)

| Endpoint | Methods | Description |
|----------|---------|-------------|
| `posts` / `posts/{id}` | GET, POST, DELETE | Generic post CRUD |
| `users` / `users/{id}` | GET, POST, DELETE | User management |
| `options` / `options/{key}` | GET, POST, DELETE | WordPress options |
| `terms` / `terms/{id}` | GET, POST, DELETE | Taxonomy terms |
| `comments` / `comments/{id}` | GET, POST, DELETE | Comments |
| `shortcodes` | POST | Execute a shortcode |
| `upload` | POST | Upload file to media library |
| `plugins` | GET | List all plugins |
| `lc` | GET | License code statuses |
| `lc/activate` | POST | Activate a license code |
| `lc/deactivate` | POST | Deactivate a license code |
| `lc/invalidate` | POST | Clear LC cache |

### WooCommerce Endpoints (require WooCommerce)

| Endpoint | Description |
|----------|-------------|
| `products` / `products/{id}` | WC product CRUD |
| `product-attributes` | WC product attributes |
| `orders` / `orders/{id}` | WC order management |
| `copy` | Duplicate posts/products |
| `limit` | Access limit management |
| `revenue` | Revenue reports |
| `subscriptions` | WC Subscriptions support |

---

## WordPress Hooks

### Filters

| Filter | Signature | Purpose |
|--------|-----------|---------|
| `powerhouse_product_infos` | `(array $infos): array` | Register plugins for LC management |

**Example — registering a product:**
```php
add_filter('powerhouse_product_infos', function(array $infos): array {
    $infos['my-plugin'] = [
        'name' => 'My Plugin',
        'link' => 'https://example.com',
    ];
    return $infos;
});
```

### Actions (Action Scheduler)

| Action | Description |
|--------|-------------|
| `powerhouse_delay_email` | Delayed WC email delivery |
| `powerhouse_auto_update` | Trigger Powerhouse auto-update |
| `powerhouse_compatibility_action_scheduler` | One-time-per-version tasks |

---

## Child Plugin Integration

Child plugins (`power-course`, `power-shop`, etc.) use Powerhouse as their foundation:

```php
// Check license activation
$is_active = \J7\Powerhouse\Domains\LC\Utils\Base::ia('my-plugin');

// Use shared admin layout (creates the <div> for the React SPA)
\J7\Powerhouse\Utils\Base::render_admin_layout([
    'title' => 'My Plugin Admin',
    'id'    => 'my_plugin_app',
]);

// Use shared utilities
use J7\WpUtils\Classes\WP;
use J7\WpUtils\Classes\General;

// Batch processing with memory management
\J7\Powerhouse\Utils\Base::batch_process($items, fn($item) => process($item));
```

---

## Database

### Custom Table: `{prefix}ph_access_itemmeta`

Created on plugin activation. Stores per-user access metadata for content limiting.

```sql
meta_id    BIGINT       PK AUTO_INCREMENT
post_id    BIGINT       Content item ID
user_id    BIGINT       WordPress user ID
meta_key   VARCHAR(255) Metadata key
meta_value LONGTEXT     Metadata value
```

### WordPress Options

| Option | Description |
|--------|-------------|
| `powerhouse_settings` | All plugin settings |
| `powerhouse_license_codes` | Saved license codes per product |
| `powerhouse_compatibility_action_scheduled` | Last version that ran compatibility checks |

---

## Development

### PHP Code Quality

```bash
# Code style check (PHPCS with WordPress standards)
composer lint

# Static analysis (PHPStan level 9)
composer analyse
# With extended memory:
vendor/bin/phpstan analyse inc --memory-limit=6G
```

### Frontend Development

```bash
# Development server with HMR (port 5179)
pnpm dev

# Production build (assets go to js/dist/)
pnpm build

# Build admin Tailwind CSS only
pnpm build-css:admin

# Watch admin CSS
pnpm watch-css:admin

# Lint TypeScript
pnpm lint

# Format TypeScript
pnpm format
```

### Release

```bash
pnpm release:patch   # Bump patch version, build, tag
pnpm release:minor   # Bump minor version
pnpm release:major   # Bump major version
pnpm zip             # Create distribution ZIP
pnpm create:release  # Push GitHub release
```

---

## Tech Stack

### PHP / Backend
- **PHP 8.1+** with strict types throughout
- **WordPress 5.7+** hooks, REST API, Options API
- **WooCommerce** (optional) for order/product/subscription features
- **Composer packages:**
  - `j7-dev/wp-utils` — shared trait & class library
  - `kucrut/vite-for-wp` — Vite asset manifest integration
  - `brainfoolong/js-aes-php` — AES encryption for LC transients
  - `gregwar/captcha` — CAPTCHA image generation
  - `symfony/finder` — file system utilities

### JavaScript / Frontend
- **React 18** + **TypeScript**
- **Vite** — build tool
- **Refine.dev** — admin CRUD framework
- **Ant Design 5** — UI components
- **antd-toolkit** — workspace shared library
- **React Query** — server state management
- **Tailwind CSS** + **daisyUI** — styling (scoped to `#tw`)
- **HashRouter** — client-side routing without server config

---

## Documentation

- **[`.github/copilot-instructions.md`](.github/copilot-instructions.md)** — Comprehensive AI assistant guide
- **[`.github/instructions/architecture.md`](.github/instructions/architecture.md)** — System architecture detail
- **[`.github/instructions/api-domains.md`](.github/instructions/api-domains.md)** — REST API domains reference
- **[`.github/instructions/lc-and-settings.md`](.github/instructions/lc-and-settings.md)** — LC & Settings system guide
- **[`.github/instructions/wordpress.instructions.md`](.github/instructions/wordpress.instructions.md)** — PHP development conventions
- **[`.github/instructions/react.instructions.md`](.github/instructions/react.instructions.md)** — React/TypeScript conventions
- **[`inc/classes/Domains/README.md`](inc/classes/Domains/README.md)** — Domain API completion status

---

## License

GPL v2 or later — see [LICENSE](LICENSE)

**Author:** JerryLiu ([@j7-dev](https://github.com/j7-dev))  
**Website:** [www.powerhouse.cloud](https://www.powerhouse.cloud)