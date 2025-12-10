<?php
/**
 * 远程图片验证器
 * 专门处理远程图片的尺寸、大小、格式验证
 * 优化网络请求，支持缓存和批量验证
 * 
 * @package WooWalmartSync
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class WooWalmartSync_Remote_Image_Validator {
    
    // Walmart图片要求（简化版）
    const MAX_FILE_SIZE = 5 * 1024 * 1024; // 5MB
    
    // 缓存相关
    const CACHE_EXPIRY = 3600; // 1小时缓存
    const CACHE_PREFIX = 'walmart_remote_image_';
    
    // 网络请求配置
    const REQUEST_TIMEOUT = 15; // 15秒超时
    const MAX_HEADER_SIZE = 32768; // 32KB头部数据
    const USER_AGENT = 'WooCommerce-Walmart-Sync/1.0';
    
    /**
     * 验证远程图片是否符合Walmart要求
     * 
     * @param string $image_url 远程图片URL
     * @param bool $is_main_image 是否为主图
     * @param bool $use_cache 是否使用缓存
     * @return array 验证结果
     */
    public function validate_remote_image($image_url, $is_main_image = false, $use_cache = true) {
        $validation_result = [
            'valid' => false,
            'errors' => [],
            'warnings' => [],
            'image_info' => null,
            'cached' => false,
            'validation_time' => microtime(true)
        ];
        
        // 基础URL验证
        if (!filter_var($image_url, FILTER_VALIDATE_URL)) {
            $validation_result['errors'][] = '无效的图片URL格式';
            return $validation_result;
        }
        
        // 检查缓存
        if ($use_cache) {
            $cached_result = $this->get_cached_validation($image_url);
            if ($cached_result !== false) {
                $cached_result['cached'] = true;
                return $cached_result;
            }
        }
        
        // 获取远程图片信息
        $image_info = $this->get_remote_image_info($image_url);
        
        if (!$image_info) {
            $validation_result['errors'][] = '无法获取远程图片信息或图片不存在';
            return $validation_result;
        }
        
        $validation_result['image_info'] = $image_info;
        
        // 只验证文件大小
        $this->validate_file_size($image_info, $validation_result);
        
        // 设置验证结果
        $validation_result['valid'] = empty($validation_result['errors']);
        $validation_result['validation_time'] = microtime(true) - $validation_result['validation_time'];
        
        // 缓存结果
        if ($use_cache) {
            $this->cache_validation_result($image_url, $validation_result);
        }
        
        return $validation_result;
    }
    
    /**
     * 批量验证远程图片
     * 
     * @param array $image_urls 图片URL数组
     * @param bool $parallel 是否并行处理
     * @return array 批量验证结果
     */
    public function batch_validate_remote_images($image_urls, $parallel = true) {
        $results = [
            'total_images' => count($image_urls),
            'valid_images' => 0,
            'invalid_images' => 0,
            'cached_results' => 0,
            'validation_time' => microtime(true),
            'details' => []
        ];
        
        if ($parallel && function_exists('curl_multi_init')) {
            // 并行处理（如果支持）
            $results['details'] = $this->parallel_validate_images($image_urls);
        } else {
            // 串行处理
            foreach ($image_urls as $url) {
                $results['details'][$url] = $this->validate_remote_image($url);
            }
        }
        
        // 统计结果
        foreach ($results['details'] as $result) {
            if ($result['valid']) {
                $results['valid_images']++;
            } else {
                $results['invalid_images']++;
            }
            
            if ($result['cached']) {
                $results['cached_results']++;
            }
        }
        
        $results['validation_time'] = microtime(true) - $results['validation_time'];
        
        return $results;
    }
    
    /**
     * 获取远程图片信息（简化版 - 只获取文件大小）
     *
     * @param string $image_url 图片URL
     * @return array|false 图片信息或false
     */
    private function get_remote_image_info($image_url) {
        try {
            // 只获取HTTP头信息来检查文件大小
            $headers = $this->get_remote_headers($image_url);
            if (!$headers) {
                return false;
            }

            $file_size = $this->extract_content_length($headers);

            return [
                'size' => $file_size,
                'url' => $image_url,
                'headers' => $headers
            ];

        } catch (Exception $e) {
            woo_walmart_sync_log('远程图片验证', '错误', [
                'image_url' => $image_url,
                'error' => $e->getMessage()
            ], '获取远程图片信息失败');

            return false;
        }
    }
    
    /**
     * 获取远程HTTP头信息
     * 
     * @param string $url 图片URL
     * @return array|false HTTP头信息
     */
    private function get_remote_headers($url) {
        $context = stream_context_create([
            'http' => [
                'method' => 'HEAD',
                'timeout' => self::REQUEST_TIMEOUT,
                'user_agent' => self::USER_AGENT,
                'follow_location' => true,
                'max_redirects' => 3
            ]
        ]);
        
        $headers = get_headers($url, 1, $context);
        
        if (!$headers) {
            return false;
        }
        
        // 处理重定向后的头信息
        if (isset($headers[0]) && is_array($headers[0])) {
            // 多次重定向，取最后一次的头信息
            $final_headers = [];
            foreach ($headers as $key => $value) {
                if (is_numeric($key)) continue;
                $final_headers[$key] = is_array($value) ? end($value) : $value;
            }
            return $final_headers;
        }
        
        return $headers;
    }
    
    /**
     * 下载图片头部数据（用于获取尺寸）
     * 
     * @param string $url 图片URL
     * @param int $max_bytes 最大下载字节数
     * @return string|false 图片头部数据
     */
    private function download_image_header($url, $max_bytes = self::MAX_HEADER_SIZE) {
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => self::REQUEST_TIMEOUT,
                'user_agent' => self::USER_AGENT,
                'header' => "Range: bytes=0-" . ($max_bytes - 1) . "\r\n"
            ]
        ]);
        
        return file_get_contents($url, false, $context, 0, $max_bytes);
    }
    
    /**
     * 从MIME类型获取格式名称
     * 
     * @param string $mime MIME类型
     * @return string 格式名称
     */
    private function get_format_from_mime($mime) {
        $mime_to_format = [
            'image/jpeg' => 'jpeg',
            'image/jpg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp'
        ];
        
        return isset($mime_to_format[$mime]) ? $mime_to_format[$mime] : 'unknown';
    }
    
    /**
     * 从HTTP头提取Content-Length
     * 
     * @param array $headers HTTP头信息
     * @return int 文件大小（字节）
     */
    private function extract_content_length($headers) {
        $content_length = 0;
        
        if (isset($headers['Content-Length'])) {
            $content_length = (int)$headers['Content-Length'];
        } elseif (isset($headers['content-length'])) {
            $content_length = (int)$headers['content-length'];
        }
        
        return $content_length;
    }

    /**
     * 验证文件大小
     *
     * @param array $image_info 图片信息
     * @param array &$validation_result 验证结果（引用传递）
     */
    private function validate_file_size($image_info, &$validation_result) {
        if ($image_info['size'] > 0 && $image_info['size'] > self::MAX_FILE_SIZE) {
            $validation_result['errors'][] = sprintf(
                '图片文件过大：%.2fMB，超过5MB限制',
                $image_info['size'] / 1024 / 1024
            );
        } elseif ($image_info['size'] == 0) {
            $validation_result['errors'][] = '无法获取图片文件大小，图片可能不存在或服务器不支持';
        }
    }



    /**
     * 获取缓存的验证结果
     *
     * @param string $image_url 图片URL
     * @return array|false 缓存的验证结果或false
     */
    private function get_cached_validation($image_url) {
        $cache_key = self::CACHE_PREFIX . md5($image_url);
        $cached_data = get_transient($cache_key);

        if ($cached_data !== false) {
            // 检查缓存是否过期
            if (isset($cached_data['cached_time']) &&
                (time() - $cached_data['cached_time']) < self::CACHE_EXPIRY) {
                return $cached_data;
            }
        }

        return false;
    }

    /**
     * 缓存验证结果
     *
     * @param string $image_url 图片URL
     * @param array $validation_result 验证结果
     */
    private function cache_validation_result($image_url, $validation_result) {
        $cache_key = self::CACHE_PREFIX . md5($image_url);
        $validation_result['cached_time'] = time();

        // 缓存1小时
        set_transient($cache_key, $validation_result, self::CACHE_EXPIRY);
    }

    /**
     * 并行验证多个图片（使用cURL Multi）
     *
     * @param array $image_urls 图片URL数组
     * @return array 验证结果数组
     */
    private function parallel_validate_images($image_urls) {
        $results = [];
        $curl_handles = [];
        $multi_handle = curl_multi_init();

        // 创建cURL句柄
        foreach ($image_urls as $url) {
            // 先检查缓存
            $cached_result = $this->get_cached_validation($url);
            if ($cached_result !== false) {
                $cached_result['cached'] = true;
                $results[$url] = $cached_result;
                continue;
            }

            $curl_handle = curl_init();
            curl_setopt_array($curl_handle, [
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HEADER => true,
                CURLOPT_NOBODY => false,
                CURLOPT_RANGE => '0-' . (self::MAX_HEADER_SIZE - 1),
                CURLOPT_TIMEOUT => self::REQUEST_TIMEOUT,
                CURLOPT_USERAGENT => self::USER_AGENT,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_MAXREDIRS => 3,
                CURLOPT_SSL_VERIFYPEER => false
            ]);

            curl_multi_add_handle($multi_handle, $curl_handle);
            $curl_handles[$url] = $curl_handle;
        }

        // 执行并行请求
        $running = null;
        do {
            curl_multi_exec($multi_handle, $running);
            curl_multi_select($multi_handle);
        } while ($running > 0);

        // 处理结果
        foreach ($curl_handles as $url => $curl_handle) {
            $response = curl_multi_getcontent($curl_handle);
            $http_code = curl_getinfo($curl_handle, CURLINFO_HTTP_CODE);

            if ($http_code == 200 || $http_code == 206) {
                $results[$url] = $this->process_curl_response($url, $response);
            } else {
                $results[$url] = [
                    'valid' => false,
                    'errors' => ["HTTP错误：{$http_code}"],
                    'warnings' => [],
                    'image_info' => null,
                    'cached' => false
                ];
            }

            curl_multi_remove_handle($multi_handle, $curl_handle);
            curl_close($curl_handle);
        }

        curl_multi_close($multi_handle);

        return $results;
    }

    /**
     * 处理cURL响应
     *
     * @param string $url 图片URL
     * @param string $response cURL响应
     * @return array 验证结果
     */
    private function process_curl_response($url, $response) {
        $validation_result = [
            'valid' => false,
            'errors' => [],
            'warnings' => [],
            'image_info' => null,
            'cached' => false,
            'validation_time' => microtime(true)
        ];

        // 分离头部和内容
        $header_size = strpos($response, "\r\n\r\n");
        if ($header_size === false) {
            $validation_result['errors'][] = '无法解析HTTP响应';
            return $validation_result;
        }

        $headers = substr($response, 0, $header_size);
        $body = substr($response, $header_size + 4);

        // 解析头部信息
        $parsed_headers = $this->parse_response_headers($headers);

        // 获取图片信息
        $image_info = getimagesizefromstring($body);
        if (!$image_info) {
            $validation_result['errors'][] = '无法解析图片格式或图片损坏';
            return $validation_result;
        }

        $image_info_array = [
            'width' => $image_info[0],
            'height' => $image_info[1],
            'format' => $this->get_format_from_mime($image_info['mime']),
            'mime' => $image_info['mime'],
            'size' => $this->extract_content_length($parsed_headers),
            'url' => $url,
            'headers' => $parsed_headers
        ];

        $validation_result['image_info'] = $image_info_array;

        // 执行验证
        $this->validate_file_size($image_info_array, $validation_result);
        $this->validate_image_format($image_info_array, $validation_result);
        $this->validate_image_dimensions($image_info_array, $validation_result);
        $this->validate_aspect_ratio($image_info_array, $validation_result);

        $validation_result['valid'] = empty($validation_result['errors']);
        $validation_result['validation_time'] = microtime(true) - $validation_result['validation_time'];

        // 缓存结果
        $this->cache_validation_result($url, $validation_result);

        return $validation_result;
    }

    /**
     * 解析HTTP响应头
     *
     * @param string $headers 原始头部字符串
     * @return array 解析后的头部数组
     */
    private function parse_response_headers($headers) {
        $parsed = [];
        $lines = explode("\r\n", $headers);

        foreach ($lines as $line) {
            if (strpos($line, ':') !== false) {
                list($key, $value) = explode(':', $line, 2);
                $parsed[trim($key)] = trim($value);
            }
        }

        return $parsed;
    }
}
