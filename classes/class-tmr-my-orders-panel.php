<?php
defined('ABSPATH') || exit;

/**
 * The tailor_staff restricted dashboard — mirrors doctor-appointment's doctor-role
 * "Booking" view: a queue of only the logged-in staff member's own work. Ownership is
 * matched by comparing each order item's plain-text cutter name against the logged-in
 * user's display name (case-insensitive, trimmed) — cutter_name deliberately stayed a
 * free-text field (not a user_id FK) per the confirmed scope, so this match is best-effort
 * on exact naming, same as the real system's own paper-trail convention.
 */
class TMR_My_Orders_Panel
{
    public function __construct()
    {
        add_action('wp_ajax_tmr_view_staff_measurements', array($this, 'ajax_view_measurements'));
    }

    public static function render()
    {
        if (!current_user_can(TMR_Staff_Role::CAPABILITY)) {
            wp_die(esc_html__('এই পেজ দেখার অনুমতি আপনার নেই।', 'tailor-manager'));
        }

        $user      = wp_get_current_user();
        $order_ids = self::get_my_order_ids($user->display_name);
        $search    = isset($_GET['s']) ? sanitize_text_field(wp_unslash($_GET['s'])) : '';
        $date_filter = isset($_GET['date']) ? sanitize_text_field(wp_unslash($_GET['date'])) : '';

        $pending = $ready = $delivered_today = 0;
        $rows    = array();

        foreach ($order_ids as $order_id) {
            $is_delivered = TMR_Order_Post_Type::is_delivered($order_id);
            $is_ready     = TMR_Order_Post_Type::is_ready($order_id);

            if ($is_delivered) {
                if (get_post_meta($order_id, '_tmr_delivery_date', true) === current_time('Y-m-d')) {
                    $delivered_today++;
                }
                continue; // queue only shows outstanding work, matching the doctor "today queue" idea
            }

            $is_ready ? $ready++ : $pending++;
            $rows[] = $order_id;
        }

        // Search/date only narrow what the table below shows — the stat cards above
        // always reflect the full outstanding queue regardless of an active filter.
        $display_rows = array_filter($rows, function ($order_id) use ($search, $date_filter) {
            if ('' !== $date_filter && get_post_meta($order_id, '_tmr_delivery_date', true) !== $date_filter) {
                return false;
            }
            if ('' !== $search) {
                $customer_id  = (int) get_post_meta($order_id, '_tmr_customer_id', true);
                $name         = $customer_id ? get_the_title($customer_id) : __('ওয়াক-ইন', 'tailor-manager');
                $matches_id   = false !== strpos((string) $order_id, $search);
                $matches_name = false !== mb_stripos($name, $search);
                if (!$matches_id && !$matches_name) {
                    return false;
                }
            }
            return true;
        });

        // Rendered as $sticky_content (not inline below) so it renders *outside*
        // TMR_Panel_Shell's .tmr-scroll-wrap, alongside the pinned title — only the
        // order list/cards inside .tmr-scroll-wrap scroll, the stats + filter bar
        // (and the rest of the page) never do.
        ob_start();
        ?>
        <div class="tmr-stats-grid tmr-stats-grid-compact">
            <div class="tmr-stat-card tmr-stat-card-amber">
                <div class="tmr-stat-icon"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg></div>
                <div class="tmr-stat-body">
                    <h4><?php esc_html_e('পেন্ডিং', 'tailor-manager'); ?></h4>
                    <div class="value"><?php echo esc_html($pending); ?></div>
                </div>
            </div>
            <div class="tmr-stat-card tmr-stat-card-blue">
                <div class="tmr-stat-icon"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 11l3 3L22 4"></path><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"></path></svg></div>
                <div class="tmr-stat-body">
                    <h4><?php esc_html_e('রেডি', 'tailor-manager'); ?></h4>
                    <div class="value"><?php echo esc_html($ready); ?></div>
                </div>
            </div>
            <div class="tmr-stat-card tmr-stat-card-green">
                <div class="tmr-stat-icon"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="3"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg></div>
                <div class="tmr-stat-body">
                    <h4><?php esc_html_e('আজ ডেলিভার হয়েছে', 'tailor-manager'); ?></h4>
                    <div class="value"><?php echo esc_html($delivered_today); ?></div>
                </div>
            </div>
        </div>

        <div class="tmr-filters-bar">
            <form method="get" class="tmr-myorders-filter-form">
                <input type="hidden" name="page" value="tmr-my-orders" />
                <div class="tmr-filter-input-wrap">
                    <svg class="tmr-filter-input-icon" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                    <input type="text" name="s" class="tmr-filter-input" value="<?php echo esc_attr($search); ?>" placeholder="<?php esc_attr_e('অর্ডার আইডি বা নাম', 'tailor-manager'); ?>" />
                </div>
                <div class="tmr-filter-input-wrap">
                    <svg class="tmr-filter-input-icon" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="3"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>
                    <input type="text" id="tmr-myorders-date-display" class="tmr-filter-input tmr-date-display-input" readonly autocomplete="off" placeholder="<?php esc_attr_e('তারিখ', 'tailor-manager'); ?>" />
                    <input type="hidden" name="date" id="tmr-myorders-date-input" value="<?php echo esc_attr($date_filter); ?>" />
                    <div class="tmr-cal-popover" id="tmr-myorders-cal-popover">
                        <div id="tmr-myorders-cal"></div>
                    </div>
                </div>
                <button type="submit" class="tmr-filter-submit-btn" title="<?php esc_attr_e('ফিল্টার করুন', 'tailor-manager'); ?>">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"></polygon></svg>
                    <span class="tmr-filter-submit-label"><?php esc_html_e('ফিল্টার করুন', 'tailor-manager'); ?></span>
                </button>
                <?php if ($search || $date_filter) : ?>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=tmr-my-orders')); ?>" class="tmr-filter-clear-btn" title="<?php esc_attr_e('মুছে ফেলুন', 'tailor-manager'); ?>">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
                    </a>
                <?php endif; ?>
            </form>
        </div>
        <?php
        $sticky_html = ob_get_clean();

        TMR_Panel_Shell::header(
            'dashboard',
            sprintf(__('স্বাগতম, %s', 'tailor-manager'), $user->display_name),
            __('আপনার নির্ধারিত অর্ডারসমূহ।', 'tailor-manager'),
            '',
            true,
            $sticky_html
        );
        ?>

        <div class="tmr-dashboard-grid-container tmr-card-plain">
            <div class="tmr-section-header">
                <h3><?php esc_html_e('আমার অর্ডার', 'tailor-manager'); ?></h3>
            </div>

            <?php if (empty($display_rows)) : ?>
                <div class="tmr-card"><table class="tmr-table"><tbody><tr><td class="tmr-empty"><?php echo empty($rows) ? esc_html__('আপনার নামে কোনো বাকি অর্ডার নেই।', 'tailor-manager') : esc_html__('এই ফিল্টারে কোনো অর্ডার পাওয়া যায়নি।', 'tailor-manager'); ?></td></tr></tbody></table></div>
            <?php else : ?>
                <div class="tmr-card">
                    <div class="tmr-table-cards">
                        <table class="tmr-table">
                            <thead>
                                <tr>
                                    <th><?php esc_html_e('অর্ডার', 'tailor-manager'); ?></th>
                                    <th><?php esc_html_e('ড্রেস', 'tailor-manager'); ?></th>
                                    <th><?php esc_html_e('ডেলিভারি তারিখ', 'tailor-manager'); ?></th>
                                    <th><?php esc_html_e('স্ট্যাটাস', 'tailor-manager'); ?></th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($display_rows as $order_id) :
                                    $customer_id = (int) get_post_meta($order_id, '_tmr_customer_id', true);
                                    $name        = $customer_id ? get_the_title($customer_id) : __('ওয়াক-ইন', 'tailor-manager');
                                    $ready       = TMR_Order_Post_Type::is_ready($order_id);
                                ?>
                                    <tr>
                                        <td data-label="<?php esc_attr_e('অর্ডার', 'tailor-manager'); ?>">#<?php echo esc_html($order_id); ?> — <?php echo esc_html($name); ?></td>
                                        <td data-label="<?php esc_attr_e('ড্রেস', 'tailor-manager'); ?>"><?php echo esc_html(self::dress_summary($order_id)); ?></td>
                                        <td data-label="<?php esc_attr_e('ডেলিভারি তারিখ', 'tailor-manager'); ?>"><?php echo esc_html(get_post_meta($order_id, '_tmr_delivery_date', true)); ?></td>
                                        <td data-label="<?php esc_attr_e('স্ট্যাটাস', 'tailor-manager'); ?>"><span class="tmr-badge tmr-badge-<?php echo $ready ? 'ready' : 'pending'; ?>"><?php echo $ready ? esc_html__('রেডি', 'tailor-manager') : esc_html__('পেন্ডিং', 'tailor-manager'); ?></span></td>
                                        <td class="tmr-myorders-actions-cell">
                                            <div class="tmr-myorders-row-actions">
                                                <span class="tmr-icon-btn tmr-view-staff-order" data-id="<?php echo esc_attr($order_id); ?>" title="<?php esc_attr_e('মাপ দেখুন', 'tailor-manager'); ?>"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg></span>
                                                <button type="button" class="tmr-btn-outline tmr-btn-sm tmr-toggle-ready" data-id="<?php echo esc_attr($order_id); ?>"><?php echo $ready ? esc_html__('নট রেডি করুন', 'tailor-manager') : esc_html__('রেডি করুন', 'tailor-manager'); ?></button>
                                                <button type="button" class="tmr-btn-add tmr-btn-sm tmr-toggle-delivered" data-id="<?php echo esc_attr($order_id); ?>"><?php esc_html_e('ডেলিভারড করুন', 'tailor-manager'); ?></button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- Deliberately minimal — staff only ever need order/delivery date and each
             dress's measurements to cut/sew from, not customer name/phone, pricing,
             or design/part selections. The full confirmation modal (customer info,
             QR, print, pricing) stays manager/admin-only in TMR_Orders_Panel. -->
        <div class="tmr-modal" id="tmr-staff-view-modal">
            <div class="tmr-modal-content">
                <div class="tmr-modal-head">
                    <h2 id="tmr-staff-view-title"></h2>
                    <button type="button" class="tmr-modal-close">&times;</button>
                </div>
                <div class="tmr-modal-body">
                    <div class="tmr-staff-view-dates">
                        <div>
                            <span class="tmr-form-label"><?php esc_html_e('অর্ডার তারিখ', 'tailor-manager'); ?></span>
                            <strong id="tmr-staff-view-order-date"></strong>
                        </div>
                        <div>
                            <span class="tmr-form-label"><?php esc_html_e('ডেলিভারি তারিখ', 'tailor-manager'); ?></span>
                            <strong id="tmr-staff-view-delivery-date"></strong>
                        </div>
                    </div>
                    <div id="tmr-staff-view-items"></div>
                </div>
            </div>
        </div>

        <script>
        jQuery(function ($) {
            // Delivery-date filter — same self-built calendar-popover pattern as the
            // order form's own delivery-date picker (.tmr-cal-popover), not a native
            // <input type="date">.
            var bnMonths = ['জানুয়ারি', 'ফেব্রুয়ারি', 'মার্চ', 'এপ্রিল', 'মে', 'জুন', 'জুলাই', 'আগস্ট', 'সেপ্টেম্বর', 'অক্টোবর', 'নভেম্বর', 'ডিসেম্বর'];
            var bnDayHeaders = ['রবি', 'সোম', 'মঙ্গল', 'বুধ', 'বৃহ', 'শুক্র', 'শনি'];
            var mcalYear, mcalMonth, mcalSelected;

            function mcalPad2(n) { return n < 10 ? '0' + n : '' + n; }
            function mcalDaysInMonth(y, m) { return new Date(y, m + 1, 0).getDate(); }
            function mcalFirstDay(y, m) { return new Date(y, m, 1).getDay(); }

            function mcalFormatDisplay(dateStr) {
                if (!dateStr) { return ''; }
                var p = dateStr.split('-');
                return parseInt(p[2], 10) + ' ' + bnMonths[parseInt(p[1], 10) - 1] + ', ' + p[0];
            }

            function renderMyOrdersCalendar() {
                var firstDay = mcalFirstDay(mcalYear, mcalMonth);
                var days = mcalDaysInMonth(mcalYear, mcalMonth);
                var today = new Date();
                var todayStr = today.getFullYear() + '-' + mcalPad2(today.getMonth() + 1) + '-' + mcalPad2(today.getDate());

                var html = '<div class="tmr-cal-nav">';
                html += '<button type="button" class="tmr-cal-nav-btn" data-action="prev">&lsaquo;</button>';
                html += '<span class="tmr-cal-title">' + bnMonths[mcalMonth] + ' ' + mcalYear + '</span>';
                html += '<button type="button" class="tmr-cal-nav-btn" data-action="next">&rsaquo;</button>';
                html += '</div>';
                html += '<div class="tmr-cal-grid">';
                bnDayHeaders.forEach(function (d) { html += '<span class="tmr-cal-day-header">' + d + '</span>'; });
                for (var i = 0; i < firstDay; i++) { html += '<span class="tmr-cal-day empty"></span>'; }
                for (var d = 1; d <= days; d++) {
                    var dateStr = mcalYear + '-' + mcalPad2(mcalMonth + 1) + '-' + mcalPad2(d);
                    var cls = 'tmr-cal-day';
                    if (dateStr === todayStr) { cls += ' today'; }
                    if (dateStr === mcalSelected) { cls += ' selected'; }
                    html += '<span class="' + cls + '" data-date="' + dateStr + '">' + d + '</span>';
                }
                html += '</div>';
                $('#tmr-myorders-cal').html(html);
            }

            (function initMyOrdersCalendar() {
                var initial = $('#tmr-myorders-date-input').val();
                var d = initial ? new Date(initial + 'T00:00:00') : new Date();
                mcalYear = d.getFullYear();
                mcalMonth = d.getMonth();
                mcalSelected = initial || '';
                if (initial) { $('#tmr-myorders-date-display').val(mcalFormatDisplay(initial)); }
                renderMyOrdersCalendar();
            })();

            $(document).on('click', '#tmr-myorders-date-display', function (e) {
                e.stopPropagation();
                $('#tmr-myorders-cal-popover').toggle();
            });
            $(document).on('click', '#tmr-myorders-cal-popover .tmr-cal-nav-btn', function (e) {
                // Re-rendering below replaces this very button's DOM node, so it must
                // stop the event from reaching the "click outside closes the popover"
                // handler further down — otherwise that handler inspects e.target
                // *after* it's already been detached from the document, closest()
                // finds nothing, and the popover incorrectly closes on every nav click.
                e.stopPropagation();
                if ('prev' === $(this).data('action')) {
                    mcalMonth--;
                    if (mcalMonth < 0) { mcalMonth = 11; mcalYear--; }
                } else {
                    mcalMonth++;
                    if (mcalMonth > 11) { mcalMonth = 0; mcalYear++; }
                }
                renderMyOrdersCalendar();
            });
            $(document).on('click', '#tmr-myorders-cal-popover .tmr-cal-day:not(.empty)', function (e) {
                e.stopPropagation();
                mcalSelected = $(this).data('date');
                $('#tmr-myorders-date-input').val(mcalSelected);
                $('#tmr-myorders-date-display').val(mcalFormatDisplay(mcalSelected));
                $('#tmr-myorders-cal-popover').hide();
            });
            $(document).on('click', function (e) {
                if (!$(e.target).closest('#tmr-myorders-cal-popover, #tmr-myorders-date-display').length) {
                    $('#tmr-myorders-cal-popover').hide();
                }
            });

            $(document).on('click', '.tmr-toggle-ready', function () {
                var $row = $(this).closest('tr');
                TMRPanel.call('tmr_toggle_ready', { id: $(this).data('id') }, function () {
                    window.location.reload();
                });
            });
            $(document).on('click', '.tmr-toggle-delivered', function () {
                if (!window.confirm('<?php echo esc_js(__('এই অর্ডারটি ডেলিভার হয়েছে বলে চিহ্নিত করবেন?', 'tailor-manager')); ?>')) { return; }
                TMRPanel.call('tmr_toggle_delivered', { id: $(this).data('id') }, function () {
                    window.location.reload();
                });
            });

            var $viewModal = $('#tmr-staff-view-modal');
            $(document).on('click', '.tmr-view-staff-order', function () {
                var id = $(this).data('id');
                TMRPanel.call('tmr_view_staff_measurements', { id: id }, function (data) {
                    $('#tmr-staff-view-title').text('#' + data.order_id);
                    $('#tmr-staff-view-order-date').text(data.order_date);
                    $('#tmr-staff-view-delivery-date').text(data.delivery_date);

                    var $items = $('#tmr-staff-view-items').empty();
                    data.items.forEach(function (item) {
                        var heading = item.category + (item.dress_summary ? ' — ' + item.dress_summary : '');
                        $items.append($('<div class="tmr-vp-block-title"></div>').text(heading));
                        var $grid = $('<div class="tmr-vp-measure-grid"></div>');
                        if (item.measurements.length) {
                            item.measurements.forEach(function (m) {
                                var $card = $('<div class="tmr-vp-measure-card"></div>');
                                $card.append($('<span class="tmr-vp-measure-label"></span>').text(m.label));
                                $card.append($('<strong class="tmr-vp-measure-value"></strong>').text(m.value));
                                $grid.append($card);
                            });
                        } else {
                            $grid.append($('<span class="tmr-empty"></span>').text('<?php echo esc_js(__('মাপ যোগ করা হয়নি।', 'tailor-manager')); ?>'));
                        }
                        $items.append($grid);
                    });

                    TMRPanel.openModal($viewModal);
                }, function (message) {
                    window.alert(message);
                });
            });
        });
        </script>
        <?php
        TMR_Panel_Shell::footer();
    }

    public function ajax_view_measurements()
    {
        check_ajax_referer('tmr_panel_nonce', 'nonce');
        if (!current_user_can(TMR_Staff_Role::CAPABILITY)) {
            wp_send_json_error(array('message' => __('অনুমতি নেই।', 'tailor-manager')));
        }

        $order_id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
        $user     = wp_get_current_user();

        // Ownership check — a staff account may only view measurements for orders
        // where they're actually the cutter/tailor, same matching get_my_order_ids()
        // already uses to build their own "আমার অর্ডার" list.
        if (!in_array($order_id, self::get_my_order_ids($user->display_name), true)) {
            wp_send_json_error(array('message' => __('এই অর্ডারটি আপনার নয়।', 'tailor-manager')));
        }

        $field_labels = TMR_Measurement_Fields::get_library();
        $items = array();

        foreach (TMR_Order_Post_Type::get_items($order_id) as $item) {
            $cat_id = TMR_Order_Item_Post_Type::get_category_id($item->ID);
            $term   = get_term($cat_id, TMR_Category_Taxonomy::TAXONOMY);

            $dress_names = array();
            foreach (TMR_Order_Item_Post_Type::get_dresses($item->ID) as $d) {
                $dress = !empty($d['dress_id']) ? get_post($d['dress_id']) : null;
                if ($dress) {
                    $dress_names[] = $dress->post_title . '(' . (int) $d['quantity'] . ')';
                }
            }

            $measurements = array();
            foreach (TMR_Order_Item_Post_Type::get_measurements($item->ID) as $slug => $val) {
                $val = trim((string) $val);
                if ('' === $val || '0' === $val) {
                    continue;
                }
                $measurements[] = array(
                    'label' => isset($field_labels[$slug]) ? $field_labels[$slug] : $slug,
                    'value' => $val,
                );
            }

            $items[] = array(
                'category'      => $term && !is_wp_error($term) ? $term->name : '',
                'dress_summary' => implode(', ', $dress_names),
                'measurements'  => $measurements,
            );
        }

        wp_send_json_success(array(
            'order_id'      => $order_id,
            'order_date'    => get_post_meta($order_id, '_tmr_order_date', true),
            'delivery_date' => get_post_meta($order_id, '_tmr_delivery_date', true),
            'items'         => $items,
        ));
    }

    /**
     * @return int[] order IDs (deduplicated) where at least one item's cutter name
     *               matches the given staff display name
     */
    public static function get_my_order_ids($display_name)
    {
        if ('' === trim($display_name)) {
            return array();
        }

        // Cutting master and sewing operator are two independent roles a staff login
        // could be assigned as — "my work" means either one, not just the cutter slot.
        $items = get_posts(array(
            'post_type'      => TMR_Order_Item_Post_Type::POST_TYPE,
            'post_status'    => 'any',
            'posts_per_page' => -1,
            'meta_query'     => array(
                'relation' => 'OR',
                array('key' => '_tmr_cutter_name', 'value' => $display_name, 'compare' => '='),
                array('key' => '_tmr_tailor_name', 'value' => $display_name, 'compare' => '='),
            ),
        ));

        $order_ids = array();
        foreach ($items as $item) {
            if ($item->post_parent) {
                $order_ids[$item->post_parent] = true;
            }
        }

        $order_ids = array_keys($order_ids);
        rsort($order_ids);

        return $order_ids;
    }

    private static function dress_summary($order_id)
    {
        $parts = array();
        foreach (TMR_Order_Post_Type::get_items($order_id) as $item) {
            foreach (TMR_Order_Item_Post_Type::get_dresses($item->ID) as $d) {
                $dress = get_post($d['dress_id']);
                if ($dress) {
                    $parts[] = $dress->post_title . '(' . (int) $d['quantity'] . ')';
                }
            }
        }
        return implode(' ', $parts);
    }
}
