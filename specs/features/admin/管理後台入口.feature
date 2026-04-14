@ignore @system-behavior
Feature: 管理後台入口

  Powerhouse 管理後台的 toplevel 頁面渲染入口。當管理員進入
  「Powerhouse」toplevel 選單時，攔截 current_screen 事件，
  渲染全螢幕的 React SPA 容器並結束 WordPress 的預設 admin 輸出流程。

  Background:
    Given Powerhouse 外掛已啟用
    And Admin\Entry::instance() 已於 Bootstrap 建構時初始化

  # ---------------------------------------------------------------------------
  # Screen 偵測
  # ---------------------------------------------------------------------------

  Rule: 系統行為 - 於 current_screen 時判斷是否為 Powerhouse toplevel 頁

    Example: 非 admin 請求直接跳過
      Given is_admin() 回傳 false
      When current_screen hook 觸發
      Then maybe_output_admin_page 直接 return
      And 不渲染任何內容

    Example: 非 Powerhouse toplevel 頁面跳過
      Given is_admin() 回傳 true
      And get_current_screen()->id !== 'toplevel_page_powerhouse'
      When current_screen hook 觸發
      Then maybe_output_admin_page 直接 return
      And 不渲染任何內容

    Example: 進入 Powerhouse toplevel 頁時渲染 SPA 並結束
      Given is_admin() 回傳 true
      And get_current_screen()->id === 'toplevel_page_powerhouse'
      When current_screen hook 觸發
      Then Entry::render_page() 被呼叫
      And 呼叫 Bootstrap::enqueue_admin_assets() 載入 SPA 資源
      And Base::render_admin_layout 被呼叫，傳入 title 與 id
      And 執行 exit 中止 WordPress 預設 admin 輸出流程

  # ---------------------------------------------------------------------------
  # 頁面渲染
  # ---------------------------------------------------------------------------

  Rule: 系統行為 - render_page 輸出 SPA 的全螢幕容器

    Example: 頁面 title 包含站點名稱
      Given get_bloginfo('name') 回傳 "My Shop"
      When Entry::render_page() 執行
      Then Base::render_admin_layout 接收到的 title 為 "Powerhouse 後台 | My Shop"

    Example: SPA 掛載容器使用 APP1_SELECTOR
      When Entry::render_page() 執行
      Then Base::render_admin_layout 接收到的 id 為 substr(Base::APP1_SELECTOR, 1)
      # APP1_SELECTOR 為 CSS selector（例如 '#powerhouse_settings'），去除第一個字元後為純 id
