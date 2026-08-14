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
    const PER_PAGE_OPTIONS = array(10, 20, 50, 100, 200);
    const PER_PAGE_DEFAULT = 20;
    const PER_PAGE_META_KEY = 'tmr_customers_per_page';

    public function __construct()
    {
        add_action('wp_ajax_tmr_save_customer', array($this, 'ajax_save'));
        add_action('wp_ajax_tmr_delete_customer', array($this, 'ajax_delete'));
        add_action('wp_ajax_tmr_get_customer', array($this, 'ajax_get'));
        add_action('wp_ajax_tmr_search_customers_list', array($this, 'ajax_search'));
    }

    /**
     * $requested (from $_GET/$_POST['per_page'], read by the caller — render()
     * and ajax_search() are the only places touching superglobals, same as
     * $search/$paged already do) wins and is remembered on the user's account
     * when it's one of the allowed options; otherwise falls back to whatever
     * was last remembered, then the hard default. Stored in user meta (not a
     * cookie/localStorage) so the choice follows the user across devices, same
     * as WP core's own per-screen "items per page" screen option.
     */
    private static function resolve_per_page($requested)
    {
        if ($requested && in_array($requested, self::PER_PAGE_OPTIONS, true)) {
            update_user_meta(get_current_user_id(), self::PER_PAGE_META_KEY, $requested);
            return $requested;
        }

        $saved = (int) get_user_meta(get_current_user_id(), self::PER_PAGE_META_KEY, true);
        return in_array($saved, self::PER_PAGE_OPTIONS, true) ? $saved : self::PER_PAGE_DEFAULT;
    }

    /**
     * Deliberately NOT WP_Query's own 's' param — that only ever matches
     * post_title/excerpt/content, so a customer looked up by their PHONE
     * NUMBER (a postmeta field, "নাম বা ফোন খুঁজুন" promises both) silently
     * matched nothing at all, even though the placeholder says otherwise.
     * posts_join/posts_where below build "title LIKE X OR phone LIKE X" as
     * one properly self-contained, AND'd-onto-the-rest clause instead —
     * scoped to only this query via the tmr_customer_search_term query var,
     * so it can never leak into some other WP_Query running the same request.
     */
    private static function build_query($search, $paged, $per_page)
    {
        $args = array(
            'post_type'      => self::POST_TYPE,
            'post_status'    => array('publish', 'draft'),
            'posts_per_page' => $per_page,
            'paged'          => $paged,
            'orderby'        => 'title',
            'order'          => 'ASC',
        );

        if ('' === $search) {
            return new WP_Query($args);
        }

        $args['tmr_customer_search_term'] = $search;

        add_filter('posts_join', array(__CLASS__, 'filter_search_join'), 10, 2);
        add_filter('posts_where', array(__CLASS__, 'filter_search_where'), 10, 2);
        add_filter('posts_distinct', array(__CLASS__, 'filter_search_distinct'), 10, 2);

        $query = new WP_Query($args);

        remove_filter('posts_join', array(__CLASS__, 'filter_search_join'), 10);
        remove_filter('posts_where', array(__CLASS__, 'filter_search_where'), 10);
        remove_filter('posts_distinct', array(__CLASS__, 'filter_search_distinct'), 10);

        return $query;
    }

    public static function filter_search_join($join, $query)
    {
        if (!$query->get('tmr_customer_search_term')) {
            return $join;
        }
        global $wpdb;
        $join .= " LEFT JOIN {$wpdb->postmeta} AS tmr_search_phone ON ({$wpdb->posts}.ID = tmr_search_phone.post_id AND tmr_search_phone.meta_key = '_tmr_phone')";
        return $join;
    }

    public static function filter_search_where($where, $query)
    {
        $term = $query->get('tmr_customer_search_term');
        if (!$term) {
            return $where;
        }
        global $wpdb;
        $like = '%' . $wpdb->esc_like($term) . '%';
        $where .= $wpdb->prepare(
            " AND ({$wpdb->posts}.post_title LIKE %s OR tmr_search_phone.meta_value LIKE %s)",
            $like,
            $like
        );
        return $where;
    }

    public static function filter_search_distinct($distinct, $query)
    {
        return $query->get('tmr_customer_search_term') ? 'DISTINCT' : $distinct;
    }

    private static function format_count($total)
    {
        /* translators: %d: total matching customer count */
        return sprintf(__('মোট %d জন কাস্টমার পাওয়া গেছে', 'tailor-manager'), $total);
    }

    public static function render()
    {
        if (!current_user_can(TMR_Panel_Shell::CAPABILITY)) {
            wp_die(esc_html__('এই পেজ দেখার অনুমতি আপনার নেই।', 'tailor-manager'));
        }

        $id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
        if ('view' === (isset($_GET['action']) ? sanitize_key($_GET['action']) : '') && $id) {
            self::render_view($id);
            return;
        }

        $search   = isset($_GET['s']) ? sanitize_text_field(wp_unslash($_GET['s'])) : '';
        $paged    = isset($_GET['paged']) ? max(1, (int) $_GET['paged']) : 1;
        $per_page = self::resolve_per_page(isset($_GET['per_page']) ? (int) $_GET['per_page'] : 0);

        $query = self::build_query($search, $paged, $per_page);

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
            <div class="tmr-per-page-wrap">
                <label for="tmr-customers-per-page" class="tmr-per-page-label"><?php esc_html_e('প্রতি পেজে', 'tailor-manager'); ?></label>
                <select id="tmr-customers-per-page" class="tmr-per-page-select">
                    <?php foreach (self::PER_PAGE_OPTIONS as $option) : ?>
                        <option value="<?php echo esc_attr($option); ?>" <?php selected($per_page, $option); ?>><?php echo esc_html($option); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <span class="tmr-filter-count" id="tmr-customers-count"><?php echo esc_html(self::format_count($query->found_posts)); ?></span>
        </div>
        <?php
        $filter_bar_html = ob_get_clean();

        TMR_Panel_Shell::header('customers', __('কাস্টমার', 'tailor-manager'), __('আপনার কাস্টমার তালিকা পরিচালনা করুন।', 'tailor-manager'), $header_right, true, $filter_bar_html);
        ?>

        <div id="tmr-customers-list-wrap">
            <?php self::render_table($query, $paged, $search, $per_page); ?>
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

            // Shared by both the debounced search input and the per-page select
            // below — re-fetches and swaps only #tmr-customers-list-wrap (+ the
            // count badge), so 2000+ customers means a fast AJAX re-render
            // instead of a full page reload per keystroke/change.
            function fetchCustomersList(paged) {
                var search = $('.tmr-customers-search-form input[name="s"]').val();
                var perPage = $('#tmr-customers-per-page').val();

                TMRPanel.call('tmr_search_customers_list', { s: search, paged: paged, per_page: perPage }, function (data) {
                    $('#tmr-customers-list-wrap').html(data.html);
                    $('#tmr-customers-count').text(data.count);

                    var url = new URL(window.location.href);
                    if (search) {
                        url.searchParams.set('s', search);
                    } else {
                        url.searchParams.delete('s');
                    }
                    url.searchParams.set('paged', paged);
                    url.searchParams.set('per_page', perPage);
                    window.history.replaceState(null, '', url.toString());
                });
            }

            // Debounced live search — mirrors the Orders panel's own pattern
            // (TMR_Orders_Panel's .tmr-orders-search-form).
            var customersSearchTimer = null;
            $(document).on('input', '.tmr-customers-search-form input[name="s"]', function () {
                clearTimeout(customersSearchTimer);
                customersSearchTimer = setTimeout(function () {
                    fetchCustomersList(1);
                }, 350);
            });

            // Per-page change is a deliberate discrete action (not a keystroke
            // stream), so it fetches immediately — no debounce needed.
            $(document).on('change', '#tmr-customers-per-page', function () {
                fetchCustomersList(1);
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

    /**
     * Read-only profile page (own admin.php?page=tmr-customers&action=view&id=
     * URL, same dispatch pattern as TMR_Orders_Panel's own view screen) — info
     * card plus this customer's order history, queried by the _tmr_customer_id
     * postmeta every order already carries. Each row links out to that order's
     * own existing view screen rather than duplicating its detail rendering here.
     */
    private static function render_view($customer_id)
    {
        $customer = get_post($customer_id);
        if (!$customer || self::POST_TYPE !== $customer->post_type) {
            wp_die(esc_html__('কাস্টমার পাওয়া যায়নি।', 'tailor-manager'));
        }

        $header_right = '<a class="tmr-btn-outline" href="' . esc_url(admin_url('admin.php?page=tmr-customers')) . '">' . esc_html__('তালিকায় ফিরুন', 'tailor-manager') . '</a>';

        TMR_Panel_Shell::header('customers', get_the_title($customer), '', $header_right, true);
        self::render_view_content($customer_id);
        TMR_Panel_Shell::footer();
    }

    private static function render_view_content($customer_id)
    {
        $customer = get_post($customer_id);
        $phone    = TMR_Customer_Post_Type::get_phone($customer_id);
        $address  = TMR_Customer_Post_Type::get_address($customer_id);
        ?>
        <div class="tmr-card-plain tmr-highlight-card">
            <div class="tmr-step-header tmr-highlight-header">
                <h3><?php esc_html_e('কাস্টমার তথ্য', 'tailor-manager'); ?></h3>
                <?php if ('publish' === $customer->post_status) : ?>
                    <span class="tmr-badge tmr-badge-green"><?php esc_html_e('সক্রিয়', 'tailor-manager'); ?></span>
                <?php else : ?>
                    <span class="tmr-badge tmr-badge-gray"><?php esc_html_e('নিষ্ক্রিয়', 'tailor-manager'); ?></span>
                <?php endif; ?>
            </div>
            <div class="tmr-vp-info-row">
                <div class="tmr-vp-info-item">
                    <span class="tmr-vp-info-label"><?php esc_html_e('নাম', 'tailor-manager'); ?></span>
                    <strong><?php echo esc_html(get_the_title($customer)); ?></strong>
                </div>
                <div class="tmr-vp-info-item">
                    <span class="tmr-vp-info-label"><?php esc_html_e('ফোন', 'tailor-manager'); ?></span>
                    <strong><?php echo esc_html($phone); ?></strong>
                </div>
                <div class="tmr-vp-info-item">
                    <span class="tmr-vp-info-label"><?php esc_html_e('নিবন্ধনের তারিখ', 'tailor-manager'); ?></span>
                    <strong><?php echo esc_html(TMR_Orders_Panel::format_date_bn(get_the_date('Y-m-d', $customer))); ?></strong>
                </div>
                <?php if ($address) : ?>
                <div class="tmr-vp-info-item">
                    <span class="tmr-vp-info-label"><?php esc_html_e('ঠিকানা', 'tailor-manager'); ?></span>
                    <strong><?php echo esc_html($address); ?></strong>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <?php
        $orders = new WP_Query(array(
            'post_type'      => TMR_Order_Post_Type::POST_TYPE,
            'post_status'    => 'any',
            'posts_per_page' => 100,
            'orderby'        => 'ID',
            'order'          => 'DESC',
            'meta_query'     => array(array('key' => '_tmr_customer_id', 'value' => $customer_id)),
        ));
        ?>

        <div class="tmr-vp-section-heading-row">
            <h3 class="tmr-order-section-heading"><?php esc_html_e('অর্ডার হিস্টরি', 'tailor-manager'); ?></h3>
            <span class="tmr-vp-section-count"><?php echo esc_html($orders->found_posts); ?> <?php esc_html_e('টি অর্ডার', 'tailor-manager'); ?></span>
        </div>

        <div class="tmr-card">
            <table class="tmr-table tmr-customers-table">
                <thead>
                    <tr>
                        <th><?php esc_html_e('অর্ডার আইডি', 'tailor-manager'); ?></th>
                        <th><?php esc_html_e('অর্ডারের তারিখ', 'tailor-manager'); ?></th>
                        <th><?php esc_html_e('ডেলিভারি তারিখ', 'tailor-manager'); ?></th>
                        <th><?php esc_html_e('স্ট্যাটাস', 'tailor-manager'); ?></th>
                        <th><?php esc_html_e('মোট', 'tailor-manager'); ?></th>
                        <th><?php esc_html_e('অগ্রিম', 'tailor-manager'); ?></th>
                        <th><?php esc_html_e('বাকি', 'tailor-manager'); ?></th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$orders->have_posts()) : ?>
                        <tr><td colspan="8" class="tmr-empty"><?php esc_html_e('এই কাস্টমারের কোনো অর্ডার নেই।', 'tailor-manager'); ?></td></tr>
                    <?php else : ?>
                        <?php foreach ($orders->posts as $order) :
                            $status_key = TMR_Order_Post_Type::status_label($order->ID);
                        ?>
                            <tr class="tmr-order-acc-trigger" data-id="<?php echo esc_attr($order->ID); ?>">
                                <td>#<?php echo esc_html(TMR_Orders_Panel::get_order_number($order->ID)); ?></td>
                                <td><?php echo esc_html(TMR_Orders_Panel::format_date_bn(get_post_meta($order->ID, '_tmr_order_date', true))); ?></td>
                                <td><?php echo esc_html(TMR_Orders_Panel::format_date_bn(get_post_meta($order->ID, '_tmr_delivery_date', true))); ?></td>
                                <td><span class="tmr-badge tmr-badge-<?php echo esc_attr($status_key); ?>"><?php echo esc_html(ucfirst($status_key)); ?></span></td>
                                <td><?php echo esc_html(TMR_Panel_Shell::format_money(get_post_meta($order->ID, '_tmr_total', true))); ?></td>
                                <td><span class="tmr-badge tmr-badge-green"><?php echo esc_html(TMR_Panel_Shell::format_money(get_post_meta($order->ID, '_tmr_advance', true))); ?></span></td>
                                <td><span class="tmr-badge tmr-badge-red"><?php echo esc_html(TMR_Panel_Shell::format_money(get_post_meta($order->ID, '_tmr_due', true))); ?></span></td>
                                <td>
                                    <?php
                                    // Expands into the same order-summary content the Orders list's
                                    // own eye-icon shows (via tmr_get_order_summary, the same AJAX
                                    // endpoint) — right here as an accordion row, no page/modal jump.
                                    ?>
                                    <button type="button" class="tmr-icon-btn tmr-order-acc-btn" title="<?php esc_attr_e('দেখুন', 'tailor-manager'); ?>">
                                        <svg class="tmr-order-acc-chevron" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 9l6 6 6-6"></path></svg>
                                    </button>
                                </td>
                            </tr>
                            <tr class="tmr-order-acc-row" id="tmr-order-acc-row-<?php echo esc_attr($order->ID); ?>" style="display:none;">
                                <td colspan="8">
                                    <div class="tmr-order-acc-body"></div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php self::render_order_accordion_script(); ?>
        <?php
    }

    /**
     * Turns tmr_get_order_summary's JSON (the same data the Orders list's
     * viewOrderSummary()/showOrderConfirmation() modal renders) into inline
     * accordion HTML — a dedicated renderer rather than reusing that modal's JS
     * as-is, since that function is wired to a single fixed set of #tmr-conf-*
     * ids for one modal instance at a time, not to N independent rows on a page.
     * Same CSS classes throughout, so the result looks identical to the modal.
     */
    private static function render_order_accordion_script()
    {
        ?>
        <script>
        jQuery(function ($) {
            var tmrAccMeasureIcon = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="9"></circle></svg>';
            var tmrAccDesignIcon = '<svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20.38 3.46 16 2a4 4 0 0 1-8 0L3.62 3.46a2 2 0 0 0-1.34 2.23l.58 3.47a1 1 0 0 0 .99.84H6v10c0 .55.45 1 1 1h10c.55 0 1-.45 1-1V10h2.15a1 1 0 0 0 .99-.84l.58-3.47a2 2 0 0 0-1.34-2.23z"></path></svg>';

            // Just the urgent flag — status itself is already visible as a badge on
            // the default row this accordion expands from, so it isn't repeated here.
            function tmrAccUrgentBadgeHtml(urgent) {
                return urgent ? '<span class="tmr-badge tmr-badge-red"><?php echo esc_js(__('জরুরি', 'tailor-manager')); ?></span>' : '';
            }

            function tmrAccBuildItemsHtml(items) {
                var html = '';
                (items || []).forEach(function (item) {
                    var dresses = item.dresses || [];
                    if (!dresses.length && !(item.measurements || []).length) { return; }
                    var heading = item.category;
                    if (dresses.length) {
                        heading += ' — ' + dresses.map(function (d) { return d.name; }).join(', ');
                    }
                    var totalQty = dresses.reduce(function (sum, d) { return sum + (parseInt(d.quantity, 10) || 0); }, 0);

                    html += '<div class="tmr-confirmation-summary-card tmr-conf-item">';
                    html += '<div class="tmr-confirmation-summary-header tmr-confirmation-item-header tmr-conf-item-collapse-header">';
                    html += '<div class="tmr-confirmation-item-header-left"><div class="tmr-confirmation-summary-customer"><strong>' + $('<div>').text(heading).html() + '</strong></div>';
                    if (totalQty > 0) {
                        html += '<span class="tmr-confirmation-summary-order-id tmr-conf-item-badge">×' + totalQty + '</span>';
                    }
                    html += '</div>';
                    html += '<div class="tmr-confirmation-item-header-center">';
                    if (item.cutter) {
                        html += '<span class="tmr-vp-staff-inline"><?php echo esc_js(__('কাটিং মাস্টার', 'tailor-manager')); ?>: <strong>' + $('<div>').text(item.cutter).html() + '</strong></span>';
                    }
                    if (item.tailor) {
                        html += '<span class="tmr-vp-staff-inline"><?php echo esc_js(__('সোয়িং অপারেটর', 'tailor-manager')); ?>: <strong>' + $('<div>').text(item.tailor).html() + '</strong></span>';
                    }
                    html += '</div><div class="tmr-confirmation-item-header-right"><svg class="tmr-conf-item-chevron is-open" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 9l6 6 6-6"></path></svg></div>';
                    html += '</div>';

                    html += '<div class="tmr-conf-item-body">';
                    if ((item.measurements || []).length) {
                        html += '<div class="tmr-vp-block-title"><?php echo esc_js(__('মাপের বিবরণ', 'tailor-manager')); ?></div><div class="tmr-vp-measure-grid">';
                        item.measurements.forEach(function (m) {
                            html += '<div class="tmr-vp-measure-card"><span class="tmr-vp-measure-icon">' + tmrAccMeasureIcon + '</span>'
                                + '<span class="tmr-vp-measure-label">' + $('<div>').text(m.label).html() + '</span>'
                                + '<strong class="tmr-vp-measure-value">' + $('<div>').text(m.value).html() + '</strong></div>';
                        });
                        html += '</div>';
                    }
                    if ((item.parts || []).length) {
                        html += '<div class="tmr-vp-block-title"><?php echo esc_js(__('ডিজাইন', 'tailor-manager')); ?></div><div class="tmr-vp-design-grid">';
                        item.parts.forEach(function (p) {
                            var iconInner = p.image_url ? '<img class="tmr-vp-design-photo" src="' + $('<div>').text(p.image_url).html() + '">' : tmrAccDesignIcon;
                            html += '<div class="tmr-vp-design-card"><div class="tmr-vp-design-icon-block"><span class="tmr-vp-design-icon-circle">' + iconInner + '</span>';
                            if (p.extra_value) {
                                html += '<span class="tmr-vp-design-qty">' + $('<div>').text(p.extra_value).html() + '</span>';
                            }
                            html += '</div><div class="tmr-vp-design-body"><span class="tmr-vp-design-label">' + $('<div>').text(p.name).html() + '</span>'
                                + '<span class="tmr-vp-design-text">' + $('<div>').text((p.designs || []).join(', ')).html() + '</span></div></div>';
                        });
                        html += '</div>';
                    }
                    html += '</div></div>';
                });
                return html;
            }

            var tmrAccEditIcon = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg>';
            var tmrAccPrintIcon = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 6 2 18 2 18 9"></polyline><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path><rect x="6" y="14" width="12" height="8"></rect></svg>';
            var tmrAccDeleteIcon = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>';

            function tmrAccRender($body, data) {
                var html = '';
                html += '<div class="tmr-confirmation-toolbar">';
                html += '<div class="tmr-confirmation-toolbar-status">' + tmrAccUrgentBadgeHtml(data.urgent) + '</div>';
                html += '<div class="tmr-confirmation-toolbar-actions">';
                html += '<a class="tmr-btn-outline tmr-btn-sm" href="' + data.edit_url + '">' + tmrAccEditIcon + ' <?php echo esc_js(__('এডিট', 'tailor-manager')); ?></a>';
                html += '<a class="tmr-btn-outline tmr-btn-sm" href="' + data.print_receipt_url + '" target="_blank">' + tmrAccPrintIcon + ' <?php echo esc_js(__('রিসিট', 'tailor-manager')); ?></a>';
                html += '<a class="tmr-btn-outline tmr-btn-sm" href="' + data.print_workslip_url + '" target="_blank">' + tmrAccPrintIcon + ' <?php echo esc_js(__('ওয়ার্ক', 'tailor-manager')); ?></a>';
                html += '<a class="tmr-btn-outline tmr-btn-sm" href="' + data.print_fullslip_url + '" target="_blank">' + tmrAccPrintIcon + ' <?php echo esc_js(__('ফুল', 'tailor-manager')); ?></a>';
                html += '<button type="button" class="tmr-btn-outline tmr-btn-outline-danger tmr-btn-sm tmr-order-acc-delete">' + tmrAccDeleteIcon + ' <?php echo esc_js(__('ডিলিট', 'tailor-manager')); ?></button>';
                html += '</div></div>';

                <?php
                // Order id, order/delivery date, status, total, advance and due are all
                // already visible as columns/badges on the row this accordion opens
                // from — nothing left here worth a summary card, so the accordion goes
                // straight to the dress/measurement items.
                ?>
                html += '<div class="tmr-confirmation-details">';
                html += '<div class="tmr-confirmation-items">' + tmrAccBuildItemsHtml(data.items) + '</div>';
                html += '</div>';

                $body.data('order-data', data).html(html);
            }

            $(document).on('click', '.tmr-order-acc-trigger', function () {
                var id = $(this).data('id');
                var $row = $('#tmr-order-acc-row-' + id);
                var $body = $row.find('.tmr-order-acc-body');
                var $chevron = $(this).find('.tmr-order-acc-chevron');
                var isOpen = $row.is(':visible');

                if (isOpen) {
                    $row.hide();
                    $chevron.removeClass('is-open');
                    return;
                }

                $row.show();
                $chevron.addClass('is-open');

                if ($body.data('order-data')) {
                    return;
                }

                $body.html('<div class="tmr-empty"><?php echo esc_js(__('লোড হচ্ছে…', 'tailor-manager')); ?></div>');
                TMRPanel.call('tmr_get_order_summary', { id: id }, function (data) {
                    tmrAccRender($body, data);
                });
            });

            $(document).on('click', '.tmr-order-acc-delete', function () {
                var $body = $(this).closest('.tmr-order-acc-body');
                var data = $body.data('order-data');
                if (!data || !TMRPanel.confirmDelete()) { return; }
                TMRPanel.call('tmr_delete_order', { id: data.id }, function () {
                    window.location.reload();
                });
            });

            // Same collapsible dress/measurement cards as the Orders list's own
            // confirmation modal — header click slides the body + flips the chevron.
            $(document).on('click', '.tmr-order-acc-body .tmr-conf-item-collapse-header', function () {
                $(this).next('.tmr-conf-item-body').slideToggle(150);
                $(this).find('.tmr-conf-item-chevron').toggleClass('is-open');
            });
        });
        </script>
        <?php
    }

    private static function render_table($query, $paged, $search, $per_page)
    {
        ?>
        <div class="tmr-card">
            <table class="tmr-table tmr-customers-table">
                <thead>
                    <tr>
                        <th><?php esc_html_e('নাম', 'tailor-manager'); ?></th>
                        <th><?php esc_html_e('ফোন', 'tailor-manager'); ?></th>
                        <th><?php esc_html_e('ঠিকানা', 'tailor-manager'); ?></th>
                        <th><?php esc_html_e('নিবন্ধনের তারিখ', 'tailor-manager'); ?></th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$query->have_posts()) : ?>
                        <tr><td colspan="5" class="tmr-empty"><?php esc_html_e('কোনো কাস্টমার পাওয়া যায়নি।', 'tailor-manager'); ?></td></tr>
                    <?php else : ?>
                        <?php foreach ($query->posts as $customer) : ?>
                            <?php $address = TMR_Customer_Post_Type::get_address($customer->ID); ?>
                            <tr>
                                <td class="tmr-customer-name-cell"><?php echo esc_html(get_the_title($customer)); ?></td>
                                <td><?php echo esc_html(TMR_Customer_Post_Type::get_phone($customer->ID)); ?></td>
                                <td class="tmr-customer-address-cell" title="<?php echo esc_attr($address); ?>"><?php echo esc_html($address); ?></td>
                                <td class="tmr-customer-date-cell"><?php echo esc_html(TMR_Orders_Panel::format_date_bn(get_the_date('Y-m-d', $customer))); ?></td>
                                <td>
                                    <div class="tmr-actions">
                                        <a class="tmr-action-btn" href="<?php echo esc_url(admin_url('admin.php?page=tmr-customers&action=view&id=' . $customer->ID)); ?>" title="<?php esc_attr_e('দেখুন', 'tailor-manager'); ?>"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg></a>
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

        <?php self::render_pagination($query->max_num_pages, $paged, array('page' => 'tmr-customers', 's' => $search, 'per_page' => $per_page)); ?>
        <?php
    }

    public function ajax_search()
    {
        check_ajax_referer('tmr_panel_nonce', 'nonce');
        if (!current_user_can(TMR_Panel_Shell::CAPABILITY)) {
            wp_send_json_error(array('message' => __('অনুমতি নেই।', 'tailor-manager')));
        }

        $search   = isset($_POST['s']) ? sanitize_text_field(wp_unslash($_POST['s'])) : '';
        $paged    = isset($_POST['paged']) ? max(1, (int) $_POST['paged']) : 1;
        $per_page = self::resolve_per_page(isset($_POST['per_page']) ? (int) $_POST['per_page'] : 0);

        $query = self::build_query($search, $paged, $per_page);

        ob_start();
        self::render_table($query, $paged, $search, $per_page);
        $html = ob_get_clean();

        wp_send_json_success(array('html' => $html, 'count' => self::format_count($query->found_posts)));
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
