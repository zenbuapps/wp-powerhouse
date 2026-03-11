<?php

declare(strict_types=1);

namespace J7\Powerhouse\Domains\Woocommerce\Core;

/**
 * Class WC_Countries
 * TODO 之後擴充 city (行政區 & 郵遞區號)
 */
final class Countries {
	use \J7\WpUtils\Traits\SingletonTrait;

	/** @var array<string,string> 台灣縣市 */
	public static $states = [
		'基隆市' => '基隆市',
		'臺北市' => '臺北市',
		'新北市' => '新北市',
		'桃園市' => '桃園市',
		'新竹市' => '新竹市',
		'新竹縣' => '新竹縣',
		'苗栗縣' => '苗栗縣',
		'臺中市' => '臺中市',
		'彰化縣' => '彰化縣',
		'南投縣' => '南投縣',
		'雲林縣' => '雲林縣',
		'嘉義市' => '嘉義市',
		'嘉義縣' => '嘉義縣',
		'臺南市' => '臺南市',
		'高雄市' => '高雄市',
		'屏東縣' => '屏東縣',
		'臺東縣' => '臺東縣',
		'花蓮縣' => '花蓮縣',
		'宜蘭縣' => '宜蘭縣',
		'澎湖縣' => '澎湖縣',
		'金門縣' => '金門縣',
		'連江縣' => '連江縣',
	];

	/**
	 * Constructor
	 */
	public function __construct() {
		\add_filter(
			'woocommerce_states',
			[ __CLASS__, 'extend_tw_states' ]
			);
	}

	/**
	 * 擴充台灣縣市
	 *
	 * @param array<string, array<string,string>|string> $states 縣市陣列
	 * @return array<string, array<string,string>|string> 擴充後的縣市陣列
	 */
	public static function extend_tw_states( array $states ): array {
		$states['TW'] = self::$states;
		return $states;
	}
}
