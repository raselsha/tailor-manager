<?php
defined('ABSPATH') || exit;

/**
 * The core order-entry screen. One order = customer + dates + pricing + one item per
 * Category actually used (each item: dresses+qty checked in that category, one shared
 * measurement set, one shared design/part selection set) — see class-tmr-order-item-post-type.php.
 */
class TMR_Orders_Panel
{
    const POST_TYPE = TMR_Order_Post_Type::POST_TYPE;
    const PER_PAGE = 20;

    public function __construct()
    {
        add_action('wp_ajax_tmr_save_order', array($this, 'ajax_save'));
        add_action('wp_ajax_tmr_delete_order', array($this, 'ajax_delete'));
        add_action('wp_ajax_tmr_search_customers', array($this, 'ajax_search_customers'));
        add_action('wp_ajax_tmr_quick_add_customer', array($this, 'ajax_quick_add_customer'));
        add_action('wp_ajax_tmr_get_order_form', array($this, 'ajax_get_order_form'));
    }

    public static function render()
    {
        if (!current_user_can(TMR_Panel_Shell::CAPABILITY)) {
            wp_die(esc_html__('এই পেজ দেখার অনুমতি আপনার নেই।', 'tailor-manager'));
        }

        $action = isset($_GET['action']) ? sanitize_key($_GET['action']) : 'list';
        $id     = isset($_GET['id']) ? (int) $_GET['id'] : 0;

        if ('view' === $action && $id) {
            self::render_view($id);
            return;
        }

        // Add/Edit now open as a modal on the list page itself (see
        // #tmr-order-modal + ajax_get_order_form()) rather than a separate page —
        // an old ?action=edit&id= link (e.g. from the View screen's Edit button)
        // still lands on the list with that order's modal auto-opened.
        self::render_list('edit' === $action ? $id : 0);
    }

    /* ---------------------------------------------------------------- */
    /* List                                                              */
    /* ---------------------------------------------------------------- */

    private static function render_list($auto_open_id = 0)
    {
        $search = isset($_GET['s']) ? sanitize_text_field(wp_unslash($_GET['s'])) : '';
        $status = isset($_GET['status']) ? sanitize_key($_GET['status']) : 'all';
        $paged  = isset($_GET['paged']) ? max(1, (int) $_GET['paged']) : 1;

        $meta_query = array();
        if ('pending' === $status) {
            $meta_query[] = array('relation' => 'OR', array('key' => '_tmr_delivered', 'compare' => 'NOT EXISTS'), array('key' => '_tmr_delivered', 'value' => '1', 'compare' => '!='));
            $meta_query[] = array('relation' => 'OR', array('key' => '_tmr_ready', 'compare' => 'NOT EXISTS'), array('key' => '_tmr_ready', 'value' => '1', 'compare' => '!='));
        } elseif ('ready' === $status) {
            $meta_query[] = array('key' => '_tmr_ready', 'value' => '1');
            $meta_query[] = array('relation' => 'OR', array('key' => '_tmr_delivered', 'compare' => 'NOT EXISTS'), array('key' => '_tmr_delivered', 'value' => '1', 'compare' => '!='));
        } elseif ('delivered' === $status) {
            $meta_query[] = array('key' => '_tmr_delivered', 'value' => '1');
        } elseif ('cancelled' === $status) {
            $meta_query[] = array('key' => '_tmr_cancelled', 'value' => '1');
        }

        $args = array(
            'post_type'      => self::POST_TYPE,
            'post_status'    => 'any',
            'posts_per_page' => self::PER_PAGE,
            'paged'          => $paged,
            'orderby'        => 'ID',
            'order'          => 'DESC',
        );

        if ($search) {
            $customer_ids = get_posts(array(
                'post_type'      => TMR_Customer_Post_Type::POST_TYPE,
                'post_status'    => array('publish', 'draft'),
                's'              => $search,
                'posts_per_page' => -1,
                'fields'         => 'ids',
            ));
            $args['meta_query'][] = array('key' => '_tmr_customer_id', 'value' => empty($customer_ids) ? array(0) : $customer_ids, 'compare' => 'IN');
        }

        if ($meta_query) {
            $args['meta_query'] = array_merge(isset($args['meta_query']) ? $args['meta_query'] : array(), $meta_query);
            $args['meta_query']['relation'] = 'AND';
        }

        $query = new WP_Query($args);

        $header_right = '<button type="button" class="tmr-btn-add" id="tmr-open-order-modal">' . esc_html__('+ অর্ডার নিন', 'tailor-manager') . '</button>';
        TMR_Panel_Shell::header('orders', __('অর্ডার ম্যানেজার', 'tailor-manager'), __('সকল কাস্টমার অর্ডার।', 'tailor-manager'), $header_right);
        ?>
        <div class="tmr-filters-bar">
            <div class="tmr-tabs">
                <?php
                $tabs = array('all' => __('সব', 'tailor-manager'), 'pending' => __('পেন্ডিং', 'tailor-manager'), 'ready' => __('রেডি', 'tailor-manager'), 'delivered' => __('ডেলিভারড', 'tailor-manager'), 'cancelled' => __('বাতিলকৃত', 'tailor-manager'));
                foreach ($tabs as $key => $label) :
                    $url = esc_url(add_query_arg(array('page' => 'tmr-orders', 'status' => $key), admin_url('admin.php')));
                    ?>
                    <a href="<?php echo $url; ?>" class="<?php echo $status === $key ? 'is-active' : ''; ?>"><?php echo esc_html($label); ?></a>
                <?php endforeach; ?>
            </div>
            <div class="tmr-filters-spacer"></div>
            <form method="get" style="display:flex;">
                <input type="hidden" name="page" value="tmr-orders" />
                <input type="hidden" name="status" value="<?php echo esc_attr($status); ?>" />
                <input type="text" name="s" class="tmr-filters-search" value="<?php echo esc_attr($search); ?>" placeholder="<?php esc_attr_e('কাস্টমারের নাম বা ফোন খুঁজুন…', 'tailor-manager'); ?>" />
            </form>
        </div>

        <div class="tmr-card">
            <table class="tmr-table">
                <thead>
                    <tr>
                        <th><?php esc_html_e('কাস্টমার', 'tailor-manager'); ?></th>
                        <th><?php esc_html_e('ড্রেস ও পরিমাণ', 'tailor-manager'); ?></th>
                        <th><?php esc_html_e('ডেলিভারি তারিখ', 'tailor-manager'); ?></th>
                        <th><?php esc_html_e('ডেলিভারি স্ট্যাটাস', 'tailor-manager'); ?></th>
                        <th><?php esc_html_e('অর্ডার আইডি', 'tailor-manager'); ?></th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$query->have_posts()) : ?>
                        <tr><td colspan="6" class="tmr-empty"><?php esc_html_e('কোনো অর্ডার পাওয়া যায়নি।', 'tailor-manager'); ?></td></tr>
                    <?php else : ?>
                        <?php foreach ($query->posts as $order) :
                            $customer_id = (int) get_post_meta($order->ID, '_tmr_customer_id', true);
                            $phone       = $customer_id ? TMR_Customer_Post_Type::get_phone($customer_id) : '';
                            $name        = $customer_id ? get_the_title($customer_id) : __('ওয়াক-ইন', 'tailor-manager');
                            $status_key  = TMR_Order_Post_Type::status_label($order->ID);
                        ?>
                            <tr>
                                <td><a href="<?php echo esc_url(admin_url('admin.php?page=tmr-orders&action=view&id=' . $order->ID)); ?>" style="color:#1e293b;font-weight:600;text-decoration:none;"><?php echo esc_html($name . ($phone ? ' (' . $phone . ')' : '')); ?></a></td>
                                <td><?php echo esc_html(self::dress_summary($order->ID)); ?></td>
                                <td><?php echo esc_html(get_post_meta($order->ID, '_tmr_delivery_date', true)); ?></td>
                                <td><span class="tmr-badge tmr-badge-<?php echo esc_attr($status_key); ?>"><?php echo esc_html(ucfirst($status_key)); ?></span></td>
                                <td>#<?php echo esc_html($order->ID); ?></td>
                                <td>
                                    <div class="tmr-actions">
                                        <a class="tmr-icon-btn" href="<?php echo esc_url(admin_url('admin.php?page=tmr-orders&action=view&id=' . $order->ID)); ?>" title="<?php esc_attr_e('দেখুন', 'tailor-manager'); ?>"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg></a>
                                        <button type="button" class="tmr-icon-btn tmr-open-order-modal-edit" data-id="<?php echo esc_attr($order->ID); ?>" title="<?php esc_attr_e('এডিট', 'tailor-manager'); ?>"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg></button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php TMR_Customers_Panel::render_pagination($query->max_num_pages, $paged); ?>

        <div class="tmr-modal" id="tmr-order-modal">
            <div class="tmr-modal-content tmr-modal-content-lg">
                <div class="tmr-modal-head">
                    <h2 id="tmr-order-modal-title"><?php esc_html_e('অর্ডার নিন', 'tailor-manager'); ?></h2>
                    <button type="button" class="tmr-modal-close">&times;</button>
                </div>
                <div class="tmr-modal-body" id="tmr-order-modal-body">
                    <div class="tmr-empty"><?php esc_html_e('লোড হচ্ছে…', 'tailor-manager'); ?></div>
                </div>
            </div>
        </div>

        <?php self::render_order_form_script($auto_open_id); ?>
        <?php
        TMR_Panel_Shell::footer();
    }

    private static function dress_summary($order_id)
    {
        $parts = array();
        foreach (TMR_Order_Post_Type::get_items($order_id) as $item) {
            foreach (TMR_Order_Item_Post_Type::get_dresses($item->ID) as $d) {
                $dress = get_post($d['dress_id']);
                if ($dress) {
                    $parts[] = $dress->post_title . '(' . (int) $d['quantity'] . ')';
                }
            }
        }
        return implode(' ', $parts);
    }

    /**
     * "26 জুলাই, 2026" — day number kept in plain digits (matches the rest of the app,
     * e.g. the dashboard's own date fields), only the month name is Bangla.
     */
    private static function format_date_bn($ymd)
    {
        if (!$ymd) {
            return '';
        }
        $months = array(
            1 => 'জানুয়ারি', 2 => 'ফেব্রুয়ারি', 3 => 'মার্চ', 4 => 'এপ্রিল',
            5 => 'মে', 6 => 'জুন', 7 => 'জুলাই', 8 => 'আগস্ট',
            9 => 'সেপ্টেম্বর', 10 => 'অক্টোবর', 11 => 'নভেম্বর', 12 => 'ডিসেম্বর',
        );
        $parts = explode('-', $ymd);
        if (count($parts) !== 3) {
            return $ymd;
        }
        list($y, $m, $d) = $parts;
        $m = (int) $m;
        if (!isset($months[$m])) {
            return $ymd;
        }
        return ((int) $d) . ' ' . $months[$m] . ', ' . $y;
    }

    /* ---------------------------------------------------------------- */
    /* Add / Edit form — rendered inside a modal on the list page, its HTML       */
    /* fetched via ajax_get_order_form() rather than a separate admin page.      */
    /* ---------------------------------------------------------------- */

    private static function render_form_body($order_id)
    {
        $is_edit  = $order_id > 0;
        $order_date = $is_edit ? get_post_meta($order_id, '_tmr_order_date', true) : current_time('Y-m-d');
        $delivery_date = $is_edit ? get_post_meta($order_id, '_tmr_delivery_date', true) : '';

        // A brand-new order has no delivery date yet — pre-fill it from Settings'
        // configurable lead time so the calendar opens with a sensible default already
        // selected instead of forcing every order to be picked manually from scratch.
        if (!$is_edit && '' === $delivery_date) {
            $default_days = (int) get_option('tmr_default_delivery_days', 7);
            $delivery_date = date('Y-m-d', strtotime('+' . $default_days . ' days', current_time('timestamp')));
        }

        $urgent = $is_edit && '1' === get_post_meta($order_id, '_tmr_urgent', true);
        $customer_id = $is_edit ? (int) get_post_meta($order_id, '_tmr_customer_id', true) : 0;
        $customer_label = $customer_id ? get_the_title($customer_id) . ' (' . TMR_Customer_Post_Type::get_phone($customer_id) . ')' : '';
        $wage = $is_edit ? get_post_meta($order_id, '_tmr_wage', true) : '';
        $cloth_price = $is_edit ? get_post_meta($order_id, '_tmr_cloth_price', true) : '';
        $total = $is_edit ? get_post_meta($order_id, '_tmr_total', true) : '';
        $advance = $is_edit ? get_post_meta($order_id, '_tmr_advance', true) : '';
        $due = $is_edit ? get_post_meta($order_id, '_tmr_due', true) : '';
        $image_id = $is_edit ? (int) get_post_meta($order_id, '_tmr_reference_image_id', true) : 0;

        $existing_items = array();
        if ($is_edit) {
            foreach (TMR_Order_Post_Type::get_items($order_id) as $item) {
                $existing_items[TMR_Order_Item_Post_Type::get_category_id($item->ID)] = $item;
            }
        }

        ?>
        <form id="tmr-order-form">
            <input type="hidden" name="order_id" value="<?php echo esc_attr($order_id); ?>" />
            <input type="hidden" name="customer_id" id="tmr-customer-id" value="<?php echo esc_attr($customer_id); ?>" />
            <input type="hidden" name="image_id" id="tmr-order-image-id" value="<?php echo esc_attr($image_id); ?>" />

            <input type="hidden" name="order_date" value="<?php echo esc_attr($order_date); ?>" />

            <div class="tmr-card-plain tmr-highlight-card">
                <div class="tmr-step-header tmr-highlight-header">
                    <h3><?php esc_html_e('কাস্টমার ও ডেলিভারি', 'tailor-manager'); ?></h3>
                </div>

                <div class="tmr-form-row tmr-inline-customer-row">
                    <div class="tmr-inline-field">
                        <label class="tmr-form-label" for="tmr-customer-search"><?php esc_html_e('কাস্টমার', 'tailor-manager'); ?> *</label>
                        <div class="tmr-customer-input-wrap">
                            <input type="text" id="tmr-customer-search" autocomplete="off" placeholder="<?php esc_attr_e('নাম বা মোবাইল নাম্বার লিখুন…', 'tailor-manager'); ?>" value="<?php echo esc_attr($customer_label); ?>" />
                            <button type="button" class="tmr-input-inline-btn" id="tmr-quick-add-customer" title="<?php esc_attr_e('নতুন কাস্টমার যোগ করুন', 'tailor-manager'); ?>">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
                            </button>
                            <div id="tmr-customer-results" style="position:absolute;left:0;right:0;border:1px solid #e2e8f0;border-radius:10px;margin-top:4px;display:none;background:#fff;box-shadow:0 10px 30px rgba(15,23,42,.12);z-index:20;"></div>
                        </div>
                    </div>

                    <div class="tmr-inline-field" style="position:relative;">
                        <label class="tmr-form-label" for="tmr-delivery-date-display"><?php esc_html_e('ডেলিভারি তারিখ', 'tailor-manager'); ?> *</label>
                        <input type="text" id="tmr-delivery-date-display" class="tmr-date-display-input" readonly autocomplete="off" placeholder="<?php esc_attr_e('তারিখ নির্বাচন করুন', 'tailor-manager'); ?>" value="<?php echo esc_attr(self::format_date_bn($delivery_date)); ?>" />
                        <input type="hidden" name="delivery_date" id="tmr-delivery-date" value="<?php echo esc_attr($delivery_date); ?>" required />
                        <div class="tmr-cal-popover" id="tmr-delivery-cal-popover">
                            <div id="tmr-delivery-cal"></div>
                        </div>
                    </div>

                    <div class="tmr-inline-field tmr-inline-toggle-field">
                        <label class="tmr-toggle">
                            <input type="checkbox" name="urgent" value="1" <?php checked($urgent); ?> />
                            <span class="tmr-toggle-slider"></span>
                            <span class="tmr-form-label" style="margin:0;"><?php esc_html_e('জরুরি ডেলিভারি', 'tailor-manager'); ?></span>
                        </label>
                    </div>
                </div>
            </div>

            <?php foreach (TMR_Category_Taxonomy::get_terms() as $term) :
                self::render_category_block($term, isset($existing_items[$term->term_id]) ? $existing_items[$term->term_id] : null);
            endforeach; ?>

            <div class="tmr-card-plain">
                <div class="tmr-step-header">
                    <h3><?php esc_html_e('মূল্য', 'tailor-manager'); ?></h3>
                </div>
                <div class="tmr-form-row tmr-form-row-duo">
                    <div><label class="tmr-form-label" for="tmr-wage"><?php esc_html_e('মজুরি', 'tailor-manager'); ?> *</label><div class="tmr-money-field"><input type="number" step="0.01" name="wage" id="tmr-wage" value="<?php echo esc_attr($wage); ?>" required /></div></div>
                    <div><label class="tmr-form-label" for="tmr-cloth-price"><?php esc_html_e('কাপড়ের দাম', 'tailor-manager'); ?></label><div class="tmr-money-field"><input type="number" step="0.01" name="cloth_price" id="tmr-cloth-price" value="<?php echo esc_attr($cloth_price); ?>" /></div></div>
                </div>
                <div class="tmr-form-row tmr-form-row-duo">
                    <div><label class="tmr-form-label" for="tmr-total"><?php esc_html_e('মোট', 'tailor-manager'); ?> *</label><div class="tmr-money-field"><input type="number" step="0.01" name="total" id="tmr-total" value="<?php echo esc_attr($total); ?>" required /></div></div>
                    <div><label class="tmr-form-label" for="tmr-advance"><?php esc_html_e('অগ্রিম', 'tailor-manager'); ?></label><div class="tmr-money-field"><input type="number" step="0.01" name="advance" id="tmr-advance" value="<?php echo esc_attr($advance); ?>" /></div></div>
                </div>
                <div class="tmr-form-row">
                    <label class="tmr-form-label" for="tmr-due"><?php esc_html_e('বাকি', 'tailor-manager'); ?></label>
                    <div class="tmr-money-field" style="max-width:200px;"><input type="number" step="0.01" name="due" id="tmr-due" value="<?php echo esc_attr($due); ?>" /></div>
                </div>
                <div class="tmr-form-row">
                    <label class="tmr-form-label"><?php esc_html_e('রেফারেন্স ছবি', 'tailor-manager'); ?></label>
                    <div class="tmr-photo-picker">
                        <div class="tmr-photo-preview">
                            <img id="tmr-order-image-preview" src="<?php echo $image_id ? esc_url(wp_get_attachment_image_url($image_id, 'thumbnail')) : ''; ?>" style="width:100%;height:100%;object-fit:cover;<?php echo $image_id ? '' : 'display:none;'; ?>" />
                            <svg id="tmr-order-image-placeholder" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" <?php echo $image_id ? 'style="display:none;"' : ''; ?>><rect x="3" y="3" width="18" height="18" rx="2"></rect><circle cx="8.5" cy="8.5" r="1.5"></circle><path d="M21 15l-5-5L5 21"></path></svg>
                        </div>
                        <button type="button" class="tmr-btn-outline tmr-btn-sm" id="tmr-pick-order-image"><?php esc_html_e('ছবি নির্বাচন করুন', 'tailor-manager'); ?></button>
                    </div>
                </div>
            </div>

            <div class="tmr-form-actions">
                <button type="submit" class="tmr-btn-add"><?php esc_html_e('অর্ডার সেভ করুন', 'tailor-manager'); ?></button>
                <button type="button" class="tmr-btn-cancel tmr-modal-close"><?php esc_html_e('বাতিল', 'tailor-manager'); ?></button>
            </div>
        </form>

        <div class="tmr-modal" id="tmr-quick-customer-modal">
            <div class="tmr-modal-content">
                <div class="tmr-modal-head">
                    <h2><?php esc_html_e('নতুন কাস্টমার যোগ করুন', 'tailor-manager'); ?></h2>
                    <button type="button" class="tmr-modal-close">&times;</button>
                </div>
                <form id="tmr-quick-customer-form">
                    <div class="tmr-modal-body">
                        <div class="tmr-form-row"><label class="tmr-form-label"><?php esc_html_e('নাম', 'tailor-manager'); ?> *</label><input type="text" name="name" required /></div>
                        <div class="tmr-form-row"><label class="tmr-form-label"><?php esc_html_e('ফোন', 'tailor-manager'); ?> *</label><input type="text" name="phone" required /></div>
                        <div class="tmr-form-row"><label class="tmr-form-label"><?php esc_html_e('ঠিকানা', 'tailor-manager'); ?></label><textarea name="address" rows="2"></textarea></div>
                    </div>
                    <div class="tmr-modal-foot">
                        <button type="submit" class="tmr-btn-add"><?php esc_html_e('কাস্টমার সেভ করুন', 'tailor-manager'); ?></button>
                    </div>
                </form>
            </div>
        </div>
        <?php
    }

    private static function render_category_block(WP_Term $term, $existing_item)
    {
        $dresses = TMR_Dress_Post_Type::get_by_category($term->slug);
        $parts   = TMR_Dress_Part_Post_Type::get_by_category($term->slug);
        $fields  = TMR_Measurement_Fields::get_for_category($term->slug);

        $selected_dresses = array();
        $cutter_name = '';
        $measurements = array();
        $part_selections = array();

        if ($existing_item) {
            foreach (TMR_Order_Item_Post_Type::get_dresses($existing_item->ID) as $d) {
                $selected_dresses[$d['dress_id']] = $d;
            }
            $measurements = TMR_Order_Item_Post_Type::get_measurements($existing_item->ID);
            foreach (TMR_Order_Item_Post_Type::get_part_selections($existing_item->ID) as $sel) {
                $part_selections[$sel['part_id']] = $sel;
            }
            $cutter_name = get_post_meta($existing_item->ID, '_tmr_cutter_name', true);
        }
        ?>
        <?php $has_data = !empty($selected_dresses); ?>
        <div class="tmr-card-plain tmr-category-block tmr-highlight-card" data-category-id="<?php echo esc_attr($term->term_id); ?>" data-category-slug="<?php echo esc_attr($term->slug); ?>">
            <div class="tmr-step-header tmr-category-block-header tmr-highlight-header">
                <h3><?php echo esc_html($term->name); ?></h3>
                <svg class="tmr-category-chevron<?php echo $has_data ? ' is-open' : ''; ?>" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 9l6 6 6-6"></path></svg>
            </div>

            <div class="tmr-form-row tmr-dress-block">
                <div class="tmr-part-block-title">
                    <span class="tmr-part-block-icon"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M20.59 13.41L13.42 20.58a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"></path><line x1="7" y1="7" x2="7.01" y2="7"></line></svg></span>
                    <?php esc_html_e('ড্রেস ও পরিমাণ (ক্লিক করে নির্বাচন করুন)', 'tailor-manager'); ?>
                </div>
                <div class="tmr-checkbox-grid tmr-dress-checkbox-grid">
                    <?php foreach ($dresses as $dress) :
                        $checked = isset($selected_dresses[$dress->ID]);
                        $qty = $checked ? (int) $selected_dresses[$dress->ID]['quantity'] : 0;
                    ?>
                        <label>
                            <input type="checkbox" class="tmr-dress-check" data-dress-id="<?php echo esc_attr($dress->ID); ?>" <?php checked($checked); ?> />
                            <?php if (has_post_thumbnail($dress)) : ?>
                                <?php echo get_the_post_thumbnail($dress, array(20, 20), array('style' => 'border-radius:5px;object-fit:contain;flex-shrink:0;')); ?>
                            <?php endif; ?>
                            <?php echo esc_html($dress->post_title); ?>
                            <span class="tmr-qty-stepper">
                                <button type="button" class="tmr-qty-btn tmr-qty-minus" tabindex="-1" aria-label="<?php esc_attr_e('কমান', 'tailor-manager'); ?>">&minus;</button>
                                <input type="number" min="0" class="tmr-dress-qty" data-dress-id="<?php echo esc_attr($dress->ID); ?>" value="<?php echo esc_attr($checked ? ($qty ?: 1) : 0); ?>" <?php echo $checked ? '' : 'disabled'; ?> />
                                <button type="button" class="tmr-qty-btn tmr-qty-plus" tabindex="-1" aria-label="<?php esc_attr_e('বাড়ান', 'tailor-manager'); ?>">+</button>
                            </span>
                        </label>
                    <?php endforeach; ?>
                    <?php if (empty($dresses)) : ?>
                        <span class="tmr-empty"><?php esc_html_e('এই ক্যাটাগরিতে এখনো কোনো ড্রেস যোগ করা হয়নি।', 'tailor-manager'); ?></span>
                    <?php endif; ?>
                </div>
            </div>

            <div class="tmr-category-collapsible" style="<?php echo $has_data ? '' : 'display:none;'; ?>">
                <div class="tmr-form-row">
                    <label class="tmr-form-label" for="tmr-cutter-<?php echo esc_attr($term->term_id); ?>"><?php esc_html_e('কাটার / টেইলারের নাম', 'tailor-manager'); ?></label>
                    <input type="text" id="tmr-cutter-<?php echo esc_attr($term->term_id); ?>" class="tmr-cutter-name" value="<?php echo esc_attr($cutter_name); ?>" style="max-width:280px;" />
                </div>

                <?php if ($fields) : ?>
                    <div class="tmr-form-row tmr-part-block">
                        <div class="tmr-part-block-title">
                            <span class="tmr-part-block-icon"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M21.3 15.3a2.4 2.4 0 0 1 0 3.4l-2.6 2.6a2.4 2.4 0 0 1-3.4 0L2.7 8.7a2.4 2.4 0 0 1 0-3.4l2.6-2.6a2.4 2.4 0 0 1 3.4 0z"></path><path d="M14.5 6.5l3 3"></path><path d="M11.5 9.5l1.5 1.5"></path><path d="M8.5 12.5l1.5 1.5"></path></svg></span>
                            <?php esc_html_e('মাপের বিবরণ', 'tailor-manager'); ?>
                        </div>
                        <div class="tmr-measure-grid">
                            <?php foreach ($fields as $slug => $label) : ?>
                                <div class="tmr-measure-field-wrap">
                                    <label class="tmr-form-label" style="font-weight:400;text-transform:none;letter-spacing:0;"><?php echo esc_html($label); ?></label>
                                    <span class="tmr-qty-stepper tmr-measure-stepper">
                                        <button type="button" class="tmr-qty-btn tmr-measure-minus" tabindex="-1" aria-label="<?php esc_attr_e('কমান', 'tailor-manager'); ?>">&minus;</button>
                                        <input type="text" class="tmr-measure-field" data-slug="<?php echo esc_attr($slug); ?>" placeholder="0" value="<?php echo esc_attr(isset($measurements[$slug]) ? $measurements[$slug] : ''); ?>" />
                                        <button type="button" class="tmr-qty-btn tmr-measure-plus" tabindex="-1" aria-label="<?php esc_attr_e('বাড়ান', 'tailor-manager'); ?>">+</button>
                                    </span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <?php foreach ($parts as $part) :
                    $designs = TMR_Design_Type_Post_Type::get_by_part($part->ID);
                    if (empty($designs)) {
                        continue;
                    }
                    $sel = isset($part_selections[$part->ID]) ? $part_selections[$part->ID] : array('design_type_ids' => array(), 'part_measurement' => '', 'note' => '');
                    $selected_ids = array_map('intval', isset($sel['design_type_ids']) ? $sel['design_type_ids'] : array());
                    ?>
                    <div class="tmr-form-row tmr-part-block" data-part-id="<?php echo esc_attr($part->ID); ?>">
                        <div class="tmr-part-block-title">
                            <span class="tmr-part-block-icon"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M20.59 13.41L13.42 20.58a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"></path></svg></span>
                            <?php echo esc_html($part->post_title); ?>
                        </div>
                        <div class="tmr-checkbox-grid">
                            <?php foreach ($designs as $design) : ?>
                                <label>
                                    <input type="checkbox" class="tmr-design-check" value="<?php echo esc_attr($design->ID); ?>" <?php checked(in_array($design->ID, $selected_ids, true)); ?> />
                                    <?php if (has_post_thumbnail($design)) : ?>
                                        <?php echo get_the_post_thumbnail($design, array(24, 24)); ?>
                                    <?php endif; ?>
                                    <?php echo esc_html($design->post_title); ?>
                                </label>
                            <?php endforeach; ?>
                        </div>
                        <?php if (TMR_Dress_Part_Post_Type::measurement_enabled($part->ID)) : ?>
                            <input type="text" class="tmr-part-measurement" placeholder="<?php esc_attr_e('পরিমাণ', 'tailor-manager'); ?>" value="<?php echo esc_attr($sel['part_measurement']); ?>" style="max-width:200px;margin-top:6px;" />
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php
    }

    private static function render_order_form_script($auto_open_id = 0)
    {
        // Bound once on the list page via $(document).on(...) delegation — the
        // form/customer-search/etc. markup itself is injected into the modal body
        // later by ajax_get_order_form(), possibly more than once per page view
        // (open, cancel, open a different order to edit...). Delegated bindings on
        // `document` don't care when their targets appear/disappear, so re-injecting
        // the form HTML never re-registers (and so never duplicates) a handler —
        // only the injected markup itself may safely be replaced wholesale.
        ?>
        <script>
        jQuery(function ($) {
            var $orderModal = $('#tmr-order-modal');
            var $orderModalBody = $('#tmr-order-modal-body');
            var $orderModalTitle = $('#tmr-order-modal-title');

            // ===== Delivery-date visible calendar (same self-built month-grid pattern
            // as the cozythai booking calendar) — state re-initialized every time the
            // form markup is (re)injected, since #tmr-delivery-cal is destroyed and
            // recreated along with the rest of the modal body each open. =====
            var bnMonths = ['জানুয়ারি', 'ফেব্রুয়ারি', 'মার্চ', 'এপ্রিল', 'মে', 'জুন', 'জুলাই', 'আগস্ট', 'সেপ্টেম্বর', 'অক্টোবর', 'নভেম্বর', 'ডিসেম্বর'];
            var bnDayHeaders = ['রবি', 'সোম', 'মঙ্গল', 'বুধ', 'বৃহ', 'শুক্র', 'শনি'];
            var calYear, calMonth, calSelected;

            function pad2(n) { return n < 10 ? '0' + n : '' + n; }
            function daysInMonth(y, m) { return new Date(y, m + 1, 0).getDate(); }
            function firstDayOfMonth(y, m) { return new Date(y, m, 1).getDay(); }

            function formatDeliveryDisplay(dateStr) {
                if (!dateStr) { return ''; }
                var p = dateStr.split('-');
                return parseInt(p[2], 10) + ' ' + bnMonths[parseInt(p[1], 10) - 1] + ', ' + p[0];
            }

            function renderDeliveryCalendar() {
                var firstDay = firstDayOfMonth(calYear, calMonth);
                var days = daysInMonth(calYear, calMonth);
                var today = new Date();
                var todayStr = today.getFullYear() + '-' + pad2(today.getMonth() + 1) + '-' + pad2(today.getDate());

                var html = '<div class="tmr-cal-nav">';
                html += '<button type="button" class="tmr-cal-nav-btn" data-action="prev">&lsaquo;</button>';
                html += '<span class="tmr-cal-title">' + bnMonths[calMonth] + ' ' + calYear + '</span>';
                html += '<button type="button" class="tmr-cal-nav-btn" data-action="next">&rsaquo;</button>';
                html += '</div>';
                html += '<div class="tmr-cal-grid">';
                bnDayHeaders.forEach(function (d) { html += '<span class="tmr-cal-day-header">' + d + '</span>'; });
                for (var i = 0; i < firstDay; i++) { html += '<span class="tmr-cal-day empty"></span>'; }
                for (var d = 1; d <= days; d++) {
                    var dateStr = calYear + '-' + pad2(calMonth + 1) + '-' + pad2(d);
                    var cls = 'tmr-cal-day';
                    if (dateStr === todayStr) { cls += ' today'; }
                    if (dateStr < todayStr) { cls += ' past'; }
                    if (dateStr === calSelected) { cls += ' selected'; }
                    html += '<span class="' + cls + '" data-date="' + dateStr + '">' + d + '</span>';
                }
                html += '</div>';
                $('#tmr-delivery-cal').html(html);
            }

            function initDeliveryCalendar() {
                var initial = $('#tmr-delivery-date').val();
                var d = initial ? new Date(initial + 'T00:00:00') : new Date();
                calYear = d.getFullYear();
                calMonth = d.getMonth();
                calSelected = initial || '';
                renderDeliveryCalendar();
            }

            // Every category block whose dress-selection already has data (edit mode)
            // starts uncollapsed server-side; newly checking a dress in a still-collapsed
            // block auto-reveals its cutter/measurement/design section — the manual
            // chevron toggle stays available regardless for browsing without selecting.
            function initCategoryCollapse() {
                $('.tmr-category-chevron').each(function () {
                    var $chevron = $(this);
                    var $collapsible = $chevron.closest('.tmr-category-block').find('.tmr-category-collapsible');
                    $collapsible.toggle($chevron.hasClass('is-open'));
                });
            }

            function loadOrderForm(id) {
                $orderModalTitle.text(id ? '<?php echo esc_js(__('অর্ডার আপডেট করুন', 'tailor-manager')); ?>' : '<?php echo esc_js(__('অর্ডার নিন', 'tailor-manager')); ?>');
                $orderModalBody.html('<div class="tmr-empty"><?php echo esc_js(__('লোড হচ্ছে…', 'tailor-manager')); ?></div>');
                TMRPanel.openModal($orderModal);
                TMRPanel.call('tmr_get_order_form', { id: id || 0 }, function (data) {
                    $orderModalBody.html(data.html);
                    initDeliveryCalendar();
                    initCategoryCollapse();
                });
            }

            $(document).on('click', '#tmr-open-order-modal', function (e) {
                e.preventDefault();
                loadOrderForm(0);
            });

            $(document).on('click', '.tmr-open-order-modal-edit', function (e) {
                e.preventDefault();
                loadOrderForm($(this).data('id'));
            });

            $(document).on('click', '#tmr-delivery-date-display', function (e) {
                e.stopPropagation();
                $('#tmr-delivery-cal-popover').toggle();
            });

            $(document).on('click', '#tmr-delivery-cal-popover .tmr-cal-nav-btn', function () {
                if ($(this).data('action') === 'prev') {
                    calMonth--;
                    if (calMonth < 0) { calMonth = 11; calYear--; }
                } else {
                    calMonth++;
                    if (calMonth > 11) { calMonth = 0; calYear++; }
                }
                renderDeliveryCalendar();
            });

            $(document).on('click', '#tmr-delivery-cal-popover .tmr-cal-day:not(.empty):not(.past)', function () {
                calSelected = $(this).data('date');
                $('#tmr-delivery-date').val(calSelected);
                $('#tmr-delivery-date-display').val(formatDeliveryDisplay(calSelected));
                $('#tmr-delivery-cal-popover').hide();
                renderDeliveryCalendar();
            });

            $(document).on('click', function (e) {
                if (!$(e.target).closest('#tmr-delivery-cal-popover, #tmr-delivery-date-display').length) {
                    $('#tmr-delivery-cal-popover').hide();
                }
            });

            $(document).on('click', '.tmr-category-block-header', function () {
                var $collapsible = $(this).closest('.tmr-category-block').find('.tmr-category-collapsible');
                $collapsible.slideToggle(150);
                $(this).find('.tmr-category-chevron').toggleClass('is-open');
            });

            $(document).on('change', '.tmr-dress-check', function () {
                var $qty = $(this).closest('label').find('.tmr-dress-qty');
                $qty.prop('disabled', !this.checked);
                if (this.checked) {
                    if (!$qty.val() || parseInt($qty.val(), 10) < 1) {
                        $qty.val(1);
                    }
                    var $block = $(this).closest('.tmr-category-block');
                    var $collapsible = $block.find('.tmr-category-collapsible');
                    if (!$collapsible.is(':visible')) {
                        $collapsible.slideDown(150);
                        $block.find('.tmr-category-chevron').addClass('is-open');
                    }
                } else {
                    $qty.val(0);
                }
            });

            $(document).on('click', '.tmr-qty-plus, .tmr-qty-minus', function (e) {
                e.preventDefault();
                var $input = $(this).siblings('.tmr-dress-qty');
                if ($input.prop('disabled')) {
                    return;
                }
                var val = parseInt($input.val(), 10) || 0;
                val = $(this).hasClass('tmr-qty-plus') ? val + 1 : Math.max(0, val - 1);
                $input.val(val);
            });

            $(document).on('click', '.tmr-measure-plus, .tmr-measure-minus', function (e) {
                e.preventDefault();
                var $input = $(this).siblings('.tmr-measure-field');
                var val = parseInt($input.val(), 10) || 0;
                val = $(this).hasClass('tmr-measure-plus') ? val + 1 : Math.max(0, val - 1);
                $input.val(val);
            });

            var searchTimer;
            $(document).on('input', '#tmr-customer-search', function () {
                var $input = $(this);
                var $results = $('#tmr-customer-results');
                clearTimeout(searchTimer);
                var term = $input.val();
                if (term.length < 2) {
                    $results.hide().empty();
                    return;
                }
                searchTimer = setTimeout(function () {
                    TMRPanel.call('tmr_search_customers', { term: term }, function (data) {
                        $results.empty();
                        if (!data.length) {
                            $results.hide();
                            return;
                        }
                        data.forEach(function (c) {
                            var $row = $('<div style="padding:6px 10px;cursor:pointer;border-bottom:1px solid var(--tmr-border);"></div>')
                                .text(c.name + ' (' + c.phone + ')')
                                .on('click', function () {
                                    $('#tmr-customer-id').val(c.id);
                                    $input.val(c.name + ' (' + c.phone + ')');
                                    $results.hide().empty();
                                });
                            $results.append($row);
                        });
                        $results.show();
                    });
                }, 300);
            });

            $(document).on('click', '#tmr-quick-add-customer', function () {
                $('#tmr-quick-customer-form')[0].reset();
                TMRPanel.openModal($('#tmr-quick-customer-modal'));
            });

            $(document).on('submit', '#tmr-quick-customer-form', function (e) {
                e.preventDefault();
                var $form = $(this);
                TMRPanel.call('tmr_quick_add_customer', $form.serialize(), function (data) {
                    $('#tmr-customer-id').val(data.id);
                    $('#tmr-customer-search').val(data.name + ' (' + data.phone + ')');
                    TMRPanel.closeModal($('#tmr-quick-customer-modal'));
                });
            });

            var orderImageFrame;
            $(document).on('click', '#tmr-pick-order-image', function (e) {
                e.preventDefault();
                if (!orderImageFrame) {
                    orderImageFrame = wp.media({ title: '<?php echo esc_js(__('রেফারেন্স ছবি নির্বাচন করুন', 'tailor-manager')); ?>', multiple: false });
                    orderImageFrame.on('select', function () {
                        var attachment = orderImageFrame.state().get('selection').first().toJSON();
                        $('#tmr-order-image-id').val(attachment.id);
                        $('#tmr-order-image-preview').attr('src', attachment.sizes && attachment.sizes.thumbnail ? attachment.sizes.thumbnail.url : attachment.url).show();
                        $('#tmr-order-image-placeholder').hide();
                    });
                }
                orderImageFrame.open();
            });

            $(document).on('submit', '#tmr-order-form', function (e) {
                e.preventDefault();

                if (!$('#tmr-customer-id').val()) {
                    window.alert('<?php echo esc_js(__('একজন কাস্টমার নির্বাচন করুন।', 'tailor-manager')); ?>');
                    return;
                }

                var categories = [];
                $('.tmr-category-block').each(function () {
                    var $block = $(this);
                    var dresses = [];
                    $block.find('.tmr-dress-check:checked').each(function () {
                        dresses.push({
                            dress_id: $(this).data('dress-id'),
                            quantity: $block.find('.tmr-dress-qty[data-dress-id="' + $(this).data('dress-id') + '"]').val() || 1
                        });
                    });

                    if (!dresses.length) {
                        return;
                    }

                    var measurements = {};
                    $block.find('.tmr-measure-field').each(function () {
                        measurements[$(this).data('slug')] = $(this).val();
                    });

                    var partSelections = [];
                    $block.find('.tmr-part-block').each(function () {
                        var $partBlock = $(this);
                        var ids = [];
                        $partBlock.find('.tmr-design-check:checked').each(function () {
                            ids.push($(this).val());
                        });
                        if (!ids.length) {
                            return;
                        }
                        partSelections.push({
                            part_id: $partBlock.data('part-id'),
                            design_type_ids: ids,
                            part_measurement: $partBlock.find('.tmr-part-measurement').val() || ''
                        });
                    });

                    categories.push({
                        category_id: $block.data('category-id'),
                        cutter_name: $block.find('.tmr-cutter-name').val() || '',
                        dresses: dresses,
                        measurements: measurements,
                        part_selections: partSelections
                    });
                });

                if (!categories.length) {
                    window.alert('<?php echo esc_js(__('কমপক্ষে একটি ড্রেস নির্বাচন করুন।', 'tailor-manager')); ?>');
                    return;
                }

                var payload = $('#tmr-order-form').serializeArray();
                payload.push({ name: 'items', value: JSON.stringify(categories) });

                TMRPanel.call('tmr_save_order', $.param(payload), function (data) {
                    window.location.href = '<?php echo esc_js(admin_url('admin.php?page=tmr-orders&action=view&id=')); ?>' + data.id;
                });
            });

            <?php if ($auto_open_id) : ?>
            loadOrderForm(<?php echo (int) $auto_open_id; ?>);
            <?php endif; ?>
        });
        </script>
        <?php
    }

    /* ---------------------------------------------------------------- */
    /* View                                                               */
    /* ---------------------------------------------------------------- */

    private static function render_view($order_id)
    {
        $order = get_post($order_id);
        if (!$order || self::POST_TYPE !== $order->post_type) {
            wp_die(esc_html__('অর্ডার পাওয়া যায়নি।', 'tailor-manager'));
        }

        $customer_id = (int) get_post_meta($order_id, '_tmr_customer_id', true);

        $header_right = '<a class="tmr-btn-outline" href="' . esc_url(admin_url('admin.php?page=tmr-orders&action=edit&id=' . $order_id)) . '">' . esc_html__('এডিট', 'tailor-manager') . '</a>'
            . ' <a class="tmr-btn-outline" target="_blank" href="' . esc_url(admin_url('admin-post.php?action=tmr_print&order_id=' . $order_id . '&type=1')) . '">' . esc_html__('রিসিট প্রিন্ট', 'tailor-manager') . '</a>'
            . ' <a class="tmr-btn-outline" target="_blank" href="' . esc_url(admin_url('admin-post.php?action=tmr_print&order_id=' . $order_id . '&type=2')) . '">' . esc_html__('ওয়ার্ক স্লিপ প্রিন্ট', 'tailor-manager') . '</a>'
            . ' <a class="tmr-btn-outline" target="_blank" href="' . esc_url(admin_url('admin-post.php?action=tmr_print&order_id=' . $order_id . '&type=3')) . '">' . esc_html__('ফুল স্লিপ প্রিন্ট', 'tailor-manager') . '</a>';
        TMR_Panel_Shell::header('orders', __('অর্ডার', 'tailor-manager') . ' #' . $order_id, '', $header_right);
        ?>
        <div class="tmr-card-plain">
            <p style="margin:0 0 8px;"><span class="tmr-badge tmr-badge-<?php echo esc_attr(TMR_Order_Post_Type::status_label($order_id)); ?>"><?php echo esc_html(ucfirst(TMR_Order_Post_Type::status_label($order_id))); ?></span></p>
            <p><strong><?php esc_html_e('কাস্টমার', 'tailor-manager'); ?>:</strong> <?php echo $customer_id ? esc_html(get_the_title($customer_id) . ' (' . TMR_Customer_Post_Type::get_phone($customer_id) . ')') : esc_html__('ওয়াক-ইন', 'tailor-manager'); ?></p>
            <p><strong><?php esc_html_e('অর্ডারের তারিখ', 'tailor-manager'); ?>:</strong> <?php echo esc_html(get_post_meta($order_id, '_tmr_order_date', true)); ?> &nbsp; <strong><?php esc_html_e('ডেলিভারি তারিখ', 'tailor-manager'); ?>:</strong> <?php echo esc_html(get_post_meta($order_id, '_tmr_delivery_date', true)); ?></p>
            <?php if ('1' === get_post_meta($order_id, '_tmr_urgent', true)) : ?>
                <p><span class="tmr-badge tmr-badge-red"><?php esc_html_e('জরুরি', 'tailor-manager'); ?></span></p>
            <?php endif; ?>
        </div>

        <?php foreach (TMR_Order_Post_Type::get_items($order_id) as $item) :
            $cat_id = TMR_Order_Item_Post_Type::get_category_id($item->ID);
            $term   = get_term($cat_id, TMR_Category_Taxonomy::TAXONOMY);
        ?>
            <div class="tmr-card-plain">
                <h3 style="margin:0 0 10px;font-size:15px;"><?php echo $term && !is_wp_error($term) ? esc_html($term->name) : ''; ?></h3>
                <p>
                    <?php foreach (TMR_Order_Item_Post_Type::get_dresses($item->ID) as $d) :
                        $dress = get_post($d['dress_id']);
                        if ($dress) :
                    ?>
                        <?php echo esc_html($dress->post_title . '(' . (int) $d['quantity'] . ') '); ?>
                    <?php endif; endforeach; ?>
                </p>
                <?php $cutter = get_post_meta($item->ID, '_tmr_cutter_name', true); ?>
                <?php if ($cutter) : ?><p><strong><?php esc_html_e('কাটার/টেইলার', 'tailor-manager'); ?>:</strong> <?php echo esc_html($cutter); ?></p><?php endif; ?>

                <?php $measurements = TMR_Order_Item_Post_Type::get_measurements($item->ID); ?>
                <?php if ($measurements) : ?>
                    <table class="tmr-table">
                        <thead><tr><?php foreach ($measurements as $slug => $val) : ?><th><?php echo esc_html($slug); ?></th><?php endforeach; ?></tr></thead>
                        <tbody><tr><?php foreach ($measurements as $val) : ?><td><?php echo esc_html($val); ?></td><?php endforeach; ?></tr></tbody>
                    </table>
                <?php endif; ?>

                <?php foreach (TMR_Order_Item_Post_Type::get_part_selections($item->ID) as $sel) :
                    $part = get_post($sel['part_id']);
                    if (!$part) {
                        continue;
                    }
                    $names = array();
                    foreach ($sel['design_type_ids'] as $did) {
                        $d = get_post($did);
                        if ($d) {
                            $names[] = $d->post_title;
                        }
                    }
                ?>
                    <p><strong><?php echo esc_html($part->post_title); ?>:</strong> <?php echo esc_html(implode(', ', $names)); ?><?php if (!empty($sel['part_measurement'])) : ?> (<?php echo esc_html($sel['part_measurement']); ?>)<?php endif; ?></p>
                <?php endforeach; ?>
            </div>
        <?php endforeach; ?>

        <div class="tmr-card-plain">
            <h3 style="margin:0 0 10px;font-size:15px;"><?php esc_html_e('মূল্য', 'tailor-manager'); ?></h3>
            <p><strong><?php esc_html_e('মজুরি', 'tailor-manager'); ?>:</strong> <?php echo esc_html(get_post_meta($order_id, '_tmr_wage', true)); ?></p>
            <p><strong><?php esc_html_e('কাপড়ের দাম', 'tailor-manager'); ?>:</strong> <?php echo esc_html(get_post_meta($order_id, '_tmr_cloth_price', true)); ?></p>
            <p><strong><?php esc_html_e('মোট', 'tailor-manager'); ?>:</strong> <?php echo esc_html(get_post_meta($order_id, '_tmr_total', true)); ?></p>
            <p><strong><?php esc_html_e('অগ্রিম', 'tailor-manager'); ?>:</strong> <?php echo esc_html(get_post_meta($order_id, '_tmr_advance', true)); ?></p>
            <p><strong><?php esc_html_e('বাকি', 'tailor-manager'); ?>:</strong> <?php echo esc_html(get_post_meta($order_id, '_tmr_due', true)); ?></p>
        </div>
        <?php
        TMR_Panel_Shell::footer();
    }

    /* ---------------------------------------------------------------- */
    /* AJAX                                                               */
    /* ---------------------------------------------------------------- */

    /**
     * Returns the Take/Update Order form as a rendered HTML string, for the
     * "+ অর্ডার নিন" / row-Edit buttons to inject into #tmr-order-modal-body — reuses
     * render_form_body() as-is (same PHP that used to render a full standalone page),
     * just captured via output buffering instead of echoed directly.
     */
    public function ajax_get_order_form()
    {
        check_ajax_referer('tmr_panel_nonce', 'nonce');
        if (!current_user_can(TMR_Panel_Shell::CAPABILITY)) {
            wp_send_json_error(array('message' => __('অনুমতি নেই।', 'tailor-manager')));
        }

        $order_id = isset($_POST['id']) ? (int) $_POST['id'] : 0;

        if ($order_id > 0) {
            $order = get_post($order_id);
            if (!$order || self::POST_TYPE !== $order->post_type) {
                wp_send_json_error(array('message' => __('অর্ডার পাওয়া যায়নি।', 'tailor-manager')));
            }
        }

        ob_start();
        self::render_form_body($order_id);
        $html = ob_get_clean();

        wp_send_json_success(array('html' => $html));
    }

    public function ajax_search_customers()
    {
        check_ajax_referer('tmr_panel_nonce', 'nonce');
        if (!current_user_can(TMR_Panel_Shell::CAPABILITY)) {
            wp_send_json_error(array('message' => __('অনুমতি নেই।', 'tailor-manager')));
        }

        $term = isset($_POST['term']) ? sanitize_text_field(wp_unslash($_POST['term'])) : '';

        // WP's native 's' search only matches the post title (customer name) — phone
        // number lives in postmeta, so it needs its own meta_query LIKE match. Run both
        // and merge, since a search term could be either a name or a mobile number.
        $by_name = get_posts(array(
            'post_type'      => TMR_Customer_Post_Type::POST_TYPE,
            'post_status'    => array('publish'),
            's'              => $term,
            'posts_per_page' => 15,
        ));

        $by_phone = get_posts(array(
            'post_type'      => TMR_Customer_Post_Type::POST_TYPE,
            'post_status'    => array('publish'),
            'posts_per_page' => 15,
            'meta_query'     => array(
                array('key' => '_tmr_phone', 'value' => $term, 'compare' => 'LIKE'),
            ),
        ));

        $merged = array();
        foreach (array_merge($by_name, $by_phone) as $p) {
            $merged[$p->ID] = $p;
        }

        $results = array();
        foreach (array_slice($merged, 0, 15, true) as $p) {
            $results[] = array('id' => $p->ID, 'name' => $p->post_title, 'phone' => TMR_Customer_Post_Type::get_phone($p->ID));
        }

        wp_send_json_success($results);
    }

    public function ajax_quick_add_customer()
    {
        check_ajax_referer('tmr_panel_nonce', 'nonce');
        if (!current_user_can(TMR_Panel_Shell::CAPABILITY)) {
            wp_send_json_error(array('message' => __('অনুমতি নেই।', 'tailor-manager')));
        }

        $name  = isset($_POST['name']) ? sanitize_text_field(wp_unslash($_POST['name'])) : '';
        $phone = isset($_POST['phone']) ? sanitize_text_field(wp_unslash($_POST['phone'])) : '';
        $address = isset($_POST['address']) ? sanitize_textarea_field(wp_unslash($_POST['address'])) : '';

        if ('' === $name || '' === $phone) {
            wp_send_json_error(array('message' => __('নাম ও ফোন নম্বর আবশ্যক।', 'tailor-manager')));
        }

        $id = wp_insert_post(array(
            'post_type'   => TMR_Customer_Post_Type::POST_TYPE,
            'post_title'  => $name,
            'post_status' => 'publish',
        ), true);

        if (is_wp_error($id)) {
            wp_send_json_error(array('message' => $id->get_error_message()));
        }

        update_post_meta($id, '_tmr_phone', $phone);
        update_post_meta($id, '_tmr_address', $address);

        wp_send_json_success(array('id' => $id, 'name' => $name, 'phone' => $phone));
    }

    public function ajax_save()
    {
        check_ajax_referer('tmr_panel_nonce', 'nonce');
        if (!current_user_can(TMR_Panel_Shell::CAPABILITY)) {
            wp_send_json_error(array('message' => __('অনুমতি নেই।', 'tailor-manager')));
        }

        $order_id      = isset($_POST['order_id']) ? (int) $_POST['order_id'] : 0;
        $customer_id   = isset($_POST['customer_id']) ? (int) $_POST['customer_id'] : 0;
        $order_date    = isset($_POST['order_date']) ? sanitize_text_field(wp_unslash($_POST['order_date'])) : '';
        $delivery_date = isset($_POST['delivery_date']) ? sanitize_text_field(wp_unslash($_POST['delivery_date'])) : '';
        $urgent        = !empty($_POST['urgent']);
        $wage          = isset($_POST['wage']) ? (float) $_POST['wage'] : 0;
        $cloth_price   = isset($_POST['cloth_price']) ? (float) $_POST['cloth_price'] : 0;
        $total         = isset($_POST['total']) ? (float) $_POST['total'] : 0;
        $advance       = isset($_POST['advance']) ? (float) $_POST['advance'] : 0;
        $due           = isset($_POST['due']) ? (float) $_POST['due'] : 0;
        $image_id      = isset($_POST['image_id']) ? (int) $_POST['image_id'] : 0;
        $items_json    = isset($_POST['items']) ? wp_unslash($_POST['items']) : '[]';
        $items         = json_decode($items_json, true);

        if (!$customer_id || !$order_date || !$delivery_date || !is_array($items) || empty($items)) {
            wp_send_json_error(array('message' => __('কাস্টমার, তারিখ এবং কমপক্ষে একটি ড্রেস আবশ্যক।', 'tailor-manager')));
        }

        $customer = get_post($customer_id);
        $title = $customer ? $customer->post_title . ' - ' . $delivery_date : 'Order - ' . $delivery_date;

        $post_data = array(
            'post_type'   => self::POST_TYPE,
            'post_title'  => $title,
            'post_status' => 'publish',
        );

        if ($order_id > 0) {
            $post_data['ID'] = $order_id;
            $result = wp_update_post($post_data, true);
        } else {
            $result = wp_insert_post($post_data, true);
        }

        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }

        $order_id = $result;

        update_post_meta($order_id, '_tmr_customer_id', $customer_id);
        update_post_meta($order_id, '_tmr_order_date', $order_date);
        update_post_meta($order_id, '_tmr_delivery_date', $delivery_date);
        update_post_meta($order_id, '_tmr_urgent', $urgent ? '1' : '0');
        update_post_meta($order_id, '_tmr_wage', $wage);
        update_post_meta($order_id, '_tmr_cloth_price', $cloth_price);
        update_post_meta($order_id, '_tmr_total', $total);
        update_post_meta($order_id, '_tmr_advance', $advance);
        update_post_meta($order_id, '_tmr_due', $due);

        if ($image_id > 0) {
            set_post_thumbnail($order_id, $image_id);
        }
        update_post_meta($order_id, '_tmr_reference_image_id', $image_id);

        // Replace existing items with the submitted set (simplest correct approach for a
        // small, bounded number of items per order — at most one per category today).
        foreach (TMR_Order_Post_Type::get_items($order_id) as $existing) {
            wp_delete_post($existing->ID, true);
        }

        foreach ($items as $item) {
            $category_id = isset($item['category_id']) ? (int) $item['category_id'] : 0;
            $dresses     = isset($item['dresses']) && is_array($item['dresses']) ? $item['dresses'] : array();

            if (!$category_id || empty($dresses)) {
                continue;
            }

            $clean_dresses = array();
            foreach ($dresses as $d) {
                $dress_id = isset($d['dress_id']) ? (int) $d['dress_id'] : 0;
                $qty      = isset($d['quantity']) ? max(1, (int) $d['quantity']) : 1;
                if ($dress_id) {
                    $clean_dresses[] = array('dress_id' => $dress_id, 'quantity' => $qty);
                }
            }

            if (empty($clean_dresses)) {
                continue;
            }

            $item_id = wp_insert_post(array(
                'post_type'   => TMR_Order_Item_Post_Type::POST_TYPE,
                'post_parent' => $order_id,
                'post_title'  => 'Item',
                'post_status' => 'publish',
            ), true);

            if (is_wp_error($item_id)) {
                continue;
            }

            update_post_meta($item_id, '_tmr_category_id', $category_id);
            update_post_meta($item_id, '_tmr_dresses', $clean_dresses);
            update_post_meta($item_id, '_tmr_cutter_name', isset($item['cutter_name']) ? sanitize_text_field($item['cutter_name']) : '');

            $measurements = array();
            if (isset($item['measurements']) && is_array($item['measurements'])) {
                foreach ($item['measurements'] as $slug => $val) {
                    $measurements[sanitize_key($slug)] = sanitize_text_field($val);
                }
            }
            update_post_meta($item_id, '_tmr_measurements', $measurements);

            $part_selections = array();
            if (isset($item['part_selections']) && is_array($item['part_selections'])) {
                foreach ($item['part_selections'] as $sel) {
                    $part_id = isset($sel['part_id']) ? (int) $sel['part_id'] : 0;
                    $ids     = isset($sel['design_type_ids']) && is_array($sel['design_type_ids']) ? array_map('intval', $sel['design_type_ids']) : array();
                    if (!$part_id || empty($ids)) {
                        continue;
                    }
                    $part_selections[] = array(
                        'part_id'          => $part_id,
                        'design_type_ids'  => $ids,
                        'part_measurement' => isset($sel['part_measurement']) ? sanitize_text_field($sel['part_measurement']) : '',
                    );
                }
            }
            update_post_meta($item_id, '_tmr_part_selections', $part_selections);
        }

        wp_send_json_success(array('id' => $order_id));
    }

    public function ajax_delete()
    {
        check_ajax_referer('tmr_panel_nonce', 'nonce');
        if (!current_user_can(TMR_Panel_Shell::CAPABILITY)) {
            wp_send_json_error(array('message' => __('অনুমতি নেই।', 'tailor-manager')));
        }

        $id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
        $post = get_post($id);

        if (!$post || self::POST_TYPE !== $post->post_type) {
            wp_send_json_error(array('message' => __('অর্ডার পাওয়া যায়নি।', 'tailor-manager')));
        }

        foreach (TMR_Order_Post_Type::get_items($id) as $item) {
            wp_delete_post($item->ID, true);
        }

        wp_trash_post($id);
        wp_send_json_success();
    }
}
