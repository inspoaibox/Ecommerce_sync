<?php
/**
 * 稳健版Walmart API Schema处理器
 * 增强错误处理和调试功能
 */

// 错误处理和调试设置
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', './error.log');

// 启动会话
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 配置参数 - 优化性能设置
$config = [
    'chunk_size' => 2048 * 2048 * 2,  // 4MB per chunk (更大的块，提升性能)
    'max_execution_time' => 600,       // 5分钟执行时间
    'memory_limit' => '20148G',            // 4GB内存限制
    'max_objects_per_chunk' => 200,     // 每次处理50个对象
    'temp_dir' => './temp/',
    'results_dir' => './results/',
    'debug' => true                    // 启用调试模式
];

// 设置PHP环境
@ini_set('max_execution_time', $config['max_execution_time']);
@ini_set('memory_limit', $config['memory_limit']);

/**
 * 调试日志函数
 */
function debug_log($message, $type = 'INFO') {
    global $config;
    if ($config['debug']) {
        $timestamp = date('Y-m-d H:i:s');
        $log_message = "[$timestamp] [$type] $message" . PHP_EOL;
        error_log($log_message, 3, './debug.log');
    }
}

/**
 * 安全的JSON响应
 */
function safe_json_response($data, $http_code = 200) {
    http_response_code($http_code);
    header('Content-Type: application/json; charset=utf-8');
    
    try {
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'JSON编码错误: ' . $e->getMessage()]);
    }
    exit;
}

/**
 * 错误处理函数
 */
function handle_error($message, $code = 500) {
    debug_log("ERROR: $message", 'ERROR');
    safe_json_response(['success' => false, 'message' => $message], $code);
}

class WalmartSchemaProcessor {
    private $config;
    private $session_key = 'walmart_processing';
    
    public function __construct($config) {
        $this->config = $config;
        debug_log("处理器初始化开始");
        
        try {
            $this->createDirectories();
            debug_log("目录创建成功");
        } catch (Exception $e) {
            debug_log("目录创建失败: " . $e->getMessage(), 'ERROR');
            throw $e;
        }
    }
    
    private function createDirectories() {
        foreach ([$this->config['temp_dir'], $this->config['results_dir']] as $dir) {
            if (!is_dir($dir)) {
                if (!@mkdir($dir, 0755, true)) {
                    throw new Exception("无法创建目录: $dir");
                }
            }
            if (!is_writable($dir)) {
                throw new Exception("目录不可写: $dir");
            }
        }
    }
    
    /**
     * 开始处理
     */
    public function startProcessing($file_path) {
        debug_log("开始处理文件: $file_path");
        
        try {
            // 验证文件
            if (!file_exists($file_path)) {
                throw new Exception("文件不存在: $file_path");
            }
            
            if (!is_readable($file_path)) {
                throw new Exception("文件不可读: $file_path");
            }
            
            $file_size = filesize($file_path);
            if ($file_size === false || $file_size === 0) {
                throw new Exception("无法获取文件大小或文件为空");
            }
            
            debug_log("文件验证成功，大小: " . number_format($file_size) . " bytes");
            
            // 清理之前的状态
            unset($_SESSION[$this->session_key]);
            
            // 初始化处理状态
            $processing_state = [
                'file_path' => realpath($file_path),
                'file_size' => $file_size,
                'current_position' => 0,
                'categories' => [],
                'attributes' => [],
                'netcontent_analysis' => [],
                'walmart_categories' => [],
                'buffer' => '',
                'brace_level' => 0,
                'in_string' => false,
                'objects_processed' => 0,
                'chunks_processed' => 0,
                'start_time' => time(),
                'last_update' => time(),
                'status' => 'initialized',
                'errors' => []
            ];
            
            $_SESSION[$this->session_key] = $processing_state;
            debug_log("处理状态初始化成功");
            
            return [
                'success' => true,
                'file_size' => $file_size,
                'file_path' => $processing_state['file_path'],
                'message' => '处理任务已启动'
            ];
            
        } catch (Exception $e) {
            debug_log("启动处理失败: " . $e->getMessage(), 'ERROR');
            throw $e;
        }
    }
    
    /**
     * 处理下一个数据块
     */
    public function processNextChunk() {
        debug_log("开始处理下一个数据块");
        
        if (!isset($_SESSION[$this->session_key])) {
            throw new Exception("没有正在进行的处理任务");
        }
        
        $state = $_SESSION[$this->session_key];
        
        if ($state['status'] === 'completed') {
            debug_log("处理已完成");
            return [
                'success' => true, 
                'completed' => true, 
                'message' => '处理已完成',
                'state' => $this->getSafeState($state)
            ];
        }
        
        try {
            $start_time = microtime(true);
            $max_time = $this->config['max_execution_time'] - 8; // 留8秒缓冲
            $objects_in_this_run = 0;
            
            $state['status'] = 'processing';
            
            while ((microtime(true) - $start_time) < $max_time && $objects_in_this_run < $this->config['max_objects_per_chunk']) {
                $result = $this->processSingleChunk($state);
                
                if ($result['eof']) {
                    $state['status'] = 'completed';
                    debug_log("文件处理完成");
                    break;
                }
                
                $objects_in_this_run += $result['objects_processed'];
                
                // 每处理几个块就保存一次状态
                if ($state['chunks_processed'] % 5 === 0) {
                    $_SESSION[$this->session_key] = $state;
                }
                
                // 如果没有处理到新对象，避免无限循环
                if ($result['objects_processed'] === 0 && $result['bytes_read'] === 0) {
                    debug_log("没有新数据可处理，可能到达文件末尾");
                    break;
                }
            }
            
            // 保存最终状态
            $_SESSION[$this->session_key] = $state;
            
            $progress = $state['file_size'] > 0 ? ($state['current_position'] / $state['file_size']) * 100 : 0;
            
            debug_log(sprintf(
                "处理完成一轮: 进度%.2f%%, 分类%d, 属性%d, netContent%d, Walmart分类%d, 对象%d",
                $progress,
                count($state['categories']),
                count($state['attributes']),
                count($state['netcontent_analysis']),
                count($state['walmart_categories']),
                $state['objects_processed']
            ));
            
            return [
                'success' => true,
                'completed' => $state['status'] === 'completed',
                'progress' => $progress,
                'categories_count' => count($state['categories']),
                'attributes_count' => count($state['attributes']),
                'netcontent_count' => count($state['netcontent_analysis']),
                'walmart_categories_count' => count($state['walmart_categories']),
                'objects_processed' => $state['objects_processed'],
                'chunks_processed' => $state['chunks_processed'],
                'current_position' => $state['current_position'],
                'file_size' => $state['file_size'],
                'memory_usage' => memory_get_usage(true),
                'peak_memory' => memory_get_peak_usage(true)
            ];
            
        } catch (Exception $e) {
            $state['errors'][] = [
                'time' => time(),
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ];
            $_SESSION[$this->session_key] = $state;
            
            debug_log("处理数据块时出错: " . $e->getMessage(), 'ERROR');
            throw $e;
        }
    }
    
    /**
     * 处理单个数据块
     */
    private function processSingleChunk(&$state) {
        $bytes_read = 0;
        $objects_processed = 0;
        
        try {
            $handle = @fopen($state['file_path'], 'rb');
            if (!$handle) {
                throw new Exception("无法打开文件进行读取");
            }
            
            if (@fseek($handle, $state['current_position']) !== 0) {
                fclose($handle);
                throw new Exception("无法定位到文件位置: " . $state['current_position']);
            }
            
            $chunk = @fread($handle, $this->config['chunk_size']);
            if ($chunk === false) {
                fclose($handle);
                throw new Exception("读取文件失败");
            }
            
            $bytes_read = strlen($chunk);
            $new_position = ftell($handle);
            $eof = feof($handle);
            
            fclose($handle);
            
            if ($bytes_read > 0) {
                $state['buffer'] .= $chunk;
                $objects_processed = $this->parseJsonChunk($state);
                $state['current_position'] = $new_position;
                $state['objects_processed'] += $objects_processed;
                $state['chunks_processed']++;
                $state['last_update'] = time();
            }
            
            return [
                'eof' => $eof && empty($state['buffer']),
                'objects_processed' => $objects_processed,
                'bytes_read' => $bytes_read
            ];
            
        } catch (Exception $e) {
            debug_log("处理单个数据块失败: " . $e->getMessage(), 'ERROR');
            throw $e;
        }
    }
    
    /**
     * 解析JSON数据块
     */
    private function parseJsonChunk(&$state) {
        $buffer = &$state['buffer'];
        $categories = &$state['categories'];
        $attributes = &$state['attributes'];
        $brace_level = &$state['brace_level'];
        $in_string = &$state['in_string'];
        
        $current_object = '';
        $objects_found = 0;
        $buffer_length = strlen($buffer);
        $keep_from = 0;
        
        try {
            for ($i = 0; $i < $buffer_length; $i++) {
                $char = $buffer[$i];
                $current_object .= $char;
                
                // 处理转义字符
                if ($char === '"' && ($i === 0 || $buffer[$i-1] !== '\\')) {
                    $in_string = !$in_string;
                }
                
                if (!$in_string) {
                    if ($char === '{') {
                        $brace_level++;
                    } elseif ($char === '}') {
                        $brace_level--;
                        
                        // 找到完整的JSON对象
                        if ($brace_level >= 0 && strlen(trim($current_object)) > 10) {
                            try {
                                // 添加调试信息
                                if ($objects_found % 100 === 0) {
                                    debug_log("处理对象 #$objects_found, 当前分类: " . count($categories) . ", 属性: " . count($attributes));
                                }

                                $this->extractDataFromObject($current_object, $categories, $attributes);
                                $objects_found++;
                            } catch (Exception $e) {
                                debug_log("解析对象失败: " . substr($e->getMessage(), 0, 100), 'WARNING');
                            }
                            
                            $current_object = '';
                            $keep_from = $i + 1;
                            
                            // 限制处理数量
                            if ($objects_found >= $this->config['max_objects_per_chunk']) {
                                break;
                            }
                        }
                    }
                }
                
                // 防止内存溢出
                if (strlen($current_object) > 1024 * 1024) { // 1MB limit per object
                    debug_log("对象过大，跳过", 'WARNING');
                    $current_object = '';
                    $keep_from = $i + 1;
                }
            }
            
            // 保留未完成的部分，但限制buffer大小
            $remaining_buffer = substr($buffer, $keep_from);
            if (strlen($remaining_buffer) > 1024 * 1024) { // 1MB buffer limit
                $remaining_buffer = substr($remaining_buffer, -1024 * 512); // Keep last 512KB
                debug_log("Buffer过大，截断", 'WARNING');
            }
            $buffer = $remaining_buffer;
            
            return $objects_found;
            
        } catch (Exception $e) {
            debug_log("解析JSON块失败: " . $e->getMessage(), 'ERROR');
            // 清理buffer避免重复错误
            $buffer = '';
            return 0;
        }
    }
    
    /**
     * 从JSON对象中提取数据
     */
    private function extractDataFromObject($json_string, &$categories, &$attributes) {
        // 清理JSON字符串
        $json_string = trim($json_string);
        if (empty($json_string) || $json_string === '{}') {
            return;
        }
        
        // 尝试修复常见的JSON问题
        if (substr($json_string, -1) !== '}' && substr($json_string, -1) !== ']') {
            $json_string .= '}'; // 尝试修复
        }
        
        $data = @json_decode($json_string, true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($data)) {
            // 记录但不抛出异常
            return;
        }
        
        try {
            // 获取状态引用
            $state = $_SESSION[$this->session_key];
            $netcontent_analysis = &$state['netcontent_analysis'];
            $walmart_categories = &$state['walmart_categories'];

            // 快速诊断JSON结构
            if (count($categories) === 0 && count($attributes) === 0 && $state['objects_processed'] % 100 === 0) {
                debug_log("诊断JSON结构: " . implode(', ', array_keys($data)));

                // 检查是否有definitions
                if (isset($data['definitions'])) {
                    debug_log("发现definitions，包含: " . count($data['definitions']) . " 个定义");
                }
            }

            // 执行多种分析
            $this->recursiveExtract($data, $categories, $attributes);
            $this->analyzeNetContentFields($data, $netcontent_analysis);
            $this->extractWalmartCategories($data, $walmart_categories);

            // 更新会话状态
            $_SESSION[$this->session_key] = $state;

        } catch (Exception $e) {
            debug_log("递归提取失败: " . $e->getMessage(), 'WARNING');
        }
    }
    
    /**
     * 递归提取分类和属性信息 - 修复版
     */
    private function recursiveExtract($data, &$categories, &$attributes, $path = '', $depth = 0) {
        // 严格限制递归深度和处理时间
        if ($depth > 8 || !is_array($data) || count($categories) > 10000 || count($attributes) > 10000) {
            return;
        }

        // 特别处理definitions部分
        if ($path === 'definitions' || strpos($path, 'definitions.') === 0) {
            $this->extractFromDefinitions($data, $categories, $attributes, $path, $depth);
            return;
        }
        
        foreach ($data as $key => $value) {
            if (!is_string($key) || strlen($key) > 100) continue;
            
            $current_path = $path ? $path . '.' . $key : $key;
            
            try {
                // 检查分类信息
                if ($this->isCategoryInfo($key, $value)) {
                    $hash = md5($current_path);
                    
                    // 简单的重复检查
                    $exists = false;
                    foreach ($categories as $existing) {
                        if ($existing['hash'] === $hash) {
                            $exists = true;
                            break;
                        }
                    }
                    
                    if (!$exists && count($categories) < 5000) {
                        $categories[] = [
                            'path' => substr($current_path, 0, 200),
                            'key' => substr($key, 0, 100),
                            'data' => $this->sanitizeData($value),
                            'level' => $depth,
                            'hash' => $hash
                        ];
                    }
                }
                
                // 检查属性信息
                if ($this->isAttributeInfo($key, $value)) {
                    $hash = md5($current_path);
                    
                    $exists = false;
                    foreach ($attributes as $existing) {
                        if ($existing['hash'] === $hash) {
                            $exists = true;
                            break;
                        }
                    }
                    
                    if (!$exists && count($attributes) < 5000) {
                        $attributes[] = [
                            'path' => substr($current_path, 0, 200),
                            'key' => substr($key, 0, 100),
                            'data' => $this->sanitizeData($value),
                            'hash' => $hash
                        ];
                    }
                }
                
                // 递归处理嵌套结构
                if (is_array($value) && $depth < 6 && count($value) < 100) {
                    $this->recursiveExtract($value, $categories, $attributes, $current_path, $depth + 1);
                }
            } catch (Exception $e) {
                debug_log("处理键值对失败 [$key]: " . $e->getMessage(), 'WARNING');
                continue;
            }
        }
    }
    
    /**
     * 清理和压缩数据
     */
    private function sanitizeData($data) {
        if (is_array($data)) {
            if (count($data) > 50) {
                $data = array_slice($data, 0, 50);
                $data['__truncated'] = true;
            }
            return $data;
        }
        
        if (is_string($data)) {
            return strlen($data) > 500 ? substr($data, 0, 500) . '...' : $data;
        }
        
        return $data;
    }
    
    /**
     * 判断是否为分类信息 - 专注Walmart类目识别
     */
    private function isCategoryInfo($key, $value) {
        // 1. 直接识别包含&符号的类目名 (如 "Home & Garden")
        if (strpos($key, '&') !== false) {
            return true;
        }

        // 2. 识别包含逗号的类目名 (如 "Electronics, Computers")
        if (strpos($key, ',') !== false && strlen($key) > 10) {
            return true;
        }

        // 3. 识别枚举类型的分类列表
        if (is_array($value) && isset($value['enum']) && count($value['enum']) > 10) {
            $category_like = 0;
            foreach (array_slice($value['enum'], 0, 10) as $enum_value) {
                if (is_string($enum_value) && (
                    strpos($enum_value, '&') !== false ||
                    strpos($enum_value, ',') !== false ||
                    preg_match('/^[A-Z][a-z]+ [A-Z][a-z]+/', $enum_value)
                )) {
                    $category_like++;
                }
            }
            // 如果大部分枚举值看起来像分类名，认为是分类
            return $category_like > 3;
        }

        // 4. 识别明显的分类关键词
        $key_lower = strtolower($key);
        $category_keywords = ['category', 'department', 'subcategory'];
        foreach ($category_keywords as $keyword) {
            if (strpos($key_lower, $keyword) !== false) {
                return true;
            }
        }

        return false;
    }
    
    /**
     * 判断是否为属性信息 - 针对Walmart API Schema优化
     */
    private function isAttributeInfo($key, $value) {
        $key_lower = strtolower($key);

        // Walmart特定的属性关键词
        $walmart_attribute_patterns = [
            'attribute', 'property', 'field', 'spec', 'parameter',
            'netcontent', 'productidentifier', 'brand', 'manufacturer',
            'keyfeatures', 'productname', 'shortdescription', 'longdescription',
            'price', 'weight', 'dimensions', 'color', 'size', 'material',
            'model', 'upc', 'gtin', 'isbn', 'ean', 'mpn'
        ];

        // 检查键名
        foreach ($walmart_attribute_patterns as $pattern) {
            if (strpos($key_lower, $pattern) !== false) {
                return true;
            }
        }

        // 检查是否为definitions中的属性定义
        if (is_array($value)) {
            // 检查是否包含属性相关的结构
            $attribute_indicators = [
                'type', 'enum', 'properties', 'required', 'minLength', 'maxLength',
                'minimum', 'maximum', 'pattern', 'format'
            ];

            $indicator_count = 0;
            foreach ($attribute_indicators as $indicator) {
                if (isset($value[$indicator])) {
                    $indicator_count++;
                }
            }

            // 如果包含多个属性定义指标，认为是属性
            if ($indicator_count >= 2) {
                return true;
            }

            // 特别检查netContent相关的复合属性
            if (isset($value['properties'])) {
                $properties = $value['properties'];
                $netcontent_props = ['productNetContentMeasure', 'productNetContentUnit'];
                foreach ($netcontent_props as $prop) {
                    if (isset($properties[$prop])) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    /**
     * 专门处理JSON Schema结构 - 重点提取类目属性
     */
    private function extractFromDefinitions($data, &$categories, &$attributes, $path, $depth) {
        foreach ($data as $key => $value) {
            if (!is_array($value)) continue;

            $current_path = $path . '.' . $key;

            // 识别类目名称 (包含&符号的通常是类目)
            if (strpos($key, '&') !== false || strpos($key, ',') !== false) {
                $categories[] = [
                    'path' => $current_path,
                    'key' => $key,
                    'data' => ['category_name' => $key, 'type' => 'walmart_category'],
                    'level' => $depth,
                    'hash' => md5($current_path),
                    'type' => 'category'
                ];
            }

            // 识别属性字段 - 任何有type定义的都是属性
            if (isset($value['type']) || isset($value['properties']) || isset($value['enum'])) {
                $attr_info = [
                    'field_name' => $key,
                    'type' => $value['type'] ?? 'object',
                    'has_enum' => isset($value['enum']),
                    'enum_count' => isset($value['enum']) ? count($value['enum']) : 0,
                    'has_properties' => isset($value['properties']),
                    'properties_count' => isset($value['properties']) ? count($value['properties']) : 0
                ];

                // 如果有枚举值，取前几个作为示例
                if (isset($value['enum'])) {
                    $attr_info['enum_samples'] = array_slice($value['enum'], 0, 5);
                }

                // 如果有子属性，列出子属性名
                if (isset($value['properties'])) {
                    $attr_info['sub_properties'] = array_keys($value['properties']);
                }

                $attributes[] = [
                    'path' => $current_path,
                    'key' => $key,
                    'data' => $attr_info,
                    'level' => $depth,
                    'hash' => md5($current_path),
                    'type' => 'attribute_field'
                ];
            }

            // 检查是否为分类枚举
            if (isset($value['enum']) && is_array($value['enum'])) {
                $enum_values = $value['enum'];

                // 如果枚举值看起来像分类
                if (count($enum_values) > 5) {
                    $category_like = 0;
                    foreach (array_slice($enum_values, 0, 10) as $enum_val) {
                        if (is_string($enum_val) && (
                            strpos($enum_val, '&') !== false ||
                            strpos($enum_val, ',') !== false ||
                            preg_match('/^[A-Z][a-z]+ [A-Z]/', $enum_val)
                        )) {
                            $category_like++;
                        }
                    }

                    if ($category_like > 3) {
                        $categories[] = [
                            'path' => $current_path,
                            'key' => $key,
                            'data' => [
                                'type' => 'category_enum',
                                'count' => count($enum_values),
                                'samples' => array_slice($enum_values, 0, 5),
                                'all_values' => $enum_values
                            ],
                            'level' => $depth,
                            'hash' => md5($current_path)
                        ];
                    }
                }
            }

            // 递归处理
            if (is_array($value) && $depth < 6) {
                $this->recursiveExtract($value, $categories, $attributes, $current_path, $depth + 1);
            }
        }
    }

    /**
     * 专门分析netContent相关字段
     */
    private function analyzeNetContentFields($data, &$netcontent_analysis, $path = '') {
        if (!is_array($data)) return;

        foreach ($data as $key => $value) {
            $current_path = $path ? $path . '.' . $key : $key;
            $key_lower = strtolower($key);

            // 检查netContent相关的键
            if (strpos($key_lower, 'netcontent') !== false ||
                strpos($key_lower, 'productnetcontent') !== false) {

                $netcontent_analysis[] = [
                    'path' => $current_path,
                    'key' => $key,
                    'type' => gettype($value),
                    'structure' => $this->analyzeStructure($value),
                    'data_sample' => $this->sanitizeData($value)
                ];
            }

            // 递归分析
            if (is_array($value) && count($value) < 1000) {
                $this->analyzeNetContentFields($value, $netcontent_analysis, $current_path);
            }
        }
    }

    /**
     * 分析数据结构
     */
    private function analyzeStructure($data) {
        if (!is_array($data)) {
            return ['type' => gettype($data), 'value' => $data];
        }

        $structure = [
            'type' => 'array',
            'count' => count($data),
            'keys' => array_keys($data)
        ];

        // 检查是否为对象定义
        if (isset($data['type'])) {
            $structure['schema_type'] = $data['type'];
        }

        if (isset($data['properties'])) {
            $structure['properties'] = array_keys($data['properties']);
        }

        if (isset($data['enum'])) {
            $structure['enum_count'] = count($data['enum']);
            $structure['enum_sample'] = array_slice($data['enum'], 0, 5);
        }

        if (isset($data['required'])) {
            $structure['required_fields'] = $data['required'];
        }

        return $structure;
    }

    /**
     * 专门提取Walmart分类信息
     */
    private function extractWalmartCategories($data, &$categories, $path = '') {
        if (!is_array($data)) return;

        foreach ($data as $key => $value) {
            $current_path = $path ? $path . '.' . $key : $key;

            // 查找分类相关的定义
            if (is_array($value)) {
                // 检查是否为分类枚举
                if (isset($value['enum']) && is_array($value['enum'])) {
                    $enum_values = $value['enum'];
                    $category_like = 0;

                    foreach (array_slice($enum_values, 0, 10) as $enum_val) {
                        if (is_string($enum_val) && (
                            strpos($enum_val, '&') !== false || // "Home & Garden"
                            strpos($enum_val, ',') !== false || // "Electronics, Computers"
                            preg_match('/^[A-Z][a-z]+ [A-Z][a-z]+/', $enum_val) // "Home Decor"
                        )) {
                            $category_like++;
                        }
                    }

                    // 如果大部分枚举值看起来像分类名称
                    if ($category_like > count($enum_values) * 0.3) {
                        $categories[] = [
                            'path' => $current_path,
                            'key' => $key,
                            'type' => 'category_enum',
                            'count' => count($enum_values),
                            'samples' => array_slice($enum_values, 0, 10),
                            'all_values' => $enum_values
                        ];
                    }
                }

                // 递归查找
                if (count($value) < 1000) {
                    $this->extractWalmartCategories($value, $categories, $current_path);
                }
            }
        }
    }

    /**
     * 获取安全的状态信息（用于前端显示）
     */
    private function getSafeState($state) {
        return [
            'progress' => $state['file_size'] > 0 ? ($state['current_position'] / $state['file_size']) * 100 : 0,
            'categories_count' => count($state['categories']),
            'attributes_count' => count($state['attributes']),
            'netcontent_count' => count($state['netcontent_analysis']),
            'walmart_categories_count' => count($state['walmart_categories']),
            'objects_processed' => $state['objects_processed'],
            'chunks_processed' => $state['chunks_processed'],
            'status' => $state['status'],
            'elapsed_time' => time() - $state['start_time']
        ];
    }
    
    /**
     * 获取处理状态
     */
    public function getStatus() {
        if (!isset($_SESSION[$this->session_key])) {
            return ['success' => false, 'message' => '没有正在进行的处理任务'];
        }
        
        $state = $_SESSION[$this->session_key];
        return [
            'success' => true,
            'state' => $this->getSafeState($state)
        ];
    }
    
    /**
     * 停止处理
     */
    public function stopProcessing() {
        if (isset($_SESSION[$this->session_key])) {
            $_SESSION[$this->session_key]['status'] = 'stopped';
            debug_log("处理已手动停止");
        }
        return ['success' => true, 'message' => '处理已停止'];
    }
    
    /**
     * 清理处理状态
     */
    public function clearSession() {
        if (isset($_SESSION[$this->session_key])) {
            unset($_SESSION[$this->session_key]);
        }
        debug_log("会话状态已清理");
        return ['success' => true, 'message' => '会话已清理'];
    }

    /**
     * 导出分析结果
     */
    public function exportResults() {
        if (!isset($_SESSION[$this->session_key])) {
            return ['success' => false, 'message' => '没有可导出的数据'];
        }

        $state = $_SESSION[$this->session_key];

        $export_data = [
            'timestamp' => date('Y-m-d H:i:s'),
            'processing_info' => [
                'status' => $state['status'],
                'objects_processed' => $state['objects_processed'],
                'chunks_processed' => $state['chunks_processed'],
                'elapsed_time' => time() - $state['start_time']
            ],
            'categories' => $state['categories'],
            'attributes' => $state['attributes'],
            'netcontent_analysis' => $state['netcontent_analysis'],
            'walmart_categories' => $state['walmart_categories']
        ];

        $filename = 'walmart_analysis_' . date('Ymd_His') . '.json';
        $filepath = $this->config['results_dir'] . $filename;

        try {
            file_put_contents($filepath, json_encode($export_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            return [
                'success' => true,
                'message' => '结果已导出',
                'filename' => $filename,
                'filepath' => $filepath,
                'summary' => [
                    'categories' => count($state['categories']),
                    'attributes' => count($state['attributes']),
                    'netcontent_fields' => count($state['netcontent_analysis']),
                    'walmart_categories' => count($state['walmart_categories'])
                ]
            ];
        } catch (Exception $e) {
            return ['success' => false, 'message' => '导出失败: ' . $e->getMessage()];
        }
    }
}

// 主要的请求处理逻辑
try {
    debug_log("收到请求: " . $_SERVER['REQUEST_METHOD']);
    
    // 处理AJAX请求
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
        debug_log("处理AJAX请求: " . $_POST['action']);
        
        $processor = new WalmartSchemaProcessor($config);
        
        switch ($_POST['action']) {
            case 'start':
                $file_path = isset($_POST['file_path']) ? trim($_POST['file_path']) : '';
                if (empty($file_path)) {
                    handle_error('文件路径不能为空', 400);
                }
                
                $result = $processor->startProcessing($file_path);
                safe_json_response($result);
                break;
                
            case 'process':
                $result = $processor->processNextChunk();
                safe_json_response($result);
                break;
                
            case 'status':
                $result = $processor->getStatus();
                safe_json_response($result);
                break;
                
            case 'stop':
                $result = $processor->stopProcessing();
                safe_json_response($result);
                break;
                
            case 'clear':
                $result = $processor->clearSession();
                safe_json_response($result);
                break;

            case 'export':
                $result = $processor->exportResults();
                safe_json_response($result);
                break;
                
            default:
                handle_error('未知操作: ' . $_POST['action'], 400);
        }
    }
    
} catch (Exception $e) {
    debug_log("全局异常: " . $e->getMessage(), 'ERROR');
    handle_error('服务器错误: ' . $e->getMessage(), 500);
}

// 如果不是POST请求，显示HTML界面
$current_status = null;
try {
    $processor = new WalmartSchemaProcessor($config);
    $current_status = $processor->getStatus();
} catch (Exception $e) {
    debug_log("获取状态失败: " . $e->getMessage(), 'ERROR');
}
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Walmart API Schema 处理器 - 稳健版</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        
        .container {
            max-width: 1000px;
            margin: 0 auto;
            background: white;
            border-radius: 15px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .header {
            background: linear-gradient(45deg, #2196F3, #21CBF3);
            color: white;
            padding: 30px;
            text-align: center;
        }
        
        .header h1 {
            font-size: 2.5em;
            margin-bottom: 10px;
        }
        
        .content {
            padding: 30px;
        }
        
        .config-info {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            color: #856404;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
        }
        
        .form-section {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 25px;
            margin-bottom: 25px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        label {
            display: block;
            margin-bottom: 8px;
            font-weight: bold;
            color: #333;
        }
        
        input[type="text"] {
            width: 100%;
            padding: 12px;
            border: 2px solid #ddd;
            border-radius: 8px;
            font-size: 16px;
        }
        
        .btn {
            background: linear-gradient(45deg, #2196F3, #21CBF3);
            color: white;
            padding: 12px 25px;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            cursor: pointer;
            margin-right: 10px;
            margin-bottom: 10px;
        }
        
        .btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }
        
        .btn-danger {
            background: linear-gradient(45deg, #f44336, #e91e63);
        }
        
        .alert {
            padding: 15px;
            border-radius: 8px;
            margin: 15px 0;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .alert-info {
            background: #cce7ff;
            color: #004085;
            border: 1px solid #99d1ff;
        }
        
        .progress-section {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 25px;
            margin-bottom: 25px;
            display: none;
        }
        
        .progress-bar {
            background: #e0e0e0;
            border-radius: 10px;
            height: 25px;
            margin: 15px 0;
            overflow: hidden;
            position: relative;
        }
        
        .progress-fill {
            background: linear-gradient(45deg, #4CAF50, #45a049);
            height: 100%;
            width: 0%;
            transition: width 0.5s ease;
        }
        
        .progress-text {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            font-weight: bold;
            color: #333;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin: 20px 0;
        }
        
        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            text-align: center;
            box-shadow: 0 3px 10px rgba(0,0,0,0.1);
        }
        
        .stat-number {
            font-size: 2em;
            font-weight: bold;
            color: #2196F3;
        }
        
        .log-container {
            background: #2d3748;
            color: #e2e8f0;
            padding: 20px;
            border-radius: 8px;
            max-height: 300px;
            overflow-y: auto;
            font-family: 'Courier New', monospace;
            font-size: 14px;
        }
        
        .debug-info {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 15px;
            margin-top: 20px;
        }
        
        .debug-info h4 {
            margin-bottom: 10px;
            color: #495057;
        }
        
        .debug-info pre {
            background: #e9ecef;
            padding: 10px;
            border-radius: 4px;
            overflow: auto;
            max-height: 200px;
            font-size: 12px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🛒 Walmart API Schema 处理器</h1>
            <p>稳健版 - 增强错误处理和调试功能</p>
        </div>
        
        <div class="content">
            <div class="config-info">
                <strong>当前配置:</strong>
                分块大小: <?php echo number_format($config['chunk_size']/1024/1024, 1); ?>MB |
                内存限制: <?php echo $config['memory_limit']; ?> |
                执行时间: <?php echo $config['max_execution_time']; ?>秒 |
                每次最大对象数: <?php echo $config['max_objects_per_chunk']; ?> |
                调试模式: <?php echo $config['debug'] ? '开启' : '关闭'; ?>
                <br>
                <small style="color: #666;">
                    💡 提示: 分块大小越大处理越快，但占用内存也越多。如果出现内存不足，请减小分块大小。
                </small>
            </div>
            
            <div class="form-section">
                <h2>📁 文件处理</h2>
                <div class="form-group">
                    <label for="file_path">文件路径:</label>
                    <input type="text" id="file_path" 
                           placeholder="输入JSON文件的完整路径，如: ./MP_ITEM-5.0.20241118-04_39_24-api2.json"
                           value="./MP_ITEM-5.0.20241118-04_39_24-api2.json">
                </div>
                
                <button id="start-btn" class="btn">🚀 开始处理</button>
                <button id="stop-btn" class="btn btn-danger" disabled>⏹️ 停止处理</button>
                <button id="export-btn" class="btn" disabled>📥 导出结果</button>
                <button id="clear-btn" class="btn">🗑️ 清理状态</button>
                <button id="test-btn" class="btn">🔧 测试连接</button>
            </div>
            
            <div id="progress-section" class="progress-section">
                <h3>⚡ 处理进度</h3>
                <div class="progress-bar">
                    <div id="progress-fill" class="progress-fill"></div>
                    <div id="progress-text" class="progress-text">0%</div>
                </div>
                
                <div class="stats-grid">
                    <div class="stat-card">
                        <div id="categories-count" class="stat-number">0</div>
                        <div class="stat-label">分类数量</div>
                    </div>
                    <div class="stat-card">
                        <div id="attributes-count" class="stat-number">0</div>
                        <div class="stat-label">属性数量</div>
                    </div>
                    <div class="stat-card">
                        <div id="netcontent-count" class="stat-number">0</div>
                        <div class="stat-label">netContent字段</div>
                    </div>
                    <div class="stat-card">
                        <div id="walmart-categories-count" class="stat-number">0</div>
                        <div class="stat-label">Walmart分类</div>
                    </div>
                    <div class="stat-card">
                        <div id="objects-count" class="stat-number">0</div>
                        <div class="stat-label">已处理对象</div>
                    </div>
                    <div class="stat-card">
                        <div id="elapsed-time" class="stat-number">0</div>
                        <div class="stat-label">耗时(秒)</div>
                    </div>
                    <div class="stat-card">
                        <div id="memory-usage" class="stat-number">0MB</div>
                        <div class="stat-label">内存使用</div>
                    </div>
                </div>
                
                <div id="log-section">
                    <h4>📋 处理日志</h4>
                    <div id="log-container" class="log-container">
                        <div class="log-entry">
                            <span class="log-time">[<?php echo date('H:i:s'); ?>]</span> 
                            系统就绪，等待开始处理...
                        </div>
                    </div>
                </div>
            </div>
            
            <?php if ($current_status && $current_status['success']): ?>
            <div class="alert alert-info">
                <strong>当前状态:</strong> 
                <?php 
                $state = $current_status['state'];
                echo "状态: {$state['status']} | ";
                echo "进度: " . number_format($state['progress'], 2) . "% | ";
                echo "分类: {$state['categories_count']} | ";
                echo "属性: {$state['attributes_count']} | ";
                echo "对象: {$state['objects_processed']}";
                ?>
            </div>
            <?php endif; ?>
            
            <div id="alert-container"></div>
            
            <?php if ($config['debug']): ?>
            <div class="debug-info">
                <h4>🔍 调试信息</h4>
                <p><strong>PHP版本:</strong> <?php echo PHP_VERSION; ?></p>
                <p><strong>内存限制:</strong> <?php echo ini_get('memory_limit'); ?></p>
                <p><strong>执行时间限制:</strong> <?php echo ini_get('max_execution_time'); ?>秒</p>
                <p><strong>当前内存使用:</strong> <?php echo number_format(memory_get_usage(true)/1024/1024, 2); ?>MB</p>
                <p><strong>峰值内存使用:</strong> <?php echo number_format(memory_get_peak_usage(true)/1024/1024, 2); ?>MB</p>
                
                <h5>错误日志 (最近10条):</h5>
                <pre id="error-log">
<?php
if (file_exists('./error.log')) {
    $logs = file('./error.log');
    echo htmlspecialchars(implode('', array_slice($logs, -10)));
} else {
    echo "无错误日志";
}
?>
                </pre>
                
                <h5>调试日志 (最近10条):</h5>
                <pre id="debug-log">
<?php
if (file_exists('./debug.log')) {
    $logs = file('./debug.log');
    echo htmlspecialchars(implode('', array_slice($logs, -10)));
} else {
    echo "无调试日志";
}
?>
                </pre>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <script>
    class WalmartProcessor {
        constructor() {
            this.isProcessing = false;
            this.processingInterval = null;
            this.startTime = null;
            this.consecutiveErrors = 0;
            this.maxConsecutiveErrors = 3;
            
            this.initElements();
            this.bindEvents();
            this.updateUI();
            
            // 添加错误处理
            window.addEventListener('error', (e) => {
                this.logError('JavaScript错误: ' + e.message);
            });
        }
        
        initElements() {
            this.elements = {
                filePathInput: document.getElementById('file_path'),
                startBtn: document.getElementById('start-btn'),
                stopBtn: document.getElementById('stop-btn'),
                exportBtn: document.getElementById('export-btn'),
                clearBtn: document.getElementById('clear-btn'),
                testBtn: document.getElementById('test-btn'),
                progressSection: document.getElementById('progress-section'),
                progressFill: document.getElementById('progress-fill'),
                progressText: document.getElementById('progress-text'),
                categoriesCount: document.getElementById('categories-count'),
                attributesCount: document.getElementById('attributes-count'),
                netcontentCount: document.getElementById('netcontent-count'),
                walmartCategoriesCount: document.getElementById('walmart-categories-count'),
                objectsCount: document.getElementById('objects-count'),
                elapsedTime: document.getElementById('elapsed-time'),
                memoryUsage: document.getElementById('memory-usage'),
                logContainer: document.getElementById('log-container'),
                alertContainer: document.getElementById('alert-container')
            };
        }
        
        bindEvents() {
            this.elements.startBtn.addEventListener('click', () => this.startProcessing());
            this.elements.stopBtn.addEventListener('click', () => this.stopProcessing());
            this.elements.exportBtn.addEventListener('click', () => this.exportResults());
            this.elements.clearBtn.addEventListener('click', () => this.clearSession());
            this.elements.testBtn.addEventListener('click', () => this.testConnection());
        }
        
        updateUI() {
            this.elements.startBtn.disabled = this.isProcessing;
            this.elements.stopBtn.disabled = !this.isProcessing;
            this.elements.exportBtn.disabled = this.isProcessing;
            this.elements.filePathInput.disabled = this.isProcessing;
        }
        
        async testConnection() {
            this.log('测试服务器连接...');
            
            try {
                const response = await fetch(window.location.href, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'action=status'
                });
                
                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                }
                
                const data = await response.json();
                this.log('服务器连接正常: ' + JSON.stringify(data));
                this.showAlert('服务器连接测试成功', 'success');
                
            } catch (error) {
                this.logError('连接测试失败: ' + error.message);
                this.showAlert('服务器连接测试失败: ' + error.message, 'error');
            }
        }
        
        async startProcessing() {
            const filePath = this.elements.filePathInput.value.trim();
            if (!filePath) {
                this.showAlert('请输入文件路径', 'error');
                return;
            }
            
            try {
                this.log('初始化... 正在获取分类列表...');
                this.consecutiveErrors = 0;
                
                const response = await this.apiCall('start', { file_path: filePath });
                
                if (response.success) {
                    this.isProcessing = true;
                    this.startTime = Date.now();
                    this.elements.progressSection.style.display = 'block';
                    this.updateUI();
                    
                    this.log('初始化成功，文件大小: ' + this.formatBytes(response.file_size));
                    this.showAlert('开始处理，文件大小: ' + this.formatBytes(response.file_size), 'success');
                    
                    // 延迟开始处理循环，让初始化完成
                    setTimeout(() => this.startProgressLoop(), 1000);
                } else {
                    this.logError('初始化失败: ' + response.message);
                    this.showAlert('启动失败: ' + response.message, 'error');
                }
            } catch (error) {
                this.logError('初始化请求失败: ' + error.toString() + '. 后台PHP可能因超时或内存不足而崩溃，请检查PHP错误日志。');
                this.showAlert('初始化请求失败: ' + error.toString() + '. 后台PHP可能因超时或内存不足而崩溃，请检查PHP错误日志。', 'error');
            }
        }
        
        async stopProcessing() {
            try {
                this.isProcessing = false;
                this.updateUI();
                this.stopProgressLoop();
                
                await this.apiCall('stop');
                this.log('处理已停止');
                this.showAlert('处理已停止', 'info');
            } catch (error) {
                this.logError('停止处理失败: ' + error.message);
            }
        }
        
        async exportResults() {
            try {
                this.log('正在导出分析结果...');

                const response = await this.apiCall('export');

                if (response.success) {
                    this.log('导出成功: ' + response.filename);
                    this.showAlert(`导出成功！文件: ${response.filename}`, 'success');

                    // 显示导出摘要
                    const summary = response.summary;
                    this.log(`导出摘要: 分类${summary.categories}, 属性${summary.attributes}, netContent${summary.netcontent_fields}, Walmart分类${summary.walmart_categories}`);
                } else {
                    this.logError('导出失败: ' + response.message);
                    this.showAlert('导出失败: ' + response.message, 'error');
                }
            } catch (error) {
                this.logError('导出请求失败: ' + error.message);
                this.showAlert('导出请求失败: ' + error.message, 'error');
            }
        }

        async clearSession() {
            try {
                this.isProcessing = false;
                this.updateUI();
                this.stopProgressLoop();

                await this.apiCall('clear');

                this.elements.progressSection.style.display = 'none';
                this.resetStats();
                this.log('会话已清理');
                this.showAlert('会话状态已清理', 'success');

                // 刷新页面以获取最新状态
                setTimeout(() => location.reload(), 1000);
            } catch (error) {
                this.logError('清理会话失败: ' + error.message);
            }
        }
        
        startProgressLoop() {
            this.processingInterval = setInterval(async () => {
                try {
                    const response = await this.apiCall('process');
                    
                    if (response.success) {
                        this.consecutiveErrors = 0; // 重置错误计数
                        this.updateProgress(response);
                        
                        if (response.completed) {
                            this.completeProcessing(response);
                        }
                    } else {
                        this.consecutiveErrors++;
                        this.logError(`处理错误 (${this.consecutiveErrors}/${this.maxConsecutiveErrors}): ` + response.message);
                        
                        if (this.consecutiveErrors >= this.maxConsecutiveErrors) {
                            this.showAlert('连续错误过多，停止处理', 'error');
                            this.stopProcessing();
                        }
                    }
                } catch (error) {
                    this.consecutiveErrors++;
                    this.logError(`请求错误 (${this.consecutiveErrors}/${this.maxConsecutiveErrors}): ` + error.message);
                    
                    if (this.consecutiveErrors >= this.maxConsecutiveErrors) {
                        this.showAlert('连续网络错误过多，停止处理: ' + error.message, 'error');
                        this.stopProcessing();
                    }
                }
            }, 2000); // 增加到2秒间隔，减少服务器压力
        }
        
        stopProgressLoop() {
            if (this.processingInterval) {
                clearInterval(this.processingInterval);
                this.processingInterval = null;
            }
        }
        
        updateProgress(data) {
            const progress = Math.round(data.progress || 0);
            this.elements.progressFill.style.width = progress + '%';
            this.elements.progressText.textContent = progress + '%';
            
            this.elements.categoriesCount.textContent = data.categories_count || 0;
            this.elements.attributesCount.textContent = data.attributes_count || 0;
            this.elements.netcontentCount.textContent = data.netcontent_count || 0;
            this.elements.walmartCategoriesCount.textContent = data.walmart_categories_count || 0;
            this.elements.objectsCount.textContent = data.objects_processed || 0;
            
            if (data.memory_usage) {
                this.elements.memoryUsage.textContent = Math.round(data.memory_usage / 1024 / 1024) + 'MB';
            }
            
            if (this.startTime) {
                const elapsed = Math.round((Date.now() - this.startTime) / 1000);
                this.elements.elapsedTime.textContent = elapsed;
            }
            
            // 定期更新日志
            if (data.objects_processed > 0) {
                this.log(`进度: ${progress}% | 分类: ${data.categories_count} | 属性: ${data.attributes_count} | netContent: ${data.netcontent_count} | Walmart分类: ${data.walmart_categories_count} | 对象: ${data.objects_processed}`);
            }
        }
        
        completeProcessing(data) {
            this.isProcessing = false;
            this.updateUI();
            this.stopProgressLoop();
            
            this.log('🎉 处理完成！');
            this.showAlert('处理完成！', 'success');
            
            // 显示最终统计
            const finalStats = `
                总计: ${data.categories_count} 个分类,
                ${data.attributes_count} 个属性,
                ${data.netcontent_count} 个netContent字段,
                ${data.walmart_categories_count} 个Walmart分类,
                ${data.objects_processed} 个对象已处理
            `;
            this.log(finalStats);
        }
        
        async apiCall(action, data = {}) {
            const formData = new FormData();
            formData.append('action', action);
            
            for (const key in data) {
                formData.append(key, data[key]);
            }
            
            const controller = new AbortController();
            const timeoutId = setTimeout(() => controller.abort(), 600000); // 600秒超时
            
            try {
                const response = await fetch(window.location.href, {
                    method: 'POST',
                    body: formData,
                    signal: controller.signal
                });
                
                clearTimeout(timeoutId);
                
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                
                const contentType = response.headers.get('content-type');
                if (!contentType || !contentType.includes('application/json')) {
                    const text = await response.text();
                    throw new Error('服务器返回非JSON响应: ' + text.substring(0, 200));
                }
                
                return await response.json();
                
            } catch (error) {
                clearTimeout(timeoutId);
                if (error.name === 'AbortError') {
                    throw new Error('请求超时 (600秒)');
                }
                throw error;
            }
        }
        
        log(message) {
            const time = new Date().toLocaleTimeString();
            const logEntry = document.createElement('div');
            logEntry.className = 'log-entry';
            logEntry.innerHTML = `<span class="log-time">[${time}]</span> ${message}`;
            
            this.elements.logContainer.appendChild(logEntry);
            this.elements.logContainer.scrollTop = this.elements.logContainer.scrollHeight;
            
            // 限制日志条数
            const entries = this.elements.logContainer.children;
            if (entries.length > 50) {
                this.elements.logContainer.removeChild(entries[0]);
            }
        }
        
        logError(message) {
            console.error(message);
            this.log('❌ ' + message);
        }
        
        showAlert(message, type = 'info') {
            const alert = document.createElement('div');
            alert.className = `alert alert-${type}`;
            alert.textContent = message;
            
            this.elements.alertContainer.appendChild(alert);
            
            // 自动移除警告
            setTimeout(() => {
                if (alert.parentNode) {
                    alert.parentNode.removeChild(alert);
                }
            }, 8000); // 8秒后自动消失
            
            // 限制警告数量
            const alerts = this.elements.alertContainer.children;
            if (alerts.length > 3) {
                this.elements.alertContainer.removeChild(alerts[0]);
            }
        }
        
        resetStats() {
            this.elements.categoriesCount.textContent = '0';
            this.elements.attributesCount.textContent = '0';
            this.elements.netcontentCount.textContent = '0';
            this.elements.walmartCategoriesCount.textContent = '0';
            this.elements.objectsCount.textContent = '0';
            this.elements.elapsedTime.textContent = '0';
            this.elements.memoryUsage.textContent = '0MB';
            this.elements.progressFill.style.width = '0%';
            this.elements.progressText.textContent = '0%';
        }
        
        formatBytes(bytes) {
            if (bytes === 0) return '0 Bytes';
            
            const k = 1024;
            const sizes = ['Bytes', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            
            return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
        }
    }
    
    // 初始化处理器
    document.addEventListener('DOMContentLoaded', function() {
        try {
            new WalmartProcessor();
        } catch (error) {
            console.error('初始化失败:', error);
            alert('页面初始化失败: ' + error.message);
        }
    });
    </script>
</body>
</html>