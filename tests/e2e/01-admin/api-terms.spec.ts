/**
 * Terms (分類法詞彙) CRUD API 測試
 *
 * 對應 spec:
 *   - 查詢詞彙列表.feature
 *   - 查詢單一詞彙.feature
 *   - 建立詞彙.feature
 *   - 更新詞彙.feature
 *   - 刪除詞彙.feature
 *   - 排序詞彙.feature
 */
import { test, expect } from '@playwright/test'
import { getNonce } from '../helpers/admin-setup.js'
import { wpGet, wpPost, wpDelete, type ApiOptions } from '../helpers/api-client.js'
import { API, STRING_EDGE, ID_EDGE } from '../fixtures/test-data.js'

const TAXONOMY = 'product_cat'
let apiOpts: ApiOptions
/** 此套件建立的詞彙 ID */
const createdTermIds: number[] = []

test.beforeAll(async ({ request }, testInfo) => {
  const baseURL = testInfo.project.use.baseURL || 'http://localhost:8898'
  apiOpts = { request, baseURL, nonce: getNonce() }
})

test.afterAll(async () => {
  for (const id of createdTermIds) {
    try {
      await wpDelete(apiOpts, API.termById(TAXONOMY, id))
    } catch { /* 忽略清理失敗 */ }
  }
})

// ─────────────────────────────────────────────────────
// 查詢詞彙列表 GET /terms/:taxonomy
// ─────────────────────────────────────────────────────

test.describe('GET /terms/:taxonomy — 查詢詞彙列表', () => {
  test('product_cat 應回傳 200 和陣列', async () => {
    const res = await wpGet<any[]>(apiOpts, API.terms(TAXONOMY))
    expect(res.status).toBe(200)
    expect(Array.isArray(res.data)).toBe(true)
  })

  test('應包含分頁 headers', async () => {
    const res = await wpGet(apiOpts, API.terms(TAXONOMY), {
      posts_per_page: '1',
    })
    expect(res.status).toBe(200)
    expect(Number(res.headers['x-wp-total'])).toBeGreaterThanOrEqual(0)
  })

  test('預設只回傳頂層詞彙（parent=0）', async () => {
    const res = await wpGet<any[]>(apiOpts, API.terms(TAXONOMY))
    expect(res.status).toBe(200)
    if (Array.isArray(res.data) && res.data.length > 0) {
      for (const term of res.data) {
        expect(Number(term.parent || 0)).toBe(0)
      }
    }
  })

  test('無效的 taxonomy 應回傳錯誤', async () => {
    const res = await wpGet(apiOpts, API.terms('nonexistent_taxonomy_xyz'))
    expect([400, 404]).toContain(res.status)
  })

  test('posts_per_page=1 應限制結果', async () => {
    const res = await wpGet<any[]>(apiOpts, API.terms(TAXONOMY), { posts_per_page: '1' })
    expect(res.status).toBe(200)
    expect((res.data as any[]).length).toBeLessThanOrEqual(1)
  })
})

// ─────────────────────────────────────────────────────
// 查詢單一詞彙 GET /terms/:taxonomy/:id
// ─────────────────────────────────────────────────────

test.describe('GET /terms/:taxonomy/:id — 查詢單一詞彙', () => {
  test('查詢存在的詞彙應回傳完整資料', async () => {
    // 先建立
    const createRes = await wpPost<any>(apiOpts, API.terms(TAXONOMY), {
      name: 'E2E PH 查詢詞彙測試',
    })
    const ids: number[] = Array.isArray(createRes.data.data) ? createRes.data.data : [createRes.data.data]
    const termId = Number(ids[0])
    createdTermIds.push(termId)

    const res = await wpGet<any>(apiOpts, API.termById(TAXONOMY, termId))
    expect(res.status).toBe(200)
    expect(res.data.term_id || res.data.id).toBeTruthy()
    expect(res.data).toHaveProperty('name')
  })

  test('查詢不存在的詞彙（999999）應回傳錯誤', async () => {
    const res = await wpGet(apiOpts, API.termById(TAXONOMY, ID_EDGE.nonExistent))
    expect([400, 404]).toContain(res.status)
  })

  test('查詢 ID=-1 應回傳錯誤', async () => {
    const res = await wpGet(apiOpts, API.termById(TAXONOMY, ID_EDGE.negOne))
    expect(res.status).toBeGreaterThanOrEqual(400)
  })
})

// ─────────────────────────────────────────────────────
// 建立詞彙 POST /terms/:taxonomy
// ─────────────────────────────────────────────────────

test.describe('POST /terms/:taxonomy — 建立詞彙', () => {
  test('建立單一詞彙應回傳 create_success', async () => {
    const res = await wpPost<any>(apiOpts, API.terms(TAXONOMY), {
      name: 'E2E PH 新建詞彙',
    })
    expect(res.status).toBe(200)
    expect(res.data.code).toBe('create_success')
    const ids: number[] = Array.isArray(res.data.data) ? res.data.data : [res.data.data]
    expect(Number(ids[0])).toBeGreaterThan(0)
    createdTermIds.push(...ids.map(Number))
  })

  test('qty=3 應批量建立 3 個詞彙', async () => {
    const res = await wpPost<any>(apiOpts, API.terms(TAXONOMY), {
      name: 'E2E PH 批量詞彙',
      qty: 3,
    })
    expect(res.status).toBe(200)
    expect(res.data.code).toBe('create_success')
    const ids: number[] = Array.isArray(res.data.data) ? res.data.data : [res.data.data]
    expect(ids.length).toBe(3)
    createdTermIds.push(...ids.map(Number))
  })

  test('建立含 description 的詞彙', async () => {
    const res = await wpPost<any>(apiOpts, API.terms(TAXONOMY), {
      name: 'E2E PH 含描述詞彙',
      description: '這是 E2E PH 測試詞彙的描述',
    })
    expect(res.status).toBe(200)
    const ids: number[] = Array.isArray(res.data.data) ? res.data.data : [res.data.data]
    createdTermIds.push(...ids.map(Number))
  })

  test('XSS payload 名稱應被安全處理', async () => {
    const res = await wpPost<any>(apiOpts, API.terms(TAXONOMY), {
      name: STRING_EDGE.xss1,
    })
    expect(res.status).toBe(200)
    const ids: number[] = Array.isArray(res.data.data) ? res.data.data : [res.data.data]
    createdTermIds.push(...ids.map(Number))
    // 查詢驗證
    const getRes = await wpGet<any>(apiOpts, API.termById(TAXONOMY, Number(ids[0])))
    if (getRes.status === 200) {
      expect(getRes.data.name).not.toContain('<script>')
    }
  })

  test('Unicode/Emoji 名稱應正常處理', async () => {
    const res = await wpPost<any>(apiOpts, API.terms(TAXONOMY), {
      name: STRING_EDGE.emoji,
    })
    expect(res.status).toBe(200)
    const ids: number[] = Array.isArray(res.data.data) ? res.data.data : [res.data.data]
    createdTermIds.push(...ids.map(Number))
  })

  test('空名稱應回傳錯誤（不應建立）', async () => {
    const res = await wpPost<any>(apiOpts, API.terms(TAXONOMY), {
      name: STRING_EDGE.empty,
    })
    expect([400, 500]).toContain(res.status)
  })

  test('無效 taxonomy 應回傳錯誤', async () => {
    const res = await wpPost(apiOpts, API.terms('nonexistent_taxonomy_xyz'), {
      name: 'E2E PH 無效 Taxonomy',
    })
    expect(res.status).toBeGreaterThanOrEqual(400)
  })
})

// ─────────────────────────────────────────────────────
// 更新詞彙 POST /terms/:taxonomy/:id
// ─────────────────────────────────────────────────────

test.describe('POST /terms/:taxonomy/:id — 更新詞彙', () => {
  test('成功更新詞彙名稱', async () => {
    const createRes = await wpPost<any>(apiOpts, API.terms(TAXONOMY), {
      name: 'E2E PH 更新前詞彙',
    })
    const ids: number[] = Array.isArray(createRes.data.data) ? createRes.data.data : [createRes.data.data]
    const termId = Number(ids[0])
    createdTermIds.push(termId)

    const updateRes = await wpPost(apiOpts, API.termById(TAXONOMY, termId), {
      name: 'E2E PH 更新後詞彙',
    })
    expect(updateRes.status).toBe(200)

    // 驗證更新
    const getRes = await wpGet<any>(apiOpts, API.termById(TAXONOMY, termId))
    expect(getRes.data.name).toContain('更新後')
  })

  test('更新不存在的詞彙應回傳錯誤', async () => {
    const res = await wpPost(apiOpts, API.termById(TAXONOMY, ID_EDGE.nonExistent), {
      name: '不存在的詞彙',
    })
    expect(res.status).toBeGreaterThanOrEqual(400)
  })
})

// ─────────────────────────────────────────────────────
// 排序詞彙 POST /terms/:taxonomy/sort
// ─────────────────────────────────────────────────────

test.describe('POST /terms/:taxonomy/sort — 排序詞彙', () => {
  test('成功排序詞彙（更新 order meta）', async () => {
    // 建立 2 個詞彙
    const r1 = await wpPost<any>(apiOpts, API.terms(TAXONOMY), { name: 'E2E PH 排序詞彙 A' })
    const r2 = await wpPost<any>(apiOpts, API.terms(TAXONOMY), { name: 'E2E PH 排序詞彙 B' })
    const id1 = Number((Array.isArray(r1.data.data) ? r1.data.data : [r1.data.data])[0])
    const id2 = Number((Array.isArray(r2.data.data) ? r2.data.data : [r2.data.data])[0])
    createdTermIds.push(id1, id2)

    const tree = [
      { id: String(id1), order: '0', parent: '0' },
      { id: String(id2), order: '1', parent: '0' },
    ]

    const sortRes = await wpPost(apiOpts, API.termSort(TAXONOMY), {
      from_tree: tree,
      to_tree: [...tree].reverse(),
    })
    expect(sortRes.status).toBe(200)
  })

  test('排序不含 id 欄位應回傳錯誤', async () => {
    const res = await wpPost(apiOpts, API.termSort(TAXONOMY), {
      from_tree: [{ order: '0' }],
      to_tree: [{ order: '0' }],
    })
    expect([400, 500]).toContain(res.status)
  })
})

// ─────────────────────────────────────────────────────
// 刪除詞彙 DELETE /terms/:taxonomy/:id
// ─────────────────────────────────────────────────────

test.describe('DELETE /terms/:taxonomy/:id — 刪除詞彙', () => {
  test('刪除詞彙應成功', async () => {
    const createRes = await wpPost<any>(apiOpts, API.terms(TAXONOMY), {
      name: 'E2E PH 待刪除詞彙',
    })
    const ids: number[] = Array.isArray(createRes.data.data) ? createRes.data.data : [createRes.data.data]
    const termId = Number(ids[0])

    const delRes = await wpDelete(apiOpts, API.termById(TAXONOMY, termId))
    expect([200, 204]).toContain(delRes.status)

    // 驗證已刪除
    const getRes = await wpGet(apiOpts, API.termById(TAXONOMY, termId))
    expect(getRes.status).toBeGreaterThanOrEqual(400)
  })

  test('刪除不存在的詞彙應回傳錯誤', async () => {
    const res = await wpDelete(apiOpts, API.termById(TAXONOMY, ID_EDGE.nonExistent))
    expect(res.status).toBeGreaterThanOrEqual(400)
  })

  test('批量刪除詞彙應成功', async () => {
    const r1 = await wpPost<any>(apiOpts, API.terms(TAXONOMY), { name: 'E2E PH 批量刪除詞彙 1' })
    const r2 = await wpPost<any>(apiOpts, API.terms(TAXONOMY), { name: 'E2E PH 批量刪除詞彙 2' })
    const id1 = Number((Array.isArray(r1.data.data) ? r1.data.data : [r1.data.data])[0])
    const id2 = Number((Array.isArray(r2.data.data) ? r2.data.data : [r2.data.data])[0])

    const delRes = await wpDelete(apiOpts, API.terms(TAXONOMY), { ids: [id1, id2] })
    expect([200, 204]).toContain(delRes.status)
  })
})
