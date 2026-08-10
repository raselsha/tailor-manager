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
    const CAPABILITY = 'manage_tmr_shop';

    /**
     * User meta key storing this plugin's own UI language choice ('en' or 'bn') —
     * deliberately its own meta key, not core's 'locale' user meta (the Site
     * Language field on wp-admin/profile.php), since that native field is
     * unreachable for tailor_staff/tmr_manager anyway (enforce_panel_only_access()
     * blocks profile.php) and forcing core's own locale would also retranslate
     * unrelated wp-admin/core strings this plugin doesn't own.
     */
    const LOCALE_META = 'tmr_ui_lang';

    /** @var array<string,array{slug:string,title:string,icon:string}> */
    public static $nav = array();

    public function __construct()
    {
        // Deferred to 'init' (priority 20, after TMR_Tailor_Manager::load_textdomain()'s
        // default-priority 10 call) instead of being built here in the constructor —
        // this class is instantiated while WordPress is still including active plugin
        // files, long before 'init' fires, so every __() call here used to run before
        // the language switcher's chosen textdomain was even loaded and permanently
        // baked Bangla into this static array regardless of the user's own choice.
        add_action('init', array($this, 'build_nav'), 20);

        add_action('admin_menu', array($this, 'register_menu'));
        add_action('admin_menu', array($this, 'remove_native_profile_menu'), 999);
        add_action('admin_bar_menu', array($this, 'remove_wp_logo_from_admin_bar'), 999);
        add_filter('edit_profile_url', array($this, 'redirect_profile_url'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_assets'));
        add_action('admin_head', array($this, 'maybe_collapse_wp_chrome'));
        add_filter('admin_body_class', array($this, 'filter_body_class'));
        add_action('admin_init', array($this, 'enforce_panel_only_access'));
        add_filter('login_redirect', array($this, 'redirect_after_login'), 10, 3);
        add_action('wp_ajax_tmr_save_ui_lang', array($this, 'ajax_save_ui_lang'));
    }

    public function build_nav()
    {
        self::$nav = array(
            'dashboard'   => array('slug' => 'tmr-panel', 'title' => __('ড্যাশবোর্ড', 'tailor-manager'), 'icon' => self::icon('grid')),
            'orders'      => array('slug' => 'tmr-orders', 'title' => __('অর্ডার', 'tailor-manager'), 'icon' => self::icon('calendar')),
            'my-orders'   => array('slug' => 'tmr-my-orders', 'title' => __('আমার অর্ডার', 'tailor-manager'), 'icon' => self::icon('calendar')),
            'customers'   => array('slug' => 'tmr-customers', 'title' => __('কাস্টমার', 'tailor-manager'), 'icon' => self::icon('users')),
            // URL slugs deliberately swapped from what their array keys/classes suggest —
            // ?page=tmr-dress is the "পোশাক" (broad category, TMR_Categories_Panel) screen
            // and ?page=tmr-categories is the "বিভিন্ন পোশাক" (individual garment,
            // TMR_Dress_Panel) screen. The array KEYS still match each screen's underlying
            // class/purpose (TMR_Dress_Panel = 'dress', TMR_Categories_Panel = 'categories')
            // so render() calls below didn't need to change — only the slug/title values did.
            // Menu order: "পোশাক" (categories key) right after কাস্টমার, then "বিভিন্ন পোশাক"
            // (dress key) beneath it — array order drives sidebar order (visible_nav()).
            'categories'  => array('slug' => 'tmr-dress', 'title' => __('পোশাক', 'tailor-manager'), 'icon' => self::icon('shirt')),
            'dress'       => array('slug' => 'tmr-categories', 'title' => __('বিভিন্ন পোশাক', 'tailor-manager'), 'icon' => self::icon('layers')),
            'dress-part'  => array('slug' => 'tmr-dress-part', 'title' => __('পোশাকের বিভিন্ন অংশ', 'tailor-manager'), 'icon' => self::icon('scissors')),
            'design-type' => array('slug' => 'tmr-design-type', 'title' => __('বিভিন্ন অংশের ডিজাইন', 'tailor-manager'), 'icon' => self::icon('tag')),
            'measurement-fields' => array('slug' => 'tmr-measurement-fields', 'title' => __('পোশাকের পরিমাপ', 'tailor-manager'), 'icon' => self::icon('ruler')),
            'staff'       => array('slug' => 'tmr-staff', 'title' => __('স্টাফ', 'tailor-manager'), 'icon' => self::icon('user')),
            'accounts'    => array('slug' => 'tmr-accounts', 'title' => __('হিসাব', 'tailor-manager'), 'icon' => self::icon('dollar')),
            'settings'    => array('slug' => 'tmr-settings', 'title' => __('সেটিংস', 'tailor-manager'), 'icon' => self::icon('settings')),
            'profile'     => array('slug' => 'tmr-profile', 'title' => __('প্রোফাইল', 'tailor-manager'), 'icon' => self::icon('user')),
            'change-password' => array('slug' => 'tmr-change-password', 'title' => __('পাসওয়ার্ড পরিবর্তন', 'tailor-manager'), 'icon' => self::icon('lock')),
        );
    }

    /**
     * @return string current user's saved UI language, 'en' or 'bn' (default —
     *                 preserves the plugin's original Bangla-only behavior for
     *                 every account that has never touched the new switcher).
     */
    public static function current_ui_lang()
    {
        $user_id = get_current_user_id();
        $lang    = $user_id ? get_user_meta($user_id, self::LOCALE_META, true) : '';
        return ('en' === $lang) ? 'en' : 'bn';
    }

    public function ajax_save_ui_lang()
    {
        check_ajax_referer('tmr_panel_nonce', 'nonce');

        $user_id = get_current_user_id();
        if (!$user_id) {
            wp_send_json_error();
        }

        $lang = isset($_POST['lang']) && 'en' === $_POST['lang'] ? 'en' : 'bn';
        update_user_meta($user_id, self::LOCALE_META, $lang);

        wp_send_json_success(array('lang' => $lang));
    }

    /**
     * True for tailor_staff/tmr_manager accounts — the two roles this plugin's own
     * "app shell" is meant to fully replace WordPress's native admin chrome for.
     * A real administrator keeps the normal WP experience untouched. Same idea as
     * doctor-appointment's own is_restricted_panel_user() helper.
     */
    public static function is_restricted_panel_user($user = null)
    {
        $user = $user ? $user : wp_get_current_user();
        return (TMR_Staff_Role::is_staff($user) || TMR_Manager_Role::is_manager($user)) && !user_can($user, 'manage_options');
    }

    /**
     * Bounces tailor_staff/tmr_manager accounts off every wp-admin screen this
     * plugin doesn't own — direct-URL access to core screens (Dashboard, Posts,
     * Media, Users, Plugins, Themes, Settings, Tools, profile.php, another
     * plugin's own admin.php page, etc.) redirects back into the panel instead
     * of rendering WordPress's native UI. Without this, maybe_collapse_wp_chrome()'s
     * CSS-only hiding only fires on our own tmr-* screens, so typing any other
     * wp-admin URL would still show the full native chrome underneath.
     * admin-ajax.php/admin-post.php/async-upload.php stay open since this
     * plugin's own AJAX handlers, password-change form, and print-slip links
     * run through them and render no visible chrome anyway.
     */
    public function enforce_panel_only_access()
    {
        if (!self::is_restricted_panel_user()) {
            return;
        }

        global $pagenow;

        if (in_array($pagenow, array('admin-ajax.php', 'admin-post.php', 'async-upload.php'), true)) {
            return;
        }

        if ('admin.php' === $pagenow && self::is_tmr_screen()) {
            return;
        }

        wp_safe_redirect(admin_url('admin.php?page=' . self::$nav['dashboard']['slug']));
        exit;
    }

    /**
     * Sends tailor_staff/tmr_manager straight into their own panel after login
     * instead of WordPress's native Dashboard — the Dashboard is one of the
     * screens enforce_panel_only_access() immediately bounces away from anyway,
     * so landing there first would just cost an extra redirect.
     */
    public function redirect_after_login($redirect_to, $requested_redirect_to, $user)
    {
        if ($user instanceof WP_User && self::is_restricted_panel_user($user)) {
            return admin_url('admin.php?page=' . self::$nav['dashboard']['slug']);
        }
        return $redirect_to;
    }

    /**
     * WP core always adds a "Profile" item under Users/Dashboard pointing at
     * wp-admin/profile.php — redundant (and confusing) next to our own Profile
     * page, so it's removed for staff/manager the same way doctor-appointment
     * removes it for its own restricted roles.
     */
    public function remove_native_profile_menu()
    {
        if (self::is_restricted_panel_user()) {
            remove_menu_page('profile.php');
        }
    }

    /**
     * The WordPress-logo dropdown (About WordPress / WordPress.org / Documentation
     * / Support / Feedback) in the admin bar's far top-left — not relevant chrome
     * for a shop-panel-only account.
     */
    public function remove_wp_logo_from_admin_bar($wp_admin_bar)
    {
        if (self::is_restricted_panel_user()) {
            $wp_admin_bar->remove_node('wp-logo');
        }
    }


    /**
     * Every "Edit Profile" link WP core builds (the admin-bar "Howdy" avatar/name
     * itself, and its dropdown's own "Edit Profile" row) is built from
     * get_edit_profile_url() (which applies the 'edit_profile_url' filter, despite
     * the mismatched name) — filtering it here retargets both to our own
     * Profile page instead of wp-admin/profile.php, without needing to touch
     * core's admin-bar markup directly.
     */
    public function redirect_profile_url($url)
    {
        if (self::is_restricted_panel_user()) {
            return admin_url('admin.php?page=' . self::$nav['profile']['slug']);
        }
        return $url;
    }

    public function register_menu()
    {
        $n = self::$nav;

        // 'read' (not TMR_Staff_Role::CAPABILITY) — tailor_staff and tmr_manager
        // are deliberately disjoint capabilities (see TMR_Profile_Panel::can_access()'s
        // own comment), so gating the top-level menu/dashboard entry on the staff
        // capability alone silently locked a pure manager account out of the whole
        // panel, dashboard included. render_home() already does its own real
        // dispatch/capability check below, and both branches it can route to
        // (TMR_Dashboard_Panel::render(), TMR_My_Orders_Panel::render()) have their
        // own wp_die() gate — so 'read' here is safe, same loose-registration
        // pattern as the Profile/Change-Password pages.
        add_menu_page(
            __('টেইলার প্যানেল', 'tailor-manager'),
            __('টেইলার প্যানেল', 'tailor-manager'),
            'read',
            $n['dashboard']['slug'],
            array(__CLASS__, 'render_home'),
            'dashicons-store',
            3
        );

        add_submenu_page($n['dashboard']['slug'], $n['dashboard']['title'], $n['dashboard']['title'], 'read', $n['dashboard']['slug'], array(__CLASS__, 'render_home'));
        add_submenu_page($n['dashboard']['slug'], $n['my-orders']['title'], $n['my-orders']['title'], TMR_Staff_Role::CAPABILITY, $n['my-orders']['slug'], array('TMR_My_Orders_Panel', 'render'));
        add_submenu_page($n['dashboard']['slug'], $n['orders']['title'], $n['orders']['title'], self::CAPABILITY, $n['orders']['slug'], array('TMR_Orders_Panel', 'render'));
        add_submenu_page($n['dashboard']['slug'], $n['customers']['title'], $n['customers']['title'], self::CAPABILITY, $n['customers']['slug'], array('TMR_Customers_Panel', 'render'));
        add_submenu_page($n['dashboard']['slug'], $n['dress']['title'], __('প্রোডাক্ট ইনপুট: বিভিন্ন পোশাক', 'tailor-manager'), self::CAPABILITY, $n['dress']['slug'], array('TMR_Dress_Panel', 'render'));
        add_submenu_page($n['dashboard']['slug'], $n['dress-part']['title'], __('প্রোডাক্ট ইনপুট: পোশাকের বিভিন্ন অংশ', 'tailor-manager'), self::CAPABILITY, $n['dress-part']['slug'], array('TMR_Dress_Part_Panel', 'render'));
        add_submenu_page($n['dashboard']['slug'], $n['design-type']['title'], __('প্রোডাক্ট ইনপুট: বিভিন্ন অংশের ডিজাইন', 'tailor-manager'), self::CAPABILITY, $n['design-type']['slug'], array('TMR_Design_Type_Panel', 'render'));
        add_submenu_page($n['dashboard']['slug'], $n['measurement-fields']['title'], __('প্রোডাক্ট ইনপুট: পোশাকের পরিমাপ', 'tailor-manager'), self::CAPABILITY, $n['measurement-fields']['slug'], array('TMR_Measurement_Fields_Panel', 'render'));
        add_submenu_page($n['dashboard']['slug'], $n['categories']['title'], $n['categories']['title'], self::CAPABILITY, $n['categories']['slug'], array('TMR_Categories_Panel', 'render'));
        add_submenu_page($n['dashboard']['slug'], $n['staff']['title'], $n['staff']['title'], self::CAPABILITY, $n['staff']['slug'], array('TMR_Staff_Panel', 'render'));
        add_submenu_page($n['dashboard']['slug'], $n['accounts']['title'], $n['accounts']['title'], self::CAPABILITY, $n['accounts']['slug'], array('TMR_Accounts_Report', 'render'));
        add_submenu_page($n['dashboard']['slug'], $n['settings']['title'], $n['settings']['title'], self::CAPABILITY, $n['settings']['slug'], array('TMR_Settings_Page', 'render'));
        // 'read' (not self::CAPABILITY or TMR_Staff_Role::CAPABILITY) — tailor_staff
        // and tmr_manager are deliberately disjoint capabilities (only
        // administrator has both), so neither alone covers "every logged-in panel
        // user." Same loose-registration-plus-OR-check-in-the-callback pattern
        // doctor-appointment uses for its own Profile/Change-Password pages — see
        // TMR_Profile_Panel::render_profile()/render_change_password() for the
        // real check.
        add_submenu_page($n['dashboard']['slug'], $n['profile']['title'], $n['profile']['title'], 'read', $n['profile']['slug'], array('TMR_Profile_Panel', 'render_profile'));
        add_submenu_page($n['dashboard']['slug'], $n['change-password']['title'], $n['change-password']['title'], 'read', $n['change-password']['slug'], array('TMR_Profile_Panel', 'render_change_password'));
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

        return array(
            'dashboard'        => array_merge($n['my-orders'], array('slug' => $n['dashboard']['slug'])),
            'profile'          => $n['profile'],
            'change-password'  => $n['change-password'],
        );
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
     * Shared by every drag-and-drop-sortable CPT grid (Dress, Dress Part, Design
     * Type) — persists a new display order as each post's own native menu_order
     * field rather than a bespoke option, so no extra storage/migration is
     * needed and every existing WP_Query('orderby' => 'menu_order') convention
     * just works. $ids is scoped to ONE grid/group at a time (e.g. one category's
     * dress cards), so menu_order values intentionally restart at 0 per group —
     * they're never compared across groups, only used to sort within one.
     */
    public static function save_menu_order(array $ids)
    {
        foreach ($ids as $index => $id) {
            wp_update_post(array('ID' => (int) $id, 'menu_order' => $index));
        }
    }

    /**
     * date_i18n('l, M j') still renders English day/month names unless the whole site's
     * locale is switched to bn_BD (a bigger, site-wide change beyond this plugin) — so
     * dates shown in the panel are formatted through this small fixed map instead.
     * Dispatches on current_ui_lang() so the panel's own English mode gets plain
     * "Sun, 9 Aug" instead of Bangla day/month names.
     */
    public static function bangla_date($timestamp)
    {
        if ('en' === self::current_ui_lang()) {
            return gmdate('D, j M', $timestamp);
        }

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

    /**
     * "৳ 3,200" — same ৳-prefixed, thousands-separated, no-unnecessary-decimals format
     * as the order form's own JS formatMoney(), for read-only PHP-rendered amounts
     * (order view page, print slips) to match what the live form already shows.
     */
    public static function format_money($value)
    {
        $value = round((float) $value, 2);
        $decimals = (floor($value) == $value) ? 0 : 2;
        return '৳ ' . number_format($value, $decimals);
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
        // Kazuhiko Arase's public-domain QR generator (same vendor copy doctor-appointment
        // uses for its booking-confirmation QR) — powers the order confirmation's QR code.
        wp_enqueue_script('tmr-qrcode', TMR_PLUGIN_URL . 'assets/js/vendor/qrcode.js', array(), TMR_VERSION, true);
        // SortableJS (MIT, vendored — RubaXa/SortableJS), not jQuery UI Sortable:
        // jQuery UI's own sortable was built for normal block/list flow and drags
        // items via absolute-positioning math that doesn't account for CSS Grid
        // track layout (.tmr-dress-grid is `display: grid`) — it would
        // intermittently miscalculate the drop target and silently fail to
        // reorder. SortableJS moves the real DOM node during drag instead, which
        // the grid just reflows around, and needs no jQuery UI dependency at all.
        wp_enqueue_script('tmr-sortable', TMR_PLUGIN_URL . 'assets/js/vendor/sortable.min.js', array(), TMR_VERSION, true);
        wp_enqueue_script('tmr-panel', TMR_PLUGIN_URL . 'assets/js/panel.js', array('jquery', 'tmr-qrcode', 'tmr-sortable'), file_exists($js_path) ? filemtime($js_path) : TMR_VERSION, true);
        wp_enqueue_media();

        wp_localize_script('tmr-panel', 'TMR', array(
            'ajaxUrl'           => admin_url('admin-ajax.php'),
            'nonce'             => wp_create_nonce('tmr_panel_nonce'),
            // Settings > ডেলিভারি সেটিংস toggle — the Orders panel's own JS
            // (TMR_Orders_Panel) checks this before ever touching localStorage,
            // so a shop owner who doesn't want in-progress orders remembered
            // across page loads can turn the whole thing off from one place.
            'orderDraftEnabled' => (bool) get_option('tmr_order_draft_enabled', '1'),
        ));
    }

    public function filter_body_class($classes)
    {
        if (self::is_tmr_screen()) {
            $classes .= ' tmr-panel-chrome';
            // Was gated on "lacks the manager+ capability" (tailor_staff only) —
            // a pure manager account has that capability by design, so it never
            // got this treatment and kept seeing WP's native left sidebar
            // alongside our own. is_restricted_panel_user() covers both roles.
            if (self::is_restricted_panel_user()) {
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
        // The "টেইলার প্যানেল" top-level item (and its native flyout submenu) sits
        // in WP's own left sidebar on every wp-admin screen, not just our own —
        // so this rule has to run unconditionally, every admin_head. Our own
        // in-panel sidebar (render_sidebar()) already duplicates every one of
        // those submenu links, so the native flyout is always redundant, not
        // just while already inside the plugin.
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
        </style>
        <?php
        if (!self::is_tmr_screen()) {
            return;
        }
        ?>
        <style>
            /* Any core/plugin admin notice (update nags, "Thank you for creating
               with WordPress", etc.) prints into #wpbody-content ahead of our own
               render() output — hiding everything there except our own root div
               keeps the panel's "shows nothing WordPress" promise regardless of
               what notices happen to be registered. Applies on every tmr-* screen,
               not just staff/manager, since a true admin gets the normal WP
               experience back the moment they leave this plugin's own pages. */
            body.tmr-panel-chrome #wpbody-content > *:not(#tmr-admin-dashboard) {
                display: none !important;
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
            body.tmr-staff-chrome #wpfooter {
                display: none !important;
            }
            /* The top #wpadminbar (WP logo, site name, comments, "+New",
               "Howdy"/avatar) — show_admin_bar(false) can't do this from PHP
               because is_admin_bar_showing() hardcodes true for every wp-admin
               screen (see wp-includes/admin-bar.php), so it has to be hidden the
               same way the rest of this native chrome already is: CSS scoped to
               tmr-staff-chrome. The 32px/46px top padding WP reserves for it is
               on <html class="wp-toolbar">, not <body>, so reclaiming it needs
               :has() to reach html from the body class — universally supported
               by now. .tmr-admin-wrapper's own height calc is adjusted to match
               in panel.css (search "40px" near .tmr-admin-wrapper). */
            body.tmr-staff-chrome #wpadminbar {
                display: none !important;
            }
            html:has(body.tmr-staff-chrome) {
                padding-top: 0 !important;
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
    /**
     * @var bool tracks whether header() opened a .tmr-scroll-wrap that footer() needs
     * to close — set per-call since header()/footer() are static and this plugin
     * never nests two panel shells in one request.
     */
    private static $fixed_header_open = false;

    /**
     * @param bool   $fixed_header keeps the title/subtitle/header-right row pinned in
     * place while everything below it scrolls in its own contained area — for pages
     * whose content (collapsible category/part sections, grid cards) can get long
     * enough to otherwise push the sidebar out of view. Same pattern already used for
     * doctor-appointment's schedule view (.mdbk-main-content-fixed-header).
     * @param string $sticky_content raw HTML (caller-escaped) rendered right after the
     * title row but still *outside* .tmr-scroll-wrap — e.g. a filter/tabs bar that
     * should stay pinned alongside the title while only the list/table below it
     * scrolls. Only meaningful when $fixed_header is true; a flex-based "pin above,
     * scroll below" split, not a guessed pixel offset, so it stays correct
     * regardless of how tall the title/filter content actually renders.
     */
    public static function header($active, $title, $subtitle = '', $header_right = '', $fixed_header = false, $sticky_content = '')
    {
        self::$fixed_header_open = $fixed_header;
        ?>
        <div id="tmr-admin-dashboard"><div class="tmr-admin-wrapper">
            <?php self::render_sidebar($active); ?>
            <div class="tmr-sidebar-backdrop" id="tmr-sidebar-backdrop"></div>
            <div class="tmr-main-content<?php echo $fixed_header ? ' tmr-main-content-fixed-header' : ''; ?>">
                <div class="tmr-header">
                    <div class="tmr-header-left">
                        <button type="button" class="tmr-header-hamburger" id="tmr-mobile-menu-toggle" aria-label="<?php esc_attr_e('মেনু', 'tailor-manager'); ?>">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="3" y1="6" x2="21" y2="6"></line><line x1="3" y1="12" x2="21" y2="12"></line><line x1="3" y1="18" x2="21" y2="18"></line></svg>
                        </button>
                        <div>
                            <h1><?php echo esc_html($title); ?></h1>
                            <?php if ($subtitle) : ?><p><?php echo esc_html($subtitle); ?></p><?php endif; ?>
                        </div>
                    </div>
                    <?php if ($header_right) : ?>
                        <div class="tmr-header-right"><?php echo $header_right; // phpcs:ignore -- caller-escaped ?></div>
                    <?php endif; ?>
                </div>
                <?php if ($sticky_content) : ?>
                    <?php echo $sticky_content; // phpcs:ignore -- caller-escaped ?>
                <?php endif; ?>
                <?php if ($fixed_header) : ?>
                <div class="tmr-scroll-wrap">
                <?php endif; ?>
        <?php
    }

    public static function footer()
    {
        ?>
                <?php if (self::$fixed_header_open) : ?>
                </div>
                <?php endif; ?>
            </div>
        </div></div>
        <?php
    }

    private static function render_sidebar($active)
    {
        $shop_name  = get_option('tmr_shop_name', get_bloginfo('name'));
        $shop_phone = get_option('tmr_shop_phone', '');
        $user       = wp_get_current_user();
        ?>
        <div class="tmr-sidebar">
            <div class="tmr-sidebar-logo">
                <span id="tmr-sidebar-shop-name"><?php echo esc_html($shop_name); ?></span>
                <div class="tmr-sidebar-shop-phone" id="tmr-sidebar-shop-phone" style="<?php echo $shop_phone ? '' : 'display:none;'; ?>"><?php echo esc_html($shop_phone); ?></div>
            </div>
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
            <a class="tmr-sidebar-footer" href="<?php echo esc_url(admin_url('admin.php?page=' . self::$nav['profile']['slug'])); ?>">
                <?php echo TMR_Profile_Panel::avatar_html($user->ID, 32); // phpcs:ignore -- self-escaped ?>
                <div>
                    <div style="font-weight:700;font-size:13px;"><?php echo esc_html($user->display_name); ?></div>
                    <div style="font-size:11px;opacity:0.6;"><?php echo current_user_can(self::CAPABILITY) ? esc_html__('অ্যাডমিন', 'tailor-manager') : esc_html__('টেইলার স্টাফ', 'tailor-manager'); ?></div>
                </div>
            </a>
            <a class="tmr-sidebar-logout" href="<?php echo esc_url(wp_logout_url(home_url('/'))); ?>">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><polyline points="16 17 21 12 16 7"></polyline><line x1="21" y1="12" x2="9" y2="12"></line></svg>
                <?php esc_html_e('লগআউট', 'tailor-manager'); ?>
            </a>
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
            'shirt'    => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20.38 3.46 16 2a4 4 0 0 1-8 0L3.62 3.46a2 2 0 0 0-1.34 2.23l.58 3.47a1 1 0 0 0 .99.84H6v10c0 .55.45 1 1 1h10c.55 0 1-.45 1-1V10h2.15a1 1 0 0 0 .99-.84l.58-3.47a2 2 0 0 0-1.34-2.23z"></path></svg>',
            'layers'   => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12.83 2.18a2 2 0 0 0-1.66 0L2.6 6.08a1 1 0 0 0 0 1.83l8.58 3.91a2 2 0 0 0 1.66 0l8.58-3.9a1 1 0 0 0 0-1.83Z"></path><path d="M2 12a1 1 0 0 0 .58.91l8.6 3.91a2 2 0 0 0 1.65 0l8.58-3.9A1 1 0 0 0 22 12"></path><path d="M2 17a1 1 0 0 0 .58.91l8.6 3.91a2 2 0 0 0 1.65 0l8.58-3.9A1 1 0 0 0 22 17"></path></svg>',
            'scissors' => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="6" cy="6" r="3"></circle><circle cx="6" cy="18" r="3"></circle><line x1="20" y1="4" x2="8.12" y2="15.88"></line><line x1="14.47" y1="14.48" x2="20" y2="20"></line><line x1="8.12" y1="8.12" x2="12" y2="12"></line></svg>',
            'ruler'    => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21.3 15.3a2.4 2.4 0 0 1 0 3.4l-2.6 2.6a2.4 2.4 0 0 1-3.4 0L2.7 8.7a2.4 2.4 0 0 1 0-3.4l2.6-2.6a2.4 2.4 0 0 1 3.4 0z"></path><path d="M14.5 6.5l3 3"></path><path d="M11.5 9.5l1.5 1.5"></path><path d="M8.5 12.5l1.5 1.5"></path></svg>',
            'user'     => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>',
            'settings' => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"></circle><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"></path></svg>',
            'lock'     => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>',
        );

        return isset($icons[$name]) ? $icons[$name] : '';
    }
}
