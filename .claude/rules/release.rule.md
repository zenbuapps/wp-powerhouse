---
globs: "{release/**,package.json,tailwind.config*.cjs,postcss*.cjs,js/src/assets/scss/**}"
---

# Release / 建置規則

## 🔴 只能在「plugins 目錄下的主 checkout、master 分支」建置與發版

**禁止**在 git worktree（`git worktree add`、orca workspace、`.ph-release` 之類的暫存目錄）、
feature branch、或任何「上一層不是 WordPress `plugins` 目錄」的地方跑 `pnpm release*`、
`pnpm release:build-only`、`pnpm zip`。

唯一合法的發版位置：

```
<WordPress>/wp-content/plugins/powerhouse   ← 主 checkout，branch = master，已 pull 最新
<WordPress>/wp-content/plugins/power-course ← sibling 外掛都在旁邊（最好也在 master）
<WordPress>/wp-content/plugins/power-shop
...
```

`release/assert-release-env.cjs` 會在 `before:init` 第一步與 `pnpm zip` 前檢查，不符合就 exit 1。
**不要繞過它**（不要拿掉 hook、不要在 worktree 裡改它的判斷再發版）。

## 為什麼：v3.6.0 事故（2026-10-25，power-course Issue #285）

Tailwind 的 `content` 用**相對路徑**掃旁邊的外掛：

```js
'../power-*/inc/**/*.{php,js,ts,jsx,tsx}',
'../power-*/js/src/**/*.{js,ts,jsx,tsx}',
```

v3.6.0 是在 `C:/Users/User/.ph-release`（一個 linked worktree，分支 `release-build-7a4b9da`）建置的。
它的上一層是 `C:/Users/User/`，旁邊**一個 power-* 外掛都沒有**。Tailwind 不會報錯，只會把「沒掃到的
class」全部 purge，build / zip / GitHub release 全部照常成功：

| | v3.5.5 | v3.6.0 |
|---|---|---|
| `front.min.css` | 182KB | 121KB（少 786 條規則） |
| `admin.min.css` | 346KB | 285KB（少 787 條規則） |

結果：託管的約 110 站自動更新後，power-course 學員「我的課程」整片空白（`[&_.active]:!tw-block` 不見）、
教室版面塌掉（`pc-drawer` 等 daisyUI 元件不見）、圖示撐到 620px（`size-3` 不見）。後台資料全部正常，
只有用學員身分看前台才看得出來，客戶回報成「課程不見了」。託管方先用 mu-plugin 補 CSS 止血。

教訓：**這個 build 的正確性取決於「建置時旁邊放了什麼」，而不是 repo 內容**。任何會改變目錄位置的
建置方式都會靜默產出壞的 CSS。

## 發版前自檢

```bash
pwd                                   # 必須是 .../wp-content/plugins/powerhouse
git branch --show-current             # master
git rev-parse --git-dir               # .git（若是 .git/worktrees/xxx 代表在 worktree）
ls ../ | grep '^power-'               # 依賴 Powerhouse 樣式的外掛都要在
```

建置後抽查（數字大幅下降就是掃描範圍出問題）：

```bash
ls -l js/dist/css/front.min.css js/dist/css/admin.min.css   # 與上一版比較大小
grep -c 'tw-block .active' js/dist/css/front.min.css         # power-course「我的課程」分頁，應 ≥ 1
```

## 依賴 Powerhouse 樣式的外掛清單

`release/assert-release-env.cjs` 的 `REQUIRED_SIBLINGS`：power-course、power-shop、power-partner、
power-docs、power-funnel、power-contract（2026-10 統計：inc/ 或 js/src 內用到 Tailwind / daisyUI class 的外掛）。
新外掛開始用 Powerhouse 的 class 時，加進這個陣列。

power-course 自 Issue #285 起另外自產一份 utility CSS（排在 `powerhouse_front` / `admin.min.css` 之後），
不再完全依賴這裡；其餘外掛仍然完全依賴。所以這條規則對 power-course 以外的外掛依然是唯一防線。

## 已知陷阱：`build-css:front` 實際用的是 `tailwind.config.cjs`

`build-css:front` 同時帶了 `-c ./tailwind.config.front.cjs` 與 `--config ./tailwind.config.cjs`。
`-c` 和 `--config` 是同一個旗標，Tailwind CLI 取**最後一個** → 前台實際用的是後台設定，
`tailwind.config.front.cjs` 目前沒有作用（證據：v3.5.5 的 front.min.css 含只出現在 `js/src` 的 class，
而 front 設定沒有掃 `js/src`）。

**不要順手刪掉 `--config` 來「修正」**：前台有 React 元件（例如 power-course 的 vidstack 播放器在
`js/src/App2`）靠的正是後台設定掃到的 `js/src` class，改成 front 設定會讓它們消失。要改就先比對兩份產出的差異。
