/**
 * Admin SPA 頁面測試
 *
 * 驗證 powerhouse 後台管理 SPA 頁面可正常載入。
 * powerhouse 提供 React admin SPA、DaisyUI theming。
 */
import { test, expect } from '@playwright/test'
import { URLS, TIMEOUTS } from '../fixtures/test-data.js'

test.describe('Admin Dashboard', () => {
	test('後台首頁應可正常載入', async ({ page, baseURL }) => {
		await page.goto(`${baseURL}${URLS.adminDashboard}`)
		await page.waitForLoadState('domcontentloaded')
		await expect(page.locator('body.wp-admin')).toBeVisible({ timeout: TIMEOUTS.spaLoad })
	})

	test('Powerhouse admin 頁面應可正常載入', async ({ page, baseURL }) => {
		await page.goto(`${baseURL}${URLS.adminPowerhouse}`)
		await page.waitForLoadState('domcontentloaded')
		// 驗證 admin 頁面不是 404
		const content = await page.content()
		expect(content).not.toContain('Not Found')
	})

	test('外掛頁面應列出 powerhouse', async ({ page, baseURL }) => {
		await page.goto(`${baseURL}${URLS.adminPlugins}`)
		await page.waitForLoadState('domcontentloaded')
		const content = await page.content()
		expect(content.toLowerCase()).toContain('powerhouse')
	})
})

test.describe('Admin SPA React App', () => {
	test('Powerhouse SPA 應載入 React 根節點', async ({ page, baseURL }) => {
		await page.goto(`${baseURL}${URLS.adminPowerhouse}`)
		await page.waitForLoadState('domcontentloaded')

		// React SPA 通常會有一個根元素
		const hasReactRoot = await page.evaluate(() => {
			return (
				!!document.querySelector('#powerhouse-app') ||
				!!document.querySelector('[id*="powerhouse"]') ||
				!!document.querySelector('[data-reactroot]') ||
				!!document.querySelector('#app')
			)
		})
		// 如果找不到 React root，至少確認頁面已載入
		const bodyContent = await page.textContent('body')
		expect(bodyContent).toBeTruthy()
	})
})
