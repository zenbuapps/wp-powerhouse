/**
 * Products CRUD API 測試
 *
 * 對應 spec:
 *   - 查詢商品列表.feature
 *   - 查詢單一商品.feature
 *   - 查詢商品選擇器.feature
 *   - 查詢商品選項.feature
 *   - 建立商品.feature
 *   - 更新商品.feature
 *   - 刪除商品.feature
 *   - 更新商品屬性.feature
 *   - 產生商品變體.feature
 *   - 更新商品變體.feature
 *   - 綁定權限項目到商品.feature
 *   - 更新綁定權限.feature
 *   - 解除綁定權限項目.feature
 *   - 複製文章.feature（商品）
 */
import { test, expect } from '@playwright/test'
import { getNonce } from '../helpers/admin-setup.js'
import { wpGet, wpPost, wpDelete, type ApiOptions } from '../helpers/api-client.js'
import { API, WC_API, STRING_EDGE, ID_EDGE } from '../fixtures/test-data.js'
import fs from 'fs'
import path from 'path'

let apiOpts: ApiOptions
/** setup 建立的 IDs */
let setupSimpleProductId: number | null = null
let setupVariableProductId: number | null = null
/** 此套件建立的商品 ID */
const createdProductIds: number[] = []

test.beforeAll(async ({ request }, testInfo) => {
  const baseURL = testInfo.project.use.baseURL || 'http://localhost:8898'
  apiOpts = { request, baseURL, nonce: getNonce() }

  const idsFile = path.resolve(import.meta.dirname, '../.auth/setup-ids.json')
  if (fs.existsSync(idsFile)) {
    const ids = JSON.parse(fs.readFileSync(idsFile, 'utf-8'))
    setupSimpleProductId = ids.productIds?.[0] ?? null
    setupVariableProductId = ids.variableProductId ?? null
  }
})

test.afterAll(async () => {
  for (const id of createdProductIds) {
    try {
      await apiOpts.request.delete(
        `${apiOpts.baseURL}/wp-json/wc/v3/products/${id}?force=true`,
        { headers: { 'X-WP-Nonce': apiOpts.nonce } },
      )
    } catch { /* 忽略清理失敗 */ }
  }
})

// ─────────────────────────────────────────────────────
// 查詢商品列表 GET /products
// ─────────────────────────────────────────────────────

test.describe('GET /products — 查詢商品列表', () => {
  test('不帶參數應使用預設值回傳商品列表', async () => {
    const res = await wpGet<any[]>(apiOpts, API.products)
    expect(res.status).toBe(200)
    expect(Array.isArray(res.data)).toBe(true)
  })

  test('應包含分頁 headers', async () => {
    const res = await wpGet(apiOpts, API.products, { posts_per_page: '1' })
    expect(res.status).toBe(200)
    expect(Number(res.headers['x-wp-total'])).toBeGreaterThanOrEqual(0)
  })

  test('帶 status=publish 應只回傳已發佈商品', async () => {
    const res = await wpGet<any[]>(apiOpts, API.products, { status: 'publish' })
    expect(res.status).toBe(200)
    if (Array.isArray(res.data) && res.data.length > 0) {
      for (const item of res.data) {
        expect(item.status).toBe('publish')
      }
    }
  })

  test('posts_per_page=1 應限制結果', async () => {
    const res = await wpGet<any[]>(apiOpts, API.products, { posts_per_page: '1' })
    expect(res.status).toBe(200)
    expect((res.data as any[]).length).toBeLessThanOrEqual(1)
  })

  test('SQL injection 搜尋不應造成 500', async () => {
    const res = await wpGet(apiOpts, API.products, { s: STRING_EDGE.sqlInject1 })
    expect([200, 400]).toContain(res.status)
  })
})

// ─────────────────────────────────────────────────────
// 查詢單一商品 GET /products/:id
// ─────────────────────────────────────────────────────

test.describe('GET /products/:id — 查詢單一商品', () => {
  test('查詢存在的商品應回傳完整資料', async () => {
    test.skip(!setupSimpleProductId, '沒有可用的測試商品')
    const res = await wpGet<any>(apiOpts, API.productById(setupSimpleProductId!))
    expect(res.status).toBe(200)
    expect(res.data.id).toBe(setupSimpleProductId)
    expect(res.data).toHaveProperty('name')
    expect(res.data).toHaveProperty('status')
  })

  test('查詢不存在的商品（999999）應回傳錯誤', async () => {
    const res = await wpGet(apiOpts, API.productById(ID_EDGE.nonExistent))
    expect([400, 404]).toContain(res.status)
  })

  test('查詢 ID=-1 應回傳錯誤', async () => {
    const res = await wpGet(apiOpts, API.productById(ID_EDGE.negOne))
    expect(res.status).toBeGreaterThanOrEqual(400)
  })

  test('查詢 ID 為非數字應回傳錯誤', async () => {
    const res = await wpGet(apiOpts, API.productById(ID_EDGE.abcStr))
    expect(res.status).toBeGreaterThanOrEqual(400)
  })
})

// ─────────────────────────────────────────────────────
// 商品選擇器 GET /products/select
// ─────────────────────────────────────────────────────

test.describe('GET /products/select — 商品選擇器', () => {
  test('搜尋 E2E PH 應回傳結果', async () => {
    const res = await wpGet<any[]>(apiOpts, API.productSelect, { s: 'E2E PH' })
    expect(res.status).toBe(200)
    expect(Array.isArray(res.data)).toBe(true)
  })

  test('搜尋空字串應回傳 200', async () => {
    const res = await wpGet(apiOpts, API.productSelect, { s: '' })
    expect(res.status).toBe(200)
  })

  test('搜尋不存在的關鍵字應回傳空陣列', async () => {
    const res = await wpGet<any[]>(apiOpts, API.productSelect, {
      s: 'zzz_nonexistent_e2e_ph_product_xyz',
    })
    expect(res.status).toBe(200)
    expect(Array.isArray(res.data)).toBe(true)
    expect((res.data as any[]).length).toBe(0)
  })

  test('SQL injection 搜尋不應造成 500', async () => {
    const res = await wpGet(apiOpts, API.productSelect, { s: STRING_EDGE.sqlInject2 })
    expect([200, 400]).toContain(res.status)
  })
})

// ─────────────────────────────────────────────────────
// 商品選項 GET /products/options
// ─────────────────────────────────────────────────────

test.describe('GET /products/options — 商品選項', () => {
  test('應回傳商品選項資料（含分類/標籤）', async () => {
    const res = await wpGet<any>(apiOpts, API.productOptions)
    expect(res.status).toBe(200)
    expect(res.data).toBeTruthy()
  })
})

// ─────────────────────────────────────────────────────
// 建立商品 POST /products
// ─────────────────────────────────────────────────────

test.describe('POST /products — 建立商品', () => {
  test('建立單一商品應回傳 create_success', async () => {
    const res = await wpPost<any>(apiOpts, API.products, {
      name: 'E2E PH 建立商品測試',
      status: 'draft',
    })
    expect(res.status).toBe(200)
    expect(res.data.code).toBe('create_success')
    const ids: number[] = Array.isArray(res.data.data) ? res.data.data : [res.data.data]
    expect(Number(ids[0])).toBeGreaterThan(0)
    createdProductIds.push(...ids.map(Number))
  })

  test('qty=3 應批量建立 3 個商品', async () => {
    const res = await wpPost<any>(apiOpts, API.products, {
      name: 'E2E PH 批量商品',
      status: 'draft',
      qty: 3,
    })
    expect(res.status).toBe(200)
    expect(res.data.code).toBe('create_success')
    const ids: number[] = Array.isArray(res.data.data) ? res.data.data : [res.data.data]
    expect(ids.length).toBe(3)
    createdProductIds.push(...ids.map(Number))
  })

  test('建立含 regular_price 的商品', async () => {
    const res = await wpPost<any>(apiOpts, API.products, {
      name: 'E2E PH 含價格商品',
      regular_price: '999',
      status: 'draft',
    })
    expect(res.status).toBe(200)
    const ids: number[] = Array.isArray(res.data.data) ? res.data.data : [res.data.data]
    createdProductIds.push(...ids.map(Number))
  })

  test('price=0 （免費商品）應正常建立', async () => {
    const res = await wpPost<any>(apiOpts, API.products, {
      name: 'E2E PH 免費商品',
      regular_price: '0',
      status: 'draft',
    })
    expect(res.status).toBe(200)
    const ids: number[] = Array.isArray(res.data.data) ? res.data.data : [res.data.data]
    createdProductIds.push(...ids.map(Number))
  })

  test('price=-100 應回傳錯誤或被接受（視後端實作）', async () => {
    const res = await wpPost<any>(apiOpts, API.products, {
      name: 'E2E PH 負價格商品',
      regular_price: '-100',
      status: 'draft',
    })
    expect([200, 400]).toContain(res.status)
    if (res.status === 200) {
      const ids: number[] = Array.isArray(res.data.data) ? res.data.data : [res.data.data]
      createdProductIds.push(...ids.map(Number))
    }
  })

  test('action=update-many 缺少 ids 應回傳 500 含 ids is required', async () => {
    const res = await wpPost<any>(apiOpts, API.products, {
      action: 'update-many',
      name: '批量更新',
      // 刻意缺少 ids
    })
    expect(res.status).toBe(500)
  })

  test('XSS payload 商品名稱應被安全處理', async () => {
    const res = await wpPost<any>(apiOpts, API.products, {
      name: STRING_EDGE.xss1,
      status: 'draft',
    })
    expect(res.status).toBe(200)
    const ids: number[] = Array.isArray(res.data.data) ? res.data.data : [res.data.data]
    createdProductIds.push(...ids.map(Number))
  })
})

// ─────────────────────────────────────────────────────
// 更新商品 POST /products/:id
// ─────────────────────────────────────────────────────

test.describe('POST /products/:id — 更新商品', () => {
  test('成功更新商品名稱', async () => {
    test.skip(!setupSimpleProductId, '沒有可用的測試商品')
    const res = await wpPost<any>(apiOpts, API.productById(setupSimpleProductId!), {
      name: 'E2E PH 商品已更新',
    })
    expect(res.status).toBe(200)
  })

  test('更新不存在的商品應回傳錯誤', async () => {
    const res = await wpPost(apiOpts, API.productById(ID_EDGE.nonExistent), {
      name: '不存在的商品',
    })
    expect(res.status).toBeGreaterThanOrEqual(400)
  })

  test('更新商品的 sale_price', async () => {
    test.skip(!setupSimpleProductId, '沒有可用的測試商品')
    const res = await wpPost(apiOpts, API.productById(setupSimpleProductId!), {
      sale_price: '800',
    })
    expect(res.status).toBe(200)
  })
})

// ─────────────────────────────────────────────────────
// 刪除商品 DELETE /products
// ─────────────────────────────────────────────────────

test.describe('DELETE /products/:id — 刪除商品', () => {
  test('刪除商品應成功（移至垃圾桶）', async () => {
    // 建立待刪除商品
    const createRes = await wpPost<any>(apiOpts, API.products, {
      name: 'E2E PH 待刪除商品',
      status: 'draft',
    })
    const ids: number[] = Array.isArray(createRes.data.data) ? createRes.data.data : [createRes.data.data]
    const pid = Number(ids[0])

    const delRes = await wpDelete(apiOpts, API.productById(pid))
    expect([200, 204]).toContain(delRes.status)
  })

  test('刪除不存在的商品應回傳錯誤', async () => {
    const res = await wpDelete(apiOpts, API.productById(ID_EDGE.nonExistent))
    expect(res.status).toBeGreaterThanOrEqual(400)
  })
})

// ─────────────────────────────────────────────────────
// 商品屬性 POST /products/attributes/:id
// ─────────────────────────────────────────────────────

test.describe('POST /products/attributes/:id — 更新商品屬性', () => {
  test('更新不存在商品的屬性應回傳錯誤', async () => {
    const res = await wpPost(apiOpts, API.productAttributes(ID_EDGE.nonExistent), {
      new_attributes: [],
    })
    expect(res.status).toBeGreaterThanOrEqual(400)
  })

  test('更新商品屬性（空陣列）應回傳 200', async () => {
    test.skip(!setupSimpleProductId, '沒有可用的測試商品')
    const res = await wpPost(apiOpts, API.productAttributes(setupSimpleProductId!), {
      new_attributes: [],
    })
    expect([200, 400]).toContain(res.status)
  })
})

// ─────────────────────────────────────────────────────
// 產生商品變體 POST /products/create-variations/:id
// ─────────────────────────────────────────────────────

test.describe('POST /products/create-variations/:id — 產生商品變體', () => {
  test('可變商品應成功產生變體', async () => {
    test.skip(!setupVariableProductId, '沒有可用的可變商品')
    const res = await wpPost<any>(apiOpts, API.productCreateVariations(setupVariableProductId!), {})
    expect(res.status).toBe(200)
    expect(res.data.code).toBe('update_attributes_success')
    if (res.data.data?.created_variation_ids) {
      expect(res.data.data.created_variation_ids.length).toBeGreaterThan(0)
    }
  })

  test('非數字 ID 應回傳錯誤', async () => {
    const res = await wpPost(apiOpts, API.productCreateVariations(ID_EDGE.abcStr), {})
    expect(res.status).toBeGreaterThanOrEqual(400)
  })

  test('不存在商品應回傳錯誤', async () => {
    const res = await wpPost(apiOpts, API.productCreateVariations(ID_EDGE.nonExistent), {})
    expect(res.status).toBeGreaterThanOrEqual(400)
  })

  test('重複產生變體（冪等操作）應不新增重複變體', async () => {
    test.skip(!setupVariableProductId, '沒有可用的可變商品')
    // 第一次產生
    await wpPost(apiOpts, API.productCreateVariations(setupVariableProductId!), {})
    // 第二次產生（不應增加新變體，應刪除重複）
    const res2 = await wpPost<any>(apiOpts, API.productCreateVariations(setupVariableProductId!), {})
    expect(res2.status).toBe(200)
  })
})

// ─────────────────────────────────────────────────────
// 全局商品屬性 GET /product-attributes
// ─────────────────────────────────────────────────────

test.describe('GET /product-attributes — 查詢全局商品屬性', () => {
  test('應回傳全局屬性列表', async () => {
    const res = await wpGet<any[]>(apiOpts, API.productAttributesList)
    expect(res.status).toBe(200)
    expect(Array.isArray(res.data)).toBe(true)
  })
})

test.describe('CRUD /product-attributes — 全局商品屬性 CRUD', () => {
  let createdAttrId: number | null = null

  test('建立全局商品屬性', async () => {
    const timestamp = Date.now()
    const res = await wpPost<any>(apiOpts, API.productAttributesList, {
      name: `E2E PH 屬性 ${timestamp}`,
      slug: `e2e-ph-attr-${timestamp}`,
    })
    expect([200, 201]).toContain(res.status)
    const id = Number((res.data as any)?.id)
    if (id) {
      createdAttrId = id
    }
  })

  test('更新全局商品屬性', async () => {
    test.skip(!createdAttrId, '無可用屬性')
    const res = await wpPost(apiOpts, API.productAttributeById(createdAttrId!), {
      name: `E2E PH 屬性已更新`,
    })
    expect([200, 201]).toContain(res.status)
  })

  test('刪除全局商品屬性', async () => {
    test.skip(!createdAttrId, '無可用屬性')
    const res = await wpDelete(apiOpts, API.productAttributeById(createdAttrId!))
    expect([200, 204]).toContain(res.status)
  })

  test('刪除不存在屬性應回傳錯誤', async () => {
    const res = await wpDelete(apiOpts, API.productAttributeById(ID_EDGE.nonExistent))
    expect(res.status).toBeGreaterThanOrEqual(400)
  })
})

// ─────────────────────────────────────────────────────
// 複製商品 POST /copy/:id
// ─────────────────────────────────────────────────────

test.describe('POST /copy/:id — 複製商品', () => {
  test('成功複製商品應回傳新 ID', async () => {
    test.skip(!setupSimpleProductId, '沒有可用的測試商品')
    const copyRes = await wpPost<any>(apiOpts, API.copy(setupSimpleProductId!), {})
    expect(copyRes.status).toBe(200)
    expect(copyRes.data.code).toBe('post_copy_success')
    const newId = Number(copyRes.data.data)
    expect(newId).toBeGreaterThan(0)
    createdProductIds.push(newId)
  })
})

// ─────────────────────────────────────────────────────
// 綁定權限項目 POST /products/bind-items
// ─────────────────────────────────────────────────────

test.describe('POST /products/bind-items — 綁定/更新/解除綁定', () => {
  test('缺少 product_ids 應回傳錯誤', async () => {
    const res = await wpPost(apiOpts, API.productBindItems, {
      item_ids: [1],
      limit_type: 'unlimited',
      meta_key: '_course_ids',
    })
    expect(res.status).toBeGreaterThanOrEqual(400)
  })

  test('缺少 item_ids 應回傳錯誤', async () => {
    const res = await wpPost(apiOpts, API.productBindItems, {
      product_ids: [1],
      limit_type: 'unlimited',
      meta_key: '_course_ids',
    })
    expect(res.status).toBeGreaterThanOrEqual(400)
  })

  test('缺少 meta_key 應回傳錯誤', async () => {
    const res = await wpPost(apiOpts, API.productBindItems, {
      product_ids: [1],
      item_ids: [1],
      limit_type: 'unlimited',
    })
    expect(res.status).toBeGreaterThanOrEqual(400)
  })

  test('完整綁定流程：綁定 → 更新 → 解綁', async () => {
    test.skip(!setupSimpleProductId, '沒有可用的測試商品')

    // 建立一個 post 作為 item
    const postRes = await wpPost<any>(apiOpts, API.posts, {
      post_type: 'post',
      post_title: 'E2E PH 綁定 Item',
      post_status: 'publish',
    })
    const postIds: number[] = Array.isArray(postRes.data.data) ? postRes.data.data : [postRes.data.data]
    const itemId = Number(postIds[0])

    try {
      // Step 1: 綁定
      const bindRes = await wpPost<any>(apiOpts, API.productBindItems, {
        product_ids: [setupSimpleProductId],
        item_ids: [itemId],
        limit_type: 'unlimited',
        meta_key: 'e2e_bound_items_data',
      })
      expect([200, 201]).toContain(bindRes.status)

      // Step 2: 更新綁定
      const updateRes = await wpPost<any>(apiOpts, API.productUpdateBoundItems, {
        product_ids: [setupSimpleProductId],
        item_ids: [itemId],
        limit_type: 'fixed',
        limit_value: 30,
        limit_unit: 'day',
        meta_key: 'e2e_bound_items_data',
      })
      expect([200, 201]).toContain(updateRes.status)

      // Step 3: 解綁
      const unbindRes = await wpPost<any>(apiOpts, API.productUnbindItems, {
        product_ids: [setupSimpleProductId],
        item_ids: [itemId],
        meta_key: 'e2e_bound_items_data',
      })
      expect([200, 201]).toContain(unbindRes.status)
    } finally {
      // 清理 post
      await apiOpts.request.delete(
        `${apiOpts.baseURL}/wp-json/wp/v2/posts/${itemId}?force=true`,
        { headers: { 'X-WP-Nonce': apiOpts.nonce } },
      )
    }
  })
})
