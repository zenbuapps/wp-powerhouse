<?php

declare(strict_types=1);

namespace J7\Powerhouse\Domains\Term\Utils;

use J7\WpUtils\Classes\WP;

/** Term CRUD */
abstract class CRUD {

	/**
	 * Create a new term
	 *
	 * @see https://developer.wordpress.org/reference/functions/wp_insert_term/
	 *
	 * @param string               $taxonomy taxonomy.
	 * @param array<string, mixed> $args Arguments.
	 *
	 * @return int|\WP_Error
	 */
	public static function create_term( string $taxonomy, array $args = [] ): int|\WP_Error {

		[
			'data' => $data,
			'meta_data' => $meta_data,
		] = WP::separator($args, 'term');

		$term = (string) ( $data['name'] ?? '' );
		unset($data['name']);

		/** @var array{term_id: int, term_taxonomy_id: int}|\WP_Error $result */
		$result = \wp_insert_term($term, $taxonomy, $data); // @phpstan-ignore-line

		if (\is_wp_error($result)) {
			return $result;
		}

		$term_id = $result['term_id'];
		foreach ($meta_data as $meta_key => $meta_value) {
			\update_term_meta($term_id, $meta_key, $meta_value);
		}

		return $term_id;
	}

	/**
	 * Sort terms
	 * 改變 term 順序
	 *
	 * @param string                                                                        $taxonomy taxonomy.
	 * @param array{from_tree: array<array{id: string}>, to_tree: array<array{id: string}>} $params Parameters.
	 *
	 * @return true|\WP_Error
	 * @throws \Exception 當 taxonomy 不存在時拋出異常
	 */
	public static function sort_terms( string $taxonomy, array $params ): bool|\WP_Error {
		$from_tree = $params['from_tree'] ?? []; // @phpstan-ignore-line
		$to_tree   = $params['to_tree'] ?? []; // @phpstan-ignore-line

		if (!$taxonomy) {
			throw new \Exception(__('taxonomy is required', 'powerhouse'));
		}

		$delete_ids = [];
		foreach ($from_tree as $from_node) {
			$from_id = $from_node['id'];
			$to_node = array_filter($to_tree, fn ( $node ) => $node['id'] === $from_id);
			if (empty($to_node)) {
				$delete_ids[] = $from_id;
			}
		}
		foreach ($to_tree as $node) {
			$to_id = $node['id'];

			$args = $node;
			unset($args['id']); // 不存 id

			$insert_result = self::update_term($to_id, $taxonomy, $args);

			if (\is_wp_error($insert_result)) {
				return $insert_result;
			}
		}

		foreach ($delete_ids as $id) {
			self::delete_term( (int) $id, $taxonomy );
		}

		return true;
	}

	/**
	 * 刪除 term
	 *
	 * @param int    $id       term id.
	 * @param string $taxonomy taxonomy.
	 *
	 * @return bool|int|\WP_Error
	 * @throws \Exception 刪除 term 失敗時拋出異常
	 */
	public static function delete_term( int $id, string $taxonomy ): bool|int|\WP_Error {
		if (!$taxonomy) {
			throw new \Exception(__('taxonomy is required', 'powerhouse'));
		}

		$result = \wp_delete_term( $id, $taxonomy );
		if (false === $result) {
			throw new \Exception(__('term not exists', 'powerhouse'));
		}

		if (0 === $result) {
			throw new \Exception(__('Attempted deletion of default Category', 'powerhouse'));
		}

		if (\is_wp_error($result)) {
			throw new \Exception($result->get_error_message());
		}

		return $result;
	}

	/**
	 * Update a term
	 *
	 * @param string|int           $term_id   term id.
	 * @param string               $taxonomy  taxonomy.
	 * @param array<string, mixed> $args Arguments.
	 *
	 * @return int|\WP_Error
	 */
	public static function update_term( string|int $term_id, string $taxonomy, array $args ): int|\WP_Error {

		[
			'data' => $data,
			'meta_data' => $meta_data,
		] = WP::separator($args, 'term');

		/** @var array{term_id: int, term_taxonomy_id: int}|\WP_Error $result */
		$result = \wp_update_term( (int) $term_id, $taxonomy, $data); // @phpstan-ignore-line

		if (\is_wp_error($result)) {
			return $result;
		}

		foreach ($meta_data as $meta_key => $meta_value) {
			\update_term_meta( (int) $term_id, $meta_key, $meta_value);
		}

		return (int) $term_id;
	}

	/**
	 * 取得最上層的 term ID
	 *
	 * @param int    $term_id  term ID.
	 * @param string $taxonomy taxonomy.
	 *
	 * @return int
	 */
	public static function get_top_term_id( int $term_id, string $taxonomy ): int {

		$cache_key   = "top_term_id_{$term_id}";
		$top_term_id = \wp_cache_get( $cache_key );
		if ( $top_term_id ) {
			return (int) $top_term_id;
		}

		$ancestors = \get_ancestors( $term_id, $taxonomy, 'taxonomy' );
		if ( empty( $ancestors ) ) {
			// 如果沒有祖先，自己就是最上層
			return $term_id;
		}
		// 取最後一個
		$top_term_id = $ancestors[ count( $ancestors ) - 1 ];

		\wp_cache_set( $cache_key, $top_term_id );
		return (int) $top_term_id;
	}


	/**
	 * 取得扁平的子孫 term ids，不包含頂層 id
	 * 階層子孫結構都打平
	 *
	 * @param int $term_id 文章 ID.
	 * @return array<int>
	 */
	public static function get_flatten_term_ids( int $term_id, string $taxonomy ): array {
		$result = \get_terms(
			[
				'taxonomy' => $taxonomy,
				'child_of' => $term_id,
				'fields'   => 'ids',
			]
			);
		if (\is_wp_error($result)) {
			return [];
		}
		/** @var array<int> $result */
		return $result;
	}
}
