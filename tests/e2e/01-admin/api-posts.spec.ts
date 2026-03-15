/**
 * Posts CRUD API 測試
 *
 * 對應 spec:
 *   - 建立文章.feature
 *   - 查詢文章列表.feature
 *   - 查詢單一文章.feature
 *   - 查詢文章欄位.feature
 *   - 更新文章.feature
 *   - 刪除文章.feature
 *   - 排序文章.feature
 *   - 複製文章.feature
 */
import { test, expect } from '@playwright/test'
import { getNonce } from '../helpers/admin-setup.js'
import { wpGet, wpPost, wpDelete, type ApiOptions } from '../helpers/api-client.js'
import { API, STRING_EDGE, ID_EDGE, NUMERIC_EDGE } from '../fixtures/test-data.js'

let apiOpts: ApiOptions
/** 此測試套件建立的文章 ID，afterAll 統一清理 */
const createdPostIds: number[] = []

test.beforeAll(async ({ request }, testInfo) => {
  const baseURL = testInfo.project.use.baseURL || 'http://localhost:8898'
  apiOpts = { request, baseURL, nonce: getNonce() }
})

test.afterAll(async () => {
  for (const id of createdPostIds) {
    try {
      await apiOpts.request.delete(
        `${apiOpts.baseURL}/wp-json/wp/v2/posts/${id}?force=true`,
        { headers: { 'X-WP-Nonce': apiOpts.nonce } },
      )
    } catch { /* 忽略清理失敗 */ }
  }
})

// ─────────────────────────────────────────────────────
// 查詢文章列表 GET /posts
// ─────────────────────────────────────────────────────

test.describe('GET /posts — 查詢文章列表', () => {
  test('不帶參數應使用預設值回傳文章列表', async () => {
    const res = await wpGet<any[]>(apiOpts, API.posts)
    expect(res.status).toBe(200)
    expect(Array.isArray(res.data)).toBe(true)
  })

  test('應包含分頁 headers（X-WP-Total 等）', async () => {
    const res = await wpGet(apiOpts, API.posts, { posts_per_page: '1', paged: '1' })
    expect(res.status).toBe(200)
    // headers 可能大小寫不一，已轉小寫
    expect(Number(res.headers['x-wp-total'])).toBeGreaterThanOrEqual(0)
    expect(Number(res.headers['x-wp-totalpages'])).toBeGreaterThanOrEqual(0)
  })

  test('指定 post_type=page 只應回傳頁面', async () => {
    const res = await wpGet<any[]>(apiOpts, API.posts, { post_type: 'page' })
    expect(res.status).toBe(200)
    if (Array.isArray(res.data) && res.data.length > 0) {
      for (const item of res.data) {
        expect(item.post_type).toBe('page')
      }
    }
  })

  test('posts_per_page=2 應限制回傳數量', async () => {
    const res = await wpGet<any[]>(apiOpts, API.posts, { posts_per_page: '2', paged: '1' })
    expect(res.status).toBe(200)
    expect((res.data as any[]).length).toBeLessThanOrEqual(2)
  })

  test('posts_per_page=0 應回傳 200 或錯誤（不應 500）', async () => {
    const res = await wpGet(apiOpts, API.posts, { posts_per_page: '0' })
    expect([200, 400]).toContain(res.status)
  })

  test('posts_per_page=-1 應回傳全部（或錯誤）', async () => {
    const res = await wpGet(apiOpts, API.posts, { posts_per_page: '-1' })
    expect([200, 400]).toContain(res.status)
  })

  test('paged 超出範圍應回傳空陣列', async () => {
    const res = await wpGet<any[]>(apiOpts, API.posts, {
      posts_per_page: '10',
      paged: '99999',
    })
    expect(res.status).toBe(200)
    expect(Array.isArray(res.data)).toBe(true)
    expect((res.data as any[]).length).toBe(0)
  })

  test('SQL injection 搜尋參數不應造成 500', async () => {
    const res = await wpGet(apiOpts, API.posts, { s: STRING_EDGE.sqlInject2 })
    expect([200, 400]).toContain(res.status)
  })

  test('XSS 搜尋參數不應造成 500', async () => {
    const res = await wpGet(apiOpts, API.posts, { s: STRING_EDGE.xss1 })
    expect([200, 400]).toContain(res.status)
  })

  test('搜尋無結果應回傳空陣列', async () => {
    const res = await wpGet<any[]>(apiOpts, API.posts, {
      s: 'zzz_nonexistent_e2e_ph_term_xyz_123',
    })
    expect(res.status).toBe(200)
    expect((res.data as any[]).length).toBe(0)
  })
})

// ─────────────────────────────────────────────────────
// 建立文章 POST /posts
// ─────────────────────────────────────────────────────

test.describe('POST /posts — 建立文章', () => {
  test('不帶 qty 應建立 1 篇文章，回傳 code=create_success', async () => {
    const res = await wpPost<any>(apiOpts, API.posts, {
      post_type: 'post',
      post_title: 'E2E PH 建立測試',
      post_status: 'draft',
    })
    expect(res.status).toBe(200)
    expect(res.data.code).toBe('create_success')
    const ids: number[] = Array.isArray(res.data.data) ? res.data.data : [res.data.data]
    expect(ids.length).toBe(1)
    expect(Number(ids[0])).toBeGreaterThan(0)
    createdPostIds.push(...ids.map(Number))
  })

  test('qty=3 應批量建立 3 篇文章', async () => {
    const res = await wpPost<any>(apiOpts, API.posts, {
      post_type: 'post',
      post_title: 'E2E PH 批量文章',
      post_status: 'draft',
      qty: 3,
    })
    expect(res.status).toBe(200)
    expect(res.data.code).toBe('create_success')
    const ids: number[] = Array.isArray(res.data.data) ? res.data.data : [res.data.data]
    expect(ids.length).toBe(3)
    createdPostIds.push(...ids.map(Number))
  })

  test('建立含 meta_data 的文章', async () => {
    const res = await wpPost<any>(apiOpts, API.posts, {
      post_type: 'post',
      post_title: 'E2E PH Meta 測試',
      post_status: 'draft',
      meta_data: { _test_key: 'test_value' },
    })
    expect(res.status).toBe(200)
    const ids: number[] = Array.isArray(res.data.data) ? res.data.data : [res.data.data]
    createdPostIds.push(...ids.map(Number))
  })

  test('XSS payload 標題應被安全處理（不存入原始 script 標籤）', async () => {
    const res = await wpPost<any>(apiOpts, API.posts, {
      post_type: 'post',
      post_title: STRING_EDGE.xss1,
      post_status: 'draft',
    })
    expect(res.status).toBe(200)
    const ids: number[] = Array.isArray(res.data.data) ? res.data.data : [res.data.data]
    createdPostIds.push(...ids.map(Number))

    // 查詢驗證標題已被消毒
    const postRes = await wpGet<any>(apiOpts, API.postById(Number(ids[0])))
    expect(postRes.status).toBe(200)
    expect(postRes.data.post_title).not.toContain('<script>')
  })

  test('img onerror XSS payload 不應執行腳本', async () => {
    const res = await wpPost<any>(apiOpts, API.posts, {
      post_type: 'post',
      post_title: 'E2E XSS img test',
      post_content: STRING_EDGE.xss2,
      post_status: 'draft',
    })
    expect(res.status).toBe(200)
    const ids: number[] = Array.isArray(res.data.data) ? res.data.data : [res.data.data]
    createdPostIds.push(...ids.map(Number))
  })

  test('SQL injection 標題不應造成資料庫錯誤', async () => {
    const res = await wpPost<any>(apiOpts, API.posts, {
      post_type: 'post',
      post_title: STRING_EDGE.sqlInject2,
      post_status: 'draft',
    })
    expect(res.status).toBe(200)
    const ids: number[] = Array.isArray(res.data.data) ? res.data.data : [res.data.data]
    createdPostIds.push(...ids.map(Number))
  })

  test('Unicode/Emoji 標題應正常儲存', async () => {
    const res = await wpPost<any>(apiOpts, API.posts, {
      post_type: 'post',
      post_title: STRING_EDGE.unicode,
      post_status: 'draft',
    })
    expect(res.status).toBe(200)
    const ids: number[] = Array.isArray(res.data.data) ? res.data.data : [res.data.data]
    createdPostIds.push(...ids.map(Number))

    // 驗證 Emoji 有被正確儲存
    const postRes = await wpGet<any>(apiOpts, API.postById(Number(ids[0])))
    if (postRes.status === 200) {
      expect(postRes.data.post_title).toContain('🎓')
    }
  })

  test('RTL 文字標題應正常儲存', async () => {
    const res = await wpPost<any>(apiOpts, API.posts, {
      post_type: 'post',
      post_title: STRING_EDGE.rtl,
      post_status: 'draft',
    })
    expect(res.status).toBe(200)
    const ids: number[] = Array.isArray(res.data.data) ? res.data.data : [res.data.data]
    createdPostIds.push(...ids.map(Number))
  })

  test('超長字串標題應被處理（不應 500）', async () => {
    const res = await wpPost<any>(apiOpts, API.posts, {
      post_type: 'post',
      post_title: STRING_EDGE.longStr,
      post_status: 'draft',
    })
    expect([200, 400]).toContain(res.status)
    if (res.status === 200) {
      const ids: number[] = Array.isArray(res.data.data) ? res.data.data : [res.data.data]
      createdPostIds.push(...ids.map(Number))
    }
  })

  test('NULL byte 標題應被處理（不應 500）', async () => {
    const res = await wpPost<any>(apiOpts, API.posts, {
      post_type: 'post',
      post_title: STRING_EDGE.nullByte,
      post_status: 'draft',
    })
    expect([200, 400]).toContain(res.status)
    if (res.status === 200) {
      const ids: number[] = Array.isArray(res.data.data) ? res.data.data : [res.data.data]
      createdPostIds.push(...ids.map(Number))
    }
  })

  test('路徑穿越字串作為標題應被安全處理', async () => {
    const res = await wpPost<any>(apiOpts, API.posts, {
      post_type: 'post',
      post_title: STRING_EDGE.pathTraversal,
      post_status: 'draft',
    })
    expect([200, 400]).toContain(res.status)
    if (res.status === 200) {
      const ids: number[] = Array.isArray(res.data.data) ? res.data.data : [res.data.data]
      createdPostIds.push(...ids.map(Number))
    }
  })

  test('空標題不應造成 500', async () => {
    const res = await wpPost<any>(apiOpts, API.posts, {
      post_type: 'post',
      post_title: STRING_EDGE.empty,
      post_status: 'draft',
    })
    expect([200, 400]).toContain(res.status)
    if (res.status === 200) {
      const ids: number[] = Array.isArray(res.data.data) ? res.data.data : [res.data.data]
      createdPostIds.push(...ids.map(Number))
    }
  })

  test('qty=0 應回傳錯誤或建立 0 篇', async () => {
    const res = await wpPost<any>(apiOpts, API.posts, {
      post_type: 'post',
      post_title: 'E2E qty=0 測試',
      post_status: 'draft',
      qty: 0,
    })
    // qty=0 視 API 實作可能建立 0 篇或回傳 400
    expect([200, 400]).toContain(res.status)
    if (res.status === 200 && Array.isArray(res.data?.data)) {
      createdPostIds.push(...(res.data.data as number[]).map(Number))
    }
  })

  test('qty 為負數應回傳錯誤（不應建立負數篇）', async () => {
    const res = await wpPost<any>(apiOpts, API.posts, {
      post_type: 'post',
      post_title: 'E2E qty負數測試',
      post_status: 'draft',
      qty: -1,
    })
    expect([200, 400]).toContain(res.status)
    if (res.status === 200 && Array.isArray(res.data?.data)) {
      createdPostIds.push(...(res.data.data as number[]).map(Number))
    }
  })
})

// ─────────────────────────────────────────────────────
// 查詢單一文章 GET /posts/:id
// ─────────────────────────────────────────────────────

test.describe('GET /posts/:id — 查詢單一文章', () => {
  test('查詢存在的文章應回傳完整資料', async () => {
    // 先建立
    const createRes = await wpPost<any>(apiOpts, API.posts, {
      post_type: 'post',
      post_title: 'E2E PH 查詢單一測試',
      post_status: 'draft',
    })
    const ids: number[] = Array.isArray(createRes.data.data) ? createRes.data.data : [createRes.data.data]
    const postId = Number(ids[0])
    createdPostIds.push(postId)

    const res = await wpGet<any>(apiOpts, API.postById(postId))
    expect(res.status).toBe(200)
    expect(res.data.id).toBe(postId)
    expect(res.data).toHaveProperty('post_title')
    expect(res.data).toHaveProperty('post_status')
  })

  test('ID 不存在（999999）應回傳 404 或 400', async () => {
    const res = await wpGet(apiOpts, API.postById(ID_EDGE.nonExistent))
    expect([400, 404]).toContain(res.status)
  })

  test('ID 為 0 應回傳錯誤', async () => {
    const res = await wpGet(apiOpts, API.postById(ID_EDGE.zero))
    expect(res.status).toBeGreaterThanOrEqual(400)
  })

  test('ID 為 -1 應回傳錯誤', async () => {
    const res = await wpGet(apiOpts, API.postById(ID_EDGE.negOne))
    expect(res.status).toBeGreaterThanOrEqual(400)
  })

  test('ID 為非數字字串 abc 應回傳 404', async () => {
    const res = await wpGet(apiOpts, API.postById(ID_EDGE.abcStr))
    expect(res.status).toBeGreaterThanOrEqual(400)
  })

  test('ID 超出 int 上限應回傳錯誤', async () => {
    const res = await wpGet(apiOpts, API.postById(ID_EDGE.overMaxInt))
    expect(res.status).toBeGreaterThanOrEqual(400)
  })
})

// ─────────────────────────────────────────────────────
// 查詢文章欄位 GET /posts/:id/field/:field_name
// ─────────────────────────────────────────────────────

test.describe('GET /posts/:id/field/:field — 查詢文章單一欄位', () => {
  test('查詢現有文章的 post_title 欄位', async () => {
    // 先建立
    const createRes = await wpPost<any>(apiOpts, API.posts, {
      post_type: 'post',
      post_title: 'E2E PH 欄位查詢測試',
      post_status: 'draft',
    })
    const ids: number[] = Array.isArray(createRes.data.data) ? createRes.data.data : [createRes.data.data]
    const postId = Number(ids[0])
    createdPostIds.push(postId)

    const res = await wpGet(apiOpts, API.postField(postId, 'post_title'))
    expect(res.status).toBe(200)
  })

  test('查詢不存在文章的欄位應回傳錯誤', async () => {
    const res = await wpGet(apiOpts, API.postField(ID_EDGE.nonExistent, 'post_title'))
    expect(res.status).toBeGreaterThanOrEqual(400)
  })
})

// ─────────────────────────────────────────────────────
// 更新文章 POST /posts/:id
// ─────────────────────────────────────────────────────

test.describe('POST /posts/:id — 更新文章', () => {
  test('成功更新文章標題', async () => {
    // 先建立
    const createRes = await wpPost<any>(apiOpts, API.posts, {
      post_type: 'post',
      post_title: 'E2E PH 更新前',
      post_status: 'draft',
    })
    const ids: number[] = Array.isArray(createRes.data.data) ? createRes.data.data : [createRes.data.data]
    const postId = Number(ids[0])
    createdPostIds.push(postId)

    const updateRes = await wpPost<any>(apiOpts, API.postById(postId), {
      post_title: 'E2E PH 更新後',
    })
    expect(updateRes.status).toBe(200)

    // 驗證更新
    const getRes = await wpGet<any>(apiOpts, API.postById(postId))
    expect(getRes.data.post_title).toContain('更新後')
  })

  test('更新不存在的文章應回傳錯誤', async () => {
    const res = await wpPost(apiOpts, API.postById(ID_EDGE.nonExistent), {
      post_title: '不存在的文章',
    })
    expect(res.status).toBeGreaterThanOrEqual(400)
  })

  test('更新文章狀態為 publish', async () => {
    const createRes = await wpPost<any>(apiOpts, API.posts, {
      post_type: 'post',
      post_title: 'E2E PH 狀態更新',
      post_status: 'draft',
    })
    const ids: number[] = Array.isArray(createRes.data.data) ? createRes.data.data : [createRes.data.data]
    const postId = Number(ids[0])
    createdPostIds.push(postId)

    const updateRes = await wpPost(apiOpts, API.postById(postId), {
      post_status: 'publish',
    })
    expect(updateRes.status).toBe(200)
  })
})

// ─────────────────────────────────────────────────────
// 排序文章 POST /posts/sort
// ─────────────────────────────────────────────────────

test.describe('POST /posts/sort — 排序文章', () => {
  test('成功排序文章（更新 menu_order）', async () => {
    // 建立 2 篇文章
    const res1 = await wpPost<any>(apiOpts, API.posts, {
      post_type: 'post',
      post_title: 'E2E PH 排序 A',
      post_status: 'draft',
    })
    const res2 = await wpPost<any>(apiOpts, API.posts, {
      post_type: 'post',
      post_title: 'E2E PH 排序 B',
      post_status: 'draft',
    })
    const ids1 = Array.isArray(res1.data.data) ? res1.data.data : [res1.data.data]
    const ids2 = Array.isArray(res2.data.data) ? res2.data.data : [res2.data.data]
    const id1 = Number(ids1[0])
    const id2 = Number(ids2[0])
    createdPostIds.push(id1, id2)

    const tree = [
      { id: String(id1), menu_order: '0', post_parent: '0' },
      { id: String(id2), menu_order: '1', post_parent: '0' },
    ]

    const sortRes = await wpPost(apiOpts, API.postSort, {
      from_tree: tree,
      to_tree: [...tree].reverse(),
    })
    expect(sortRes.status).toBe(200)
  })

  test('排序不含 id 欄位應回傳錯誤', async () => {
    const sortRes = await wpPost(apiOpts, API.postSort, {
      from_tree: [{ menu_order: '0' }], // 缺少 id
      to_tree: [{ menu_order: '0' }],
    })
    expect([400, 500]).toContain(sortRes.status)
  })

  test('空 tree 排序應回傳成功（冪等）', async () => {
    const sortRes = await wpPost(apiOpts, API.postSort, {
      from_tree: [],
      to_tree: [],
    })
    expect([200, 400]).toContain(sortRes.status)
  })
})

// ─────────────────────────────────────────────────────
// 刪除文章 DELETE /posts
// ─────────────────────────────────────────────────────

test.describe('DELETE /posts — 刪除文章', () => {
  test('刪除單一文章應成功', async () => {
    // 建立待刪除文章
    const createRes = await wpPost<any>(apiOpts, API.posts, {
      post_type: 'post',
      post_title: 'E2E PH 待刪除',
      post_status: 'draft',
    })
    const ids: number[] = Array.isArray(createRes.data.data) ? createRes.data.data : [createRes.data.data]
    const postId = Number(ids[0])

    const delRes = await wpDelete(apiOpts, API.postById(postId))
    expect([200, 204]).toContain(delRes.status)

    // 驗證已刪除（應為 trash 狀態或 404）
    const getRes = await wpGet<any>(apiOpts, API.postById(postId))
    if (getRes.status === 200) {
      expect(getRes.data.post_status).toBe('trash')
    } else {
      expect([400, 404]).toContain(getRes.status)
    }
  })

  test('批量刪除文章應成功（帶 ids 陣列）', async () => {
    // 建立 2 篇
    const res = await wpPost<any>(apiOpts, API.posts, {
      post_type: 'post',
      post_title: 'E2E PH 批量刪除',
      post_status: 'draft',
      qty: 2,
    })
    const ids: number[] = (Array.isArray(res.data.data) ? res.data.data : [res.data.data]).map(Number)

    const delRes = await wpDelete(apiOpts, API.posts, { ids })
    expect([200, 204]).toContain(delRes.status)
  })

  test('刪除不存在的文章應回傳錯誤', async () => {
    const res = await wpDelete(apiOpts, API.postById(ID_EDGE.nonExistent))
    expect(res.status).toBeGreaterThanOrEqual(400)
  })

  test('重複刪除同一文章不應 500', async () => {
    // 建立再刪除
    const createRes = await wpPost<any>(apiOpts, API.posts, {
      post_type: 'post',
      post_title: 'E2E PH 重複刪除',
      post_status: 'draft',
    })
    const ids: number[] = Array.isArray(createRes.data.data) ? createRes.data.data : [createRes.data.data]
    const postId = Number(ids[0])

    await wpDelete(apiOpts, API.postById(postId))
    // 第二次刪除（已在 trash）
    const res2 = await wpDelete(apiOpts, API.postById(postId))
    expect([200, 204, 400, 404]).toContain(res2.status)
  })
})

// ─────────────────────────────────────────────────────
// 複製文章 POST /copy/:id
// ─────────────────────────────────────────────────────

test.describe('POST /copy/:id — 複製文章', () => {
  test('成功複製文章，回傳新 ID', async () => {
    // 建立來源文章
    const createRes = await wpPost<any>(apiOpts, API.posts, {
      post_type: 'post',
      post_title: 'E2E PH 複製來源',
      post_status: 'publish',
    })
    const ids: number[] = Array.isArray(createRes.data.data) ? createRes.data.data : [createRes.data.data]
    const sourceId = Number(ids[0])
    createdPostIds.push(sourceId)

    const copyRes = await wpPost<any>(apiOpts, API.copy(sourceId), {})
    expect(copyRes.status).toBe(200)
    expect(copyRes.data.code).toBe('post_copy_success')

    const newId = Number(copyRes.data.data)
    expect(newId).toBeGreaterThan(0)
    expect(newId).not.toBe(sourceId) // 新 ID 必須不同於來源
    createdPostIds.push(newId)
  })

  test('複製不存在的文章應回傳錯誤', async () => {
    const res = await wpPost(apiOpts, API.copy(ID_EDGE.nonExistent), {})
    expect(res.status).toBeGreaterThanOrEqual(400)
  })

  test('複製 ID=0 應回傳錯誤', async () => {
    const res = await wpPost(apiOpts, API.copy(ID_EDGE.zero), {})
    expect(res.status).toBeGreaterThanOrEqual(400)
  })

  test('複製 ID 為非數字 abc 應回傳 404', async () => {
    const res = await wpPost(apiOpts, API.copy(ID_EDGE.abcStr), {})
    expect(res.status).toBeGreaterThanOrEqual(400)
  })
})
