<?php
defined('ABSPATH') || exit;

/**
 * Three bare (no panel chrome) printable views of an order, replicating the reference
 * system's ?action=print1/2/3 — customer receipt, tailor work slip, full design slip.
 * Browser print-to-PDF, no PDF library, same as the original.
 */
class TMR_Print_Slips
{
    public function __construct()
    {
        add_action('admin_post_tmr_print', array($this, 'render'));
    }

    public function render()
    {
        if (!current_user_can(TMR_Panel_Shell::CAPABILITY)) {
            wp_die(esc_html__('এটি দেখার অনুমতি আপনার নেই।', 'tailor-manager'));
        }

        $order_id = isset($_GET['order_id']) ? (int) $_GET['order_id'] : 0;
        $type     = isset($_GET['type']) ? (int) $_GET['type'] : 1;
        $item_id  = isset($_GET['item_id']) ? (int) $_GET['item_id'] : 0;
        $order    = get_post($order_id);

        if (!$order || TMR_Order_Post_Type::POST_TYPE !== $order->post_type) {
            wp_die(esc_html__('অর্ডার পাওয়া যায়নি।', 'tailor-manager'));
        }

        $data = self::prepare_order_data($order_id, $item_id);

        $template = 'print-receipt.php';
        if (2 === $type) {
            $template = 'print-workslip.php';
        } elseif (3 === $type) {
            $template = 'print-full.php';
        }

        $file = TMR_PLUGIN_PATH . 'templates/' . $template;

        if (!file_exists($file)) {
            wp_die(esc_html__('প্রিন্ট টেমপ্লেট পাওয়া যায়নি।', 'tailor-manager'));
        }

        include $file;
        exit;
    }

    /**
     * @param int $only_item_id Scope the slip to a single order item (0 = every
     *                          item in the order, the original whole-order slip).
     */
    public static function prepare_order_data($order_id, $only_item_id = 0)
    {
        $customer_id  = (int) get_post_meta($order_id, '_tmr_customer_id', true);
        $field_labels = TMR_Measurement_Fields::get_library();

        $items = array();
        foreach (TMR_Order_Post_Type::get_items($order_id) as $item) {
            if ($only_item_id && $only_item_id !== $item->ID) {
                continue;
            }

            $cat_id = TMR_Order_Item_Post_Type::get_category_id($item->ID);
            $term   = get_term($cat_id, TMR_Category_Taxonomy::TAXONOMY);

            $category_name = $term && !is_wp_error($term) ? $term->name : '';
            $dress_lines = array();
            foreach (TMR_Order_Item_Post_Type::get_dresses($item->ID) as $d) {
                $dress = !empty($d['dress_id']) ? get_post($d['dress_id']) : null;
                $name  = $dress ? $dress->post_title : $category_name;
                if ($name) {
                    $dress_lines[] = $name . '(' . (int) $d['quantity'] . ')';
                }
            }

            $part_lines = array();
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
                $part_lines[] = array(
                    'part'        => $part->post_title,
                    'designs'     => $names,
                    'measurement' => $sel['part_measurement'],
                );
            }

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

            $items[] = array(
                'item_id'      => $item->ID,
                'category'     => $term && !is_wp_error($term) ? $term->name : '',
                'dress_lines'  => $dress_lines,
                'measurements' => $measurements,
                'part_lines'   => $part_lines,
                'cutter'       => get_post_meta($item->ID, '_tmr_cutter_name', true),
                'tailor'       => get_post_meta($item->ID, '_tmr_tailor_name', true),
            );
        }

        return array(
            'order_id'      => $order_id,
            'shop_name'     => get_option('tmr_shop_name', get_bloginfo('name')),
            'shop_address'  => get_option('tmr_shop_address', ''),
            'shop_phone'    => get_option('tmr_shop_phone', ''),
            'customer_name' => $customer_id ? get_the_title($customer_id) : __('ওয়াক-ইন', 'tailor-manager'),
            'customer_phone' => $customer_id ? TMR_Customer_Post_Type::get_phone($customer_id) : '',
            'order_date'    => TMR_Orders_Panel::format_date_bn(get_post_meta($order_id, '_tmr_order_date', true)),
            'delivery_date' => TMR_Orders_Panel::format_date_bn(get_post_meta($order_id, '_tmr_delivery_date', true)),
            'wage'          => get_post_meta($order_id, '_tmr_wage', true),
            'cloth_price'   => get_post_meta($order_id, '_tmr_cloth_price', true),
            'total'         => get_post_meta($order_id, '_tmr_total', true),
            'advance'       => get_post_meta($order_id, '_tmr_advance', true),
            'due'           => get_post_meta($order_id, '_tmr_due', true),
            'items'         => $items,
        );
    }
}
