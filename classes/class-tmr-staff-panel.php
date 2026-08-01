<?php
defined('ABSPATH') || exit;

/**
 * Cutting master / tailor directory — flat grid-card manager (no grouping needed,
 * unlike Dress Part/Design Type which group by category/part). Same single add/edit
 * modal + photo-picker-at-top language as the Categories page's own modal.
 */
class TMR_Staff_Panel
{
    const POST_TYPE = TMR_Staff_Post_Type::POST_TYPE;

    // Bidirectional link between a tmr_staff CPT record (the plain-name directory
    // used by the order form) and the WP_User account that logs in as that
    // person — same pattern doctor-appointment uses to link its doctor CPT to a
    // WP_User. display_name is kept in sync with the CPT's post_title (see
    // ajax_save() below) since TMR_My_Orders_Panel matches an order's cutter/
    // tailor free-text field against the logged-in user's display_name.
    const LINKED_USER_META = '_tmr_linked_user_id';
    const LINKED_STAFF_META = '_tmr_linked_staff_post_id';

    public function __construct()
    {
        add_action('wp_ajax_tmr_save_staff', array($this, 'ajax_save'));
        add_action('wp_ajax_tmr_get_staff', array($this, 'ajax_get'));
        add_action('wp_ajax_tmr_delete_staff', array($this, 'ajax_delete'));
        add_action('wp_ajax_tmr_toggle_staff_status', array($this, 'ajax_toggle_status'));
        add_action('wp_ajax_tmr_create_staff_login', array($this, 'ajax_create_login'));
        add_action('wp_ajax_tmr_remove_staff_login', array($this, 'ajax_remove_login'));
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

        $header_right = '<a href="#" class="tmr-btn-add" id="tmr-add-staff">' . esc_html__('+ স্টাফ যোগ করুন', 'tailor-manager') . '</a>';
        TMR_Panel_Shell::header('staff', __('স্টাফ', 'tailor-manager'), __('কাটিং মাস্টার ও সোয়িং অপারেটরদের তালিকা — অর্ডার নেওয়ার সময় এখান থেকেই বেছে নেওয়া যাবে।', 'tailor-manager'), $header_right, true);
        ?>
        <div class="tmr-card">
            <div class="tmr-dress-grid">
                <?php if (empty($query->posts)) : ?>
                    <span class="tmr-empty"><?php esc_html_e('এখনো কোনো স্টাফ যোগ করা হয়নি।', 'tailor-manager'); ?></span>
                <?php else : ?>
                    <?php foreach ($query->posts as $staff) : ?>
                        <?php self::render_staff_card($staff); ?>
                    <?php endforeach; ?>
                <?php endif; ?>
                <div class="tmr-dress-card tmr-dress-card-add" id="tmr-add-staff-trigger">
                    <div class="tmr-dress-card-icon"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg></div>
                    <div class="tmr-dress-card-name"><?php esc_html_e('যোগ করুন', 'tailor-manager'); ?></div>
                </div>
            </div>
        </div>

        <div class="tmr-modal" id="tmr-staff-modal">
            <div class="tmr-modal-content">
                <div class="tmr-modal-head">
                    <h2 id="tmr-staff-modal-title"><?php esc_html_e('স্টাফ যোগ করুন', 'tailor-manager'); ?></h2>
                    <button type="button" class="tmr-modal-close">&times;</button>
                </div>
                <form id="tmr-staff-form">
                    <input type="hidden" name="id" value="0" />
                    <input type="hidden" name="image_id" class="tmr-staff-image-id" value="0" />
                    <div class="tmr-modal-body">
                        <div class="tmr-modal-section">
                            <div class="tmr-icon-picker-center">
                                <div class="tmr-photo-preview tmr-photo-preview-lg tmr-staff-preview-wrap">
                                    <img class="tmr-staff-preview" src="" style="width:100%;height:100%;object-fit:cover;display:none;" />
                                    <svg class="tmr-staff-preview-placeholder" width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
                                </div>
                                <div class="tmr-icon-picker-actions">
                                    <button type="button" class="tmr-btn-outline tmr-btn-sm tmr-pick-staff-image"><?php esc_html_e('ছবি নির্বাচন', 'tailor-manager'); ?></button>
                                    <button type="button" class="tmr-btn-outline tmr-btn-sm tmr-btn-outline-danger tmr-remove-staff-image" style="display:none;"><?php esc_html_e('ছবি সরান', 'tailor-manager'); ?></button>
                                </div>
                            </div>
                        </div>
                        <div class="tmr-modal-section">
                            <label class="tmr-form-label" for="tmr-staff-name"><?php esc_html_e('নাম', 'tailor-manager'); ?> *</label>
                            <input type="text" name="name" id="tmr-staff-name" required />
                        </div>
                    </div>
                    <div class="tmr-modal-foot" style="justify-content:space-between;">
                        <label class="tmr-toggle">
                            <input type="checkbox" name="status" value="publish" id="tmr-staff-status" class="tmr-status-toggle" checked />
                            <span class="tmr-toggle-slider"></span>
                            <span class="tmr-form-label tmr-status-toggle-label" style="margin:0;"><?php esc_html_e('সক্রিয়', 'tailor-manager'); ?></span>
                        </label>
                        <button type="submit" class="tmr-btn-add"><?php esc_html_e('সেভ করুন', 'tailor-manager'); ?></button>
                    </div>
                </form>
            </div>
        </div>

        <div class="tmr-modal" id="tmr-staff-login-modal">
            <div class="tmr-modal-content">
                <div class="tmr-modal-head">
                    <h2><?php esc_html_e('লগইন অ্যাক্সেস দিন', 'tailor-manager'); ?></h2>
                    <button type="button" class="tmr-modal-close">&times;</button>
                </div>
                <form id="tmr-staff-login-form">
                    <input type="hidden" name="staff_id" id="tmr-staff-login-staff-id" value="0" />
                    <div class="tmr-modal-body">
                        <p class="tmr-form-hint" style="margin:0 0 16px;">
                            <?php esc_html_e('নাম', 'tailor-manager'); ?>: <strong id="tmr-staff-login-name"></strong>
                            — <?php esc_html_e('এই নামেই লগইন অ্যাকাউন্টের ডিসপ্লে নাম সেট হবে, যাতে "আমার অর্ডার" পেজে সঠিক অর্ডার দেখায়।', 'tailor-manager'); ?>
                        </p>
                        <div class="tmr-form-row">
                            <label class="tmr-form-label" for="tmr-staff-login-username"><?php esc_html_e('ইউজারনেম', 'tailor-manager'); ?></label>
                            <input type="text" name="username" id="tmr-staff-login-username" required autocomplete="off" />
                        </div>
                        <div class="tmr-form-row">
                            <label class="tmr-form-label" for="tmr-staff-login-email"><?php esc_html_e('ইমেইল', 'tailor-manager'); ?></label>
                            <input type="email" name="email" id="tmr-staff-login-email" required autocomplete="off" />
                        </div>
                        <div class="tmr-form-row">
                            <label class="tmr-form-label" for="tmr-staff-login-password"><?php esc_html_e('পাসওয়ার্ড', 'tailor-manager'); ?></label>
                            <div class="tmr-password-field">
                                <input type="password" name="password" id="tmr-staff-login-password" required autocomplete="new-password" minlength="8" />
                                <button type="button" class="tmr-password-toggle" data-target="tmr-staff-login-password" title="<?php esc_attr_e('দেখান/লুকান', 'tailor-manager'); ?>">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                                </button>
                            </div>
                            <p class="tmr-form-hint"><?php esc_html_e('কমপক্ষে ৮ অক্ষর।', 'tailor-manager'); ?></p>
                        </div>
                    </div>
                    <div class="tmr-modal-foot">
                        <button type="submit" class="tmr-btn-add"><?php esc_html_e('অ্যাকাউন্ট তৈরি করুন', 'tailor-manager'); ?></button>
                    </div>
                </form>
            </div>
        </div>

        <script>
        jQuery(function ($) {
            var $modal = $('#tmr-staff-modal');
            var $form = $('#tmr-staff-form');

            function setImage(imageId, url) {
                $form.find('.tmr-staff-image-id').val(imageId);
                if (url) {
                    $form.find('.tmr-staff-preview').attr('src', url).show();
                    $form.find('.tmr-staff-preview-placeholder').hide();
                    $form.find('.tmr-remove-staff-image').show();
                } else {
                    $form.find('.tmr-staff-preview').attr('src', '').hide();
                    $form.find('.tmr-staff-preview-placeholder').show();
                    $form.find('.tmr-remove-staff-image').hide();
                }
            }

            function resetModal() {
                $form[0].reset();
                $form.find('[name="id"]').val(0);
                setImage(0, '');
                $form.find('[name="status"]').prop('checked', true);
                TMRPanel.syncStatusToggle($form.find('[name="status"]'));
            }

            function openAddModal() {
                resetModal();
                $('#tmr-staff-modal-title').text('<?php echo esc_js(__('স্টাফ যোগ করুন', 'tailor-manager')); ?>');
                TMRPanel.openModal($modal);
            }

            $('#tmr-add-staff, #tmr-add-staff-trigger').on('click', function (e) {
                e.preventDefault();
                openAddModal();
            });

            $(document).on('click', '.tmr-edit-staff', function () {
                var id = $(this).data('id');
                TMRPanel.call('tmr_get_staff', { id: id }, function (data) {
                    resetModal();
                    $form.find('[name="id"]').val(data.id);
                    $form.find('[name="name"]').val(data.name);
                    setImage(data.image_id, data.image_url);
                    $form.find('[name="status"]').prop('checked', data.status === 'publish');
                    TMRPanel.syncStatusToggle($form.find('[name="status"]'));
                    $('#tmr-staff-modal-title').text('<?php echo esc_js(__('স্টাফ এডিট করুন', 'tailor-manager')); ?>');
                    TMRPanel.openModal($modal);
                });
            });

            $(document).on('click', '.tmr-pick-staff-image', function (e) {
                e.preventDefault();
                var frame = wp.media({ title: '<?php echo esc_js(__('ছবি নির্বাচন করুন', 'tailor-manager')); ?>', multiple: false });
                frame.on('select', function () {
                    var attachment = frame.state().get('selection').first().toJSON();
                    var url = attachment.sizes && attachment.sizes.thumbnail ? attachment.sizes.thumbnail.url : attachment.url;
                    setImage(attachment.id, url);
                });
                frame.open();
            });

            $(document).on('click', '.tmr-remove-staff-image', function (e) {
                e.preventDefault();
                setImage(0, '');
            });

            $(document).on('change', '.tmr-staff-card-toggle', function () {
                var $toggle = $(this);
                var id = $toggle.data('id');
                TMRPanel.call('tmr_toggle_staff_status', { id: id }, function () {
                    // checkbox already reflects the new state visually; nothing else to sync.
                }, function (message) {
                    $toggle.prop('checked', !$toggle.prop('checked'));
                    window.alert(message);
                });
            });

            $(document).on('click', '.tmr-delete-staff', function () {
                if (!TMRPanel.confirmDelete('<?php echo esc_js(__('এই স্টাফকে ডিলিট করবেন?', 'tailor-manager')); ?>')) {
                    return;
                }
                var id = $(this).data('id');
                TMRPanel.call('tmr_delete_staff', { id: id }, function () {
                    window.location.reload();
                });
            });

            $form.on('submit', function (e) {
                e.preventDefault();
                var data = $form.serializeArray();
                if (!$form.find('[name="status"]').is(':checked')) {
                    data.push({ name: 'status', value: 'draft' });
                }
                TMRPanel.call('tmr_save_staff', $.param(data), function () {
                    window.location.reload();
                });
            });

            var $loginModal = $('#tmr-staff-login-modal');
            var $loginForm = $('#tmr-staff-login-form');

            $(document).on('click', '.tmr-give-staff-login', function () {
                var id = $(this).data('id');
                var name = $(this).closest('.tmr-dress-card').find('.tmr-dress-card-name').text();
                $loginForm[0].reset();
                $('#tmr-staff-login-staff-id').val(id);
                $('#tmr-staff-login-name').text(name);
                TMRPanel.openModal($loginModal);
            });

            $('.tmr-password-toggle').on('click', function () {
                var $input = $('#' + $(this).data('target'));
                $input.attr('type', 'password' === $input.attr('type') ? 'text' : 'password');
            });

            $loginForm.on('submit', function (e) {
                e.preventDefault();
                TMRPanel.call('tmr_create_staff_login', $(this).serialize(), function () {
                    window.location.reload();
                });
            });

            $(document).on('click', '.tmr-remove-staff-login', function () {
                if (!TMRPanel.confirmDelete('<?php echo esc_js(__('এই স্টাফের লগইন অ্যাক্সেস সরাবেন? অ্যাকাউন্টটি ডিলিট হয়ে যাবে।', 'tailor-manager')); ?>')) {
                    return;
                }
                var id = $(this).data('id');
                TMRPanel.call('tmr_remove_staff_login', { staff_id: id }, function () {
                    window.location.reload();
                });
            });
        });
        </script>
        <?php
        TMR_Panel_Shell::footer();
    }

    private static function render_staff_card(WP_Post $staff)
    {
        $linked_user_id = (int) get_post_meta($staff->ID, self::LINKED_USER_META, true);
        $linked_user    = $linked_user_id ? get_userdata($linked_user_id) : false;
        ?>
        <div class="tmr-dress-card">
            <div class="tmr-dress-card-icon">
                <?php if (has_post_thumbnail($staff)) : ?>
                    <?php echo get_the_post_thumbnail($staff, array(40, 40), array('style' => 'object-fit:cover;')); ?>
                <?php else : ?>
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
                <?php endif; ?>
            </div>
            <div class="tmr-dress-card-name"><?php echo esc_html(get_the_title($staff)); ?></div>
            <?php if ($linked_user) : ?>
                <span class="tmr-staff-login-chip tmr-staff-login-chip-on" title="<?php echo esc_attr($linked_user->user_login); ?>"><?php esc_html_e('লগইন সক্রিয়', 'tailor-manager'); ?></span>
            <?php else : ?>
                <span class="tmr-staff-login-chip tmr-staff-login-chip-off"><?php esc_html_e('লগইন নেই', 'tailor-manager'); ?></span>
            <?php endif; ?>
            <div class="tmr-dress-card-footer">
                <label class="tmr-toggle tmr-mini-toggle" title="<?php esc_attr_e('সক্রিয়/নিষ্ক্রিয়', 'tailor-manager'); ?>">
                    <input type="checkbox" class="tmr-status-toggle tmr-staff-card-toggle" data-id="<?php echo esc_attr($staff->ID); ?>" <?php checked('publish' === $staff->post_status); ?> />
                    <span class="tmr-toggle-slider"></span>
                </label>
                <div class="tmr-dress-card-actions">
                    <?php if ($linked_user) : ?>
                        <span class="tmr-action-btn tmr-action-btn-red tmr-remove-staff-login" data-id="<?php echo esc_attr($staff->ID); ?>" title="<?php esc_attr_e('লগইন সরান', 'tailor-manager'); ?>"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2"></rect><path d="M7 11V7a5 5 0 0 1 9.9-1"></path><line x1="4" y1="4" x2="20" y2="20"></line></svg></span>
                    <?php else : ?>
                        <span class="tmr-action-btn tmr-give-staff-login" data-id="<?php echo esc_attr($staff->ID); ?>" title="<?php esc_attr_e('লগইন অ্যাক্সেস দিন', 'tailor-manager'); ?>"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg></span>
                    <?php endif; ?>
                    <span class="tmr-action-btn tmr-edit-staff" data-id="<?php echo esc_attr($staff->ID); ?>" title="<?php esc_attr_e('এডিট', 'tailor-manager'); ?>"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg></span>
                    <span class="tmr-action-btn tmr-action-btn-red tmr-delete-staff" data-id="<?php echo esc_attr($staff->ID); ?>" title="<?php esc_attr_e('ডিলিট', 'tailor-manager'); ?>"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg></span>
                </div>
            </div>
        </div>
        <?php
    }

    public function ajax_toggle_status()
    {
        check_ajax_referer('tmr_panel_nonce', 'nonce');
        if (!current_user_can(TMR_Panel_Shell::CAPABILITY)) {
            wp_send_json_error(array('message' => __('অনুমতি নেই।', 'tailor-manager')));
        }

        $id   = isset($_POST['id']) ? (int) $_POST['id'] : 0;
        $post = get_post($id);

        if (!$post || self::POST_TYPE !== $post->post_type) {
            wp_send_json_error(array('message' => __('স্টাফ পাওয়া যায়নি।', 'tailor-manager')));
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
            wp_send_json_error(array('message' => __('স্টাফ পাওয়া যায়নি।', 'tailor-manager')));
        }

        $image_id = get_post_thumbnail_id($post);

        wp_send_json_success(array(
            'id'        => $post->ID,
            'name'      => $post->post_title,
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

        $id       = isset($_POST['id']) ? (int) $_POST['id'] : 0;
        $name     = isset($_POST['name']) ? sanitize_text_field(wp_unslash($_POST['name'])) : '';
        $image_id = isset($_POST['image_id']) ? (int) $_POST['image_id'] : 0;
        $status   = !empty($_POST['status']) && 'publish' === $_POST['status'] ? 'publish' : 'draft';

        if ('' === $name) {
            wp_send_json_error(array('message' => __('নাম আবশ্যক।', 'tailor-manager')));
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

        if ($image_id > 0) {
            set_post_thumbnail($result, $image_id);
        } else {
            delete_post_thumbnail($result);
        }

        // Keep a linked login account's display_name matched to this record's own
        // name — TMR_My_Orders_Panel finds a staff member's orders by comparing
        // the order's plain-text cutter/tailor field against display_name, so a
        // rename here that didn't propagate would silently break their queue.
        $linked_user_id = (int) get_post_meta($result, self::LINKED_USER_META, true);
        if ($linked_user_id) {
            wp_update_user(array('ID' => $linked_user_id, 'display_name' => $name));
        }

        wp_send_json_success(array('id' => $result));
    }

    public function ajax_create_login()
    {
        check_ajax_referer('tmr_panel_nonce', 'nonce');
        if (!current_user_can(TMR_Panel_Shell::CAPABILITY)) {
            wp_send_json_error(array('message' => __('অনুমতি নেই।', 'tailor-manager')));
        }

        $staff_id = isset($_POST['staff_id']) ? (int) $_POST['staff_id'] : 0;
        $staff    = get_post($staff_id);
        if (!$staff || self::POST_TYPE !== $staff->post_type) {
            wp_send_json_error(array('message' => __('স্টাফ পাওয়া যায়নি।', 'tailor-manager')));
        }
        if (get_post_meta($staff_id, self::LINKED_USER_META, true)) {
            wp_send_json_error(array('message' => __('এই স্টাফের ইতিমধ্যে একটি লগইন অ্যাকাউন্ট আছে।', 'tailor-manager')));
        }

        $username = isset($_POST['username']) ? sanitize_user(wp_unslash($_POST['username']), true) : '';
        $email    = isset($_POST['email']) ? sanitize_email(wp_unslash($_POST['email'])) : '';
        $password = isset($_POST['password']) ? (string) $_POST['password'] : '';

        if ('' === $username || !validate_username($username)) {
            wp_send_json_error(array('message' => __('সঠিক ইউজারনেম দিন (শুধু অক্ষর, সংখ্যা, ., -, _)।', 'tailor-manager')));
        }
        if (username_exists($username)) {
            wp_send_json_error(array('message' => __('এই ইউজারনেম আগে থেকেই আছে।', 'tailor-manager')));
        }
        if (!is_email($email) || email_exists($email)) {
            wp_send_json_error(array('message' => __('সঠিক ও নতুন ইমেইল দিন — এই ইমেইল হয়তো আগে থেকেই ব্যবহৃত।', 'tailor-manager')));
        }
        if (strlen($password) < 8) {
            wp_send_json_error(array('message' => __('পাসওয়ার্ড কমপক্ষে ৮ অক্ষরের হতে হবে।', 'tailor-manager')));
        }

        $user_id = wp_insert_user(array(
            'user_login'   => $username,
            'user_email'   => $email,
            'user_pass'    => $password,
            'display_name' => $staff->post_title,
            'role'         => TMR_Staff_Role::ROLE,
        ));

        if (is_wp_error($user_id)) {
            wp_send_json_error(array('message' => $user_id->get_error_message()));
        }

        update_post_meta($staff_id, self::LINKED_USER_META, $user_id);
        update_user_meta($user_id, self::LINKED_STAFF_META, $staff_id);

        wp_send_json_success(array('user_id' => $user_id));
    }

    public function ajax_remove_login()
    {
        check_ajax_referer('tmr_panel_nonce', 'nonce');
        if (!current_user_can(TMR_Panel_Shell::CAPABILITY)) {
            wp_send_json_error(array('message' => __('অনুমতি নেই।', 'tailor-manager')));
        }

        $staff_id = isset($_POST['staff_id']) ? (int) $_POST['staff_id'] : 0;
        $user_id  = (int) get_post_meta($staff_id, self::LINKED_USER_META, true);

        if (!$user_id) {
            wp_send_json_error(array('message' => __('এই স্টাফের কোনো লগইন অ্যাকাউন্ট নেই।', 'tailor-manager')));
        }

        require_once ABSPATH . 'wp-admin/includes/user.php';
        wp_delete_user($user_id);
        delete_post_meta($staff_id, self::LINKED_USER_META);

        wp_send_json_success();
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
            wp_send_json_error(array('message' => __('স্টাফ পাওয়া যায়নি।', 'tailor-manager')));
        }

        wp_trash_post($id);
        wp_send_json_success();
    }
}
