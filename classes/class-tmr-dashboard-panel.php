<?php
defined('ABSPATH') || exit;

class TMR_Dashboard_Panel
{
    public function __construct()
    {
        add_action('wp_ajax_tmr_toggle_ready', array($this, 'ajax_toggle_ready'));
        add_action('wp_ajax_tmr_toggle_delivered', array($this, 'ajax_toggle_delivered'));
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

        $groups = self::get_pending_grouped_by_cutter();

        $header_right = '<input type="text" class="tmr-search-box" id="tmr-dash-search" placeholder="'
            . esc_attr__('দ্রুত খুঁজুন…', 'tailor-manager') . '" />'
            . '<a href="' . esc_url(admin_url('admin.php?page=tmr-orders&action=add')) . '" class="tmr-btn-add">'
            . esc_html__('+ অর্ডার নিন', 'tailor-manager') . '</a>';

        TMR_Panel_Shell::header('dashboard', __('টেইলার ওভারভিউ', 'tailor-manager'), __('আপনার দৈনন্দিন কার্যক্রম দেখুন।', 'tailor-manager'), $header_right);
        ?>
        <div class="tmr-stats-grid">
            <div class="tmr-stat-card tmr-stat-card-blue">
                <div class="tmr-stat-icon"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20.59 13.41L13.42 20.58a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"></path><line x1="7" y1="7" x2="7.01" y2="7"></line></svg></div>
                <h4><?php esc_html_e('বিভিন্ন পোশাক', 'tailor-manager'); ?></h4>
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
                <h3><?php esc_html_e('কাটিং মাস্টার / সোয়িং অপারেটর সারি', 'tailor-manager'); ?></h3>
                <a href="<?php echo esc_url(admin_url('admin.php?page=tmr-orders&status=pending')); ?>" class="tmr-view-all-link"><?php esc_html_e('সব দেখুন', 'tailor-manager'); ?> &rarr;</a>
            </div>

            <?php if (empty($groups)) : ?>
                <div class="tmr-card"><table class="tmr-table"><tbody><tr><td class="tmr-empty"><?php esc_html_e('এই মুহূর্তে কোনো পেন্ডিং কাজ নেই।', 'tailor-manager'); ?></td></tr></tbody></table></div>
            <?php else : ?>
                <div class="tmr-dashboard-doctor-groups" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(240px,1fr));gap:12px;">
                    <?php foreach ($groups as $cutter => $order_ids) : ?>
                        <div class="tmr-card">
                            <div class="tmr-card-header">
                                <div>
                                    <h3><?php echo esc_html($cutter ?: __('অনির্ধারিত', 'tailor-manager')); ?></h3>
                                    <span style="display:block;font-size:10px;color:#94a3b8;"><?php echo esc_html(TMR_Panel_Shell::bangla_date(strtotime($today))); ?></span>
                                </div>
                                <span class="tmr-badge tmr-badge-green"><?php echo esc_html(count($order_ids)); ?></span>
                            </div>
                            <ul style="list-style:none;margin:0;padding:4px 0;min-height:100px;max-height:120px;overflow-y:auto;">
                                <?php foreach (array_slice($order_ids, 0, 6) as $order_id) :
                                    $customer_id = (int) get_post_meta($order_id, '_tmr_customer_id', true);
                                    $name        = $customer_id ? get_the_title($customer_id) : __('ওয়াক-ইন', 'tailor-manager');
                                ?>
                                    <li style="display:flex;align-items:center;gap:6px;padding:5px 14px;border-top:1px dotted #cbd5e1;font-size:12px;">
                                        <a href="<?php echo esc_url(admin_url('admin.php?page=tmr-orders&action=view&id=' . $order_id)); ?>" style="flex:1;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;color:#1e293b;text-decoration:none;">#<?php echo esc_html($order_id); ?> <?php echo esc_html($name); ?></a>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        <?php
        TMR_Panel_Shell::footer();
    }

    /**
     * @return array<string,int[]> cutter name (may be '' for unassigned) => order IDs,
     *         for orders that are neither delivered nor cancelled
     */
    public static function get_pending_grouped_by_cutter()
    {
        $items = get_posts(array(
            'post_type'      => TMR_Order_Item_Post_Type::POST_TYPE,
            'post_status'    => 'any',
            'posts_per_page' => -1,
        ));

        $groups = array();
        foreach ($items as $item) {
            if (!$item->post_parent) {
                continue;
            }
            if (TMR_Order_Post_Type::is_delivered($item->post_parent) || TMR_Order_Post_Type::is_cancelled($item->post_parent)) {
                continue;
            }

            $cutter = get_post_meta($item->ID, '_tmr_cutter_name', true);
            if (!isset($groups[$cutter])) {
                $groups[$cutter] = array();
            }
            if (!in_array($item->post_parent, $groups[$cutter], true)) {
                $groups[$cutter][] = $item->post_parent;
            }
        }

        return $groups;
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
}
