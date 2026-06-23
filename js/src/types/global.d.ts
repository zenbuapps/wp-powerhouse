declare global {
	var powerhouse_data: {
		/** 加密後的環境變數 blob，由 antd-toolkit 的 simpleDecrypt 解密 */
		env: string
		/**
		 * Blocksy 主題跟隨資訊（後端於頁面 localize 的明文物件）
		 *
		 * 舊快取 JS 可能無此 key，讀取時須以可選鏈 + 預設值
		 * `{ is_blocksy: false, palette_hex: [] }` 兜底。
		 */
		blocksy?: {
			/** 當前站台主題是否為 Blocksy（含子主題） */
			is_blocksy: boolean
			/**
			 * Blocksy 8 色調色盤 hex 陣列，index 0..7 對應 color1..color8；
			 * 非 Blocksy 站台時為空陣列。
			 */
			palette_hex: string[]
		}
	}
}

export {}
