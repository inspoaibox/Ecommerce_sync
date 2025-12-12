<?php
/**
 * SKU批量同步页面
 * 
 * @package WooWalmartSync
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

// 检查用户权限
if (!current_user_can('manage_options')) {
    wp_die(__('您没有权限访问此页面。'));
}

// AJAX处理器在主插件文件中注册

?>

<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    
    <div class="sku-batch-sync-container">
        <!-- SKU输入区域 -->
        <div class="sku-input-section">
            <h2>📝 SKU列表输入</h2>
            <div class="input-group">
                <label for="sku-list-input">
                    <strong>请输入要同步的SKU列表（每行一个SKU）：</strong>
                </label>
                <textarea 
                    id="sku-list-input" 
                    class="sku-textarea" 
                    rows="15" 
                    placeholder="请输入SKU，每行一个，例如：&#10;W2791P306821&#10;W2792P306822&#10;W2793P306823"
                ></textarea>
                <div class="input-help">
                    <p>💡 <strong>使用说明：</strong></p>
                    <ul>
                        <li>每行输入一个SKU</li>
                        <li>支持复制粘贴Excel列表</li>
                        <li>空行和重复SKU会自动过滤</li>
                        <li>最多支持一次同步500个产品</li>
                    </ul>
                </div>
            </div>
            
            <div class="action-buttons">
                <button type="button" id="validate-sku-btn" class="button button-secondary">
                    🔍 验证SKU列表
                </button>
                <button type="button" id="clear-input-btn" class="button">
                    🗑️ 清空输入
                </button>
            </div>
        </div>

        <!-- 验证结果区域 -->
        <div class="validation-section" id="validation-section" style="display: none;">
            <h2>✅ SKU验证结果</h2>
            <div class="validation-summary">
                <div class="summary-stats">
                    <span class="stat-item">
                        <strong>总计：</strong>
                        <span id="total-sku-count">0</span>
                    </span>
                    <span class="stat-item valid">
                        <strong>有效：</strong>
                        <span id="valid-sku-count">0</span>
                    </span>
                    <span class="stat-item invalid">
                        <strong>无效：</strong>
                        <span id="invalid-sku-count">0</span>
                    </span>
                    <span class="stat-item unmapped">
                        <strong>未映射：</strong>
                        <span id="unmapped-sku-count">0</span>
                    </span>
                </div>
            </div>
            
            <div class="validation-details">
                <div class="valid-products" id="valid-products">
                    <h3>✅ 可同步产品</h3>
                    <div class="product-list"></div>
                </div>
                
                <div class="invalid-products" id="invalid-products" style="display: none;">
                    <h3>❌ 无效SKU</h3>
                    <div class="product-list"></div>
                </div>
                
                <div class="unmapped-products" id="unmapped-products" style="display: none;">
                    <h3>⚠️ 未映射分类</h3>
                    <div class="product-list"></div>
                </div>
            </div>
            
            <div class="sync-options">
                <h3>🔧 同步选项</h3>
                <label>
                    <input type="checkbox" id="force-sync" value="1">
                    强制同步（忽略上次同步时间限制）
                </label>
                <label>
                    <input type="checkbox" id="skip-validation" value="1">
                    跳过产品验证（加快同步速度）
                </label>
            </div>
            
            <div class="sync-actions">
                <div class="sync-buttons-group">
                    <button type="button" id="start-batch-sync-btn" class="button button-primary" disabled>
                        🚀 开始批量同步
                    </button>
                    <button type="button" id="start-single-sync-btn" class="button button-secondary" disabled>
                        🔄 开始单个同步
                    </button>
                </div>
                <div class="sync-buttons-help">
                    <p><strong>同步方式说明：</strong></p>
                    <ul>
                        <li><strong>批量同步</strong>：将所有产品打包成一个Feed提交，效率更高，适合大量产品</li>
                        <li><strong>单个同步</strong>：逐个产品分别提交，每个产品独立的Feed ID，错误隔离更好</li>
                    </ul>
                    <p><strong>建议：</strong>5个以上产品使用批量同步，5个以下产品使用单个同步</p>
                </div>
                <button type="button" id="export-results-btn" class="button button-secondary">
                    📊 导出验证结果
                </button>
            </div>
        </div>

        <!-- 同步进度区域 -->
        <div class="sync-progress-section" id="sync-progress-section" style="display: none;">
            <h2>⏳ 同步进度</h2>
            <div class="progress-container">
                <div class="progress-bar">
                    <div class="progress-fill" id="progress-fill"></div>
                </div>
                <div class="progress-text">
                    <span id="progress-current">0</span> / 
                    <span id="progress-total">0</span> 
                    (<span id="progress-percentage">0%</span>)
                </div>
            </div>
            
            <div class="sync-status">
                <div class="status-item">
                    <strong>当前状态：</strong>
                    <span id="current-status">准备中...</span>
                </div>
                <div class="status-item">
                    <strong>当前产品：</strong>
                    <span id="current-product">-</span>
                </div>
                <div class="status-item">
                    <strong>预计剩余时间：</strong>
                    <span id="estimated-time">计算中...</span>
                </div>
            </div>
            
            <div class="sync-actions">
                <button type="button" id="pause-sync-btn" class="button button-secondary">
                    ⏸️ 暂停同步
                </button>
                <button type="button" id="stop-sync-btn" class="button button-secondary">
                    ⏹️ 停止同步
                </button>
            </div>
        </div>

        <!-- 同步结果区域 -->
        <div class="sync-results-section" id="sync-results-section" style="display: none;">
            <h2>📊 同步结果</h2>
            <div class="results-summary">
                <div class="summary-stats">
                    <span class="stat-item success">
                        <strong>成功：</strong>
                        <span id="success-count">0</span>
                    </span>
                    <span class="stat-item failed">
                        <strong>失败：</strong>
                        <span id="failed-count">0</span>
                    </span>
                    <span class="stat-item skipped">
                        <strong>跳过：</strong>
                        <span id="skipped-count">0</span>
                    </span>
                </div>
                <div class="total-time">
                    <strong>总耗时：</strong>
                    <span id="total-sync-time">-</span>
                </div>
            </div>
            
            <div class="results-details">
                <div class="success-products" id="success-products">
                    <h3>✅ 同步成功</h3>
                    <div class="product-list"></div>
                </div>
                
                <div class="failed-products" id="failed-products">
                    <h3>❌ 同步失败</h3>
                    <div class="product-list"></div>
                </div>
            </div>
            
            <div class="results-actions">
                <button type="button" id="download-report-btn" class="button button-primary">
                    📥 下载详细报告
                </button>
                <button type="button" id="retry-failed-btn" class="button button-secondary">
                    🔄 重试失败项目
                </button>
                <button type="button" id="new-sync-btn" class="button button-secondary">
                    🆕 开始新的同步
                </button>
            </div>
        </div>
    </div>
</div>

<!-- 样式 -->
<style>
.sku-batch-sync-container {
    max-width: 1200px;
    margin: 20px 0;
}

.sku-input-section,
.validation-section,
.sync-progress-section,
.sync-results-section {
    background: #fff;
    border: 1px solid #ccd0d4;
    border-radius: 4px;
    padding: 20px;
    margin-bottom: 20px;
    box-shadow: 0 1px 1px rgba(0,0,0,.04);
}

.sku-textarea {
    width: 100%;
    max-width: 800px;
    font-family: 'Courier New', monospace;
    font-size: 14px;
    line-height: 1.4;
    border: 1px solid #ddd;
    border-radius: 4px;
    padding: 10px;
    resize: vertical;
}

.input-help {
    margin-top: 10px;
    padding: 10px;
    background: #f0f8ff;
    border-left: 4px solid #0073aa;
    border-radius: 4px;
}

.input-help ul {
    margin: 5px 0 0 20px;
}

.action-buttons,
.sync-actions,
.results-actions {
    margin-top: 15px;
}

.action-buttons button,
.sync-actions button,
.results-actions button {
    margin-right: 10px;
}

.summary-stats {
    display: flex;
    gap: 20px;
    flex-wrap: wrap;
    margin-bottom: 15px;
}

.stat-item {
    padding: 8px 12px;
    border-radius: 4px;
    background: #f1f1f1;
}

.stat-item.valid { background: #d4edda; color: #155724; }
.stat-item.invalid { background: #f8d7da; color: #721c24; }
.stat-item.unmapped { background: #fff3cd; color: #856404; }
.stat-item.success { background: #d4edda; color: #155724; }
.stat-item.failed { background: #f8d7da; color: #721c24; }
.stat-item.skipped { background: #e2e3e5; color: #383d41; }

.progress-container {
    margin: 15px 0;
}

.progress-bar {
    width: 100%;
    height: 20px;
    background: #f1f1f1;
    border-radius: 10px;
    overflow: hidden;
    margin-bottom: 10px;
}

.progress-fill {
    height: 100%;
    background: linear-gradient(90deg, #0073aa, #005177);
    width: 0%;
    transition: width 0.3s ease;
}

.progress-text {
    text-align: center;
    font-weight: bold;
}

.sync-status {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 15px;
    margin: 15px 0;
}

.status-item {
    padding: 10px;
    background: #f8f9fa;
    border-radius: 4px;
}

.product-list {
    max-height: 300px;
    overflow-y: auto;
    border: 1px solid #ddd;
    border-radius: 4px;
    padding: 10px;
    background: #fafafa;
}

.sync-options {
    margin: 15px 0;
    padding: 15px;
    background: #f8f9fa;
    border-radius: 4px;
}

.sync-options label {
    display: block;
    margin-bottom: 8px;
    cursor: pointer;
}

.sync-options input[type="checkbox"] {
    margin-right: 8px;
}

.sync-buttons-group {
    display: flex;
    gap: 10px;
    margin-bottom: 15px;
}

.sync-buttons-help {
    background: #f8f9fa;
    border: 1px solid #dee2e6;
    border-radius: 4px;
    padding: 12px;
    margin-bottom: 15px;
    font-size: 13px;
}

.sync-buttons-help p {
    margin: 0 0 8px 0;
    font-weight: bold;
}

.sync-buttons-help ul {
    margin: 0;
    padding-left: 20px;
}

.sync-buttons-help li {
    margin-bottom: 4px;
}

.sync-buttons-help strong {
    color: #0073aa;
}

.validation-details > div {
    margin-bottom: 20px;
}

.validation-details h3 {
    margin-bottom: 10px;
    padding-bottom: 5px;
    border-bottom: 2px solid #ddd;
}

.results-summary {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    margin-bottom: 20px;
}

.total-time {
    font-size: 16px;
    font-weight: bold;
}

@media (max-width: 768px) {
    .summary-stats {
        flex-direction: column;
        gap: 10px;
    }
    
    .results-summary {
        flex-direction: column;
        align-items: flex-start;
        gap: 15px;
    }
    
    .sync-status {
        grid-template-columns: 1fr;
    }
}
</style>

<!-- JavaScript -->
<script>
jQuery(document).ready(function($) {
    // 页面初始化
    initSkuBatchSync();
    
    function initSkuBatchSync() {
        // 绑定事件
        $('#validate-sku-btn').on('click', validateSkuList);
        $('#clear-input-btn').on('click', clearInput);
        $('#start-batch-sync-btn').on('click', startBatchSync);
        $('#start-single-sync-btn').on('click', startSingleSync);
        $('#export-results-btn').on('click', exportValidationResults);
        $('#download-report-btn').on('click', downloadSyncReport);
        $('#retry-failed-btn').on('click', retryFailedItems);
        $('#new-sync-btn').on('click', startNewSync);
        
        // 输入框变化时重置验证状态
        $('#sku-list-input').on('input', function() {
            resetValidationState();
        });
    }
    
    // 验证SKU列表
    function validateSkuList() {
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
        
        if (skuList.length > 500) {
            alert('一次最多只能同步500个产品，请减少SKU数量');
            return;
        }
        
        // 显示加载状态
        $('#validate-sku-btn').prop('disabled', true).text('🔄 验证中...');
        
        // 发送AJAX请求验证SKU
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'validate_sku_list',
                sku_list: skuList,
                nonce: '<?php echo wp_create_nonce("sku_batch_sync_nonce"); ?>'
            },
            success: function(response) {
                if (response.success) {
                    displayValidationResults(response.data);
                } else {
                    alert('验证失败：' + response.data);
                }
            },
            error: function() {
                alert('验证请求失败，请重试');
            },
            complete: function() {
                $('#validate-sku-btn').prop('disabled', false).text('🔍 验证SKU列表');
            }
        });
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
    
    // 显示验证结果
    function displayValidationResults(data) {
        // 更新统计数据
        $('#total-sku-count').text(data.total);
        $('#valid-sku-count').text(data.valid.length);
        $('#invalid-sku-count').text(data.invalid.length);
        $('#unmapped-sku-count').text(data.unmapped.length);
        
        // 显示有效产品
        displayProductList('#valid-products .product-list', data.valid, 'valid');
        
        // 显示无效SKU
        if (data.invalid.length > 0) {
            displayProductList('#invalid-products .product-list', data.invalid, 'invalid');
            $('#invalid-products').show();
        } else {
            $('#invalid-products').hide();
        }
        
        // 显示未映射产品
        if (data.unmapped.length > 0) {
            displayProductList('#unmapped-products .product-list', data.unmapped, 'unmapped');
            $('#unmapped-products').show();
        } else {
            $('#unmapped-products').hide();
        }
        
        // 启用/禁用同步按钮
        const hasValidProducts = data.valid.length > 0;
        $('#start-batch-sync-btn').prop('disabled', !hasValidProducts);
        $('#start-single-sync-btn').prop('disabled', !hasValidProducts);
        
        // 显示验证结果区域
        $('#validation-section').show();
        
        // 滚动到验证结果
        $('html, body').animate({
            scrollTop: $('#validation-section').offset().top - 50
        }, 500);
    }
    
    // 显示产品列表
    function displayProductList(selector, products, type) {
        const container = $(selector);
        container.empty();
        
        if (products.length === 0) {
            container.html('<p>无数据</p>');
            return;
        }
        
        const list = $('<ul></ul>');
        
        products.forEach(item => {
            const listItem = $('<li></li>');
            
            if (type === 'valid') {
                listItem.html(`
                    <strong>${item.sku}</strong> - ${item.name}
                    <br><small>产品ID：${item.product_id} | 分类：${item.category} | 状态：${item.status}</small>
                `);
            } else if (type === 'invalid') {
                listItem.html(`
                    <strong>${item.sku}</strong> - ${item.reason}
                `);
            } else if (type === 'unmapped') {
                listItem.html(`
                    <strong>${item.sku}</strong> - ${item.name}
                    <br><small>分类：${item.category} | 原因：${item.reason}</small>
                `);
            }
            
            list.append(listItem);
        });
        
        container.append(list);
    }
    
    // 开始批量同步（真正的批量Feed）
    function startBatchSync() {
        // 获取有效的产品ID列表
        const validProducts = getValidProductIds();

        if (validProducts.length === 0) {
            alert('没有可同步的产品');
            return;
        }

        // 检查产品数量限制
        if (validProducts.length > 10000) {
            alert('批量同步最多支持10000个产品，请减少产品数量');
            return;
        }

        // 获取同步选项
        const options = {
            force_sync: $('#force-sync').is(':checked'),
            skip_validation: $('#skip-validation').is(':checked')
        };

        // 隐藏验证结果，显示进度区域
        $('#validation-section').hide();
        $('#sync-progress-section').show();

        // 初始化进度
        initSyncProgress(1); // 批量同步只有一个进度步骤

        // 开始批量同步
        executeBatchFeedSync(validProducts, options);
    }

    // 开始单个同步（逐个产品同步）
    function startSingleSync() {
        // 获取有效的产品ID列表
        const validProducts = getValidProductIds();

        if (validProducts.length === 0) {
            alert('没有可同步的产品');
            return;
        }

        // 获取同步选项
        const options = {
            force_sync: $('#force-sync').is(':checked'),
            skip_validation: $('#skip-validation').is(':checked')
        };

        // 隐藏验证结果，显示进度区域
        $('#validation-section').hide();
        $('#sync-progress-section').show();

        // 初始化进度
        initSyncProgress(validProducts.length);

        // 开始单个同步
        executeSingleSync(validProducts, options);
    }

    // 执行批量Feed同步
    function executeBatchFeedSync(productIds, options) {
        const startTime = Date.now();
        window.syncStartTime = startTime;

        // 更新进度状态
        $('#current-status').text('准备批量同步...');
        $('#current-product').text(`${productIds.length}个产品`);
        $('#progress-current').text(0);
        $('#progress-total').text(1);
        $('#progress-percentage').text('0%');
        $('#progress-fill').css('width', '0%');

        // 发送批量同步请求
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'walmart_batch_sync_products',
                product_ids: productIds,
                force_sync: options.force_sync ? 1 : 0,
                skip_validation: options.skip_validation ? 1 : 0,
                nonce: '<?php echo wp_create_nonce("sku_batch_sync_nonce"); ?>'
            },
            success: function(response) {
                console.log('批量同步结果:', response);

                // 更新进度为完成
                $('#progress-current').text(1);
                $('#progress-percentage').text('100%');
                $('#progress-fill').css('width', '100%');
                $('#current-status').text('批量同步完成');

                // 处理批量同步结果
                processBatchSyncResult(productIds, response, startTime);
            },
            error: function(xhr, status, error) {
                console.error('批量同步错误:', xhr, status, error);

                let errorMessage = '批量同步请求失败';
                if (xhr.responseJSON && xhr.responseJSON.data) {
                    errorMessage = xhr.responseJSON.data;
                } else if (xhr.responseText) {
                    errorMessage = xhr.responseText;
                } else if (error) {
                    errorMessage = error;
                }

                // 处理批量同步错误
                processBatchSyncError(productIds, errorMessage, startTime);
            }
        });
    }

    // 处理批量同步结果
    function processBatchSyncResult(productIds, response, startTime) {
        const totalTime = Date.now() - startTime;
        const minutes = Math.floor(totalTime / 60000);
        const seconds = Math.floor((totalTime % 60000) / 1000);

        // 隐藏进度区域，显示结果区域
        $('#sync-progress-section').hide();
        $('#sync-results-section').show();

        if (response.success) {
            // 批量同步成功
            const feedId = response.data.feedId || '未知';
            const message = response.data.message || '批量同步请求已提交';

            // 所有产品标记为成功（等待Walmart处理）
            window.syncResults.success = productIds.map(productId => ({
                product_id: productId,
                message: `${message}，Feed ID: ${feedId}`
            }));

            window.syncResults.failed = [];
            window.syncResults.skipped = [];

        } else {
            // 批量同步失败
            const errorMessage = response.data || '批量同步失败';

            // 所有产品标记为失败
            window.syncResults.success = [];
            window.syncResults.failed = productIds.map(productId => ({
                product_id: productId,
                reason: errorMessage
            }));
            window.syncResults.skipped = [];
        }

        // 更新结果统计
        $('#success-count').text(window.syncResults.success.length);
        $('#failed-count').text(window.syncResults.failed.length);
        $('#skipped-count').text(window.syncResults.skipped.length);
        $('#total-sync-time').text(minutes + '分' + seconds + '秒');

        // 显示详细结果
        displaySyncResults();

        // 滚动到结果区域
        $('html, body').animate({
            scrollTop: $('#sync-results-section').offset().top - 50
        }, 500);
    }

    // 处理批量同步错误
    function processBatchSyncError(productIds, errorMessage, startTime) {
        const totalTime = Date.now() - startTime;
        const minutes = Math.floor(totalTime / 60000);
        const seconds = Math.floor((totalTime % 60000) / 1000);

        // 隐藏进度区域，显示结果区域
        $('#sync-progress-section').hide();
        $('#sync-results-section').show();

        // 所有产品标记为失败
        window.syncResults.success = [];
        window.syncResults.failed = productIds.map(productId => ({
            product_id: productId,
            reason: errorMessage
        }));
        window.syncResults.skipped = [];

        // 更新结果统计
        $('#success-count').text(0);
        $('#failed-count').text(productIds.length);
        $('#skipped-count').text(0);
        $('#total-sync-time').text(minutes + '分' + seconds + '秒');

        // 显示详细结果
        displaySyncResults();

        // 滚动到结果区域
        $('html, body').animate({
            scrollTop: $('#sync-results-section').offset().top - 50
        }, 500);
    }

    // 获取有效产品ID列表
    function getValidProductIds() {
        const validProducts = [];
        $('#valid-products .product-list li').each(function() {
            const text = $(this).text();
            const match = text.match(/产品ID：(\d+)/);
            if (match) {
                validProducts.push(parseInt(match[1]));
            }
        });
        return validProducts;
    }

    // 初始化同步进度
    function initSyncProgress(total) {
        $('#progress-total').text(total);
        $('#progress-current').text(0);
        $('#progress-percentage').text('0%');
        $('#progress-fill').css('width', '0%');
        $('#current-status').text('准备同步...');
        $('#current-product').text('-');
        $('#estimated-time').text('计算中...');
    }

    // 执行单个同步（逐个产品同步）
    function executeSingleSync(productIds, options) {
        const startTime = Date.now();
        let currentIndex = 0;

        function syncNext() {
            if (currentIndex >= productIds.length) {
                // 同步完成
                completeBatchSync(startTime);
                return;
            }

            const productId = productIds[currentIndex];
            updateSyncProgress(currentIndex + 1, productIds.length, productId);

            // 发送同步请求
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'walmart_sync_product',
                    product_id: productId,
                    force_sync: options.force_sync ? 1 : 0,
                    skip_validation: options.skip_validation ? 1 : 0
                },
                success: function(response) {
                    // 处理同步结果
                    processSyncResult(productId, response);
                },
                error: function(xhr, status, error) {
                    // 处理同步错误
                    let errorMessage = '网络请求失败';
                    if (xhr.responseJSON && xhr.responseJSON.data) {
                        errorMessage = xhr.responseJSON.data;
                    } else if (xhr.responseText) {
                        errorMessage = xhr.responseText;
                    } else if (error) {
                        errorMessage = error;
                    }
                    processSyncError(productId, errorMessage);
                },
                complete: function() {
                    currentIndex++;
                    // 延迟执行下一个，避免服务器压力
                    setTimeout(syncNext, 1000);
                }
            });
        }

        // 开始同步
        syncNext();
    }

    // 更新同步进度
    function updateSyncProgress(current, total, productId) {
        const percentage = Math.round((current / total) * 100);

        $('#progress-current').text(current);
        $('#progress-percentage').text(percentage + '%');
        $('#progress-fill').css('width', percentage + '%');
        $('#current-status').text('正在同步...');
        $('#current-product').text('产品ID: ' + productId);

        // 计算预计剩余时间
        const elapsed = Date.now() - window.syncStartTime;
        const avgTime = elapsed / current;
        const remaining = (total - current) * avgTime;
        const remainingMinutes = Math.ceil(remaining / 60000);

        $('#estimated-time').text(remainingMinutes + ' 分钟');
    }

    // 处理同步结果
    function processSyncResult(productId, response) {
        console.log('同步结果:', productId, response);

        if (response.success) {
            // 成功的情况
            let message = '同步成功';
            if (response.data && response.data.message) {
                message = response.data.message;
            } else if (typeof response.data === 'string') {
                message = response.data;
            }

            window.syncResults.success.push({
                product_id: productId,
                message: message
            });
        } else {
            // 失败的情况
            let reason = '同步失败';
            if (response.data && response.data.message) {
                reason = response.data.message;
            } else if (typeof response.data === 'string') {
                reason = response.data;
            } else if (response.message) {
                reason = response.message;
            }

            window.syncResults.failed.push({
                product_id: productId,
                reason: reason
            });
        }
    }

    // 处理同步错误
    function processSyncError(productId, error) {
        window.syncResults.failed.push({
            product_id: productId,
            reason: error
        });
    }

    // 完成批量同步
    function completeBatchSync(startTime) {
        const totalTime = Date.now() - startTime;
        const minutes = Math.floor(totalTime / 60000);
        const seconds = Math.floor((totalTime % 60000) / 1000);

        // 隐藏进度区域，显示结果区域
        $('#sync-progress-section').hide();
        $('#sync-results-section').show();

        // 更新结果统计
        $('#success-count').text(window.syncResults.success.length);
        $('#failed-count').text(window.syncResults.failed.length);
        $('#skipped-count').text(window.syncResults.skipped.length);
        $('#total-sync-time').text(minutes + '分' + seconds + '秒');

        // 显示详细结果
        displaySyncResults();

        // 滚动到结果区域
        $('html, body').animate({
            scrollTop: $('#sync-results-section').offset().top - 50
        }, 500);
    }

    // 显示同步结果
    function displaySyncResults() {
        // 显示成功的产品
        if (window.syncResults.success.length > 0) {
            const successList = $('<ul></ul>');
            window.syncResults.success.forEach(item => {
                successList.append(`<li><strong>产品ID: ${item.product_id}</strong> - ${item.message}</li>`);
            });
            $('#success-products .product-list').html(successList);
        } else {
            $('#success-products .product-list').html('<p>无成功同步的产品</p>');
        }

        // 显示失败的产品
        if (window.syncResults.failed.length > 0) {
            const failedList = $('<ul></ul>');
            window.syncResults.failed.forEach(item => {
                failedList.append(`<li><strong>产品ID: ${item.product_id}</strong> - ${item.reason}</li>`);
            });
            $('#failed-products .product-list').html(failedList);
        } else {
            $('#failed-products .product-list').html('<p>无失败的产品</p>');
        }
    }

    // 导出验证结果
    function exportValidationResults() {
        // 实现导出功能
        alert('导出功能开发中...');
    }

    // 下载同步报告
    function downloadSyncReport() {
        // 实现下载报告功能
        alert('下载报告功能开发中...');
    }

    // 重试失败项目
    function retryFailedItems() {
        if (window.syncResults.failed.length === 0) {
            alert('没有失败的项目需要重试');
            return;
        }

        const failedProductIds = window.syncResults.failed.map(item => item.product_id);

        // 重置结果
        window.syncResults = { success: [], failed: [], skipped: [] };

        // 重新开始同步失败的产品
        $('#sync-results-section').hide();
        $('#sync-progress-section').show();

        initSyncProgress(failedProductIds.length);
        executeSingleSync(failedProductIds, {});
    }

    // 开始新的同步
    function startNewSync() {
        // 重置所有状态
        resetValidationState();
        $('#sku-list-input').val('');
        window.syncResults = { success: [], failed: [], skipped: [] };

        // 滚动到顶部
        $('html, body').animate({ scrollTop: 0 }, 500);
    }

    // 其他辅助函数
    function clearInput() {
        $('#sku-list-input').val('');
        resetValidationState();
    }

    function resetValidationState() {
        $('#validation-section').hide();
        $('#sync-progress-section').hide();
        $('#sync-results-section').hide();
        $('#start-batch-sync-btn').prop('disabled', true);
        $('#start-single-sync-btn').prop('disabled', true);
    }

    // 初始化全局变量
    window.syncResults = { success: [], failed: [], skipped: [] };
    window.syncStartTime = 0;
});
</script>


