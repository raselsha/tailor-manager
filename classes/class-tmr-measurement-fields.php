<?php
defined('ABSPATH') || exit;

/**
 * Per-category measurement field labels, configurable via Settings (wp_options) instead
 * of hardcoded in PHP like the reference system — adding a 3rd category later needs no
 * code change, just a Settings edit.
 */
class TMR_Measurement_Fields
{
    const OPTION = 'tmr_measurement_fields';

    /**
     * @return array<string,string> field_slug => label, in display order
     */
    public static function get_for_category($category_slug)
    {
        $all = get_option(self::OPTION, array());

        if (isset($all[$category_slug]) && is_array($all[$category_slug])) {
            return $all[$category_slug];
        }

        return array();
    }

    public static function save_for_category($category_slug, array $fields)
    {
        $all = get_option(self::OPTION, array());
        $all[$category_slug] = $fields;
        update_option(self::OPTION, $all);
    }

    public static function maybe_seed_defaults()
    {
        if (get_option('tmr_measurement_fields_seeded')) {
            return;
        }

        $terms = TMR_Category_Taxonomy::get_terms();
        $by_name = array();
        foreach ($terms as $term) {
            $by_name[$term->name] = $term->slug;
        }

        $defaults = array(
            'Category 1' => array(
                'length'  => __('Length', 'tailor-manager'),
                'body'    => __('Body', 'tailor-manager'),
                'put'     => __('Put', 'tailor-manager'),
                'sleeve'  => __('Sleeve', 'tailor-manager'),
                'collar'  => __('Collar/Neck', 'tailor-manager'),
                'muhuri'  => __('Muhuri', 'tailor-manager'),
                'cuff'    => __('Cuff', 'tailor-manager'),
                'plate'   => __('Plate', 'tailor-manager'),
                'gher'    => __('Gher', 'tailor-manager'),
            ),
            'Category 2' => array(
                'length' => __('Length', 'tailor-manager'),
                'muhuri' => __('Muhuri', 'tailor-manager'),
                'waist'  => __('Waist', 'tailor-manager'),
                'hai'    => __('Hai', 'tailor-manager'),
                'loose'  => __('Loose', 'tailor-manager'),
                'hip'    => __('Hip', 'tailor-manager'),
            ),
        );

        foreach ($defaults as $name => $fields) {
            if (isset($by_name[$name])) {
                self::save_for_category($by_name[$name], $fields);
            }
        }

        update_option('tmr_measurement_fields_seeded', 1);
    }
}
