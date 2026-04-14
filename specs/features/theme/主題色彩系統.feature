@ignore @system-behavior
Feature: 主題色彩系統

  Power 外掛生態系的統一主題色彩系統。基於 daisyUI 主題機制，
  將自訂色彩設定以 CSS 變數形式注入前端 HTML，支援主題切換器
  讓用戶在前台即時切換主題，並持久化至 localStorage。

  Background:
    Given Powerhouse 外掛已啟用
    And powerhouse_settings option 已存在

  # ---------------------------------------------------------------------------
  # 主題啟用/停用
  # ---------------------------------------------------------------------------

  Rule: 前置（狀態）- 主題功能受 enable_theme 設定控制

    Example: 啟用主題時注入 data-theme 屬性
      Given powerhouse_settings.enable_theme 為 "yes"
      And powerhouse_settings.theme 為 "power"
      When 前端頁面載入
      Then HTML 標籤包含 id="tw" class="tailwind" data-theme="power"

    Example: 停用主題時不注入 data-theme
      Given powerhouse_settings.enable_theme 為 "no"
      When 前端頁面載入
      Then HTML 標籤包含 id="tw" class="tailwind"
      And HTML 標籤不包含 data-theme 屬性

    Example: 已存在 data-theme 時不重複添加
      Given 其他外掛已在 HTML 標籤設定 data-theme="custom"
      When language_attributes filter 執行
      Then data-theme 不被重複添加
      And 保留原有的 data-theme 值

  # ---------------------------------------------------------------------------
  # 主題 CSS 變數注入
  # ---------------------------------------------------------------------------

  Rule: 後置（狀態）- 啟用主題時在 wp_head 注入自訂 CSS 變數

    Example: 注入完整的主題色彩 CSS
      Given powerhouse_settings.enable_theme 為 "yes"
      And powerhouse_settings.theme 為 "power"
      And powerhouse_settings.theme_css 包含自訂色彩值
      When wp_head hook 觸發（priority -100）
      Then 輸出 <style> 標籤
      And CSS 選擇器為 #tw[data-theme='power']
      And 包含 --p（primary）、--s（secondary）、--a（accent）等 CSS 變數
      And 包含 --b1（base-100）、--b2（base-200）、--b3（base-300）等基底色
      And 包含 --n（neutral）、--in（info）、--su（success）、--wa（warning）、--er（error）
      And 包含 --rounded-box、--rounded-btn、--border-btn 等幾何變數
      And 包含 color-scheme（light 或 dark）

    Example: 停用主題時不注入 CSS
      Given powerhouse_settings.enable_theme 為 "no"
      When wp_head hook 觸發
      Then 不輸出任何 <style> 標籤

  # ---------------------------------------------------------------------------
  # Theme Model
  # ---------------------------------------------------------------------------

  Rule: 系統行為 - Theme Model 從 powerhouse_settings 載入色彩值

    Example: 從 wp_options 載入自訂色彩
      Given powerhouse_settings.theme_css 包含：
        | key  | value                                                  |
        | --p  | 65% 0.2 280                                            |
        | --s  | 70% 0.15 200                                           |
      And powerhouse_settings.theme 為 "custom-theme"
      When Theme::instance() 被呼叫
      Then Theme 物件的 p 屬性為 "65% 0.2 280"
      And Theme 物件的 s 屬性為 "70% 0.15 200"
      And Theme 物件的 theme 屬性為 "custom-theme"

    Example: theme_css 不存在時使用預設值
      Given powerhouse_settings 中不存在 theme_css
      When Theme::instance() 被呼叫
      Then 使用 Theme Model 類別中定義的預設色彩值
      And theme 預設為 "power"
      And color_scheme 預設為 "light"

    Example: CSS 變數鍵名轉換
      Given theme_css 中的鍵名為 "--rounded-box"
      When Theme Model 載入時
      Then 移除雙破折號前綴，將 "-" 轉換為 "_"
      And 儲存為 PHP 屬性名 "rounded_box"

  # ---------------------------------------------------------------------------
  # remove_double_dash CSS 變數鍵名處理
  # ---------------------------------------------------------------------------

  Rule: 系統行為 - remove_double_dash 將 CSS 變數鍵名轉為 PHP 屬性名

    Example: 移除 "--" 前綴
      Given 輸入陣列 ["--p" => "65% 0.2 280"]
      When remove_double_dash($theme_css) 被呼叫
      Then 回傳 ["p" => "65% 0.2 280"]

    Example: 將 "-" 轉換為 "_"
      Given 輸入陣列 ["--rounded-box" => "1rem"]
      When remove_double_dash($theme_css) 被呼叫
      Then 回傳 ["rounded_box" => "1rem"]

    Example: 同時處理多個鍵名
      Given 輸入陣列 ["--p" => "v1", "--rounded-btn" => "v2", "--animation-input" => "v3"]
      When remove_double_dash($theme_css) 被呼叫
      Then 回傳 ["p" => "v1", "rounded_btn" => "v2", "animation_input" => "v3"]

    Example: 空陣列回傳空陣列
      When remove_double_dash([]) 被呼叫
      Then 回傳 []

    Example: 沒有 "--" 前綴的鍵名也會被處理（只替換 "-" 為 "_"）
      Given 輸入陣列 ["rounded-box" => "1rem"]
      When remove_double_dash($theme_css) 被呼叫
      Then 回傳 ["rounded_box" => "1rem"]
      # 邏輯：先 str_replace("--", "")，再 str_replace("-", "_")

    Example: 呼叫時機是 Theme::instance() 載入 wp_options 後
      Given powerhouse_settings.theme_css 從 wp_options 讀取
      When Theme::instance() 被呼叫（首次）
      Then remove_double_dash 處理 theme_css 後作為 new self(...) 的參數

    Example: to_array 輸出格式（帶破折號）
      When Theme::instance()->to_array(true) 被呼叫
      Then 回傳陣列中的鍵名格式為 "--p"、"--s"、"--rounded-box" 等
      And theme 鍵名保持為 "theme"（不加 --）
      And color_scheme 鍵名轉換為 "color-scheme"

    Example: to_array 輸出格式（不帶破折號）
      When Theme::instance()->to_array(false) 被呼叫
      Then 回傳陣列中的鍵名格式為 "p"、"s"、"rounded_box" 等

  # ---------------------------------------------------------------------------
  # Theme 預設色彩
  # ---------------------------------------------------------------------------

  Rule: 系統行為 - Theme Model 定義完整的預設色彩組

    Example: 預設色彩組包含所有必要的 daisyUI 變數
      When Theme Model 使用預設值
      Then 包含 primary 色系：p（主色）、pc（主色內容）
      And 包含 secondary 色系：s、sc
      And 包含 accent 色系：a、ac
      And 包含 neutral 色系：n、nc
      And 包含 base 色系：b1、b2、b3、bc
      And 包含 status 色系：in、inc、su、suc、wa、wac、er、erc
      And 包含幾何設定：rounded_box、rounded_btn、rounded_badge
      And 包含動畫設定：animation_btn、animation_input、btn_focus_scale
      And 包含邊框設定：border_btn、tab_border、tab_radius
