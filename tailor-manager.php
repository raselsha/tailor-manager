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
            add_action('init', array($this, 'load_textdomain'));
        }

        /**
         * Loads this plugin's own translations directly off TMR_Panel_Shell's
         * per-user language switcher instead of the site's global locale —
         * 'en' loads languages/tailor-manager-tmr_en.mo; 'bn' (the default for
         * every account that has never touched the switcher) points at a file
         * that's deliberately never shipped, so __()/_e() just fall through to
         * the original Bangla msgid unchanged, exactly like before this feature
         * existed. The locale slug is deliberately NOT 'en_US': this site's own
         * WordPress locale already IS en_US, and WordPress's built-in
         * just-in-time textdomain loader auto-discovers
         * languages/{domain}-{site-locale}.mo for any plugin that declares a
         * Domain Path — a real 'en_US.mo' file here would get silently loaded
         * for every account regardless of their own switcher choice, the exact
         * bug this custom slug avoids. Bypassing get_locale()/
         * load_plugin_textdomain() is deliberate too: this plugin's own strings
         * must follow its own switcher regardless of the site's locale or which
         * page is loading.
         */
        public function load_textdomain()
        {
            $locale = ('en' === TMR_Panel_Shell::current_ui_lang()) ? 'tmr_en' : 'tmr_bn';
            load_textdomain('tailor-manager', TMR_PLUGIN_PATH . 'languages/tailor-manager-' . $locale . '.mo');
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

            new TMR_Svg_Upload_Support();
            new TMR_Category_Taxonomy();
            new TMR_Dress_Post_Type();
            new TMR_Dress_Part_Post_Type();
            new TMR_Design_Type_Post_Type();
            new TMR_Customer_Post_Type();
            new TMR_Staff_Post_Type();
            new TMR_Order_Post_Type();
            new TMR_Order_Item_Post_Type();
            $staff_role = new TMR_Staff_Role();
            $staff_role->register();
            $manager_role = new TMR_Manager_Role();
            $manager_role->register();
            new TMR_Panel_Shell();
            new TMR_Dashboard_Panel();
            new TMR_Orders_Panel();
            new TMR_Customers_Panel();
            new TMR_Dress_Panel();
            new TMR_Dress_Part_Panel();
            new TMR_Design_Type_Panel();
            new TMR_Categories_Panel();
            new TMR_Measurement_Fields_Panel();
            new TMR_Staff_Panel();
            new TMR_Accounts_Report();
            new TMR_Settings_Page();
            new TMR_Profile_Panel();
            new TMR_My_Orders_Panel();
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
            $staff       = new TMR_Staff_Post_Type();
            $order       = new TMR_Order_Post_Type();
            $order_item  = new TMR_Order_Item_Post_Type();

            $taxonomy->register();
            $dress->register();
            $dress_part->register();
            $design_type->register();
            $customer->register();
            $staff->register();
            $order->register();
            $order_item->register();

            TMR_Demo_Data::maybe_seed();

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
