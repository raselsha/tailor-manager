<?php
defined('ABSPATH') || exit;

/**
 * The "Category 1 / Category 2" concept from the reference system, as an open-ended
 * taxonomy shared by Dress and Dress Part instead of a hardcoded 1/2 switch.
 */
class TMR_Category_Taxonomy
{
    const TAXONOMY     = 'tmr_category';
    const ACTIVE_META  = '_tmr_category_active';
    const ORDER_META   = '_tmr_category_order';

    public function __construct()
    {
        add_action('init', array($this, 'register'));
    }

    public function register()
    {
        register_taxonomy(
            self::TAXONOMY,
            array('tmr_dress', 'tmr_dress_part'),
            array(
                'label'             => __('ক্যাটাগরি', 'tailor-manager'),
                'public'            => false,
                'show_ui'           => false,
                'show_admin_column' => false,
                'hierarchical'      => false,
                'rewrite'           => false,
            )
        );
    }

    /**
     * @return WP_Term[] in drag-and-drop display order (reorder()'s own saved
     *         order, falling back to term_id — i.e. creation order — for any
     *         term that predates the sort feature and was never dragged)
     */
    public static function get_terms()
    {
        $terms = get_terms(array(
            'taxonomy'   => self::TAXONOMY,
            'hide_empty' => false,
            'orderby'    => 'term_id',
            'order'      => 'ASC',
        ));

        if (is_wp_error($terms)) {
            return array();
        }

        usort($terms, function ($a, $b) {
            $order_a = get_term_meta($a->term_id, self::ORDER_META, true);
            $order_b = get_term_meta($b->term_id, self::ORDER_META, true);
            // '' (never explicitly ordered) sorts after any real position, by
            // term_id — new/legacy categories land at the end, not scattered in.
            $order_a = '' === $order_a ? PHP_INT_MAX : (int) $order_a;
            $order_b = '' === $order_b ? PHP_INT_MAX : (int) $order_b;
            return $order_a === $order_b ? ($a->term_id - $b->term_id) : ($order_a - $order_b);
        });

        return $terms;
    }

    /**
     * @param int[] $term_ids in the new desired display order
     */
    public static function reorder(array $term_ids)
    {
        foreach ($term_ids as $index => $term_id) {
            update_term_meta((int) $term_id, self::ORDER_META, $index);
        }
    }

    /**
     * Absent meta means active — existing categories created before this flag existed
     * shouldn't silently turn inactive.
     */
    public static function is_active($term_id)
    {
        $value = get_term_meta($term_id, self::ACTIVE_META, true);
        return '' === $value || '1' === $value;
    }

    public static function set_active($term_id, $active)
    {
        update_term_meta($term_id, self::ACTIVE_META, $active ? '1' : '0');
    }
}
