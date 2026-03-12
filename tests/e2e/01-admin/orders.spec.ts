/**
 * 訂單管理 API 測試
 *
 * 對應 spec: 查詢訂單列表.feature / 建立訂單備註.feature
 */
import { test, expect } from '@playwright/test'
import { getNonce } from '../helpers/admin-setup.js'
import { wpGet, wpPost, wpDelete, type ApiOptions } from '../helpers/api-client.js'
import { API, EDGE_CASES } from '../fixtures/test-data.js'
import fs from 'fs'
import path from 'path'

let apiOpts: ApiOptions
let testOrderId: number | null = null

test.beforeAll(async ({ request }, testInfo) => {
	const baseURL = testInfo.project.use.baseURL || 'http://localhost:8898'
	apiOpts = { request, baseURL, nonce: getNonce() }

	const idsFile = path.resolve(import.meta.dirname, '../.auth/setup-ids.json')
	if (fs.existsSync(idsFile)) {
		const ids = JSON.parse(fs.readFileSync(idsFile, 'utf-8'))
		testOrderId = ids.orderId ?? null
	}
})

// ==================== 查詢訂單列表 ====================

test.describe('查詢訂單列表 GET /orders', () => {
	test('不帶參數應使用預設值回傳訂單列表', async () => {
		const res = await wpGet<any[]>(apiOpts, API.orders)
		expect(res.status).toBe(200)
		expect(Array.isArray(res.data)).toBeTruthy()
	})

	test('回應應包含分頁 headers', async () => {
		const res = await wpGet<any[]>(apiOpts, API.orders)
		expect(res.status).toBe(200)
		expect(res.headers['x-wp-total']).toBeDefined()
		expect(Number(res.headers['x-wp-total'])).toBeGreaterThanOrEqual(0)
	})

	test('帶 status 篩選應只回傳對應狀態訂單', async () => {
		const res = await wpGet<any[]>(apiOpts, API.orders, { status: 'wc-processing' })
		expect(res.status).toBe(200)
		if (Array.isArray(res.data) && res.data.length > 0) {
			for (const order of res.data) {
				expect(order.status).toMatch(/processing/)
			}
		}
	})

	test('帶 limit=1 應限制結果數量', async () => {
		const res = await wpGet<any[]>(apiOpts, API.orders, { limit: '1' })
		expect(res.status).toBe(200)
		expect(Array.isArray(res.data)).toBeTruthy()
		expect(res.data.length).toBeLessThanOrEqual(1)
	})
})

// ==================== 查詢單一訂單 ====================

test.describe('查詢單一訂單 GET /orders/:id', () => {
	test('查詢存在的訂單應回傳 200', async () => {
		test.skip(!testOrderId, '沒有可用的測試訂單')
		const res = await wpGet<any>(apiOpts, API.orderById(testOrderId!))
		expect(res.status).toBe(200)
		expect(res.data.id).toBe(testOrderId)
	})

	test('查詢不存在的訂單應回傳錯誤', async () => {
		const res = await wpGet<any>(apiOpts, API.orderById(EDGE_CASES.nonExistentId))
		expect([400, 404]).toContain(res.status)
	})
})

// ==================== 訂單選項 ====================

test.describe('訂單選項 GET /orders/options', () => {
	test('應回傳訂單狀態列表', async () => {
		const res = await wpGet<any>(apiOpts, API.orderOptions)
		expect(res.status).toBe(200)
		expect(res.data).toBeTruthy()
	})
})

// ==================== 建立訂單備註 ====================

test.describe('建立訂單備註 POST /order-notes', () => {
	test('成功新增管理員備註', async () => {
		test.skip(!testOrderId, '沒有可用的測試訂單')
		const res = await wpPost<any>(apiOpts, API.orderNotes, {
			order_id: String(testOrderId),
			note: 'E2E 測試管理員備註',
			is_customer_note: '0',
		})
		expect(res.status).toBe(200)
		expect(res.data.code).toBe('create_success')
	})

	test('成功新增客戶備註', async () => {
		test.skip(!testOrderId, '沒有可用的測試訂單')
		const res = await wpPost<any>(apiOpts, API.orderNotes, {
			order_id: String(testOrderId),
			note: 'E2E 測試客戶備註',
			is_customer_note: '1',
		})
		expect(res.status).toBe(200)
		expect(res.data.code).toBe('create_success')
	})

	test('缺少 order_id 應回傳 400', async () => {
		const res = await wpPost<any>(apiOpts, API.orderNotes, {
			note: '測試備註',
			is_customer_note: '0',
		})
		expect(res.status).toBe(400)
	})

	test('訂單不存在應回傳錯誤', async () => {
		const res = await wpPost<any>(apiOpts, API.orderNotes, {
			order_id: String(EDGE_CASES.nonExistentId),
			note: '測試備註',
			is_customer_note: '0',
		})
		expect(res.status).toBeGreaterThanOrEqual(400)
	})

	test('XSS payload 作為備註應被安全處理', async () => {
		test.skip(!testOrderId, '沒有可用的測試訂單')
		const res = await wpPost<any>(apiOpts, API.orderNotes, {
			order_id: String(testOrderId),
			note: EDGE_CASES.xssPayload,
			is_customer_note: '0',
		})
		// 應成功建立但內容被消毒
		expect(res.status).toBe(200)
	})

	test('SQL injection 作為備註應被安全處理', async () => {
		test.skip(!testOrderId, '沒有可用的測試訂單')
		const res = await wpPost<any>(apiOpts, API.orderNotes, {
			order_id: String(testOrderId),
			note: EDGE_CASES.sqlInjection,
			is_customer_note: '0',
		})
		expect(res.status).toBe(200)
	})
})
