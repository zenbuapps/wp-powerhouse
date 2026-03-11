<?php

declare(strict_types=1);

namespace J7\Powerhouse\Domains\Comment\Model;

use J7\WpUtils\Classes\DTO;

/**
 * Class Comment
 */
final class Comment extends DTO {
	/** @var string $id 評論 ID */
	public string $id;

	/** @var string $date_created 建立日期時間 */
	public string $date_created;

	/** @var string $content 評論內容 */
	public string $content;

	/** @var string $added_by 新增者 */
	public string $added_by;

	/** @var bool $is_customer_note 客戶是否可見 */
	public bool $is_customer_note = false;

	/** @var string $user_id 評論的作者 ID */
	public string $user_id;

	/** @var string $commented_user_id 被評論的用戶 ID */
	public string $commented_user_id;

	/**
	 * 實例化評論
	 *
	 * @param int $comment_id 評論 ID
	 * @return self|null 評論實例或 null
	 */
	public static function instance( $comment_id ): self|null {
		$comment = \get_comment( $comment_id );
		if ( ! ( $comment instanceof \WP_Comment ) ) {
			return null;
		}

		/** @var string $is_customer_note_raw */
		$is_customer_note_raw = \get_comment_meta( $comment_id, 'is_customer_note', true );

		$comment_data = [
			'id'                => (string) $comment->comment_ID,
			'date_created'      => $comment->comment_date,
			'content'           => \wpautop( $comment->comment_content ),
			'added_by'          => $comment->comment_author,
			'is_customer_note'  => \wc_string_to_bool( $is_customer_note_raw ),
			'user_id'           => (string) $comment->user_id,
			'commented_user_id' => (string) \get_comment_meta( $comment_id, 'commented_user_id', true ) ?: '0',
		];

		return new self( $comment_data );
	}
}
