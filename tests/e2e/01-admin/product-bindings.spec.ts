/**
 * 綁定權限項目到商品 API 測試
 *
 * 對應 spec: 綁定權限項目到商品.feature
 */
import { test, expect } from '@playwright/test'
import { getNonce } from '../helpers/admin-setup.js'
import { wpGet, wpPost, type ApiOptions } from '../helpers/api-client.js'
import { API, EDGE_CASES } from '../fixtures/test-data.js'
import fs from 'fs'
import path from 'path'

let apiOpts: ApiOptions
let simpleProductId: number | null = null
let testPostId: number | null = null

test.beforeAll(async ({ request }, testInfo) => {
	const baseURL = testInfo.project.use.baseURL || 'http://localhost:8898'
	apiOpts = { request, baseURL, nonce: getNonce() }

	const idsFile = path.resolve(import.meta.dirname, '../.auth/setup-ids.json')
	if (fs.existsSync(idsFile)) {
		const ids = JSON.parse(fs.readFileSync(idsFile, 'utf-8'))
		simpleProductId = ids.productIds?.[0] ?? null
		testPostId = ids.postIds?.[0] ?? null
	}
})

// ==================== 綁定權限項目到商品 ====================

test.describe('綁定權限項目到商品 POST /products/bind-items', () => {
	test('成功綁定項目到商品（無期限）', async () => {
		test.skip(!simpleProductId || !testPostId, '缺少測試商品或項目')
		const res = await wpPost<any>(apiOpts, API.productBindItems, {
			product_ids: [simpleProductId],
			item_ids: [testPostId],
			limit_type: 'unlimited',
			meta_key: 'bound_items_data',
		})
		expect(res.status).toBe(200)
		expect(res.data.code).toBe('success')
	})

	test('成功綁定項目到商品（固定期限）', async () => {
		test.skip(!simpleProductId || !testPostId, '缺少測試商品或項目')
		const res = await wpPost<any>(apiOpts, API.productBindItems, {
			product_ids: [simpleProductId],
			item_ids: [testPostId],
			limit_type: 'fixed',
			limit_value: 30,
			limit_unit: 'day',
			meta_key: 'bound_items_data',
		})
		expect(res.status).toBe(200)
		expect(res.data.code).toBe('success')
	})

	test('缺少 meta_key 應回傳 400', async () => {
		const res = await wpPost<any>(apiOpts, API.productBindItems, {
			product_ids: [simpleProductId || 1],
			item_ids: [testPostId || 1],
			limit_type: 'unlimited',
		})
		expect(res.status).toBe(400)
	})

	test('缺少 limit_type 應回傳 400', async () => {
		const res = await wpPost<any>(apiOpts, API.productBindItems, {
			product_ids: [simpleProductId || 1],
			item_ids: [testPostId || 1],
			meta_key: 'bound_items_data',
		})
		expect(res.status).toBe(400)
	})

	test('不存在的商品 ID 應回傳錯誤或靜默處理', async () => {
		const res = await wpPost<any>(apiOpts, API.productBindItems, {
			product_ids: [EDGE_CASES.nonExistentId],
			item_ids: [testPostId || 1],
			limit_type: 'unlimited',
			meta_key: 'bound_items_data',
		})
		// 可能成功也可能失敗，取決於實作
		expect([200, 400, 404]).toContain(res.status)
	})
})

// ==================== 解除綁定 ====================

test.describe('解除綁定權限項目 POST /products/unbind-items', () => {
	test('成功解除綁定', async () => {
		test.skip(!simpleProductId || !testPostId, '缺少測試商品或項目')

		// 先綁定
		await wpPost<any>(apiOpts, API.productBindItems, {
			product_ids: [simpleProductId],
			item_ids: [testPostId],
			limit_type: 'unlimited',
			meta_key: 'bound_items_data',
		})

		// 再解除
		const res = await wpPost<any>(apiOpts, API.productUnbindItems, {
			product_ids: [simpleProductId],
			item_ids: [testPostId],
			meta_key: 'bound_items_data',
		})
		expect(res.status).toBe(200)
	})
})
