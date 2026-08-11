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
     * @return array<string,int> "cat_key|part title" => new post ID, keyed this
     *         way (not just by title) because the same part title — সেলাই —
     *         legitimately exists once per category with a totally different
     *         option set, so title alone would collide.
     */
    private static function seed_dress_parts($cat_slugs)
    {
        $parts = array(
            'কলার ডিজাইন'      => array($cat_slugs['coat'], 1),
            'পকেট ডিজাইন'      => array($cat_slugs['coat'], 1),
            'প্লেট ডিজাইন'     => array($cat_slugs['coat'], 1),
            'তিরা'             => array($cat_slugs['coat'], 0),
            'সেলাই|coat'       => array($cat_slugs['coat'], 0, 'সেলাই'),
            'চেইন|coat'        => array($cat_slugs['coat'], 0, 'চেইন'),
            'ফাড়া'            => array($cat_slugs['coat'], 1),
            'পাইপি'            => array($cat_slugs['coat'], 0),
            'কফ ডিজাইন'        => array($cat_slugs['coat'], 0),
            'এমব্রয়ডারী'       => array($cat_slugs['coat'], 0),
            'এমব্রয়ডারী বোর্ড' => array($cat_slugs['coat'], 1),
            'কারচুপি'          => array($cat_slugs['coat'], 0),
            // Shape-swatch parts — options carry a featured image on the real
            // shop's install (order form auto-renders it via has_post_thumbnail());
            // seeded here as text-only choices since a generic install has no
            // matching image asset to ship, same as any other design type's
            // image being optional.
            'প্লেট নমুনা'       => array($cat_slugs['coat'], 0),
            'পকেট নমুনা'       => array($cat_slugs['coat'], 0),
            'কফ নমুনা'         => array($cat_slugs['coat'], 0),
            'পকেট'             => array($cat_slugs['trouser'], 0),
            'সেলাই|trouser'    => array($cat_slugs['trouser'], 0, 'সেলাই'),
            'চেইন'             => array($cat_slugs['trouser'], 0),
        );
        $ids = array();
        foreach ($parts as $key => $info) {
            $cat_slug = $info[0];
            $enabled  = $info[1];
            $title    = isset($info[2]) ? $info[2] : $key;
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
            $ids[$key] = $post_id;
        }
        return $ids;
    }

    private static function seed_design_types($part_ids)
    {
        $designs = array(
            // কলার ডিজাইন (জামা)
            'রাউন্ড কলার' => 'কলার ডিজাইন', 'ব্যান্ড কলার' => 'কলার ডিজাইন', 'মদিনা কলার' => 'কলার ডিজাইন',
            'সেরওয়ানী কলার' => 'কলার ডিজাইন', 'রাউন্ড সেরওয়ানী কলার' => 'কলার ডিজাইন', 'ব্যান্ড সেরওয়ানী কলার' => 'কলার ডিজাইন',
            'কলার বেশী রাউন্ড' => 'কলার ডিজাইন', 'কলার আড়া' => 'কলার ডিজাইন', 'কলার ওরফ' => 'কলার ডিজাইন',
            'কলার রিং বোতাম' => 'কলার ডিজাইন', 'কলার টিপ বোতাম' => 'কলার ডিজাইন', 'কলারে ঘাট' => 'কলার ডিজাইন',
            'কলারে ডাবল ঘাট' => 'কলার ডিজাইন', 'শার্ট কলার' => 'কলার ডিজাইন',
            'এরো শার্ট কলার' => 'কলার ডিজাইন', 'সেলাই ছাড়া কলার' => 'কলার ডিজাইন', 'সাইড বর্ডার' => 'কলার ডিজাইন',

            // পকেট ডিজাইন (জামা)
            'বুক পকেট ডাবল' => 'পকেট ডিজাইন', 'বুক পকেট' => 'পকেট ডিজাইন', 'মোবাইল পকেট' => 'পকেট ডিজাইন',
            'মেসওয়াক পকেট' => 'পকেট ডিজাইন', 'বোগল পকেট' => 'পকেট ডিজাইন', 'বাক পকেট' => 'পকেট ডিজাইন',
            'কল্লির সাথে পকেট' => 'পকেট ডিজাইন', 'বন পকেট' => 'পকেট ডিজাইন', 'সেলাই ছাড়া পকেট' => 'পকেট ডিজাইন',
            'সাইড পকেটে মেসয়াবা' => 'পকেট ডিজাইন',

            // প্লেট ডিজাইন (জামা)
            'কাপড় প্লেট' => 'প্লেট ডিজাইন', 'ডানে প্লেট' => 'প্লেট ডিজাইন', 'ডাবল প্লেট' => 'প্লেট ডিজাইন',
            'সিঙ্গেল প্লেট' => 'প্লেট ডিজাইন', 'বামে খোলা' => 'প্লেট ডিজাইন', 'প্লেট আড়া' => 'প্লেট ডিজাইন',
            'বামে প্লেট' => 'প্লেট ডিজাইন', 'প্লেট ওরফ' => 'প্লেট ডিজাইন', 'প্লেটে চেইন বোতাম' => 'প্লেট ডিজাইন',
            'প্লেটে ৪ বোতাম' => 'প্লেট ডিজাইন', 'প্লেটে ৩ বোতাম' => 'প্লেট ডিজাইন', 'প্লেটে টিপ বোতাম' => 'প্লেট ডিজাইন',
            'V প্লেট' => 'প্লেট ডিজাইন', 'বন প্লেট' => 'প্লেট ডিজাইন', 'প্লেটে চেইন' => 'প্লেট ডিজাইন', 'প্লেট ডিজাইন Description' => 'প্লেট ডিজাইন',

            // তিরা (জামা)
            'স্ট্রেইট তিরা' => 'তিরা', 'V তিরা' => 'তিরা', 'রাউন্ড তিরা' => 'তিরা', 'গোল তিরা (ছোট)' => 'তিরা',

            // সেলাই (জামা)
            'কান্দি মোড়া চাপ সেলাই' => 'সেলাই|coat', 'কান্দি মোড়া ডাবল সেলাই' => 'সেলাই|coat',
            'সাইড নিচ চিকন সেলাই' => 'সেলাই|coat', 'কলার প্লেট কান্দি মোড়া তিরা (ডাবল সেলাই)' => 'সেলাই|coat',
            'সাইড নিচ/হাতা/ডাবল সেলাই' => 'সেলাই|coat', 'মুহরী দ ডাবল সেলাই' => 'সেলাই|coat',
            'নিচ হাতা ১ ই:' => 'সেলাই|coat', 'নিচ হাতা ১/ ই:' => 'সেলাই|coat', 'নিচ হাতা ১// ই:' => 'সেলাই|coat',
            'নিচ হাতা ২/ ই:' => 'সেলাই|coat', 'নিচ হাতা ২// ই:' => 'সেলাই|coat', 'নিচ হাতা ৩ ই:' => 'সেলাই|coat',
            'সাইড নিচ ১// সুতা' => 'সেলাই|coat', 'সাইড নিচ + নিচ হাতা ৩ সুতা' => 'সেলাই|coat',

            // চেইন (জামা)
            'দুই পকেট চেইন' => 'চেইন|coat', 'ডান পকেটে চেইন' => 'চেইন|coat', 'বাম পকেটে চেইন' => 'চেইন|coat',
            'চেইন উল্টা হবে' => 'চেইন|coat', 'স্টিল রানার' => 'চেইন|coat',

            // ফাড়া (জামা)
            'পকেট ঢাকা' => 'ফাড়া', 'মাদানী' => 'ফাড়া', 'সাইড ফাড়া' => 'ফাড়া',

            // পাইপি (জামা)
            'প্লেটের ১ পাশ' => 'পাইপি', 'প্লেটের ২ পাশ' => 'পাইপি',

            // কফ ডিজাইন (জামা)
            'কফলিং ৩ই:' => 'কফ ডিজাইন', 'ডাবল কফলিং ৩ই:' => 'কফ ডিজাইন',

            // এমব্রয়ডারী (জামা)
            'সামনা+কলার+মুহরী' => 'এমব্রয়ডারী', 'সামনা+কলার+মুহরী+মোড়া' => 'এমব্রয়ডারী',
            'শুধু সামনা+কলার' => 'এমব্রয়ডারী', 'শুধু সামনা' => 'এমব্রয়ডারী',

            // এমব্রয়ডারী বোর্ড (জামা)
            'M বোর্ড' => 'এমব্রয়ডারী বোর্ড', 'B বোর্ড (কালো)' => 'এমব্রয়ডারী বোর্ড', 'B বোর্ড (সাদা)' => 'এমব্রয়ডারী বোর্ড',
            'ব্লবোর্ড' => 'এমব্রয়ডারী বোর্ড', 'চিকন বোর্ড' => 'এমব্রয়ডারী বোর্ড',

            // কারচুপি (জামা)
            'কলার প্লেট' => 'কারচুপি', 'কারচুপি - সামনা কালর হাতা' => 'কারচুপি',

            // প্লেট নমুনা (জামা)
            'সোজা প্লেট' => 'প্লেট নমুনা', 'গোল মাথা প্লেট' => 'প্লেট নমুনা',
            'কোনা কাটা প্লেট' => 'প্লেট নমুনা', 'কোনাকুনি প্লেট' => 'প্লেট নমুনা',

            // পকেট নমুনা (জামা)
            'চারকোনা পকেট' => 'পকেট নমুনা', 'গোল কোণা পকেট' => 'পকেট নমুনা', 'কোনা কাটা পকেট' => 'পকেট নমুনা',
            'কোনাকুনি দাগ পকেট' => 'পকেট নমুনা', 'সোজা দাগ পকেট' => 'পকেট নমুনা', 'একদাগ পকেট' => 'পকেট নমুনা',
            'কোনা কাটা পকেট ২' => 'পকেট নমুনা',

            // কফ নমুনা (জামা)
            'সোজা কফ' => 'কফ নমুনা', 'গোল কফ' => 'কফ নমুনা', 'চারকোনা কফ' => 'কফ নমুনা', 'কোনাকুনি কফ' => 'কফ নমুনা',

            // পকেট (পায়জামা)
            'দুই পকেট_2' => 'পকেট', 'এক পকেট ডানে' => 'পকেট', 'এক পকেট বামে' => 'পকেট',
            'মোবাইল পকেট ডানে' => 'পকেট', 'মোবাইল পকেট ডানে (দুই পাশ)' => 'পকেট', 'মোবাইল পকেট বামে' => 'পকেট',
            'হিপ পকেট দুই পাশ' => 'পকেট', 'হিপ পকেট এক পাশ' => 'পকেট', 'পাঞ্জাবী পকেট' => 'পকেট',

            // সেলাই (পায়জামা)
            'পাটিশ ২-২ সেলাই' => 'সেলাই|trouser', 'পাটিশ ২-১ সেলাই' => 'সেলাই|trouser', 'পাটিশ ঘন সেলাই' => 'সেলাই|trouser',
            'চাপ সেলাই' => 'সেলাই|trouser', 'চাপ সেলাই ডাবল' => 'সেলাই|trouser', 'মোটা সুতা' => 'সেলাই|trouser',
            'মোটা রাবার/ফিতা' => 'সেলাই|trouser', 'চিকন রাবার/ফিতা' => 'সেলাই|trouser', 'ফিতা' => 'সেলাই|trouser', 'নেরো শেপ' => 'সেলাই|trouser',

            // চেইন (পায়জামা)
            'সামনা চেইন' => 'চেইন', 'দুই পাশ চেইন' => 'চেইন', '১ পাশ চেইন (ডান)' => 'চেইন',
            '১ পাশ চেইন (বাম)' => 'চেইন', '৩ চেইন' => 'চেইন',
        );
        foreach ($designs as $title => $part_key) {
            if (!isset($part_ids[$part_key])) {
                continue;
            }
            // "_1"/"_2" suffixes only disambiguate two design types that share the
            // exact title ("দুই পকেট") under two different parts — stripped before
            // the post is created, never stored.
            $real_title = preg_replace('/_[12]$/', '', $title);
            $post_id = wp_insert_post(array(
                'post_type'   => TMR_Design_Type_Post_Type::POST_TYPE,
                'post_title'  => $real_title,
                'post_status' => 'publish',
            ));
            if (is_wp_error($post_id) || !$post_id) {
                continue;
            }
            update_post_meta($post_id, '_tmr_dress_part_id', $part_ids[$part_key]);
        }
    }

    private static function seed_dress($cat_slugs)
    {
        $dresses = array(
            'এক ছাটা'        => $cat_slugs['coat'],
            'একছাটা জুব্বা'   => $cat_slugs['coat'],
            'এরাবিয়ান জুব্বা' => $cat_slugs['coat'],
            'কাবলী'          => $cat_slugs['coat'],
            'গোল জামা'        => $cat_slugs['coat'],
            'পাঞ্জাবী'        => $cat_slugs['coat'],
            'পুলিশ কাবলি'     => $cat_slugs['coat'],
            'ফতুয়া'          => $cat_slugs['coat'],
            'বোরখা'          => $cat_slugs['coat'],
            'শার্ট'           => $cat_slugs['coat'],
            'সেরোয়ানী'        => $cat_slugs['coat'],
            'আলিগড়'          => $cat_slugs['trouser'],
            'চুড়িদার'        => $cat_slugs['trouser'],
            'চোজ পায়জামা'     => $cat_slugs['trouser'],
            'পায়জামা'        => $cat_slugs['trouser'],
            'প্যান্ট'         => $cat_slugs['trouser'],
            'সেলোয়ার'        => $cat_slugs['trouser'],
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
     * came from, which diverges from that generic fallback (a trouser category
     * needs its own waist/hip/etc. fields, not a shirt's body/sleeve set).
     */
    private static function seed_measurement_fields($cat_slugs)
    {
        $coat_fields = array('লম্বা', 'বডি', 'লুজ', 'পুট', 'হাতা', 'কলার/গলা', 'মুহরী', 'কফ', 'প্লেট', 'ঘের');
        $trouser_fields = array('লম্বা', 'মুহরী', 'কোমড়', 'হাই', 'লুজ', 'হিপ');

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
