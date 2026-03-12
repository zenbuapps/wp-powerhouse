/**
 * 授權碼管理 API 測試
 *
 * 對應 spec: 啟用授權碼.feature / 棄用授權碼.feature / 查詢授權碼狀態.feature / 清除授權碼快取.feature
 *
 * 注意: LC bypass 已啟用，授權碼相關操作需要 Cloud API 配合。
 *       在 E2E 環境中，我們主要測試 API 端點的回應格式和錯誤處理。
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

// ==================== 查詢授權碼狀態 ====================

test.describe('查詢授權碼狀態 GET /lc', () => {
	test('應回傳 200 和產品授權狀態列表', async () => {
		const res = await wpGet<any>(apiOpts, API.lc)
		expect(res.status).toBe(200)
		expect(Array.isArray(res.data)).toBeTruthy()
	})

	test('每項應包含必要欄位', async () => {
		const res = await wpGet<any[]>(apiOpts, API.lc)
		expect(res.status).toBe(200)
		if (Array.isArray(res.data) && res.data.length > 0) {
			const item = res.data[0]
			expect(item).toHaveProperty('product_slug')
			expect(item).toHaveProperty('product_name')
			expect(item).toHaveProperty('code')
			expect(item).toHaveProperty('post_status')
		}
	})
})

// ==================== 啟用授權碼 ====================

test.describe('啟用授權碼 POST /lc/activate', () => {
	test('缺少 code 應回傳 400', async () => {
		const res = await wpPost<any>(apiOpts, API.lcActivate, {
			product_slug: 'power-course',
		})
		expect(res.status).toBe(400)
	})

	test('缺少 product_slug 應回傳 400', async () => {
		const res = await wpPost<any>(apiOpts, API.lcActivate, {
			code: 'ABC-123-DEF',
		})
		expect(res.status).toBe(400)
	})

	test('空字串 code 應回傳錯誤', async () => {
		const res = await wpPost<any>(apiOpts, API.lcActivate, {
			code: '',
			product_slug: 'power-course',
		})
		expect(res.status).toBeGreaterThanOrEqual(400)
	})

	test('無效的授權碼應回傳錯誤（Cloud API 不可用）', async () => {
		const res = await wpPost<any>(apiOpts, API.lcActivate, {
			code: 'INVALID-E2E-CODE-999',
			product_slug: 'power-course',
		})
		// Cloud API 不可用時應回傳非 200
		expect(res.status).toBeGreaterThanOrEqual(400)
	})
})

// ==================== 棄用授權碼 ====================

test.describe('棄用授權碼 POST /lc/deactivate', () => {
	test('缺少 code 應回傳 400', async () => {
		const res = await wpPost<any>(apiOpts, API.lcDeactivate, {
			product_slug: 'power-course',
		})
		expect(res.status).toBe(400)
	})

	test('缺少 product_slug 應回傳 400', async () => {
		const res = await wpPost<any>(apiOpts, API.lcDeactivate, {
			code: 'ABC-123-DEF',
		})
		expect(res.status).toBe(400)
	})
})

// ==================== 清除授權碼快取 ====================

test.describe('清除授權碼快取 POST /lc/invalidate', () => {
	test('成功清除快取應回傳 200', async () => {
		const res = await wpPost<any>(apiOpts, API.lcInvalidate, {
			product_slug: 'power-course',
		})
		expect(res.status).toBe(200)
		expect(res.data.code).toBe('invalidate_lc_cache_success')
	})

	test('空 product_slug 應回傳 400', async () => {
		const res = await wpPost<any>(apiOpts, API.lcInvalidate, {
			product_slug: '',
		})
		expect(res.status).toBe(400)
		expect(res.data.code).toBe('invalidate_lc_cache_failed')
	})

	test('此端點無需認證（公開端點）', async ({ request }, testInfo) => {
		const baseURL = testInfo.project.use.baseURL || 'http://localhost:8898'
		// 不帶 nonce 呼叫
		const unauthOpts: ApiOptions = { request, baseURL, nonce: '' }
		const res = await wpPost<any>(unauthOpts, API.lcInvalidate, {
			product_slug: 'power-course',
		})
		expect(res.status).toBe(200)
	})
})
