@ignore @model
Feature: 用戶查詢擴展

  描述 User Domain 的 ExtendQuery 類別（Domains/User/Core/ExtendQuery.php），
  其於 WP_User_Query 的 meta_query 被組合後，透過 filter hook
  "powerhouse/user/prepare_query_args/meta_query_builder" 對特定 meta key 做查詢方式改寫。

  Background:
    Given Powerhouse 外掛已啟用
    And User Domain 已載入
    And ExtendQuery 為 Singleton 於 Bootstrap 階段實例化

  Rule: ExtendQuery 於 constructor 註冊 meta_query_builder filter

    Example: 建構時附加 filter callback
      Given ExtendQuery::instance() 被呼叫
      When ExtendQuery 建構子執行
      Then 註冊 filter "powerhouse/user/prepare_query_args/meta_query_builder"
      And 以優先順序 10 綁定 extend_query_args 方法

  Rule: billing_phone 改為 LIKE 模糊比對

    Example: meta_query 含 billing_phone 時 compare 被改為 LIKE
      Given MetaQueryBuilder 的 clauses 包含 key="billing_phone" compare="="
      When 透過 filter 呼叫 extend_query_args($builder)
      Then builder 找到 billing_phone clause
      And 將該 clause 的 compare 改為 "LIKE"
      And value 維持不變

    Example: meta_query 不含 billing_phone 時無副作用
      Given MetaQueryBuilder 的 clauses 不含 billing_phone
      When 呼叫 extend_query_args
      Then find("billing_phone") 回傳 null
      And 因 nullsafe operator ?-> 不觸發錯誤

  Rule: user_birthday 以 LIKE 搭配 -{value}- 包裝後模糊比對

    Example: 將 value 以 "-" 前後包裹
      Given MetaQueryBuilder 的 clauses 包含 key="user_birthday" value="1990-01-01" compare="="
      When 呼叫 extend_query_args($builder)
      Then user_birthday clause 的 value 被改為 "-1990-01-01-"
      And compare 被改為 "LIKE"

    Example: 搭配 User CRUD 的查詢流程
      Given Domains/User/Utils/CRUD.php 在 prepare_query_args 中觸發
        """
        $builder = apply_filters('powerhouse/user/prepare_query_args/meta_query_builder', $builder);
        """
      And ExtendQuery::extend_query_args 為該 filter 的預設監聽器
      Then 所有 User 查詢都會套用 billing_phone 與 user_birthday 的改寫規則

  Rule: extend_query_args 回傳同一個 builder 實例

    Example: 鏈式呼叫後回傳 builder
      Given 已建立 MetaQueryBuilder 實例
      When 呼叫 extend_query_args($builder)
      Then 回傳的 builder 為同一實例
      And 後續 get_meta_query() 將反映修改後的 meta_query 陣列
