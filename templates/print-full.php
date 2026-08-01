<?php
defined('ABSPATH') || exit;
/** @var array $data */
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title><?php echo esc_html__('ফুল ডিজাইন স্লিপ', 'tailor-manager'); ?> #<?php echo esc_html($data['order_id']); ?></title>
<style>
    body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif; padding: 24px; color: #1e293b; background: #fff; margin: 0; }
    .print-shop-name { margin: 0 0 3px; font-size: 17px; font-weight: 700; text-align: center; }
    .print-shop-meta { margin: 0 0 3px; font-size: 11px; color: #64748b; text-align: center; }
    .print-divider { border: none; border-top: 1px solid #cbd5e1; margin: 14px 0 20px; }
    .print-meta-row { display: flex; flex-wrap: wrap; gap: 6px 22px; font-size: 12px; margin-bottom: 16px; }
    .print-meta-row span { color: #64748b; }
    .print-meta-row strong { color: #1e293b; margin-left: 4px; }
    table { width: 100%; border-collapse: collapse; font-size: 12px; margin-bottom: 16px; }
    th, td { padding: 7px 10px; border-bottom: 1px solid #94a3b8; text-align: left; vertical-align: top; }
    th { background: #f8fafc; text-transform: uppercase; font-size: 10px; color: #64748b; letter-spacing: .03em; }
    td strong { color: #1e293b; }
    .print-measure-grid { display: flex; flex-wrap: wrap; gap: 6px; margin-bottom: 8px; }
    .print-measure-chip { display: inline-block; border: 1px solid #cbd5e1; border-radius: 4px; padding: 3px 7px; font-size: 11px; }
    .print-measure-chip span { color: #64748b; }
    .print-design-line { display: block; font-size: 12px; padding: 2px 0; }
    .print-design-line strong { color: #1e293b; }
    .print-totals { max-width: 260px; margin-left: auto; }
    .print-totals td:last-child { text-align: right; font-weight: 700; }
    @media print {
        body { padding: 0; }
    }
</style>
</head>
<body onload="window.print()">
    <?php if (!empty($data['shop_name'])) : ?>
        <p class="print-shop-name"><?php echo esc_html($data['shop_name']); ?></p>
    <?php endif; ?>
    <?php
    $shop_meta = array_filter(array($data['shop_address'], $data['shop_phone']));
    if ($shop_meta) :
    ?>
        <p class="print-shop-meta"><?php echo esc_html(implode(' • ', $shop_meta)); ?></p>
    <?php endif; ?>
    <hr class="print-divider">

    <div class="print-meta-row">
        <span><?php esc_html_e('অর্ডার নং', 'tailor-manager'); ?>:<strong>#<?php echo esc_html($data['order_id']); ?></strong></span>
        <span><?php esc_html_e('নাম', 'tailor-manager'); ?>:<strong><?php echo esc_html($data['customer_name']); ?></strong></span>
        <?php if ($data['customer_phone']) : ?><span><?php esc_html_e('মোবাইল', 'tailor-manager'); ?>:<strong><?php echo esc_html($data['customer_phone']); ?></strong></span><?php endif; ?>
        <span><?php esc_html_e('অর্ডারের তারিখ', 'tailor-manager'); ?>:<strong><?php echo esc_html($data['order_date']); ?></strong></span>
        <span><?php esc_html_e('ডেলিভারি তারিখ', 'tailor-manager'); ?>:<strong><?php echo esc_html($data['delivery_date']); ?></strong></span>
    </div>

    <table>
        <thead>
            <tr>
                <th style="width:16%;"><?php esc_html_e('ড্রেস', 'tailor-manager'); ?></th>
                <th><?php esc_html_e('মাপ ও ডিজাইনের বিবরণ', 'tailor-manager'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($data['items'] as $item) : ?>
                <tr>
                    <td>
                        <?php foreach ($item['dress_lines'] as $line) : ?>
                            <div><?php echo esc_html($line); ?></div>
                        <?php endforeach; ?>
                    </td>
                    <td>
                        <?php if ($item['measurements']) : ?>
                            <div class="print-measure-grid">
                                <?php foreach ($item['measurements'] as $m) : ?>
                                    <span class="print-measure-chip"><span><?php echo esc_html($m['label']); ?>:</span> <strong><?php echo esc_html($m['value']); ?></strong></span>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                        <?php foreach ($item['part_lines'] as $line) : ?>
                            <span class="print-design-line"><strong><?php echo esc_html($line['part']); ?>:</strong> <?php echo esc_html(implode(', ', $line['designs'])); ?><?php if ($line['measurement']) : ?> (<?php echo esc_html($line['measurement']); ?>)<?php endif; ?></span>
                        <?php endforeach; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <table class="print-totals">
        <tr><td><?php esc_html_e('মজুরি', 'tailor-manager'); ?></td><td><?php echo esc_html($data['wage']); ?></td></tr>
        <tr><td><?php esc_html_e('কাপড়ের দাম', 'tailor-manager'); ?></td><td><?php echo esc_html($data['cloth_price']); ?></td></tr>
        <tr><td><?php esc_html_e('মোট', 'tailor-manager'); ?></td><td><?php echo esc_html($data['total']); ?></td></tr>
        <tr><td><?php esc_html_e('অগ্রিম', 'tailor-manager'); ?></td><td><?php echo esc_html($data['advance']); ?></td></tr>
        <tr><td><?php esc_html_e('বাকি', 'tailor-manager'); ?></td><td><?php echo esc_html($data['due']); ?></td></tr>
    </table>
</body>
</html>
