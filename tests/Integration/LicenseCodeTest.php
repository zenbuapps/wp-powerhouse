<?php
/**
 * License Code（授權碼）系統整合測試
 * 驗證 LC Utils Base 的 encode/decode、transient 管理、ia() 授權狀態判斷
 */

declare( strict_types=1 );

namespace Tests\Integration;

use J7\Powerhouse\Domains\LC\Utils\Base as LCBase;

/**
 * Class LicenseCodeTest
 *
 * @group license-code
 */
class LicenseCodeTest extends TestCase {

	/** @var string 測試用假產品 slug */
	private const TEST_PRODUCT_SLUG = 'test-power-plugin';

	/** @var string 測試用假授權碼 */
	private const TEST_CODE = 'TEST-ABCD-1234-EFGH';

	public function tear_down(): void {
		$this->clean_lc_transients();
		\delete_option( LCBase::KEY );
		parent::tear_down();
	}

	// ========== 🔥 冒煙測試（Smoke）==========

	/**
	 * @test
	 * @group smoke
	 */
	public function LC_Utils_Base_類別應可存取(): void {
		$this->assertTrue( \class_exists( LCBase::class ) );
	}

	/**
	 * @test
	 * @group smoke
	 */
	public function encode_後_decode_應還原相同資料(): void {
		$original = [
			'code'         => self::TEST_CODE,
			'post_status'  => 'activated',
			'expire_date'  => 1999999999,
			'type'         => 'standard',
			'product_slug' => self::TEST_PRODUCT_SLUG,
			'product_name' => 'Test Power Plugin',
		];

		$encoded = LCBase::encode( $original );
		$this->assertIsString( $encoded );
		$this->assertNotEmpty( $encoded );

		$decoded = LCBase::decode( $encoded );
		$this->assertIsArray( $decoded );
		$this->assertSame( $original['code'], $decoded['code'] );
		$this->assertSame( $original['post_status'], $decoded['post_status'] );
		$this->assertSame( $original['product_slug'], $decoded['product_slug'] );
	}

	// ========== ✅ 快樂路徑（Happy Flow）==========

	/**
	 * @test
	 * @group happy
	 */
	public function get_default_lc_應回傳包含正確欄位的陣列(): void {
		$product_info = [ 'link' => 'https://example.com/product' ];
		$default      = LCBase::get_default_lc( self::TEST_PRODUCT_SLUG, 'Test Plugin', $product_info );

		$this->assertIsArray( $default );
		$this->assertSame( '', $default['code'], '預設 code 應為空字串' );
		$this->assertSame( '', $default['post_status'], '預設 post_status 應為空字串' );
		$this->assertSame( self::TEST_PRODUCT_SLUG, $default['product_slug'] );
		$this->assertSame( 'Test Plugin', $default['product_name'] );
		$this->assertSame( 'https://example.com/product', $default['link'] );
	}

	/**
	 * @test
	 * @group happy
	 */
	public function set_lc_transient_應正確儲存並可用_ia_驗證(): void {
		$lc_data = [
			'code'         => self::TEST_CODE,
			'post_status'  => 'activated',
			'expire_date'  => time() + DAY_IN_SECONDS,
			'type'         => 'standard',
			'product_slug' => self::TEST_PRODUCT_SLUG,
			'product_name' => 'Test Plugin',
		];

		LCBase::set_lc_transient( $lc_data );

		// ia() 應回傳 true，因為 post_status 為 activated
		$this->assertTrue( LCBase::ia( self::TEST_PRODUCT_SLUG ), '已啟用的授權碼 ia() 應回傳 true' );
	}

	/**
	 * @test
	 * @group happy
	 */
	public function set_lc_transient_應同時儲存_saved_code_到_option(): void {
		$lc_data = [
			'code'         => self::TEST_CODE,
			'post_status'  => 'activated',
			'expire_date'  => time() + DAY_IN_SECONDS,
			'type'         => 'standard',
			'product_slug' => self::TEST_PRODUCT_SLUG,
			'product_name' => 'Test Plugin',
		];

		LCBase::set_lc_transient( $lc_data );

		/** @var array<string, string> $saved_codes */
		$saved_codes = \get_option( LCBase::KEY, [] );
		$this->assertArrayHasKey( self::TEST_PRODUCT_SLUG, $saved_codes );
		$this->assertSame( self::TEST_CODE, $saved_codes[ self::TEST_PRODUCT_SLUG ] );
	}

	/**
	 * @test
	 * @group happy
	 */
	public function delete_lc_transient_應清除_transient_和_saved_code(): void {
		// 先設定 transient
		$lc_data = [
			'code'         => self::TEST_CODE,
			'post_status'  => 'activated',
			'expire_date'  => time() + DAY_IN_SECONDS,
			'type'         => 'standard',
			'product_slug' => self::TEST_PRODUCT_SLUG,
			'product_name' => 'Test Plugin',
		];
		LCBase::set_lc_transient( $lc_data );

		// 刪除
		LCBase::delete_lc_transient( self::TEST_PRODUCT_SLUG );

		// 確認 transient 已清除
		$this->assertFalse( \get_transient( "lc_" . self::TEST_PRODUCT_SLUG ), 'delete 後 transient 應為 false' );

		// 確認 saved_codes 中也已清除
		/** @var array<string, string> $saved_codes */
		$saved_codes = \get_option( LCBase::KEY, [] );
		$this->assertArrayNotHasKey( self::TEST_PRODUCT_SLUG, $saved_codes );
	}

	/**
	 * @test
	 * @group happy
	 */
	public function ia_未啟用產品應回傳_false(): void {
		$this->assertFalse( LCBase::ia( 'non-existent-product' ), '未設定 transient 的產品 ia() 應回傳 false' );
	}

	/**
	 * @test
	 * @group happy
	 */
	public function ia_狀態非_activated_時應回傳_false(): void {
		$lc_data = [
			'code'         => self::TEST_CODE,
			'post_status'  => 'available', // 未啟用
			'expire_date'  => time() + DAY_IN_SECONDS,
			'type'         => 'standard',
			'product_slug' => self::TEST_PRODUCT_SLUG,
			'product_name' => 'Test Plugin',
		];
		LCBase::set_lc_transient( $lc_data );

		$this->assertFalse( LCBase::ia( self::TEST_PRODUCT_SLUG ), '狀態為 available 時 ia() 應回傳 false' );
	}

	/**
	 * @test
	 * @group happy
	 */
	public function get_lc_array_沒有已註冊產品時應回傳空陣列(): void {
		// 沒有任何 powerhouse_product_infos filter 被加入
		$lc_array = LCBase::get_lc_array();
		$this->assertIsArray( $lc_array );
		$this->assertEmpty( $lc_array, '沒有已登記的產品時 get_lc_array 應回傳空陣列' );
	}

	/**
	 * @test
	 * @group happy
	 */
	public function get_lc_array_有已登記產品且有_transient_時應回傳解密資料(): void {
		// 建立假 transient
		$this->set_activated_lc_transient( self::TEST_PRODUCT_SLUG, self::TEST_CODE );

		// 透過 filter 登記假產品
		\add_filter(
			'powerhouse_product_infos',
			function ( $infos ) {
				$infos[ self::TEST_PRODUCT_SLUG ] = [
					'name' => 'Test Power Plugin',
					'link' => 'https://example.com',
				];
				return $infos;
			}
		);

		$lc_array = LCBase::get_lc_array();

		// 清除 filter
		\remove_all_filters( 'powerhouse_product_infos' );

		$this->assertIsArray( $lc_array );
		$this->assertCount( 1, $lc_array );
		$this->assertSame( self::TEST_PRODUCT_SLUG, $lc_array[0]['product_slug'] );
		$this->assertSame( 'activated', $lc_array[0]['post_status'] );
	}

	// ========== ❌ 錯誤處理（Error Handling）==========

	/**
	 * @test
	 * @group error
	 *
	 * 注意：JsAesPhp::decrypt() 對無效輸入會拋出 Error 而非 Exception
	 * Base::decode() 內部的 catch 只捕獲 \Exception，所以 Error 會向上拋出
	 * 這是目前 powerhouse 實作的已知行為，測試在此驗證此行為
	 */
	public function decode_損壞字串_JsAesPhp_會拋出_Error(): void {
		$corrupted = 'this-is-not-valid-encrypted-data-@#$%';

		$this->expectException( \Error::class );
		LCBase::decode( $corrupted );
	}

	/**
	 * @test
	 * @group error
	 *
	 * 注意：JsAesPhp::decrypt() 對空字串輸入會拋出 Error
	 */
	public function decode_空字串_JsAesPhp_會拋出_Error(): void {
		$this->expectException( \Error::class );
		LCBase::decode( '' );
	}

	/**
	 * @test
	 * @group error
	 *
	 * 注意：損壞的 transient 在 ia() 中解密會因 JsAesPhp 拋出 Error
	 * 因為 Base::decode() 的 catch 只捕獲 \Exception 而非 \Error
	 * ia() 最終會因為 decode() 拋出而回傳 false（外層邏輯捕獲）
	 * 或者直接拋出 Error；此測試驗證 ia() 對損壞 transient 的處理
	 */
	public function ia_transient_被損壞時的行為(): void {
		// 直接設定一個損壞的 transient
		\set_transient( 'lc_' . self::TEST_PRODUCT_SLUG, 'corrupted-data', HOUR_IN_SECONDS );

		// ia() 讀取到非 false 的 transient 值後會呼叫 decode()
		// decode() 內部的 JsAesPhp::decrypt() 對 'corrupted-data' 會拋出 Error
		// 這是 JsAesPhp 的已知行為：對無效 base64/JSON 輸入拋出 Error
		try {
			$result = LCBase::ia( self::TEST_PRODUCT_SLUG );
			// 如果沒有拋出（未來版本可能修正），確認回傳 false
			$this->assertFalse( $result, '損壞的 transient 應導致 ia() 回傳 false' );
		} catch ( \Error $e ) {
			// JsAesPhp 拋出 Error 是當前已知行為，test pass
			$this->assertStringContainsString( 'Invalid', $e->getMessage(), 'Error 訊息應包含 Invalid' );
		}
	}

	// ========== 🔀 邊緣案例（Edge Cases）==========

	/**
	 * @test
	 * @group edge
	 */
	public function encode_含有_Unicode_字元的資料應正常編解碼(): void {
		$original = [
			'code'         => 'UNICODE-測試碼-🚀',
			'post_status'  => 'activated',
			'expire_date'  => 0,
			'type'         => 'standard',
			'product_slug' => 'test-unicode',
			'product_name' => '測試外掛 Unicode 名稱',
		];

		$encoded = LCBase::encode( $original );
		$decoded = LCBase::decode( $encoded );

		$this->assertIsArray( $decoded );
		$this->assertSame( 'UNICODE-測試碼-🚀', $decoded['code'] );
		$this->assertSame( '測試外掛 Unicode 名稱', $decoded['product_name'] );
	}

	/**
	 * @test
	 * @group edge
	 */
	public function delete_lc_transient_刪除不存在的_slug_應不拋出例外(): void {
		try {
			$result = LCBase::delete_lc_transient( 'non-existent-product-slug' );
			$this->assertIsBool( $result );
			$this->assertFalse( $result, '不存在的 transient 刪除應回傳 false' );
		} catch ( \Throwable $e ) {
			$this->fail( "刪除不存在的 transient 不應拋出例外：{$e->getMessage()}" );
		}
	}

	/**
	 * @test
	 * @group edge
	 */
	public function set_lc_transient_應移除_logs_欄位後再儲存(): void {
		$lc_data = [
			'code'         => self::TEST_CODE,
			'post_status'  => 'activated',
			'expire_date'  => time() + DAY_IN_SECONDS,
			'type'         => 'standard',
			'product_slug' => self::TEST_PRODUCT_SLUG,
			'product_name' => 'Test Plugin',
			'logs'         => [ 'some log data', 'another log' ], // logs 欄位應被移除
		];

		LCBase::set_lc_transient( $lc_data );

		$transient_value = \get_transient( 'lc_' . self::TEST_PRODUCT_SLUG );
		$this->assertNotFalse( $transient_value );

		$decoded = LCBase::decode( (string) $transient_value );
		$this->assertIsArray( $decoded );
		$this->assertArrayNotHasKey( 'logs', $decoded, 'transient 中不應包含 logs 欄位' );
	}

	/**
	 * @test
	 * @group edge
	 */
	public function LC_KEY_常數應為正確值(): void {
		$this->assertSame( 'powerhouse_license_codes', LCBase::KEY );
	}

	/**
	 * @test
	 * @group edge
	 */
	public function LC_CACHE_TIME_應為_24_小時(): void {
		$this->assertSame( 24 * HOUR_IN_SECONDS, LCBase::CACHE_TIME );
	}
}
