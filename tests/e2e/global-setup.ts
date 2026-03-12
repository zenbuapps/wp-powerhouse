/**
 * Playwright Global Setup
 *
 * 測試開始前執行：
 * 1. 套用 LC bypass（注入 'lc' => false 到 plugin.php）
 * 2. 登入 WordPress Admin
 * 3. 儲存認證狀態供後續測試使用
 * 4. 透過 REST API 停用 Coming Soon、Flush Rewrite Rules
 * 5. 清除舊 E2E 測試資料
 * 6. 建立測試資料（文章、商品、訂單、用戶）
 */
import { chromium, type FullConfig } from '@playwright/test'
import { applyLcBypass } from './helpers/lc-bypass.js'
import { loginAsAdmin, AUTH_FILE, NONCE_FILE } from './helpers/admin-setup.js'
import { extractNonce, wpGet, wpPost, wpDelete, type ApiOptions } from './helpers/api-client.js'
import { WP_ADMIN, TEST_SUBSCRIBER, TEST_SHOP_MANAGER, TEST_POST, API, WC_API, WP_API } from './fixtures/test-data.js'
import path from 'path'
import fs from 'fs'

/** 儲存 setup 時建立的資源 ID，供 teardown 或測試讀取 */
const SETUP_IDS_FILE = path.resolve(import.meta.dirname, '.auth/setup-ids.json')

interface SetupIds {
	postIds: number[]
	productIds: number[]
	variableProductId: number | null
	orderId: number | null
	subscriberUserId: number | null
	shopManagerUserId: number | null
}

async function globalSetup(config: FullConfig): Promise<void> {
	const baseURL = config.projects[0]?.use?.baseURL || 'http://localhost:8898'

	// 1. 套用 LC bypass
	console.log('[Global Setup] Applying LC bypass...')
	applyLcBypass()

	// 2. 確保 .auth 目錄存在
	const authDir = path.resolve(import.meta.dirname, '.auth')
	if (!fs.existsSync(authDir)) {
		fs.mkdirSync(authDir, { recursive: true })
	}

	// 3. 登入 WordPress Admin 並儲存 storageState + nonce
	console.log('[Global Setup] Logging in to WordPress Admin...')
	const nonce = await loginAsAdmin(baseURL)
	console.log('[Global Setup] Login successful, nonce saved.')

	// 4. 建立 API context 用於後續 REST API 操作
	const browser = await chromium.launch()
	const context = await browser.newContext({ storageState: AUTH_FILE })
	const apiOpts: ApiOptions = { request: context.request, baseURL, nonce }

	const ids: SetupIds = {
		postIds: [],
		productIds: [],
		variableProductId: null,
		orderId: null,
		subscriberUserId: null,
		shopManagerUserId: null,
	}

	try {
		// 4.1 Flush rewrite rules
		console.log('[Global Setup] Flushing rewrite rules via Permalinks page...')
		try {
			const page = await context.newPage()
			await page.goto(`${baseURL}/wp-admin/options-permalink.php`, {
				waitUntil: 'domcontentloaded',
				timeout: 30_000,
			})
			await page.click('#submit')
			await page.waitForURL(/options-permalink/, { timeout: 30_000 })
			await page.close()
			console.log('[Global Setup] Rewrite rules flushed.')
		} catch (e) {
			console.warn('[Global Setup] Flush rewrite rules warning:', e)
		}

		// 4.2 停用 WooCommerce "Coming Soon" 模式
		console.log('[Global Setup] Disabling WooCommerce Coming Soon mode...')
		try {
			await wpPost(apiOpts, WP_API.settings, { woocommerce_coming_soon: 'no' })
		} catch (e) {
			console.warn('[Global Setup] Coming Soon disable (non-fatal):', e)
		}

		// 5. 清除舊 E2E 測試資料
		console.log('[Global Setup] Cleaning old E2E test data...')
		await cleanOldTestData(apiOpts)

		// 6. 建立新測試資料
		console.log('[Global Setup] Creating fresh test data...')

		// 6.1 建立測試文章
		console.log('[Global Setup] Creating test posts...')
		try {
			const postRes = await wpPost<{ data: number[] }>(apiOpts, API.posts, {
				post_type: 'post',
				post_title: TEST_POST.title,
				post_content: TEST_POST.content,
				post_status: 'publish',
				qty: 1,
			})
			if (postRes.status === 200 && Array.isArray(postRes.data?.data)) {
				ids.postIds = postRes.data.data
				console.log(`[Global Setup] Created ${ids.postIds.length} test post(s): ${ids.postIds}`)
			} else {
				// SuccessResponse 格式: { code, message, data }
				const body = postRes.data as any
				if (body?.data && Array.isArray(body.data)) {
					ids.postIds = body.data.map(Number)
				}
				console.log(`[Global Setup] Posts response: ${JSON.stringify(postRes.data)}`)
			}
		} catch (e) {
			console.warn('[Global Setup] Post creation warning:', e)
		}

		// 6.2 建立測試商品 (simple product via WC API)
		console.log('[Global Setup] Creating test WC products...')
		try {
			const wcRes = await wpPost<{ id: number }>(apiOpts, WC_API.products, {
				name: 'E2E 測試商品',
				type: 'simple',
				regular_price: '1000',
				status: 'publish',
			})
			if (wcRes.status < 300 && (wcRes.data as any)?.id) {
				ids.productIds.push((wcRes.data as any).id)
				console.log(`[Global Setup] Created simple product: ${(wcRes.data as any).id}`)
			}
		} catch (e) {
			console.warn('[Global Setup] Simple product creation warning:', e)
		}

		// 6.3 建立可變商品 (variable product)
		console.log('[Global Setup] Creating variable product...')
		try {
			const varRes = await wpPost<{ id: number }>(apiOpts, WC_API.products, {
				name: 'E2E 可變測試商品',
				type: 'variable',
				status: 'publish',
				attributes: [
					{
						name: 'Color',
						options: ['Red', 'Blue'],
						visible: true,
						variation: true,
					},
					{
						name: 'Size',
						options: ['S', 'M'],
						visible: true,
						variation: true,
					},
				],
			})
			if (varRes.status < 300 && (varRes.data as any)?.id) {
				ids.variableProductId = (varRes.data as any).id
				ids.productIds.push(ids.variableProductId!)
				console.log(`[Global Setup] Created variable product: ${ids.variableProductId}`)
			}
		} catch (e) {
			console.warn('[Global Setup] Variable product creation warning:', e)
		}

		// 6.4 建立測試訂單
		console.log('[Global Setup] Creating test order...')
		try {
			const orderRes = await wpPost<{ id: number }>(apiOpts, WC_API.orders, {
				status: 'processing',
				line_items: ids.productIds.length > 0
					? [{ product_id: ids.productIds[0], quantity: 1 }]
					: [],
			})
			if (orderRes.status < 300 && (orderRes.data as any)?.id) {
				ids.orderId = (orderRes.data as any).id
				console.log(`[Global Setup] Created order: ${ids.orderId}`)
			}
		} catch (e) {
			console.warn('[Global Setup] Order creation warning:', e)
		}

		// 6.5 建立測試用戶（subscriber + shop_manager）
		console.log('[Global Setup] Creating test users...')
		ids.subscriberUserId = await createTestUser(apiOpts, TEST_SUBSCRIBER, 'subscriber')
		ids.shopManagerUserId = await createTestUser(apiOpts, TEST_SHOP_MANAGER, 'shop_manager')

		// 儲存建立的 ID
		fs.writeFileSync(SETUP_IDS_FILE, JSON.stringify(ids, null, 2))
		console.log('[Global Setup] Setup IDs saved.')
	} catch (error) {
		console.error('[Global Setup] Failed:', error)
		throw error
	} finally {
		await browser.close()
	}

	console.log('[Global Setup] Complete.')
}

async function cleanOldTestData(apiOpts: ApiOptions): Promise<void> {
	// 清除舊 E2E 測試文章
	try {
		const postsRes = await wpGet<any[]>(apiOpts, WP_API.posts, { per_page: '100', search: 'E2E' })
		if (postsRes.status === 200 && Array.isArray(postsRes.data)) {
			const e2ePostIds = postsRes.data
				.filter((p: any) => p.title?.rendered?.startsWith('E2E'))
				.map((p: any) => p.id)
			for (const id of e2ePostIds) {
				try {
					await wpDelete(apiOpts, `${WP_API.posts}/${id}?force=true`)
				} catch { /* ignore */ }
			}
			if (e2ePostIds.length > 0) {
				console.log(`[Global Setup] Deleted ${e2ePostIds.length} old E2E posts`)
			}
		}
	} catch (e) {
		console.warn('[Global Setup] Post cleanup warning:', e)
	}

	// 清除舊 E2E WC 商品
	try {
		const productsRes = await wpGet<any[]>(apiOpts, WC_API.products, { per_page: '100', search: 'E2E' })
		if (productsRes.status === 200 && Array.isArray(productsRes.data)) {
			const e2eProductIds = productsRes.data
				.filter((p: any) => p.name?.startsWith('E2E'))
				.map((p: any) => p.id)
			for (const id of e2eProductIds) {
				try {
					await wpDelete(apiOpts, `${WC_API.products}/${id}?force=true`)
				} catch { /* ignore */ }
			}
			if (e2eProductIds.length > 0) {
				console.log(`[Global Setup] Deleted ${e2eProductIds.length} old E2E products`)
			}
		}
	} catch (e) {
		console.warn('[Global Setup] Product cleanup warning:', e)
	}

	// 清除舊 E2E 訂單
	try {
		const ordersRes = await wpGet<any[]>(apiOpts, WC_API.orders, { per_page: '100' })
		if (ordersRes.status === 200 && Array.isArray(ordersRes.data)) {
			for (const order of ordersRes.data) {
				// 只清理包含 E2E 商品的訂單
				const hasE2E = order.line_items?.some((li: any) => li.name?.startsWith('E2E'))
				if (hasE2E) {
					try {
						await wpDelete(apiOpts, `${WC_API.orders}/${order.id}?force=true`)
					} catch { /* ignore */ }
				}
			}
		}
	} catch (e) {
		console.warn('[Global Setup] Order cleanup warning:', e)
	}
}

async function createTestUser(
	apiOpts: ApiOptions,
	userData: { username: string; password: string; email: string; firstName: string; lastName: string },
	role: string,
): Promise<number | null> {
	try {
		// 先查詢是否已存在
		const existing = await wpGet<any[]>(apiOpts, WP_API.users, { search: userData.email })
		if (existing.status === 200 && Array.isArray(existing.data) && existing.data.length > 0) {
			console.log(`[Global Setup] User ${userData.username} already exists: ${existing.data[0].id}`)
			return existing.data[0].id
		}
		// 建立新用戶
		const res = await wpPost<{ id: number }>(apiOpts, WP_API.users, {
			username: userData.username,
			password: userData.password,
			email: userData.email,
			first_name: userData.firstName,
			last_name: userData.lastName,
			roles: [role],
		})
		if (res.status < 300 && (res.data as any)?.id) {
			console.log(`[Global Setup] Created user ${userData.username}: ${(res.data as any).id}`)
			return (res.data as any).id
		}
		console.warn(`[Global Setup] User creation ${userData.username} unexpected response: ${res.status}`)
		return null
	} catch (e) {
		console.warn(`[Global Setup] User creation ${userData.username} warning:`, e)
		return null
	}
}

export default globalSetup
