<?php
/**
 * Walmart图片扫描器类
 * 
 * 功能：
 * 1. 扫描产品中超过5MB的图片
 * 2. 支持按分类和SKU批量扫描
 * 3. 提供删除和标记跳过两种处理方式
 */

class Walmart_Image_Scanner {
    
    private $size_limit = 5242880; // 5MB = 5 * 1024 * 1024
    
    /**
     * 快速获取远程图片大小 (只获取Content-Length)
     */
    public function get_image_size_fast($url) {
        static $cache = [];
        
        if (isset($cache[$url])) {
            return $cache[$url];
        }
        
        try {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_NOBODY, true);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            
            curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $content_length = curl_getinfo($ch, CURLINFO_CONTENT_LENGTH_DOWNLOAD);
            
            curl_close($ch);
            
            $size = ($http_code == 200 && $content_length > 0) ? (int)$content_length : 0;
            return $cache[$url] = $size;
            
        } catch (Exception $e) {
            return $cache[$url] = 0;
        }
    }
    
    /**
     * 扫描单个产品的图片
     */
    public function scan_product_images($product) {
        $product_id = $product->get_id();
        $remote_gallery = get_post_meta($product_id, '_remote_gallery_urls', true);
        
        if (!is_array($remote_gallery) || empty($remote_gallery)) {
            return [
                'product_id' => $product_id,
                'sku' => $product->get_sku(),
                'name' => $product->get_name(),
                'has_oversized' => false,
                'total_images' => 0,
                'oversized_images' => [],
                'total_size' => 0
            ];
        }
        
        $oversized_images = [];
        $total_size = 0;
        
        foreach ($remote_gallery as $index => $url) {
            $size = $this->get_image_size_fast($url);
            $total_size += $size;
            
            if ($size > $this->size_limit) {
                $oversized_images[] = [
                    'index' => $index,
                    'url' => $url,
                    'size' => $size,
                    'is_main' => ($index === 0),
                    'size_formatted' => $this->format_file_size($size)
                ];
            }
        }
        
        return [
            'product_id' => $product_id,
            'sku' => $product->get_sku(),
            'name' => $product->get_name(),
            'has_oversized' => !empty($oversized_images),
            'oversized_images' => $oversized_images,
            'oversized_count' => count($oversized_images),
            'total_images' => count($remote_gallery),
            'total_size' => $total_size,
            'total_size_formatted' => $this->format_file_size($total_size)
        ];
    }
    
    /**
     * 按分类扫描产品
     */
    public function scan_by_category($category_identifier) {
        global $wpdb;

        $products = [];
        $category_name = '';

        // 判断传入的是WC分类ID还是Walmart分类路径
        if (is_numeric($category_identifier)) {
            // WC分类ID
            $wc_category_id = intval($category_identifier);
            $category = get_term($wc_category_id, 'product_cat');

            if ($category && !is_wp_error($category)) {
                $category_name = $category->name;

                // 获取该分类及其子分类下的所有产品
                $category_ids = [$wc_category_id];

                // 获取所有子分类
                $child_categories = get_term_children($wc_category_id, 'product_cat');
                if (!is_wp_error($child_categories)) {
                    $category_ids = array_merge($category_ids, $child_categories);
                }

                $category_ids_str = implode(',', array_map('intval', $category_ids));

                $products = $wpdb->get_results("
                    SELECT DISTINCT p.ID
                    FROM {$wpdb->posts} p
                    INNER JOIN {$wpdb->term_relationships} tr ON p.ID = tr.object_id
                    INNER JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
                    WHERE p.post_type = 'product'
                    AND p.post_status = 'publish'
                    AND tt.taxonomy = 'product_cat'
                    AND tt.term_id IN ({$category_ids_str})
                ", ARRAY_A);
            }
        } else {
            // Walmart分类路径（向后兼容）
            $category_name = $category_identifier;

            // 方法1: 从分类映射表获取该分类的WC分类ID
            $wc_category_ids = $wpdb->get_col($wpdb->prepare("
                SELECT wc_category_id
                FROM {$wpdb->prefix}walmart_category_map
                WHERE walmart_category_path = %s
            ", $category_identifier));

            if (!empty($wc_category_ids)) {
                $wc_category_ids_str = implode(',', array_map('intval', $wc_category_ids));

                $products = $wpdb->get_results("
                    SELECT DISTINCT p.ID
                    FROM {$wpdb->posts} p
                    INNER JOIN {$wpdb->term_relationships} tr ON p.ID = tr.object_id
                    INNER JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
                    WHERE p.post_type = 'product'
                    AND p.post_status = 'publish'
                    AND tt.taxonomy = 'product_cat'
                    AND tt.term_id IN ({$wc_category_ids_str})
                ", ARRAY_A);
            }

            // 方法2: 如果没有找到产品，尝试通过产品的_walmart_category字段查找
            if (empty($products)) {
                $products = $wpdb->get_results($wpdb->prepare("
                    SELECT p.ID
                    FROM {$wpdb->posts} p
                    LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_walmart_category'
                    WHERE p.post_type = 'product'
                    AND p.post_status = 'publish'
                    AND pm.meta_value = %s
                ", $category_identifier), ARRAY_A);
            }
        }

        $results = [];
        foreach ($products as $product_data) {
            $product = wc_get_product($product_data['ID']);
            if ($product) {
                $scan_result = $this->scan_product_images($product);
                if ($scan_result['has_oversized']) {
                    $results[] = $scan_result;
                }
            }
        }

        return [
            'scan_type' => 'category',
            'scan_target' => $category_name,
            'total_products' => count($products),
            'problem_products' => count($results),
            'results' => $results
        ];
    }
    
    /**
     * 按SKU批量扫描
     */
    public function scan_by_skus($sku_list) {
        $skus = array_filter(array_map('trim', explode("\n", $sku_list)));
        $results = [];
        $found_products = 0;
        
        global $wpdb;
        
        foreach ($skus as $sku) {
            $product_id = $wpdb->get_var($wpdb->prepare("
                SELECT post_id FROM {$wpdb->postmeta} 
                WHERE meta_key = '_sku' AND meta_value = %s
            ", $sku));
            
            if ($product_id) {
                $product = wc_get_product($product_id);
                if ($product) {
                    $found_products++;
                    $scan_result = $this->scan_product_images($product);
                    if ($scan_result['has_oversized']) {
                        $results[] = $scan_result;
                    }
                }
            }
        }
        
        return [
            'scan_type' => 'sku_batch',
            'scan_target' => implode(', ', array_slice($skus, 0, 5)) . (count($skus) > 5 ? '...' : ''),
            'total_skus' => count($skus),
            'found_products' => $found_products,
            'problem_products' => count($results),
            'results' => $results
        ];
    }
    
    /**
     * 处理方式1: 直接删除超大图片
     */
    public function process_delete_oversized($product_id, $oversized_images) {
        $remote_gallery = get_post_meta($product_id, '_remote_gallery_urls', true);
        
        if (!is_array($remote_gallery)) {
            return ['success' => false, 'error' => '没有远程图库'];
        }
        
        // 获取要删除的索引
        $delete_indices = array_column($oversized_images, 'index');
        
        // 创建新的图片数组（排除要删除的）
        $new_remote_urls = [];
        foreach ($remote_gallery as $index => $url) {
            if (!in_array($index, $delete_indices)) {
                $new_remote_urls[] = $url;
            }
        }
        
        if (empty($new_remote_urls)) {
            return ['success' => false, 'error' => '删除后没有剩余图片'];
        }
        
        // 检查是否删除了主图
        $main_image_deleted = in_array(0, $delete_indices);
        
        try {
            // 更新远程图库
            update_post_meta($product_id, '_remote_gallery_urls', $new_remote_urls);
            
            // 如果主图被删除，更新主图ID
            if ($main_image_deleted) {
                $new_main_id = 'remote_' . md5($new_remote_urls[0] . time());
                update_post_meta($product_id, '_thumbnail_id', $new_main_id);
            }
            
            // 更新WC图库（副图从索引1开始）
            $new_gallery_ids = [];
            if (count($new_remote_urls) > 1) {
                for ($i = 1; $i < count($new_remote_urls); $i++) {
                    $new_gallery_ids[] = 'remote_' . md5($new_remote_urls[$i] . time() . $i);
                }
            }
            
            if (!empty($new_gallery_ids)) {
                update_post_meta($product_id, '_product_image_gallery', implode(',', $new_gallery_ids));
            } else {
                delete_post_meta($product_id, '_product_image_gallery');
            }
            
            return [
                'success' => true,
                'main_image_updated' => $main_image_deleted,
                'deleted_count' => count($delete_indices),
                'remaining_count' => count($new_remote_urls),
                'message' => "成功删除 " . count($delete_indices) . " 张超大图片，剩余 " . count($new_remote_urls) . " 张图片"
            ];
            
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * 处理方式2: 标记超大图片跳过
     */
    public function process_mark_oversized($product_id, $oversized_images) {
        try {
            // 获取当前的跳过标记
            $skip_indices = get_post_meta($product_id, '_walmart_skip_image_indices', true);
            if (!is_array($skip_indices)) {
                $skip_indices = [];
            }
            
            // 添加新的跳过索引
            $new_skip_indices = array_column($oversized_images, 'index');
            $skip_indices = array_unique(array_merge($skip_indices, $new_skip_indices));
            
            // 保存跳过标记
            update_post_meta($product_id, '_walmart_skip_image_indices', $skip_indices);
            
            // 如果主图被标记跳过，需要找到替代主图
            $main_replacement = null;
            if (in_array(0, $skip_indices)) {
                $remote_gallery = get_post_meta($product_id, '_remote_gallery_urls', true);
                if (is_array($remote_gallery)) {
                    // 找到第一个未被标记跳过的图片作为主图
                    for ($i = 1; $i < count($remote_gallery); $i++) {
                        if (!in_array($i, $skip_indices)) {
                            $main_replacement = $i;
                            break;
                        }
                    }
                }
            }
            
            return [
                'success' => true,
                'marked_count' => count($new_skip_indices),
                'total_skip_count' => count($skip_indices),
                'main_replacement' => $main_replacement,
                'message' => "成功标记 " . count($new_skip_indices) . " 张图片跳过，总计跳过 " . count($skip_indices) . " 张图片"
            ];
            
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * 获取产品的有效图片列表（排除跳过的图片）
     */
    public function get_valid_images($product_id) {
        $remote_gallery = get_post_meta($product_id, '_remote_gallery_urls', true);
        $skip_indices = get_post_meta($product_id, '_walmart_skip_image_indices', true);
        
        if (!is_array($remote_gallery)) {
            return [];
        }
        
        if (!is_array($skip_indices)) {
            $skip_indices = [];
        }
        
        $valid_images = [];
        foreach ($remote_gallery as $index => $url) {
            if (!in_array($index, $skip_indices)) {
                $valid_images[] = [
                    'index' => $index,
                    'url' => $url,
                    'is_main' => ($index === 0 && !in_array(0, $skip_indices)) || 
                                ($index > 0 && in_array(0, $skip_indices) && empty($valid_images))
                ];
            }
        }
        
        return $valid_images;
    }
    
    /**
     * 批量处理产品
     */
    public function batch_process($products, $process_type = 'delete') {
        $results = [
            'success_count' => 0,
            'failed_count' => 0,
            'details' => []
        ];
        
        foreach ($products as $product_data) {
            $product_id = $product_data['product_id'];
            $oversized_images = $product_data['oversized_images'];
            
            if ($process_type === 'delete') {
                $result = $this->process_delete_oversized($product_id, $oversized_images);
            } else {
                $result = $this->process_mark_oversized($product_id, $oversized_images);
            }
            
            if ($result['success']) {
                $results['success_count']++;
            } else {
                $results['failed_count']++;
            }
            
            $results['details'][] = [
                'product_id' => $product_id,
                'sku' => $product_data['sku'],
                'result' => $result
            ];
        }
        
        return $results;
    }
    
    /**
     * 格式化文件大小
     */
    private function format_file_size($bytes) {
        if ($bytes == 0) return '0 B';
        
        $units = ['B', 'KB', 'MB', 'GB'];
        $factor = floor(log($bytes, 1024));
        
        return sprintf("%.2f %s", $bytes / pow(1024, $factor), $units[$factor]);
    }
}
?>
