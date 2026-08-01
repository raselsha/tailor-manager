<?php
defined('ABSPATH') || exit;

/**
 * Self-service account panel for every logged-in Tailor Panel user (staff and
 * manager/admin alike) — "প্রোফাইল" (name/email/phone/avatar) and "পাসওয়ার্ড
 * পরিবর্তন" (change password), mirroring doctor-appointment's mdbk-profile /
 * mdbk-change-password screens: same no-current-password-field change-password
 * flow (re-issues the auth cookie right after wp_set_password() so the user
 * isn't logged out of their own session by changing it), and the same
 * wp.media() avatar picker restricted to "own uploads only" for tailor_staff
 * while tmr_manager/administrator stay unrestricted.
 */
class TMR_Profile_Panel
{
    const AVATAR_META = '_tmr_avatar_id';
    const PHONE_META  = '_tmr_user_phone';

    public function __construct()
    {
        add_action('wp_ajax_tmr_save_profile', array($this, 'ajax_save_profile'));
        add_action('admin_post_tmr_change_password', array($this, 'handle_change_password'));
        add_filter('ajax_query_attachments_args', array($this, 'restrict_media_to_own_uploads'));
        add_action('pre_get_posts', array($this, 'restrict_media_library_query'));
    }

    /**
     * TMR_Staff_Role::CAPABILITY and TMR_Panel_Shell::CAPABILITY are deliberately
     * disjoint (only administrator has both) — every logged-in panel user, staff
     * or manager, needs their own profile/password screens, so this checks either.
     */
    private static function can_access()
    {
        return current_user_can(TMR_Staff_Role::CAPABILITY) || current_user_can(TMR_Panel_Shell::CAPABILITY);
    }

    /**
     * @return string self-escaped <div class="tmr-user-avatar">...</div> — an <img> if
     *                the user has uploaded one, otherwise a colored initials circle.
     */
    public static function avatar_html($user_id, $size = 32)
    {
        $avatar_id = (int) get_user_meta($user_id, self::AVATAR_META, true);
        $url       = $avatar_id ? wp_get_attachment_image_url($avatar_id, 'thumbnail') : '';
        $style     = 'width:' . (int) $size . 'px;height:' . (int) $size . 'px;';

        if ($url) {
            return '<div class="tmr-user-avatar" style="' . esc_attr($style) . '"><img src="' . esc_url($url) . '" alt="" /></div>';
        }

        $user    = get_userdata($user_id);
        $name    = $user ? $user->display_name : '';
        $initial = $name ? mb_substr($name, 0, 1, 'UTF-8') : '?';

        return '<div class="tmr-user-avatar" style="' . esc_attr($style) . '">' . esc_html($initial) . '</div>';
    }

    public static function render_profile()
    {
        if (!self::can_access()) {
            wp_die(esc_html__('এই পেজ দেখার অনুমতি আপনার নেই।', 'tailor-manager'));
        }

        $user       = wp_get_current_user();
        $phone      = get_user_meta($user->ID, self::PHONE_META, true);
        $avatar_id  = (int) get_user_meta($user->ID, self::AVATAR_META, true);
        $avatar_url = $avatar_id ? wp_get_attachment_image_url($avatar_id, 'medium') : '';

        TMR_Panel_Shell::header('profile', __('প্রোফাইল', 'tailor-manager'), __('আপনার অ্যাকাউন্টের তথ্য পরিচালনা করুন।', 'tailor-manager'));
        ?>
        <div class="tmr-card-plain" style="max-width:480px;">
            <form id="tmr-profile-form">
                <input type="hidden" name="avatar_id" id="tmr-profile-avatar-id" value="<?php echo esc_attr($avatar_id); ?>" />
                <div class="tmr-icon-picker-center">
                    <div class="tmr-photo-preview tmr-photo-preview-lg">
                        <img class="tmr-profile-preview" src="<?php echo esc_url($avatar_url); ?>" style="width:100%;height:100%;object-fit:cover;<?php echo $avatar_url ? '' : 'display:none;'; ?>" />
                        <svg class="tmr-profile-preview-placeholder" width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="<?php echo $avatar_url ? 'display:none;' : ''; ?>"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
                    </div>
                    <div class="tmr-icon-picker-actions">
                        <button type="button" class="tmr-btn-outline tmr-btn-sm" id="tmr-pick-profile-image"><?php esc_html_e('ছবি নির্বাচন', 'tailor-manager'); ?></button>
                        <button type="button" class="tmr-btn-outline tmr-btn-sm tmr-btn-outline-danger" id="tmr-remove-profile-image" style="<?php echo $avatar_url ? '' : 'display:none;'; ?>"><?php esc_html_e('ছবি সরান', 'tailor-manager'); ?></button>
                    </div>
                </div>

                <div class="tmr-form-row"><label class="tmr-form-label" for="tmr-profile-name"><?php esc_html_e('নাম', 'tailor-manager'); ?></label><input type="text" id="tmr-profile-name" name="display_name" value="<?php echo esc_attr($user->display_name); ?>" required /></div>
                <div class="tmr-form-row"><label class="tmr-form-label" for="tmr-profile-email"><?php esc_html_e('ইমেইল', 'tailor-manager'); ?></label><input type="email" id="tmr-profile-email" name="email" value="<?php echo esc_attr($user->user_email); ?>" required /></div>
                <div class="tmr-form-row"><label class="tmr-form-label" for="tmr-profile-phone"><?php esc_html_e('ফোন', 'tailor-manager'); ?></label><input type="text" id="tmr-profile-phone" name="phone" value="<?php echo esc_attr($phone); ?>" /></div>
                <button type="submit" class="tmr-btn-add"><?php esc_html_e('সেভ করুন', 'tailor-manager'); ?></button>
            </form>
        </div>

        <script>
        jQuery(function ($) {
            var frame;
            $('#tmr-pick-profile-image').on('click', function (e) {
                e.preventDefault();
                if (frame) { frame.open(); return; }
                frame = wp.media({
                    title: '<?php echo esc_js(__('ছবি নির্বাচন করুন', 'tailor-manager')); ?>',
                    multiple: false,
                    library: { type: 'image' }
                });
                frame.on('select', function () {
                    var attachment = frame.state().get('selection').first().toJSON();
                    var url = (attachment.sizes && attachment.sizes.thumbnail) ? attachment.sizes.thumbnail.url : attachment.url;
                    $('#tmr-profile-avatar-id').val(attachment.id);
                    $('.tmr-profile-preview').attr('src', url).show();
                    $('.tmr-profile-preview-placeholder').hide();
                    $('#tmr-remove-profile-image').show();
                });
                frame.open();
            });

            $('#tmr-remove-profile-image').on('click', function (e) {
                e.preventDefault();
                $('#tmr-profile-avatar-id').val(0);
                $('.tmr-profile-preview').hide();
                $('.tmr-profile-preview-placeholder').show();
                $(this).hide();
            });

            $('#tmr-profile-form').on('submit', function (e) {
                e.preventDefault();
                TMRPanel.call('tmr_save_profile', $(this).serialize(), function () {
                    window.alert('<?php echo esc_js(__('সেভ হয়েছে।', 'tailor-manager')); ?>');
                    window.location.reload();
                });
            });
        });
        </script>
        <?php
        TMR_Panel_Shell::footer();
    }

    public function ajax_save_profile()
    {
        check_ajax_referer('tmr_panel_nonce', 'nonce');
        if (!self::can_access()) {
            wp_send_json_error(array('message' => __('অনুমতি নেই।', 'tailor-manager')));
        }

        $user_id      = get_current_user_id();
        $display_name = isset($_POST['display_name']) ? sanitize_text_field(wp_unslash($_POST['display_name'])) : '';
        $email        = isset($_POST['email']) ? sanitize_email(wp_unslash($_POST['email'])) : '';
        $phone        = isset($_POST['phone']) ? sanitize_text_field(wp_unslash($_POST['phone'])) : '';
        $avatar_id    = isset($_POST['avatar_id']) ? (int) $_POST['avatar_id'] : 0;

        if ('' === $display_name) {
            wp_send_json_error(array('message' => __('নাম আবশ্যক।', 'tailor-manager')));
        }
        if (!is_email($email)) {
            wp_send_json_error(array('message' => __('সঠিক ইমেইল দিন।', 'tailor-manager')));
        }
        $existing = email_exists($email);
        if ($existing && (int) $existing !== $user_id) {
            wp_send_json_error(array('message' => __('এই ইমেইল আরেকটি অ্যাকাউন্টে ব্যবহৃত হচ্ছে।', 'tailor-manager')));
        }

        wp_update_user(array('ID' => $user_id, 'display_name' => $display_name, 'user_email' => $email));
        update_user_meta($user_id, self::PHONE_META, $phone);
        if ($avatar_id) {
            update_user_meta($user_id, self::AVATAR_META, $avatar_id);
        } else {
            delete_user_meta($user_id, self::AVATAR_META);
        }

        wp_send_json_success();
    }

    public static function render_change_password()
    {
        if (!self::can_access()) {
            wp_die(esc_html__('এই পেজ দেখার অনুমতি আপনার নেই।', 'tailor-manager'));
        }

        // WP core's own zxcvbn-backed meter (wp-admin/js/password-strength-meter.js)
        // — same scoring wp-admin's native profile.php uses, just remapped below to
        // this plugin's own color/label chips instead of core's markup.
        wp_enqueue_script('password-strength-meter');

        TMR_Panel_Shell::header('change-password', __('পাসওয়ার্ড পরিবর্তন', 'tailor-manager'), __('আপনার লগইন পাসওয়ার্ড পরিবর্তন করুন।', 'tailor-manager'));

        if (isset($_GET['success'])) {
            echo '<div class="tmr-alert tmr-alert-success">' . esc_html__('পাসওয়ার্ড সফলভাবে পরিবর্তিত হয়েছে।', 'tailor-manager') . '</div>';
        } elseif (isset($_GET['error'])) {
            $errors = array(
                'short'    => __('পাসওয়ার্ড কমপক্ষে ৮ অক্ষরের হতে হবে।', 'tailor-manager'),
                'mismatch' => __('দুটি পাসওয়ার্ড মিলছে না।', 'tailor-manager'),
            );
            $err = sanitize_key(wp_unslash($_GET['error']));
            echo '<div class="tmr-alert tmr-alert-danger">' . esc_html(isset($errors[$err]) ? $errors[$err] : __('একটি সমস্যা হয়েছে।', 'tailor-manager')) . '</div>';
        }
        ?>
        <div class="tmr-card-plain" style="max-width:420px;">
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <input type="hidden" name="action" value="tmr_change_password" />
                <?php wp_nonce_field('tmr_change_password'); ?>

                <div class="tmr-form-row">
                    <label class="tmr-form-label" for="tmr-new-password"><?php esc_html_e('নতুন পাসওয়ার্ড', 'tailor-manager'); ?></label>
                    <div class="tmr-password-field">
                        <input type="password" name="new_password" id="tmr-new-password" required autocomplete="new-password" minlength="8" />
                        <button type="button" class="tmr-password-toggle" data-target="tmr-new-password" title="<?php esc_attr_e('দেখান/লুকান', 'tailor-manager'); ?>">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                        </button>
                    </div>
                    <p class="tmr-pass-strength" id="tmr-pass-strength-result"></p>
                </div>

                <div class="tmr-form-row">
                    <label class="tmr-form-label" for="tmr-confirm-password"><?php esc_html_e('পাসওয়ার্ড নিশ্চিত করুন', 'tailor-manager'); ?></label>
                    <div class="tmr-password-field">
                        <input type="password" name="confirm_password" id="tmr-confirm-password" required autocomplete="new-password" minlength="8" />
                        <button type="button" class="tmr-password-toggle" data-target="tmr-confirm-password" title="<?php esc_attr_e('দেখান/লুকান', 'tailor-manager'); ?>">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                        </button>
                    </div>
                    <p class="tmr-form-hint" id="tmr-pass-match-hint"></p>
                </div>

                <button type="submit" class="tmr-btn-add"><?php esc_html_e('পাসওয়ার্ড পরিবর্তন করুন', 'tailor-manager'); ?></button>
            </form>
        </div>

        <script>
        jQuery(function ($) {
            $('.tmr-password-toggle').on('click', function () {
                var $input = $('#' + $(this).data('target'));
                $input.attr('type', 'password' === $input.attr('type') ? 'text' : 'password');
            });

            var labels = [
                '<?php echo esc_js(__('খুবই দুর্বল', 'tailor-manager')); ?>',
                '<?php echo esc_js(__('দুর্বল', 'tailor-manager')); ?>',
                '<?php echo esc_js(__('মোটামুটি', 'tailor-manager')); ?>',
                '<?php echo esc_js(__('ভালো', 'tailor-manager')); ?>',
                '<?php echo esc_js(__('চমৎকার', 'tailor-manager')); ?>'
            ];
            var classes = ['bad', 'bad', 'short', 'good', 'strong'];

            function checkStrength() {
                var pass1 = $('#tmr-new-password').val();
                var $result = $('#tmr-pass-strength-result');
                if (!pass1) { $result.text('').attr('class', 'tmr-pass-strength'); return; }
                var disallowed = wp.passwordStrength.userInputDisallowedList ? wp.passwordStrength.userInputDisallowedList() : wp.passwordStrength.userInputBlacklist();
                var strength = wp.passwordStrength.meter(pass1, disallowed, '');
                var idx = Math.max(0, Math.min(4, strength));
                $result.text(labels[idx]).attr('class', 'tmr-pass-strength tmr-pass-strength-' + classes[idx]);
            }

            function checkMatch() {
                var pass1 = $('#tmr-new-password').val();
                var pass2 = $('#tmr-confirm-password').val();
                var $hint = $('#tmr-pass-match-hint');
                if (!pass2) { $hint.text('').css('color', ''); return; }
                if (pass1 === pass2) {
                    $hint.text('<?php echo esc_js(__('পাসওয়ার্ড মিলেছে', 'tailor-manager')); ?>').css('color', '#16a34a');
                } else {
                    $hint.text('<?php echo esc_js(__('পাসওয়ার্ড মিলছে না', 'tailor-manager')); ?>').css('color', '#dc2626');
                }
            }

            $('#tmr-new-password').on('input', function () { checkStrength(); checkMatch(); });
            $('#tmr-confirm-password').on('input', checkMatch);
        });
        </script>
        <?php
        TMR_Panel_Shell::footer();
    }

    public function handle_change_password()
    {
        if (!is_user_logged_in()) {
            wp_die(esc_html__('আপনি লগইন করেননি।', 'tailor-manager'));
        }
        check_admin_referer('tmr_change_password');

        $redirect_base = admin_url('admin.php?page=tmr-change-password');
        $new_password  = isset($_POST['new_password']) ? (string) $_POST['new_password'] : '';
        $confirm       = isset($_POST['confirm_password']) ? (string) $_POST['confirm_password'] : '';

        if (strlen($new_password) < 8) {
            wp_safe_redirect(add_query_arg('error', 'short', $redirect_base));
            exit;
        }
        if ($new_password !== $confirm) {
            wp_safe_redirect(add_query_arg('error', 'mismatch', $redirect_base));
            exit;
        }

        $user_id = get_current_user_id();
        wp_set_password($new_password, $user_id);
        // wp_set_password() destroys all of this user's sessions, including the
        // current one — re-issue the auth cookie right away so changing your own
        // password doesn't also log you out of it.
        wp_set_auth_cookie($user_id);
        wp_set_current_user($user_id);

        wp_safe_redirect(add_query_arg('success', '1', $redirect_base));
        exit;
    }

    /**
     * Scopes the Media Library grid/AJAX query to the current user's own uploads
     * for tailor_staff accounts only — a manager/administrator can still browse
     * every attachment. Same exemption doctor-appointment makes for its own
     * manager role; staff otherwise browsing/reusing another account's photos
     * (or unrelated site media) isn't a real use case this panel needs.
     */
    public function restrict_media_to_own_uploads($query)
    {
        if (TMR_Staff_Role::is_staff() && !current_user_can(TMR_Panel_Shell::CAPABILITY)) {
            $query['author'] = get_current_user_id();
        }
        return $query;
    }

    public function restrict_media_library_query($query)
    {
        if (is_admin() && $query->is_main_query() && 'attachment' === $query->get('post_type')
            && TMR_Staff_Role::is_staff() && !current_user_can(TMR_Panel_Shell::CAPABILITY)) {
            $query->set('author', get_current_user_id());
        }
    }
}
