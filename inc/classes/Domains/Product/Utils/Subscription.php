<?php

declare(strict_types=1);

namespace J7\Powerhouse\Domains\Product\Utils;

use J7\Powerhouse\Domains\Product\Service\PeriodLabel;

/** Subscription  訂閱相關 Helper */
abstract class Subscription {

	/**
	 * 取得訂閱商品的 meta data 欄位
	 *
	 * @param bool $with_underline 是否包含底線
	 *
	 * @return array<string>
	 */
	public static function get_fields( bool $with_underline = true ): array {

		$fields = [
			'subscription_price',
			'subscription_period',
			'subscription_period_interval',
			'subscription_length',
			'subscription_sign_up_fee',
			'subscription_trial_length',
			'subscription_trial_period',
		];

		if ($with_underline) {
			return array_map(fn( $field ) => "_{$field}", $fields);
		}

		return $fields;
	}

	/**
	 * 取得訂閱商品的 meta data
	 *
	 * @param \WC_Product $product 商品
	 *
	 * @return array<string>
	 */
	public static function get_meta_data_label( \WC_Product $product ): array {
		if (!class_exists('\WC_Subscription')) {
			return [];
		}

		$product_meta_data = [];

		[
			'_subscription_period' => $subscription_period,
			'_subscription_length' => $subscription_length,
			'_subscription_sign_up_fee' => $subscription_sign_up_fee,
			'_subscription_trial_length' => $subscription_trial_length,
			'_subscription_trial_period' => $subscription_trial_period,
		] = self::get_subscription_meta_data( $product );

		$valid_periods = [ 'day', 'week', 'month', 'year' ];

		// 持續 4 個月文字
		if ($subscription_length && \in_array($subscription_period, $valid_periods, true)) {
			/** @var 'day'|'week'|'month'|'year' $subscription_period */
			$subscription_period_label = ( new PeriodLabel( $subscription_period ) )->period_label;
			$product_meta_data[]       = "扣款持續 {$subscription_length}{$subscription_period_label}";
		}

		if ($subscription_sign_up_fee) {
			$price               = \wc_price( (float) $subscription_sign_up_fee );
			$product_meta_data[] = "首次開通 {$price}";
		}

		if ($subscription_trial_length && \in_array($subscription_trial_period, $valid_periods, true)) {
			/** @var 'day'|'week'|'month'|'year' $subscription_trial_period */
			$subscription_trial_period_label = ( new PeriodLabel( $subscription_trial_period ) )->period_label;
			$product_meta_data[]             = "包含 {$subscription_trial_length}{$subscription_trial_period_label} 免費試用";
		}

		return $product_meta_data;
	}

	/**
	 * 取得訂閱商品價格
	 *
	 * @param \WC_Product $product 商品
	 *
	 * @return string
	 */
	public static function get_price_html( \WC_Product $product ): string {
		$price = $product->get_price_html();
		if (!class_exists('\WC_Subscription')) {
			return $price;
		}

		[
			'_subscription_period' => $subscription_period,
			'_subscription_period_interval' => $subscription_period_interval,
		] = self::get_subscription_meta_data( $product );

		$valid_periods = [ 'day', 'week', 'month', 'year' ];
		if (!\in_array($subscription_period, $valid_periods, true)) {
			return $price;
		}

		/** @var 'day'|'week'|'month'|'year' $subscription_period */
		// 組合成  /月 /2月 的文字
		$period_label = ( new PeriodLabel( $subscription_period, (int) $subscription_period_interval ) )->get_label('/');
		$period_label = sprintf( /*html*/'<span class="text-sm">%1$s</span>', $period_label);

		// 同 WC_Product_Simple::get_price_html()
		if ( '' === $product->get_price() ) {
			$price = (string) \apply_filters( 'woocommerce_empty_price_html', '', $product );
		} elseif ( $product->is_on_sale() ) {
			$price = self::wc_format_subscription_sale_price( (string) \wc_get_price_to_display( $product, [ 'price' => $product->get_regular_price() ] ), (string) \wc_get_price_to_display( $product ), $period_label ) . $product->get_price_suffix();
		} else {
			$price = \wc_price( \wc_get_price_to_display( $product ) ) . $product->get_price_suffix() . $period_label;
		}

		return $price;
	}

	/**
	 * 取得訂閱商品的 meta data
	 *
	 * @param \WC_Product $product 商品
	 *
	 * @return array{
	 *  _subscription_price: string,
	 *  _subscription_period: string,
	 *  _subscription_period_interval: string,
	 *  _subscription_length: string,
	 *  _subscription_sign_up_fee: string,
	 *  _subscription_trial_length: string,
	 *  _subscription_trial_period: string,
	 * }
	 */
	public static function get_subscription_meta_data( \WC_Product $product ): array {

		$fields = self::get_fields();

		if (!class_exists('\WC_Subscription')) {
			return array_fill_keys($fields, ''); // @phpstan-ignore-line
		}

		$values = [];
		foreach ($fields as $field) {
			$value            = $product->get_meta($field);
			$values[ $field ] = $value;
		}

		/**
		 * @var array{
		 *  _subscription_price: string,
		 *  _subscription_period: string,
		 *  _subscription_period_interval: string,
		 *  _subscription_length: string,
		 *  _subscription_sign_up_fee: string,
			*  _subscription_trial_length: string,
			*  _subscription_trial_period: string,
			* } $values
		 */
		return $values;
	}

	/**
	 * 覆寫 wc_format_sale_price
	 * Format a sale price for display.
	 *
	 * @since  3.0.0
	 * @param  string $regular_price Regular price.
	 * @param  string $sale_price    Sale price.
	 * @param  string $period_label  Period label.
	 * @return string
	 */
	public static function wc_format_subscription_sale_price( $regular_price, $sale_price, $period_label ) {
		// Format the prices.
		$formatted_regular_price = is_numeric( $regular_price ) ? \wc_price( (float) $regular_price ) : $regular_price;
		$formatted_regular_price = $formatted_regular_price . $period_label;
		$formatted_sale_price    = is_numeric( $sale_price ) ? \wc_price( (float) $sale_price ) : $sale_price;
		$formatted_sale_price    = $formatted_sale_price . $period_label;

		// Strikethrough pricing.
		$price = '<del aria-hidden="true">' . $formatted_regular_price . '</del> ';

		// For accessibility (a11y) we'll also display that information to screen readers.
		$price .= '<span class="screen-reader-text">';
		// translators: %s is a product's regular price.
		$price .= \esc_html( sprintf( \__( 'Original price was: %s.', 'woocommerce' ), \wp_strip_all_tags( $formatted_regular_price ) ) );
		$price .= '</span>';

		// Add the sale price.
		$price .= '<ins aria-hidden="true">' . $formatted_sale_price . '</ins>';

		// For accessibility (a11y) we'll also display that information to screen readers.
		$price .= '<span class="screen-reader-text">';
		// translators: %s is a product's current (sale) price.
		$price .= \esc_html( sprintf( __( 'Current price is: %s.', 'woocommerce' ), \wp_strip_all_tags( $formatted_sale_price ) ) );
		$price .= '</span>';

		return (string) \apply_filters( 'woocommerce_format_sale_price', $price, $regular_price, $sale_price );
	}
}
