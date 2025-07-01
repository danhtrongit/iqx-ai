<?php
/**
 * Fired when the plugin is uninstalled.
 *
 * @since      1.0.0
 *
 * @package    IQX_AI
 */

// If uninstall not called from WordPress, then exit.
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Include necessary files
require_once plugin_dir_path(__FILE__) . 'includes/class-iqx-ai-loader.php';

// Run uninstall tasks
IQX_AI_Loader::uninstall(); 