/**
 * Subscriber 角色權限測試
 *
 * 驗證 subscriber 角色無法存取管理 API。
 */
import { test, expect, type BrowserContext } from '@playwright/test'
import { API, TEST_SUBSCRIBER } from '../fixtures/test-data.js'

let subscriberContext: BrowserContext
let subscriberNonce: string = ''

test.beforeAll(async ({ browser }) => {
	const baseURL = 'http://localhost:8898'
	subscriberContext = await browser.newContext()
	const page = await subscriberContext.newPage()

	// 登入 subscriber
	try {
		await page.goto(`${baseURL}/wp-login.php`)
		await page.fill('#user_login', TEST_SUBSCRIBER.username)
		await page.fill('#user_pass', TEST_SUBSCRIBER.password)
		await page.click('#wp-submit')
		await page.waitForLoadState('domcontentloaded', { timeout: 15_000 })

		// 嘗試取得 nonce
		await page.goto(`${baseURL}/wp-admin/`)
		await page.waitForLoadState('domcontentloaded', { timeout: 15_000 })
		subscriberNonce = await page.evaluate(() => (window as any).wpApiSettings?.nonce ?? '')
	} catch (e) {
		console.warn('Subscriber login warning:', e)
	}

	await page.close()
})

test.afterAll(async () => {
	await subscriberContext?.close()
})

test.describe('Subscriber 角色 API 存取', () => {
	test('subscriber 呼叫 GET /posts 應被拒絕', async () => {
		test.skip(!subscriberNonce, 'Subscriber 登入失敗')
		const baseURL = 'http://localhost:8898'
		const res = await subscriberContext.request.get(`${baseURL}/wp-json/${API.posts}`, {
			headers: { 'X-WP-Nonce': subscriberNonce },
		})
		expect([401, 403]).toContain(res.status())
	})

	test('subscriber 呼叫 POST /posts 應被拒絕', async () => {
		test.skip(!subscriberNonce, 'Subscriber 登入失敗')
		const baseURL = 'http://localhost:8898'
		const res = await subscriberContext.request.post(`${baseURL}/wp-json/${API.posts}`, {
			headers: {
				'X-WP-Nonce': subscriberNonce,
				'Content-Type': 'application/json',
			},
			data: { post_type: 'post', post_title: 'Subscriber 嘗試', post_status: 'publish' },
		})
		expect([401, 403]).toContain(res.status())
	})

	test('subscriber 呼叫 GET /options 應被拒絕', async () => {
		test.skip(!subscriberNonce, 'Subscriber 登入失敗')
		const baseURL = 'http://localhost:8898'
		const res = await subscriberContext.request.get(`${baseURL}/wp-json/${API.options}`, {
			headers: { 'X-WP-Nonce': subscriberNonce },
		})
		expect([401, 403]).toContain(res.status())
	})

	test('subscriber 呼叫 GET /plugins 應被拒絕', async () => {
		test.skip(!subscriberNonce, 'Subscriber 登入失敗')
		const baseURL = 'http://localhost:8898'
		const res = await subscriberContext.request.get(`${baseURL}/wp-json/${API.plugins}`, {
			headers: { 'X-WP-Nonce': subscriberNonce },
		})
		expect([401, 403]).toContain(res.status())
	})

	test('subscriber 呼叫 POST /limit/grant-users 應被拒絕', async () => {
		test.skip(!subscriberNonce, 'Subscriber 登入失敗')
		const baseURL = 'http://localhost:8898'
		const res = await subscriberContext.request.post(
			`${baseURL}/wp-json/${API.limitGrantUsers}`,
			{
				headers: {
					'X-WP-Nonce': subscriberNonce,
					'Content-Type': 'application/json',
				},
				data: { user_ids: [1], item_ids: [1], expire_date: '0' },
			},
		)
		expect([401, 403]).toContain(res.status())
	})

	test('subscriber 呼叫 POST /users/resetpassword 應被拒絕', async () => {
		test.skip(!subscriberNonce, 'Subscriber 登入失敗')
		const baseURL = 'http://localhost:8898'
		const res = await subscriberContext.request.post(
			`${baseURL}/wp-json/${API.resetPassword}`,
			{
				headers: {
					'X-WP-Nonce': subscriberNonce,
					'Content-Type': 'application/json',
				},
				data: { ids: ['1'] },
			},
		)
		expect([401, 403]).toContain(res.status())
	})
})
