# Powerhouse — REST API Domains Reference

> **Last Updated:** 2025-01-31  
> **Base URL:** `/wp-json/v2/powerhouse`  
> **Applies to:** `inc/classes/Domains/` and `inc/classes/Api/`

---

## API Architecture

All domain REST APIs extend `J7\WpUtils\Classes\ApiBase` and are registered under the `v2/powerhouse` namespace. They are instantiated in `Domains\Loader`.

### Callback Naming Convention

Method names for callbacks are derived from `{HTTP_METHOD}_{endpoint_as_snake_case}_callback`:

| Endpoint Pattern | Callback Method Name |
|-----------------|---------------------|
| `GET posts` | `get_posts_callback` |
| `GET posts/(?P<id>\d+)` | `get_posts_with_id_callback` |
| `POST posts` | `post_posts_callback` |
| `POST posts/(?P<id>\d+)` | `post_posts_with_id_callback` |
| `DELETE posts` | `delete_posts_callback` |
| `DELETE posts/(?P<id>\d+)` | `delete_posts_with_id_callback` |
| `POST lc/activate` | `post_lc_activate_callback` |
| `GET options/(?P<key>[\\w-]+)` | `get_options_with_key_callback` |

### Standard Response Format

```php
// Success response
return new \WP_REST_Response([
    'code'    => 'action_success',           // machine-readable code
    'message' => __('human message', 'powerhouse'),
    'data'    => $result,                    // actual payload
]);

// List response with pagination headers
$response = new \WP_REST_Response($items);
$response->header('X-WP-Total',       (string) $total);
$response->header('X-WP-TotalPages',  (string) $total_pages);
$response->header('X-WP-CurrentPage', (string) $paged);
$response->header('X-WP-PageSize',    (string) $per_page);
return $response;

// Error — throw exception (ApiBase converts to WP_Error response)
throw new \Exception('error message', $http_status_code);
```

### Request Parameter Handling

```php
// GET: query params
$params = $request->get_query_params();
$params = WP::sanitize_text_field_deep($params, false);
$args   = \wp_parse_args($params, $default_args);
$args   = General::parse($args);  // '[]' → [], 'true' → true, 'false' → false

// POST: JSON body
$body_params = $request->get_json_params();
WP::include_required_params($body_params, ['code', 'product_slug']);  // throws if missing
```

---

## Core Domains (Always Available)

### Post Domain — `Domains\Post\Core\V2Api`

| Method | Endpoint | Callback | Description |
|--------|----------|----------|-------------|
| GET | `posts` | `get_posts_callback` | List posts (paginated, all types) |
| GET | `posts/{id}` | `get_posts_with_id_callback` | Single post with meta |
| POST | `posts` | `post_posts_callback` | Create post(s), supports `qty` bulk creation |
| POST | `posts/{id}` | `post_posts_with_id_callback` | Update post |
| DELETE | `posts` | `delete_posts_callback` | Bulk trash posts |
| DELETE | `posts/{id}` | `delete_posts_with_id_callback` | Trash single post |

Default list query args:
```php
[
    'post_type'      => 'post',
    'posts_per_page' => 20,
    'paged'          => 1,
    'post_parent'    => 0,
    'post_status'    => 'any',
    'orderby'        => ['menu_order' => 'ASC', 'ID' => 'DESC', 'date' => 'DESC'],
]
```

### User Domain — `Domains\User\Core\V2Api`

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `users` | List users (paginated) |
| GET | `users/{id}` | Single user with meta |
| POST | `users` | Create user |
| POST | `users/{id}` | Update user |
| DELETE | `users/{id}` | Delete user |

### Option Domain — `Domains\Option\Core\V2Api`

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `options` | Get all options |
| GET | `options/{key}` | Get single option |
| POST | `options` | Create/update option |
| POST | `options/{key}` | Update single option |
| DELETE | `options/{key}` | Delete option |

> **Used by Settings page**: The admin SPA reads/writes `powerhouse_settings` via `options/powerhouse_settings`.

### Term Domain — `Domains\Term\Core\V2Api`

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `terms` | List terms (by taxonomy) |
| GET | `terms/{id}` | Single term |
| POST | `terms` | Create term |
| POST | `terms/{id}` | Update term |
| DELETE | `terms/{id}` | Delete term |

### Comment Domain — `Domains\Comment\Core\V2Api`

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `comments` | List comments |
| GET | `comments/{id}` | Single comment |
| POST | `comments` | Create comment |
| POST | `comments/{id}` | Update comment |
| DELETE | `comments/{id}` | Delete comment |

### Shortcode Domain — `Domains\Shortcode\Core\V2Api`

| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `shortcodes` | Execute a shortcode, returns rendered HTML |

### Upload Domain — `Domains\Upload\Core\V2Api`

| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `upload` | Upload file to WordPress media library |

### Plugin Domain — `Domains\Plugin\Core\V2Api`

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `plugins` | List installed plugins with activation status |

> **Note:** This endpoint is excluded from API Booster selective loading.

### License Code Domain — `Domains\LC\Core\V2Api`

| Method | Endpoint | Permission | Description |
|--------|----------|-----------|-------------|
| GET | `lc` | admin | Get all LC statuses |
| POST | `lc/activate` | admin | Activate a license code |
| POST | `lc/deactivate` | admin | Deactivate a license code |
| POST | `lc/invalidate` | open (`__return_true`) | Clear cached LC transient |

Request body for `activate`/`deactivate`:
```json
{ "code": "LICENSE-CODE-HERE", "product_slug": "power-course" }
```

---

## WooCommerce Domains (Require WooCommerce Active)

### Product Domain — `Domains\Product\Core\V2Api`

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `products` | List WC products |
| GET | `products/{id}` | Single product with meta |
| POST | `products` | Create product |
| POST | `products/{id}` | Update product |
| DELETE | `products/{id}` | Trash product |

### Product Attribute Domain — `Domains\ProductAttribute\Core\V2Api`

Manages WooCommerce product attributes (`pa_*` taxonomies).

### Order Domain — `Domains\Order\Core\V2Api`

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `orders` | List WC orders |
| GET | `orders/{id}` | Single order with metadata |
| POST | `orders/{id}` | Update order |
| DELETE | `orders/{id}` | Cancel/trash order |

### WooCommerce Generic — `Domains\Woocommerce\Core\V2Api`

General WC helper endpoints (cart, checkout, store settings).

### Copy Domain — `Domains\Copy\Core\V2Api`

| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `copy` | Duplicate a post/product with all meta |

### Limit Domain — `Domains\Limit\Core\V2Api`

Manages per-user access limits stored in `{prefix}ph_access_itemmeta`.

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `limit` | Get access limit info |
| POST | `limit` | Set/update access limit |
| DELETE | `limit/{id}` | Remove access limit |

### Revenue Report Domain — `Domains\Report\Revenue\Core\V2Api`

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `revenue` | Revenue statistics (date range, grouping) |

### Subscription Domain — `Domains\Subscription\Core\Loader`

Loads subscription-related sub-APIs for WooCommerce Subscriptions integration.

---

## Cloud API Client (`Api\Base`)

Used internally by Powerhouse to call `cloud.luke.cafe` (license server):

```php
$api = \J7\Powerhouse\Api\Base::instance();

// GET request
$response = $api->remote_get('license-codes', ['product_slug' => 'power-course']);

// POST request
$response = $api->remote_post('license-codes/activate', [
    'code'            => $code,
    'product_slug'    => $product_slug,
    'is_system_check' => true,
]);

// DELETE request
$response = $api->remote_delete('license-codes/deactivate', ['code' => $code]);
```

Authentication: HTTP Basic Auth with credentials that vary by environment (local/staging/production).

---

## Adding a New Domain API — Step-by-Step

1. **Create the V2Api class:**

```php
// inc/classes/Domains/MyFeature/Core/V2Api.php
<?php
declare(strict_types=1);

namespace J7\Powerhouse\Domains\MyFeature\Core;

use J7\WpUtils\Classes\ApiBase;

final class V2Api extends ApiBase {
    use \J7\WpUtils\Traits\SingletonTrait;

    protected $namespace = 'v2/powerhouse';

    protected $apis = [
        [
            'endpoint'            => 'my-feature',
            'method'              => 'get',
            'permission_callback' => null,
        ],
        [
            'endpoint'            => 'my-feature/(?P<id>\d+)',
            'method'              => 'post',
            'permission_callback' => null,
        ],
    ];

    /**
     * 取得我的功能列表
     *
     * @param \WP_REST_Request $request Request 物件
     * @return \WP_REST_Response
     * @phpstan-ignore-next-line
     */
    public function get_my_feature_callback(\WP_REST_Request $request): \WP_REST_Response {
        $params = $request->get_query_params();
        // ... your logic
        return new \WP_REST_Response($data);
    }

    /**
     * 更新我的功能
     *
     * @param \WP_REST_Request $request Request 物件
     * @return \WP_REST_Response
     * @phpstan-ignore-next-line
     */
    public function post_my_feature_with_id_callback(\WP_REST_Request $request): \WP_REST_Response {
        $id          = (int) $request['id'];
        $body_params = $request->get_json_params();
        // ... your logic
        return new \WP_REST_Response(['code' => 'update_success', 'message' => '更新成功', 'data' => []]);
    }
}
```

2. **Register in `Domains\Loader`:**

```php
// inc/classes/Domains/Loader.php — add inside __construct():
MyFeature\Core\V2Api::instance();

// Or if WooCommerce-only:
if (class_exists('\WooCommerce')) {
    MyFeature\Core\V2Api::instance();
}
```
