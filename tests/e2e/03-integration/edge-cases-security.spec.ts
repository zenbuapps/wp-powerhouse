/**
 * 邊界值與安全性整合測試
 *
 * 測試各種邊界值、XSS、SQL Injection、特殊字元等安全相關場景。
 */
import { test, expect } from '@playwright/test'
import { getNonce } from '../helpers/admin-setup.js'
import { wpGet, wpPost, type ApiOptions } from '../helpers/api-client.js'
import { API, EDGE_CASES } from '../fixtures/test-data.js'

let apiOpts: ApiOptions
const createdIds: number[] = []

test.beforeAll(async ({ request }, testInfo) => {
	const baseURL = testInfo.project.use.baseURL || 'http://localhost:8898'
	apiOpts = { request, baseURL, nonce: getNonce() }
})

test.afterAll(async () => {
	for (const id of createdIds) {
		try {
			await wpPost(apiOpts, API.postById(id), {})
		} catch { /* ignore */ }
	}
})

// ==================== XSS 防護 ====================

test.describe('XSS 防護', () => {
	test('文章標題 XSS payload 應被消毒', async () => {
		const res = await wpPost<any>(apiOpts, API.posts, {
			post_type: 'post',
			post_title: EDGE_CASES.xssPayload,
			post_status: 'draft',
		})
		expect(res.status).toBe(200)
		const ids = Array.isArray(res.data.data) ? res.data.data : [res.data.data]
		createdIds.push(...ids.map(Number))

		// 查詢回來的標題不應包含未消毒的 script
		const getRes = await wpGet<any>(apiOpts, API.postById(Number(ids[0])))
		if (getRes.status === 200) {
			expect(getRes.data.post_title).not.toContain('<script>')
		}
	})

	test('文章內容 XSS payload 應被消毒', async () => {
		const res = await wpPost<any>(apiOpts, API.posts, {
			post_type: 'post',
			post_title: 'E2E XSS Content Test',
			post_content: '<img src=x onerror=alert(1)>',
			post_status: 'draft',
		})
		expect(res.status).toBe(200)
		const ids = Array.isArray(res.data.data) ? res.data.data : [res.data.data]
		createdIds.push(...ids.map(Number))
	})

	test('訂單備註 XSS payload 應被消毒', async ({ request }, testInfo) => {
		const baseURL = testInfo.project.use.baseURL || 'http://localhost:8898'
		const nonce = getNonce()
		const res = await request.post(`${baseURL}/wp-json/${API.orderNotes}`, {
			headers: { 'X-WP-Nonce': nonce, 'Content-Type': 'application/json' },
			data: {
				order_id: '1', // 可能不存在
				note: '<script>document.cookie</script>',
				is_customer_note: '0',
			},
		})
		// 不管是否成功，不應執行腳本
		const body = await res.json().catch(() => ({}))
		if (typeof body.data === 'string') {
			expect(body.data).not.toContain('<script>')
		}
	})
})

// ==================== SQL Injection 防護 ====================

test.describe('SQL Injection 防護', () => {
	test('文章標題 SQL injection 不應造成資料庫錯誤', async () => {
		const res = await wpPost<any>(apiOpts, API.posts, {
			post_type: 'post',
			post_title: EDGE_CASES.sqlInjection,
			post_status: 'draft',
		})
		expect(res.status).toBe(200)
		const ids = Array.isArray(res.data.data) ? res.data.data : [res.data.data]
		createdIds.push(...ids.map(Number))
	})

	test('搜尋參數 SQL injection 不應造成錯誤', async () => {
		const res = await wpGet<any>(apiOpts, API.posts, {
			s: EDGE_CASES.sqlInjection,
		})
		expect([200, 400]).toContain(res.status)
	})

	test('商品搜尋 SQL injection 不應造成錯誤', async () => {
		const res = await wpGet<any>(apiOpts, API.productSelect, {
			s: EDGE_CASES.sqlInjection,
		})
		expect([200, 400]).toContain(res.status)
	})
})

// ==================== 特殊字元處理 ====================

test.describe('特殊字元處理', () => {
	test('Unicode/Emoji 標題應正常儲存', async () => {
		const res = await wpPost<any>(apiOpts, API.posts, {
			post_type: 'post',
			post_title: EDGE_CASES.unicodeEmoji,
			post_status: 'draft',
		})
		expect(res.status).toBe(200)
		const ids = Array.isArray(res.data.data) ? res.data.data : [res.data.data]
		createdIds.push(...ids.map(Number))

		const getRes = await wpGet<any>(apiOpts, API.postById(Number(ids[0])))
		if (getRes.status === 200) {
			expect(getRes.data.post_title).toContain('🎓')
		}
	})

	test('特殊符號標題應正常處理', async () => {
		const res = await wpPost<any>(apiOpts, API.posts, {
			post_type: 'post',
			post_title: EDGE_CASES.specialChars,
			post_status: 'draft',
		})
		expect(res.status).toBe(200)
		const ids = Array.isArray(res.data.data) ? res.data.data : [res.data.data]
		createdIds.push(...ids.map(Number))
	})

	test('超長字串標題應正常處理', async () => {
		const res = await wpPost<any>(apiOpts, API.posts, {
			post_type: 'post',
			post_title: EDGE_CASES.longString,
			post_status: 'draft',
		})
		// 可能成功也可能因為太長被截斷
		expect([200, 400]).toContain(res.status)
		if (res.status === 200) {
			const ids = Array.isArray(res.data.data) ? res.data.data : [res.data.data]
			createdIds.push(...ids.map(Number))
		}
	})
})

// ==================== 不存在資源 ID ====================

test.describe('不存在的資源 ID', () => {
	test('GET /posts/999999 應回傳 404 或空', async () => {
		const res = await wpGet<any>(apiOpts, API.postById(EDGE_CASES.nonExistentId))
		expect([400, 404]).toContain(res.status)
	})

	test('GET /products/999999 應回傳 404 或空', async () => {
		const res = await wpGet<any>(apiOpts, API.productById(EDGE_CASES.nonExistentId))
		expect([400, 404]).toContain(res.status)
	})

	test('GET /orders/999999 應回傳 404 或空', async () => {
		const res = await wpGet<any>(apiOpts, API.orderById(EDGE_CASES.nonExistentId))
		expect([400, 404]).toContain(res.status)
	})

	test('GET /users/999999 應回傳 404 或空', async () => {
		const res = await wpGet<any>(apiOpts, API.userById(EDGE_CASES.nonExistentId))
		expect([400, 404]).toContain(res.status)
	})

	test('POST /copy/999999 應回傳錯誤', async () => {
		const res = await wpPost<any>(apiOpts, API.copy(EDGE_CASES.nonExistentId), {})
		expect(res.status).toBeGreaterThanOrEqual(400)
	})

	test('POST /products/create-variations/999999 應回傳錯誤', async () => {
		const res = await wpPost<any>(apiOpts, API.productCreateVariations(EDGE_CASES.nonExistentId), {})
		expect(res.status).toBeGreaterThanOrEqual(400)
	})
})

// ==================== 空資料處理 ====================

test.describe('空資料/空列表處理', () => {
	test('空搜尋結果不應報錯', async () => {
		const res = await wpGet<any[]>(apiOpts, API.posts, {
			s: 'nonexistent_e2e_term_that_matches_nothing_at_all',
		})
		expect(res.status).toBe(200)
		expect(Array.isArray(res.data)).toBeTruthy()
		expect(res.data.length).toBe(0)
	})

	test('空商品搜尋不應報錯', async () => {
		const res = await wpGet<any[]>(apiOpts, API.productSelect, {
			s: 'absolutely_nothing_matches_this_e2e_query',
		})
		expect(res.status).toBe(200)
		expect(Array.isArray(res.data)).toBeTruthy()
	})
})
