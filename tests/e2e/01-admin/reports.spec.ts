/**
 * 營收統計 + WooCommerce 資訊 API 測試
 *
 * 對應 spec: 查詢營收統計.feature
 */
import { test, expect } from '@playwright/test'
import { getNonce } from '../helpers/admin-setup.js'
import { wpGet, type ApiOptions } from '../helpers/api-client.js'
import { API } from '../fixtures/test-data.js'

let apiOpts: ApiOptions

test.beforeAll(async ({ request }, testInfo) => {
	const baseURL = testInfo.project.use.baseURL || 'http://localhost:8898'
	apiOpts = { request, baseURL, nonce: getNonce() }
})

// ==================== 查詢營收統計 ====================

test.describe('查詢營收統計 GET /reports/revenue/stats', () => {
	test('帶日期範圍應回傳 200', async () => {
		const res = await wpGet<any>(apiOpts, API.revenueStats, {
			after: '2024-01-01',
			before: '2026-12-31',
		})
		expect(res.status).toBe(200)
	})

	test('回傳結構應包含 totals', async () => {
		const res = await wpGet<any>(apiOpts, API.revenueStats, {
			after: '2024-01-01',
			before: '2026-12-31',
		})
		expect(res.status).toBe(200)
		expect(res.data).toHaveProperty('totals')
	})

	test('回傳結構應包含 intervals', async () => {
		const res = await wpGet<any>(apiOpts, API.revenueStats, {
			after: '2024-01-01',
			before: '2026-12-31',
		})
		expect(res.status).toBe(200)
		expect(res.data).toHaveProperty('intervals')
		expect(Array.isArray(res.data.intervals)).toBeTruthy()
	})

	test('totals 應包含 orders_count 和 total_sales', async () => {
		const res = await wpGet<any>(apiOpts, API.revenueStats, {
			after: '2024-01-01',
			before: '2026-12-31',
		})
		expect(res.status).toBe(200)
		expect(res.data.totals).toHaveProperty('orders_count')
		expect(res.data.totals).toHaveProperty('total_sales')
	})

	test('interval=month 應回傳月粒度資料', async () => {
		const res = await wpGet<any>(apiOpts, API.revenueStats, {
			after: '2024-01-01',
			before: '2024-06-30',
			interval: 'month',
		})
		expect(res.status).toBe(200)
		expect(res.data).toHaveProperty('intervals')
	})

	test('interval=year 應回傳年粒度資料', async () => {
		const res = await wpGet<any>(apiOpts, API.revenueStats, {
			after: '2020-01-01',
			before: '2026-12-31',
			interval: 'year',
		})
		expect(res.status).toBe(200)
		expect(res.data).toHaveProperty('intervals')
	})

	test('回傳應包含 total 和 pages 分頁資訊', async () => {
		const res = await wpGet<any>(apiOpts, API.revenueStats, {
			after: '2024-01-01',
			before: '2026-12-31',
		})
		expect(res.status).toBe(200)
		expect(res.data).toHaveProperty('total')
		expect(res.data).toHaveProperty('pages')
	})
})

// ==================== WooCommerce 資訊 ====================

test.describe('WooCommerce 資訊 GET /woocommerce', () => {
	test('應回傳 WooCommerce 全局資訊', async () => {
		const res = await wpGet<any>(apiOpts, API.woocommerce)
		expect(res.status).toBe(200)
		expect(res.data).toBeTruthy()
	})
})
