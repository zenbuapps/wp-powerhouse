import { hexToOklch } from './utils'

/**
 * Blocksy palette_hex index → daisyUI CSS 變數 的對應表（單一事實來源）
 *
 * 與後端 `Theme\Core\Blocksy::get_oklch_overrides()` 一致。
 * index 4（Blocksy color5，邊框色）不對應任何 daisyUI token，故不在此表中。
 * 其餘未列出的 token（`--a/--ac/--pc/--sc/--nc`、狀態色與其 content、
 * 所有圓角/動畫/邊框尺寸）一律沿用 THEME_MAPPER 中 `power` 主題的預設值。
 */
export const BLOCKSY_PALETTE_TOKEN_MAP = [
	{ index: 0, token: '--p' }, // color1 → primary
	{ index: 1, token: '--s' }, // color2 → secondary
	{ index: 2, token: '--bc' }, // color3 → base-content
	{ index: 3, token: '--n' }, // color4 → neutral
	{ index: 5, token: '--b3' }, // color6 → base-300
	{ index: 6, token: '--b2' }, // color7 → base-200
	{ index: 7, token: '--b1' }, // color8 → base-100
] as const

/** 合法 hex 色碼格式（#RRGGBB，大小寫皆可） */
const HEX_REGEX = /^#[0-9a-fA-F]{6}$/

/**
 * 將 Blocksy palette_hex 依對應表轉成 daisyUI token 的 OKLCH 覆寫物件
 *
 * 供後台「跟隨 Blocksy」卡片的色塊與即時預覽使用——因為編譯後的 daisyUI CSS
 * 沒有 `[data-theme=blocksy]` 區塊，需以當前 palette 衍生的 OKLCH inline 渲染。
 *
 * 缺色或非法 hex 的 index 會被略過（該 token 退回呼叫端的預設值），
 * 採「部分成功優於全有全無」策略，不中斷其餘 token。
 *
 * @param paletteHex Blocksy 8 色 hex 陣列（index 0..7 = color1..color8）
 * @return 以 daisyUI CSS 變數為 key、OKLCH 字串為 value 的覆寫物件
 */
export function getBlocksyOklchOverrides(
	paletteHex: string[] | undefined
): Record<string, string> {
	if (!Array.isArray(paletteHex)) {
		return {}
	}

	return BLOCKSY_PALETTE_TOKEN_MAP.reduce<Record<string, string>>(
		(overrides, { index, token }) => {
			const hex = paletteHex[index]
			if ('string' === typeof hex && HEX_REGEX.test(hex)) {
				overrides[token] = hexToOklch(hex)
			}
			return overrides
		},
		{}
	)
}
