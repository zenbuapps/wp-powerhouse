@ignore @command
Feature: 刪除評論

  Background:
    Given Powerhouse 外掛已啟用
    And 系統中有以下評論：
      | comment_ID | comment_content |
      | 301        | 測試評論         |

  Rule: 前置（狀態）- id 必須為數字

    Example: id 非數字時拋出例外
      When 管理員發送 DELETE /wp-json/v2/powerhouse/comments/abc
      Then 操作失敗，錯誤為「comment id format not match #abc」

  Rule: 後置（狀態）- 成功刪除評論

    Example: 刪除指定評論
      When 管理員發送 DELETE /wp-json/v2/powerhouse/comments/301
      Then 應回傳 200
      And code 為 "delete_success"
      And data.id 為 "301"

  Rule: 後置（狀態）- 評論不存在時刪除失敗

    Example: 刪除不存在的評論
      When 管理員發送 DELETE /wp-json/v2/powerhouse/comments/999
      Then 操作失敗，錯誤為「delete comment failed #999」
