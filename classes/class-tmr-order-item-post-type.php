<?php
defined('ABSPATH') || exit;

/**
 * One line item within an order — scoped to a single Category, since the real reference
 * system's Take-Order form shares one measurement set and one design/part selection per
 * category block, with possibly several dresses (each its own quantity) checked within
 * that block (e.g. 2x Panjabi + 1x Kabli in the same Category-1 block, one measurement
 * set for all of them). post_parent = the order's post ID; this is the WP-native way to
 * model a variable-length repeating child record without a join table. In practice an
 * order has at most one item per category actually used (today: up to 2, since only 2
 * categories exist) but nothing here hardcodes that limit.
 */
class TMR_Order_Item_Post_Type
{
    const POST_TYPE = 'tmr_order_item';

    public function __construct()
    {
        add_action('init', array($this, 'register'));
    }

    public function register()
    {
        register_post_type(self::POST_TYPE, array(
            'label'           => __('অর্ডার আইটেম', 'tailor-manager'),
            'public'          => false,
            'show_ui'         => false,
            'show_in_menu'    => false,
            'supports'        => array('title'),
            'capability_type' => 'post',
            'map_meta_cap'    => true,
        ));
    }

    public static function get_category_id($item_id)
    {
        return (int) get_post_meta($item_id, '_tmr_category_id', true);
    }

    /**
     * @return array [{dress_id:int, quantity:int, cutter_name:string}]
     */
    public static function get_dresses($item_id)
    {
        $dresses = get_post_meta($item_id, '_tmr_dresses', true);

        return is_array($dresses) ? $dresses : array();
    }

    public static function get_measurements($item_id)
    {
        $measurements = get_post_meta($item_id, '_tmr_measurements', true);

        return is_array($measurements) ? $measurements : array();
    }

    /**
     * @return array [{part_id, design_type_ids:[], part_measurement:'', note:''}]
     */
    public static function get_part_selections($item_id)
    {
        $selections = get_post_meta($item_id, '_tmr_part_selections', true);

        return is_array($selections) ? $selections : array();
    }
}
