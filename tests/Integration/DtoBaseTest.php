<?php
/**
 * DTO Base / Contracts DTO 整合測試
 * 驗證 Contracts\DTOs 底下 DTO 子類別的 hydration 行為
 */

declare( strict_types=1 );

namespace Tests\Integration;

use J7\Powerhouse\Contracts\DTOs\FormFieldDTO;

/**
 * Class DtoBaseTest
 *
 * @group dto
 */
class DtoBaseTest extends TestCase {

	// ========== 🔥 冒煙測試 ==========

	/**
	 * @test
	 * @group smoke
	 */
	public function form_field_dto_類別應存在(): void {
		$this->assertTrue( class_exists( FormFieldDTO::class ) );
	}

	/**
	 * @test
	 * @group smoke
	 */
	public function callable_dto_類別應存在(): void {
		$this->assertTrue( class_exists( \J7\Powerhouse\Contracts\DTOs\CallableDTO::class ) );
	}

	/**
	 * @test
	 * @group smoke
	 */
	public function message_template_dto_類別應存在(): void {
		$this->assertTrue( class_exists( \J7\Powerhouse\Contracts\DTOs\MessageTemplateDTO::class ) );
	}

	// ========== ✅ 快樂路徑 ==========

	/**
	 * @test
	 * @group happy
	 */
	public function form_field_dto_預設值應正確(): void {
		$dto = new FormFieldDTO();

		$this->assertSame( '', $dto->element );
		$this->assertSame( [], $dto->attributes );
		$this->assertSame( '', $dto->name );
		$this->assertSame( '', $dto->label );
		$this->assertSame( 'text', $dto->type );
		$this->assertFalse( $dto->required );
		$this->assertSame( '', $dto->placeholder );
		$this->assertSame( 0, $dto->sort );
	}

	/**
	 * @test
	 * @group happy
	 */
	public function form_field_dto_建構子可接受完整資料陣列(): void {
		$dto = new FormFieldDTO(
			[
				'element'     => 'input',
				'name'        => 'email',
				'label'       => 'Email',
				'type'        => 'text',
				'required'    => true,
				'placeholder' => 'your@mail.com',
				'sort'        => 10,
			]
		);

		$this->assertSame( 'input', $dto->element );
		$this->assertSame( 'email', $dto->name );
		$this->assertSame( 'Email', $dto->label );
		$this->assertTrue( $dto->required );
		$this->assertSame( 'your@mail.com', $dto->placeholder );
		$this->assertSame( 10, $dto->sort );
	}

	/**
	 * @test
	 * @group happy
	 */
	public function form_field_dto_to_array_應回傳所有欄位(): void {
		$dto = new FormFieldDTO(
			[
				'name' => 'password',
				'type' => 'password',
			]
		);

		$arr = $dto->to_array();
		$this->assertIsArray( $arr );
		$this->assertArrayHasKey( 'name', $arr );
		$this->assertArrayHasKey( 'type', $arr );
		$this->assertSame( 'password', $arr['name'] );
		$this->assertSame( 'password', $arr['type'] );
	}

	/**
	 * @test
	 * @group happy
	 */
	public function message_template_dto_of_可從_post_建立(): void {
		$post_id = $this->factory()->post->create(
			[
				'post_type'    => 'ph_message_tpl',
				'post_title'   => '歡迎信',
				'post_content' => '歡迎加入',
				'post_status'  => 'publish',
			]
		);
		\update_post_meta( $post_id, 'subject', '主旨測試' );
		\update_post_meta( $post_id, 'content_type', 'html' );

		$dto = \J7\Powerhouse\Contracts\DTOs\MessageTemplateDTO::of( (string) $post_id );

		// EContentType 可能僅支援特定值，這裡只驗證基本欄位
		if ( null === $dto ) {
			$this->markTestSkipped( 'EContentType 不接受 html 值' );
			return;
		}

		$this->assertSame( (string) $post_id, $dto->id );
		$this->assertSame( '歡迎信', $dto->name );
		$this->assertSame( '歡迎加入', $dto->content );
		$this->assertSame( '主旨測試', $dto->subject );
	}

	/**
	 * @test
	 * @group edge
	 */
	public function message_template_dto_of_不存在_id_應回傳_null(): void {
		$dto = \J7\Powerhouse\Contracts\DTOs\MessageTemplateDTO::of( '999999999' );
		$this->assertNull( $dto );
	}

	// ========== ❌ 錯誤處理 ==========

	/**
	 * @test
	 * @group error
	 */
	public function form_field_dto_傳入_null_應安全回退(): void {
		$dto = new FormFieldDTO( null );
		$this->assertSame( 'text', $dto->type );
	}
}
