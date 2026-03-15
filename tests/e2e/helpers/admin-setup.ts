/**
 * Admin Setup — 管理員登入與認證狀態管理
 *
 * 提供登入、Nonce 讀取等基礎設施。
 */
import { chromium } from '@playwright/test'
import * as fs from 'fs'
import * as path from 'path'
import { extractNonce } from './api-client.js'

export const AUTH_FILE = path.resolve(import.meta.dirname, '../.auth/admin.json')
export const NONCE_FILE = path.resolve(import.meta.dirname, '../.auth/nonce.txt')

/**
 * 從緩存檔案讀取 nonce（在測試中使用）
 */
export function getNonce(): string {
  return fs.readFileSync(NONCE_FILE, 'utf-8').trim()
}

/**
 * 登入 WordPress Admin，儲存 storageState 與 nonce
 * 回傳 nonce 字串
 */
export async function loginAsAdmin(baseURL: string): Promise<string> {
  const browser = await chromium.launch()
  const context = await browser.newContext()
  const page = await context.newPage()

  // 先注入 test cookie 避免 WordPress cookie check 問題
  await context.addCookies([
    {
      name: 'wordpress_test_cookie',
      value: 'WP+Cookie+check',
      domain: new URL(baseURL).hostname,
      path: '/',
    },
  ])

  await page.goto(`${baseURL}/wp-login.php`, { waitUntil: 'domcontentloaded' })
  await page.fill('#user_login', process.env.WP_ADMIN_USERNAME || 'admin')
  await page.fill('#user_pass', process.env.WP_ADMIN_PASSWORD || 'password')
  await page.click('#wp-submit')
  await page.waitForURL('**/wp-admin/**', { timeout: 60_000 })

  // 儲存認證 cookie
  await context.storageState({ path: AUTH_FILE })

  // 取得 nonce
  const nonce = await extractNonce(page, baseURL)
  fs.writeFileSync(NONCE_FILE, nonce)

  await browser.close()
  return nonce
}
