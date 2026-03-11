<?php

declare(strict_types=1);

namespace J7\Powerhouse\Captcha\Core;

use Gregwar\Captcha\CaptchaBuilder;
use Gregwar\Captcha\PhraseBuilder;

/**
 * Class 基類抽象
 */
abstract class Base {

	/** @var string 驗證碼欄位名稱 */
	protected $captcha_name = 'powerhouse_captcha';

	/** @var string 驗證碼 session 鍵名 */
	protected $session_key = 'powerhouse_phrase';

	/** @var int 驗證碼長度 */
	protected $length = 4;

	/** @var string 驗證碼字符集不支援中文、特殊字符 */
	protected $charset = '0123456789';

	/** @var string 驗證碼 */
	protected $phrase;

	/** @var CaptchaBuilder 實例 */
	protected $builder;

	/** @var PhraseBuilder 實例 */
	protected $phrase_builder;

	/** @var string 驗證碼 nonce 名稱 */
	protected $nonce_action = 'powerhouse_captcha_nonce';

	/** @var string 驗證碼容器 class 用來區分是 login 還是 register */
	protected string $container_class = 'login';

	/** Constructor */
	public function __construct() {
		\add_action('init', [ $this, 'session_start' ], 1);
		\add_action('wp_enqueue_scripts', [ $this, 'enqueue_block_ui' ]);
		\add_action('login_enqueue_scripts', [ $this, 'enqueue_block_ui' ]);
		\add_action('admin_enqueue_scripts', [ $this, 'enqueue_block_ui' ]);

		// ajax
		\add_action('wp_ajax_nopriv_get_captcha', [ $this, 'generate_captcha' ]);
	}

	/** 註冊腳本 */
	public function enqueue_block_ui(): void {
		// 先確認是否已經註冊
		if (!wp_script_is('jquery-blockui', 'registered')) {
			$blockui_path = \untrailingslashit(\site_url() . '/wp-content/plugins/woocommerce/assets/js/jquery-blockui/jquery.blockUI.min.js');
			\wp_register_script('jquery-blockui', $blockui_path, [ 'jquery' ], '2.70', true);
		}
		\wp_enqueue_script('jquery-blockui');
	}

	/** 開啟 session 支持 */
	public function session_start(): void {
		if (session_status() == PHP_SESSION_NONE) {
			session_start();
		}
	}

	/** 渲染驗證碼欄位 */
	public function render_captcha_field(): void {
		$name = $this->captcha_name;
		// render
		printf(
		/*html*/'<div class="captcha_container %1$s" style="display: %2$s;">
		',
		$this->container_class,
		$this->container_class === 'login' ? 'none' : 'block'
		);
		printf(/*html*/'<label for="%s">驗證碼</label>', $name);
		printf(
			/*html*/'
					<div>
							<div style="display: flex; align-items: end; gap: 10px; margin-bottom: 10px;">
								<img class="captcha-img" src="" />
								<span class="refresh-captcha" style="cursor: pointer;">換一組</span>
							</div>
							<input type="text" name="%1$s" class="input input-text" />
					</div>
					',
			$name
			);
		echo '</div>';

		?>
				<script type="module" defer>
					(function($){
						$(document).ready(function(){

							class Core{
								type = '<?php echo $this->container_class; ?>'; // login | register
								ajaxUrl = '<?php echo \admin_url('admin-ajax.php'); ?>';
								ajaxNonce = '<?php echo \wp_create_nonce($this->nonce_action); ?>';
								$container; // 容器 jQuery 實例
								$img; // 圖片 jQuery 實例
								$refresh; // 刷新按鈕 jQuery 實例
								$form; // 表單 jQuery 實例
								$formRenderer; // 表單渲染器 jQuery 實例
								shouldBlock = true;// 是否需要阻擋提交

								constructor(){
									this.$container = $(`.captcha_container.${this.type}`);
									this.$img = this.$container.find('.captcha-img');
									this.$refresh = this.$container.find('.refresh-captcha');
									this.$form = this.$container.closest('form');
									this.$formRenderer = new Renderer(this.$form);

									this.$refresh.on('click', () => this.getCaptcha());
									if(this.type === 'login'){
										this.blockSubmit();
									}else{
										this.getCaptcha()
									}
								}

								/** AJAX 取得驗證碼 */
								getCaptcha = () => {
									this.$formRenderer.isLoading = true;
									$.ajax({
										url: this.ajaxUrl,
										type: 'POST',
										data: {
											action: 'get_captcha',
											nonce: this.ajaxNonce,
										},
										success: (response) => {
											if(!response?.success){
												alert(`獲取驗證碼生成失敗: ${response?.data}`);
												return;
											}
											this.$img.attr('src', response?.data);
											this.$container.show();
											this.shouldBlock = false;
										},
										error: (response) => {
											alert(`獲取驗證碼生成失敗: ${response?.data}`);
											return;
										},
										complete: () => {
											this.$formRenderer.isLoading = false;
										},
									});
								}

								/** 判斷用戶是否為需要驗證的腳色 */
								needCaptcha = () => {
									this.$formRenderer.isLoading = true;
									// wp-admin & woocommerce 的 username 欄位名稱不同
									const $username = this.$form.find('#user_login, input[name="username"]').first();
									$.ajax({
										url: this.ajaxUrl,
										type: 'POST',
										data: {
											action: 'need_captcha',
											nonce: this.ajaxNonce,
											username: $username.val(),
											pathname: window.location.pathname,
										},
										success: (response) => {
											// 如果不需要驗證就表單提交
											// 檢查 response.data 是否為 true
											if(response?.data !== true || !response?.success){
												this.shouldBlock = response?.data !== false && response?.success === true
												this.submitForm(this.$form[0])
												return;
											}
											// 如果需要就取得授權碼
											this.getCaptcha()
										},
										error: (response) => {
											console.error(response?.data)
											return;
										},
										complete: () => {
											this.$formRenderer.isLoading = false;
										},
									});
								}

								/** 阻擋表單提交 */
								blockSubmit = () => {
									this.$form.on('submit', (e) => {
										if(this.shouldBlock){
											e.preventDefault();
											e.stopPropagation();
											this.needCaptcha()
											return;
										}
									});
								}


								/** 提交表單 */
								submitForm = () => {
									const submitButton = $(this.$form[0]).find('[type="submit"]')
									if(submitButton?.length){
										submitButton.click()
									}else{
										this.$form[0].submit()
									}
								}
							}

						/** UI renderer */
						class Renderer{
							$blockEl; // 要 block 的 element jQuery 實例
							_isLoading = false; // 是否正在載入
							defaultBlockUIProps = {
								css: {
									border: 'none',
									backgroundColor: 'transparent',
									color: '#999',
								},
								overlayCSS:  {
									backgroundColor: 'transparent',
									opacity:         0,
									cursor:          'wait'
								},
								message: `<svg version="1.1" id="loader-1" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" x="0px" y="0px"
								width="40px" height="40px" viewBox="0 0 50 50" style="enable-background:new 0 0 50 50;" xml:space="preserve">
								<path fill="#999" d="M25.251,6.461c-10.318,0-18.683,8.365-18.683,18.683h4.068c0-8.071,6.543-14.615,14.615-14.615V6.461z">
								<animateTransform attributeType="xml"
									attributeName="transform"
									type="rotate"
									from="0 25 25"
									to="360 25 25"
									dur="0.6s"
									repeatCount="indefinite"/>
								</path>
								</svg>`
							}

							/** Constructor */
							constructor($blockEl){
								this.$blockEl = $blockEl;
							}

							get isLoading(){
								return this._isLoading;
							}

							set isLoading(isLoading){
								try {
									this._isLoading = isLoading;
									if(isLoading){
										this.$blockEl?.block?.(this.defaultBlockUIProps);
									}else{
											this.$blockEl?.unblock?.();
									}
								} catch (error) {
									console.error(error);
								}
							}
						}

						new Core();

						});
					})(jQuery);
				</script>
		<?php
	}

	/** AJAX 生成驗證碼 */
	public function generate_captcha(): void {

		$this->init();

		\wp_send_json(
			[
				'success' => true,
				'data' => $_SESSION[ "{$this->session_key}_url" ], // phpcs:ignore
			]
			);
	}

	/**
	 * 測試驗證碼
	 *
	 * @param string $user_input 用戶輸入的驗證碼
	 * @return bool
	 */
	protected function test_phrase( string $user_input ): bool {
		if (!isset($_SESSION[ $this->session_key ])) {
			return false;
		}
		return $user_input == $_SESSION[ $this->session_key ]; // phpcs:ignore
	}

	/** 初始化 */
	private function init(): void {
		$this->phrase_builder = new PhraseBuilder($this->length, $this->charset);
		$this->builder        = new CaptchaBuilder(null, $this->phrase_builder);
		$this->builder->setInterpolation(false); // 更快、圖更醜
		$this->builder->build(); // 生成圖片

		// $builder->save('test/out.jpg');  // 保存圖片，從網站跟目錄開始的 folder，目錄必須存在

		// 存入 session
		$_SESSION[ $this->session_key ]         = $this->builder->getPhrase();
		$_SESSION[ "{$this->session_key}_url" ] = $this->builder->inline();
	}
}
