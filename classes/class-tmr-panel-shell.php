<?php
defined('ABSPATH') || exit;

/**
 * Registers the "Tailor Panel" menu structure and renders a custom top bar + sidebar shell
 * around every screen, independent of wp-admin's own chrome — so it reads as a dedicated
 * system rather than "a WordPress dashboard." Core wp-admin menu items are left in place
 * (not removed) — only visually collapsed via CSS while on our own pages, which is fully
 * reversible.
 */
class TMR_Panel_Shell
{
    const CAPABILITY = 'manage_options';

    /** @var array<string,array{slug:string,title:string,icon:string}> */
    public static $nav = array();

    public function __construct()
    {
        self::$nav = array(
            'dashboard'   => array('slug' => 'tmr-panel', 'title' => __('Dashboard', 'tailor-manager'), 'icon' => 'dashicons-dashboard'),
            'orders'      => array('slug' => 'tmr-orders', 'title' => __('Orders', 'tailor-manager'), 'icon' => 'dashicons-grid-view'),
            'customers'   => array('slug' => 'tmr-customers', 'title' => __('Customers', 'tailor-manager'), 'icon' => 'dashicons-groups'),
            'dress'       => array('slug' => 'tmr-dress', 'title' => __('Dress', 'tailor-manager'), 'icon' => 'dashicons-admin-appearance'),
            'dress-part'  => array('slug' => 'tmr-dress-part', 'title' => __('Part Design', 'tailor-manager'), 'icon' => 'dashicons-admin-appearance'),
            'design-type' => array('slug' => 'tmr-design-type', 'title' => __('Design Type', 'tailor-manager'), 'icon' => 'dashicons-admin-appearance'),
            'accounts'    => array('slug' => 'tmr-accounts', 'title' => __('Accounts', 'tailor-manager'), 'icon' => 'dashicons-money-alt'),
            'settings'    => array('slug' => 'tmr-settings', 'title' => __('Settings', 'tailor-manager'), 'icon' => 'dashicons-admin-generic'),
        );

        add_action('admin_menu', array($this, 'register_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_assets'));
        add_action('admin_head', array($this, 'maybe_collapse_wp_chrome'));
    }

    public function register_menu()
    {
        add_menu_page(
            __('Tailor Panel', 'tailor-manager'),
            __('Tailor Panel', 'tailor-manager'),
            self::CAPABILITY,
            self::$nav['dashboard']['slug'],
            array('TMR_Dashboard_Panel', 'render'),
            'dashicons-store',
            3
        );

        add_submenu_page(self::$nav['dashboard']['slug'], self::$nav['dashboard']['title'], self::$nav['dashboard']['title'], self::CAPABILITY, self::$nav['dashboard']['slug'], array('TMR_Dashboard_Panel', 'render'));
        add_submenu_page(self::$nav['dashboard']['slug'], self::$nav['orders']['title'], self::$nav['orders']['title'], self::CAPABILITY, self::$nav['orders']['slug'], array('TMR_Orders_Panel', 'render'));
        add_submenu_page(self::$nav['dashboard']['slug'], self::$nav['customers']['title'], self::$nav['customers']['title'], self::CAPABILITY, self::$nav['customers']['slug'], array('TMR_Customers_Panel', 'render'));
        add_submenu_page(self::$nav['dashboard']['slug'], self::$nav['dress']['title'], __('Product Input: Dress', 'tailor-manager'), self::CAPABILITY, self::$nav['dress']['slug'], array('TMR_Dress_Panel', 'render'));
        add_submenu_page(self::$nav['dashboard']['slug'], self::$nav['dress-part']['title'], __('Product Input: Part Design', 'tailor-manager'), self::CAPABILITY, self::$nav['dress-part']['slug'], array('TMR_Dress_Part_Panel', 'render'));
        add_submenu_page(self::$nav['dashboard']['slug'], self::$nav['design-type']['title'], __('Product Input: Design Type', 'tailor-manager'), self::CAPABILITY, self::$nav['design-type']['slug'], array('TMR_Design_Type_Panel', 'render'));
        add_submenu_page(self::$nav['dashboard']['slug'], self::$nav['accounts']['title'], self::$nav['accounts']['title'], self::CAPABILITY, self::$nav['accounts']['slug'], array('TMR_Accounts_Report', 'render'));
        add_submenu_page(self::$nav['dashboard']['slug'], self::$nav['settings']['title'], self::$nav['settings']['title'], self::CAPABILITY, self::$nav['settings']['slug'], array('TMR_Settings_Page', 'render'));
    }

    public static function current_page_slug()
    {
        return isset($_GET['page']) ? sanitize_key(wp_unslash($_GET['page'])) : '';
    }

    public static function is_tmr_screen()
    {
        return 0 === strpos(self::current_page_slug(), 'tmr-');
    }

    public function enqueue_assets()
    {
        if (!self::is_tmr_screen()) {
            return;
        }

        wp_enqueue_style('tmr-panel', TMR_PLUGIN_URL . 'assets/css/panel.css', array(), TMR_VERSION);
        wp_enqueue_script('tmr-panel', TMR_PLUGIN_URL . 'assets/js/panel.js', array('jquery'), TMR_VERSION, true);
        wp_enqueue_media();

        wp_localize_script('tmr-panel', 'TMR', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce'   => wp_create_nonce('tmr_panel_nonce'),
        ));
    }

    /**
     * Visually collapses wp-admin's own admin bar/left menu on our pages only — pure CSS,
     * fully reversible, core menu items themselves are never removed.
     */
    public function maybe_collapse_wp_chrome()
    {
        if (!self::is_tmr_screen()) {
            return;
        }
        ?>
        <style>
            #wpadminbar,
            #adminmenumain,
            #wpfooter { display: none !important; }
            html.wp-toolbar { padding-top: 0 !important; }
            #wpcontent, #wpbody-content { margin-left: 0 !important; padding-left: 0 !important; padding-bottom: 0; }
            #wpbody { padding-top: 0; }
        </style>
        <?php
    }

    /**
     * @param string $active nav key from self::$nav
     */
    public static function header($active, $page_title)
    {
        $shop_name = get_option('tmr_shop_name', get_bloginfo('name'));
        ?>
        <div class="tmr-shell">
            <div class="tmr-topbar">
                <div class="tmr-topbar__brand"><?php echo esc_html($shop_name); ?></div>
                <div class="tmr-topbar__actions">
                    <a href="<?php echo esc_url(home_url('/')); ?>" target="_blank"><span class="dashicons dashicons-visibility"></span> <?php esc_html_e('View Site', 'tailor-manager'); ?></a>
                    <a href="<?php echo esc_url(admin_url()); ?>"><span class="dashicons dashicons-wordpress"></span> <?php esc_html_e('WP Admin', 'tailor-manager'); ?></a>
                    <span class="tmr-topbar__user"><span class="dashicons dashicons-admin-users"></span> <?php echo esc_html(wp_get_current_user()->user_login); ?></span>
                    <a href="<?php echo esc_url(wp_logout_url(admin_url())); ?>"><span class="dashicons dashicons-migrate"></span> <?php esc_html_e('Sign Out', 'tailor-manager'); ?></a>
                </div>
            </div>
            <div class="tmr-body">
                <div class="tmr-sidebar">
                    <ul class="tmr-nav">
                        <?php foreach (self::$nav as $key => $item) : ?>
                            <li class="<?php echo $key === $active ? 'is-active' : ''; ?>">
                                <a href="<?php echo esc_url(admin_url('admin.php?page=' . $item['slug'])); ?>">
                                    <span class="dashicons <?php echo esc_attr($item['icon']); ?>"></span>
                                    <?php echo esc_html($item['title']); ?>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <div class="tmr-main">
                    <div class="tmr-main__header">
                        <h1><?php echo esc_html($page_title); ?></h1>
                    </div>
                    <div class="tmr-main__content">
        <?php
    }

    public static function footer()
    {
        ?>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
}
