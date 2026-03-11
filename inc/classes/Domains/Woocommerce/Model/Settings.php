<?php

declare(strict_types=1);

namespace J7\Powerhouse\Domains\Woocommerce\Model;

use J7\WpUtils\Classes\DTO;


/**
 * Woocommerce 設定
 * 從 db 的 wp_options 取得
 */
class Settings extends DTO {
	use \J7\WpUtils\Traits\SingletonTrait;

	const PREFIX = 'woocommerce_';

	/** @var string 版本 */
	public string $schema_version;

	/** @var string 商店地址 */
	public string $store_address;

	/** @var string 商店地址第二行 */
	public string $store_address_2;

	/** @var string 商店城市 */
	public string $store_city;

	/** @var string 預設國家 TW:台北市 */
	public string $default_country;

	/** @var string 商店郵遞區號 */
	public string $store_postcode;

	/**
	 * @var string 允許的國家設定
	 * "" = 運送至所有您有銷售商品的國家
	 * "all" = 運送至所有國家
	 * "specific" = 運送至特定國家
	 * "disabled" = 停用運送 & 運費計算
	 */
	public string $allowed_countries;

	/** @var array<string> 排除的國家列表 */
	public array $all_except_countries;

	/** @var array<string> 特定允許的國家列表 */
	public array $specific_allowed_countries;

	/** @var string 運送到國家設定 */
	public string $ship_to_countries;

	/** @var array<string> 特定運送到國家列表 */
	public array $specific_ship_to_countries;

	/** @var string 預設客戶地址 */
	public string $default_customer_address;

	/** @var bool 是否計算稅金 "yes" | "no" */
	public bool $calc_taxes;

	/** @var bool 是否啟用優惠券 "yes" | "no" */
	public bool $enable_coupons;

	/** @var bool 是否依序計算折扣 "yes" | "no" */
	public bool $calc_discounts_sequentially;

	/** @var string 貨幣 TWD */
	public string $currency;

	/** @var string 貨幣位置 "left" | "right" | "left_space" | "right_space" */
	public string $currency_pos;

	/** @var string 價格千分位符號 , */
	public string $price_thousand_sep;

	/** @var string 價格小數點符號 . */
	public string $price_decimal_sep;

	/** @var string 價格小數位數 0 */
	public string $price_num_decimals;

	/** @var string 商店頁面 ID */
	public string $shop_page_id;

	/** @var bool 加入購物車後是否重定向 "yes" | "no" */
	public bool $cart_redirect_after_add;

	/** @var bool 是否啟用 AJAX 加入購物車 "yes" | "no" */
	public bool $enable_ajax_add_to_cart;

	/** @var string 預設圖片 ID */
	public string $placeholder_image;

	/**
	 * @var string 重量單位 kg
	 * "kg" = 公斤
	 * "g" = 克
	 * "lbs" = 磅
	 * "oz" = 盎司
	 */
	public string $weight_unit;

	/**
	 * @var string 尺寸單位
	 * "m" = 分
	 * "cm" = 公分
	 * "mm" = 公厘
	 * "in" = 英吋
	 * "yd" = 碼
	 */
	public string $dimension_unit;

	/** @var bool 啟用商品評論 "yes" | "no" */
	public bool $enable_reviews;

	/** @var bool 在顧客的評價中顯示 "已驗證" 標籤 "yes" | "no" */
	public bool $review_rating_verification_label;

	/** @var bool 只有 "通過驗證的會員" 才能參與評論 "yes" | "no" */
	public bool $review_rating_verification_required;

	/** @var bool 在評論裡啟用星星評分 "yes" | "no" */
	public bool $enable_review_rating;

	/** @var bool 星星評分是否必填 "yes" | "no" */
	public bool $review_rating_required;

	/** @var bool 是否管理庫存 "yes" | "no" */
	public bool $manage_stock;

	/**
	 * @var string 保留庫存分鐘數 15
	 *  看要幫「未付款訂單」保留多少分鐘的庫存。當時間到了之後，待處理的訂單將被取消。要停用這個設定，請留空白。
	 */
	public string $hold_stock_minutes;

	/** @var bool 是否通知低庫存 "yes" | "no" */
	public bool $notify_low_stock;

	/** @var bool 是否通知無庫存 "yes" | "no" */
	public bool $notify_no_stock;

	/** @var string 通知收件人 j7.dev.gg@gmail.com */
	public string $stock_email_recipient;

	/** @var string 低庫存臨界值 2 */
	public string $notify_low_stock_amount;

	/** @var string 無庫存臨界值 0 */
	public string $notify_no_stock_amount;

	/** @var bool 無庫存可見度 在目錄裡隱藏無庫存的品項 "yes" | "no" */
	public bool $hide_out_of_stock_items;

	/**
	 * @var string 庫存格式
	 * "" = 總是顯示剩餘的庫存量，例如: "12件庫存"
	 * "low_amount" = 當只有在低庫存時才顯示剩餘的庫存量，例如 "庫存只剩2件"
	 * "no_amount" = 永不顯示剩餘的庫存量
	 *  */
	public string $stock_format;

	/**
	 * @var string 檔案下載方式
	 * "force" = 強制下載
	 * "xsendfile" = X-Accel-Redirect/X-Sendfile 如果將 X-Accel-Redirect 下載方法與 NGINX 伺服器搭配使用，請確認你已依照《數位/可下載產品處理》指南的說明套用設定。
	 * "redirect" = 只重新導向 (不安全)
	 *  */
	public string $file_download_method;

	/** @var bool 允許使用重新導向模式 (不安全) 做為最後手段 如果選取「強制下載」或「X-Accel-Redirect/X-Sendfile」下載方式但無效，系統最終會採用「重新導向」。 如需詳細資訊，請參閱本指南。 "yes" | "no" */
	public bool $downloads_redirect_fallback_allowed;

	/** @var bool 下載前需登入 此項設定不適用於訪客購物 "yes" | "no" */
	public bool $downloads_require_login;

	/** @var bool  付款後賦予商品下載權限 啟用此選項會讓下載商品訂單在"處理中"就取得下載權限，不用等到訂單"完成"。 "yes" | "no" */
	public bool $downloads_grant_access_after_payment;

	/** @var bool 在瀏覽器開啟可供下載的檔案，而非儲存到裝置中 顧客仍然可以將檔案儲存到自己的裝置上，但檔案會根據預設開啟，而非直接下載 (不適用重新導向)。 "yes" | "no" */
	public bool $downloads_deliver_inline;

	/** @var bool 為檔案名稱附加唯一字串以確保安全性 如果下載目錄受到保護則不需要。請參閱本指南瞭解詳情。 已上傳的檔案不會受到影響。 "yes" | "no" */
	public bool $downloads_add_hash_to_filename;

	/** @var bool  即使僅擷取到檔案的一部分，也會計入下載次數 系統不會重複計算合理時段內 (預設為 30 分鐘) 的重複擷取。 這是在執行與範圍要求相關的下載限制時，較為合理的方式。 深入了解。 "yes" | "no" */
	public bool $downloads_count_partial;

	/** @var bool 使用產品屬性查詢表進行型錄篩選。 "yes" | "no" */
	public bool $attribute_lookup_enabled;

	/** @var bool 在產品變更時直接更新表格，而不是排程延遲更新 "yes" | "no" */
	public bool $attribute_lookup_direct_updates;

	/** @var bool 使用更多高效能查詢來更新查閱表格，但某些擴充功能可能不相容。 唯有商品資料儲存在文章表格內時，才可使用本設定 "yes" | "no" */
	public bool $attribute_lookup_optimized_updates;

	/** @var bool 按貨號配對的產品影像 當上傳的圖片檔案名稱與產品貨號相符時，即設為產品精選圖片 "yes" | "no" */
	public bool $product_match_featured_image_by_sku;


	/** @var bool 售價包含稅金 "yes" | "no" */
	public bool $prices_include_tax;

	/**
	 * @var string 稅金計算依據 shipping
	 * "shipping" = 顧客運送地址
	 * "billing" = 顧客帳單地址
	 * "base" = 商店基本地址
	 *  */
	public string $tax_based_on;

	/**
	 * @var string 運費稅金類別
	 * "" = 標準
	 * "inherit" = 依據購物車內容項目決定運費稅金類別
	 * "reduced-rate" = Reduced rate
	 * "zero-rate" = Zero rate
	 *  */
	public string $shipping_tax_class;

	/** @var bool 先加總之後再四捨五入 "yes" | "no" */
	public bool $tax_round_at_subtotal;

	/** @var string 稅金類別 */
	public string $tax_classes;

	/**
	 * @var string 在商店顯示售價
	 * "excl" = 未稅
	 * "incl" = 含稅
	 */
	public string $tax_display_shop;

	/**
	 * @var string 在購物車及結帳過程顯示價格
	 * "excl" = 未稅
	 * "incl" = 含稅
	 */
	public string $tax_display_cart;

	/** @var string 售價顯示後綴 */
	public string $price_display_suffix;

	/**
	 * @var string 顯示稅金總計
	 * "single" = 合計為一個
	 * "itemized" = 每項商品
	 */
	public string $tax_total_display;

	/** @var bool 在購物車頁面啟用運費計算器 "yes" | "no" */
	public bool $enable_shipping_calc;

	/** @var bool 在地址尚未輸入之前隱藏運送費用 "yes" | "no" */
	public bool $shipping_cost_requires_address = false;

	/**
	 * @var string 運送到目的地設定
	 * "shipping" = 預設的客戶運送地址
	 * "billing" = 預設的客戶帳單地址
	 * "billing_only" = 強制運送至客戶帳單地址
	 */
	public string $ship_to_destination;

	/** @var bool 運費除錯模式 略過運送率的快取 "yes" | "no" */
	public bool $shipping_debug_mode;

	/** @var bool 是否啟用訪客結帳 購買訂閱依然需要帳號  "yes" | "no" */
	public bool $enable_guest_checkout;

	/** @var bool 啟用在結帳期間登入的功能 "yes" | "no" */
	public bool $enable_checkout_login_reminder;

	/** @var bool 結帳期間顧客可在下單前建立帳號 "yes" | "no" */
	public bool $enable_signup_and_login_from_checkout;

	/** @var bool 是否啟用我的帳戶註冊 "yes" | "no" */
	public bool $enable_myaccount_registration;

	/** @var bool  使用電子郵件地址作為登入帳號 (建議) 若電子郵件未確認，顧客將需要在建立帳號時設定使用者名稱。 "yes" | "no" */
	public bool $registration_generate_username;

	/** @var bool 傳送密碼設定連結 (建議) 新顧客會收到密碼設定電子郵件。 "yes" | "no" */
	public bool $registration_generate_password;

	/** @var bool 依照要求將個人資料從訂單移除 處理帳號清除要求時，是否會保留或是移除訂單中的個人資料？ "yes" | "no" */
	public bool $erasure_request_removes_order_data;

	/** @var bool 移除訂閱中的個人資料 處理 帳號清除要求 時，是否會保留或移除訂閱中的個人資料？ "yes" | "no" */
	public bool $erasure_request_removes_subscription_data;

	/** @var bool 依照要求移除下載項目的存取權 處理帳號清除要求時，是否會撤銷可下載檔案的存取權限並清除下載記錄？ "yes" | "no" */
	public bool $erasure_request_removes_download_data;

	/** @var bool 允許從訂單大量移除個人資料 在訂單畫面新增大量移除個人資料的選項。 請注意，個人資料一經移除將無法復原。  "yes" | "no" */
	public bool $allow_bulk_remove_personal_data;

	/** @var string 註冊隱私權政策文字 */
	public string $registration_privacy_policy_text;

	/** @var string 結帳隱私權政策文字 */
	public string $checkout_privacy_policy_text;

	/** @var array{number: string, unit: "days" | "weeks" | "months" | "years"} 保留閒置的帳號 */
	public array $delete_inactive_accounts;

	/** @var array{number: string, unit: "days" | "weeks" | "months" | "years"} 保留待確認的訂單 */
	public array $trash_pending_orders;

	/** @var array{number: string, unit: "days" | "weeks" | "months" | "years"} 保留失敗的訂單 */
	public array $trash_failed_orders;

	/** @var array{number: string, unit: "days" | "weeks" | "months" | "years"} 保留已取消的訂單 */
	public array $trash_cancelled_orders;

	/** @var array{number: string, unit: "days" | "weeks" | "months" | "years"} 保留已完成的訂單 */
	public array $anonymize_completed_orders;

	/** @var array{number: string, unit: "days" | "weeks" | "months" | "years"} 保留已結束的訂閱 */
	public array $anonymize_ended_subscriptions;

	/** @var string 電子郵件寄件者名稱 */
	public string $email_from_name;

	/** @var string 電子郵件寄件者地址 */
	public string $email_from_address;

	/** @var string 電子郵件頁首圖片 */
	public string $email_header_image;

	/** @var string 電子郵件頁尾文字 */
	public string $email_footer_text;

	/** @var string 電子郵件基本顏色 #7f54b3 */
	public string $email_base_color;

	/** @var string 電子郵件背景顏色 #f7f7f7 */
	public string $email_background_color;

	/** @var string 電子郵件內容背景顏色 #ffffff */
	public string $email_body_background_color;

	/** @var string 電子郵件文字顏色 #3c3c3c */
	public string $email_text_color;

	/** @var string 電子郵件頁尾文字顏色 #3c3c3c */
	public string $email_footer_text_color;

	/** @var bool 接收電子郵件通知，取得其他指南完成基本商店設定，並獲得實用的深入分析 "yes" | "no" */
	public bool $merchant_email_notifications;

	/** @var string 購物車頁面 ID */
	public string $cart_page_id;

	/** @var string 結帳頁面 ID */
	public string $checkout_page_id;

	/** @var string 我的帳戶頁面 ID */
	public string $myaccount_page_id;

	/** @var string 條款頁面 ID */
	public string $terms_page_id;

	/** @var bool 是否強制 SSL 結帳 "yes" | "no" */
	public bool $force_ssl_checkout;

	/** @var bool 是否取消強制 SSL 結帳 */
	public bool $unforce_ssl_checkout;

	/** @var string 結帳->付款 order-pay */
	public string $checkout_pay_endpoint;

	/** @var string 已收到訂單 order-received */
	public string $checkout_order_received_endpoint;

	/** @var string 新增付款方式 add-payment-method */
	public string $myaccount_add_payment_method_endpoint;

	/** @var string 刪除付款方式 delete-payment-method */
	public string $myaccount_delete_payment_method_endpoint;

	/** @var string 設定預設付款方式 set-default-payment-method */
	public string $myaccount_set_default_payment_method_endpoint;

	/** @var string 訂單 orders */
	public string $myaccount_orders_endpoint;

	/** @var string 查看訂單 view-order */
	public string $myaccount_view_order_endpoint;

	/** @var string 訂閱 subscriptions */
	public string $myaccount_subscriptions_endpoint;

	/** @var string 檢視訂閱 view-subscription */
	public string $myaccount_view_subscription_endpoint;

	/** @var string 訂閱付款方式 subscription-payment-method */
	public string $myaccount_subscription_payment_method_endpoint;

	/** @var string 下載 downloads */
	public string $myaccount_downloads_endpoint;

	/** @var string 編輯帳號 edit-account */
	public string $myaccount_edit_account_endpoint;

	/** @var string 地址 edit-address */
	public string $myaccount_edit_address_endpoint;

	/** @var string 付款方式 payment-methods */
	public string $myaccount_payment_methods_endpoint;

	/** @var string 忘記密碼 lost-password */
	public string $myaccount_lost_password_endpoint;

	/** @var string 登出 customer-logout */
	public string $logout_endpoint;

	/** @var bool 是否啟用 API (這好像是舊版本 REST API) "yes" | "no" */
	public bool $api_enabled;

	/** @var bool 是否允許追蹤 "yes" | "no" */
	public bool $allow_tracking;

	/** @var bool 是否顯示市集建議 "yes" | "no" */
	public bool $show_marketplace_suggestions;

	/** @var bool ⭐ 是否啟用高效能訂單儲存 "yes" HPOS | "no" 舊版 */
	public bool $custom_orders_table_enabled;

	/** @var bool ⭐ 啟用相容性模式 (將訂單同步到文章表格)。 "yes" | "no" */
	public bool $custom_orders_table_data_sync_enabled;

	/** @var bool ⭐ HPOS table 是否已經創建 */
	public bool $custom_orders_table_created;

	/** @var bool ⭐ HPOS 全文搜尋索引 建立並使用全文搜尋索引來查詢訂單。 此功能僅供高效能訂單儲存空間使用。 "yes" | "no" */
	public bool $hpos_fts_index_enabled;

	/** @var bool 是否啟用分析 "yes" | "no" */
	public bool $analytics_enabled;

	/** @var bool 是否啟用訂單歸屬 即可追蹤和宣告對網站訂單有所貢獻的通路與活動 "yes" | "no" */
	public bool $feature_order_attribution_enabled;

	/** @var bool 網站可見度徽章 在 WordPress 管理員列中啟用網站可見度徽章 "yes" | "no" */
	public bool $feature_site_visibility_badge_enabled;

	/** @var bool 是否啟用產品區塊編輯器功能 "yes" | "no" */
	public bool $feature_product_block_editor_enabled;

	/** @var bool 是否啟用 Woocommerce 新手導覽 "yes" | "no" */
	public bool $navigation_enabled;

	/** @var string 單一圖片寬度 600 */
	public string $single_image_width;

	/** @var string 縮圖寬度 300 */
	public string $thumbnail_image_width;

	/** @var bool 是否在結帳時高亮必填欄位 "yes" | "no" */
	public bool $checkout_highlight_required_fields;

	/** @var bool 是否為 DEMO 商店 "yes" | "no" */
	public bool $demo_store;

	/** @var array{product_base: string, category_base: string, tag_base: string, attribute_base: string, use_verbose_page_rules: bool} 永久連結設定 */
	public array $permalinks;

	/** @var bool 是否刷新重寫規則佇列 "yes" | "no" */
	public bool $queue_flush_rewrite_rules;

	/** @var string 退款退貨頁面 ID */
	public string $refund_returns_page_id;

	/** @var array{enabled: string, title: string, description: string, email: string, advanced: string, testmode: string, debug: string, ipn_notification: string, receiver_email: string, identity_token: string, invoice_prefix: string, send_shipping: string, address_override: string, paymentaction: string, image_url: string, api_details: string, api_username: string, api_password: string, api_signature: string, sandbox_api_username: string, sandbox_api_password: string, sandbox_api_signature: string, _should_load: string} PayPal 設定 */
	public array $paypal_settings;

	/** @var string WooCommerce 版本 */
	public string $version;

	/** @var string WooCommerce 資料庫版本 */
	public string $db_version;

	/** @var string WooCommerce 管理員安裝時間戳記 */
	public string $admin_install_timestamp;

	/** @var string WooCommerce 收件匣變體分配 6 */
	public string $inbox_variant_assignment;

	/** @var array<string> WooCommerce 管理員通知 */
	public array $admin_notices;

	/** @var array{database_prefix: string} MaxMind 地理位置設定 */
	public array $maxmind_geolocation_settings;

	/** @var array<string> 任務清單已完成的任務 */
	public array $task_list_tracked_completed_tasks;

	/** @var array{skipped: bool} 入門設定檔 */
	public array $onboarding_profile;

	/** @var bool 任務清單提示是否已顯示 */
	public bool $task_list_prompt_shown;

	/** @var array{suggestions: array<int, array<string, mixed>>, updated: int} 市集建議 */
	public array $marketplace_suggestions;

	/** @var array<string> 布林值屬性 */
	private array $bool_keys = [
		'calc_taxes',
		'enable_coupons',
		'calc_discounts_sequentially',
		'cart_redirect_after_add',
		'enable_ajax_add_to_cart',
		'enable_reviews',
		'review_rating_verification_label',
		'review_rating_verification_required',
		'enable_review_rating',
		'review_rating_required',
		'manage_stock',
		'notify_low_stock',
		'notify_no_stock',
		'hide_out_of_stock_items',
		'downloads_redirect_fallback_allowed',
		'downloads_require_login',
		'downloads_grant_access_after_payment',
		'downloads_deliver_inline',
		'downloads_add_hash_to_filename',
		'downloads_count_partial',
		'attribute_lookup_enabled',
		'attribute_lookup_direct_updates',
		'attribute_lookup_optimized_updates',
		'product_match_featured_image_by_sku',
		'prices_include_tax',
		'tax_round_at_subtotal',
		'enable_shipping_calc',
		'shipping_cost_requires_address',
		'shipping_debug_mode',
		'enable_guest_checkout',
		'enable_checkout_login_reminder',
		'enable_signup_and_login_from_checkout',
		'enable_myaccount_registration',
		'registration_generate_username',
		'registration_generate_password',
		'erasure_request_removes_order_data',
		'erasure_request_removes_subscription_data',
		'erasure_request_removes_download_data',
		'allow_bulk_remove_personal_data',
		'merchant_email_notifications',
		'force_ssl_checkout',
		'unforce_ssl_checkout',
		'api_enabled',
		'allow_tracking',
		'show_marketplace_suggestions',
		'custom_orders_table_enabled',
		'custom_orders_table_data_sync_enabled',
		'custom_orders_table_created',
		'hpos_fts_index_enabled',
		'analytics_enabled',
		'feature_order_attribution_enabled',
		'feature_site_visibility_badge_enabled',
		'feature_product_block_editor_enabled',
		'navigation_enabled',
		'checkout_highlight_required_fields',
		'demo_store',
		'queue_flush_rewrite_rules',
		'task_list_prompt_shown',
	];

	/** Constructor */
	public function __construct() {
		// 用 反射 取得所有 public 的屬性名稱
		$reflection = new \ReflectionClass( $this );
		$properties = $reflection->getProperties( \ReflectionProperty::IS_PUBLIC );

		// 建立屬性型別映射，用於檢查屬性是否為陣列型別
		$array_properties = [];
		foreach ($properties as $property) {
			$property_type = $property->getType();
			if ($property_type instanceof \ReflectionNamedType && $property_type->getName() === 'array') {
				$array_properties[ $property->getName() ] = true;
			}
		}

		foreach ( $properties as $property ) {
			$property_name = $property->getName();
			$value         = \get_option( self::PREFIX . $property_name, '' );

			if ( in_array( $property_name, $this->bool_keys, true ) ) {
				$this->{$property_name} = \wc_string_to_bool( (string) $value );
				continue;
			}

			if ( isset( $array_properties[ $property_name ] ) ) {
				// 確保 array 屬性的值是 array
				$this->{$property_name} = \is_array( $value ) ? $value : [];
			} else {
				$this->{$property_name} = (string) $value;
			}
		}
	}
}
