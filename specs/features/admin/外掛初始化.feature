@ignore @system-behavior
Feature: 外掛初始化

  Powerhouse 的入口 Bootstrap 類別，於外掛載入時初始化所有子系統、
  註冊管理後台選單、設定前後台資源載入，並處理授權碼快取、
  Local 開發環境的 script src 修正等系統級行為。

  Background:
    Given WordPress 已載入
    And Powerhouse 外掛已啟用
    And Powerhouse\Plugin 透過 PluginTrait 的 callback 機制觸發 Bootstrap::instance()

  # ---------------------------------------------------------------------------
  # 子系統載入
  # ---------------------------------------------------------------------------

  Rule: 系統行為 - Bootstrap 初始化時載入所有核心子系統

    Example: 一律載入的核心子系統
      When Bootstrap::instance() 被建構
      Then 以下子系統的 instance() 被呼叫：
        | subsystem                              |
        | J7\Powerhouse\Admin\Entry              |
        | J7\Powerhouse\Api\Base                 |
        | J7\Powerhouse\Api\LC                   |
        | J7\Powerhouse\Domains\Loader           |
        | J7\Powerhouse\Theme\Core\FrontEnd      |
        | J7\Powerhouse\Captcha\Core\Login       |
        | J7\Powerhouse\Captcha\Core\Register    |

  Rule: 系統行為 - WooCommerce 相依的子系統只在 WooCommerce 啟用時載入

    Example: WooCommerce 已啟用時載入 WC 整合子系統
      Given class_exists('\WooCommerce') 回傳 true
      When Bootstrap::instance() 被建構
      Then 以下子系統的 instance() 被呼叫：
        | subsystem                                        |
        | J7\Powerhouse\Compatibility\Services\Scheduler   |
        | J7\Powerhouse\Admin\Debug                        |
        | J7\Powerhouse\Admin\OrderList                    |
        | J7\Powerhouse\Admin\Account                      |
        | J7\Powerhouse\Admin\DelayEmail                   |

    Example: WooCommerce 未啟用時跳過 WC 整合子系統
      Given class_exists('\WooCommerce') 回傳 false
      When Bootstrap::instance() 被建構
      Then Admin\Debug / OrderList / Account / DelayEmail 不會被載入
      And Compatibility\Services\Scheduler 不會被載入

    Example: Admin\OrderDetail 已被註解停用
      When Bootstrap::instance() 被建構
      Then Admin\OrderDetail::instance() 不會被呼叫
      # 程式碼中該行被註解（// Admin\OrderDetail::instance();）

  # ---------------------------------------------------------------------------
  # 管理後台選單
  # ---------------------------------------------------------------------------

  Rule: 系統行為 - 於 admin_menu hook 註冊主選單與子選單

    Example: 主選單於 admin_menu (priority 10) 註冊
      When admin_menu hook 觸發
      Then Bootstrap::add_menu() 被呼叫
      And add_menu_page 註冊以下主選單：
        | page_title | menu_title | capability      | menu_slug  | position |
        | Powerhouse | Powerhouse | manage_options  | powerhouse | 3        |
      And menu icon 為 base64 編碼的 SVG

    Example: 子選單於 admin_menu (priority 100) 註冊
      When admin_menu hook 觸發
      Then Bootstrap::add_submenu() 被呼叫
      And add_submenu_page 註冊「設定」子選單，slug 為 powerhouse

    Example: 有註冊產品資訊時顯示授權碼子選單
      Given apply_filters('powerhouse_product_infos', []) 回傳非空陣列
      When Bootstrap::add_submenu() 執行
      Then add_submenu_page 額外註冊「授權碼」子選單
      And 授權碼子選單的 menu_slug 為 admin.php?page=powerhouse#license-code

    Example: 無註冊產品資訊時不顯示授權碼子選單
      Given apply_filters('powerhouse_product_infos', []) 回傳空陣列
      When Bootstrap::add_submenu() 執行
      Then 只註冊「設定」子選單，不註冊「授權碼」子選單

  # ---------------------------------------------------------------------------
  # 前台資源載入
  # ---------------------------------------------------------------------------

  Rule: 系統行為 - wp_enqueue_scripts 時載入前台共用樣式與腳本

    Example: 前台一律載入共用樣式與 frontend.js
      When wp_enqueue_scripts hook 觸發（priority -100）
      Then wp_enqueue_style 載入 front.min.css（handle: powerhouse_front）
      And wp_enqueue_script 載入 inc/assets/js/frontend.js（handle: powerhouse_frontend_js）
      And frontend.js 以 jQuery 為相依，in-footer + async 載入

  # ---------------------------------------------------------------------------
  # 後台資源載入
  # ---------------------------------------------------------------------------

  Rule: 系統行為 - admin_enqueue_scripts 時依 URL 條件載入後台資源

    Example: 非 Power 系列頁面不載入任何資源
      Given 當前 URL 不包含 "power-" 或 "powerhouse"
      When admin_enqueue_scripts hook 觸發（priority -100）
      Then 不載入任何 admin 樣式或腳本

    Example: Power 系列頁面載入共用 CSS
      Given 當前 URL 包含 "power-" 或 "powerhouse"
      When admin_enqueue_scripts hook 觸發
      Then wp_enqueue_style 載入 admin.min.css（handle: powerhouse_admin）
      And wp_enqueue_style 載入 antd-toolkit 的 style.css（handle: powerhouse_antd_toolkit）

    Example: 僅在 Powerhouse 後台頁面載入 React SPA
      Given 當前 URL 包含 "admin.php?page=powerhouse"
      When admin_enqueue_scripts hook 觸發
      Then Vite\enqueue_asset 載入 js/src/main.tsx（handle: powerhouse, in-footer）
      And wp_localize_script 注入 powerhouse_data 物件
      And powerhouse_data.env 為 Base::simple_encrypt 加密過的環境變數

    Example: 加密後的環境變數包含完整的運行時資訊
      Given 當前 URL 包含 "admin.php?page=powerhouse"
      When enqueue_admin_assets 執行
      Then 加密的 env 陣列至少包含以下 key：
        | key                    |
        | SITE_URL               |
        | API_URL                |
        | CURRENT_USER_ID        |
        | CURRENT_POST_ID        |
        | PERMALINK              |
        | APP_NAME               |
        | KEBAB                  |
        | SNAKE                  |
        | BUNNY_LIBRARY_ID       |
        | BUNNY_CDN_HOSTNAME     |
        | BUNNY_STREAM_API_KEY   |
        | NONCE                  |
        | APP1_SELECTOR          |
        | ELEMENTOR_ENABLED      |
        | ROLES                  |
        | WOOCOMMERCE_ENABLED    |
      And NONCE 使用 wp_create_nonce('wp_rest') 產生
      And ELEMENTOR_ENABLED 依 elementor/elementor.php 是否在 active_plugins 判斷
      And WOOCOMMERCE_ENABLED 依 class_exists('\WooCommerce') 判斷

  # ---------------------------------------------------------------------------
  # 授權碼快取檢查
  # ---------------------------------------------------------------------------

  Rule: 系統行為 - 於 plugins_loaded 尾端觸發授權碼快取檢查

    Example: 所有外掛載入完成後呼叫 LCUtils::get_lc_array()
      When plugins_loaded hook 觸發（priority 999）
      Then Bootstrap::check_lc_array() 被呼叫
      And LCUtils::get_lc_array() 被執行
      # 此呼叫用於觸發 LC 陣列的快取更新（結果不被直接使用）

  # ---------------------------------------------------------------------------
  # Local 環境 script src 修正
  # ---------------------------------------------------------------------------

  Rule: 系統行為 - modify_script_src 於 local 環境修正 Vite build 後的 script src 路徑

    Example: Bootstrap 註冊 modify_script_src 至 script_loader_src filter
      When Bootstrap::instance() 執行
      Then add_filter("script_loader_src", [Bootstrap, "modify_script_src"], 10, 2) 被呼叫

    Example: local 環境且 handle 符合時替換路徑
      Given Plugin::$env === 'local'
      And script handle === Plugin::$kebab
      When modify_script_src($src, $handle) 被呼叫
      Then src 中的 "C:/Users/User/DEV/turborepo/powerrepo/apps" 被 str_replace 為 "plugins"
      And 回傳替換後的 src

    Example: 非 local 環境不做任何替換
      Given Plugin::$env !== 'local'（例如 "production" 或 "staging"）
      When modify_script_src($src, $handle) 被呼叫
      Then 立即回傳原始 src（不做替換）

    Example: handle 不符時不做替換
      Given Plugin::$env === 'local'
      And script handle !== Plugin::$kebab
      When modify_script_src($src, $handle) 被呼叫
      Then 立即回傳原始 src（不做替換）

    Example: 替換僅作用於 Powerhouse 自己的 script
      Given Plugin::$env === 'local'
      And handle 為其他 Power 外掛的 handle
      When modify_script_src 被呼叫
      Then src 不被替換（只有 handle === Plugin::$kebab 時才處理）
