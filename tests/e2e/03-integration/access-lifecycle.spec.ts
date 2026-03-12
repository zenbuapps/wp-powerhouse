/**
 * 存取控制生命週期整合測試
 *
 * 完整測試：授權 → 更新期限 → 撤銷的完整流程
 */
import { test, expect } from '@playwright/test'
import { getNonce } from '../helpers/admin-setup.js'
import { wpGet, wpPost, type ApiOptions } from '../helpers/api-client.js'
import { API, WC_API } from '../fixtures/test-data.js'
import fs from 'fs'
import path from 'path'

let apiOpts: ApiOptions
let subscriberUserId: number | null = null
let testItemId: number | null = null

test.beforeAll(async ({ request }, testInfo) => {
	const baseURL = testInfo.project.use.baseURL || 'http://localhost:8898'
	apiOpts = { request, baseURL, nonce: getNonce() }

	const idsFile = path.resolve(import.meta.dirname, '../.auth/setup-ids.json')
	if (fs.existsSync(idsFile)) {
		const ids = JSON.parse(fs.readFileSync(idsFile, 'utf-8'))
		subscriberUserId = ids.subscriberUserId ?? null
		testItemId = ids.postIds?.[0] ?? null
	}
})

test.describe('存取控制完整生命週期', () => {
	test('授權 → 更新期限 → 撤銷完整流程', async () => {
		test.skip(!subscriberUserId || !testItemId, '缺少測試用戶或項目')

		// Step 1: 授權存取（無期限）
		const grantRes = await wpPost<any>(apiOpts, API.limitGrantUsers, {
			user_ids: [subscriberUserId],
			item_ids: [testItemId],
			expire_date: '0',
		})
		expect(grantRes.status).toBe(200)
		expect(grantRes.data.code).toBe('grant_users_success')

		// Step 2: 更新為有期限
		const futureTimestamp = Math.floor(Date.now() / 1000) + 86400 * 90 // 90 天後
		const updateRes = await wpPost<any>(apiOpts, API.limitUpdateUsers, {
			user_ids: [subscriberUserId],
			item_ids: [testItemId],
			timestamp: futureTimestamp,
		})
		expect(updateRes.status).toBe(200)
		expect(updateRes.data.code).toBe('update_users_success')

		// Step 3: 撤銷存取
		const revokeRes = await wpPost<any>(apiOpts, API.limitRevokeUsers, {
			user_ids: [subscriberUserId],
			item_ids: [testItemId],
		})
		expect(revokeRes.status).toBe(200)
		expect(revokeRes.data.code).toBe('revoke_users_success')
	})

	test('重複授權同一用戶不應報錯', async () => {
		test.skip(!subscriberUserId || !testItemId, '缺少測試用戶或項目')

		// 授權第一次
		await wpPost<any>(apiOpts, API.limitGrantUsers, {
			user_ids: [subscriberUserId],
			item_ids: [testItemId],
			expire_date: '0',
		})

		// 授權第二次（應覆蓋或靜默處理）
		const res = await wpPost<any>(apiOpts, API.limitGrantUsers, {
			user_ids: [subscriberUserId],
			item_ids: [testItemId],
			expire_date: '0',
		})
		expect(res.status).toBe(200)

		// 清理
		await wpPost<any>(apiOpts, API.limitRevokeUsers, {
			user_ids: [subscriberUserId],
			item_ids: [testItemId],
		})
	})

	test('撤銷不存在的授權不應報錯', async () => {
		test.skip(!subscriberUserId, '缺少測試用戶')

		const res = await wpPost<any>(apiOpts, API.limitRevokeUsers, {
			user_ids: [subscriberUserId],
			item_ids: [999998], // 不存在的項目
		})
		// 應該成功或靜默處理
		expect([200, 400]).toContain(res.status)
	})
})

test.describe('商品權限綁定整合流程', () => {
	test('綁定 → 更新 → 解綁完整流程', async () => {
		const idsFile = path.resolve(import.meta.dirname, '../.auth/setup-ids.json')
		let productId: number | null = null
		let itemId: number | null = null

		if (fs.existsSync(idsFile)) {
			const ids = JSON.parse(fs.readFileSync(idsFile, 'utf-8'))
			productId = ids.productIds?.[0] ?? null
			itemId = ids.postIds?.[0] ?? null
		}
		test.skip(!productId || !itemId, '缺少測試商品或項目')

		// Step 1: 綁定（無期限）
		const bindRes = await wpPost<any>(apiOpts, API.productBindItems, {
			product_ids: [productId],
			item_ids: [itemId],
			limit_type: 'unlimited',
			meta_key: 'bound_items_data',
		})
		expect(bindRes.status).toBe(200)

		// Step 2: 更新綁定（改為固定期限）
		const updateRes = await wpPost<any>(apiOpts, API.productUpdateBoundItems, {
			product_ids: [productId],
			item_ids: [itemId],
			limit_type: 'fixed',
			limit_value: 60,
			limit_unit: 'day',
			meta_key: 'bound_items_data',
		})
		expect(updateRes.status).toBe(200)

		// Step 3: 解綁
		const unbindRes = await wpPost<any>(apiOpts, API.productUnbindItems, {
			product_ids: [productId],
			item_ids: [itemId],
			meta_key: 'bound_items_data',
		})
		expect(unbindRes.status).toBe(200)
	})
})
