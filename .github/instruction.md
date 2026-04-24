# `.github/` 目錄架構指引（Powerhouse）

> 簡化自 `power-course/.github/instruction.md`。完整設計哲學請參考範本來源。
> **Powerhouse 角色**：Power 系列外掛的共用基礎庫（foundation plugin），提供統一 REST API、LC、Theme、mu-plugins、WC 整合。

---

## 目錄結構

```
.github/
├── workflows/
│   ├── pipe.yml         # 主 pipeline (claude job + integration-tests job)
│   ├── issue.yml        # Issue requirement expansion (PM/DEV 模式)
│   └── release.yml      # tag v* 推送時自動打 release zip
├── pipe.md              # pipe.yml 的中文規格書（必看；刻意放在 workflows/ 外避免被誤認為 workflow）
├── act/
│   └── test.yml         # 本機 act 多 job 結構驗證（不在 workflows/ 內，避免線上誤觸發）
├── actions/claude-retry/action.yml   # 3 次重試 + 30s/60s backoff composite
├── prompts/
│   ├── clarifier-interactive.md
│   ├── clarifier-pipeline.md
│   ├── planner.md
│   └── tdd-coordinator.md
├── templates/
│   ├── pipeline-upgrade-comment.md
│   ├── test-result-comment.md
│   └── acceptance-comment.md
├── scripts/upload-to-bunny.sh
└── instruction.md       # 本文件
```

---

## 設計哲學（精簡版）

- **Workflow-Level Orchestration**：agent 之間靠 git commit、step outputs、issue comment 串接，不靠 sub-agent。
- **Two-Job Pipeline**：Job 1 專注 AI 創作，Job 2 專注自動化驗證 + PR。
- **3 次重試 + 指數退避**：`actions/claude-retry` 包裝 `claude-code-action@v1`，避免暫時性 API 失敗炸 pipeline。
- **三循環測試修復**：`test → fix → test → fix → final test`，所有步驟 `continue-on-error`，最終 evaluate step 判定整體結果。
- **Prompt 抽取原則**：超過 20 行 / 含角色定義 / 多處引用 → 進 `prompts/`；佔位符用 `{{NAME}}`。
- **Comment 模板**：所有條件邏輯在 shell 端完成，模板僅做佔位符替換。

---

## Powerhouse 特性偵測結果

| 特性 | 狀態 | 影響 |
|------|------|------|
| `.wp-env.json` | ✅ 存在（port 8898） | 可跑 wp-env / PHPUnit |
| Frontend build | ✅ `pnpm run build`（單一 vite build） | 保留 K 段建置前端 |
| `'capability' => 'manage_woocommerce'` 行 | ❌ 不存在（plugin.php 已 `'lc' => false`） | **移除 LC Bypass step** |
| React Admin SPA | ✅ Refine + AntD，`?page=powerhouse` | AI 驗收以 SPA 載入 + REST API 可達為主 |
| `specs/` 目錄 | ✅ 存在 | 保留 clarifier-pipeline aibdd 流程 |
| PHPUnit | ✅ `composer run test`（Integration testsuite） | 保留 I/J 段三循環 |
| `tests/e2e/` Playwright | ✅ 存在（admin/frontend/integration） | 保留 K/L 段 AI 驗收 |
| `.e2e-progress.json` | ✅ 存在 | 不需注入 lc_bypass_applied（無 LC bypass） |

---

## Docker / wp-env 防雷重點（**不可省略**）

1. **wp-env start 3 次重試 + delays 15/45/90s + 重啟 unhealthy 容器** — `pipe.yml` H 段
2. **uploads 目錄前置建立**：必須在 wp-env start **之前** `sudo rm -rf && mkdir -p && chmod 777`
3. **Composer 主機端安裝**：確保 `vendor/` 存在
4. **`set -o pipefail` + `tee`**：必須加 pipefail 才能抓 exit code
5. **`wp-env run tests-cli --env-cwd=wp-content/plugins/powerhouse`**：path 與 `.wp-env.json` mapping `.` 對齊
6. **強制 git HTTPS**：`url."https://github.com/".insteadOf "git@github.com:"`
7. **fetch-depth: 0**（Job 1）vs **fetch-depth: 50**（Job 2）
8. **Playwright `--with-deps` + CJK 字型**：`fonts-noto-cjk` 必裝
9. **wp-env start 失敗時的 unhealthy 容器 Recovery**：`docker ps --filter "health=unhealthy"`

---

## Secrets 清單（repo settings 必備）

| Secret | 用途 |
|--------|------|
| `CLAUDE_CODE_OAUTH_TOKEN` | Claude Code Action 必備 |
| `BUNNY_STORAGE_HOST` | Bunny Storage 主機 |
| `BUNNY_STORAGE_ZONE` | Bunny Storage zone 名稱 |
| `BUNNY_STORAGE_PASSWORD` | Bunny Storage AccessKey |
| `BUNNY_CDN_URL` | Bunny CDN 公開 URL prefix |

`GITHUB_TOKEN` 由 Actions 自動提供，permissions 已在 workflow 內宣告。

---

## TODO / 已知問題

- ~~**`parse_agent` 英文關鍵字（OK/go/start）匹配過寬**~~：已加字邊界 `\b(OK|go|start)\b`
- **Claude fix prompt 寫死在 workflow**（I 段約 60 行）：未來可抽到 `prompts/claude-fix.md`
- **PHPUnit 預設只跑 5 大 category**：`subscription/infrastructure/mu-plugin` 未涵蓋；若 issue 涉及這些 domain，需手動 `composer run test:smoke` 等替代或在 pipe.yml 顯式加 `--group`
- **AI 驗收 SPA 業務測試**：已加強為 6 大區塊（plugin 啟用 / SPA 完整載入 / REST API 路由 / mu-plugins 注入 / Settings 持久化 / WC 整合 smoke）；若未來新增業務 UI，請繼續增補 prompt 內驗收清單
- **Bunny CDN secrets 未設**：smoke media 上傳會 fail（已 `continue-on-error`），不影響 PR 建立

---

## 延伸閱讀

- 完整設計哲學：`power-course/.github/instruction.md`
- 範本主 pipeline：`power-course/.github/workflows/pipe.yml` + `pipe.md`
