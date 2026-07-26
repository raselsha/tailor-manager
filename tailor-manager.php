<?php
/**
 * Plugin Name: Tailor Manager – Measurement, Orders & Delivery
 * Plugin URI: https://lieusoft.com/plugins/tailor-manager/
 * Description: Complete tailor shop management solution for WordPress. Manage customers, measurements, dress orders, payments, invoices, trials, and deliveries from one dashboard.
 * Version: 1.0.0
 * Stable Tag: trunk
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Author: Shahadat Hossain
 * Author URI: https://lieusoft.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: tailor-manager
 * Domain Path: /languages
 */

defined('ABSPATH') || exit;

if (!class_exists('TMR_Tailor_Manager')) {
    class TMR_Tailor_Manager
    {
        public function __construct()
        {
            $this->define_constants();
            $this->include_plugin_files();
        }

        public function define_constants()
        {
            define('TMR_PLUGIN_PATH', plugin_dir_path(__FILE__));
            define('TMR_PLUGIN_URL', plugin_dir_url(__FILE__));
            define('TMR_VERSION', '1.0.0');
        }

        public static function include_plugin_files()
        {
        }

        public static function activate()
        {
            update_option('rewrite_rules', '');
        }

        public static function deactivate()
        {
            flush_rewrite_rules();
        }

        public static function uninstall()
        {
        }
    }
}

if (class_exists('TMR_Tailor_Manager')) {
    register_activation_hook(__FILE__, array('TMR_Tailor_Manager', 'activate'));
    register_deactivation_hook(__FILE__, array('TMR_Tailor_Manager', 'deactivate'));
    register_uninstall_hook(__FILE__, array('TMR_Tailor_Manager', 'uninstall'));
    new TMR_Tailor_Manager();
}
