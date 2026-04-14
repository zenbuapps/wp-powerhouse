@ignore @model
Feature: 共享工具函式庫

  描述 Powerhouse 的共享工具類別：純函式 helper、enum、utility。
  這些類別不包含業務邏輯，僅提供其他 Domain 共用的技術能力。

  檔案範圍：
    - inc/classes/Shared/Enums/EObjectType.php
    - inc/classes/Shared/Enums/EOperater.php
    - inc/classes/Shared/Helpers/CompareHelper.php
    - inc/classes/Shared/Helpers/NonceHelper.php
    - inc/classes/Shared/Helpers/ReplaceHelper.php
    - inc/classes/Utils/Compare.php
    - inc/classes/Utils/DateTimeHandler.php
    - inc/classes/Utils/ExportCSV.php
    - inc/classes/Settings/Core/ApiBoosterRule.php

  Background:
    Given Powerhouse 外掛已啟用

  Rule: EObjectType enum - 物件類型列舉

    EObjectType 用來標示一個值屬於哪一種常見的物件/資料型態，
    主要被 ReplaceHelper 用來判斷 placeholder 的類型前綴。

    Example: 列舉所有支援的物件類型
      When EObjectType 被列舉
      Then 應包含以下項目（case => value）：
        | case    | value   |
        | Array   | arr     |
        | User    | user    |
        | Product | product |
        | Order   | order   |
        | Post    | post    |
        | Object  | obj     |

    Example: 由陣列推斷類型
      Given 輸入為 PHP array
      When 呼叫 EObjectType::get_type($arr)
      Then 回傳 EObjectType::Array

    Example: 由 WP_User 實例推斷類型
      Given 輸入為 \WP_User 實例
      When 呼叫 EObjectType::get_type($user)
      Then 回傳 EObjectType::User

    Example: 由 WC_Product 實例推斷類型
      Given 輸入為 \WC_Product 實例
      When 呼叫 EObjectType::get_type($product)
      Then 回傳 EObjectType::Product

    Example: 由 WP_Post 實例推斷類型
      Given 輸入為 \WP_Post 實例
      When 呼叫 EObjectType::get_type($post)
      Then 回傳 EObjectType::Post

    Example: 由 WC_Order 實例推斷類型
      Given 輸入為 \WC_Order 實例
      When 呼叫 EObjectType::get_type($order)
      Then 回傳 EObjectType::Order

    Example: 其他泛用物件回傳 Object
      Given 輸入為任意未被前述類別命中的 object
      When 呼叫 EObjectType::get_type($obj)
      Then 回傳 EObjectType::Object

    Example: 無法識別的型態拋出例外
      Given 輸入為純量（例如 string、int）
      When 呼叫 EObjectType::get_type($value)
      Then 拋出 \Exception("Unsupported object type")

  Rule: EOperater enum - 比較運算子列舉

    EOperater 定義一組通用的比較運算子字串常數，
    供 CompareHelper 等比較工具使用；命名風格參考 Refine 的 CrudFilter。

    Example: 列舉所有支援的運算子
      When EOperater 被列舉
      Then 應包含以下主要運算子（name => value）：
        | name              | value     |
        | EXACT             | exact     |
        | EQUAL             | eq        |
        | NOT_EQUAL         | ne        |
        | LESS              | lt        |
        | GREATER           | gt        |
        | LESS_OR_EQUAL     | lte       |
        | GREATER_OR_EQUAL  | gte       |
        | IN                | in        |
        | NOT_IN            | nin       |
        | IN_ARRAY          | ina       |
        | NOT_IN_ARRAY      | nina      |
        | CONTAINS          | contains  |
        | NOT_CONTAINS      | ncontains |
        | BETWEEN           | between   |
        | NOT_BETWEEN       | nbetween  |
        | IS_NULL           | null      |
        | NOT_NULL          | nnull     |
        | STARTS_WITH       | startswith|
        | ENDS_WITH         | endswith  |
        | OR                | or        |
        | AND               | and       |
      And 另包含 case-sensitive 變體：EQUAL_SENSITIVE/eqs、CONTAINS_SENSITIVE/containss、STARTS_WITH_SENSITIVE/startswiths、ENDS_WITH_SENSITIVE/endswiths 及其 NOT_ 版本

  Rule: CompareHelper - 流暢式比較 Helper

    CompareHelper 是一個 final class，提供 fluent API 串接多個比較，
    所有比較結果以 AND 聚合（任一為 false 即整體為 false）。
    CompareHelper 屬於 Shared\Helpers 層，與 EOperater enum 綁定。

    Example: 建立 Helper 並進行單次比較
      Given target 為 5
      And compared 為 10
      When 建立 new CompareHelper(5, 10)
      And 呼叫 ->is(EOperater::LESS)
      And 呼叫 ->match()
      Then 回傳 true

    Example: 串接多個條件，全部滿足才回傳 true
      Given target 為 5
      And compared 為 10
      When 建立 new CompareHelper(5, 10)
      And 呼叫 ->is(EOperater::LESS)->is(EOperater::NOT_EQUAL)->match()
      Then 回傳 true

    Example: 任一條件不成立即回傳 false
      Given target 為 10
      And compared 為 10
      When 呼叫 (new CompareHelper(10, 10))->is(EOperater::LESS)->match()
      Then 回傳 false

    Example: IN 運算子 - 目標在陣列內
      Given target 為 "apple"
      And compared 為 ["apple", "banana"]
      When 呼叫 ->is(EOperater::IN)->match()
      Then 回傳 true

    Example: CONTAINS 運算子 - 字串包含
      Given target 為 "hello world"
      And compared 為 "world"
      When 呼叫 ->is(EOperater::CONTAINS)->match()
      Then 回傳 true

    Example: 支援的運算子清單
      Then CompareHelper::is_match() 實際支援以下 EOperater：
        | LESS | LESS_OR_EQUAL | EQUAL | EXACT | GREATER_OR_EQUAL | GREATER | NOT_EQUAL | IN | NOT_IN | CONTAINS | NOT_CONTAINS |
      And 未列於此清單的運算子一律回傳 false
      And 比較過程若拋出例外會被捕捉並回傳 false

  Rule: NonceHelper - 一次性 Nonce 產生與驗證

    NonceHelper 是一個 final class，提供基於 transient 的高熵一次性 nonce 機制。
    用途與 WordPress 原生 wp_create_nonce / wp_verify_nonce 不同：
    此 Helper 為真正的一次性（驗證通過即失效），並支援自訂 TTL。
    常見使用場景為 Email 驗證流程。

    Example: 建立 NonceHelper 服務
      Given key 為 "user@example.com"
      And ttl 為 600 秒
      When new NonceHelper("user@example.com", 600)
      Then 服務建立成功

    Example: 空 key 會拋出例外
      When new NonceHelper("", 600)
      Then 拋出 \InvalidArgumentException("Nonce key 不可為空")

    Example: ttl 非正整數會拋出例外
      When new NonceHelper("k", 0)
      Then 拋出 \InvalidArgumentException("Nonce TTL 需為正整數")

    Example: 建立 nonce
      Given 已建立 NonceHelper 實例
      When 呼叫 ->create()
      Then 回傳 URL-safe base64 字串（32 bytes 隨機值，去除 =、+、/）
      And 以 transient_key(nonce) 寫入 transient，值為 1，過期時間為 ttl

    Example: 驗證有效且未過期的 nonce
      Given 已呼叫 ->create() 取得 nonce
      When 呼叫 ->verify($nonce)
      Then 回傳 [true, false]（is_valid=true, is_expired=false）
      And transient 被立即刪除（一次性）

    Example: 重複驗證同一個 nonce 會失敗
      Given 已對 nonce 呼叫過一次 verify
      When 再次呼叫 ->verify($nonce)
      Then 回傳 [false, true]（is_valid=false, is_expired=true）

    Example: 驗證空字串拋出例外
      When 呼叫 ->verify("")
      Then 拋出 \InvalidArgumentException("nonce 不可為空")

    Example: transient key 格式
      Then transient key 格式為 "ph_nonce:{sha256(key) 前 16 碼}:{nonce}"

  Rule: ReplaceHelper - 模板字串替換工具

    ReplaceHelper 是一個 final class，支援以 {{type.property}} 或 {{type[key]}}
    形式的 placeholder 替換。會依物件類型（由 EObjectType 判斷）過濾對應的 placeholder。

    Example: 依物件類型過濾 placeholder
      Given template 為 "Hello {{user.display_name}}, order {{order.id}}"
      And 傳入 \WP_User 實例
      When 呼叫 (new ReplaceHelper($template))->replace($user)
      Then 只解析並替換 user.* 類型的 placeholder
      And {{order.id}} 在本次 replace 不被處理

    Example: 串接多個物件替換
      Given template 為 "{{user.display_name}} 訂購 {{product.name}} 於 {{order.id}}"
      When 建立 helper 並依序 ->replace($user)->replace($product)->replace($order)
      And 呼叫 ->get_replaced_template()
      Then 回傳每個 placeholder 皆被對應物件的屬性取代的字串

    Example: 存取巢狀屬性
      Given template 為 "{{user.name}}"
      And $user->name 為 "Alice"
      When 執行替換
      Then 結果為 "Alice"

    Example: 存取陣列 key
      Given template 為 "{{arr[display_name]}}"
      And $arr = ['display_name' => 'Bob']
      When 執行 replace($arr)
      Then 結果為 "Bob"

    Example: 自訂 start/end tag
      Given 使用 new ReplaceHelper($tpl, '<%', '%>')
      Then placeholder 以 <%user.name%> 格式匹配

    Example: 傳入非 array/object 會被忽略
      Given template 為 "{{user.name}}"
      When 呼叫 ->replace("string") 或 ->replace(123)
      Then 不進行替換，filtered_template 維持不變

    Example: 找不到屬性時回傳原 placeholder
      Given template 為 "{{user.nonexistent}}"
      When 執行替換但物件無該屬性
      Then 該位置回傳原始 placeholder 字串 "nonexistent"（由 get_placeholder_value 的 try/catch 處理）

  Rule: Utils\Compare - 日期區間比較容器

    Utils\Compare 與 Shared\Helpers\CompareHelper 是兩個不同用途的類別：
    - Shared\Helpers\CompareHelper：通用值比較 + EOperater
    - Utils\Compare：專門處理「日期區間 + 比較區間」的資料容器，通常搭配圖表使用

    Example: 由參數建構比較時間區間
      Given args 為
        | key           | value                 |
        | after         | 2025-01-01T00:00:00   |
        | before        | 2025-01-31T23:59:59   |
        | compare_type  | month                 |
        | compare_value | 1                     |
      When new Compare($args)
      Then $this->after 為 2025-01-01 的 DateTime
      And $this->before 為 2025-01-31 的 DateTime
      And $this->after_compared 為 after 往前推 1 個月的 DateTime
      And $this->before_compared 為 before 往前推 1 個月的 DateTime
      And 使用 DateTimeHandler::parse_date_time 與 DateTimeHandler::get_compared_date_time 計算

  Rule: Utils\DateTimeHandler - 時間計算工具

    DateTimeHandler 為 abstract class，僅提供靜態方法（無 instance）。
    負責時區感知的時間解析與「往前 N 個單位」的時間計算。

    Example: 解析日期時間字串
      Given date_string 為 "2025-01-01T12:00:00"
      When 呼叫 DateTimeHandler::parse_date_time($date_string)
      Then 回傳 \DateTime 實例
      And 時區為 wp_timezone_string() 回傳的 WordPress 站點時區

    Example: 取得前 N 天
      Given date_time 為 2025-01-10
      And compare_type 為 "day"
      And compare_value 為 3
      When 呼叫 DateTimeHandler::get_compared_date_time($date_time, "day", 3)
      Then 回傳 2025-01-07 的 DateTime

    Example: 取得前 N 週
      Given compare_type 為 "week"
      And compare_value 為 2
      When 呼叫 get_compared_date_time
      Then 回傳 date_time 減 2 週的 DateTime

    Example: 取得前 N 個月 - 處理月份天數不一致
      Given date_time 為 2025-03-31
      And compare_type 為 "month"
      And compare_value 為 1
      When 呼叫 get_compared_date_time
      Then 計算前 1 個月為 2025-02
      And 由於 2 月沒有 31 號，使用 min(31, 28) = 28
      And 回傳 2025-02-28 的 DateTime

    Example: 取得前 N 年
      Given compare_type 為 "year"
      When 呼叫 get_compared_date_time
      Then 回傳 clone 後 modify("-{N} year") 的結果

    Example: 不支援的 compare_type 拋出例外
      Given compare_type 為 "decade"
      When 呼叫 get_compared_date_time
      Then 拋出 \InvalidArgumentException 並訊息為 "不支持的比較類型: decade，支持的類型為: 'day', 'week', 'month', 'year'"

    Example: 類別已載入時不重複宣告
      Given 已有其他程式載入 J7\Powerhouse\Utils\DateTimeHandler
      When 此檔案被再次 require
      Then 檔案開頭的 class_exists 檢查會 return 並略過宣告

  Rule: Utils\ExportCSV - 抽象 CSV 匯出基底

    ExportCSV 為 abstract class，提供 CSV 匯出的標準流程。
    使用方式：
      1. 繼承此類
      2. 定義 $filename, $rows, $columns
      3. 呼叫 ->export()

    Example: 類別定義的屬性
      Then ExportCSV 定義以下 protected 屬性：
        | property | type                   | 說明                              |
        | filename | string                 | 不含副檔名與日期的檔名前綴        |
        | rows     | array<object>          | 資料來源，每列為一個物件          |
        | columns  | array<string, string>  | key 為物件屬性名，value 為欄位標籤 |

    Example: 執行 export() 的輸出行為
      Given 已設定 filename="orders"
      And columns=['id' => 'ID', 'total' => '金額']
      And rows 為陣列中每個元素皆為有 id、total 屬性的物件
      When 呼叫 ->export()
      Then 送出 header "Content-Type: text/csv; charset=utf-8"
      And 送出 header "Content-Disposition: attachment; filename=orders_{YYYY-MM-DD}.csv"（日期由 wp_date 取得）
      And 輸出 UTF-8 BOM（0xEF 0xBB 0xBF）
      And 第一列寫入 columns 的 values（即欄位標籤）
      And 透過 Utils\Base::batch_process 逐筆處理 rows
      And 每筆以 fputcsv 寫入 get_field_value($row) 結果
      And 最後 fclose 並 exit

    Example: 無法開啟輸出檔案時拋出例外
      When fopen('php://output', 'w') 回傳 false
      Then 拋出 \Exception("無法開啟輸出檔案")

    Example: 無法寫入 CSV 標頭時拋出例外
      When fputcsv(header) 回傳 false
      Then 拋出 \Exception("無法寫入 CSV 標頭")

    Example: 匯出過程任何 Throwable 都會被包裝為 Exception
      When 匯出過程拋出 \Throwable
      Then catch 並拋出 new \Exception($th->getMessage())

    Example: get_field_value 以屬性名對應欄位
      Given row 為含 id=1, total=100 的物件
      And columns 為 ['id' => 'ID', 'missing' => 'X']
      When 呼叫 get_field_value($row)
      Then 回傳 [1, ""]（property_exists 檢查不到時回空字串）

    Example: 欄位值會強制轉 scalar 或保留 null
      When row 的某欄位為物件
      Then 寫入 CSV 前會透過 (string) 強制轉型，維持 scalar|null 型別

  Rule: ApiBoosterRule - API Booster 預設規則 DTO

    ApiBoosterRule 使用 SingletonTrait，提供 API Booster 的預設規則範本（recipes）。
    與 Domain 無關，純粹作為 Settings 頁面的預設值來源。

    Example: 取得預設規則清單
      When 呼叫 ApiBoosterRule::instance()->get_recipes()
      Then 回傳 3 筆規則 recipes，分別為：
        | key                             | name                                |
        | api_booster_rule_power_plugins  | Power 系列外掛 API 時，不載入其他外掛 |
        | api_booster_rule_power_course   | Power Course 後台 API 加速           |
        | api_booster_rule_power_shop     | Power Shop 後台 API 加速             |
      And 每筆皆包含 key, enabled(預設 "no"), name, rules, plugins 欄位

    Example: 基礎外掛白名單
      Then $base_plugins 永遠包含以下 5 個外掛：
        | woocommerce/woocommerce.php                               |
        | woocommerce-subscriptions/woocommerce-subscriptions.php   |
        | powerhouse/plugin.php                                     |
        | elementor/elementor.php                                   |
        | elementor-pro/elementor-pro.php                           |

    Example: Power 系列外掛清單
      Then $power_plugins 包含：
        | power-contract/plugin.php   |
        | power-course/plugin.php     |
        | power-docs/plugin.php       |
        | power-membership/plugin.php |
        | power-partner/plugin.php    |
        | power-shop/plugin.php       |

    Example: Power 系列 recipe 的規則內容
      When 取得 api_booster_rule_power_plugins
      Then rules 為多行字串："/wp-json/power-*\n/wp-json/v2/powerhouse/*"
      And plugins 為 base_plugins 展開後再串接 power_plugins

    Example: Power Course / Power Shop recipe
      Then Power Course recipe 的 plugins 為 base_plugins + ['power-course/plugin.php']
      And Power Shop recipe 的 plugins 為 base_plugins + ['power-shop/plugin.php']
      And 兩者 rules 分別為自身命名空間 + /wp-json/v2/powerhouse/*
