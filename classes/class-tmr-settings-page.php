<?php
defined('ABSPATH') || exit;

/**
 * Shop information only — category + measurement field management moved to its own
 * dedicated sidebar page (class-tmr-categories-panel.php) since that's the setting
 * shop-owners actually touch often and deserves its own easy-to-find screen.
 */
class TMR_Settings_Page
{
    public function __construct()
    {
        add_action('wp_ajax_tmr_save_shop_info', array($this, 'ajax_save_shop_info'));
        add_action('wp_ajax_tmr_save_delivery_settings', array($this, 'ajax_save_delivery_settings'));
    }

    public static function render()
    {
        if (!current_user_can(TMR_Panel_Shell::CAPABILITY)) {
            wp_die(esc_html__('এই পেজ দেখার অনুমতি আপনার নেই।', 'tailor-manager'));
        }

        $shop_name    = get_option('tmr_shop_name', get_bloginfo('name'));
        $shop_address = get_option('tmr_shop_address', '');
        $shop_phone   = get_option('tmr_shop_phone', '');
        $default_delivery_days = (int) get_option('tmr_default_delivery_days', 7);

        TMR_Panel_Shell::header('settings', __('সেটিংস', 'tailor-manager'), __('দোকানের তথ্য পরিচালনা করুন।', 'tailor-manager'));
        ?>
        <div class="tmr-card-plain" style="max-width:480px;">
            <h3 style="margin:0 0 16px;font-size:15px;"><?php esc_html_e('দোকানের তথ্য', 'tailor-manager'); ?></h3>
            <form id="tmr-shop-info-form">
                <div class="tmr-form-row"><label class="tmr-form-label"><?php esc_html_e('দোকানের নাম', 'tailor-manager'); ?></label><input type="text" name="shop_name" value="<?php echo esc_attr($shop_name); ?>" /></div>
                <div class="tmr-form-row"><label class="tmr-form-label"><?php esc_html_e('ঠিকানা', 'tailor-manager'); ?></label><textarea name="shop_address" rows="2"><?php echo esc_textarea($shop_address); ?></textarea></div>
                <div class="tmr-form-row"><label class="tmr-form-label"><?php esc_html_e('ফোন', 'tailor-manager'); ?></label><input type="text" name="shop_phone" value="<?php echo esc_attr($shop_phone); ?>" /></div>
                <button type="submit" class="tmr-btn-add"><?php esc_html_e('সেভ করুন', 'tailor-manager'); ?></button>
            </form>
        </div>

        <div class="tmr-card-plain" style="max-width:480px;">
            <h3 style="margin:0 0 16px;font-size:15px;"><?php esc_html_e('ডেলিভারি সেটিংস', 'tailor-manager'); ?></h3>
            <form id="tmr-delivery-settings-form">
                <div class="tmr-form-row">
                    <label class="tmr-form-label"><?php esc_html_e('ডিফল্ট ডেলিভারি সময় (দিনে)', 'tailor-manager'); ?></label>
                    <input type="number" min="0" step="1" name="default_delivery_days" value="<?php echo esc_attr($default_delivery_days); ?>" style="max-width:140px;" />
                    <p class="tmr-form-hint"><?php esc_html_e('নতুন অর্ডার নেওয়ার সময় ডেলিভারি তারিখ আজ থেকে এত দিন পরে স্বয়ংক্রিয়ভাবে নির্বাচিত থাকবে।', 'tailor-manager'); ?></p>
                </div>
                <button type="submit" class="tmr-btn-add"><?php esc_html_e('সেভ করুন', 'tailor-manager'); ?></button>
            </form>
        </div>

        <p style="color:#94a3b8;font-size:13px;">
            <?php
            printf(
                /* translators: %s: link to the Categories page */
                esc_html__('ক্যাটাগরি ও মাপের ফিল্ড এখন %s পেজ থেকে পরিচালনা করুন।', 'tailor-manager'),
                '<a href="' . esc_url(admin_url('admin.php?page=tmr-categories')) . '" style="color:#0061d5;font-weight:600;">' . esc_html__('ক্যাটাগরি', 'tailor-manager') . '</a>'
            );
            ?>
        </p>

        <script>
        jQuery(function ($) {
            $('#tmr-shop-info-form').on('submit', function (e) {
                e.preventDefault();
                TMRPanel.call('tmr_save_shop_info', $(this).serialize(), function () {
                    window.alert('<?php echo esc_js(__('সেভ হয়েছে।', 'tailor-manager')); ?>');
                });
            });

            $('#tmr-delivery-settings-form').on('submit', function (e) {
                e.preventDefault();
                TMRPanel.call('tmr_save_delivery_settings', $(this).serialize(), function () {
                    window.alert('<?php echo esc_js(__('সেভ হয়েছে।', 'tailor-manager')); ?>');
                });
            });
        });
        </script>
        <?php
        TMR_Panel_Shell::footer();
    }

    public function ajax_save_shop_info()
    {
        check_ajax_referer('tmr_panel_nonce', 'nonce');
        if (!current_user_can(TMR_Panel_Shell::CAPABILITY)) {
            wp_send_json_error(array('message' => __('অনুমতি নেই।', 'tailor-manager')));
        }

        update_option('tmr_shop_name', isset($_POST['shop_name']) ? sanitize_text_field(wp_unslash($_POST['shop_name'])) : '');
        update_option('tmr_shop_address', isset($_POST['shop_address']) ? sanitize_textarea_field(wp_unslash($_POST['shop_address'])) : '');
        update_option('tmr_shop_phone', isset($_POST['shop_phone']) ? sanitize_text_field(wp_unslash($_POST['shop_phone'])) : '');

        wp_send_json_success();
    }

    public function ajax_save_delivery_settings()
    {
        check_ajax_referer('tmr_panel_nonce', 'nonce');
        if (!current_user_can(TMR_Panel_Shell::CAPABILITY)) {
            wp_send_json_error(array('message' => __('অনুমতি নেই।', 'tailor-manager')));
        }

        $days = isset($_POST['default_delivery_days']) ? max(0, (int) $_POST['default_delivery_days']) : 7;
        update_option('tmr_default_delivery_days', $days);

        wp_send_json_success();
    }
}
