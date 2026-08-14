<?php
defined('ABSPATH') || exit;

/**
 * Global field library + per-category assignments. Fields are defined ONCE in a shared
 * library (renaming one here updates it everywhere it's assigned) and then simply
 * toggled on/off per category, instead of being freely retyped from scratch in every
 * category the way the old per-category-only option worked.
 */
class TMR_Measurement_Fields
{
    const LIBRARY_OPTION     = 'tmr_field_library';
    const ASSIGNMENTS_OPTION = 'tmr_category_field_assignments';
    const ACTIVE_OPTION      = 'tmr_field_active_state';
    const DEFAULT_OPTION     = 'tmr_field_default_state';
    const IMAGE_OPTION       = 'tmr_field_image_state';
    const LEGACY_OPTION      = 'tmr_measurement_fields';

    /**
     * @return array<string,string> field_slug => label, every field ever created
     */
    public static function get_library()
    {
        self::maybe_migrate();
        return get_option(self::LIBRARY_OPTION, array());
    }

    /**
     * @return array<string,string> field_slug => label, active fields only — what a
     * category's own field-selection checklist should offer (a retired/inactive field
     * stays in the library for old orders' sake but shouldn't be pickable for new ones).
     */
    public static function get_active_library()
    {
        $library = self::get_library();
        return array_filter($library, array(__CLASS__, 'is_field_active'), ARRAY_FILTER_USE_KEY);
    }

    /**
     * Reorders the library itself — PHP associative arrays preserve insertion/key
     * order, and get_library()/get_active_library() both just iterate that order,
     * so rebuilding LIBRARY_OPTION in the requested slug order is the entire
     * change; nothing downstream needs to know sorting happened.
     * @param string[] $slugs field slugs in the new desired display order
     */
    public static function reorder_library(array $slugs)
    {
        $library  = self::get_library();
        $reordered = array();
        foreach ($slugs as $slug) {
            if (isset($library[$slug])) {
                $reordered[$slug] = $library[$slug];
            }
        }
        // Any field not present in $slugs (shouldn't happen from the UI, but a
        // stale/partial request must not silently delete fields) keeps its
        // relative position, appended after the newly ordered ones.
        foreach ($library as $slug => $label) {
            if (!isset($reordered[$slug])) {
                $reordered[$slug] = $label;
            }
        }
        update_option(self::LIBRARY_OPTION, $reordered);

        // get_for_category() (the order-taking form's own field list) reads each
        // category's OWN assignment array, not the library, for its order — so a
        // library-only reorder would resort the Measurement Fields manager's grid
        // but leave every order form showing the old order. Re-sorting each
        // category's existing assignment list to match keeps the library as the
        // one place that actually controls display order everywhere, instead of
        // two independently-ordered lists that can drift apart.
        $new_order   = array_flip(array_keys($reordered));
        $assignments = get_option(self::ASSIGNMENTS_OPTION, array());
        foreach ($assignments as $category_slug => $slugs) {
            if (!is_array($slugs)) {
                continue;
            }
            usort($slugs, function ($a, $b) use ($new_order) {
                $pos_a = isset($new_order[$a]) ? $new_order[$a] : PHP_INT_MAX;
                $pos_b = isset($new_order[$b]) ? $new_order[$b] : PHP_INT_MAX;
                return $pos_a - $pos_b;
            });
            $assignments[$category_slug] = $slugs;
        }
        update_option(self::ASSIGNMENTS_OPTION, $assignments);
    }

    /**
     * Absent state means active — fields created before this flag existed shouldn't
     * silently turn inactive.
     */
    public static function is_field_active($field_slug)
    {
        $states = get_option(self::ACTIVE_OPTION, array());
        return !isset($states[$field_slug]) || !empty($states[$field_slug]);
    }

    public static function set_field_active($field_slug, $active)
    {
        $states = get_option(self::ACTIVE_OPTION, array());
        $states[$field_slug] = $active ? 1 : 0;
        update_option(self::ACTIVE_OPTION, $states);
    }

    /**
     * A "default" field is one created (or later saved) with zero dresses explicitly
     * picked — instead of sitting unused, it applies to EVERY category automatically,
     * current and future, with no per-category assignment entries needed at all.
     */
    public static function is_default_field($field_slug)
    {
        $states = get_option(self::DEFAULT_OPTION, array());
        return !empty($states[$field_slug]);
    }

    public static function set_default_field($field_slug, $is_default)
    {
        $states = get_option(self::DEFAULT_OPTION, array());
        if ($is_default) {
            $states[$field_slug] = 1;
        } else {
            unset($states[$field_slug]);
        }
        update_option(self::DEFAULT_OPTION, $states);
    }

    /**
     * @return array<string> field slugs currently marked as "default" (universal)
     */
    public static function get_default_field_slugs()
    {
        return array_keys(array_filter(get_option(self::DEFAULT_OPTION, array())));
    }

    /**
     * A reference photo for this field (e.g. what "লম্বা" actually measures on the
     * garment) — fields are option-array entries, not posts, so this can't be a
     * post_thumbnail like the dress/dress-part/design-type CPTs use; a slug =>
     * attachment ID map is the same shape as ACTIVE_OPTION/DEFAULT_OPTION above.
     */
    public static function get_field_image_id($field_slug)
    {
        $images = get_option(self::IMAGE_OPTION, array());
        return isset($images[$field_slug]) ? (int) $images[$field_slug] : 0;
    }

    public static function set_field_image($field_slug, $image_id)
    {
        $images = get_option(self::IMAGE_OPTION, array());
        if ($image_id > 0) {
            $images[$field_slug] = (int) $image_id;
        } else {
            unset($images[$field_slug]);
        }
        update_option(self::IMAGE_OPTION, $images);
    }

    /**
     * @return array<string,string> field_slug => label, in this category's assigned order.
     * Same return shape as before the library rewrite, so every existing caller
     * (Orders panel's measurement grid, Categories panel) needed no changes. Default
     * (universal) fields are appended after the explicitly-assigned ones, so a category
     * doesn't need its own assignment entry for a field that applies everywhere.
     */
    public static function get_for_category($category_slug)
    {
        self::maybe_migrate();

        $library = get_option(self::LIBRARY_OPTION, array());
        $slugs   = self::get_assigned_slugs($category_slug);

        $fields = array();
        foreach ($slugs as $slug) {
            if (isset($library[$slug]) && self::is_field_active($slug)) {
                $fields[$slug] = $library[$slug];
            }
        }

        foreach (self::get_default_field_slugs() as $slug) {
            if (isset($library[$slug]) && self::is_field_active($slug) && !isset($fields[$slug])) {
                $fields[$slug] = $library[$slug];
            }
        }

        return $fields;
    }

    /**
     * @return array<string> raw assigned field slugs for a category, in display order
     */
    public static function get_assigned_slugs($category_slug)
    {
        self::maybe_migrate();
        $assignments = get_option(self::ASSIGNMENTS_OPTION, array());
        return isset($assignments[$category_slug]) && is_array($assignments[$category_slug])
            ? $assignments[$category_slug]
            : array();
    }

    /**
     * Replaces which library fields are turned on for a category. Slugs that don't
     * exist in the library are silently dropped (stale/forged input guard).
     */
    public static function save_assignments_for_category($category_slug, array $field_slugs)
    {
        self::maybe_migrate();

        $library = get_option(self::LIBRARY_OPTION, array());
        $field_slugs = array_values(array_filter($field_slugs, function ($slug) use ($library) {
            return isset($library[$slug]);
        }));

        $assignments = get_option(self::ASSIGNMENTS_OPTION, array());
        $assignments[$category_slug] = $field_slugs;
        update_option(self::ASSIGNMENTS_OPTION, $assignments);
    }

    /**
     * Finds an existing library field with this exact label, or creates a new one.
     * @return string the field's slug
     */
    public static function create_or_get_field($label)
    {
        self::maybe_migrate();

        $label   = trim($label);
        $library = get_option(self::LIBRARY_OPTION, array());

        // A Bangla label typed via a different keyboard/IME can produce a different
        // (but visually identical) Unicode byte sequence for the same conjunct —
        // e.g. ড়/য় as one precomposed codepoint vs. base letter + combining nukta.
        // A plain === on the raw bytes treats those as different strings and
        // silently creates a duplicate field instead of reusing the existing one
        // (this is exactly how three catalog entries got accidentally duplicated
        // during the Al-Modina data import), so both sides are normalized to NFC
        // first when the intl extension is available — falls back to the old
        // exact-byte comparison on hosts without it, same as before this fix.
        $can_normalize = class_exists('Normalizer');
        $label_key     = $can_normalize ? Normalizer::normalize($label, Normalizer::FORM_C) : $label;

        foreach ($library as $slug => $existing_label) {
            $existing_key = $can_normalize ? Normalizer::normalize($existing_label, Normalizer::FORM_C) : $existing_label;
            if ($existing_key === $label_key) {
                return $slug;
            }
        }

        $base = sanitize_title($label);
        if ('' === $base) {
            $base = 'field';
        }
        $slug   = $base;
        $suffix = 2;
        while (isset($library[$slug])) {
            $slug = $base . '-' . $suffix;
            $suffix++;
        }

        $library[$slug] = $label;
        update_option(self::LIBRARY_OPTION, $library);

        return $slug;
    }

    /**
     * Renames a field in the shared library — affects every category it's assigned to.
     */
    public static function rename_field($slug, $label)
    {
        self::maybe_migrate();

        $label = trim($label);
        if ('' === $label) {
            return false;
        }

        $library = get_option(self::LIBRARY_OPTION, array());
        if (!isset($library[$slug])) {
            return false;
        }

        $library[$slug] = $label;
        update_option(self::LIBRARY_OPTION, $library);

        return true;
    }

    /**
     * @return array<string> category slugs this field is currently assigned to —
     * reverse of get_assigned_slugs(), for the field-first (not category-first)
     * management screen. Filtered against currently-existing category terms: a
     * deleted/renamed category leaves its old slug behind as a dangling key in the
     * assignments option (nothing purges it retroactively), and counting those would
     * show a "used in N" badge with no way to actually see which category that is.
     */
    public static function get_assigned_categories($field_slug)
    {
        self::maybe_migrate();

        $assignments   = get_option(self::ASSIGNMENTS_OPTION, array());
        $valid_slugs   = wp_list_pluck(TMR_Category_Taxonomy::get_terms(), 'slug');
        $categories    = array();
        foreach ($assignments as $category_slug => $field_slugs) {
            if (is_array($field_slugs) && in_array($field_slug, $field_slugs, true) && in_array($category_slug, $valid_slugs, true)) {
                $categories[] = $category_slug;
            }
        }

        return $categories;
    }

    /**
     * Drops a category's own key from the assignments option entirely — called when a
     * category term is deleted so its slug doesn't linger as a dangling assignments key
     * (get_assigned_categories() already filters these out defensively, but cleaning up
     * on delete keeps the option itself from growing unbounded over time).
     */
    public static function forget_category($category_slug)
    {
        $assignments = get_option(self::ASSIGNMENTS_OPTION, array());
        if (isset($assignments[$category_slug])) {
            unset($assignments[$category_slug]);
            update_option(self::ASSIGNMENTS_OPTION, $assignments);
        }
    }

    /**
     * Replaces which categories a single field is turned on for — the field-first
     * counterpart to save_assignments_for_category(). Adds/removes this one field
     * from each affected category's own assignment list without disturbing that
     * category's other fields.
     */
    public static function set_field_categories($field_slug, array $category_slugs)
    {
        self::maybe_migrate();

        $library = get_option(self::LIBRARY_OPTION, array());
        if (!isset($library[$field_slug])) {
            return;
        }

        $assignments  = get_option(self::ASSIGNMENTS_OPTION, array());
        $wanted       = array_flip($category_slugs);
        $touched_slugs = array_unique(array_merge(array_keys($assignments), $category_slugs));

        foreach ($touched_slugs as $category_slug) {
            $current = isset($assignments[$category_slug]) && is_array($assignments[$category_slug])
                ? $assignments[$category_slug]
                : array();
            $has = in_array($field_slug, $current, true);

            if (isset($wanted[$category_slug]) && !$has) {
                $current[] = $field_slug;
            } elseif (!isset($wanted[$category_slug]) && $has) {
                $current = array_values(array_diff($current, array($field_slug)));
            }

            $assignments[$category_slug] = $current;
        }

        update_option(self::ASSIGNMENTS_OPTION, $assignments);
    }

    /**
     * Removes a field from the library entirely and un-assigns it from every category.
     * Past orders keep whatever measurement value was already saved under this slug
     * (order postmeta is independent of the library) — only future order forms stop
     * offering it.
     */
    public static function delete_field($field_slug)
    {
        self::maybe_migrate();

        $library = get_option(self::LIBRARY_OPTION, array());
        unset($library[$field_slug]);
        update_option(self::LIBRARY_OPTION, $library);

        $assignments = get_option(self::ASSIGNMENTS_OPTION, array());
        foreach ($assignments as $category_slug => $field_slugs) {
            if (is_array($field_slugs) && in_array($field_slug, $field_slugs, true)) {
                $assignments[$category_slug] = array_values(array_diff($field_slugs, array($field_slug)));
            }
        }
        update_option(self::ASSIGNMENTS_OPTION, $assignments);

        $states = get_option(self::ACTIVE_OPTION, array());
        unset($states[$field_slug]);
        update_option(self::ACTIVE_OPTION, $states);

        self::set_default_field($field_slug, false);
        self::set_field_image($field_slug, 0);
    }

    /**
     * One-time move from the old per-category "tmr_measurement_fields" option (which
     * duplicated the same label text separately into every category) into a shared
     * library + per-category assignment list — preserves every category's existing
     * fields and order exactly, just de-duplicated by label into one shared source.
     * Self-healing: called defensively from every read/write method above, so it runs
     * exactly once regardless of whether the plugin's own activation hook fired.
     */
    private static function maybe_migrate()
    {
        if (get_option(self::LIBRARY_OPTION) !== false) {
            return;
        }

        $legacy      = get_option(self::LEGACY_OPTION, array());
        $library     = array();
        $assignments = array();

        foreach ($legacy as $category_slug => $fields) {
            if (!is_array($fields)) {
                continue;
            }
            $slugs = array();
            foreach ($fields as $legacy_slug => $label) {
                $found_slug = null;
                foreach ($library as $slug => $existing_label) {
                    if ($existing_label === $label) {
                        $found_slug = $slug;
                        break;
                    }
                }
                if (null === $found_slug) {
                    $found_slug = isset($library[$legacy_slug]) ? $legacy_slug . '-2' : $legacy_slug;
                    $library[$found_slug] = $label;
                }
                $slugs[] = $found_slug;
            }
            $assignments[$category_slug] = $slugs;
        }

        update_option(self::LIBRARY_OPTION, $library);
        update_option(self::ASSIGNMENTS_OPTION, $assignments);
    }

}
