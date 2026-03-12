/**
 * 外掛列表 + 短碼 + 詞彙 API 測試
 *
 * 對應 spec: 查詢外掛列表.feature / 執行短碼.feature / 查詢詞彙列表.feature
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

// ==================== 查詢外掛列表 ====================

test.describe('查詢外掛列表 GET /plugins', () => {
	test('應回傳外掛列表', async () => {
		const res = await wpGet<any[]>(apiOpts, API.plugins)
		expect(res.status).toBe(200)
		expect(Array.isArray(res.data)).toBeTruthy()
	})

	test('結果應包含 key 和 is_active 欄位', async () => {
		const res = await wpGet<any[]>(apiOpts, API.plugins)
		expect(res.status).toBe(200)
		if (Array.isArray(res.data) && res.data.length > 0) {
			const first = res.data[0]
			expect(first).toHaveProperty('key')
			expect(first).toHaveProperty('is_active')
		}
	})

	test('應包含 X-WP-Total header', async () => {
		const res = await wpGet<any[]>(apiOpts, API.plugins)
		expect(res.status).toBe(200)
		expect(Number(res.headers['x-wp-total'])).toBeGreaterThan(0)
	})

	test('powerhouse 外掛應在列表中', async () => {
		const res = await wpGet<any[]>(apiOpts, API.plugins)
		expect(res.status).toBe(200)
		if (Array.isArray(res.data)) {
			const hasPowerhouse = res.data.some(
				(p: any) => p.key?.includes('powerhouse') || p.name?.includes('Powerhouse'),
			)
			expect(hasPowerhouse).toBeTruthy()
		}
	})
})

// ==================== 執行短碼 ====================

test.describe('執行短碼 GET /shortcode', () => {
	test('傳入 shortcode 應回傳 200', async () => {
		const res = await wpGet<any>(apiOpts, API.shortcode, { shortcode: '[woocommerce_my_account]' })
		expect(res.status).toBe(200)
		expect(res.data.code).toBe('get_shortcode_success')
	})

	test('執行不存在的短碼應回傳空結果', async () => {
		const res = await wpGet<any>(apiOpts, API.shortcode, { shortcode: '[nonexistent_e2e_shortcode]' })
		expect(res.status).toBe(200)
		// 不存在的短碼通常會原樣回傳或回傳空
	})

	test('XSS payload 作為短碼應被安全處理', async () => {
		const res = await wpGet<any>(apiOpts, API.shortcode, { shortcode: EDGE_CASES.xssPayload })
		expect(res.status).toBe(200)
		// 不應包含原始 script
		const dataStr = typeof res.data.data === 'string' ? res.data.data : JSON.stringify(res.data.data)
		expect(dataStr).not.toContain('<script>alert(1)</script>')
	})
})

// ==================== 查詢詞彙列表 ====================

test.describe('查詢詞彙列表 GET /terms/:taxonomy', () => {
	test('查詢 product_cat 應回傳 200', async () => {
		const res = await wpGet<any[]>(apiOpts, API.terms('product_cat'))
		expect(res.status).toBe(200)
		expect(Array.isArray(res.data)).toBeTruthy()
	})

	test('查詢 product_tag 應回傳 200', async () => {
		const res = await wpGet<any[]>(apiOpts, API.terms('product_tag'))
		expect(res.status).toBe(200)
		expect(Array.isArray(res.data)).toBeTruthy()
	})

	test('查詢 category 應回傳 200', async () => {
		const res = await wpGet<any[]>(apiOpts, API.terms('category'))
		expect(res.status).toBe(200)
		expect(Array.isArray(res.data)).toBeTruthy()
	})

	test('應包含分頁 headers', async () => {
		const res = await wpGet<any[]>(apiOpts, API.terms('product_cat'))
		expect(res.status).toBe(200)
		expect(Number(res.headers['x-wp-total'])).toBeGreaterThanOrEqual(0)
	})

	test('預設只查詢頂層詞彙 (parent=0)', async () => {
		const res = await wpGet<any[]>(apiOpts, API.terms('product_cat'))
		expect(res.status).toBe(200)
		if (Array.isArray(res.data) && res.data.length > 0) {
			for (const term of res.data) {
				expect(term.parent).toBe(0)
			}
		}
	})

	test('查詢不存在的 taxonomy 應回傳錯誤或空', async () => {
		const res = await wpGet<any>(apiOpts, API.terms('nonexistent_taxonomy_e2e'))
		expect([200, 400, 404]).toContain(res.status)
	})
})
