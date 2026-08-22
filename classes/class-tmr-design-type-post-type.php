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
            'orderby'        => array('menu_order' => 'ASC', 'title' => 'ASC'),
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

    /**
     * Same design types get_by_part() would return for each part, but for every
     * part_id at once — one query instead of one per part. The order form's own
     * category block calls get_by_part() once per assigned part (up to ~18 across
     * both categories), on every single open of the take/edit-order form, which is
     * by far the most frequently-opened screen in the app.
     * @return array<int, WP_Post[]> part_id => ordered design-type posts
     */
    public static function get_by_parts(array $part_ids, $active_only = true)
    {
        $part_ids = array_values(array_unique(array_map('intval', $part_ids)));
        if (empty($part_ids)) {
            return array();
        }

        $posts = get_posts(array(
            'post_type'      => self::POST_TYPE,
            'posts_per_page' => -1,
            'orderby'        => array('menu_order' => 'ASC', 'title' => 'ASC'),
            'post_status'    => $active_only ? array('publish') : array('publish', 'draft'),
            'meta_query'     => array(
                array(
                    'key'     => '_tmr_dress_part_id',
                    'value'   => $part_ids,
                    'compare' => 'IN',
                ),
            ),
        ));

        // The query's own orderby sorts the combined result set globally, so grouping
        // here (which preserves each post's relative position) leaves every part's own
        // sub-list still in menu_order/title order — same order get_by_part() would
        // return for that one part.
        $grouped = array();
        foreach ($posts as $post) {
            $part_id = self::get_parent_part_id($post->ID);
            $grouped[$part_id][] = $post;
        }

        return $grouped;
    }
}
