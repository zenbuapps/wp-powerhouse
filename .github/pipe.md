# `pipe.yml` 結構速查（Powerhouse）

> 對應檔案：`.github/workflows/pipe.yml`
> **兩個 Job**：`claude`（釐清 → 規劃 → 實作）→ `integration-tests`（測試 → 修復 → AI 驗收 → PR）
> **適配自**：`power-course/.github/workflows/pipe.yml`，差異詳見最下方「Powerhouse 適配差異」。

---

## 一、觸發方式與模式對照

**觸發事件**：`issue_comment` / `pull_request_review_comment` / `pull_request_review`，body 須含 `@claude`。
**Concurrency**：同一 issue/PR 的新 `@claude` 會取消舊的。

### 關鍵字 → 模式對照

| 留言 | 開工（clarifier → tdd） | 整合測試 + AI 驗收 |
|------|------------------------|-------------------|
| `@claude`（需求還需釐清） | ❌ 僅提澄清問題 | ❌ |
| `@claude`（需求已清楚） | ✅ 由 clarifier 自動升級 pipeline 並一路跑到 tdd | ❌ 需再打 `@claude PR` |
| `@claude 開工`（含 確認/OK/沒問題/開始/go/start） | ✅ | ❌ 需再打 `@claude PR` |
| `@claude 全自動` | ✅ | ✅ 自動 |
| `@claude PR` | ❌ 跳過 | ✅ 於現有分支直接跑 |

**解析優先序**：`全自動` > `PR` > `開工等` > 互動。

---

## 二、Job 1：`claude`

**Runner** `ubuntu-latest` / **Timeout** 180 min / **Permissions**：`contents`/`pull-requests`/`issues: write`、`id-token: write`、`actions: read`

### Job Outputs

| output | 意義 |
|--------|------|
| `branch_name` / `issue_num` | 本輪 `issue/{N}` 分支與 issue 編號（向下相容舊格式 `issue/{N}-*`） |
| `initial_sha` | 進入 workflow 時的 HEAD（用於偵測變更） |
| `claude_ok` | clarifier + (planner/tdd) 整體成敗；skipped 視為 OK |
| `has_changes` | 是否有 commit 或 working tree 變動 |
| `agent_name` | `clarifier` / `clarifier+planner` / `...+tdd-coordinator` / `pr-only` |
| `pipeline_mode` / `full_auto_mode` / `pr_mode` | 模式旗標 |
| `run_integration_tests` | `full_auto_mode OR pr_mode` → 控制 Job 2 觸發 |

### Steps 流程

| 段 | 核心動作 |
|----|---------|
| **A** 前置 | eyes reaction → checkout(fetch-depth:0) → `resolve_branch`（優先 `issue/{N}`，fallback `issue/{N}-*`，新建 `issue/{N}`）→ HTTPS git config → `save_sha` |
| **B** 模式解析 | `parse_agent` 設 `PIPELINE_MODE`/`FULL_AUTO_MODE`/`PR_MODE` → `fetch_context`（issue 上下文）→ 組 clarifier prompt（`PR_MODE=true` 則跳過） |
| **C** Clarifier | `claude-retry` composite action，agent=`zenbu-powers:clarifier`，`max_turns=200`(pipeline)/`120`(interactive)；`PR_MODE=true` 跳過 |
| **D** 橋接 | `detect_specs`（比對 `specs/` diff，early exit 時設 `specs_available=false`）→ `dynamic_upgrade`（`PR_MODE!=true` + interactive + 生成 specs → 升級 pipeline_mode）→ 通知留言 |
| **E** Planner | `PR_MODE!=true && specs_available && pipeline_mode` 才跑；agent=`zenbu-powers:planner`，`max_turns=120` |
| **F** TDD | `PR_MODE!=true && planner_ok=true` 才跑；agent=`zenbu-powers:tdd-coordinator`，`max_turns=200` |
| **G** 收尾 | `check_result` 匯整 outputs → 若有變更 `git push --force-with-lease` 兜底推送 |

---

## 三、Job 2：`integration-tests`

**依賴** `needs: claude` / **Timeout** 150 min

### 啟動條件

```yaml
run_integration_tests == 'true' &&
(
  pr_mode == 'true'                           # PR 模式旁路 claude_ok/has_changes
  OR
  (claude_ok == 'true' && has_changes == 'true')
)
```

### Steps 流程

| 段 | 核心動作 |
|----|---------|
| **H** 環境 | checkout(branch_name, fetch-depth:50) → Node 20 / pnpm / composer → 建 uploads → wp-env start（3 次重試，delay 15/45/90s，重啟 unhealthy 容器） |
| **I** PHPUnit 3 循環 | `test_cycle_1` 失敗 → `claude_fix_1` → `test_cycle_2` 失敗 → `claude_fix_2` → `test_cycle_3`（final，無修復）。所有步驟 `continue-on-error: true`，fix 走 `anthropics/claude-code-action@v1`。指令路徑 `--env-cwd=wp-content/plugins/powerhouse` |
| **J** 彙整 | `final_result` parse PHPUnit summary（`OK (...)` 或 `Tests: ...`）→ 發測試結果留言 |
| **K** AI 驗收 | `detect_smoke` 檢查 diff 有無動到 `js/src/`、`inc/classes/`、`inc/templates/`、`inc/assets/` → 建置前端（`pnpm run build` 單一指令）→ 安裝 CJK 字型 → Playwright 裝 chromium → `run_ai_acceptance`（agent=`zenbu-powers:browser-tester`） |
| **L** 媒體 | `collect_smoke_media` 集中到 `/tmp/smoke-media` → 上傳 Bunny CDN（`ci/{branch}/smoke-test`）→ Artifact 備份 7 天 → 發 Smoke Test 報告留言 |
| **M** PR 守門 | `run_ai_acceptance.outcome != 'failure'` → `自動建立 PR`（gh pr create，body 含測試 badge + AI 驗收 badge + `Closes #N`）；反之發「驗收失敗不自動開 PR」通知 |

### Job Outputs

`final_result_*` 系列：`status` / `cycle` / `fix_count` / `test_total/passed/failures/errors/assertions/skipped/incomplete/warnings`

---

## 四、外部依賴資產

| 類型 | 路徑 |
|------|------|
| Composite action | `./.github/actions/claude-retry` |
| Prompt 模板 | `.github/prompts/{clarifier-pipeline,clarifier-interactive,planner,tdd-coordinator}.md` |
| 留言模板 | `.github/templates/{pipeline-upgrade-comment,test-result-comment,acceptance-comment}.md` |
| Shell script | `.github/scripts/upload-to-bunny.sh` |
| Marketplace | `https://github.com/zenbuapps/zenbu-powers.git`（提供 4 個 agents：clarifier / planner / tdd-coordinator / browser-tester） |
| Secrets | `CLAUDE_CODE_OAUTH_TOKEN`、`BUNNY_STORAGE_{HOST,ZONE,PASSWORD}`、`BUNNY_CDN_URL` |

> **Commit 語言**：所有 prompt 模板皆包含「commit message 必須使用繁體中文」指示。

---

## 五、Powerhouse 適配差異（vs 範本 power-course）

| 範本（power-course） | Powerhouse | 原因 |
|---------------------|-----------|------|
| `--env-cwd=wp-content/plugins/wp-power-course` | `--env-cwd=wp-content/plugins/powerhouse` | 此 plugin 在 `.wp-env.json` 是用 `.`（自身根目錄），對應 wp-env 容器 mapping 為 `wp-content/plugins/powerhouse` |
| `http://localhost:8895` | `http://localhost:8898` | `tests/e2e/playwright.config.ts` 已 hardcode port 8898 |
| `?page=power-course#/courses` HashRouter SPA | `?page=powerhouse`（含 `#license-code` 子頁面） | `inc/classes/Bootstrap.php` add_menu_page slug = `powerhouse` |
| `pnpm run build && pnpm run build:wp` | `pnpm run build` 單一指令 | powerhouse 無 `build:wp` script |
| 套用 LC Bypass step（注入 `'lc' => false`） | **整段移除** | `plugin.php` 已 `'lc' => false`，且無 `'capability' => 'manage_woocommerce'` 行 |
| Smoke 報告條件 `upload_smoke_media.outputs.has_media`（範本 bug，永遠空） | **修正**為 `collect_smoke_media.outputs.has_media` | 詳見 power-course pipe.md Gotchas #1 |
| AI 驗收前端業務描述 (Power Course LMS) | Powerhouse 為核心庫的描述（無傳統業務 UI），驗收重點改為 plugin 啟用無致命錯誤 + Admin SPA 載入 + REST API 路由可達 + mu-plugins 載入 | powerhouse 是 foundation plugin，沒有像 LMS 那樣的業務操作流程 |
| diff 偵測路徑 `js/src/|inc/templates/|inc/assets/|inc/classes/` | **順序調整**為 `js/src/|inc/classes/|inc/templates/|inc/assets/` | powerhouse 主程式集中在 `inc/classes/`，把它前移以提示重要性（功能等價） |

---

## 六、Gotchas

1. **`parse_agent` 英文關鍵字太寬**：`grep -qiE '...|OK|...|go|start'` 大小寫不敏感，一般對話中的 `ok`/`go` 會誤觸。建議加字邊界或限定起始位置。（沿用範本 TODO）
2. **Claude fix prompt 寫死在 workflow**（I 段兩處約 60 行）：可搬到 `.github/prompts/claude-fix.md`。（沿用範本 TODO）
3. **AI 驗收 prompt 寫死 `http://localhost:8898`**：與 `.wp-env.json` port 耦合，若改 port 需同步調整 prompt 內 URL。
4. **PHPUnit 預設 group filter**：powerhouse `phpunit.xml.dist` 預設只跑 `smoke|happy|error|edge|security` 五大 category；`subscription/infrastructure/mu-plugin` 等 domain 需顯式指定 `--group`，CI 預設不會跑到。

---

## 七、修改自查清單

- [ ] 新增 `env.` / `steps.<id>.outputs.` 引用，名稱是否拼對？
- [ ] 跨 job 走 `needs.<job>.outputs.`，Job 1 `outputs:` 區塊同步新增？
- [ ] Stage gating 改動時，B/D/E/F/G 五段一起看
- [ ] Prompt / 留言模板的 `{{ISSUE_NUM}}` placeholder 有對應？
- [ ] Secrets 是否在 repo settings 備齊？
- [ ] 改 `.wp-env.json` 的 port 時，記得同步 K 段 AI 驗收 prompt 的 URL
