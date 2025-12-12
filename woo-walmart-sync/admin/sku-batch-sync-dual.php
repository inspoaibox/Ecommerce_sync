<?php
/**
 * SKU批量同步页面 - 双输入模式
 * 支持产品ID和SKU两种输入方式
 */

if (!defined('ABSPATH')) {
    exit;
}

// 检查用户权限
if (!current_user_can('manage_options')) {
    wp_die(__('您没有权限访问此页面。'));
}
?>

<div class="wrap">
    <h1>🚀 批量产品同步</h1>
    
    <div class="batch-sync-container">
        <!-- 输入方式选择 -->
        <div class="input-mode-section">
            <h2>📝 选择输入方式</h2>
            <div class="input-mode-options">
                <label class="input-mode-option recommended">
                    <input type="radio" name="input_type" value="product_id" checked>
                    <span class="option-title">🎯 产品ID (推荐)</span>
                    <span class="option-desc">直接从产品列表复制，无需转换，性能最佳</span>
                </label>
                <label class="input-mode-option">
                    <input type="radio" name="input_type" value="sku">
                    <span class="option-title">🏷️ SKU</span>
                    <span class="option-desc">需要转换为产品ID，可能存在不匹配的情况</span>
                </label>
            </div>
        </div>

        <!-- 输入区域 -->
        <div class="input-section">
            <h2 id="input-title">📝 输入产品ID列表</h2>
            <div class="input-help" id="input-help">
                <p><strong>💡 使用说明：</strong></p>
                <ul id="help-list">
                    <li>每行输入一个产品ID</li>
                    <li>支持复制粘贴Excel列表</li>
                    <li>空行和重复ID会自动过滤</li>
                    <li>最多支持一次同步1000个产品</li>
                    <li>可以从产品管理页面的URL或列表中获取产品ID</li>
                </ul>
            </div>
            
            <textarea 
                id="batch-input" 
                placeholder="请输入产品ID，每行一个，例如：&#10;25924&#10;25925&#10;25926"
                rows="15"
            ></textarea>
            
            <div class="input-stats">
                <span>已输入：<strong id="input-count">0</strong> 个</span>
                <span class="separator">|</span>
                <span>限制：<strong>1000</strong> 个</span>
            </div>
        </div>

        <!-- 同步选项 -->
        <div class="sync-options-section">
            <h2>🔧 同步选项</h2>
            <div class="sync-options">
                <label class="option-item">
                    <input type="checkbox" id="force-sync" checked>
                    <span class="option-label">强制同步</span>
                    <span class="option-desc">覆盖已存在的商品，忽略上次同步时间限制</span>
                </label>
                <label class="option-item">
                    <input type="checkbox" id="skip-validation">
                    <span class="option-label">跳过验证</span>
                    <span class="option-desc">加快同步速度，但可能增加失败风险</span>
                </label>
            </div>
        </div>

        <!-- 操作按钮 -->
        <div class="action-section">
            <button type="button" id="start-sync-btn" class="button button-primary button-large">
                🚀 开始批量同步
            </button>
            <button type="button" id="clear-input-btn" class="button button-large">
                🗑️ 清空输入
            </button>
        </div>

        <!-- 同步进度区域 -->
        <div id="sync-progress-section" style="display: none;">
            <h2>📊 同步进度</h2>
            <div class="progress-container">
                <div class="progress-bar">
                    <div class="progress-fill" id="progress-fill"></div>
                </div>
                <div class="progress-text">
                    <span id="progress-info">准备同步...</span>
                </div>
            </div>
            <div id="sync-results"></div>
        </div>
    </div>
</div>

<style>
.batch-sync-container {
    max-width: 900px;
    margin: 20px 0;
}

.input-mode-section, .input-section, .sync-options-section, .action-section {
    background: #fff;
    padding: 20px;
    margin-bottom: 20px;
    border: 1px solid #ccd0d4;
    border-radius: 4px;
    box-shadow: 0 1px 1px rgba(0,0,0,.04);
}

.input-mode-options {
    display: flex;
    gap: 20px;
    margin-top: 15px;
}

.input-mode-option {
    flex: 1;
    padding: 15px;
    border: 2px solid #ddd;
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.3s ease;
    display: block;
}

.input-mode-option:hover {
    border-color: #0073aa;
    background-color: #f8f9fa;
}

.input-mode-option.recommended {
    border-color: #00a32a;
    background-color: #f0f8f0;
}

.input-mode-option input[type="radio"] {
    margin-right: 10px;
}

.option-title {
    display: block;
    font-weight: bold;
    font-size: 16px;
    margin-bottom: 5px;
}

.option-desc {
    display: block;
    color: #666;
    font-size: 14px;
}

.input-help {
    background: #f8f9fa;
    padding: 15px;
    border-radius: 4px;
    margin-bottom: 15px;
}

.input-help ul {
    margin: 10px 0 0 20px;
}

.input-help li {
    margin-bottom: 5px;
}

#batch-input {
    width: 100%;
    min-height: 300px;
    font-family: 'Courier New', monospace;
    font-size: 14px;
    padding: 15px;
    border: 1px solid #ddd;
    border-radius: 4px;
    resize: vertical;
}

.input-stats {
    margin-top: 10px;
    color: #666;
    font-size: 14px;
}

.separator {
    margin: 0 10px;
}

.sync-options {
    display: flex;
    flex-direction: column;
    gap: 15px;
}

.option-item {
    display: flex;
    align-items: flex-start;
    gap: 10px;
    cursor: pointer;
}

.option-item input[type="checkbox"] {
    margin-top: 2px;
}

.option-label {
    font-weight: bold;
    min-width: 100px;
}

.option-desc {
    color: #666;
    font-size: 14px;
}

.action-section {
    text-align: center;
}

.button-large {
    height: 40px;
    line-height: 38px;
    padding: 0 20px;
    font-size: 16px;
    margin: 0 10px;
}

#sync-progress-section {
    background: #fff;
    padding: 20px;
    border: 1px solid #ccd0d4;
    border-radius: 4px;
    margin-top: 20px;
}

.progress-container {
    margin: 20px 0;
}

.progress-bar {
    width: 100%;
    height: 30px;
    background: #f1f1f1;
    border-radius: 15px;
    overflow: hidden;
    margin-bottom: 10px;
}

.progress-fill {
    height: 100%;
    background: linear-gradient(90deg, #0073aa, #005a87);
    width: 0%;
    transition: width 0.3s ease;
    border-radius: 15px;
}

.progress-text {
    text-align: center;
    font-weight: bold;
    color: #333;
}

#sync-results {
    margin-top: 20px;
}

.result-box {
    padding: 15px;
    border-radius: 4px;
    margin-bottom: 15px;
}

.result-success {
    background: #d4edda;
    border: 1px solid #c3e6cb;
    color: #155724;
}

.result-error {
    background: #f8d7da;
    border: 1px solid #f5c6cb;
    color: #721c24;
}

.result-info {
    background: #d1ecf1;
    border: 1px solid #bee5eb;
    color: #0c5460;
}
</style>

<script>
jQuery(document).ready(function($) {
    // 绑定事件
    $('input[name="input_type"]').on('change', updateInputMode);
    $('#batch-input').on('input', updateInputCount);
    $('#start-sync-btn').on('click', startBatchSync);
    $('#clear-input-btn').on('click', clearInput);

    // 初始化
    updateInputMode();
    updateInputCount();

    // 更新输入模式
    function updateInputMode() {
        const inputType = $('input[name="input_type"]:checked').val();

        if (inputType === 'product_id') {
            $('#input-title').text('📝 输入产品ID列表');
            $('#batch-input').attr('placeholder', '请输入产品ID，每行一个，例如：\n25924\n25925\n25926');
            $('#help-list').html(`
                <li>每行输入一个产品ID</li>
                <li>支持复制粘贴Excel列表</li>
                <li>空行和重复ID会自动过滤</li>
                <li>最多支持一次同步1000个产品</li>
                <li>可以从产品管理页面的URL或列表中获取产品ID</li>
            `);
        } else {
            $('#input-title').text('📝 输入SKU列表');
            $('#batch-input').attr('placeholder', '请输入SKU，每行一个，例如：\nW3622S00002\nW3623S00003\nW3624S00004');
            $('#help-list').html(`
                <li>每行输入一个SKU</li>
                <li>支持复制粘贴Excel列表</li>
                <li>空行和重复SKU会自动过滤</li>
                <li>最多支持一次同步1000个产品</li>
                <li>系统会自动将SKU转换为产品ID</li>
            `);
        }

        updateInputCount();
    }

    // 更新输入计数
    function updateInputCount() {
        const inputText = $('#batch-input').val().trim();
        const lines = inputText ? inputText.split(/\r?\n/).filter(line => line.trim()) : [];
        const uniqueLines = [...new Set(lines.map(line => line.trim()))];

        $('#input-count').text(uniqueLines.length);

        // 更新按钮状态
        const isValid = uniqueLines.length > 0 && uniqueLines.length <= 1000;
        $('#start-sync-btn').prop('disabled', !isValid);

        // 更新计数颜色
        if (uniqueLines.length > 1000) {
            $('#input-count').css('color', '#d63384');
        } else if (uniqueLines.length > 0) {
            $('#input-count').css('color', '#198754');
        } else {
            $('#input-count').css('color', '#666');
        }
    }

    // 开始批量同步
    function startBatchSync() {
        const inputType = $('input[name="input_type"]:checked').val();
        const inputText = $('#batch-input').val().trim();

        if (!inputText) {
            alert('请输入' + (inputType === 'product_id' ? '产品ID' : 'SKU') + '列表');
            return;
        }

        // 解析输入
        const inputList = parseInput(inputText);

        if (inputList.length === 0) {
            alert('没有找到有效的' + (inputType === 'product_id' ? '产品ID' : 'SKU'));
            return;
        }

        if (inputList.length > 1000) {
            alert('一次最多只能同步1000个产品，请减少数量');
            return;
        }

        // 确认开始同步
        const confirmMessage = `确定要同步 ${inputList.length} 个产品吗？\n\n注意：\n• 同步过程可能需要几分钟时间\n• 无效的${inputType === 'product_id' ? '产品ID' : 'SKU'}会被跳过\n• 可以在同步状态页面查看详细进度`;

        if (!confirm(confirmMessage)) {
            return;
        }

        // 根据输入类型选择处理方式
        if (inputType === 'product_id') {
            // 直接使用产品ID同步
            startWalmartSync(inputList);
        } else {
            // SKU模式：先转换为产品ID
            convertSkusAndSync(inputList);
        }
    }

    // 解析输入内容
    function parseInput(text) {
        const lines = text.split(/\r?\n/);
        const inputList = [];
        const seen = new Set();

        lines.forEach(line => {
            const item = line.trim();
            if (item && !seen.has(item)) {
                inputList.push(item);
                seen.add(item);
            }
        });

        return inputList;
    }

    // SKU转换为产品ID并开始同步
    function convertSkusAndSync(skuList) {
        showProgress('正在将SKU转换为产品ID...');
        disableButtons(true);

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
                    const foundCount = response.data.found_skus.length;
                    const notFoundCount = response.data.not_found_skus.length;

                    showProgress(`找到 ${foundCount} 个有效产品，开始同步...`);

                    if (notFoundCount > 0) {
                        console.log('未找到的SKU:', response.data.not_found_skus);
                    }

                    // 开始批量同步
                    startWalmartSync(response.data.product_ids);
                } else {
                    showError('没有找到有效的产品，请检查SKU是否正确');
                    disableButtons(false);
                }
            },
            error: function() {
                showError('SKU转换失败，请重试');
                disableButtons(false);
            }
        });
    }

    // 开始Walmart同步 (与产品目录页面完全一致)
    function startWalmartSync(productIds) {
        const forceSync = $('#force-sync').is(':checked');
        const skipValidation = $('#skip-validation').is(':checked');

        showProgress(`开始同步 ${productIds.length} 个产品...`);

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'walmart_batch_sync_products', // 与产品目录页面完全一致
                product_ids: productIds,
                force_sync: forceSync ? 1 : 0,
                skip_validation: skipValidation ? 1 : 0,
                nonce: '<?php echo wp_create_nonce("sku_batch_sync_nonce"); ?>'
            },
            success: function(response) {
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
                let errorMessage = '网络请求失败，请重试';
                if (xhr.responseJSON && xhr.responseJSON.data) {
                    errorMessage = xhr.responseJSON.data.message || xhr.responseJSON.data;
                }

                showError(`
                    <h3>❌ 同步请求失败</h3>
                    <p>${errorMessage}</p>
                    <button type="button" class="button" onclick="location.reload()">重新尝试</button>
                `);
                disableButtons(false);
            }
        });
    }

    // 显示进度
    function showProgress(message) {
        $('#sync-progress-section').show();
        $('#progress-info').text(message);
        $('#progress-fill').css('width', '50%');
        $('#sync-results').empty();

        // 滚动到进度区域
        $('html, body').animate({
            scrollTop: $('#sync-progress-section').offset().top - 50
        }, 500);
    }

    // 显示成功结果
    function showSuccess(html) {
        $('#progress-fill').css('width', '100%');
        $('#progress-info').text('同步完成！');
        $('#sync-results').html(`<div class="result-box result-success">${html}</div>`);
    }

    // 显示错误结果
    function showError(html) {
        $('#progress-fill').css('width', '0%');
        $('#progress-info').text('同步失败');
        $('#sync-results').html(`<div class="result-box result-error">${html}</div>`);
    }

    // 显示信息
    function showInfo(html) {
        $('#sync-results').html(`<div class="result-box result-info">${html}</div>`);
    }

    // 禁用/启用按钮
    function disableButtons(disabled) {
        $('#start-sync-btn').prop('disabled', disabled);
        if (disabled) {
            $('#start-sync-btn').text('🔄 同步中...');
        } else {
            $('#start-sync-btn').text('🚀 开始批量同步');
        }
    }

    // 清空输入
    function clearInput() {
        $('#batch-input').val('');
        $('#sync-progress-section').hide();
        updateInputCount();
        $('#batch-input').focus();
    }
});
</script>
