@ignore @model
Feature: 抽象 DTO 基底

  描述 Powerhouse 的 Contracts\DTOs 層：純粹用來約束資料結構的 DTO 類別。
  這些類別無業務行為，僅定義欄位型別以利 IDE 自動完成與 PHPStan 檢查。

  檔案範圍：
    - inc/classes/Contracts/DTOs/CallableDTO.php
    - inc/classes/Contracts/DTOs/FormFieldDTO.php

  Background:
    Given Powerhouse 外掛已啟用

  Rule: CallableDTO - 可呼叫 DTO 佔位類別

    CallableDTO 目前為空類別，不繼承 DTO 基底，也不包含任何屬性或方法。
    預留給未來「callable 型別的資料結構」使用，目前僅作為 namespace 佔位。

    Example: 類別結構
      Then J7\Powerhouse\Contracts\DTOs\CallableDTO 存在於 Contracts\DTOs namespace
      And 不繼承任何父類別
      And 不實作任何介面
      And class body 為空（無屬性、無方法）

    Example: 可被 instantiate
      When new CallableDTO()
      Then 物件建立成功（無 constructor 檢查）

    # 注意：此 DTO 尚未實作任何行為，屬於預留檔案。

  Rule: FormFieldDTO - 表單欄位描述 DTO

    FormFieldDTO 繼承自 J7\WpUtils\Classes\DTO，
    用來描述一個表單欄位的完整中繼資料，供前端渲染表單使用。
    結構對齊前端 NodeDefinition 的擴充屬性。

    Example: 類別繼承關係
      Then FormFieldDTO extends J7\WpUtils\Classes\DTO
      And 因此支援 DTO::create($array) factory 與 to_array() 序列化

    Example: 基本屬性定義
      Then 類別定義以下 public 屬性與預設值：
        | property      | type                                | default | 說明                                     |
        | element       | string                              | ''      | 表單種類                                 |
        | attributes    | array<string, mixed>                | []      | HTML 元素 attribute                      |

    Example: NodeDefinition 擴充屬性
      Then 類別於 region 標記內定義以下擴充屬性：
        | property      | type                                                              | default | 說明                        |
        | name          | string                                                            | ''      | 欄位 key，對應 NodeDTO.args |
        | label         | string                                                            | ''      | 顯示標籤                    |
        | type          | string                                                            | 'text'  | text/number/select/textarea/template_editor/switch/date/json |
        | required      | bool                                                              | false   | 是否必填                    |
        | default_value | mixed                                                             | ''      | 預設值                      |
        | placeholder   | string                                                            | ''      | placeholder 文字            |
        | description   | string                                                            | ''      | 欄位說明（tooltip）         |
        | options       | array<int, array{value: string, label: string}>                   | []      | select 類型選項列表         |
        | validation    | array<int, array{rule: string, value: mixed, message: string}>    | []      | 額外驗證規則列表            |
        | sort          | int                                                               | 0       | 欄位排序                    |
        | depends_on    | array<int, array{field: string, operator: string, value: mixed}>  | []      | 條件顯示規則列表            |

    Example: 支援的欄位 type 列舉
      Then FormFieldDTO::$type 的合法值為：
        | text |
        | number |
        | select |
        | textarea |
        | template_editor |
        | switch |
        | date |
        | json |

    Example: 透過 DTO factory 建立實例
      Given 輸入陣列 ['name' => 'email', 'label' => 'Email', 'type' => 'text', 'required' => true]
      When 呼叫 FormFieldDTO::create($array)
      Then 回傳 FormFieldDTO 實例
      And $dto->name === 'email'
      And $dto->label === 'Email'
      And $dto->type === 'text'
      And $dto->required === true
      And 其他屬性維持 class default

    Example: 序列化為陣列
      Given 已建立的 FormFieldDTO 實例
      When 呼叫 $dto->to_array()
      Then 回傳包含所有 public 屬性的 array（由 DTO 基底提供）

    Example: depends_on 條件顯示結構
      Given depends_on 為 [['field' => 'type', 'operator' => 'eq', 'value' => 'select']]
      Then 表示此欄位僅當 type 欄位等於 "select" 時顯示
      And operator 可對應到 Shared\Enums\EOperater 定義的運算子字串
