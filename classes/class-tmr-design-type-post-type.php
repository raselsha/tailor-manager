<?php
defined('ABSPATH') || exit;

/**
 * One selectable design option under a Dress Part (e.g. "Round Sherwani Collar" under
 * Collar Design). Swatch image uses the native WP featured image, not a raw upload path.
 */
class TMR_Design_Type_Post_Type
{
    const POST_TYPE = 'tmr_design_type';

    public function __construct()
    {
        add_action('init', array($this, 'register'));
    }

    public function register()
    {
        register_post_type(self::POST_TYPE, array(
            'label'           => __('ডিজাইন টাইপ', 'tailor-manager'),
            'public'          => false,
            'show_ui'         => false,
            'show_in_menu'    => false,
            'supports'        => array('title', 'thumbnail'),
            'capability_type' => 'post',
            'map_meta_cap'    => true,
        ));
    }

    public static function get_parent_part_id($design_type_id)
    {
        return (int) get_post_meta($design_type_id, '_tmr_dress_part_id', true);
    }

    /**
     * @return WP_Post[]
     */
    public static function get_by_part($part_id, $active_only = true)
    {
        $args = array(
            'post_type'      => self::POST_TYPE,
            'posts_per_page' => -1,
            'orderby'        => 'title',
            'order'          => 'ASC',
            'post_status'    => $active_only ? array('publish') : array('publish', 'draft'),
            'meta_query'     => array(
                array(
                    'key'   => '_tmr_dress_part_id',
                    'value' => (int) $part_id,
                ),
            ),
        );

        return get_posts($args);
    }
}
