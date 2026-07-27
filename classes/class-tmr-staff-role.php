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
            ));
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
