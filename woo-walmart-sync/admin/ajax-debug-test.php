<?php
/**
 * AJAX调试测试页面
 * 在WordPress后台中直接测试AJAX功能
 */

if (!defined('ABSPATH')) {
    exit;
}

// 检查用户权限
if (!current_user_can('manage_options')) {
    wp_die(__('您没有权限访问此页面。'));
}

// 使用最近同步的产品ID
$test_product_ids = [17801, 17800, 17799, 17798, 17797];

// 验证这些产品是否存在
global $wpdb;
$existing_ids = [];
foreach ($test_product_ids as $id) {
    $exists = $wpdb->get_var($wpdb->prepare(
        "SELECT ID FROM {$wpdb->posts} WHERE ID = %d AND post_type = 'product'",
        $id
    ));
    if ($exists) {
        $existing_ids[] = $id;
    }
}
$test_product_ids = $existing_ids;
?>

<div class="wrap">
    <h1>🔧 AJAX调试测试</h1>
    
    <div class="ajax-debug-container">
        <div class="debug-section">
            <h2>📋 测试信息</h2>
            <p><strong>找到的测试产品ID：</strong><?php echo implode(', ', $test_product_ids); ?></p>
            <p><strong>当前时间：</strong><?php echo current_time('Y-m-d H:i:s'); ?></p>
            <p><strong>AJAX URL：</strong><?php echo admin_url('admin-ajax.php'); ?></p>
        </div>
        
        <div class="debug-section">
            <h2>🧪 SKU转换测试</h2>
            <button type="button" id="test-sku-conversion" class="button button-primary">测试SKU转换</button>
            <div id="sku-conversion-result" class="debug-result"></div>
        </div>
        
        <div class="debug-section">
            <h2>🚀 批量同步测试</h2>
            <p><strong>测试选项：</strong></p>
            <label><input type="checkbox" id="force-sync-option" checked> 强制同步</label><br>
            <label><input type="checkbox" id="skip-validation-option"> 跳过验证</label><br><br>

            <button type="button" id="test-batch-sync" class="button button-primary">测试批量同步 (3个产品)</button>
            <button type="button" id="test-single-product" class="button button-secondary">测试单个产品</button>
            <div id="batch-sync-result" class="debug-result"></div>
        </div>
        
        <div class="debug-section">
            <h2>📊 系统状态</h2>
            <div id="system-status">
                <p><strong>内存使用：</strong><?php echo round(memory_get_usage() / 1024 / 1024, 2); ?> MB</p>
                <p><strong>内存限制：</strong><?php echo ini_get('memory_limit'); ?></p>
                <p><strong>执行时间限制：</strong><?php echo ini_get('max_execution_time'); ?>秒</p>
            </div>
        </div>
    </div>
</div>

<style>
.ajax-debug-container {
    max-width: 1200px;
}

.debug-section {
    background: #fff;
    border: 1px solid #ccd0d4;
    border-radius: 4px;
    padding: 20px;
    margin-bottom: 20px;
}

.debug-result {
    margin-top: 15px;
    padding: 10px;
    background: #f8f9fa;
    border-left: 4px solid #007cba;
    font-family: monospace;
    white-space: pre-wrap;
    max-height: 400px;
    overflow-y: auto;
}

.debug-result.success {
    border-left-color: #00a32a;
    background: #f0f8f0;
}

.debug-result.error {
    border-left-color: #d63638;
    background: #f8f0f0;
}

.loading {
    opacity: 0.6;
    pointer-events: none;
}
</style>

<script>
jQuery(document).ready(function($) {
    const testProductIds = <?php echo json_encode($test_product_ids); ?>;
    const ajaxUrl = '<?php echo admin_url('admin-ajax.php'); ?>';
    const nonce = '<?php echo wp_create_nonce('sku_batch_sync_nonce'); ?>';
    
    // 通用AJAX测试函数
    function testAjax(action, data, resultContainer, description) {
        const $container = $(resultContainer);
        const $button = $(`#test-${action.replace('_', '-')}`);
        
        $button.addClass('loading').text('测试中...');
        $container.removeClass('success error').text('正在测试 ' + description + '...');
        
        const startTime = Date.now();
        
        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            data: {
                action: action,
                nonce: nonce,
                ...data
            },
            timeout: 60000, // 60秒超时
            success: function(response) {
                const duration = Date.now() - startTime;
                $container.addClass('success');
                
                let result = `✅ ${description}成功 (耗时: ${duration}ms)\n\n`;
                result += `响应数据:\n${JSON.stringify(response, null, 2)}`;
                
                $container.text(result);
            },
            error: function(xhr, status, error) {
                const duration = Date.now() - startTime;
                $container.addClass('error');
                
                let result = `❌ ${description}失败 (耗时: ${duration}ms)\n\n`;
                result += `状态码: ${xhr.status}\n`;
                result += `状态: ${status}\n`;
                result += `错误: ${error}\n\n`;
                
                if (xhr.responseText) {
                    result += `响应内容:\n${xhr.responseText}`;
                }
                
                // 特殊处理500错误
                if (xhr.status === 500) {
                    result += `\n\n🔍 500错误分析:\n`;
                    result += `这通常表示服务器端PHP错误。\n`;
                    result += `请检查 wp-content/debug.log 文件获取详细错误信息。`;
                }
                
                $container.text(result);
            },
            complete: function() {
                $button.removeClass('loading').text($button.data('original-text'));
            }
        });
    }
    
    // 保存按钮原始文本
    $('.button').each(function() {
        $(this).data('original-text', $(this).text());
    });
    
    // SKU转换测试
    $('#test-sku-conversion').on('click', function() {
        testAjax('convert_skus_to_product_ids', {
            sku_list: ['TEST001', 'TEST002', 'NONEXISTENT']
        }, '#sku-conversion-result', 'SKU转换');
    });
    
    // 批量同步测试
    $('#test-batch-sync').on('click', function() {
        if (testProductIds.length === 0) {
            $('#batch-sync-result').addClass('error').text('❌ 没有找到测试产品ID');
            return;
        }

        const forceSync = $('#force-sync-option').is(':checked') ? 1 : 0;
        const skipValidation = $('#skip-validation-option').is(':checked') ? 1 : 0;

        testAjax('walmart_batch_sync_products', {
            product_ids: testProductIds.slice(0, 3), // 只测试前3个
            force_sync: forceSync,
            skip_validation: skipValidation
        }, '#batch-sync-result', '批量同步');
    });

    // 单个产品测试
    $('#test-single-product').on('click', function() {
        if (testProductIds.length === 0) {
            $('#batch-sync-result').addClass('error').text('❌ 没有找到测试产品ID');
            return;
        }

        const forceSync = $('#force-sync-option').is(':checked') ? 1 : 0;
        const skipValidation = $('#skip-validation-option').is(':checked') ? 1 : 0;

        testAjax('walmart_batch_sync_products', {
            product_ids: [testProductIds[0]], // 只测试第一个产品
            force_sync: forceSync,
            skip_validation: skipValidation
        }, '#batch-sync-result', '单个产品同步');
    });
    
    // 页面加载时显示基本信息
    console.log('🔧 AJAX调试页面已加载');
    console.log('测试产品ID:', testProductIds);
    console.log('AJAX URL:', ajaxUrl);
});
</script>
