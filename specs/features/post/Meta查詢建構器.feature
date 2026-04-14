@ignore @model
Feature: Meta 查詢建構器

  描述 Post Domain 內部的 MetaQueryBuilder 與 MetaQueryClause，用於在執行
  WP_Query / WP_User_Query 前，對 raw meta_query 陣列進行程式化的改寫
  （新增、移除、修改 clause 的 key/value/compare）。
  主要由 Powerhouse 的各 Core\V2Api 於 prepare_query_args 階段使用，
  並透過 filter hook 開放給其他模組（如 User\Core\ExtendQuery）擴充。

  Background:
    Given Powerhouse 外掛已啟用

  # ============================================================
  # MetaQueryClause - 單一 meta_query 條件
  # ============================================================

  Rule: MetaQueryClause 為 DTO 繼承 J7\WpUtils\Classes\DTO

    Example: 以陣列建構 clause
      Given 陣列 ["key" => "billing_phone", "value" => "0912", "compare" => "LIKE"]
      When 透過 DTO 基底建立 MetaQueryClause 實例
      Then key 為 "billing_phone"
      And value 為 "0912"
      And compare 為 "LIKE"

    Example: compare 預設為 "="
      Given 陣列 ["key" => "role", "value" => "admin"]
      When 建立 MetaQueryClause
      Then compare 為 "="

  Rule: set 方法支援部分更新 clause 屬性

    Example: 只更新 compare 不動 key 與 value
      Given 已建立 clause(key="user_birthday", value="1990", compare="=")
      When 呼叫 $clause->set(["compare" => "LIKE"])
      Then key 維持 "user_birthday"
      And value 維持 "1990"
      And compare 變更為 "LIKE"
      And 回傳 self 以支援鏈式呼叫

    Example: 同時更新 key、value、compare
      Given 已建立 clause
      When 呼叫 set(["key" => "new_key", "value" => "new_value", "compare" => "!="])
      Then 三個屬性皆被更新

  Rule: format_value 以 {value} 佔位符包裝原值

    Example: 前後加上 "-" 符號
      Given clause value 為 "1990"
      When 呼叫 $clause->format_value("-{value}-")
      Then value 變更為 "-1990-"
      And 回傳 self 以支援鏈式呼叫

    Example: 僅前置字串
      Given clause value 為 "abc"
      When 呼叫 format_value("prefix_{value}")
      Then value 變更為 "prefix_abc"

    Example: 無 {value} 佔位符時僅附加後綴為空
      Given clause value 為 "abc"
      When 呼叫 format_value("plain")
      Then value 變更為 "plainabc"
      And 因為 explode 沒有第二段而用空字串

  # ============================================================
  # MetaQueryBuilder - meta_query 陣列操作
  # ============================================================

  Rule: MetaQueryBuilder 從 raw meta_query 陣列初始化

    Example: 保留 relation 並將其他項轉為 Clause
      Given raw_meta_query 為
        """
        [
          "relation" => "AND",
          0 => ["key" => "a", "value" => "1", "compare" => "="],
          1 => ["key" => "b", "value" => "2", "compare" => "LIKE"]
        ]
        """
      When 建立 MetaQueryBuilder($raw_meta_query)
      Then relation 為 "AND"
      And clauses 為包含 2 個 MetaQueryClause 實例的陣列

    Example: 沒有 relation 時預設為 "AND"
      Given raw_meta_query 為 [["key" => "a", "value" => "1"]]
      When 建立 MetaQueryBuilder
      Then relation 為 "AND"

    Example: 非陣列項目被略過
      Given raw_meta_query 為 [["key" => "a"], "not_an_array", 123]
      When 建立 MetaQueryBuilder
      Then clauses 只包含 1 個 Clause

  Rule: find 以 key 搜尋 clause

    Example: 找到對應 key 的 clause
      Given builder 的 clauses 包含 key="billing_phone" 的 clause
      When 呼叫 $builder->find("billing_phone")
      Then 回傳該 MetaQueryClause 實例

    Example: 找不到時回傳 null
      Given builder 的 clauses 不含 key="unknown" 的 clause
      When 呼叫 find("unknown")
      Then 回傳 null

  Rule: remove 依 key 移除 clause

    Example: 移除指定 key
      Given builder clauses 包含 keys ["a", "b", "c"]
      When 呼叫 $builder->remove("b")
      Then clauses 剩下 keys ["a", "c"]
      And 回傳 self 以支援鏈式呼叫

  Rule: add 以陣列或 Clause 新增條件

    Example: 以陣列新增
      When 呼叫 $builder->add(["key" => "new", "value" => "val"])
      Then clauses 增加一個新的 MetaQueryClause

    Example: 以 Clause 實例新增
      Given 已有 clause 實例
      When 呼叫 $builder->add($clause)
      Then clauses 直接附加該實例

  Rule: get_meta_query 輸出可用於 WP_Query 的陣列

    Example: 含 relation 與所有 clause 的 to_array 結果
      Given builder 有 2 個 clauses 且 relation 為 "OR"
      When 呼叫 get_meta_query()
      Then 回傳陣列第一個 key 為 "relation" 值為 "OR"
      And 其後為各 clause 的 to_array() 結果

    Example: clauses 為空時回傳空陣列
      Given builder 的 clauses 為空
      When 呼叫 get_meta_query()
      Then 回傳 []（不包含 relation）
