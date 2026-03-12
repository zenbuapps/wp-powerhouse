/**
 * 使用者管理 + 重設密碼 API 測試
 *
 * 對應 spec: 重設密碼.feature
 */
import { test, expect } from '@playwright/test'
import { getNonce } from '../helpers/admin-setup.js'
import { wpGet, wpPost, type ApiOptions } from '../helpers/api-client.js'
import { API, EDGE_CASES } from '../fixtures/test-data.js'
import fs from 'fs'
import path from 'path'

let apiOpts: ApiOptions
let subscriberUserId: number | null = null

test.beforeAll(async ({ request }, testInfo) => {
	const baseURL = testInfo.project.use.baseURL || 'http://localhost:8898'
	apiOpts = { request, baseURL, nonce: getNonce() }

	const idsFile = path.resolve(import.meta.dirname, '../.auth/setup-ids.json')
	if (fs.existsSync(idsFile)) {
		const ids = JSON.parse(fs.readFileSync(idsFile, 'utf-8'))
		subscriberUserId = ids.subscriberUserId ?? null
	}
})

// ==================== 使用者列表 ====================

test.describe('使用者列表 GET /users', () => {
	test('應回傳使用者列表', async () => {
		const res = await wpGet<any[]>(apiOpts, API.users)
		expect(res.status).toBe(200)
		expect(Array.isArray(res.data)).toBeTruthy()
	})

	test('應包含分頁 headers', async () => {
		const res = await wpGet<any[]>(apiOpts, API.users, { number: '1' })
		expect(res.status).toBe(200)
		expect(Number(res.headers['x-wp-total'])).toBeGreaterThan(0)
	})

	test('查詢單一用戶應回傳正確資料', async () => {
		test.skip(!subscriberUserId, '沒有可用的測試用戶')
		const res = await wpGet<any>(apiOpts, API.userById(subscriberUserId!))
		expect(res.status).toBe(200)
		expect(res.data.id).toBe(subscriberUserId)
	})

	test('查詢不存在的用戶應回傳錯誤', async () => {
		const res = await wpGet<any>(apiOpts, API.userById(EDGE_CASES.nonExistentId))
		expect([400, 404]).toContain(res.status)
	})
})

// ==================== 使用者選項 ====================

test.describe('使用者選項 GET /users/options', () => {
	test('應回傳角色列表', async () => {
		const res = await wpGet<any>(apiOpts, API.userOptions)
		expect(res.status).toBe(200)
		expect(res.data).toBeTruthy()
	})
})

// ==================== 重設密碼 ====================

test.describe('重設密碼 POST /users/resetpassword', () => {
	test('成功寄送重設密碼信', async () => {
		test.skip(!subscriberUserId, '沒有可用的測試用戶')
		const res = await wpPost<any>(apiOpts, API.resetPassword, {
			ids: [String(subscriberUserId)],
		})
		expect(res.status).toBe(200)
		expect(res.data.code).toBe('resetpassword_success')
	})

	test('空 ids 陣列應回傳錯誤', async () => {
		const res = await wpPost<any>(apiOpts, API.resetPassword, {
			ids: [],
		})
		expect(res.status).toBeGreaterThanOrEqual(400)
	})

	test('不存在的用戶 ID 應回傳錯誤或靜默處理', async () => {
		const res = await wpPost<any>(apiOpts, API.resetPassword, {
			ids: [String(EDGE_CASES.nonExistentId)],
		})
		// 有些實作會靜默忽略，有些會報錯
		expect([200, 400, 404]).toContain(res.status)
	})

	test('批量重設多個用戶密碼', async () => {
		test.skip(!subscriberUserId, '沒有可用的測試用戶')
		const res = await wpPost<any>(apiOpts, API.resetPassword, {
			ids: [String(subscriberUserId), '1'],
		})
		expect(res.status).toBe(200)
	})
})
