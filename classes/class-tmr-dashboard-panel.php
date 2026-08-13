<?php
defined('ABSPATH') || exit;

class TMR_Dashboard_Panel
{
    public function __construct()
    {
        add_action('wp_ajax_tmr_toggle_ready', array($this, 'ajax_toggle_ready'));
        add_action('wp_ajax_tmr_toggle_delivered', array($this, 'ajax_toggle_delivered'));
        add_action('wp_ajax_tmr_set_order_status', array($this, 'ajax_set_order_status'));
    }

    public static function render()
    {
        if (!current_user_can(TMR_Panel_Shell::CAPABILITY)) {
            wp_die(esc_html__('এই পেজ দেখার অনুমতি আপনার নেই।', 'tailor-manager'));
        }

        $today       = current_time('Y-m-d');
        $month_start = current_time('Y-m-01');

        $stats = array(
            'dresses'   => wp_count_posts(TMR_Dress_Post_Type::POST_TYPE)->publish,
            'customers' => wp_count_posts(TMR_Customer_Post_Type::POST_TYPE)->publish,
            'orders'    => self::count_all_orders(),
        );
        $today_delivery = self::get_orders_by_delivery_date($today, $today, false);

        $upcoming_orders = self::get_upcoming_orders();

        $header_right = '<div class="tmr-filter-input-wrap" style="flex:0 0 260px;">'
            . '<svg class="tmr-filter-input-icon" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>'
            . '<input type="text" class="tmr-filter-input" id="tmr-dash-search" placeholder="'
            . esc_attr__('দ্রুত খুঁজুন…', 'tailor-manager') . '" />'
            . '</div>'
            . '<a href="' . esc_url(admin_url('admin.php?page=tmr-orders&action=add')) . '" class="tmr-btn-add">'
            . esc_html__('+ অর্ডার নিন', 'tailor-manager') . '</a>';

        TMR_Panel_Shell::header('dashboard', __('টেইলার ওভারভিউ', 'tailor-manager'), __('আপনার দৈনন্দিন কার্যক্রম দেখুন।', 'tailor-manager'), $header_right);
        ?>
        <div class="tmr-stats-grid">
            <div class="tmr-stat-card tmr-stat-card-blue">
                <div class="tmr-stat-icon"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20.59 13.41L13.42 20.58a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"></path><line x1="7" y1="7" x2="7.01" y2="7"></line></svg></div>
                <h4><?php esc_html_e('পোশাক', 'tailor-manager'); ?></h4>
                <div class="value"><?php echo esc_html($stats['dresses']); ?></div>
                <div class="trend"><?php esc_html_e('ক্যাটালগে সক্রিয়', 'tailor-manager'); ?></div>
            </div>
            <div class="tmr-stat-card tmr-stat-card-violet">
                <div class="tmr-stat-icon"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg></div>
                <h4><?php esc_html_e('কাস্টমার', 'tailor-manager'); ?></h4>
                <div class="value"><?php echo esc_html($stats['customers']); ?></div>
                <div class="trend"><?php esc_html_e('মোট রেকর্ড', 'tailor-manager'); ?></div>
            </div>
            <div class="tmr-stat-card tmr-stat-card-green">
                <div class="tmr-stat-icon"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="3"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg></div>
                <h4><?php esc_html_e('মোট অর্ডার', 'tailor-manager'); ?></h4>
                <div class="value"><?php echo esc_html($stats['orders']); ?></div>
                <div class="trend"><?php esc_html_e('সর্বমোট', 'tailor-manager'); ?></div>
            </div>
            <div class="tmr-stat-card tmr-stat-card-amber">
                <div class="tmr-stat-icon"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg></div>
                <h4><?php esc_html_e('আজকের ডেলিভারি', 'tailor-manager'); ?></h4>
                <div class="value"><?php echo esc_html(count($today_delivery)); ?></div>
                <div class="trend"><?php echo esc_html(TMR_Panel_Shell::bangla_date(strtotime($today))); ?></div>
            </div>
        </div>

        <div class="tmr-dashboard-grid-container tmr-card-plain">
            <div class="tmr-section-header">
                <h3><?php esc_html_e('আসন্ন ডেলিভারি', 'tailor-manager'); ?></h3>
                <a href="<?php echo esc_url(admin_url('admin.php?page=tmr-orders&status=pending')); ?>" class="tmr-view-all-link"><?php esc_html_e('সব দেখুন', 'tailor-manager'); ?> &rarr;</a>
            </div>

            <div class="tmr-card">
                <div class="tmr-table-cards">
                <table class="tmr-table tmr-orders-table">
                    <thead>
                        <tr>
                            <th class="tmr-orders-id-cell"><?php esc_html_e('অর্ডার আইডি', 'tailor-manager'); ?></th>
                            <th class="tmr-orders-customer-cell"><?php esc_html_e('কাস্টমার', 'tailor-manager'); ?></th>
                            <th class="tmr-orders-dress-cell"><?php esc_html_e('ড্রেস ও পরিমাণ', 'tailor-manager'); ?></th>
                            <th class="tmr-orders-staff-cell"><?php esc_html_e('স্টাফ', 'tailor-manager'); ?></th>
                            <th class="tmr-orders-date-cell"><?php esc_html_e('অর্ডারের তারিখ', 'tailor-manager'); ?></th>
                            <th class="tmr-orders-date-cell"><?php esc_html_e('ডেলিভারি তারিখ', 'tailor-manager'); ?></th>
                            <th class="tmr-orders-status-cell"><?php esc_html_e('ডেলিভারি স্ট্যাটাস', 'tailor-manager'); ?></th>
                            <th class="tmr-orders-total-cell"><?php esc_html_e('মোট', 'tailor-manager'); ?></th>
                            <th class="tmr-orders-actions-cell"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($upcoming_orders)) : ?>
                            <tr><td colspan="9" class="tmr-empty"><?php esc_html_e('আসন্ন কোনো ডেলিভারি নেই।', 'tailor-manager'); ?></td></tr>
                        <?php else : ?>
                            <?php foreach ($upcoming_orders as $order) :
                                $customer_id = (int) get_post_meta($order->ID, '_tmr_customer_id', true);
                                $name        = $customer_id ? get_the_title($customer_id) : __('ওয়াক-ইন', 'tailor-manager');
                                $status_key  = TMR_Order_Post_Type::status_label($order->ID);
                                $staff       = TMR_Orders_Panel::staff_summary($order->ID);
                            ?>
                                <tr>
                                    <td data-label="<?php esc_attr_e('অর্ডার আইডি', 'tailor-manager'); ?>" class="tmr-orders-id-cell">#<?php echo esc_html($order->ID); ?></td>
                                    <td data-label="<?php esc_attr_e('কাস্টমার', 'tailor-manager'); ?>" class="tmr-orders-customer-cell" title="<?php echo esc_attr($name); ?>"><?php echo esc_html($name); ?></td>
                                    <td data-label="<?php esc_attr_e('ড্রেস ও পরিমাণ', 'tailor-manager'); ?>" class="tmr-orders-dress-cell" title="<?php echo esc_attr(TMR_Orders_Panel::dress_summary($order->ID)); ?>"><?php echo esc_html(TMR_Orders_Panel::dress_summary($order->ID)); ?></td>
                                    <td data-label="<?php esc_attr_e('স্টাফ', 'tailor-manager'); ?>" class="tmr-orders-staff-cell"><?php echo $staff ? esc_html($staff) : '<span class="tmr-empty-inline">' . esc_html__('অনির্ধারিত', 'tailor-manager') . '</span>'; // phpcs:ignore -- self-escaped ?></td>
                                    <td data-label="<?php esc_attr_e('অর্ডারের তারিখ', 'tailor-manager'); ?>" class="tmr-orders-date-cell"><?php echo esc_html(get_post_meta($order->ID, '_tmr_order_date', true)); ?></td>
                                    <td data-label="<?php esc_attr_e('ডেলিভারি তারিখ', 'tailor-manager'); ?>" class="tmr-orders-date-cell"><?php echo esc_html(get_post_meta($order->ID, '_tmr_delivery_date', true)); ?></td>
                                    <td data-label="<?php esc_attr_e('ডেলিভারি স্ট্যাটাস', 'tailor-manager'); ?>" class="tmr-orders-status-cell"><span class="tmr-badge tmr-badge-<?php echo esc_attr($status_key); ?>"><?php echo esc_html(ucfirst($status_key)); ?></span></td>
                                    <td data-label="<?php esc_attr_e('মোট', 'tailor-manager'); ?>" class="tmr-orders-total-cell"><?php echo esc_html('৳ ' . number_format((float) get_post_meta($order->ID, '_tmr_total', true))); ?></td>
                                    <td class="tmr-orders-actions-cell">
                                        <div class="tmr-actions">
                                            <?php
                                            // Same view_order= mechanism the Orders list's own eye-icon
                                            // uses — lands on the Orders page with its real confirmation
                                            // modal already open, not the standalone view page.
                                            ?>
                                            <a class="tmr-icon-btn" href="<?php echo esc_url(admin_url('admin.php?page=tmr-orders&view_order=' . $order->ID)); ?>" title="<?php esc_attr_e('দেখুন', 'tailor-manager'); ?>"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg></a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
                </div>
            </div>
        </div>
        <?php
        TMR_Panel_Shell::footer();
    }

    /**
     * @return WP_Post[] orders with a delivery date today or later, that aren't
     *         already delivered or cancelled — ordered soonest-first, same "what's
     *         actually coming up" list the standalone Orders page's own status
     *         filters are built from, just date-bounded for the dashboard glance.
     */
    public static function get_upcoming_orders($limit = 20)
    {
        $today = current_time('Y-m-d');
        $meta  = array(
            array('key' => '_tmr_delivery_date', 'value' => $today, 'compare' => '>=', 'type' => 'DATE'),
            array(
                'relation' => 'OR',
                array('key' => '_tmr_delivered', 'compare' => 'NOT EXISTS'),
                array('key' => '_tmr_delivered', 'value' => '1', 'compare' => '!='),
            ),
            array(
                'relation' => 'OR',
                array('key' => '_tmr_cancelled', 'compare' => 'NOT EXISTS'),
                array('key' => '_tmr_cancelled', 'value' => '1', 'compare' => '!='),
            ),
        );

        return self::base_order_query($meta, $limit)->posts;
    }

    private static function base_order_query($extra_meta = array(), $limit = -1)
    {
        return new WP_Query(array(
            'post_type'      => TMR_Order_Post_Type::POST_TYPE,
            'post_status'    => 'any',
            'posts_per_page' => $limit,
            'orderby'        => 'meta_value',
            'meta_key'       => '_tmr_delivery_date',
            'order'          => 'ASC',
            'meta_query'     => array_merge(array('relation' => 'AND'), $extra_meta),
        ));
    }

    public static function get_orders_by_delivery_date($from, $to, $exclude_delivered = false)
    {
        $meta = array(
            array('key' => '_tmr_delivery_date', 'value' => array($from, $to), 'compare' => 'BETWEEN', 'type' => 'DATE'),
        );

        if ($exclude_delivered) {
            $meta[] = array(
                'relation' => 'OR',
                array('key' => '_tmr_delivered', 'compare' => 'NOT EXISTS'),
                array('key' => '_tmr_delivered', 'value' => '1', 'compare' => '!='),
            );
        }

        return self::base_order_query($meta)->posts;
    }

    public static function get_pending_delivery($limit = 10)
    {
        $meta = array(
            array(
                'relation' => 'OR',
                array('key' => '_tmr_delivered', 'compare' => 'NOT EXISTS'),
                array('key' => '_tmr_delivered', 'value' => '1', 'compare' => '!='),
            ),
        );

        return self::base_order_query($meta, $limit)->posts;
    }

    public static function count_all_orders()
    {
        $query = new WP_Query(array('post_type' => TMR_Order_Post_Type::POST_TYPE, 'post_status' => 'any', 'posts_per_page' => 1, 'fields' => 'ids'));
        return (int) $query->found_posts;
    }

    public static function count_all_delivered()
    {
        $query = new WP_Query(array(
            'post_type'      => TMR_Order_Post_Type::POST_TYPE,
            'post_status'    => 'any',
            'posts_per_page' => 1,
            'fields'         => 'ids',
            'meta_query'     => array(array('key' => '_tmr_delivered', 'value' => '1')),
        ));
        return (int) $query->found_posts;
    }

    public static function count_orders_between($from, $to)
    {
        $query = new WP_Query(array(
            'post_type'      => TMR_Order_Post_Type::POST_TYPE,
            'post_status'    => 'any',
            'posts_per_page' => 1,
            'fields'         => 'ids',
            'meta_query'     => array(array('key' => '_tmr_order_date', 'value' => array($from, $to), 'compare' => 'BETWEEN', 'type' => 'DATE')),
        ));
        return (int) $query->found_posts;
    }

    public static function count_delivered_between($from, $to)
    {
        $query = new WP_Query(array(
            'post_type'      => TMR_Order_Post_Type::POST_TYPE,
            'post_status'    => 'any',
            'posts_per_page' => 1,
            'fields'         => 'ids',
            'meta_query'     => array(
                'relation' => 'AND',
                array('key' => '_tmr_delivered', 'value' => '1'),
                array('key' => '_tmr_delivery_date', 'value' => array($from, $to), 'compare' => 'BETWEEN', 'type' => 'DATE'),
            ),
        ));
        return (int) $query->found_posts;
    }

    /**
     * A tailor_staff user may toggle only orders that are actually theirs (an item's
     * cutter name matches their own display name) — same ownership-check discipline as
     * every other staff-facing write path; full admins can toggle anything.
     */
    private function can_toggle_order($order_id)
    {
        if (current_user_can(TMR_Panel_Shell::CAPABILITY)) {
            return true;
        }

        if (!current_user_can(TMR_Staff_Role::CAPABILITY)) {
            return false;
        }

        $my_ids = TMR_My_Orders_Panel::get_my_order_ids(wp_get_current_user()->display_name);
        return in_array((int) $order_id, $my_ids, true);
    }

    public function ajax_toggle_ready()
    {
        check_ajax_referer('tmr_panel_nonce', 'nonce');

        $id = isset($_POST['id']) ? (int) $_POST['id'] : 0;

        if (!$this->can_toggle_order($id)) {
            wp_send_json_error(array('message' => __('অনুমতি নেই।', 'tailor-manager')));
        }

        $new = TMR_Order_Post_Type::is_ready($id) ? '0' : '1';
        update_post_meta($id, '_tmr_ready', $new);

        wp_send_json_success(array('ready' => '1' === $new));
    }

    public function ajax_toggle_delivered()
    {
        check_ajax_referer('tmr_panel_nonce', 'nonce');

        $id = isset($_POST['id']) ? (int) $_POST['id'] : 0;

        if (!$this->can_toggle_order($id)) {
            wp_send_json_error(array('message' => __('অনুমতি নেই।', 'tailor-manager')));
        }

        $new = TMR_Order_Post_Type::is_delivered($id) ? '0' : '1';
        update_post_meta($id, '_tmr_delivered', $new);

        wp_send_json_success(array('delivered' => '1' === $new));
    }

    /**
     * Direct status-dropdown entry point — sets the underlying _tmr_ready/
     * _tmr_delivered/_tmr_cancelled flags to whichever combination matches the
     * chosen single status, keeping TMR_Order_Post_Type::status_label() (and
     * every existing is_ready()/is_delivered()/is_cancelled() reader) correct
     * without changing the two-boolean-plus-cancelled-flag data model itself.
     */
    public function ajax_set_order_status()
    {
        check_ajax_referer('tmr_panel_nonce', 'nonce');

        $id     = isset($_POST['id']) ? (int) $_POST['id'] : 0;
        $status = isset($_POST['status']) ? sanitize_key($_POST['status']) : '';

        if (!$this->can_toggle_order($id)) {
            wp_send_json_error(array('message' => __('অনুমতি নেই।', 'tailor-manager')));
        }

        if (!in_array($status, array('pending', 'ready', 'delivered', 'cancelled'), true)) {
            wp_send_json_error(array('message' => __('অজানা স্ট্যাটাস।', 'tailor-manager')));
        }

        switch ($status) {
            case 'pending':
                update_post_meta($id, '_tmr_ready', '0');
                update_post_meta($id, '_tmr_delivered', '0');
                update_post_meta($id, '_tmr_cancelled', '0');
                break;
            case 'ready':
                update_post_meta($id, '_tmr_ready', '1');
                update_post_meta($id, '_tmr_delivered', '0');
                update_post_meta($id, '_tmr_cancelled', '0');
                break;
            case 'delivered':
                // Delivered implies ready — a shop wouldn't hand over an order
                // that was never marked ready.
                update_post_meta($id, '_tmr_ready', '1');
                update_post_meta($id, '_tmr_delivered', '1');
                update_post_meta($id, '_tmr_cancelled', '0');
                break;
            case 'cancelled':
                update_post_meta($id, '_tmr_cancelled', '1');
                break;
        }

        wp_send_json_success(array('status_key' => TMR_Order_Post_Type::status_label($id)));
    }
}
