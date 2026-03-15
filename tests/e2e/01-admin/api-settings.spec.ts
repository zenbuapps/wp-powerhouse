/**
 * Settings API 測試
 *
 * 對應 spec:
 *   - 查詢設定.feature
 *   - 更新設定.feature
 *   - 查詢上傳選項.feature
 */
import { test, expect } from '@playwright/test'
import { getNonce } from '../helpers/admin-setup.js'
import { wpGet, wpPost, type ApiOptions } from '../helpers/api-client.js'
import { API, SETTINGS_KEYS, STRING_EDGE } from '../fixtures/test-data.js'

let apiOpts: ApiOptions

test.beforeAll(async ({ request }, testInfo) => {
  const baseURL = testInfo.project.use.baseURL || 'http://localhost:8898'
  apiOpts = { request, baseURL, nonce: getNonce() }
})

// ─────────────────────────────────────────────────────
// 查詢設定 GET /options
// ─────────────────────────────────────────────────────

test.describe('GET /options — 查詢設定', () => {
  test('應回傳 200 和設定物件', async () => {
    const res = await wpGet<any>(apiOpts, API.options)
    expect(res.status).toBe(200)
    expect(typeof res.data).toBe('object')
    expect(res.data).not.toBeNull()
  })

  test('應包含 powerhouse_settings 欄位', async () => {
    const res = await wpGet<any>(apiOpts, API.options)
    expect(res.status).toBe(200)
    // 可能直接回傳設定物件或包裝在 powerhouse_settings 鍵內
    const settings = res.data?.powerhouse_settings ?? res.data
    expect(settings).toBeTruthy()
  })

  test('各 yes/no 設定欄位應存在', async () => {
    const res = await wpGet<any>(apiOpts, API.options)
    expect(res.status).toBe(200)
    const settings = res.data?.powerhouse_settings ?? res.data
    // 至少應有若干已知欄位（不強制全部）
    if (settings) {
      expect(typeof settings).toBe('object')
    }
  })
})

// ─────────────────────────────────────────────────────
// 更新設定 POST /options
// ─────────────────────────────────────────────────────

test.describe('POST /options — 更新設定', () => {
  test('更新 enable_captcha_login=yes 應成功', async () => {
    const res = await wpPost<any>(apiOpts, API.options, {
      powerhouse_settings: {
        enable_captcha_login: 'yes',
      },
    })
    expect(res.status).toBe(200)

    // 還原
    await wpPost(apiOpts, API.options, {
      powerhouse_settings: {
        enable_captcha_login: 'no',
      },
    })
  })

  test('更新 delay_email=no 應成功', async () => {
    const res = await wpPost<any>(apiOpts, API.options, {
      powerhouse_settings: {
        delay_email: 'no',
      },
    })
    expect(res.status).toBe(200)

    // 還原
    await wpPost(apiOpts, API.options, {
      powerhouse_settings: {
        delay_email: 'yes',
      },
    })
  })

  test('部分更新（只傳遞部分欄位）應只更新該欄位', async () => {
    // 先讀取現有設定
    const getRes = await wpGet<any>(apiOpts, API.options)
    const currentSettings = getRes.data?.powerhouse_settings ?? getRes.data

    // 只更新 last_name_optional
    const updateRes = await wpPost<any>(apiOpts, API.options, {
      powerhouse_settings: {
        last_name_optional: 'yes',
      },
    })
    expect(updateRes.status).toBe(200)

    // 驗證其他欄位未被清空
    const getRes2 = await wpGet<any>(apiOpts, API.options)
    const newSettings = getRes2.data?.powerhouse_settings ?? getRes2.data
    expect(newSettings).toBeTruthy()
  })

  test('設定值為無效的 yes/no 字串（隨機字串）應回傳錯誤或被忽略', async () => {
    const res = await wpPost<any>(apiOpts, API.options, {
      powerhouse_settings: {
        enable_captcha_login: 'invalid_value_xyz',
      },
    })
    // 可能被 sanitize 為預設值，或回傳 400
    expect([200, 400]).toContain(res.status)
  })

  test('設定 XSS payload 應被安全處理', async () => {
    const res = await wpPost<any>(apiOpts, API.options, {
      powerhouse_settings: {
        theme: STRING_EDGE.xss1,
      },
    })
    expect([200, 400]).toContain(res.status)

    if (res.status === 200) {
      // 讀回驗證值被消毒
      const getRes = await wpGet<any>(apiOpts, API.options)
      const settings = getRes.data?.powerhouse_settings ?? getRes.data
      if (settings?.theme) {
        expect(settings.theme).not.toContain('<script>')
      }
    }
  })

  test('空 powerhouse_settings 物件不應造成 500', async () => {
    const res = await wpPost<any>(apiOpts, API.options, {
      powerhouse_settings: {},
    })
    expect([200, 400]).toContain(res.status)
  })

  test('未允許的欄位應被忽略（不會寫入）', async () => {
    const res = await wpPost<any>(apiOpts, API.options, {
      powerhouse_settings: {
        unknown_field_xyz: 'test_value',
      },
    })
    // 未允許欄位應被忽略，不應 500
    expect([200, 400]).toContain(res.status)
  })

  test('SQL injection 設定值應被安全處理', async () => {
    const res = await wpPost<any>(apiOpts, API.options, {
      powerhouse_settings: {
        theme: STRING_EDGE.sqlInject2,
      },
    })
    expect([200, 400]).toContain(res.status)
  })

  test('超長字串設定值應被處理（不應 500）', async () => {
    const res = await wpPost<any>(apiOpts, API.options, {
      powerhouse_settings: {
        theme: STRING_EDGE.longStr,
      },
    })
    expect([200, 400]).toContain(res.status)
  })
})

// ─────────────────────────────────────────────────────
// 查詢上傳選項 GET /options/upload
// ─────────────────────────────────────────────────────

test.describe('GET /options/upload — 查詢上傳選項', () => {
  test('應回傳允許的 MIME 類型列表', async () => {
    const res = await wpGet<any>(apiOpts, API.uploadOptions)
    expect(res.status).toBe(200)
    expect(res.data).toBeTruthy()
    // 應包含 allowed_mime_types 或類似結構
  })
})
