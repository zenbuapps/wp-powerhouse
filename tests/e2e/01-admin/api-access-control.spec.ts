/**
 * Access Control (Limit) API 測試
 *
 * 對應 spec:
 *   - 授權用戶存取.feature
 *   - 更新用戶存取期限.feature
 *   - 撤銷用戶存取.feature
 *
 * 測試 ph_access_itemmeta 表的寫入操作。
 */
import { test, expect } from '@playwright/test'
import { getNonce } from '../helpers/admin-setup.js'
import { wpGet, wpPost, type ApiOptions } from '../helpers/api-client.js'
import { API, ID_EDGE, NUMERIC_EDGE } from '../fixtures/test-data.js'
import fs from 'fs'
import path from 'path'

let apiOpts: ApiOptions
let subscriberUserId: number | null = null
let testItemId: number | null = null
/** 此套件建立的臨時 post ID（作為 item） */
const createdTempPostIds: number[] = []

test.beforeAll(async ({ request }, testInfo) => {
  const baseURL = testInfo.project.use.baseURL || 'http://localhost:8898'
  apiOpts = { request, baseURL, nonce: getNonce() }

  // 讀取 setup 建立的 ID
  const idsFile = path.resolve(import.meta.dirname, '../.auth/setup-ids.json')
  if (fs.existsSync(idsFile)) {
    const ids = JSON.parse(fs.readFileSync(idsFile, 'utf-8'))
    subscriberUserId = ids.subscriberUserId ?? null
    testItemId = ids.postIds?.[0] ?? null
  }

  // 若沒有 testItemId，建立一個
  if (!testItemId) {
    const createRes = await wpPost<any>(apiOpts, API.posts, {
      post_type: 'post',
      post_title: 'E2E PH Access Control 測試項目',
      post_status: 'publish',
    })
    const ids: number[] = Array.isArray(createRes.data?.data) ? createRes.data.data : [createRes.data?.data]
    testItemId = Number(ids[0]) || null
    if (testItemId) createdTempPostIds.push(testItemId)
  }
})

test.afterAll(async () => {
  // 清理授權記錄（若有）
  if (subscriberUserId && testItemId) {
    try {
      await wpPost(apiOpts, API.limitRevokeUsers, {
        user_ids: [subscriberUserId],
        item_ids: [testItemId],
      })
    } catch { /* 忽略清理失敗 */ }
  }

  // 清理暫時文章
  for (const id of createdTempPostIds) {
    try {
      await apiOpts.request.delete(
        `${apiOpts.baseURL}/wp-json/wp/v2/posts/${id}?force=true`,
        { headers: { 'X-WP-Nonce': apiOpts.nonce } },
      )
    } catch { /* 忽略 */ }
  }
})

// ─────────────────────────────────────────────────────
// 授權用戶存取 POST /limit/grant-users
// ─────────────────────────────────────────────────────

test.describe('POST /limit/grant-users — 授權用戶存取', () => {
  test('成功授權用戶存取項目（無期限 expire_date=0）', async () => {
    test.skip(!subscriberUserId || !testItemId, '缺少測試用戶或項目')
    const res = await wpPost<any>(apiOpts, API.limitGrantUsers, {
      user_ids: [subscriberUserId],
      item_ids: [testItemId],
      expire_date: '0',
    })
    expect(res.status).toBe(200)
    expect(res.data.code).toBe('grant_users_success')
  })

  test('成功授權用戶存取項目（指定未來到期日）', async () => {
    test.skip(!subscriberUserId || !testItemId, '缺少測試用戶或項目')
    const futureTimestamp = Math.floor(Date.now() / 1000) + 86400 * 365 // 一年後
    const res = await wpPost<any>(apiOpts, API.limitGrantUsers, {
      user_ids: [subscriberUserId],
      item_ids: [testItemId],
      expire_date: String(futureTimestamp),
    })
    expect(res.status).toBe(200)
    expect(res.data.code).toBe('grant_users_success')
  })

  test('訂閱型授權使用 subscription_{id}', async () => {
    test.skip(!subscriberUserId || !testItemId, '缺少測試用戶或項目')
    const res = await wpPost<any>(apiOpts, API.limitGrantUsers, {
      user_ids: [subscriberUserId],
      item_ids: [testItemId],
      expire_date: 'subscription_123',
    })
    expect(res.status).toBe(200)
    expect(res.data.code).toBe('grant_users_success')
  })

  test('缺少 user_ids 應回傳 400', async () => {
    const res = await wpPost<any>(apiOpts, API.limitGrantUsers, {
      item_ids: [testItemId || 1],
      expire_date: '0',
    })
    expect(res.status).toBe(400)
  })

  test('缺少 item_ids 應回傳 400', async () => {
    const res = await wpPost<any>(apiOpts, API.limitGrantUsers, {
      user_ids: [subscriberUserId || 1],
      expire_date: '0',
    })
    expect(res.status).toBe(400)
  })

  test('缺少 expire_date 應回傳錯誤', async () => {
    const res = await wpPost<any>(apiOpts, API.limitGrantUsers, {
      user_ids: [subscriberUserId || 1],
      item_ids: [testItemId || 1],
    })
    expect(res.status).toBeGreaterThanOrEqual(400)
  })

  test('user_ids 為空陣列應回傳 500（含 missing user_ids or item_ids）', async () => {
    const res = await wpPost<any>(apiOpts, API.limitGrantUsers, {
      user_ids: [],
      item_ids: [testItemId || 1],
      expire_date: '0',
    })
    expect(res.status).toBeGreaterThanOrEqual(400)
  })

  test('item_ids 為空陣列應回傳錯誤', async () => {
    const res = await wpPost<any>(apiOpts, API.limitGrantUsers, {
      user_ids: [subscriberUserId || 1],
      item_ids: [],
      expire_date: '0',
    })
    expect(res.status).toBeGreaterThanOrEqual(400)
  })

  test('不存在的 user_id 應回傳錯誤', async () => {
    const res = await wpPost<any>(apiOpts, API.limitGrantUsers, {
      user_ids: [ID_EDGE.nonExistent],
      item_ids: [testItemId || 1],
      expire_date: '0',
    })
    expect(res.status).toBeGreaterThanOrEqual(400)
  })

  test('expire_date 為負數時間戳應被處理（過去時間）', async () => {
    test.skip(!subscriberUserId || !testItemId, '缺少測試用戶或項目')
    const res = await wpPost<any>(apiOpts, API.limitGrantUsers, {
      user_ids: [subscriberUserId],
      item_ids: [testItemId],
      expire_date: '-1',
    })
    // 可能接受（過去時間戳）或拒絕
    expect([200, 400]).toContain(res.status)
  })
})

// ─────────────────────────────────────────────────────
// 更新用戶存取期限 POST /limit/update-users
// ─────────────────────────────────────────────────────

test.describe('POST /limit/update-users — 更新用戶存取期限', () => {
  test('成功延長用戶存取期限', async () => {
    test.skip(!subscriberUserId || !testItemId, '缺少測試用戶或項目')

    // 先授權
    await wpPost(apiOpts, API.limitGrantUsers, {
      user_ids: [subscriberUserId],
      item_ids: [testItemId],
      expire_date: '0',
    })

    const futureTimestamp = Math.floor(Date.now() / 1000) + 86400 * 730 // 兩年後
    const res = await wpPost<any>(apiOpts, API.limitUpdateUsers, {
      user_ids: [subscriberUserId],
      item_ids: [testItemId],
      timestamp: futureTimestamp,
    })
    expect(res.status).toBe(200)
    expect(res.data.code).toBe('update_users_success')
  })

  test('設定為無期限（timestamp=0）', async () => {
    test.skip(!subscriberUserId || !testItemId, '缺少測試用戶或項目')
    const res = await wpPost<any>(apiOpts, API.limitUpdateUsers, {
      user_ids: [subscriberUserId],
      item_ids: [testItemId],
      timestamp: 0,
    })
    expect(res.status).toBe(200)
    expect(res.data.code).toBe('update_users_success')
  })

  test('設定為過去時間（已過期）', async () => {
    test.skip(!subscriberUserId || !testItemId, '缺少測試用戶或項目')
    const pastTimestamp = Math.floor(Date.now() / 1000) - 86400 // 昨天
    const res = await wpPost<any>(apiOpts, API.limitUpdateUsers, {
      user_ids: [subscriberUserId],
      item_ids: [testItemId],
      timestamp: pastTimestamp,
    })
    expect([200, 400]).toContain(res.status)
  })

  test('缺少 timestamp 應回傳 400', async () => {
    const res = await wpPost<any>(apiOpts, API.limitUpdateUsers, {
      user_ids: [subscriberUserId || 1],
      item_ids: [testItemId || 1],
    })
    expect(res.status).toBe(400)
  })

  test('缺少 user_ids 應回傳 400', async () => {
    const res = await wpPost<any>(apiOpts, API.limitUpdateUsers, {
      item_ids: [testItemId || 1],
      timestamp: 0,
    })
    expect(res.status).toBe(400)
  })

  test('timestamp 為 NaN 字串應回傳錯誤', async () => {
    const res = await wpPost<any>(apiOpts, API.limitUpdateUsers, {
      user_ids: [subscriberUserId || 1],
      item_ids: [testItemId || 1],
      timestamp: 'NaN',
    })
    expect(res.status).toBeGreaterThanOrEqual(400)
  })
})

// ─────────────────────────────────────────────────────
// 撤銷用戶存取 POST /limit/revoke-users
// ─────────────────────────────────────────────────────

test.describe('POST /limit/revoke-users — 撤銷用戶存取', () => {
  test('成功撤銷用戶存取', async () => {
    test.skip(!subscriberUserId || !testItemId, '缺少測試用戶或項目')

    // 先授權
    await wpPost(apiOpts, API.limitGrantUsers, {
      user_ids: [subscriberUserId],
      item_ids: [testItemId],
      expire_date: '0',
    })

    // 再撤銷
    const res = await wpPost<any>(apiOpts, API.limitRevokeUsers, {
      user_ids: [subscriberUserId],
      item_ids: [testItemId],
    })
    expect(res.status).toBe(200)
    expect(res.data.code).toBe('revoke_users_success')
  })

  test('撤銷未授權用戶不應 500（靜默處理）', async () => {
    test.skip(!subscriberUserId, '缺少測試用戶')
    const res = await wpPost<any>(apiOpts, API.limitRevokeUsers, {
      user_ids: [subscriberUserId],
      item_ids: [ID_EDGE.nonExistent], // 不存在的項目
    })
    expect([200, 400]).toContain(res.status)
  })

  test('缺少 user_ids 應回傳 400', async () => {
    const res = await wpPost<any>(apiOpts, API.limitRevokeUsers, {
      item_ids: [testItemId || 1],
    })
    expect(res.status).toBe(400)
  })

  test('缺少 item_ids 應回傳 400', async () => {
    const res = await wpPost<any>(apiOpts, API.limitRevokeUsers, {
      user_ids: [subscriberUserId || 1],
    })
    expect(res.status).toBe(400)
  })

  test('不存在的 user_id 撤銷不應 500', async () => {
    const res = await wpPost<any>(apiOpts, API.limitRevokeUsers, {
      user_ids: [ID_EDGE.nonExistent],
      item_ids: [testItemId || 1],
    })
    expect([200, 400]).toContain(res.status)
  })
})
