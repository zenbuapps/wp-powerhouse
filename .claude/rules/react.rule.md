---
globs: "js/src/**/*.{ts,tsx,scss,css}"
---

# React / TypeScript 開發規則

## 技術棧
- React 18 + TypeScript 5.5
- Refine v4（Admin CRUD 框架）+ Ant Design 5
- TanStack Query v4（data fetching）
- Jotai（global state）
- React Router v7
- BlockNote v0.30（rich text editor）

## Build
- Vite 6 + `@kucrut/vite-for-wp`
- Dev server: port 5179
- Entry: `js/src/main.tsx` → mount `#powerhouse_settings`
- CSS: 獨立 pipeline（SCSS → PostCSS → TailwindCSS）

## Import Alias
- `@/` → `js/src/`
- 使用 `vite-tsconfig-paths` 解析

## Data Provider
- 自訂 Refine data provider 包裝多個 API：
  - `v2/powerhouse` — Powerhouse REST API
  - `wp/v2` — WordPress core
  - `wc/v3` + `wc/store/v1` — WooCommerce
  - BunnyCDN Stream（條件啟用）

## API Layer
- `js/src/api/resources/` — CRUD 操作封裝（create, get, update, delete）
- 使用 Axios，自動附帶 WP Nonce

## 頁面結構
- `pages/admin/Settings/` — General / Theme / Lab / WooCommerce 分頁
- `pages/admin/LicenseCode/` — 授權碼管理

## Style 規則
- TailwindCSS 用於 layout
- daisyUI 4（`^4.12.23`）提供元件 class，**必須加 `pc-` prefix**（`pc-btn`、`pc-card-body`）；
  寫任何 daisyUI 標記前先載入 `daisyui-v4` skill（含 55 個元件的 class 清單與專案特殊設定）
- Ant Design CSS-in-JS 用於元件樣式
- antd-style 用於自訂 token
- SCSS 用於全域樣式（admin.scss, front.scss, blocknote.scss）

## WordPress 外部化
- `@wordpress/element`, `@wordpress/i18n` → window.wp.*
- jQuery → window.jQuery
- 透過 `vite-plugin-optimizer`
