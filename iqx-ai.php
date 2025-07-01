<?php
/**
 * Plugin Name:       IQX AI
 * Plugin URI:        https://iqx.vn/
 * Description:       Tự động lấy dữ liệu bài viết của trang khác và viết lại chuẩn SEO, phân tích chuyên sâu hơn.
 * Version:           1.0.0
 * Author:            IQX Team
 * Author URI:        https://iqx.vn/
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       iqx-ai
 * Domain Path:       /languages
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

/**
 * Define constants.
 */
define('IQX_AI_VERSION', '1.0.0');
define('IQX_AI_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('IQX_AI_PLUGIN_URL', plugin_dir_url(__FILE__));
define('IQX_AI_API_URL', 'https://api.yescale.io');

/**
 * Include required files
 */
require_once IQX_AI_PLUGIN_DIR . 'includes/class-iqx-ai-db.php';
require_once IQX_AI_PLUGIN_DIR . 'includes/class-iqx-ai-api.php';
require_once IQX_AI_PLUGIN_DIR . 'includes/class-iqx-ai-scraper.php';
require_once IQX_AI_PLUGIN_DIR . 'includes/class-iqx-ai-admin.php';
require_once IQX_AI_PLUGIN_DIR . 'includes/class-iqx-ai-loader.php';

/**
 * Activation and deactivation hooks
 */
register_activation_hook(__FILE__, array('IQX_AI_Loader', 'activate'));
register_deactivation_hook(__FILE__, array('IQX_AI_Loader', 'deactivate'));

/**
 * Register an uninstall hook
 */
register_uninstall_hook(__FILE__, array('IQX_AI_Loader', 'uninstall'));

/**
 * Monitor for setting changes
 */
add_action('update_option_iqx_ai_settings', array('IQX_AI_Loader', 'update_cron_schedule'), 10, 2);

/**
 * Run the scraper via cron
 */
add_action('iqx_ai_scrape_cron', 'iqx_ai_run_scraper');

/**
 * Run the scraper
 */
function iqx_ai_run_scraper() {
    $scraper = new IQX_AI_Scraper();
    $scraper->run();
}

/**
 * Begins execution of the plugin.
 */
function run_iqx_ai() {
    // Load admin interface
    if (is_admin()) {
        $admin = new IQX_AI_Admin();
        $admin->init();
    }
}

// Start the plugin
run_iqx_ai();