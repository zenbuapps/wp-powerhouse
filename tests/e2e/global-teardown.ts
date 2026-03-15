/**
 * Global Teardown — 測試完成後清理
 *
 * 還原 LC bypass，確保 plugin.php 回到原始狀態。
 */
import { revertLcBypass } from './helpers/lc-bypass.js'

async function globalTeardown(): Promise<void> {
  console.log('\n[Global Teardown] 開始清理...')
  try {
    revertLcBypass()
  } catch (e) {
    console.warn('[Global Teardown] LC bypass 還原失敗（非致命）:', (e as Error).message)
  }
  console.log('[Global Teardown] 完成\n')
}

export default globalTeardown
