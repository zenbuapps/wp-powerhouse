/**
 * 使用者存取控制 API 測試
 *
 * 對應 spec: 授權用戶存取.feature / 撤銷用戶存取.feature / 更新用戶存取期限.feature
 */
import { test, expect } from '@playwright/test'
import { getNonce } from '../helpers/admin-setup.js'
import { wpGet, wpPost, type ApiOptions } from '../helpers/api-client.js'
import { API, EDGE_CASES } from '../fixtures/test-data.js'
import fs from 'fs'
import path from 'path'

let apiOpts: ApiOptions
let subscriberUserId: number | null = null
let testPostId: number | null = null

test.beforeAll(async ({ request }, testInfo) => {
	const baseURL = testInfo.project.use.baseURL || 'http://localhost:8898'
	apiOpts = { request, baseURL, nonce: getNonce() }

	const idsFile = path.resolve(import.meta.dirname, '../.auth/setup-ids.json')
	if (fs.existsSync(idsFile)) {
		const ids = JSON.parse(fs.readFileSync(idsFile, 'utf-8'))
		subscriberUserId = ids.subscriberUserId ?? null
		testPostId = ids.postIds?.[0] ?? null
	}

	// 如果沒有 postId，建立一個
	if (!testPostId) {
		const createRes = await wpPost<any>(apiOpts, API.posts, {
			post_type: 'post',
			post_title: 'E2E Access Control 測試項目',
			post_status: 'publish',
		})
		const ids = Array.isArray(createRes.data?.data) ? createRes.data.data : [createRes.data?.data]
		testPostId = Number(ids[0]) || null
	}
})

// ==================== 授權用戶存取 ====================

test.describe('授權用戶存取 POST /limit/grant-users', () => {
	test('成功授權用戶存取項目（無期限）', async () => {
		test.skip(!subscriberUserId || !testPostId, '缺少測試用戶或項目')
		const res = await wpPost<any>(apiOpts, API.limitGrantUsers, {
			user_ids: [subscriberUserId],
			item_ids: [testPostId],
			expire_date: '0',
		})
		expect(res.status).toBe(200)
		expect(res.data.code).toBe('grant_users_success')
	})

	test('成功授權用戶存取項目（指定到期日）', async () => {
		test.skip(!subscriberUserId || !testPostId, '缺少測試用戶或項目')
		const futureTimestamp = Math.floor(Date.now() / 1000) + 86400 * 365 // 一年後
		const res = await wpPost<any>(apiOpts, API.limitGrantUsers, {
			user_ids: [subscriberUserId],
			item_ids: [testPostId],
			expire_date: String(futureTimestamp),
		})
		expect(res.status).toBe(200)
		expect(res.data.code).toBe('grant_users_success')
	})

	test('缺少 user_ids 應回傳 400', async () => {
		const res = await wpPost<any>(apiOpts, API.limitGrantUsers, {
			item_ids: [testPostId || 1],
			expire_date: '0',
		})
		expect(res.status).toBe(400)
	})

	test('缺少 item_ids 應回傳 400', async () => {
		const res = await wpPost<any>(apiOpts, API.limitGrantUsers, {
			user_ids: [subscriberUserId || 1],
			expire_date: '0',
		})
		expect(res.status).toBe(400)
	})
})

// ==================== 更新用戶存取期限 ====================

test.describe('更新用戶存取期限 POST /limit/update-users', () => {
	test('成功延長用戶存取期限', async () => {
		test.skip(!subscriberUserId || !testPostId, '缺少測試用戶或項目')
		const futureTimestamp = Math.floor(Date.now() / 1000) + 86400 * 730 // 兩年後
		const res = await wpPost<any>(apiOpts, API.limitUpdateUsers, {
			user_ids: [subscriberUserId],
			item_ids: [testPostId],
			timestamp: futureTimestamp,
		})
		expect(res.status).toBe(200)
		expect(res.data.code).toBe('update_users_success')
	})

	test('設定為無期限', async () => {
		test.skip(!subscriberUserId || !testPostId, '缺少測試用戶或項目')
		const res = await wpPost<any>(apiOpts, API.limitUpdateUsers, {
			user_ids: [subscriberUserId],
			item_ids: [testPostId],
			timestamp: 0,
		})
		expect(res.status).toBe(200)
		expect(res.data.code).toBe('update_users_success')
	})

	test('缺少 timestamp 應回傳 400', async () => {
		const res = await wpPost<any>(apiOpts, API.limitUpdateUsers, {
			user_ids: [subscriberUserId || 1],
			item_ids: [testPostId || 1],
		})
		expect(res.status).toBe(400)
	})
})

// ==================== 撤銷用戶存取 ====================

test.describe('撤銷用戶存取 POST /limit/revoke-users', () => {
	test('成功撤銷用戶存取', async () => {
		test.skip(!subscriberUserId || !testPostId, '缺少測試用戶或項目')

		// 先授權
		await wpPost<any>(apiOpts, API.limitGrantUsers, {
			user_ids: [subscriberUserId],
			item_ids: [testPostId],
			expire_date: '0',
		})

		// 再撤銷
		const res = await wpPost<any>(apiOpts, API.limitRevokeUsers, {
			user_ids: [subscriberUserId],
			item_ids: [testPostId],
		})
		expect(res.status).toBe(200)
		expect(res.data.code).toBe('revoke_users_success')
	})

	test('缺少 user_ids 應回傳 400', async () => {
		const res = await wpPost<any>(apiOpts, API.limitRevokeUsers, {
			item_ids: [testPostId || 1],
		})
		expect(res.status).toBe(400)
	})
})
