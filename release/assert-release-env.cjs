/**
 * Release 環境檢查：只允許在「WordPress plugins 目錄下的主 checkout、master 分支」建置與發版
 *
 * ## 為什麼（v3.6.0 事故，power-course Issue #285）
 *
 * `tailwind.config.cjs` / `tailwind.config.front.cjs` 的 `content` 用相對路徑
 * `'../power-*' + '/inc/**'`、`'../power-*' + '/js/src/**'` 掃「旁邊的外掛」，才知道要產哪些 class。
 * v3.6.0 是在 `C:/Users/User/.ph-release`（一個 linked worktree）建置的，它的上一層是
 * `C:/Users/User/`，旁邊**沒有任何 power-* 外掛**——Tailwind 沒有報錯，只是把用不到的 class 全部 purge：
 *
 *   - front.min.css 182KB → 121KB，少 786 條規則；admin.min.css 少 787 條
 *   - power-course：學員「我的課程」整片空白、教室版面塌掉、圖示撐到 620px
 *   - 託管的 110 站全部受影響，客戶回報「課程不見了」
 *
 * 建置不會失敗、zip 照樣產生、release 照樣發出去——所以必須在發版前主動擋下。
 *
 * ## 檢查項目（任一不符就 exit 1）
 *
 *   1. 目前分支是 `master`
 *   2. 是主 checkout，不是 `git worktree add` 出來的 linked worktree
 *   3. 上一層目錄是 WordPress 的 `plugins`，且 REQUIRED_SIBLINGS 都在、都有 `inc/`
 *
 * sibling 不在 master 或有未提交改動只警告不擋：掃到的是它當下 checkout 的 class，
 * 結果可能多一些或少一些，但不會像 v3.6.0 那樣整批消失。
 *
 * 由 `release/.release-it.cjs` 的 `before:init` 第一個 hook 與 `pnpm zip` 呼叫。
 * 只擋 release 流程；日常 `pnpm build-css:*` 在 worktree 跑沒關係（dist 不進版控）。
 */

const { execSync } = require('child_process')
const fs = require('fs')
const path = require('path')

/**
 * 依賴 Powerhouse front.min.css / admin.min.css 的外掛（inc/ 或 js/src 內有 Tailwind / daisyUI class）
 * 新增會用到 Powerhouse 樣式的外掛時，加進這裡
 */
const REQUIRED_SIBLINGS = [
	'power-course',
	'power-shop',
	'power-partner',
	'power-docs',
	'power-funnel',
	'power-contract',
]

const RELEASE_BRANCH = 'master'

const root = path.resolve(__dirname, '..')

/** @param {string} cmd @param {string} [cwd] */
function git(cmd, cwd = root) {
	return execSync(`git ${cmd}`, { cwd, encoding: 'utf8', stdio: ['ignore', 'pipe', 'ignore'] }).trim()
}

const errors = []
const warnings = []

// 1. 分支
const branch = git('branch --show-current')
if (branch !== RELEASE_BRANCH) {
	errors.push(`目前分支是 "${branch || '(detached HEAD)'}"，release 只能在 ${RELEASE_BRANCH} 進行`)
}

// 2. 主 checkout：linked worktree 的 git-dir 是 <main>/.git/worktrees/<name>，與 common-dir 不同
const gitDir = path.resolve(root, git('rev-parse --git-dir'))
const commonDir = path.resolve(root, git('rev-parse --git-common-dir'))
if (gitDir !== commonDir) {
	errors.push(
		`這是 linked worktree（${root}），release 只能在主 checkout 進行。` +
			`主 checkout：${path.dirname(commonDir)}`
	)
}

// 3. sibling 外掛（Tailwind content 掃的就是這些）
const pluginsDir = path.dirname(root)
if (path.basename(pluginsDir) !== 'plugins') {
	errors.push(`上一層目錄是 ${pluginsDir}，不是 WordPress 的 plugins 目錄；Tailwind 掃不到 sibling 外掛`)
}

for (const name of REQUIRED_SIBLINGS) {
	const dir = path.join(pluginsDir, name)
	if (!fs.existsSync(path.join(dir, 'inc'))) {
		errors.push(`缺少 sibling 外掛 ${dir}（或其 inc/）；它用的 class 會被整批 purge`)
		continue
	}

	try {
		const siblingBranch = git('branch --show-current', dir)
		if (siblingBranch !== RELEASE_BRANCH) {
			warnings.push(`${name} 目前在 "${siblingBranch}"，不是 ${RELEASE_BRANCH}；掃到的是該分支的 class`)
		}
		// 只看 Tailwind 會掃的目錄，.serena/ 之類的工具檔不算
		if (git('status --porcelain -- inc js/src', dir) !== '') {
			warnings.push(`${name} 的 inc/ 或 js/src/ 有未提交的改動；掃到的 class 含這些改動`)
		}
	} catch {
		warnings.push(`${name} 不是 git repo，無法確認分支`)
	}
}

for (const w of warnings) {
	// eslint-disable-next-line no-console
	console.warn(`⚠ [release-env] ${w}`)
}

if (errors.length) {
	// eslint-disable-next-line no-console
	console.error(
		[
			'',
			'✖ [release-env] 這個環境不能建置 / 發佈 Powerhouse：',
			...errors.map((e) => `  - ${e}`),
			'',
			'  請到 WordPress plugins 目錄下的 powerhouse 主 checkout、切到 master、pull 最新後再跑。',
			'  原因見本檔開頭（v3.6.0 在 worktree 建置，front.min.css 少了 786 條規則）。',
			'',
		].join('\n')
	)
	process.exit(1)
}

// eslint-disable-next-line no-console
console.log(`✓ [release-env] ${root}（${RELEASE_BRANCH}、主 checkout、sibling ${REQUIRED_SIBLINGS.length} 個齊全）`)
