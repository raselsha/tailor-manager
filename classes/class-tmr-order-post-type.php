<?php
defined('ABSPATH') || exit;

/**
 * The parent order record. Status is two independent boolean flags (_tmr_ready,
 * _tmr_delivered), not a single enum — the real system's dashboard toggles them
 * independently (an order can be delivered without ever being marked ready).
 */
class TMR_Order_Post_Type
{
    const POST_TYPE = 'tmr_order';

    public function __construct()
    {
        add_action('init', array($this, 'register'));
    }

    public function register()
    {
        register_post_type(self::POST_TYPE, array(
            'label'           => __('Order', 'tailor-manager'),
            'public'          => false,
            'show_ui'         => false,
            'show_in_menu'    => false,
            'supports'        => array('title'),
            'capability_type' => 'post',
            'map_meta_cap'    => true,
        ));
    }

    public static function is_ready($order_id)
    {
        return '1' === get_post_meta($order_id, '_tmr_ready', true);
    }

    public static function is_delivered($order_id)
    {
        return '1' === get_post_meta($order_id, '_tmr_delivered', true);
    }

    public static function is_cancelled($order_id)
    {
        return '1' === get_post_meta($order_id, '_tmr_cancelled', true);
    }

    /**
     * @return string one of pending|ready|delivered|cancelled
     */
    public static function status_label($order_id)
    {
        if (self::is_cancelled($order_id)) {
            return 'cancelled';
        }

        if (self::is_delivered($order_id)) {
            return 'delivered';
        }

        if (self::is_ready($order_id)) {
            return 'ready';
        }

        return 'pending';
    }

    /**
     * @return WP_Post[]
     */
    public static function get_items($order_id)
    {
        return get_posts(array(
            'post_type'      => TMR_Order_Item_Post_Type::POST_TYPE,
            'post_parent'    => $order_id,
            'posts_per_page' => -1,
            'orderby'        => 'menu_order',
            'order'          => 'ASC',
            'post_status'    => 'any',
        ));
    }
}
