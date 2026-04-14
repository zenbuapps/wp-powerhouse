@ignore @system-behavior
Feature: Debug 工具

  提供管理員在後台檢視、下載、刪除 wp-content/debug.log 的工具，
  並在 Admin Bar 新增「Logs」快捷選單連結到 WC Logger 與 Debug Log Viewer。
  僅在 WooCommerce 啟用時由 Bootstrap 載入。

  Background:
    Given WooCommerce 已啟用
    And Powerhouse 外掛已啟用
    And Admin\Debug::instance() 已於 Bootstrap 建構時初始化

  # ---------------------------------------------------------------------------
  # Hook 註冊
  # ---------------------------------------------------------------------------

  Rule: 系統行為 - 初始化時註冊必要的 hooks

    Example: 建構子註冊以下 hooks
      When Admin\Debug::__construct 執行
      Then 以下 hook 被註冊：
        | hook                            | callback                      | priority |
        | admin_menu                      | add_debug_submenu_page        | -10      |
        | admin_bar_menu                  | add_debug_admin_bar_menu      | 100      |
        | admin_post_delete_debug_log     | handle_delete_debug_log       | 10       |
        | http_api_debug                  | __return_true                 | 10       |

  # ---------------------------------------------------------------------------
  # 子選單註冊
  # ---------------------------------------------------------------------------

  Rule: 系統行為 - 在「工具」選單下新增 Debug Log Viewer 子選單

    Example: tools.php 子選單不存在時跳過
      Given 全域變數 $submenu['tools.php'] 不存在
      When add_debug_submenu_page 執行
      Then 不註冊任何子選單

    Example: 尚未註冊 debug-log-viewer 時註冊
      Given $submenu['tools.php'] 存在
      And $submenu['tools.php'] 中沒有 slug 為 debug-log-viewer 的項目
      When add_debug_submenu_page 執行
      Then add_submenu_page 註冊以下項目：
        | parent     | page_title       | menu_title | capability     | menu_slug         | position |
        | tools.php  | Debug Log Viewer | Debug Log  | manage_options | debug-log-viewer  | 1000     |

    Example: 已經註冊過時不重複註冊
      Given $submenu['tools.php'] 中已存在 slug 為 debug-log-viewer 的項目
      When add_debug_submenu_page 執行
      Then 不呼叫 add_submenu_page

  # ---------------------------------------------------------------------------
  # Debug Log 頁面
  # ---------------------------------------------------------------------------

  Rule: 系統行為 - debug_log_page_content 渲染 Debug Log 頁面的 HTML 輸出

    Example: 頁面包含下載與刪除按鈕
      When debug_log_page_content 被呼叫（由 add_submenu_page 的 callback 觸發）
      Then 輸出 <h1>Debug Log</h1>
      And 頁面顯示「下載 debug.log」連結，指向 site_url('/wp-content/debug.log')
      And 頁面顯示「刪除 debug.log」按鈕（帶有 confirm JS 提示）
      And form action 為 admin_url('admin-post.php?action=delete_debug_log')
      And 頁面包含 wp_nonce_field('delete_debug_log') 的隱藏欄位
      And 頁面顯示「前往底部」錨點連結指向 #bottom
      And 頁面以 <pre style="line-height: 0.75;"> 包裹 log 內容
      And 頁面底部輸出 <div id="bottom"></div>

    Example: debug_log_page_content 呼叫 read_debug_log 取得內容
      Given wp-content/debug.log 檔案存在
      When debug_log_page_content 執行
      Then read_debug_log 被呼叫（讀取最後 1000 行）
      And 以 nl2br + esc_html 輸出至 <pre> 區塊

    Example: debug.log 檔案不存在時顯示提示
      Given wp-content/debug.log 檔案不存在
      When debug_log_page_content 執行
      Then 顯示 "Log file does not exist."

    Example: 讀取 debug.log 失敗時顯示錯誤
      Given wp-content/debug.log 存在但無法讀取內容
      When read_debug_log 執行
      Then 回傳 "Error reading log file."

    Example: URL 包含 deleted=1 時顯示刪除成功訊息
      Given URL 參數 $_GET['deleted'] === '1'
      When debug_log_page_content 執行
      Then 輸出 <div class="notice notice-success"><p>Debug log 已成功刪除</p></div>

    Example: URL 包含 deleted=0 時顯示刪除失敗訊息
      Given URL 參數 $_GET['deleted'] 存在且非 '1'
      When debug_log_page_content 執行
      Then 輸出 <div class="notice notice-error"><p>刪除 Debug log 失敗</p></div>

    Example: URL 沒有 deleted 參數時不顯示通知
      Given $_GET['deleted'] 不存在
      When debug_log_page_content 執行
      Then 不輸出任何 notice div

  # ---------------------------------------------------------------------------
  # Admin Bar 選單
  # ---------------------------------------------------------------------------

  Rule: 系統行為 - 於 Admin Bar 右側新增 Logs 快捷選單

    Example: 非管理員不顯示 Logs 選單
      Given 當前用戶不具 manage_options 權限
      When add_debug_admin_bar_menu 執行
      Then 不在 Admin Bar 新增任何節點

    Example: 管理員看到 Logs 主節點與子選單
      Given 當前用戶具 manage_options 權限
      When add_debug_admin_bar_menu 執行
      Then Admin Bar 新增主節點：
        | id          | title | parent        |
        | debug-tools | Logs  | top-secondary |
      And Logs 主節點的 href 指向 WC Logs 頁面（帶有 debugger-{today} file_id）
      And 新增 WC Logger 子節點（id: wc-logger, parent: debug-tools）
      And 新增 Debug Log 子節點（id: debug-log-viewer, parent: debug-tools）

  # ---------------------------------------------------------------------------
  # 刪除 debug.log
  # ---------------------------------------------------------------------------

  Rule: 系統行為 - handle_delete_debug_log 驗證權限與 nonce 後刪除檔案

    Example: 非管理員無權限刪除
      Given 當前用戶不具 manage_options 權限
      When admin_post_delete_debug_log 觸發
      Then wp_die('權限不足')

    Example: nonce 無效時阻止
      Given 當前用戶具 manage_options 權限
      And $_REQUEST['_wpnonce'] 無法通過 wp_verify_nonce('delete_debug_log')
      When admin_post_delete_debug_log 觸發
      Then wp_die('無效的請求')

    Example: 刪除成功後導向並帶 deleted=1
      Given 權限與 nonce 驗證通過
      And wp-content/debug.log 存在且可刪除
      When admin_post_delete_debug_log 觸發
      Then unlink(debug.log) 成功
      And wp_redirect 帶 deleted=1 參數回到 wp_get_referer()

    Example: 刪除失敗後導向並帶 deleted=0
      Given 權限與 nonce 驗證通過
      And wp-content/debug.log 不存在或刪除失敗
      When admin_post_delete_debug_log 觸發
      Then wp_redirect 帶 deleted=0 參數回到 wp_get_referer()
