<?php

declare(strict_types=1);

namespace J7\Powerhouse\Shared\Helpers;

use J7\Powerhouse\Shared\Enums\EOperater;

/**
 * 方便比較
 *
 * @example
 * $result = (new CompareHelper($post, $post_ids))->is(EOperater::IN)->match();
 */
final class CompareHelper {

	/** @var array<bool>  每次比較的結果 */
	private array $match_results = [];


	/** Constructor
	 *
	 * @param string|int|float|bool|array<mixed>|null $target 目標物件
	 * @param string|int|float|bool|array<mixed>|null $compared 比較物件
	 */
	public function __construct(
		private readonly string|int|float|bool|array|null $target,
		private readonly string|int|float|bool|array|null $compared,
	) {
	}

	/**
	 * 操作符，可以鏈式
	 *
	 * @param EOperater $operator 操作
	 *
	 * @return CompareHelper
	 */
	public function is( EOperater $operator ): self {
		$this->match_results[] = $this->is_match( $operator);
		return $this;
	}

	/** 是否滿足所有條件 */
	public function match(): bool {
		return !in_array(false, $this->match_results, true);
	}

	/**
	 * 是否符合
	 *
	 * @param EOperater $operator 操作
	 *
	 * @return bool
	 */
	private function is_match( EOperater $operator ): bool {
		try {
			switch ($operator) {
				case EOperater::LESS:
					return $this->target < $this->compared;
				case EOperater::LESS_OR_EQUAL:
					return $this->target <= $this->compared;
				case EOperater::EQUAL:
				case EOperater::EXACT:
					return $this->target === $this->compared;
				case EOperater::GREATER_OR_EQUAL:
					return $this->target >= $this->compared;
				case EOperater::GREATER:
					return $this->target > $this->compared;
				case EOperater::NOT_EQUAL:
					return $this->target !== $this->compared;
				case EOperater::IN:
					return \is_array($this->compared) && \in_array($this->target, $this->compared, true);
				case EOperater::NOT_IN:
					return \is_array($this->compared) && !\in_array($this->target, $this->compared, true);
				case EOperater::CONTAINS:
					return \is_string($this->target) && \is_string($this->compared) && \str_contains($this->target, $this->compared);
				case EOperater::NOT_CONTAINS:
					return \is_string($this->target) && \is_string($this->compared) && !\str_contains($this->target, $this->compared);
			}
			return false;
		} catch (\Throwable $th) {
			return false;
		}
	}
}
