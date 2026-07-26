<?php
defined('ABSPATH') || exit;

/**
 * Date-range financial report. Uses a direct $wpdb prepared SUM/GROUP BY against core
 * wp_postmeta/wp_posts for aggregate performance — a read-only report query against core
 * tables, not a new custom table, so it doesn't conflict with the no-extra-tables standard.
 */
class TMR_Accounts_Report
{
    public function __construct()
    {
    }

    public static function render()
    {
        global $wpdb;

        if (!current_user_can(TMR_Panel_Shell::CAPABILITY)) {
            wp_die(esc_html__('You do not have permission to access this page.', 'tailor-manager'));
        }

        $from = isset($_GET['from']) ? sanitize_text_field(wp_unslash($_GET['from'])) : current_time('Y-m-01');
        $to   = isset($_GET['to']) ? sanitize_text_field(wp_unslash($_GET['to'])) : current_time('Y-m-d');

        $post_type = TMR_Order_Post_Type::POST_TYPE;

        $sql = "
            SELECT
                pm_date.meta_value AS order_date,
                COUNT(DISTINCT p.ID) AS order_count,
                SUM(CAST(pm_wage.meta_value AS DECIMAL(12,2))) AS wage,
                SUM(CAST(pm_cloth.meta_value AS DECIMAL(12,2))) AS cloth_price,
                SUM(CAST(pm_advance.meta_value AS DECIMAL(12,2))) AS advance,
                SUM(CAST(pm_due.meta_value AS DECIMAL(12,2))) AS due,
                SUM(CAST(pm_total.meta_value AS DECIMAL(12,2))) AS total
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} pm_date ON pm_date.post_id = p.ID AND pm_date.meta_key = '_tmr_order_date'
            LEFT JOIN {$wpdb->postmeta} pm_wage ON pm_wage.post_id = p.ID AND pm_wage.meta_key = '_tmr_wage'
            LEFT JOIN {$wpdb->postmeta} pm_cloth ON pm_cloth.post_id = p.ID AND pm_cloth.meta_key = '_tmr_cloth_price'
            LEFT JOIN {$wpdb->postmeta} pm_advance ON pm_advance.post_id = p.ID AND pm_advance.meta_key = '_tmr_advance'
            LEFT JOIN {$wpdb->postmeta} pm_due ON pm_due.post_id = p.ID AND pm_due.meta_key = '_tmr_due'
            LEFT JOIN {$wpdb->postmeta} pm_total ON pm_total.post_id = p.ID AND pm_total.meta_key = '_tmr_total'
            WHERE p.post_type = %s
              AND p.post_status != 'trash'
              AND pm_date.meta_value BETWEEN %s AND %s
            GROUP BY pm_date.meta_value
            ORDER BY pm_date.meta_value DESC
        ";

        $rows = $wpdb->get_results($wpdb->prepare($sql, $post_type, $from, $to));

        $grand = array('order_count' => 0, 'wage' => 0, 'cloth_price' => 0, 'advance' => 0, 'due' => 0, 'total' => 0);
        foreach ($rows as $row) {
            $grand['order_count'] += (int) $row->order_count;
            $grand['wage']        += (float) $row->wage;
            $grand['cloth_price'] += (float) $row->cloth_price;
            $grand['advance']     += (float) $row->advance;
            $grand['due']         += (float) $row->due;
            $grand['total']       += (float) $row->total;
        }

        TMR_Panel_Shell::header('accounts', __('Accounts Report', 'tailor-manager'));
        ?>
        <form class="tmr-card" method="get" style="display:flex;gap:10px;align-items:flex-end;">
            <input type="hidden" name="page" value="tmr-accounts" />
            <div class="tmr-form-row" style="margin:0;"><label><?php esc_html_e('From', 'tailor-manager'); ?></label><input type="date" name="from" value="<?php echo esc_attr($from); ?>" /></div>
            <div class="tmr-form-row" style="margin:0;"><label><?php esc_html_e('To', 'tailor-manager'); ?></label><input type="date" name="to" value="<?php echo esc_attr($to); ?>" /></div>
            <button type="submit" class="tmr-btn tmr-btn--primary"><?php esc_html_e('Go', 'tailor-manager'); ?></button>
        </form>
        <p>
            <a href="<?php echo esc_url(add_query_arg(array('from' => current_time('Y-m-01'), 'to' => current_time('Y-m-d')))); ?>"><?php esc_html_e('This Month', 'tailor-manager'); ?></a> |
            <a href="<?php echo esc_url(add_query_arg(array('from' => date('Y-m-01', strtotime('first day of last month')), 'to' => date('Y-m-t', strtotime('last day of last month'))))); ?>"><?php esc_html_e('Last Month', 'tailor-manager'); ?></a> |
            <a href="<?php echo esc_url(add_query_arg(array('from' => '2000-01-01', 'to' => current_time('Y-m-d')))); ?>"><?php esc_html_e('All Time', 'tailor-manager'); ?></a>
        </p>

        <table class="tmr-table">
            <thead>
                <tr>
                    <th><?php esc_html_e('Date', 'tailor-manager'); ?></th>
                    <th><?php esc_html_e('Orders', 'tailor-manager'); ?></th>
                    <th><?php esc_html_e('Wage', 'tailor-manager'); ?></th>
                    <th><?php esc_html_e('Cloth Price', 'tailor-manager'); ?></th>
                    <th><?php esc_html_e('Advance', 'tailor-manager'); ?></th>
                    <th><?php esc_html_e('Due', 'tailor-manager'); ?></th>
                    <th><?php esc_html_e('Total', 'tailor-manager'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($rows)) : ?>
                    <tr><td colspan="7" class="tmr-empty"><?php esc_html_e('No orders in this range.', 'tailor-manager'); ?></td></tr>
                <?php else : ?>
                    <?php foreach ($rows as $row) : ?>
                        <tr>
                            <td><?php echo esc_html($row->order_date); ?></td>
                            <td><?php echo esc_html($row->order_count); ?></td>
                            <td><?php echo esc_html(number_format((float) $row->wage, 2)); ?></td>
                            <td><?php echo esc_html(number_format((float) $row->cloth_price, 2)); ?></td>
                            <td><?php echo esc_html(number_format((float) $row->advance, 2)); ?></td>
                            <td><?php echo esc_html(number_format((float) $row->due, 2)); ?></td>
                            <td><?php echo esc_html(number_format((float) $row->total, 2)); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <tr style="font-weight:700;">
                        <td><?php esc_html_e('Total', 'tailor-manager'); ?></td>
                        <td><?php echo esc_html($grand['order_count']); ?></td>
                        <td><?php echo esc_html(number_format($grand['wage'], 2)); ?></td>
                        <td><?php echo esc_html(number_format($grand['cloth_price'], 2)); ?></td>
                        <td><?php echo esc_html(number_format($grand['advance'], 2)); ?></td>
                        <td><?php echo esc_html(number_format($grand['due'], 2)); ?></td>
                        <td><?php echo esc_html(number_format($grand['total'], 2)); ?></td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
        <?php
        TMR_Panel_Shell::footer();
    }
}
