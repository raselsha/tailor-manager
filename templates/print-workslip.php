<?php
defined('ABSPATH') || exit;
/** @var array $data */
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title><?php echo esc_html__('ওয়ার্ক স্লিপ', 'tailor-manager'); ?> #<?php echo esc_html($data['order_id']); ?></title>
<style>
    body { font-family: Arial, Helvetica, sans-serif; font-size: 13px; padding: 20px; background: #efefef; }
    table { border-collapse: collapse; width: 100%; max-width: 480px; margin-bottom: 10px; }
    td, th { border: 1px solid #777; padding: 4px 6px; text-align: center; }
    .header { text-align: center; font-weight: bold; margin-bottom: 8px; }
    .block { background: #fff; padding: 8px; margin-bottom: 14px; max-width: 480px; }
    .design-line { display: block; font-size: 12px; padding: 2px 0; }
</style>
</head>
<body onload="window.print()">
    <div class="header"><?php echo esc_html($data['shop_name']); ?></div>

    <?php foreach ($data['items'] as $item) : ?>
        <div class="block">
            <table>
                <tr>
                    <td><?php esc_html_e('অর্ডার নং', 'tailor-manager'); ?> <strong><?php echo esc_html($data['order_id']); ?></strong></td>
                    <td><?php echo esc_html(implode(', ', $item['dress_lines'])); ?></td>
                    <td><?php esc_html_e('ডেলিভারি', 'tailor-manager'); ?><br><?php echo esc_html($data['delivery_date']); ?></td>
                </tr>
            </table>

            <?php if ($item['measurements']) : ?>
                <table>
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

            <?php if ($item['cutter']) : ?>
                <p><strong><?php esc_html_e('কাটিং মাস্টার', 'tailor-manager'); ?>:</strong> <?php echo esc_html($item['cutter']); ?></p>
            <?php endif; ?>
            <?php if ($item['tailor']) : ?>
                <p><strong><?php esc_html_e('সোয়িং অপারেটর', 'tailor-manager'); ?>:</strong> <?php echo esc_html($item['tailor']); ?></p>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
</body>
</html>
