/**
 * 前台頁面基礎測試
 *
 * 驗證 WordPress 前台頁面可正常載入，powerhouse 主題/功能正常運作。
 */
import { test, expect } from '@playwright/test'
import { URLS } from '../fixtures/test-data.js'

test.describe('前台頁面載入', () => {
	test('首頁應可正常載入', async ({ page, baseURL }) => {
		const res = await page.goto(`${baseURL}/`)
		expect(res?.status()).toBeLessThan(500)
		await expect(page).not.toHaveURL(/wp-login/)
	})

	test('商店頁面應可正常載入', async ({ page, baseURL }) => {
		const res = await page.goto(`${baseURL}${URLS.shop}`)
		expect(res?.status()).toBeLessThan(500)
	})

	test('我的帳戶頁面應可正常載入', async ({ page, baseURL }) => {
		const res = await page.goto(`${baseURL}${URLS.myAccount}`)
		expect(res?.status()).toBeLessThan(500)
	})

	test('購物車頁面應可正常載入', async ({ page, baseURL }) => {
		const res = await page.goto(`${baseURL}${URLS.cart}`)
		expect(res?.status()).toBeLessThan(500)
	})

	test('結帳頁面應可正常載入', async ({ page, baseURL }) => {
		const res = await page.goto(`${baseURL}${URLS.checkout}`)
		expect(res?.status()).toBeLessThan(500)
	})
})

test.describe('WordPress 登入頁面', () => {
	test('登入頁面應可正常載入', async ({ page, baseURL }) => {
		await page.goto(`${baseURL}${URLS.loginPage}`)
		await expect(page.locator('#user_login')).toBeVisible()
		await expect(page.locator('#user_pass')).toBeVisible()
		await expect(page.locator('#wp-submit')).toBeVisible()
	})

	test('錯誤的帳密應顯示錯誤訊息', async ({ page, baseURL }) => {
		await page.goto(`${baseURL}${URLS.loginPage}`)
		await page.fill('#user_login', 'fake_e2e_user')
		await page.fill('#user_pass', 'wrong_password')
		await page.click('#wp-submit')
		// 等待錯誤訊息或頁面刷新
		await page.waitForLoadState('domcontentloaded')
		const errorVisible = await page.locator('#login_error').isVisible()
		expect(errorVisible).toBeTruthy()
	})
})
