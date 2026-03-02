# Powerhouse — License Code (LC) & Settings System

> **Last Updated:** 2025-01-31  
> **Applies to:** `inc/classes/Domains/LC/`, `inc/classes/Settings/`, `inc/classes/Api/`

---

## License Code System Overview

The LC system enables Powerhouse and its child plugins to validate commercial licenses against the Powerhouse cloud server (`cloud.luke.cafe`).

### How It Works

```
Plugin activation / plugins_loaded (priority 999)
  └── Bootstrap::check_lc_array()
        └── LC\Utils\Base::get_lc_array()
              ├── apply_filters('powerhouse_product_infos', []) → registered products
              ├── For each product:
              │     ├── get_transient("lc_{product_slug}")
              │     │     ├── EXISTS → decode (JsAesPhp decrypt) → add to array
              │     │     └── MISSING/EXPIRED
              │     │           ├── No saved code → use default (empty) status
              │     │           └── Has saved code → call cloud API to re-validate
              │     │                 ├── 200 → set_lc_transient() → add to array
              │     │                 ├── 401 → clear transient, use default
              │     │                 └── Error → keep as "activated" (graceful degradation)
              └── returns $lc_array
```

### Registering Your Plugin for LC Management

Add this to your child plugin's bootstrap:

```php
\add_filter('powerhouse_product_infos', function(array $infos): array {
    $infos['power-course'] = [
        'name' => 'Power Course',
        'link' => 'https://powerhouse.cloud/products/power-course',
    ];
    return $infos;
});
```

The **key** (`power-course`) must match the plugin directory slug exactly.

### Checking License Status

```php
use J7\Powerhouse\Domains\LC\Utils\Base as LCUtils;

// Check if a product is currently activated (reads from transient)
$is_activated = LCUtils::ia('power-course');  // returns bool

// Get full LC array (all products)
$lc_array = LCUtils::get_lc_array();
// Returns: array<array{
//   code: string,
//   post_status: string,  // '' | 'activated' | 'available' | 'expired' | 'deactivated'
//   expire_date: string,
//   type: string,         // 'lifetime' | 'yearly' | ...
//   product_slug: string,
//   product_name: string,
//   link: string,
// }>
```

### LC Transient Structure

Each product's LC status is encrypted with `JsAesPhp::encrypt($data, 'powerhouse', 1)` and stored as:
- **Transient key**: `lc_{product_slug}` (e.g., `lc_power-course`)
- **TTL**: 24 hours (`HOUR_IN_SECONDS * 24`)
- **Saved codes**: `powerhouse_license_codes` WP option — `array{product_slug: code}`

### LC Encryption

Powerhouse uses two encryption mechanisms:

1. **LC transients** (server-side): `JsAesPhp` (AES-256) — compatible with JavaScript `crypto-js`
   - Key: `Plugin::$kebab` = `'powerhouse'`
   - Use: secure storage of license status in transients

2. **JS env data** (client-side bridge): `Utils\Base::simple_encrypt()` — simple base64 + char shift
   - Use: passing WordPress data to React without exposing plain JSON
   - Decrypted in JS by `simpleDecrypt()` from `antd-toolkit`

### REST API for LC (`/wp-json/v2/powerhouse/lc`)

| Endpoint | Method | Body | Description |
|----------|--------|------|-------------|
| `/lc` | GET | — | Get all LC statuses |
| `/lc/activate` | POST | `{code, product_slug}` | Activate code |
| `/lc/deactivate` | POST | `{code, product_slug}` | Deactivate code |
| `/lc/invalidate` | POST | `{product_slug}` | Clear cache (public endpoint) |

> **`/lc/invalidate`** is open (`permission_callback: '__return_true'`) so the cloud server can invalidate a license remotely.

### LC Status Values

| `post_status` | Meaning |
|--------------|---------|
| `''` (empty) | No license code entered |
| `'activated'` | License is active and valid |
| `'available'` | Code exists but not yet activated on this domain |
| `'deactivated'` | Explicitly deactivated |
| `'expired'` | License has expired |

---

## Settings System Overview

All Powerhouse settings are stored in a single WordPress option: `powerhouse_settings`.

### Settings DTO Class

```php
use J7\Powerhouse\Settings\Model\Settings;

// Get singleton instance (loaded from DB)
$settings = Settings::instance();

// Read a value
$theme = $settings->theme;  // 'power' | 'light' | 'dark' | 'cupcake' | ...

// Partial update (preserves all other values)
$settings->partial_update([
    'theme'       => 'dark',
    'delay_email' => 'no',
]);
```

### Full Settings Reference

#### General Settings

| Property | Type | Default | Description |
|----------|------|---------|-------------|
| `enable_manual_send_email` | `'yes'\|'no'` | `'no'` | Allow admin to manually resend WC emails |
| `enable_captcha_login` | `'yes'\|'no'` | `'no'` | Add captcha to WP login form |
| `captcha_role_list` | `string[]` | `['administrator']` | User roles that see the login captcha |
| `enable_captcha_register` | `'yes'\|'no'` | `'no'` | Add captcha to WP registration form |
| `enable_email_domain_check_register` | `'yes'\|'no'` | `'yes'` | Validate email MX records on registration |
| `enable_email_domain_check_wp_mail` | `'yes'\|'no'` | `'yes'` | Validate email MX records before `wp_mail` |
| `email_domain_check_white_list` | `string[]` | `['gmail.com', ...]` | Domains exempt from MX validation |

#### WooCommerce Settings

| Property | Type | Default | Description |
|----------|------|---------|-------------|
| `delay_email` | `'yes'\|'no'` | `'yes'` | Route WC emails through Action Scheduler |
| `last_name_optional` | `'yes'\|'no'` | `'yes'` | Remove last name from required WC account fields |

#### Theme Settings

| Property | Type | Default | Description |
|----------|------|---------|-------------|
| `theme` | `string` | `'power'` | daisyUI theme name |
| `enable_theme` | `'yes'\|'no'` | `'yes'` | Apply `data-theme` to `<html>` tag |
| `enable_theme_changer` | `'yes'\|'no'` | `'no'` | Show theme switcher button on frontend |
| `theme_css` | `array` | `[]` | Custom CSS variables for `custom` theme |

#### Experimental / Advanced

| Property | Type | Default | Description |
|----------|------|---------|-------------|
| `api_booster_rules` | `array` | `[]` | Rules for API Booster selective plugin loading |
| `api_booster_rule_recipes` | `array` | `[]` | Pre-built templates for API Booster rules |

#### BunnyCDN Integration

| Property | Type | Default | Description |
|----------|------|---------|-------------|
| `bunny_library_id` | `string` | `''` | BunnyCDN video library ID |
| `bunny_cdn_hostname` | `string` | `''` | BunnyCDN CDN hostname |
| `bunny_stream_api_key` | `string` | `''` | BunnyCDN Stream API key |

### Adding a New Setting Field

1. **Add property to `Settings` DTO:**
   ```php
   // inc/classes/Settings/Model/Settings.php
   /** @var string $my_new_setting 我的新設定說明 */
   public string $my_new_setting = 'default_value';
   ```

2. **Use it anywhere:**
   ```php
   $value = Settings::instance()->my_new_setting;
   ```

3. **Update frontend form:**  
   Add the field to `js/src/pages/admin/Settings/index.tsx` using Ant Design Form components.

### Settings Key Name

The option key `powerhouse_settings` is also used by `mu-plugins/powerhouse-api-booster.php` (which reads it independently without full plugin bootstrap). **Do not change this key.**

---

## API Booster System

The API Booster is a mu-plugin that speeds up REST API responses by selectively loading only the plugins needed for a specific API route.

### How It Works

1. On each REST request, `powerhouse-api-booster.php` checks the request URI
2. Matches against configured rules in `powerhouse_settings.api_booster_rules`
3. If matched, removes non-essential plugins from the active plugins list for that request
4. Dramatically reduces memory usage and bootstrap time for API routes

### Rule Structure

```php
$rule = [
    'name'       => 'My Rule',
    'enabled'    => 'yes',
    'url_rules'  => ['/wp-json/v2/powerhouse/products'],  // URL patterns to match
    'plugins'    => ['woocommerce/woocommerce.php'],       // Plugins to KEEP loaded
];
```

> **Important:** The `/wp-json/v2/powerhouse/plugins` endpoint is explicitly excluded from API Booster to ensure accurate plugin status data.
