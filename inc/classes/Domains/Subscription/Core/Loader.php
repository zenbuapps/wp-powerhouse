<?php

declare(strict_types=1);

namespace J7\Powerhouse\Domains\Subscription\Core;

/**
 * 載入器，如果沒有安裝 WooCommerce Subscriptions 則不載入
 *  */
final class Loader {
	use \J7\WpUtils\Traits\SingletonTrait;

	/** Constructor */
	public function __construct() {
		if (!class_exists('\WC_Subscriptions')) {
			return;
		}

		LifeCycle::instance();
		RetryPayment::instance();
	}
}
