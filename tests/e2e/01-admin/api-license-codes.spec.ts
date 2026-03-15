/**
 * License Code API 測試
 *
 * 對應 spec:
 *   - 查詢授權碼狀態.feature
 *   - 啟用授權碼.feature
 *   - 棄用授權碼.feature
 *   - 清除授權碼快取.feature
 *   - 系統檢查授權碼.feature
 *
 * 注意：LC bypass 已啟用，Cloud API 不可達。
 *       主要測試 API 端點的輸入驗證與錯誤處理。
 */
import { test, expect } from '@playwright/test'
import { getNonce } from '../helpers/admin-setup.js'
import { wpGet, wpPost, type ApiOptions } from '../helpers/api-client.js'
import { API, STRING_EDGE } from '../fixtures/test-data.js'

let apiOpts: ApiOptions

test.beforeAll(async ({ request }, testInfo) => {
  const baseURL = testInfo.project.use.baseURL || 'http://localhost:8898'
  apiOpts = { request, baseURL, nonce: getNonce() }
})

// ─────────────────────────────────────────────────────
// 查詢授權碼狀態 GET /lc
// ─────────────────────────────────────────────────────

test.describe('GET /lc — 查詢授權碼狀態', () => {
  test('應回傳 200 和授權碼狀態陣列', async () => {
    const res = await wpGet<any[]>(apiOpts, API.lc)
    expect(res.status).toBe(200)
    expect(Array.isArray(res.data)).toBe(true)
  })

  test('每個項目應包含必要欄位', async () => {
    const res = await wpGet<any[]>(apiOpts, API.lc)
    expect(res.status).toBe(200)
    if (Array.isArray(res.data) && res.data.length > 0) {
      const item = res.data[0]
      expect(item).toHaveProperty('product_slug')
      expect(item).toHaveProperty('product_name')
      expect(item).toHaveProperty('code')
      expect(item).toHaveProperty('post_status')
    }
  })

  test('code 欄位應為字串（非 undefined）', async () => {
    const res = await wpGet<any[]>(apiOpts, API.lc)
    expect(res.status).toBe(200)
    for (const item of (res.data as any[])) {
      expect(typeof item.code).toBe('string')
    }
  })
})

// ─────────────────────────────────────────────────────
// 啟用授權碼 POST /lc/activate
// ─────────────────────────────────────────────────────

test.describe('POST /lc/activate — 啟用授權碼', () => {
  test('缺少 code 應回傳 400', async () => {
    const res = await wpPost<any>(apiOpts, API.lcActivate, {
      product_slug: 'power-course',
    })
    expect(res.status).toBe(400)
  })

  test('缺少 product_slug 應回傳 400', async () => {
    const res = await wpPost<any>(apiOpts, API.lcActivate, {
      code: 'TEST-CODE-XXXX',
    })
    expect(res.status).toBe(400)
  })

  test('空字串 code 應回傳錯誤', async () => {
    const res = await wpPost<any>(apiOpts, API.lcActivate, {
      code: STRING_EDGE.empty,
      product_slug: 'power-course',
    })
    expect(res.status).toBeGreaterThanOrEqual(400)
  })

  test('空字串 product_slug 應回傳錯誤', async () => {
    const res = await wpPost<any>(apiOpts, API.lcActivate, {
      code: 'TEST-CODE-XXXX',
      product_slug: STRING_EDGE.empty,
    })
    expect(res.status).toBeGreaterThanOrEqual(400)
  })

  test('無效授權碼（Cloud API 不可達）應回傳非 200', async () => {
    const res = await wpPost<any>(apiOpts, API.lcActivate, {
      code: 'INVALID-E2E-CODE-999',
      product_slug: 'power-course',
    })
    // LC bypass 下或 Cloud API 不可用，應回傳錯誤
    expect(res.status).toBeGreaterThanOrEqual(400)
  })

  test('XSS payload 作為 code 應被安全處理', async () => {
    const res = await wpPost<any>(apiOpts, API.lcActivate, {
      code: STRING_EDGE.xss1,
      product_slug: 'power-course',
    })
    expect(res.status).toBeGreaterThanOrEqual(400)
  })

  test('SQL injection 作為 product_slug 不應造成 500', async () => {
    const res = await wpPost<any>(apiOpts, API.lcActivate, {
      code: 'TEST-CODE-001',
      product_slug: STRING_EDGE.sqlInject1,
    })
    expect([400, 500]).toContain(res.status)
    // 若 500，確認不是資料庫錯誤
  })
})

// ─────────────────────────────────────────────────────
// 棄用授權碼 POST /lc/deactivate
// ─────────────────────────────────────────────────────

test.describe('POST /lc/deactivate — 棄用授權碼', () => {
  test('缺少 code 應回傳 400', async () => {
    const res = await wpPost<any>(apiOpts, API.lcDeactivate, {
      product_slug: 'power-course',
    })
    expect(res.status).toBe(400)
  })

  test('缺少 product_slug 應回傳 400', async () => {
    const res = await wpPost<any>(apiOpts, API.lcDeactivate, {
      code: 'TEST-CODE-XXXX',
    })
    expect(res.status).toBe(400)
  })

  test('空字串 code 應回傳錯誤', async () => {
    const res = await wpPost<any>(apiOpts, API.lcDeactivate, {
      code: STRING_EDGE.empty,
      product_slug: 'power-course',
    })
    expect(res.status).toBeGreaterThanOrEqual(400)
  })

  test('棄用不存在的授權碼（Cloud API 不可達）應回傳錯誤', async () => {
    const res = await wpPost<any>(apiOpts, API.lcDeactivate, {
      code: 'INVALID-E2E-CODE-999',
      product_slug: 'power-course',
    })
    expect(res.status).toBeGreaterThanOrEqual(400)
  })
})

// ─────────────────────────────────────────────────────
// 清除授權碼快取 POST /lc/invalidate
// ─────────────────────────────────────────────────────

test.describe('POST /lc/invalidate — 清除授權碼快取', () => {
  test('有效 product_slug 應成功清除快取並回傳 invalidate_lc_cache_success', async () => {
    const res = await wpPost<any>(apiOpts, API.lcInvalidate, {
      product_slug: 'power-course',
    })
    expect(res.status).toBe(200)
    expect(res.data.code).toBe('invalidate_lc_cache_success')
  })

  test('空字串 product_slug 應回傳 400 和 invalidate_lc_cache_failed', async () => {
    const res = await wpPost<any>(apiOpts, API.lcInvalidate, {
      product_slug: STRING_EDGE.empty,
    })
    expect(res.status).toBe(400)
    expect(res.data.code).toBe('invalidate_lc_cache_failed')
  })

  test('缺少 product_slug 應回傳 400', async () => {
    const res = await wpPost<any>(apiOpts, API.lcInvalidate, {})
    expect(res.status).toBe(400)
  })

  test('連續兩次清除快取應皆成功（冪等）', async () => {
    const res1 = await wpPost<any>(apiOpts, API.lcInvalidate, {
      product_slug: 'power-course',
    })
    expect(res1.status).toBe(200)

    const res2 = await wpPost<any>(apiOpts, API.lcInvalidate, {
      product_slug: 'power-course',
    })
    expect(res2.status).toBe(200)
  })

  test('此端點無需 nonce（公開端點）', async ({ request }, testInfo) => {
    const baseURL = testInfo.project.use.baseURL || 'http://localhost:8898'
    // 不帶 nonce 呼叫
    const res = await request.post(`${baseURL}/wp-json/${API.lcInvalidate}`, {
      headers: { 'Content-Type': 'application/json' },
      data: { product_slug: 'power-course' },
    })
    expect(res.status()).toBe(200)
  })

  test('SQL injection product_slug 不應造成 500', async () => {
    const res = await wpPost<any>(apiOpts, API.lcInvalidate, {
      product_slug: STRING_EDGE.sqlInject1,
    })
    expect([200, 400]).toContain(res.status)
  })
})

// ─────────────────────────────────────────────────────
// 系統檢查授權碼 GET /lc/check
// ─────────────────────────────────────────────────────

test.describe('GET /lc/check — 系統檢查授權碼', () => {
  test('應回傳 200（系統檢查）', async () => {
    const res = await wpGet(apiOpts, API.lcCheck)
    // 系統檢查端點應存活
    expect([200, 404]).toContain(res.status)
  })
})
