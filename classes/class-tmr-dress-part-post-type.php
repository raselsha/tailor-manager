<?php
defined('ABSPATH') || exit;

/**
 * A customizable part of a garment (e.g. Collar Design, Pocket Design), scoped to a Category.
 * `_tmr_measurement_enabled` mirrors the reference system's "Part Measurement Active/Deactive"
 * flag: does picking a Design Type under this part also capture one extra numeric value.
 */
class TMR_Dress_Part_Post_Type
{
    const POST_TYPE = 'tmr_dress_part';

    public function __construct()
    {
        add_action('init', array($this, 'register'));
    }

    public function register()
    {
        register_post_type(self::POST_TYPE, array(
            'label'           => __('ড্রেস পার্ট', 'tailor-manager'),
            'public'          => false,
            'show_ui'         => false,
            'show_in_menu'    => false,
            'supports'        => array('title'),
            'capability_type' => 'post',
            'map_meta_cap'    => true,
        ));
    }

    public static function measurement_enabled($part_id)
    {
        return '1' === get_post_meta($part_id, '_tmr_measurement_enabled', true);
    }

    /**
     * The custom label shown on the order form's extra measurement box for this part
     * (e.g. "লম্বা") — falls back to a generic "পরিমাণ" if never set.
     */
    public static function get_measurement_label($part_id)
    {
        $label = get_post_meta($part_id, '_tmr_measurement_label', true);
        return '' !== $label ? $label : __('পরিমাণ', 'tailor-manager');
    }

    /**
     * @param string $category_slug
     * @return WP_Post[]
     */
    public static function get_by_category($category_slug, $active_only = true)
    {
        $args = array(
            'post_type'      => self::POST_TYPE,
            'posts_per_page' => -1,
            'orderby'        => array('menu_order' => 'ASC', 'title' => 'ASC'),
            'post_status'    => $active_only ? array('publish') : array('publish', 'draft'),
            'tax_query'      => array(
                array(
                    'taxonomy' => TMR_Category_Taxonomy::TAXONOMY,
                    'field'    => 'slug',
                    'terms'    => $category_slug,
                ),
            ),
        );

        return get_posts($args);
    }
}
