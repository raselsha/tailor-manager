<?php
defined('ABSPATH') || exit;

/**
 * Dedicated Category + per-category Measurement Field management — split out of the
 * general Settings screen into its own sidebar page since this is the one setting
 * shop-owners actually touch often (a new garment category needs its own measurement
 * field set), per explicit request to make it easier to find/use.
 */
class TMR_Categories_Panel
{
    public function __construct()
    {
        add_action('wp_ajax_tmr_save_category', array($this, 'ajax_save_category'));
        add_action('wp_ajax_tmr_delete_category', array($this, 'ajax_delete_category'));
        add_action('wp_ajax_tmr_save_measurement_fields', array($this, 'ajax_save_measurement_fields'));
    }

    public static function render()
    {
        if (!current_user_can(TMR_Panel_Shell::CAPABILITY)) {
            wp_die(esc_html__('এই পেজ দেখার অনুমতি আপনার নেই।', 'tailor-manager'));
        }

        $categories = TMR_Category_Taxonomy::get_terms();
        $open_cat   = isset($_GET['open_cat']) ? (int) $_GET['open_cat'] : 0;

        TMR_Panel_Shell::header('categories', __('ক্যাটাগরি', 'tailor-manager'), __('প্রথমে একটি ক্যাটাগরি নির্বাচন করুন, তারপর তার সেটিংস দেখা যাবে।', 'tailor-manager'));
        ?>
        <div class="tmr-cat-layout">
            <div class="tmr-cat-list">
                <?php foreach ($categories as $term) :
                    $image_id  = (int) get_term_meta($term->term_id, '_tmr_category_image_id', true);
                    $image_url = $image_id ? wp_get_attachment_image_url($image_id, 'thumbnail') : '';
                ?>
                    <div class="tmr-cat-row" data-id="<?php echo esc_attr($term->term_id); ?>">
                        <span class="tmr-cat-row-icon">
                            <?php if ($image_url) : ?>
                                <img src="<?php echo esc_url($image_url); ?>" alt="" />
                            <?php else : ?>
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20.59 13.41L13.42 20.58a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"></path><line x1="7" y1="7" x2="7.01" y2="7"></line></svg>
                            <?php endif; ?>
                        </span>
                        <span class="tmr-cat-row-name"><?php echo esc_html($term->name); ?></span>
                        <svg class="tmr-cat-row-chevron" width="9" height="9" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><path d="M9 18l6-6-6-6"></path></svg>
                    </div>
                <?php endforeach; ?>
                <div class="tmr-cat-row tmr-cat-row-new" data-id="0">
                    <span class="tmr-cat-row-icon tmr-cat-row-icon-add">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
                    </span>
                    <span class="tmr-cat-row-name"><?php esc_html_e('নতুন ক্যাটাগরি যোগ করুন', 'tailor-manager'); ?></span>
                </div>
            </div>

            <div class="tmr-cat-detail-wrap">
                <p class="tmr-empty tmr-cat-detail-placeholder"><?php esc_html_e('বামের তালিকা থেকে একটি ক্যাটাগরি নির্বাচন করুন।', 'tailor-manager'); ?></p>

                <?php foreach ($categories as $term) :
                    $fields      = TMR_Measurement_Fields::get_for_category($term->slug);
                    $dress_count = count(TMR_Dress_Post_Type::get_by_category($term->slug, false));
                    $part_count  = count(TMR_Dress_Part_Post_Type::get_by_category($term->slug, false));
                    $in_use      = ($dress_count + $part_count) > 0;
                    $image_id    = (int) get_term_meta($term->term_id, '_tmr_category_image_id', true);
                    $image_url   = $image_id ? wp_get_attachment_image_url($image_id, 'thumbnail') : '';
                ?>
                    <div class="tmr-card tmr-cat-detail" data-id="<?php echo esc_attr($term->term_id); ?>" data-slug="<?php echo esc_attr($term->slug); ?>" style="display:none;">
                        <div class="tmr-card-header">
                            <h3><?php echo esc_html($term->name); ?></h3>
                            <button type="button" class="tmr-icon-btn tmr-icon-btn-danger tmr-delete-category" <?php echo $in_use ? 'disabled title="' . esc_attr__('এই ক্যাটাগরিতে ড্রেস বা পার্ট যুক্ত আছে, তাই মোছা যাবে না।', 'tailor-manager') . '"' : ''; ?>>
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
                            </button>
                        </div>
                        <div style="padding:18px 20px 0;">
                            <p class="tmr-cat-usage"><?php echo esc_html(sprintf(__('%1$d টি ড্রেস · %2$d টি পার্ট এই ক্যাটাগরিতে', 'tailor-manager'), $dress_count, $part_count)); ?></p>

                            <div class="tmr-step-header">
                                <span class="tmr-step-number">১</span>
                                <h3><?php esc_html_e('নাম ও আইকন', 'tailor-manager'); ?></h3>
                            </div>
                            <div class="tmr-form-row tmr-form-row-duo">
                                <div>
                                    <label class="tmr-form-label"><?php esc_html_e('নাম', 'tailor-manager'); ?></label>
                                    <input type="text" class="tmr-cat-name" value="<?php echo esc_attr($term->name); ?>" />
                                </div>
                                <div>
                                    <label class="tmr-form-label"><?php esc_html_e('আইকন / ছবি', 'tailor-manager'); ?></label>
                                    <div class="tmr-photo-picker">
                                        <div class="tmr-photo-preview tmr-cat-preview-wrap">
                                            <img class="tmr-cat-preview" src="<?php echo esc_url($image_url); ?>" style="width:100%;height:100%;object-fit:contain;<?php echo $image_url ? '' : 'display:none;'; ?>" />
                                            <svg class="tmr-cat-preview-placeholder" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" <?php echo $image_url ? 'style="display:none;"' : ''; ?>><path d="M20.59 13.41L13.42 20.58a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"></path><line x1="7" y1="7" x2="7.01" y2="7"></line></svg>
                                        </div>
                                        <input type="hidden" class="tmr-cat-image-id" value="<?php echo esc_attr($image_id); ?>" />
                                        <button type="button" class="tmr-btn-outline tmr-btn-sm tmr-pick-cat-image"><?php esc_html_e('ছবি নির্বাচন', 'tailor-manager'); ?></button>
                                    </div>
                                </div>
                            </div>
                            <button type="button" class="tmr-btn-add tmr-btn-sm tmr-save-category-detail"><?php esc_html_e('সেভ করুন', 'tailor-manager'); ?></button>

                            <div class="tmr-cat-section2">
                                <div class="tmr-step-header" style="margin-top:22px;">
                                    <span class="tmr-step-number" style="background:#7c3aed;">২</span>
                                    <h3><?php esc_html_e('মাপের ফিল্ড', 'tailor-manager'); ?></h3>
                                </div>
                                <div class="tmr-field-tags">
                                    <?php foreach ($fields as $label) : ?>
                                        <span class="tmr-field-tag"><?php echo esc_html($label); ?><button type="button" class="tmr-field-tag-remove" aria-label="<?php esc_attr_e('মুছুন', 'tailor-manager'); ?>">&times;</button></span>
                                    <?php endforeach; ?>
                                </div>
                                <div class="tmr-field-tag-add">
                                    <input type="text" class="tmr-new-field-input" placeholder="<?php esc_attr_e('নতুন ফিল্ডের নাম লিখে Enter চাপুন…', 'tailor-manager'); ?>" />
                                    <button type="button" class="tmr-btn-outline tmr-btn-sm tmr-add-field-btn"><?php esc_html_e('যোগ করুন', 'tailor-manager'); ?></button>
                                </div>
                            </div>
                        </div>
                        <div class="tmr-cat-section2-footer" style="padding:16px 20px;">
                            <button type="button" class="tmr-btn-add tmr-btn-sm tmr-save-fields-detail"><?php esc_html_e('মাপের ফিল্ড সেভ করুন', 'tailor-manager'); ?></button>
                        </div>
                    </div>
                <?php endforeach; ?>

                <div class="tmr-card tmr-cat-detail tmr-cat-detail-new" data-id="0" data-slug="" style="display:none;">
                    <div class="tmr-card-header"><h3><?php esc_html_e('নতুন ক্যাটাগরি', 'tailor-manager'); ?></h3></div>
                    <div style="padding:18px 20px;">
                        <div class="tmr-step-header">
                            <span class="tmr-step-number">১</span>
                            <h3><?php esc_html_e('নাম ও আইকন', 'tailor-manager'); ?></h3>
                        </div>
                        <div class="tmr-form-row tmr-form-row-duo">
                            <div>
                                <label class="tmr-form-label"><?php esc_html_e('নাম', 'tailor-manager'); ?></label>
                                <input type="text" class="tmr-cat-name" value="" placeholder="<?php esc_attr_e('ক্যাটাগরির নাম…', 'tailor-manager'); ?>" />
                            </div>
                            <div>
                                <label class="tmr-form-label"><?php esc_html_e('আইকন / ছবি', 'tailor-manager'); ?></label>
                                <div class="tmr-photo-picker">
                                    <div class="tmr-photo-preview tmr-cat-preview-wrap">
                                        <img class="tmr-cat-preview" src="" style="width:100%;height:100%;object-fit:contain;display:none;" />
                                        <svg class="tmr-cat-preview-placeholder" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20.59 13.41L13.42 20.58a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"></path><line x1="7" y1="7" x2="7.01" y2="7"></line></svg>
                                    </div>
                                    <input type="hidden" class="tmr-cat-image-id" value="0" />
                                    <button type="button" class="tmr-btn-outline tmr-btn-sm tmr-pick-cat-image"><?php esc_html_e('ছবি নির্বাচন', 'tailor-manager'); ?></button>
                                </div>
                            </div>
                        </div>
                        <button type="button" class="tmr-btn-add tmr-btn-sm tmr-save-category-detail"><?php esc_html_e('সেভ করুন ও পরের ধাপে যান', 'tailor-manager'); ?></button>

                        <p class="tmr-form-hint tmr-cat-new-hint" style="margin-top:18px;"><?php esc_html_e('২. মাপের ফিল্ড — ক্যাটাগরি সেভ করার পর এখানে যোগ করতে পারবেন।', 'tailor-manager'); ?></p>

                        <div class="tmr-cat-section2" style="display:none;">
                            <div class="tmr-step-header" style="margin-top:22px;">
                                <span class="tmr-step-number" style="background:#7c3aed;">২</span>
                                <h3><?php esc_html_e('মাপের ফিল্ড', 'tailor-manager'); ?></h3>
                            </div>
                            <div class="tmr-field-tags"></div>
                            <div class="tmr-field-tag-add">
                                <input type="text" class="tmr-new-field-input" placeholder="<?php esc_attr_e('নতুন ফিল্ডের নাম লিখে Enter চাপুন…', 'tailor-manager'); ?>" />
                                <button type="button" class="tmr-btn-outline tmr-btn-sm tmr-add-field-btn"><?php esc_html_e('যোগ করুন', 'tailor-manager'); ?></button>
                            </div>
                        </div>
                    </div>
                    <div class="tmr-cat-section2-footer" style="padding:16px 20px;display:none;">
                        <button type="button" class="tmr-btn-add tmr-btn-sm tmr-save-fields-detail"><?php esc_html_e('মাপের ফিল্ড সেভ করুন', 'tailor-manager'); ?></button>
                    </div>
                </div>
            </div>
        </div>

        <style>
            /* Master (simple list: icon + name) / detail (full settings, one at a time) —
               selecting a category is the dependency gate for seeing its own settings,
               same "select the primary thing first" pattern as the booking modal this
               was modeled after. */
            .tmr-cat-layout { display: grid; grid-template-columns: 260px 1fr; gap: 20px; align-items: start; }
            @media (max-width: 800px) { .tmr-cat-layout { grid-template-columns: 1fr; } }

            .tmr-cat-list { background: #fff; border: 1px solid #e2e8f0; border-radius: 16px; overflow: hidden; }
            .tmr-cat-row { display: flex; align-items: center; gap: 10px; padding: 12px 16px; cursor: pointer; border-bottom: 1px solid #f1f5f9; transition: background .15s ease; }
            .tmr-cat-row:last-child { border-bottom: none; }
            .tmr-cat-row:hover { background: #f8fafc; }
            .tmr-cat-row.is-active { background: rgba(0,97,213,.06); }
            .tmr-cat-row.is-active .tmr-cat-row-name { color: #0061d5; font-weight: 700; }
            .tmr-cat-row-icon { width: 30px; height: 30px; border-radius: 8px; background: #f1f5f9; color: #94a3b8; display: flex; align-items: center; justify-content: center; flex-shrink: 0; overflow: hidden; }
            .tmr-cat-row-icon img { width: 100%; height: 100%; object-fit: cover; }
            .tmr-cat-row-icon-add { background: rgba(0,97,213,.1); color: #0061d5; }
            .tmr-cat-row-name { flex: 1; font-size: 13px; font-weight: 600; color: #1e293b; }
            .tmr-cat-row-chevron { color: #cbd5e1; flex-shrink: 0; }
            .tmr-cat-row-new .tmr-cat-row-name { color: #0061d5; }

            .tmr-cat-detail-placeholder { background: #fff; border: 1px dashed #e2e8f0; border-radius: 16px; padding: 40px; }
            .tmr-cat-name { flex: 1; width: 100%; border: 1px solid #e2e8f0; padding: 9px 12px; border-radius: 10px; font-family: inherit; font-size: 13px; color: #1e293b; box-sizing: border-box; }
            .tmr-cat-usage { font-size: 12px; color: #94a3b8; margin: 0 0 4px; }
            .tmr-field-tags { display: flex; flex-wrap: wrap; gap: 8px; margin: 10px 0 12px; min-height: 30px; }
            .tmr-field-tag { display: inline-flex; align-items: center; gap: 6px; background: rgba(124,58,237,.08); color: #7c3aed; border: 1px solid rgba(124,58,237,.2); border-radius: 999px; padding: 5px 8px 5px 12px; font-size: 12px; font-weight: 600; }
            .tmr-field-tag-remove { background: none; border: none; color: #7c3aed; cursor: pointer; font-size: 15px; line-height: 1; padding: 0 2px; opacity: .6; }
            .tmr-field-tag-remove:hover { opacity: 1; }
            .tmr-field-tag-add { display: flex; gap: 8px; margin-bottom: 12px; }
            .tmr-field-tag-add input { flex: 1; padding: 7px 10px; border: 1px solid #e2e8f0; border-radius: 10px; font-size: 13px; }
        </style>

        <script>
        jQuery(function ($) {
            var $list = $('.tmr-cat-list');
            var $wrap = $('.tmr-cat-detail-wrap');

            function addTag($container, label) {
                if (!label) { return; }
                var exists = false;
                $container.find('.tmr-field-tag').each(function () {
                    if ($(this).contents().first().text().trim() === label) { exists = true; }
                });
                if (exists) { return; }
                var $tag = $('<span class="tmr-field-tag"></span>').text(label);
                $tag.append('<button type="button" class="tmr-field-tag-remove" aria-label="<?php echo esc_js(__('মুছুন', 'tailor-manager')); ?>">&times;</button>');
                $container.append($tag);
            }

            function resetNewPanel() {
                var $panel = $wrap.find('.tmr-cat-detail-new');
                $panel.attr('data-id', '0').attr('data-slug', '');
                $panel.find('.tmr-cat-name').val('');
                $panel.find('.tmr-cat-image-id').val(0);
                $panel.find('.tmr-cat-preview').attr('src', '').hide();
                $panel.find('.tmr-cat-preview-placeholder').show();
                $panel.find('.tmr-field-tags').empty();
                $panel.find('.tmr-new-field-input').val('');
                $panel.find('.tmr-cat-new-hint').show();
                $panel.find('.tmr-cat-section2, .tmr-cat-section2-footer').hide();
            }

            function selectRow(id) {
                $list.find('.tmr-cat-row').removeClass('is-active');
                $list.find('.tmr-cat-row[data-id="' + id + '"]').addClass('is-active');
                $wrap.find('.tmr-cat-detail-placeholder').hide();
                $wrap.find('.tmr-cat-detail').hide();

                if (String(id) === '0') {
                    resetNewPanel();
                    $wrap.find('.tmr-cat-detail-new').show();
                    return;
                }

                $wrap.find('.tmr-cat-detail:not(.tmr-cat-detail-new)[data-id="' + id + '"]').show();
            }

            $(document).on('click', '.tmr-cat-row', function () {
                selectRow($(this).data('id'));
            });

            var openCat = <?php echo (int) $open_cat; ?>;
            if (openCat && $wrap.find('.tmr-cat-detail:not(.tmr-cat-detail-new)[data-id="' + openCat + '"]').length) {
                selectRow(openCat);
            }

            $(document).on('click', '.tmr-field-tag-remove', function () {
                $(this).closest('.tmr-field-tag').remove();
            });

            $(document).on('click', '.tmr-add-field-btn', function () {
                var $panel = $(this).closest('.tmr-cat-detail');
                var $input = $panel.find('.tmr-new-field-input');
                addTag($panel.find('.tmr-field-tags'), $input.val().trim());
                $input.val('').focus();
            });

            $(document).on('keydown', '.tmr-new-field-input', function (e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    $(this).closest('.tmr-cat-detail').find('.tmr-add-field-btn').trigger('click');
                }
            });

            $(document).on('click', '.tmr-pick-cat-image', function (e) {
                e.preventDefault();
                var $panel = $(this).closest('.tmr-cat-detail');
                var frame = wp.media({ title: '<?php echo esc_js(__('ছবি নির্বাচন করুন', 'tailor-manager')); ?>', multiple: false });
                frame.on('select', function () {
                    var attachment = frame.state().get('selection').first().toJSON();
                    var url = attachment.sizes && attachment.sizes.thumbnail ? attachment.sizes.thumbnail.url : attachment.url;
                    $panel.find('.tmr-cat-image-id').val(attachment.id);
                    $panel.find('.tmr-cat-preview').attr('src', url).show();
                    $panel.find('.tmr-cat-preview-placeholder').hide();
                });
                frame.open();
            });

            $(document).on('click', '.tmr-save-category-detail', function () {
                var $btn   = $(this);
                var $panel = $btn.closest('.tmr-cat-detail');
                var isNew  = $panel.hasClass('tmr-cat-detail-new');
                var id     = $panel.data('id');
                var name   = $panel.find('.tmr-cat-name').val().trim();
                var imageId = $panel.find('.tmr-cat-image-id').val();

                if (!name) {
                    window.alert('<?php echo esc_js(__('নাম আবশ্যক।', 'tailor-manager')); ?>');
                    return;
                }

                TMRPanel.call('tmr_save_category', { id: id, name: name, image_id: imageId }, function (data) {
                    if (isNew) {
                        // A brand-new category needs a real term_id/slug before its list row and
                        // detail panel can exist as first-class DOM elements — one reload (with
                        // ?open_cat= so it lands right back on this category, Section 2 unlocked)
                        // is simpler and more reliable than hand-cloning a full detail panel in JS,
                        // and matches how every other panel in this plugin already saves.
                        var url = new URL(window.location.href);
                        url.searchParams.set('open_cat', data.id);
                        window.location.href = url.toString();
                        return;
                    }

                    $panel.find('.tmr-card-header h3').text(data.name);
                    $list.find('.tmr-cat-row[data-id="' + id + '"] .tmr-cat-row-name').text(data.name);
                    if ($panel.find('.tmr-cat-preview').is(':visible')) {
                        var newUrl = $panel.find('.tmr-cat-preview').attr('src');
                        var $rowIcon = $list.find('.tmr-cat-row[data-id="' + id + '"] .tmr-cat-row-icon');
                        $rowIcon.html($('<img />').attr('src', newUrl));
                    }
                });
            });

            $(document).on('click', '.tmr-save-fields-detail', function () {
                var $panel = $(this).closest('.tmr-cat-detail');
                var slug   = $panel.data('slug');
                var labels = [];
                $panel.find('.tmr-field-tag').each(function () {
                    labels.push($(this).contents().first().text().trim());
                });

                TMRPanel.call('tmr_save_measurement_fields', { slug: slug, lines: labels.join('\n') }, function () {
                    window.alert('<?php echo esc_js(__('মাপের ফিল্ড সেভ হয়েছে।', 'tailor-manager')); ?>');
                });
            });

            $(document).on('click', '.tmr-delete-category', function () {
                if ($(this).is(':disabled')) { return; }
                if (!TMRPanel.confirmDelete('<?php echo esc_js(__('এই ক্যাটাগরিটি মুছবেন?', 'tailor-manager')); ?>')) { return; }
                var id = $(this).closest('.tmr-cat-detail').data('id');
                TMRPanel.call('tmr_delete_category', { id: id }, function () {
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

    public function ajax_save_category()
    {
        check_ajax_referer('tmr_panel_nonce', 'nonce');
        if (!current_user_can(TMR_Panel_Shell::CAPABILITY)) {
            wp_send_json_error(array('message' => __('অনুমতি নেই।', 'tailor-manager')));
        }

        $id       = isset($_POST['id']) ? (int) $_POST['id'] : 0;
        $name     = isset($_POST['name']) ? sanitize_text_field(wp_unslash($_POST['name'])) : '';
        $image_id = isset($_POST['image_id']) ? (int) $_POST['image_id'] : 0;

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

        $term = get_term($term_id, TMR_Category_Taxonomy::TAXONOMY);

        wp_send_json_success(array(
            'id'   => $term_id,
            'slug' => $term && !is_wp_error($term) ? $term->slug : '',
            'name' => $name,
        ));
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
        wp_send_json_success();
    }

    public function ajax_save_measurement_fields()
    {
        check_ajax_referer('tmr_panel_nonce', 'nonce');
        if (!current_user_can(TMR_Panel_Shell::CAPABILITY)) {
            wp_send_json_error(array('message' => __('অনুমতি নেই।', 'tailor-manager')));
        }

        $slug  = isset($_POST['slug']) ? sanitize_title(wp_unslash($_POST['slug'])) : '';
        $lines = isset($_POST['lines']) ? explode("\n", wp_unslash($_POST['lines'])) : array();

        if ('' === $slug) {
            wp_send_json_error(array('message' => __('ভুল ক্যাটাগরি।', 'tailor-manager')));
        }

        $fields = array();
        foreach ($lines as $line) {
            $label = trim(sanitize_text_field($line));
            if ('' === $label) {
                continue;
            }

            // sanitize_key() strips everything but a-z0-9_- and collapses to '' for
            // non-Latin scripts (e.g. Bangla labels), silently colliding distinct fields
            // into one key. sanitize_title() percent-encodes UTF-8 instead, so it stays
            // unique; a numeric suffix guards the remaining edge case of two identical labels.
            $field_slug = sanitize_title($label);
            if ('' === $field_slug) {
                $field_slug = 'field';
            }
            $base   = $field_slug;
            $suffix = 2;
            while (isset($fields[$field_slug])) {
                $field_slug = $base . '-' . $suffix;
                $suffix++;
            }

            $fields[$field_slug] = $label;
        }

        TMR_Measurement_Fields::save_for_category($slug, $fields);
        wp_send_json_success();
    }
}
