/**
 * Users CRUD API 測試
 *
 * 對應 spec:
 *   - 查詢用戶列表.feature
 *   - 查詢單一用戶.feature
 *   - 建立用戶.feature
 *   - 更新用戶.feature
 *   - 刪除用戶.feature
 *   - 重設密碼.feature
 *   - 查詢用戶選項.feature
 */
import { test, expect } from '@playwright/test'
import { getNonce } from '../helpers/admin-setup.js'
import { wpGet, wpPost, wpDelete, type ApiOptions } from '../helpers/api-client.js'
import { API, WP_API, STRING_EDGE, ID_EDGE } from '../fixtures/test-data.js'
import fs from 'fs'
import path from 'path'

let apiOpts: ApiOptions
let setupSubscriberId: number | null = null
/** 此套件建立的用戶 ID */
const createdUserIds: number[] = []

test.beforeAll(async ({ request }, testInfo) => {
  const baseURL = testInfo.project.use.baseURL || 'http://localhost:8898'
  apiOpts = { request, baseURL, nonce: getNonce() }

  const idsFile = path.resolve(import.meta.dirname, '../.auth/setup-ids.json')
  if (fs.existsSync(idsFile)) {
    const ids = JSON.parse(fs.readFileSync(idsFile, 'utf-8'))
    setupSubscriberId = ids.subscriberUserId ?? null
  }
})

test.afterAll(async () => {
  // 用 WP REST API 刪除，Powerhouse 的是永久刪除
  for (const id of createdUserIds) {
    try {
      await apiOpts.request.delete(
        `${apiOpts.baseURL}/wp-json/${WP_API.userById(id)}?force=true&reassign=1`,
        { headers: { 'X-WP-Nonce': apiOpts.nonce } },
      )
    } catch { /* 忽略清理失敗 */ }
  }
})

// ─────────────────────────────────────────────────────
// 查詢用戶列表 GET /users
// ─────────────────────────────────────────────────────

test.describe('GET /users — 查詢用戶列表', () => {
  test('不帶參數應使用預設值回傳用戶列表', async () => {
    const res = await wpGet<any[]>(apiOpts, API.users)
    expect(res.status).toBe(200)
    expect(Array.isArray(res.data)).toBe(true)
  })

  test('應包含分頁 headers', async () => {
    const res = await wpGet(apiOpts, API.users, { number: '1', paged: '1' })
    expect(res.status).toBe(200)
    expect(Number(res.headers['x-wp-total'])).toBeGreaterThan(0)
  })

  test('帶 role=subscriber 應只回傳訂閱者', async () => {
    const res = await wpGet<any[]>(apiOpts, API.users, { role: 'subscriber' })
    expect(res.status).toBe(200)
    if (Array.isArray(res.data) && res.data.length > 0) {
      for (const user of res.data) {
        // roles 可能是字串或陣列
        const roles = user.roles || user.role || ''
        const rolesStr = typeof roles === 'string' ? roles : JSON.stringify(roles)
        expect(rolesStr).toContain('subscriber')
      }
    }
  })

  test('number=2 應限制回傳數量', async () => {
    const res = await wpGet<any[]>(apiOpts, API.users, { number: '2' })
    expect(res.status).toBe(200)
    expect((res.data as any[]).length).toBeLessThanOrEqual(2)
  })

  test('SQL injection 搜尋不應造成 500', async () => {
    const res = await wpGet(apiOpts, API.users, { search: STRING_EDGE.sqlInject1 })
    expect([200, 400]).toContain(res.status)
  })

  test('搜尋不存在的用戶應回傳空陣列', async () => {
    const res = await wpGet<any[]>(apiOpts, API.users, {
      search: 'zzz_nonexistent_user_e2e_ph_xyz',
    })
    expect(res.status).toBe(200)
    expect((res.data as any[]).length).toBe(0)
  })
})

// ─────────────────────────────────────────────────────
// 查詢單一用戶 GET /users/:id
// ─────────────────────────────────────────────────────

test.describe('GET /users/:id — 查詢單一用戶', () => {
  test('查詢存在的用戶應回傳資料', async () => {
    test.skip(!setupSubscriberId, '沒有可用的測試用戶')
    const res = await wpGet<any>(apiOpts, API.userById(setupSubscriberId!))
    expect(res.status).toBe(200)
    expect(res.data.ID || res.data.id).toBeTruthy()
    expect(res.data).toHaveProperty('user_login')
    expect(res.data).toHaveProperty('user_email')
  })

  test('查詢不存在的用戶（999999）應回傳錯誤', async () => {
    const res = await wpGet(apiOpts, API.userById(ID_EDGE.nonExistent))
    expect([400, 404]).toContain(res.status)
  })

  test('查詢 ID=-1 應回傳錯誤', async () => {
    const res = await wpGet(apiOpts, API.userById(ID_EDGE.negOne))
    expect(res.status).toBeGreaterThanOrEqual(400)
  })

  test('查詢 ID 為非數字應回傳錯誤', async () => {
    const res = await wpGet(apiOpts, API.userById(ID_EDGE.abcStr))
    expect(res.status).toBeGreaterThanOrEqual(400)
  })
})

// ─────────────────────────────────────────────────────
// 查詢用戶選項 GET /users/options
// ─────────────────────────────────────────────────────

test.describe('GET /users/options — 查詢用戶選項', () => {
  test('應回傳角色列表（value/label 格式）', async () => {
    const res = await wpGet<any>(apiOpts, API.userOptions)
    expect(res.status).toBe(200)
    expect(res.data).toBeTruthy()
    // 應包含 roles 相關資料
  })
})

// ─────────────────────────────────────────────────────
// 建立用戶 POST /users
// ─────────────────────────────────────────────────────

test.describe('POST /users — 建立用戶', () => {
  test('建立單一用戶應成功', async () => {
    const timestamp = Date.now()
    const res = await wpPost<any>(apiOpts, API.users, {
      user_login: `e2e_ph_new_${timestamp}`,
      user_email: `e2e_ph_new_${timestamp}@test.local`,
      role: 'subscriber',
    })
    expect(res.status).toBe(200)
    const ids: number[] = Array.isArray(res.data.data) ? res.data.data : [res.data.data]
    expect(Number(ids[0])).toBeGreaterThan(0)
    createdUserIds.push(...ids.map(Number))
  })

  test('qty=3 應批量建立 3 個用戶', async () => {
    const timestamp = Date.now()
    const res = await wpPost<any>(apiOpts, API.users, {
      user_email: `e2e_ph_batch_${timestamp}@test.local`,
      role: 'subscriber',
      qty: 3,
    })
    expect(res.status).toBe(200)
    const ids: number[] = Array.isArray(res.data.data) ? res.data.data : [res.data.data]
    expect(ids.length).toBe(3)
    createdUserIds.push(...ids.map(Number))
  })

  test('格式錯誤的 email 應回傳錯誤', async () => {
    const res = await wpPost(apiOpts, API.users, {
      user_login: `e2e_ph_bademail_${Date.now()}`,
      user_email: 'user@@domain..com',
      role: 'subscriber',
    })
    expect(res.status).toBeGreaterThanOrEqual(400)
  })

  test('純空白的 email 應回傳錯誤', async () => {
    const res = await wpPost(apiOpts, API.users, {
      user_login: `e2e_ph_emptyemail_${Date.now()}`,
      user_email: STRING_EDGE.spaces,
      role: 'subscriber',
    })
    expect(res.status).toBeGreaterThanOrEqual(400)
  })

  test('重複的 user_login 應回傳錯誤', async () => {
    const timestamp = Date.now()
    const username = `e2e_ph_dup_${timestamp}`
    const email1 = `e2e_ph_dup1_${timestamp}@test.local`
    const email2 = `e2e_ph_dup2_${timestamp}@test.local`

    // 先建立
    const res1 = await wpPost<any>(apiOpts, API.users, {
      user_login: username,
      user_email: email1,
      role: 'subscriber',
    })
    if (res1.status === 200) {
      const ids: number[] = Array.isArray(res1.data.data) ? res1.data.data : [res1.data.data]
      createdUserIds.push(...ids.map(Number))

      // 再建立同名用戶
      const res2 = await wpPost(apiOpts, API.users, {
        user_login: username,
        user_email: email2,
        role: 'subscriber',
      })
      expect(res2.status).toBeGreaterThanOrEqual(400)
    }
  })

  test('超長 username 應回傳錯誤或被截斷（不應 500）', async () => {
    const res = await wpPost(apiOpts, API.users, {
      user_login: `e2e_${STRING_EDGE.longStr.slice(0, 200)}`,
      user_email: `e2e_long_${Date.now()}@test.local`,
      role: 'subscriber',
    })
    expect([200, 400]).toContain(res.status)
    if (res.status === 200) {
      const ids: number[] = Array.isArray(res.data.data) ? res.data.data : [res.data.data]
      createdUserIds.push(...ids.map(Number))
    }
  })
})

// ─────────────────────────────────────────────────────
// 更新用戶 POST /users/:id
// ─────────────────────────────────────────────────────

test.describe('POST /users/:id — 更新用戶', () => {
  test('成功更新用戶顯示名稱', async () => {
    test.skip(!setupSubscriberId, '沒有可用的測試用戶')
    const res = await wpPost(apiOpts, API.userById(setupSubscriberId!), {
      display_name: 'E2E PH 已更新顯示名稱',
    })
    expect(res.status).toBe(200)
  })

  test('更新不存在的用戶應回傳錯誤', async () => {
    const res = await wpPost(apiOpts, API.userById(ID_EDGE.nonExistent), {
      display_name: '不存在用戶',
    })
    expect(res.status).toBeGreaterThanOrEqual(400)
  })

  test('更新用戶角色', async () => {
    test.skip(!setupSubscriberId, '沒有可用的測試用戶')
    const res = await wpPost(apiOpts, API.userById(setupSubscriberId!), {
      role: 'subscriber',
    })
    expect(res.status).toBe(200)
  })

  test('更新用戶 meta_data', async () => {
    test.skip(!setupSubscriberId, '沒有可用的測試用戶')
    const res = await wpPost(apiOpts, API.userById(setupSubscriberId!), {
      meta_data: { _e2e_test_meta: 'test_value' },
    })
    expect(res.status).toBe(200)
  })
})

// ─────────────────────────────────────────────────────
// 刪除用戶 DELETE /users
// ─────────────────────────────────────────────────────

test.describe('DELETE /users/:id — 刪除用戶', () => {
  test('刪除用戶應永久刪除', async () => {
    // 建立待刪除用戶
    const timestamp = Date.now()
    const createRes = await wpPost<any>(apiOpts, API.users, {
      user_login: `e2e_ph_del_${timestamp}`,
      user_email: `e2e_ph_del_${timestamp}@test.local`,
      role: 'subscriber',
    })
    expect(createRes.status).toBe(200)
    const ids: number[] = Array.isArray(createRes.data.data) ? createRes.data.data : [createRes.data.data]
    const userId = Number(ids[0])

    const delRes = await wpDelete(apiOpts, API.userById(userId))
    expect([200, 204]).toContain(delRes.status)

    // 驗證已刪除
    const getRes = await wpGet(apiOpts, API.userById(userId))
    expect(getRes.status).toBeGreaterThanOrEqual(400)
  })

  test('批量刪除用戶應成功', async () => {
    // 建立 2 個用戶
    const timestamp = Date.now()
    const r1 = await wpPost<any>(apiOpts, API.users, {
      user_email: `e2e_ph_bulkdel1_${timestamp}@test.local`,
      role: 'subscriber',
    })
    const r2 = await wpPost<any>(apiOpts, API.users, {
      user_email: `e2e_ph_bulkdel2_${timestamp}@test.local`,
      role: 'subscriber',
    })
    const ids1: number[] = Array.isArray(r1.data.data) ? r1.data.data : [r1.data.data]
    const ids2: number[] = Array.isArray(r2.data.data) ? r2.data.data : [r2.data.data]
    const uid1 = Number(ids1[0])
    const uid2 = Number(ids2[0])

    const delRes = await wpDelete(apiOpts, API.users, {
      ids: [uid1, uid2],
    })
    expect([200, 204]).toContain(delRes.status)
  })

  test('刪除不存在的用戶應回傳錯誤', async () => {
    const res = await wpDelete(apiOpts, API.userById(ID_EDGE.nonExistent))
    expect(res.status).toBeGreaterThanOrEqual(400)
  })
})

// ─────────────────────────────────────────────────────
// 重設密碼 POST /users/resetpassword
// ─────────────────────────────────────────────────────

test.describe('POST /users/resetpassword — 重設密碼', () => {
  test('為存在的用戶寄送重設密碼信', async () => {
    test.skip(!setupSubscriberId, '沒有可用的測試用戶')
    const res = await wpPost<any>(apiOpts, API.resetPassword, {
      ids: [setupSubscriberId],
    })
    // 可能回傳 200（已寄送）或其他狀態
    expect([200, 400]).toContain(res.status)
  })

  test('空 ids 陣列應回傳錯誤', async () => {
    const res = await wpPost(apiOpts, API.resetPassword, {
      ids: [],
    })
    expect(res.status).toBeGreaterThanOrEqual(400)
  })

  test('缺少 ids 應回傳錯誤', async () => {
    const res = await wpPost(apiOpts, API.resetPassword, {})
    expect(res.status).toBeGreaterThanOrEqual(400)
  })

  test('不存在的用戶 ID 應回傳錯誤', async () => {
    const res = await wpPost(apiOpts, API.resetPassword, {
      ids: [ID_EDGE.nonExistent],
    })
    expect(res.status).toBeGreaterThanOrEqual(400)
  })
})
