<?php
defined('ABSPATH') || exit;

/**
 * Fields as their own first-class items (same grid-card + modal language as the
 * Dress Part manager) instead of being buried inside each category's own detail
 * panel — a field is created once and then, per field, you pick which ড্রেস
 * (category) it applies to, rather than picking fields one category at a time.
 */
class TMR_Measurement_Fields_Panel
{
    public function __construct()
    {
        add_action('wp_ajax_tmr_mf_get_field', array($this, 'ajax_get_field'));
        add_action('wp_ajax_tmr_mf_save_field', array($this, 'ajax_save_field'));
        add_action('wp_ajax_tmr_mf_delete_field', array($this, 'ajax_delete_field'));
        add_action('wp_ajax_tmr_mf_toggle_status', array($this, 'ajax_toggle_status'));
        add_action('wp_ajax_tmr_reorder_measurement_fields', array($this, 'ajax_reorder'));
    }

    public static function render()
    {
        if (!current_user_can(TMR_Panel_Shell::CAPABILITY)) {
            wp_die(esc_html__('এই পেজ দেখার অনুমতি আপনার নেই।', 'tailor-manager'));
        }

        $library    = TMR_Measurement_Fields::get_library();
        $categories = TMR_Category_Taxonomy::get_terms();

        // Each category gets a stable color (by its position in the terms list) so the
        // same dress always shows the same color tag across every field's card — makes
        // it easy to scan "which fields does জামা use" at a glance across the whole grid.
        $tag_palette     = array('blue', 'green', 'amber', 'red', 'purple');
        $category_names  = array();
        $category_colors = array();
        foreach ($categories as $i => $term) {
            $category_names[$term->slug]  = $term->name;
            $category_colors[$term->slug] = $tag_palette[$i % count($tag_palette)];
        }

        $header_right = '<a href="#" class="tmr-btn-add" id="tmr-add-field">' . esc_html__('+ ফিল্ড যোগ করুন', 'tailor-manager') . '</a>';
        TMR_Panel_Shell::header('measurement-fields', __('পরিমাপ', 'tailor-manager'), __('পরিমাপের ফিল্ড তৈরি করুন, তারপর কোন পোশাকে ব্যবহার হবে তা নির্বাচন করুন।', 'tailor-manager'), $header_right, true);
        ?>
        <div class="tmr-card">
            <div class="tmr-dress-grid">
                <?php if (empty($library)) : ?>
                    <span class="tmr-empty"><?php esc_html_e('এখনো কোনো মাপের ফিল্ড তৈরি হয়নি।', 'tailor-manager'); ?></span>
                <?php else : ?>
                    <?php foreach ($library as $slug => $label) :
                        $assigned_tags = array();
                        foreach (TMR_Measurement_Fields::get_assigned_categories($slug) as $cat_slug) {
                            if (isset($category_names[$cat_slug])) {
                                $assigned_tags[] = array(
                                    'name'  => $category_names[$cat_slug],
                                    'color' => $category_colors[$cat_slug],
                                );
                            }
                        }
                        self::render_field_card($slug, $label, $assigned_tags, TMR_Measurement_Fields::is_field_active($slug), TMR_Measurement_Fields::is_default_field($slug), TMR_Measurement_Fields::get_field_image_id($slug));
                    endforeach; ?>
                <?php endif; ?>
                <div class="tmr-dress-card tmr-dress-card-add" id="tmr-add-field-trigger">
                    <div class="tmr-dress-card-icon"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg></div>
                    <div class="tmr-dress-card-name"><?php esc_html_e('যোগ করুন', 'tailor-manager'); ?></div>
                </div>
            </div>
        </div>

        <div class="tmr-modal" id="tmr-field-modal">
            <div class="tmr-modal-content">
                <div class="tmr-modal-head">
                    <h2 id="tmr-field-modal-title"><?php esc_html_e('মাপের ফিল্ড যোগ করুন', 'tailor-manager'); ?></h2>
                    <button type="button" class="tmr-modal-close">&times;</button>
                </div>
                <form id="tmr-field-form">
                    <input type="hidden" name="slug" value="" />
                    <input type="hidden" name="image_id" value="0" />
                    <div class="tmr-modal-body">
                        <div class="tmr-form-row">
                            <label class="tmr-form-label"><?php esc_html_e('মাপের ছবি', 'tailor-manager'); ?></label>
                            <div class="tmr-photo-picker">
                                <div class="tmr-photo-preview" id="tmr-field-preview-wrap"><img id="tmr-field-preview" src="" style="display:none;width:100%;height:100%;object-fit:contain;" /><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" id="tmr-field-preview-placeholder"><rect x="3" y="3" width="18" height="18" rx="2"></rect><circle cx="8.5" cy="8.5" r="1.5"></circle><path d="M21 15l-5-5L5 21"></path></svg></div>
                                <div class="tmr-photo-actions">
                                    <button type="button" class="tmr-btn-outline tmr-btn-sm" id="tmr-field-pick-image"><?php esc_html_e('ছবি নির্বাচন করুন', 'tailor-manager'); ?></button>
                                    <button type="button" class="tmr-btn-outline tmr-btn-sm" id="tmr-field-remove-image"><?php esc_html_e('মুছুন', 'tailor-manager'); ?></button>
                                </div>
                            </div>
                        </div>
                        <div class="tmr-form-row">
                            <label class="tmr-form-label" for="tmr-field-name"><?php esc_html_e('ফিল্ডের নাম', 'tailor-manager'); ?> *</label>
                            <input type="text" name="label" id="tmr-field-name" required />
                        </div>
                        <div class="tmr-form-row">
                            <label class="tmr-form-label"><?php esc_html_e('কোন পোশাকে ব্যবহার হবে', 'tailor-manager'); ?></label>
                            <?php if (empty($categories)) : ?>
                                <p class="tmr-form-hint"><?php esc_html_e('এখনো কোনো পোশাক তৈরি হয়নি।', 'tailor-manager'); ?></p>
                            <?php else : ?>
                                <div class="tmr-field-chip-grid">
                                    <?php foreach ($categories as $term) : ?>
                                        <label class="tmr-field-chip">
                                            <input type="checkbox" name="categories[]" value="<?php echo esc_attr($term->slug); ?>" />
                                            <span class="tmr-field-chip-label"><?php echo esc_html($term->name); ?></span>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                            <p class="tmr-form-hint"><?php esc_html_e('কোনো পোশাক সিলেক্ট না করলে এই ফিল্ডটি স্বয়ংক্রিয়ভাবে সব পোশাকে (ডিফল্ট হিসেবে) যুক্ত থাকবে।', 'tailor-manager'); ?></p>
                        </div>
                    </div>
                    <div class="tmr-modal-foot" style="justify-content:space-between;">
                        <label class="tmr-toggle">
                            <input type="checkbox" name="active" value="1" id="tmr-field-status" class="tmr-status-toggle" checked />
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
            var $modal = $('#tmr-field-modal');
            var $form = $('#tmr-field-form');
            var $preview = $('#tmr-field-preview');
            var $placeholder = $('#tmr-field-preview-placeholder');
            var frame;

            TMRPanel.initSortableGrids('.tmr-dress-grid', 'tmr_reorder_measurement_fields');

            function setPreview(url) {
                if (url) {
                    $preview.attr('src', url).show();
                    $placeholder.hide();
                } else {
                    $preview.hide().attr('src', '');
                    $placeholder.show();
                }
            }

            function openAddModal() {
                $form[0].reset();
                $form.find('[name="slug"]').val('');
                $form.find('[name="image_id"]').val(0);
                setPreview('');
                $form.find('[name="active"]').prop('checked', true);
                TMRPanel.syncStatusToggle($form.find('[name="active"]'));
                $('#tmr-field-modal-title').text('<?php echo esc_js(__('মাপের ফিল্ড যোগ করুন', 'tailor-manager')); ?>');
                TMRPanel.openModal($modal);
            }

            $('#tmr-add-field, #tmr-add-field-trigger').on('click', function (e) {
                e.preventDefault();
                openAddModal();
            });

            $(document).on('click', '.tmr-edit-field', function () {
                var slug = $(this).data('slug');
                TMRPanel.call('tmr_mf_get_field', { slug: slug }, function (data) {
                    $form.find('[name="slug"]').val(data.slug);
                    $form.find('[name="label"]').val(data.label);
                    $form.find('[name="image_id"]').val(data.image_id);
                    setPreview(data.image_url);
                    $form.find('input[name="categories[]"]').each(function () {
                        $(this).prop('checked', data.categories.indexOf($(this).val()) !== -1);
                    });
                    $form.find('[name="active"]').prop('checked', data.active);
                    TMRPanel.syncStatusToggle($form.find('[name="active"]'));
                    $('#tmr-field-modal-title').text('<?php echo esc_js(__('মাপের ফিল্ড এডিট করুন', 'tailor-manager')); ?>');
                    TMRPanel.openModal($modal);
                });
            });

            $('#tmr-field-pick-image').on('click', function (e) {
                e.preventDefault();
                if (frame) {
                    frame.open();
                    return;
                }
                frame = wp.media({ title: '<?php echo esc_js(__('মাপের ছবি নির্বাচন করুন', 'tailor-manager')); ?>', multiple: false });
                frame.on('select', function () {
                    var attachment = frame.state().get('selection').first().toJSON();
                    $form.find('[name="image_id"]').val(attachment.id);
                    setPreview(attachment.sizes && attachment.sizes.thumbnail ? attachment.sizes.thumbnail.url : attachment.url);
                });
                frame.open();
            });

            $('#tmr-field-remove-image').on('click', function (e) {
                e.preventDefault();
                $form.find('[name="image_id"]').val(0);
                setPreview('');
            });

            $(document).on('click', '.tmr-delete-field', function () {
                if (!TMRPanel.confirmDelete('<?php echo esc_js(__('এই মাপের ফিল্ডটি ডিলিট করবেন? এটি সব পোশাক থেকে সরে যাবে।', 'tailor-manager')); ?>')) {
                    return;
                }
                var slug = $(this).data('slug');
                TMRPanel.call('tmr_mf_delete_field', { slug: slug }, function () {
                    window.location.reload();
                });
            });

            $(document).on('change', '.tmr-field-card-toggle', function () {
                var $toggle = $(this);
                var slug = $toggle.data('slug');
                TMRPanel.call('tmr_mf_toggle_status', { slug: slug }, function () {
                    // checkbox already reflects the new state visually; nothing else to sync.
                }, function (message) {
                    $toggle.prop('checked', !$toggle.prop('checked'));
                    window.alert(message);
                });
            });

            $form.on('submit', function (e) {
                e.preventDefault();
                var data = $form.serializeArray();
                if (!$form.find('[name="active"]').is(':checked')) {
                    data.push({ name: 'active', value: '0' });
                }
                TMRPanel.call('tmr_mf_save_field', $.param(data), function () {
                    window.location.reload();
                });
            });
        });
        </script>
        <?php
        TMR_Panel_Shell::footer();
    }

    private static function render_field_card($slug, $label, array $assigned_tags, $active, $is_default, $image_id = 0)
    {
        ?>
        <div class="tmr-dress-card" data-id="<?php echo esc_attr($slug); ?>">
            <div class="tmr-dress-card-icon">
                <?php if ($image_id) : ?>
                    <?php echo wp_get_attachment_image($image_id, array(40, 40)); ?>
                <?php else : ?>
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M21.3 15.3a2.4 2.4 0 0 1 0 3.4l-2.6 2.6a2.4 2.4 0 0 1-3.4 0L2.7 8.7a2.4 2.4 0 0 1 0-3.4l2.6-2.6a2.4 2.4 0 0 1 3.4 0z"></path><path d="M14.5 6.5l3 3"></path><path d="M11.5 9.5l1.5 1.5"></path><path d="M8.5 12.5l1.5 1.5"></path></svg>
                <?php endif; ?>
            </div>
            <div class="tmr-dress-card-name"><?php echo esc_html($label); ?></div>
            <?php if ($is_default) : ?>
                <span class="tmr-badge tmr-badge-purple tmr-dress-card-badge"><?php esc_html_e('ডিফল্ট — সব পোশাকে', 'tailor-manager'); ?></span>
            <?php elseif (empty($assigned_tags)) : ?>
                <span class="tmr-badge tmr-badge-gray tmr-dress-card-badge"><?php esc_html_e('কোনো পোশাকে ব্যবহৃত হয়নি', 'tailor-manager'); ?></span>
            <?php else : ?>
                <div class="tmr-field-card-tags">
                    <?php foreach ($assigned_tags as $tag) : ?>
                        <span class="tmr-badge tmr-badge-<?php echo esc_attr($tag['color']); ?> tmr-field-card-tag"><?php echo esc_html($tag['name']); ?></span>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            <div class="tmr-dress-card-footer">
                <label class="tmr-toggle tmr-mini-toggle" title="<?php esc_attr_e('সক্রিয়/নিষ্ক্রিয়', 'tailor-manager'); ?>">
                    <input type="checkbox" class="tmr-status-toggle tmr-field-card-toggle" data-slug="<?php echo esc_attr($slug); ?>" <?php checked($active); ?> />
                    <span class="tmr-toggle-slider"></span>
                </label>
                <div class="tmr-dress-card-actions">
                    <span class="tmr-drag-handle" title="<?php esc_attr_e('টেনে সাজান', 'tailor-manager'); ?>"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="9" cy="6" r="1.5"></circle><circle cx="15" cy="6" r="1.5"></circle><circle cx="9" cy="12" r="1.5"></circle><circle cx="15" cy="12" r="1.5"></circle><circle cx="9" cy="18" r="1.5"></circle><circle cx="15" cy="18" r="1.5"></circle></svg></span>
                    <span class="tmr-action-btn tmr-edit-field" data-slug="<?php echo esc_attr($slug); ?>" title="<?php esc_attr_e('এডিট', 'tailor-manager'); ?>"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg></span>
                    <span class="tmr-action-btn tmr-action-btn-red tmr-delete-field" data-slug="<?php echo esc_attr($slug); ?>" title="<?php esc_attr_e('ডিলিট', 'tailor-manager'); ?>"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg></span>
                </div>
            </div>
        </div>
        <?php
    }

    public function ajax_reorder()
    {
        check_ajax_referer('tmr_panel_nonce', 'nonce');
        if (!current_user_can(TMR_Panel_Shell::CAPABILITY)) {
            wp_send_json_error(array('message' => __('অনুমতি নেই।', 'tailor-manager')));
        }

        // sanitize_key() (not used here) strips '%' — a field whose label can't
        // transliterate to ASCII (e.g. a Bangla-only label like "লুজ") gets a
        // percent-encoded slug from sanitize_title() at creation time, and
        // sanitize_key() would mangle that back into a string matching nothing
        // in the library, silently dropping the field from the reorder (it just
        // falls through to reorder_library()'s own "append unmatched fields at
        // the end" fallback — every drag attempt on it looks like it does nothing).
        $order = isset($_POST['order']) && is_array($_POST['order']) ? array_map('sanitize_title', wp_unslash($_POST['order'])) : array();
        TMR_Measurement_Fields::reorder_library($order);

        wp_send_json_success();
    }

    public function ajax_get_field()
    {
        check_ajax_referer('tmr_panel_nonce', 'nonce');
        if (!current_user_can(TMR_Panel_Shell::CAPABILITY)) {
            wp_send_json_error(array('message' => __('অনুমতি নেই।', 'tailor-manager')));
        }

        // No sanitize_text_field()/sanitize_title() here on purpose — these already-
        // slugified values (e.g. "%e0%a6%b2%e0%a6%ae..." for Bangla labels) get mangled
        // by both (sanitize_text_field strips "%XX" patterns as an anti-double-encoding
        // measure; sanitize_title would re-slugify an already-encoded string). The real
        // library-membership check right below is the actual security boundary.
        $slug    = isset($_POST['slug']) ? wp_unslash($_POST['slug']) : '';
        $library = TMR_Measurement_Fields::get_library();

        if (!isset($library[$slug])) {
            wp_send_json_error(array('message' => __('ফিল্ড পাওয়া যায়নি।', 'tailor-manager')));
        }

        $image_id = TMR_Measurement_Fields::get_field_image_id($slug);

        wp_send_json_success(array(
            'slug'       => $slug,
            'label'      => $library[$slug],
            'categories' => TMR_Measurement_Fields::get_assigned_categories($slug),
            'active'     => TMR_Measurement_Fields::is_field_active($slug),
            'image_id'   => $image_id,
            'image_url'  => $image_id ? wp_get_attachment_image_url($image_id, 'thumbnail') : '',
        ));
    }

    public function ajax_save_field()
    {
        check_ajax_referer('tmr_panel_nonce', 'nonce');
        if (!current_user_can(TMR_Panel_Shell::CAPABILITY)) {
            wp_send_json_error(array('message' => __('অনুমতি নেই।', 'tailor-manager')));
        }

        $slug  = isset($_POST['slug']) ? wp_unslash($_POST['slug']) : '';
        $label = isset($_POST['label']) ? trim(sanitize_text_field(wp_unslash($_POST['label']))) : '';

        if ('' === $label) {
            wp_send_json_error(array('message' => __('নাম আবশ্যক।', 'tailor-manager')));
        }

        if ('' === $slug || !isset(TMR_Measurement_Fields::get_library()[$slug])) {
            $slug = TMR_Measurement_Fields::create_or_get_field($label);
        } else {
            TMR_Measurement_Fields::rename_field($slug, $label);
        }

        // Whitelist against real taxonomy terms, same reasoning as above — raw slugs,
        // not re-sanitized, but only ones that actually exist survive.
        $valid_slugs = wp_list_pluck(TMR_Category_Taxonomy::get_terms(), 'slug');
        $requested   = isset($_POST['categories']) && is_array($_POST['categories'])
            ? wp_unslash($_POST['categories'])
            : array();
        $category_slugs = array_values(array_intersect($requested, $valid_slugs));

        // No dress checked at all → treat this field as a "default" measurement that
        // automatically applies to every category (current and future) instead of
        // sitting unused; checking specific dresses always means "only these" and
        // clears the default flag, matching how the field library is field-first.
        if (empty($category_slugs)) {
            TMR_Measurement_Fields::set_field_categories($slug, array());
            TMR_Measurement_Fields::set_default_field($slug, true);
        } else {
            TMR_Measurement_Fields::set_default_field($slug, false);
            TMR_Measurement_Fields::set_field_categories($slug, $category_slugs);
        }
        TMR_Measurement_Fields::set_field_active($slug, !empty($_POST['active']));

        $image_id = isset($_POST['image_id']) ? (int) $_POST['image_id'] : 0;
        TMR_Measurement_Fields::set_field_image($slug, $image_id);

        wp_send_json_success(array('slug' => $slug));
    }

    /**
     * Lightweight toggle for the grid card's own active/inactive switch — flips
     * only the active flag, doesn't touch label/category assignments like the full save does.
     */
    public function ajax_toggle_status()
    {
        check_ajax_referer('tmr_panel_nonce', 'nonce');
        if (!current_user_can(TMR_Panel_Shell::CAPABILITY)) {
            wp_send_json_error(array('message' => __('অনুমতি নেই।', 'tailor-manager')));
        }

        $slug    = isset($_POST['slug']) ? wp_unslash($_POST['slug']) : '';
        $library = TMR_Measurement_Fields::get_library();

        if (!isset($library[$slug])) {
            wp_send_json_error(array('message' => __('ফিল্ড পাওয়া যায়নি।', 'tailor-manager')));
        }

        $new_active = !TMR_Measurement_Fields::is_field_active($slug);
        TMR_Measurement_Fields::set_field_active($slug, $new_active);

        wp_send_json_success(array('active' => $new_active));
    }

    public function ajax_delete_field()
    {
        check_ajax_referer('tmr_panel_nonce', 'nonce');
        if (!current_user_can(TMR_Panel_Shell::CAPABILITY)) {
            wp_send_json_error(array('message' => __('অনুমতি নেই।', 'tailor-manager')));
        }

        $slug = isset($_POST['slug']) ? wp_unslash($_POST['slug']) : '';
        if ('' === $slug) {
            wp_send_json_error(array('message' => __('ফিল্ড পাওয়া যায়নি।', 'tailor-manager')));
        }

        TMR_Measurement_Fields::delete_field($slug);
        wp_send_json_success();
    }
}
