/**
 * 前台未認證存取測試
 *
 * 驗證未登入使用者無法存取管理 API。
 */
import { test, expect } from '@playwright/test'
import { API, URLS } from '../fixtures/test-data.js'

test.describe('未認證使用者 API 存取', () => {
	test('未認證呼叫 GET /posts 應回傳 401', async ({ request, baseURL }) => {
		const res = await request.get(`${baseURL}/wp-json/${API.posts}`, {
			headers: { 'Content-Type': 'application/json' },
		})
		// WordPress REST API 預設會回傳 401 如果需要認證
		expect([401, 403]).toContain(res.status())
	})

	test('未認證呼叫 GET /options 應回傳 401', async ({ request, baseURL }) => {
		const res = await request.get(`${baseURL}/wp-json/${API.options}`, {
			headers: { 'Content-Type': 'application/json' },
		})
		expect([401, 403]).toContain(res.status())
	})

	test('未認證呼叫 GET /products 應回傳 401', async ({ request, baseURL }) => {
		const res = await request.get(`${baseURL}/wp-json/${API.products}`, {
			headers: { 'Content-Type': 'application/json' },
		})
		expect([401, 403]).toContain(res.status())
	})

	test('未認證呼叫 GET /orders 應回傳 401', async ({ request, baseURL }) => {
		const res = await request.get(`${baseURL}/wp-json/${API.orders}`, {
			headers: { 'Content-Type': 'application/json' },
		})
		expect([401, 403]).toContain(res.status())
	})

	test('未認證呼叫 GET /users 應回傳 401', async ({ request, baseURL }) => {
		const res = await request.get(`${baseURL}/wp-json/${API.users}`, {
			headers: { 'Content-Type': 'application/json' },
		})
		expect([401, 403]).toContain(res.status())
	})

	test('未認證呼叫 GET /plugins 應回傳 401', async ({ request, baseURL }) => {
		const res = await request.get(`${baseURL}/wp-json/${API.plugins}`, {
			headers: { 'Content-Type': 'application/json' },
		})
		expect([401, 403]).toContain(res.status())
	})

	test('未認證呼叫 GET /lc 應回傳 401', async ({ request, baseURL }) => {
		const res = await request.get(`${baseURL}/wp-json/${API.lc}`, {
			headers: { 'Content-Type': 'application/json' },
		})
		expect([401, 403]).toContain(res.status())
	})

	test('未認證呼叫 POST /limit/grant-users 應回傳 401', async ({ request, baseURL }) => {
		const res = await request.post(`${baseURL}/wp-json/${API.limitGrantUsers}`, {
			headers: { 'Content-Type': 'application/json' },
			data: { user_ids: [1], item_ids: [1], expire_date: '0' },
		})
		expect([401, 403]).toContain(res.status())
	})

	test('/lc/invalidate 公開端點即使未認證也應回傳 200', async ({ request, baseURL }) => {
		const res = await request.post(`${baseURL}/wp-json/${API.lcInvalidate}`, {
			headers: { 'Content-Type': 'application/json' },
			data: { product_slug: 'power-course' },
		})
		expect(res.status()).toBe(200)
	})
})

test.describe('未認證使用者頁面存取', () => {
	test('直接存取 wp-admin 應重導到登入頁', async ({ browser }) => {
		const context = await browser.newContext()
		const page = await context.newPage()

		const baseURL = 'http://localhost:8898'
		await page.goto(`${baseURL}${URLS.adminDashboard}`)
		await page.waitForLoadState('domcontentloaded')
		expect(page.url()).toContain('wp-login.php')

		await context.close()
	})
})
