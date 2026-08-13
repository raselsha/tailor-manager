<?php
defined('ABSPATH') || exit;

class TMR_Design_Type_Panel
{
    const POST_TYPE = TMR_Design_Type_Post_Type::POST_TYPE;

    public function __construct()
    {
        add_action('wp_ajax_tmr_save_design_type', array($this, 'ajax_save'));
        add_action('wp_ajax_tmr_delete_design_type', array($this, 'ajax_delete'));
        add_action('wp_ajax_tmr_get_design_type', array($this, 'ajax_get'));
        add_action('wp_ajax_tmr_toggle_design_status', array($this, 'ajax_toggle_status'));
        add_action('wp_ajax_tmr_reorder_design_type', array($this, 'ajax_reorder'));
    }

    public static function render()
    {
        if (!current_user_can(TMR_Panel_Shell::CAPABILITY)) {
            wp_die(esc_html__('এই পেজ দেখার অনুমতি আপনার নেই।', 'tailor-manager'));
        }

        // No pagination here anymore — grouping by dress part (below) only makes sense
        // with the full set loaded, same as the Dress/Dress Part managers already do.
        $query = new WP_Query(array(
            'post_type'      => self::POST_TYPE,
            'post_status'    => array('publish', 'draft'),
            'posts_per_page' => -1,
            'orderby'        => array('menu_order' => 'ASC', 'title' => 'ASC'),
        ));

        // Same order Dress Part's own manager shows/saves (menu_order) — so
        // dragging a part there reorders its section here too, consistently.
        $parts = get_posts(array(
            'post_type'      => TMR_Dress_Part_Post_Type::POST_TYPE,
            'post_status'    => array('publish', 'draft'),
            'posts_per_page' => -1,
            'orderby'        => array('menu_order' => 'ASC', 'title' => 'ASC'),
        ));

        // Group by parent dress part so the list can be shown as collapsible
        // per-part sections instead of one flat table with a repeated part column.
        $designs_by_part = array();
        $unassigned       = array();
        foreach ($query->posts as $design) {
            $part_id = TMR_Design_Type_Post_Type::get_parent_part_id($design->ID);
            if ($part_id) {
                $designs_by_part[$part_id][] = $design;
            } else {
                $unassigned[] = $design;
            }
        }

        $header_right = '<button type="button" class="tmr-btn-outline tmr-toggle-all-btn" id="tmr-toggle-all">' . esc_html__('সব বন্ধ করুন', 'tailor-manager') . '</button>'
            . '<a href="#" class="tmr-btn-add" id="tmr-add-design">' . esc_html__('+ ডিজাইন টাইপ যোগ করুন', 'tailor-manager') . '</a>';
        TMR_Panel_Shell::header('design-type', __('ডিজাইন', 'tailor-manager'), __('পোশাকের প্রতিটি অংশের জন্য নির্বাচনযোগ্য ডিজাইন অপশন।', 'tailor-manager'), $header_right, true);
        ?>
        <?php if (empty($parts)) : ?>
            <div class="tmr-card"><p class="tmr-empty"><?php esc_html_e('এখনো কোনো ড্রেস পার্ট তৈরি হয়নি।', 'tailor-manager'); ?></p></div>
        <?php else : ?>
            <?php foreach ($parts as $part) :
                $designs = isset($designs_by_part[$part->ID]) ? $designs_by_part[$part->ID] : array();
                self::render_part_block($part->post_title, $designs, $part->ID);
            endforeach; ?>

            <?php if (!empty($unassigned)) :
                self::render_part_block(__('পার্ট নির্ধারণ করা হয়নি', 'tailor-manager'), $unassigned, 0);
            endif; ?>
        <?php endif; ?>

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
                                <div class="tmr-photo-preview" id="tmr-design-preview-wrap"><img id="tmr-design-preview" src="" style="display:none;width:100%;height:100%;object-fit:contain;" /><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" id="tmr-design-preview-placeholder"><rect x="3" y="3" width="18" height="18" rx="2"></rect><circle cx="8.5" cy="8.5" r="1.5"></circle><path d="M21 15l-5-5L5 21"></path></svg></div>
                                <div class="tmr-photo-actions">
                                    <button type="button" class="tmr-btn-outline tmr-btn-sm" id="tmr-pick-image"><?php esc_html_e('ছবি নির্বাচন করুন', 'tailor-manager'); ?></button>
                                    <button type="button" class="tmr-btn-outline tmr-btn-sm" id="tmr-remove-image"><?php esc_html_e('মুছুন', 'tailor-manager'); ?></button>
                                </div>
                            </div>
                        </div>
                        <div class="tmr-form-row tmr-form-row-duo">
                            <div>
                                <label class="tmr-form-label" for="tmr-design-name"><?php esc_html_e('ডিজাইনের নাম', 'tailor-manager'); ?> *</label>
                                <input type="text" name="name" id="tmr-design-name" required />
                            </div>
                            <div>
                                <label class="tmr-form-label" for="tmr-design-part"><?php esc_html_e('পার্টের নাম', 'tailor-manager'); ?> *</label>
                                <select name="part_id" id="tmr-design-part" required>
                                    <option value=""><?php esc_html_e('ড্রেস পার্ট নির্বাচন করুন', 'tailor-manager'); ?></option>
                                    <?php foreach ($parts as $part) : ?>
                                        <option value="<?php echo esc_attr($part->ID); ?>"><?php echo esc_html($part->post_title); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="tmr-modal-foot" style="justify-content:space-between;">
                        <label class="tmr-toggle">
                            <input type="checkbox" name="status" value="publish" id="tmr-design-status" class="tmr-status-toggle" checked />
                            <span class="tmr-toggle-slider"></span>
                            <span class="tmr-form-label tmr-status-toggle-label" style="margin:0;"><?php esc_html_e('সক্রিয়', 'tailor-manager'); ?></span>
                        </label>
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

            TMRPanel.initSortableGrids('.tmr-dress-grid', 'tmr_reorder_design_type');

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

            $('.tmr-design-card-toggle').on('change', function () {
                var $toggle = $(this);
                var id = $toggle.data('id');
                TMRPanel.call('tmr_toggle_design_status', { id: id }, function () {
                    // checkbox already reflects the new state visually; nothing else to sync.
                }, function (message) {
                    $toggle.prop('checked', !$toggle.prop('checked'));
                    window.alert(message);
                });
            });

            function setPreview(url) {
                if (url) {
                    $preview.attr('src', url).show();
                    $placeholder.hide();
                } else {
                    $preview.hide().attr('src', '');
                    $placeholder.show();
                }
            }

            function openAddDesignModal(partId) {
                $form[0].reset();
                $form.find('[name="design_id"]').val(0);
                $form.find('[name="image_id"]').val(0);
                TMRPanel.syncStatusToggle($form.find('[name="status"]'));
                setPreview('');
                if (partId) {
                    $form.find('[name="part_id"]').val(partId);
                }
                $('#tmr-design-modal-title').text('<?php echo esc_js(__('ডিজাইন টাইপ যোগ করুন', 'tailor-manager')); ?>');
                TMRPanel.openModal($modal);
            }

            $('#tmr-add-design').on('click', function (e) {
                e.preventDefault();
                openAddDesignModal(0);
            });

            $(document).on('click', '.tmr-add-design-in-part', function () {
                openAddDesignModal($(this).data('part-id'));
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

    /**
     * @param string $title   parent dress part's name (or "পার্ট নির্ধারণ করা হয়নি" for unassigned)
     * @param array  $designs WP_Post[]
     */
    private static function render_part_block($title, array $designs, $part_id = 0)
    {
        ?>
        <div class="tmr-card tmr-highlight-card tmr-cat-collapse-block">
            <div class="tmr-cat-collapse-header">
                <h3><?php echo esc_html($title); ?></h3>
                <span class="tmr-cat-collapse-count"><?php echo esc_html(sprintf(_n('%d টি ডিজাইন', '%d টি ডিজাইন', count($designs), 'tailor-manager'), count($designs))); ?></span>
                <svg class="tmr-cat-collapse-chevron is-open" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 9l6 6 6-6"></path></svg>
            </div>
            <div class="tmr-cat-collapse-body">
                <div class="tmr-dress-grid">
                    <?php foreach ($designs as $design) : ?>
                        <?php self::render_design_card($design); ?>
                    <?php endforeach; ?>
                    <?php if ($part_id) : ?>
                        <div class="tmr-dress-card tmr-dress-card-add tmr-add-design-in-part" data-part-id="<?php echo esc_attr($part_id); ?>">
                            <div class="tmr-dress-card-icon"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg></div>
                            <div class="tmr-dress-card-name"><?php esc_html_e('যোগ করুন', 'tailor-manager'); ?></div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php
    }

    private static function render_design_card(WP_Post $design)
    {
        ?>
        <div class="tmr-dress-card" data-id="<?php echo esc_attr($design->ID); ?>">
            <div class="tmr-dress-card-icon">
                <?php if (has_post_thumbnail($design)) : ?>
                    <?php echo get_the_post_thumbnail($design, array(40, 40)); ?>
                <?php else : ?>
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"></rect><circle cx="8.5" cy="8.5" r="1.5"></circle><path d="M21 15l-5-5L5 21"></path></svg>
                <?php endif; ?>
            </div>
            <div class="tmr-dress-card-name"><?php echo esc_html(get_the_title($design)); ?></div>
            <div class="tmr-dress-card-footer">
                <label class="tmr-toggle tmr-mini-toggle" title="<?php esc_attr_e('সক্রিয়/নিষ্ক্রিয়', 'tailor-manager'); ?>">
                    <input type="checkbox" class="tmr-status-toggle tmr-design-card-toggle" data-id="<?php echo esc_attr($design->ID); ?>" <?php checked('publish' === $design->post_status); ?> />
                    <span class="tmr-toggle-slider"></span>
                </label>
                <div class="tmr-dress-card-actions">
                    <span class="tmr-drag-handle" title="<?php esc_attr_e('টেনে সাজান', 'tailor-manager'); ?>"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="9" cy="6" r="1.5"></circle><circle cx="15" cy="6" r="1.5"></circle><circle cx="9" cy="12" r="1.5"></circle><circle cx="15" cy="12" r="1.5"></circle><circle cx="9" cy="18" r="1.5"></circle><circle cx="15" cy="18" r="1.5"></circle></svg></span>
                    <span class="tmr-action-btn tmr-edit-design" data-id="<?php echo esc_attr($design->ID); ?>" title="<?php esc_attr_e('এডিট', 'tailor-manager'); ?>"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg></span>
                    <span class="tmr-action-btn tmr-action-btn-red tmr-delete-design" data-id="<?php echo esc_attr($design->ID); ?>" title="<?php esc_attr_e('ডিলিট', 'tailor-manager'); ?>"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg></span>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Lightweight toggle for the grid card's own active/inactive switch — flips
     * status only, doesn't touch name/part/image like the full save does.
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
            wp_send_json_error(array('message' => __('ডিজাইন টাইপ পাওয়া যায়নি।', 'tailor-manager')));
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
