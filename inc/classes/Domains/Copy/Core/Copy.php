<?php
/**
 * Copy
 * 複製文章功能的類
 */

declare ( strict_types=1 );

namespace J7\Powerhouse\Domains\Copy\Core;

/**
 * Class Copy
 */
final class Copy {
	use \J7\WpUtils\Traits\SingletonTrait;

	/**
	 * 要排除的 meta key
	 *
	 * @var array<string>
	 */
	protected static array $exclude_meta_keys = [
		'total_sales', // 商品銷售量
		'_edit_lock',
		'_edit_last',
	];

	/**
	 * Constructor
	 */
	public function __construct() {
		\add_action( 'powerhouse_after_copy_post', [ __CLASS__, 'copy_children_post' ], 10, 5 );
	}


	/**
	 * 複製文章 post
	 *
	 * @param int      $post_id 要複製的文章 ID
	 * @param bool     $copy_terms 是否複製分類
	 * @param int|bool $override_post_parent 白話來說就是，你複製一個子文章時，要複製到誰底下，
	 * false 則不複製
	 * true 複製到同一個 post_parent
	 * int 複製到另一個 post_parent 底下
	 *
	 * @return int 複製後的文章 ID
	 * @throws \Exception Exception
	 */
	public function process( int $post_id, ?bool $copy_terms = true, int|bool $override_post_parent = false, int $depth = 0 ): int {
		$post_type = \get_post_type( $post_id );

		$copy_callback = match ( $post_type ) {
			'product' => [ __CLASS__, 'copy_product' ],
			default => [ __CLASS__, 'copy_post' ],
		};

		// 可以改寫複製的 callback，例如課程商品、銷售方案等
		$copy_callback = \apply_filters( 'powerhouse/copy/callback', $copy_callback, $post_id, $copy_terms, $override_post_parent, $depth );

		/** @var callable(int, bool, int|bool): int $copy_callback */
		$new_id = call_user_func( $copy_callback, $post_id, $copy_terms, $override_post_parent, $depth ); // @phpstan-ignore-line

		\do_action( 'powerhouse_after_copy_post', $this, $post_id, $new_id, $override_post_parent, $depth );

		return $new_id;
	}

	/**
	 * 複製文章/Email
	 *
	 * @param int      $post_id 要複製的文章 ID
	 * @param bool     $copy_terms 是否複製分類
	 * @param int|bool $override_post_parent 覆寫 post_parent, false 則不複製當前文章的子文章, true 會複製當前文章的子文章但當前文章 post_parent 不變
	 * @param int      $depth 遞迴深度
	 *
	 * @return int 複製後的文章 ID
	 * @throws \Exception Exception
	 */
	public static function copy_post( int $post_id, ?bool $copy_terms = true, int|bool $override_post_parent = false, int $depth = 0 ): int {
		$post = \get_post($post_id);
		if (!$post) {
			throw new \Exception(
				sprintf(
				__('post not found #%s', 'powerhouse'),
				$post_id
			)
			);
		}

		// 複製文章
		/** @var \WP_Post $post */
		// @phpstan-ignore-next-line
		$post->ID = null;
		if (0 === $depth) {
			$post->post_title .= __(' (copy)', 'powerhouse');
		}
		// $post->post_status = 'draft';

		// 插入新文章
		// @phpstan-ignore-next-line
		$new_id = \wp_insert_post( (array) $post );

		if (!\is_numeric($new_id)) {
			throw new \Exception(
				sprintf(
				__('copy post failed, %s', 'powerhouse'),
				$new_id->get_error_message()
			)
			);
		}

		// 複製 meta
		/** @var array<string, array<int, string>> $metas */
		$metas = \get_post_meta($post_id);
		foreach ($metas as $key => $values) {
			foreach ($values as $value) {
				if (in_array($key, self::$exclude_meta_keys, true)) {
					continue;
				}

				$unserialized = \maybe_unserialize($value);
				/** @var array<mixed>|string $slash_value */
				$slash_value = is_array($unserialized) || is_string($unserialized) ? $unserialized : (string) $unserialized;
				\add_post_meta($new_id, $key, \wp_slash($slash_value));
			}
		}

		// 複製文章 terms
		if ($copy_terms) {
			$success = self::copy_terms($post_id, $new_id);
		}

		if (\is_numeric($override_post_parent)) {
			\wp_update_post(
				[
					'ID'          => $new_id,
					'post_parent' => $override_post_parent,
				]
			);
		}

		return $new_id;
	}

	/**
	 * 複製產品
	 *
	 * @param int      $post_id 要複製的文章 ID
	 * @param bool     $copy_terms 是否複製分類
	 * @param int|bool $override_post_parent 覆寫 post_parent, false 則不複製當前文章的子文章, true 會複製當前文章的子文章但當前文章 post_parent 不變
	 * @param int      $depth 遞迴深度
	 * @return int 複製後的商品 ID
	 * @throws \Exception Exception
	 */
	public static function copy_product( int $post_id, ?bool $copy_terms = true, int|bool $override_post_parent = false, int $depth = 0 ): int {
		$product = \wc_get_product($post_id);
		if (!$product) {
			throw new \Exception(
				sprintf(
				__('product not found #%s', 'powerhouse'),
				$post_id
			)
			);
		}

		// 使用 WC_Admin_Duplicate_Product 複製產品
		$copy           = new \WC_Admin_Duplicate_Product();
		$new_product    = $copy->product_duplicate($product);
		$new_product_id = $new_product->get_id();

		// 如果需要複製分類
		if ($copy_terms) {
			self::copy_terms($post_id, $new_product_id);
		}

		return $new_product_id;
	}

	/**
	 * 複製項目的分類關係
	 *
	 * @param int|\WP_Post|\WC_Product $source 來源項目（可以是 ID、Post 物件或 Product 物件）
	 * @param int                      $target_id 目標項目 ID
	 *
	 * @return bool 設定 term 是否成功
	 * @throws \Exception Exception
	 */
	public static function copy_terms( $source, int $target_id ): bool {
		// 取得來源 ID 和類型
		$source_id = 0;
		$post_type = '';

		if (is_numeric($source)) {
			$source_id = (int) $source;
			$post      = \get_post($source_id);
			// @phpstan-ignore-next-line
			$post_type = $post ? $post->post_type : '';
		} elseif ($source instanceof \WC_Product) {
			$source_id = $source->get_id();
			$post_type = 'product';
		} elseif ($source instanceof \WP_Post) {
			$source_id = $source->ID;
			$post_type = $source->post_type;
		}

		if (!$source_id || !$post_type) {
			return false;
		}

		// 取得該類型的所有分類法
		$taxonomies = \get_object_taxonomies($post_type);

		foreach ($taxonomies as $taxonomy) {
			$terms = \wp_get_object_terms($source_id, $taxonomy);
			if (!empty($terms) && !\is_wp_error($terms)) {
				$term_ids = \wp_list_pluck($terms, 'term_id');
				$result   = \wp_set_object_terms($target_id, $term_ids, $taxonomy);
				if (\is_wp_error($result)) {
					throw new \Exception($result->get_error_message());
				}
			}
		}

		return true;
	}

	/**
	 * 複製子文章
	 *
	 * @param self $copy 複製物件
	 * @param int  $post_id 文章 ID
	 * @param int  $new_id 複製後的文章 ID
	 * @param int  $override_post_parent 覆寫 post_parent, false 則不複製當前文章的子文章, true 會複製當前文章的子文章但當前文章 post_parent 不變
	 * @param int  $depth 遞迴深度
	 *
	 * @return void
	 */
	public static function copy_children_post( self $copy, int $post_id, int $new_id, ?int $override_post_parent = 0, int $depth = 0 ): void {
		if (!$override_post_parent) {
			return;
		}

		$default_args = [
			'post_parent' => $post_id,
			'post_type'   => 'post', // ❗ 這邊要記得改成你要複製的 post_type
			'numberposts' => -1,
			'fields'      => 'ids',
		];

		$args = \apply_filters( 'powerhouse/copy/children_post_args', $default_args, $post_id, $new_id, $override_post_parent, $depth );

		/** @var array<int> $children_ids */
		$children_ids = \get_children($args);

		foreach ($children_ids as $child_id) {
			$copy->process($child_id, true, $new_id, $depth + 1);
		}
	}
}
