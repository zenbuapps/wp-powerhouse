<?php

declare(strict_types=1);

namespace J7\Powerhouse\Domains\User\Model;

use J7\WpUtils\Classes\DTO;
use Automattic\WooCommerce\Admin\API\Reports\Customers\Query as CustomersQuery;
use J7\Powerhouse\Domains\User\Utils\CRUD;
use J7\Powerhouse\Domains\Order\Utils\Info;

/** Class User */
final class User extends DTO {
	/** @var string 用戶 ID */
	public string $id = '';

	/** @var string 用戶名字 */
	public string $first_name = '';

	/** @var string 用戶姓氏 */
	public string $last_name = '';

	/** @var string 用戶登入名稱 */
	public string $user_login = '';

	/** @var string 用戶電子郵件 */
	public string $user_email = '';

	/** @var string 用戶顯示名稱 */
	public string $display_name = '訪客';

	/** @var string 用戶註冊時間 (MySQL datetime 格式) */
	public string $user_registered = '';

	/** @var string 用戶註冊時間 (人類可讀格式) */
	public string $user_registered_human = '';

	/** @var string 用戶頭像 URL */
	public string $user_avatar_url = 'https://www.google.com/url?sa=i&url=https%3A%2F%2Fwww.vecteezy.com%2Fvector-art%2F26631837-incognito-icon-vector-symbol-design-illustration&psig=AOvVaw0KlMIe2lsttP8SC-47PjOn&ust=1741950083858000&source=images&cd=vfe&opi=89978449&ved=0CBQQjRxqFwoTCPDiwPbzhowDFQAAAAAdAAAAABAb';

	/** @var string 用戶描述/簡介 */
	public string $description = '';

	/** @var string 用戶角色 */
	public string $role = 'subscriber';

	/** @var string 用戶聯絡電話 */
	public string $billing_phone = '';

	/** @var string 用戶生日 格式: YYYY-MM-DD */
	public string $user_birthday = '';

	/** @var string 用戶編輯頁面 URL */
	public string $edit_url = '';

	/** @var ?string 用戶上次登入帳號時間 */
	public ?string $date_last_active = null;

	/** @var ?string 用戶上次下單時間時間 */
	public ?string $date_last_order = null;

	/** @var ?string 用戶總訂單數 */
	public ?string $orders_count = null;

	/** @var ?string 用戶總消費金額 */
	public ?string $total_spend = null;

	/** @var ?string 用戶平均訂單金額 */
	public ?string $avg_order_value = null;

	/**
	 * @var array{
	 *   product_id: int,
	 *   product_name: string,
	 *   quantity: int,
	 *   price: string|float,
	 *   variation_id: int,
	 *   variation: array<string, string>,
	 *   line_total: float
	 * }[] 用戶購物車資料  Edit 時才暴露 */
	public array $cart = [];

	/**
	 * @var array{
	 *   order_id: int,
	 *   order_date: string,
	 *   order_date_human: string|null,
	 *   order_total: string|float,
	 *   order_status: string,
	 * }[] 用戶最近訂單資料 Edit 時才暴露
	 * */
	public array $recent_orders = [];

	/**
	 * @var array{
	 * first_name: string,
	 * last_name: string,
	 * email: string,
	 * phone: string,
	 * company: string,
	 * postcode: string,
	 * country: string,
	 * state: string,
	 * city: string,
	 * address_1: string,
	 * address_2: string,
	 * } 用戶 billing 資料 Edit 時才暴露
	 * */
	public array $billing = []; // @phpstan-ignore-line

	/**
	 * @var array{
	 * first_name: string,
	 * last_name: string,
	 * email: string,
	 * phone: string,
	 * postcode: string,
	 * country: string,
	 * state: string,
	 * city: string,
	 * address_1: string,
	 * address_2: string,
	 * } 用戶 shipping 資料 Edit 時才暴露
	 * */
	public array $shipping = []; // @phpstan-ignore-line

	/** @var array<string, mixed> 聯絡註記 Edit 時才暴露 */
	public array $contact_remarks = [];

	/** @var array<string, mixed> 其他 meta 資料 Edit 時才暴露 */
	public array $other_meta_data = [];



	/**
	 * Format user details
	 *
	 * @param int $user_id  User ID.
	 * @return self
	 */
	public static function instance( int $user_id ) {
		$user = \get_user_by( 'id', $user_id );
		if ( !$user ) {
			return new self( [] );
		}

		$user_registered      = (string) $user->get( 'user_registered' );
		$user_registered_time = \strtotime($user_registered);
		$user_avatar_url      = \get_user_meta($user_id, 'user_avatar_url', true);
		$user_avatar_url      = $user_avatar_url ? $user_avatar_url : \get_avatar_url( $user_id );

		// 取得 customer 資料
		$customers_query = new CustomersQuery(
		[
			'customers'    => [ $user_id ],
			// If unset, these params have default values that affect the results.
			'order_after'  => null,
			'order_before' => null,
		]
		);

		$customer_data = $customers_query->get_data();
		/** @var object{data: array<int, array<string, mixed>>} $customer_data */
		$customer_history = $customer_data->data[0] ?? null;

		$user_record = [
			'id'                    => (string) $user_id,
			'first_name'            => $user->first_name,
			'last_name'             => $user->last_name,
			'user_login'            => $user->user_login,
			'user_email'            => $user->user_email,
			'display_name'          => $user->display_name,
			'user_registered'       => $user_registered ? \get_date_from_gmt( $user_registered ) : '',
			'user_registered_human' => $user_registered_time ? \human_time_diff( $user_registered_time ) : null,
			'user_avatar_url'       => $user_avatar_url,
			'description'           => $user->description,
			'role'                  => (string) reset( $user->roles ) ?: 'subscriber',
			'billing_phone'         => \get_user_meta($user_id, 'billing_phone', true),
			'user_birthday'         => \get_user_meta($user_id, 'user_birthday', true),
			'edit_url'              => \get_edit_user_link( $user_id ),
			'date_last_active'      => $customer_history['date_last_active'] ?? null,
			'date_last_order'       => $customer_history['date_last_order'] ?? null,
			'orders_count'          => $customer_history['orders_count'] ?? null,
			'total_spend'           => $customer_history['total_spend'] ?? null,
			'avg_order_value'       => $customer_history['avg_order_value'] ?? null,
		];

		return new self($user_record);
	}


	/**
	 * 取得公開的屬性 array
	 *
	 * @param string        $context 'list' | 'edit'
	 *         - list: 列表用，較少資料
	 *         - edit: 編輯用，較多資料
	 * @param array<string> $meta_keys 要暴露的前端 meta key
	 * @return array<string,mixed>
	 */
	public function to_array( $context = 'list', array $meta_keys = [] ): array {
		$reflection = new \ReflectionClass($this);
		$props      = $reflection->getProperties(\ReflectionProperty::IS_PUBLIC);
		$user_id    = (int) $this->id;

		$user_record = [];
		foreach ($props as $prop) {
			$user_record[ $prop->getName() ] = $prop->getValue($this);
		}

		$meta_keys_array = [];
		if ($meta_keys) {
			$user = \get_user_by( 'id', $user_id );
			if ($user) {
				$meta_keys_array = CRUD::get_meta_keys_array( $user, $meta_keys );
			}
		}

		if ( 'edit' === $context ) {
			// 取得 用戶 billing, shipping 資料
			$billing_shipping_data = Info::to_user_array( $user_id );

			// 持久購物車資料
			$user_record['cart']            = CRUD::get_user_cart_items( $user_id );
			$user_record['recent_orders']   = CRUD::get_user_orders( $user_id, [], ARRAY_A );
			$user_record['other_meta_data'] = self::get_rest_meta_data();
			$user_record['contact_remarks'] = CRUD::get_contact_remarks($user_id);

			return array_merge( $user_record, $billing_shipping_data, $meta_keys_array );
		}

		if ( 'list' === $context ) {
			return array_merge( $user_record, $meta_keys_array );
		}

		return array_merge( $user_record, $meta_keys_array );
	}

	/**
	 * 取得屬性以外剩餘的 meta_data 資料
	 *
	 * @return array<array{umeta_id:string, meta_key:string, meta_value:string}>
	 */
	public function get_rest_meta_data(): array {
		$exclude_fields = [
			...Info::get_billing_fields(),
			...Info::get_shipping_fields(),
			'first_name',
			'last_name',
			'nickname',
			'user_email',
			'display_name',
			'description',
			'user_avatar_url',
			'wp_capabilities',
			'wp_user_level',
			'_woocommerce_persistent_cart_1', // 持久購物車
			'session_tokens',
			// TODO 雖然沒有下面欄位，但之後做排序會新增，先寫起來
			'orders_count',
			'total_spend',
			'avg_order_value',
		];

		global $wpdb;
		/** @var array<array{umeta_id:string, meta_key:string, meta_value:string}> $user_meta_array */
		$user_meta_array = $wpdb->get_results( $wpdb->prepare( "SELECT umeta_id, meta_key, meta_value FROM {$wpdb->usermeta} WHERE user_id = %d", $this->id ), ARRAY_A );

		$meta_data = [];
		foreach ($user_meta_array as $user_meta_record) {
			if ( in_array( $user_meta_record['meta_key'], $exclude_fields ) ) {
				continue;
			}
			$meta_data[] = $user_meta_record;
		}

		return $meta_data;
	}
}
