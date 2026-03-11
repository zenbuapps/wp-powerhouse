<?php

declare(strict_types=1);

namespace J7\Powerhouse\Compatibility\Services;

use J7\Powerhouse\Compatibility\Shared\MuPluginsLoader;

/**
 * DisableFeatures
 * 將 powerhouse-disable-features.php 檔案移動到 mu-plugins 目錄下
 * 為了安全性禁用功能
 */
final class DisableFeatures extends MuPluginsLoader {

	use \J7\WpUtils\Traits\SingletonTrait;

	/** @var string $file_name 要移動的檔案名稱 */
	protected string $file_name = 'powerhouse-disable-features.php';
}
