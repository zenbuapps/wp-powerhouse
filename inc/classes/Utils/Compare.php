<?php

declare(strict_types=1);

namespace J7\Powerhouse\Utils;

use J7\Powerhouse\Utils\DateTimeHandler;

/** Compare Date Time */
class Compare {

	/** @var \DateTime  開始時間 start time*/
	public \DateTime $after;

	/** @var \DateTime  前 {N} {時間區間} 的開始時間 compared start time*/
	public \DateTime $after_compared;

	/** @var \DateTime  結束時間 end time*/
	public \DateTime $before;

	/** @var \DateTime  前 {N} {時間區間} 的結束時間 compared end time*/
	public \DateTime $before_compared;


	/** @var string 比較時間區間 type: 'day' | 'week' | 'month' | 'year' */
	public string $compare_type;

	/** @var int 比較時間區間 value */
	public int $compare_value;

	/**
	 * 建構子
	 *
	 * @param array{after: string, before: string, compare_type: string, compare_value: int} $args 參數
	 */
	public function __construct( array $args ) {
		[
			'after' => $after,
			'before' => $before,
			'compare_type' => $compare_type,
			'compare_value' => $compare_value,
		] = $args;

		$compare_type  = (string) $compare_type;
		$compare_value = (int) $compare_value;

		$this->compare_type  = $compare_type;
		$this->compare_value = $compare_value;

		// 取得 before 和 after 還有計算 要比較的時間區間 \DateTime
		$this->after          = DateTimeHandler::parse_date_time( $after );
		$this->after_compared = DateTimeHandler::get_compared_date_time($this->after, $compare_type, $compare_value );

		$this->before          = DateTimeHandler::parse_date_time( $before );
		$this->before_compared = DateTimeHandler::get_compared_date_time($this->before, $compare_type, $compare_value );
	}
}
