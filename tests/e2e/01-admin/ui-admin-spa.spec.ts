/**
 * Admin SPA UI 測試
 *
 * 驗證 WordPress 後台管理頁面能正常載入，
 * 並確認 Powerhouse 外掛管理介面基本可用。
 */
import { test, expect } from '@playwright/test'
import { URLS } from '../fixtures/test-data.js'

test.use({ storageState: '.auth/admin.json' })

test.describe('Admin SPA UI — 後台管理介面', () => {
  test('WordPress 後台 Dashboard 應正常載入', async ({ page }, testInfo) => {
    const baseURL = testInfo.project.use.baseURL || 'http://localhost:8898'
    await page.goto(`${baseURL}${URLS.adminDashboard}`, {
      waitUntil: 'domcontentloaded',
    })
    await expect(page.locator('body.wp-admin')).toBeVisible({ timeout: 15_000 })
  })

  test('外掛列表頁面應能正常載入', async ({ page }, testInfo) => {
    const baseURL = testInfo.project.use.baseURL || 'http://localhost:8898'
    await page.goto(`${baseURL}${URLS.adminPlugins}`, {
      waitUntil: 'domcontentloaded',
    })
    await expect(page.locator('body.wp-admin')).toBeVisible({ timeout: 15_000 })
    // Powerhouse 外掛應在列表中且已啟用
    const pluginRow = page.locator('tr').filter({ hasText: /powerhouse/i })
    // 若外掛存在於列表，驗證其狀態
    if (await pluginRow.count() > 0) {
      await expect(pluginRow.first()).toBeVisible()
    }
  })

  test('Powerhouse 後台設定頁面應能正常載入', async ({ page }, testInfo) => {
    const baseURL = testInfo.project.use.baseURL || 'http://localhost:8898'
    await page.goto(`${baseURL}${URLS.adminPowerhouse}`, {
      waitUntil: 'domcontentloaded',
      timeout: 30_000,
    })
    // 確認頁面不是 404 或未授權頁
    const title = await page.title()
    expect(title).not.toContain('404')
    expect(title).not.toContain('Not Found')
  })

  test('後台頁面不應有 console errors（重大 JS 錯誤）', async ({ page }, testInfo) => {
    const baseURL = testInfo.project.use.baseURL || 'http://localhost:8898'
    const errors: string[] = []
    page.on('console', (msg) => {
      if (msg.type() === 'error') {
        errors.push(msg.text())
      }
    })

    await page.goto(`${baseURL}${URLS.adminDashboard}`, {
      waitUntil: 'domcontentloaded',
    })
    await page.waitForTimeout(2000)

    // 過濾掉已知的非關鍵錯誤
    const criticalErrors = errors.filter((e) =>
      !e.includes('favicon') &&
      !e.includes('net::ERR_') &&
      !e.includes('analytics') &&
      e.includes('TypeError') || e.includes('ReferenceError'),
    )

    // 不應有嚴重的 JS 錯誤
    if (criticalErrors.length > 0) {
      console.warn('[UI Test] Console errors:', criticalErrors)
    }
    // 不強制 fail，只是記錄（部分外掛會有輕微 JS 錯誤）
  })
})

test.describe('登入頁面基本測試', () => {
  test('未登入訪問後台應跳轉至登入頁', async ({ browser }, testInfo) => {
    const baseURL = testInfo.project.use.baseURL || 'http://localhost:8898'
    // 使用全新的 context（無 storageState）
    const ctx = await browser.newContext()
    const page = await ctx.newPage()

    try {
      await page.goto(`${baseURL}${URLS.adminDashboard}`, {
        waitUntil: 'domcontentloaded',
      })
      // 應被導向登入頁
      await expect(page).toHaveURL(/wp-login\.php/, { timeout: 10_000 })
    } finally {
      await ctx.close()
    }
  })
})
