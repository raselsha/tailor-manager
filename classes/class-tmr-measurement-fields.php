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

        foreach ($library as $slug => $existing_label) {
            if ($existing_label === $label) {
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

    public static function maybe_seed_defaults()
    {
        if (get_option('tmr_measurement_fields_seeded')) {
            return;
        }

        self::maybe_migrate();

        $terms = TMR_Category_Taxonomy::get_terms();
        $by_name = array();
        foreach ($terms as $term) {
            $by_name[$term->name] = $term->slug;
        }

        $defaults = array(
            'ক্যাটাগরি ১' => array(
                __('লম্বা', 'tailor-manager'),
                __('বডি', 'tailor-manager'),
                __('পুট', 'tailor-manager'),
                __('হাতা', 'tailor-manager'),
                __('কলার/গলা', 'tailor-manager'),
                __('মুহরী', 'tailor-manager'),
                __('কফ', 'tailor-manager'),
                __('প্লেট', 'tailor-manager'),
                __('ঘের', 'tailor-manager'),
            ),
            'ক্যাটাগরি ২' => array(
                __('লম্বা', 'tailor-manager'),
                __('মুহরী', 'tailor-manager'),
                __('কোমড়', 'tailor-manager'),
                __('হাই', 'tailor-manager'),
                __('লুজ', 'tailor-manager'),
                __('হিপ', 'tailor-manager'),
            ),
        );

        foreach ($defaults as $name => $labels) {
            if (!isset($by_name[$name])) {
                continue;
            }
            $slugs = array();
            foreach ($labels as $label) {
                $slugs[] = self::create_or_get_field($label);
            }
            self::save_assignments_for_category($by_name[$name], $slugs);
        }

        update_option('tmr_measurement_fields_seeded', 1);
    }
}
