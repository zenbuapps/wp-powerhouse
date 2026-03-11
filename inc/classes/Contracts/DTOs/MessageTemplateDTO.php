<?php

declare(strict_types=1);

namespace J7\Powerhouse\Contracts\DTOs;

use J7\Powerhouse\Shared\Enums\EContentType;
use J7\WpUtils\Classes\DTO;

/** 訊息模板 DTO */
class MessageTemplateDTO extends DTO {

	/** @var string 訊息模板 id */
	public string $id;

	/** @var string 名稱 */
	public string $name;

	/** @var string 主旨 */
	public string $subject;

	/** @var string 內文 */
	public string $content;

	/** @var EContentType 內容類型 */
	public EContentType $content_type;

	/** 創建實例 */
	public static function of( string $post_id ): self|null {
		$post = \get_post( (int) $post_id );
		if (!$post instanceof \WP_Post) {
			return null;
		}
		$content_type = (string) \get_post_meta( (int) $post_id, 'content_type', true );
		$args         = [
			'id'           => (string) $post->ID,
			'name'         => $post->post_title,
			'subject'      => (string) \get_post_meta( (int) $post_id, 'subject', true ),
			'content'      => $post->post_content,
			'content_type' => EContentType::from( $content_type ),
		];
		return new self( $args );
	}
}
