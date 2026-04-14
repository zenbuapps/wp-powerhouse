@ignore @system-behavior
Feature: 訊息範本

  自訂文章類型（CPT）ph_message_tpl 的註冊與資料模型。
  提供訊息範本的基礎結構，供其他 Power 外掛（如 Power Funnel）
  使用於通知信、系統訊息等場景。

  Background:
    Given Powerhouse 外掛已啟用

  # ---------------------------------------------------------------------------
  # Hook 註冊（register_hooks）
  # ---------------------------------------------------------------------------

  Rule: 系統行為 - register_hooks 將 register_cpt 掛載到 init hook

    Example: register_hooks 註冊 init action
      When Register::register_hooks() 被呼叫
      Then add_action("init", [Register, "register_cpt"]) 被呼叫
      And WordPress init hook 觸發時會執行 register_cpt

    Example: register_hooks 為靜態方法
      When Register::register_hooks() 被呼叫
      Then 方法無需實例化 Register
      And 不回傳任何值（void）

  # ---------------------------------------------------------------------------
  # CPT 註冊（register_cpt）
  # ---------------------------------------------------------------------------

  Rule: 系統行為 - register_cpt 在 init hook 時註冊 ph_message_tpl CPT

    Example: CPT 成功註冊
      When WordPress init hook 觸發
      Then Register::register_cpt 被呼叫
      And 呼叫 register_post_type("ph_message_tpl", $args)
      And $args 包含 public = true
      And $args 包含 publicly_queryable = true
      And $args 包含 show_ui = true
      And $args 包含 show_in_menu = true
      And $args 包含 query_var = true
      And $args 包含 capability_type = "post"
      And $args 包含 has_archive = true
      And $args 包含 hierarchical = false
      And $args 包含 menu_position = null
      And $args 包含 supports = ["title", "custom-fields"]
      And $args['labels'] 來自 self::labels() 的回傳

    Example: CPT 的 capability_type 為 post
      When ph_message_tpl CPT 註冊完成
      Then 使用標準 post 權限（read_post, edit_post, delete_post）
      And 任何有 edit_posts 權限的角色可以編輯

  # ---------------------------------------------------------------------------
  # 標籤（labels）
  # ---------------------------------------------------------------------------

  Rule: 系統行為 - labels() 定義 ph_message_tpl CPT 的顯示標籤

    Example: labels 回傳 CPT 標籤陣列
      When Register::labels() 被呼叫
      Then 回傳 array<string, string>
      And 包含以下主要 key：
        | key             | 值                                 | 註解                 |
        | name            | Message Template                   | 一般名稱             |
        | singular_name   | Message Template                   | 單數名稱             |
        | menu_name       | Message Templates                  | 後台選單名稱         |
        | name_admin_bar  | Message Template                   | Admin Bar 新增用名稱 |
        | add_new         | Add New                            | 新增按鈕             |
        | add_new_item    | Add New Message Template           | 新增項目             |
        | new_item        | New Message Template               | 新項目               |
        | edit_item       | Edit Message Template              | 編輯項目             |
        | view_item       | View Message Template              | 檢視項目             |
        | all_items       | All Message Templates              | 所有項目             |
        | search_items    | Search Message Templates           | 搜尋項目             |
        | not_found       | No Message Templates found.        | 找不到項目           |
        | not_found_in_trash | No Message Templates found in Trash. | 垃圾桶內找不到項目 |
      And 字串透過 _x / __ 國際化函式包裝
      And translation domain 為 "power_funnel"

    Example: labels 也包含 featured image 相關標籤
      When Register::labels() 被呼叫
      Then 回傳陣列包含 featured_image = "Message Template Cover Image"
      And 包含 set_featured_image、remove_featured_image、use_featured_image 等 key

    Example: Register::label() 回傳 name 標籤
      When Register::label() 被呼叫
      Then 回傳 labels()["name"]（即 "Message Template"）

  # ---------------------------------------------------------------------------
  # DTO 資料模型
  # ---------------------------------------------------------------------------

  Rule: 系統行為 - MessageTemplateDTO 從 post 載入資料

    Example: 從 post ID 建立 DTO
      Given 存在一個 ph_message_tpl 文章，ID 為 100
      And post_title 為 "歡迎信"
      And post_content 為 "<p>歡迎加入</p>"
      And post_meta "subject" 為 "歡迎您"
      And post_meta "content_type" 為 "html"
      When MessageTemplateDTO::of("100") 被呼叫
      Then 回傳 MessageTemplateDTO 物件
      And id 為 "100"
      And name 為 "歡迎信"
      And subject 為 "歡迎您"
      And content 為 "<p>歡迎加入</p>"
      And content_type 為 EContentType::HTML

    Example: post 不存在時回傳 null
      Given 不存在 ID 為 999 的文章
      When MessageTemplateDTO::of("999") 被呼叫
      Then 回傳 null

    Example: content_type 支援多種格式
      Given 存在一個 ph_message_tpl 文章
      When post_meta "content_type" 為 "text"
      Then MessageTemplateDTO 的 content_type 為 EContentType::PLAIN_TEXT

      When post_meta "content_type" 為 "json"
      Then MessageTemplateDTO 的 content_type 為 EContentType::JSON

      When post_meta "content_type" 為 "xml"
      Then MessageTemplateDTO 的 content_type 為 EContentType::XML

  # ---------------------------------------------------------------------------
  # 類型匹配
  # ---------------------------------------------------------------------------

  Rule: 系統行為 - Register::match 判斷文章是否為訊息範本

    Example: ph_message_tpl 類型的文章匹配成功
      Given 一個 WP_Post 物件的 post_type 為 "ph_message_tpl"
      When Register::match($post) 被呼叫
      Then 回傳 true

    Example: 其他類型的文章匹配失敗
      Given 一個 WP_Post 物件的 post_type 為 "post"
      When Register::match($post) 被呼叫
      Then 回傳 false

  # ---------------------------------------------------------------------------
  # 靜態方法
  # ---------------------------------------------------------------------------

  Rule: 系統行為 - Register 提供靜態存取方法

    Example: 取得 post_type 名稱
      When Register::post_type() 被呼叫
      Then 回傳 "ph_message_tpl"

    Example: 取得 post_type 標籤
      When Register::label() 被呼叫
      Then 回傳 "Message Template"
