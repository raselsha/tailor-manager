<?php
defined('ABSPATH') || exit;

/**
 * The tailor_staff restricted dashboard — mirrors doctor-appointment's doctor-role
 * "Booking" view: a queue of only the logged-in staff member's own work. Ownership is
 * matched by comparing each order item's plain-text cutter name against the logged-in
 * user's display name (case-insensitive, trimmed) — cutter_name deliberately stayed a
 * free-text field (not a user_id FK) per the confirmed scope, so this match is best-effort
 * on exact naming, same as the real system's own paper-trail convention.
 */
class TMR_My_Orders_Panel
{
    public static function render()
    {
        if (!current_user_can(TMR_Staff_Role::CAPABILITY)) {
            wp_die(esc_html__('এই পেজ দেখার অনুমতি আপনার নেই।', 'tailor-manager'));
        }

        $user      = wp_get_current_user();
        $order_ids = self::get_my_order_ids($user->display_name);

        $pending = $ready = $delivered_today = 0;
        $rows    = array();

        foreach ($order_ids as $order_id) {
            $is_delivered = TMR_Order_Post_Type::is_delivered($order_id);
            $is_ready     = TMR_Order_Post_Type::is_ready($order_id);

            if ($is_delivered) {
                if (get_post_meta($order_id, '_tmr_delivery_date', true) === current_time('Y-m-d')) {
                    $delivered_today++;
                }
                continue; // queue only shows outstanding work, matching the doctor "today queue" idea
            }

            $is_ready ? $ready++ : $pending++;
            $rows[] = $order_id;
        }

        TMR_Panel_Shell::header(
            'dashboard',
            sprintf(__('স্বাগতম, %s', 'tailor-manager'), $user->display_name),
            __('আপনার নির্ধারিত অর্ডারসমূহ।', 'tailor-manager')
        );
        ?>
        <div class="tmr-stats-grid">
            <div class="tmr-stat-card tmr-stat-card-amber">
                <div class="tmr-stat-icon"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg></div>
                <h4><?php esc_html_e('পেন্ডিং', 'tailor-manager'); ?></h4>
                <div class="value"><?php echo esc_html($pending); ?></div>
                <div class="trend"><?php esc_html_e('শুরু হয়নি', 'tailor-manager'); ?></div>
            </div>
            <div class="tmr-stat-card tmr-stat-card-blue">
                <div class="tmr-stat-icon"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 11l3 3L22 4"></path><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"></path></svg></div>
                <h4><?php esc_html_e('রেডি', 'tailor-manager'); ?></h4>
                <div class="value"><?php echo esc_html($ready); ?></div>
                <div class="trend"><?php esc_html_e('ডেলিভারির অপেক্ষায়', 'tailor-manager'); ?></div>
            </div>
            <div class="tmr-stat-card tmr-stat-card-green">
                <div class="tmr-stat-icon"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="3"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg></div>
                <h4><?php esc_html_e('আজ ডেলিভার হয়েছে', 'tailor-manager'); ?></h4>
                <div class="value"><?php echo esc_html($delivered_today); ?></div>
                <div class="trend"><?php echo esc_html(TMR_Panel_Shell::bangla_date(current_time('U'))); ?></div>
            </div>
        </div>

        <div class="tmr-dashboard-grid-container tmr-card-plain">
            <div class="tmr-section-header">
                <h3><?php esc_html_e('আমার সারি', 'tailor-manager'); ?></h3>
            </div>

            <?php if (empty($rows)) : ?>
                <div class="tmr-card"><table class="tmr-table"><tbody><tr><td class="tmr-empty"><?php esc_html_e('আপনার নামে কোনো বাকি অর্ডার নেই।', 'tailor-manager'); ?></td></tr></tbody></table></div>
            <?php else : ?>
                <div class="tmr-card">
                    <table class="tmr-table">
                        <thead>
                            <tr>
                                <th><?php esc_html_e('অর্ডার', 'tailor-manager'); ?></th>
                                <th><?php esc_html_e('ড্রেস', 'tailor-manager'); ?></th>
                                <th><?php esc_html_e('ডেলিভারি তারিখ', 'tailor-manager'); ?></th>
                                <th><?php esc_html_e('স্ট্যাটাস', 'tailor-manager'); ?></th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($rows as $order_id) :
                                $customer_id = (int) get_post_meta($order_id, '_tmr_customer_id', true);
                                $name        = $customer_id ? get_the_title($customer_id) : __('ওয়াক-ইন', 'tailor-manager');
                                $ready       = TMR_Order_Post_Type::is_ready($order_id);
                            ?>
                                <tr>
                                    <td>#<?php echo esc_html($order_id); ?> — <?php echo esc_html($name); ?></td>
                                    <td><?php echo esc_html(self::dress_summary($order_id)); ?></td>
                                    <td><?php echo esc_html(get_post_meta($order_id, '_tmr_delivery_date', true)); ?></td>
                                    <td><span class="tmr-badge tmr-badge-<?php echo $ready ? 'ready' : 'pending'; ?>"><?php echo $ready ? esc_html__('রেডি', 'tailor-manager') : esc_html__('পেন্ডিং', 'tailor-manager'); ?></span></td>
                                    <td style="text-align:right;">
                                        <button type="button" class="tmr-btn-outline tmr-btn-sm tmr-toggle-ready" data-id="<?php echo esc_attr($order_id); ?>"><?php echo $ready ? esc_html__('নট রেডি করুন', 'tailor-manager') : esc_html__('রেডি করুন', 'tailor-manager'); ?></button>
                                        <button type="button" class="tmr-btn-add tmr-btn-sm tmr-toggle-delivered" data-id="<?php echo esc_attr($order_id); ?>"><?php esc_html_e('ডেলিভারড করুন', 'tailor-manager'); ?></button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <script>
        jQuery(function ($) {
            $(document).on('click', '.tmr-toggle-ready', function () {
                var $row = $(this).closest('tr');
                TMRPanel.call('tmr_toggle_ready', { id: $(this).data('id') }, function () {
                    window.location.reload();
                });
            });
            $(document).on('click', '.tmr-toggle-delivered', function () {
                if (!window.confirm('<?php echo esc_js(__('এই অর্ডারটি ডেলিভার হয়েছে বলে চিহ্নিত করবেন?', 'tailor-manager')); ?>')) { return; }
                TMRPanel.call('tmr_toggle_delivered', { id: $(this).data('id') }, function () {
                    window.location.reload();
                });
            });
        });
        </script>
        <?php
        TMR_Panel_Shell::footer();
    }

    /**
     * @return int[] order IDs (deduplicated) where at least one item's cutter name
     *               matches the given staff display name
     */
    public static function get_my_order_ids($display_name)
    {
        if ('' === trim($display_name)) {
            return array();
        }

        // Cutting master and sewing operator are two independent roles a staff login
        // could be assigned as — "my work" means either one, not just the cutter slot.
        $items = get_posts(array(
            'post_type'      => TMR_Order_Item_Post_Type::POST_TYPE,
            'post_status'    => 'any',
            'posts_per_page' => -1,
            'meta_query'     => array(
                'relation' => 'OR',
                array('key' => '_tmr_cutter_name', 'value' => $display_name, 'compare' => '='),
                array('key' => '_tmr_tailor_name', 'value' => $display_name, 'compare' => '='),
            ),
        ));

        $order_ids = array();
        foreach ($items as $item) {
            if ($item->post_parent) {
                $order_ids[$item->post_parent] = true;
            }
        }

        $order_ids = array_keys($order_ids);
        rsort($order_ids);

        return $order_ids;
    }

    private static function dress_summary($order_id)
    {
        $parts = array();
        foreach (TMR_Order_Post_Type::get_items($order_id) as $item) {
            foreach (TMR_Order_Item_Post_Type::get_dresses($item->ID) as $d) {
                $dress = get_post($d['dress_id']);
                if ($dress) {
                    $parts[] = $dress->post_title . '(' . (int) $d['quantity'] . ')';
                }
            }
        }
        return implode(' ', $parts);
    }
}
