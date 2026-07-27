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
            wp_die(esc_html__('এই পেজ দেখার অনুমতি আপনার নেই।', 'tailor-manager'));
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

        $header_right = '<a href="#" class="tmr-btn-add" id="tmr-add-design">' . esc_html__('+ ডিজাইন টাইপ যোগ করুন', 'tailor-manager') . '</a>';
        TMR_Panel_Shell::header('design-type', __('ডিজাইন টাইপ ম্যানেজার', 'tailor-manager'), __('প্রতিটি ড্রেস পার্টের জন্য নির্বাচনযোগ্য ডিজাইন অপশন।', 'tailor-manager'), $header_right);
        ?>
        <div class="tmr-card">
            <table class="tmr-table">
                <thead>
                    <tr>
                        <th><?php esc_html_e('ডিজাইনের নাম', 'tailor-manager'); ?></th>
                        <th><?php esc_html_e('ডিজাইনের ছবি', 'tailor-manager'); ?></th>
                        <th><?php esc_html_e('ড্রেস পার্ট', 'tailor-manager'); ?></th>
                        <th><?php esc_html_e('স্ট্যাটাস', 'tailor-manager'); ?></th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$query->have_posts()) : ?>
                        <tr><td colspan="5" class="tmr-empty"><?php esc_html_e('এখনো কোনো ডিজাইন টাইপ যোগ করা হয়নি।', 'tailor-manager'); ?></td></tr>
                    <?php else : ?>
                        <?php foreach ($query->posts as $design) : ?>
                            <?php $part_id = TMR_Design_Type_Post_Type::get_parent_part_id($design->ID); ?>
                            <tr>
                                <td><?php echo esc_html(get_the_title($design)); ?></td>
                                <td><?php echo has_post_thumbnail($design) ? get_the_post_thumbnail($design, array(40, 40), array('style' => 'border-radius:8px;')) : '—'; ?></td>
                                <td><?php echo $part_id ? esc_html(get_the_title($part_id)) : '—'; ?></td>
                                <td>
                                    <?php if ('publish' === $design->post_status) : ?>
                                        <span class="tmr-badge tmr-badge-green"><?php esc_html_e('সক্রিয়', 'tailor-manager'); ?></span>
                                    <?php else : ?>
                                        <span class="tmr-badge tmr-badge-gray"><?php esc_html_e('নিষ্ক্রিয়', 'tailor-manager'); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="tmr-actions">
                                        <span class="tmr-action-btn tmr-edit-design" data-id="<?php echo esc_attr($design->ID); ?>" title="<?php esc_attr_e('এডিট', 'tailor-manager'); ?>"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg></span>
                                        <span class="tmr-action-btn tmr-action-btn-red tmr-delete-design" data-id="<?php echo esc_attr($design->ID); ?>" title="<?php esc_attr_e('ডিলিট', 'tailor-manager'); ?>"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg></span>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php TMR_Customers_Panel::render_pagination($query->max_num_pages, $paged); ?>

        <div class="tmr-modal" id="tmr-design-modal">
            <div class="tmr-modal-content">
                <div class="tmr-modal-head">
                    <h2 id="tmr-design-modal-title"><?php esc_html_e('ডিজাইন টাইপ যোগ করুন', 'tailor-manager'); ?></h2>
                    <button type="button" class="tmr-modal-close">&times;</button>
                </div>
                <form id="tmr-design-form">
                    <input type="hidden" name="design_id" value="0" />
                    <input type="hidden" name="image_id" value="0" />
                    <div class="tmr-modal-body">
                        <div class="tmr-form-row">
                            <label class="tmr-form-label"><?php esc_html_e('ডিজাইনের ছবি', 'tailor-manager'); ?></label>
                            <div class="tmr-photo-picker">
                                <div class="tmr-photo-preview" id="tmr-design-preview-wrap"><img id="tmr-design-preview" src="" style="display:none;width:100%;height:100%;object-fit:cover;" /><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" id="tmr-design-preview-placeholder"><rect x="3" y="3" width="18" height="18" rx="2"></rect><circle cx="8.5" cy="8.5" r="1.5"></circle><path d="M21 15l-5-5L5 21"></path></svg></div>
                                <div class="tmr-photo-actions">
                                    <button type="button" class="tmr-btn-outline tmr-btn-sm" id="tmr-pick-image"><?php esc_html_e('ছবি নির্বাচন করুন', 'tailor-manager'); ?></button>
                                    <button type="button" class="tmr-btn-outline tmr-btn-sm" id="tmr-remove-image"><?php esc_html_e('মুছুন', 'tailor-manager'); ?></button>
                                </div>
                            </div>
                        </div>
                        <div class="tmr-form-row">
                            <label class="tmr-form-label" for="tmr-design-name"><?php esc_html_e('ডিজাইনের নাম', 'tailor-manager'); ?> *</label>
                            <input type="text" name="name" id="tmr-design-name" required />
                        </div>
                        <div class="tmr-form-row">
                            <label class="tmr-form-label" for="tmr-design-part"><?php esc_html_e('পার্টের নাম', 'tailor-manager'); ?> *</label>
                            <select name="part_id" id="tmr-design-part" required>
                                <option value=""><?php esc_html_e('ড্রেস পার্ট নির্বাচন করুন', 'tailor-manager'); ?></option>
                                <?php foreach ($parts as $part) : ?>
                                    <option value="<?php echo esc_attr($part->ID); ?>"><?php echo esc_html($part->post_title); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="tmr-form-row">
                            <label class="tmr-form-label"><?php esc_html_e('স্ট্যাটাস', 'tailor-manager'); ?></label>
                            <label class="tmr-toggle">
                                <input type="checkbox" name="status" value="publish" id="tmr-design-status" class="tmr-status-toggle" checked />
                                <span class="tmr-toggle-slider"></span>
                                <span class="tmr-form-label tmr-status-toggle-label" style="margin:0;"><?php esc_html_e('সক্রিয়', 'tailor-manager'); ?></span>
                            </label>
                        </div>
                    </div>
                    <div class="tmr-modal-foot">
                        <button type="submit" class="tmr-btn-add"><?php esc_html_e('ডিজাইন টাইপ সেভ করুন', 'tailor-manager'); ?></button>
                    </div>
                </form>
            </div>
        </div>

        <script>
        jQuery(function ($) {
            var $modal = $('#tmr-design-modal');
            var $form = $('#tmr-design-form');
            var $preview = $('#tmr-design-preview');
            var $placeholder = $('#tmr-design-preview-placeholder');
            var frame;

            function setPreview(url) {
                if (url) {
                    $preview.attr('src', url).show();
                    $placeholder.hide();
                } else {
                    $preview.hide().attr('src', '');
                    $placeholder.show();
                }
            }

            $('#tmr-add-design').on('click', function (e) {
                e.preventDefault();
                $form[0].reset();
                $form.find('[name="design_id"]').val(0);
                $form.find('[name="image_id"]').val(0);
                TMRPanel.syncStatusToggle($form.find('[name="status"]'));
                setPreview('');
                $('#tmr-design-modal-title').text('<?php echo esc_js(__('ডিজাইন টাইপ যোগ করুন', 'tailor-manager')); ?>');
                TMRPanel.openModal($modal);
            });

            $('.tmr-edit-design').on('click', function () {
                var id = $(this).data('id');
                TMRPanel.call('tmr_get_design_type', { id: id }, function (data) {
                    $form.find('[name="design_id"]').val(data.id);
                    $form.find('[name="name"]').val(data.name);
                    $form.find('[name="part_id"]').val(data.part_id);
                    $form.find('[name="image_id"]').val(data.image_id);
                    $form.find('[name="status"]').prop('checked', data.status === 'publish');
                    TMRPanel.syncStatusToggle($form.find('[name="status"]'));
                    setPreview(data.image_url);
                    $('#tmr-design-modal-title').text('<?php echo esc_js(__('ডিজাইন টাইপ এডিট করুন', 'tailor-manager')); ?>');
                    TMRPanel.openModal($modal);
                });
            });

            $('.tmr-delete-design').on('click', function () {
                if (!TMRPanel.confirmDelete('<?php echo esc_js(__('এই ডিজাইন টাইপটি ডিলিট করবেন?', 'tailor-manager')); ?>')) {
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
                frame = wp.media({ title: '<?php echo esc_js(__('ডিজাইনের ছবি নির্বাচন করুন', 'tailor-manager')); ?>', multiple: false });
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
            wp_send_json_error(array('message' => __('অনুমতি নেই।', 'tailor-manager')));
        }

        $id   = isset($_POST['id']) ? (int) $_POST['id'] : 0;
        $post = get_post($id);

        if (!$post || self::POST_TYPE !== $post->post_type) {
            wp_send_json_error(array('message' => __('ডিজাইন টাইপ পাওয়া যায়নি।', 'tailor-manager')));
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
            wp_send_json_error(array('message' => __('অনুমতি নেই।', 'tailor-manager')));
        }

        $id       = isset($_POST['design_id']) ? (int) $_POST['design_id'] : 0;
        $name     = isset($_POST['name']) ? sanitize_text_field(wp_unslash($_POST['name'])) : '';
        $part_id  = isset($_POST['part_id']) ? (int) $_POST['part_id'] : 0;
        $image_id = isset($_POST['image_id']) ? (int) $_POST['image_id'] : 0;
        // Status is a checkbox (toggle) now, not a <select> — unchecked means the field
        // is simply absent from the serialized data, so presence (not value) decides it.
        $status   = !empty($_POST['status']) ? 'publish' : 'draft';

        if ('' === $name || !$part_id) {
            wp_send_json_error(array('message' => __('ডিজাইনের নাম ও ড্রেস পার্ট আবশ্যক।', 'tailor-manager')));
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
            wp_send_json_error(array('message' => __('অনুমতি নেই।', 'tailor-manager')));
        }

        $id   = isset($_POST['id']) ? (int) $_POST['id'] : 0;
        $post = get_post($id);

        if (!$post || self::POST_TYPE !== $post->post_type) {
            wp_send_json_error(array('message' => __('ডিজাইন টাইপ পাওয়া যায়নি।', 'tailor-manager')));
        }

        wp_trash_post($id);
        wp_send_json_success();
    }
}
