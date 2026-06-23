import { Form, FormInstance, Tooltip } from 'antd'
import { cn } from 'antd-toolkit'
import { useMemo } from 'react'

import { getBlocksyOklchOverrides } from './blocksy'
import { THEME_MAPPER } from './constants'

type TOptionProps = {
	/** 主題 slug（對應 THEME_MAPPER 的 theme 欄位，custom 為自訂） */
	theme: string

	/** antd Form 實例，用於讀取/設定選中的主題 */
	form: FormInstance

	/** 是否禁用此卡片（如非 Blocksy 站台的 blocksy 卡片），預設 false */
	disabled?: boolean

	/** 禁用時 Tooltip 提示文案 */
	disabledTooltip?: string

	/**
	 * Blocksy 調色盤 hex 陣列，僅 blocksy 卡片使用。
	 * 因編譯後的 daisyUI CSS 無 `[data-theme=blocksy]` 區塊，
	 * 卡片 swatch 改以此 palette 衍生的 OKLCH inline 渲染。
	 */
	paletteHex?: string[]
}

/**
 * 主題選項卡片
 *
 * 顯示單一主題的 swatch 預覽，點擊後將 form 的 theme 設為該主題。
 * 支援 disabled 態（灰態、不可點、Tooltip 包裹），用於非 Blocksy 站台的「跟隨 Blocksy」卡片。
 */
const Option = ({
	theme,
	form,
	disabled = false,
	disabledTooltip,
	paletteHex,
}: TOptionProps) => {
	const watchTheme =
		Form.useWatch(['powerhouse_settings', 'theme'], form) || 'power'

	const isSelected = watchTheme === theme

	/**
	 * blocksy 卡片專用的 scoped style。
	 * 以 THEME_MAPPER 的 blocksy placeholder 為基底，疊加當前 palette 衍生的 OKLCH 覆寫，
	 * 注入 `[data-theme=blocksy]` selector 供 swatch 的 daisyUI class 解析。
	 */
	const blocksyScopedStyle = useMemo(() => {
		if ('blocksy' !== theme) {
			return ''
		}
		const placeholder =
			THEME_MAPPER.find(
				({ theme: singleTheme }) => 'blocksy' === singleTheme
			) || {}
		const merged: Record<string, string> = {
			...placeholder,
			...getBlocksyOklchOverrides(paletteHex),
		}
		const declarations = Object.entries(merged)
			.filter(([key]) => key.startsWith('--'))
			.map(([key, value]) => `${key}: ${value};`)
			.join('')
		return `[data-theme=blocksy]{${declarations}}`
	}, [theme, paletteHex])

	const card = (
		<div
			className={cn(
				'relative',
				disabled ? 'cursor-not-allowed opacity-40 grayscale' : 'cursor-pointer'
			)}
		>
			{blocksyScopedStyle && <style>{blocksyScopedStyle}</style>}
			<div
				className={cn(
					'overflow-hidden border-base-content/20 rounded-lg border outline outline-2 outline-offset-2 outline-transparent',
					!disabled && 'hover:border-base-content/40',
					isSelected && 'outline outline-4 outline-yellow-300'
				)}
				onClick={
					disabled
						? undefined
						: () => {
								form.setFieldValue(['powerhouse_settings', 'theme'], theme)
							}
				}
			>
				<div
					className="bg-base-100 text-base-content w-full font-sans"
					data-theme={theme}
				>
					<div className="grid grid-cols-5 grid-rows-3">
						<div className="bg-base-200 col-start-1 row-span-2 row-start-1"></div>{' '}
						<div className="bg-base-300 col-start-1 row-start-3"></div>{' '}
						<div className="bg-base-100 col-span-4 col-start-2 row-span-3 row-start-1 flex flex-col gap-1 p-2">
							<div className="font-bold">
								{'blocksy' === theme ? '跟隨 Blocksy' : theme}
							</div>{' '}
							<div className="flex flex-wrap gap-1">
								<div className="bg-primary flex aspect-square w-5 items-center justify-center rounded lg:w-6">
									<div className="text-primary-content text-sm font-bold">
										A
									</div>
								</div>{' '}
								<div className="bg-secondary flex aspect-square w-5 items-center justify-center rounded lg:w-6">
									<div className="text-secondary-content text-sm font-bold">
										A
									</div>
								</div>{' '}
								<div className="bg-accent flex aspect-square w-5 items-center justify-center rounded lg:w-6">
									<div className="text-accent-content text-sm font-bold">A</div>
								</div>{' '}
								<div className="bg-neutral flex aspect-square w-5 items-center justify-center rounded lg:w-6">
									<div className="text-neutral-content text-sm font-bold">
										A
									</div>
								</div>
							</div>
						</div>
					</div>
				</div>
			</div>
			{isSelected && (
				<div className="bg-white absolute -top-2 -right-2 z-30 w-6 h-6 -1 rounded-full flex items-center justify-center">
					<svg
						viewBox="0 0 20 20"
						xmlns="http://www.w3.org/2000/svg"
						fill="none"
						className="w-5 h-5 [&_path]:fill-yellow-300"
					>
						<g strokeWidth="0"></g>
						<g strokeLinecap="round" strokeLinejoin="round"></g>
						<g>
							{' '}
							<path
								fill="#000000"
								fillRule="evenodd"
								d="M3 10a7 7 0 019.307-6.611 1 1 0 00.658-1.889 9 9 0 105.98 7.501 1 1 0 00-1.988.22A7 7 0 113 10zm14.75-5.338a1 1 0 00-1.5-1.324l-6.435 7.28-3.183-2.593a1 1 0 00-1.264 1.55l3.929 3.2a1 1 0 001.38-.113l7.072-8z"
							></path>{' '}
						</g>
					</svg>
				</div>
			)}
		</div>
	)

	// antd Tooltip 包 disabled 元素需用 wrapper span 才能觸發 hover
	if (disabled && disabledTooltip) {
		return (
			<Tooltip title={disabledTooltip}>
				<span className="block">{card}</span>
			</Tooltip>
		)
	}

	return card
}

export default Option
