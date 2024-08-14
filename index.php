<?php
/**
 * Plugin Name: Outbound Link Tracker
 * Description: Tracks and analyzes outbound link clicks
 * Version: 1.0.0
 * Author: EPIC WP Solutions
 * Author URI: https://www.epicwpsolutions.com
 * License: GPL-2.0+
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: olt
 */

define('OLT_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('OLT_PLUGIN_URL', plugin_dir_url(__FILE__));
define('OLT_PLUGIN_FILE', __FILE__);
define('OLT_PLUGIN_VERSION', '1.0.0');
define('OLT_PLUGIN_UPDATE_URL', 'https://www.epicwpsolutions.com/wp-update-server/?action=get_metadata&slug=wp-outbound-link-tracker');

// Load composer
require_once OLT_PLUGIN_DIR . 'vendor/autoload.php';

class Outbound_Link_Tracking_Plugin {

    private static $instance;

    private function __construct() {

        $this->init_acf();

        register_activation_hook(__FILE__, [$this, 'plugin_activation']);

        include OLT_PLUGIN_DIR . 'includes/updates.php';
        include OLT_PLUGIN_DIR . 'includes/link-tracking.php';
    }

    public function init_acf() {

        if (!function_exists('is_plugin_active')) {
            include_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        
        // Check if ACF PRO is active
        if (is_plugin_active('advanced-custom-fields-pro/acf.php')) {
            return;
        }

        if (defined('MY_ACF_PATH')) {
            return;
        }

        define('MY_ACF_PATH', OLT_PLUGIN_DIR . 'includes/acf/');
        define('MY_ACF_URL', OLT_PLUGIN_URL . 'includes/acf/');

        // Include the ACF plugin.
        include_once(MY_ACF_PATH . 'acf.php');

        add_filter('acf/settings/url', [$this, 'set_acf_settings_url']);
    }

    public function plugin_activation() {

        // Register db tables
        $this->register_custom_db_tables();

        // Flush permalinks
        flush_rewrite_rules();
    }

    public function register_custom_db_tables() {
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    
        global $wpdb;
        $table_name = $wpdb->prefix . 'tracked_link_redirects';
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE $table_name (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            link_id BIGINT(20) UNSIGNED NOT NULL,
            date DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL,
            os VARCHAR(255) DEFAULT NULL,
            browser VARCHAR(255) DEFAULT NULL,
            PRIMARY KEY (id),
            KEY link_id (link_id)
        ) $charset_collate;";
        
        dbDelta($sql);
    }

    public function set_acf_settings_url($url) {
        return MY_ACF_URL;
    }

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    } 

}

Outbound_Link_Tracking_Plugin::get_instance();

