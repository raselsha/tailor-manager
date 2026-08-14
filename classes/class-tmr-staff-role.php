<?php
defined('ABSPATH') || exit;

/**
 * A tailor/cutter's own restricted login — mirrors doctor-appointment's mdbk_doctor_role
 * precedent (minimal role: read + level_0 + one narrow custom capability, no wp-admin
 * screens beyond the plugin's own). Staff see only their own orders, matched by comparing
 * the order item's plain-text cutter name against their account's display name — see
 * class-tmr-my-orders-panel.php.
 */
class TMR_Staff_Role
{
    const ROLE = 'tailor_staff';
    const CAPABILITY = 'manage_tmr_staff_orders';

    public function register()
    {
        if (!get_role(self::ROLE)) {
            add_role(self::ROLE, __('টেইলার স্টাফ', 'tailor-manager'), array(
                'read'            => true,
                'level_0'         => true,
                self::CAPABILITY => true,
                // Needed for the Profile page's wp.media() avatar picker — scoped
                // back down to "own uploads only" by TMR_Profile_Panel's media
                // query filters, same trust-boundary pattern as doctor-appointment's
                // mdbk_doctor_role.
                'upload_files'    => true,
                // upload_files alone lets wp.media() upload an attachment but not
                // delete one — WP maps the "delete_post" meta cap for an attachment
                // the user owns to this primitive cap, so without it the media
                // library's own "delete permanently" silently 403s.
                'delete_posts'    => true,
            ));
        } else {
            $role = get_role(self::ROLE);
            if ($role && !$role->has_cap('upload_files')) {
                $role->add_cap('upload_files');
            }
            if ($role && !$role->has_cap('delete_posts')) {
                $role->add_cap('delete_posts');
            }
        }

        $admin = get_role('administrator');
        if ($admin && !$admin->has_cap(self::CAPABILITY)) {
            $admin->add_cap(self::CAPABILITY);
        }
    }

    public static function is_staff($user = null)
    {
        $user = $user ? $user : wp_get_current_user();
        return in_array(self::ROLE, (array) $user->roles, true);
    }
}
