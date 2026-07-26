<?php
defined('ABSPATH') || exit;

class TMR_Dress_Part_Panel
{
    const POST_TYPE = TMR_Dress_Part_Post_Type::POST_TYPE;

    public function __construct()
    {
        add_action('wp_ajax_tmr_save_dress_part', array($this, 'ajax_save'));
        add_action('wp_ajax_tmr_delete_dress_part', array($this, 'ajax_delete'));
        add_action('wp_ajax_tmr_get_dress_part', array($this, 'ajax_get'));
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

        TMR_Panel_Shell::header('dress-part', __('Dress Part Manager', 'tailor-manager'));
        ?>
        <div class="tmr-toolbar">
            <div></div>
            <button type="button" class="tmr-btn tmr-btn--primary" id="tmr-add-part"><span class="dashicons dashicons-plus"></span> <?php esc_html_e('Add Part', 'tailor-manager'); ?></button>
        </div>

        <table class="tmr-table">
            <thead>
                <tr>
                    <th><?php esc_html_e('Dress Part Name', 'tailor-manager'); ?></th>
                    <th><?php esc_html_e('Category', 'tailor-manager'); ?></th>
                    <th><?php esc_html_e('Part Measurement', 'tailor-manager'); ?></th>
                    <th><?php esc_html_e('Status', 'tailor-manager'); ?></th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$query->have_posts()) : ?>
                    <tr><td colspan="5" class="tmr-empty"><?php esc_html_e('No dress parts yet.', 'tailor-manager'); ?></td></tr>
                <?php else : ?>
                    <?php foreach ($query->posts as $part) : ?>
                        <?php $terms = get_the_terms($part, TMR_Category_Taxonomy::TAXONOMY); ?>
                        <tr>
                            <td><?php echo esc_html(get_the_title($part)); ?></td>
                            <td><?php echo $terms && !is_wp_error($terms) ? esc_html($terms[0]->name) : '—'; ?></td>
                            <td><?php echo TMR_Dress_Part_Post_Type::measurement_enabled($part->ID) ? esc_html__('Active', 'tailor-manager') : esc_html__('Deactive', 'tailor-manager'); ?></td>
                            <td>
                                <?php if ('publish' === $part->post_status) : ?>
                                    <span class="tmr-badge tmr-badge--delivered"><?php esc_html_e('Active', 'tailor-manager'); ?></span>
                                <?php else : ?>
                                    <span class="tmr-badge tmr-badge--cancelled"><?php esc_html_e('Inactive', 'tailor-manager'); ?></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <button type="button" class="tmr-btn tmr-btn--sm tmr-edit-part" data-id="<?php echo esc_attr($part->ID); ?>"><?php esc_html_e('Edit', 'tailor-manager'); ?></button>
                                <button type="button" class="tmr-btn tmr-btn--sm tmr-btn--danger tmr-delete-part" data-id="<?php echo esc_attr($part->ID); ?>"><?php esc_html_e('Delete', 'tailor-manager'); ?></button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>

        <div class="tmr-modal-backdrop" id="tmr-part-modal">
            <div class="tmr-modal">
                <div class="tmr-modal__title">
                    <h2 id="tmr-part-modal-title"><?php esc_html_e('Add Part', 'tailor-manager'); ?></h2>
                    <button type="button" class="tmr-modal__close">&times;</button>
                </div>
                <form id="tmr-part-form">
                    <input type="hidden" name="part_id" value="0" />
                    <div class="tmr-form-row">
                        <label><?php esc_html_e('Dress Part Name', 'tailor-manager'); ?> *</label>
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
                        <label><input type="checkbox" name="measurement_enabled" value="1" /> <?php esc_html_e('Capture an extra measurement value for this part', 'tailor-manager'); ?></label>
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
            var $modal = $('#tmr-part-modal');
            var $form = $('#tmr-part-form');

            $('#tmr-add-part').on('click', function () {
                $form[0].reset();
                $form.find('[name="part_id"]').val(0);
                $('#tmr-part-modal-title').text('<?php echo esc_js(__('Add Part', 'tailor-manager')); ?>');
                TMRPanel.openModal($modal);
            });

            $('.tmr-edit-part').on('click', function () {
                var id = $(this).data('id');
                TMRPanel.call('tmr_get_dress_part', { id: id }, function (data) {
                    $form.find('[name="part_id"]').val(data.id);
                    $form.find('[name="name"]').val(data.name);
                    $form.find('[name="category"]').val(data.category_id);
                    $form.find('[name="measurement_enabled"]').prop('checked', data.measurement_enabled);
                    $form.find('[name="status"]').val(data.status);
                    $('#tmr-part-modal-title').text('<?php echo esc_js(__('Edit Part', 'tailor-manager')); ?>');
                    TMRPanel.openModal($modal);
                });
            });

            $('.tmr-delete-part').on('click', function () {
                if (!TMRPanel.confirmDelete('<?php echo esc_js(__('Delete this part?', 'tailor-manager')); ?>')) {
                    return;
                }
                var id = $(this).data('id');
                TMRPanel.call('tmr_delete_dress_part', { id: id }, function () {
                    window.location.reload();
                });
            });

            $form.on('submit', function (e) {
                e.preventDefault();
                var data = $form.serializeArray();
                if (!$form.find('[name="measurement_enabled"]').is(':checked')) {
                    data.push({ name: 'measurement_enabled', value: '0' });
                }
                TMRPanel.call('tmr_save_dress_part', $.param(data), function () {
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
            wp_send_json_error(array('message' => __('Dress part not found.', 'tailor-manager')));
        }

        $terms = get_the_terms($post, TMR_Category_Taxonomy::TAXONOMY);

        wp_send_json_success(array(
            'id'                  => $post->ID,
            'name'                => $post->post_title,
            'category_id'         => $terms && !is_wp_error($terms) ? $terms[0]->term_id : '',
            'measurement_enabled' => TMR_Dress_Part_Post_Type::measurement_enabled($post->ID),
            'status'              => $post->post_status,
        ));
    }

    public function ajax_save()
    {
        check_ajax_referer('tmr_panel_nonce', 'nonce');
        if (!current_user_can(TMR_Panel_Shell::CAPABILITY)) {
            wp_send_json_error(array('message' => __('Permission denied.', 'tailor-manager')));
        }

        $id                  = isset($_POST['part_id']) ? (int) $_POST['part_id'] : 0;
        $name                = isset($_POST['name']) ? sanitize_text_field(wp_unslash($_POST['name'])) : '';
        $category_id         = isset($_POST['category']) ? (int) $_POST['category'] : 0;
        $measurement_enabled = !empty($_POST['measurement_enabled']) && '1' === $_POST['measurement_enabled'];
        $status              = isset($_POST['status']) && 'draft' === $_POST['status'] ? 'draft' : 'publish';

        if ('' === $name || !$category_id) {
            wp_send_json_error(array('message' => __('Part name and category are required.', 'tailor-manager')));
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
        update_post_meta($result, '_tmr_measurement_enabled', $measurement_enabled ? '1' : '0');

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
            wp_send_json_error(array('message' => __('Dress part not found.', 'tailor-manager')));
        }

        wp_trash_post($id);
        wp_send_json_success();
    }
}
