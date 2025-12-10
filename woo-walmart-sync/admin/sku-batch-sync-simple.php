<?php
/**
 * SKU批量同步页面 - 简化版
 */

// 防止直接访问
if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap">
    <h1>🚀 SKU批量同步</h1>
    
    <div class="sku-batch-sync-container">
        <!-- SKU输入区域 -->
        <div class="input-section">
            <h2>📝 输入SKU列表</h2>
            <p>每行输入一个SKU，最多支持1000个SKU</p>
            
            <textarea id="sku-list-input" placeholder="请输入SKU列表，每行一个SKU，例如：
SKU001
SKU002
SKU003" rows="15" style="width: 100%; font-family: monospace;"></textarea>
            
            <div class="action-buttons" style="margin-top: 15px;">
                <button type="button" id="start-sync-btn" class="button button-primary button-large">
                    🚀 开始批量同步
                </button>
                <button type="button" id="clear-input-btn" class="button">
                    🗑️ 清空输入
                </button>
            </div>
        </div>

        <!-- 同步选项 -->
        <div class="sync-options" style="margin-top: 20px;">
            <h3>🔧 同步选项</h3>
            <label style="display: block; margin: 10px 0;">
                <input type="checkbox" id="force-sync" checked>
                强制同步 (覆盖已存在的商品)
            </label>
            <label style="display: block; margin: 10px 0;">
                <input type="checkbox" id="skip-validation">
                跳过验证 (加快同步速度)
            </label>
        </div>

        <!-- 同步进度区域 -->
        <div id="sync-progress-section" style="display: none; margin-top: 30px;">
            <h2>📊 同步进度</h2>
            <div id="progress-info"></div>
            <div id="progress-bar" style="width: 100%; background: #f1f1f1; border-radius: 5px; overflow: hidden; margin: 10px 0;">
                <div id="progress-fill" style="height: 30px; background: #0073aa; width: 0%; transition: width 0.3s;"></div>
            </div>
            <div id="sync-results"></div>
        </div>
    </div>
</div>

<style>
.sku-batch-sync-container {
    max-width: 800px;
}

.input-section {
    background: #fff;
    padding: 20px;
    border: 1px solid #ccd0d4;
    border-radius: 4px;
    margin-bottom: 20px;
}

.sync-options {
    background: #fff;
    padding: 20px;
    border: 1px solid #ccd0d4;
    border-radius: 4px;
}

#sync-progress-section {
    background: #fff;
    padding: 20px;
    border: 1px solid #ccd0d4;
    border-radius: 4px;
}

.action-buttons {
    text-align: left;
}

.button-large {
    height: 40px;
    line-height: 38px;
    padding: 0 20px;
    font-size: 16px;
}
</style>

<script>
jQuery(document).ready(function($) {
    // 绑定事件
    $('#start-sync-btn').on('click', startBatchSync);
    $('#clear-input-btn').on('click', clearInput);
    
    // 开始批量同步
    function startBatchSync() {
        const skuText = $('#sku-list-input').val().trim();
        
        if (!skuText) {
            alert('请输入SKU列表');
            return;
        }
        
        // 解析SKU列表
        const skuList = parseSkuList(skuText);
        
        if (skuList.length === 0) {
            alert('没有找到有效的SKU');
            return;
        }
        
        if (skuList.length > 1000) {
            alert('一次最多只能同步1000个产品，请减少SKU数量');
            return;
        }
        
        // 确认开始同步
        if (!confirm(`确定要同步 ${skuList.length} 个SKU吗？\n\n注意：\n• 不存在的SKU会被跳过\n• 没有分类映射的产品会同步失败\n• 同步过程可能需要几分钟时间`)) {
            return;
        }
        
        // 将SKU转换为产品ID并开始同步
        convertSkusAndSync(skuList);
    }
    
    // 解析SKU列表
    function parseSkuList(text) {
        const lines = text.split(/\r?\n/);
        const skuList = [];
        const seen = new Set();
        
        lines.forEach(line => {
            const sku = line.trim();
            if (sku && !seen.has(sku)) {
                skuList.push(sku);
                seen.add(sku);
            }
        });
        
        return skuList;
    }
    
    // 转换SKU为产品ID并开始同步
    function convertSkusAndSync(skuList) {
        $('#start-sync-btn').prop('disabled', true).text('🔄 准备同步...');
        $('#sync-progress-section').show();
        $('#progress-info').text('正在查找产品...');
        
        // 查找产品ID
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'convert_skus_to_product_ids',
                sku_list: skuList,
                nonce: '<?php echo wp_create_nonce("sku_batch_sync_nonce"); ?>'
            },
            success: function(response) {
                if (response.success && response.data.product_ids.length > 0) {
                    // 开始批量同步
                    startWalmartSync(response.data.product_ids);
                } else {
                    alert('没有找到有效的产品，请检查SKU是否正确');
                    resetSyncState();
                }
            },
            error: function() {
                alert('查找产品失败，请重试');
                resetSyncState();
            }
        });
    }
    
    // 开始Walmart同步
    function startWalmartSync(productIds) {
        const forceSync = $('#force-sync').is(':checked');
        const skipValidation = $('#skip-validation').is(':checked');
        
        $('#progress-info').text(`开始同步 ${productIds.length} 个产品...`);
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'walmart_batch_sync_products',
                product_ids: productIds,
                force_sync: forceSync,
                skip_validation: skipValidation,
                nonce: '<?php echo wp_create_nonce("sku_batch_sync_nonce"); ?>'
            },
            success: function(response) {
                if (response.success) {
                    $('#progress-info').text('同步完成！');
                    $('#progress-fill').css('width', '100%');
                    $('#sync-results').html(`
                        <div style="margin-top: 20px; padding: 15px; background: #d4edda; border: 1px solid #c3e6cb; border-radius: 4px;">
                            <h4>✅ 同步成功</h4>
                            <p>已成功提交 ${productIds.length} 个产品到Walmart进行同步</p>
                            <p>您可以在 <a href="admin.php?page=woo-walmart-sync-status">同步状态</a> 页面查看详细进度</p>
                        </div>
                    `);
                } else {
                    $('#sync-results').html(`
                        <div style="margin-top: 20px; padding: 15px; background: #f8d7da; border: 1px solid #f5c6cb; border-radius: 4px;">
                            <h4>❌ 同步失败</h4>
                            <p>${response.data.message || '未知错误'}</p>
                        </div>
                    `);
                }
                resetSyncState();
            },
            error: function() {
                $('#sync-results').html(`
                    <div style="margin-top: 20px; padding: 15px; background: #f8d7da; border: 1px solid #f5c6cb; border-radius: 4px;">
                        <h4>❌ 同步失败</h4>
                        <p>网络错误，请重试</p>
                    </div>
                `);
                resetSyncState();
            }
        });
    }
    
    // 重置同步状态
    function resetSyncState() {
        $('#start-sync-btn').prop('disabled', false).text('🚀 开始批量同步');
    }
    
    // 清空输入
    function clearInput() {
        $('#sku-list-input').val('');
        $('#sync-progress-section').hide();
    }
});
</script>
