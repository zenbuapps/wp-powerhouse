---
name: powerhouse
description: Powerhouse — Power 系列外掛基礎架構平台開發指引。DDD Domain API、統一 REST 層、License Code 系統、Theme 系統、React Refine Admin SPA、mu-plugins。使用 /powerhouse 觸發。
---

# Powerhouse SKILL

## Quick Facts
- **Plugin:** Powerhouse v3.3.48
- **Namespace:** `J7\Powerhouse`
- **PHP:** 8.1+ | **WP:** 5.7+
- **Frontend:** React 18 + Refine v4 + Ant Design 5
- **Role:** Power 系列外掛的 Foundation Plugin

## Why This Plugin Matters
所有 Power 外掛（power-course, power-shop, power-partner, power-contract 等）都依賴 Powerhouse。它提供：
- REST API 框架（ApiBase）
- Singleton + PluginTrait 生命週期
- License Code 統一管理
- Theme 色彩系統
- WooCommerce 整合基底

## Domain API Pattern
每個 Domain 在 `Domains/{Name}/Core/V2Api.php`，繼承 ApiBase：
```php
protected static $apis = [
    ['endpoint' => 'posts', 'method' => 'get', 'permission_callback' => '__return_true'],
    ['endpoint' => 'posts', 'method' => 'post', 'permission_callback' => 'check'],
];
// 自動路由到 get_posts_callback(), post_posts_callback()
```

## Available Domains (20+)
| Domain | REST Base | Key Features |
|--------|-----------|-------------|
| Post | `posts` | All CPT CRUD + sort + field query |
| Comment | `comments` | Comment CRUD |
| Term | `terms/{taxonomy}` | Taxonomy CRUD |
| User | `users` | User CRUD |
| Option | `options` | WP Options read/write |
| Upload | `upload` | File upload |
| Copy | `copy/{id}` | Post deep copy |
| LC | `lc` | License activate/deactivate |
| Limit | `limit` | Usage limit grant/revoke |
| Order | `orders` | WC Order CRUD + notes |
| Product | `products` | WC Product CRUD + variations + attributes |
| Subscription | — | WC Subscriptions loader |
| Report | `report/revenue` | Revenue data |

## Settings System
- **Option key:** `powerhouse_settings`
- **DTO:** `Settings\Model\Settings`
- Categories: General, WooCommerce, Theme, Lab, BunnyCDN

## mu-plugins (System-level)
| File | Purpose |
|------|---------|
| `powerhouse-api-booster.php` | API 效能優化 |
| `powerhouse-disable-features.php` | 停用 WP 預設功能 |
| `powerhouse-email-validator.php` | Email domain 驗證 |
| `powerhouse-loader.php` | 早期載入 |

## Extensibility Points
- `powerhouse_product_infos` — 註冊 Power 外掛產品（LC 系統）
- `powerhouse/option/*` — Option 讀寫攔截
- `powerhouse_after_copy_post` — Post 複製後事件
- `powerhouse_delay_email` — 延遲 email 排程

## Frontend Admin SPA
- Mount: `#powerhouse_settings`
- Framework: Refine v4 + Ant Design 5
- Pages: Settings (4 tabs) + License Codes
- Multi-API data provider (Powerhouse + WP + WC + BunnyCDN)

## Development
```bash
pnpm dev              # Vite dev (port 5179)
pnpm build            # React app → js/dist/
pnpm build-css:admin  # SCSS → CSS + Tailwind
pnpm build-css:front  # Frontend CSS
pnpm lint             # ESLint + PHPCS
```

## Key Dependencies
- PHP: j7-dev/wp-utils, kucrut/vite-for-wp, gregwar/captcha, js-aes-php
- JS: react 18, @refinedev/core 4, antd 5, @tanstack/react-query 4, jotai, @blocknote/react, react-router 7
