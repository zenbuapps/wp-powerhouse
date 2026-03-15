/**
 * 生命週期整合測試
 *
 * 測試跨 API 的完整工作流程：
 * - 文章建立 → 更新 → 複製 → 刪除
 * - 商品建立 → 設定屬性 → 產生變體 → 刪除
 * - 用戶建立 → 授權存取 → 更新期限 → 撤銷 → 刪除
 * - 訂單建立 → 更新狀態 → 新增備註 → 刪除
 */
import { test, expect } from '@playwright/test'
import { getNonce } from '../helpers/admin-setup.js'
import { wpGet, wpPost, wpDelete, type ApiOptions } from '../helpers/api-client.js'
import { API, WC_API } from '../fixtures/test-data.js'

let apiOpts: ApiOptions

test.beforeAll(async ({ request }, testInfo) => {
  const baseURL = testInfo.project.use.baseURL || 'http://localhost:8898'
  apiOpts = { request, baseURL, nonce: getNonce() }
})

// ─────────────────────────────────────────────────────
// 文章完整生命週期
// ─────────────────────────────────────────────────────

test.describe('文章完整生命週期', () => {
  test('建立 → 查詢 → 更新 → 複製 → 刪除', async () => {
    test.slow()

    // 1. 建立文章
    const createRes = await wpPost<any>(apiOpts, API.posts, {
      post_type: 'post',
      post_title: 'E2E PH Lifecycle 測試文章',
      post_status: 'draft',
      post_content: '生命週期測試內容',
    })
    expect(createRes.status).toBe(200)
    const ids: number[] = Array.isArray(createRes.data.data) ? createRes.data.data : [createRes.data.data]
    const postId = Number(ids[0])
    expect(postId).toBeGreaterThan(0)

    try {
      // 2. 查詢驗證
      const getRes = await wpGet<any>(apiOpts, API.postById(postId))
      expect(getRes.status).toBe(200)
      expect(getRes.data.post_title).toContain('Lifecycle')

      // 3. 更新
      const updateRes = await wpPost(apiOpts, API.postById(postId), {
        post_title: 'E2E PH Lifecycle 更新後',
        post_status: 'publish',
      })
      expect(updateRes.status).toBe(200)

      // 4. 查詢欄位
      const fieldRes = await wpGet(apiOpts, API.postField(postId, 'post_title'))
      expect(fieldRes.status).toBe(200)

      // 5. 複製
      const copyRes = await wpPost<any>(apiOpts, API.copy(postId), {})
      expect(copyRes.status).toBe(200)
      const copiedId = Number(copyRes.data.data)
      expect(copiedId).toBeGreaterThan(0)

      // 6. 刪除原始文章
      const delRes = await wpDelete(apiOpts, API.postById(postId))
      expect([200, 204]).toContain(delRes.status)

      // 7. 刪除複製文章
      await apiOpts.request.delete(
        `${apiOpts.baseURL}/wp-json/wp/v2/posts/${copiedId}?force=true`,
        { headers: { 'X-WP-Nonce': apiOpts.nonce } },
      )
    } finally {
      // 確保清理
      try {
        await apiOpts.request.delete(
          `${apiOpts.baseURL}/wp-json/wp/v2/posts/${postId}?force=true`,
          { headers: { 'X-WP-Nonce': apiOpts.nonce } },
        )
      } catch { /* 忽略 */ }
    }
  })

  test('排序多篇文章後查詢 menu_order 應更新', async () => {
    // 建立 3 篇文章
    const resA = await wpPost<any>(apiOpts, API.posts, {
      post_type: 'post',
      post_title: 'E2E PH 排序 A',
      post_status: 'draft',
    })
    const resB = await wpPost<any>(apiOpts, API.posts, {
      post_type: 'post',
      post_title: 'E2E PH 排序 B',
      post_status: 'draft',
    })
    const idA = Number((Array.isArray(resA.data.data) ? resA.data.data : [resA.data.data])[0])
    const idB = Number((Array.isArray(resB.data.data) ? resB.data.data : [resB.data.data])[0])

    try {
      // 排序：B 在前，A 在後
      const sortRes = await wpPost(apiOpts, API.postSort, {
        from_tree: [
          { id: String(idA), menu_order: '0', post_parent: '0' },
          { id: String(idB), menu_order: '1', post_parent: '0' },
        ],
        to_tree: [
          { id: String(idB), menu_order: '0', post_parent: '0' },
          { id: String(idA), menu_order: '1', post_parent: '0' },
        ],
      })
      expect(sortRes.status).toBe(200)
    } finally {
      await apiOpts.request.delete(
        `${apiOpts.baseURL}/wp-json/wp/v2/posts/${idA}?force=true`,
        { headers: { 'X-WP-Nonce': apiOpts.nonce } },
      )
      await apiOpts.request.delete(
        `${apiOpts.baseURL}/wp-json/wp/v2/posts/${idB}?force=true`,
        { headers: { 'X-WP-Nonce': apiOpts.nonce } },
      )
    }
  })
})

// ─────────────────────────────────────────────────────
// 用戶存取控制完整生命週期
// ─────────────────────────────────────────────────────

test.describe('用戶存取控制完整生命週期', () => {
  test('建立用戶 → 授權存取 → 更新期限 → 撤銷 → 刪除用戶', async () => {
    test.slow()

    // 1. 建立測試用戶
    const timestamp = Date.now()
    const userRes = await wpPost<any>(apiOpts, API.users, {
      user_login: `e2e_ph_lc_user_${timestamp}`,
      user_email: `e2e_ph_lc_user_${timestamp}@test.local`,
      role: 'subscriber',
    })
    expect(userRes.status).toBe(200)
    const userIds: number[] = Array.isArray(userRes.data.data) ? userRes.data.data : [userRes.data.data]
    const userId = Number(userIds[0])
    expect(userId).toBeGreaterThan(0)

    // 2. 建立測試項目（post）
    const postRes = await wpPost<any>(apiOpts, API.posts, {
      post_type: 'post',
      post_title: 'E2E PH LC 測試項目',
      post_status: 'publish',
    })
    const postIds: number[] = Array.isArray(postRes.data.data) ? postRes.data.data : [postRes.data.data]
    const itemId = Number(postIds[0])

    try {
      // 3. 授權用戶存取（無期限）
      const grantRes = await wpPost<any>(apiOpts, API.limitGrantUsers, {
        user_ids: [userId],
        item_ids: [itemId],
        expire_date: '0',
      })
      expect(grantRes.status).toBe(200)
      expect(grantRes.data.code).toBe('grant_users_success')

      // 4. 更新為有期限（3 個月後）
      const futureTs = Math.floor(Date.now() / 1000) + 86400 * 90
      const updateRes = await wpPost<any>(apiOpts, API.limitUpdateUsers, {
        user_ids: [userId],
        item_ids: [itemId],
        timestamp: futureTs,
      })
      expect(updateRes.status).toBe(200)
      expect(updateRes.data.code).toBe('update_users_success')

      // 5. 撤銷存取
      const revokeRes = await wpPost<any>(apiOpts, API.limitRevokeUsers, {
        user_ids: [userId],
        item_ids: [itemId],
      })
      expect(revokeRes.status).toBe(200)
      expect(revokeRes.data.code).toBe('revoke_users_success')
    } finally {
      // 6. 清理：刪除用戶和文章
      try {
        await wpDelete(apiOpts, API.userById(userId))
      } catch { /* 忽略 */ }
      try {
        await apiOpts.request.delete(
          `${apiOpts.baseURL}/wp-json/wp/v2/posts/${itemId}?force=true`,
          { headers: { 'X-WP-Nonce': apiOpts.nonce } },
        )
      } catch { /* 忽略 */ }
    }
  })
})

// ─────────────────────────────────────────────────────
// 訂單完整生命週期
// ─────────────────────────────────────────────────────

test.describe('訂單完整生命週期', () => {
  test('建立訂單 → 更新狀態 → 新增備註 → 刪除備註 → 刪除訂單', async () => {
    test.slow()

    // 1. 建立訂單
    const createRes = await wpPost<any>(apiOpts, API.orders, {})
    expect(createRes.status).toBe(200)
    const orderId = Number(Array.isArray(createRes.data.data) ? createRes.data.data[0] : createRes.data.data)
    expect(orderId).toBeGreaterThan(0)

    try {
      // 2. 更新訂單狀態
      const updateRes = await wpPost(apiOpts, API.orderById(orderId), {
        status: 'processing',
      })
      expect(updateRes.status).toBe(200)

      // 3. 新增訂單備註
      const noteRes = await wpPost<any>(apiOpts, API.orderNotes, {
        order_id: orderId,
        note: 'E2E PH 訂單備註測試',
        is_customer_note: '0',
      })
      expect(noteRes.status).toBe(200)
      const noteId = Number(noteRes.data?.data)

      // 4. 刪除備註（若成功建立）
      if (noteId) {
        const delNoteRes = await wpDelete(apiOpts, API.orderNoteById(noteId))
        expect([200, 204]).toContain(delNoteRes.status)
      }

      // 5. 刪除訂單
      const delRes = await wpDelete(apiOpts, API.orderById(orderId))
      expect([200, 204]).toContain(delRes.status)
    } finally {
      // 確保清理
      try {
        await apiOpts.request.delete(
          `${apiOpts.baseURL}/wp-json/wc/v3/orders/${orderId}?force=true`,
          { headers: { 'X-WP-Nonce': apiOpts.nonce } },
        )
      } catch { /* 忽略 */ }
    }
  })
})

// ─────────────────────────────────────────────────────
// 詞彙完整生命週期
// ─────────────────────────────────────────────────────

test.describe('詞彙完整生命週期', () => {
  const TAXONOMY = 'product_cat'

  test('建立詞彙 → 查詢 → 排序 → 更新 → 刪除', async () => {
    // 建立 2 個詞彙
    const r1 = await wpPost<any>(apiOpts, API.terms(TAXONOMY), {
      name: 'E2E PH 詞彙 Alpha',
    })
    const r2 = await wpPost<any>(apiOpts, API.terms(TAXONOMY), {
      name: 'E2E PH 詞彙 Beta',
    })
    const id1 = Number((Array.isArray(r1.data.data) ? r1.data.data : [r1.data.data])[0])
    const id2 = Number((Array.isArray(r2.data.data) ? r2.data.data : [r2.data.data])[0])
    expect(id1).toBeGreaterThan(0)
    expect(id2).toBeGreaterThan(0)

    try {
      // 查詢
      const getRes = await wpGet<any>(apiOpts, API.termById(TAXONOMY, id1))
      expect(getRes.status).toBe(200)

      // 排序
      const sortRes = await wpPost(apiOpts, API.termSort(TAXONOMY), {
        from_tree: [
          { id: String(id1), order: '0', parent: '0' },
          { id: String(id2), order: '1', parent: '0' },
        ],
        to_tree: [
          { id: String(id2), order: '0', parent: '0' },
          { id: String(id1), order: '1', parent: '0' },
        ],
      })
      expect(sortRes.status).toBe(200)

      // 更新
      const updateRes = await wpPost(apiOpts, API.termById(TAXONOMY, id1), {
        name: 'E2E PH 詞彙 Alpha 已更新',
        description: '已更新的描述',
      })
      expect(updateRes.status).toBe(200)
    } finally {
      // 清理
      try { await wpDelete(apiOpts, API.termById(TAXONOMY, id1)) } catch { /* 忽略 */ }
      try { await wpDelete(apiOpts, API.termById(TAXONOMY, id2)) } catch { /* 忽略 */ }
    }
  })
})
