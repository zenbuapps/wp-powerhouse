/**
 * License Check Bypass — 繞過 Powerhouse 授權檢查（E2E 測試專用）
 *
 * 在 plugin.php 中注入 'lc' => false，使 Powerhouse 跳過 Cloud API 授權驗證。
 * 測試結束後由 global-teardown.ts 呼叫 revertLcBypass() 還原。
 */
import * as fs from 'fs'
import * as path from 'path'

const PLUGIN_FILE = path.resolve(import.meta.dirname, '../../../plugin.php')
const BACKUP_FILE = PLUGIN_FILE + '.e2e-backup'
const MARKER = '/* E2E-LC-BYPASS */'

/**
 * 注入 LC bypass 到 plugin.php
 * 若已注入則跳過（冪等）
 */
export function applyLcBypass(): void {
  if (!fs.existsSync(PLUGIN_FILE)) {
    console.warn('[LC Bypass] plugin.php 不存在，跳過 LC bypass 注入')
    return
  }

  const content = fs.readFileSync(PLUGIN_FILE, 'utf-8')
  if (content.includes(MARKER)) {
    console.log('[LC Bypass] 已注入，跳過')
    return
  }

  // 備份原始文件
  fs.copyFileSync(PLUGIN_FILE, BACKUP_FILE)

  // 嘗試精確注入
  const needle = "'callback'    => [ Bootstrap::class, 'instance' ],"
  if (content.includes(needle)) {
    const patched = content.replace(
      needle,
      `${needle}\n\t\t\t\t'lc'          => false, ${MARKER}`,
    )
    fs.writeFileSync(PLUGIN_FILE, patched)
    console.log('[LC Bypass] 已注入（精確模式）')
    return
  }

  // Fallback：regex 模式
  const patched = content.replace(
    /('callback'\s*=>\s*\[.*?\],)/s,
    `$1\n\t\t\t\t'lc'          => false, ${MARKER}`,
  )
  if (patched === content) {
    console.warn('[LC Bypass] 注入失敗（找不到注入點），跳過')
    return
  }
  fs.writeFileSync(PLUGIN_FILE, patched)
  console.log('[LC Bypass] 已注入（regex 模式）')
}

/**
 * 還原 plugin.php 到原始狀態
 */
export function revertLcBypass(): void {
  if (fs.existsSync(BACKUP_FILE)) {
    fs.copyFileSync(BACKUP_FILE, PLUGIN_FILE)
    fs.unlinkSync(BACKUP_FILE)
    console.log('[LC Bypass] 已還原')
  } else {
    console.log('[LC Bypass] 無備份檔，跳過還原')
  }
}
