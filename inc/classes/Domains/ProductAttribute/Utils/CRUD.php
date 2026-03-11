<?php

declare(strict_types=1);

namespace J7\Powerhouse\Domains\ProductAttribute\Utils;

/** ProductAttribute CRUD */
abstract class CRUD {

	/**
	 * Create a new product attribute
	 *
	 * @see https://wp-kama.com/plugin/woocommerce/function/wc_create_attribute
	 * @param array{
	 * name?: string,
	 * slug?: string,
	 * type?: string,
	 * order_by?: string,
	 * has_archives?: boolean,
	 * } $args Arguments.
	 *
	 * @return int|\WP_Error
	 */
	public static function create_product_attribute( array $args = [] ): int|\WP_Error {
		$taxonomy = 'pa_' . ( $args['slug'] ?? '' ); // 前綴 pa_ 是 WooCommerce的慣例
		if (\taxonomy_exists($taxonomy)) {
			return new \WP_Error('taxonomy_exists', __('Taxonomy already exists', 'powerhouse'));
		}

		// 使用 WooCommerce API新增屬性
		$attribute_id = \wc_create_attribute($args);

		// 註冊taxonomy以便立即使用
		\register_taxonomy(
					$taxonomy,
					'product',
					[
						'hierarchical'      => false,
						'show_ui'           => true,
						'show_in_nav_menus' => false,
					]
			);

		// 清除快取以立即生效
		// \wp_schedule_single_event( time(), 'woocommerce_flush_rewrite_rules' );
		// \delete_transient('wc_attribute_taxonomies');
		// \WC_Cache_Helper::invalidate_cache_group( 'woocommerce-attributes' );
		// \clean_taxonomy_cache( $taxonomy );

		return $attribute_id;
	}

	/**
	 * 刪除 product attribute
	 *
	 * @param int $id       product_attribute id.
	 *
	 * @return bool|\WP_Error
	 */
	public static function delete_product_attribute( int $id ): bool|\WP_Error {

		$result = \wc_delete_attribute($id);

		if (!$result) {
			return new \WP_Error('delete_attribute_failed', __('Delete attribute failed', 'powerhouse'));
		}

		return $result;
	}

	/**
	 * Update a product attribute
	 *
	 * @param string|int $id   product attribute id.
	 * @param array{
	 * id?: int,
	 * name?: string,
	 * slug?: string,
	 * type?: string,
	 * order_by?: string,
	 * has_archives?: boolean,
	 * } $args Arguments.
	 *
	 * @return int|\WP_Error
	 */
	public static function update_product_attribute( string|int $id, array $args ): int|\WP_Error {
		$result = \wc_update_attribute( (int) $id, $args);
		return $result;
	}
}
