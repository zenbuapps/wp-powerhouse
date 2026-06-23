import { Form } from 'antd'
import { Heading, Switch } from 'antd-toolkit'
import { memo } from 'react'

import { THEME_MAPPER } from './constants'
import Custom from './Custom'
import Option from './Option'
import { getStyle } from './utils'

import hoodie from '@/assets/images/hoodie.jpg'

const { Item } = Form

const index = () => {
	const form = Form.useFormInstance()
	const watchTheme =
		Form.useWatch(['powerhouse_settings', 'theme'], form) || 'power'

	// 後端 localize 的 Blocksy 跟隨資訊；舊快取 JS 可能無此 key，以預設值兜底
	const { is_blocksy, palette_hex } = window?.powerhouse_data?.blocksy || {
		is_blocksy: false,
		palette_hex: [],
	}

	// 自訂的
	const watchCustomThemeOKLCH =
		Form.useWatch(['powerhouse_settings', 'theme_css'], form) || {}

	//	選中的
	const theme =
		THEME_MAPPER.find(({ theme: singleTheme }) => singleTheme === watchTheme) ||
		THEME_MAPPER?.[0]

	const mergedTheme = { ...theme, ...watchCustomThemeOKLCH }

	const themeStyle = getStyle(mergedTheme)
	return (
		<>
			<Item name={['powerhouse_settings', 'theme']} hidden />
			<div className="flex flex-col md:flex-row gap-4">
				<div className="w-full max-w-[400px] px-3 h-[calc(100vh-8rem)] overflow-y-auto overflow-x-hidden">
					<Custom />
					<Heading className="mt-8">或選擇主題</Heading>
					<Switch
						formItemProps={{
							label: '在前台顯示主題切換按鈕',
							name: ['powerhouse_settings', 'enable_theme_changer'],
						}}
					/>
					<div className="rounded-box grid grid-cols-2 gap-4">
						<Option theme="custom" form={form} />
						<Option
							theme="blocksy"
							form={form}
							disabled={!is_blocksy}
							disabledTooltip="需使用 Blocksy 主題"
							paletteHex={palette_hex}
						/>
						{THEME_MAPPER.filter(
							({ theme: singleTheme }) => 'blocksy' !== singleTheme
						).map(({ theme: singleTheme }) => (
							<Option key={singleTheme} theme={singleTheme} form={form} />
						))}
					</div>
				</div>
				<div
					data-theme="custom"
					className="flex-1 bg-transparent h-[calc(100vh-8rem)] overflow-y-auto overflow-x-hidden pr-3"
				>
					<style>{themeStyle}</style>
					<div className="pc-mockup-browser bg-base-300 border">
						<div className="pc-mockup-browser-toolbar">
							<div className="pc-input text-center">preview</div>
						</div>
						<div className="p-6 flex flex-col gap-12">
							<div className="flex gap-6">
								{/* Card */}
								<div className="pc-card bg-base-100 w-96 h-96 shadow-xl">
									<figure>
										<img
											className="w-full h-full object-cover"
											src={hoodie}
											alt="Shoes"
										/>
									</figure>
									<div className="pc-card-body">
										<h2 className="pc-card-title">
											Shoes!
											<div className="pc-badge pc-badge-secondary border-solid">
												NEW
											</div>
										</h2>
										<p>If a dog chews shoes whose shoes does he choose?</p>
										<div className="pc-card-actions justify-end">
											<div className="pc-badge pc-badge-outline border-solid ">
												Fashion
											</div>
											<div className="pc-badge pc-badge-outline border-solid ">
												Products
											</div>
										</div>
									</div>
								</div>
								<div className="flex-1 flex flex-col gap-6">
									{/* Badge */}
									<div className="flex gap-4">
										<div className="pc-badge">default</div>
										<div className="pc-badge pc-badge-neutral">neutral</div>
										<div className="pc-badge pc-badge-primary">primary</div>
										<div className="pc-badge pc-badge-secondary">secondary</div>
										<div className="pc-badge pc-badge-accent">accent</div>
										<div className="pc-badge pc-badge-ghost">ghost</div>
									</div>
									<div className="flex gap-4">
										<div className="pc-badge pc-badge-outline border-solid ">
											default
										</div>
										<div className="pc-badge pc-badge-primary pc-badge-outline border-solid ">
											primary
										</div>
										<div className="pc-badge pc-badge-secondary pc-badge-outline border-solid ">
											secondary
										</div>
										<div className="pc-badge pc-badge-accent pc-badge-outline border-solid ">
											accent
										</div>
									</div>
									<div className="flex gap-4">
										<div className="pc-badge pc-badge-info gap-2">
											<svg
												xmlns="http://www.w3.org/2000/svg"
												fill="none"
												viewBox="0 0 24 24"
												className="inline-block h-4 w-4 stroke-current"
											>
												<path
													strokeLinecap="round"
													strokeLinejoin="round"
													strokeWidth="2"
													d="M6 18L18 6M6 6l12 12"
												></path>
											</svg>
											info
										</div>
										<div className="pc-badge pc-badge-success gap-2">
											<svg
												xmlns="http://www.w3.org/2000/svg"
												fill="none"
												viewBox="0 0 24 24"
												className="inline-block h-4 w-4 stroke-current"
											>
												<path
													strokeLinecap="round"
													strokeLinejoin="round"
													strokeWidth="2"
													d="M6 18L18 6M6 6l12 12"
												></path>
											</svg>
											success
										</div>
										<div className="pc-badge pc-badge-warning gap-2">
											<svg
												xmlns="http://www.w3.org/2000/svg"
												fill="none"
												viewBox="0 0 24 24"
												className="inline-block h-4 w-4 stroke-current"
											>
												<path
													strokeLinecap="round"
													strokeLinejoin="round"
													strokeWidth="2"
													d="M6 18L18 6M6 6l12 12"
												></path>
											</svg>
											warning
										</div>
										<div className="pc-badge pc-badge-error gap-2">
											<svg
												xmlns="http://www.w3.org/2000/svg"
												fill="none"
												viewBox="0 0 24 24"
												className="inline-block h-4 w-4 stroke-current"
											>
												<path
													strokeLinecap="round"
													strokeLinejoin="round"
													strokeWidth="2"
													d="M6 18L18 6M6 6l12 12"
												></path>
											</svg>
											error
										</div>
									</div>

									{/* Alert */}
									<div role="alert" className="pc-alert shadow-lg">
										<svg
											xmlns="http://www.w3.org/2000/svg"
											fill="none"
											viewBox="0 0 24 24"
											className="stroke-info h-6 w-6 shrink-0"
										>
											<path
												strokeLinecap="round"
												strokeLinejoin="round"
												strokeWidth="2"
												d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
											></path>
										</svg>
										<div>
											<h3 className="font-bold">New message!</h3>
											<div className="text-xs">You have 1 unread message</div>
										</div>
										<button type="button" className="pc-btn pc-btn-sm">
											Deny
										</button>
										<button
											type="button"
											className="pc-btn pc-btn-sm pc-btn-primary"
										>
											Accept
										</button>
									</div>
								</div>
							</div>

							{/* Hero */}
							<div className="pc-hero bg-base-200 py-16 px-6">
								<div className="pc-hero-content flex-col lg:flex-row-reverse">
									<div className="text-center lg:text-left pl-12">
										<h1 className="text-5xl font-bold">Login now!</h1>
										<p className="py-6">
											Provident cupiditate voluptatem et in. Quaerat fugiat ut
											assumenda excepturi exercitationem quasi. In deleniti
											eaque aut repudiandae et a id nisi.
										</p>
									</div>
									<div className="pc-card bg-base-100 w-full max-w-sm shrink-0 shadow-2xl">
										<div className="pc-card-body">
											<div className="pc-form-control">
												<label className="pc-label">
													<span className="pc-label-text">Email</span>
												</label>
												<input
													type="email"
													placeholder="email"
													className="pc-input pc-input-bordered"
												/>
											</div>
											<div className="pc-form-control">
												<label className="pc-label">
													<span className="pc-label-text">Password</span>
												</label>
												<input
													type="password"
													placeholder="password"
													className="pc-input pc-input-bordered"
												/>
												<label className="pc-label">
													<span className="pc-label-text-alt pc-link pc-link-hover">
														Forgot password?
													</span>
												</label>
											</div>
											<div className="pc-form-control mt-6">
												<button type="button" className="pc-btn pc-btn-primary">
													Login
												</button>
											</div>
										</div>
									</div>
								</div>
							</div>

							{/* Step */}
							<ul className="pc-steps">
								<li className="pc-step pc-step-primary">Register</li>
								<li className="pc-step pc-step-primary">Choose plan</li>
								<li className="pc-step">Purchase</li>
								<li className="pc-step">Receive Product</li>
							</ul>

							{/* Timeline */}
							<ul className="pc-timeline">
								<li>
									<div className="pc-timeline-start pc-timeline-box">
										First Macintosh computer
									</div>
									<div className="pc-timeline-middle">
										<svg
											xmlns="http://www.w3.org/2000/svg"
											viewBox="0 0 20 20"
											fill="currentColor"
											className="text-primary h-5 w-5"
										>
											<path
												fillRule="evenodd"
												d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.857-9.809a.75.75 0 00-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 10-1.06 1.061l2.5 2.5a.75.75 0 001.137-.089l4-5.5z"
												clipRule="evenodd"
											/>
										</svg>
									</div>
									<hr className="bg-primary" />
								</li>
								<li>
									<hr className="bg-primary" />
									<div className="pc-timeline-middle">
										<svg
											xmlns="http://www.w3.org/2000/svg"
											viewBox="0 0 20 20"
											fill="currentColor"
											className="text-primary h-5 w-5"
										>
											<path
												fillRule="evenodd"
												d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.857-9.809a.75.75 0 00-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 10-1.06 1.061l2.5 2.5a.75.75 0 001.137-.089l4-5.5z"
												clipRule="evenodd"
											/>
										</svg>
									</div>
									<div className="pc-timeline-end pc-timeline-box">iMac</div>
									<hr className="bg-primary" />
								</li>
								<li>
									<hr className="bg-primary" />
									<div className="pc-timeline-start pc-timeline-box">iPod</div>
									<div className="pc-timeline-middle">
										<svg
											xmlns="http://www.w3.org/2000/svg"
											viewBox="0 0 20 20"
											fill="currentColor"
											className="text-primary h-5 w-5"
										>
											<path
												fillRule="evenodd"
												d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.857-9.809a.75.75 0 00-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 10-1.06 1.061l2.5 2.5a.75.75 0 001.137-.089l4-5.5z"
												clipRule="evenodd"
											/>
										</svg>
									</div>
									<hr />
								</li>
								<li>
									<hr />
									<div className="pc-timeline-middle">
										<svg
											xmlns="http://www.w3.org/2000/svg"
											viewBox="0 0 20 20"
											fill="currentColor"
											className="h-5 w-5"
										>
											<path
												fillRule="evenodd"
												d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.857-9.809a.75.75 0 00-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 10-1.06 1.061l2.5 2.5a.75.75 0 001.137-.089l4-5.5z"
												clipRule="evenodd"
											/>
										</svg>
									</div>
									<div className="pc-timeline-end pc-timeline-box">iPhone</div>
									<hr />
								</li>
								<li>
									<hr />
									<div className="pc-timeline-start pc-timeline-box">
										Apple Watch
									</div>
									<div className="pc-timeline-middle">
										<svg
											xmlns="http://www.w3.org/2000/svg"
											viewBox="0 0 20 20"
											fill="currentColor"
											className="h-5 w-5"
										>
											<path
												fillRule="evenodd"
												d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.857-9.809a.75.75 0 00-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 10-1.06 1.061l2.5 2.5a.75.75 0 001.137-.089l4-5.5z"
												clipRule="evenodd"
											/>
										</svg>
									</div>
								</li>
							</ul>

							{/* Stat */}
							<div className="pc-stats shadow">
								<div className="pc-stat">
									<div className="pc-stat-figure text-primary">
										<svg
											xmlns="http://www.w3.org/2000/svg"
											fill="none"
											viewBox="0 0 24 24"
											className="inline-block h-8 w-8 stroke-current"
										>
											<path
												strokeLinecap="round"
												strokeLinejoin="round"
												strokeWidth="2"
												d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"
											></path>
										</svg>
									</div>
									<div className="pc-stat-title">Total Likes</div>
									<div className="pc-stat-value text-primary">25.6K</div>
									<div className="pc-stat-desc">21% more than last month</div>
								</div>

								<div className="pc-stat">
									<div className="pc-stat-figure text-secondary">
										<svg
											xmlns="http://www.w3.org/2000/svg"
											fill="none"
											viewBox="0 0 24 24"
											className="inline-block h-8 w-8 stroke-current"
										>
											<path
												strokeLinecap="round"
												strokeLinejoin="round"
												strokeWidth="2"
												d="M13 10V3L4 14h7v7l9-11h-7z"
											></path>
										</svg>
									</div>
									<div className="pc-stat-title">Page Views</div>
									<div className="pc-stat-value text-secondary">2.6M</div>
									<div className="pc-stat-desc">21% more than last month</div>
								</div>

								<div className="pc-stat">
									<div className="pc-stat-figure text-secondary">
										<div className="pc-avatar pc-online">
											<div className="w-16 rounded-full">
												<img src="https://img.daisyui.com/images/stock/photo-1534528741775-53994a69daeb.webp" />
											</div>
										</div>
									</div>
									<div className="pc-stat-value">86%</div>
									<div className="pc-stat-title">Tasks done</div>
									<div className="pc-stat-desc text-secondary">
										31 tasks remaining
									</div>
								</div>
							</div>

							{/* Chat Bubble */}
							<div>
								<div className="pc-chat pc-chat-start">
									<div className="pc-chat-image avatar">
										<div className="w-10 rounded-full overflow-hidden">
											<img
												className="w-full h-full object-cover"
												alt="Tailwind CSS chat bubble component"
												src="https://img.daisyui.com/images/stock/photo-1534528741775-53994a69daeb.webp"
											/>
										</div>
									</div>
									<div className="pc-chat-header">
										Obi-Wan Kenobi
										<time className="text-xs opacity-50">12:45</time>
									</div>
									<div className="pc-chat-bubble">You were the Chosen One!</div>
									<div className="pc-chat-footer opacity-50">Delivered</div>
								</div>
								<div className="pc-chat pc-chat-end">
									<div className="pc-chat-image avatar">
										<div className="w-10 rounded-full overflow-hidden">
											<img
												className="w-full h-full object-cover"
												alt="Tailwind CSS chat bubble component"
												src="https://img.daisyui.com/images/stock/photo-1534528741775-53994a69daeb.webp"
											/>
										</div>
									</div>
									<div className="pc-chat-header">
										Anakin
										<time className="text-xs opacity-50">12:46</time>
									</div>
									<div className="pc-chat-bubble">I hate you!</div>
									<div className="pc-chat-footer opacity-50">Seen at 12:46</div>
								</div>
							</div>

							{/* Avatar */}
							<div className="pc-avatar-group -space-x-6 rtl:space-x-reverse">
								<div className="pc-avatar">
									<div className="w-12">
										<img src="https://img.daisyui.com/images/stock/photo-1534528741775-53994a69daeb.webp" />
									</div>
								</div>
								<div className="pc-avatar">
									<div className="w-12">
										<img src="https://img.daisyui.com/images/stock/photo-1534528741775-53994a69daeb.webp" />
									</div>
								</div>
								<div className="pc-avatar">
									<div className="w-12">
										<img src="https://img.daisyui.com/images/stock/photo-1534528741775-53994a69daeb.webp" />
									</div>
								</div>
								<div className="pc-avatar pc-placeholder">
									<div className="bg-neutral text-neutral-content w-12">
										<span>+99</span>
									</div>
								</div>
							</div>
						</div>
					</div>
				</div>
			</div>
		</>
	)
}

export default memo(index)
