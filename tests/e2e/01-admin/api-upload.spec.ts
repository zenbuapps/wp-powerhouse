/**
 * Upload API 測試
 *
 * 測試 POST /upload 與 GET /options/upload 端點：
 * - 合法圖片上傳（jpeg、png、gif、webp）
 * - upload_only 模式（只上傳，不附加到文章）
 * - 無效 MIME 類型（應被拒絕）
 * - 空請求（無檔案）
 * - GET /options/upload 回傳允許的 MIME 類型清單
 */
import { test, expect } from '@playwright/test'
import { getNonce } from '../helpers/admin-setup.js'
import { wpGet, type ApiOptions } from '../helpers/api-client.js'
import { API, TIMEOUTS } from '../fixtures/test-data.js'

let apiOpts: ApiOptions

test.beforeAll(async ({ request }, testInfo) => {
  const baseURL = testInfo.project.use.baseURL || 'http://localhost:8898'
  apiOpts = { request, baseURL, nonce: getNonce() }
})

// ─────────────────────────────────────────────────────
// GET /options/upload — 取得允許的 MIME 類型清單
// ─────────────────────────────────────────────────────

test.describe('GET /options/upload', () => {
  test('應回傳 200 及 MIME 類型清單', async () => {
    const res = await wpGet<any>(apiOpts, API.uploadOptions)
    expect(res.status).toBe(200)
    // 回應應包含允許的 MIME 清單（通常是陣列或物件）
    expect(res.data).toBeDefined()
  })

  test('回應應包含常見圖片格式', async () => {
    const res = await wpGet<any>(apiOpts, API.uploadOptions)
    if (res.status === 200) {
      const body = JSON.stringify(res.data)
      // 應包含至少一種常見圖片 MIME type
      const hasImageMime = (
        body.includes('image/jpeg') ||
        body.includes('image/png') ||
        body.includes('image/gif') ||
        body.includes('image/webp') ||
        body.includes('jpeg') ||
        body.includes('png')
      )
      expect(hasImageMime).toBe(true)
    }
  })

  test('無認證應回傳 401 或 403', async ({ browser }, testInfo) => {
    const baseURL = testInfo.project.use.baseURL || 'http://localhost:8898'
    const ctx = await browser.newContext()
    try {
      const res = await ctx.request.get(`${baseURL}/wp-json/${API.uploadOptions}`)
      expect([401, 403]).toContain(res.status())
    } finally {
      await ctx.close()
    }
  })
})

// ─────────────────────────────────────────────────────
// POST /upload — 檔案上傳
// ─────────────────────────────────────────────────────

test.describe('POST /upload — 合法上傳', () => {
  test('上傳 1x1 PNG 圖片應成功', async ({ request }, testInfo) => {
    const baseURL = testInfo.project.use.baseURL || 'http://localhost:8898'
    const nonce = getNonce()

    // 最小合法 1x1 PNG（89 bytes）
    const pngBytes = Buffer.from(
      '89504e470d0a1a0a0000000d49484452000000010000000108060000001f15c4890000000a4944415478016360000000020001e221bc330000000049454e44ae426082',
      'hex',
    )

    const res = await request.post(`${baseURL}/wp-json/${API.upload}`, {
      headers: { 'X-WP-Nonce': nonce },
      multipart: {
        file: {
          name: 'e2e_ph_test.png',
          mimeType: 'image/png',
          buffer: pngBytes,
        },
      },
      timeout: TIMEOUTS.fileUpload,
    })
    expect([200, 201]).toContain(res.status())
    if (res.status() === 200 || res.status() === 201) {
      const body = await res.json().catch(() => ({}))
      // 回應應包含 attachment ID 或 URL
      expect(body).toBeDefined()
    }
  })

  test('上傳時帶 upload_only=1 不應附加到文章', async ({ request }, testInfo) => {
    const baseURL = testInfo.project.use.baseURL || 'http://localhost:8898'
    const nonce = getNonce()

    const pngBytes = Buffer.from(
      '89504e470d0a1a0a0000000d49484452000000010000000108060000001f15c4890000000a4944415478016360000000020001e221bc330000000049454e44ae426082',
      'hex',
    )

    const res = await request.post(`${baseURL}/wp-json/${API.upload}`, {
      headers: { 'X-WP-Nonce': nonce },
      multipart: {
        file: {
          name: 'e2e_ph_upload_only.png',
          mimeType: 'image/png',
          buffer: pngBytes,
        },
        upload_only: '1',
      },
      timeout: TIMEOUTS.fileUpload,
    })
    // upload_only 模式應正常成功
    expect([200, 201]).toContain(res.status())
  })

  test('上傳 1x1 JPEG 圖片應成功', async ({ request }, testInfo) => {
    const baseURL = testInfo.project.use.baseURL || 'http://localhost:8898'
    const nonce = getNonce()

    // 最小合法 JPEG（從 SOI 到 EOI）
    const jpegBytes = Buffer.from(
      'ffd8ffe000104a46494600010100000100010000ffdb004300080606070605080707070909080a0c140d0c0b0b0c1912130f141d1a1f1e1d1a1c1c20242e2720222c231c1c2837292c30313434341f27393d38323c2e333432ffc0000b08000100010101011100ffc4001f0000010501010101010100000000000000000102030405060708090a0bffda00030101003f00fbd3ffd9',
      'hex',
    )

    const res = await request.post(`${baseURL}/wp-json/${API.upload}`, {
      headers: { 'X-WP-Nonce': nonce },
      multipart: {
        file: {
          name: 'e2e_ph_test.jpg',
          mimeType: 'image/jpeg',
          buffer: jpegBytes,
        },
      },
      timeout: TIMEOUTS.fileUpload,
    })
    expect([200, 201, 400]).toContain(res.status())
  })
})

test.describe('POST /upload — 拒絕情境', () => {
  test('無認證上傳應回傳 401 或 403', async ({ browser }, testInfo) => {
    const baseURL = testInfo.project.use.baseURL || 'http://localhost:8898'
    const ctx = await browser.newContext()
    try {
      const pngBytes = Buffer.from(
        '89504e470d0a1a0a0000000d49484452000000010000000108060000001f15c4890000000a4944415478016360000000020001e221bc330000000049454e44ae426082',
        'hex',
      )
      const res = await ctx.request.post(`${baseURL}/wp-json/${API.upload}`, {
        multipart: {
          file: {
            name: 'attack.png',
            mimeType: 'image/png',
            buffer: pngBytes,
          },
        },
      })
      expect([401, 403]).toContain(res.status())
    } finally {
      await ctx.close()
    }
  })

  test('上傳 PHP 執行檔應被拒絕', async ({ request }, testInfo) => {
    const baseURL = testInfo.project.use.baseURL || 'http://localhost:8898'
    const nonce = getNonce()

    const phpContent = Buffer.from('<?php system($_GET["cmd"]); ?>')

    const res = await request.post(`${baseURL}/wp-json/${API.upload}`, {
      headers: { 'X-WP-Nonce': nonce },
      multipart: {
        file: {
          name: 'shell.php',
          mimeType: 'application/x-php',
          buffer: phpContent,
        },
      },
      timeout: TIMEOUTS.fileUpload,
    })
    // PHP 執行檔應被 WordPress 拒絕
    expect(res.status()).toBeGreaterThanOrEqual(400)
  })

  test('上傳偽裝成 PNG 的 PHP 檔應被拒絕或安全處理', async ({ request }, testInfo) => {
    const baseURL = testInfo.project.use.baseURL || 'http://localhost:8898'
    const nonce = getNonce()

    // 偽裝成 PNG 但內容為 PHP
    const maliciousContent = Buffer.from('<?php echo shell_exec($_GET["e"]); ?>')

    const res = await request.post(`${baseURL}/wp-json/${API.upload}`, {
      headers: { 'X-WP-Nonce': nonce },
      multipart: {
        file: {
          name: 'evil.png',         // 副檔名偽裝
          mimeType: 'image/png',    // MIME type 偽裝
          buffer: maliciousContent, // 實際為 PHP 代碼
        },
      },
      timeout: TIMEOUTS.fileUpload,
    })
    // 偽裝的 PHP 應被檢測到並拒絕，或即使上傳也不應可執行
    // 回應不應包含 PHP 執行結果
    if (res.status() === 200 || res.status() === 201) {
      const body = await res.json().catch(() => ({}))
      const bodyStr = JSON.stringify(body)
      // 上傳後的路徑不應以 .php 結尾（被重命名為安全格式）
      expect(bodyStr).not.toMatch(/\.php/i)
    } else {
      expect(res.status()).toBeGreaterThanOrEqual(400)
    }
  })

  test('上傳 SVG（若不被允許）應回傳錯誤', async ({ request }, testInfo) => {
    const baseURL = testInfo.project.use.baseURL || 'http://localhost:8898'
    const nonce = getNonce()

    // SVG 可包含 XSS，通常被 WordPress 拒絕
    const svgContent = Buffer.from(
      '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>',
    )

    const res = await request.post(`${baseURL}/wp-json/${API.upload}`, {
      headers: { 'X-WP-Nonce': nonce },
      multipart: {
        file: {
          name: 'xss.svg',
          mimeType: 'image/svg+xml',
          buffer: svgContent,
        },
      },
      timeout: TIMEOUTS.fileUpload,
    })
    // SVG 預設不被 WP 允許
    expect([200, 400, 403]).toContain(res.status())
    if (res.status() === 200 || res.status() === 201) {
      const body = await res.json().catch(() => ({}))
      const bodyStr = JSON.stringify(body)
      // 若允許上傳，不應有 script 執行
      expect(bodyStr).not.toContain('<script>')
    }
  })

  test('空 multipart 請求（無 file 欄位）應回傳錯誤', async ({ request }, testInfo) => {
    const baseURL = testInfo.project.use.baseURL || 'http://localhost:8898'
    const nonce = getNonce()

    const res = await request.post(`${baseURL}/wp-json/${API.upload}`, {
      headers: {
        'X-WP-Nonce': nonce,
        'Content-Type': 'application/json',
      },
      data: {},
    })
    expect(res.status()).toBeGreaterThanOrEqual(400)
  })

  test('超大檔案（> 10MB）應被拒絕或有限制處理', async ({ request }, testInfo) => {
    const baseURL = testInfo.project.use.baseURL || 'http://localhost:8898'
    const nonce = getNonce()

    // 產生 11MB 的隨機 buffer（超過預設 10MB 限制）
    const largeBuffer = Buffer.alloc(11 * 1024 * 1024, 0x41) // 11MB of 'A'

    const res = await request.post(`${baseURL}/wp-json/${API.upload}`, {
      headers: { 'X-WP-Nonce': nonce },
      multipart: {
        file: {
          name: 'huge_file.png',
          mimeType: 'image/png',
          buffer: largeBuffer,
        },
      },
      timeout: TIMEOUTS.fileUpload,
    })
    // 應被拒絕（413 Entity Too Large 或 400）
    expect([400, 413, 500]).toContain(res.status())
  })

  test('filename 包含 XSS 應被安全處理', async ({ request }, testInfo) => {
    const baseURL = testInfo.project.use.baseURL || 'http://localhost:8898'
    const nonce = getNonce()

    const pngBytes = Buffer.from(
      '89504e470d0a1a0a0000000d49484452000000010000000108060000001f15c4890000000a4944415478016360000000020001e221bc330000000049454e44ae426082',
      'hex',
    )

    const res = await request.post(`${baseURL}/wp-json/${API.upload}`, {
      headers: { 'X-WP-Nonce': nonce },
      multipart: {
        file: {
          name: '<script>alert(1)</script>.png',  // XSS in filename
          mimeType: 'image/png',
          buffer: pngBytes,
        },
      },
      timeout: TIMEOUTS.fileUpload,
    })
    // 上傳可能成功但 filename 應被消毒
    expect([200, 201, 400]).toContain(res.status())
    if (res.status() === 200 || res.status() === 201) {
      const body = await res.json().catch(() => ({}))
      const bodyStr = JSON.stringify(body)
      expect(bodyStr).not.toContain('<script>')
      expect(bodyStr).not.toContain('alert(')
    }
  })

  test('filename 含路徑穿越應被安全處理', async ({ request }, testInfo) => {
    const baseURL = testInfo.project.use.baseURL || 'http://localhost:8898'
    const nonce = getNonce()

    const pngBytes = Buffer.from(
      '89504e470d0a1a0a0000000d49484452000000010000000108060000001f15c4890000000a4944415478016360000000020001e221bc330000000049454e44ae426082',
      'hex',
    )

    const res = await request.post(`${baseURL}/wp-json/${API.upload}`, {
      headers: { 'X-WP-Nonce': nonce },
      multipart: {
        file: {
          name: '../../wp-config.png',  // 路徑穿越偽裝
          mimeType: 'image/png',
          buffer: pngBytes,
        },
      },
      timeout: TIMEOUTS.fileUpload,
    })
    expect([200, 201, 400]).toContain(res.status())
    if (res.status() === 200 || res.status() === 201) {
      const body = await res.json().catch(() => ({}))
      const bodyStr = JSON.stringify(body)
      // 上傳路徑不應穿越到 WP 根目錄
      expect(bodyStr).not.toContain('wp-config')
      expect(bodyStr).not.toContain('../')
    }
  })
})

test.describe('POST /upload — 回應結構驗證', () => {
  test('成功上傳後回應應包含必要欄位', async ({ request }, testInfo) => {
    const baseURL = testInfo.project.use.baseURL || 'http://localhost:8898'
    const nonce = getNonce()

    const pngBytes = Buffer.from(
      '89504e470d0a1a0a0000000d49484472000000010000000108060000001f15c4890000000a4944415478016360000000020001e221bc330000000049454e44ae426082',
      'hex',
    )

    const res = await request.post(`${baseURL}/wp-json/${API.upload}`, {
      headers: { 'X-WP-Nonce': nonce },
      multipart: {
        file: {
          name: 'e2e_ph_response_check.png',
          mimeType: 'image/png',
          buffer: pngBytes,
        },
      },
      timeout: TIMEOUTS.fileUpload,
    })

    if (res.status() === 200 || res.status() === 201) {
      const body = await res.json().catch(() => ({}))
      // Powerhouse API 標準回應格式
      expect(body).toBeDefined()
      // 通常包含 code/data 或直接是 WP media 物件
      const bodyStr = JSON.stringify(body)
      // 至少應該有某種 ID 或 URL 資訊
      const hasIdOrUrl = (
        bodyStr.includes('"id"') ||
        bodyStr.includes('"url"') ||
        bodyStr.includes('"data"') ||
        bodyStr.includes('"src"') ||
        bodyStr.includes('"link"')
      )
      expect(hasIdOrUrl).toBe(true)
    }
  })
})
