@ignore @command
Feature: 建立評論

  Background:
    Given Powerhouse 外掛已啟用
    And 管理員已登入（user_id=1, display_name="Admin", user_email="admin@test.com"）

  Rule: 後置（狀態）- 成功建立評論並回傳 comment_id

    Example: 建立一則評論
      When 管理員發送 POST /wp-json/v2/powerhouse/comments，body 為：
        | key          | value    |
        | note         | 測試評論 |
        | comment_type | comment  |
      Then 應回傳 200
      And code 為 "create_success"
      And data 為新建評論的 ID
      And 評論的 comment_author 為 "Admin"
      And 評論的 comment_author_email 為 "admin@test.com"

  Rule: 後置（狀態）- 支援 is_customer_note 和 commented_user_id meta

    Example: 建立含 meta 的評論
      When 管理員發送 POST /wp-json/v2/powerhouse/comments，body 為：
        | key               | value    |
        | note              | 客戶備註 |
        | is_customer_note  | 1        |
        | commented_user_id | 42       |
      Then 應回傳 200
      And 評論的 comment_meta 中 is_customer_note 為 "1"
      And 評論的 comment_meta 中 commented_user_id 為 "42"

  Rule: 後置（狀態）- 建立失敗時拋出例外

    Example: wp_insert_comment 回傳 false 時失敗
      Given wp_insert_comment 將回傳 false
      When 管理員發送 POST /wp-json/v2/powerhouse/comments
      Then 操作失敗，錯誤為「create comment failed」
