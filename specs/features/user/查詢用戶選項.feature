@ignore @query
Feature: 查詢用戶選項

  Background:
    Given Powerhouse 外掛已啟用

  Rule: 後置（狀態）- 回傳用戶管理所需的選項資料（可編輯角色列表）

    Example: 成功查詢用戶選項
      When 管理員發送 GET /wp-json/v2/powerhouse/users/options
      Then 應回傳 200
      And code 為 "get_success"
      And data.roles 包含所有可編輯的 WordPress 角色：
        | value         | label         |
        | administrator | Administrator |
        | subscriber    | Subscriber    |
        | (其他角色)    | (對應名稱)    |
