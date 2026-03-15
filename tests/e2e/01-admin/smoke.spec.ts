/**
 * P0 Smoke Test — 冒煙測試
 *
 * 驗證 Powerhouse 外掛核心 API 端點存活，是所有測試的前提。
 * 若此測試失敗，後續所有測試都無意義。
 */
import { test, expect } from '@playwright/test'
import { getNonce } from '../helpers/admin-setup.js'
import { wpGet, type ApiOptions } from '../helpers/api-client.js'
import { API } from '../fixtures/test-data.js'

let apiOpts: ApiOptions

test.beforeAll(async ({ request }, testInfo) => {
  const baseURL = testInfo.project.use.baseURL || 'http://localhost:8898'
  apiOpts = { request, baseURL, nonce: getNonce() }
})

test.describe('P0 Smoke — Powerhouse API 存活確認', () => {
  test('GET /options 應回傳 200', async () => {
    const res = await wpGet(apiOpts, API.options)
    expect(res.status).toBe(200)
  })

  test('GET /lc 應回傳 200', async () => {
    const res = await wpGet(apiOpts, API.lc)
    expect(res.status).toBe(200)
  })

  test('GET /posts 應回傳 200', async () => {
    const res = await wpGet(apiOpts, API.posts)
    expect(res.status).toBe(200)
  })

  test('GET /products 應回傳 200', async () => {
    const res = await wpGet(apiOpts, API.products)
    expect(res.status).toBe(200)
  })

  test('GET /orders 應回傳 200', async () => {
    const res = await wpGet(apiOpts, API.orders)
    expect(res.status).toBe(200)
  })

  test('GET /users 應回傳 200', async () => {
    const res = await wpGet(apiOpts, API.users)
    expect(res.status).toBe(200)
  })

  test('GET /plugins 應回傳 200', async () => {
    const res = await wpGet(apiOpts, API.plugins)
    expect(res.status).toBe(200)
  })

  test('GET /woocommerce 應回傳 200', async () => {
    const res = await wpGet(apiOpts, API.woocommerce)
    expect(res.status).toBe(200)
  })

  test('未帶 Nonce 存取受保護端點應回傳 401', async ({ request }, testInfo) => {
    const baseURL = testInfo.project.use.baseURL || 'http://localhost:8898'
    // 不帶任何認證
    const unauthRes = await request.post(`${baseURL}/wp-json/${API.posts}`, {
      headers: { 'Content-Type': 'application/json' },
      data: { post_type: 'post', post_title: 'Test' },
    })
    // 未授權應回傳 401（或 403）
    expect([401, 403]).toContain(unauthRes.status())
  })
})
