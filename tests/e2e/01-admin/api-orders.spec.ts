/**
 * Orders CRUD API 測試
 *
 * 對應 spec:
 *   - 查詢訂單列表.feature
 *   - 查詢單一訂單.feature
 *   - 建立訂單.feature
 *   - 更新訂單.feature
 *   - 刪除訂單.feature
 *   - 查詢訂單選項.feature
 *   - 建立訂單備註.feature
 *   - 刪除訂單備註.feature
 */
import { test, expect } from '@playwright/test'
import { getNonce } from '../helpers/admin-setup.js'
import { wpGet, wpPost, wpDelete, type ApiOptions } from '../helpers/api-client.js'
import { API, WC_API, STRING_EDGE, ID_EDGE } from '../fixtures/test-data.js'
import fs from 'fs'
import path from 'path'

let apiOpts: ApiOptions
let setupOrderId: number | null = null
/** 此套件建立的訂單 ID */
const createdOrderIds: number[] = []

test.beforeAll(async ({ request }, testInfo) => {
  const baseURL = testInfo.project.use.baseURL || 'http://localhost:8898'
  apiOpts = { request, baseURL, nonce: getNonce() }

  const idsFile = path.resolve(import.meta.dirname, '../.auth/setup-ids.json')
  if (fs.existsSync(idsFile)) {
    const ids = JSON.parse(fs.readFileSync(idsFile, 'utf-8'))
    setupOrderId = ids.orderId ?? null
  }
})

test.afterAll(async () => {
  for (const id of createdOrderIds) {
    try {
      await apiOpts.request.delete(
        `${apiOpts.baseURL}/wp-json/wc/v3/orders/${id}?force=true`,
        { headers: { 'X-WP-Nonce': apiOpts.nonce } },
      )
    } catch { /* 忽略清理失敗 */ }
  }
})

// ─────────────────────────────────────────────────────
// 查詢訂單列表 GET /orders
// ─────────────────────────────────────────────────────

test.describe('GET /orders — 查詢訂單列表', () => {
  test('不帶參數應使用預設值回傳訂單列表', async () => {
    const res = await wpGet<any[]>(apiOpts, API.orders)
    expect(res.status).toBe(200)
    expect(Array.isArray(res.data)).toBe(true)
  })

  test('應包含分頁 headers', async () => {
    const res = await wpGet(apiOpts, API.orders, { limit: '1', paged: '1' })
    expect(res.status).toBe(200)
    expect(Number(res.headers['x-wp-total'])).toBeGreaterThanOrEqual(0)
  })

  test('limit=1 應限制結果數量', async () => {
    const res = await wpGet<any[]>(apiOpts, API.orders, { limit: '1' })
    expect(res.status).toBe(200)
    expect((res.data as any[]).length).toBeLessThanOrEqual(1)
  })

  test('帶 status=processing 應過濾訂單狀態', async () => {
    const res = await wpGet<any[]>(apiOpts, API.orders, { status: 'processing' })
    expect(res.status).toBe(200)
    if (Array.isArray(res.data) && res.data.length > 0) {
      for (const order of res.data) {
        expect(order.status).toBe('processing')
      }
    }
  })
})

// ─────────────────────────────────────────────────────
// 查詢單一訂單 GET /orders/:id
// ─────────────────────────────────────────────────────

test.describe('GET /orders/:id — 查詢單一訂單', () => {
  test('查詢存在的訂單應回傳完整資料', async () => {
    test.skip(!setupOrderId, '沒有可用的測試訂單')
    const res = await wpGet<any>(apiOpts, API.orderById(setupOrderId!))
    expect(res.status).toBe(200)
    expect(res.data.id).toBe(setupOrderId)
    expect(res.data).toHaveProperty('status')
    expect(res.data).toHaveProperty('line_items')
  })

  test('查詢不存在的訂單（999999）應回傳錯誤', async () => {
    const res = await wpGet(apiOpts, API.orderById(ID_EDGE.nonExistent))
    expect([400, 404]).toContain(res.status)
  })

  test('查詢 ID=-1 應回傳錯誤', async () => {
    const res = await wpGet(apiOpts, API.orderById(ID_EDGE.negOne))
    expect(res.status).toBeGreaterThanOrEqual(400)
  })
})

// ─────────────────────────────────────────────────────
// 查詢訂單選項 GET /orders/options
// ─────────────────────────────────────────────────────

test.describe('GET /orders/options — 查詢訂單選項', () => {
  test('應回傳訂單狀態列表', async () => {
    const res = await wpGet<any>(apiOpts, API.orderOptions)
    expect(res.status).toBe(200)
    expect(res.data).toBeTruthy()
    // 應包含 statuses 或類似欄位
  })
})

// ─────────────────────────────────────────────────────
// 建立訂單 POST /orders
// ─────────────────────────────────────────────────────

test.describe('POST /orders — 建立訂單', () => {
  test('建立空白訂單應成功', async () => {
    const res = await wpPost<any>(apiOpts, API.orders, {})
    expect(res.status).toBe(200)
    expect(res.data.code).toBe('create_success')
    const id = Number(Array.isArray(res.data.data) ? res.data.data[0] : res.data.data)
    expect(id).toBeGreaterThan(0)
    createdOrderIds.push(id)
  })

  test('建立含狀態的訂單', async () => {
    const res = await wpPost<any>(apiOpts, API.orders, {
      status: 'pending',
    })
    expect(res.status).toBe(200)
    const id = Number(Array.isArray(res.data.data) ? res.data.data[0] : res.data.data)
    if (id) createdOrderIds.push(id)
  })
})

// ─────────────────────────────────────────────────────
// 更新訂單 POST /orders/:id
// ─────────────────────────────────────────────────────

test.describe('POST /orders/:id — 更新訂單', () => {
  test('成功更新訂單狀態', async () => {
    test.skip(!setupOrderId, '沒有可用的測試訂單')
    const res = await wpPost(apiOpts, API.orderById(setupOrderId!), {
      status: 'on-hold',
    })
    expect(res.status).toBe(200)
  })

  test('更新不存在的訂單應回傳錯誤', async () => {
    const res = await wpPost(apiOpts, API.orderById(ID_EDGE.nonExistent), {
      status: 'processing',
    })
    expect(res.status).toBeGreaterThanOrEqual(400)
  })

  test('更新訂單 meta_data', async () => {
    test.skip(!setupOrderId, '沒有可用的測試訂單')
    const res = await wpPost(apiOpts, API.orderById(setupOrderId!), {
      meta_data: [{ key: '_e2e_test_meta', value: 'test_value' }],
    })
    expect(res.status).toBe(200)
  })
})

// ─────────────────────────────────────────────────────
// 刪除訂單 DELETE /orders
// ─────────────────────────────────────────────────────

test.describe('DELETE /orders/:id — 刪除訂單', () => {
  test('刪除訂單應成功', async () => {
    // 建立待刪除訂單
    const createRes = await wpPost<any>(apiOpts, API.orders, {})
    const id = Number(Array.isArray(createRes.data.data) ? createRes.data.data[0] : createRes.data.data)
    expect(id).toBeGreaterThan(0)

    const delRes = await wpDelete(apiOpts, API.orderById(id))
    expect([200, 204]).toContain(delRes.status)
  })

  test('刪除不存在的訂單應回傳錯誤', async () => {
    const res = await wpDelete(apiOpts, API.orderById(ID_EDGE.nonExistent))
    expect(res.status).toBeGreaterThanOrEqual(400)
  })

  test('重複刪除同一訂單不應 500', async () => {
    const createRes = await wpPost<any>(apiOpts, API.orders, {})
    const id = Number(Array.isArray(createRes.data.data) ? createRes.data.data[0] : createRes.data.data)
    if (!id) return

    await wpDelete(apiOpts, API.orderById(id))
    const res2 = await wpDelete(apiOpts, API.orderById(id))
    expect([200, 204, 400, 404]).toContain(res2.status)
  })
})

// ─────────────────────────────────────────────────────
// 訂單備註 POST /order-notes 及 DELETE /order-notes/:id
// ─────────────────────────────────────────────────────

test.describe('訂單備註 CRUD', () => {
  test('建立訂單備註應成功', async () => {
    test.skip(!setupOrderId, '沒有可用的測試訂單')
    const res = await wpPost<any>(apiOpts, API.orderNotes, {
      order_id: setupOrderId,
      note: 'E2E PH 測試備註',
      is_customer_note: '0',
    })
    expect(res.status).toBe(200)
    // 應回傳備註 ID
    const noteId = Number(res.data?.data)
    if (noteId) {
      // 清理備註
      await wpDelete(apiOpts, API.orderNoteById(noteId))
    }
  })

  test('訂單備註 XSS payload 應被安全處理', async () => {
    test.skip(!setupOrderId, '沒有可用的測試訂單')
    const res = await wpPost<any>(apiOpts, API.orderNotes, {
      order_id: setupOrderId,
      note: STRING_EDGE.xss1,
      is_customer_note: '0',
    })
    expect([200, 400]).toContain(res.status)
    if (res.status === 200) {
      const noteId = Number(res.data?.data)
      if (noteId) {
        await wpDelete(apiOpts, API.orderNoteById(noteId))
      }
    }
  })

  test('缺少 order_id 應回傳錯誤', async () => {
    const res = await wpPost(apiOpts, API.orderNotes, {
      note: '無 order_id 的備註',
      is_customer_note: '0',
    })
    expect(res.status).toBeGreaterThanOrEqual(400)
  })

  test('刪除不存在的備註應回傳錯誤', async () => {
    const res = await wpDelete(apiOpts, API.orderNoteById(ID_EDGE.nonExistent))
    expect(res.status).toBeGreaterThanOrEqual(400)
  })
})
