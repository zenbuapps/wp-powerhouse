<?php
/**
 * ColorConvert 測試
 * 驗證 Hex → OKLCH 轉換對齊前端 culori 的精度
 */

declare( strict_types=1 );

namespace Tests\Integration;

use J7\Powerhouse\Theme\Utils\ColorConvert;

/**
 * Class ColorConvertTest
 *
 * @group theme
 */
class ColorConvertTest extends TestCase {

	/**
	 * 8 個 Blocksy 預設色的 culori golden values
	 * 來源：前端 culori（js/.../Theme/utils.tsx hexToOklch）對同色輸出
	 *
	 * @return array<string, array{0:string, 1:float, 2:float, 3:float}> [hex, L, C, H]
	 */
	public function blocksy_golden_provider(): array {
		return [
			'color1 #2872fa' => [ '#2872fa', 58.816253995304216, 0.21604942058310722, 261.33299834994443 ],
			'color2 #1559ed' => [ '#1559ed', 52.57284454395409, 0.2318521955937036, 262.7704224732516 ],
			'color3 #3A4F66' => [ '#3A4F66', 42.00285313026899, 0.04661461995383527, 251.21554177766404 ],
			'color4 #192a3d' => [ '#192a3d', 27.977535624252337, 0.04201383349445245, 252.07049464385133 ],
			'color5 #e1e8ed' => [ '#e1e8ed', 92.7126910342545, 0.010142585990617715, 238.5177593602014 ],
			'color6 #f2f5f7' => [ '#f2f5f7', 96.84406350142008, 0.004181085522055404, 236.49705936568103 ],
			'color7 #FAFBFC' => [ '#FAFBFC', 98.76094193568422, 0.001704785131930662, 247.8393139342965 ],
			'color8 #ffffff' => [ '#ffffff', 100.00000000000003, 0.0, 0.0 ],
		];
	}

	/**
	 * @test
	 * @group happy
	 * @dataProvider blocksy_golden_provider
	 *
	 * @param string $hex   輸入 hex。
	 * @param float  $exp_l 期望 L（百分比）。
	 * @param float  $exp_c 期望 C。
	 * @param float  $exp_h 期望 H（度）。
	 */
	public function hex_to_oklch_應對齊_culori_golden_values( string $hex, float $exp_l, float $exp_c, float $exp_h ): void {
		$result = ColorConvert::hex_to_oklch( $hex );
		$this->assertNotNull( $result, "{$hex} 應成功轉換" );

		[ $l, $c, $h ] = $this->parse_oklch( $result );

		$this->assertEqualsWithDelta( $exp_l, $l, 0.5, "L 偏差過大：{$hex}" );
		$this->assertEqualsWithDelta( $exp_c, $c, 0.005, "C 偏差過大：{$hex}" );
		// 近灰（C≈0）色相無意義，僅在彩度足夠時比對 H
		if ( $exp_c > 0.01 ) {
			$this->assertEqualsWithDelta( $exp_h, $h, 0.5, "H 偏差過大：{$hex}" );
		}
	}

	/**
	 * @test
	 * @group edge
	 */
	public function 大小寫_hex_應產生相同結果(): void {
		$this->assertSame(
			ColorConvert::hex_to_oklch( '#2872FA' ),
			ColorConvert::hex_to_oklch( '#2872fa' )
		);
	}

	/**
	 * @test
	 * @group edge
	 */
	public function 前後空白_應被容忍(): void {
		$this->assertNotNull( ColorConvert::hex_to_oklch( '  #2872fa  ' ) );
	}

	/**
	 * @test
	 * @group error
	 * @dataProvider invalid_hex_provider
	 *
	 * @param string $invalid 非法輸入。
	 */
	public function 非法_hex_應回_null( string $invalid ): void {
		$this->assertNull( ColorConvert::hex_to_oklch( $invalid ) );
	}

	/**
	 * @return array<string, array{0:string}>
	 */
	public function invalid_hex_provider(): array {
		return [
			'空字串'       => [ '' ],
			'無井號'       => [ '2872fa' ],
			'3 碼縮寫'     => [ '#abc' ],
			'8 碼含 alpha' => [ '#2872fa80' ],
			'非 hex 字元'  => [ '#gggggg' ],
			'rgb 字串'     => [ 'rgb(40,114,250)' ],
		];
	}

	/**
	 * @test
	 * @group happy
	 */
	public function 純黑應為_L0_C0(): void {
		$result = ColorConvert::hex_to_oklch( '#000000' );
		[ $l, $c ] = $this->parse_oklch( (string) $result );
		$this->assertEqualsWithDelta( 0.0, $l, 0.5 );
		$this->assertEqualsWithDelta( 0.0, $c, 0.005 );
	}

	/**
	 * 解析 "L% C H" → [l, c, h]
	 *
	 * @param string $oklch OKLCH 字串。
	 * @return array{0:float,1:float,2:float}
	 */
	private function parse_oklch( string $oklch ): array {
		$parts = explode( ' ', $oklch );
		$l     = (float) rtrim( $parts[0], '%' );
		$c     = (float) ( $parts[1] ?? '0' );
		$h     = (float) ( $parts[2] ?? '0' );
		return [ $l, $c, $h ];
	}
}
