<?php

declare(strict_types=1);

namespace J7\Powerhouse\Shared\Enums;

enum EObjectType: string {
	case Array   = 'arr';
	case User    = 'user';
	case Product = 'product';
	case Order   = 'order';
	case Post    = 'post';
	case Object  = 'obj';

	/**
	 * 取得 EObjectTypes
	 *
	 * @param mixed $obj 物件
	 *
	 * @return self
	 * @throws \Exception 如果找不到對應的 Type
	 */
	public static function get_type( mixed $obj ): self {
		$type = \gettype($obj);
		if ('array' === $type) {
			return self::Array;
		}
		if ($obj instanceof \WP_User) {
			return self::User;
		}
		if ($obj instanceof \WC_Product) {
			return self::Product;
		}
		if ($obj instanceof \WP_Post) {
			return self::Post;
		}
		if ( $obj instanceof \WC_Order) {
			return self::Order;
		}
		if (\is_object( $obj)) {
			return self::Object;
		}
		throw new \Exception('Unsupported object type');
	}
}
