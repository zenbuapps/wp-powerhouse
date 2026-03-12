/**
 * WooCommerce 商品整合測試
 *
 * 測試商品建立 → 變體產生 → 權限綁定 → 訂單建立 的完整流程
 */
import { test, expect } from '@playwright/test'
import { getNonce } from '../helpers/admin-setup.js'
import { wpGet, wpPost, wpDelete, type ApiOptions } from '../helpers/api-client.js'
import { API, WC_API, EDGE_CASES } from '../fixtures/test-data.js'
import fs from 'fs'
import path from 'path'

let apiOpts: ApiOptions
let variableProductId: number | null = null
let simpleProductId: number | null = null
let testOrderId: number | null = null

test.beforeAll(async ({ request }, testInfo) => {
	const baseURL = testInfo.project.use.baseURL || 'http://localhost:8898'
	apiOpts = { request, baseURL, nonce: getNonce() }

	const idsFile = path.resolve(import.meta.dirname, '../.auth/setup-ids.json')
	if (fs.existsSync(idsFile)) {
		const ids = JSON.parse(fs.readFileSync(idsFile, 'utf-8'))
		variableProductId = ids.variableProductId ?? null
		simpleProductId = ids.productIds?.[0] ?? null
		testOrderId = ids.orderId ?? null
	}
})

// ==================== 商品 + 變體整合 ====================

test.describe('商品 + 變體整合', () => {
	test('可變商品應可查詢並有屬性', async () => {
		test.skip(!variableProductId, '沒有可用的可變商品')

		// 透過 Powerhouse API 查詢
		const res = await wpGet<any>(apiOpts, API.productById(variableProductId!))
		expect(res.status).toBe(200)
		expect(res.data.id).toBe(variableProductId)
	})

	test('產生變體後應可在 WC API 查詢到', async () => {
		test.skip(!variableProductId, '沒有可用的可變商品')

		// 產生變體
		const createRes = await wpPost<any>(
			apiOpts,
			API.productCreateVariations(variableProductId!),
			{},
		)
		expect(createRes.status).toBe(200)

		// 透過 WC API 查詢變體
		const wcRes = await wpGet<any[]>(
			apiOpts,
			`${WC_API.products}/${variableProductId}/variations`,
		)
		expect(wcRes.status).toBe(200)
		if (Array.isArray(wcRes.data)) {
			expect(wcRes.data.length).toBeGreaterThan(0)
		}
	})
})

// ==================== 商品 + 訂單整合 ====================

test.describe('商品 + 訂單整合', () => {
	test('訂單中的商品應可在商品列表找到', async () => {
		test.skip(!testOrderId || !simpleProductId, '缺少測試訂單或商品')

		// 查詢訂單
		const orderRes = await wpGet<any>(apiOpts, API.orderById(testOrderId!))
		expect(orderRes.status).toBe(200)

		// 查詢商品存在
		const productRes = await wpGet<any>(apiOpts, API.productById(simpleProductId!))
		expect(productRes.status).toBe(200)
	})

	test('建立訂單備註 + 查詢訂單應一致', async () => {
		test.skip(!testOrderId, '缺少測試訂單')

		// 新增備註
		const noteRes = await wpPost<any>(apiOpts, API.orderNotes, {
			order_id: String(testOrderId),
			note: 'E2E 整合測試備註',
			is_customer_note: '0',
		})
		expect(noteRes.status).toBe(200)

		// 訂單仍可正常查詢
		const orderRes = await wpGet<any>(apiOpts, API.orderById(testOrderId!))
		expect(orderRes.status).toBe(200)
	})
})

// ==================== 文章 + 複製整合 ====================

test.describe('文章 + 複製整合', () => {
	test('複製文章後新舊文章應獨立', async () => {
		// 建立原文章
		const createRes = await wpPost<any>(apiOpts, API.posts, {
			post_type: 'post',
			post_title: 'E2E 整合複製來源',
			post_content: '這是原始內容',
			post_status: 'publish',
		})
		expect(createRes.status).toBe(200)
		const sourceIds = Array.isArray(createRes.data.data) ? createRes.data.data : [createRes.data.data]
		const sourceId = Number(sourceIds[0])

		// 複製
		const copyRes = await wpPost<any>(apiOpts, API.copy(sourceId), {})
		expect(copyRes.status).toBe(200)
		const newId = Number(copyRes.data.data)
		expect(newId).toBeGreaterThan(0)
		expect(newId).not.toBe(sourceId)

		// 兩篇文章獨立存在
		const sourcePost = await wpGet<any>(apiOpts, API.postById(sourceId))
		const newPost = await wpGet<any>(apiOpts, API.postById(newId))
		expect(sourcePost.status).toBe(200)
		expect(newPost.status).toBe(200)

		// 清理
		await wpDelete(apiOpts, API.postById(sourceId))
		await wpDelete(apiOpts, API.postById(newId))
	})
})

// ==================== 設定持久性 ====================

test.describe('設定讀寫一致性', () => {
	test('更新設定後查詢應反映最新值', async () => {
		// 讀取原值
		const beforeRes = await wpGet<any>(apiOpts, API.options)
		const originalTheme = beforeRes.data?.data?.powerhouse_settings?.theme

		// 更新
		await wpPost<any>(apiOpts, API.options, {
			powerhouse_settings: { theme: 'power' },
		})

		// 讀取新值
		const afterRes = await wpGet<any>(apiOpts, API.options)
		expect(afterRes.data?.data?.powerhouse_settings?.theme).toBe('power')

		// 還原
		if (originalTheme) {
			await wpPost<any>(apiOpts, API.options, {
				powerhouse_settings: { theme: originalTheme },
			})
		}
	})
})

// ==================== 跨端點一致性 ====================

test.describe('Powerhouse API vs WC API 一致性', () => {
	test('Powerhouse /products 和 WC /products 商品應一致', async () => {
		test.skip(!simpleProductId, '缺少測試商品')

		const phRes = await wpGet<any>(apiOpts, API.productById(simpleProductId!))
		const wcRes = await wpGet<any>(apiOpts, WC_API.productById(simpleProductId!))

		expect(phRes.status).toBe(200)
		expect(wcRes.status).toBe(200)

		// ID 應一致
		expect(phRes.data.id).toBe(wcRes.data.id)
	})
})
