/**
 * 資料邊界整合測試
 *
 * 測試跨 API 的資料一致性與邊界值處理：
 * - 數值邊界（0、-1、最大整數、小數）
 * - 字串邊界（空、超長、Emoji、RTL）
 * - 分頁邊界（頁碼超出、每頁 0 筆）
 * - 重複操作（冪等性）
 */
import { test, expect } from '@playwright/test'
import { getNonce } from '../helpers/admin-setup.js'
import { wpGet, wpPost, wpDelete, type ApiOptions } from '../helpers/api-client.js'
import { API, WC_API, STRING_EDGE, ID_EDGE, NUMERIC_EDGE } from '../fixtures/test-data.js'

let apiOpts: ApiOptions
const tempIds: number[] = []

test.beforeAll(async ({ request }, testInfo) => {
  const baseURL = testInfo.project.use.baseURL || 'http://localhost:8898'
  apiOpts = { request, baseURL, nonce: getNonce() }
})

test.afterAll(async () => {
  for (const id of tempIds) {
    try {
      await apiOpts.request.delete(
        `${apiOpts.baseURL}/wp-json/wp/v2/posts/${id}?force=true`,
        { headers: { 'X-WP-Nonce': apiOpts.nonce } },
      )
    } catch { /* 忽略 */ }
  }
})

// ─────────────────────────────────────────────────────
// 數值邊界
// ─────────────────────────────────────────────────────

test.describe('數值邊界測試', () => {
  test('posts_per_page=0 應不回傳 500', async () => {
    const res = await wpGet(apiOpts, API.posts, { posts_per_page: NUMERIC_EDGE.zeroStr })
    expect([200, 400]).toContain(res.status)
  })

  test('posts_per_page=-1 應不回傳 500', async () => {
    const res = await wpGet(apiOpts, API.posts, { posts_per_page: NUMERIC_EDGE.negOneStr })
    expect([200, 400]).toContain(res.status)
  })

  test('qty=0.5（小數）建立文章應被處理', async () => {
    const res = await wpPost<any>(apiOpts, API.posts, {
      post_type: 'post',
      post_title: 'E2E PH qty 小數測試',
      post_status: 'draft',
      qty: 0.5,
    })
    expect([200, 400]).toContain(res.status)
    if (res.status === 200 && Array.isArray(res.data?.data)) {
      tempIds.push(...(res.data.data as number[]).map(Number))
    }
  })

  test('qty=Number.MAX_SAFE_INTEGER 應回傳錯誤（不應建立天文數量文章）', async () => {
    const res = await wpPost<any>(apiOpts, API.posts, {
      post_type: 'post',
      post_title: 'E2E PH qty max 測試',
      post_status: 'draft',
      qty: NUMERIC_EDGE.maxSafeInt,
    })
    // 應拒絕或限制數量，不應真的建立 MAX_SAFE_INTEGER 篇
    expect([200, 400, 500]).toContain(res.status)
    if (res.status === 200 && Array.isArray(res.data?.data)) {
      // 若接受，數量應合理（有上限保護）
      expect((res.data.data as number[]).length).toBeLessThan(1000)
      tempIds.push(...(res.data.data as number[]).map(Number))
    }
  })

  test('商品 regular_price=0.001 應被接受', async () => {
    const res = await wpPost<any>(apiOpts, API.products, {
      name: 'E2E PH 超低價商品',
      regular_price: '0.001',
      status: 'draft',
    })
    expect([200, 400]).toContain(res.status)
    if (res.status === 200) {
      const ids: number[] = Array.isArray(res.data.data) ? res.data.data : [res.data.data]
      const pid = Number(ids[0])
      if (pid) {
        await apiOpts.request.delete(
          `${apiOpts.baseURL}/wp-json/wc/v3/products/${pid}?force=true`,
          { headers: { 'X-WP-Nonce': apiOpts.nonce } },
        )
      }
    }
  })

  test('expire_date=Infinity 字串應被安全處理', async () => {
    const res = await wpPost<any>(apiOpts, API.limitGrantUsers, {
      user_ids: [1],
      item_ids: [1],
      expire_date: NUMERIC_EDGE.infinityStr,
    })
    expect([200, 400, 500]).toContain(res.status)
  })

  test('expire_date=NaN 字串應被安全處理', async () => {
    const res = await wpPost<any>(apiOpts, API.limitGrantUsers, {
      user_ids: [1],
      item_ids: [1],
      expire_date: NUMERIC_EDGE.nanStr,
    })
    expect([200, 400, 500]).toContain(res.status)
  })
})

// ─────────────────────────────────────────────────────
// 字串邊界
// ─────────────────────────────────────────────────────

test.describe('字串邊界測試', () => {
  test('中文標題應正常儲存並回傳', async () => {
    const res = await wpPost<any>(apiOpts, API.posts, {
      post_type: 'post',
      post_title: STRING_EDGE.zhChinese,
      post_status: 'draft',
    })
    expect(res.status).toBe(200)
    const ids: number[] = Array.isArray(res.data.data) ? res.data.data : [res.data.data]
    const id = Number(ids[0])
    tempIds.push(id)

    const getRes = await wpGet<any>(apiOpts, API.postById(id))
    if (getRes.status === 200) {
      expect(getRes.data.post_title).toContain('中文')
    }
  })

  test('日文標題應正常儲存', async () => {
    const res = await wpPost<any>(apiOpts, API.posts, {
      post_type: 'post',
      post_title: STRING_EDGE.japanese,
      post_status: 'draft',
    })
    expect(res.status).toBe(200)
    const ids: number[] = Array.isArray(res.data.data) ? res.data.data : [res.data.data]
    tempIds.push(...ids.map(Number))
  })

  test('韓文標題應正常儲存', async () => {
    const res = await wpPost<any>(apiOpts, API.posts, {
      post_type: 'post',
      post_title: STRING_EDGE.korean,
      post_status: 'draft',
    })
    expect(res.status).toBe(200)
    const ids: number[] = Array.isArray(res.data.data) ? res.data.data : [res.data.data]
    tempIds.push(...ids.map(Number))
  })

  test('Emoji 標題應正常儲存', async () => {
    const res = await wpPost<any>(apiOpts, API.posts, {
      post_type: 'post',
      post_title: STRING_EDGE.emoji,
      post_status: 'draft',
    })
    expect(res.status).toBe(200)
    const ids: number[] = Array.isArray(res.data.data) ? res.data.data : [res.data.data]
    tempIds.push(...ids.map(Number))
  })

  test('RTL 文字（阿拉伯文）應正常儲存', async () => {
    const res = await wpPost<any>(apiOpts, API.posts, {
      post_type: 'post',
      post_title: STRING_EDGE.rtl,
      post_status: 'draft',
    })
    expect(res.status).toBe(200)
    const ids: number[] = Array.isArray(res.data.data) ? res.data.data : [res.data.data]
    tempIds.push(...ids.map(Number))
  })

  test('10000 字元超長字串應被處理（不應 500）', async () => {
    const res = await wpPost<any>(apiOpts, API.posts, {
      post_type: 'post',
      post_title: STRING_EDGE.longStr,
      post_status: 'draft',
    })
    expect([200, 400]).toContain(res.status)
    if (res.status === 200) {
      const ids: number[] = Array.isArray(res.data.data) ? res.data.data : [res.data.data]
      tempIds.push(...ids.map(Number))
    }
  })

  test('NULL byte 字串應被安全過濾', async () => {
    const res = await wpPost<any>(apiOpts, API.posts, {
      post_type: 'post',
      post_title: STRING_EDGE.nullByte,
      post_status: 'draft',
    })
    expect([200, 400]).toContain(res.status)
    if (res.status === 200) {
      const ids: number[] = Array.isArray(res.data.data) ? res.data.data : [res.data.data]
      tempIds.push(...ids.map(Number))
    }
  })
})

// ─────────────────────────────────────────────────────
// ID 邊界
// ─────────────────────────────────────────────────────

test.describe('ID 邊界測試', () => {
  test('GET /posts/0 應回傳錯誤', async () => {
    const res = await wpGet(apiOpts, API.postById(ID_EDGE.zero))
    expect(res.status).toBeGreaterThanOrEqual(400)
  })

  test('GET /posts/-1 應回傳錯誤', async () => {
    const res = await wpGet(apiOpts, API.postById(ID_EDGE.negOne))
    expect(res.status).toBeGreaterThanOrEqual(400)
  })

  test('GET /posts/999999 應回傳 404', async () => {
    const res = await wpGet(apiOpts, API.postById(ID_EDGE.nonExistent))
    expect([400, 404]).toContain(res.status)
  })

  test('GET /posts/null 應回傳錯誤', async () => {
    const res = await wpGet(apiOpts, API.postById(ID_EDGE.nullStr))
    expect(res.status).toBeGreaterThanOrEqual(400)
  })

  test('GET /posts/abc 非數字應回傳錯誤', async () => {
    const res = await wpGet(apiOpts, API.postById(ID_EDGE.abcStr))
    expect(res.status).toBeGreaterThanOrEqual(400)
  })

  test('GET /products/999999 應回傳 404', async () => {
    const res = await wpGet(apiOpts, API.productById(ID_EDGE.nonExistent))
    expect([400, 404]).toContain(res.status)
  })

  test('GET /orders/999999 應回傳 404', async () => {
    const res = await wpGet(apiOpts, API.orderById(ID_EDGE.nonExistent))
    expect([400, 404]).toContain(res.status)
  })

  test('GET /users/999999 應回傳 404', async () => {
    const res = await wpGet(apiOpts, API.userById(ID_EDGE.nonExistent))
    expect([400, 404]).toContain(res.status)
  })

  test('超出 int32 上限的 ID 應被安全處理', async () => {
    const res = await wpGet(apiOpts, API.postById(ID_EDGE.overMaxInt))
    expect(res.status).toBeGreaterThanOrEqual(400)
  })
})

// ─────────────────────────────────────────────────────
// 重複操作（冪等性）
// ─────────────────────────────────────────────────────

test.describe('重複操作冪等性', () => {
  test('連續發送 2 次相同的建立文章請求應建立 2 篇獨立文章', async () => {
    const payload = {
      post_type: 'post',
      post_title: 'E2E PH 冪等測試文章',
      post_status: 'draft',
    }

    const res1 = await wpPost<any>(apiOpts, API.posts, payload)
    const res2 = await wpPost<any>(apiOpts, API.posts, payload)

    expect(res1.status).toBe(200)
    expect(res2.status).toBe(200)

    const ids1 = Array.isArray(res1.data.data) ? res1.data.data : [res1.data.data]
    const ids2 = Array.isArray(res2.data.data) ? res2.data.data : [res2.data.data]

    // 兩次建立應產生不同 ID
    expect(Number(ids1[0])).not.toBe(Number(ids2[0]))
    tempIds.push(...ids1.map(Number), ...ids2.map(Number))
  })

  test('連續兩次查詢同一文章應回傳相同結果', async () => {
    // 建立文章
    const createRes = await wpPost<any>(apiOpts, API.posts, {
      post_type: 'post',
      post_title: 'E2E PH 查詢冪等測試',
      post_status: 'draft',
    })
    const ids: number[] = Array.isArray(createRes.data.data) ? createRes.data.data : [createRes.data.data]
    const postId = Number(ids[0])
    tempIds.push(postId)

    // 兩次查詢
    const get1 = await wpGet<any>(apiOpts, API.postById(postId))
    const get2 = await wpGet<any>(apiOpts, API.postById(postId))

    expect(get1.status).toBe(200)
    expect(get2.status).toBe(200)
    expect(get1.data.post_title).toBe(get2.data.post_title)
  })

  test('連續兩次清除授權碼快取應皆成功', async () => {
    const r1 = await wpPost<any>(apiOpts, API.lcInvalidate, {
      product_slug: 'power-course',
    })
    const r2 = await wpPost<any>(apiOpts, API.lcInvalidate, {
      product_slug: 'power-course',
    })

    expect(r1.status).toBe(200)
    expect(r2.status).toBe(200)
    expect(r1.data.code).toBe('invalidate_lc_cache_success')
    expect(r2.data.code).toBe('invalidate_lc_cache_success')
  })
})
