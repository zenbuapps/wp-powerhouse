@ignore
Feature: 上傳檔案

  Background:
    Given 系統中有以下用戶：
      | userId | name   | email              | role          |
      | 1      | Admin  | admin@example.com  | administrator |

  # ========== 前置（參數）==========
  Rule: 前置（參數）- files 必須存在
    Example: 沒有檔案時應回傳錯誤
      When Admin 發送 POST /wp-json/v2/powerhouse/upload（不帶檔案）
      Then 應回傳錯誤 "upload file not found"

  Rule: 前置（參數）- MIME 類型需符合允許清單（若有設定）
    Example: 上傳不允許的 MIME 類型應回傳錯誤
      Given 系統設定允許的 MIME 類型為 ["image/jpeg", "image/png"]
      When Admin 上傳一個 application/pdf 檔案
      Then 應回傳錯誤 "not allowed mime type"

  # ========== 後置（狀態）==========
  Rule: 後置（狀態）- upload_only=0 時新增到媒體庫
    Example: 上傳圖片到媒體庫
      When Admin 發送 POST /wp-json/v2/powerhouse/upload：
        | files       | test.jpg（binary） |
        | upload_only | 0                  |
      Then 應回傳 200 且 code 為 "upload_success"
      And data 應包含 id（attachment ID）
      And data 應包含 url、type、name、size、width、height

  Rule: 後置（狀態）- upload_only=1 時僅上傳不加入媒體庫
    Example: 僅上傳檔案不加入媒體庫
      When Admin 發送 POST /wp-json/v2/powerhouse/upload：
        | files       | test.jpg（binary） |
        | upload_only | 1                  |
      Then 應回傳 200 且 code 為 "upload_success"
      And data 的 id 應為 null
      And data 應包含 url

  Rule: 後置（狀態）- 支援多檔案上傳
    Example: 同時上傳多個檔案
      When Admin 發送 POST /wp-json/v2/powerhouse/upload（帶多個檔案）
      Then 應回傳 200 且 data 為陣列，包含每個檔案的資訊
