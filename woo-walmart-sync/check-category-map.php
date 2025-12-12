<?php
require_once(__DIR__ . '/../../../wp-load.php');
global $wpdb;

$rows = $wpdb->get_results('SELECT * FROM ' . $wpdb->prefix . 'walmart_category_map LIMIT 5', ARRAY_A);

echo "分类映射表结构:\n";
if (!empty($rows)) {
    echo "列: " . implode(', ', array_keys($rows[0])) . "\n\n";
    foreach ($rows as $row) {
        echo "wc_category_id: " . $row['wc_category_id'] . "\n";
        echo "walmart_category_path: " . $row['walmart_category_path'] . "\n";
        echo "walmart_category_id: " . ($row['walmart_category_id'] ?? 'N/A') . "\n";
        echo "---\n";
    }
}
