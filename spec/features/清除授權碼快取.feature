@ignore
Feature: 清除授權碼快取

  Background:
    And 系統中已啟用以下授權碼：
      | product_slug | code        | post_status |
      | power-course | ABC-123-DEF | activated   |

  # ========== 前置（參數）==========
  Rule: 前置（參數）- product_slug 不可為空
    Example: 缺少 product_slug 時應回傳 400
      When 發送 POST /wp-json/v2/powerhouse/lc/invalidate：
        | product_slug | |
      Then 應回傳 400 且 code 為 "invalidate_lc_cache_failed"

  # ========== 後置（狀態）==========
  Rule: 後置（狀態）- 清除成功後 transient 被刪除
    Example: 成功清除授權碼快取
      When 發送 POST /wp-json/v2/powerhouse/lc/invalidate：
        | product_slug | power-course |
      Then 應回傳 200 且 code 為 "invalidate_lc_cache_success"
      And transient "lc_power-course" 應不存在
