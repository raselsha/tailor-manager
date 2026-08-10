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
        $order_draft_enabled   = (bool) get_option('tmr_order_draft_enabled', '1');

        TMR_Panel_Shell::header('settings', __('সেটিংস', 'tailor-manager'), __('দোকানের তথ্য পরিচালনা করুন।', 'tailor-manager'));
        ?>
        <div class="tmr-settings-grid">
            <div class="tmr-card-plain">
                <h3 style="margin:0 0 16px;font-size:15px;"><?php esc_html_e('দোকানের তথ্য', 'tailor-manager'); ?></h3>
                <form id="tmr-shop-info-form">
                    <div class="tmr-form-row"><label class="tmr-form-label"><?php esc_html_e('দোকানের নাম', 'tailor-manager'); ?></label><input type="text" name="shop_name" value="<?php echo esc_attr($shop_name); ?>" /></div>
                    <div class="tmr-form-row"><label class="tmr-form-label"><?php esc_html_e('ঠিকানা', 'tailor-manager'); ?></label><textarea name="shop_address" rows="2"><?php echo esc_textarea($shop_address); ?></textarea></div>
                    <div class="tmr-form-row"><label class="tmr-form-label"><?php esc_html_e('ফোন', 'tailor-manager'); ?></label><input type="text" name="shop_phone" value="<?php echo esc_attr($shop_phone); ?>" /></div>
                    <div class="tmr-form-save-row">
                        <button type="submit" class="tmr-btn-add"><?php esc_html_e('সেভ করুন', 'tailor-manager'); ?></button>
                        <span class="tmr-inline-save-msg" id="tmr-shop-info-msg"><span>&#10003;</span> <?php esc_html_e('সেভ হয়েছে।', 'tailor-manager'); ?></span>
                    </div>
                </form>
            </div>

            <div class="tmr-card-plain">
                <h3 style="margin:0 0 16px;font-size:15px;"><?php esc_html_e('ডেলিভারি সেটিংস', 'tailor-manager'); ?></h3>
                <form id="tmr-delivery-settings-form">
                    <div class="tmr-form-row">
                        <label class="tmr-form-label"><?php esc_html_e('ডিফল্ট ডেলিভারি সময় (দিনে)', 'tailor-manager'); ?></label>
                        <input type="number" min="0" step="1" name="default_delivery_days" value="<?php echo esc_attr($default_delivery_days); ?>" style="max-width:140px;" />
                        <p class="tmr-form-hint"><?php esc_html_e('নতুন অর্ডার নেওয়ার সময় ডেলিভারি তারিখ আজ থেকে এত দিন পরে স্বয়ংক্রিয়ভাবে নির্বাচিত থাকবে।', 'tailor-manager'); ?></p>
                    </div>
                    <div class="tmr-form-row">
                        <label class="tmr-toggle">
                            <input type="checkbox" name="order_draft_enabled" value="1" <?php checked($order_draft_enabled); ?> />
                            <span class="tmr-toggle-slider"></span>
                            <span class="tmr-form-label" style="margin:0;"><?php esc_html_e('অর্ডার খসড়া সংরক্ষণ', 'tailor-manager'); ?></span>
                        </label>
                        <p class="tmr-form-hint"><?php esc_html_e('চালু থাকলে, নতুন অর্ডার ফর্মে যা লেখা হচ্ছে তা এই ডিভাইসেই সাথে সাথে সংরক্ষিত হয় — অন্য পেজে গিয়ে ফিরে এলেও ফর্মের ডেটা থেকে যায়। বন্ধ থাকলে প্রতিবার ফাঁকা ফর্ম দেখাবে।', 'tailor-manager'); ?></p>
                    </div>
                    <div class="tmr-form-save-row">
                        <button type="submit" class="tmr-btn-add"><?php esc_html_e('সেভ করুন', 'tailor-manager'); ?></button>
                        <span class="tmr-inline-save-msg" id="tmr-delivery-settings-msg"><span>&#10003;</span> <?php esc_html_e('সেভ হয়েছে।', 'tailor-manager'); ?></span>
                    </div>
                </form>
            </div>
        </div>

        <p style="color:#94a3b8;font-size:13px;">
            <?php
            printf(
                /* translators: 1: link to the "পোশাক" (category) management page, 2: link to the "পোশাকের পরিমাপ" (measurement fields) management page */
                esc_html__('পোশাক এখন %1$s পেজ থেকে এবং পরিমাপের ফিল্ড %2$s পেজ থেকে পরিচালনা করুন।', 'tailor-manager'),
                '<a href="' . esc_url(admin_url('admin.php?page=' . TMR_Panel_Shell::$nav['categories']['slug'])) . '" style="color:#0061d5;font-weight:600;">' . esc_html(TMR_Panel_Shell::$nav['categories']['title']) . '</a>',
                '<a href="' . esc_url(admin_url('admin.php?page=' . TMR_Panel_Shell::$nav['measurement-fields']['slug'])) . '" style="color:#0061d5;font-weight:600;">' . esc_html(TMR_Panel_Shell::$nav['measurement-fields']['title']) . '</a>'
            );
            ?>
        </p>

        <script>
        jQuery(function ($) {
            // Briefly shows the small inline "✓ সেভ হয়েছে।" next to the button that
            // was just clicked, instead of a native window.alert() popup.
            function flashSaveMessage($msg) {
                $msg.stop(true, true).css('display', 'inline-flex').hide().fadeIn(150).delay(1800).fadeOut(400);
            }

            $('#tmr-shop-info-form').on('submit', function (e) {
                e.preventDefault();
                var newShopName = $(this).find('input[name="shop_name"]').val();
                var newShopPhone = $(this).find('input[name="shop_phone"]').val();
                var $form = $(this);
                TMRPanel.call('tmr_save_shop_info', $form.serialize(), function () {
                    // render_sidebar() only re-reads these options on the next full
                    // page load — this AJAX save never triggers one, so the sidebar
                    // logo/phone are patched here directly for an instant update.
                    $('#tmr-sidebar-shop-name').text(newShopName || '<?php echo esc_js(get_bloginfo('name')); ?>');
                    $('#tmr-sidebar-shop-phone').text(newShopPhone).toggle(!!newShopPhone);
                    flashSaveMessage($('#tmr-shop-info-msg'));
                });
            });

            $('#tmr-delivery-settings-form').on('submit', function (e) {
                e.preventDefault();
                TMRPanel.call('tmr_save_delivery_settings', $(this).serialize(), function () {
                    flashSaveMessage($('#tmr-delivery-settings-msg'));
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
        update_option('tmr_order_draft_enabled', !empty($_POST['order_draft_enabled']) ? '1' : '0');

        wp_send_json_success();
    }
}
