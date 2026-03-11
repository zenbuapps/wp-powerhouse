<?php

declare (strict_types = 1);

namespace J7\Powerhouse\Domains\Subscription\Shared\Enums;

/**
 * Status 訂閱的狀態
 *  */
enum Status: string {

	// 已啟用
	case ACTIVE = 'active';

	// 保留，嘗試付款中
	case ON_HOLD = 'on-hold';

	// 待取消
	case PENDING_CANCEL = 'pending-cancel';

	// 已取消
	case CANCELLED = 'cancelled';

	// 已過期
	case EXPIRED = 'expired';

	/** @return bool 判斷狀態是否為訂閱失敗的狀態 變成 [已取消][已過期]就算失敗 [保留][待取消]不算失敗，[保留]代表正在嘗試付款中 */
	public function is_failed(): bool {
		return in_array(
			$this,
			[
				self::CANCELLED,
				self::EXPIRED,
			],
			true
			);
	}
}
