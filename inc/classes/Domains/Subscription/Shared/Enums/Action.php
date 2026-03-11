<?php

declare (strict_types = 1);

namespace J7\Powerhouse\Domains\Subscription\Shared\Enums;

/**
 * Action 信件觸發的時間點
 *  */
enum Action: string {

	// 訂閱創建後
	case DATE_CREATED = 'date_created';

	/*
	* 訂閱首次付款成功後，
	* 如果要設定例如 TRIAL_END 或 NEXT_PAYMENT 前 N 的時間，可以設定在這個時間點
	* 個時間點都被寫入 db
	* 如果要監聽 訂閱時間點的變化，可以用
	* woocommerce_subscription_date_updated hook
	*/
	case INITIAL_PAYMENT_COMPLETE = 'initial_payment_complete';

	// 訂閱從成功到失敗
	case SUBSCRIPTION_FAILED = 'subscription_failed';

	// 訂閱從失敗到成功
	case SUBSCRIPTION_SUCCESS = 'subscription_success';

	// 訂閱付款重試
	case PAYMENT_RETRY = 'payment_retry';

	// 試用結束
	case TRIAL_END = 'trial_end';

	// 監聽訂閱時間點的變化
	case WATCH_TRIAL_END = 'watch_trial_end';

	// 下次付款
	case NEXT_PAYMENT = 'next_payment';

	// 監聽訂閱時間點的變化
	case WATCH_NEXT_PAYMENT = 'watch_next_payment';

	// 新的續訂訂單創建
	case RENEWAL_ORDER_CREATED = 'renewal_order_created';

	// 訂閱結束
	case END = 'end';

	// 監聽訂閱時間點的變化
	case WATCH_END = 'watch_end';

	// 訂閱結束，如果訂閱有 "cancelled" 或 "pending-cancel" 狀態，會觸發這個
	case END_OF_PREPAID_TERM = 'end_of_prepaid_term';

	/**
	 * 取得 訂閱觸發的時間點的 action hook
	 *
	 * @return non-empty-string
	 */
	public function get_action_hook(): string {
		return 'powerhouse_subscription_at_' . $this->value;
	}
}
