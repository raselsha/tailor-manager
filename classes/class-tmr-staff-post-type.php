<?php
defined('ABSPATH') || exit;

/**
 * A cutting master / tailor as a directory entry (name + photo) — separate from
 * TMR_Staff_Role's wp-admin login accounts. Most cutters/tailors never log into the
 * panel at all; this is just who's available to pick from the order form's cutter/
 * tailor dropdown. post_status publish/draft = active/inactive, matching every other
 * catalog CPT in this plugin.
 */
class TMR_Staff_Post_Type
{
    const POST_TYPE = 'tmr_staff';

    public function __construct()
    {
        add_action('init', array($this, 'register'));
    }

    public function register()
    {
        register_post_type(self::POST_TYPE, array(
            'label'           => __('স্টাফ', 'tailor-manager'),
            'public'          => false,
            'show_ui'         => false,
            'show_in_menu'    => false,
            'supports'        => array('title', 'thumbnail'),
            'capability_type' => 'post',
            'map_meta_cap'    => true,
        ));
    }

    /**
     * @return WP_Post[] active staff, ordered by name — what the order form's
     * cutter/tailor picker offers.
     */
    public static function get_active()
    {
        return get_posts(array(
            'post_type'      => self::POST_TYPE,
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'orderby'        => 'title',
            'order'          => 'ASC',
        ));
    }
}
