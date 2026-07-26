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
            spl_autoload_register(array(__CLASS__, 'autoload'));

            new TMR_Category_Taxonomy();
            new TMR_Dress_Post_Type();
            new TMR_Dress_Part_Post_Type();
            new TMR_Design_Type_Post_Type();
            new TMR_Customer_Post_Type();
            new TMR_Order_Post_Type();
            new TMR_Order_Item_Post_Type();
            new TMR_Panel_Shell();
            new TMR_Dashboard_Panel();
            new TMR_Orders_Panel();
            new TMR_Customers_Panel();
            new TMR_Dress_Panel();
            new TMR_Dress_Part_Panel();
            new TMR_Design_Type_Panel();
            new TMR_Accounts_Report();
            new TMR_Settings_Page();
            new TMR_Print_Slips();
        }

        public static function autoload($class)
        {
            if (strpos($class, 'TMR_') !== 0) {
                return;
            }

            $file = TMR_PLUGIN_PATH . 'classes/class-' . strtolower(str_replace('_', '-', $class)) . '.php';

            if (file_exists($file)) {
                require_once $file;
            }
        }

        public static function activate()
        {
            $taxonomy    = new TMR_Category_Taxonomy();
            $dress       = new TMR_Dress_Post_Type();
            $dress_part  = new TMR_Dress_Part_Post_Type();
            $design_type = new TMR_Design_Type_Post_Type();
            $customer    = new TMR_Customer_Post_Type();
            $order       = new TMR_Order_Post_Type();
            $order_item  = new TMR_Order_Item_Post_Type();

            $taxonomy->register();
            $dress->register();
            $dress_part->register();
            $design_type->register();
            $customer->register();
            $order->register();
            $order_item->register();

            $taxonomy->maybe_seed_default_terms();
            TMR_Measurement_Fields::maybe_seed_defaults();

            flush_rewrite_rules();
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
