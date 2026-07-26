<?php
defined('ABSPATH') || exit;

/**
 * Simple CRM entry — a shop customer, not necessarily a WP user account.
 */
class TMR_Customer_Post_Type
{
    const POST_TYPE = 'tmr_customer';

    public function __construct()
    {
        add_action('init', array($this, 'register'));
    }

    public function register()
    {
        register_post_type(self::POST_TYPE, array(
            'label'           => __('Customer', 'tailor-manager'),
            'public'          => false,
            'show_ui'         => false,
            'show_in_menu'    => false,
            'supports'        => array('title', 'thumbnail'),
            'capability_type' => 'post',
            'map_meta_cap'    => true,
        ));
    }

    public static function get_phone($customer_id)
    {
        return get_post_meta($customer_id, '_tmr_phone', true);
    }

    public static function get_address($customer_id)
    {
        return get_post_meta($customer_id, '_tmr_address', true);
    }
}
