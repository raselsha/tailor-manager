<?php
defined('ABSPATH') || exit;

/**
 * A shop-manager login — full access to every Tailor Panel screen (orders, customers,
 * catalog, staff, accounts, settings) without being a full WordPress administrator.
 * Mirrors doctor-appointment's mdbk_manager_role precedent: TMR_Panel_Shell::CAPABILITY
 * moved off the built-in manage_options so a manager account never gets core wp-admin
 * access (Plugins/Themes/Users/core Settings) — just this plugin's own pages. The SVG
 * upload gate in TMR_Svg_Upload_Support deliberately stays manage_options-only and is
 * NOT extended here, same trust-boundary reasoning as that file's own comment.
 */
class TMR_Manager_Role
{
    const ROLE = 'tmr_manager';

    public function register()
    {
        if (!get_role(self::ROLE)) {
            add_role(self::ROLE, __('ম্যানেজার', 'tailor-manager'), array(
                'read'                      => true,
                'level_0'                   => true,
                TMR_Panel_Shell::CAPABILITY => true,
                // Needed for the Profile page's wp.media() avatar picker — unlike
                // tailor_staff, a manager isn't restricted to "own uploads only"
                // (TMR_Profile_Panel's media query filters exempt this role), same
                // as doctor-appointment's own manager-role exemption.
                'upload_files'              => true,
            ));
        } else {
            $role = get_role(self::ROLE);
            if ($role && !$role->has_cap('upload_files')) {
                $role->add_cap('upload_files');
            }
        }

        $admin = get_role('administrator');
        if ($admin && !$admin->has_cap(TMR_Panel_Shell::CAPABILITY)) {
            $admin->add_cap(TMR_Panel_Shell::CAPABILITY);
        }
    }

    public static function is_manager($user = null)
    {
        $user = $user ? $user : wp_get_current_user();
        return in_array(self::ROLE, (array) $user->roles, true);
    }
}
