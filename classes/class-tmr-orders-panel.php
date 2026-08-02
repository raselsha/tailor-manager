<?php
defined('ABSPATH') || exit;

/**
 * The core order-entry screen. One order = customer + dates + pricing + one item per
 * Category actually used (each item: dresses+qty checked in that category, one shared
 * measurement set, one shared design/part selection set) — see class-tmr-order-item-post-type.php.
 */
class TMR_Orders_Panel
{
    const POST_TYPE = TMR_Order_Post_Type::POST_TYPE;
    const PER_PAGE = 20;

    public function __construct()
    {
        add_action('wp_ajax_tmr_save_order', array($this, 'ajax_save'));
        add_action('wp_ajax_tmr_delete_order', array($this, 'ajax_delete'));
        add_action('wp_ajax_tmr_search_customers', array($this, 'ajax_search_customers'));
        add_action('wp_ajax_tmr_quick_add_customer', array($this, 'ajax_quick_add_customer'));
        add_action('wp_ajax_tmr_get_order_form', array($this, 'ajax_get_order_form'));
        add_action('wp_ajax_tmr_get_order_summary', array($this, 'ajax_get_order_summary'));
        add_action('wp_ajax_tmr_search_orders', array($this, 'ajax_search'));
    }

    public static function render()
    {
        if (!current_user_can(TMR_Panel_Shell::CAPABILITY)) {
            wp_die(esc_html__('এই পেজ দেখার অনুমতি আপনার নেই।', 'tailor-manager'));
        }

        $action = isset($_GET['action']) ? sanitize_key($_GET['action']) : 'list';
        $id     = isset($_GET['id']) ? (int) $_GET['id'] : 0;

        if ('view' === $action && $id) {
            self::render_view($id);
            return;
        }

        // Add/Edit now open as a modal on the list page itself (see
        // #tmr-order-modal + ajax_get_order_form()) rather than a separate page —
        // an old ?action=edit&id= link (e.g. from the View screen's Edit button)
        // still lands on the list with that order's modal auto-opened.
        self::render_list('edit' === $action ? $id : 0);
    }

    /* ---------------------------------------------------------------- */
    /* List                                                              */
    /* ---------------------------------------------------------------- */

    /**
     * Builds the WP_Query behind the list — shared by the normal page-load
     * render and the live-search AJAX handler (ajax_search()) so both stay in
     * sync automatically instead of duplicating the meta_query logic.
     */
    private static function build_query($search, $status, $paged)
    {
        // Cancelled takes priority over ready/delivered in TMR_Order_Post_Type::
        // status_label() (an order can be cancelled after being marked ready or
        // even delivered — the flags aren't reset on cancel), so every other tab
        // needs to explicitly exclude cancelled orders too, or a cancelled order
        // shows up under both its old tab and "বাতিলকৃত" at once.
        $not_cancelled = array('relation' => 'OR', array('key' => '_tmr_cancelled', 'compare' => 'NOT EXISTS'), array('key' => '_tmr_cancelled', 'value' => '1', 'compare' => '!='));

        $meta_query = array();
        if ('pending' === $status) {
            $meta_query[] = array('relation' => 'OR', array('key' => '_tmr_delivered', 'compare' => 'NOT EXISTS'), array('key' => '_tmr_delivered', 'value' => '1', 'compare' => '!='));
            $meta_query[] = array('relation' => 'OR', array('key' => '_tmr_ready', 'compare' => 'NOT EXISTS'), array('key' => '_tmr_ready', 'value' => '1', 'compare' => '!='));
            $meta_query[] = $not_cancelled;
        } elseif ('ready' === $status) {
            $meta_query[] = array('key' => '_tmr_ready', 'value' => '1');
            $meta_query[] = array('relation' => 'OR', array('key' => '_tmr_delivered', 'compare' => 'NOT EXISTS'), array('key' => '_tmr_delivered', 'value' => '1', 'compare' => '!='));
            $meta_query[] = $not_cancelled;
        } elseif ('delivered' === $status) {
            $meta_query[] = array('key' => '_tmr_delivered', 'value' => '1');
            $meta_query[] = $not_cancelled;
        } elseif ('cancelled' === $status) {
            $meta_query[] = array('key' => '_tmr_cancelled', 'value' => '1');
        }

        $args = array(
            'post_type'      => self::POST_TYPE,
            'post_status'    => 'any',
            'posts_per_page' => self::PER_PAGE,
            'paged'          => $paged,
            'orderby'        => 'ID',
            'order'          => 'DESC',
        );

        if ($search) {
            $matched_order_ids = array();

            // Order ID match — typing "116" or "#116" finds that order directly.
            // WP_Query's own 's' only ever matches post_title (tmr_order posts have
            // no title), so this has to be resolved by hand.
            $numeric_search = ltrim($search, '#');
            if (ctype_digit($numeric_search)) {
                $possible_order = get_post((int) $numeric_search);
                if ($possible_order && self::POST_TYPE === $possible_order->post_type) {
                    $matched_order_ids[] = (int) $numeric_search;
                }
            }

            // Customer name (WP's own 's', matches post_title) OR phone number
            // (stored as postmeta, so 's' alone never matches it) — either one
            // resolves to the customer's own orders.
            $name_matched_ids = get_posts(array(
                'post_type'      => TMR_Customer_Post_Type::POST_TYPE,
                'post_status'    => array('publish', 'draft'),
                's'              => $search,
                'posts_per_page' => -1,
                'fields'         => 'ids',
            ));
            $phone_matched_ids = get_posts(array(
                'post_type'      => TMR_Customer_Post_Type::POST_TYPE,
                'post_status'    => array('publish', 'draft'),
                'posts_per_page' => -1,
                'fields'         => 'ids',
                'meta_query'     => array(array('key' => '_tmr_phone', 'value' => $search, 'compare' => 'LIKE')),
            ));
            $customer_ids = array_unique(array_merge($name_matched_ids, $phone_matched_ids));

            if ($customer_ids) {
                $orders_by_customer = get_posts(array(
                    'post_type'      => self::POST_TYPE,
                    'post_status'    => 'any',
                    'posts_per_page' => -1,
                    'fields'         => 'ids',
                    'meta_query'     => array(array('key' => '_tmr_customer_id', 'value' => $customer_ids, 'compare' => 'IN')),
                ));
                $matched_order_ids = array_merge($matched_order_ids, $orders_by_customer);
            }

            $args['post__in'] = empty($matched_order_ids) ? array(0) : array_unique($matched_order_ids);
        }

        if ($meta_query) {
            $args['meta_query'] = array_merge(isset($args['meta_query']) ? $args['meta_query'] : array(), $meta_query);
            $args['meta_query']['relation'] = 'AND';
        }

        return new WP_Query($args);
    }

    /**
     * The table + pagination fragment inside #tmr-orders-list-wrap — rendered
     * both on the normal page load (render_list()) and, unchanged, as the
     * response HTML for ajax_search()'s live-search-as-you-type requests.
     */
    private static function render_table($query, $search, $status, $paged)
    {
        ?>
        <div class="tmr-card">
            <div class="tmr-table-cards">
            <table class="tmr-table">
                <thead>
                    <tr>
                        <th><?php esc_html_e('অর্ডার আইডি', 'tailor-manager'); ?></th>
                        <th><?php esc_html_e('কাস্টমার', 'tailor-manager'); ?></th>
                        <th><?php esc_html_e('ড্রেস ও পরিমাণ', 'tailor-manager'); ?></th>
                        <th><?php esc_html_e('স্টাফ', 'tailor-manager'); ?></th>
                        <th><?php esc_html_e('ডেলিভারি তারিখ', 'tailor-manager'); ?></th>
                        <th><?php esc_html_e('ডেলিভারি স্ট্যাটাস', 'tailor-manager'); ?></th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$query->have_posts()) : ?>
                        <tr><td colspan="7" class="tmr-empty"><?php esc_html_e('কোনো অর্ডার পাওয়া যায়নি।', 'tailor-manager'); ?></td></tr>
                    <?php else : ?>
                        <?php foreach ($query->posts as $order) :
                            $customer_id = (int) get_post_meta($order->ID, '_tmr_customer_id', true);
                            $phone       = $customer_id ? TMR_Customer_Post_Type::get_phone($customer_id) : '';
                            $name        = $customer_id ? get_the_title($customer_id) : __('ওয়াক-ইন', 'tailor-manager');
                            $status_key  = TMR_Order_Post_Type::status_label($order->ID);
                            $staff       = self::staff_summary($order->ID);
                        ?>
                            <tr>
                                <td data-label="<?php esc_attr_e('অর্ডার আইডি', 'tailor-manager'); ?>">#<?php echo esc_html($order->ID); ?></td>
                                <td data-label="<?php esc_attr_e('কাস্টমার', 'tailor-manager'); ?>"><a href="#" class="tmr-view-order-trigger" data-id="<?php echo esc_attr($order->ID); ?>" style="color:#1e293b;font-weight:600;text-decoration:none;"><?php echo esc_html($name . ($phone ? ' (' . $phone . ')' : '')); ?></a></td>
                                <td data-label="<?php esc_attr_e('ড্রেস ও পরিমাণ', 'tailor-manager'); ?>"><?php echo esc_html(self::dress_summary($order->ID)); ?></td>
                                <td data-label="<?php esc_attr_e('স্টাফ', 'tailor-manager'); ?>"><?php echo $staff ? esc_html($staff) : '<span class="tmr-empty-inline">' . esc_html__('অনির্ধারিত', 'tailor-manager') . '</span>'; // phpcs:ignore -- self-escaped ?></td>
                                <td data-label="<?php esc_attr_e('ডেলিভারি তারিখ', 'tailor-manager'); ?>"><?php echo esc_html(get_post_meta($order->ID, '_tmr_delivery_date', true)); ?></td>
                                <td data-label="<?php esc_attr_e('ডেলিভারি স্ট্যাটাস', 'tailor-manager'); ?>"><span class="tmr-badge tmr-badge-<?php echo esc_attr($status_key); ?>"><?php echo esc_html(ucfirst($status_key)); ?></span></td>
                                <td class="tmr-orders-actions-cell">
                                    <div class="tmr-actions">
                                        <a class="tmr-icon-btn tmr-view-order-trigger" href="#" data-id="<?php echo esc_attr($order->ID); ?>" title="<?php esc_attr_e('দেখুন', 'tailor-manager'); ?>"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg></a>
                                        <button type="button" class="tmr-icon-btn tmr-open-order-modal-edit" data-id="<?php echo esc_attr($order->ID); ?>" title="<?php esc_attr_e('এডিট', 'tailor-manager'); ?>"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg></button>
                                        <span class="tmr-icon-btn tmr-icon-btn-danger tmr-delete-order" data-id="<?php echo esc_attr($order->ID); ?>" title="<?php esc_attr_e('ডিলিট', 'tailor-manager'); ?>"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg></span>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
            </div>
        </div>

        <?php TMR_Customers_Panel::render_pagination($query->max_num_pages, $paged, array('page' => 'tmr-orders', 'status' => $status, 's' => $search)); ?>
        <?php
    }

    private static function render_list($auto_open_id = 0)
    {
        $search = isset($_GET['s']) ? sanitize_text_field(wp_unslash($_GET['s'])) : '';
        $status = isset($_GET['status']) ? sanitize_key($_GET['status']) : 'all';
        $paged  = isset($_GET['paged']) ? max(1, (int) $_GET['paged']) : 1;

        $query = self::build_query($search, $status, $paged);

        $header_right = '<button type="button" class="tmr-btn-add" id="tmr-open-order-modal">' . esc_html__('+ অর্ডার নিন', 'tailor-manager') . '</button>';

        // Rendered as $sticky_content (not inline below) so it renders *outside*
        // TMR_Panel_Shell's .tmr-scroll-wrap, alongside the pinned title — only the
        // order table/cards inside .tmr-scroll-wrap scroll, the filter bar (and the
        // rest of the page) never does.
        ob_start();
        ?>
        <div class="tmr-filters-bar tmr-orders-filters-bar">
            <div class="tmr-tabs-scroll">
                <div class="tmr-tabs">
                    <?php
                    $tabs = array('all' => __('সব', 'tailor-manager'), 'pending' => __('পেন্ডিং', 'tailor-manager'), 'ready' => __('রেডি', 'tailor-manager'), 'delivered' => __('ডেলিভারড', 'tailor-manager'), 'cancelled' => __('বাতিলকৃত', 'tailor-manager'));
                    foreach ($tabs as $key => $label) :
                        $url = esc_url(add_query_arg(array('page' => 'tmr-orders', 'status' => $key), admin_url('admin.php')));
                        ?>
                        <a href="<?php echo $url; ?>" class="<?php echo $status === $key ? 'is-active' : ''; ?>"><?php echo esc_html($label); ?></a>
                    <?php endforeach; ?>
                </div>
            </div>
            <form method="get" class="tmr-orders-search-form">
                <input type="hidden" name="page" value="tmr-orders" />
                <input type="hidden" name="status" value="<?php echo esc_attr($status); ?>" />
                <div class="tmr-filter-input-wrap">
                    <svg class="tmr-filter-input-icon" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                    <input type="text" name="s" class="tmr-filter-input" value="<?php echo esc_attr($search); ?>" placeholder="<?php esc_attr_e('কাস্টমারের নাম বা ফোন খুঁজুন…', 'tailor-manager'); ?>" />
                </div>
            </form>
        </div>
        <?php
        $filter_bar_html = ob_get_clean();

        TMR_Panel_Shell::header('orders', __('অর্ডার ম্যানেজার', 'tailor-manager'), __('সকল কাস্টমার অর্ডার।', 'tailor-manager'), $header_right, true, $filter_bar_html);
        ?>

        <div id="tmr-orders-list-wrap">
            <?php self::render_table($query, $search, $status, $paged); ?>
        </div>

        <div class="tmr-modal" id="tmr-order-modal">
            <div class="tmr-modal-content tmr-modal-content-lg">
                <div class="tmr-modal-head">
                    <h2 id="tmr-order-modal-title"><?php esc_html_e('অর্ডার নিন', 'tailor-manager'); ?></h2>
                    <button type="button" class="tmr-modal-close">&times;</button>
                </div>
                <div class="tmr-modal-body" id="tmr-order-modal-body">
                    <div class="tmr-empty"><?php esc_html_e('লোড হচ্ছে…', 'tailor-manager'); ?></div>
                </div>
                <div class="tmr-modal-body tmr-order-confirmation" id="tmr-order-confirmation-body" style="display:none;">
                    <div class="tmr-confirmation-heading">
                        <span class="tmr-confirmation-icon">&#10003;</span>
                        <span>
                            <strong><?php esc_html_e('অর্ডার সম্পন্ন হয়েছে', 'tailor-manager'); ?></strong>
                            <span class="tmr-confirmation-shop-name"><?php echo esc_html(get_bloginfo('name')); ?></span>
                        </span>
                    </div>
                    <div class="tmr-confirmation-toolbar" id="tmr-confirmation-toolbar">
                        <div class="tmr-confirmation-toolbar-status">
                            <div class="tmr-status-field">
                                <div class="tmr-status-dropdown" id="tmr-conf-status-dropdown">
                                    <button type="button" class="tmr-status-select-btn" id="tmr-conf-status-btn">
                                        <span class="tmr-status-select-label" id="tmr-conf-status-label"></span>
                                        <svg class="tmr-status-select-chevron" width="10" height="6" viewBox="0 0 10 6" fill="none"><path d="M1 1l4 4 4-4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"></path></svg>
                                    </button>
                                    <div class="tmr-status-dropdown-menu">
                                        <button type="button" class="tmr-status-dropdown-item" data-status="pending"><?php esc_html_e('পেন্ডিং', 'tailor-manager'); ?></button>
                                        <button type="button" class="tmr-status-dropdown-item" data-status="ready"><?php esc_html_e('রেডি', 'tailor-manager'); ?></button>
                                        <button type="button" class="tmr-status-dropdown-item" data-status="delivered"><?php esc_html_e('ডেলিভারড', 'tailor-manager'); ?></button>
                                        <button type="button" class="tmr-status-dropdown-item" data-status="cancelled"><?php esc_html_e('বাতিল', 'tailor-manager'); ?></button>
                                    </div>
                                </div>
                                <span class="tmr-status-hint"><?php esc_html_e('স্ট্যাটাস পরিবর্তন করুন', 'tailor-manager'); ?></span>
                            </div>
                        </div>
                        <div class="tmr-confirmation-toolbar-actions">
                            <button type="button" class="tmr-btn-outline tmr-btn-sm tmr-open-order-modal-edit" id="tmr-conf-edit-btn">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg>
                                <?php esc_html_e('এডিট', 'tailor-manager'); ?>
                            </button>
                            <a class="tmr-btn-outline tmr-btn-sm" id="tmr-conf-print-receipt" href="#" target="_blank">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 6 2 18 2 18 9"></polyline><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path><rect x="6" y="14" width="12" height="8"></rect></svg>
                                <?php esc_html_e('রিসিট', 'tailor-manager'); ?>
                            </a>
                            <a class="tmr-btn-outline tmr-btn-sm" id="tmr-conf-print-workslip" href="#" target="_blank">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 6 2 18 2 18 9"></polyline><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path><rect x="6" y="14" width="12" height="8"></rect></svg>
                                <?php esc_html_e('ওয়ার্ক', 'tailor-manager'); ?>
                            </a>
                            <a class="tmr-btn-outline tmr-btn-sm" id="tmr-conf-print-fullslip" href="#" target="_blank">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 6 2 18 2 18 9"></polyline><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path><rect x="6" y="14" width="12" height="8"></rect></svg>
                                <?php esc_html_e('ফুল', 'tailor-manager'); ?>
                            </a>
                            <button type="button" class="tmr-btn-outline tmr-btn-outline-danger tmr-btn-sm" id="tmr-conf-delete-btn">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
                                <?php esc_html_e('ডিলিট', 'tailor-manager'); ?>
                            </button>
                            <span class="tmr-confirmation-toolbar-divider"></span>
                            <button type="button" class="tmr-conf-toolbar-action" data-action="full-capture" title="<?php echo esc_attr(__('ফুল পেজ স্ক্রিন ক্যাপচার', 'tailor-manager')); ?>">
                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"></path><circle cx="12" cy="13" r="4"></circle></svg>
                            </button>
                            <button type="button" class="tmr-conf-toolbar-action" data-action="dress-capture" title="<?php echo esc_attr(__('শুধু পোশাক স্ক্রিন ক্যাপচার', 'tailor-manager')); ?>">
                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20.38 3.46 16 2a4 4 0 0 1-8 0L3.62 3.46a2 2 0 0 0-1.34 2.23l.58 3.47a1 1 0 0 0 .99.84H6v10c0 .55.45 1 1 1h10c.55 0 1-.45 1-1V10h2.15a1 1 0 0 0 .99-.84l.58-3.47a2 2 0 0 0-1.34-2.23z"></path></svg>
                            </button>
                        </div>
                    </div>
                    <div class="tmr-confirmation-details">
                        <div class="tmr-confirmation-summary-card" id="tmr-conf-summary-card">
                            <div class="tmr-confirmation-summary-header">
                                <span class="tmr-confirmation-summary-order-id" id="tmr-conf-order-id"></span>
                                <div class="tmr-confirmation-summary-customer">
                                    <div class="tmr-confirmation-summary-name-row">
                                        <strong id="tmr-conf-customer-name"></strong>
                                        <span class="tmr-badge tmr-badge-red" id="tmr-conf-urgent-badge" style="display:none;"><?php esc_html_e('জরুরি', 'tailor-manager'); ?></span>
                                    </div>
                                    <span class="tmr-confirmation-summary-phone" id="tmr-conf-customer-phone-row">
                                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 22 16.92z"></path></svg>
                                        <span id="tmr-conf-customer-phone"></span>
                                    </span>
                                </div>
                                <div class="tmr-confirmation-header-actions" id="tmr-conf-customer-actions">
                                    <button type="button" class="tmr-conf-customer-action" data-action="print" title="<?php echo esc_attr(__('প্রিন্ট', 'tailor-manager')); ?>">
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 6 2 18 2 18 9"></polyline><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path><rect x="6" y="14" width="12" height="8"></rect></svg>
                                    </button>
                                    <button type="button" class="tmr-conf-customer-action" data-action="download" title="<?php echo esc_attr(__('ছবি ক্যাপচার', 'tailor-manager')); ?>">
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"></path><circle cx="12" cy="13" r="4"></circle></svg>
                                    </button>
                                    <button type="button" class="tmr-conf-customer-action" data-action="whatsapp" id="tmr-conf-customer-message" title="<?php echo esc_attr(__('মেসেজ', 'tailor-manager')); ?>">
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path></svg>
                                    </button>
                                </div>
                                <span class="tmr-confirmation-status-pill" id="tmr-conf-status-pill">
                                    <span class="tmr-confirmation-status-dot"></span>
                                    <span id="tmr-conf-status-text"></span>
                                </span>
                                <div class="tmr-confirmation-qr" id="tmr-order-confirmation-qr"></div>
                            </div>
                            <div class="tmr-confirmation-summary-pay-row">
                                <div class="tmr-confirmation-pay-card tmr-confirmation-pay-date tmr-confirmation-pay-date-combined">
                                    <span class="tmr-confirmation-pay-icon">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>
                                    </span>
                                    <div class="tmr-confirmation-pay-text">
                                        <span class="tmr-confirmation-date-line">
                                            <span class="tmr-confirmation-chip-label"><?php esc_html_e('অর্ডার', 'tailor-manager'); ?></span>
                                            <strong id="tmr-conf-order-date"></strong>
                                        </span>
                                        <span class="tmr-confirmation-date-line">
                                            <span class="tmr-confirmation-chip-label"><?php esc_html_e('ডেলিভারি', 'tailor-manager'); ?></span>
                                            <strong id="tmr-conf-delivery"></strong>
                                        </span>
                                    </div>
                                </div>
                                <div class="tmr-confirmation-pay-card tmr-confirmation-pay-total">
                                    <span class="tmr-confirmation-pay-icon">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4Z"></path><path d="M3 6h18"></path><path d="M16 10a4 4 0 0 1-8 0"></path></svg>
                                    </span>
                                    <span class="tmr-confirmation-pay-text">
                                        <span class="tmr-confirmation-chip-label"><?php esc_html_e('মোট', 'tailor-manager'); ?></span>
                                        <strong id="tmr-conf-total"></strong>
                                    </span>
                                </div>
                                <div class="tmr-confirmation-pay-card tmr-confirmation-pay-paid">
                                    <span class="tmr-confirmation-pay-icon">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 6 9 17l-5-5"></path></svg>
                                    </span>
                                    <span class="tmr-confirmation-pay-text">
                                        <span class="tmr-confirmation-chip-label"><?php esc_html_e('অগ্রিম', 'tailor-manager'); ?></span>
                                        <strong id="tmr-conf-advance"></strong>
                                    </span>
                                </div>
                                <div class="tmr-confirmation-pay-card tmr-confirmation-pay-due" id="tmr-conf-due-row">
                                    <span class="tmr-confirmation-pay-icon">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
                                    </span>
                                    <span class="tmr-confirmation-pay-text">
                                        <span class="tmr-confirmation-chip-label"><?php esc_html_e('বাকি', 'tailor-manager'); ?></span>
                                        <strong id="tmr-conf-due"></strong>
                                    </span>
                                </div>
                            </div>
                        </div>
                        <div id="tmr-conf-items" class="tmr-confirmation-items"></div>
                    </div>
                    <div class="tmr-confirmation-actions-row">
                        <a href="#" class="tmr-confirmation-view-link" id="tmr-order-confirmation-view"><?php esc_html_e('সম্পূর্ণ অর্ডার দেখুন', 'tailor-manager'); ?> &rarr;</a>
                        <button type="button" class="tmr-confirmation-close-btn tmr-modal-close"><?php esc_html_e('বন্ধ করুন', 'tailor-manager'); ?></button>
                    </div>
                </div>
            </div>
        </div>

        <?php self::render_order_form_script($auto_open_id); ?>
        <?php
        TMR_Panel_Shell::footer();
    }

    private static function dress_summary($order_id)
    {
        $parts = array();
        foreach (TMR_Order_Post_Type::get_items($order_id) as $item) {
            $cat_id = TMR_Order_Item_Post_Type::get_category_id($item->ID);
            $term   = get_term($cat_id, TMR_Category_Taxonomy::TAXONOMY);
            $category_name = $term && !is_wp_error($term) ? $term->name : '';

            foreach (TMR_Order_Item_Post_Type::get_dresses($item->ID) as $d) {
                $dress = !empty($d['dress_id']) ? get_post($d['dress_id']) : null;
                $name  = $dress ? $dress->post_title : $category_name;
                if ($name) {
                    $parts[] = $name . '(' . (int) $d['quantity'] . ')';
                }
            }
        }
        return implode(' ', $parts);
    }

    /**
     * @return string deduplicated "কাটিং মাস্টার/সোয়িং অপারেটর" names across every
     *                 item on this order — a multi-item order can have different
     *                 staff per item (or one person doing both roles), so this
     *                 collapses them into one unique, comma-separated list rather
     *                 than repeating the same name per item.
     */
    private static function staff_summary($order_id)
    {
        $names = array();
        foreach (TMR_Order_Post_Type::get_items($order_id) as $item) {
            $cutter = trim((string) get_post_meta($item->ID, '_tmr_cutter_name', true));
            $tailor = trim((string) get_post_meta($item->ID, '_tmr_tailor_name', true));
            if ('' !== $cutter) {
                $names[$cutter] = true;
            }
            if ('' !== $tailor) {
                $names[$tailor] = true;
            }
        }
        return implode(', ', array_keys($names));
    }

    /**
     * "26 জুলাই, 2026" — day number kept in plain digits (matches the rest of the app,
     * e.g. the dashboard's own date fields), only the month name is Bangla.
     */
    /**
     * Bangla month name + digits, but the token ORDER/punctuation follows
     * wp-admin's own Settings > General > Date Format option — so an admin who
     * reorders that (e.g. "j F, Y" to "F j, Y") sees it reflected here too,
     * instead of a hardcoded "day Month, Year" layout.
     */
    private static function format_date_bn($ymd, $short_year = false)
    {
        if (!$ymd) {
            return '';
        }
        $months = array(
            1 => 'জানুয়ারি', 2 => 'ফেব্রুয়ারি', 3 => 'মার্চ', 4 => 'এপ্রিল',
            5 => 'মে', 6 => 'জুন', 7 => 'জুলাই', 8 => 'আগস্ট',
            9 => 'সেপ্টেম্বর', 10 => 'অক্টোবর', 11 => 'নভেম্বর', 12 => 'ডিসেম্বর',
        );
        $parts = explode('-', $ymd);
        if (count($parts) !== 3) {
            return $ymd;
        }
        list($y, $m, $d) = $parts;
        $m = (int) $m;
        $d = (int) $d;
        if (!isset($months[$m])) {
            return $ymd;
        }

        $format = get_option('date_format', 'F j, Y');
        $output = '';
        $len    = strlen($format);
        for ($i = 0; $i < $len; $i++) {
            $char = $format[$i];
            switch ($char) {
                case 'd':
                    $output .= str_pad($d, 2, '0', STR_PAD_LEFT);
                    break;
                case 'j':
                    $output .= $d;
                    break;
                case 'F':
                case 'M':
                    $output .= $months[$m];
                    break;
                case 'n':
                    $output .= $m;
                    break;
                case 'm':
                    $output .= str_pad($m, 2, '0', STR_PAD_LEFT);
                    break;
                case 'Y':
                    $output .= $short_year ? substr($y, -2) : $y;
                    break;
                case 'y':
                    $output .= substr($y, -2);
                    break;
                case '\\':
                    $i++;
                    if ($i < $len) {
                        $output .= $format[$i];
                    }
                    break;
                default:
                    $output .= $char;
            }
        }
        return $output;
    }

    /* ---------------------------------------------------------------- */
    /* Add / Edit form — rendered inside a modal on the list page, its HTML       */
    /* fetched via ajax_get_order_form() rather than a separate admin page.      */
    /* ---------------------------------------------------------------- */

    private static function render_form_body($order_id)
    {
        $is_edit  = $order_id > 0;
        $order_date = $is_edit ? get_post_meta($order_id, '_tmr_order_date', true) : current_time('Y-m-d');
        $delivery_date = $is_edit ? get_post_meta($order_id, '_tmr_delivery_date', true) : '';

        // A brand-new order has no delivery date yet — pre-fill it from Settings'
        // configurable lead time so the calendar opens with a sensible default already
        // selected instead of forcing every order to be picked manually from scratch.
        if (!$is_edit && '' === $delivery_date) {
            $default_days = (int) get_option('tmr_default_delivery_days', 7);
            $delivery_date = date('Y-m-d', strtotime('+' . $default_days . ' days', current_time('timestamp')));
        }

        $urgent = $is_edit && '1' === get_post_meta($order_id, '_tmr_urgent', true);
        $customer_id = $is_edit ? (int) get_post_meta($order_id, '_tmr_customer_id', true) : 0;
        $customer_label = $customer_id ? get_the_title($customer_id) . ' (' . TMR_Customer_Post_Type::get_phone($customer_id) . ')' : '';
        $wage = $is_edit ? get_post_meta($order_id, '_tmr_wage', true) : '';
        $cloth_price = $is_edit ? get_post_meta($order_id, '_tmr_cloth_price', true) : '';
        $total = $is_edit ? get_post_meta($order_id, '_tmr_total', true) : '';
        $advance = $is_edit ? get_post_meta($order_id, '_tmr_advance', true) : '';
        $due = $is_edit ? get_post_meta($order_id, '_tmr_due', true) : '';
        $image_id = $is_edit ? (int) get_post_meta($order_id, '_tmr_reference_image_id', true) : 0;

        $existing_items = array();
        if ($is_edit) {
            foreach (TMR_Order_Post_Type::get_items($order_id) as $item) {
                $existing_items[TMR_Order_Item_Post_Type::get_category_id($item->ID)] = $item;
            }
        }

        ?>
        <form id="tmr-order-form">
            <input type="hidden" name="order_id" value="<?php echo esc_attr($order_id); ?>" />
            <input type="hidden" name="customer_id" id="tmr-customer-id" value="<?php echo esc_attr($customer_id); ?>" />
            <input type="hidden" name="image_id" id="tmr-order-image-id" value="<?php echo esc_attr($image_id); ?>" />

            <input type="hidden" name="order_date" value="<?php echo esc_attr($order_date); ?>" />

            <div class="tmr-card-plain tmr-highlight-card">
                <div class="tmr-step-header tmr-highlight-header">
                    <h3><?php esc_html_e('কাস্টমার ও ডেলিভারি', 'tailor-manager'); ?></h3>
                </div>

                <div class="tmr-form-row tmr-inline-customer-row">
                    <div class="tmr-inline-field">
                        <label class="tmr-form-label" for="tmr-customer-search"><?php esc_html_e('কাস্টমার', 'tailor-manager'); ?> *</label>
                        <div class="tmr-customer-input-wrap">
                            <input type="text" id="tmr-customer-search" autocomplete="off" placeholder="<?php esc_attr_e('নাম বা মোবাইল নাম্বার লিখুন…', 'tailor-manager'); ?>" value="<?php echo esc_attr($customer_label); ?>" />
                            <button type="button" class="tmr-input-inline-btn" id="tmr-quick-add-customer" title="<?php esc_attr_e('নতুন কাস্টমার যোগ করুন', 'tailor-manager'); ?>">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
                            </button>
                            <div id="tmr-customer-results" style="position:absolute;left:0;right:0;border:1px solid #e2e8f0;border-radius:10px;margin-top:4px;display:none;background:#fff;box-shadow:0 10px 30px rgba(15,23,42,.12);z-index:20;"></div>
                        </div>
                    </div>

                    <div class="tmr-inline-field" style="position:relative;">
                        <label class="tmr-form-label" for="tmr-delivery-date-display"><?php esc_html_e('ডেলিভারি তারিখ', 'tailor-manager'); ?> *</label>
                        <input type="text" id="tmr-delivery-date-display" class="tmr-date-display-input" readonly autocomplete="off" placeholder="<?php esc_attr_e('তারিখ নির্বাচন করুন', 'tailor-manager'); ?>" value="<?php echo esc_attr(self::format_date_bn($delivery_date)); ?>" />
                        <input type="hidden" name="delivery_date" id="tmr-delivery-date" value="<?php echo esc_attr($delivery_date); ?>" required />
                        <div class="tmr-cal-popover" id="tmr-delivery-cal-popover">
                            <div id="tmr-delivery-cal"></div>
                        </div>
                    </div>

                    <div class="tmr-inline-field tmr-inline-toggle-field">
                        <label class="tmr-toggle">
                            <input type="checkbox" name="urgent" value="1" <?php checked($urgent); ?> />
                            <span class="tmr-toggle-slider"></span>
                            <span class="tmr-form-label" style="margin:0;"><?php esc_html_e('জরুরি ডেলিভারি', 'tailor-manager'); ?></span>
                        </label>
                    </div>
                </div>

                <div class="tmr-form-row">
                    <label class="tmr-form-label"><?php esc_html_e('রেফারেন্স ছবি', 'tailor-manager'); ?></label>
                    <div class="tmr-photo-picker">
                        <div class="tmr-photo-preview">
                            <img id="tmr-order-image-preview" src="<?php echo $image_id ? esc_url(wp_get_attachment_image_url($image_id, 'thumbnail')) : ''; ?>" style="width:100%;height:100%;object-fit:cover;<?php echo $image_id ? '' : 'display:none;'; ?>" />
                            <svg id="tmr-order-image-placeholder" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" <?php echo $image_id ? 'style="display:none;"' : ''; ?>><rect x="3" y="3" width="18" height="18" rx="2"></rect><circle cx="8.5" cy="8.5" r="1.5"></circle><path d="M21 15l-5-5L5 21"></path></svg>
                        </div>
                        <button type="button" class="tmr-btn-outline tmr-btn-sm" id="tmr-pick-order-image"><?php esc_html_e('ছবি নির্বাচন করুন', 'tailor-manager'); ?></button>
                    </div>
                </div>
            </div>

            <?php foreach (TMR_Category_Taxonomy::get_terms() as $term) :
                self::render_category_block($term, isset($existing_items[$term->term_id]) ? $existing_items[$term->term_id] : null);
            endforeach; ?>

            <div class="tmr-card-plain tmr-highlight-card">
                <div class="tmr-step-header tmr-highlight-header">
                    <h3><?php esc_html_e('মূল্য', 'tailor-manager'); ?></h3>
                </div>
                <div class="tmr-price-grid">
                    <div class="tmr-price-inputs">
                        <div class="tmr-form-row">
                            <label class="tmr-form-label" for="tmr-wage"><?php esc_html_e('মজুরি', 'tailor-manager'); ?> *</label>
                            <div class="tmr-money-field"><input type="number" step="0.01" name="wage" id="tmr-wage" class="tmr-price-input" value="<?php echo esc_attr($wage); ?>" required /></div>
                        </div>
                        <div class="tmr-form-row">
                            <label class="tmr-form-label" for="tmr-cloth-price"><?php esc_html_e('কাপড়ের দাম', 'tailor-manager'); ?></label>
                            <div class="tmr-money-field"><input type="number" step="0.01" name="cloth_price" id="tmr-cloth-price" class="tmr-price-input" value="<?php echo esc_attr($cloth_price); ?>" /></div>
                        </div>
                        <div class="tmr-form-row">
                            <label class="tmr-form-label" for="tmr-advance"><?php esc_html_e('অগ্রিম', 'tailor-manager'); ?></label>
                            <div class="tmr-money-field"><input type="number" step="0.01" name="advance" id="tmr-advance" class="tmr-price-input" value="<?php echo esc_attr($advance); ?>" /></div>
                        </div>
                    </div>
                    <div class="tmr-price-summary">
                        <div class="tmr-price-summary-row">
                            <span><?php esc_html_e('মোট', 'tailor-manager'); ?></span>
                            <span id="tmr-total-display">৳ 0</span>
                        </div>
                        <div class="tmr-price-summary-row">
                            <span><?php esc_html_e('অগ্রিম', 'tailor-manager'); ?></span>
                            <span id="tmr-advance-display">৳ 0</span>
                        </div>
                        <div class="tmr-price-summary-row tmr-price-summary-due">
                            <span><?php esc_html_e('বাকি', 'tailor-manager'); ?></span>
                            <span id="tmr-due-display">৳ 0</span>
                        </div>
                    </div>
                </div>
                <input type="hidden" name="total" id="tmr-total" value="<?php echo esc_attr($total); ?>" />
                <input type="hidden" name="due" id="tmr-due" value="<?php echo esc_attr($due); ?>" />
            </div>

            <div class="tmr-form-actions">
                <button type="submit" class="tmr-btn-add"><?php esc_html_e('অর্ডার সেভ করুন', 'tailor-manager'); ?></button>
                <button type="button" class="tmr-btn-cancel tmr-modal-close"><?php esc_html_e('বাতিল', 'tailor-manager'); ?></button>
            </div>
        </form>

        <div class="tmr-modal" id="tmr-quick-customer-modal">
            <div class="tmr-modal-content">
                <div class="tmr-modal-head">
                    <h2><?php esc_html_e('নতুন কাস্টমার যোগ করুন', 'tailor-manager'); ?></h2>
                    <button type="button" class="tmr-modal-close">&times;</button>
                </div>
                <form id="tmr-quick-customer-form">
                    <div class="tmr-modal-body">
                        <div class="tmr-form-row"><label class="tmr-form-label"><?php esc_html_e('নাম', 'tailor-manager'); ?> *</label><input type="text" name="name" required /></div>
                        <div class="tmr-form-row"><label class="tmr-form-label"><?php esc_html_e('ফোন', 'tailor-manager'); ?> *</label><input type="text" name="phone" required /></div>
                        <div class="tmr-form-row"><label class="tmr-form-label"><?php esc_html_e('ঠিকানা', 'tailor-manager'); ?></label><textarea name="address" rows="2"></textarea></div>
                    </div>
                    <div class="tmr-modal-foot">
                        <button type="submit" class="tmr-btn-add"><?php esc_html_e('কাস্টমার সেভ করুন', 'tailor-manager'); ?></button>
                    </div>
                </form>
            </div>
        </div>
        <?php
    }

    private static function render_category_block(WP_Term $term, $existing_item)
    {
        $dresses = TMR_Dress_Post_Type::get_by_category($term->slug);
        $parts   = TMR_Dress_Part_Post_Type::get_by_category($term->slug);
        $fields  = TMR_Measurement_Fields::get_for_category($term->slug);

        $selected_dresses = array();
        $cutter_name = '';
        $tailor_name = '';
        $measurements = array();
        $part_selections = array();

        if ($existing_item) {
            foreach (TMR_Order_Item_Post_Type::get_dresses($existing_item->ID) as $d) {
                $selected_dresses[$d['dress_id']] = $d;
            }
            $measurements = TMR_Order_Item_Post_Type::get_measurements($existing_item->ID);
            foreach (TMR_Order_Item_Post_Type::get_part_selections($existing_item->ID) as $sel) {
                $part_selections[$sel['part_id']] = $sel;
            }
            $cutter_name = get_post_meta($existing_item->ID, '_tmr_cutter_name', true);
            $tailor_name = get_post_meta($existing_item->ID, '_tmr_tailor_name', true);
        }

        // dress_id 0 is the sentinel for "this category has no distinct dress products —
        // take the order against the category itself" (see the empty($dresses) branch
        // below). Only relevant/possible when there are no real dress products to check.
        $category_only_qty = isset($selected_dresses[0]) ? (int) $selected_dresses[0]['quantity'] : 0;
        ?>
        <?php $has_data = !empty($selected_dresses); ?>
        <div class="tmr-card-plain tmr-category-block tmr-highlight-card" data-category-id="<?php echo esc_attr($term->term_id); ?>" data-category-slug="<?php echo esc_attr($term->slug); ?>">
            <div class="tmr-step-header tmr-category-block-header tmr-highlight-header">
                <h3><?php echo esc_html($term->name); ?></h3>
                <svg class="tmr-category-chevron<?php echo $has_data ? ' is-open' : ''; ?>" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 9l6 6 6-6"></path></svg>
            </div>

            <div class="tmr-form-row tmr-dress-block">
                <?php if (!empty($dresses)) : ?>
                    <div class="tmr-part-block-title">
                        <span class="tmr-part-block-icon"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M20.59 13.41L13.42 20.58a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"></path><line x1="7" y1="7" x2="7.01" y2="7"></line></svg></span>
                        <?php esc_html_e('ড্রেস ও পরিমাণ (ক্লিক করে নির্বাচন করুন)', 'tailor-manager'); ?>
                    </div>
                    <div class="tmr-checkbox-grid tmr-dress-checkbox-grid">
                        <?php foreach ($dresses as $dress) :
                            $checked = isset($selected_dresses[$dress->ID]);
                            $qty = $checked ? (int) $selected_dresses[$dress->ID]['quantity'] : 0;
                        ?>
                            <label>
                                <input type="checkbox" class="tmr-dress-check" data-dress-id="<?php echo esc_attr($dress->ID); ?>" <?php checked($checked); ?> />
                                <?php if (has_post_thumbnail($dress)) : ?>
                                    <?php echo get_the_post_thumbnail($dress, array(20, 20), array('style' => 'border-radius:5px;object-fit:contain;flex-shrink:0;')); ?>
                                <?php endif; ?>
                                <?php echo esc_html($dress->post_title); ?>
                                <span class="tmr-qty-stepper">
                                    <button type="button" class="tmr-qty-btn tmr-qty-minus" tabindex="-1" aria-label="<?php esc_attr_e('কমান', 'tailor-manager'); ?>">&minus;</button>
                                    <input type="number" min="0" class="tmr-dress-qty" data-dress-id="<?php echo esc_attr($dress->ID); ?>" value="<?php echo esc_attr($checked ? ($qty ?: 1) : 0); ?>" <?php echo $checked ? '' : 'disabled'; ?> />
                                    <button type="button" class="tmr-qty-btn tmr-qty-plus" tabindex="-1" aria-label="<?php esc_attr_e('বাড়ান', 'tailor-manager'); ?>">+</button>
                                </span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                <?php else : ?>
                    <div class="tmr-part-block-title tmr-category-qty-direct">
                        <span><?php esc_html_e('এই পোশাকে এখনো আলাদা কোনো প্রোডাক্ট যোগ করা হয়নি — সরাসরি পরিমাণ দিন', 'tailor-manager'); ?></span>
                        <span class="tmr-qty-stepper">
                            <button type="button" class="tmr-qty-btn tmr-qty-minus" tabindex="-1" aria-label="<?php esc_attr_e('কমান', 'tailor-manager'); ?>">&minus;</button>
                            <input type="number" min="0" class="tmr-category-qty" value="<?php echo esc_attr($category_only_qty); ?>" />
                            <button type="button" class="tmr-qty-btn tmr-qty-plus" tabindex="-1" aria-label="<?php esc_attr_e('বাড়ান', 'tailor-manager'); ?>">+</button>
                        </span>
                    </div>
                <?php endif; ?>
            </div>

            <div class="tmr-category-collapsible" style="<?php echo $has_data ? '' : 'display:none;'; ?>">
                <div class="tmr-form-row tmr-part-block">
                    <div class="tmr-part-block-title">
                        <span class="tmr-part-block-icon"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg></span>
                        <?php esc_html_e('স্টাফ', 'tailor-manager'); ?>
                    </div>
                    <?php
                    self::render_staff_field('tmr-cutter-' . $term->term_id, 'tmr-cutter-name', __('কাটিং মাস্টার', 'tailor-manager'), $cutter_name);
                    self::render_staff_field('tmr-tailor-' . $term->term_id, 'tmr-tailor-name', __('সোয়িং অপারেটর', 'tailor-manager'), $tailor_name);
                    ?>
                </div>

                <?php if ($fields) : ?>
                    <div class="tmr-form-row tmr-part-block">
                        <div class="tmr-part-block-title">
                            <span class="tmr-part-block-icon"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M21.3 15.3a2.4 2.4 0 0 1 0 3.4l-2.6 2.6a2.4 2.4 0 0 1-3.4 0L2.7 8.7a2.4 2.4 0 0 1 0-3.4l2.6-2.6a2.4 2.4 0 0 1 3.4 0z"></path><path d="M14.5 6.5l3 3"></path><path d="M11.5 9.5l1.5 1.5"></path><path d="M8.5 12.5l1.5 1.5"></path></svg></span>
                            <?php esc_html_e('মাপের বিবরণ', 'tailor-manager'); ?>
                        </div>
                        <div class="tmr-measure-grid">
                            <?php foreach ($fields as $slug => $label) : ?>
                                <div class="tmr-measure-field-wrap">
                                    <label class="tmr-form-label" style="font-weight:400;text-transform:none;letter-spacing:0;"><?php echo esc_html($label); ?></label>
                                    <span class="tmr-qty-stepper tmr-measure-stepper">
                                        <button type="button" class="tmr-qty-btn tmr-measure-minus" tabindex="-1" aria-label="<?php esc_attr_e('কমান', 'tailor-manager'); ?>">&minus;</button>
                                        <input type="text" class="tmr-measure-field" data-slug="<?php echo esc_attr($slug); ?>" placeholder="0" value="<?php echo esc_attr(isset($measurements[$slug]) ? $measurements[$slug] : ''); ?>" />
                                        <button type="button" class="tmr-qty-btn tmr-measure-plus" tabindex="-1" aria-label="<?php esc_attr_e('বাড়ান', 'tailor-manager'); ?>">+</button>
                                    </span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <?php foreach ($parts as $part) :
                    $designs = TMR_Design_Type_Post_Type::get_by_part($part->ID);
                    if (empty($designs)) {
                        continue;
                    }
                    $sel = isset($part_selections[$part->ID]) ? $part_selections[$part->ID] : array('design_type_ids' => array(), 'part_measurement' => '', 'note' => '');
                    $selected_ids = array_map('intval', isset($sel['design_type_ids']) ? $sel['design_type_ids'] : array());
                    ?>
                    <div class="tmr-form-row tmr-part-block" data-part-id="<?php echo esc_attr($part->ID); ?>">
                        <div class="tmr-part-block-title">
                            <span class="tmr-part-block-icon"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M20.59 13.41L13.42 20.58a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"></path></svg></span>
                            <?php echo esc_html($part->post_title); ?>
                        </div>
                        <div class="tmr-checkbox-grid">
                            <?php foreach ($designs as $design) : ?>
                                <label>
                                    <input type="checkbox" class="tmr-design-check" value="<?php echo esc_attr($design->ID); ?>" <?php checked(in_array($design->ID, $selected_ids, true)); ?> />
                                    <?php if (has_post_thumbnail($design)) : ?>
                                        <?php echo get_the_post_thumbnail($design, array(24, 24)); ?>
                                    <?php endif; ?>
                                    <?php echo esc_html($design->post_title); ?>
                                </label>
                            <?php endforeach; ?>
                        </div>
                        <?php if (TMR_Dress_Part_Post_Type::measurement_enabled($part->ID)) : ?>
                            <div class="tmr-part-measure-row" style="<?php echo empty($selected_ids) ? 'display:none;' : ''; ?>">
                                <div class="tmr-measure-field-wrap" style="max-width:170px;">
                                    <span class="tmr-form-label" style="margin:0;font-weight:400;text-transform:none;letter-spacing:0;"><?php echo esc_html(TMR_Dress_Part_Post_Type::get_measurement_label($part->ID)); ?></span>
                                    <span class="tmr-qty-stepper tmr-measure-stepper">
                                        <button type="button" class="tmr-qty-btn tmr-part-measure-minus" tabindex="-1" aria-label="<?php esc_attr_e('কমান', 'tailor-manager'); ?>">&minus;</button>
                                        <input type="text" class="tmr-part-measurement" placeholder="0" value="<?php echo esc_attr($sel['part_measurement']); ?>" />
                                        <button type="button" class="tmr-qty-btn tmr-part-measure-plus" tabindex="-1" aria-label="<?php esc_attr_e('বাড়ান', 'tailor-manager'); ?>">+</button>
                                    </span>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php
    }

    /**
     * One label-left/field-right staff combobox row — cutting master and sewing
     * operator are two independent roles/people in a real shop, so each gets its own
     * row instead of sharing one free-text field.
     *
     * @param string $id        unique DOM id for this row's input
     * @param string $css_class value-carrying class the submit JS/PHP reads (tmr-cutter-name or tmr-tailor-name)
     */
    private static function render_staff_field($id, $css_class, $label, $value)
    {
        ?>
        <div class="tmr-staff-field-row">
            <label class="tmr-form-label" for="<?php echo esc_attr($id); ?>"><?php echo esc_html($label); ?></label>
            <div class="tmr-staff-select-wrap">
                <input type="text" id="<?php echo esc_attr($id); ?>" class="<?php echo esc_attr($css_class); ?> tmr-staff-input" autocomplete="off" value="<?php echo esc_attr($value); ?>" placeholder="<?php esc_attr_e('নাম লিখুন বা তালিকা থেকে বেছে নিন…', 'tailor-manager'); ?>" />
                <div class="tmr-staff-suggest-list">
                    <?php foreach (TMR_Staff_Post_Type::get_active() as $staff_member) : ?>
                        <div class="tmr-staff-suggest-item" data-name="<?php echo esc_attr($staff_member->post_title); ?>">
                            <span class="tmr-staff-avatar">
                                <?php if (has_post_thumbnail($staff_member)) : ?>
                                    <?php echo get_the_post_thumbnail($staff_member, array(22, 22), array('style' => 'object-fit:cover;')); ?>
                                <?php else : ?>
                                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
                                <?php endif; ?>
                            </span>
                            <span><?php echo esc_html($staff_member->post_title); ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php
    }

    private static function render_order_form_script($auto_open_id = 0)
    {
        // Bound once on the list page via $(document).on(...) delegation — the
        // form/customer-search/etc. markup itself is injected into the modal body
        // later by ajax_get_order_form(), possibly more than once per page view
        // (open, cancel, open a different order to edit...). Delegated bindings on
        // `document` don't care when their targets appear/disappear, so re-injecting
        // the form HTML never re-registers (and so never duplicates) a handler —
        // only the injected markup itself may safely be replaced wholesale.
        ?>
        <script>
        jQuery(function ($) {
            var $orderModal = $('#tmr-order-modal');
            var $orderModalBody = $('#tmr-order-modal-body');
            var $orderModalTitle = $('#tmr-order-modal-title');
            var $orderConfirmationBody = $('#tmr-order-confirmation-body');
            var currentOrderConfirmation = null;

            var tmrStatusLabels = {
                pending: '<?php echo esc_js(__('পেন্ডিং', 'tailor-manager')); ?>',
                ready: '<?php echo esc_js(__('রেডি', 'tailor-manager')); ?>',
                delivered: '<?php echo esc_js(__('ডেলিভারড', 'tailor-manager')); ?>',
                cancelled: '<?php echo esc_js(__('বাতিল', 'tailor-manager')); ?>'
            };

            // Live search: debounced AJAX re-render of #tmr-orders-list-wrap as the
            // customer search box is typed into, instead of requiring Enter/submit.
            // The input itself lives in the sticky filter bar *outside*
            // #tmr-orders-list-wrap, so re-rendering the wrap on every keystroke never
            // touches (and so never steals focus from) the input being typed into.
            var ordersSearchTimer = null;
            $(document).on('input', '.tmr-orders-search-form input[name="s"]', function () {
                var search = $(this).val();
                var status = $('.tmr-orders-search-form input[name="status"]').val();

                clearTimeout(ordersSearchTimer);
                ordersSearchTimer = setTimeout(function () {
                    TMRPanel.call('tmr_search_orders', { s: search, status: status, paged: 1 }, function (data) {
                        $('#tmr-orders-list-wrap').html(data.html);

                        var url = new URL(window.location.href);
                        if (search) {
                            url.searchParams.set('s', search);
                        } else {
                            url.searchParams.delete('s');
                        }
                        url.searchParams.set('paged', '1');
                        window.history.replaceState(null, '', url.toString());
                    });
                }, 350);
            });

            // Shared by both the modal toolbar's #tmr-conf-status-dropdown and the
            // standalone order-view page's .tmr-view-status-dropdown — updates the
            // trigger button's color/label and which menu item shows as active.
            function tmrSetStatusDropdown($dropdown, statusKey) {
                var $btn = $dropdown.find('.tmr-status-select-btn');
                $btn.attr('class', 'tmr-status-select-btn tmr-status-select-' + statusKey);
                $btn.find('.tmr-status-select-label').text(tmrStatusLabels[statusKey] || statusKey);
                $dropdown.find('.tmr-status-dropdown-item').removeClass('is-active')
                    .filter('[data-status="' + statusKey + '"]').addClass('is-active');
            }

            // Cycled by column position (not matched to field meaning — labels are
            // shop-editable free text, so there's no reliable slug-to-icon mapping) —
            // just gives each measurement card in the confirmation view a distinct icon.
            var tmrMeasureIcons = [
                '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21.3 15.3a2.4 2.4 0 0 1 0 3.4l-2.6 2.6a2.4 2.4 0 0 1-3.4 0L2.7 8.7a2.4 2.4 0 0 1 0-3.4l2.6-2.6a2.4 2.4 0 0 1 3.4 0z"></path><path d="M14.5 6.5l3 3"></path><path d="M11.5 9.5l1.5 1.5"></path><path d="M8.5 12.5l1.5 1.5"></path></svg>',
                '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>',
                '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="5 9 2 12 5 15"></polyline><polyline points="9 5 12 2 15 5"></polyline><polyline points="15 19 12 22 9 19"></polyline><polyline points="19 9 22 12 19 15"></polyline><line x1="2" y1="12" x2="22" y2="12"></line><line x1="12" y1="2" x2="12" y2="22"></line></svg>',
                '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9.59 4.59A2 2 0 1 1 11 8H2m10.59 11.41A2 2 0 1 0 14 16H2m15.73-8.27A2.5 2.5 0 1 1 19.5 12H2"></path></svg>',
                '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="9"></circle></svg>',
                '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8h1a4 4 0 0 1 0 8h-1"></path><path d="M2 8h16v9a4 4 0 0 1-4 4H6a4 4 0 0 1-4-4Z"></path><line x1="6" y1="1" x2="6" y2="4"></line><line x1="10" y1="1" x2="10" y2="4"></line><line x1="14" y1="1" x2="14" y2="4"></line></svg>',
                '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="4" y="4" width="16" height="16" rx="2"></rect></svg>',
                '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20.38 3.46 16 2a4 4 0 0 1-8 0L3.62 3.46a2 2 0 0 0-1.34 2.23l.58 3.47a1 1 0 0 0 .99.84H6v10c0 .55.45 1 1 1h10c.55 0 1-.45 1-1V10h2.15a1 1 0 0 0 .99-.84l.58-3.47a2 2 0 0 0-1.34-2.23z"></path></svg>',
                '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 9-3 9h18s-3-2-3-9"></path><path d="M13.73 21a2 2 0 0 1-3.46 0"></path></svg>'
            ];

            // ===== Delivery-date visible calendar (same self-built month-grid pattern
            // as the cozythai booking calendar) — state re-initialized every time the
            // form markup is (re)injected, since #tmr-delivery-cal is destroyed and
            // recreated along with the rest of the modal body each open. =====
            var bnMonths = ['জানুয়ারি', 'ফেব্রুয়ারি', 'মার্চ', 'এপ্রিল', 'মে', 'জুন', 'জুলাই', 'আগস্ট', 'সেপ্টেম্বর', 'অক্টোবর', 'নভেম্বর', 'ডিসেম্বর'];
            var bnDayHeaders = ['রবি', 'সোম', 'মঙ্গল', 'বুধ', 'বৃহ', 'শুক্র', 'শনি'];
            var calYear, calMonth, calSelected;

            function pad2(n) { return n < 10 ? '0' + n : '' + n; }
            function daysInMonth(y, m) { return new Date(y, m + 1, 0).getDate(); }
            function firstDayOfMonth(y, m) { return new Date(y, m, 1).getDay(); }

            function formatDeliveryDisplay(dateStr) {
                if (!dateStr) { return ''; }
                var p = dateStr.split('-');
                return parseInt(p[2], 10) + ' ' + bnMonths[parseInt(p[1], 10) - 1] + ', ' + p[0];
            }

            function renderDeliveryCalendar() {
                var firstDay = firstDayOfMonth(calYear, calMonth);
                var days = daysInMonth(calYear, calMonth);
                var today = new Date();
                var todayStr = today.getFullYear() + '-' + pad2(today.getMonth() + 1) + '-' + pad2(today.getDate());

                var html = '<div class="tmr-cal-nav">';
                html += '<button type="button" class="tmr-cal-nav-btn" data-action="prev">&lsaquo;</button>';
                html += '<span class="tmr-cal-title">' + bnMonths[calMonth] + ' ' + calYear + '</span>';
                html += '<button type="button" class="tmr-cal-nav-btn" data-action="next">&rsaquo;</button>';
                html += '</div>';
                html += '<div class="tmr-cal-grid">';
                bnDayHeaders.forEach(function (d) { html += '<span class="tmr-cal-day-header">' + d + '</span>'; });
                for (var i = 0; i < firstDay; i++) { html += '<span class="tmr-cal-day empty"></span>'; }
                for (var d = 1; d <= days; d++) {
                    var dateStr = calYear + '-' + pad2(calMonth + 1) + '-' + pad2(d);
                    var cls = 'tmr-cal-day';
                    if (dateStr === todayStr) { cls += ' today'; }
                    if (dateStr < todayStr) { cls += ' past'; }
                    if (dateStr === calSelected) { cls += ' selected'; }
                    html += '<span class="' + cls + '" data-date="' + dateStr + '">' + d + '</span>';
                }
                html += '</div>';
                $('#tmr-delivery-cal').html(html);
            }

            function initDeliveryCalendar() {
                var initial = $('#tmr-delivery-date').val();
                var d = initial ? new Date(initial + 'T00:00:00') : new Date();
                calYear = d.getFullYear();
                calMonth = d.getMonth();
                calSelected = initial || '';
                renderDeliveryCalendar();
            }

            // Every category block whose dress-selection already has data (edit mode)
            // starts uncollapsed server-side; newly checking a dress in a still-collapsed
            // block auto-reveals its cutter/measurement/design section — the manual
            // chevron toggle stays available regardless for browsing without selecting.
            function initCategoryCollapse() {
                $('.tmr-category-chevron').each(function () {
                    var $chevron = $(this);
                    var $collapsible = $chevron.closest('.tmr-category-block').find('.tmr-category-collapsible');
                    $collapsible.toggle($chevron.hasClass('is-open'));
                });
            }

            // "মাপের বিবরণ" boxes light up (blue border/tint) the moment they carry a
            // real value, so at a glance it's obvious which measurements are still
            // blank — same "filled = highlighted" language as the dress/design chips'
            // own :has(input:checked) treatment, just driven by JS since a text value
            // (not a checkbox) can't be read with a pure :has() selector.
            function updateMeasureFieldState($input) {
                // 0 (the stepper's own resting/empty state) reads as "not measured yet",
                // same as a blank field — only a real positive value counts as filled in.
                var num = parseFloat($input.val());
                var hasValue = !isNaN(num) && num > 0;
                $input.closest('.tmr-measure-field-wrap').toggleClass('tmr-measure-has-value', hasValue);
            }

            function initMeasureFieldStates() {
                $('.tmr-measure-field, .tmr-part-measurement').each(function () {
                    updateMeasureFieldState($(this));
                });
            }

            // A part's own extra measurement row only makes sense once one of its
            // designs is actually picked — stays hidden otherwise instead of showing
            // an input with nothing to measure yet.
            function updatePartMeasureVisibility($partBlock) {
                var anyChecked = $partBlock.find('.tmr-design-check:checked').length > 0;
                $partBlock.find('.tmr-part-measure-row').toggle(anyChecked);
            }

            function initPartMeasureVisibility() {
                $('.tmr-part-block[data-part-id]').each(function () {
                    updatePartMeasureVisibility($(this));
                });
            }

            $(document).on('change', '.tmr-design-check', function () {
                updatePartMeasureVisibility($(this).closest('.tmr-part-block'));
            });

            // Total and due are derived, not typed in — total = wage + cloth price,
            // due = total - advance. Kept as hidden fields (still submitted with the
            // rest of the form, so ajax_save() needed no changes) with a read-only
            // summary card showing the live result instead of an editable number box.
            function formatMoney(n) {
                n = Math.round((parseFloat(n) || 0) * 100) / 100;
                return '৳ ' + n.toLocaleString('en-US');
            }

            function recalcPricing() {
                var wage = parseFloat($('#tmr-wage').val()) || 0;
                var clothPrice = parseFloat($('#tmr-cloth-price').val()) || 0;
                var advance = parseFloat($('#tmr-advance').val()) || 0;
                var total = wage + clothPrice;
                var due = total - advance;

                $('#tmr-total').val(total);
                $('#tmr-due').val(due);
                $('#tmr-total-display').text(formatMoney(total));
                $('#tmr-advance-display').text(formatMoney(advance));
                $('#tmr-due-display').text(formatMoney(due));
            }

            $(document).on('input', '.tmr-price-input', recalcPricing);

            function loadOrderForm(id) {
                $orderModalTitle.text(id ? '<?php echo esc_js(__('অর্ডার আপডেট করুন', 'tailor-manager')); ?>' : '<?php echo esc_js(__('অর্ডার নিন', 'tailor-manager')); ?>');
                $orderConfirmationBody.hide();
                $orderModalBody.show().html('<div class="tmr-empty"><?php echo esc_js(__('লোড হচ্ছে…', 'tailor-manager')); ?></div>');
                TMRPanel.openModal($orderModal);
                TMRPanel.call('tmr_get_order_form', { id: id || 0 }, function (data) {
                    $orderModalBody.html(data.html);
                    initDeliveryCalendar();
                    initCategoryCollapse();
                    initMeasureFieldStates();
                    initPartMeasureVisibility();
                    recalcPricing();
                });
            }

            $(document).on('click', '#tmr-open-order-modal', function (e) {
                e.preventDefault();
                loadOrderForm(0);
            });

            $(document).on('click', '.tmr-open-order-modal-edit', function (e) {
                e.preventDefault();
                loadOrderForm($(this).data('id'));
            });

            $(document).on('click', '#tmr-delivery-date-display', function (e) {
                e.stopPropagation();
                $('#tmr-delivery-cal-popover').toggle();
            });

            $(document).on('click', '#tmr-delivery-cal-popover .tmr-cal-nav-btn', function (e) {
                // Re-rendering below replaces this very button's DOM node, so it must
                // stop the event from reaching the "click outside closes the popover"
                // handler further down — otherwise that handler inspects e.target
                // *after* it's already been detached from the document, closest()
                // finds nothing, and the popover incorrectly closes on every nav click.
                e.stopPropagation();
                if ($(this).data('action') === 'prev') {
                    calMonth--;
                    if (calMonth < 0) { calMonth = 11; calYear--; }
                } else {
                    calMonth++;
                    if (calMonth > 11) { calMonth = 0; calYear++; }
                }
                renderDeliveryCalendar();
            });

            $(document).on('click', '#tmr-delivery-cal-popover .tmr-cal-day:not(.empty):not(.past)', function (e) {
                e.stopPropagation();
                calSelected = $(this).data('date');
                $('#tmr-delivery-date').val(calSelected);
                $('#tmr-delivery-date-display').val(formatDeliveryDisplay(calSelected));
                $('#tmr-delivery-cal-popover').hide();
                renderDeliveryCalendar();
            });

            $(document).on('click', function (e) {
                if (!$(e.target).closest('#tmr-delivery-cal-popover, #tmr-delivery-date-display').length) {
                    $('#tmr-delivery-cal-popover').hide();
                }
            });

            $(document).on('click', '.tmr-category-block-header', function () {
                var $collapsible = $(this).closest('.tmr-category-block').find('.tmr-category-collapsible');
                $collapsible.slideToggle(150);
                $(this).find('.tmr-category-chevron').toggleClass('is-open');
            });

            $(document).on('change', '.tmr-dress-check', function () {
                var $qty = $(this).closest('label').find('.tmr-dress-qty');
                $qty.prop('disabled', !this.checked);
                if (this.checked) {
                    if (!$qty.val() || parseInt($qty.val(), 10) < 1) {
                        $qty.val(1);
                    }
                    var $block = $(this).closest('.tmr-category-block');
                    var $collapsible = $block.find('.tmr-category-collapsible');
                    if (!$collapsible.is(':visible')) {
                        $collapsible.slideDown(150);
                        $block.find('.tmr-category-chevron').addClass('is-open');
                    }
                } else {
                    $qty.val(0);
                }
            });

            $(document).on('click', '.tmr-qty-plus, .tmr-qty-minus', function (e) {
                e.preventDefault();
                var $input = $(this).siblings('.tmr-dress-qty, .tmr-category-qty');
                if ($input.prop('disabled')) {
                    return;
                }
                var val = parseInt($input.val(), 10) || 0;
                val = $(this).hasClass('tmr-qty-plus') ? val + 1 : Math.max(0, val - 1);
                $input.val(val).trigger('input');
            });

            // Categories with no dress products yet skip the checkbox grid entirely (see
            // render_category_block()) — typing a quantity directly is what "activates"
            // that category's block, same role checking a dress checkbox plays elsewhere.
            $(document).on('input', '.tmr-category-qty', function () {
                var qty = parseInt($(this).val(), 10) || 0;
                var $block = $(this).closest('.tmr-category-block');
                var $collapsible = $block.find('.tmr-category-collapsible');
                if (qty > 0 && !$collapsible.is(':visible')) {
                    $collapsible.slideDown(150);
                    $block.find('.tmr-category-chevron').addClass('is-open');
                }
            });

            $(document).on('click', '.tmr-measure-plus, .tmr-measure-minus', function (e) {
                e.preventDefault();
                var $input = $(this).siblings('.tmr-measure-field');
                var val = parseInt($input.val(), 10) || 0;
                val = $(this).hasClass('tmr-measure-plus') ? val + 1 : Math.max(0, val - 1);
                $input.val(val);
                updateMeasureFieldState($input);
            });

            $(document).on('input', '.tmr-measure-field', function () {
                updateMeasureFieldState($(this));
            });

            $(document).on('click', '.tmr-part-measure-plus, .tmr-part-measure-minus', function (e) {
                e.preventDefault();
                var $input = $(this).siblings('.tmr-part-measurement');
                var val = parseInt($input.val(), 10) || 0;
                val = $(this).hasClass('tmr-part-measure-plus') ? val + 1 : Math.max(0, val - 1);
                $input.val(val);
                updateMeasureFieldState($input);
            });

            $(document).on('input', '.tmr-part-measurement', function () {
                updateMeasureFieldState($(this));
            });

            // Cutter/tailor picker — a typeable combobox (not a rigid <select>) since
            // cutter_name deliberately stays free text: it's what "My Orders" matches
            // against a staff login's display name, and a shop's cutter/tailor might not
            // have a directory entry (or account) yet. The whole staff list is already in
            // the DOM per category block (small, bounded dataset), so filtering is a plain
            // client-side substring match — no AJAX round-trip needed like customer search.
            $(document).on('focus click', '.tmr-staff-input', function () {
                $(this).siblings('.tmr-staff-suggest-list').show();
            });

            $(document).on('input', '.tmr-staff-input', function () {
                var term = $(this).val().trim().toLowerCase();
                $(this).siblings('.tmr-staff-suggest-list').show().find('.tmr-staff-suggest-item').each(function () {
                    var name = ($(this).data('name') + '').toLowerCase();
                    $(this).toggle(!term || name.indexOf(term) !== -1);
                });
            });

            $(document).on('click', '.tmr-staff-suggest-item', function () {
                var $wrap = $(this).closest('.tmr-staff-select-wrap');
                $wrap.find('.tmr-staff-input').val($(this).data('name'));
                $wrap.find('.tmr-staff-suggest-list').hide();
            });

            $(document).on('click', function (e) {
                if (!$(e.target).closest('.tmr-staff-select-wrap').length) {
                    $('.tmr-staff-suggest-list').hide();
                }
            });

            var searchTimer;
            $(document).on('input', '#tmr-customer-search', function () {
                var $input = $(this);
                var $results = $('#tmr-customer-results');
                clearTimeout(searchTimer);
                var term = $input.val();
                if (term.length < 2) {
                    $results.hide().empty();
                    return;
                }
                searchTimer = setTimeout(function () {
                    TMRPanel.call('tmr_search_customers', { term: term }, function (data) {
                        $results.empty();
                        if (!data.length) {
                            $results.hide();
                            return;
                        }
                        data.forEach(function (c) {
                            var $row = $('<div style="padding:6px 10px;cursor:pointer;border-bottom:1px solid var(--tmr-border);"></div>')
                                .text(c.name + ' (' + c.phone + ')')
                                .on('click', function () {
                                    $('#tmr-customer-id').val(c.id);
                                    $input.val(c.name + ' (' + c.phone + ')');
                                    $results.hide().empty();
                                });
                            $results.append($row);
                        });
                        $results.show();
                    });
                }, 300);
            });

            $(document).on('click', '#tmr-quick-add-customer', function () {
                $('#tmr-quick-customer-form')[0].reset();
                TMRPanel.openModal($('#tmr-quick-customer-modal'));
            });

            $(document).on('submit', '#tmr-quick-customer-form', function (e) {
                e.preventDefault();
                var $form = $(this);
                TMRPanel.call('tmr_quick_add_customer', $form.serialize(), function (data) {
                    $('#tmr-customer-id').val(data.id);
                    $('#tmr-customer-search').val(data.name + ' (' + data.phone + ')');
                    TMRPanel.closeModal($('#tmr-quick-customer-modal'));
                });
            });

            var orderImageFrame;
            $(document).on('click', '#tmr-pick-order-image', function (e) {
                e.preventDefault();
                if (!orderImageFrame) {
                    orderImageFrame = wp.media({ title: '<?php echo esc_js(__('রেফারেন্স ছবি নির্বাচন করুন', 'tailor-manager')); ?>', multiple: false });
                    orderImageFrame.on('select', function () {
                        var attachment = orderImageFrame.state().get('selection').first().toJSON();
                        $('#tmr-order-image-id').val(attachment.id);
                        $('#tmr-order-image-preview').attr('src', attachment.sizes && attachment.sizes.thumbnail ? attachment.sizes.thumbnail.url : attachment.url).show();
                        $('#tmr-order-image-placeholder').hide();
                    });
                }
                orderImageFrame.open();
            });

            $(document).on('submit', '#tmr-order-form', function (e) {
                e.preventDefault();

                if (!$('#tmr-customer-id').val()) {
                    window.alert('<?php echo esc_js(__('একজন কাস্টমার নির্বাচন করুন।', 'tailor-manager')); ?>');
                    return;
                }

                var categories = [];
                $('.tmr-category-block').each(function () {
                    var $block = $(this);
                    var dresses = [];
                    $block.find('.tmr-dress-check:checked').each(function () {
                        dresses.push({
                            dress_id: $(this).data('dress-id'),
                            quantity: $block.find('.tmr-dress-qty[data-dress-id="' + $(this).data('dress-id') + '"]').val() || 1
                        });
                    });

                    var $categoryQty = $block.find('.tmr-category-qty');
                    if ($categoryQty.length) {
                        var directQty = parseInt($categoryQty.val(), 10) || 0;
                        if (directQty > 0) {
                            dresses.push({ dress_id: 0, quantity: directQty });
                        }
                    }

                    if (!dresses.length) {
                        return;
                    }

                    var measurements = {};
                    $block.find('.tmr-measure-field').each(function () {
                        measurements[$(this).data('slug')] = $(this).val();
                    });

                    var partSelections = [];
                    $block.find('.tmr-part-block').each(function () {
                        var $partBlock = $(this);
                        var ids = [];
                        $partBlock.find('.tmr-design-check:checked').each(function () {
                            ids.push($(this).val());
                        });
                        if (!ids.length) {
                            return;
                        }
                        partSelections.push({
                            part_id: $partBlock.data('part-id'),
                            design_type_ids: ids,
                            part_measurement: $partBlock.find('.tmr-part-measurement').val() || ''
                        });
                    });

                    categories.push({
                        category_id: $block.data('category-id'),
                        cutter_name: $block.find('.tmr-cutter-name').val() || '',
                        tailor_name: $block.find('.tmr-tailor-name').val() || '',
                        dresses: dresses,
                        measurements: measurements,
                        part_selections: partSelections
                    });
                });

                if (!categories.length) {
                    window.alert('<?php echo esc_js(__('কমপক্ষে একটি ড্রেস নির্বাচন করুন।', 'tailor-manager')); ?>');
                    return;
                }

                var payload = $('#tmr-order-form').serializeArray();
                payload.push({ name: 'items', value: JSON.stringify(categories) });

                TMRPanel.call('tmr_save_order', $.param(payload), function (data) {
                    showOrderConfirmation(data);
                });
            });

            // Post-save summary + QR, in the same modal (no more redirecting away) —
            // same recipe as the doctor-appointment plugin's own booking-confirmation
            // card: qrcode.js for the code, a hand-drawn <canvas> card for "Download as
            // Image", and window.print() + a @media print rule for "Print / Save as PDF".
            function showOrderConfirmation(data, modalTitle) {
                currentOrderConfirmation = data;

                // The "✓ অর্ডার সম্পন্ন হয়েছে" heading only makes sense right after a
                // save — when re-opening an existing order (view mode, signalled by a
                // modalTitle being passed) it's misleading, so it's hidden and the
                // customer/payment cards get the extra visual weight instead.
                $('.tmr-confirmation-heading').toggle(!modalTitle);

                $('#tmr-conf-order-id').text('#' + data.id);
                $('#tmr-conf-customer-name').text(data.customer_name);
                $('#tmr-conf-customer-phone').text(data.customer_phone);
                $('#tmr-conf-customer-phone-row').toggle(!!data.customer_phone);
                $('#tmr-conf-customer-message').toggle(!!data.customer_phone);
                $('#tmr-conf-order-date').text(data.order_date);
                $('#tmr-conf-delivery').text(data.delivery_date);
                $('#tmr-conf-total').text(formatMoney(data.total));
                $('#tmr-conf-advance').text(formatMoney(data.advance));
                $('#tmr-conf-due').text(formatMoney(data.due));
                $('#tmr-order-confirmation-view').attr('href', data.view_url);

                // Same status/edit/print/delete toolbar the standalone order-view page
                // has, so the modal doesn't need a trip to that page for any of it.
                var statusKey = data.status_key || 'pending';
                $('#tmr-conf-status-pill').attr('class', 'tmr-confirmation-status-pill tmr-confirmation-status-' + statusKey);
                $('#tmr-conf-status-text').text(statusKey.charAt(0).toUpperCase() + statusKey.slice(1));
                $('#tmr-conf-urgent-badge').toggle(!!data.urgent);
                tmrSetStatusDropdown($('#tmr-conf-status-dropdown'), statusKey);
                $('#tmr-conf-edit-btn').data('id', data.id);
                $('#tmr-conf-print-receipt').attr('href', data.print_receipt_url);
                $('#tmr-conf-print-workslip').attr('href', data.print_workslip_url);
                $('#tmr-conf-print-fullslip').attr('href', data.print_fullslip_url);
                $('#tmr-conf-delete-btn').data('id', data.id);

                // Each dress/category item becomes its own card, same header-bar +
                // heading/value-cell language as the customer/payment card above it —
                // just without the full-width span, so dress 1/2/3… still flow two per
                // row in the same grid (dress 1 bottom-left, dress 2 bottom-right, …).
                var $items = $('#tmr-conf-items').empty();
                (data.items || []).forEach(function (item, idx) {
                    var dresses = item.dresses || [];
                    if (!dresses.length && !item.measurements.length) { return; }
                    var $block = $('<div class="tmr-confirmation-summary-card tmr-conf-item"></div>');
                    // Category + dress name(s) together on the left (like the customer
                    // name above), total quantity as a badge right next to it (not
                    // detached on the far side of the row).
                    var heading = item.category;
                    if (dresses.length) {
                        heading += ' — ' + dresses.map(function (d) { return d.name; }).join(', ');
                    }
                    var totalQty = dresses.reduce(function (sum, d) { return sum + (parseInt(d.quantity, 10) || 0); }, 0);
                    var $header = $('<div class="tmr-confirmation-summary-header tmr-confirmation-item-header tmr-conf-item-collapse-header"></div>');
                    var $headerLeft = $('<div class="tmr-confirmation-item-header-left"></div>')
                        .append($('<div class="tmr-confirmation-summary-customer"></div>').append($('<strong></strong>').text(heading)));
                    if (totalQty > 0) {
                        $headerLeft.append($('<span class="tmr-confirmation-summary-order-id tmr-conf-item-badge"></span>').text('×' + totalQty));
                    }
                    $header.append($headerLeft);

                    // Cutting master / sewing operator — white pills, true-centered in
                    // the row (equal flex:1 left/right zones push this middle one to
                    // the exact center regardless of how wide the title or badge are).
                    var $staffCenter = $('<div class="tmr-confirmation-item-header-center"></div>');
                    if (item.cutter) {
                        $staffCenter.append($('<span class="tmr-vp-staff-inline"></span>').text('<?php echo esc_js(__('কাটিং মাস্টার', 'tailor-manager')); ?>: ').append($('<strong></strong>').text(item.cutter)));
                    }
                    if (item.tailor) {
                        $staffCenter.append($('<span class="tmr-vp-staff-inline"></span>').text('<?php echo esc_js(__('সোয়িং অপারেটর', 'tailor-manager')); ?>: ').append($('<strong></strong>').text(item.tailor)));
                    }
                    $header.append($staffCenter);

                    // Print / download-as-image / WhatsApp for just this dress, then
                    // the collapse chevron.
                    var $headerRight = $('<div class="tmr-confirmation-item-header-right"></div>');
                    $headerRight.append(
                        $('<button type="button" class="tmr-conf-item-action" data-action="print" data-idx="' + idx + '" title="<?php echo esc_attr(__('প্রিন্ট', 'tailor-manager')); ?>"></button>')
                            .html('<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 6 2 18 2 18 9"></polyline><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path><rect x="6" y="14" width="12" height="8"></rect></svg>')
                    );
                    $headerRight.append(
                        $('<button type="button" class="tmr-conf-item-action" data-action="download" data-idx="' + idx + '" title="<?php echo esc_attr(__('ছবি ডাউনলোড', 'tailor-manager')); ?>"></button>')
                            .html('<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"></path><circle cx="12" cy="13" r="4"></circle></svg>')
                    );
                    $headerRight.append(
                        $('<button type="button" class="tmr-conf-item-action" data-action="whatsapp" data-idx="' + idx + '" title="<?php echo esc_attr(__('হোয়াটসঅ্যাপ', 'tailor-manager')); ?>"></button>')
                            .html('<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path></svg>')
                    );
                    $headerRight.append('<svg class="tmr-conf-item-chevron is-open" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 9l6 6 6-6"></path></svg>');
                    $header.append($headerRight);
                    $block.append($header);

                    // Measurements / design — same sub-sections the standalone
                    // order-view page shows per dress, inside one padded body block.
                    var $body = $('<div class="tmr-conf-item-body"></div>');
                    if (item.measurements.length) {
                        // One compact pill per field — icon, label, value and unit all
                        // in a single row (not stacked), sized to its own content
                        // instead of stretching to fill a grid track, wrapping to more
                        // rows as needed.
                        $body.append($('<div class="tmr-vp-block-title"></div>').text('<?php echo esc_js(__('মাপের বিবরণ', 'tailor-manager')); ?>'));
                        var $mGrid = $('<div class="tmr-vp-measure-grid"></div>');
                        item.measurements.forEach(function (m, idx) {
                            var $card = $('<div class="tmr-vp-measure-card"></div>');
                            $card.append($('<span class="tmr-vp-measure-icon"></span>').html(tmrMeasureIcons[idx % tmrMeasureIcons.length]));
                            $card.append($('<span class="tmr-vp-measure-label"></span>').text(m.label));
                            $card.append($('<strong class="tmr-vp-measure-value"></strong>').text(m.value));
                            $mGrid.append($card);
                        });
                        $body.append($mGrid);
                    }
                    if (item.parts && item.parts.length) {
                        $body.append($('<div class="tmr-vp-block-title"></div>').text('<?php echo esc_js(__('ডিজাইন', 'tailor-manager')); ?>'));
                        // Same read-only "icon + label, value pinned right" card look
                        // as the order form's own dress/design selector boxes — just a
                        // static value box instead of a live +/- stepper.
                        var $partGrid = $('<div class="tmr-vp-design-grid"></div>');
                        item.parts.forEach(function (p) {
                            var $card = $('<div class="tmr-vp-design-card"></div>');
                            var $iconBlock = $('<div class="tmr-vp-design-icon-block"></div>').append(
                                $('<span class="tmr-vp-design-icon-circle"></span>').html('<svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20.38 3.46 16 2a4 4 0 0 1-8 0L3.62 3.46a2 2 0 0 0-1.34 2.23l.58 3.47a1 1 0 0 0 .99.84H6v10c0 .55.45 1 1 1h10c.55 0 1-.45 1-1V10h2.15a1 1 0 0 0 .99-.84l.58-3.47a2 2 0 0 0-1.34-2.23z"></path></svg>')
                            );
                            if (p.extra_value) {
                                $iconBlock.append($('<span class="tmr-vp-design-qty"></span>').text(p.extra_value));
                            }
                            $card.append($iconBlock);
                            $card.append(
                                $('<div class="tmr-vp-design-body"></div>')
                                    .append($('<span class="tmr-vp-design-label"></span>').text(p.name))
                                    .append($('<span class="tmr-vp-design-text"></span>').text((p.designs || []).join(', ')))
                            );
                            $partGrid.append($card);
                        });
                        $body.append($partGrid);
                    }
                    if ($body.children().length) {
                        $block.append($body);
                    }
                    $items.append($block);
                });

                var $qr = $('#tmr-order-confirmation-qr');
                $qr.empty();
                if (typeof qrcode === 'function') {
                    var qr = qrcode(0, 'M');
                    qr.addData(data.view_url);
                    qr.make();
                    $qr.html(qr.createImgTag(5, 4));
                }

                $orderModalTitle.text(modalTitle || '<?php echo esc_js(__('অর্ডার সম্পন্ন হয়েছে', 'tailor-manager')); ?>');
                $orderModalBody.hide();
                $orderConfirmationBody.show();
            }

            // Reuses the exact same read-only summary the post-save flow shows — lets
            // you re-open any existing order's card straight from the list (no need to
            // create a fresh order just to see how the summary/QR/download look).
            function viewOrderSummary(id) {
                $orderModalTitle.text('<?php echo esc_js(__('অর্ডার সারাংশ', 'tailor-manager')); ?>');
                $orderModalBody.hide();
                $orderConfirmationBody.hide();
                TMRPanel.openModal($orderModal);
                TMRPanel.call('tmr_get_order_summary', { id: id }, function (data) {
                    showOrderConfirmation(data, '#' + data.id + ' — ' + data.customer_name);
                });
            }

            $(document).on('click', '.tmr-view-order-trigger', function (e) {
                e.preventDefault();
                viewOrderSummary($(this).data('id'));
            });

            // Self-built status dropdown (button + popover menu), same pattern as the
            // delivery-date calendar popover — used both by the modal toolbar's
            // #tmr-conf-status-dropdown and the standalone view page's
            // .tmr-view-status-dropdown. A native <select> was tried first, but its
            // OS-drawn arrow/menu chrome can't be fully suppressed cross-browser and
            // ended up looking broken next to this plugin's other custom controls.
            $(document).on('click', '.tmr-status-select-btn', function (e) {
                e.stopPropagation();
                var $dropdown = $(this).closest('.tmr-status-dropdown');
                var wasOpen = $dropdown.hasClass('is-open');
                $('.tmr-status-dropdown').removeClass('is-open');
                if (!wasOpen) { $dropdown.addClass('is-open'); }
            });

            $(document).on('click', function (e) {
                if (!$(e.target).closest('.tmr-status-dropdown').length) {
                    $('.tmr-status-dropdown').removeClass('is-open');
                }
            });

            // Confirmation-modal toolbar — same actions as the standalone order-view
            // page's header, minus the page trip: changing status refreshes the modal
            // in place, edit reuses the existing .tmr-open-order-modal-edit handler.
            $(document).on('click', '#tmr-conf-status-dropdown .tmr-status-dropdown-item', function () {
                var newStatus = $(this).data('status');
                $('#tmr-conf-status-dropdown').removeClass('is-open');
                if (!currentOrderConfirmation) { return; }
                // Delivery was already a confirm-worthy action before this became a
                // dropdown (a mis-click risk); kept only for that one transition since
                // picking an option from an open dropdown is already a deliberate
                // two-step action for everything else.
                if ('delivered' === newStatus && !TMRPanel.confirmDelete('<?php echo esc_js(__('এই অর্ডারটির ডেলিভারি স্ট্যাটাস পরিবর্তন করবেন?', 'tailor-manager')); ?>')) {
                    return;
                }
                TMRPanel.call('tmr_set_order_status', { id: currentOrderConfirmation.id, status: newStatus }, function () {
                    viewOrderSummary(currentOrderConfirmation.id);
                });
            });

            $(document).on('click', '#tmr-conf-delete-btn', function () {
                if (!TMRPanel.confirmDelete('<?php echo esc_js(__('এই অর্ডারটি ডিলিট করবেন?', 'tailor-manager')); ?>')) {
                    return;
                }
                TMRPanel.call('tmr_delete_order', { id: currentOrderConfirmation.id }, function () {
                    window.location.reload();
                });
            });

            $(document).on('click', '.tmr-delete-order', function () {
                if (!TMRPanel.confirmDelete('<?php echo esc_js(__('এই অর্ডারটি ডিলিট করবেন?', 'tailor-manager')); ?>')) {
                    return;
                }
                var id = $(this).data('id');
                TMRPanel.call('tmr_delete_order', { id: id }, function () {
                    window.location.reload();
                });
            });

            // Collapsible dress cards — same header-click + chevron-flip pattern the
            // standalone order-view page uses for its category cards, just scoped to
            // this modal's dynamically-built item cards.
            $(document).on('click', '.tmr-conf-item-collapse-header', function (e) {
                if ($(e.target).closest('.tmr-conf-item-action').length) { return; }
                $(this).next('.tmr-conf-item-body').slideToggle(150);
                $(this).find('.tmr-conf-item-chevron').toggleClass('is-open');
            });

            function tmrItemSummaryText(item) {
                var dresses = item.dresses || [];
                var lines = [item.category + (dresses.length ? ' — ' + dresses.map(function (d) { return d.name; }).join(', ') : '')];
                if (item.cutter) { lines.push('<?php echo esc_js(__('কাটিং মাস্টার', 'tailor-manager')); ?>: ' + item.cutter); }
                if (item.tailor) { lines.push('<?php echo esc_js(__('সোয়িং অপারেটর', 'tailor-manager')); ?>: ' + item.tailor); }
                if (item.measurements && item.measurements.length) {
                    lines.push('<?php echo esc_js(__('মাপ', 'tailor-manager')); ?>: ' + item.measurements.map(function (m) { return m.label + ' ' + m.value; }).join(', '));
                }
                if (item.parts && item.parts.length) {
                    lines.push('<?php echo esc_js(__('ডিজাইন', 'tailor-manager')); ?>: ' + item.parts.map(function (p) { return p.name + ' - ' + (p.designs || []).join('/'); }).join(', '));
                }
                return lines.join('\n');
            }

            // Fallback only — a hand-drawn card from the raw data, used if the real
            // DOM screenshot below can't run (very old browser, tainted canvas, etc).
            function tmrDownloadItemImageFallback(item) {
                var dresses = item.dresses || [];
                var heading = item.category + (dresses.length ? ' — ' + dresses.map(function (d) { return d.name; }).join(', ') : '');
                var rows = [];
                if (item.cutter) { rows.push({ label: '<?php echo esc_js(__('কাটিং মাস্টার', 'tailor-manager')); ?>', value: item.cutter }); }
                if (item.tailor) { rows.push({ label: '<?php echo esc_js(__('সোয়িং অপারেটর', 'tailor-manager')); ?>', value: item.tailor }); }
                (item.measurements || []).forEach(function (m) { rows.push({ label: m.label, value: m.value }); });
                (item.parts || []).forEach(function (p) { rows.push({ label: p.name, value: (p.designs || []).join(', ') }); });

                var W = 380, pad = 20, lineH = 26, titleH = 34, scale = 2;
                var H = titleH + pad + rows.length * lineH + pad;
                var canvas = document.createElement('canvas');
                canvas.width = W * scale;
                canvas.height = H * scale;
                canvas.style.width = W + 'px';
                canvas.style.height = H + 'px';
                var ctx = canvas.getContext('2d');
                ctx.scale(scale, scale);
                ctx.fillStyle = '#ffffff';
                ctx.fillRect(0, 0, W, H);
                tmrRoundRect(ctx, 0.5, 0.5, W - 1, H - 1, 14);
                ctx.strokeStyle = '#e2e8f0';
                ctx.lineWidth = 1;
                ctx.stroke();

                ctx.textBaseline = 'middle';
                ctx.fillStyle = '#1e293b';
                ctx.font = '700 15px sans-serif';
                ctx.textAlign = 'left';
                ctx.fillText(heading, pad, pad + 10);
                ctx.strokeStyle = '#e2e8f0';
                ctx.beginPath();
                ctx.moveTo(pad, titleH);
                ctx.lineTo(W - pad, titleH);
                ctx.stroke();

                var y = titleH + pad / 2 + lineH / 2;
                rows.forEach(function (r) {
                    ctx.font = '400 12px sans-serif';
                    ctx.fillStyle = '#64748b';
                    ctx.textAlign = 'left';
                    ctx.fillText(r.label, pad, y);
                    ctx.font = '700 12px sans-serif';
                    ctx.fillStyle = '#1e293b';
                    ctx.textAlign = 'right';
                    ctx.fillText(String(r.value), W - pad, y);
                    y += lineH;
                });

                var link = document.createElement('a');
                link.download = 'item-' + (item.category || 'dress') + '.png';
                link.href = canvas.toDataURL('image/png');
                document.body.appendChild(link);
                link.click();
                link.remove();
            }

            // Real screenshot of any element — clones it, inlines every loaded
            // stylesheet's rules into it via an SVG <foreignObject> (no external
            // library), rasterizes that to <canvas>, then downloads the PNG.
            // hideSelector (optional, comma-separated for more than one group) is
            // hidden in the clone before capture — e.g. the action buttons
            // themselves, so they don't show up in their own screenshot. onFail
            // runs if the browser can't do this (very old browser, tainted
            // canvas, etc).
            function tmrScreenshotElement(el, filename, hideSelector, onFail) {
                if (!el || typeof XMLSerializer === 'undefined') {
                    if (onFail) { onFail(); }
                    return;
                }
                var width = Math.ceil(el.getBoundingClientRect().width) || 380;

                var clone = el.cloneNode(true);
                if (hideSelector) {
                    var hidden = clone.querySelectorAll(hideSelector);
                    // display:none (not visibility:hidden) so the hidden element's own
                    // reserved layout space collapses too — otherwise a big block like
                    // the toolbar leaves a blank gap where it used to be.
                    Array.prototype.forEach.call(hidden, function (h) { h.style.display = 'none'; });
                }

                var cssText = '';
                try {
                    Array.prototype.forEach.call(document.styleSheets, function (sheet) {
                        try {
                            Array.prototype.forEach.call(sheet.cssRules || sheet.rules || [], function (rule) {
                                cssText += rule.cssText + '\n';
                            });
                        } catch (e) { /* cross-origin sheet — skip */ }
                    });
                } catch (e) { /* ignore */ }

                var xhtmlNS = 'http://www.w3.org/1999/xhtml';
                var wrapper = document.createElementNS(xhtmlNS, 'div');
                wrapper.style.width = width + 'px';
                wrapper.style.background = '#fff';
                // Establishes its own block-formatting context so the last child's
                // margin can't collapse through the bottom and escape the wrapper's
                // own box — without this, the height measured off-screen (a normal
                // HTML context) didn't match what the SVG <foreignObject> actually
                // rendered, and the last row got clipped at the bottom.
                wrapper.style.overflow = 'hidden';
                var styleEl = document.createElementNS(xhtmlNS, 'style');
                styleEl.textContent = cssText;
                wrapper.appendChild(styleEl);
                wrapper.appendChild(clone);

                // Measure the WRAPPED CLONE's own real rendered height (by actually
                // inserting it off-screen) instead of trusting the live card's own
                // rect — a mismatch between the two was what clipped the bottom of
                // the exported image before.
                var probe = document.createElement('div');
                probe.style.position = 'fixed';
                probe.style.left = '-9999px';
                probe.style.top = '0';
                document.body.appendChild(probe);
                probe.appendChild(wrapper);
                // Take the largest of every height-measuring method the DOM offers —
                // a single one (even with a small buffer added) kept coming up a few
                // pixels short of what the SVG <foreignObject> actually needed, so the
                // card's own bottom border/rounded corner got clipped by its edge.
                var height = Math.ceil(Math.max(
                    wrapper.getBoundingClientRect().height,
                    wrapper.scrollHeight,
                    wrapper.offsetHeight
                )) + 24;
                document.body.removeChild(probe);

                var svgNS = 'http://www.w3.org/2000/svg';
                var svg = document.createElementNS(svgNS, 'svg');
                svg.setAttribute('xmlns', svgNS);
                svg.setAttribute('width', width);
                svg.setAttribute('height', height);
                var fo = document.createElementNS(svgNS, 'foreignObject');
                fo.setAttribute('width', '100%');
                fo.setAttribute('height', '100%');
                fo.appendChild(wrapper);
                svg.appendChild(fo);

                var svgData = new XMLSerializer().serializeToString(svg);
                var img = new Image();
                img.onload = function () {
                    try {
                        // A white margin on all four sides in the FINAL image — without
                        // it, the card's own rounded corners/border sit flush against
                        // the image edge and read as if they'd been cropped off.
                        var margin = 20;
                        var scale = 2;
                        var canvas = document.createElement('canvas');
                        canvas.width = (width + margin * 2) * scale;
                        canvas.height = (height + margin * 2) * scale;
                        var ctx = canvas.getContext('2d');
                        ctx.scale(scale, scale);
                        ctx.fillStyle = '#fff';
                        ctx.fillRect(0, 0, width + margin * 2, height + margin * 2);
                        ctx.drawImage(img, margin, margin, width, height);
                        var link = document.createElement('a');
                        link.download = filename;
                        link.href = canvas.toDataURL('image/png');
                        document.body.appendChild(link);
                        link.click();
                        link.remove();
                    } catch (e) {
                        if (onFail) { onFail(); }
                    }
                };
                img.onerror = function () { if (onFail) { onFail(); } };
                img.src = 'data:image/svg+xml;charset=utf-8,' + encodeURIComponent(svgData);
            }

            function tmrScreenshotItem($card, item) {
                tmrScreenshotElement(
                    $card.get(0),
                    'item-' + (item.category || 'dress') + '.png',
                    '.tmr-confirmation-item-header-right',
                    function () { tmrDownloadItemImageFallback(item); }
                );
            }

            $(document).on('click', '.tmr-conf-customer-action', function (e) {
                e.preventDefault();
                e.stopPropagation();
                if (!currentOrderConfirmation) { return; }
                var action = $(this).data('action');

                if (action === 'print') {
                    window.open(currentOrderConfirmation.print_receipt_url, '_blank');
                } else if (action === 'download') {
                    var el = document.querySelector('#tmr-conf-summary-card');
                    tmrScreenshotElement(
                        el,
                        'order-' + (currentOrderConfirmation.id || 'summary') + '.png',
                        // Hides the buttons, not the #tmr-conf-customer-actions wrapper
                        // itself — that wrapper carries the margin-left:auto that pins
                        // the status pill + QR code to the right edge, so it needs to
                        // stay in flow (now empty, zero-width) rather than disappear.
                        '#tmr-conf-customer-actions button',
                        function () { window.alert('<?php echo esc_js(__('স্ক্রিনশট নেওয়া যায়নি।', 'tailor-manager')); ?>'); }
                    );
                } else if (action === 'whatsapp') {
                    if (!currentOrderConfirmation.customer_phone) { return; }
                    // wa.me needs digits only, country-code-first — local numbers are
                    // stored as "01XXXXXXXXX" (no country code), so a leading 0 is
                    // swapped for Bangladesh's 880 the same way a customer would
                    // actually dial it internationally.
                    var digits = currentOrderConfirmation.customer_phone.replace(/\D/g, '');
                    if (digits.indexOf('0') === 0) { digits = '880' + digits.substring(1); }
                    var text = '<?php echo esc_js(__('আপনার অর্ডার', 'tailor-manager')); ?> #' + currentOrderConfirmation.id + ' — ' +
                        '<?php echo esc_js(__('বাকি', 'tailor-manager')); ?>: ' + formatMoney(currentOrderConfirmation.due) + '\n' +
                        currentOrderConfirmation.view_url;
                    window.open('https://wa.me/' + digits + '?text=' + encodeURIComponent(text), '_blank');
                }
            });

            $(document).on('click', '.tmr-conf-toolbar-action', function (e) {
                e.preventDefault();
                if (!currentOrderConfirmation) { return; }
                var action = $(this).data('action');

                if (action === 'full-capture') {
                    var fullEl = document.querySelector('#tmr-order-confirmation-body');
                    tmrScreenshotElement(
                        fullEl,
                        'order-' + (currentOrderConfirmation.id || 'summary') + '-full.png',
                        // .tmr-confirmation-header-actions itself must stay (only its
                        // buttons go) — see the comment on the customer-card capture
                        // above; the toolbar/actions-row/item-header-right blocks have
                        // no siblings relying on their layout space, so those can go
                        // entirely.
                        '.tmr-confirmation-toolbar, .tmr-confirmation-actions-row, .tmr-confirmation-header-actions button, .tmr-confirmation-item-header-right',
                        function () { window.alert('<?php echo esc_js(__('স্ক্রিনশট নেওয়া যায়নি।', 'tailor-manager')); ?>'); }
                    );
                } else if (action === 'dress-capture') {
                    var itemsEl = document.querySelector('#tmr-conf-items');
                    tmrScreenshotElement(
                        itemsEl,
                        'order-' + (currentOrderConfirmation.id || 'summary') + '-dresses.png',
                        '.tmr-confirmation-item-header-right',
                        function () { window.alert('<?php echo esc_js(__('স্ক্রিনশট নেওয়া যায়নি।', 'tailor-manager')); ?>'); }
                    );
                }
            });

            $(document).on('click', '.tmr-conf-item-action', function (e) {
                e.preventDefault();
                e.stopPropagation();
                var idx = $(this).data('idx');
                var item = currentOrderConfirmation && currentOrderConfirmation.items ? currentOrderConfirmation.items[idx] : null;
                if (!item) { return; }
                var action = $(this).data('action');
                var $btn = $(this);

                if (action === 'download') {
                    tmrScreenshotItem($btn.closest('.tmr-conf-item'), item);
                } else if (action === 'print') {
                    // Same server-rendered work-slip template the toolbar's own
                    // "ওয়ার্ক স্লিপ প্রিন্ট" uses (shop header + a real table), just
                    // scoped to this one item instead of the whole order.
                    var printUrl = '<?php echo esc_js(admin_url('admin-post.php')); ?>' +
                        '?action=tmr_print&type=2&order_id=' + currentOrderConfirmation.id + '&item_id=' + item.item_id;
                    window.open(printUrl, '_blank');
                } else if (action === 'whatsapp') {
                    window.open('https://wa.me/?text=' + encodeURIComponent(tmrItemSummaryText(item)), '_blank');
                }
            });

            function tmrRoundRect(ctx, x, y, w, h, r) {
                ctx.beginPath();
                ctx.moveTo(x + r, y);
                ctx.arcTo(x + w, y, x + w, y + h, r);
                ctx.arcTo(x + w, y + h, x, y + h, r);
                ctx.arcTo(x, y + h, x, y, r);
                ctx.arcTo(x, y, x + w, y, r);
                ctx.closePath();
            }

            <?php if ($auto_open_id) : ?>
            loadOrderForm(<?php echo (int) $auto_open_id; ?>);
            <?php endif; ?>
        });
        </script>
        <?php
    }

    /* ---------------------------------------------------------------- */
    /* View                                                               */
    /* ---------------------------------------------------------------- */

    private static function render_view($order_id)
    {
        $order = get_post($order_id);
        if (!$order || self::POST_TYPE !== $order->post_type) {
            wp_die(esc_html__('অর্ডার পাওয়া যায়নি।', 'tailor-manager'));
        }

        $customer_id = (int) get_post_meta($order_id, '_tmr_customer_id', true);

        $header_right = '<a class="tmr-btn-outline" href="' . esc_url(admin_url('admin.php?page=tmr-orders&action=edit&id=' . $order_id)) . '">' . esc_html__('এডিট', 'tailor-manager') . '</a>'
            . ' <a class="tmr-btn-outline" target="_blank" href="' . esc_url(admin_url('admin-post.php?action=tmr_print&order_id=' . $order_id . '&type=1')) . '">' . esc_html__('রিসিট প্রিন্ট', 'tailor-manager') . '</a>'
            . ' <a class="tmr-btn-outline" target="_blank" href="' . esc_url(admin_url('admin-post.php?action=tmr_print&order_id=' . $order_id . '&type=2')) . '">' . esc_html__('ওয়ার্ক স্লিপ প্রিন্ট', 'tailor-manager') . '</a>'
            . ' <a class="tmr-btn-outline" target="_blank" href="' . esc_url(admin_url('admin-post.php?action=tmr_print&order_id=' . $order_id . '&type=3')) . '">' . esc_html__('ফুল স্লিপ প্রিন্ট', 'tailor-manager') . '</a>'
            . ' <button type="button" class="tmr-btn-outline tmr-btn-outline-danger tmr-delete-order-view" data-id="' . esc_attr($order_id) . '">' . esc_html__('ডিলিট', 'tailor-manager') . '</button>';
        TMR_Panel_Shell::header('orders', __('অর্ডার', 'tailor-manager') . ' #' . $order_id, '', $header_right, true);
        $status_key   = TMR_Order_Post_Type::status_label($order_id);
        $field_labels = TMR_Measurement_Fields::get_library();
        $due          = (float) get_post_meta($order_id, '_tmr_due', true);
        $items        = TMR_Order_Post_Type::get_items($order_id);
        ?>
        <div class="tmr-order-view">
        <div class="tmr-card-plain tmr-highlight-card">
            <div class="tmr-step-header tmr-highlight-header">
                <h3><?php esc_html_e('অর্ডার তথ্য', 'tailor-manager'); ?></h3>
                <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
                    <span class="tmr-badge tmr-badge-<?php echo esc_attr($status_key); ?>"><?php echo esc_html(ucfirst($status_key)); ?></span>
                    <?php if ('1' === get_post_meta($order_id, '_tmr_urgent', true)) : ?>
                        <span class="tmr-badge tmr-badge-red"><?php esc_html_e('জরুরি', 'tailor-manager'); ?></span>
                    <?php endif; ?>
                    <?php
                    $tmr_status_labels = array(
                        'pending'   => __('পেন্ডিং', 'tailor-manager'),
                        'ready'     => __('রেডি', 'tailor-manager'),
                        'delivered' => __('ডেলিভারড', 'tailor-manager'),
                        'cancelled' => __('বাতিল', 'tailor-manager'),
                    );
                    ?>
                    <div class="tmr-status-field">
                        <div class="tmr-status-dropdown tmr-view-status-dropdown" data-id="<?php echo esc_attr($order_id); ?>">
                            <button type="button" class="tmr-status-select-btn tmr-status-select-<?php echo esc_attr($status_key); ?>">
                                <span class="tmr-status-select-label"><?php echo esc_html($tmr_status_labels[$status_key]); ?></span>
                                <svg class="tmr-status-select-chevron" width="10" height="6" viewBox="0 0 10 6" fill="none"><path d="M1 1l4 4 4-4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"></path></svg>
                            </button>
                            <div class="tmr-status-dropdown-menu">
                                <?php foreach ($tmr_status_labels as $tmr_status_key => $tmr_status_option_label) : ?>
                                    <button type="button" class="tmr-status-dropdown-item<?php echo $tmr_status_key === $status_key ? ' is-active' : ''; ?>" data-status="<?php echo esc_attr($tmr_status_key); ?>"><?php echo esc_html($tmr_status_option_label); ?></button>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <span class="tmr-status-hint"><?php esc_html_e('স্ট্যাটাস পরিবর্তন করুন', 'tailor-manager'); ?></span>
                    </div>
                </div>
            </div>

            <div class="tmr-order-detail-section">
                <p class="tmr-order-detail-section-title"><?php esc_html_e('কাস্টমার তথ্য', 'tailor-manager'); ?></p>
                <div class="tmr-vp-info-row">
                    <div class="tmr-vp-info-item">
                        <span class="tmr-vp-info-label"><?php esc_html_e('কাস্টমার', 'tailor-manager'); ?></span>
                        <strong><?php echo $customer_id ? esc_html(get_the_title($customer_id) . ' (' . TMR_Customer_Post_Type::get_phone($customer_id) . ')') : esc_html__('ওয়াক-ইন', 'tailor-manager'); ?></strong>
                    </div>
                    <div class="tmr-vp-info-item">
                        <span class="tmr-vp-info-label"><?php esc_html_e('অর্ডারের তারিখ', 'tailor-manager'); ?></span>
                        <strong><?php echo esc_html(get_post_meta($order_id, '_tmr_order_date', true)); ?></strong>
                    </div>
                    <div class="tmr-vp-info-item">
                        <span class="tmr-vp-info-label"><?php esc_html_e('ডেলিভারি তারিখ', 'tailor-manager'); ?></span>
                        <strong><?php echo esc_html(get_post_meta($order_id, '_tmr_delivery_date', true)); ?></strong>
                    </div>
                </div>
            </div>

            <div class="tmr-order-detail-section">
                <p class="tmr-order-detail-section-title"><?php esc_html_e('পেমেন্ট সামারি', 'tailor-manager'); ?></p>
                <div class="tmr-vp-pay-strip">
                    <div class="tmr-vp-pay-cell">
                        <span class="tmr-vp-info-label"><?php esc_html_e('মজুরি', 'tailor-manager'); ?></span>
                        <strong><?php echo esc_html(TMR_Panel_Shell::format_money(get_post_meta($order_id, '_tmr_wage', true))); ?></strong>
                    </div>
                    <div class="tmr-vp-pay-cell">
                        <span class="tmr-vp-info-label"><?php esc_html_e('কাপড়ের দাম', 'tailor-manager'); ?></span>
                        <strong><?php echo esc_html(TMR_Panel_Shell::format_money(get_post_meta($order_id, '_tmr_cloth_price', true))); ?></strong>
                    </div>
                    <div class="tmr-vp-pay-cell tmr-vp-pay-total">
                        <span class="tmr-vp-info-label"><?php esc_html_e('মোট', 'tailor-manager'); ?></span>
                        <strong><?php echo esc_html(TMR_Panel_Shell::format_money(get_post_meta($order_id, '_tmr_total', true))); ?></strong>
                    </div>
                    <div class="tmr-vp-pay-cell">
                        <span class="tmr-vp-info-label"><?php esc_html_e('অগ্রিম', 'tailor-manager'); ?></span>
                        <strong><?php echo esc_html(TMR_Panel_Shell::format_money(get_post_meta($order_id, '_tmr_advance', true))); ?></strong>
                    </div>
                    <div class="tmr-vp-pay-cell tmr-vp-pay-due<?php echo $due > 0 ? ' tmr-vp-pay-due-outstanding' : ''; ?>">
                        <span class="tmr-vp-info-label"><?php esc_html_e('বাকি', 'tailor-manager'); ?></span>
                        <strong><?php echo esc_html(TMR_Panel_Shell::format_money(get_post_meta($order_id, '_tmr_due', true))); ?></strong>
                    </div>
                </div>
            </div>
        </div>

        <div class="tmr-vp-section-heading-row">
            <h3 class="tmr-order-section-heading"><?php esc_html_e('পোশাক', 'tailor-manager'); ?></h3>
            <span class="tmr-vp-section-count"><?php echo esc_html(count($items)); ?> <?php esc_html_e('টি আইটেম', 'tailor-manager'); ?></span>
        </div>

        <?php foreach ($items as $item) :
            $cat_id = TMR_Order_Item_Post_Type::get_category_id($item->ID);
            $term   = get_term($cat_id, TMR_Category_Taxonomy::TAXONOMY);
            $dresses = TMR_Order_Item_Post_Type::get_dresses($item->ID);
        ?>
            <div class="tmr-card tmr-highlight-card tmr-cat-collapse-block">
                <div class="tmr-cat-collapse-header">
                    <h3><?php echo $term && !is_wp_error($term) ? esc_html($term->name) : ''; ?></h3>
                    <span class="tmr-cat-collapse-count"><?php echo esc_html(self::dress_summary_for_item($item->ID, $term)); ?></span>
                    <svg class="tmr-cat-collapse-chevron is-open" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 9l6 6 6-6"></path></svg>
                </div>
                <div class="tmr-cat-collapse-body">
                    <div class="tmr-vp-item-body">
                        <?php $cutter = get_post_meta($item->ID, '_tmr_cutter_name', true); ?>
                        <?php $tailor = get_post_meta($item->ID, '_tmr_tailor_name', true); ?>
                        <?php if ($cutter || $tailor) : ?>
                            <div class="tmr-vp-staff-row">
                                <?php if ($cutter) : ?><span class="tmr-vp-staff-chip"><?php esc_html_e('কাটিং মাস্টার', 'tailor-manager'); ?>: <strong><?php echo esc_html($cutter); ?></strong></span><?php endif; ?>
                                <?php if ($tailor) : ?><span class="tmr-vp-staff-chip"><?php esc_html_e('সোয়িং অপারেটর', 'tailor-manager'); ?>: <strong><?php echo esc_html($tailor); ?></strong></span><?php endif; ?>
                            </div>
                        <?php endif; ?>

                        <?php $measurements = TMR_Order_Item_Post_Type::get_measurements($item->ID); ?>
                        <?php if ($measurements) : ?>
                            <div class="tmr-vp-block-title">
                                <span class="tmr-part-block-icon"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M21.3 15.3a2.4 2.4 0 0 1 0 3.4l-2.6 2.6a2.4 2.4 0 0 1-3.4 0L2.7 8.7a2.4 2.4 0 0 1 0-3.4l2.6-2.6a2.4 2.4 0 0 1 3.4 0z"></path><path d="M14.5 6.5l3 3"></path><path d="M11.5 9.5l1.5 1.5"></path><path d="M8.5 12.5l1.5 1.5"></path></svg></span>
                                <?php esc_html_e('মাপের বিবরণ', 'tailor-manager'); ?>
                            </div>
                            <div class="tmr-vm-grid">
                                <?php foreach ($measurements as $slug => $val) : ?>
                                    <div class="tmr-vm-card">
                                        <span class="tmr-vm-card-label"><?php echo esc_html(isset($field_labels[$slug]) ? $field_labels[$slug] : $slug); ?></span>
                                        <strong class="tmr-vm-card-value"><?php echo esc_html($val); ?></strong>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>

                        <?php
                        $part_selections = TMR_Order_Item_Post_Type::get_part_selections($item->ID);
                        if ($part_selections) : ?>
                            <div class="tmr-vp-block-title">
                                <span class="tmr-part-block-icon"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M20.59 13.41L13.42 20.58a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"></path></svg></span>
                                <?php esc_html_e('ডিজাইন', 'tailor-manager'); ?>
                            </div>
                            <div class="tmr-vp-part-list">
                                <?php foreach ($part_selections as $sel) :
                                    $part = get_post($sel['part_id']);
                                    if (!$part) {
                                        continue;
                                    }
                                    $names = array();
                                    foreach ($sel['design_type_ids'] as $did) {
                                        $d = get_post($did);
                                        if ($d) {
                                            $names[] = $d->post_title;
                                        }
                                    }
                                ?>
                                    <div class="tmr-vp-part-row">
                                        <span class="tmr-vp-part-label"><?php echo esc_html($part->post_title); ?></span>
                                        <div class="tmr-view-design-chips">
                                            <?php foreach ($names as $n) : ?>
                                                <span class="tmr-badge tmr-badge-blue"><?php echo esc_html($n); ?></span>
                                            <?php endforeach; ?>
                                        </div>
                                        <?php if (!empty($sel['part_measurement'])) : ?>
                                            <span class="tmr-vp-part-extra"><?php echo esc_html(TMR_Dress_Part_Post_Type::get_measurement_label($part->ID)); ?>: <strong><?php echo esc_html($sel['part_measurement']); ?></strong></span>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
        </div>

        <script>
        jQuery(function ($) {
            $('.tmr-cat-collapse-header').on('click', function () {
                $(this).next('.tmr-cat-collapse-body').slideToggle(150);
                $(this).find('.tmr-cat-collapse-chevron').toggleClass('is-open');
            });

            // Same self-built status dropdown as the Orders-list confirmation modal
            // (see that page's inline script for why a native <select> was dropped).
            $('.tmr-status-select-btn').on('click', function (e) {
                e.stopPropagation();
                var $dropdown = $(this).closest('.tmr-status-dropdown');
                var wasOpen = $dropdown.hasClass('is-open');
                $('.tmr-status-dropdown').removeClass('is-open');
                if (!wasOpen) { $dropdown.addClass('is-open'); }
            });

            $(document).on('click', function (e) {
                if (!$(e.target).closest('.tmr-status-dropdown').length) {
                    $('.tmr-status-dropdown').removeClass('is-open');
                }
            });

            $('.tmr-view-status-dropdown .tmr-status-dropdown-item').on('click', function () {
                var $item = $(this);
                var $dropdown = $item.closest('.tmr-status-dropdown');
                var newStatus = $item.data('status');
                $dropdown.removeClass('is-open');
                if ('delivered' === newStatus && !TMRPanel.confirmDelete('<?php echo esc_js(__('এই অর্ডারটির ডেলিভারি স্ট্যাটাস পরিবর্তন করবেন?', 'tailor-manager')); ?>')) {
                    return;
                }
                TMRPanel.call('tmr_set_order_status', { id: $dropdown.data('id'), status: newStatus }, function () {
                    window.location.reload();
                });
            });

            $('.tmr-delete-order-view').on('click', function () {
                if (!TMRPanel.confirmDelete('<?php echo esc_js(__('এই অর্ডারটি ডিলিট করবেন?', 'tailor-manager')); ?>')) {
                    return;
                }
                var id = $(this).data('id');
                TMRPanel.call('tmr_delete_order', { id: id }, function () {
                    window.location.href = '<?php echo esc_js(admin_url('admin.php?page=tmr-orders')); ?>';
                });
            });
        });
        </script>
        <?php
        TMR_Panel_Shell::footer();
    }

    /**
     * [{name, quantity}] for one order item's dress list — falls back to the
     * category's own name when a "dress-less" item uses the dress_id=0 sentinel
     * (take the order against the category itself).
     */
    private static function dress_list_for_item($item_id, $term)
    {
        $category_name = $term && !is_wp_error($term) ? $term->name : '';
        $list = array();
        foreach (TMR_Order_Item_Post_Type::get_dresses($item_id) as $d) {
            $dress = !empty($d['dress_id']) ? get_post($d['dress_id']) : null;
            $name  = $dress ? $dress->post_title : $category_name;
            if ($name) {
                $list[] = array('name' => $name, 'quantity' => (int) $d['quantity']);
            }
        }
        return $list;
    }

    /**
     * "Test(1) চুড়িদার(1)" style summary for one order item's own dress list (used
     * as the collapse header's item count/preview).
     */
    private static function dress_summary_for_item($item_id, $term)
    {
        $parts = array();
        foreach (self::dress_list_for_item($item_id, $term) as $d) {
            $parts[] = $d['name'] . '(' . $d['quantity'] . ')';
        }
        return implode(' ', $parts);
    }

    /**
     * Per-category dress + resolved-label measurement rows, for the order
     * confirmation panel's "পোশাক ও মাপ" section (shared by ajax_save() and
     * ajax_get_order_summary() so a fresh save and a later re-view render identically).
     * Zero/blank values are skipped — same "0 isn't really a measurement yet"
     * convention as the order form's own active-highlight logic.
     */
    private static function build_confirmation_items($order_id)
    {
        $field_labels = TMR_Measurement_Fields::get_library();
        $items = array();

        foreach (TMR_Order_Post_Type::get_items($order_id) as $item) {
            $cat_id = TMR_Order_Item_Post_Type::get_category_id($item->ID);
            $term   = get_term($cat_id, TMR_Category_Taxonomy::TAXONOMY);

            $measurements = array();
            foreach (TMR_Order_Item_Post_Type::get_measurements($item->ID) as $slug => $val) {
                $val = trim((string) $val);
                if ('' === $val || '0' === $val) {
                    continue;
                }
                $measurements[] = array(
                    'label' => isset($field_labels[$slug]) ? $field_labels[$slug] : $slug,
                    'value' => $val,
                );
            }

            $parts = array();
            foreach (TMR_Order_Item_Post_Type::get_part_selections($item->ID) as $sel) {
                $part = get_post($sel['part_id']);
                if (!$part) {
                    continue;
                }
                $names = array();
                foreach ($sel['design_type_ids'] as $did) {
                    $d = get_post($did);
                    if ($d) {
                        $names[] = $d->post_title;
                    }
                }
                $parts[] = array(
                    'name'         => $part->post_title,
                    'designs'      => $names,
                    'extra_label'  => !empty($sel['part_measurement']) ? TMR_Dress_Part_Post_Type::get_measurement_label($part->ID) : '',
                    'extra_value'  => !empty($sel['part_measurement']) ? $sel['part_measurement'] : '',
                );
            }

            $items[] = array(
                'item_id'       => $item->ID,
                'category'      => $term && !is_wp_error($term) ? $term->name : '',
                'dress_summary' => self::dress_summary_for_item($item->ID, $term),
                'dresses'       => self::dress_list_for_item($item->ID, $term),
                'cutter'        => get_post_meta($item->ID, '_tmr_cutter_name', true),
                'tailor'        => get_post_meta($item->ID, '_tmr_tailor_name', true),
                'measurements'  => $measurements,
                'parts'         => $parts,
            );
        }

        return $items;
    }

    /**
     * Status badge/toggle state + edit/print/delete link targets — the same set of
     * controls the standalone order-view page's header offers, shared here so the
     * confirmation modal (post-save and "view order") can show them too.
     */
    private static function build_order_action_meta($order_id)
    {
        return array(
            'status_key'          => TMR_Order_Post_Type::status_label($order_id),
            'is_ready'            => TMR_Order_Post_Type::is_ready($order_id),
            'is_delivered'        => TMR_Order_Post_Type::is_delivered($order_id),
            'urgent'              => '1' === get_post_meta($order_id, '_tmr_urgent', true),
            'edit_url'            => admin_url('admin.php?page=tmr-orders&action=edit&id=' . $order_id),
            'print_receipt_url'   => admin_url('admin-post.php?action=tmr_print&order_id=' . $order_id . '&type=1'),
            'print_workslip_url'  => admin_url('admin-post.php?action=tmr_print&order_id=' . $order_id . '&type=2'),
            'print_fullslip_url'  => admin_url('admin-post.php?action=tmr_print&order_id=' . $order_id . '&type=3'),
        );
    }

    /* ---------------------------------------------------------------- */
    /* AJAX                                                               */
    /* ---------------------------------------------------------------- */

    /**
     * Returns the Take/Update Order form as a rendered HTML string, for the
     * "+ অর্ডার নিন" / row-Edit buttons to inject into #tmr-order-modal-body — reuses
     * render_form_body() as-is (same PHP that used to render a full standalone page),
     * just captured via output buffering instead of echoed directly.
     */
    /**
     * Powers the "search as you type" customer search box on the list —
     * re-runs the exact same query/render as a normal page load (build_query()
     * + render_table()) and returns just the #tmr-orders-list-wrap fragment, so
     * the JS side can swap it in without a full page reload.
     */
    public function ajax_search()
    {
        check_ajax_referer('tmr_panel_nonce', 'nonce');
        if (!current_user_can(TMR_Panel_Shell::CAPABILITY)) {
            wp_send_json_error(array('message' => __('অনুমতি নেই।', 'tailor-manager')));
        }

        $search = isset($_POST['s']) ? sanitize_text_field(wp_unslash($_POST['s'])) : '';
        $status = isset($_POST['status']) ? sanitize_key($_POST['status']) : 'all';
        $paged  = isset($_POST['paged']) ? max(1, (int) $_POST['paged']) : 1;

        $query = self::build_query($search, $status, $paged);

        ob_start();
        self::render_table($query, $search, $status, $paged);
        $html = ob_get_clean();

        wp_send_json_success(array('html' => $html));
    }

    public function ajax_get_order_form()
    {
        check_ajax_referer('tmr_panel_nonce', 'nonce');
        if (!current_user_can(TMR_Panel_Shell::CAPABILITY)) {
            wp_send_json_error(array('message' => __('অনুমতি নেই।', 'tailor-manager')));
        }

        $order_id = isset($_POST['id']) ? (int) $_POST['id'] : 0;

        if ($order_id > 0) {
            $order = get_post($order_id);
            if (!$order || self::POST_TYPE !== $order->post_type) {
                wp_send_json_error(array('message' => __('অর্ডার পাওয়া যায়নি।', 'tailor-manager')));
            }
        }

        ob_start();
        self::render_form_body($order_id);
        $html = ob_get_clean();

        wp_send_json_success(array('html' => $html));
    }

    /**
     * Same data shape as ajax_save()'s success response — feeds the identical
     * read-only summary/QR panel, just for an order that already exists (the list's
     * "view" action), instead of only right after a fresh save.
     */
    public function ajax_get_order_summary()
    {
        check_ajax_referer('tmr_panel_nonce', 'nonce');
        if (!current_user_can(TMR_Panel_Shell::CAPABILITY)) {
            wp_send_json_error(array('message' => __('অনুমতি নেই।', 'tailor-manager')));
        }

        $order_id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
        $order    = get_post($order_id);

        if (!$order || self::POST_TYPE !== $order->post_type) {
            wp_send_json_error(array('message' => __('অর্ডার পাওয়া যায়নি।', 'tailor-manager')));
        }

        $customer_id = (int) get_post_meta($order_id, '_tmr_customer_id', true);
        $customer    = $customer_id ? get_post($customer_id) : null;

        wp_send_json_success(array_merge(array(
            'id'             => $order_id,
            'customer_name'  => $customer ? $customer->post_title : __('ওয়াক-ইন', 'tailor-manager'),
            'customer_phone' => $customer_id ? TMR_Customer_Post_Type::get_phone($customer_id) : '',
            'order_date'     => self::format_date_bn(get_post_meta($order_id, '_tmr_order_date', true), true),
            'delivery_date'  => self::format_date_bn(get_post_meta($order_id, '_tmr_delivery_date', true), true),
            'dress_summary'  => self::dress_summary($order_id),
            'items'          => self::build_confirmation_items($order_id),
            'total'          => get_post_meta($order_id, '_tmr_total', true),
            'advance'        => get_post_meta($order_id, '_tmr_advance', true),
            'due'            => get_post_meta($order_id, '_tmr_due', true),
            'view_url'       => admin_url('admin.php?page=tmr-orders&action=view&id=' . $order_id),
        ), self::build_order_action_meta($order_id)));
    }

    public function ajax_search_customers()
    {
        check_ajax_referer('tmr_panel_nonce', 'nonce');
        if (!current_user_can(TMR_Panel_Shell::CAPABILITY)) {
            wp_send_json_error(array('message' => __('অনুমতি নেই।', 'tailor-manager')));
        }

        $term = isset($_POST['term']) ? sanitize_text_field(wp_unslash($_POST['term'])) : '';

        // WP's native 's' search only matches the post title (customer name) — phone
        // number lives in postmeta, so it needs its own meta_query LIKE match. Run both
        // and merge, since a search term could be either a name or a mobile number.
        $by_name = get_posts(array(
            'post_type'      => TMR_Customer_Post_Type::POST_TYPE,
            'post_status'    => array('publish'),
            's'              => $term,
            'posts_per_page' => 15,
        ));

        $by_phone = get_posts(array(
            'post_type'      => TMR_Customer_Post_Type::POST_TYPE,
            'post_status'    => array('publish'),
            'posts_per_page' => 15,
            'meta_query'     => array(
                array('key' => '_tmr_phone', 'value' => $term, 'compare' => 'LIKE'),
            ),
        ));

        $merged = array();
        foreach (array_merge($by_name, $by_phone) as $p) {
            $merged[$p->ID] = $p;
        }

        $results = array();
        foreach (array_slice($merged, 0, 15, true) as $p) {
            $results[] = array('id' => $p->ID, 'name' => $p->post_title, 'phone' => TMR_Customer_Post_Type::get_phone($p->ID));
        }

        wp_send_json_success($results);
    }

    public function ajax_quick_add_customer()
    {
        check_ajax_referer('tmr_panel_nonce', 'nonce');
        if (!current_user_can(TMR_Panel_Shell::CAPABILITY)) {
            wp_send_json_error(array('message' => __('অনুমতি নেই।', 'tailor-manager')));
        }

        $name  = isset($_POST['name']) ? sanitize_text_field(wp_unslash($_POST['name'])) : '';
        $phone = isset($_POST['phone']) ? sanitize_text_field(wp_unslash($_POST['phone'])) : '';
        $address = isset($_POST['address']) ? sanitize_textarea_field(wp_unslash($_POST['address'])) : '';

        if ('' === $name || '' === $phone) {
            wp_send_json_error(array('message' => __('নাম ও ফোন নম্বর আবশ্যক।', 'tailor-manager')));
        }

        $id = wp_insert_post(array(
            'post_type'   => TMR_Customer_Post_Type::POST_TYPE,
            'post_title'  => $name,
            'post_status' => 'publish',
        ), true);

        if (is_wp_error($id)) {
            wp_send_json_error(array('message' => $id->get_error_message()));
        }

        update_post_meta($id, '_tmr_phone', $phone);
        update_post_meta($id, '_tmr_address', $address);

        wp_send_json_success(array('id' => $id, 'name' => $name, 'phone' => $phone));
    }

    public function ajax_save()
    {
        check_ajax_referer('tmr_panel_nonce', 'nonce');
        if (!current_user_can(TMR_Panel_Shell::CAPABILITY)) {
            wp_send_json_error(array('message' => __('অনুমতি নেই।', 'tailor-manager')));
        }

        $order_id      = isset($_POST['order_id']) ? (int) $_POST['order_id'] : 0;
        $customer_id   = isset($_POST['customer_id']) ? (int) $_POST['customer_id'] : 0;
        $order_date    = isset($_POST['order_date']) ? sanitize_text_field(wp_unslash($_POST['order_date'])) : '';
        $delivery_date = isset($_POST['delivery_date']) ? sanitize_text_field(wp_unslash($_POST['delivery_date'])) : '';
        $urgent        = !empty($_POST['urgent']);
        $wage          = isset($_POST['wage']) ? (float) $_POST['wage'] : 0;
        $cloth_price   = isset($_POST['cloth_price']) ? (float) $_POST['cloth_price'] : 0;
        $total         = isset($_POST['total']) ? (float) $_POST['total'] : 0;
        $advance       = isset($_POST['advance']) ? (float) $_POST['advance'] : 0;
        $due           = isset($_POST['due']) ? (float) $_POST['due'] : 0;
        $image_id      = isset($_POST['image_id']) ? (int) $_POST['image_id'] : 0;
        $items_json    = isset($_POST['items']) ? wp_unslash($_POST['items']) : '[]';
        $items         = json_decode($items_json, true);

        if (!$customer_id || !$order_date || !$delivery_date || !is_array($items) || empty($items)) {
            wp_send_json_error(array('message' => __('কাস্টমার, তারিখ এবং কমপক্ষে একটি ড্রেস আবশ্যক।', 'tailor-manager')));
        }

        $customer = get_post($customer_id);
        $title = $customer ? $customer->post_title . ' - ' . $delivery_date : 'Order - ' . $delivery_date;

        $post_data = array(
            'post_type'   => self::POST_TYPE,
            'post_title'  => $title,
            'post_status' => 'publish',
        );

        if ($order_id > 0) {
            $post_data['ID'] = $order_id;
            $result = wp_update_post($post_data, true);
        } else {
            $result = wp_insert_post($post_data, true);
        }

        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }

        $order_id = $result;

        update_post_meta($order_id, '_tmr_customer_id', $customer_id);
        update_post_meta($order_id, '_tmr_order_date', $order_date);
        update_post_meta($order_id, '_tmr_delivery_date', $delivery_date);
        update_post_meta($order_id, '_tmr_urgent', $urgent ? '1' : '0');
        update_post_meta($order_id, '_tmr_wage', $wage);
        update_post_meta($order_id, '_tmr_cloth_price', $cloth_price);
        update_post_meta($order_id, '_tmr_total', $total);
        update_post_meta($order_id, '_tmr_advance', $advance);
        update_post_meta($order_id, '_tmr_due', $due);

        if ($image_id > 0) {
            set_post_thumbnail($order_id, $image_id);
        }
        update_post_meta($order_id, '_tmr_reference_image_id', $image_id);

        // Replace existing items with the submitted set (simplest correct approach for a
        // small, bounded number of items per order — at most one per category today).
        foreach (TMR_Order_Post_Type::get_items($order_id) as $existing) {
            wp_delete_post($existing->ID, true);
        }

        foreach ($items as $item) {
            $category_id = isset($item['category_id']) ? (int) $item['category_id'] : 0;
            $dresses     = isset($item['dresses']) && is_array($item['dresses']) ? $item['dresses'] : array();

            if (!$category_id || empty($dresses)) {
                continue;
            }

            // dress_id 0 is a deliberate sentinel, not a missing/invalid value here — it
            // means "this category has no distinct dress products, take the order against
            // the category itself" (see render_category_block()'s empty($dresses) branch).
            $clean_dresses = array();
            foreach ($dresses as $d) {
                $dress_id = isset($d['dress_id']) ? (int) $d['dress_id'] : 0;
                $qty      = isset($d['quantity']) ? max(1, (int) $d['quantity']) : 1;
                $clean_dresses[] = array('dress_id' => $dress_id, 'quantity' => $qty);
            }

            if (empty($clean_dresses)) {
                continue;
            }

            $item_id = wp_insert_post(array(
                'post_type'   => TMR_Order_Item_Post_Type::POST_TYPE,
                'post_parent' => $order_id,
                'post_title'  => 'Item',
                'post_status' => 'publish',
            ), true);

            if (is_wp_error($item_id)) {
                continue;
            }

            update_post_meta($item_id, '_tmr_category_id', $category_id);
            update_post_meta($item_id, '_tmr_dresses', $clean_dresses);
            update_post_meta($item_id, '_tmr_cutter_name', isset($item['cutter_name']) ? sanitize_text_field($item['cutter_name']) : '');
            update_post_meta($item_id, '_tmr_tailor_name', isset($item['tailor_name']) ? sanitize_text_field($item['tailor_name']) : '');

            $measurements = array();
            if (isset($item['measurements']) && is_array($item['measurements'])) {
                foreach ($item['measurements'] as $slug => $val) {
                    $measurements[sanitize_key($slug)] = sanitize_text_field($val);
                }
            }
            update_post_meta($item_id, '_tmr_measurements', $measurements);

            $part_selections = array();
            if (isset($item['part_selections']) && is_array($item['part_selections'])) {
                foreach ($item['part_selections'] as $sel) {
                    $part_id = isset($sel['part_id']) ? (int) $sel['part_id'] : 0;
                    $ids     = isset($sel['design_type_ids']) && is_array($sel['design_type_ids']) ? array_map('intval', $sel['design_type_ids']) : array();
                    if (!$part_id || empty($ids)) {
                        continue;
                    }
                    $part_selections[] = array(
                        'part_id'          => $part_id,
                        'design_type_ids'  => $ids,
                        'part_measurement' => isset($sel['part_measurement']) ? sanitize_text_field($sel['part_measurement']) : '',
                    );
                }
            }
            update_post_meta($item_id, '_tmr_part_selections', $part_selections);
        }

        wp_send_json_success(array_merge(array(
            'id'             => $order_id,
            'customer_name'  => $customer ? $customer->post_title : '',
            'customer_phone' => $customer_id ? TMR_Customer_Post_Type::get_phone($customer_id) : '',
            'order_date'     => self::format_date_bn($order_date, true),
            'delivery_date'  => self::format_date_bn($delivery_date, true),
            'dress_summary'  => self::dress_summary($order_id),
            'items'          => self::build_confirmation_items($order_id),
            'total'          => $total,
            'advance'        => $advance,
            'due'            => $due,
            'view_url'       => admin_url('admin.php?page=tmr-orders&action=view&id=' . $order_id),
        ), self::build_order_action_meta($order_id)));
    }

    public function ajax_delete()
    {
        check_ajax_referer('tmr_panel_nonce', 'nonce');
        if (!current_user_can(TMR_Panel_Shell::CAPABILITY)) {
            wp_send_json_error(array('message' => __('অনুমতি নেই।', 'tailor-manager')));
        }

        $id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
        $post = get_post($id);

        if (!$post || self::POST_TYPE !== $post->post_type) {
            wp_send_json_error(array('message' => __('অর্ডার পাওয়া যায়নি।', 'tailor-manager')));
        }

        foreach (TMR_Order_Post_Type::get_items($id) as $item) {
            wp_delete_post($item->ID, true);
        }

        wp_trash_post($id);
        wp_send_json_success();
    }
}
