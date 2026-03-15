/**
 * Playwright Global Setup
 *
 * 測試開始前執行：
 * 1. 套用 LC bypass（注入 'lc' => false 到 plugin.php）
 * 2. 登入 WordPress Admin
 * 3. 儲存認證狀態供後續測試使用
 * 4. Flush rewrite rules
 * 5. 停用 WooCommerce Coming Soon 模式
 * 6. 清除舊 E2E 測試資料（避免 slug 衝突）
 * 7. 建立新測試資料（文章、商品、訂單、用戶）
 * 8. 將建立的 ID 儲存至 .auth/setup-ids.json
 */
import { chromium, type FullConfig } from '@playwright/test'
import { applyLcBypass } from './helpers/lc-bypass.js'
import { loginAsAdmin, AUTH_FILE, NONCE_FILE } from './helpers/admin-setup.js'
import {
  extractNonce,
  wpGet,
  wpPost,
  wpDelete,
  type ApiOptions,
} from './helpers/api-client.js'
import {
  WP_ADMIN,
  TEST_SUBSCRIBER,
  TEST_SHOP_MANAGER,
  TEST_POST,
  API,
  WC_API,
  WP_API,
} from './fixtures/test-data.js'
import path from 'path'
import fs from 'fs'

/** 儲存 setup 時建立的資源 ID，供測試讀取 */
const SETUP_IDS_FILE = path.resolve(import.meta.dirname, '.auth/setup-ids.json')

export interface SetupIds {
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
  console.log('[Global Setup] 套用 LC bypass...')
  applyLcBypass()

  // 2. 確保 .auth 目錄存在
  const authDir = path.resolve(import.meta.dirname, '.auth')
  if (!fs.existsSync(authDir)) {
    fs.mkdirSync(authDir, { recursive: true })
  }

  // 3. 登入 WordPress Admin 並儲存 storageState + nonce
  console.log('[Global Setup] 登入 WordPress Admin...')
  const nonce = await loginAsAdmin(baseURL)
  console.log('[Global Setup] 登入成功，nonce 已儲存')

  // 4. 建立 browser context 用於後續 REST API 操作
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
    // 4.1 Flush rewrite rules（避免 404 問題）
    console.log('[Global Setup] 刷新永久連結規則...')
    try {
      const page = await context.newPage()
      await page.goto(`${baseURL}/wp-admin/options-permalink.php`, {
        waitUntil: 'domcontentloaded',
        timeout: 30_000,
      })
      await page.click('#submit')
      await page.waitForURL(/options-permalink/, { timeout: 30_000 })
      await page.close()
      console.log('[Global Setup] 永久連結已刷新')
    } catch (e) {
      console.warn('[Global Setup] 刷新永久連結失敗（非致命）:', e)
    }

    // 4.2 停用 WooCommerce "Coming Soon" 模式
    console.log('[Global Setup] 停用 WooCommerce Coming Soon 模式...')
    try {
      await wpPost(apiOpts, WP_API.settings, { woocommerce_coming_soon: 'no' })
    } catch (e) {
      console.warn('[Global Setup] 停用 Coming Soon 失敗（非致命）:', e)
    }

    // 5. 清除舊 E2E 測試資料
    console.log('[Global Setup] 清除舊 E2E 測試資料...')
    await cleanOldTestData(apiOpts)

    // 6. 建立測試文章
    console.log('[Global Setup] 建立測試文章...')
    try {
      const postRes = await wpPost<any>(apiOpts, API.posts, {
        post_type: 'post',
        post_title: TEST_POST.title,
        post_content: TEST_POST.content,
        post_status: 'publish',
        qty: 1,
      })
      // Powerhouse 回傳格式: { code, message, data: number[] }
      const body = postRes.data as any
      if (Array.isArray(body?.data)) {
        ids.postIds = body.data.map(Number)
      }
      console.log(`[Global Setup] 建立文章：${ids.postIds}`)
    } catch (e) {
      console.warn('[Global Setup] 建立文章失敗（非致命）:', e)
    }

    // 6.2 建立簡單商品
    console.log('[Global Setup] 建立測試商品（simple）...')
    try {
      const wcRes = await wpPost<any>(apiOpts, WC_API.products, {
        name: 'E2E PH 測試商品',
        type: 'simple',
        regular_price: '1000',
        status: 'publish',
      })
      const pid = Number((wcRes.data as any)?.id)
      if (pid) {
        ids.productIds.push(pid)
        console.log(`[Global Setup] 建立簡單商品：${pid}`)
      }
    } catch (e) {
      console.warn('[Global Setup] 建立商品失敗（非致命）:', e)
    }

    // 6.3 建立可變商品
    console.log('[Global Setup] 建立可變商品...')
    try {
      const varRes = await wpPost<any>(apiOpts, WC_API.products, {
        name: 'E2E PH 可變測試商品',
        type: 'variable',
        status: 'publish',
        attributes: [
          { name: 'Color', options: ['Red', 'Blue'], visible: true, variation: true },
          { name: 'Size', options: ['S', 'M'], visible: true, variation: true },
        ],
      })
      const vid = Number((varRes.data as any)?.id)
      if (vid) {
        ids.variableProductId = vid
        ids.productIds.push(vid)
        console.log(`[Global Setup] 建立可變商品：${vid}`)
      }
    } catch (e) {
      console.warn('[Global Setup] 建立可變商品失敗（非致命）:', e)
    }

    // 6.4 建立測試訂單
    console.log('[Global Setup] 建立測試訂單...')
    try {
      const orderRes = await wpPost<any>(apiOpts, WC_API.orders, {
        status: 'processing',
        line_items:
          ids.productIds.length > 0
            ? [{ product_id: ids.productIds[0], quantity: 1 }]
            : [],
      })
      const oid = Number((orderRes.data as any)?.id)
      if (oid) {
        ids.orderId = oid
        console.log(`[Global Setup] 建立訂單：${oid}`)
      }
    } catch (e) {
      console.warn('[Global Setup] 建立訂單失敗（非致命）:', e)
    }

    // 6.5 建立測試用戶
    console.log('[Global Setup] 建立測試用戶...')
    ids.subscriberUserId = await ensureTestUser(apiOpts, TEST_SUBSCRIBER, 'subscriber')
    ids.shopManagerUserId = await ensureTestUser(apiOpts, TEST_SHOP_MANAGER, 'shop_manager')

    // 儲存 ID
    fs.writeFileSync(SETUP_IDS_FILE, JSON.stringify(ids, null, 2))
    console.log('[Global Setup] Setup IDs 已儲存:', ids)
  } catch (error) {
    console.error('[Global Setup] 失敗:', error)
    throw error
  } finally {
    await browser.close()
  }

  console.log('[Global Setup] 完成')
}

/**
 * 清除舊 E2E 測試資料，避免 slug 衝突
 */
async function cleanOldTestData(apiOpts: ApiOptions): Promise<void> {
  // 清除舊 E2E 文章（透過 WP REST API 強制刪除）
  try {
    const postsRes = await wpGet<any[]>(apiOpts, WP_API.posts, {
      per_page: '100',
      search: 'E2E PH',
    })
    if (postsRes.status === 200 && Array.isArray(postsRes.data)) {
      const e2eIds = postsRes.data
        .filter((p: any) => p.title?.rendered?.includes('E2E PH'))
        .map((p: any) => p.id)
      for (const id of e2eIds) {
        try {
          await apiOpts.request.delete(
            `${apiOpts.baseURL}/wp-json/wp/v2/posts/${id}?force=true`,
            {
              headers: { 'X-WP-Nonce': apiOpts.nonce },
            },
          )
        } catch { /* 忽略單筆刪除失敗 */ }
      }
      if (e2eIds.length > 0) {
        console.log(`[Global Setup] 已清除 ${e2eIds.length} 篇舊 E2E 文章`)
      }
    }
  } catch (e) {
    console.warn('[Global Setup] 清除文章失敗（非致命）:', e)
  }

  // 清除舊 E2E WC 商品
  try {
    const productsRes = await wpGet<any[]>(apiOpts, WC_API.products, {
      per_page: '100',
      search: 'E2E PH',
    })
    if (productsRes.status === 200 && Array.isArray(productsRes.data)) {
      const e2eIds = productsRes.data
        .filter((p: any) => p.name?.includes('E2E PH'))
        .map((p: any) => p.id)
      for (const id of e2eIds) {
        try {
          await apiOpts.request.delete(
            `${apiOpts.baseURL}/wp-json/wc/v3/products/${id}?force=true`,
            {
              headers: { 'X-WP-Nonce': apiOpts.nonce },
            },
          )
        } catch { /* 忽略單筆刪除失敗 */ }
      }
      if (e2eIds.length > 0) {
        console.log(`[Global Setup] 已清除 ${e2eIds.length} 個舊 E2E 商品`)
      }
    }
  } catch (e) {
    console.warn('[Global Setup] 清除商品失敗（非致命）:', e)
  }

  // 清除舊 E2E 詞彙（product_cat）
  try {
    const termsRes = await wpGet<any[]>(apiOpts, 'v2/powerhouse/terms/product_cat', {
      per_page: '100',
    })
    if (termsRes.status === 200 && Array.isArray(termsRes.data)) {
      const e2eIds = termsRes.data
        .filter((t: any) => (t.name as string)?.includes('E2E PH'))
        .map((t: any) => t.term_id || t.id)
      for (const id of e2eIds) {
        try {
          await wpDelete(apiOpts, `v2/powerhouse/terms/product_cat/${id}`)
        } catch { /* 忽略 */ }
      }
      if (e2eIds.length > 0) {
        console.log(`[Global Setup] 已清除 ${e2eIds.length} 個舊 E2E 詞彙`)
      }
    }
  } catch (e) {
    console.warn('[Global Setup] 清除詞彙失敗（非致命）:', e)
  }
}

/**
 * 冪等建立測試用戶（若已存在則回傳現有 ID）
 */
async function ensureTestUser(
  apiOpts: ApiOptions,
  userData: {
    username: string
    password: string
    email: string
    firstName: string
    lastName: string
  },
  role: string,
): Promise<number | null> {
  try {
    // 先查詢是否已存在
    const existing = await wpGet<any[]>(apiOpts, WP_API.users, {
      search: userData.email,
      context: 'edit',
    })
    if (
      existing.status === 200 &&
      Array.isArray(existing.data) &&
      existing.data.length > 0
    ) {
      const existingId = (existing.data as any[])[0].id
      console.log(`[Global Setup] 用戶 ${userData.username} 已存在：${existingId}`)
      return existingId
    }

    // 建立新用戶
    const res = await wpPost<any>(apiOpts, WP_API.users, {
      username: userData.username,
      password: userData.password,
      email: userData.email,
      first_name: userData.firstName,
      last_name: userData.lastName,
      roles: [role],
    })
    const uid = Number((res.data as any)?.id)
    if (uid) {
      console.log(`[Global Setup] 建立用戶 ${userData.username}：${uid}`)
      return uid
    }
    console.warn(`[Global Setup] 用戶建立回應異常：${JSON.stringify(res.data)}`)
    return null
  } catch (e) {
    console.warn(`[Global Setup] 建立用戶 ${userData.username} 失敗（非致命）:`, e)
    return null
  }
}

export default globalSetup
