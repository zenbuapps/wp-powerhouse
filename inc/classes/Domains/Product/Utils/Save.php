<?php
/**
 * Product Save
 * 商品儲存相關 Helper
 */

declare(strict_types=1);

namespace J7\Powerhouse\Domains\Product\Utils;

/**
 * Class Save
 */
class Save {

	/**
	 * 處理儲存產品資料
	 *
	 * @param \WC_Product          $product 產品
	 * @param array<string, mixed> $data 資料
	 *
	 * @return void
	 */
	public static function data( \WC_Product &$product, array $data = [] ): void {
		\do_action('powerhouse/product/before_save_data', $product, $data);

		$product->set_props( $data );

		\do_action('powerhouse/product/after_save_data', $product, $data);
	}

	/**
	 * 處理儲存產品 meta data
	 *
	 * @param \WC_Product          $product 產品
	 * @param array<string, mixed> $meta_data 資料
	 *
	 * @return void
	 * @throws \Exception Exception 當 WC_Subscription 不存在時
	 */
	public static function meta_data( \WC_Product &$product, array $meta_data = [] ): void {
		// ----- ▼ 前端送 type 進來後，變更商品類型 ----- //
		$is_subscription = false;
		if (isset($meta_data['type'])) {
			$old_type = $product->get_type();
			$new_type = (string) $meta_data['type'];
			if ($old_type !== $new_type) {
				\wp_remove_object_terms($product->get_id(), $old_type, 'product_type');
				\wp_set_object_terms($product->get_id(), $new_type, 'product_type');
			}

			// type 會被儲存為商品的類型，不需要再額外存進 meta data
			$is_subscription = strpos( $new_type, 'subscription') !== false; // 判斷是否為訂閱商品
			unset($meta_data['type']);

			if ($is_subscription && !class_exists('WC_Subscription')) {
				throw new \Exception(__('WC_Subscription class does not exist, please make sure WooCommerce Subscription is installed', 'powerhouse'));
			}
		}

		unset( $meta_data['images'] ); // 圖片只做顯示用，不用存
		unset( $meta_data['files'] ); // files 會上傳，不用存

		// 如果是非訂閱商品，則刪除訂閱商品的相關資料
		if (!$is_subscription) {
			$fields_to_delete = Subscription::get_fields();
			foreach ($fields_to_delete as $field) {
				$product->delete_meta_data($field);
			}
		}

		\do_action('powerhouse/product/before_save_meta_data', $product, $meta_data);

		// 最後再來處理剩餘的 meta_data
		foreach ( $meta_data as $key => $value ) {
			\update_post_meta( $product->get_id(), $key, $value );
			// 如果要用 update_meta_data 需要知道 mid
			// $product->update_meta_data( $key, $value );
		}

		\do_action('powerhouse/product/after_save_meta_data', $product, $meta_data);
	}
}
