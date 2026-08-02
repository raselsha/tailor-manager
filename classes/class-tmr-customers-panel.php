<?php
defined('ABSPATH') || exit;

/**
 * Customers screen: server-rendered list (search via GET, so it's a plain reloadable page)
 * + an AJAX-submitted modal for add/edit, + AJAX delete. Matches CLAUDE.md's "admin-ajax.php
 * for all AJAX" rule while keeping search/pagination as simple, robust GET requests.
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
    }

    public static function render()
    {
        if (!current_user_can(TMR_Panel_Shell::CAPABILITY)) {
            wp_die(esc_html__('এই পেজ দেখার অনুমতি আপনার নেই।', 'tailor-manager'));
        }

        $search = isset($_GET['s']) ? sanitize_text_field(wp_unslash($_GET['s'])) : '';
        $paged  = isset($_GET['paged']) ? max(1, (int) $_GET['paged']) : 1;

        $query = new WP_Query(array(
            'post_type'      => self::POST_TYPE,
            'post_status'    => array('publish', 'draft'),
            's'              => $search,
            'posts_per_page' => self::PER_PAGE,
            'paged'          => $paged,
            'orderby'        => 'title',
            'order'          => 'ASC',
        ));

        $header_right = '<form method="get" style="display:flex;gap:10px;">'
            . '<input type="hidden" name="page" value="tmr-customers" />'
            . '<div class="tmr-filter-input-wrap" style="flex:0 0 260px;">'
            . '<svg class="tmr-filter-input-icon" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>'
            . '<input type="text" name="s" class="tmr-filter-input" value="' . esc_attr($search) . '" placeholder="' . esc_attr__('নাম বা ফোন খুঁজুন…', 'tailor-manager') . '" />'
            . '</div>'
            . '</form>'
            . '<a href="#" class="tmr-btn-add" id="tmr-add-customer">' . esc_html__('+ কাস্টমার যোগ করুন', 'tailor-manager') . '</a>';

        TMR_Panel_Shell::header('customers', __('কাস্টমার', 'tailor-manager'), __('আপনার কাস্টমার তালিকা পরিচালনা করুন।', 'tailor-manager'), $header_right);
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

        <?php self::render_pagination($query->max_num_pages, $paged); ?>

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

            $('#tmr-add-customer').on('click', function (e) {
                e.preventDefault();
                $form[0].reset();
                $form.find('[name="customer_id"]').val(0);
                TMRPanel.syncStatusToggle($form.find('[name="status"]'));
                $('#tmr-customer-modal-title').text('<?php echo esc_js(__('কাস্টমার যোগ করুন', 'tailor-manager')); ?>');
                TMRPanel.openModal($modal);
            });

            $('.tmr-edit-customer').on('click', function () {
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

            $('.tmr-delete-customer').on('click', function () {
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
     * $base_args, when given, builds each page link against admin_url('admin.php')
     * with those args explicitly merged in, instead of the default of merging
     * 'paged' onto the current request's own URL. Callers that can also render
     * this same fragment from an AJAX handler (e.g. TMR_Orders_Panel::ajax_search())
     * must pass $base_args — inside admin-ajax.php, "the current request's URL" is
     * admin-ajax.php itself, which would silently produce broken pagination links.
     */
    public static function render_pagination($max_pages, $current, $base_args = null)
    {
        if ($max_pages <= 1) {
            return;
        }
        echo '<div class="tmr-pagination">';
        for ($i = 1; $i <= $max_pages; $i++) {
            $url = null === $base_args
                ? esc_url(add_query_arg(array('paged' => $i)))
                : esc_url(add_query_arg(array_merge($base_args, array('paged' => $i)), admin_url('admin.php')));
            $class = 'tmr-page-btn' . ($i === $current ? ' is-active' : '');
            echo '<a href="' . $url . '" class="' . esc_attr($class) . '">' . esc_html($i) . '</a>';
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
