<?php
defined('ABSPATH') || exit;

class TMR_Dress_Panel
{
    const POST_TYPE = TMR_Dress_Post_Type::POST_TYPE;

    public function __construct()
    {
        add_action('wp_ajax_tmr_save_dress', array($this, 'ajax_save'));
        add_action('wp_ajax_tmr_delete_dress', array($this, 'ajax_delete'));
        add_action('wp_ajax_tmr_get_dress', array($this, 'ajax_get'));
    }

    public static function render()
    {
        if (!current_user_can(TMR_Panel_Shell::CAPABILITY)) {
            wp_die(esc_html__('You do not have permission to access this page.', 'tailor-manager'));
        }

        $query = new WP_Query(array(
            'post_type'      => self::POST_TYPE,
            'post_status'    => array('publish', 'draft'),
            'posts_per_page' => -1,
            'orderby'        => 'title',
            'order'          => 'ASC',
        ));

        $categories = TMR_Category_Taxonomy::get_terms();

        TMR_Panel_Shell::header('dress', __('Dress Manager', 'tailor-manager'));
        ?>
        <div class="tmr-toolbar">
            <div></div>
            <button type="button" class="tmr-btn tmr-btn--primary" id="tmr-add-dress"><span class="dashicons dashicons-plus"></span> <?php esc_html_e('Add Dress', 'tailor-manager'); ?></button>
        </div>

        <table class="tmr-table">
            <thead>
                <tr>
                    <th><?php esc_html_e('Dress', 'tailor-manager'); ?></th>
                    <th><?php esc_html_e('Category', 'tailor-manager'); ?></th>
                    <th><?php esc_html_e('Status', 'tailor-manager'); ?></th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$query->have_posts()) : ?>
                    <tr><td colspan="4" class="tmr-empty"><?php esc_html_e('No dress types yet.', 'tailor-manager'); ?></td></tr>
                <?php else : ?>
                    <?php foreach ($query->posts as $dress) : ?>
                        <?php $terms = get_the_terms($dress, TMR_Category_Taxonomy::TAXONOMY); ?>
                        <tr>
                            <td><?php echo esc_html(get_the_title($dress)); ?></td>
                            <td><?php echo $terms && !is_wp_error($terms) ? esc_html($terms[0]->name) : '—'; ?></td>
                            <td>
                                <?php if ('publish' === $dress->post_status) : ?>
                                    <span class="tmr-badge tmr-badge--delivered"><?php esc_html_e('Active', 'tailor-manager'); ?></span>
                                <?php else : ?>
                                    <span class="tmr-badge tmr-badge--cancelled"><?php esc_html_e('Inactive', 'tailor-manager'); ?></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <button type="button" class="tmr-btn tmr-btn--sm tmr-edit-dress" data-id="<?php echo esc_attr($dress->ID); ?>"><?php esc_html_e('Edit', 'tailor-manager'); ?></button>
                                <button type="button" class="tmr-btn tmr-btn--sm tmr-btn--danger tmr-delete-dress" data-id="<?php echo esc_attr($dress->ID); ?>"><?php esc_html_e('Delete', 'tailor-manager'); ?></button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>

        <div class="tmr-modal-backdrop" id="tmr-dress-modal">
            <div class="tmr-modal">
                <div class="tmr-modal__title">
                    <h2 id="tmr-dress-modal-title"><?php esc_html_e('Add Dress', 'tailor-manager'); ?></h2>
                    <button type="button" class="tmr-modal__close">&times;</button>
                </div>
                <form id="tmr-dress-form">
                    <input type="hidden" name="dress_id" value="0" />
                    <div class="tmr-form-row">
                        <label><?php esc_html_e('Dress', 'tailor-manager'); ?> *</label>
                        <input type="text" name="name" required />
                    </div>
                    <div class="tmr-form-row">
                        <label><?php esc_html_e('Category', 'tailor-manager'); ?> *</label>
                        <select name="category" required>
                            <option value=""><?php esc_html_e('Select Category', 'tailor-manager'); ?></option>
                            <?php foreach ($categories as $term) : ?>
                                <option value="<?php echo esc_attr($term->term_id); ?>"><?php echo esc_html($term->name); ?></option>
                            <?php endforeach; ?>
                        </select>
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
            var $modal = $('#tmr-dress-modal');
            var $form = $('#tmr-dress-form');

            $('#tmr-add-dress').on('click', function () {
                $form[0].reset();
                $form.find('[name="dress_id"]').val(0);
                $('#tmr-dress-modal-title').text('<?php echo esc_js(__('Add Dress', 'tailor-manager')); ?>');
                TMRPanel.openModal($modal);
            });

            $('.tmr-edit-dress').on('click', function () {
                var id = $(this).data('id');
                TMRPanel.call('tmr_get_dress', { id: id }, function (data) {
                    $form.find('[name="dress_id"]').val(data.id);
                    $form.find('[name="name"]').val(data.name);
                    $form.find('[name="category"]').val(data.category_id);
                    $form.find('[name="status"]').val(data.status);
                    $('#tmr-dress-modal-title').text('<?php echo esc_js(__('Edit Dress', 'tailor-manager')); ?>');
                    TMRPanel.openModal($modal);
                });
            });

            $('.tmr-delete-dress').on('click', function () {
                if (!TMRPanel.confirmDelete('<?php echo esc_js(__('Delete this dress?', 'tailor-manager')); ?>')) {
                    return;
                }
                var id = $(this).data('id');
                TMRPanel.call('tmr_delete_dress', { id: id }, function () {
                    window.location.reload();
                });
            });

            $form.on('submit', function (e) {
                e.preventDefault();
                TMRPanel.call('tmr_save_dress', $form.serialize(), function () {
                    window.location.reload();
                });
            });
        });
        </script>
        <?php
        TMR_Panel_Shell::footer();
    }

    public function ajax_get()
    {
        check_ajax_referer('tmr_panel_nonce', 'nonce');
        if (!current_user_can(TMR_Panel_Shell::CAPABILITY)) {
            wp_send_json_error(array('message' => __('Permission denied.', 'tailor-manager')));
        }

        $id   = isset($_POST['id']) ? (int) $_POST['id'] : 0;
        $post = get_post($id);

        if (!$post || self::POST_TYPE !== $post->post_type) {
            wp_send_json_error(array('message' => __('Dress not found.', 'tailor-manager')));
        }

        $terms = get_the_terms($post, TMR_Category_Taxonomy::TAXONOMY);

        wp_send_json_success(array(
            'id'          => $post->ID,
            'name'        => $post->post_title,
            'category_id' => $terms && !is_wp_error($terms) ? $terms[0]->term_id : '',
            'status'      => $post->post_status,
        ));
    }

    public function ajax_save()
    {
        check_ajax_referer('tmr_panel_nonce', 'nonce');
        if (!current_user_can(TMR_Panel_Shell::CAPABILITY)) {
            wp_send_json_error(array('message' => __('Permission denied.', 'tailor-manager')));
        }

        $id          = isset($_POST['dress_id']) ? (int) $_POST['dress_id'] : 0;
        $name        = isset($_POST['name']) ? sanitize_text_field(wp_unslash($_POST['name'])) : '';
        $category_id = isset($_POST['category']) ? (int) $_POST['category'] : 0;
        $status      = isset($_POST['status']) && 'draft' === $_POST['status'] ? 'draft' : 'publish';

        if ('' === $name || !$category_id) {
            wp_send_json_error(array('message' => __('Dress name and category are required.', 'tailor-manager')));
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

        wp_set_object_terms($result, array($category_id), TMR_Category_Taxonomy::TAXONOMY, false);

        wp_send_json_success(array('id' => $result));
    }

    public function ajax_delete()
    {
        check_ajax_referer('tmr_panel_nonce', 'nonce');
        if (!current_user_can(TMR_Panel_Shell::CAPABILITY)) {
            wp_send_json_error(array('message' => __('Permission denied.', 'tailor-manager')));
        }

        $id   = isset($_POST['id']) ? (int) $_POST['id'] : 0;
        $post = get_post($id);

        if (!$post || self::POST_TYPE !== $post->post_type) {
            wp_send_json_error(array('message' => __('Dress not found.', 'tailor-manager')));
        }

        wp_trash_post($id);
        wp_send_json_success();
    }
}
