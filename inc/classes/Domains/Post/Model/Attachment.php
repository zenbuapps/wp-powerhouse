<?php

declare(strict_types=1);

namespace J7\Powerhouse\Domains\Post\Model;

use J7\WpUtils\Classes\DTO;

/**
 * 附件屬性 DTO
 */
final class Attachment extends DTO {
	/** @var string $id 附件 ID */
	public string $id;

	/** @var string $status 附件狀態 */
	public string $status;

	/** @var string $slug 附件 slug */
	public string $slug;

	/** @var string $title 附件標題 */
	public string $title;

	/** @var string $filename 檔案名稱 */
	public string $filename;

	/** @var string $url 附件 URL */
	public string $url;

	/** @var string $img_url 圖片 URL */
	public string $img_url;

	/** @var string $_wp_attachment_image_alt 替代文字 */
	public string $_wp_attachment_image_alt;

	/** @var string $description 描述 */
	public string $description;

	/** @var string $short_description 說明文字 */
	public string $short_description;

	/** @var string $type 檔案類型 */
	public string $type;

	/** @var string $mime MIME 類型 */
	public string $mime;

	/** @var string $subtype 子類型 */
	public string $subtype;

	/** @var string $edit_link 編輯連結 */
	public string $edit_link;

	/** @var string $filesize_human_readable 人類可讀的檔案大小 */
	public string $filesize_human_readable;

	/** @var int|null $height 圖片高度 */
	public int|null $height;

	/** @var int|null $width 圖片寬度 */
	public int|null $width;

	/** @var string $date_created 建立日期 */
	public string $date_created;

	/** @var string $date_modified 修改日期 */
	public string $date_modified;

	/** @var array{id: string, name: string} $author 作者 */
	public array $author;

	/**
	 * 取得實例
	 *
	 * @see wp_prepare_attachment_for_js AND wp_ajax_query_attachments
	 * @param \WP_Post $post 文章
	 */
	public static function instance( \WP_Post $post ): self {

		$attachment_info = \wp_prepare_attachment_for_js( $post );
		if (!is_array($attachment_info)) {
			$attachment_info = [];
		}
		$subtype = self::get_subtype( $attachment_info );
		$img_url = self::get_image_url( $attachment_info, $subtype);

		// filename 取得 . 後面的附檔名

		$args = [
			'id'                       => (string) $post->ID,
			'status'                   => $post->post_status,
			'slug'                     => $post->post_name,
			'title'                    => $post->post_title,
			'filename'                 => $attachment_info['filename'] ?? '',
			'url'                      => $attachment_info['url'] ?? '',
			'img_url'                  => $img_url,
			'_wp_attachment_image_alt' => $attachment_info['alt'] ?? '',
			'description'              => $post->post_content,
			'short_description'        => $post->post_excerpt,
			'type'                     => isset($attachment_info['type']) ? (string) $attachment_info['type'] : '',
			'mime'                     => $post->post_mime_type,
			'subtype'                  => self::get_subtype( $attachment_info ),
			'edit_link'                => isset($attachment_info['editLink']) ? (string) $attachment_info['editLink'] : '',
			'filesize_human_readable'  => $attachment_info['filesizeHumanReadable'] ?? '',
			'height'                   => $attachment_info['height'] ?? null,
			'width'                    => $attachment_info['width'] ?? null,
			'date_created'             => $post->post_date,
			'date_modified'            => $post->post_modified,
			'author'                   => [
				'id'   => (string) $post->post_author,
				'name' => \get_the_author_meta( 'display_name', (int) $post->post_author ),
			],
		];

		$instance = new self( $args );
		return $instance;
	}

	/**
	 * 取得圖片 URL
	 *
	 * @param array<string, mixed> $attachment_info 附件資訊
	 * @param string               $subtype 子類型
	 * @return string 圖片 URL
	 */
	private static function get_image_url( array $attachment_info, string $subtype ): string {
		$url = isset($attachment_info['url']) ? (string) $attachment_info['url'] : '';
		if ('image' === ( $attachment_info['type'] ?? '' )) {
			return $url;
		}

		return match ( $subtype ) {
			'avi' => 'https://www.svgrepo.com/show/255798/avi.svg',
			'aac' => 'https://www.svgrepo.com/show/255799/aac.svg',
			'cdr' => 'https://www.svgrepo.com/show/255800/cdr.svg',
			'ai' => 'https://www.svgrepo.com/show/255801/ai-ai.svg',
			'3ds' => 'https://www.svgrepo.com/show/255802/3ds.svg',
			'cad' => 'https://www.svgrepo.com/show/255803/cad.svg',
			'dat' => 'https://www.svgrepo.com/show/255804/dat.svg',
			'bmp' => 'https://www.svgrepo.com/show/255805/bmp.svg',
			'css' => 'https://www.svgrepo.com/show/255806/css.svg',
			'fla' => 'https://www.svgrepo.com/show/255807/fla.svg',
			'dmg' => 'https://www.svgrepo.com/show/255808/dmg.svg',
			'eps' => 'https://www.svgrepo.com/show/255809/eps.svg',
			'doc' => 'https://www.svgrepo.com/show/255810/doc.svg',
			'docx' => 'https://www.svgrepo.com/show/255810/doc.svg',
			'dll' => 'https://www.svgrepo.com/show/255811/dll.svg',
			'html' => 'https://www.svgrepo.com/show/255812/html.svg',
			'gif' => 'https://www.svgrepo.com/show/255813/gif.svg',
			'flv' => 'https://www.svgrepo.com/show/255814/flv.svg',
			'iso' => 'https://www.svgrepo.com/show/255815/iso.svg',
			'indd' => 'https://www.svgrepo.com/show/255816/indd.svg',
			'mpg' => 'https://www.svgrepo.com/show/255817/mpg.svg',
			'pdf' => 'https://www.svgrepo.com/show/255818/pdf.svg',
			'mov' => 'https://www.svgrepo.com/show/255819/mov.svg',
			'ppt' => 'https://www.svgrepo.com/show/255820/ppt.svg',
			'pptx' => 'https://www.svgrepo.com/show/255820/ppt.svg',
			'midi' => 'https://www.svgrepo.com/show/255821/midi.svg',
			'png' => 'https://www.svgrepo.com/show/255822/png.svg',
			'php' => 'https://www.svgrepo.com/show/255823/php.svg',
			'mp3' => 'https://www.svgrepo.com/show/255824/mp3.svg',
			'js' => 'https://www.svgrepo.com/show/255825/js.svg',
			'jpg' => 'https://www.svgrepo.com/show/255826/jpg.svg',
			'xml' => 'https://www.svgrepo.com/show/255827/xml.svg',
			'ps' => 'https://www.svgrepo.com/show/255828/ps-ps.svg',
			'csv' => 'https://www.svgrepo.com/show/161804/csv.svg',
			'xls' => 'https://www.svgrepo.com/show/255829/xls.svg',
			'xlsx' => 'https://www.svgrepo.com/show/255829/xls.svg',
			'svg' => 'https://www.svgrepo.com/show/255830/svg.svg',
			'tif' => 'https://www.svgrepo.com/show/255831/tif.svg',
			'sql' => 'https://www.svgrepo.com/show/255832/sql.svg',
			'raw' => 'https://www.svgrepo.com/show/255833/raw.svg',
			'psd' => 'https://www.svgrepo.com/show/255834/psd.svg',
			'wmv' => 'https://www.svgrepo.com/show/255835/wmv.svg',
			'txt' => 'https://www.svgrepo.com/show/255836/txt.svg',
			'zip' => 'https://www.svgrepo.com/show/255837/zip.svg',
			default => $url,
		};
	}

	/**
	 * 取得附檔名
	 *
	 * @param array<string, mixed> $attachment_info 附件資訊
	 * @return string 附檔名
	 */
	private static function get_subtype( array $attachment_info ): string {
		$subtype = isset($attachment_info['subtype']) ? (string) $attachment_info['subtype'] : '';
		if ($subtype) {
			return $subtype;
		}
		$filename = isset($attachment_info['filename']) ? (string) $attachment_info['filename'] : '';
		// 不論 filename 有幾個 . 取得最後一個 . 後面的附檔名
		$parts = \explode( '.', $filename );
		return $parts[ \count( $parts ) - 1 ];
	}
}
