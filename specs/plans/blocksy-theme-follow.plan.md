# 實作計劃：主題顏色「跟隨 Blocksy」選項

## 概述

在 Powerhouse 後台「主題顏色」設定新增一張「跟隨 Blocksy」主題卡片。選此選項後，前台 `wp_head` 輸出 CSS 時**即時讀取當前 Blocksy 調色盤**（8 色 hex），於 **PHP 端轉成 OKLCH** 套用到 daisyUI 色彩變數。不存快照——Blocksy 改調色盤，Powerhouse 自動跟隨。非 Blocksy 站台則該卡片 **disable + tooltip**。

範圍模式：**HOLD SCOPE**（單一內聚功能、需求已定案、刻意保持最小：disable+tooltip、動態跟隨、不存快照）。預估 13–15 個檔案，採分階段、每階段可獨立合併。

---

## 需求重述

- **建構什麼**：daisyUI token ← Blocksy palette 的動態色彩橋接，外加一張後台選項卡片與其 disable 邏輯。
- **服務對象**：使用 Blocksy（或其子主題）的 Power 生態系站台管理員，想讓 Powerhouse 元件配色自動對齊主題。
- **成功樣子**：
  1. Blocksy 站台 → 卡片可選；選後前台 daisyUI 元件主色 = Blocksy color1，base = color8 等；改 Blocksy 調色盤後前台立即同步（無需重存 Powerhouse 設定）。
  2. 非 Blocksy 站台 → 卡片 disabled + tooltip「需使用 Blocksy 主題」。
  3. 後台預覽不誤導使用者。

### 色彩對應（定案）

| daisyUI token | ← Blocksy | 預設 hex（Blocksy） |
| --- | --- | --- |
| `--p` primary | color1 | #2872fa |
| `--s` secondary | color2 | #1559ed |
| `--bc` base-content | color3 | #3A4F66 |
| `--n` neutral | color4 | #192a3d |
| `--b3` base-300 | color6 | #f2f5f7 |
| `--b2` base-200 | color7 | #FAFBFC |
| `--b1` base-100 | color8 | #ffffff |

> color5 (#e1e8ed, 邊框) **不對應任何 token**（daisyUI 無對應的 border 色變數，邊框走 `--bc` 透明度）。
> 未對應者保留 Powerhouse 既有預設：`--a/--ac`、所有 content 前景（`--pc/--sc/--nc`）、狀態色（`--in/--su/--wa/--er` 及其 content）、所有圓角/動畫/邊框尺寸。
> content 前景策略：**保留既有預設**（`--pc/--sc = 純白 '1 0 0'`，已是 Blocksy buttonTextColor #ffffff 預設值，天然吻合）。不引入對比演算法（YAGNI；若日後 base 色偏亮導致對比不足再議）。

---

## 已知風險（來自研究）

- **風險：PHP 無 culori，自行實作 Hex→OKLCH 可能與前端 culori 結果有誤差** — 緩解：採標準 sRGB→linear→OKLab→OKLCH 公式（culori 同源），用 PHPUnit 對 8 個 Blocksy 預設色斷言「PHP 輸出 ≈ culori 輸出」，容差 L ±0.5%、C ±0.005、H ±0.5°。
- **風險：`data-theme="blocksy"` 在編譯後的 daisyUI CSS 中不存在，導致 daisyUI 結構性 token（hover/focus 的 color-mix、自動 content 推導）無基底** — 緩解：**沿用既有可運作的 `data-theme` selector 機制 + print_css 全量覆寫**。見「架構決策 D1」。
- **風險：`blocksy_manager()` 在 Blocksy 未啟用時不存在，直接呼叫 fatal** — 緩解：偵測層三重防護（`function_exists` + `get_template()` 判斷 + try/catch），fallback 回 Powerhouse 預設。
- **風險：Blocksy palette 可能缺色或非預期格式** — 緩解：逐色 `isset` + hex 格式驗證（`/^#[0-9a-fA-F]{6}$/`），缺一色則該 token 退回 Powerhouse 預設，不中斷其餘。
- **風險：Blocksy Companion 未裝是否影響 palette** — 研究確認 **不影響**（colors.php:46 無 Companion gate，free 主題即可讀）。仍以偵測層容錯兜底。
- 未發現其他額外已知風險。

---

## 架構決策（規劃階段拍板，附理由）

### D1 — `data-theme` selector 策略（最小驚訝方案）

**問題**：`print_css()`（Theme.php:212-224）硬編碼輸出 `#tw[data-theme='{theme}'] { ... }`，而 `add_html_attr`（FrontEnd.php:30-43）把 `<html data-theme>` 設為 `Settings::theme`。若 `theme='blocksy'`，daisyUI 編譯 CSS 裡沒有 `[data-theme=blocksy]` 區塊（tailwind.config.front.cjs:115 themes 清單無 blocksy），daisyUI 的結構性 color-mix token 會落空。

**決策**：**儲存值與輸出 selector 解耦**。
- 持久化：`Settings::theme = 'blocksy'`（驅動後台卡片選中態、識別「跟隨模式」）。
- 前台 HTML/CSS 的 `data-theme` 實際輸出值 = **`'power'`**（既有、已在 daisyUI 註冊、結構 token 齊全），再由 `print_css()` 於同一 selector 用 Blocksy 衍生的 OKLCH **覆寫色彩變數**。
- 實作點：在 `Theme::instance()` 組裝流程偵測 `theme==='blocksy'` → 將 `$this->theme` 正規化為 `'power'`（給 selector 用）+ 覆寫色彩屬性為 Blocksy 衍生值；`add_html_attr` 同步輸出 `data-theme="power"`。

**理由**：完全複用「power 主題 = daisyUI 註冊 + print_css 覆寫」這條已驗證可運作的路徑，**無需改 daisyUI 設定、無需 rebuild CSS**。代價：前台 `data-theme` 屬性顯示 `power` 而非 `blocksy`（純屬性層，使用者不可見，元件外觀正確）。

> **替代方案（已否決）**：在 tailwind.config.front.cjs themes 註冊 `blocksy` 條目。否決理由：需 `pnpm build-css:front` 重建並提交 `front.min.css`，增加 build 耦合與 PR 體積，且 daisyUI 條目的 base 值仍會被 print_css 覆寫，註冊純屬多餘。

### D2 — OKLCH 轉換放置位置

新增 `J7\Powerhouse\Theme\Utils\ColorConvert`（靜態類別，`Theme/Utils/ColorConvert.php`）。理由：色彩數學是 Theme 領域純函式，獨立檔便於單元測試與 PHPStan level 9；不污染通用 `Utils\Base`。

### D3 — Blocksy 偵測 + palette 讀取放置位置

新增 `J7\Powerhouse\Theme\Core\Blocksy`（`Theme/Core/Blocksy.php`），職責：`is_blocksy(): bool`、`get_palette(): array`（回傳 `['color1'=>'#hex', ...]` 已驗證）、`get_oklch_overrides(): array`（回傳 `['p'=>'L% C H', 's'=>..., 'b1'=>...]` 對應屬性名，缺色自動略過）。理由：偵測與資料來源集中、可獨立測試、Theme 與 Bootstrap 共用。

### D4 — 前端「是否 Blocksy」資料流

複用 **Bootstrap.php:162-168 既有 `wp_localize_script(powerhouse_data, ...)`** 管道，在該陣列**新增明文** sibling key（與加密的 `env` 並列）：
```php
'blocksy' => [
  'is_blocksy'   => Theme\Core\Blocksy::instance()->is_blocksy(),
  'palette_hex'  => Theme\Core\Blocksy::instance()->get_palette_hex_for_preview(), // ['#hex'...8 色] 或 []
],
```
前端讀 `window.powerhouse_data.blocksy`（明文，無需 simpleDecrypt）。理由：env 是加密 blob，塞布林+palette 進去要連帶改 antd-toolkit 的 encrypt schema，成本高；明文 sibling 零侵入。

---

## 架構變更（檔案清單）

### 後端（PHP）
- **新增** `inc/classes/Theme/Utils/ColorConvert.php` — `hex_to_oklch(string $hex): string`，輸出 `"L% C H"`（對齊 utils.tsx:33 `${l*100}% ${c} ${h}`）。
- **新增** `inc/classes/Theme/Core/Blocksy.php` — Singleton；`is_blocksy()`、`get_palette()`、`get_oklch_overrides()`、`get_palette_hex_for_preview()`。
- **修改** `inc/classes/Theme/Model/Theme.php` — `instance()`（131-148）偵測 `theme==='blocksy'` → 套用 `Blocksy::get_oklch_overrides()` 覆寫色彩屬性、並把 `$this->theme` 正規化為 `'power'`（D1）。
- **修改** `inc/classes/Theme/Core/FrontEnd.php` — `add_html_attr`（30-43）對 blocksy 模式輸出正規化後的 `data-theme`（與 Theme model 一致，D1）。
- **修改** `inc/classes/Bootstrap.php` — `enqueue_admin_assets`（162-168）localize 陣列新增明文 `blocksy` key（D4）。

### 前端（React / TS）
- **修改** `js/src/pages/admin/Settings/Theme/constants.tsx` — `THEME_MAPPER` 開頭新增 `blocksy` placeholder 條目（含 `theme:'blocksy'` 與一組佔位 OKLCH，實際值執行期由 localized palette 覆蓋）。
- **修改** `js/src/pages/admin/Settings/Theme/index.tsx` — 卡片網格在 `custom` 旁加入 `blocksy` 卡片；傳入 `isBlocksy` 與 `paletteHex`。
- **修改** `js/src/pages/admin/Settings/Theme/Option.tsx` — 支援 `disabled` + Tooltip（非 Blocksy 時）；blocksy 卡片 swatch 用 inline OKLCH（來自 palette）渲染。
- **修改** `js/src/pages/admin/Settings/Theme/Custom.tsx` — `useEffect`（14-28）針對 `theme==='blocksy'` 特例：把 localized palette 轉成的 OKLCH 寫入 `theme_css` 供預覽（取代「從 THEME_MAPPER 找」）。
- **修改** `js/src/types/global.d.ts` — 修正/擴充 `powerhouse_data` 型別，新增 `blocksy: { is_blocksy: boolean; palette_hex: string[] }`。
- **（可選）新增** `js/src/pages/admin/Settings/Theme/blocksy.ts` — 集中 palette_hex → daisyUI OKLCH 映射的前端工具（與後端 `get_oklch_overrides` 對應，供預覽用），避免在元件內散落映射邏輯。

### 測試
- **修改** `tests/Integration/ThemeTest.php` — 新增 blocksy 模式的 happy/edge/error 案例（`@group theme`）。
- **新增** `tests/Integration/ColorConvertTest.php` — Hex→OKLCH 對 8 個 Blocksy 預設色的精度斷言（`@group theme`）。
- **（可選）新增/修改** E2E：`tests/e2e/01-admin/api-settings.spec.ts` 驗證 blocksy 選項持久化。

---

## 資料流分析

### 流程 A — 前台動態套用（核心）

```
wp_head(-100)         Theme::instance()          Blocksy::get_oklch_overrides()      print_css()
     │                      │                              │                              │
     ▼                      ▼                              ▼                              ▼
enable_theme=yes? ─▶ theme==='blocksy'? ──▶ is_blocksy()? ──▶ get_palette() ──▶ hex_to_oklch ──▶ <style>#tw[data-theme='power']{--p:..}</style>
     │                      │                    │                │                │                 │
     ▼(no)                  ▼(no)                ▼(no/fn missing)  ▼(缺色)          ▼(parse fail)     ▼(空 palette)
   不輸出                走原本 theme         退回 Powerhouse    該 token 退預設    該 token 退預設    全退 Powerhouse 預設
                        (power/light/custom)   預設(等同 power)
```

Shadow paths：
- **nil**：`blocksy_manager()` / `->colors` 為 null → `get_oklch_overrides()` 回 `[]` → Theme 屬性全保留預設。
- **empty**：palette 回空陣列 → 同上。
- **partial**：palette 僅部分色 → 僅覆寫成功轉換的 token，其餘保留預設。
- **error**：單一色 hex 非法 / 轉換丟例外 → catch 後該色略過，不影響其他色與頁面渲染。

### 流程 B — 後台卡片可選性 + 預覽

```
admin_enqueue_scripts(-100)        window.powerhouse_data.blocksy        React Theme page
        │                                    │                                  │
        ▼                                    ▼                                  ▼
  is_blocksy()─▶ localize{is_blocksy,palette_hex} ─▶ Option(blocksy){disabled=!is_blocksy} ─▶ 點擊?
        │                                    │                                  │
        ▼(非 powerhouse 頁)                  ▼(palette_hex=[])                  ▼(disabled)
   不 localize(SPA 不在此頁)            預覽顯示提示/灰態                    Tooltip「需使用 Blocksy 主題」, 不可選
```

Shadow paths（使用者互動）：
- **disabled 卡片重複點擊** → onClick noop（disabled 時不綁 setFieldValue）。
- **palette_hex 為空但 is_blocksy=true**（理論罕見）→ 卡片可選，預覽退回 placeholder OKLCH，不 crash。
- **選 blocksy 後切到其他卡片再切回** → `Custom.tsx` useEffect 依 `theme` 重新以 palette 重建 theme_css，不殘留舊值。

---

## 錯誤處理登記表

| 方法/路徑 | 可能失敗原因 | 錯誤類型 | 處理方式 | 使用者可見? |
| --- | --- | --- | --- | --- |
| `Blocksy::get_palette()` | `blocksy_manager()` 未定義 | fatal（若未防護） | `function_exists` 前置守衛 → 回 `[]` | 否（靜默退預設） |
| `Blocksy::get_palette()` | `->colors` 為 null / method 不存在 | TypeError | `is_object` + `method_exists` 守衛 → 回 `[]` | 否 |
| `Blocksy::get_oklch_overrides()` | 某 color key 缺失 | 無例外 | `isset` 略過該 key，續處理其餘 | 否（該 token 退預設） |
| `ColorConvert::hex_to_oklch()` | hex 格式非法（非 #RRGGBB） | InvalidArgument | 正則驗證失敗回 `null` → 呼叫端略過該 token | 否 |
| `ColorConvert::hex_to_oklch()` | 數學域錯誤（cbrt 負值等） | 無（公式定義域安全） | linear/OKLab 公式對 0–1 安全；夾值 clamp | 否 |
| `Theme::instance()` blocksy 分支 | overrides 為空 | 無例外 | 等同未覆寫，輸出 Powerhouse 預設色 | 否（外觀=power 預設） |
| Bootstrap localize | `Blocksy::instance()` 拋例外 | 任意 | try/catch 包裹，失敗則 `is_blocksy=false` | 否（卡片 disabled，安全降級） |
| 前端讀 `powerhouse_data.blocksy` | key 不存在（舊快取 JS） | undefined | `?.` 可選鏈 + 預設 `{is_blocksy:false,palette_hex:[]}` | 否（卡片 disabled） |

> 全表無「處理方式=無 且 使用者可見=靜默」之 CRITICAL GAP。所有失敗路徑均安全降級為「保留 Powerhouse 預設色 / 卡片 disabled」，不中斷頁面。

---

## 失敗模式登記表

| 程式碼路徑 | 失敗模式 | 已處理? | 有測試? | 使用者可見? | 恢復路徑 |
| --- | --- | --- | --- | --- | --- |
| `Blocksy::is_blocksy()` | 非 Blocksy 站台誤判為 true | 是（`get_template()==='blocksy'`） | 是（mock get_template） | 否 | 回 false → 卡片 disabled |
| `Blocksy::is_blocksy()` | Blocksy 子主題漏判 | 是（template=父 slug，研究確認 init.php:269） | 是（mock 子主題情境） | 否 | 回 true → 正常 |
| `Blocksy::get_palette()` | Blocksy 未啟用 | 是（多重守衛） | 是（無 blocksy_manager 情境） | 否 | 回 `[]` → 退預設 |
| `ColorConvert::hex_to_oklch()` | 與 culori 結果偏差過大 | 是（標準公式） | 是（8 色精度斷言） | 否（色偏） | 公式校正 |
| `ColorConvert::hex_to_oklch()` | 大小寫/含 alpha hex | 是（正則 + lower） | 是（邊緣案例） | 否 | 略過退預設 |
| `Theme::instance()` 快取 | singleton 快取導致 Blocksy 改色後同請求不更新 | 是（每次 wp_head 為新請求，singleton 隨請求重建） | 部分（文件註記） | 否 | 跨請求自然刷新 |
| `Theme::print_css()` selector | data-theme 與輸出 selector 不一致 | 是（D1 統一正規化為 power） | 是（斷言 selector=power） | 否（屬性層） | — |
| 前端 Option 卡片 | disabled 仍可點 | 是（disabled 時不綁 onClick） | 是（E2E/單元） | 是（Tooltip） | — |
| 前端預覽 | blocksy 預覽顯示空白 | 是（palette→OKLCH 餵 theme_css） | 部分 | 是（預覽） | 退 placeholder |

---

## 實作步驟

### 第一階段：後端色彩轉換核心（PHP，可獨立合併、可獨立測試）
1. **建立 ColorConvert util**（檔案：`inc/classes/Theme/Utils/ColorConvert.php`）
   - 行動：實作 `public static function hex_to_oklch(string $hex): ?string`。流程：正則驗證 `#RRGGBB` → 解析 R/G/B(0–255) → 正規化 0–1 → sRGB 反伽馬（`c<=0.04045 ? c/12.92 : ((c+0.055)/1.055)^2.4`）得 linear RGB → linear→LMS（OKLab 矩陣 M1）→ `cbrt` → LMS'→OKLab(矩陣 M2) → OKLab→OKLCH（`C=hypot(a,b)`、`H=atan2(b,a)` 轉度、負角 +360）→ 格式化 `sprintf('%s%% %s %s', L*100, C, H)`，數值精度對齊 culori（建議保留足夠小數位，不過度四捨五入）。缺色/非法回 `null`。
   - 原因：純函式、最高可測性，後續所有覆寫依賴它。
   - 依賴：無。
   - 風險：中（數學精度）。緩解：第二階段測試立即驗證。
   - 公式來源：OKLab 官方定義（Björn Ottosson）/ culori 同源實作。

2. **建立 ColorConvert 測試**（檔案：`tests/Integration/ColorConvertTest.php`，`@group theme`）
   - 行動：對 8 個 Blocksy 預設 hex（#2872fa/#1559ed/#3A4F66/#192a3d/#e1e8ed/#f2f5f7/#FAFBFC/#ffffff）斷言輸出落在 culori 參考值容差內（L ±0.5%、C ±0.005、H ±0.5°；H 對近灰色（C≈0）放寬或忽略）。含非法 hex（回 null）、小寫、白/黑邊界。
   - 原因：把「PHP≈culori」變成可執行保證。
   - 依賴：步驟 1。風險：低。
   - **參考值取得**：以前端 `hexToOklch`（utils.tsx）對同 8 色的輸出為 golden values 寫入測試（規劃交接時於 tdd-coordinator 註明：先用 culori 跑出 golden，再硬編入斷言）。

### 第二階段：Blocksy 偵測與映射（PHP，依賴一階段）
3. **建立 Blocksy 服務**（檔案：`inc/classes/Theme/Core/Blocksy.php`，SingletonTrait）
   - 行動：
     - `is_blocksy(): bool` → `\get_template() === 'blocksy'`（涵蓋子主題）。
     - `get_palette(): array` → 守衛 `function_exists('blocksy_manager')` + `is_object($mgr->colors)` + `method_exists` → 呼叫 `get_color_palette()` → 攤平成 `['color1'=>'#hex',...]`，逐色 hex 驗證；任何例外 try/catch 回 `[]`。
     - `get_oklch_overrides(): array` → 依「色彩對應」表把 color1→`p`、color2→`s`、color3→`bc`、color4→`n`、color6→`b3`、color7→`b2`、color8→`b1`，逐項 `ColorConvert::hex_to_oklch`，null 則略過；回 `['p'=>'..','s'=>'..',...]`（key 為 Theme 屬性名，不含 dash）。
     - `get_palette_hex_for_preview(): array` → 回 `['#hex'×8]`（給前端預覽，缺色補空字串或略過）。
   - 原因：集中所有 Blocksy 耦合與容錯，Theme/Bootstrap 共用。
   - 依賴：步驟 1。風險：中（外部主題 API）。緩解：研究已驗證 API（colors.php:46）、多重守衛、步驟 4 測試。

4. **Blocksy 服務測試**（併入 `ThemeTest.php` 或 `ColorConvertTest.php`）
   - 行動：mock `get_template()` 回 'blocksy' / 非 'blocksy'；模擬 `blocksy_manager` 不存在；模擬 palette 缺 color3。斷言 `get_oklch_overrides` 正確映射與降級。
   - 依賴：步驟 3。風險：低。

### 第三階段：前台動態套用（PHP，依賴二階段）
5. **Theme model 接線**（檔案：`inc/classes/Theme/Model/Theme.php`，`instance()` 131-148）
   - 行動：在組裝 `$theme_css` 後、`new self()` 前，若 `theme==='blocksy'`：取 `Blocksy::instance()->get_oklch_overrides()`，將回傳的屬性（p/s/bc/n/b3/b2/b1）合併進建構輸入；並把 `theme` 正規化為 `'power'`（D1，供 print_css selector）。空 overrides 則僅做正規化（外觀=power 預設）。
   - 原因：讓 `print_css()` 無需改動即輸出正確 selector 與覆寫色。
   - 依賴：步驟 3。風險：中（singleton 快取語意）。緩解：跨請求天然刷新；於碼註記。

6. **FrontEnd data-theme 對齊**（檔案：`inc/classes/Theme/Core/FrontEnd.php`，`add_html_attr` 30-43）
   - 行動：`$theme` 取值改為與 Theme model 正規化一致——blocksy 模式輸出 `data-theme="power"`（可直接複用 `Theme::instance()->theme`，因步驟 5 已正規化）。確保 HTML 屬性與 print_css selector 一致。
   - 原因：避免屬性/CSS selector 不匹配導致樣式落空（D1）。
   - 依賴：步驟 5。風險：低。

7. **第三階段測試**（`ThemeTest.php`，`@group theme` happy/edge）
   - 行動：set `theme='blocksy'` + mock Blocksy palette → 斷言 `print_css()` 輸出含 `#tw[data-theme='power']` 且 `--p` = color1 的 OKLCH；mock 非 Blocksy → 斷言退回預設且 selector 仍合法；mock blocksy_manager 缺失 → 不 fatal、輸出預設。
   - 依賴：步驟 5、6。風險：低。

### 第四階段：後端暴露旗標給前端（PHP，可與三階段並行）
8. **Bootstrap localize 擴充**（檔案：`inc/classes/Bootstrap.php`，162-168）
   - 行動：localize 陣列新增明文 `'blocksy' => ['is_blocksy'=>..., 'palette_hex'=>...]`（try/catch 包裹，失敗 `is_blocksy=false`）。維持既有 `env` 不動。
   - 原因：前端取得可選性與預覽資料的零侵入管道（D4）。
   - 依賴：步驟 3。風險：低。

### 第五階段：前端卡片與預覽（React，依賴四階段）— 適合派 `react-master`
9. **型別與常數**（`js/src/types/global.d.ts`、`constants.tsx`）
   - 行動：`global.d.ts` 宣告 `var powerhouse_data: { env: string; blocksy?: { is_blocksy: boolean; palette_hex: string[] } }`；`THEME_MAPPER` 最前面加 `blocksy` placeholder 條目。
   - 依賴：步驟 8（schema 對齊）。風險：低。
10. **Option 卡片 disabled + Tooltip**（`Option.tsx`）
    - 行動：新增 `disabled?: boolean` prop；disabled 時不綁 `onClick`、加灰態樣式、包 antd `Tooltip` 文案「需使用 Blocksy 主題」。blocksy 卡片 swatch 以 inline `style`（OKLCH，來自 palette）渲染（因無 daisyUI `[data-theme=blocksy]` CSS）。
    - 依賴：步驟 9。風險：低（注意：Tooltip 包 disabled 元素需 wrapper span 才能觸發 hover）。
11. **網格接線**（`index.tsx`）
    - 行動：讀 `window.powerhouse_data?.blocksy`；在 `custom` 卡旁渲染 `<Option theme="blocksy" disabled={!is_blocksy} ... />`，傳 palette。
    - 依賴：步驟 10。風險：低。
12. **預覽資料**（`Custom.tsx` useEffect 14-28 + 可選 `blocksy.ts`）
    - 行動：`theme==='blocksy'` 時，把 `palette_hex` 經前端映射（同 D 對應表）轉 OKLCH 寫入 `theme_css`，驅動既有預覽（getStyle/index.tsx 預覽區）。映射邏輯抽到 `blocksy.ts` 與後端 `get_oklch_overrides` 對應。
    - 原因：最小驚訝——blocksy 預覽走既有 custom 預覽路徑，顯示「當前 Blocksy 實際配色」。
    - 依賴：步驟 9。風險：中（前後端映射須一致）。緩解：對應表單一事實來源（同一張表，前後端各實作一次並由步驟 2 的 golden 值間接對齊）。

### 第六階段：E2E 與收尾（可選增強）
13. **E2E**（`tests/e2e/01-admin/api-settings.spec.ts` 或 ui-admin-spa）
    - 行動：驗證 blocksy 選項可存取/持久化；非 Blocksy 環境卡片 disabled。
    - 依賴：前述全部。風險：低（E2E 環境是否安裝 Blocksy 需確認，否則僅測 disabled 態）。

---

## 測試策略

- **單元/整合（PHP，PHPUnit）**：
  - `ColorConvertTest.php`：8 色精度、非法 hex、大小寫、黑白邊界。
  - `ThemeTest.php` 擴充：blocksy 模式 print_css 輸出、selector 正規化、Blocksy 缺失降級、子主題偵測。
  - 指令：`vendor/bin/phpunit --group theme`（既有 theme domain group）；或 `composer test`。
- **前端**：元件層（Option disabled/Tooltip、Custom 預覽映射）——專案目前無 jest/vitest 前端單元設定（僅 Playwright E2E），故前端正確性主要靠 E2E + 型別。
- **E2E（Playwright）**：`tests/e2e/01-admin/` 下驗證選項持久化與 disabled 態。指令：見 `tests/e2e/package.json` / `playwright.config.ts`。
- **關鍵邊界**：Blocksy 未啟用、palette 缺色、子主題、非法 hex、disabled 卡片重複點擊、選 blocksy→切換→切回的 theme_css 重建、Blocksy 改色後跨請求刷新。
- **手動驗證建議**：本機 Blocksy 站台改調色盤 → 重整前台確認 daisyUI 元件主色跟隨（驗證「動態不存快照」）。

---

## 依賴項目

- **外部主題 Blocksy ≥ 2.1.0**（已安裝，路徑 `wp-content/themes/blocksy`）：`blocksy_manager()->colors->get_color_palette()`（colors.php:46）、theme_mod `colorPalette`（colors.php:50）。**不需** Blocksy Companion（free 主題即可讀 palette）。
- 既有 culori（前端，已有，utils.tsx）作為 OKLCH golden values 來源。
- 既有 antd `Tooltip`（前端，已有）。
- 無新增 composer / npm 依賴。

---

## 風險與緩解措施

- **高**：PHP Hex→OKLCH 與 culori 不一致 → 標準同源公式 + 8 色 golden 斷言（容差），CI 把關。
- **中**：`data-theme` selector 與 daisyUI CSS 不匹配 → D1 正規化為已註冊的 `power`，零 rebuild。
- **中**：Blocksy 外部 API 變動/未啟用 → 偵測層多重守衛 + try/catch + 降級退預設。
- **中**：前後端色彩映射對應表分歧 → 對應表為唯一事實來源，前後端各實作並以 golden 值交叉驗證；對應表變更須同步雙端（於碼註記）。
- **低**：singleton 快取造成「同請求」不刷新 → wp_head 每請求重建，跨請求自然同步；文件註記。
- **低**：Tooltip 包 disabled 元素不觸發 → 用 wrapper span。

---

## 錯誤處理策略

統一採「**安全降級（graceful degradation）**」：任何 Blocksy 偵測/讀取/轉換失敗，一律退回 Powerhouse 既有預設色（前台外觀等同 `power` 主題）或卡片 disabled（後台），**永不中斷頁面渲染、永不 fatal**。所有外部 API 呼叫以 `function_exists`/`is_object`/`method_exists` 前置守衛 + try/catch 兜底；所有色彩轉換對非法輸入回 `null` 並由呼叫端略過該 token（部分成功優於全有全無）。

---

## 限制條件（此計劃不會做的事）

- **不存快照**：不持久化 Blocksy 衍生色到 `theme_css`（DB）；前台每次即時讀取。後台預覽寫入 form state 的 theme_css 僅供預覽，不代表持久策略改變。
- **不引入對比演算法**：content 前景（`--pc/--sc/--nc`）保留 Powerhouse 預設（白/既有），不依 base 色動態計算對比（YAGNI）。
- **不對應非色彩參數**：圓角、動畫、邊框尺寸一律保留 Powerhouse 預設，不從 Blocksy 讀取。
- **不對應 color5**（Blocksy 邊框色）：daisyUI 無對應 token，略過。
- **不註冊 daisyUI `blocksy` 主題**：不改 tailwind.config、不 rebuild front.min.css（D1）。
- **不改動加密 `env` schema**：blocksy 旗標走明文 sibling key。
- **不支援多 palette / Blocksy 變體調色盤**：僅讀全域 `colorPalette` 主調色盤。
- **不處理 Blocksy 以外主題的「跟隨」**：僅 Blocksy（含子主題）。

---

## 成功標準

- [ ] Blocksy 站台後台「主題顏色」出現可選的「跟隨 Blocksy」卡片。
- [ ] 非 Blocksy（且非 Blocksy 子主題）站台該卡片 disabled + Tooltip「需使用 Blocksy 主題」。
- [ ] 選此選項後，前台 daisyUI `--p`=color1、`--s`=color2、`--b1`=color8、`--b2`=color7、`--b3`=color6、`--bc`=color3、`--n`=color4 的 OKLCH 值正確。
- [ ] 修改 Blocksy 調色盤後，前台重整即同步（不需重存 Powerhouse 設定）。
- [ ] Blocksy 未啟用 / palette 缺色 / 非法 hex 時，前台不 fatal，退回 Powerhouse 預設色。
- [ ] Blocksy 子主題站台正確被偵測為「可跟隨」。
- [ ] `ColorConvertTest` 對 8 個 Blocksy 預設色全數通過容差斷言。
- [ ] `vendor/bin/phpunit --group theme` 全綠。
- [ ] 後台選此卡片時，預覽顯示當前 Blocksy 實際配色（非空白、非誤導）。

## 預估複雜度：中
