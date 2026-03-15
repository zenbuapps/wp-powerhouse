/**
 * 用戶互動測試
 *
 * 測試不同角色（Guest、Subscriber、Admin）對受保護資源的存取行為。
 */
import { test, expect } from '@playwright/test'
import { getNonce } from '../helpers/admin-setup.js'
import { wpGet, wpPost, type ApiOptions } from '../helpers/api-client.js'
import { API, URLS, TEST_SUBSCRIBER } from '../fixtures/test-data.js'

test.describe('未登入訪客 — API 存取限制', () => {
  test('未帶 nonce 的 POST /posts 應回傳 401 或 403', async ({ page }, testInfo) => {
    const baseURL = testInfo.project.use.baseURL || 'http://localhost:8898'
    const ctx = await page.context().browser()!.newContext()
    const freshPage = await ctx.newPage()
    try {
      const res = await ctx.request.post(`${baseURL}/wp-json/${API.posts}`, {
        headers: { 'Content-Type': 'application/json' },
        data: { post_type: 'post', post_title: 'Unauthorized Test' },
      })
      expect([401, 403]).toContain(res.status())
    } finally {
      await ctx.close()
    }
  })

  test('未帶 nonce 的 DELETE 操作應被拒絕', async ({ page }, testInfo) => {
    const baseURL = testInfo.project.use.baseURL || 'http://localhost:8898'
    const ctx = await page.context().browser()!.newContext()
    try {
      const res = await ctx.request.delete(`${baseURL}/wp-json/${API.postById(1)}`, {
        headers: { 'Content-Type': 'application/json' },
      })
      expect([401, 403]).toContain(res.status())
    } finally {
      await ctx.close()
    }
  })
})

test.describe('Subscriber 角色 — 受限 API 存取', () => {
  test('Subscriber 登入後嘗試 POST /posts 應被拒絕', async ({ browser }, testInfo) => {
    const baseURL = testInfo.project.use.baseURL || 'http://localhost:8898'

    // 以 Subscriber 身份登入
    const ctx = await browser.newContext()
    const page = await ctx.newPage()
    try {
      await page.goto(`${baseURL}/wp-login.php`, { waitUntil: 'domcontentloaded' })
      await page.fill('#user_login', TEST_SUBSCRIBER.username)
      await page.fill('#user_pass', TEST_SUBSCRIBER.password)
      await page.click('#wp-submit')

      // 等待登入完成（可能跳轉到前台）
      await page.waitForTimeout(3000)

      // 嘗試取得 nonce（Subscriber 在前台無 wpApiSettings）
      // 直接用 cookie 帶請求，不帶 nonce
      const res = await ctx.request.post(`${baseURL}/wp-json/${API.posts}`, {
        headers: { 'Content-Type': 'application/json' },
        data: { post_type: 'post', post_title: 'Subscriber Attack' },
      })
      // Subscriber 無 manage_options，應被拒絕
      expect([401, 403]).toContain(res.status())
    } finally {
      await ctx.close()
    }
  })
})
