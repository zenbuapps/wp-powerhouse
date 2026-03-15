/**
 * 前台頁面渲染測試
 *
 * 驗證 WordPress 前台頁面（Shop、My Account 等）能正常渲染，
 * 確認 WooCommerce 整合正常運作。
 */
import { test, expect } from '@playwright/test'
import { URLS } from '../fixtures/test-data.js'

test.describe('前台頁面渲染 — 已登入管理員', () => {
  test.use({ storageState: '.auth/admin.json' })

  test('首頁應正常回傳 200', async ({ page }, testInfo) => {
    const baseURL = testInfo.project.use.baseURL || 'http://localhost:8898'
    const resp = await page.goto(`${baseURL}/`, { waitUntil: 'domcontentloaded' })
    expect(resp?.status()).toBeLessThan(400)
  })

  test('Shop 頁面應正常載入', async ({ page }, testInfo) => {
    const baseURL = testInfo.project.use.baseURL || 'http://localhost:8898'
    const resp = await page.goto(`${baseURL}${URLS.shop}`, {
      waitUntil: 'domcontentloaded',
    })
    expect(resp?.status()).toBeLessThan(400)
  })

  test('My Account 頁面應正常載入', async ({ page }, testInfo) => {
    const baseURL = testInfo.project.use.baseURL || 'http://localhost:8898'
    const resp = await page.goto(`${baseURL}${URLS.myAccount}`, {
      waitUntil: 'domcontentloaded',
    })
    expect(resp?.status()).toBeLessThan(400)
  })
})

test.describe('前台頁面渲染 — 未登入訪客', () => {
  test.use({ storageState: undefined })

  test('首頁應對訪客開放', async ({ page }, testInfo) => {
    const baseURL = testInfo.project.use.baseURL || 'http://localhost:8898'
    // 使用全新 context（確保無 cookie）
    const ctx = await page.context().browser()!.newContext()
    const freshPage = await ctx.newPage()
    try {
      const resp = await freshPage.goto(`${baseURL}/`, {
        waitUntil: 'domcontentloaded',
      })
      expect(resp?.status()).toBeLessThan(400)
    } finally {
      await ctx.close()
    }
  })

  test('Shop 頁面應對訪客開放', async ({ page }, testInfo) => {
    const baseURL = testInfo.project.use.baseURL || 'http://localhost:8898'
    const ctx = await page.context().browser()!.newContext()
    const freshPage = await ctx.newPage()
    try {
      const resp = await freshPage.goto(`${baseURL}${URLS.shop}`, {
        waitUntil: 'domcontentloaded',
      })
      expect(resp?.status()).toBeLessThan(400)
    } finally {
      await ctx.close()
    }
  })
})
