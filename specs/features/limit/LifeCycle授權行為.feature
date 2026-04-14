@ignore @system-behavior
Feature: LifeCycle 授權行為

  # LifeCycle 是 Powerhouse 提供給下游外掛（PowerCourse、PowerShop 等）的「授權原語監聽器」。
  # 它監聽三個 action：grant / update / revoke，收到後把資料寫入 wp_ph_access_itemmeta 表。
  # 下游外掛（例如 PowerCourse 在 woocommerce_order_status_completed 時）負責透過
  # do_action('powerhouse/limit/grant_user_to_item', ...) 觸發本監聽器。
  # 本 feature 描述「當 action 被觸發時」LifeCycle 應有的行為。

  Background:
    Given Powerhouse 外掛已啟用
    And ph_access_itemmeta 資料表已建立
    And LifeCycle::instance() 已於 Limit\Core\V2Api 建構時註冊三個 action listener

  Rule: 前置（狀態）- LifeCycle 監聽三個 action hook

    Example: 三個 action hook 已透過 add_action 註冊
      When Limit\Core\V2Api 被實例化
      Then powerhouse/limit/grant_user_to_item action 綁定 LifeCycle::grant_user_to_item，priority 10，accepted_args 4
      And powerhouse/limit/after_update_user_from_item action 綁定 LifeCycle::update_user_from_item，priority 10，accepted_args 3
      And powerhouse/limit/after_revoke_user_from_item action 綁定 LifeCycle::revoke_user_from_item，priority 10，accepted_args 2

  Rule: 後置（狀態）- powerhouse/limit/grant_user_to_item 被觸發時寫入存取記錄

    Example: 收到 grant action 時寫入 expire_date meta（timestamp 型）
      Given 用戶 ID 為 50，項目 ID 為 200
      When 子外掛執行 do_action('powerhouse/limit/grant_user_to_item', 50, 200, 1800000000, null)
      Then LifeCycle::grant_user_to_item 被呼叫
      And MetaCRUD::update(200, 50, 'expire_date', 1800000000) 被呼叫
      And ph_access_itemmeta 中存在記錄：
        | post_id | user_id | meta_key    | meta_value |
        | 200     | 50      | expire_date | 1800000000 |
      And powerhouse/limit/after_grant_user_to_item action 被觸發，參數為 (50, 200, 1800000000, null)

    Example: 收到 grant action 時寫入 expire_date meta（subscription 型）
      Given 用戶 ID 為 50，項目 ID 為 200
      When 子外掛執行 do_action('powerhouse/limit/grant_user_to_item', 50, 200, 'subscription_123', null)
      Then ph_access_itemmeta 中存在記錄：
        | post_id | user_id | meta_key    | meta_value       |
        | 200     | 50      | expire_date | subscription_123 |
      And powerhouse/limit/after_grant_user_to_item action 被觸發

    Example: 收到 grant action 時攜帶 WC_Order 物件
      Given 用戶 ID 為 50，項目 ID 為 200
      And WC_Order ID 為 500
      When 子外掛執行 do_action('powerhouse/limit/grant_user_to_item', 50, 200, 1800000000, $order)
      Then ph_access_itemmeta 中已寫入記錄
      And powerhouse/limit/after_grant_user_to_item action 第四個參數為該 $order

    Example: expire_date 為 0 表示無期限
      When 子外掛執行 do_action('powerhouse/limit/grant_user_to_item', 50, 200, 0, null)
      Then ph_access_itemmeta 中存在記錄：
        | post_id | user_id | meta_key    | meta_value |
        | 200     | 50      | expire_date | 0          |

  Rule: 前置（錯誤）- MetaCRUD::update 回傳 false 時拋出例外

    Example: 寫入 DB 失敗時拋出 Exception（無訂單）
      Given MetaCRUD::update 會因 DB 故障回傳 false
      When 子外掛執行 do_action('powerhouse/limit/grant_user_to_item', 50, 200, 1800000000, null)
      Then LifeCycle::grant_user_to_item 拋出 Exception
      And 例外訊息包含 "grant user_id #50 access to post_id #200 failed"
      And 例外訊息包含 "expire_date 1800000000"

    Example: 寫入 DB 失敗時例外訊息包含訂單 ID（有訂單）
      Given MetaCRUD::update 會因 DB 故障回傳 false
      And WC_Order ID 為 500
      When 子外掛執行 do_action('powerhouse/limit/grant_user_to_item', 50, 200, 1800000000, $order)
      Then LifeCycle::grant_user_to_item 拋出 Exception
      And 例外訊息包含 "order_id #500"

  Rule: 後置（狀態）- powerhouse/limit/after_grant_user_to_item 在 update 之後無條件觸發

    Example: after_grant action 在 update 成功後觸發
      When LifeCycle::grant_user_to_item 成功執行
      Then powerhouse/limit/after_grant_user_to_item action 被觸發
      And 觸發順序晚於 MetaCRUD::update

    Example: after_grant action 在 update 失敗後仍會觸發（再拋例外）
      Given MetaCRUD::update 回傳 false
      When LifeCycle::grant_user_to_item 被呼叫
      Then powerhouse/limit/after_grant_user_to_item action 仍然被觸發
      And 隨後拋出 Exception

  Rule: 後置（狀態）- powerhouse/limit/after_update_user_from_item 被觸發時更新期限

    Example: 收到 update action 時更新 expire_date timestamp
      Given ph_access_itemmeta 已存在 post_id=200, user_id=50 的記錄
      When 子外掛執行 do_action('powerhouse/limit/after_update_user_from_item', 50, 200, 1900000000)
      Then LifeCycle::update_user_from_item 被呼叫
      And MetaCRUD::update(200, 50, 'expire_date', 1900000000) 被呼叫
      And ph_access_itemmeta 中該記錄的 meta_value 更新為 1900000000

    Example: update 失敗時拋出 Exception
      Given MetaCRUD::update 會回傳 false
      When 子外掛執行 do_action('powerhouse/limit/after_update_user_from_item', 50, 200, 1900000000)
      Then LifeCycle::update_user_from_item 拋出 Exception
      And 例外訊息包含 "Failed to update user item expiration time"
      And 例外訊息包含 "user_id #50"
      And 例外訊息包含 "post_id #200"
      And 例外訊息包含 "timestamp #1900000000"

  Rule: 後置（狀態）- powerhouse/limit/after_revoke_user_from_item 被觸發時刪除存取記錄

    Example: 收到 revoke action 時刪除整筆 ph_access_itemmeta 記錄
      Given ph_access_itemmeta 已存在 post_id=200, user_id=50 的記錄
      When 子外掛執行 do_action('powerhouse/limit/after_revoke_user_from_item', 50, 200)
      Then LifeCycle::revoke_user_from_item 被呼叫
      And MetaCRUD::delete(200, 50) 被呼叫
      And ph_access_itemmeta 中已不存在 post_id=200, user_id=50 的記錄

    Example: revoke 失敗時拋出 Exception
      Given MetaCRUD::delete 會回傳 false
      When 子外掛執行 do_action('powerhouse/limit/after_revoke_user_from_item', 50, 200)
      Then LifeCycle::revoke_user_from_item 拋出 Exception
      And 例外訊息包含 "Failed to remove user"
      And 例外訊息包含 "user_id #50"
      And 例外訊息包含 "post_id #200"

  Rule: 前置（狀態）- LifeCycle 使用 SingletonTrait，全域僅一個實例

    Example: LifeCycle::instance() 多次呼叫回傳同一實例
      When 呼叫 LifeCycle::instance() 兩次
      Then 兩次回傳的物件為同一個 instance
      And add_action 不會重複綁定 listener
