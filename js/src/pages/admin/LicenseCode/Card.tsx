import { Tag, Button, Space, Input, Alert } from 'antd'
import dayjs from 'dayjs'
import { useState } from 'react'
import { useCustomMutation, useApiUrl, useInvalidate } from '@refinedev/core'

export type TLC = {
	code: string
	post_status:
		| 'available'
		| 'activated'
		| 'deactivated'
		| 'expired'
		| 'bound_elsewhere'
		| 'unauthorized'
		| ''
	expire_date: string | number
	type: string
	product_slug: string
	product_name: string
	link: string
	is_subscription?: boolean

	/** 保留授權碼、但 CloudServer 判定未授權時的原因（status_expired、bound_elsewhere…） */
	unauthorized_reason?: string
}

const LC_STATUS_MAPPER = [
	{
		post_status: '',
		color: 'default',
		label: '未啟用',
	},
	{
		post_status: 'available',
		color: 'blue',
		label: '可用',
	},
	{
		post_status: 'activated',
		color: 'green',
		label: '已啟用',
	},
	{
		post_status: 'deactivated',
		color: 'red',
		label: '已停用',
	},
	{
		post_status: 'expired',
		color: 'gray',
		label: '已過期',
	},
	{
		post_status: 'bound_elsewhere',
		color: 'orange',
		label: '已綁定其他網域',
	},
	{
		post_status: 'unauthorized',
		color: 'red',
		label: '未授權',
	},
]

/**
 * 保留授權碼、但目前未授權時顯示的說明
 * 授權碼仍存在本站，恢復後系統會自動重新啟用，不需要重新輸入
 */
const UNAUTHORIZED_MESSAGE_MAPPER: Record<string, string> = {
	expired: '授權碼已過期，續約後系統會自動恢復授權，不需要重新輸入授權碼',
	deactivated: '授權碼已停用，恢復後系統會自動重新啟用，不需要重新輸入授權碼',
	bound_elsewhere:
		'授權碼目前綁定在其他網域；若要改在本站使用，請先到該網域棄用授權，再按「重新啟用」',
	unauthorized: '授權碼目前無法在本站使用，恢復後系統會自動重新啟用',
}

const Card = ({ lc }: { lc: TLC }) => {
	const {
		code,
		post_status,
		expire_date,
		product_name,
		product_slug,
		link,
		is_subscription = false,
	} = lc

	const apiUrl = useApiUrl()
	const { mutate, isLoading } = useCustomMutation()
	const [inputCode, setInputCode] = useState('')
	const invalidate = useInvalidate()

	const { color, label } =
		LC_STATUS_MAPPER.find((item) => item.post_status === post_status) ||
		LC_STATUS_MAPPER[0]

	// 有授權碼但不是已啟用 → 授權碼被保留、目前未授權（過期／停用／綁定其他網域）
	const isUnauthorized = !!code && 'activated' !== post_status
	const unauthorizedMessage =
		UNAUTHORIZED_MESSAGE_MAPPER[post_status] ||
		UNAUTHORIZED_MESSAGE_MAPPER.unauthorized

	const onSuccess = () => {
		invalidate({
			resource: 'lc',
			invalidates: ['list'],
		})
	}

	const handleActivate = (activateCode: string) => {
		mutate(
			{
				url: `${apiUrl}/lc/activate`,
				method: 'post',
				values: {
					product_slug,
					code: activateCode,
				},
			},
			{
				onSuccess,
			}
		)
	}

	const handleDeactivate = () => {
		mutate(
			{
				url: `${apiUrl}/lc/deactivate`,
				method: 'post',
				values: {
					product_slug,
					code,
				},
			},
			{
				onSuccess,
			}
		)
	}

	return (
		<div
			key={product_slug}
			className="bg-white p-4 rounded-lg hover:shadow-md transition-all duration-300 w-[24rem]"
		>
			<div className="flex justify-between items-center">
				<h2 className="text-lg font-bold mt-0 mb-2">{product_name} 授權</h2>
				{!!link && (
					<Button
						href={link}
						size="small"
						type="primary"
						className="text-white"
					>
						購買授權
					</Button>
				)}
			</div>
			<div className="grid grid-cols-[6rem_1fr] text-sm text-gray-500 text-mono [&>div]:h-6">
				<div>狀態</div>
				<div className="text-right">
					<Tag className="m-0" color={color}>
						{label}
					</Tag>
				</div>
				<div>授權種類</div>
				<div className="text-right">{is_subscription ? '訂閱' : '一次性'}</div>
				<div>到期日</div>
				<div className="text-right">{getExpireDateLabel(expire_date)}</div>
				<div>授權碼</div>
				<div className="text-right">{code}</div>
			</div>
			{isUnauthorized && (
				<>
					<Alert
						className="mt-4"
						type="warning"
						showIcon
						message={unauthorizedMessage}
					/>
					<div className="mt-4 grid grid-cols-2 gap-2">
						<Button
							size="small"
							type="primary"
							loading={isLoading}
							onClick={() => handleActivate(code)}
						>
							重新啟用
						</Button>
						<Button
							size="small"
							type="default"
							danger
							loading={isLoading}
							onClick={handleDeactivate}
						>
							棄用授權
						</Button>
					</div>
				</>
			)}
			{!!code && !isUnauthorized && (
				<Button
					size="small"
					type="default"
					danger
					className="mt-4 w-full"
					loading={isLoading}
					onClick={handleDeactivate}
				>
					棄用授權
				</Button>
			)}
			{!code && (
				<Space.Compact block className="mt-4">
					<Input
						placeholder="請輸入授權碼 xxxxxx-xxxxxx-xxxxxx-xxxxxx"
						allowClear
						size="small"
						value={inputCode}
						disabled={isLoading}
						onChange={(e) => setInputCode(e.target.value)}
					/>
					<Button
						size="small"
						type="primary"
						className="text-xs px-3"
						loading={isLoading}
						onClick={() => handleActivate(inputCode)}
					>
						啟用
					</Button>
				</Space.Compact>
			)}
		</div>
	)
}

export default Card

function getExpireDateLabel(expire_date: string | number) {
	if (0 === expire_date) {
		return '無限期'
	}

	if (typeof expire_date === 'number') {
		return dayjs.unix(expire_date).format('YYYY-MM-DD')
	}

	return ''
}
