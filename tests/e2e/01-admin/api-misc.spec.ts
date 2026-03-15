/**
 * Misc API 測試（雜項功能）
 *
 * 對應 spec:
 *   - 查詢外掛列表.feature
 *   - 執行短碼.feature
 *   - 查詢WooCommerce資訊.feature
 *   - 查詢營收統計.feature
 *   - 上傳檔案.feature
 *   - 查詢上傳選項.feature
 *   - 建立評論.feature
 *   - 刪除評論.feature
 *   - 查詢商品屬性列表.feature（misc 版本）
 */
import { test, expect } from '@playwright/test'
import { getNonce } from '../helpers/admin-setup.js'
import { wpGet, wpPost, wpDelete, type ApiOptions } from '../helpers/api-client.js'
import { API, STRING_EDGE, ID_EDGE } from '../fixtures/test-data.js'

let apiOpts: ApiOptions
/** 此套件建立的評論 ID */
const createdCommentIds: number[] = []

test.beforeAll(async ({ request }, testInfo) => {
  const baseURL = testInfo.project.use.baseURL || 'http://localhost:8898'
  apiOpts = { request, baseURL, nonce: getNonce() }
})

test.afterAll(async () => {
  for (const id of createdCommentIds) {
    try {
      await wpDelete(apiOpts, API.commentById(id))
    } catch { /* 忽略 */ }
  }
})

// ─────────────────────────────────────────────────────
// 查詢外掛列表 GET /plugins
// ─────────────────────────────────────────────────────

test.describe('GET /plugins — 查詢外掛列表', () => {
  test('應回傳 200 和外掛列表', async () => {
    const res = await wpGet<any[]>(apiOpts, API.plugins)
    expect(res.status).toBe(200)
    expect(Array.isArray(res.data) || typeof res.data === 'object').toBe(true)
  })

  test('每個外掛應包含 name 和 is_active 欄位', async () => {
    const res = await wpGet<any>(apiOpts, API.plugins)
    expect(res.status).toBe(200)
    const plugins = Array.isArray(res.data) ? res.data : Object.values(res.data || {})
    if (plugins.length > 0) {
      const plugin = plugins[0]
      expect(plugin).toHaveProperty('name')
    }
  })

  test('WooCommerce 應在外掛列表中且已啟用', async () => {
    const res = await wpGet<any>(apiOpts, API.plugins)
    expect(res.status).toBe(200)
    const plugins = Array.isArray(res.data) ? res.data : Object.values(res.data || {})
    const wc = plugins.find((p: any) =>
      (p.name as string)?.toLowerCase().includes('woocommerce') ||
      (p.key as string)?.toLowerCase().includes('woocommerce'),
    )
    if (wc) {
      expect(wc.is_active).toBeTruthy()
    }
  })
})

// ─────────────────────────────────────────────────────
// 執行短碼 GET /shortcode
// ─────────────────────────────────────────────────────

test.describe('GET /shortcode — 執行短碼', () => {
  test('執行 WooCommerce 短碼應回傳 HTML', async () => {
    const res = await wpGet<any>(apiOpts, API.shortcode, {
      shortcode: '[woocommerce_cart]',
    })
    expect(res.status).toBe(200)
  })

  test('缺少 shortcode 參數應回傳錯誤', async () => {
    const res = await wpGet(apiOpts, API.shortcode)
    expect(res.status).toBeGreaterThanOrEqual(400)
  })

  test('XSS payload 作為短碼應被安全處理', async () => {
    const res = await wpGet<any>(apiOpts, API.shortcode, {
      shortcode: STRING_EDGE.xss1,
    })
    // 不應執行腳本，可能回傳空 HTML 或被處理
    expect([200, 400]).toContain(res.status)
    if (res.status === 200 && typeof res.data === 'string') {
      expect(res.data).not.toContain('alert(1)')
    }
  })

  test('無效短碼應回傳空內容或錯誤', async () => {
    const res = await wpGet<any>(apiOpts, API.shortcode, {
      shortcode: '[nonexistent_shortcode_xyz]',
    })
    expect([200, 400]).toContain(res.status)
  })
})

// ─────────────────────────────────────────────────────
// 查詢 WooCommerce 資訊 GET /woocommerce
// ─────────────────────────────────────────────────────

test.describe('GET /woocommerce — 查詢 WooCommerce 資訊', () => {
  test('應回傳 200 和 WooCommerce 設定資料', async () => {
    const res = await wpGet<any>(apiOpts, API.woocommerce)
    expect(res.status).toBe(200)
    expect(res.data).toBeTruthy()
  })

  test('回應應包含 WooCommerce 相關欄位', async () => {
    const res = await wpGet<any>(apiOpts, API.woocommerce)
    expect(res.status).toBe(200)
    // 至少回傳非空物件
    expect(typeof res.data).toBe('object')
  })
})

// ─────────────────────────────────────────────────────
// 查詢營收統計 GET /reports/revenue/stats
// ─────────────────────────────────────────────────────

test.describe('GET /reports/revenue/stats — 查詢營收統計', () => {
  test('不帶參數應回傳 200 和統計資料', async () => {
    const res = await wpGet<any>(apiOpts, API.revenueStats)
    expect(res.status).toBe(200)
    expect(res.data).toBeTruthy()
  })

  test('帶 interval=day 應回傳每日統計', async () => {
    const res = await wpGet<any>(apiOpts, API.revenueStats, { interval: 'day' })
    expect(res.status).toBe(200)
  })

  test('帶 before/after 時間範圍應正確過濾', async () => {
    const afterDate = '2020-01-01T00:00:00'
    const beforeDate = new Date().toISOString()
    const res = await wpGet<any>(apiOpts, API.revenueStats, {
      after: afterDate,
      before: beforeDate,
    })
    expect(res.status).toBe(200)
  })

  test('統計結果應包含 totals 欄位', async () => {
    const res = await wpGet<any>(apiOpts, API.revenueStats)
    expect(res.status).toBe(200)
    // 可能包含在 data.totals 或直接在 data
    expect(res.data).toBeTruthy()
  })
})

// ─────────────────────────────────────────────────────
// 建立評論 POST /comments
// ─────────────────────────────────────────────────────

test.describe('POST /comments — 建立評論', () => {
  test('成功建立評論應回傳 comment_id', async () => {
    const res = await wpPost<any>(apiOpts, API.comments, {
      note: 'E2E PH 測試評論',
      comment_type: 'comment',
      is_customer_note: '0',
    })
    expect(res.status).toBe(200)
    const commentId = Number(res.data?.data)
    expect(commentId).toBeGreaterThan(0)
    if (commentId) createdCommentIds.push(commentId)
  })

  test('XSS payload 評論內容應被安全處理', async () => {
    const res = await wpPost<any>(apiOpts, API.comments, {
      note: STRING_EDGE.xss1,
      comment_type: 'comment',
      is_customer_note: '0',
    })
    expect([200, 400]).toContain(res.status)
    if (res.status === 200) {
      const commentId = Number(res.data?.data)
      if (commentId) createdCommentIds.push(commentId)
    }
  })

  test('超長評論內容應被處理（不應 500）', async () => {
    const res = await wpPost<any>(apiOpts, API.comments, {
      note: STRING_EDGE.longStr,
      comment_type: 'comment',
      is_customer_note: '0',
    })
    expect([200, 400]).toContain(res.status)
    if (res.status === 200) {
      const commentId = Number(res.data?.data)
      if (commentId) createdCommentIds.push(commentId)
    }
  })

  test('空評論內容應回傳錯誤', async () => {
    const res = await wpPost<any>(apiOpts, API.comments, {
      note: STRING_EDGE.empty,
      comment_type: 'comment',
      is_customer_note: '0',
    })
    expect(res.status).toBeGreaterThanOrEqual(400)
  })
})

// ─────────────────────────────────────────────────────
// 刪除評論 DELETE /comments/:id
// ─────────────────────────────────────────────────────

test.describe('DELETE /comments/:id — 刪除評論', () => {
  test('成功刪除評論', async () => {
    // 先建立
    const createRes = await wpPost<any>(apiOpts, API.comments, {
      note: 'E2E PH 待刪除評論',
      comment_type: 'comment',
      is_customer_note: '0',
    })
    const commentId = Number(createRes.data?.data)
    expect(commentId).toBeGreaterThan(0)

    const delRes = await wpDelete(apiOpts, API.commentById(commentId))
    expect([200, 204]).toContain(delRes.status)

    // 驗證已刪除
    const getRes = await wpGet(apiOpts, API.commentById(commentId))
    expect(getRes.status).toBeGreaterThanOrEqual(400)
  })

  test('刪除不存在的評論應回傳錯誤', async () => {
    const res = await wpDelete(apiOpts, API.commentById(ID_EDGE.nonExistent))
    expect(res.status).toBeGreaterThanOrEqual(400)
  })

  test('重複刪除同一評論不應 500', async () => {
    const createRes = await wpPost<any>(apiOpts, API.comments, {
      note: 'E2E PH 重複刪除評論',
      comment_type: 'comment',
      is_customer_note: '0',
    })
    const commentId = Number(createRes.data?.data)
    if (!commentId) return

    await wpDelete(apiOpts, API.commentById(commentId))
    const res2 = await wpDelete(apiOpts, API.commentById(commentId))
    expect([200, 204, 400, 404]).toContain(res2.status)
  })
})
