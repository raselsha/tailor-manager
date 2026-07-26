<?php
defined('ABSPATH') || exit;

/**
 * Shop info, category management, and per-category measurement field labels — all inside
 * the panel shell, not wp-admin/options-general.php.
 */
class TMR_Settings_Page
{
    public function __construct()
    {
        add_action('wp_ajax_tmr_save_shop_info', array($this, 'ajax_save_shop_info'));
        add_action('wp_ajax_tmr_save_category', array($this, 'ajax_save_category'));
        add_action('wp_ajax_tmr_save_measurement_fields', array($this, 'ajax_save_measurement_fields'));
    }

    public static function render()
    {
        if (!current_user_can(TMR_Panel_Shell::CAPABILITY)) {
            wp_die(esc_html__('You do not have permission to access this page.', 'tailor-manager'));
        }

        $shop_name    = get_option('tmr_shop_name', get_bloginfo('name'));
        $shop_address = get_option('tmr_shop_address', '');
        $shop_phone   = get_option('tmr_shop_phone', '');
        $categories   = TMR_Category_Taxonomy::get_terms();

        TMR_Panel_Shell::header('settings', __('Settings', 'tailor-manager'));
        ?>
        <div class="tmr-card">
            <div class="tmr-card__title"><?php esc_html_e('Shop Information', 'tailor-manager'); ?></div>
            <form id="tmr-shop-info-form">
                <div class="tmr-form-row"><label><?php esc_html_e('Shop Name', 'tailor-manager'); ?></label><input type="text" name="shop_name" value="<?php echo esc_attr($shop_name); ?>" /></div>
                <div class="tmr-form-row"><label><?php esc_html_e('Address', 'tailor-manager'); ?></label><textarea name="shop_address" rows="2"><?php echo esc_textarea($shop_address); ?></textarea></div>
                <div class="tmr-form-row"><label><?php esc_html_e('Phone', 'tailor-manager'); ?></label><input type="text" name="shop_phone" value="<?php echo esc_attr($shop_phone); ?>" /></div>
                <button type="submit" class="tmr-btn tmr-btn--primary"><?php esc_html_e('Save', 'tailor-manager'); ?></button>
            </form>
        </div>

        <div class="tmr-card">
            <div class="tmr-card__title"><?php esc_html_e('Categories', 'tailor-manager'); ?></div>
            <table class="tmr-table">
                <thead><tr><th><?php esc_html_e('Name', 'tailor-manager'); ?></th><th></th></tr></thead>
                <tbody>
                    <?php foreach ($categories as $term) : ?>
                        <tr>
                            <td><input type="text" class="tmr-category-name" data-id="<?php echo esc_attr($term->term_id); ?>" value="<?php echo esc_attr($term->name); ?>" /></td>
                            <td><button type="button" class="tmr-btn tmr-btn--sm tmr-save-category" data-id="<?php echo esc_attr($term->term_id); ?>"><?php esc_html_e('Save', 'tailor-manager'); ?></button></td>
                        </tr>
                    <?php endforeach; ?>
                    <tr>
                        <td><input type="text" id="tmr-new-category-name" placeholder="<?php esc_attr_e('New category name…', 'tailor-manager'); ?>" /></td>
                        <td><button type="button" class="tmr-btn tmr-btn--sm tmr-btn--primary" id="tmr-add-category"><?php esc_html_e('Add', 'tailor-manager'); ?></button></td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="tmr-card">
            <div class="tmr-card__title"><?php esc_html_e('Measurement Fields', 'tailor-manager'); ?></div>
            <p class="tmr-empty" style="text-align:left;padding:0 0 10px;"><?php esc_html_e('One field label per line, in display order.', 'tailor-manager'); ?></p>
            <?php foreach ($categories as $term) :
                $fields = TMR_Measurement_Fields::get_for_category($term->slug);
            ?>
                <div class="tmr-form-row">
                    <label><?php echo esc_html($term->name); ?></label>
                    <textarea class="tmr-measurement-fields" data-slug="<?php echo esc_attr($term->slug); ?>" rows="5" style="max-width:300px;"><?php echo esc_textarea(implode("\n", $fields)); ?></textarea>
                    <br />
                    <button type="button" class="tmr-btn tmr-btn--sm tmr-save-measurement-fields" data-slug="<?php echo esc_attr($term->slug); ?>" style="margin-top:6px;"><?php esc_html_e('Save', 'tailor-manager'); ?></button>
                </div>
            <?php endforeach; ?>
        </div>

        <script>
        jQuery(function ($) {
            $('#tmr-shop-info-form').on('submit', function (e) {
                e.preventDefault();
                TMRPanel.call('tmr_save_shop_info', $(this).serialize(), function () {
                    window.alert('<?php echo esc_js(__('Saved.', 'tailor-manager')); ?>');
                });
            });

            $('#tmr-add-category').on('click', function () {
                var name = $('#tmr-new-category-name').val();
                if (!name) { return; }
                TMRPanel.call('tmr_save_category', { id: 0, name: name }, function () {
                    window.location.reload();
                });
            });

            $('.tmr-save-category').on('click', function () {
                var id = $(this).data('id');
                var name = $('.tmr-category-name[data-id="' + id + '"]').val();
                TMRPanel.call('tmr_save_category', { id: id, name: name }, function () {
                    window.location.reload();
                });
            });

            $('.tmr-save-measurement-fields').on('click', function () {
                var slug = $(this).data('slug');
                var lines = $('.tmr-measurement-fields[data-slug="' + slug + '"]').val();
                TMRPanel.call('tmr_save_measurement_fields', { slug: slug, lines: lines }, function () {
                    window.alert('<?php echo esc_js(__('Saved.', 'tailor-manager')); ?>');
                });
            });
        });
        </script>
        <?php
        TMR_Panel_Shell::footer();
    }

    public function ajax_save_shop_info()
    {
        check_ajax_referer('tmr_panel_nonce', 'nonce');
        if (!current_user_can(TMR_Panel_Shell::CAPABILITY)) {
            wp_send_json_error(array('message' => __('Permission denied.', 'tailor-manager')));
        }

        update_option('tmr_shop_name', isset($_POST['shop_name']) ? sanitize_text_field(wp_unslash($_POST['shop_name'])) : '');
        update_option('tmr_shop_address', isset($_POST['shop_address']) ? sanitize_textarea_field(wp_unslash($_POST['shop_address'])) : '');
        update_option('tmr_shop_phone', isset($_POST['shop_phone']) ? sanitize_text_field(wp_unslash($_POST['shop_phone'])) : '');

        wp_send_json_success();
    }

    public function ajax_save_category()
    {
        check_ajax_referer('tmr_panel_nonce', 'nonce');
        if (!current_user_can(TMR_Panel_Shell::CAPABILITY)) {
            wp_send_json_error(array('message' => __('Permission denied.', 'tailor-manager')));
        }

        $id   = isset($_POST['id']) ? (int) $_POST['id'] : 0;
        $name = isset($_POST['name']) ? sanitize_text_field(wp_unslash($_POST['name'])) : '';

        if ('' === $name) {
            wp_send_json_error(array('message' => __('Name is required.', 'tailor-manager')));
        }

        if ($id > 0) {
            wp_update_term($id, TMR_Category_Taxonomy::TAXONOMY, array('name' => $name));
        } else {
            wp_insert_term($name, TMR_Category_Taxonomy::TAXONOMY);
        }

        wp_send_json_success();
    }

    public function ajax_save_measurement_fields()
    {
        check_ajax_referer('tmr_panel_nonce', 'nonce');
        if (!current_user_can(TMR_Panel_Shell::CAPABILITY)) {
            wp_send_json_error(array('message' => __('Permission denied.', 'tailor-manager')));
        }

        $slug  = isset($_POST['slug']) ? sanitize_title(wp_unslash($_POST['slug'])) : '';
        $lines = isset($_POST['lines']) ? explode("\n", wp_unslash($_POST['lines'])) : array();

        if ('' === $slug) {
            wp_send_json_error(array('message' => __('Invalid category.', 'tailor-manager')));
        }

        $fields = array();
        foreach ($lines as $line) {
            $label = trim(sanitize_text_field($line));
            if ('' !== $label) {
                $fields[sanitize_key($label)] = $label;
            }
        }

        TMR_Measurement_Fields::save_for_category($slug, $fields);
        wp_send_json_success();
    }
}
