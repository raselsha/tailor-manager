<?php
defined('ABSPATH') || exit;

/**
 * Dress (garment) catalog entry. Active/inactive uses post_status publish/draft directly
 * instead of a redundant status meta field.
 */
class TMR_Dress_Post_Type
{
    const POST_TYPE = 'tmr_dress';

    public function __construct()
    {
        add_action('init', array($this, 'register'));
    }

    public function register()
    {
        register_post_type(self::POST_TYPE, array(
            'label'           => __('ড্রেস', 'tailor-manager'),
            'public'          => false,
            'show_ui'         => false,
            'show_in_menu'    => false,
            'supports'        => array('title', 'thumbnail'),
            'capability_type' => 'post',
            'map_meta_cap'    => true,
        ));
    }

    /**
     * @param string $category_slug
     * @param bool   $active_only
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
