<?php
defined('ABSPATH') || exit;

/**
 * Seeds a working example catalog on first-ever activation — the exact
 * category/garment/part/design/measurement-field set from a real shop
 * (originally hand-built on one live install, then migrated by hand to a
 * second one), turned into a repeatable one-time seeder so every *new*
 * install starts with the same working demo instead of two empty
 * placeholder categories and no dress/part/design content at all.
 * Deliberately excludes staff — a specific named person isn't demo content.
 * Guarded by SEEDED_OPTION exactly like the taxonomy/measurement-fields
 * seeders it replaces, so it only ever runs once per site's lifetime,
 * never re-seeding (or duplicating) on a later deactivate/reactivate.
 */
class TMR_Demo_Data
{
    const SEEDED_OPTION = 'tmr_demo_data_seeded';

    public static function maybe_seed()
    {
        if (get_option(self::SEEDED_OPTION)) {
            return;
        }

        $cat_slugs  = self::seed_categories();
        $part_ids   = self::seed_dress_parts($cat_slugs);
        self::seed_design_types($part_ids);
        self::seed_dress($cat_slugs);
        self::seed_measurement_fields($cat_slugs);

        update_option(self::SEEDED_OPTION, 1);
    }

    /**
     * @return array<string,string> category key => real term slug (whatever
     *         wp_insert_term() actually assigns — never hardcoded, since
     *         nothing downstream needs a specific slug, only a consistent one)
     */
    private static function seed_categories()
    {
        $names = array(
            'coat'    => 'জামা',
            'trouser' => 'পায়জামা',
        );
        $slugs = array();
        foreach ($names as $key => $name) {
            $existing = term_exists($name, TMR_Category_Taxonomy::TAXONOMY);
            if ($existing) {
                $term_id = is_array($existing) ? $existing['term_id'] : $existing;
            } else {
                $r = wp_insert_term($name, TMR_Category_Taxonomy::TAXONOMY);
                $term_id = is_wp_error($r) ? 0 : $r['term_id'];
            }
            $term = $term_id ? get_term($term_id, TMR_Category_Taxonomy::TAXONOMY) : null;
            $slugs[$key] = $term ? $term->slug : '';
        }
        return $slugs;
    }

    /**
     * @return array<string,int> part title => new post ID, keyed by title so
     *         seed_design_types() can look parents up by name
     */
    private static function seed_dress_parts($cat_slugs)
    {
        $parts = array(
            'কলার ডিজাইন' => array($cat_slugs['coat'], 1),
            'পকেট ডিজাইন' => array($cat_slugs['coat'], 0),
            'প্লেট ডিজাইন' => array($cat_slugs['coat'], 1),
            'হাতা ডিজাইন' => array($cat_slugs['coat'], 0),
            'পকেট'        => array($cat_slugs['trouser'], 0),
            'সেলাই'       => array($cat_slugs['trouser'], 0),
            'চেইন'        => array($cat_slugs['trouser'], 0),
        );
        $ids = array();
        foreach ($parts as $title => $info) {
            list($cat_slug, $enabled) = $info;
            $post_id = wp_insert_post(array(
                'post_type'   => TMR_Dress_Part_Post_Type::POST_TYPE,
                'post_title'  => $title,
                'post_status' => 'publish',
            ));
            if (is_wp_error($post_id) || !$post_id) {
                continue;
            }
            wp_set_object_terms($post_id, $cat_slug, TMR_Category_Taxonomy::TAXONOMY);
            update_post_meta($post_id, '_tmr_measurement_enabled', $enabled);
            $ids[$title] = $post_id;
        }
        return $ids;
    }

    private static function seed_design_types($part_ids)
    {
        // "_1"/"_2" suffixes only disambiguate two design types that share
        // the exact title ("দুই পকেট") under two different parts — stripped
        // before the post is created, never stored.
        $designs = array(
            'রাউন্ড কলার'    => 'কলার ডিজাইন',
            'সেরওয়ানী কলার' => 'কলার ডিজাইন',
            'শার্ট কলার'     => 'কলার ডিজাইন',
            'ব্যান্ড কলার'   => 'কলার ডিজাইন',
            'এক পকেট'        => 'পকেট ডিজাইন',
            'দুই পকেট_1'      => 'পকেট ডিজাইন',
            'বুক পকেট'       => 'পকেট ডিজাইন',
            'সাইড পকেট'      => 'পকেট ডিজাইন',
            'সিঙ্গেল প্লেট'  => 'প্লেট ডিজাইন',
            'ডাবল প্লেট'     => 'প্লেট ডিজাইন',
            'ভি প্লেট'       => 'প্লেট ডিজাইন',
            'ফুল হাতা'       => 'হাতা ডিজাইন',
            'হাফ হাতা'       => 'হাতা ডিজাইন',
            'কফ হাতা'        => 'হাতা ডিজাইন',
            'দুই পকেট_2'      => 'পকেট',
            'এক পকেট ডানে'   => 'পকেট',
            'এক পকেট বামে'   => 'পকেট',
            'চাপ সেলাই'      => 'সেলাই',
            'মোটা রাবার'     => 'সেলাই',
            'চিকন রাবার'     => 'সেলাই',
            'সামনা চেইন'     => 'চেইন',
            'দুই পাশ চেইন'   => 'চেইন',
            'চেইন ছাড়া'      => 'চেইন',
        );
        foreach ($designs as $title => $part_title) {
            if (!isset($part_ids[$part_title])) {
                continue;
            }
            $real_title = preg_replace('/_[12]$/', '', $title);
            $post_id = wp_insert_post(array(
                'post_type'   => TMR_Design_Type_Post_Type::POST_TYPE,
                'post_title'  => $real_title,
                'post_status' => 'publish',
            ));
            if (is_wp_error($post_id) || !$post_id) {
                continue;
            }
            update_post_meta($post_id, '_tmr_dress_part_id', $part_ids[$part_title]);
        }
    }

    private static function seed_dress($cat_slugs)
    {
        $dresses = array(
            'পাঞ্জাবী'  => $cat_slugs['coat'],
            'শার্ট'     => $cat_slugs['coat'],
            'কাবলী'     => $cat_slugs['coat'],
            'ফতুয়া'    => $cat_slugs['coat'],
            'সেরোয়ানী' => $cat_slugs['coat'],
            'সেলোয়ার'  => $cat_slugs['trouser'],
            'পায়জামা'  => $cat_slugs['trouser'],
            'প্যান্ট'   => $cat_slugs['trouser'],
            'চুড়িদার'  => $cat_slugs['trouser'],
        );
        foreach ($dresses as $title => $cat_slug) {
            $post_id = wp_insert_post(array(
                'post_type'   => TMR_Dress_Post_Type::POST_TYPE,
                'post_title'  => $title,
                'post_status' => 'publish',
            ));
            if (is_wp_error($post_id) || !$post_id) {
                continue;
            }
            wp_set_object_terms($post_id, $cat_slug, TMR_Category_Taxonomy::TAXONOMY);
        }
    }

    /**
     * Overrides (not adds to) TMR_Measurement_Fields' own generic defaults —
     * this is the real field set actually used on the shop this demo data
     * came from, which diverges from that generic fallback (4 fields for the
     * trouser category here, not 6).
     */
    private static function seed_measurement_fields($cat_slugs)
    {
        $coat_fields = array('লম্বা', 'বডি', 'পুট', 'হাতা', 'কলার/গলা', 'মুহরী', 'কফ', 'প্লেট', 'ঘের');
        $trouser_fields = array('লম্বা', 'বডি', 'পুট', 'হাতা');

        $coat_slugs = array();
        foreach ($coat_fields as $label) {
            $coat_slugs[] = TMR_Measurement_Fields::create_or_get_field($label);
        }
        TMR_Measurement_Fields::save_assignments_for_category($cat_slugs['coat'], $coat_slugs);

        $trouser_slugs = array();
        foreach ($trouser_fields as $label) {
            $trouser_slugs[] = TMR_Measurement_Fields::create_or_get_field($label);
        }
        TMR_Measurement_Fields::save_assignments_for_category($cat_slugs['trouser'], $trouser_slugs);
    }
}
