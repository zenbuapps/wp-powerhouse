<?php

declare(strict_types=1);

namespace J7\Powerhouse\Domains\Plugin\Model;

use J7\WpUtils\Classes\DTO;

/**
 * Plugin DTO
 * get_plugins 的結果會是 [file_path => plugin_data]
 * plugin_data 資料結構就是 Plugin DTO 的 array
 *  */
final class Plugin extends DTO {

	/** @var string $name 外掛名稱 */
	public string $name;

	/** @var string $title 外掛標題 */
	public string $title;

	/** @var string $plugin_uri 外掛網址 */
	public string $plugin_uri;

	/** @var string $version 版本號 */
	public string $version;

	/** @var string $description 外掛描述 */
	public string $description;

	/** @var string $author 作者名稱 */
	public string $author;

	/** @var string $author_uri 作者網址 */
	public string $author_uri;

	/** @var string $text_domain 文字域 */
	public string $text_domain;

	/** @var string $domain_path 語言檔案路徑 */
	public string $domain_path;

	/** @var bool $network 是否為網路啟用外掛 */
	public bool $network = true;

	/** @var string $requires_wp WordPress 版本需求 */
	public string $requires_wp;

	/** @var string $requires_php PHP 版本需求 */
	public string $requires_php;

	/** @var string $update_uri 更新網址 */
	public string $update_uri;

	/** @var string $requires_plugins 相依外掛需求 */
	public string $requires_plugins;

	/** @var string $author_name 作者名稱 */
	public string $author_name;

	// ----- ▼ 以下為自己擴充的屬性 ----- //

	/** @var string $key 入口檔案名稱 & 檔名 */
	public string $key;

	/** @var bool $is_active 是否啟用 */
	public bool $is_active;

	/**
	 * 取得實例
	 *
	 * @param array<string, mixed> $plugin_data 插件資料
	 */
	public static function instance( array $plugin_data ): self {
		$args = [
			'name'             => $plugin_data['Name'],
			'plugin_uri'       => $plugin_data['PluginURI'],
			'version'          => $plugin_data['Version'],
			'description'      => $plugin_data['Description'],
			'author'           => $plugin_data['Author'],
			'author_uri'       => $plugin_data['AuthorURI'],
			'text_domain'      => $plugin_data['TextDomain'],
			'domain_path'      => $plugin_data['DomainPath'],
			'network'          => $plugin_data['Network'],
			'requires_wp'      => $plugin_data['RequiresWP'],
			'requires_php'     => $plugin_data['RequiresPHP'],
			'update_uri'       => $plugin_data['UpdateURI'],
			'requires_plugins' => $plugin_data['RequiresPlugins'],
			'title'            => $plugin_data['Title'],
			'author_name'      => $plugin_data['AuthorName'],
			'key'              => $plugin_data['key'],
			'is_active'        => $plugin_data['is_active'],
		];

		$instance = new self($args);
		return $instance;
	}
}
