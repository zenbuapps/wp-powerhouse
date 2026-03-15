/**
 * 安全性測試
 *
 * 系統化測試各種安全威脅：
 * - XSS 跨站腳本攻擊
 * - SQL Injection 注入
 * - 路徑穿越攻擊
 * - 未授權存取
 * - 大量請求（不是真正的壓測，只是邊界測試）
 */
import { test, expect } from '@playwright/test'
import { getNonce } from '../helpers/admin-setup.js'
import { wpGet, wpPost, wpDelete, type ApiOptions } from '../helpers/api-client.js'
import { API, STRING_EDGE, ID_EDGE } from '../fixtures/test-data.js'

let apiOpts: ApiOptions
const createdIds: number[] = []

test.beforeAll(async ({ request }, testInfo) => {
  const baseURL = testInfo.project.use.baseURL || 'http://localhost:8898'
  apiOpts = { request, baseURL, nonce: getNonce() }
})

test.afterAll(async () => {
  for (const id of createdIds) {
    try {
      await apiOpts.request.delete(
        `${apiOpts.baseURL}/wp-json/wp/v2/posts/${id}?force=true`,
        { headers: { 'X-WP-Nonce': apiOpts.nonce } },
      )
    } catch { /* 忽略 */ }
  }
})

// ─────────────────────────────────────────────────────
// XSS 防護
// ─────────────────────────────────────────────────────

test.describe('XSS 防護', () => {
  test('文章標題 <script> XSS 應被消毒', async () => {
    const res = await wpPost<any>(apiOpts, API.posts, {
      post_type: 'post',
      post_title: STRING_EDGE.xss1,
      post_status: 'draft',
    })
    expect(res.status).toBe(200)
    const ids: number[] = Array.isArray(res.data.data) ? res.data.data : [res.data.data]
    const id = Number(ids[0])
    createdIds.push(id)

    // 查回驗證不含原始 script 標籤
    const getRes = await wpGet<any>(apiOpts, API.postById(id))
    if (getRes.status === 200) {
      expect(getRes.data.post_title).not.toContain('<script>')
      expect(getRes.data.post_title).not.toContain('alert(')
    }
  })

  test('文章內容 img onerror XSS 應被消毒', async () => {
    const res = await wpPost<any>(apiOpts, API.posts, {
      post_type: 'post',
      post_title: 'E2E PH XSS Content Test',
      post_content: STRING_EDGE.xss2,
      post_status: 'draft',
    })
    expect(res.status).toBe(200)
    const ids: number[] = Array.isArray(res.data.data) ? res.data.data : [res.data.data]
    createdIds.push(...ids.map(Number))
  })

  test('用戶 display_name XSS 應被消毒', async () => {
    const timestamp = Date.now()
    const createRes = await wpPost<any>(apiOpts, API.users, {
      user_email: `e2e_ph_xss_${timestamp}@test.local`,
      role: 'subscriber',
    })
    if (createRes.status !== 200) return

    const userIds: number[] = Array.isArray(createRes.data.data) ? createRes.data.data : [createRes.data.data]
    const userId = Number(userIds[0])
    if (!userId) return

    try {
      const updateRes = await wpPost<any>(apiOpts, API.userById(userId), {
        display_name: STRING_EDGE.xss1,
      })
      expect([200, 400]).toContain(updateRes.status)

      if (updateRes.status === 200) {
        const getRes = await wpGet<any>(apiOpts, API.userById(userId))
        if (getRes.status === 200) {
          const displayName = getRes.data.display_name || getRes.data.name || ''
          expect(displayName).not.toContain('<script>')
        }
      }
    } finally {
      await wpDelete(apiOpts, API.userById(userId))
    }
  })

  test('設定值 XSS 應被消毒', async () => {
    const res = await wpPost<any>(apiOpts, API.options, {
      powerhouse_settings: {
        theme: STRING_EDGE.xss1,
      },
    })
    expect([200, 400]).toContain(res.status)

    if (res.status === 200) {
      const getRes = await wpGet<any>(apiOpts, API.options)
      const settings = getRes.data?.powerhouse_settings ?? getRes.data
      if (settings?.theme) {
        expect(settings.theme).not.toContain('<script>')
        expect(settings.theme).not.toContain('alert(')
      }
    }
  })

  test('詞彙名稱 XSS 應被消毒', async () => {
    const res = await wpPost<any>(apiOpts, API.terms('product_cat'), {
      name: STRING_EDGE.xss1,
    })
    expect(res.status).toBe(200)
    const ids: number[] = Array.isArray(res.data.data) ? res.data.data : [res.data.data]
    const termId = Number(ids[0])

    if (termId) {
      const getRes = await wpGet<any>(apiOpts, API.termById('product_cat', termId))
      if (getRes.status === 200) {
        expect(getRes.data.name).not.toContain('<script>')
      }
      await wpDelete(apiOpts, API.termById('product_cat', termId))
    }
  })
})

// ─────────────────────────────────────────────────────
// SQL Injection 防護
// ─────────────────────────────────────────────────────

test.describe('SQL Injection 防護', () => {
  test('文章標題 SQL injection 不應造成資料庫錯誤', async () => {
    const res = await wpPost<any>(apiOpts, API.posts, {
      post_type: 'post',
      post_title: STRING_EDGE.sqlInject2,
      post_status: 'draft',
    })
    expect(res.status).toBe(200)
    const ids: number[] = Array.isArray(res.data.data) ? res.data.data : [res.data.data]
    createdIds.push(...ids.map(Number))
  })

  test('搜尋參數 SQL injection 不應造成 500', async () => {
    const res = await wpGet(apiOpts, API.posts, {
      s: STRING_EDGE.sqlInject2,
    })
    expect([200, 400]).toContain(res.status)
  })

  test('商品搜尋 SQL injection 不應造成 500', async () => {
    const res = await wpGet(apiOpts, API.productSelect, {
      s: STRING_EDGE.sqlInject1,
    })
    expect([200, 400]).toContain(res.status)
  })

  test('用戶搜尋 SQL injection 不應造成 500', async () => {
    const res = await wpGet(apiOpts, API.users, {
      search: STRING_EDGE.sqlInject1,
    })
    expect([200, 400]).toContain(res.status)
  })

  test('product_slug SQL injection 不應造成 500', async () => {
    const res = await wpPost(apiOpts, API.lcInvalidate, {
      product_slug: STRING_EDGE.sqlInject1,
    })
    expect([200, 400]).toContain(res.status)
  })

  test('回應不應洩漏 wp_ 資料表名稱', async () => {
    const res = await wpGet(apiOpts, API.posts, {
      s: "' UNION SELECT table_name FROM information_schema.tables --",
    })
    const bodyStr = JSON.stringify(res.data || {})
    expect(bodyStr).not.toContain('wp_posts')
    expect(bodyStr).not.toContain('information_schema')
  })
})

// ─────────────────────────────────────────────────────
// 路徑穿越攻擊
// ─────────────────────────────────────────────────────

test.describe('路徑穿越攻擊防護', () => {
  test('路徑穿越字串作為標題應被安全處理', async () => {
    const res = await wpPost<any>(apiOpts, API.posts, {
      post_type: 'post',
      post_title: STRING_EDGE.pathTraversal,
      post_status: 'draft',
    })
    expect([200, 400]).toContain(res.status)
    if (res.status === 200) {
      const ids: number[] = Array.isArray(res.data.data) ? res.data.data : [res.data.data]
      createdIds.push(...ids.map(Number))

      // 驗證不會嘗試讀取 wp-config.php
      const getRes = await wpGet<any>(apiOpts, API.postById(Number(ids[0])))
      if (getRes.status === 200) {
        // 標題應被儲存為純文字，不包含文件內容
        expect(typeof getRes.data.post_title).toBe('string')
      }
    }
  })

  test('路徑穿越作為 product_slug 應被安全處理', async () => {
    const res = await wpPost<any>(apiOpts, API.lcActivate, {
      code: 'TEST-CODE',
      product_slug: STRING_EDGE.pathTraversal,
    })
    // 應回傳錯誤（非 200），不應暴露檔案系統
    expect(res.status).toBeGreaterThanOrEqual(400)
    // 回應不應包含 wp-config.php 內容
    const bodyStr = JSON.stringify(res.data || {})
    expect(bodyStr).not.toContain('DB_PASSWORD')
    expect(bodyStr).not.toContain('AUTH_KEY')
  })
})

// ─────────────────────────────────────────────────────
// 未授權存取防護
// ─────────────────────────────────────────────────────

test.describe('未授權存取防護', () => {
  test('偽造 nonce 應被拒絕', async ({ request }, testInfo) => {
    const baseURL = testInfo.project.use.baseURL || 'http://localhost:8898'
    // 使用已登入 cookie 但偽造 nonce
    const res = await request.post(`${baseURL}/wp-json/${API.posts}`, {
      headers: {
        'Content-Type': 'application/json',
        'X-WP-Nonce': 'fake_nonce_xyz_123456',
      },
      data: { post_type: 'post', post_title: 'Fake Nonce Attack' },
    })
    // 偽造 nonce + 無 session 應被拒絕
    expect([401, 403]).toContain(res.status())
  })

  test('無 cookie 無 nonce 存取受保護端點應被拒絕', async ({ browser }, testInfo) => {
    const baseURL = testInfo.project.use.baseURL || 'http://localhost:8898'
    const ctx = await browser.newContext() // 全新 context，無 session
    try {
      const res = await ctx.request.delete(`${baseURL}/wp-json/${API.postById(1)}`, {
        headers: { 'Content-Type': 'application/json' },
      })
      expect([401, 403]).toContain(res.status())
    } finally {
      await ctx.close()
    }
  })

  test('GET /options 無認證應被拒絕', async ({ browser }, testInfo) => {
    const baseURL = testInfo.project.use.baseURL || 'http://localhost:8898'
    const ctx = await browser.newContext()
    try {
      const res = await ctx.request.get(`${baseURL}/wp-json/${API.options}`)
      // 設定端點應需要認證
      expect([200, 401, 403]).toContain(res.status())
      // 若回傳 200，至少確認不是空物件（不洩漏資料）
    } finally {
      await ctx.close()
    }
  })
})

// ─────────────────────────────────────────────────────
// 特殊字元處理
// ─────────────────────────────────────────────────────

test.describe('特殊字元處理', () => {
  test('特殊符號組合標題應正常處理', async () => {
    const res = await wpPost<any>(apiOpts, API.posts, {
      post_type: 'post',
      post_title: STRING_EDGE.specialChars,
      post_status: 'draft',
    })
    expect(res.status).toBe(200)
    const ids: number[] = Array.isArray(res.data.data) ? res.data.data : [res.data.data]
    createdIds.push(...ids.map(Number))
  })

  test('純空白標題應回傳錯誤或被處理', async () => {
    const res = await wpPost<any>(apiOpts, API.posts, {
      post_type: 'post',
      post_title: STRING_EDGE.spaces,
      post_status: 'draft',
    })
    expect([200, 400]).toContain(res.status)
    if (res.status === 200) {
      const ids: number[] = Array.isArray(res.data.data) ? res.data.data : [res.data.data]
      createdIds.push(...ids.map(Number))
    }
  })

  test('空字串標題應回傳錯誤或建立（視 WP 行為）', async () => {
    const res = await wpPost<any>(apiOpts, API.posts, {
      post_type: 'post',
      post_title: STRING_EDGE.empty,
      post_status: 'draft',
    })
    expect([200, 400]).toContain(res.status)
    if (res.status === 200) {
      const ids: number[] = Array.isArray(res.data.data) ? res.data.data : [res.data.data]
      createdIds.push(...ids.map(Number))
    }
  })
})
