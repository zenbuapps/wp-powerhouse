<?php

declare ( strict_types = 1 );

namespace J7\Powerhouse\Domains\Order\Shared\Enums;

/**
 * Status 訂閱的狀態
 *  */
enum Status : string {

	/** 等待付款中 */
	case PENDING = 'pending';
	/** 處理中 */
	case PROCESSING = 'processing';
	/** 已完成 */
	case COMPLETED = 'completed';
	/** 已取消 */
	case CANCELLED = 'cancelled';
	/** 已退費 */
	case REFUNDED = 'refunded';
	/** 失敗 */
	case FAILED = 'failed';
	/** 草稿 */
	case CHECKOUT_DRAFT = 'checkout-draft';

	/**
	 * 取得狀態的標籤
	 *
	 * @return string 狀態的標籤
	 */
	public function label(): string {
		/** @var array<string, string> $order_statuses key-name array */
		$order_statuses = \wc_get_order_statuses();
		return $order_statuses[ "wc-{$this->value}" ] ?? '未知狀態';
	}
}
