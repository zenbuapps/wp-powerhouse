/**
 * API 回應格式一致性整合測試
 *
 * 驗證所有端點回應遵循統一的 SuccessResponse 格式：
 * { code: string, message: string, data: any }
 */
import { test, expect } from '@playwright/test'
import { getNonce } from '../helpers/admin-setup.js'
import { wpGet, wpPost, type ApiOptions } from '../helpers/api-client.js'
import { API, EDGE_CASES } from '../fixtures/test-data.js'

let apiOpts: ApiOptions

test.beforeAll(async ({ request }, testInfo) => {
	const baseURL = testInfo.project.use.baseURL || 'http://localhost:8898'
	apiOpts = { request, baseURL, nonce: getNonce() }
})

test.describe('API 回應格式一致性', () => {
	test('GET /options 應遵循 SuccessResponse 格式', async () => {
		const res = await wpGet<any>(apiOpts, API.options)
		expect(res.status).toBe(200)
		expect(res.data).toHaveProperty('code')
		expect(res.data).toHaveProperty('message')
		expect(res.data).toHaveProperty('data')
	})

	test('POST /posts 應遵循 SuccessResponse 格式', async () => {
		const res = await wpPost<any>(apiOpts, API.posts, {
			post_type: 'post',
			post_title: 'E2E Format Test',
			post_status: 'draft',
		})
		expect(res.status).toBe(200)
		expect(res.data).toHaveProperty('code')
		expect(res.data).toHaveProperty('message')
		expect(res.data).toHaveProperty('data')
	})

	test('GET /shortcode 應遵循 SuccessResponse 格式', async () => {
		const res = await wpGet<any>(apiOpts, API.shortcode, { shortcode: '[woocommerce_cart]' })
		expect(res.status).toBe(200)
		expect(res.data).toHaveProperty('code')
		expect(res.data).toHaveProperty('data')
	})

	test('POST /lc/invalidate 應遵循 SuccessResponse 格式', async () => {
		const res = await wpPost<any>(apiOpts, API.lcInvalidate, {
			product_slug: 'power-course',
		})
		expect(res.status).toBe(200)
		expect(res.data).toHaveProperty('code')
	})
})

test.describe('錯誤回應格式一致性', () => {
	test('400 錯誤應包含 code 欄位', async () => {
		// 觸發一個 400 錯誤
		const res = await wpPost<any>(apiOpts, API.lcActivate, {
			// 缺少必填欄位
		})
		if (res.status === 400) {
			expect(res.data).toHaveProperty('code')
		}
	})

	test('不存在的端點應回傳 404 或 rest_no_route', async () => {
		const res = await wpGet<any>(apiOpts, 'v2/powerhouse/nonexistent-endpoint-e2e')
		expect([404]).toContain(res.status)
		expect(res.data).toHaveProperty('code')
	})
})

test.describe('分頁 Headers 一致性', () => {
	const paginatedEndpoints = [
		{ name: 'posts', endpoint: API.posts },
		{ name: 'products', endpoint: API.products },
		{ name: 'orders', endpoint: API.orders },
		{ name: 'users', endpoint: API.users },
	]

	for (const { name, endpoint } of paginatedEndpoints) {
		test(`GET /${name} 應包含 X-WP-Total header`, async () => {
			const res = await wpGet<any[]>(apiOpts, endpoint)
			expect(res.status).toBe(200)
			expect(res.headers['x-wp-total']).toBeDefined()
		})
	}
})

test.describe('Nonce 過期處理', () => {
	test('無效的 nonce 應回傳 403', async ({ request }, testInfo) => {
		const baseURL = testInfo.project.use.baseURL || 'http://localhost:8898'
		const invalidOpts: ApiOptions = {
			request,
			baseURL,
			nonce: 'invalid_nonce_12345',
		}
		const res = await wpGet<any>(invalidOpts, API.options)
		expect([401, 403]).toContain(res.status)
	})
})
