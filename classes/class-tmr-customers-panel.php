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
            wp_die(esc_html__('You do not have permission to access this page.', 'tailor-manager'));
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

        TMR_Panel_Shell::header('customers', __('Customers', 'tailor-manager'));
        ?>
        <div class="tmr-toolbar">
            <form class="tmr-toolbar__search" method="get">
                <input type="hidden" name="page" value="tmr-customers" />
                <input type="text" name="s" value="<?php echo esc_attr($search); ?>" placeholder="<?php esc_attr_e('Search name or phone…', 'tailor-manager'); ?>" />
                <button type="submit" class="tmr-btn"><?php esc_html_e('Search', 'tailor-manager'); ?></button>
            </form>
            <button type="button" class="tmr-btn tmr-btn--primary" id="tmr-add-customer"><span class="dashicons dashicons-plus"></span> <?php esc_html_e('Add Customer', 'tailor-manager'); ?></button>
        </div>

        <table class="tmr-table">
            <thead>
                <tr>
                    <th><?php esc_html_e('Name', 'tailor-manager'); ?></th>
                    <th><?php esc_html_e('Phone', 'tailor-manager'); ?></th>
                    <th><?php esc_html_e('Address', 'tailor-manager'); ?></th>
                    <th><?php esc_html_e('Registered', 'tailor-manager'); ?></th>
                    <th><?php esc_html_e('Status', 'tailor-manager'); ?></th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$query->have_posts()) : ?>
                    <tr><td colspan="6" class="tmr-empty"><?php esc_html_e('No customers found.', 'tailor-manager'); ?></td></tr>
                <?php else : ?>
                    <?php foreach ($query->posts as $customer) : ?>
                        <tr>
                            <td><?php echo esc_html(get_the_title($customer)); ?></td>
                            <td><?php echo esc_html(TMR_Customer_Post_Type::get_phone($customer->ID)); ?></td>
                            <td><?php echo esc_html(TMR_Customer_Post_Type::get_address($customer->ID)); ?></td>
                            <td><?php echo esc_html(get_the_date('Y-m-d', $customer)); ?></td>
                            <td>
                                <?php if ('publish' === $customer->post_status) : ?>
                                    <span class="tmr-badge tmr-badge--delivered"><?php esc_html_e('Active', 'tailor-manager'); ?></span>
                                <?php else : ?>
                                    <span class="tmr-badge tmr-badge--cancelled"><?php esc_html_e('Inactive', 'tailor-manager'); ?></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <button type="button" class="tmr-btn tmr-btn--sm tmr-edit-customer" data-id="<?php echo esc_attr($customer->ID); ?>"><?php esc_html_e('Edit', 'tailor-manager'); ?></button>
                                <button type="button" class="tmr-btn tmr-btn--sm tmr-btn--danger tmr-delete-customer" data-id="<?php echo esc_attr($customer->ID); ?>"><?php esc_html_e('Delete', 'tailor-manager'); ?></button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>

        <?php self::render_pagination($query->max_num_pages, $paged); ?>

        <div class="tmr-modal-backdrop" id="tmr-customer-modal">
            <div class="tmr-modal">
                <div class="tmr-modal__title">
                    <h2 id="tmr-customer-modal-title"><?php esc_html_e('Add Customer', 'tailor-manager'); ?></h2>
                    <button type="button" class="tmr-modal__close">&times;</button>
                </div>
                <form id="tmr-customer-form">
                    <input type="hidden" name="customer_id" value="0" />
                    <div class="tmr-form-row">
                        <label><?php esc_html_e('Name', 'tailor-manager'); ?> *</label>
                        <input type="text" name="name" required />
                    </div>
                    <div class="tmr-form-row">
                        <label><?php esc_html_e('Phone', 'tailor-manager'); ?> *</label>
                        <input type="text" name="phone" required />
                    </div>
                    <div class="tmr-form-row">
                        <label><?php esc_html_e('Address', 'tailor-manager'); ?></label>
                        <textarea name="address" rows="2"></textarea>
                    </div>
                    <div class="tmr-form-row">
                        <label><?php esc_html_e('Status', 'tailor-manager'); ?></label>
                        <select name="status">
                            <option value="publish"><?php esc_html_e('Active', 'tailor-manager'); ?></option>
                            <option value="draft"><?php esc_html_e('Inactive', 'tailor-manager'); ?></option>
                        </select>
                    </div>
                    <button type="submit" class="tmr-btn tmr-btn--primary"><?php esc_html_e('Save', 'tailor-manager'); ?></button>
                </form>
            </div>
        </div>

        <script>
        jQuery(function ($) {
            var $modal = $('#tmr-customer-modal');
            var $form = $('#tmr-customer-form');

            $('#tmr-add-customer').on('click', function () {
                $form[0].reset();
                $form.find('[name="customer_id"]').val(0);
                $('#tmr-customer-modal-title').text('<?php echo esc_js(__('Add Customer', 'tailor-manager')); ?>');
                TMRPanel.openModal($modal);
            });

            $('.tmr-edit-customer').on('click', function () {
                var id = $(this).data('id');
                TMRPanel.call('tmr_get_customer', { id: id }, function (data) {
                    $form.find('[name="customer_id"]').val(data.id);
                    $form.find('[name="name"]').val(data.name);
                    $form.find('[name="phone"]').val(data.phone);
                    $form.find('[name="address"]').val(data.address);
                    $form.find('[name="status"]').val(data.status);
                    $('#tmr-customer-modal-title').text('<?php echo esc_js(__('Edit Customer', 'tailor-manager')); ?>');
                    TMRPanel.openModal($modal);
                });
            });

            $('.tmr-delete-customer').on('click', function () {
                if (!TMRPanel.confirmDelete('<?php echo esc_js(__('Delete this customer?', 'tailor-manager')); ?>')) {
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

    public static function render_pagination($max_pages, $current)
    {
        if ($max_pages <= 1) {
            return;
        }
        echo '<div class="tmr-pagination">';
        for ($i = 1; $i <= $max_pages; $i++) {
            $url = esc_url(add_query_arg(array('paged' => $i)));
            if ($i === $current) {
                echo '<span class="is-current">' . esc_html($i) . '</span>';
            } else {
                echo '<a href="' . $url . '">' . esc_html($i) . '</a>';
            }
        }
        echo '</div>';
    }

    public function ajax_get()
    {
        check_ajax_referer('tmr_panel_nonce', 'nonce');
        if (!current_user_can(TMR_Panel_Shell::CAPABILITY)) {
            wp_send_json_error(array('message' => __('Permission denied.', 'tailor-manager')));
        }

        $id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
        $post = get_post($id);

        if (!$post || self::POST_TYPE !== $post->post_type) {
            wp_send_json_error(array('message' => __('Customer not found.', 'tailor-manager')));
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
            wp_send_json_error(array('message' => __('Permission denied.', 'tailor-manager')));
        }

        $id      = isset($_POST['customer_id']) ? (int) $_POST['customer_id'] : 0;
        $name    = isset($_POST['name']) ? sanitize_text_field(wp_unslash($_POST['name'])) : '';
        $phone   = isset($_POST['phone']) ? sanitize_text_field(wp_unslash($_POST['phone'])) : '';
        $address = isset($_POST['address']) ? sanitize_textarea_field(wp_unslash($_POST['address'])) : '';
        $status  = isset($_POST['status']) && 'draft' === $_POST['status'] ? 'draft' : 'publish';

        if ('' === $name || '' === $phone) {
            wp_send_json_error(array('message' => __('Name and phone are required.', 'tailor-manager')));
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
            wp_send_json_error(array('message' => __('Permission denied.', 'tailor-manager')));
        }

        $id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
        $post = get_post($id);

        if (!$post || self::POST_TYPE !== $post->post_type) {
            wp_send_json_error(array('message' => __('Customer not found.', 'tailor-manager')));
        }

        wp_trash_post($id);
        wp_send_json_success();
    }
}
