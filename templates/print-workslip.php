<?php
defined('ABSPATH') || exit;
/** @var array $data */
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title><?php echo esc_html__('ওয়ার্ক স্লিপ', 'tailor-manager'); ?> #<?php echo esc_html($data['order_number']); ?></title>
<style>
    body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif; padding: 24px; color: #1e293b; background: #fff; margin: 0; }
    .print-shop-name { margin: 0 0 3px; font-size: 17px; font-weight: 700; text-align: center; }
    .print-shop-meta { margin: 0 0 3px; font-size: 11px; color: #64748b; text-align: center; }
    .print-divider { border: none; border-top: 1px solid #cbd5e1; margin: 14px 0 20px; }
    .print-item-block { margin-bottom: 26px; page-break-inside: avoid; }
    .print-item-title { margin: 0 0 8px; font-size: 15px; font-weight: 700; }
    .print-meta-row { display: flex; flex-wrap: wrap; gap: 6px 22px; font-size: 12px; margin-bottom: 12px; }
    .print-meta-row span { color: #64748b; }
    .print-meta-row strong { color: #1e293b; margin-left: 4px; }
    table { width: 100%; border-collapse: collapse; font-size: 12px; margin-bottom: 12px; }
    th, td { padding: 7px 10px; border-bottom: 1px solid #94a3b8; text-align: left; }
    th { background: #f8fafc; text-transform: uppercase; font-size: 10px; color: #64748b; letter-spacing: .03em; }
    td strong { color: #1e293b; }
    .print-design-line { display: block; font-size: 12px; padding: 3px 0; }
    .print-design-line strong { color: #1e293b; }
    @media print {
        body { padding: 0; }
        .print-item-block { margin-bottom: 20px; }
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

    <?php foreach ($data['items'] as $item) : ?>
        <div class="print-item-block">
            <p class="print-item-title"><?php echo esc_html($item['category'] . (!empty($item['dress_lines']) ? ' — ' . implode(', ', $item['dress_lines']) : '')); ?></p>
            <div class="print-meta-row">
                <span><?php esc_html_e('অর্ডার নং', 'tailor-manager'); ?>:<strong>#<?php echo esc_html($data['order_number']); ?></strong></span>
                <span><?php esc_html_e('ডেলিভারি', 'tailor-manager'); ?>:<strong><?php echo esc_html($data['delivery_date']); ?></strong></span>
                <?php if ($item['cutter']) : ?><span><?php esc_html_e('কাটিং মাস্টার', 'tailor-manager'); ?>:<strong><?php echo esc_html($item['cutter']); ?></strong></span><?php endif; ?>
                <?php if ($item['tailor']) : ?><span><?php esc_html_e('সোয়িং অপারেটর', 'tailor-manager'); ?>:<strong><?php echo esc_html($item['tailor']); ?></strong></span><?php endif; ?>
            </div>

            <?php if ($item['measurements']) : ?>
                <table>
                    <thead>
                        <tr>
                            <?php foreach ($item['measurements'] as $m) : ?>
                                <th><?php echo esc_html($m['label']); ?></th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <?php foreach ($item['measurements'] as $m) : ?>
                                <td><strong><?php echo esc_html($m['value']); ?></strong></td>
                            <?php endforeach; ?>
                        </tr>
                    </tbody>
                </table>
            <?php endif; ?>

            <?php foreach ($item['part_lines'] as $line) : ?>
                <span class="print-design-line"><strong><?php echo esc_html($line['part']); ?>:</strong> <?php echo esc_html(implode(', ', $line['designs'])); ?><?php if ($line['measurement']) : ?> (<?php echo esc_html($line['measurement']); ?>)<?php endif; ?></span>
            <?php endforeach; ?>
        </div>
    <?php endforeach; ?>
</body>
</html>
