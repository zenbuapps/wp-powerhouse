/**
 * Access Control 整合測試
 *
 * 測試授權 → 更新 → 撤銷的完整流程，
 * 包含邊緣案例：重複授權、已刪除用戶存取、並發授權等。
 */
import { test, expect } from '@playwright/test'
import { getNonce } from '../helpers/admin-setup.js'
import { wpPost, wpDelete, type ApiOptions } from '../helpers/api-client.js'
import { API, WC_API, ID_EDGE } from '../fixtures/test-data.js'
import fs from 'fs'
import path from 'path'

let apiOpts: ApiOptions
let subscriberUserId: number | null = null
let testItemId: number | null = null

test.beforeAll(async ({ request }, testInfo) => {
  const baseURL = testInfo.project.use.baseURL || 'http://localhost:8898'
  apiOpts = { request, baseURL, nonce: getNonce() }

  const idsFile = path.resolve(import.meta.dirname, '../.auth/setup-ids.json')
  if (fs.existsSync(idsFile)) {
    const ids = JSON.parse(fs.readFileSync(idsFile, 'utf-8'))
    subscriberUserId = ids.subscriberUserId ?? null
    testItemId = ids.postIds?.[0] ?? null
  }
})

test.describe('存取控制完整生命週期', () => {
  test('授權 → 更新期限 → 撤銷完整流程', async () => {
    test.skip(!subscriberUserId || !testItemId, '缺少測試用戶或項目')

    // Step 1: 授權存取（無期限）
    const grantRes = await wpPost<any>(apiOpts, API.limitGrantUsers, {
      user_ids: [subscriberUserId],
      item_ids: [testItemId],
      expire_date: '0',
    })
    expect(grantRes.status).toBe(200)
    expect(grantRes.data.code).toBe('grant_users_success')
    expect(grantRes.data.data?.user_ids).toContain(String(subscriberUserId))

    // Step 2: 更新為有期限（90 天後）
    const futureTimestamp = Math.floor(Date.now() / 1000) + 86400 * 90
    const updateRes = await wpPost<any>(apiOpts, API.limitUpdateUsers, {
      user_ids: [subscriberUserId],
      item_ids: [testItemId],
      timestamp: futureTimestamp,
    })
    expect(updateRes.status).toBe(200)
    expect(updateRes.data.code).toBe('update_users_success')

    // Step 3: 撤銷存取
    const revokeRes = await wpPost<any>(apiOpts, API.limitRevokeUsers, {
      user_ids: [subscriberUserId],
      item_ids: [testItemId],
    })
    expect(revokeRes.status).toBe(200)
    expect(revokeRes.data.code).toBe('revoke_users_success')
  })

  test('重複授權同一用戶應覆蓋（冪等）', async () => {
    test.skip(!subscriberUserId || !testItemId, '缺少測試用戶或項目')

    // 第一次授權
    await wpPost<any>(apiOpts, API.limitGrantUsers, {
      user_ids: [subscriberUserId],
      item_ids: [testItemId],
      expire_date: '0',
    })

    // 第二次授權（相同用戶/項目，不同 expire_date）
    const futureTs = Math.floor(Date.now() / 1000) + 86400 * 30
    const res2 = await wpPost<any>(apiOpts, API.limitGrantUsers, {
      user_ids: [subscriberUserId],
      item_ids: [testItemId],
      expire_date: String(futureTs),
    })
    expect(res2.status).toBe(200)

    // 清理
    await wpPost(apiOpts, API.limitRevokeUsers, {
      user_ids: [subscriberUserId],
      item_ids: [testItemId],
    })
  })

  test('撤銷未授權的存取（靜默處理）', async () => {
    test.skip(!subscriberUserId || !testItemId, '缺少測試用戶或項目')

    // 先確保沒有授權記錄（先撤銷）
    await wpPost(apiOpts, API.limitRevokeUsers, {
      user_ids: [subscriberUserId],
      item_ids: [testItemId],
    })

    // 再次撤銷（無記錄）—— 應靜默處理
    const res = await wpPost<any>(apiOpts, API.limitRevokeUsers, {
      user_ids: [subscriberUserId],
      item_ids: [testItemId],
    })
    expect([200, 400]).toContain(res.status)
  })

  test('已刪除用戶的授權記錄撤銷', async () => {
    // 建立臨時用戶
    const timestamp = Date.now()
    const userRes = await wpPost<any>(apiOpts, API.users, {
      user_email: `e2e_ph_tmp_del_${timestamp}@test.local`,
      role: 'subscriber',
    })
    const userIds: number[] = Array.isArray(userRes.data.data) ? userRes.data.data : [userRes.data.data]
    const tempUserId = Number(userIds[0])
    if (!tempUserId || !testItemId) return

    // 授權
    await wpPost(apiOpts, API.limitGrantUsers, {
      user_ids: [tempUserId],
      item_ids: [testItemId],
      expire_date: '0',
    })

    // 刪除用戶
    await wpDelete(apiOpts, API.userById(tempUserId))

    // 撤銷已刪除用戶的授權——不應 500
    const revokeRes = await wpPost<any>(apiOpts, API.limitRevokeUsers, {
      user_ids: [tempUserId],
      item_ids: [testItemId],
    })
    expect([200, 400]).toContain(revokeRes.status)
  })
})

test.describe('商品權限綁定整合流程', () => {
  test('綁定 → 更新 → 解綁完整流程', async () => {
    test.skip(!testItemId, '缺少測試項目')

    // 讀取商品 ID
    const idsFile = path.resolve(import.meta.dirname, '../.auth/setup-ids.json')
    let productId: number | null = null
    if (fs.existsSync(idsFile)) {
      const ids = JSON.parse(fs.readFileSync(idsFile, 'utf-8'))
      productId = ids.productIds?.[0] ?? null
    }
    test.skip(!productId, '缺少測試商品')

    // Step 1: 綁定（無期限）
    const bindRes = await wpPost<any>(apiOpts, API.productBindItems, {
      product_ids: [productId],
      item_ids: [testItemId],
      limit_type: 'unlimited',
      meta_key: 'e2e_ph_bound_data',
    })
    expect([200, 201]).toContain(bindRes.status)

    // Step 2: 更新綁定（改為固定天數）
    const updateRes = await wpPost<any>(apiOpts, API.productUpdateBoundItems, {
      product_ids: [productId],
      item_ids: [testItemId],
      limit_type: 'fixed',
      limit_value: 60,
      limit_unit: 'day',
      meta_key: 'e2e_ph_bound_data',
    })
    expect([200, 201]).toContain(updateRes.status)

    // Step 3: 解綁
    const unbindRes = await wpPost<any>(apiOpts, API.productUnbindItems, {
      product_ids: [productId],
      item_ids: [testItemId],
      meta_key: 'e2e_ph_bound_data',
    })
    expect([200, 201]).toContain(unbindRes.status)
  })
})
