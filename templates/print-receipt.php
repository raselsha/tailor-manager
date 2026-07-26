<?php
defined('ABSPATH') || exit;
/** @var array $data */
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title><?php echo esc_html__('Receipt', 'tailor-manager'); ?> #<?php echo esc_html($data['order_id']); ?></title>
<style>
    body { font-family: Arial, Helvetica, sans-serif; font-size: 13px; padding: 20px; }
    table { border-collapse: collapse; width: 100%; max-width: 380px; }
    td, th { border: 1px solid #777; padding: 4px 6px; }
    .header { text-align: center; font-weight: bold; font-size: 16px; margin-bottom: 4px; }
    .sub { text-align: center; font-size: 11px; margin-bottom: 10px; }
    .totals td:first-child { width: 60%; }
</style>
</head>
<body onload="window.print()">
    <div class="header"><?php echo esc_html($data['shop_name']); ?></div>
    <?php if ($data['shop_address'] || $data['shop_phone']) : ?>
        <div class="sub"><?php echo esc_html($data['shop_address']); ?><?php if ($data['shop_phone']) : ?> &middot; <?php echo esc_html($data['shop_phone']); ?><?php endif; ?></div>
    <?php endif; ?>

    <table>
        <tr>
            <td>
                <?php esc_html_e('Order #', 'tailor-manager'); ?> <?php echo esc_html($data['order_id']); ?><br>
                <?php esc_html_e('Name', 'tailor-manager'); ?>: <?php echo esc_html($data['customer_name']); ?><br>
                <?php esc_html_e('Mobile', 'tailor-manager'); ?>: <?php echo esc_html($data['customer_phone']); ?>
            </td>
            <td style="text-align:right;">
                <?php esc_html_e('Order Date', 'tailor-manager'); ?>: <?php echo esc_html($data['order_date']); ?><br>
                <?php esc_html_e('Delivery', 'tailor-manager'); ?>: <?php echo esc_html($data['delivery_date']); ?>
            </td>
        </tr>
    </table>

    <table style="margin-top:10px;">
        <tr>
            <th><?php esc_html_e('Dress', 'tailor-manager'); ?></th>
            <th><?php esc_html_e('Qty', 'tailor-manager'); ?></th>
        </tr>
        <?php foreach ($data['items'] as $i => $item) : ?>
            <tr>
                <td colspan="2"><?php echo esc_html(implode(', ', $item['dress_lines'])); ?></td>
            </tr>
        <?php endforeach; ?>
    </table>

    <table class="totals" style="margin-top:10px;">
        <tr><td><?php esc_html_e('Wage', 'tailor-manager'); ?></td><td><?php echo esc_html($data['wage']); ?></td></tr>
        <tr><td><?php esc_html_e('Cloth Price', 'tailor-manager'); ?></td><td><?php echo esc_html($data['cloth_price']); ?></td></tr>
        <tr><td><?php esc_html_e('Total', 'tailor-manager'); ?></td><td><?php echo esc_html($data['total']); ?></td></tr>
        <tr><td><?php esc_html_e('Advance', 'tailor-manager'); ?></td><td><?php echo esc_html($data['advance']); ?></td></tr>
        <tr><td><?php esc_html_e('Due', 'tailor-manager'); ?></td><td><?php echo esc_html($data['due']); ?></td></tr>
    </table>
</body>
</html>
