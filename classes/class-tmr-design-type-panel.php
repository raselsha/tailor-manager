<?php
defined('ABSPATH') || exit;

class TMR_Design_Type_Panel
{
    const POST_TYPE = TMR_Design_Type_Post_Type::POST_TYPE;
    const PER_PAGE = 20;

    public function __construct()
    {
        add_action('wp_ajax_tmr_save_design_type', array($this, 'ajax_save'));
        add_action('wp_ajax_tmr_delete_design_type', array($this, 'ajax_delete'));
        add_action('wp_ajax_tmr_get_design_type', array($this, 'ajax_get'));
    }

    public static function render()
    {
        if (!current_user_can(TMR_Panel_Shell::CAPABILITY)) {
            wp_die(esc_html__('You do not have permission to access this page.', 'tailor-manager'));
        }

        $paged = isset($_GET['paged']) ? max(1, (int) $_GET['paged']) : 1;

        $query = new WP_Query(array(
            'post_type'      => self::POST_TYPE,
            'post_status'    => array('publish', 'draft'),
            'posts_per_page' => self::PER_PAGE,
            'paged'          => $paged,
            'orderby'        => 'title',
            'order'          => 'ASC',
        ));

        $parts = get_posts(array(
            'post_type'      => TMR_Dress_Part_Post_Type::POST_TYPE,
            'post_status'    => array('publish', 'draft'),
            'posts_per_page' => -1,
            'orderby'        => 'title',
            'order'          => 'ASC',
        ));

        TMR_Panel_Shell::header('design-type', __('Design Type Manager', 'tailor-manager'));
        ?>
        <div class="tmr-toolbar">
            <div></div>
            <button type="button" class="tmr-btn tmr-btn--primary" id="tmr-add-design"><span class="dashicons dashicons-plus"></span> <?php esc_html_e('Add Design Type', 'tailor-manager'); ?></button>
        </div>

        <table class="tmr-table">
            <thead>
                <tr>
                    <th><?php esc_html_e('Design Type Name', 'tailor-manager'); ?></th>
                    <th><?php esc_html_e('Design Picture', 'tailor-manager'); ?></th>
                    <th><?php esc_html_e('Dress Part', 'tailor-manager'); ?></th>
                    <th><?php esc_html_e('Status', 'tailor-manager'); ?></th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$query->have_posts()) : ?>
                    <tr><td colspan="5" class="tmr-empty"><?php esc_html_e('No design types yet.', 'tailor-manager'); ?></td></tr>
                <?php else : ?>
                    <?php foreach ($query->posts as $design) : ?>
                        <?php $part_id = TMR_Design_Type_Post_Type::get_parent_part_id($design->ID); ?>
                        <tr>
                            <td><?php echo esc_html(get_the_title($design)); ?></td>
                            <td><?php echo has_post_thumbnail($design) ? get_the_post_thumbnail($design, array(50, 50)) : '—'; ?></td>
                            <td><?php echo $part_id ? esc_html(get_the_title($part_id)) : '—'; ?></td>
                            <td>
                                <?php if ('publish' === $design->post_status) : ?>
                                    <span class="tmr-badge tmr-badge--delivered"><?php esc_html_e('Active', 'tailor-manager'); ?></span>
                                <?php else : ?>
                                    <span class="tmr-badge tmr-badge--cancelled"><?php esc_html_e('Inactive', 'tailor-manager'); ?></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <button type="button" class="tmr-btn tmr-btn--sm tmr-edit-design" data-id="<?php echo esc_attr($design->ID); ?>"><?php esc_html_e('Edit', 'tailor-manager'); ?></button>
                                <button type="button" class="tmr-btn tmr-btn--sm tmr-btn--danger tmr-delete-design" data-id="<?php echo esc_attr($design->ID); ?>"><?php esc_html_e('Delete', 'tailor-manager'); ?></button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>

        <?php TMR_Customers_Panel::render_pagination($query->max_num_pages, $paged); ?>

        <div class="tmr-modal-backdrop" id="tmr-design-modal">
            <div class="tmr-modal">
                <div class="tmr-modal__title">
                    <h2 id="tmr-design-modal-title"><?php esc_html_e('Add Design Type', 'tailor-manager'); ?></h2>
                    <button type="button" class="tmr-modal__close">&times;</button>
                </div>
                <form id="tmr-design-form">
                    <input type="hidden" name="design_id" value="0" />
                    <input type="hidden" name="image_id" value="0" />
                    <div class="tmr-form-row">
                        <label><?php esc_html_e('Design Type Name', 'tailor-manager'); ?> *</label>
                        <input type="text" name="name" required />
                    </div>
                    <div class="tmr-form-row">
                        <label><?php esc_html_e('Dress Part', 'tailor-manager'); ?> *</label>
                        <select name="part_id" required>
                            <option value=""><?php esc_html_e('Select Dress Part', 'tailor-manager'); ?></option>
                            <?php foreach ($parts as $part) : ?>
                                <option value="<?php echo esc_attr($part->ID); ?>"><?php echo esc_html($part->post_title); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="tmr-form-row">
                        <label><?php esc_html_e('Design Picture', 'tailor-manager'); ?></label>
                        <img id="tmr-design-preview" src="" style="max-width:80px;max-height:80px;display:none;margin-bottom:8px;" />
                        <br />
                        <button type="button" class="tmr-btn" id="tmr-pick-image"><?php esc_html_e('Choose Image', 'tailor-manager'); ?></button>
                        <button type="button" class="tmr-btn" id="tmr-remove-image"><?php esc_html_e('Remove', 'tailor-manager'); ?></button>
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
            var $modal = $('#tmr-design-modal');
            var $form = $('#tmr-design-form');
            var $preview = $('#tmr-design-preview');
            var frame;

            function setPreview(url) {
                if (url) {
                    $preview.attr('src', url).show();
                } else {
                    $preview.hide().attr('src', '');
                }
            }

            $('#tmr-add-design').on('click', function () {
                $form[0].reset();
                $form.find('[name="design_id"]').val(0);
                $form.find('[name="image_id"]').val(0);
                setPreview('');
                $('#tmr-design-modal-title').text('<?php echo esc_js(__('Add Design Type', 'tailor-manager')); ?>');
                TMRPanel.openModal($modal);
            });

            $('.tmr-edit-design').on('click', function () {
                var id = $(this).data('id');
                TMRPanel.call('tmr_get_design_type', { id: id }, function (data) {
                    $form.find('[name="design_id"]').val(data.id);
                    $form.find('[name="name"]').val(data.name);
                    $form.find('[name="part_id"]').val(data.part_id);
                    $form.find('[name="image_id"]').val(data.image_id);
                    $form.find('[name="status"]').val(data.status);
                    setPreview(data.image_url);
                    $('#tmr-design-modal-title').text('<?php echo esc_js(__('Edit Design Type', 'tailor-manager')); ?>');
                    TMRPanel.openModal($modal);
                });
            });

            $('.tmr-delete-design').on('click', function () {
                if (!TMRPanel.confirmDelete('<?php echo esc_js(__('Delete this design type?', 'tailor-manager')); ?>')) {
                    return;
                }
                var id = $(this).data('id');
                TMRPanel.call('tmr_delete_design_type', { id: id }, function () {
                    window.location.reload();
                });
            });

            $('#tmr-pick-image').on('click', function (e) {
                e.preventDefault();
                if (frame) {
                    frame.open();
                    return;
                }
                frame = wp.media({ title: '<?php echo esc_js(__('Choose Design Picture', 'tailor-manager')); ?>', multiple: false });
                frame.on('select', function () {
                    var attachment = frame.state().get('selection').first().toJSON();
                    $form.find('[name="image_id"]').val(attachment.id);
                    setPreview(attachment.sizes && attachment.sizes.thumbnail ? attachment.sizes.thumbnail.url : attachment.url);
                });
                frame.open();
            });

            $('#tmr-remove-image').on('click', function (e) {
                e.preventDefault();
                $form.find('[name="image_id"]').val(0);
                setPreview('');
            });

            $form.on('submit', function (e) {
                e.preventDefault();
                TMRPanel.call('tmr_save_design_type', $form.serialize(), function () {
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
            wp_send_json_error(array('message' => __('Design type not found.', 'tailor-manager')));
        }

        $image_id = get_post_thumbnail_id($post);

        wp_send_json_success(array(
            'id'        => $post->ID,
            'name'      => $post->post_title,
            'part_id'   => TMR_Design_Type_Post_Type::get_parent_part_id($post->ID),
            'image_id'  => $image_id ? $image_id : 0,
            'image_url' => $image_id ? wp_get_attachment_image_url($image_id, 'thumbnail') : '',
            'status'    => $post->post_status,
        ));
    }

    public function ajax_save()
    {
        check_ajax_referer('tmr_panel_nonce', 'nonce');
        if (!current_user_can(TMR_Panel_Shell::CAPABILITY)) {
            wp_send_json_error(array('message' => __('Permission denied.', 'tailor-manager')));
        }

        $id       = isset($_POST['design_id']) ? (int) $_POST['design_id'] : 0;
        $name     = isset($_POST['name']) ? sanitize_text_field(wp_unslash($_POST['name'])) : '';
        $part_id  = isset($_POST['part_id']) ? (int) $_POST['part_id'] : 0;
        $image_id = isset($_POST['image_id']) ? (int) $_POST['image_id'] : 0;
        $status   = isset($_POST['status']) && 'draft' === $_POST['status'] ? 'draft' : 'publish';

        if ('' === $name || !$part_id) {
            wp_send_json_error(array('message' => __('Design type name and dress part are required.', 'tailor-manager')));
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

        update_post_meta($result, '_tmr_dress_part_id', $part_id);

        if ($image_id > 0) {
            set_post_thumbnail($result, $image_id);
        } else {
            delete_post_thumbnail($result);
        }

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
            wp_send_json_error(array('message' => __('Design type not found.', 'tailor-manager')));
        }

        wp_trash_post($id);
        wp_send_json_success();
    }
}
