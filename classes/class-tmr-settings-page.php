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
        $weekly_off_days       = self::get_weekly_off_days();
        $special_off_days      = self::get_special_off_days();
        $weekday_labels        = array(
            0 => __('রবি', 'tailor-manager'),
            1 => __('সোম', 'tailor-manager'),
            2 => __('মঙ্গল', 'tailor-manager'),
            3 => __('বুধ', 'tailor-manager'),
            4 => __('বৃহ', 'tailor-manager'),
            5 => __('শুক্র', 'tailor-manager'),
            6 => __('শনি', 'tailor-manager'),
        );

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
                    <div class="tmr-form-row">
                        <label class="tmr-form-label"><?php esc_html_e('সাপ্তাহিক বন্ধের দিন', 'tailor-manager'); ?></label>
                        <div class="tmr-weekday-checks">
                            <?php foreach ($weekday_labels as $idx => $label) : ?>
                                <label class="tmr-weekday-check">
                                    <input type="checkbox" name="weekly_off_days[]" value="<?php echo esc_attr($idx); ?>" <?php checked(in_array($idx, $weekly_off_days, true)); ?> />
                                    <span><?php echo esc_html($label); ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                        <p class="tmr-form-hint"><?php esc_html_e('এই দিন(গুলো)-এ দোকান বন্ধ থাকে — ডেলিভারি তারিখ হিসেবে বাছাই করা যাবে না, আর স্বয়ংক্রিয়ভাবে বসানো ডেলিভারি তারিখও এই দিন এড়িয়ে পরের দিনে বসবে।', 'tailor-manager'); ?></p>
                    </div>
                    <div class="tmr-form-row">
                        <label class="tmr-form-label"><?php esc_html_e('বিশেষ ছুটির দিন', 'tailor-manager'); ?></label>
                        <div class="tmr-special-day-add-row">
                            <div class="tmr-inline-field" style="position:relative;max-width:180px;">
                                <input type="text" id="tmr-special-day-display" class="tmr-date-display-input" readonly autocomplete="off" placeholder="<?php esc_attr_e('তারিখ নির্বাচন করুন', 'tailor-manager'); ?>" />
                                <div class="tmr-cal-popover" id="tmr-special-day-cal-popover">
                                    <div id="tmr-special-day-cal"></div>
                                </div>
                            </div>
                            <button type="button" class="tmr-btn-outline tmr-btn-sm" id="tmr-add-special-day"><?php esc_html_e('+ যোগ করুন', 'tailor-manager'); ?></button>
                        </div>
                        <div id="tmr-special-days-list" class="tmr-special-days-list">
                            <?php foreach ($special_off_days as $date) : ?>
                                <span class="tmr-special-day-chip" data-date="<?php echo esc_attr($date); ?>"><?php echo esc_html(TMR_Orders_Panel::format_date_bn($date)); ?> <button type="button" class="tmr-special-day-remove" title="<?php esc_attr_e('মুছুন', 'tailor-manager'); ?>">&times;</button></span>
                            <?php endforeach; ?>
                        </div>
                        <input type="hidden" name="special_off_days" id="tmr-special-off-days-input" value="<?php echo esc_attr(implode(',', $special_off_days)); ?>" />
                        <p class="tmr-form-hint"><?php esc_html_e('নির্দিষ্ট কোনো তারিখে দোকান বন্ধ থাকলে (ঈদ বা অন্য কোনো ছুটি) এখানে যোগ করুন — সাপ্তাহিক বন্ধের দিনের মতোই এই তারিখগুলোও ডেলিভারির জন্য বাছাই করা যাবে না।', 'tailor-manager'); ?></p>
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
                /* translators: 1: link to the "পোশাক টাইপ" (category) management page, 2: link to the "পরিমাপ" (measurement fields) management page */
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

            // Same self-built popover-calendar pattern as the order form's own
            // delivery-date picker (custom controls everywhere, not native
            // <input type="date"> chrome) — just for picking a one-off special
            // holiday date to add to the list below.
            var spMonths = <?php echo wp_json_encode(array(
                __('জানুয়ারি', 'tailor-manager'), __('ফেব্রুয়ারি', 'tailor-manager'), __('মার্চ', 'tailor-manager'), __('এপ্রিল', 'tailor-manager'),
                __('মে', 'tailor-manager'), __('জুন', 'tailor-manager'), __('জুলাই', 'tailor-manager'), __('আগস্ট', 'tailor-manager'),
                __('সেপ্টেম্বর', 'tailor-manager'), __('অক্টোবর', 'tailor-manager'), __('নভেম্বর', 'tailor-manager'), __('ডিসেম্বর', 'tailor-manager'),
            )); ?>;
            var spDayHeaders = <?php echo wp_json_encode(array(
                __('রবি', 'tailor-manager'), __('সোম', 'tailor-manager'), __('মঙ্গল', 'tailor-manager'),
                __('বুধ', 'tailor-manager'), __('বৃহ', 'tailor-manager'), __('শুক্র', 'tailor-manager'), __('শনি', 'tailor-manager'),
            )); ?>;
            var spYear, spMonth, spSelected = '';

            function spPad2(n) { return n < 10 ? '0' + n : '' + n; }
            function spDaysInMonth(y, m) { return new Date(y, m + 1, 0).getDate(); }
            function spFirstDayOfMonth(y, m) { return new Date(y, m, 1).getDay(); }

            function renderSpecialDayCalendar() {
                var firstDay = spFirstDayOfMonth(spYear, spMonth);
                var days = spDaysInMonth(spYear, spMonth);
                var today = new Date();
                var todayStr = today.getFullYear() + '-' + spPad2(today.getMonth() + 1) + '-' + spPad2(today.getDate());
                var existing = ($('#tmr-special-off-days-input').val() || '').split(',').filter(Boolean);

                var html = '<div class="tmr-cal-nav">';
                html += '<button type="button" class="tmr-cal-nav-btn" data-action="prev">&lsaquo;</button>';
                html += '<span class="tmr-cal-title">' + spMonths[spMonth] + ' ' + spYear + '</span>';
                html += '<button type="button" class="tmr-cal-nav-btn" data-action="next">&rsaquo;</button>';
                html += '</div>';
                html += '<div class="tmr-cal-grid">';
                spDayHeaders.forEach(function (d) { html += '<span class="tmr-cal-day-header">' + d + '</span>'; });
                for (var i = 0; i < firstDay; i++) { html += '<span class="tmr-cal-day empty"></span>'; }
                for (var d = 1; d <= days; d++) {
                    var dateStr = spYear + '-' + spPad2(spMonth + 1) + '-' + spPad2(d);
                    var cls = 'tmr-cal-day';
                    if (dateStr === todayStr) { cls += ' today'; }
                    if (dateStr < todayStr) { cls += ' past'; }
                    if (dateStr === spSelected) { cls += ' selected'; }
                    if (existing.indexOf(dateStr) !== -1) { cls += ' off'; }
                    html += '<span class="' + cls + '" data-date="' + dateStr + '">' + d + '</span>';
                }
                html += '</div>';
                $('#tmr-special-day-cal').html(html);
            }

            function initSpecialDayCalendar() {
                var d = new Date();
                spYear = d.getFullYear();
                spMonth = d.getMonth();
                renderSpecialDayCalendar();
            }
            initSpecialDayCalendar();

            $(document).on('click', '#tmr-special-day-display', function (e) {
                e.stopPropagation();
                $('#tmr-special-day-cal-popover').toggle();
            });

            $(document).on('click', '#tmr-special-day-cal-popover .tmr-cal-nav-btn', function (e) {
                e.stopPropagation();
                if ($(this).data('action') === 'prev') {
                    spMonth--;
                    if (spMonth < 0) { spMonth = 11; spYear--; }
                } else {
                    spMonth++;
                    if (spMonth > 11) { spMonth = 0; spYear++; }
                }
                renderSpecialDayCalendar();
            });

            $(document).on('click', '#tmr-special-day-cal-popover .tmr-cal-day:not(.empty):not(.past):not(.off)', function (e) {
                e.stopPropagation();
                spSelected = $(this).data('date');
                var p = spSelected.split('-');
                $('#tmr-special-day-display').val(parseInt(p[2], 10) + ' ' + spMonths[parseInt(p[1], 10) - 1] + ', ' + p[0]);
                $('#tmr-special-day-cal-popover').hide();
                renderSpecialDayCalendar();
            });

            $(document).on('click', function (e) {
                if (!$(e.target).closest('#tmr-special-day-cal-popover, #tmr-special-day-display').length) {
                    $('#tmr-special-day-cal-popover').hide();
                }
            });

            function addSpecialDayChip(dateStr) {
                var p = dateStr.split('-');
                var label = parseInt(p[2], 10) + ' ' + spMonths[parseInt(p[1], 10) - 1] + ', ' + p[0];
                var $chip = $('<span class="tmr-special-day-chip"></span>').attr('data-date', dateStr).text(label + ' ');
                $chip.append($('<button type="button" class="tmr-special-day-remove">&times;</button>'));
                $('#tmr-special-days-list').append($chip);
            }

            function syncSpecialOffDaysInput() {
                var dates = $('#tmr-special-days-list .tmr-special-day-chip').map(function () {
                    return $(this).data('date');
                }).get();
                $('#tmr-special-off-days-input').val(dates.join(','));
            }

            $('#tmr-add-special-day').on('click', function () {
                if (!spSelected) { return; }
                var existing = ($('#tmr-special-off-days-input').val() || '').split(',').filter(Boolean);
                if (existing.indexOf(spSelected) === -1) {
                    addSpecialDayChip(spSelected);
                    syncSpecialOffDaysInput();
                    renderSpecialDayCalendar();
                }
                spSelected = '';
                $('#tmr-special-day-display').val('');
            });

            $(document).on('click', '.tmr-special-day-remove', function () {
                $(this).closest('.tmr-special-day-chip').remove();
                syncSpecialOffDaysInput();
                renderSpecialDayCalendar();
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

        $weekly_off_days = isset($_POST['weekly_off_days']) && is_array($_POST['weekly_off_days'])
            ? array_values(array_unique(array_intersect(array_map('intval', $_POST['weekly_off_days']), range(0, 6))))
            : array();
        update_option('tmr_weekly_off_days', $weekly_off_days);

        $special_raw  = isset($_POST['special_off_days']) ? sanitize_text_field(wp_unslash($_POST['special_off_days'])) : '';
        $special_days = array_filter(array_map('trim', explode(',', $special_raw)), function ($d) {
            return (bool) preg_match('/^\d{4}-\d{2}-\d{2}$/', $d);
        });
        update_option('tmr_special_off_days', array_values(array_unique($special_days)));

        wp_send_json_success();
    }

    public static function get_weekly_off_days()
    {
        $days = get_option('tmr_weekly_off_days', array());
        return is_array($days) ? array_map('intval', $days) : array();
    }

    public static function get_special_off_days()
    {
        $days = get_option('tmr_special_off_days', array());
        return is_array($days) ? $days : array();
    }

    /**
     * Shop-closed check for a Y-m-d date — either its weekday matches the
     * configured weekly off day(s), or it's in the one-off special-holiday
     * list. Used both to block those dates in the delivery-date calendar and
     * to skip the auto-suggested default delivery date past them.
     */
    public static function is_off_day($ymd)
    {
        if (!$ymd) {
            return false;
        }
        if (in_array($ymd, self::get_special_off_days(), true)) {
            return true;
        }
        $weekday = (int) gmdate('w', strtotime($ymd));
        return in_array($weekday, self::get_weekly_off_days(), true);
    }
}
