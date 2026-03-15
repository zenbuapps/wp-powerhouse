@ignore @command
Feature: 上傳檔案

  Background:
    Given Powerhouse 外掛已啟用

  Rule: 前置（狀態）- 請求中必須包含 files 檔案參數

    Example: 未傳入檔案時拋出例外
      When 管理員發送 POST /wp-json/v2/powerhouse/upload，body 不包含 files
      Then 操作失敗，錯誤為「upload file not found」

  Rule: 前置（狀態）- MIME 類型必須在允許清單中（若有設定限制）

    Example: 上傳不允許的 MIME 類型時拋出例外
      Given allowed_mime_types 已透過 powerhouse/upload/allowed_mime_types filter 設為 ["image/png"]
      When 管理員上傳一個 application/pdf 檔案
      Then 操作失敗，錯誤包含「not allowed mime type」

  Rule: 後置（狀態）- upload_only=0 時使用 media_handle_upload 建立 attachment

    Example: 上傳圖片到媒體庫
      When 管理員發送 POST /wp-json/v2/powerhouse/upload，body 為 form-data：
        | key         | value |
        | upload_only | 0     |
        | files       | (圖片二進位) |
      Then 應回傳 200
      And code 為 "upload_success"
      And data 包含：
        | 欄位   | 說明              |
        | id     | attachment ID     |
        | url    | 檔案 URL          |
        | type   | MIME 類型          |
        | name   | 檔案名稱          |
        | size   | 檔案大小（bytes） |
        | width  | 圖片寬度          |
        | height | 圖片高度          |

  Rule: 後置（狀態）- upload_only=1 時使用 wp_handle_upload 不建立 attachment

    Example: 僅上傳到目錄
      When 管理員發送 POST /wp-json/v2/powerhouse/upload，body 為 form-data：
        | key         | value |
        | upload_only | 1     |
        | files       | (圖片二進位) |
      Then 應回傳 200
      And code 為 "upload_success"
      And data.id 為 null
      And data.url 為上傳後的檔案路徑

  Rule: 後置（狀態）- 支援多檔案同時上傳

    Example: 同時上傳多個檔案
      When 管理員發送 POST /wp-json/v2/powerhouse/upload，files 包含多個檔案
      Then 應回傳 200
      And data 為檔案資訊陣列，每個項目包含 id、url、type、name、size

  Rule: 後置（狀態）- 非圖片檔案不包含 width/height

    Example: 上傳 PDF 檔案
      When 管理員上傳一個 application/pdf 檔案（upload_only=0）
      Then 應回傳 200
      And data 包含 id、url、type、name、size
      And data 不包含 width、height
