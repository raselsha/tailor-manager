<?php
defined('ABSPATH') || exit;

/**
 * Registers the "Tailor Panel" menu structure and renders the admin-wrapper/sidebar/
 * main-content shell around every screen — visual language cloned from
 * doctor-appointment's admin-style.css (see panel.css header comment). Landing page
 * dispatches by role, same pattern as that plugin's render_medbook_home(): full admin
 * gets the Dashboard, tailor_staff gets the restricted "My Orders" view.
 */
class TMR_Panel_Shell
{
    const CAPABILITY = 'manage_options';

    /** @var array<string,array{slug:string,title:string,icon:string}> */
    public static $nav = array();

    public function __construct()
    {
        self::$nav = array(
            'dashboard'   => array('slug' => 'tmr-panel', 'title' => __('ড্যাশবোর্ড', 'tailor-manager'), 'icon' => self::icon('grid')),
            'orders'      => array('slug' => 'tmr-orders', 'title' => __('অর্ডার', 'tailor-manager'), 'icon' => self::icon('calendar')),
            'my-orders'   => array('slug' => 'tmr-my-orders', 'title' => __('আমার অর্ডার', 'tailor-manager'), 'icon' => self::icon('calendar')),
            'customers'   => array('slug' => 'tmr-customers', 'title' => __('কাস্টমার', 'tailor-manager'), 'icon' => self::icon('users')),
            'dress'       => array('slug' => 'tmr-dress', 'title' => __('ড্রেস', 'tailor-manager'), 'icon' => self::icon('tag')),
            'dress-part'  => array('slug' => 'tmr-dress-part', 'title' => __('পার্ট ডিজাইন', 'tailor-manager'), 'icon' => self::icon('tag')),
            'design-type' => array('slug' => 'tmr-design-type', 'title' => __('ডিজাইন টাইপ', 'tailor-manager'), 'icon' => self::icon('tag')),
            'categories'  => array('slug' => 'tmr-categories', 'title' => __('ক্যাটাগরি', 'tailor-manager'), 'icon' => self::icon('grid')),
            'accounts'    => array('slug' => 'tmr-accounts', 'title' => __('হিসাব', 'tailor-manager'), 'icon' => self::icon('dollar')),
            'settings'    => array('slug' => 'tmr-settings', 'title' => __('সেটিংস', 'tailor-manager'), 'icon' => self::icon('settings')),
        );

        add_action('admin_menu', array($this, 'register_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_assets'));
        add_action('admin_head', array($this, 'maybe_collapse_wp_chrome'));
        add_filter('admin_body_class', array($this, 'filter_body_class'));
    }

    public function register_menu()
    {
        $n = self::$nav;

        add_menu_page(
            __('টেইলার প্যানেল', 'tailor-manager'),
            __('টেইলার প্যানেল', 'tailor-manager'),
            TMR_Staff_Role::CAPABILITY,
            $n['dashboard']['slug'],
            array(__CLASS__, 'render_home'),
            'dashicons-store',
            3
        );

        add_submenu_page($n['dashboard']['slug'], $n['dashboard']['title'], $n['dashboard']['title'], TMR_Staff_Role::CAPABILITY, $n['dashboard']['slug'], array(__CLASS__, 'render_home'));
        add_submenu_page($n['dashboard']['slug'], $n['my-orders']['title'], $n['my-orders']['title'], TMR_Staff_Role::CAPABILITY, $n['my-orders']['slug'], array('TMR_My_Orders_Panel', 'render'));
        add_submenu_page($n['dashboard']['slug'], $n['orders']['title'], $n['orders']['title'], self::CAPABILITY, $n['orders']['slug'], array('TMR_Orders_Panel', 'render'));
        add_submenu_page($n['dashboard']['slug'], $n['customers']['title'], $n['customers']['title'], self::CAPABILITY, $n['customers']['slug'], array('TMR_Customers_Panel', 'render'));
        add_submenu_page($n['dashboard']['slug'], $n['dress']['title'], __('প্রোডাক্ট ইনপুট: ড্রেস', 'tailor-manager'), self::CAPABILITY, $n['dress']['slug'], array('TMR_Dress_Panel', 'render'));
        add_submenu_page($n['dashboard']['slug'], $n['dress-part']['title'], __('প্রোডাক্ট ইনপুট: পার্ট ডিজাইন', 'tailor-manager'), self::CAPABILITY, $n['dress-part']['slug'], array('TMR_Dress_Part_Panel', 'render'));
        add_submenu_page($n['dashboard']['slug'], $n['design-type']['title'], __('প্রোডাক্ট ইনপুট: ডিজাইন টাইপ', 'tailor-manager'), self::CAPABILITY, $n['design-type']['slug'], array('TMR_Design_Type_Panel', 'render'));
        add_submenu_page($n['dashboard']['slug'], $n['categories']['title'], $n['categories']['title'], self::CAPABILITY, $n['categories']['slug'], array('TMR_Categories_Panel', 'render'));
        add_submenu_page($n['dashboard']['slug'], $n['accounts']['title'], $n['accounts']['title'], self::CAPABILITY, $n['accounts']['slug'], array('TMR_Accounts_Report', 'render'));
        add_submenu_page($n['dashboard']['slug'], $n['settings']['title'], $n['settings']['title'], self::CAPABILITY, $n['settings']['slug'], array('TMR_Settings_Page', 'render'));
    }

    /**
     * Dispatch like doctor-appointment's render_medbook_home(): full admin sees the
     * Dashboard, tailor_staff sees their own restricted "My Orders" view.
     */
    public static function render_home()
    {
        if (current_user_can(self::CAPABILITY)) {
            TMR_Dashboard_Panel::render();
        } else {
            TMR_My_Orders_Panel::render();
        }
    }

    /**
     * @return array<string,array{slug:string,title:string,icon:string}> nav items visible
     *         to the current user, in sidebar order
     */
    public static function visible_nav()
    {
        $n = self::$nav;

        if (current_user_can(self::CAPABILITY)) {
            unset($n['my-orders']);
            return $n;
        }

        return array('dashboard' => array_merge($n['my-orders'], array('slug' => $n['dashboard']['slug'])));
    }

    public static function current_page_slug()
    {
        return isset($_GET['page']) ? sanitize_key(wp_unslash($_GET['page'])) : '';
    }

    public static function is_tmr_screen()
    {
        return 0 === strpos(self::current_page_slug(), 'tmr-');
    }

    /**
     * date_i18n('l, M j') still renders English day/month names unless the whole site's
     * locale is switched to bn_BD (a bigger, site-wide change beyond this plugin) — so
     * dates shown in the panel are formatted through this small fixed map instead.
     */
    public static function bangla_date($timestamp)
    {
        $days = array(
            'Sunday' => 'রবিবার', 'Monday' => 'সোমবার', 'Tuesday' => 'মঙ্গলবার',
            'Wednesday' => 'বুধবার', 'Thursday' => 'বৃহস্পতিবার', 'Friday' => 'শুক্রবার', 'Saturday' => 'শনিবার',
        );
        $months = array(
            'Jan' => 'জানু', 'Feb' => 'ফেব্রু', 'Mar' => 'মার্চ', 'Apr' => 'এপ্রিল',
            'May' => 'মে', 'Jun' => 'জুন', 'Jul' => 'জুলাই', 'Aug' => 'আগস্ট',
            'Sep' => 'সেপ্ট', 'Oct' => 'অক্টো', 'Nov' => 'নভে', 'Dec' => 'ডিসে',
        );

        $day   = $days[gmdate('l', $timestamp)];
        $month = $months[gmdate('M', $timestamp)];

        return $day . ', ' . gmdate('j', $timestamp) . ' ' . $month;
    }

    public function enqueue_assets()
    {
        if (!self::is_tmr_screen()) {
            return;
        }

        // filemtime() instead of the static TMR_VERSION so every asset edit auto-busts the
        // browser cache — a hardcoded version string left the CSS silently stale after edits.
        $css_path = TMR_PLUGIN_PATH . 'assets/css/panel.css';
        $js_path  = TMR_PLUGIN_PATH . 'assets/js/panel.js';

        wp_enqueue_style('tmr-panel', TMR_PLUGIN_URL . 'assets/css/panel.css', array(), file_exists($css_path) ? filemtime($css_path) : TMR_VERSION);
        wp_enqueue_script('tmr-panel', TMR_PLUGIN_URL . 'assets/js/panel.js', array('jquery'), file_exists($js_path) ? filemtime($js_path) : TMR_VERSION, true);
        wp_enqueue_media();

        wp_localize_script('tmr-panel', 'TMR', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce'   => wp_create_nonce('tmr_panel_nonce'),
        ));
    }

    public function filter_body_class($classes)
    {
        if (self::is_tmr_screen()) {
            $classes .= ' tmr-panel-chrome';
            if (!current_user_can(self::CAPABILITY)) {
                $classes .= ' tmr-staff-chrome';
            }
        }
        return $classes;
    }

    /**
     * Same approach as doctor-appointment: the top WP admin bar (Howdy/Log Out) stays —
     * it's the standard, always-available way out, never worth reinventing. Only the
     * native LEFT menu is affected: its flyout submenu for our own top-level item is
     * always suppressed (redundant next to our own in-page sidebar), and for
     * tailor_staff — whose entire navigation IS the plugin's sidebar — the whole native
     * left column is hidden outright. Core menu items are never removed, only
     * conditionally hidden via CSS, fully reversible.
     */
    public function maybe_collapse_wp_chrome()
    {
        if (!self::is_tmr_screen()) {
            return;
        }
        ?>
        <style>
            #adminmenu #toplevel_page_tmr-panel .wp-submenu,
            #adminmenu #toplevel_page_tmr-panel .wp-submenu-wrap,
            #adminmenu #toplevel_page_tmr-panel:hover .wp-submenu,
            #adminmenu #toplevel_page_tmr-panel.opensub .wp-submenu,
            body.folded #adminmenu #toplevel_page_tmr-panel .wp-submenu {
                display: none !important;
                visibility: hidden !important;
            }
            body.tmr-staff-chrome #adminmenumain,
            body.tmr-staff-chrome #adminmenuback,
            body.tmr-staff-chrome #adminmenuwrap {
                display: none !important;
            }
            body.tmr-staff-chrome #wpcontent,
            body.tmr-staff-chrome #wpfooter {
                margin-left: 0 !important;
            }
            @media print {
                #wpadminbar, #adminmenumain, #wpfooter,
                .tmr-sidebar, .tmr-header-right { display: none !important; }
                .tmr-admin-wrapper { display: block; box-shadow: none; border-radius: 0; height: auto; overflow: visible; }
                .tmr-main-content { width: 100%; padding: 0; }
            }
        </style>
        <?php
    }

    /**
     * @param string $active nav key from self::$nav
     * @param string $title
     * @param string $subtitle
     * @param string $header_right raw HTML for the header's right side (search/add button) — caller-escaped
     */
    public static function header($active, $title, $subtitle = '', $header_right = '')
    {
        ?>
        <div id="tmr-admin-dashboard"><div class="tmr-admin-wrapper">
            <?php self::render_sidebar($active); ?>
            <div class="tmr-main-content">
                <div class="tmr-header">
                    <div class="tmr-header-left">
                        <h1><?php echo esc_html($title); ?></h1>
                        <?php if ($subtitle) : ?><p><?php echo esc_html($subtitle); ?></p><?php endif; ?>
                    </div>
                    <?php if ($header_right) : ?>
                        <div class="tmr-header-right"><?php echo $header_right; // phpcs:ignore -- caller-escaped ?></div>
                    <?php endif; ?>
                </div>
        <?php
    }

    public static function footer()
    {
        ?>
            </div>
        </div></div>
        <?php
    }

    private static function render_sidebar($active)
    {
        $shop_name = get_option('tmr_shop_name', get_bloginfo('name'));
        $user      = wp_get_current_user();
        ?>
        <div class="tmr-sidebar">
            <div class="tmr-sidebar-logo"><?php echo esc_html($shop_name); ?></div>
            <ul class="tmr-sidebar-menu">
                <?php foreach (self::visible_nav() as $key => $item) : ?>
                    <li>
                        <a class="tmr-menu-item <?php echo $key === $active ? 'is-active' : ''; ?>" href="<?php echo esc_url(admin_url('admin.php?page=' . $item['slug'])); ?>">
                            <?php echo $item['icon']; // phpcs:ignore -- fixed inline SVG, not user input ?>
                            <?php echo esc_html($item['title']); ?>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
            <div class="tmr-sidebar-footer">
                <div class="tmr-user-avatar"></div>
                <div>
                    <div style="font-weight:700;font-size:13px;"><?php echo esc_html($user->display_name); ?></div>
                    <div style="font-size:11px;opacity:0.6;"><?php echo current_user_can(self::CAPABILITY) ? esc_html__('অ্যাডমিন', 'tailor-manager') : esc_html__('টেইলার স্টাফ', 'tailor-manager'); ?></div>
                </div>
            </div>
        </div>
        <?php
    }

    private static function icon($name)
    {
        $icons = array(
            'grid'     => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"></rect><rect x="14" y="3" width="7" height="7"></rect><rect x="14" y="14" width="7" height="7"></rect><rect x="3" y="14" width="7" height="7"></rect></svg>',
            'calendar' => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="3"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>',
            'users'    => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>',
            'tag'      => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20.59 13.41L13.42 20.58a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"></path><line x1="7" y1="7" x2="7.01" y2="7"></line></svg>',
            'dollar'   => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="1" x2="12" y2="23"></line><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path></svg>',
            'settings' => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"></circle><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"></path></svg>',
        );

        return isset($icons[$name]) ? $icons[$name] : '';
    }
}
