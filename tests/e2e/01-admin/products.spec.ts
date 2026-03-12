/**
 * 商品管理 API 測試
 *
 * 對應 spec: 查詢商品列表.feature / 產生商品變體.feature
 */
import { test, expect } from '@playwright/test'
import { getNonce } from '../helpers/admin-setup.js'
import { wpGet, wpPost, wpDelete, type ApiOptions } from '../helpers/api-client.js'
import { API, WC_API, EDGE_CASES } from '../fixtures/test-data.js'
import fs from 'fs'
import path from 'path'

let apiOpts: ApiOptions
let simpleProductId: number | null = null
let variableProductId: number | null = null

test.beforeAll(async ({ request }, testInfo) => {
	const baseURL = testInfo.project.use.baseURL || 'http://localhost:8898'
	apiOpts = { request, baseURL, nonce: getNonce() }

	// 讀取 setup 建立的 IDs
	const idsFile = path.resolve(import.meta.dirname, '../.auth/setup-ids.json')
	if (fs.existsSync(idsFile)) {
		const ids = JSON.parse(fs.readFileSync(idsFile, 'utf-8'))
		simpleProductId = ids.productIds?.[0] ?? null
		variableProductId = ids.variableProductId ?? null
	}
})

// ==================== 查詢商品列表 ====================

test.describe('查詢商品列表 GET /products', () => {
	test('不帶參數應使用預設值回傳商品列表', async () => {
		const res = await wpGet<any[]>(apiOpts, API.products)
		expect(res.status).toBe(200)
		expect(Array.isArray(res.data)).toBeTruthy()
	})

	test('回應應包含分頁 headers', async () => {
		const res = await wpGet<any[]>(apiOpts, API.products, { posts_per_page: '1' })
		expect(res.status).toBe(200)
		expect(Number(res.headers['x-wp-total'])).toBeGreaterThanOrEqual(0)
	})

	test('帶 status 篩選應只回傳對應狀態商品', async () => {
		const res = await wpGet<any[]>(apiOpts, API.products, { status: 'publish' })
		expect(res.status).toBe(200)
		if (Array.isArray(res.data) && res.data.length > 0) {
			for (const item of res.data) {
				expect(item.status).toBe('publish')
			}
		}
	})

	test('分頁 posts_per_page=1 應限制結果數量', async () => {
		const res = await wpGet<any[]>(apiOpts, API.products, { posts_per_page: '1' })
		expect(res.status).toBe(200)
		expect(Array.isArray(res.data)).toBeTruthy()
		expect(res.data.length).toBeLessThanOrEqual(1)
	})
})

// ==================== 查詢單一商品 ====================

test.describe('查詢單一商品 GET /products/:id', () => {
	test('查詢存在的商品應回傳 200', async () => {
		test.skip(!simpleProductId, '沒有可用的測試商品')
		const res = await wpGet<any>(apiOpts, API.productById(simpleProductId!))
		expect(res.status).toBe(200)
		expect(res.data.id).toBe(simpleProductId)
	})

	test('查詢不存在的商品應回傳錯誤', async () => {
		const res = await wpGet<any>(apiOpts, API.productById(EDGE_CASES.nonExistentId))
		expect([400, 404]).toContain(res.status)
	})
})

// ==================== 商品選擇器 ====================

test.describe('商品選擇器 GET /products/select', () => {
	test('搜尋 E2E 應回傳對應商品', async () => {
		const res = await wpGet<any[]>(apiOpts, API.productSelect, { s: 'E2E' })
		expect(res.status).toBe(200)
		expect(Array.isArray(res.data)).toBeTruthy()
	})
})

// ==================== 商品選項 ====================

test.describe('商品選項 GET /products/options', () => {
	test('應回傳商品分類/標籤等選項', async () => {
		const res = await wpGet<any>(apiOpts, API.productOptions)
		expect(res.status).toBe(200)
		expect(res.data).toBeTruthy()
	})
})

// ==================== 產生商品變體 ====================

test.describe('產生商品變體 POST /products/create-variations/:id', () => {
	test('成功產生所有變體', async () => {
		test.skip(!variableProductId, '沒有可用的可變商品')
		const res = await wpPost<any>(apiOpts, API.productCreateVariations(variableProductId!), {})
		expect(res.status).toBe(200)
		expect(res.data.code).toBe('update_attributes_success')
		if (res.data.data?.created_variation_ids) {
			expect(res.data.data.created_variation_ids.length).toBeGreaterThan(0)
		}
	})

	test('id 為非數字應回傳錯誤', async () => {
		const res = await wpPost<any>(apiOpts, 'v2/powerhouse/products/create-variations/abc', {})
		expect(res.status).toBeGreaterThanOrEqual(400)
	})

	test('商品不存在應回傳錯誤', async () => {
		const res = await wpPost<any>(apiOpts, API.productCreateVariations(EDGE_CASES.nonExistentId), {})
		expect(res.status).toBeGreaterThanOrEqual(400)
	})
})

// ==================== 商品屬性（全局）====================

test.describe('商品屬性 GET /product-attributes', () => {
	test('應回傳屬性列表', async () => {
		const res = await wpGet<any[]>(apiOpts, API.productAttributesList)
		expect(res.status).toBe(200)
		expect(Array.isArray(res.data)).toBeTruthy()
	})
})
