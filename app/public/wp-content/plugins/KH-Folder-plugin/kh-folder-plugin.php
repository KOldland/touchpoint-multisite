<?php
/**
 * Plugin Name: KH Folder Plugin
 * Description: Bootstrap for folder management architecture inside the KH CMS suite.
 * Version: 0.1.0
 * Author: KH Team
 * Text Domain: kh-folders
 */

if (! defined('ABSPATH')) {
    exit;
}

define('KH_FOLDERS_FILE', __FILE__);
define('KH_FOLDERS_PATH', plugin_dir_path(__FILE__));
define('KH_FOLDERS_URL', plugin_dir_url(__FILE__));
define('KH_FOLDERS_VERSION', '0.1.0');

require_once KH_FOLDERS_PATH . 'includes/Autoloader.php';

$autoloader = new KHFolders\Core\Autoloader('KHFolders', KH_FOLDERS_PATH . 'includes');
$autoloader->register();

function kh_folders_bootstrap()
{
    $plugin = KHFolders\Core\Plugin::instance();
    $plugin->registry()->add(new KHFolders\Modules\PermissionsModule());
    $plugin->registry()->add(new KHFolders\Modules\TaxonomyModule());
    $plugin->registry()->add(new KHFolders\Modules\AjaxModule());
    $plugin->registry()->add(new KHFolders\Modules\AssetsModule());
    $plugin->registry()->add(new KHFolders\Modules\ListTableModule());
    $plugin->registry()->add(new KHFolders\Modules\ImportExportModule());
    $plugin->registry()->add(new KHFolders\Modules\MediaModule());
    $plugin->registry()->add(new KHFolders\Modules\AdminModule());
    $plugin->boot();
}

add_action('plugins_loaded', 'kh_folders_bootstrap');
