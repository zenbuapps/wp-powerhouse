/**
 * REST API Client — 封裝 WordPress / WooCommerce / Powerhouse API 操作
 *
 * 提供統一的 HTTP 方法包裝，自動帶上 nonce，支援非 2xx 時靜默回傳。
 * 此版本擴展自原始版本，加入更多便利方法。
 */
import type { APIRequestContext } from '@playwright/test'

export type ApiOptions = {
  request: APIRequestContext
  baseURL: string
  nonce: string
}

/** 產生帶 nonce 的請求 headers */
const headers = (nonce: string) => ({
  'X-WP-Nonce': nonce,
  'Content-Type': 'application/json',
})

/** 通用 API 回應型別 */
export interface ApiResponse<T = unknown> {
  data: T
  status: number
  headers: Record<string, string>
}

/**
 * GET 請求
 */
export async function wpGet<T = unknown>(
  opts: ApiOptions,
  endpoint: string,
  params?: Record<string, string>,
): Promise<ApiResponse<T>> {
  const url = new URL(`${opts.baseURL}/wp-json/${endpoint}`)
  if (params) Object.entries(params).forEach(([k, v]) => url.searchParams.set(k, v))
  const res = await opts.request.get(url.toString(), { headers: headers(opts.nonce) })
  const data = await res.json().catch(() => ({}) as T)
  return {
    data: data as T,
    status: res.status(),
    headers: Object.fromEntries(res.headersArray().map((h) => [h.name.toLowerCase(), h.value])),
  }
}

/**
 * POST 請求（JSON body）
 */
export async function wpPost<T = unknown>(
  opts: ApiOptions,
  endpoint: string,
  data: Record<string, unknown>,
): Promise<ApiResponse<T>> {
  const res = await opts.request.post(`${opts.baseURL}/wp-json/${endpoint}`, {
    headers: headers(opts.nonce),
    data,
  })
  const body = await res.json().catch(() => ({}) as T)
  return {
    data: body as T,
    status: res.status(),
    headers: Object.fromEntries(res.headersArray().map((h) => [h.name.toLowerCase(), h.value])),
  }
}

/**
 * PUT 請求（JSON body）
 */
export async function wpPut<T = unknown>(
  opts: ApiOptions,
  endpoint: string,
  data: Record<string, unknown>,
): Promise<ApiResponse<T>> {
  const res = await opts.request.put(`${opts.baseURL}/wp-json/${endpoint}`, {
    headers: headers(opts.nonce),
    data,
  })
  const body = await res.json().catch(() => ({}) as T)
  return {
    data: body as T,
    status: res.status(),
    headers: Object.fromEntries(res.headersArray().map((h) => [h.name.toLowerCase(), h.value])),
  }
}

/**
 * DELETE 請求
 * 支援帶 body（用於批量刪除）
 */
export async function wpDelete<T = unknown>(
  opts: ApiOptions,
  endpoint: string,
  body?: Record<string, unknown>,
): Promise<ApiResponse<T>> {
  const res = await opts.request.delete(`${opts.baseURL}/wp-json/${endpoint}`, {
    headers: headers(opts.nonce),
    data: body,
  })
  const data = await res.json().catch(() => ({}) as T)
  return {
    data: data as T,
    status: res.status(),
    headers: Object.fromEntries(res.headersArray().map((h) => [h.name.toLowerCase(), h.value])),
  }
}

/**
 * 從 wp-admin 頁面提取 WP REST Nonce
 */
export async function extractNonce(
  page: import('@playwright/test').Page,
  baseURL: string,
): Promise<string> {
  await page.goto(`${baseURL}/wp-admin/`)
  await page.waitForLoadState('domcontentloaded')
  const nonce = await page.evaluate(() => (window as any).wpApiSettings?.nonce ?? '')
  if (!nonce) {
    throw new Error('無法提取 WP REST nonce，請確認管理員已登入')
  }
  return nonce
}

/**
 * 建立不帶認證的 ApiOptions（測試無權限存取）
 */
export function unauthOpts(opts: ApiOptions): ApiOptions {
  return { ...opts, nonce: '' }
}

// ─────────────────────────────────────────────────────────
// 便利方法：Powerhouse 常用操作
// ─────────────────────────────────────────────────────────

/**
 * 建立 Powerhouse 文章，回傳第一個 ID
 */
export async function createPHPost(
  opts: ApiOptions,
  overrides: Record<string, unknown> = {},
): Promise<number> {
  const res = await wpPost<any>(opts, 'v2/powerhouse/posts', {
    post_type: 'post',
    post_title: 'E2E PH 測試文章',
    post_status: 'draft',
    ...overrides,
  })
  const ids: number[] = Array.isArray(res.data?.data) ? res.data.data : [res.data?.data]
  const id = Number(ids[0])
  if (!id || isNaN(id)) throw new Error(`建立文章失敗：${JSON.stringify(res.data)}`)
  return id
}

/**
 * 建立 WooCommerce 商品，回傳 ID
 */
export async function createWCProduct(
  opts: ApiOptions,
  overrides: Record<string, unknown> = {},
): Promise<number> {
  const res = await wpPost<any>(opts, 'wc/v3/products', {
    name: 'E2E PH 測試商品',
    type: 'simple',
    regular_price: '500',
    status: 'publish',
    ...overrides,
  })
  const id = Number((res.data as any)?.id)
  if (!id || isNaN(id)) throw new Error(`建立商品失敗：${JSON.stringify(res.data)}`)
  return id
}

/**
 * 建立 WooCommerce 訂單，回傳 ID
 */
export async function createWCOrder(
  opts: ApiOptions,
  overrides: Record<string, unknown> = {},
): Promise<number> {
  const res = await wpPost<any>(opts, 'wc/v3/orders', {
    status: 'pending',
    ...overrides,
  })
  const id = Number((res.data as any)?.id)
  if (!id || isNaN(id)) throw new Error(`建立訂單失敗：${JSON.stringify(res.data)}`)
  return id
}

/**
 * 建立 WordPress 用戶，回傳 ID
 */
export async function createWPUser(
  opts: ApiOptions,
  overrides: Record<string, unknown> = {},
): Promise<number> {
  const timestamp = Date.now()
  const res = await wpPost<any>(opts, 'wp/v2/users', {
    username: `e2e_ph_tmp_${timestamp}`,
    email: `e2e_ph_tmp_${timestamp}@test.local`,
    password: 'e2e_tmp_pass_123',
    roles: ['subscriber'],
    ...overrides,
  })
  const id = Number((res.data as any)?.id)
  if (!id || isNaN(id)) throw new Error(`建立用戶失敗：${JSON.stringify(res.data)}`)
  return id
}

/**
 * 建立詞彙，回傳第一個 term_id
 */
export async function createPHTerm(
  opts: ApiOptions,
  taxonomy: string,
  overrides: Record<string, unknown> = {},
): Promise<number> {
  const res = await wpPost<any>(opts, `v2/powerhouse/terms/${taxonomy}`, {
    name: 'E2E PH 測試詞彙',
    ...overrides,
  })
  const ids: number[] = Array.isArray(res.data?.data) ? res.data.data : [res.data?.data]
  const id = Number(ids[0])
  if (!id || isNaN(id)) throw new Error(`建立詞彙失敗：${JSON.stringify(res.data)}`)
  return id
}

/**
 * 強制刪除 WP 文章（bypass trash）
 */
export async function forceDeletePost(opts: ApiOptions, id: number): Promise<void> {
  await opts.request.delete(`${opts.baseURL}/wp-json/wp/v2/posts/${id}?force=true`, {
    headers: headers(opts.nonce),
  })
}

/**
 * 強制刪除 WC 商品
 */
export async function forceDeleteProduct(opts: ApiOptions, id: number): Promise<void> {
  await opts.request.delete(`${opts.baseURL}/wp-json/wc/v3/products/${id}?force=true`, {
    headers: headers(opts.nonce),
  })
}
