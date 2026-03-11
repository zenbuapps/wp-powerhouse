<?php

declare(strict_types=1);

namespace J7\Powerhouse\Domains\Term\Model;

use J7\WpUtils\Classes\DTO;

/** 商品 Term DTO */
final class Term extends DTO {

	/** @var string $id */
	public string $id;

	/** @var string $name 名稱 */
	public string $name = '';

	/** @var string $slug */
	public string $slug = '';

	/** @var string $permalink 連結 */
	public string $permalink = '';

	/** @var string $edit_url 編輯連結 */
	public string $edit_url = '';

	/** @var string $term_taxonomy_id */
	public string $term_taxonomy_id = '';

	/** @var string $taxonomy ex 分類/標籤等等... */
	public string $taxonomy = '';

	/** @var string $description 描述 */
	public string $description = '';

	/** @var string $parent 父 ID */
	public string $parent = '';

	/** @var int $count term 數量 */
	public int $count = 0;

	/** @var int $order term 排序 */
	public int $order = 0;

	/** @var Term[]|null  $children 子孫 */
	public array|null $children = null;

	/** @var string $thumbnail_id 縮圖 id，DB 是存 id，但前端拿 url 直接顯示 */
	public string $thumbnail_id = '';

	/**
	 * 取得實例
	 *
	 * @param \WP_Term $term 商品
	 */
	public static function instance( \WP_Term $term ): self {

		$children = \get_terms(
			[
				'taxonomy'   => $term->taxonomy,
				'parent'     => $term->term_id,
				'hide_empty' => false,
				'orderby'    => 'order',
				'order'      => 'ASC',
			]
			);

		$permalink = \get_term_link( $term, $term->taxonomy );
		$permalink = \is_wp_error( $permalink ) ? '' : $permalink;

		$thumb_id      = \get_term_meta( $term->term_id, 'thumbnail_id', true );
		$thumbnail_url = \wp_get_attachment_url( (int) $thumb_id );
		$thumbnail_url = $thumbnail_url ? $thumbnail_url : '';

		$order = \get_term_meta( $term->term_id, 'order', true );

		$args = [
			'id'               => (string) $term->term_id,
			'name'             => $term->name,
			'slug'             => $term->slug,
			'permalink'        => $permalink,
			'edit_url'         => \get_edit_term_link( $term->term_id, $term->taxonomy ),
			'term_taxonomy_id' => (string) $term->term_taxonomy_id,
			'taxonomy'         => $term->taxonomy,
			'description'      => $term->description,
			'parent'           => $term->parent ? (string) $term->parent : '',
			'count'            => (int) $term->count,
			'order'            => (int) $order,
			'children'         => !\is_wp_error($children) ? array_map(
				fn ( \WP_Term $child ) => self::instance( $child ),
				$children
			) : [],
			'thumbnail_id'     => $thumbnail_url,
		];

		$instance = new self($args);
		return $instance;
	}

	/**
	 * 轉換為陣列
	 *
	 * @return array<string, mixed>
	 */
	public function to_array(): array {
		$array = parent::to_array();

		if (!$array['children']) {
			$array['children'] = null;
			return $array;
		}

		/** @var array<Term> $children */
		$children          = $array['children'];
		$array['children'] = array_map(
			fn ( Term $child ) => $child->to_array(),
			$children
		);

		return $array;
	}
}
