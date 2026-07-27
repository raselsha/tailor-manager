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
            wp_die(esc_html__('এই পেজ দেখার অনুমতি আপনার নেই।', 'tailor-manager'));
        }

        $query = new WP_Query(array(
            'post_type'      => self::POST_TYPE,
            'post_status'    => array('publish', 'draft'),
            'posts_per_page' => -1,
            'orderby'        => 'title',
            'order'          => 'ASC',
        ));

        $categories = TMR_Category_Taxonomy::get_terms();

        $header_right = '<a href="#" class="tmr-btn-add" id="tmr-add-part">' . esc_html__('+ পার্ট যোগ করুন', 'tailor-manager') . '</a>';
        TMR_Panel_Shell::header('dress-part', __('ড্রেস পার্ট ম্যানেজার', 'tailor-manager'), __('প্রতিটি ক্যাটাগরির কাস্টমাইজযোগ্য অংশ (কলার, পকেট, প্লেট...)।', 'tailor-manager'), $header_right);
        ?>
        <div class="tmr-card">
            <table class="tmr-table">
                <thead>
                    <tr>
                        <th><?php esc_html_e('পার্টের নাম', 'tailor-manager'); ?></th>
                        <th><?php esc_html_e('ক্যাটাগরি', 'tailor-manager'); ?></th>
                        <th><?php esc_html_e('পার্ট মাপ', 'tailor-manager'); ?></th>
                        <th><?php esc_html_e('স্ট্যাটাস', 'tailor-manager'); ?></th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$query->have_posts()) : ?>
                        <tr><td colspan="5" class="tmr-empty"><?php esc_html_e('এখনো কোনো ড্রেস পার্ট যোগ করা হয়নি।', 'tailor-manager'); ?></td></tr>
                    <?php else : ?>
                        <?php foreach ($query->posts as $part) : ?>
                            <?php $terms = get_the_terms($part, TMR_Category_Taxonomy::TAXONOMY); ?>
                            <tr>
                                <td><?php echo esc_html(get_the_title($part)); ?></td>
                                <td><?php echo $terms && !is_wp_error($terms) ? '<span class="tmr-badge tmr-badge-blue">' . esc_html($terms[0]->name) . '</span>' : '—'; ?></td>
                                <td><?php echo TMR_Dress_Part_Post_Type::measurement_enabled($part->ID) ? '<span class="tmr-badge tmr-badge-green">' . esc_html__('সক্রিয়', 'tailor-manager') . '</span>' : '<span class="tmr-badge tmr-badge-gray">' . esc_html__('নিষ্ক্রিয়', 'tailor-manager') . '</span>'; ?></td>
                                <td>
                                    <?php if ('publish' === $part->post_status) : ?>
                                        <span class="tmr-badge tmr-badge-green"><?php esc_html_e('সক্রিয়', 'tailor-manager'); ?></span>
                                    <?php else : ?>
                                        <span class="tmr-badge tmr-badge-gray"><?php esc_html_e('নিষ্ক্রিয়', 'tailor-manager'); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="tmr-actions">
                                        <span class="tmr-action-btn tmr-edit-part" data-id="<?php echo esc_attr($part->ID); ?>" title="<?php esc_attr_e('এডিট', 'tailor-manager'); ?>"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg></span>
                                        <span class="tmr-action-btn tmr-action-btn-red tmr-delete-part" data-id="<?php echo esc_attr($part->ID); ?>" title="<?php esc_attr_e('ডিলিট', 'tailor-manager'); ?>"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg></span>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="tmr-modal" id="tmr-part-modal">
            <div class="tmr-modal-content">
                <div class="tmr-modal-head">
                    <h2 id="tmr-part-modal-title"><?php esc_html_e('পার্ট যোগ করুন', 'tailor-manager'); ?></h2>
                    <button type="button" class="tmr-modal-close">&times;</button>
                </div>
                <form id="tmr-part-form">
                    <input type="hidden" name="part_id" value="0" />
                    <div class="tmr-modal-body">
                        <div class="tmr-form-row">
                            <label class="tmr-form-label" for="tmr-part-name"><?php esc_html_e('পার্টের নাম', 'tailor-manager'); ?> *</label>
                            <input type="text" name="name" id="tmr-part-name" required />
                        </div>
                        <div class="tmr-form-row">
                            <label class="tmr-form-label" for="tmr-part-category"><?php esc_html_e('ড্রেস পার্ট ক্যাটাগরি', 'tailor-manager'); ?> *</label>
                            <select name="category" id="tmr-part-category" required>
                                <option value=""><?php esc_html_e('ক্যাটাগরি নির্বাচন করুন', 'tailor-manager'); ?></option>
                                <?php foreach ($categories as $term) : ?>
                                    <option value="<?php echo esc_attr($term->term_id); ?>"><?php echo esc_html($term->name); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="tmr-form-row">
                            <label class="tmr-toggle">
                                <input type="checkbox" name="measurement_enabled" value="1" />
                                <span class="tmr-toggle-slider"></span>
                                <span class="tmr-form-label" style="margin:0;"><?php esc_html_e('পার্ট মাপ — এই পার্টের জন্য অতিরিক্ত মাপ নেওয়া হবে', 'tailor-manager'); ?></span>
                            </label>
                        </div>
                        <div class="tmr-form-row">
                            <label class="tmr-form-label"><?php esc_html_e('স্ট্যাটাস', 'tailor-manager'); ?></label>
                            <label class="tmr-toggle">
                                <input type="checkbox" name="status" value="publish" id="tmr-part-status" class="tmr-status-toggle" checked />
                                <span class="tmr-toggle-slider"></span>
                                <span class="tmr-form-label tmr-status-toggle-label" style="margin:0;"><?php esc_html_e('সক্রিয়', 'tailor-manager'); ?></span>
                            </label>
                        </div>
                    </div>
                    <div class="tmr-modal-foot">
                        <button type="submit" class="tmr-btn-add"><?php esc_html_e('পার্ট সেভ করুন', 'tailor-manager'); ?></button>
                    </div>
                </form>
            </div>
        </div>

        <script>
        jQuery(function ($) {
            var $modal = $('#tmr-part-modal');
            var $form = $('#tmr-part-form');

            $('#tmr-add-part').on('click', function (e) {
                e.preventDefault();
                $form[0].reset();
                $form.find('[name="part_id"]').val(0);
                TMRPanel.syncStatusToggle($form.find('[name="status"]'));
                $('#tmr-part-modal-title').text('<?php echo esc_js(__('পার্ট যোগ করুন', 'tailor-manager')); ?>');
                TMRPanel.openModal($modal);
            });

            $('.tmr-edit-part').on('click', function () {
                var id = $(this).data('id');
                TMRPanel.call('tmr_get_dress_part', { id: id }, function (data) {
                    $form.find('[name="part_id"]').val(data.id);
                    $form.find('[name="name"]').val(data.name);
                    $form.find('[name="category"]').val(data.category_id);
                    $form.find('[name="measurement_enabled"]').prop('checked', data.measurement_enabled);
                    $form.find('[name="status"]').prop('checked', data.status === 'publish');
                    TMRPanel.syncStatusToggle($form.find('[name="status"]'));
                    $('#tmr-part-modal-title').text('<?php echo esc_js(__('পার্ট এডিট করুন', 'tailor-manager')); ?>');
                    TMRPanel.openModal($modal);
                });
            });

            $('.tmr-delete-part').on('click', function () {
                if (!TMRPanel.confirmDelete('<?php echo esc_js(__('এই পার্টটি ডিলিট করবেন?', 'tailor-manager')); ?>')) {
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
            wp_send_json_error(array('message' => __('অনুমতি নেই।', 'tailor-manager')));
        }

        $id   = isset($_POST['id']) ? (int) $_POST['id'] : 0;
        $post = get_post($id);

        if (!$post || self::POST_TYPE !== $post->post_type) {
            wp_send_json_error(array('message' => __('ড্রেস পার্ট পাওয়া যায়নি।', 'tailor-manager')));
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
            wp_send_json_error(array('message' => __('অনুমতি নেই।', 'tailor-manager')));
        }

        $id                  = isset($_POST['part_id']) ? (int) $_POST['part_id'] : 0;
        $name                = isset($_POST['name']) ? sanitize_text_field(wp_unslash($_POST['name'])) : '';
        $category_id         = isset($_POST['category']) ? (int) $_POST['category'] : 0;
        $measurement_enabled = !empty($_POST['measurement_enabled']) && '1' === $_POST['measurement_enabled'];
        // Status is a checkbox (toggle) now, not a <select> — unchecked means the field
        // is simply absent from the serialized data, so presence (not value) decides it.
        $status              = !empty($_POST['status']) ? 'publish' : 'draft';

        if ('' === $name || !$category_id) {
            wp_send_json_error(array('message' => __('পার্টের নাম ও ক্যাটাগরি আবশ্যক।', 'tailor-manager')));
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
            wp_send_json_error(array('message' => __('অনুমতি নেই।', 'tailor-manager')));
        }

        $id   = isset($_POST['id']) ? (int) $_POST['id'] : 0;
        $post = get_post($id);

        if (!$post || self::POST_TYPE !== $post->post_type) {
            wp_send_json_error(array('message' => __('ড্রেস পার্ট পাওয়া যায়নি।', 'tailor-manager')));
        }

        wp_trash_post($id);
        wp_send_json_success();
    }
}
