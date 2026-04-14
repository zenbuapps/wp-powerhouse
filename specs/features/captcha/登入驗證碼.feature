@ignore @system-behavior
Feature: 登入驗證碼

  登入頁面驗證碼生成和驗證機制，防止暴力破解攻擊。
  使用 Gregwar/Captcha 套件生成數字驗證碼圖片。

  Background:
    Given Powerhouse 外掛已啟用
    And powerhouse_settings.enable_captcha_login 為 "yes"

  # =========================================================
  # 啟用/停用條件
  # =========================================================

  Rule: 前置（狀態）- enable_captcha_login 為 "no" 時不顯示驗證碼

    Example: 停用登入驗證碼
      Given powerhouse_settings.enable_captcha_login 為 "no"
      When 用戶訪問 WordPress 登入頁面
      Then 登入表單不包含驗證碼欄位
      And authenticate filter 未被掛載

  Rule: 前置（狀態）- power-partner-server/identity 路徑排除

    Example: Power Partner Server 身份驗證頁面不顯示驗證碼
      Given powerhouse_settings.enable_captcha_login 為 "yes"
      And 當前 REQUEST_URI 包含 "power-partner-server/identity"
      When 系統初始化 Login Captcha
      Then 驗證碼功能不載入

  # =========================================================
  # 驗證碼圖片生成
  # =========================================================

  Rule: 後置（狀態）- AJAX 生成驗證碼圖片並存入 session

    Example: 成功生成驗證碼圖片
      Given 用戶未登入
      When 用戶發送 POST wp-admin/admin-ajax.php，action 為 "get_captcha"，附帶有效 nonce
      Then 應回傳 JSON success=true
      And data 為驗證碼圖片的 inline base64 data URI
      And $_SESSION["powerhouse_phrase"] 存有 4 位數字驗證碼
      And $_SESSION["powerhouse_phrase_url"] 存有圖片 data URI

    Example: 驗證碼為 4 位純數字
      When 系統生成驗證碼
      Then 驗證碼長度為 4
      And 驗證碼字符集僅包含 0-9

  # =========================================================
  # 角色篩選機制
  # =========================================================

  Rule: 前置（狀態）- 僅對 captcha_role_list 中的角色要求驗證碼

    Example: 用戶角色在 captcha_role_list 中需要驗證碼
      Given powerhouse_settings.captcha_role_list 為 ["administrator"]
      And 用戶 "admin1" 的角色為 "administrator"
      When 前端 AJAX 發送 need_captcha，username 為 "admin1"
      Then 應回傳 JSON success=true, data=true
      And 前端顯示驗證碼輸入欄位

    Example: 用戶角色不在 captcha_role_list 中不需要驗證碼
      Given powerhouse_settings.captcha_role_list 為 ["administrator"]
      And 用戶 "subscriber1" 的角色為 "subscriber"
      When 前端 AJAX 發送 need_captcha，username 為 "subscriber1"
      Then 應回傳 JSON success=true, data=false
      And 前端直接提交表單，不顯示驗證碼

    Example: captcha_role_list 包含多個角色
      Given powerhouse_settings.captcha_role_list 為 ["administrator", "editor"]
      And 用戶 "editor1" 的角色為 "editor"
      When 前端 AJAX 發送 need_captcha，username 為 "editor1"
      Then 應回傳 JSON success=true, data=true

  # =========================================================
  # need_captcha AJAX 端點
  # =========================================================

  Rule: 前置（狀態）- need_captcha 需要有效的 username

    Example: 缺少 username 時回傳錯誤
      When 前端 AJAX 發送 need_captcha，未提供 username
      Then 應回傳 JSON success=false
      And data 包含 "缺少用戶名稱"

    Example: username 找不到用戶時回傳錯誤
      When 前端 AJAX 發送 need_captcha，username 為 "nonexistent_user"
      Then 應回傳 JSON success=false
      And data 包含 "找不到此用戶名稱"

    Example: 以 Email 作為 username 查詢用戶
      Given 用戶 "admin1" 的 email 為 "admin@example.com"
      And 用戶 "admin1" 的角色為 "administrator"
      And powerhouse_settings.captcha_role_list 為 ["administrator"]
      When 前端 AJAX 發送 need_captcha，username 為 "admin@example.com"
      Then 應回傳 JSON success=true, data=true

  Rule: 前置（狀態）- 結帳頁面跳過 need_captcha 檢查

    Example: 結帳頁面不需要驗證碼
      When 前端 AJAX 發送 need_captcha，pathname 包含 "checkout"
      Then 應回傳 JSON success=true, data=false

  # =========================================================
  # 驗證碼驗證流程（authenticate filter）
  # =========================================================

  Rule: 後置（狀態）- 驗證碼正確時允許登入

    Example: 輸入正確驗證碼成功登入
      Given $_SESSION["powerhouse_phrase"] 為 "1234"
      And 用戶 "admin1" 角色為 "administrator"
      And powerhouse_settings.captcha_role_list 為 ["administrator"]
      When 用戶提交登入表單，powerhouse_captcha 為 "1234"
      Then authenticate filter 回傳 WP_User 物件
      And 登入成功

  Rule: 後置（狀態）- 驗證碼錯誤時回傳 WP_Error

    Example: 輸入錯誤驗證碼登入失敗
      Given $_SESSION["powerhouse_phrase"] 為 "1234"
      And 用戶 "admin1" 角色為 "administrator"
      And powerhouse_settings.captcha_role_list 為 ["administrator"]
      When 用戶提交登入表單，powerhouse_captcha 為 "9999"
      Then authenticate filter 回傳 WP_Error
      And 錯誤碼為 "captcha_failed"
      And 錯誤訊息為 "驗證碼錯誤"

    Example: 未輸入驗證碼登入失敗
      Given $_SESSION["powerhouse_phrase"] 為 "1234"
      And 用戶 "admin1" 角色為 "administrator"
      And powerhouse_settings.captcha_role_list 為 ["administrator"]
      When 用戶提交登入表單，powerhouse_captcha 為空字串
      Then authenticate filter 回傳 WP_Error
      And 錯誤碼為 "captcha_failed"

  # =========================================================
  # 驗證碼驗證的跳過條件
  # =========================================================

  Rule: 前置（狀態）- 非 WP_User 時直接回傳原始值

    Example: 帳號密碼錯誤時不進入驗證碼檢查
      Given authenticate 收到的 $user 為 WP_Error（帳號密碼錯誤）
      When authenticate filter 執行
      Then 直接回傳原始 WP_Error，不檢查驗證碼

  Rule: 前置（狀態）- 角色不在 captcha_role_list 中時跳過驗證碼

    Example: subscriber 角色不需要驗證碼即可登入
      Given powerhouse_settings.captcha_role_list 為 ["administrator"]
      And 用戶 "subscriber1" 角色為 "subscriber"
      When 用戶 "subscriber1" 提交登入表單，未附帶驗證碼
      Then authenticate filter 回傳 WP_User 物件
      And 登入成功

  Rule: 前置（狀態）- 結帳頁面跳過驗證碼檢查

    Example: 從結帳頁面登入不需要驗證碼
      Given REQUEST_URI 為 "/checkout"
      And 用戶 "admin1" 角色為 "administrator"
      And powerhouse_settings.captcha_role_list 為 ["administrator"]
      When 用戶從結帳頁面提交登入表單
      Then authenticate filter 直接回傳 WP_User，不檢查驗證碼

  # =========================================================
  # Session 管理
  # =========================================================

  Rule: 前置（狀態）- 系統在 init 時啟動 session

    Example: PHP session 自動啟動
      Given PHP session 尚未啟動（session_status 為 PHP_SESSION_NONE）
      When WordPress init hook 觸發
      Then session_start() 被呼叫
      And session 可正常讀寫

    Example: PHP session 已啟動時不重複啟動
      Given PHP session 已啟動
      When WordPress init hook 觸發
      Then session_start() 不被重複呼叫

  # =========================================================
  # 前端渲染
  # =========================================================

  Rule: 前置（狀態）- 登入表單渲染驗證碼區塊

    Example: WordPress 標準登入頁面渲染驗證碼
      When 用戶訪問 wp-login.php 登入頁面
      Then login_form hook 觸發渲染驗證碼欄位
      And 驗證碼容器 class 為 "login"
      And 驗證碼容器初始 display 為 "none"（等待 AJAX 判斷後顯示）

    Example: WooCommerce 登入表單渲染驗證碼
      When 用戶訪問 WooCommerce My Account 登入表單
      Then woocommerce_login_form hook 觸發渲染驗證碼欄位
      And 驗證碼容器 class 為 "login"

  Rule: 前置（狀態）- 前端 JavaScript 流程

    Example: 登入表單阻擋提交並判斷角色
      When 用戶在登入表單點擊提交
      Then JavaScript 攔截表單 submit 事件
      And 發送 AJAX need_captcha 請求判斷是否需要驗證碼
      And 若需要驗證碼則顯示驗證碼容器並載入驗證碼圖片
      And 若不需要則直接提交表單

    Example: 點擊「換一組」重新取得驗證碼
      Given 驗證碼容器已顯示
      When 用戶點擊「換一組」連結
      Then 發送 AJAX get_captcha 請求
      And 更新驗證碼圖片 src

  # =========================================================
  # 依賴注入
  # =========================================================

  Rule: 前置（狀態）- jQuery BlockUI 腳本載入

    Example: 載入 jQuery BlockUI
      When login_enqueue_scripts hook 觸發
      Then jquery-blockui 腳本被 enqueue
      And 來源為 WooCommerce 的 jquery.blockUI.min.js

  # =========================================================
  # 內部實作：enqueue_block_ui / generate_captcha / render_captcha_field
  # =========================================================

  Rule: 內部實作 - enqueue_block_ui() 條件式註冊 jquery-blockui 腳本

    Example: jquery-blockui 尚未註冊時自行註冊
      Given wp_script_is("jquery-blockui", "registered") 為 false
      When enqueue_block_ui() 被呼叫
      Then 系統以 site_url() + "/wp-content/plugins/woocommerce/assets/js/jquery-blockui/jquery.blockUI.min.js" 為 src
      And 依賴為 ["jquery"]
      And 版本為 "2.70"
      And 最後參數 in_footer 為 true
      And wp_register_script("jquery-blockui", ...) 被呼叫
      And wp_enqueue_script("jquery-blockui") 被呼叫

    Example: jquery-blockui 已被其他外掛註冊時直接 enqueue
      Given wp_script_is("jquery-blockui", "registered") 為 true
      When enqueue_block_ui() 被呼叫
      Then 不會重複呼叫 wp_register_script
      And 仍然呼叫 wp_enqueue_script("jquery-blockui")

    Example: enqueue_block_ui 掛載於三個 hook
      When Captcha Base 類別初始化
      Then enqueue_block_ui 綁定至 wp_enqueue_scripts
      And enqueue_block_ui 綁定至 login_enqueue_scripts
      And enqueue_block_ui 綁定至 admin_enqueue_scripts

  Rule: 內部實作 - generate_captcha() 使用 Gregwar\Captcha 生成 4 位數字驗證碼

    Example: AJAX 呼叫 generate_captcha() 生成驗證碼並回傳圖片
      Given 已透過 wp_ajax_nopriv_get_captcha action 觸發
      And session 已啟動
      When generate_captcha() 被呼叫
      Then 內部 init() 建立 PhraseBuilder(length=4, charset="0123456789")
      And 內部 init() 建立 CaptchaBuilder(null, $phrase_builder)
      And builder->setInterpolation(false) 被呼叫（更快、圖更醜）
      And builder->build() 被呼叫生成圖片
      And $_SESSION["powerhouse_phrase"] 被設為 builder->getPhrase() 回傳的 4 位數字字串
      And $_SESSION["powerhouse_phrase_url"] 被設為 builder->inline() 回傳的 base64 data URI
      And wp_send_json 回傳 {"success": true, "data": <inline base64 data URI>}
      And 回傳結構中 data 取自 $_SESSION["powerhouse_phrase_url"]

    Example: 驗證碼字符集嚴格限制為 0-9
      Given PhraseBuilder 被初始化
      Then charset 為 "0123456789"
      And length 為 4
      And 不包含任何英文字母或特殊字元

  Rule: 內部實作 - render_captcha_field() 輸出 HTML 表單欄位與內嵌 JavaScript

    Example: 登入頁面渲染驗證碼容器（預設隱藏）
      Given container_class 為 "login"
      When render_captcha_field() 被呼叫
      Then 輸出 <div class="captcha_container login" style="display: none;">
      And 包含 <label for="powerhouse_captcha">驗證碼</label>
      And 包含 <img class="captcha-img" src="" />
      And 包含 <span class="refresh-captcha">換一組</span>
      And 包含 <input type="text" name="powerhouse_captcha" class="input input-text" />

    Example: 註冊頁面渲染驗證碼容器（預設顯示）
      Given container_class 為 "register"
      When render_captcha_field() 被呼叫
      Then 輸出 <div class="captcha_container register" style="display: block;">
      And 註冊頁面建構時即透過 AJAX 呼叫 getCaptcha() 載入驗證碼
      And 登入頁面則透過 blockSubmit() 攔截提交後動態判斷

    Example: 內嵌 JavaScript 包含 Core 類別與 Renderer 類別
      When render_captcha_field() 被呼叫
      Then 輸出 <script type="module" defer> 標籤
      And 內嵌 Core 類別處理 AJAX 取得驗證碼與表單提交邏輯
      And 內嵌 Renderer 類別使用 jQuery BlockUI 顯示 loading 遮罩
      And Core 類別的 ajaxUrl 取自 admin_url("admin-ajax.php")
      And Core 類別的 ajaxNonce 取自 wp_create_nonce("powerhouse_captcha_nonce")
      And type 取自 container_class ("login" | "register")
