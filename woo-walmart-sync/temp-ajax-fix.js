/**
 * 临时AJAX错误处理修复
 * 在批量同步页面中使用，处理500错误但实际成功的情况
 */

// 重写批量同步函数，增加更好的错误处理
function startWalmartSyncWithFallback(productIds) {
    const forceSync = $('#force-sync').is(':checked');
    const skipValidation = $('#skip-validation').is(':checked');
    
    showProgress(`开始同步 ${productIds.length} 个产品...`);
    
    $.ajax({
        url: ajaxurl,
        type: 'POST',
        data: {
            action: 'walmart_batch_sync_products',
            product_ids: productIds,
            force_sync: forceSync ? 1 : 0,
            skip_validation: skipValidation ? 1 : 0,
            nonce: $('input[name="batch_sync_nonce"]').val() || '<?php echo wp_create_nonce("sku_batch_sync_nonce"); ?>'
        },
        timeout: 300000, // 5分钟超时
        success: function(response) {
            console.log('✅ AJAX成功响应:', response);
            
            if (response.success) {
                showSuccess(`
                    <h3>✅ 同步提交成功</h3>
                    <p>已成功提交 <strong>${productIds.length}</strong> 个产品到Walmart进行同步</p>
                    <p>批次ID: <code>${response.data.batch_id || '未知'}</code></p>
                    <p>Feed ID: <code>${response.data.feed_id || '处理中'}</code></p>
                    <p>您可以在 <a href="admin.php?page=woo-walmart-sync-status" target="_blank">同步状态页面</a> 查看详细进度</p>
                    <button type="button" class="button" onclick="location.reload()">开始新的同步</button>
                `);
            } else {
                showError(`
                    <h3>❌ 同步提交失败</h3>
                    <p>${response.data.message || '未知错误'}</p>
                    <button type="button" class="button" onclick="location.reload()">重新尝试</button>
                `);
            }
            disableButtons(false);
        },
        error: function(xhr, status, error) {
            console.log('❌ AJAX错误响应:', {xhr, status, error});
            
            // 🔧 特殊处理：检查是否是500错误但实际成功的情况
            if (xhr.status === 500) {
                console.log('检测到500错误，尝试检查是否实际成功...');
                
                // 延迟检查批次状态
                setTimeout(function() {
                    checkBatchStatusFallback(productIds.length);
                }, 3000);
                
            } else {
                // 其他错误正常处理
                let errorMessage = '网络请求失败';
                if (xhr.responseJSON && xhr.responseJSON.data) {
                    errorMessage = xhr.responseJSON.data.message || xhr.responseJSON.data;
                } else if (xhr.responseText) {
                    errorMessage = xhr.responseText;
                } else if (error) {
                    errorMessage = error;
                }
                
                showError(`
                    <h3>❌ 同步请求失败</h3>
                    <p>${errorMessage}</p>
                    <button type="button" class="button" onclick="location.reload()">重新尝试</button>
                `);
                disableButtons(false);
            }
        }
    });
}

// 检查批次状态的回退方法
function checkBatchStatusFallback(expectedProductCount) {
    console.log('执行批次状态回退检查...');
    
    $.ajax({
        url: ajaxurl,
        type: 'POST',
        data: {
            action: 'check_recent_batch_status',
            expected_count: expectedProductCount,
            nonce: $('input[name="batch_sync_nonce"]').val()
        },
        success: function(response) {
            console.log('批次状态检查结果:', response);
            
            if (response.success && response.data.found_recent_batch) {
                // 找到了最近的成功批次
                showSuccess(`
                    <h3>✅ 同步已成功提交</h3>
                    <p>检测到您的同步请求已成功处理</p>
                    <p>批次ID: <code>${response.data.batch_id}</code></p>
                    <p>Feed ID: <code>${response.data.feed_id || '处理中'}</code></p>
                    <p>产品数量: <strong>${response.data.product_count}</strong></p>
                    <p>您可以在 <a href="admin.php?page=woo-walmart-sync-status" target="_blank">同步状态页面</a> 查看详细进度</p>
                    <button type="button" class="button" onclick="location.reload()">开始新的同步</button>
                `);
            } else {
                // 没有找到成功的批次，显示错误
                showError(`
                    <h3>❌ 同步状态未知</h3>
                    <p>无法确认同步是否成功，请检查同步状态页面或重新尝试</p>
                    <button type="button" class="button" onclick="location.reload()">重新尝试</button>
                `);
            }
            disableButtons(false);
        },
        error: function() {
            showError(`
                <h3>⚠️ 状态检查失败</h3>
                <p>无法检查同步状态，请手动查看同步状态页面</p>
                <p><a href="admin.php?page=woo-walmart-sync-status" target="_blank">查看同步状态</a></p>
                <button type="button" class="button" onclick="location.reload()">重新尝试</button>
            `);
            disableButtons(false);
        }
    });
}

// 使用说明
console.log('🔧 临时AJAX修复已加载');
console.log('使用 startWalmartSyncWithFallback(productIds) 替代原来的同步函数');
