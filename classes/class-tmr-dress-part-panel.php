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
        add_action('wp_ajax_tmr_toggle_part_status', array($this, 'ajax_toggle_status'));
        add_action('wp_ajax_tmr_reorder_dress_part', array($this, 'ajax_reorder'));
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
            'orderby'        => array('menu_order' => 'ASC', 'title' => 'ASC'),
        ));

        $categories = TMR_Category_Taxonomy::get_terms();

        // Group by category so the list can be shown as collapsible per-category
        // sections instead of one flat table with a repeated category column.
        $parts_by_category = array();
        $uncategorized      = array();
        foreach ($query->posts as $part) {
            $terms = get_the_terms($part, TMR_Category_Taxonomy::TAXONOMY);
            if ($terms && !is_wp_error($terms) && !empty($terms)) {
                $parts_by_category[$terms[0]->term_id][] = $part;
            } else {
                $uncategorized[] = $part;
            }
        }

        $header_right = '<button type="button" class="tmr-btn-outline tmr-toggle-all-btn" id="tmr-toggle-all">' . esc_html__('সব বন্ধ করুন', 'tailor-manager') . '</button>'
            . '<a href="#" class="tmr-btn-add" id="tmr-add-part">' . esc_html__('+ পার্ট যোগ করুন', 'tailor-manager') . '</a>';
        TMR_Panel_Shell::header('dress-part', __('পোশাকের অংশ', 'tailor-manager'), __('প্রতিটি পোশাকের কাস্টমাইজযোগ্য অংশ (কলার, পকেট, প্লেট...)।', 'tailor-manager'), $header_right, true);
        ?>
        <?php if (empty($categories)) : ?>
            <div class="tmr-card"><p class="tmr-empty"><?php esc_html_e('এখনো কোনো ক্যাটাগরি তৈরি হয়নি।', 'tailor-manager'); ?></p></div>
        <?php else : ?>
            <?php foreach ($categories as $term) :
                $parts = isset($parts_by_category[$term->term_id]) ? $parts_by_category[$term->term_id] : array();
                self::render_category_block($term->name, $parts, $term->term_id);
            endforeach; ?>

            <?php if (!empty($uncategorized)) :
                self::render_category_block(__('ক্যাটাগরি ছাড়া', 'tailor-manager'), $uncategorized, 0);
            endif; ?>
        <?php endif; ?>

        <div class="tmr-modal" id="tmr-part-modal">
            <div class="tmr-modal-content">
                <div class="tmr-modal-head">
                    <h2 id="tmr-part-modal-title"><?php esc_html_e('পার্ট যোগ করুন', 'tailor-manager'); ?></h2>
                    <button type="button" class="tmr-modal-close">&times;</button>
                </div>
                <form id="tmr-part-form">
                    <input type="hidden" name="part_id" value="0" />
                    <div class="tmr-modal-body">
                        <div class="tmr-form-row tmr-form-row-duo">
                            <div>
                                <label class="tmr-form-label" for="tmr-part-name"><?php esc_html_e('পার্টের নাম', 'tailor-manager'); ?> *</label>
                                <input type="text" name="name" id="tmr-part-name" required />
                            </div>
                            <div>
                                <label class="tmr-form-label" for="tmr-part-category"><?php esc_html_e('ড্রেস পার্ট ক্যাটাগরি', 'tailor-manager'); ?> *</label>
                                <select name="category" id="tmr-part-category" required>
                                    <option value=""><?php esc_html_e('ক্যাটাগরি নির্বাচন করুন', 'tailor-manager'); ?></option>
                                    <?php foreach ($categories as $term) : ?>
                                        <option value="<?php echo esc_attr($term->term_id); ?>"><?php echo esc_html($term->name); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="tmr-form-row">
                            <label class="tmr-toggle">
                                <input type="checkbox" name="measurement_enabled" value="1" id="tmr-part-measurement-toggle" />
                                <span class="tmr-toggle-slider"></span>
                                <span class="tmr-form-label" style="margin:0;"><?php esc_html_e('পার্ট মাপ — এই পার্টের জন্য অতিরিক্ত মাপ নেওয়া হবে', 'tailor-manager'); ?></span>
                            </label>
                        </div>
                        <div class="tmr-form-row" id="tmr-part-measurement-label-row" style="display:none;">
                            <label class="tmr-form-label" for="tmr-part-measurement-label"><?php esc_html_e('এই মাপের নাম', 'tailor-manager'); ?></label>
                            <input type="text" name="measurement_label" id="tmr-part-measurement-label" placeholder="<?php esc_attr_e('যেমনঃ লম্বা', 'tailor-manager'); ?>" />
                        </div>
                    </div>
                    <div class="tmr-modal-foot" style="justify-content:space-between;">
                        <label class="tmr-toggle">
                            <input type="checkbox" name="status" value="publish" id="tmr-part-status" class="tmr-status-toggle" checked />
                            <span class="tmr-toggle-slider"></span>
                            <span class="tmr-form-label tmr-status-toggle-label" style="margin:0;"><?php esc_html_e('সক্রিয়', 'tailor-manager'); ?></span>
                        </label>
                        <button type="submit" class="tmr-btn-add"><?php esc_html_e('পার্ট সেভ করুন', 'tailor-manager'); ?></button>
                    </div>
                </form>
            </div>
        </div>

        <script>
        jQuery(function ($) {
            var $modal = $('#tmr-part-modal');
            var $form = $('#tmr-part-form');

            TMRPanel.initSortableGrids('.tmr-dress-grid', 'tmr_reorder_dress_part');

            $('.tmr-cat-collapse-header').on('click', function () {
                $(this).next('.tmr-cat-collapse-body').slideToggle(150);
                $(this).find('.tmr-cat-collapse-chevron').toggleClass('is-open');
            });

            var allExpanded = true;
            $('#tmr-toggle-all').on('click', function () {
                allExpanded = !allExpanded;
                $('.tmr-cat-collapse-body').toggle(allExpanded);
                $('.tmr-cat-collapse-chevron').toggleClass('is-open', allExpanded);
                $(this).text(allExpanded ? '<?php echo esc_js(__('সব বন্ধ করুন', 'tailor-manager')); ?>' : '<?php echo esc_js(__('সব খুলুন', 'tailor-manager')); ?>');
            });

            $('.tmr-part-card-toggle').on('change', function () {
                var $toggle = $(this);
                var id = $toggle.data('id');
                TMRPanel.call('tmr_toggle_part_status', { id: id }, function () {
                    // checkbox already reflects the new state visually; nothing else to sync.
                }, function (message) {
                    $toggle.prop('checked', !$toggle.prop('checked'));
                    window.alert(message);
                });
            });

            function toggleMeasurementLabelRow() {
                $('#tmr-part-measurement-label-row').toggle($('#tmr-part-measurement-toggle').is(':checked'));
            }

            $(document).on('change', '#tmr-part-measurement-toggle', toggleMeasurementLabelRow);

            function openAddPartModal(categoryId) {
                $form[0].reset();
                $form.find('[name="part_id"]').val(0);
                TMRPanel.syncStatusToggle($form.find('[name="status"]'));
                if (categoryId) {
                    $form.find('[name="category"]').val(categoryId);
                }
                toggleMeasurementLabelRow();
                $('#tmr-part-modal-title').text('<?php echo esc_js(__('পার্ট যোগ করুন', 'tailor-manager')); ?>');
                TMRPanel.openModal($modal);
            }

            $('#tmr-add-part').on('click', function (e) {
                e.preventDefault();
                openAddPartModal(0);
            });

            $(document).on('click', '.tmr-add-part-in-category', function () {
                openAddPartModal($(this).data('category-id'));
            });

            $('.tmr-edit-part').on('click', function () {
                var id = $(this).data('id');
                TMRPanel.call('tmr_get_dress_part', { id: id }, function (data) {
                    $form.find('[name="part_id"]').val(data.id);
                    $form.find('[name="name"]').val(data.name);
                    $form.find('[name="category"]').val(data.category_id);
                    $form.find('[name="measurement_enabled"]').prop('checked', data.measurement_enabled);
                    $form.find('[name="measurement_label"]').val(data.measurement_label);
                    toggleMeasurementLabelRow();
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

    /**
     * @param string $title category name (or "ক্যাটাগরি ছাড়া" for the uncategorized bucket)
     * @param array  $parts WP_Post[]
     */
    private static function render_category_block($title, array $parts, $category_id = 0)
    {
        ?>
        <div class="tmr-card tmr-highlight-card tmr-cat-collapse-block">
            <div class="tmr-cat-collapse-header">
                <h3><?php echo esc_html($title); ?></h3>
                <span class="tmr-cat-collapse-count"><?php echo esc_html(sprintf(_n('%d টি পার্ট', '%d টি পার্ট', count($parts), 'tailor-manager'), count($parts))); ?></span>
                <svg class="tmr-cat-collapse-chevron is-open" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 9l6 6 6-6"></path></svg>
            </div>
            <div class="tmr-cat-collapse-body">
                <div class="tmr-dress-grid">
                    <?php foreach ($parts as $part) : ?>
                        <?php self::render_part_card($part); ?>
                    <?php endforeach; ?>
                    <?php if ($category_id) : ?>
                        <div class="tmr-dress-card tmr-dress-card-add tmr-add-part-in-category" data-category-id="<?php echo esc_attr($category_id); ?>">
                            <div class="tmr-dress-card-icon"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg></div>
                            <div class="tmr-dress-card-name"><?php esc_html_e('যোগ করুন', 'tailor-manager'); ?></div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php
    }

    private static function render_part_card(WP_Post $part)
    {
        $measurement_enabled = TMR_Dress_Part_Post_Type::measurement_enabled($part->ID);
        ?>
        <div class="tmr-dress-card" data-id="<?php echo esc_attr($part->ID); ?>">
            <div class="tmr-dress-card-icon">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M21.3 15.3a2.4 2.4 0 0 1 0 3.4l-2.6 2.6a2.4 2.4 0 0 1-3.4 0L2.7 8.7a2.4 2.4 0 0 1 0-3.4l2.6-2.6a2.4 2.4 0 0 1 3.4 0z"></path><path d="M14.5 6.5l3 3"></path><path d="M11.5 9.5l1.5 1.5"></path><path d="M8.5 12.5l1.5 1.5"></path></svg>
            </div>
            <div class="tmr-dress-card-name"><?php echo esc_html(get_the_title($part)); ?></div>
            <?php if ($measurement_enabled) : ?>
                <span class="tmr-badge tmr-badge-blue tmr-dress-card-badge"><?php esc_html_e('পার্ট মাপ চালু', 'tailor-manager'); ?></span>
            <?php endif; ?>
            <div class="tmr-dress-card-footer">
                <label class="tmr-toggle tmr-mini-toggle" title="<?php esc_attr_e('সক্রিয়/নিষ্ক্রিয়', 'tailor-manager'); ?>">
                    <input type="checkbox" class="tmr-status-toggle tmr-part-card-toggle" data-id="<?php echo esc_attr($part->ID); ?>" <?php checked('publish' === $part->post_status); ?> />
                    <span class="tmr-toggle-slider"></span>
                </label>
                <div class="tmr-dress-card-actions">
                    <span class="tmr-drag-handle" title="<?php esc_attr_e('টেনে সাজান', 'tailor-manager'); ?>"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="9" cy="6" r="1.5"></circle><circle cx="15" cy="6" r="1.5"></circle><circle cx="9" cy="12" r="1.5"></circle><circle cx="15" cy="12" r="1.5"></circle><circle cx="9" cy="18" r="1.5"></circle><circle cx="15" cy="18" r="1.5"></circle></svg></span>
                    <span class="tmr-action-btn tmr-edit-part" data-id="<?php echo esc_attr($part->ID); ?>" title="<?php esc_attr_e('এডিট', 'tailor-manager'); ?>"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg></span>
                    <span class="tmr-action-btn tmr-action-btn-red tmr-delete-part" data-id="<?php echo esc_attr($part->ID); ?>" title="<?php esc_attr_e('ডিলিট', 'tailor-manager'); ?>"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg></span>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Lightweight toggle for the grid card's own active/inactive switch — flips
     * status only, doesn't touch name/category/measurement_enabled like the full save does.
     */
    public function ajax_toggle_status()
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

        $new_status = 'publish' === $post->post_status ? 'draft' : 'publish';
        wp_update_post(array('ID' => $id, 'post_status' => $new_status));

        wp_send_json_success(array('status' => $new_status));
    }

    public function ajax_reorder()
    {
        check_ajax_referer('tmr_panel_nonce', 'nonce');
        if (!current_user_can(TMR_Panel_Shell::CAPABILITY)) {
            wp_send_json_error(array('message' => __('অনুমতি নেই।', 'tailor-manager')));
        }

        $order = isset($_POST['order']) && is_array($_POST['order']) ? array_map('intval', $_POST['order']) : array();
        TMR_Panel_Shell::save_menu_order($order);

        wp_send_json_success();
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
            'measurement_label'   => get_post_meta($post->ID, '_tmr_measurement_label', true),
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
        $measurement_label   = isset($_POST['measurement_label']) ? sanitize_text_field(wp_unslash($_POST['measurement_label'])) : '';
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
        update_post_meta($result, '_tmr_measurement_label', $measurement_label);

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
