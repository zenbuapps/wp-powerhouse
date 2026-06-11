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

	/**
	 * 判斷是否為「轉為 active 時算作恢復」的來源狀態
	 * [待取消][已取消][已過期]算恢復來源（WCS 中 cancelled 的合法復活路徑為 cancelled → pending-cancel → active）
	 * [保留]是催繳補款的日常震盪，不算恢復，避免下游（重啟網站、恢復授權碼、自動化 workflow）重複觸發
	 *
	 * @return bool
	 */
	public function is_recoverable(): bool {
		return in_array(
			$this,
			[
				self::PENDING_CANCEL,
				self::CANCELLED,
				self::EXPIRED,
			],
			true
			);
	}
}
