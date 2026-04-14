@ignore @system-behavior
Feature: 註冊驗證碼

  註冊頁面驗證碼生成和驗證機制，防止自動化批量註冊。
  使用 Gregwar/Captcha 套件生成數字驗證碼圖片。
  僅在 WooCommerce 註冊表單生效。

  Background:
    Given Powerhouse 外掛已啟用
    And powerhouse_settings.enable_captcha_register 為 "yes"

  # =========================================================
  # 啟用/停用條件
  # =========================================================

  Rule: 前置（狀態）- enable_captcha_register 為 "no" 時不顯示驗證碼

    Example: 停用註冊驗證碼
      Given powerhouse_settings.enable_captcha_register 為 "no"
      When 用戶訪問 WooCommerce 註冊頁面
      Then 註冊表單不包含驗證碼欄位
      And wp_pre_insert_user_data filter 未被掛載

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

    Example: 驗證碼為 4 位純數字
      When 系統生成驗證碼
      Then 驗證碼長度為 4
      And 驗證碼字符集僅包含 0-9

  # =========================================================
  # 前端渲染
  # =========================================================

  Rule: 前置（狀態）- WooCommerce 註冊表單渲染驗證碼區塊

    Example: WooCommerce 註冊表單顯示驗證碼
      When 用戶訪問 WooCommerce My Account 註冊表單
      Then woocommerce_register_form hook 觸發渲染驗證碼欄位
      And 驗證碼容器 class 為 "register"
      And 驗證碼容器初始 display 為 "block"（立即顯示）
      And 頁面載入時自動發送 AJAX 取得驗證碼圖片

    Example: 點擊「換一組」重新取得驗證碼
      Given 驗證碼容器已顯示
      When 用戶點擊「換一組」連結
      Then 發送 AJAX get_captcha 請求
      And 更新驗證碼圖片 src

  # =========================================================
  # 驗證碼驗證流程（wp_pre_insert_user_data filter）
  # =========================================================

  Rule: 後置（狀態）- 驗證碼正確時允許註冊

    Example: 輸入正確驗證碼成功註冊
      Given $_SESSION["powerhouse_phrase"] 為 "5678"
      When 用戶提交註冊表單，powerhouse_captcha 為 "5678"
      Then wp_pre_insert_user_data filter 回傳原始 $data
      And 用戶註冊流程繼續執行

  Rule: 後置（狀態）- 驗證碼錯誤時阻止註冊

    Example: 輸入錯誤驗證碼註冊失敗
      Given $_SESSION["powerhouse_phrase"] 為 "5678"
      When 用戶提交註冊表單，powerhouse_captcha 為 "0000"
      Then 拋出 Exception "驗證碼錯誤，註冊已被取消。"

    Example: 未輸入驗證碼註冊失敗
      Given $_SESSION["powerhouse_phrase"] 為 "5678"
      When 用戶提交註冊表單，powerhouse_captcha 為空字串
      Then 拋出 Exception "缺少驗證碼，註冊已被取消。"

  Rule: 後置（狀態）- 錯誤回應格式依請求類型而異

    Example: REST API 請求時回傳 JSON 錯誤
      Given 當前為 REST API 請求（wp_is_serving_rest_request 為 true）
      And 驗證碼驗證失敗
      When wp_pre_insert_user_data filter 執行
      Then wp_send_json_error 回傳 JSON，message 為錯誤訊息

    Example: AJAX 請求時回傳 JSON 錯誤
      Given 當前為 AJAX 請求（wp_doing_ajax 為 true）
      And 驗證碼驗證失敗
      When wp_pre_insert_user_data filter 執行
      Then wp_send_json_error 回傳 JSON，message 為錯誤訊息

    Example: 一般頁面請求時顯示 wp_die 頁面
      Given 當前為一般頁面請求（非 REST/AJAX）
      And 驗證碼驗證失敗
      When wp_pre_insert_user_data filter 執行
      Then wp_die 顯示錯誤訊息頁面

  # =========================================================
  # 跳過條件
  # =========================================================

  Rule: 前置（狀態）- 用戶更新時跳過驗證碼檢查

    Example: 更新現有用戶不需要驗證碼
      Given $update 參數為 true
      When wp_pre_insert_user_data filter 執行
      Then 直接回傳原始 $data，不檢查驗證碼

  # =========================================================
  # Session 管理
  # =========================================================

  Rule: 前置（狀態）- 系統在 init 時啟動 session

    Example: PHP session 自動啟動
      Given PHP session 尚未啟動（session_status 為 PHP_SESSION_NONE）
      When WordPress init hook 觸發
      Then session_start() 被呼叫
      And session 可正常讀寫

  Rule: 後置（狀態）- session 中的驗證碼在驗證後仍保留

    Example: 驗證碼驗證後 session 不清除
      Given $_SESSION["powerhouse_phrase"] 為 "5678"
      And 用戶成功通過驗證碼驗證
      When 驗證完成後
      Then $_SESSION["powerhouse_phrase"] 值仍存在
      And 可用於後續驗證（直到新驗證碼生成覆蓋）

  # =========================================================
  # 與登入驗證碼的差異
  # =========================================================

  Rule: 前置（狀態）- 註冊驗證碼不依賴角色篩選

    Example: 所有註冊用戶都需要驗證碼
      Given powerhouse_settings.enable_captcha_register 為 "yes"
      When 任何用戶提交註冊表單
      Then 一律需要通過驗證碼驗證
      And 不檢查 captcha_role_list（captcha_role_list 僅適用於登入）

  Rule: 前置（狀態）- 註冊驗證碼不阻擋表單提交

    Example: 驗證碼容器在頁面載入時立即顯示
      When 用戶訪問註冊頁面
      Then 驗證碼容器 display 為 "block"（不像登入頁面初始隱藏）
      And 頁面載入時自動呼叫 getCaptcha（不像登入頁面先 blockSubmit 再判斷）
