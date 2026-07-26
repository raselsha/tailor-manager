<?php
defined('ABSPATH') || exit;
/** @var array $data */
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title><?php echo esc_html__('Design Slip', 'tailor-manager'); ?> #<?php echo esc_html($data['order_id']); ?></title>
<style>
    body { font-family: Arial, Helvetica, sans-serif; font-size: 13px; padding: 20px; }
    table { border-collapse: collapse; width: 100%; margin-bottom: 10px; }
    td, th { border: 1px solid #777; padding: 6px 8px; vertical-align: top; }
    .header h1 { font-size: 22px; margin: 0 0 6px; }
    .measurements td { text-align: center; }
    .design-line { display: block; font-size: 12px; padding: 2px 0; }
</style>
</head>
<body onload="window.print()">
    <table class="header">
        <tr>
            <td style="border-right:1px solid #ccc;">
                <h1><?php echo esc_html($data['shop_name']); ?></h1>
                <?php esc_html_e('Name', 'tailor-manager'); ?>: <?php echo esc_html($data['customer_name']); ?>
            </td>
            <td>
                <?php esc_html_e('Order #', 'tailor-manager'); ?>: <?php echo esc_html($data['order_id']); ?><br>
                <?php esc_html_e('Order Date', 'tailor-manager'); ?>: <?php echo esc_html($data['order_date']); ?><br>
                <?php esc_html_e('Delivery Date', 'tailor-manager'); ?>: <?php echo esc_html($data['delivery_date']); ?><br>
                <?php esc_html_e('Mobile', 'tailor-manager'); ?>: <?php echo esc_html($data['customer_phone']); ?>
            </td>
        </tr>
    </table>

    <table>
        <tr>
            <th style="width:15%;"><?php esc_html_e('Dress', 'tailor-manager'); ?></th>
            <th><?php esc_html_e('Measurement & Design Details', 'tailor-manager'); ?></th>
            <th style="width:15%;"><?php esc_html_e('Price', 'tailor-manager'); ?></th>
        </tr>
        <?php foreach ($data['items'] as $item) : ?>
            <tr>
                <td><?php echo esc_html(implode('<br>', $item['dress_lines'])); ?></td>
                <td>
                    <?php if ($item['measurements']) : ?>
                        <table class="measurements">
                            <tr>
                                <?php foreach ($item['measurements'] as $slug => $val) : ?>
                                    <td><?php echo esc_html($slug); ?><br><?php echo esc_html($val); ?></td>
                                <?php endforeach; ?>
                            </tr>
                        </table>
                    <?php endif; ?>
                    <?php foreach ($item['part_lines'] as $line) : ?>
                        <span class="design-line"><strong><?php echo esc_html($line['part']); ?>:</strong> <?php echo esc_html(implode(', ', $line['designs'])); ?><?php if ($line['measurement']) : ?> (<?php echo esc_html($line['measurement']); ?>)<?php endif; ?></span>
                    <?php endforeach; ?>
                </td>
                <td></td>
            </tr>
        <?php endforeach; ?>
    </table>

    <table style="max-width:220px;">
        <tr><td><?php esc_html_e('Wage', 'tailor-manager'); ?></td><td><?php echo esc_html($data['wage']); ?></td></tr>
        <tr><td><?php esc_html_e('Cloth Price', 'tailor-manager'); ?></td><td><?php echo esc_html($data['cloth_price']); ?></td></tr>
        <tr><td><?php esc_html_e('Total', 'tailor-manager'); ?></td><td><?php echo esc_html($data['total']); ?></td></tr>
        <tr><td><?php esc_html_e('Advance', 'tailor-manager'); ?></td><td><?php echo esc_html($data['advance']); ?></td></tr>
        <tr><td><?php esc_html_e('Due', 'tailor-manager'); ?></td><td><?php echo esc_html($data['due']); ?></td></tr>
    </table>
</body>
</html>
