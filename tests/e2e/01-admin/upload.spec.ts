/**
 * 檔案上傳 API 測試
 *
 * 對應 spec: 上傳檔案.feature
 */
import { test, expect } from '@playwright/test'
import { getNonce } from '../helpers/admin-setup.js'
import { wpGet, type ApiOptions } from '../helpers/api-client.js'
import { API } from '../fixtures/test-data.js'
import fs from 'fs'
import path from 'path'

let apiOpts: ApiOptions

test.beforeAll(async ({ request }, testInfo) => {
	const baseURL = testInfo.project.use.baseURL || 'http://localhost:8898'
	apiOpts = { request, baseURL, nonce: getNonce() }
})

// ==================== 上傳檔案 ====================

test.describe('上傳檔案 POST /upload', () => {
	test('上傳圖片到媒體庫 (upload_only=0)', async ({ request }, testInfo) => {
		const baseURL = testInfo.project.use.baseURL || 'http://localhost:8898'
		const nonce = getNonce()

		// 建立測試用圖片（1x1 pixel PNG）
		const testImageDir = path.resolve(import.meta.dirname, '../.uploads')
		if (!fs.existsSync(testImageDir)) {
			fs.mkdirSync(testImageDir, { recursive: true })
		}
		const testImagePath = path.join(testImageDir, 'e2e-test.png')

		// 1x1 pixel PNG binary
		const pngBuffer = Buffer.from(
			'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAAC0lEQVQI12NgAAIABQABNjN9GQAAAABJREFAAkDxkEAAAAASUVORK5CYII=',
			'base64',
		)
		fs.writeFileSync(testImagePath, pngBuffer)

		const res = await request.post(`${baseURL}/wp-json/${API.upload}`, {
			headers: {
				'X-WP-Nonce': nonce,
			},
			multipart: {
				files: {
					name: 'e2e-test.png',
					mimeType: 'image/png',
					buffer: pngBuffer,
				},
				upload_only: '0',
			},
		})

		const data = await res.json()
		expect(res.status()).toBe(200)
		expect(data.code).toBe('upload_success')
		expect(data.data).toHaveProperty('url')

		// 清理
		if (fs.existsSync(testImagePath)) fs.unlinkSync(testImagePath)
	})

	test('僅上傳不加入媒體庫 (upload_only=1)', async ({ request }, testInfo) => {
		const baseURL = testInfo.project.use.baseURL || 'http://localhost:8898'
		const nonce = getNonce()

		const pngBuffer = Buffer.from(
			'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAAC0lEQVQI12NgAAIABQABNjN9GQAAAABJREFAAkDxkEAAAAASUVORK5CYII=',
			'base64',
		)

		const res = await request.post(`${baseURL}/wp-json/${API.upload}`, {
			headers: { 'X-WP-Nonce': nonce },
			multipart: {
				files: {
					name: 'e2e-upload-only.png',
					mimeType: 'image/png',
					buffer: pngBuffer,
				},
				upload_only: '1',
			},
		})

		const data = await res.json()
		expect(res.status()).toBe(200)
		expect(data.code).toBe('upload_success')
		expect(data.data).toHaveProperty('url')
	})

	test('不帶檔案應回傳錯誤', async ({ request }, testInfo) => {
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
})

// ==================== 上傳選項 ====================

test.describe('查詢上傳選項 GET /upload/options', () => {
	test('應回傳允許的 MIME 類型', async () => {
		const res = await wpGet<any>(apiOpts, API.uploadOptions)
		expect(res.status).toBe(200)
		expect(res.data).toBeTruthy()
	})
})
