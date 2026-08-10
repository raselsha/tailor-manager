<?php
defined('ABSPATH') || exit;

/**
 * Customers screen: debounced AJAX live search (same #tmr-customers-list-wrap
 * re-render pattern as the Orders panel's own search) + an AJAX-submitted
 * modal for add/edit, + AJAX delete. Pagination links still do a plain page
 * load — deep-linkable/bookmarkable on their own — since only the search
 * input itself needs to avoid a full reload per keystroke.
 */
class TMR_Customers_Panel
{
    const POST_TYPE = TMR_Customer_Post_Type::POST_TYPE;
    const PER_PAGE = 20;

    public function __construct()
    {
        add_action('wp_ajax_tmr_save_customer', array($this, 'ajax_save'));
        add_action('wp_ajax_tmr_delete_customer', array($this, 'ajax_delete'));
        add_action('wp_ajax_tmr_get_customer', array($this, 'ajax_get'));
        add_action('wp_ajax_tmr_search_customers_list', array($this, 'ajax_search'));
    }

    private static function build_query($search, $paged)
    {
        return new WP_Query(array(
            'post_type'      => self::POST_TYPE,
            'post_status'    => array('publish', 'draft'),
            's'              => $search,
            'posts_per_page' => self::PER_PAGE,
            'paged'          => $paged,
            'orderby'        => 'title',
            'order'          => 'ASC',
        ));
    }

    public static function render()
    {
        if (!current_user_can(TMR_Panel_Shell::CAPABILITY)) {
            wp_die(esc_html__('এই পেজ দেখার অনুমতি আপনার নেই।', 'tailor-manager'));
        }

        $search = isset($_GET['s']) ? sanitize_text_field(wp_unslash($_GET['s'])) : '';
        $paged  = isset($_GET['paged']) ? max(1, (int) $_GET['paged']) : 1;

        $query = self::build_query($search, $paged);

        $header_right = '<a href="#" class="tmr-btn-add" id="tmr-add-customer">' . esc_html__('+ কাস্টমার যোগ করুন', 'tailor-manager') . '</a>';

        // Its own row (below the title, not crammed into the header next to it —
        // same split the Orders panel already uses) rendered as $sticky_content so
        // it stays pinned above the list while a long result set scrolls under it.
        ob_start();
        ?>
        <div class="tmr-filters-bar">
            <form method="get" class="tmr-customers-search-form">
                <input type="hidden" name="page" value="tmr-customers" />
                <div class="tmr-filter-input-wrap">
                    <svg class="tmr-filter-input-icon" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                    <input type="text" name="s" class="tmr-filter-input" value="<?php echo esc_attr($search); ?>" placeholder="<?php esc_attr_e('নাম বা ফোন খুঁজুন…', 'tailor-manager'); ?>" />
                </div>
            </form>
        </div>
        <?php
        $filter_bar_html = ob_get_clean();

        TMR_Panel_Shell::header('customers', __('কাস্টমার', 'tailor-manager'), __('আপনার কাস্টমার তালিকা পরিচালনা করুন।', 'tailor-manager'), $header_right, true, $filter_bar_html);
        ?>

        <div id="tmr-customers-list-wrap">
            <?php self::render_table($query, $paged, $search); ?>
        </div>

        <div class="tmr-modal" id="tmr-customer-modal">
            <div class="tmr-modal-content">
                <div class="tmr-modal-head">
                    <h2 id="tmr-customer-modal-title"><?php esc_html_e('কাস্টমার যোগ করুন', 'tailor-manager'); ?></h2>
                    <button type="button" class="tmr-modal-close">&times;</button>
                </div>
                <form id="tmr-customer-form">
                    <input type="hidden" name="customer_id" value="0" />
                    <div class="tmr-modal-body">
                        <div class="tmr-form-row">
                            <label class="tmr-form-label" for="tmr-cust-name"><?php esc_html_e('নাম', 'tailor-manager'); ?> *</label>
                            <input type="text" name="name" id="tmr-cust-name" required />
                        </div>
                        <div class="tmr-form-row">
                            <label class="tmr-form-label" for="tmr-cust-phone"><?php esc_html_e('ফোন', 'tailor-manager'); ?> *</label>
                            <input type="text" name="phone" id="tmr-cust-phone" required />
                        </div>
                        <div class="tmr-form-row">
                            <label class="tmr-form-label" for="tmr-cust-address"><?php esc_html_e('ঠিকানা', 'tailor-manager'); ?></label>
                            <textarea name="address" id="tmr-cust-address" rows="2"></textarea>
                        </div>
                        <div class="tmr-form-row">
                            <label class="tmr-form-label"><?php esc_html_e('স্ট্যাটাস', 'tailor-manager'); ?></label>
                            <label class="tmr-toggle">
                                <input type="checkbox" name="status" value="publish" id="tmr-cust-status" class="tmr-status-toggle" checked />
                                <span class="tmr-toggle-slider"></span>
                                <span class="tmr-form-label tmr-status-toggle-label" style="margin:0;"><?php esc_html_e('সক্রিয়', 'tailor-manager'); ?></span>
                            </label>
                        </div>
                    </div>
                    <div class="tmr-modal-foot">
                        <button type="submit" class="tmr-btn-add"><?php esc_html_e('কাস্টমার সেভ করুন', 'tailor-manager'); ?></button>
                    </div>
                </form>
            </div>
        </div>

        <script>
        jQuery(function ($) {
            var $modal = $('#tmr-customer-modal');
            var $form = $('#tmr-customer-form');

            // Debounced live search — mirrors the Orders panel's own pattern
            // (TMR_Orders_Panel's .tmr-orders-search-form): re-fetches and swaps
            // only #tmr-customers-list-wrap, so 2000+ customers means a fast
            // AJAX re-render per pause in typing instead of a full page reload
            // per keystroke (or needing Enter, the old plain-GET form's only way
            // to search at all).
            var customersSearchTimer = null;
            $(document).on('input', '.tmr-customers-search-form input[name="s"]', function () {
                var search = $(this).val();

                clearTimeout(customersSearchTimer);
                customersSearchTimer = setTimeout(function () {
                    TMRPanel.call('tmr_search_customers_list', { s: search, paged: 1 }, function (data) {
                        $('#tmr-customers-list-wrap').html(data.html);

                        var url = new URL(window.location.href);
                        if (search) {
                            url.searchParams.set('s', search);
                        } else {
                            url.searchParams.delete('s');
                        }
                        url.searchParams.set('paged', '1');
                        window.history.replaceState(null, '', url.toString());
                    });
                }, 350);
            });

            $('#tmr-add-customer').on('click', function (e) {
                e.preventDefault();
                $form[0].reset();
                $form.find('[name="customer_id"]').val(0);
                TMRPanel.syncStatusToggle($form.find('[name="status"]'));
                $('#tmr-customer-modal-title').text('<?php echo esc_js(__('কাস্টমার যোগ করুন', 'tailor-manager')); ?>');
                TMRPanel.openModal($modal);
            });

            // Delegated (not directly bound) — the table these targets live in
            // gets replaced wholesale on every search/pagination fetch, so a
            // direct .on('click', ...) bound once at page load would silently
            // stop matching anything after the first re-render.
            $(document).on('click', '.tmr-edit-customer', function () {
                var id = $(this).data('id');
                TMRPanel.call('tmr_get_customer', { id: id }, function (data) {
                    $form.find('[name="customer_id"]').val(data.id);
                    $form.find('[name="name"]').val(data.name);
                    $form.find('[name="phone"]').val(data.phone);
                    $form.find('[name="address"]').val(data.address);
                    $form.find('[name="status"]').prop('checked', data.status === 'publish');
                    TMRPanel.syncStatusToggle($form.find('[name="status"]'));
                    $('#tmr-customer-modal-title').text('<?php echo esc_js(__('কাস্টমার এডিট করুন', 'tailor-manager')); ?>');
                    TMRPanel.openModal($modal);
                });
            });

            $(document).on('click', '.tmr-delete-customer', function () {
                if (!TMRPanel.confirmDelete('<?php echo esc_js(__('এই কাস্টমারকে ডিলিট করবেন?', 'tailor-manager')); ?>')) {
                    return;
                }
                var id = $(this).data('id');
                TMRPanel.call('tmr_delete_customer', { id: id }, function () {
                    window.location.reload();
                });
            });

            $form.on('submit', function (e) {
                e.preventDefault();
                TMRPanel.call('tmr_save_customer', $form.serialize(), function () {
                    window.location.reload();
                });
            });
        });
        </script>
        <?php
        TMR_Panel_Shell::footer();
    }

    private static function render_table($query, $paged, $search)
    {
        ?>
        <div class="tmr-card">
            <table class="tmr-table">
                <thead>
                    <tr>
                        <th><?php esc_html_e('নাম', 'tailor-manager'); ?></th>
                        <th><?php esc_html_e('ফোন', 'tailor-manager'); ?></th>
                        <th><?php esc_html_e('ঠিকানা', 'tailor-manager'); ?></th>
                        <th><?php esc_html_e('নিবন্ধনের তারিখ', 'tailor-manager'); ?></th>
                        <th><?php esc_html_e('স্ট্যাটাস', 'tailor-manager'); ?></th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$query->have_posts()) : ?>
                        <tr><td colspan="6" class="tmr-empty"><?php esc_html_e('কোনো কাস্টমার পাওয়া যায়নি।', 'tailor-manager'); ?></td></tr>
                    <?php else : ?>
                        <?php foreach ($query->posts as $customer) : ?>
                            <tr>
                                <td><?php echo esc_html(get_the_title($customer)); ?></td>
                                <td><?php echo esc_html(TMR_Customer_Post_Type::get_phone($customer->ID)); ?></td>
                                <td><?php echo esc_html(TMR_Customer_Post_Type::get_address($customer->ID)); ?></td>
                                <td><?php echo esc_html(get_the_date('Y-m-d', $customer)); ?></td>
                                <td>
                                    <?php if ('publish' === $customer->post_status) : ?>
                                        <span class="tmr-badge tmr-badge-green"><?php esc_html_e('সক্রিয়', 'tailor-manager'); ?></span>
                                    <?php else : ?>
                                        <span class="tmr-badge tmr-badge-gray"><?php esc_html_e('নিষ্ক্রিয়', 'tailor-manager'); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="tmr-actions">
                                        <span class="tmr-action-btn tmr-edit-customer" data-id="<?php echo esc_attr($customer->ID); ?>" title="<?php esc_attr_e('এডিট', 'tailor-manager'); ?>"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg></span>
                                        <span class="tmr-action-btn tmr-action-btn-red tmr-delete-customer" data-id="<?php echo esc_attr($customer->ID); ?>" title="<?php esc_attr_e('ডিলিট', 'tailor-manager'); ?>"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg></span>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php self::render_pagination($query->max_num_pages, $paged, array('page' => 'tmr-customers', 's' => $search)); ?>
        <?php
    }

    public function ajax_search()
    {
        check_ajax_referer('tmr_panel_nonce', 'nonce');
        if (!current_user_can(TMR_Panel_Shell::CAPABILITY)) {
            wp_send_json_error(array('message' => __('অনুমতি নেই।', 'tailor-manager')));
        }

        $search = isset($_POST['s']) ? sanitize_text_field(wp_unslash($_POST['s'])) : '';
        $paged  = isset($_POST['paged']) ? max(1, (int) $_POST['paged']) : 1;

        $query = self::build_query($search, $paged);

        ob_start();
        self::render_table($query, $paged, $search);
        $html = ob_get_clean();

        wp_send_json_success(array('html' => $html));
    }

    /**
     * $base_args, when given, builds each page link against admin_url('admin.php')
     * with those args explicitly merged in, instead of the default of merging
     * 'paged' onto the current request's own URL. Callers that can also render
     * this same fragment from an AJAX handler (e.g. TMR_Orders_Panel::ajax_search())
     * must pass $base_args — inside admin-ajax.php, "the current request's URL" is
     * admin-ajax.php itself, which would silently produce broken pagination links.
     *
     * Windowed with ellipsis (first/last page + current ± 2) instead of one link
     * per page — a straight 1..N loop rendered 100+ buttons in a single row for a
     * 2000+-row list (2166 imported customers = 109 pages at 20/page), which just
     * overflowed the card sideways. First/prev/next/last jump links plus a
     * type-a-page-number box (past 10 pages, where scanning for one by eye stops
     * being realistic) cover getting anywhere in a long list without that.
     */
    public static function render_pagination($max_pages, $current, $base_args = null)
    {
        if ($max_pages <= 1) {
            return;
        }

        $build_url = function ($page) use ($base_args) {
            return null === $base_args
                ? esc_url(add_query_arg(array('paged' => $page)))
                : esc_url(add_query_arg(array_merge($base_args, array('paged' => $page)), admin_url('admin.php')));
        };

        $window = 2;
        $pages = array();
        for ($i = 1; $i <= $max_pages; $i++) {
            if (1 === $i || $max_pages === $i || ($i >= $current - $window && $i <= $current + $window)) {
                $pages[] = $i;
            }
        }

        echo '<div class="tmr-pagination">';

        if ($current > 1) {
            echo '<a href="' . $build_url(1) . '" class="tmr-page-btn tmr-page-edge" title="' . esc_attr__('প্রথম পেজ', 'tailor-manager') . '">&laquo;</a>';
            echo '<a href="' . $build_url($current - 1) . '" class="tmr-page-btn tmr-page-edge" title="' . esc_attr__('আগের পেজ', 'tailor-manager') . '">&lsaquo;</a>';
        }

        $prev_shown = 0;
        foreach ($pages as $i) {
            if ($prev_shown && $i - $prev_shown > 1) {
                echo '<span class="tmr-page-ellipsis">&hellip;</span>';
            }
            $class = 'tmr-page-btn' . ($i === $current ? ' is-active' : '');
            echo '<a href="' . $build_url($i) . '" class="' . esc_attr($class) . '">' . esc_html($i) . '</a>';
            $prev_shown = $i;
        }

        if ($current < $max_pages) {
            echo '<a href="' . $build_url($current + 1) . '" class="tmr-page-btn tmr-page-edge" title="' . esc_attr__('পরের পেজ', 'tailor-manager') . '">&rsaquo;</a>';
            echo '<a href="' . $build_url($max_pages) . '" class="tmr-page-btn tmr-page-edge" title="' . esc_attr__('শেষ পেজ', 'tailor-manager') . '">&raquo;</a>';
        }

        if ($max_pages > 10) {
            echo '<span class="tmr-page-jump" data-max="' . esc_attr($max_pages) . '" data-base="' . esc_attr(wp_json_encode($base_args ? $base_args : array())) . '">'
                . '<input type="number" min="1" max="' . esc_attr($max_pages) . '" placeholder="' . esc_attr__('পেজ নং', 'tailor-manager') . '" class="tmr-page-jump-input" />'
                . '<button type="button" class="tmr-page-jump-btn">' . esc_html__('যান', 'tailor-manager') . '</button>'
                . '</span>';
        }

        echo '</div>';
    }

    public function ajax_get()
    {
        check_ajax_referer('tmr_panel_nonce', 'nonce');
        if (!current_user_can(TMR_Panel_Shell::CAPABILITY)) {
            wp_send_json_error(array('message' => __('অনুমতি নেই।', 'tailor-manager')));
        }

        $id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
        $post = get_post($id);

        if (!$post || self::POST_TYPE !== $post->post_type) {
            wp_send_json_error(array('message' => __('কাস্টমার পাওয়া যায়নি।', 'tailor-manager')));
        }

        wp_send_json_success(array(
            'id'      => $post->ID,
            'name'    => $post->post_title,
            'phone'   => TMR_Customer_Post_Type::get_phone($post->ID),
            'address' => TMR_Customer_Post_Type::get_address($post->ID),
            'status'  => $post->post_status,
        ));
    }

    public function ajax_save()
    {
        check_ajax_referer('tmr_panel_nonce', 'nonce');
        if (!current_user_can(TMR_Panel_Shell::CAPABILITY)) {
            wp_send_json_error(array('message' => __('অনুমতি নেই।', 'tailor-manager')));
        }

        $id      = isset($_POST['customer_id']) ? (int) $_POST['customer_id'] : 0;
        $name    = isset($_POST['name']) ? sanitize_text_field(wp_unslash($_POST['name'])) : '';
        $phone   = isset($_POST['phone']) ? sanitize_text_field(wp_unslash($_POST['phone'])) : '';
        $address = isset($_POST['address']) ? sanitize_textarea_field(wp_unslash($_POST['address'])) : '';
        // Status is now a checkbox (toggle), not a <select> — an unchecked checkbox is
        // simply omitted from serialize()'d form data entirely, so its presence/absence
        // (not its value) is what "active" vs "inactive" hinges on.
        $status  = !empty($_POST['status']) ? 'publish' : 'draft';

        if ('' === $name || '' === $phone) {
            wp_send_json_error(array('message' => __('নাম ও ফোন নম্বর আবশ্যক।', 'tailor-manager')));
        }

        $post_data = array(
            'post_type'   => self::POST_TYPE,
            'post_title'  => $name,
            'post_status' => $status,
        );

        if ($id > 0) {
            $post_data['ID'] = $id;
            $result = wp_update_post($post_data, true);
        } else {
            $result = wp_insert_post($post_data, true);
        }

        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }

        update_post_meta($result, '_tmr_phone', $phone);
        update_post_meta($result, '_tmr_address', $address);

        wp_send_json_success(array('id' => $result));
    }

    public function ajax_delete()
    {
        check_ajax_referer('tmr_panel_nonce', 'nonce');
        if (!current_user_can(TMR_Panel_Shell::CAPABILITY)) {
            wp_send_json_error(array('message' => __('অনুমতি নেই।', 'tailor-manager')));
        }

        $id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
        $post = get_post($id);

        if (!$post || self::POST_TYPE !== $post->post_type) {
            wp_send_json_error(array('message' => __('কাস্টমার পাওয়া যায়নি।', 'tailor-manager')));
        }

        wp_trash_post($id);
        wp_send_json_success();
    }
}
