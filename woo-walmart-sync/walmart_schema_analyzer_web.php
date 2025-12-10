<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Walmart API 5.0 Schema 分析工具</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
            background: #f5f5f5;
        }
        .container {
            background: white;
            border-radius: 8px;
            padding: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1 {
            color: #0073aa;
            border-bottom: 3px solid #0073aa;
            padding-bottom: 10px;
        }
        .status {
            padding: 15px;
            border-radius: 5px;
            margin: 10px 0;
        }
        .status.info { background: #e7f3ff; border-left: 4px solid #0073aa; }
        .status.success { background: #e8f5e8; border-left: 4px solid #00a32a; }
        .status.warning { background: #fff8e1; border-left: 4px solid #ffb900; }
        .status.error { background: #ffe6e6; border-left: 4px solid #d63638; }
        .progress-bar {
            width: 100%;
            height: 20px;
            background: #f0f0f0;
            border-radius: 10px;
            overflow: hidden;
            margin: 10px 0;
        }
        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #0073aa, #005a87);
            width: 0%;
            transition: width 0.3s ease;
        }
        .btn {
            background: #0073aa;
            color: white;
            padding: 12px 24px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
            margin: 10px 5px;
        }
        .btn:hover { background: #005a87; }
        .btn:disabled { background: #ccc; cursor: not-allowed; }
        .log {
            background: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 5px;
            padding: 15px;
            height: 400px;
            overflow-y: auto;
            font-family: 'Courier New', monospace;
            font-size: 14px;
            white-space: pre-wrap;
        }
        .results {
            margin-top: 20px;
        }
        .result-item {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 5px;
            padding: 15px;
            margin: 10px 0;
        }
        .file-info {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin: 20px 0;
        }
        .info-card {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            text-align: center;
        }
        .info-card h3 {
            margin: 0 0 10px 0;
            color: #0073aa;
        }
        .info-card .value {
            font-size: 24px;
            font-weight: bold;
            color: #333;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔍 Walmart API 5.0 Schema 分析工具</h1>
        
        <?php if (!isset($_POST['analyze'])): ?>
        
        <div class="status info">
            <h3>📋 分析说明</h3>
            <p>此工具将分析 <code>MP_ITEM-5.0.20241118-04_39_24-api2.json</code> 文件，深度搜索所有 netContent 相关的字段定义。</p>
            <ul>
                <li>文件大小约 1GB，分析需要一些时间</li>
                <li>会显示实时进度和详细日志</li>
                <li>分析结果将保存为 JSON 文件</li>
                <li>重点关注 netContent 字段的结构和定义</li>
            </ul>
        </div>
        
        <form method="post">
            <button type="submit" name="analyze" class="btn">🚀 开始分析</button>
        </form>
        
        <?php else: ?>
        
        <div class="status info">
            <h3>🔄 正在分析中...</h3>
            <p>分析开始时间: <?php echo date('Y-m-d H:i:s'); ?></p>
        </div>
        
        <div class="progress-bar">
            <div class="progress-fill" id="progressBar"></div>
        </div>
        <div id="progressText">准备开始...</div>
        
        <div class="log" id="logOutput"></div>
        
        <script>
        // 实时更新日志的JavaScript
        let logElement = document.getElementById('logOutput');
        let progressBar = document.getElementById('progressBar');
        let progressText = document.getElementById('progressText');
        
        function updateLog(message) {
            logElement.textContent += message + '\n';
            logElement.scrollTop = logElement.scrollHeight;
        }
        
        function updateProgress(percent, text) {
            progressBar.style.width = percent + '%';
            progressText.textContent = text;
        }
        
        // 开始分析
        updateLog('=== Walmart API 5.0 Schema 分析开始 ===');
        updateProgress(5, '初始化...');
        </script>
        
        <?php
        // 设置不超时和增加内存
        set_time_limit(0);
        ini_set('memory_limit', '2G');
        
        // 刷新输出缓冲区，让前端能实时看到进度
        if (ob_get_level()) ob_end_flush();
        
        // 分析逻辑
        $json_file = '4.8_Upgrade_5.0_Documentation/MP_ITEM-5.0.20241118-04_39_24-api2.json';
        
        echo "<script>updateLog('检查文件: $json_file'); updateProgress(10, '检查文件...');</script>";
        flush();
        
        if (!file_exists($json_file)) {
            echo "<script>updateLog('❌ 文件不存在: $json_file');</script>";
            echo '<div class="status error"><h3>❌ 错误</h3><p>找不到分析文件，请确保文件路径正确。</p></div>';
        } else {
            $file_size = filesize($json_file);
            $file_size_mb = round($file_size / 1024 / 1024, 2);
            
            echo "<script>updateLog('✅ 文件找到，大小: {$file_size_mb} MB'); updateProgress(15, '读取文件...');</script>";
            flush();
            
            // 读取文件
            $start_time = microtime(true);
            $json_content = file_get_contents($json_file);
            $read_time = microtime(true) - $start_time;
            
            echo "<script>updateLog('✅ 文件读取完成，耗时: " . number_format($read_time, 2) . " 秒'); updateProgress(30, '解析JSON...');</script>";
            flush();
            
            // 解析JSON
            $parse_start = microtime(true);
            $schema = json_decode($json_content, true);
            $parse_time = microtime(true) - $parse_start;
            
            if (!$schema) {
                echo "<script>updateLog('❌ JSON解析失败: " . json_last_error_msg() . "');</script>";
                echo '<div class="status error"><h3>❌ JSON解析失败</h3><p>' . json_last_error_msg() . '</p></div>';
            } else {
                echo "<script>updateLog('✅ JSON解析完成，耗时: " . number_format($parse_time, 2) . " 秒'); updateProgress(50, '分析结构...');</script>";
                flush();
                
                // 释放内存
                unset($json_content);
                
                // 分析顶级结构
                $top_keys = array_keys($schema);
                echo "<script>updateLog('📊 顶级键数量: " . count($top_keys) . "'); updateProgress(60, '搜索netContent...');</script>";
                flush();
                
                // 搜索netContent
                $search_terms = ['netcontent', 'netContent', 'productnetcontent', 'productNetContent'];
                $results = [];
                
                function search_recursive($data, $path = '', &$results = [], $search_terms = []) {
                    if (is_array($data)) {
                        foreach ($data as $key => $value) {
                            $current_path = $path ? "$path.$key" : $key;
                            
                            foreach ($search_terms as $term) {
                                if (stripos($key, $term) !== false) {
                                    $results[] = [
                                        'path' => $current_path,
                                        'key' => $key,
                                        'type' => gettype($value),
                                        'preview' => is_array($value) ? '[' . count($value) . ' items]' : (is_string($value) ? substr($value, 0, 100) : $value)
                                    ];
                                }
                            }
                            
                            if (is_array($value)) {
                                search_recursive($value, $current_path, $results, $search_terms);
                            }
                        }
                    }
                }
                
                search_recursive($schema, '', $results, $search_terms);
                
                echo "<script>updateLog('🔍 搜索完成，找到 " . count($results) . " 个相关结果'); updateProgress(90, '保存结果...');</script>";
                flush();
                
                // 保存结果
                $analysis_data = [
                    'timestamp' => date('Y-m-d H:i:s'),
                    'file_info' => [
                        'path' => $json_file,
                        'size_mb' => $file_size_mb,
                        'read_time' => $read_time,
                        'parse_time' => $parse_time
                    ],
                    'structure' => [
                        'top_level_keys' => $top_keys
                    ],
                    'netcontent_results' => $results
                ];
                
                $output_file = 'walmart_analysis_' . date('Ymd_His') . '.json';
                file_put_contents($output_file, json_encode($analysis_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                
                $total_time = microtime(true) - $start_time;
                
                echo "<script>updateLog('✅ 分析完成！总耗时: " . number_format($total_time, 2) . " 秒'); updateProgress(100, '完成！');</script>";
                flush();
                
                // 显示结果
                ?>
                
                <div class="results">
                    <h2>📊 分析结果</h2>
                    
                    <div class="file-info">
                        <div class="info-card">
                            <h3>文件大小</h3>
                            <div class="value"><?php echo $file_size_mb; ?> MB</div>
                        </div>
                        <div class="info-card">
                            <h3>处理时间</h3>
                            <div class="value"><?php echo number_format($total_time, 1); ?> 秒</div>
                        </div>
                        <div class="info-card">
                            <h3>找到结果</h3>
                            <div class="value"><?php echo count($results); ?> 个</div>
                        </div>
                        <div class="info-card">
                            <h3>顶级键</h3>
                            <div class="value"><?php echo count($top_keys); ?> 个</div>
                        </div>
                    </div>
                    
                    <?php if (!empty($results)): ?>
                    <h3>🔍 netContent 相关发现</h3>
                    <?php foreach (array_slice($results, 0, 10) as $result): ?>
                    <div class="result-item">
                        <strong>路径:</strong> <?php echo htmlspecialchars($result['path']); ?><br>
                        <strong>键名:</strong> <?php echo htmlspecialchars($result['key']); ?><br>
                        <strong>类型:</strong> <?php echo $result['type']; ?><br>
                        <strong>预览:</strong> <?php echo htmlspecialchars($result['preview']); ?>
                    </div>
                    <?php endforeach; ?>
                    
                    <?php if (count($results) > 10): ?>
                    <div class="status info">
                        <p>还有 <?php echo count($results) - 10; ?> 个结果，请查看保存的JSON文件: <strong><?php echo $output_file; ?></strong></p>
                    </div>
                    <?php endif; ?>
                    
                    <?php else: ?>
                    <div class="status warning">
                        <h3>⚠️ 未找到结果</h3>
                        <p>没有找到包含 netContent 的字段定义。</p>
                    </div>
                    <?php endif; ?>
                    
                    <div class="status success">
                        <h3>✅ 分析完成</h3>
                        <p>详细结果已保存到: <strong><?php echo $output_file; ?></strong></p>
                        <p>您可以下载此文件查看完整的分析结果。</p>
                    </div>
                </div>
                
                <?php
            }
        }
        ?>
        
        <div style="margin-top: 30px;">
            <a href="?" class="btn">🔄 重新分析</a>
        </div>
        
        <?php endif; ?>
    </div>
</body>
</html>
