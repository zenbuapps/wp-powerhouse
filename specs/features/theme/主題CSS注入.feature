@ignore @system-behavior
Feature: 主題 CSS 注入

  前端主題色彩 CSS 注入與主題切換器行為。在 wp_head 最早期注入
  自訂主題 CSS 變數，並支援前台主題切換按鈕與 localStorage 持久化。

  Background:
    Given Powerhouse 外掛已啟用
    And powerhouse_settings.enable_theme 為 "yes"

  # ---------------------------------------------------------------------------
  # CSS 注入時機
  # ---------------------------------------------------------------------------

  Rule: 系統行為 - CSS 在 wp_head 最早期注入

    Example: CSS 以最高優先級注入
      When wp_head hook 觸發
      Then custom_theme_color 方法以 priority -100 執行
      And 確保在其他 CSS 之前載入
      And 主題色彩變數可被後續的 CSS 引用

    Example: HTML 屬性以較高優先級注入
      When language_attributes filter 觸發
      Then add_html_attr 方法以 priority 20 執行

  # ---------------------------------------------------------------------------
  # 主題切換器
  # ---------------------------------------------------------------------------

  Rule: 前置（狀態）- 主題切換器受 enable_theme_changer 控制

    Example: 啟用切換器時注入 localStorage 同步腳本
      Given powerhouse_settings.enable_theme_changer 為 "yes"
      When wp_head hook 觸發
      Then 輸出一段同步執行的 JavaScript
      And 腳本讀取 localStorage 的 "theme" 值
      And 若 localStorage 有 theme 值，立即設定 HTML 的 data-theme 屬性
      # 同步執行避免頁面閃爍

    Example: 停用切換器時不注入 localStorage 腳本
      Given powerhouse_settings.enable_theme_changer 為 "no"
      When wp_head hook 觸發
      Then 只輸出 CSS 變數的 <style> 標籤
      And 不輸出 localStorage 同步腳本

    Example: localStorage 無 theme 值時保持伺服器端設定
      Given powerhouse_settings.enable_theme_changer 為 "yes"
      And localStorage 中不存在 "theme" 鍵
      When 前端頁面載入
      Then data-theme 保持伺服器端設定的值
      And 不修改 HTML 屬性

  # ---------------------------------------------------------------------------
  # 主題切換按鈕
  # ---------------------------------------------------------------------------

  Rule: 系統行為 - 主題切換按鈕的渲染邏輯

    Example: 啟用切換器時渲染主題按鈕
      Given powerhouse_settings.enable_theme_changer 為 "yes"
      When FrontEnd::render_button() 被呼叫
      Then 載入 theme 模板
      And 渲染主題切換按鈕

    Example: 停用切換器時不渲染按鈕
      Given powerhouse_settings.enable_theme_changer 為 "no"
      When FrontEnd::render_button() 被呼叫（未帶 force_render）
      Then 不載入任何模板
      And 不渲染按鈕

    Example: 強制渲染模式忽略設定
      Given powerhouse_settings.enable_theme_changer 為 "no"
      When FrontEnd::render_button(true) 被呼叫
      Then 仍然載入 theme 模板
      And 渲染主題切換按鈕

  # ---------------------------------------------------------------------------
  # CSS 輸出格式
  # ---------------------------------------------------------------------------

  Rule: 系統行為 - print_css 輸出完整的 CSS 變數定義

    Example: CSS 變數格式正確
      Given Theme 物件的 theme 為 "power"
      And Theme 物件的 p 為 "59.87% 0.219 259.04"
      When print_css 被呼叫
      Then 輸出的 CSS 格式為：
        """
        <style>#tw[data-theme='power'] {color-scheme: light;--p: 59.87% 0.219 259.04;...}</style>
        """
      And 每個 CSS 變數以分號結尾
      And theme 鍵不出現在 CSS 變數中（僅用於選擇器）

  # ---------------------------------------------------------------------------
  # 邊界條件
  # ---------------------------------------------------------------------------

  Rule: 邊界條件 - Theme 單例模式

    Example: Theme::instance 只建立一次
      When Theme::instance() 被多次呼叫
      Then 僅第一次從 wp_options 讀取設定
      And 後續呼叫回傳相同的 Theme 物件

    Example: 透過 new Theme(array) 可建立自訂實例
      When new Theme(['p' => '50% 0.1 200']) 被呼叫
      Then 建立的 Theme 物件 p 屬性為 "50% 0.1 200"
      And 其他屬性使用預設值
      And 此實例被設定為 static instance
