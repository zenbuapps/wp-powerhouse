---
globs: "**/*.php"
---

# WordPress / PHP 開發規則

## 語言與風格
- PHP 8.1+，每個檔案 `declare(strict_types=1)`
- 遵循 WPCS（`phpcs.xml`），PHPStan level 9
- Namespace: `J7\Powerhouse`，PSR-4 autoloading（`inc/classes/`）

## Singleton Pattern
- 所有服務類別使用 `\J7\WpUtils\Traits\SingletonTrait`
- 存取: `ClassName::instance()`，禁止 `new ClassName()`
- Plugin 類別額外使用 `PluginTrait`

## Domain API Pattern
- 每個 Domain 在 `Domains/{Name}/Core/V2Api.php`
- 繼承 `J7\WpUtils\Classes\ApiBase`
- `$apis` static property 定義路由，callback 自動對應 `{method}_{endpoint}_callback()`
- REST namespace: `v2/powerhouse`

## DTO Pattern
- 繼承 `J7\WpUtils\Classes\DTO`
- 使用 `::create($array)` factory 建構
- `to_array()` 序列化
- Settings DTO 支援 partial update

## WooCommerce 整合
- 條件載入: `class_exists('\WooCommerce')` guard
- HPOS 相容: 使用 `wc_get_order()` 而非 `get_post()`
- 訂閱: 依賴 WooCommerce Subscriptions plugin

## mu-plugins
- 路徑: `Compatibility/mu-plugins/`
- 安裝時複製到 `wp-content/mu-plugins/`
- 用於系統級功能（API Booster, Email Validator, Disable Features）
- 修改後必須確保 `powerhouse-loader.php` 能正確載入

## License Code System
- `Domains\LC\Core\V2Api` — REST API
- `Domains\LC\Utils\Base` — 驗證邏輯
- 所有 Power 外掛透過 `powerhouse_product_infos` filter 註冊產品
- LC array 存在 wp_options，Bootstrap 啟動時驗證

## Settings
- Option key: `powerhouse_settings`
- DTO: `Settings\Model\Settings`
- 透過 `Domains\Option\Core\V2Api` 的 REST 端點讀寫
- 敏感設定（bunny_stream_api_key）不可 log

## Hooks 命名
- Action/Filter prefix: `powerhouse/` 或 `powerhouse_`
- 自訂 hook 需在 CLAUDE.md 的 Extensibility Hooks 章節登記

## External API
- `Api\Base` — cloud.luke.cafe 通訊
- 環境感知（local / staging / production）
- 認證: Basic Auth（`Plugin::instance()->t`，禁止暴露）
