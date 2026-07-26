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
            wp_die(esc_html__('You do not have permission to access this page.', 'tailor-manager'));
        }

        $today          = current_time('Y-m-d');
        $month_start    = current_time('Y-m-01');
        $next_week      = gmdate('Y-m-d', strtotime($today . ' +7 days'));

        $orders_this_month = self::count_orders_between($month_start, current_time('Y-m-t'));
        $delivered_this_month = self::count_delivered_between($month_start, current_time('Y-m-t'));
        $orders_all_time = self::count_all_orders();
        $delivered_all_time = self::count_all_delivered();

        $today_delivery = self::get_orders_by_delivery_date($today, $today, true);
        $next7_delivery = self::get_orders_by_delivery_date(gmdate('Y-m-d', strtotime($today . ' +1 day')), $next_week, true);
        $pending_delivery = self::get_pending_delivery(10);

        TMR_Panel_Shell::header('dashboard', __('Dashboard', 'tailor-manager'));
        ?>
        <div class="tmr-stat-grid">
            <div class="tmr-stat">
                <div class="tmr-stat__label"><?php esc_html_e('Total Order (This Month)', 'tailor-manager'); ?></div>
                <div class="tmr-stat__value"><?php echo esc_html($orders_this_month); ?></div>
            </div>
            <div class="tmr-stat">
                <div class="tmr-stat__label"><?php esc_html_e('Total Delivered (This Month)', 'tailor-manager'); ?></div>
                <div class="tmr-stat__value"><?php echo esc_html($delivered_this_month); ?></div>
            </div>
            <div class="tmr-stat">
                <div class="tmr-stat__label"><?php esc_html_e('Total Order (All Time)', 'tailor-manager'); ?></div>
                <div class="tmr-stat__value"><?php echo esc_html($orders_all_time); ?></div>
            </div>
            <div class="tmr-stat">
                <div class="tmr-stat__label"><?php esc_html_e('Total Delivery (All Time)', 'tailor-manager'); ?></div>
                <div class="tmr-stat__value"><?php echo esc_html($delivered_all_time); ?></div>
            </div>
        </div>

        <div class="tmr-card">
            <div class="tmr-card__title">
                <span><?php esc_html_e('Take an Order', 'tailor-manager'); ?></span>
                <a class="tmr-btn tmr-btn--success" href="<?php echo esc_url(admin_url('admin.php?page=tmr-orders&action=add')); ?>"><span class="dashicons dashicons-plus"></span> <?php esc_html_e('Take an Order', 'tailor-manager'); ?></a>
            </div>
        </div>

        <?php self::render_order_card(__('Today\'s Delivery', 'tailor-manager'), $today_delivery); ?>
        <?php self::render_order_card(__('Delivery for Next 7 Days', 'tailor-manager'), $next7_delivery); ?>
        <?php self::render_order_card(__('Pending Delivery', 'tailor-manager'), $pending_delivery); ?>

        <script>
        jQuery(function ($) {
            $(document).on('click', '.tmr-toggle-ready', function () {
                var $btn = $(this);
                TMRPanel.call('tmr_toggle_ready', { id: $btn.data('id') }, function (data) {
                    $btn.text(data.ready ? '<?php echo esc_js(__('Ready', 'tailor-manager')); ?>' : '<?php echo esc_js(__('Not Ready', 'tailor-manager')); ?>');
                });
            });
            $(document).on('click', '.tmr-toggle-delivered', function () {
                var $btn = $(this);
                TMRPanel.call('tmr_toggle_delivered', { id: $btn.data('id') }, function (data) {
                    $btn.text(data.delivered ? '<?php echo esc_js(__('Delivered', 'tailor-manager')); ?>' : '<?php echo esc_js(__('Undelivered', 'tailor-manager')); ?>');
                });
            });
        });
        </script>
        <?php
        TMR_Panel_Shell::footer();
    }

    private static function render_order_card($title, $orders)
    {
        ?>
        <div class="tmr-card">
            <div class="tmr-card__title">
                <span><?php echo esc_html($title); ?> <span class="tmr-badge tmr-badge--pending"><?php echo count($orders); ?></span></span>
            </div>
            <?php if (empty($orders)) : ?>
                <div class="tmr-empty"><?php esc_html_e('No items.', 'tailor-manager'); ?></div>
            <?php else : ?>
                <table class="tmr-table">
                    <tbody>
                        <?php foreach ($orders as $order) :
                            $customer_id = (int) get_post_meta($order->ID, '_tmr_customer_id', true);
                            $phone       = $customer_id ? TMR_Customer_Post_Type::get_phone($customer_id) : '';
                            $name        = $customer_id ? get_the_title($customer_id) : __('Walk-in', 'tailor-manager');
                            $ready       = TMR_Order_Post_Type::is_ready($order->ID);
                            $delivered   = TMR_Order_Post_Type::is_delivered($order->ID);
                        ?>
                            <tr>
                                <td>
                                    <a href="<?php echo esc_url(admin_url('admin.php?page=tmr-orders&action=view&id=' . $order->ID)); ?>">
                                        <?php echo esc_html($order->ID . '. ' . $name . ($phone ? ' (' . $phone . ')' : '')); ?>
                                    </a>
                                </td>
                                <td style="text-align:right; white-space:nowrap;">
                                    <button type="button" class="tmr-btn tmr-btn--sm tmr-toggle-delivered" data-id="<?php echo esc_attr($order->ID); ?>"><?php echo $delivered ? esc_html__('Delivered', 'tailor-manager') : esc_html__('Undelivered', 'tailor-manager'); ?></button>
                                    <button type="button" class="tmr-btn tmr-btn--sm tmr-toggle-ready" data-id="<?php echo esc_attr($order->ID); ?>"><?php echo $ready ? esc_html__('Ready', 'tailor-manager') : esc_html__('Not Ready', 'tailor-manager'); ?></button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        <?php
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

    public function ajax_toggle_ready()
    {
        check_ajax_referer('tmr_panel_nonce', 'nonce');
        if (!current_user_can(TMR_Panel_Shell::CAPABILITY)) {
            wp_send_json_error(array('message' => __('Permission denied.', 'tailor-manager')));
        }

        $id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
        $new = TMR_Order_Post_Type::is_ready($id) ? '0' : '1';
        update_post_meta($id, '_tmr_ready', $new);

        wp_send_json_success(array('ready' => '1' === $new));
    }

    public function ajax_toggle_delivered()
    {
        check_ajax_referer('tmr_panel_nonce', 'nonce');
        if (!current_user_can(TMR_Panel_Shell::CAPABILITY)) {
            wp_send_json_error(array('message' => __('Permission denied.', 'tailor-manager')));
        }

        $id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
        $new = TMR_Order_Post_Type::is_delivered($id) ? '0' : '1';
        update_post_meta($id, '_tmr_delivered', $new);

        wp_send_json_success(array('delivered' => '1' === $new));
    }
}
