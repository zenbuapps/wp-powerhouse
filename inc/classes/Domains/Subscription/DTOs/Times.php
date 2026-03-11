<?php

declare(strict_types=1);

namespace J7\Powerhouse\Domains\Subscription\DTOs;

use J7\WpUtils\Classes\DTO;
use J7\Powerhouse\Domains\Subscription\Shared\Enums\Action;

/**
 * Class Times
 * 訂閱的各時間點
 *  */
final class Times extends DTO {


	/** @var int $trial_end 試用期結束時間戳記 */
	public int $trial_end;

	/** @var int $next_payment 下次付款時間戳記 */
	public int $next_payment;

	/** @var int $last_order_date_created 最後訂單創建時間戳記 */
	public int $last_order_date_created;

	/** @var int $end 訂閱結束時間戳記 */
	public int $end;

	/** @var int $end_of_prepaid_term 預付期間結束時間戳記 */
	public int $end_of_prepaid_term;



	/**
	 * Instance
	 *
	 * @param \WC_Subscription $subscription 訂閱
	 * @return self
	 */
	public static function instance( \WC_Subscription $subscription ): self {

		$args = [
			'trial_end'               => $subscription->get_time( Action::TRIAL_END->value ),
			'next_payment'            => $subscription->get_time( Action::NEXT_PAYMENT->value ),
			'last_order_date_created' => $subscription->get_time('last_order_date_created' ),
			'end'                     => $subscription->get_time( Action::END->value ),
			'end_of_prepaid_term'     => $subscription->get_time( Action::END_OF_PREPAID_TERM->value ),
		];

		return new self( $args );
	}
}
