<?php

declare(strict_types=1);

namespace J7\Powerhouse\Domains\Post\Service;

use J7\WpUtils\Classes\General;
use J7\Powerhouse\Domains\Post\Service\MetaQueryClause as Clause;

/**
 * 操作 Meta Query Builder 的類
 * 可以用來改寫 meta_query 的參數
 * 用法:
 * $builder = new MetaQueryBuilder( $raw_meta_query );
 * $builder->find( 'key' )?->set( [ 'value' => 'value', 'compare' => 'compare' ] );
 * $builder->get_meta_query();
 */
final class MetaQueryBuilder {

	/** @var string 查詢參數的關係 */
	public string $relation = 'AND';

	/** @var Clause[] 查詢參數 */
	public array $clauses = [];

	/**
	 * Constructor
	 *
	 * @param array<string, mixed> $raw_meta_query 查詢參數
	 * @return void
	 *
	 * @example
	 * [
	 *  'relation' => 'AND',
	 *  '0' => [
	 *      'key' => 'key',
	 *      'value' => 'value',
	 *      'compare' => 'compare',
	 *  ],
	 *  '1' => [
	 *      'key' => 'key',
	 *      'value' => 'value',
	 *      'compare' => 'compare',
	 *  ],
	 * ]
	 */
	public function __construct( protected array $raw_meta_query ) {
		$this->relation = isset($this->raw_meta_query['relation']) ? (string) $this->raw_meta_query['relation'] : 'AND';
		unset( $raw_meta_query['relation'] );
		foreach ( $raw_meta_query as $clause ) {
			if (!is_array($clause)) {
				continue;
			}
			/** @var array<string, mixed> $clause */
			$this->clauses[] = new Clause( $clause );
		}
	}

	/**
	 * 查找 meta_query 中的 key
	 *
	 * @param string $key 查詢的 key
	 * @return Clause|null
	 */
	public function find( string $key ): Clause|null {
		$result = General::array_find( $this->clauses, fn( Clause $clause ) => $clause->key === $key );
		if ($result instanceof Clause) {
			return $result;
		}
		return null;
	}

	/**
	 * 移除 meta_query 中的 key
	 *
	 * @param string $key 查詢的 key
	 * @return self
	 */
	public function remove( string $key ): self {
		$this->clauses = \array_filter( $this->clauses, fn( $clause ) => $clause->key !== $key );
		return $this;
	}

	/**
	 * 新增 meta_query 中的 key
	 *
	 * @param Clause|array{key: string, value?: mixed, compare?: string} $clause 查詢的參數
	 * @return self
	 */
	public function add( Clause|array $clause ): self {
		if ( is_array( $clause ) ) {
			$this->clauses[] = new Clause( $clause );
		} else {
			$this->clauses[] = $clause;
		}
		return $this;
	}

	/**
	 * 取得 meta_query 的參數
	 *
	 * @return array<int|string, mixed>
	 */
	public function get_meta_query(): array {
		if ( !$this->clauses ) {
			return [];
		}

		$meta_query = [
			'relation' => $this->relation,
		];
		foreach ( $this->clauses as $clause ) {
			$meta_query[] = $clause->to_array();
		}
		return $meta_query;
	}
}
