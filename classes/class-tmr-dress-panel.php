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
        add_action('wp_ajax_tmr_toggle_dress_status', array($this, 'ajax_toggle_status'));
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

        // Group by category so the list can be shown as collapsible per-category
        // sections instead of one flat table with a repeated category column.
        $dresses_by_category = array();
        $uncategorized       = array();
        foreach ($query->posts as $dress) {
            $terms = get_the_terms($dress, TMR_Category_Taxonomy::TAXONOMY);
            if ($terms && !is_wp_error($terms) && !empty($terms)) {
                $dresses_by_category[$terms[0]->term_id][] = $dress;
            } else {
                $uncategorized[] = $dress;
            }
        }

        $header_right = '<button type="button" class="tmr-btn-outline tmr-toggle-all-btn" id="tmr-toggle-all">' . esc_html__('সব বন্ধ করুন', 'tailor-manager') . '</button>'
            . '<a href="#" class="tmr-btn-add" id="tmr-add-dress">' . esc_html__('+ ড্রেস যোগ করুন', 'tailor-manager') . '</a>';
        TMR_Panel_Shell::header('dress', __('বিভিন্ন পোশাক', 'tailor-manager'), __('প্রতিটি পোশাকের ধরন আলাদাভাবে যোগ করুন।', 'tailor-manager'), $header_right, true);
        ?>
        <?php if (empty($categories)) : ?>
            <div class="tmr-card"><p class="tmr-empty"><?php esc_html_e('এখনো কোনো ক্যাটাগরি তৈরি হয়নি।', 'tailor-manager'); ?></p></div>
        <?php else : ?>
            <?php foreach ($categories as $term) :
                $dresses = isset($dresses_by_category[$term->term_id]) ? $dresses_by_category[$term->term_id] : array();
                self::render_category_block($term->name, $dresses, $term->term_id);
            endforeach; ?>

            <?php if (!empty($uncategorized)) :
                self::render_category_block(__('ক্যাটাগরি ছাড়া', 'tailor-manager'), $uncategorized, 0);
            endif; ?>
        <?php endif; ?>

        <div class="tmr-modal" id="tmr-dress-modal">
            <div class="tmr-modal-content">
                <div class="tmr-modal-head">
                    <h2 id="tmr-dress-modal-title"><?php esc_html_e('ড্রেস যোগ করুন', 'tailor-manager'); ?></h2>
                    <button type="button" class="tmr-modal-close">&times;</button>
                </div>
                <form id="tmr-dress-form">
                    <input type="hidden" name="dress_id" value="0" />
                    <input type="hidden" name="image_id" id="tmr-dress-image-id" value="0" />
                    <div class="tmr-modal-body">
                        <div class="tmr-form-row">
                            <label class="tmr-form-label"><?php esc_html_e('আইকন / ছবি (SVG বা PNG)', 'tailor-manager'); ?></label>
                            <div class="tmr-photo-picker">
                                <div class="tmr-photo-preview" id="tmr-dress-preview-wrap">
                                    <img id="tmr-dress-preview" src="" style="display:none;width:100%;height:100%;object-fit:contain;" />
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" id="tmr-dress-preview-placeholder"><path d="M20.59 13.41L13.42 20.58a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"></path><line x1="7" y1="7" x2="7.01" y2="7"></line></svg>
                                </div>
                                <div class="tmr-photo-actions">
                                    <button type="button" class="tmr-btn-outline tmr-btn-sm" id="tmr-pick-dress-image"><?php esc_html_e('ছবি নির্বাচন করুন', 'tailor-manager'); ?></button>
                                    <button type="button" class="tmr-btn-outline tmr-btn-sm" id="tmr-remove-dress-image"><?php esc_html_e('মুছুন', 'tailor-manager'); ?></button>
                                </div>
                            </div>
                            <p class="tmr-form-hint"><?php esc_html_e('SVG আপলোড করলে script/event-handler যুক্ত ফাইল স্বয়ংক্রিয়ভাবে প্রত্যাখ্যান হবে।', 'tailor-manager'); ?></p>
                        </div>
                        <div class="tmr-form-row tmr-form-row-duo">
                            <div>
                                <label class="tmr-form-label" for="tmr-dress-name"><?php esc_html_e('ড্রেস', 'tailor-manager'); ?> *</label>
                                <input type="text" name="name" id="tmr-dress-name" required />
                            </div>
                            <div>
                                <label class="tmr-form-label" for="tmr-dress-category"><?php esc_html_e('ড্রেস ক্যাটাগরি', 'tailor-manager'); ?> *</label>
                                <select name="category" id="tmr-dress-category" required>
                                    <option value=""><?php esc_html_e('ড্রেস ক্যাটাগরি নির্বাচন করুন', 'tailor-manager'); ?></option>
                                    <?php foreach ($categories as $term) : ?>
                                        <option value="<?php echo esc_attr($term->term_id); ?>"><?php echo esc_html($term->name); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="tmr-modal-foot" style="justify-content:space-between;">
                        <label class="tmr-toggle">
                            <input type="checkbox" name="status" value="publish" id="tmr-dress-status" class="tmr-status-toggle" checked />
                            <span class="tmr-toggle-slider"></span>
                            <span class="tmr-form-label tmr-status-toggle-label" style="margin:0;"><?php esc_html_e('সক্রিয়', 'tailor-manager'); ?></span>
                        </label>
                        <button type="submit" class="tmr-btn-add"><?php esc_html_e('ড্রেস সেভ করুন', 'tailor-manager'); ?></button>
                    </div>
                </form>
            </div>
        </div>

        <script>
        jQuery(function ($) {
            var $modal = $('#tmr-dress-modal');
            var $form = $('#tmr-dress-form');
            var $preview = $('#tmr-dress-preview');
            var $placeholder = $('#tmr-dress-preview-placeholder');
            var dressImageFrame;

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

            $('.tmr-dress-card-toggle').on('change', function () {
                var $toggle = $(this);
                var id = $toggle.data('id');
                TMRPanel.call('tmr_toggle_dress_status', { id: id }, function () {
                    // checkbox already reflects the new state visually; nothing else to sync.
                }, function (message) {
                    $toggle.prop('checked', !$toggle.prop('checked'));
                    window.alert(message);
                });
            });

            function setDressPreview(url) {
                if (url) {
                    $preview.attr('src', url).show();
                    $placeholder.hide();
                } else {
                    $preview.hide().attr('src', '');
                    $placeholder.show();
                }
            }

            function openAddDressModal(categoryId) {
                $form[0].reset();
                $form.find('[name="dress_id"]').val(0);
                $form.find('[name="image_id"]').val(0);
                TMRPanel.syncStatusToggle($form.find('[name="status"]'));
                setDressPreview('');
                if (categoryId) {
                    $form.find('[name="category"]').val(categoryId);
                }
                $('#tmr-dress-modal-title').text('<?php echo esc_js(__('ড্রেস যোগ করুন', 'tailor-manager')); ?>');
                TMRPanel.openModal($modal);
            }

            $('#tmr-add-dress').on('click', function (e) {
                e.preventDefault();
                openAddDressModal(0);
            });

            $(document).on('click', '.tmr-add-dress-in-category', function () {
                openAddDressModal($(this).data('category-id'));
            });

            $('.tmr-edit-dress').on('click', function () {
                var id = $(this).data('id');
                TMRPanel.call('tmr_get_dress', { id: id }, function (data) {
                    $form.find('[name="dress_id"]').val(data.id);
                    $form.find('[name="name"]').val(data.name);
                    $form.find('[name="category"]').val(data.category_id);
                    $form.find('[name="status"]').prop('checked', data.status === 'publish');
                    TMRPanel.syncStatusToggle($form.find('[name="status"]'));
                    $form.find('[name="image_id"]').val(data.image_id);
                    setDressPreview(data.image_url);
                    $('#tmr-dress-modal-title').text('<?php echo esc_js(__('ড্রেস এডিট করুন', 'tailor-manager')); ?>');
                    TMRPanel.openModal($modal);
                });
            });

            $('#tmr-pick-dress-image').on('click', function (e) {
                e.preventDefault();
                if (dressImageFrame) {
                    dressImageFrame.open();
                    return;
                }
                dressImageFrame = wp.media({ title: '<?php echo esc_js(__('ড্রেসের আইকন/ছবি নির্বাচন করুন', 'tailor-manager')); ?>', multiple: false });
                dressImageFrame.on('select', function () {
                    var attachment = dressImageFrame.state().get('selection').first().toJSON();
                    $form.find('[name="image_id"]').val(attachment.id);
                    setDressPreview(attachment.sizes && attachment.sizes.thumbnail ? attachment.sizes.thumbnail.url : attachment.url);
                });
                dressImageFrame.open();
            });

            $('#tmr-remove-dress-image').on('click', function (e) {
                e.preventDefault();
                $form.find('[name="image_id"]').val(0);
                setDressPreview('');
            });

            $('.tmr-delete-dress').on('click', function () {
                if (!TMRPanel.confirmDelete('<?php echo esc_js(__('এই ড্রেসটি ডিলিট করবেন?', 'tailor-manager')); ?>')) {
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

    /**
     * @param string $title   category name (or "ক্যাটাগরি ছাড়া" for the uncategorized bucket)
     * @param array  $dresses WP_Post[]
     */
    private static function render_category_block($title, array $dresses, $category_id = 0)
    {
        ?>
        <div class="tmr-card tmr-highlight-card tmr-cat-collapse-block">
            <div class="tmr-cat-collapse-header">
                <h3><?php echo esc_html($title); ?></h3>
                <span class="tmr-cat-collapse-count"><?php echo esc_html(sprintf(_n('%d টি ড্রেস', '%d টি ড্রেস', count($dresses), 'tailor-manager'), count($dresses))); ?></span>
                <svg class="tmr-cat-collapse-chevron is-open" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 9l6 6 6-6"></path></svg>
            </div>
            <div class="tmr-cat-collapse-body">
                <div class="tmr-dress-grid">
                    <?php foreach ($dresses as $dress) : ?>
                        <?php self::render_dress_card($dress); ?>
                    <?php endforeach; ?>
                    <?php if ($category_id) : ?>
                        <div class="tmr-dress-card tmr-dress-card-add tmr-add-dress-in-category" data-category-id="<?php echo esc_attr($category_id); ?>">
                            <div class="tmr-dress-card-icon"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg></div>
                            <div class="tmr-dress-card-name"><?php esc_html_e('যোগ করুন', 'tailor-manager'); ?></div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php
    }

    private static function render_dress_card(WP_Post $dress)
    {
        ?>
        <div class="tmr-dress-card">
            <div class="tmr-dress-card-icon">
                <?php if (has_post_thumbnail($dress)) : ?>
                    <?php echo get_the_post_thumbnail($dress, array(40, 40)); ?>
                <?php else : ?>
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20.59 13.41L13.42 20.58a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"></path><line x1="7" y1="7" x2="7.01" y2="7"></line></svg>
                <?php endif; ?>
            </div>
            <div class="tmr-dress-card-name"><?php echo esc_html(get_the_title($dress)); ?></div>
            <div class="tmr-dress-card-footer">
                <label class="tmr-toggle tmr-mini-toggle" title="<?php esc_attr_e('সক্রিয়/নিষ্ক্রিয়', 'tailor-manager'); ?>">
                    <input type="checkbox" class="tmr-status-toggle tmr-dress-card-toggle" data-id="<?php echo esc_attr($dress->ID); ?>" <?php checked('publish' === $dress->post_status); ?> />
                    <span class="tmr-toggle-slider"></span>
                </label>
                <div class="tmr-dress-card-actions">
                    <span class="tmr-action-btn tmr-edit-dress" data-id="<?php echo esc_attr($dress->ID); ?>" title="<?php esc_attr_e('এডিট', 'tailor-manager'); ?>"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg></span>
                    <span class="tmr-action-btn tmr-action-btn-red tmr-delete-dress" data-id="<?php echo esc_attr($dress->ID); ?>" title="<?php esc_attr_e('ডিলিট', 'tailor-manager'); ?>"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg></span>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Lightweight toggle for the grid card's own active/inactive switch — flips
     * status only, doesn't touch name/category/image like the full save does.
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
            wp_send_json_error(array('message' => __('ড্রেস পাওয়া যায়নি।', 'tailor-manager')));
        }

        $new_status = 'publish' === $post->post_status ? 'draft' : 'publish';
        wp_update_post(array('ID' => $id, 'post_status' => $new_status));

        wp_send_json_success(array('status' => $new_status));
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
            wp_send_json_error(array('message' => __('ড্রেস পাওয়া যায়নি।', 'tailor-manager')));
        }

        $terms    = get_the_terms($post, TMR_Category_Taxonomy::TAXONOMY);
        $image_id = get_post_thumbnail_id($post);

        wp_send_json_success(array(
            'id'          => $post->ID,
            'name'        => $post->post_title,
            'category_id' => $terms && !is_wp_error($terms) ? $terms[0]->term_id : '',
            'status'      => $post->post_status,
            'image_id'    => $image_id ? $image_id : 0,
            'image_url'   => $image_id ? wp_get_attachment_image_url($image_id, 'thumbnail') : '',
        ));
    }

    public function ajax_save()
    {
        check_ajax_referer('tmr_panel_nonce', 'nonce');
        if (!current_user_can(TMR_Panel_Shell::CAPABILITY)) {
            wp_send_json_error(array('message' => __('অনুমতি নেই।', 'tailor-manager')));
        }

        $id          = isset($_POST['dress_id']) ? (int) $_POST['dress_id'] : 0;
        $name        = isset($_POST['name']) ? sanitize_text_field(wp_unslash($_POST['name'])) : '';
        $category_id = isset($_POST['category']) ? (int) $_POST['category'] : 0;
        // Status is a checkbox (toggle) now, not a <select> — unchecked means the field
        // is simply absent from the serialized data, so presence (not value) decides it.
        $status      = !empty($_POST['status']) ? 'publish' : 'draft';
        $image_id    = isset($_POST['image_id']) ? (int) $_POST['image_id'] : 0;

        if ('' === $name || !$category_id) {
            wp_send_json_error(array('message' => __('ড্রেসের নাম ও ক্যাটাগরি আবশ্যক।', 'tailor-manager')));
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
            wp_send_json_error(array('message' => __('ড্রেস পাওয়া যায়নি।', 'tailor-manager')));
        }

        wp_trash_post($id);
        wp_send_json_success();
    }
}
