<?php
/**
 * The loader functionality of the plugin.
 *
 * @since      1.0.0
 *
 * @package    IQX_AI
 * @subpackage IQX_AI/includes
 */

class IQX_AI_Loader {

    /**
     * Initialize the class and set its properties.
     *
     * @since    1.0.0
     */
    public function __construct() {
        // Add any initialization code here
    }

    /**
     * Handle plugin activation tasks
     *
     * @since    1.0.0
     */
    public static function activate() {
        // Create necessary database tables
        $db = new IQX_AI_DB();
        $db->create_tables();
        
        // Schedule cron job if not already scheduled
        if (!wp_next_scheduled('iqx_ai_scrape_cron')) {
            wp_schedule_event(time(), 'hourly', 'iqx_ai_scrape_cron');
        }
        
        // Create required directories
        if (!file_exists(IQX_AI_PLUGIN_DIR . 'logs')) {
            mkdir(IQX_AI_PLUGIN_DIR . 'logs', 0755, true);
        }
        
        // Set default settings
        if (!get_option('iqx_ai_settings')) {
            update_option('iqx_ai_settings', array(
                'enable_scraping' => '0',
                'scraping_frequency' => 'hourly',
                'scraping_limit' => '5',
                'auto_publish' => '0',
                'post_status' => 'draft',
                'model' => 'gpt-4o'
            ));
        }
    }

    /**
     * Handle plugin deactivation tasks
     *
     * @since    1.0.0
     */
    public static function deactivate() {
        // Clear scheduled hooks
        $timestamp = wp_next_scheduled('iqx_ai_scrape_cron');
        wp_unschedule_event($timestamp, 'iqx_ai_scrape_cron');
    }

    /**
     * Handle plugin uninstall tasks
     *
     * @since    1.0.0
     */
    public static function uninstall() {
        // Remove plugin options
        delete_option('iqx_ai_settings');
        
        // Remove database tables
        global $wpdb;
        $table_name = $wpdb->prefix . 'iqx_ai_articles';
        $wpdb->query("DROP TABLE IF EXISTS $table_name");
        
        // Remove log files
        $log_dir = IQX_AI_PLUGIN_DIR . 'logs';
        if (file_exists($log_dir)) {
            $files = glob($log_dir . '/*');
            foreach ($files as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
            rmdir($log_dir);
        }
    }

    /**
     * Update the cron schedule if frequency setting has changed
     *
     * @since    1.0.0
     * @param    array    $old_value    The old option value
     * @param    array    $new_value    The new option value
     */
    public static function update_cron_schedule($old_value, $new_value) {
        // Check if scraping frequency has changed
        if (
            isset($old_value['scraping_frequency'], $new_value['scraping_frequency']) && 
            $old_value['scraping_frequency'] !== $new_value['scraping_frequency']
        ) {
            // Clear the old schedule
            $timestamp = wp_next_scheduled('iqx_ai_scrape_cron');
            wp_unschedule_event($timestamp, 'iqx_ai_scrape_cron');
            
            // Set the new schedule
            wp_schedule_event(time(), $new_value['scraping_frequency'], 'iqx_ai_scrape_cron');
        }
        
        // Check if scraping was enabled
        if (
            (!isset($old_value['enable_scraping']) || $old_value['enable_scraping'] !== '1') &&
            isset($new_value['enable_scraping']) && $new_value['enable_scraping'] === '1'
        ) {
            // Make sure cron is scheduled
            if (!wp_next_scheduled('iqx_ai_scrape_cron')) {
                $frequency = isset($new_value['scraping_frequency']) ? $new_value['scraping_frequency'] : 'hourly';
                wp_schedule_event(time(), $frequency, 'iqx_ai_scrape_cron');
            }
        }
        
        // Check if scraping was disabled
        if (
            isset($old_value['enable_scraping']) && $old_value['enable_scraping'] === '1' &&
            (!isset($new_value['enable_scraping']) || $new_value['enable_scraping'] !== '1')
        ) {
            // Clear the schedule
            $timestamp = wp_next_scheduled('iqx_ai_scrape_cron');
            wp_unschedule_event($timestamp, 'iqx_ai_scrape_cron');
        }
    }
} 