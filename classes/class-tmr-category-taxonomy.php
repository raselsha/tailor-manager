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

    public function maybe_seed_default_terms()
    {
        if (get_option('tmr_categories_seeded')) {
            return;
        }

        foreach (array('ক্যাটাগরি ১', 'ক্যাটাগরি ২') as $name) {
            if (!term_exists($name, self::TAXONOMY)) {
                wp_insert_term($name, self::TAXONOMY);
            }
        }

        update_option('tmr_categories_seeded', 1);
    }

    /**
     * @return WP_Term[]
     */
    public static function get_terms()
    {
        $terms = get_terms(array(
            'taxonomy'   => self::TAXONOMY,
            'hide_empty' => false,
            'orderby'    => 'term_id',
            'order'      => 'ASC',
        ));

        return is_wp_error($terms) ? array() : $terms;
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
