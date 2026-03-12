/**
 * E2E 測試資料常數
 *
 * 所有測試共用的資料定義。帳密從環境變數讀取，其餘為固定常數。
 */

/** WordPress Admin 帳密 */
export const WP_ADMIN = {
	username: process.env.WP_ADMIN_USERNAME || 'admin',
	password: process.env.WP_ADMIN_PASSWORD || 'password',
}

/** 測試用訂閱者帳號 */
export const TEST_SUBSCRIBER = {
	username: 'e2e_ph_subscriber',
	password: 'e2e_ph_subscriber_pass',
	email: 'e2e_ph_subscriber@test.local',
	firstName: '測試',
	lastName: '訂閱者',
	displayName: '測試訂閱者',
}

/** 測試用 Shop Manager 帳號 */
export const TEST_SHOP_MANAGER = {
	username: 'e2e_ph_shop_manager',
	password: 'e2e_ph_shop_manager_pass',
	email: 'e2e_ph_shop_manager@test.local',
	firstName: '測試',
	lastName: '店長',
	displayName: '測試店長',
}

/** 測試文章資料 */
export const TEST_POST = {
	title: 'E2E 測試文章',
	content: '這是一篇 E2E 測試用的文章。',
	type: 'post',
}

/** 測試商品資料 */
export const TEST_PRODUCT = {
	name: 'E2E 測試商品',
	regularPrice: '1000',
	salePrice: '800',
}

/** 測試可變商品 */
export const TEST_VARIABLE_PRODUCT = {
	name: 'E2E 可變測試商品',
	type: 'variable',
}

/** 測試訂單資料 */
export const TEST_ORDER = {
	status: 'wc-processing',
}

/** 測試授權碼資料 (LC bypass 下不會實際啟用) */
export const TEST_LICENSE_CODE = {
	code: 'E2E-TEST-LC-001',
	productSlug: 'power-course',
}

/** 常用 URL 路徑 */
export const URLS = {
	adminDashboard: '/wp-admin/',
	adminPlugins: '/wp-admin/plugins.php',
	adminPowerhouse: '/wp-admin/admin.php?page=powerhouse',
	loginPage: '/wp-login.php',
	shop: '/shop/',
	myAccount: '/my-account/',
	cart: '/cart/',
	checkout: '/checkout/',
}

/** Powerhouse REST API 端點 (base: /wp-json/v2/powerhouse) */
export const API = {
	// Post
	posts: 'v2/powerhouse/posts',
	postById: (id: number) => `v2/powerhouse/posts/${id}`,
	postField: (id: number, field: string) => `v2/powerhouse/posts/${id}/field/${field}`,
	postSort: 'v2/powerhouse/posts/sort',

	// User
	users: 'v2/powerhouse/users',
	userById: (id: number) => `v2/powerhouse/users/${id}`,
	userOptions: 'v2/powerhouse/users/options',
	resetPassword: 'v2/powerhouse/users/resetpassword',

	// Option
	options: 'v2/powerhouse/options',

	// License Code
	lc: 'v2/powerhouse/lc',
	lcActivate: 'v2/powerhouse/lc/activate',
	lcDeactivate: 'v2/powerhouse/lc/deactivate',
	lcInvalidate: 'v2/powerhouse/lc/invalidate',

	// Upload
	upload: 'v2/powerhouse/upload',
	uploadOptions: 'v2/powerhouse/upload/options',

	// Shortcode
	shortcode: 'v2/powerhouse/shortcode',

	// Plugin
	plugins: 'v2/powerhouse/plugins',

	// Comment
	comments: 'v2/powerhouse/comments',
	commentById: (id: number) => `v2/powerhouse/comments/${id}`,

	// Term
	terms: (taxonomy: string) => `v2/powerhouse/terms/${taxonomy}`,
	termById: (taxonomy: string, id: number) => `v2/powerhouse/terms/${taxonomy}/${id}`,
	termSort: (taxonomy: string) => `v2/powerhouse/terms/${taxonomy}/sort`,

	// Product (WooCommerce)
	products: 'v2/powerhouse/products',
	productById: (id: number) => `v2/powerhouse/products/${id}`,
	productSelect: 'v2/powerhouse/products/select',
	productOptions: 'v2/powerhouse/products/options',
	productAttributes: (id: number) => `v2/powerhouse/products/attributes/${id}`,
	productCreateVariations: (id: number) => `v2/powerhouse/products/create-variations/${id}`,
	productUpdateVariations: (id: number) => `v2/powerhouse/products/update-variations/${id}`,
	productBindItems: 'v2/powerhouse/products/bind-items',
	productUpdateBoundItems: 'v2/powerhouse/products/update-bound-items',
	productUnbindItems: 'v2/powerhouse/products/unbind-items',

	// Product Attribute (Global)
	productAttributesList: 'v2/powerhouse/product-attributes',
	productAttributeById: (id: number) => `v2/powerhouse/product-attributes/${id}`,

	// Order (WooCommerce)
	orders: 'v2/powerhouse/orders',
	orderById: (id: number) => `v2/powerhouse/orders/${id}`,
	orderOptions: 'v2/powerhouse/orders/options',
	orderNotes: 'v2/powerhouse/order-notes',
	orderNoteById: (id: number) => `v2/powerhouse/order-notes/${id}`,

	// Copy
	copy: (id: number) => `v2/powerhouse/copy/${id}`,

	// Limit (Access Control)
	limitGrantUsers: 'v2/powerhouse/limit/grant-users',
	limitUpdateUsers: 'v2/powerhouse/limit/update-users',
	limitRevokeUsers: 'v2/powerhouse/limit/revoke-users',

	// Report
	revenueStats: 'v2/powerhouse/reports/revenue/stats',

	// WooCommerce info
	woocommerce: 'v2/powerhouse/woocommerce',
}

/** WooCommerce REST API (v3) */
export const WC_API = {
	products: 'wc/v3/products',
	productById: (id: number) => `wc/v3/products/${id}`,
	orders: 'wc/v3/orders',
	orderById: (id: number) => `wc/v3/orders/${id}`,
}

/** WordPress REST API (v2) */
export const WP_API = {
	posts: 'wp/v2/posts',
	postById: (id: number) => `wp/v2/posts/${id}`,
	pages: 'wp/v2/pages',
	users: 'wp/v2/users',
	settings: 'wp/v2/settings',
}

/** 邊界值測試資料 */
export const EDGE_CASES = {
	xssPayload: '<script>alert(1)</script>',
	sqlInjection: "'; DROP TABLE wp_posts; --",
	unicodeEmoji: '🎓 E2E 測試 🚀',
	longString: 'A'.repeat(1000),
	nonExistentId: 999999,
	negativeId: -1,
	zeroPrice: '0',
	negativePrice: '-100',
	maxIntPrice: '2147483647',
	emptyString: '',
	specialChars: '!@#$%^&*()_+-=[]{}|;:,.<>?',
}

/** Timeout 常數 */
export const TIMEOUTS = {
	spaLoad: 15_000,
	apiResponse: 10_000,
	fileUpload: 30_000,
	pageNavigation: 15_000,
}
