<?php
require_once 'D:/phpstudy_pro/WWW/test.localhost/wp-config.php';
require_once 'D:/phpstudy_pro/WWW/test.localhost/wp-load.php';

global $wpdb;

$fields = ['luggage_inner_dimension_depth', 'height_with_handle_extended', 'luggage_overall_dimension_depth'];

foreach ($fields as $field) {
    $spec = $wpdb->get_row($wpdb->prepare(
        "SELECT attribute_type, default_type, validation_rules FROM {$wpdb->prefix}walmart_product_attributes WHERE product_type_id = %s AND attribute_name = %s",
        'Luggage & Luggage Sets', $field
    ));
    
    if ($spec) {
        echo "$field:\n";
        echo "  attribute_type: {$spec->attribute_type}\n";
        echo "  default_type: {$spec->default_type}\n";
        echo "  validation_rules: {$spec->validation_rules}\n";
        echo "\n";
    }
}
?>
