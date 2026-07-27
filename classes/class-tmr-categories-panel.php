<?php
defined('ABSPATH') || exit;

/**
 * Dedicated Category ("পোশাক") management — split out of the general Settings screen
 * into its own sidebar page since this is the one setting shop-owners actually touch
 * often (a new garment category needs its own measurement field set), per explicit
 * request to make it easier to find/use. Same grid-card + single add/edit modal
 * language as the Dress Part / Design Type managers, for consistency.
 */
class TMR_Categories_Panel
{
    public function __construct()
    {
        add_action('wp_ajax_tmr_save_category', array($this, 'ajax_save_category'));
        add_action('wp_ajax_tmr_get_category', array($this, 'ajax_get_category'));
        add_action('wp_ajax_tmr_delete_category', array($this, 'ajax_delete_category'));
        add_action('wp_ajax_tmr_toggle_category_status', array($this, 'ajax_toggle_status'));
    }

    public static function render()
    {
        if (!current_user_can(TMR_Panel_Shell::CAPABILITY)) {
            wp_die(esc_html__('এই পেজ দেখার অনুমতি আপনার নেই।', 'tailor-manager'));
        }

        $categories       = TMR_Category_Taxonomy::get_terms();
        $default_slugs    = TMR_Measurement_Fields::get_default_field_slugs();
        // "Default" fields apply to every category automatically — they're managed
        // from the field's own modal (Measurement Fields page), not picked per category,
        // so they're excluded here and just listed as a read-only note instead.
        $selectable_fields = array_diff_key(TMR_Measurement_Fields::get_active_library(), array_flip($default_slugs));
        $default_labels    = array_intersect_key(TMR_Measurement_Fields::get_library(), array_flip($default_slugs));

        $header_right = '<a href="#" class="tmr-btn-add" id="tmr-add-category">' . esc_html__('+ পোশাক যোগ করুন', 'tailor-manager') . '</a>';
        TMR_Panel_Shell::header('categories', __('পোশাক', 'tailor-manager'), __('আপনার সব পোশাক ক্যাটাগরি এখানে পরিচালনা করুন।', 'tailor-manager'), $header_right, true);
        ?>
        <div class="tmr-card">
            <div class="tmr-dress-grid">
                <?php if (empty($categories)) : ?>
                    <span class="tmr-empty"><?php esc_html_e('এখনো কোনো পোশাক তৈরি হয়নি।', 'tailor-manager'); ?></span>
                <?php else : ?>
                    <?php foreach ($categories as $term) :
                        $image_id    = (int) get_term_meta($term->term_id, '_tmr_category_image_id', true);
                        $image_url   = $image_id ? wp_get_attachment_image_url($image_id, 'thumbnail') : '';
                        $dress_count = count(TMR_Dress_Post_Type::get_by_category($term->slug, false));
                        $part_count  = count(TMR_Dress_Part_Post_Type::get_by_category($term->slug, false));
                        $active      = TMR_Category_Taxonomy::is_active($term->term_id);
                        self::render_category_card($term, $image_url, $dress_count, $part_count, $active);
                    endforeach; ?>
                <?php endif; ?>
                <div class="tmr-dress-card tmr-dress-card-add" id="tmr-add-category-trigger">
                    <div class="tmr-dress-card-icon"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg></div>
                    <div class="tmr-dress-card-name"><?php esc_html_e('যোগ করুন', 'tailor-manager'); ?></div>
                </div>
            </div>
        </div>

        <div class="tmr-modal" id="tmr-category-modal">
            <div class="tmr-modal-content">
                <div class="tmr-modal-head">
                    <h2 id="tmr-category-modal-title"><?php esc_html_e('পোশাক যোগ করুন', 'tailor-manager'); ?></h2>
                    <button type="button" class="tmr-modal-close">&times;</button>
                </div>
                <form id="tmr-category-form">
                    <input type="hidden" name="id" value="0" />
                    <div class="tmr-modal-body">
                        <div class="tmr-form-row tmr-form-row-duo">
                            <div>
                                <label class="tmr-form-label" for="tmr-category-name"><?php esc_html_e('নাম', 'tailor-manager'); ?> *</label>
                                <input type="text" name="name" id="tmr-category-name" required />
                            </div>
                            <div>
                                <label class="tmr-form-label"><?php esc_html_e('আইকন / ছবি', 'tailor-manager'); ?></label>
                                <div class="tmr-photo-picker">
                                    <div class="tmr-photo-preview tmr-cat-preview-wrap">
                                        <img class="tmr-cat-preview" src="" style="width:100%;height:100%;object-fit:contain;display:none;" />
                                        <svg class="tmr-cat-preview-placeholder" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20.59 13.41L13.42 20.58a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"></path><line x1="7" y1="7" x2="7.01" y2="7"></line></svg>
                                    </div>
                                    <input type="hidden" name="image_id" class="tmr-cat-image-id" value="0" />
                                    <button type="button" class="tmr-btn-outline tmr-btn-sm tmr-pick-cat-image"><?php esc_html_e('ছবি নির্বাচন', 'tailor-manager'); ?></button>
                                </div>
                            </div>
                        </div>
                        <div class="tmr-form-row">
                            <label class="tmr-form-label"><?php esc_html_e('মাপের ফিল্ড — এই পোশাকে যেসব মাপ নেওয়া হবে', 'tailor-manager'); ?></label>
                            <?php if (empty($selectable_fields)) : ?>
                                <p class="tmr-form-hint"><?php esc_html_e('এখনো কোনো সক্রিয় মাপের ফিল্ড তৈরি হয়নি।', 'tailor-manager'); ?></p>
                            <?php else : ?>
                                <div class="tmr-field-chip-grid">
                                    <?php foreach ($selectable_fields as $field_slug => $field_label) : ?>
                                        <label class="tmr-field-chip">
                                            <input type="checkbox" name="field_slugs[]" value="<?php echo esc_attr($field_slug); ?>" />
                                            <span class="tmr-field-chip-label"><?php echo esc_html($field_label); ?></span>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                            <?php if (!empty($default_labels)) : ?>
                                <p class="tmr-form-hint"><?php echo esc_html(sprintf(__('এগুলো সব পোশাকে স্বয়ংক্রিয়ভাবে যুক্ত থাকে (ডিফল্ট): %s', 'tailor-manager'), implode(', ', $default_labels))); ?></p>
                            <?php endif; ?>
                            <p class="tmr-form-hint">
                                <?php
                                printf(
                                    /* translators: %s: link to the Measurement Fields manager page */
                                    esc_html__('নতুন ফিল্ড তৈরি বা এডিট করতে %s পেজে যান।', 'tailor-manager'),
                                    '<a href="' . esc_url(admin_url('admin.php?page=' . TMR_Panel_Shell::$nav['measurement-fields']['slug'])) . '" style="color:#0061d5;font-weight:600;">' . esc_html(TMR_Panel_Shell::$nav['measurement-fields']['title']) . '</a>'
                                );
                                ?>
                            </p>
                        </div>
                    </div>
                    <div class="tmr-modal-foot" style="justify-content:space-between;">
                        <label class="tmr-toggle">
                            <input type="checkbox" name="active" value="1" id="tmr-category-status" class="tmr-status-toggle" checked />
                            <span class="tmr-toggle-slider"></span>
                            <span class="tmr-form-label tmr-status-toggle-label" style="margin:0;"><?php esc_html_e('সক্রিয়', 'tailor-manager'); ?></span>
                        </label>
                        <button type="submit" class="tmr-btn-add"><?php esc_html_e('সেভ করুন', 'tailor-manager'); ?></button>
                    </div>
                </form>
            </div>
        </div>

        <script>
        jQuery(function ($) {
            var $modal = $('#tmr-category-modal');
            var $form = $('#tmr-category-form');

            function resetModal() {
                $form[0].reset();
                $form.find('[name="id"]').val(0);
                $form.find('.tmr-cat-image-id').val(0);
                $form.find('.tmr-cat-preview').attr('src', '').hide();
                $form.find('.tmr-cat-preview-placeholder').show();
                $form.find('[name="active"]').prop('checked', true);
                TMRPanel.syncStatusToggle($form.find('[name="active"]'));
            }

            function openAddModal() {
                resetModal();
                $('#tmr-category-modal-title').text('<?php echo esc_js(__('পোশাক যোগ করুন', 'tailor-manager')); ?>');
                TMRPanel.openModal($modal);
            }

            $('#tmr-add-category, #tmr-add-category-trigger').on('click', function (e) {
                e.preventDefault();
                openAddModal();
            });

            $(document).on('click', '.tmr-edit-category', function () {
                var id = $(this).data('id');
                TMRPanel.call('tmr_get_category', { id: id }, function (data) {
                    resetModal();
                    $form.find('[name="id"]').val(data.id);
                    $form.find('[name="name"]').val(data.name);
                    $form.find('.tmr-cat-image-id').val(data.image_id);
                    if (data.image_url) {
                        $form.find('.tmr-cat-preview').attr('src', data.image_url).show();
                        $form.find('.tmr-cat-preview-placeholder').hide();
                    }
                    $form.find('input[name="field_slugs[]"]').each(function () {
                        $(this).prop('checked', data.field_slugs.indexOf($(this).val()) !== -1);
                    });
                    $form.find('[name="active"]').prop('checked', data.active);
                    TMRPanel.syncStatusToggle($form.find('[name="active"]'));
                    $('#tmr-category-modal-title').text('<?php echo esc_js(__('পোশাক এডিট করুন', 'tailor-manager')); ?>');
                    TMRPanel.openModal($modal);
                });
            });

            $(document).on('click', '.tmr-pick-cat-image', function (e) {
                e.preventDefault();
                var frame = wp.media({ title: '<?php echo esc_js(__('ছবি নির্বাচন করুন', 'tailor-manager')); ?>', multiple: false });
                frame.on('select', function () {
                    var attachment = frame.state().get('selection').first().toJSON();
                    var url = attachment.sizes && attachment.sizes.thumbnail ? attachment.sizes.thumbnail.url : attachment.url;
                    $form.find('.tmr-cat-image-id').val(attachment.id);
                    $form.find('.tmr-cat-preview').attr('src', url).show();
                    $form.find('.tmr-cat-preview-placeholder').hide();
                });
                frame.open();
            });

            $(document).on('change', '.tmr-category-card-toggle', function () {
                var $toggle = $(this);
                var id = $toggle.data('id');
                TMRPanel.call('tmr_toggle_category_status', { id: id }, function () {
                    // checkbox already reflects the new state visually; nothing else to sync.
                }, function (message) {
                    $toggle.prop('checked', !$toggle.prop('checked'));
                    window.alert(message);
                });
            });

            $(document).on('click', '.tmr-delete-category', function () {
                if (!TMRPanel.confirmDelete('<?php echo esc_js(__('এই পোশাকটি মুছবেন?', 'tailor-manager')); ?>')) {
                    return;
                }
                var id = $(this).data('id');
                TMRPanel.call('tmr_delete_category', { id: id }, function () {
                    window.location.reload();
                }, function (message) {
                    window.alert(message);
                });
            });

            $form.on('submit', function (e) {
                e.preventDefault();
                var data = $form.serializeArray();
                if (!$form.find('[name="active"]').is(':checked')) {
                    data.push({ name: 'active', value: '0' });
                }
                TMRPanel.call('tmr_save_category', $.param(data), function () {
                    window.location.reload();
                }, function (message) {
                    window.alert(message);
                });
            });
        });
        </script>
        <?php
        TMR_Panel_Shell::footer();
    }

    private static function render_category_card(WP_Term $term, $image_url, $dress_count, $part_count, $active)
    {
        ?>
        <div class="tmr-dress-card">
            <div class="tmr-dress-card-icon">
                <?php if ($image_url) : ?>
                    <img src="<?php echo esc_url($image_url); ?>" alt="" />
                <?php else : ?>
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20.59 13.41L13.42 20.58a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"></path><line x1="7" y1="7" x2="7.01" y2="7"></line></svg>
                <?php endif; ?>
            </div>
            <div class="tmr-dress-card-name"><?php echo esc_html($term->name); ?></div>
            <span class="tmr-badge tmr-badge-gray tmr-dress-card-badge"><?php echo esc_html(sprintf(__('%1$d টি ড্রেস · %2$d টি পার্ট', 'tailor-manager'), $dress_count, $part_count)); ?></span>
            <div class="tmr-dress-card-footer">
                <label class="tmr-toggle tmr-mini-toggle" title="<?php esc_attr_e('সক্রিয়/নিষ্ক্রিয়', 'tailor-manager'); ?>">
                    <input type="checkbox" class="tmr-status-toggle tmr-category-card-toggle" data-id="<?php echo esc_attr($term->term_id); ?>" <?php checked($active); ?> />
                    <span class="tmr-toggle-slider"></span>
                </label>
                <div class="tmr-dress-card-actions">
                    <span class="tmr-action-btn tmr-edit-category" data-id="<?php echo esc_attr($term->term_id); ?>" title="<?php esc_attr_e('এডিট', 'tailor-manager'); ?>"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg></span>
                    <span class="tmr-action-btn tmr-action-btn-red tmr-delete-category" data-id="<?php echo esc_attr($term->term_id); ?>" title="<?php esc_attr_e('ডিলিট', 'tailor-manager'); ?>"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg></span>
                </div>
            </div>
        </div>
        <?php
    }

    public function ajax_get_category()
    {
        check_ajax_referer('tmr_panel_nonce', 'nonce');
        if (!current_user_can(TMR_Panel_Shell::CAPABILITY)) {
            wp_send_json_error(array('message' => __('অনুমতি নেই।', 'tailor-manager')));
        }

        $id   = isset($_POST['id']) ? (int) $_POST['id'] : 0;
        $term = get_term($id, TMR_Category_Taxonomy::TAXONOMY);

        if (!$term || is_wp_error($term)) {
            wp_send_json_error(array('message' => __('ক্যাটাগরি পাওয়া যায়নি।', 'tailor-manager')));
        }

        $image_id  = (int) get_term_meta($term->term_id, '_tmr_category_image_id', true);
        $image_url = $image_id ? wp_get_attachment_image_url($image_id, 'thumbnail') : '';

        wp_send_json_success(array(
            'id'          => $term->term_id,
            'name'        => $term->name,
            'image_id'    => $image_id,
            'image_url'   => $image_url,
            'field_slugs' => TMR_Measurement_Fields::get_assigned_slugs($term->slug),
            'active'      => TMR_Category_Taxonomy::is_active($term->term_id),
        ));
    }

    public function ajax_save_category()
    {
        check_ajax_referer('tmr_panel_nonce', 'nonce');
        if (!current_user_can(TMR_Panel_Shell::CAPABILITY)) {
            wp_send_json_error(array('message' => __('অনুমতি নেই।', 'tailor-manager')));
        }

        $id       = isset($_POST['id']) ? (int) $_POST['id'] : 0;
        $name     = isset($_POST['name']) ? sanitize_text_field(wp_unslash($_POST['name'])) : '';
        $image_id = isset($_POST['image_id']) ? (int) $_POST['image_id'] : 0;
        $active   = !empty($_POST['active']);

        if ('' === $name) {
            wp_send_json_error(array('message' => __('নাম আবশ্যক।', 'tailor-manager')));
        }

        if ($id > 0) {
            $result  = wp_update_term($id, TMR_Category_Taxonomy::TAXONOMY, array('name' => $name));
            $term_id = $id;
        } else {
            $result  = wp_insert_term($name, TMR_Category_Taxonomy::TAXONOMY);
            $term_id = is_wp_error($result) ? 0 : $result['term_id'];
        }

        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }

        if ($image_id > 0) {
            update_term_meta($term_id, '_tmr_category_image_id', $image_id);
        } else {
            delete_term_meta($term_id, '_tmr_category_image_id');
        }

        TMR_Category_Taxonomy::set_active($term_id, $active);

        $term = get_term($term_id, TMR_Category_Taxonomy::TAXONOMY);
        if ($term && !is_wp_error($term)) {
            // Only the selectable checklist (active, non-default fields) is editable from
            // this form — inactive fields aren't rendered as checkboxes at all, and default
            // (universal) fields are managed from the field's own modal, not per category —
            // so a save here must only touch this category's selectable-field assignments
            // and leave anything else (inactive fields, stray default entries) untouched.
            $selectable_slugs = array_diff(
                array_keys(TMR_Measurement_Fields::get_active_library()),
                TMR_Measurement_Fields::get_default_field_slugs()
            );
            $requested = isset($_POST['field_slugs']) && is_array($_POST['field_slugs'])
                ? wp_unslash($_POST['field_slugs'])
                : array();
            $requested_selectable = array_values(array_intersect($requested, $selectable_slugs));

            $existing_assigned = TMR_Measurement_Fields::get_assigned_slugs($term->slug);
            $preserved_other   = array_diff($existing_assigned, $selectable_slugs);

            $final_slugs = array_values(array_unique(array_merge($requested_selectable, $preserved_other)));
            TMR_Measurement_Fields::save_assignments_for_category($term->slug, $final_slugs);
        }

        wp_send_json_success(array(
            'id'   => $term_id,
            'slug' => $term && !is_wp_error($term) ? $term->slug : '',
            'name' => $name,
        ));
    }

    public function ajax_toggle_status()
    {
        check_ajax_referer('tmr_panel_nonce', 'nonce');
        if (!current_user_can(TMR_Panel_Shell::CAPABILITY)) {
            wp_send_json_error(array('message' => __('অনুমতি নেই।', 'tailor-manager')));
        }

        $id   = isset($_POST['id']) ? (int) $_POST['id'] : 0;
        $term = get_term($id, TMR_Category_Taxonomy::TAXONOMY);

        if (!$term || is_wp_error($term)) {
            wp_send_json_error(array('message' => __('ক্যাটাগরি পাওয়া যায়নি।', 'tailor-manager')));
        }

        $new_active = !TMR_Category_Taxonomy::is_active($id);
        TMR_Category_Taxonomy::set_active($id, $new_active);

        wp_send_json_success(array('active' => $new_active));
    }

    public function ajax_delete_category()
    {
        check_ajax_referer('tmr_panel_nonce', 'nonce');
        if (!current_user_can(TMR_Panel_Shell::CAPABILITY)) {
            wp_send_json_error(array('message' => __('অনুমতি নেই।', 'tailor-manager')));
        }

        $id   = isset($_POST['id']) ? (int) $_POST['id'] : 0;
        $term = get_term($id, TMR_Category_Taxonomy::TAXONOMY);

        if (!$term || is_wp_error($term)) {
            wp_send_json_error(array('message' => __('ক্যাটাগরি পাওয়া যায়নি।', 'tailor-manager')));
        }

        $dress_count = count(TMR_Dress_Post_Type::get_by_category($term->slug, false));
        $part_count  = count(TMR_Dress_Part_Post_Type::get_by_category($term->slug, false));

        if ($dress_count > 0 || $part_count > 0) {
            wp_send_json_error(array('message' => __('এই ক্যাটাগরিতে ড্রেস বা পার্ট যুক্ত আছে, তাই মোছা যাবে না।', 'tailor-manager')));
        }

        wp_delete_term($id, TMR_Category_Taxonomy::TAXONOMY);
        TMR_Measurement_Fields::forget_category($term->slug);
        wp_send_json_success();
    }
}
