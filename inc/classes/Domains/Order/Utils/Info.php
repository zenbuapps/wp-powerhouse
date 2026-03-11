<?php

declare(strict_types=1);

namespace J7\Powerhouse\Domains\Order\Utils;

/**
 * 訂單資訊
 * 因為 WooCommerce billing, shipping 資訊欄位很多
 * 透過這個類可以一次拿到所有相關的欄位以及組合資料結構
 */
abstract class Info {

	/** @var array<string>  類型*/
	private static $types = [ 'billing', 'shipping' ];

	/** @var array<string>  欄位名稱*/
	private static $fields = [
		'first_name',
		'last_name',
		'email',
		'phone',
		'company',
		'postcode',
		'country',
		'state',
		'city',
		'address_1',
		'address_2',
	];

	/**
	 * Get billing fields
	 *
	 * @param bool $prefix 是否添加前綴
	 * @return array<string>
	 */
	public static function get_billing_fields( bool $prefix = true ): array {
		if ( $prefix ) {
			return array_map( fn( $field ) => "billing_{$field}", self::$fields );
		}
		return self::$fields;
	}

	/**
	 * Get shipping fields
	 *
	 * @param bool $prefix 是否添加前綴
	 * @return array<string>
	 */
	public static function get_shipping_fields( bool $prefix = true ): array {
		$shipping_fields = array_filter( self::$fields, fn( $field ) => $field !== 'company' );
		if ( $prefix ) {
			return array_map( fn( $field ) => "shipping_{$field}", $shipping_fields );
		}
		return $shipping_fields;
	}

	/**
	 * 轉換為訂單 INFO 陣列
	 *
	 * @param int $order_id 訂單 ID
	 * @return array<string, array<string, mixed>>
	 */
	public static function to_order_array( int $order_id ): array {
		$order = \wc_get_order( $order_id );
		if ( ! $order ) {
			/** @var array<string, array<string, mixed>> */
			return [];
		}

		$order_array = [];
		foreach ( self::$types as $type ) {
			$order_array[ $type ] = [];
			$fields               = $type === 'shipping' ? self::get_shipping_fields( false ) : self::get_billing_fields( false );
			foreach ( $fields as $field ) {
				if ( method_exists( $order, "get_{$type}_{$field}" ) ) {
					$order_array[ $type ][ $field ] = $order->{"get_{$type}_{$field}"}();
				}
			}
		}

		return $order_array;
	}


	/**
	 * 轉換為用戶 INFO 陣列
	 *
	 * @param int $user_id 用戶 ID
	 * @return array<string, array<string, mixed>>
	 */
	public static function to_user_array( int $user_id ): array {
		$user_array = [];
		foreach ( self::$types as $type ) {
			$user_array[ $type ] = [];
			$fields              = $type === 'shipping' ? self::get_shipping_fields( false ) : self::get_billing_fields( false );
			foreach ( $fields as $field ) {
				$user_array[ $type ][ $field ] = \get_user_meta( $user_id, "{$type}_{$field}", true );
			}
		}

		return $user_array;
	}
}
