/**
 * 設定管理 API 測試
 *
 * 對應 spec: 查詢設定.feature / 更新設定.feature
 */
import { test, expect } from '@playwright/test'
import { getNonce } from '../helpers/admin-setup.js'
import { wpGet, wpPost, type ApiOptions } from '../helpers/api-client.js'
import { API } from '../fixtures/test-data.js'

let apiOpts: ApiOptions
let originalSettings: any = null

test.beforeAll(async ({ request }, testInfo) => {
	const baseURL = testInfo.project.use.baseURL || 'http://localhost:8898'
	apiOpts = { request, baseURL, nonce: getNonce() }

	// 先備份原始設定
	const res = await wpGet<any>(apiOpts, API.options)
	if (res.status === 200) {
		originalSettings = res.data?.data?.powerhouse_settings ?? null
	}
})

test.afterAll(async () => {
	// 還原設定
	if (originalSettings) {
		try {
			await wpPost(apiOpts, API.options, { powerhouse_settings: originalSettings })
		} catch { /* ignore */ }
	}
})

// ==================== 查詢設定 ====================

test.describe('查詢設定 GET /options', () => {
	test('應回傳 200 且 code 為 get_options_success', async () => {
		const res = await wpGet<any>(apiOpts, API.options)
		expect(res.status).toBe(200)
		expect(res.data.code).toBe('get_options_success')
	})

	test('回傳應包含 powerhouse_settings 物件', async () => {
		const res = await wpGet<any>(apiOpts, API.options)
		expect(res.status).toBe(200)
		const settings = res.data.data?.powerhouse_settings
		expect(settings).toBeTruthy()
		expect(typeof settings).toBe('object')
	})

	test('powerhouse_settings 應包含預設欄位', async () => {
		const res = await wpGet<any>(apiOpts, API.options)
		expect(res.status).toBe(200)
		const settings = res.data.data?.powerhouse_settings
		expect(settings).toBeTruthy()

		// 驗證 spec 中提到的預設欄位存在
		const expectedFields = [
			'enable_captcha_login',
			'enable_captcha_register',
			'delay_email',
			'theme',
			'enable_theme',
		]
		for (const field of expectedFields) {
			expect(settings).toHaveProperty(field)
		}
	})
})

// ==================== 更新設定 ====================

test.describe('更新設定 POST /options', () => {
	test('部分更新 powerhouse_settings 應成功', async () => {
		const res = await wpPost<any>(apiOpts, API.options, {
			powerhouse_settings: {
				enable_captcha_login: 'yes',
			},
		})
		expect(res.status).toBe(200)

		// 驗證更新後的值
		const getRes = await wpGet<any>(apiOpts, API.options)
		expect(getRes.data.data?.powerhouse_settings?.enable_captcha_login).toBe('yes')
	})

	test('部分更新不應影響其他設定', async () => {
		// 取得當前 delay_email 值
		const beforeRes = await wpGet<any>(apiOpts, API.options)
		const beforeDelayEmail = beforeRes.data.data?.powerhouse_settings?.delay_email

		// 只更新 enable_captcha_register
		await wpPost<any>(apiOpts, API.options, {
			powerhouse_settings: {
				enable_captcha_register: 'yes',
			},
		})

		// 驗證 delay_email 未被改變
		const afterRes = await wpGet<any>(apiOpts, API.options)
		expect(afterRes.data.data?.powerhouse_settings?.delay_email).toBe(beforeDelayEmail)
	})

	test('傳入未註冊的欄位應被忽略', async () => {
		const res = await wpPost<any>(apiOpts, API.options, {
			unknown_totally_fake_field: 'should_be_ignored',
		})
		expect(res.status).toBe(200)
	})

	test('更新後再查詢應反映最新值', async () => {
		const newTheme = 'power'
		await wpPost<any>(apiOpts, API.options, {
			powerhouse_settings: { theme: newTheme },
		})

		const res = await wpGet<any>(apiOpts, API.options)
		expect(res.data.data?.powerhouse_settings?.theme).toBe(newTheme)
	})
})
