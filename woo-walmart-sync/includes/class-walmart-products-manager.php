<?php

/**
 * Walmart商品管理类
 * 负责从Walmart API获取商品信息并管理本地缓存
 */
class WooWalmartSync_Products_Manager {
    
    private $api_auth;
    private $cache_table;
    private $notifications_table;
    
    public function __construct() {
        global $wpdb;
        $this->api_auth = new Woo_Walmart_API_Key_Auth();
        $this->cache_table = $wpdb->prefix . 'walmart_products_cache';
        $this->notifications_table = $wpdb->prefix . 'walmart_sync_notifications';
    }
    
    /**
     * 从Walmart API获取所有商品信息
     * @param bool $force_refresh 是否强制刷新缓存
     * @return array
     */
    public function fetch_walmart_products($force_refresh = false) {
        global $wpdb;
        
        // 检查缓存是否需要更新（24小时）
        if (!$force_refresh) {
            $last_sync = $wpdb->get_var("SELECT MAX(last_sync_time) FROM {$this->cache_table}");
            if ($last_sync && (time() - strtotime($last_sync)) < 86400) {
                return [
                    'success' => true,
                    'message' => '使用缓存数据（24小时内已同步）',
                    'from_cache' => true
                ];
            }
        }
        
        $all_products = [];
        $total_fetched = 0;
        $errors = [];

        woo_walmart_sync_log('Walmart商品同步', '开始', [
            'force_refresh' => $force_refresh
        ], '开始从Walmart API获取所有商品信息');

        // 使用分页获取所有商品
        $limit = 200; // 每次获取200个商品
        $offset = 0;
        $has_more = true;

        while ($has_more) {
            $endpoint = "/v3/items?limit={$limit}&offset={$offset}";
            $result = $this->api_auth->make_request($endpoint);

            woo_walmart_sync_log('商品API调用', '调试', [
                'endpoint' => $endpoint,
                'limit' => $limit,
                'offset' => $offset,
                'api_response' => $result
            ], "调用商品API: offset={$offset}, limit={$limit}");

            if (is_wp_error($result)) {
                $error_msg = $result->get_error_message();
                $errors[] = "API调用失败 (offset: {$offset}): {$error_msg}";

                // 记录错误通知
                $this->add_notification(
                    'api_error',
                    'Walmart API调用失败',
                    "获取商品列表时发生错误: {$error_msg}",
                    'error',
                    ['offset' => $offset, 'error' => $error_msg]
                );
                break; // 出错时停止
            } else {
                if (isset($result['ItemResponse']) && !empty($result['ItemResponse'])) {
                    $items = $result['ItemResponse'];
                    if (!is_array($items)) {
                        $items = [$items]; // 确保是数组
                    }

                    $current_batch_count = count($items);

                    foreach ($items as $item) {
                        $product_data = $this->parse_walmart_product($item);
                        if ($product_data) {
                            $all_products[] = $product_data;
                            $total_fetched++;
                        }
                    }

                    // 检查是否还有更多数据
                    if ($current_batch_count < $limit) {
                        $has_more = false; // 没有更多数据了
                    } else {
                        $offset += $limit; // 继续下一页
                    }

                    woo_walmart_sync_log('商品批次处理', '成功', [
                        'offset' => $offset - $limit,
                        'limit' => $limit,
                        'current_batch_count' => $current_batch_count,
                        'total_fetched' => $total_fetched,
                        'has_more' => $has_more
                    ], "批次处理完成: 获取 {$current_batch_count} 个商品，总计 {$total_fetched} 个");

                } else {
                    // 没有商品数据，停止
                    $has_more = false;
                    woo_walmart_sync_log('商品同步结束', '信息', [
                        'offset' => $offset,
                        'reason' => 'no_items_in_response'
                    ], "没有更多商品数据，同步结束");
                }
            }

            // 添加延迟避免API频率限制
            if ($has_more) {
                usleep(200000); // 0.2秒延迟
            }
        }
        
        // 更新本地缓存并获取删除同步结果
        $cache_result = null;
        if (!empty($all_products)) {
            $cache_result = $this->update_products_cache($all_products, $force_refresh);
        }

        // 记录同步结果
        woo_walmart_sync_log('Walmart商品同步', '完成', [
            'total_fetched' => $total_fetched,
            'errors_count' => count($errors),
            'errors' => $errors,
            'cache_result' => $cache_result
        ], "商品同步完成，获取 {$total_fetched} 个商品");

        // 添加成功通知
        if ($total_fetched > 0) {
            $notification_message = "成功同步 {$total_fetched} 个商品到本地缓存";

            // 如果有删除同步结果，添加到通知消息中
            if ($cache_result && isset($cache_result['deleted_count']) && $cache_result['deleted_count'] > 0) {
                $notification_message .= "，同时删除了 {$cache_result['deleted_count']} 个在Walmart中不存在的本地商品";
            }

            $this->add_notification(
                'sync_success',
                '🔄 Walmart商品同步成功',
                $notification_message,
                'success',
                [
                    'total_fetched' => $total_fetched,
                    'errors' => $errors,
                    'cache_result' => $cache_result,
                    'force_refresh' => $force_refresh
                ]
            );
        }
        
        return [
            'success' => true,
            'total_fetched' => $total_fetched,
            'errors' => $errors,
            'from_cache' => false
        ];
    }
    
    /**
     * 解析Walmart API返回的商品数据
     * @param array $item
     * @return array|null
     */
    private function parse_walmart_product($item) {
        try {
            // 提取基本信息
            $wpid = isset($item['wpid']) ? $item['wpid'] : '';
            $sku = isset($item['sku']) ? $item['sku'] : '';
            
            if (empty($wpid) || empty($sku)) {
                return null; // 跳过无效商品
            }
            
            // 提取商品名称
            $product_name = '';
            if (isset($item['productName'])) {
                $product_name = $item['productName'];
            }
            
            // 提取价格信息
            $price = 0.00;
            if (isset($item['price']['amount'])) {
                $price = floatval($item['price']['amount']);
            }
            
            // 提取库存信息 - 尝试多种可能的字段
            $inventory_count = 0;

            // 记录原始数据用于调试
            $inventory_debug = [
                'item_keys' => array_keys($item),
                'raw_item' => $item
            ];

            // 尝试各种可能的库存字段
            if (isset($item['quantity']['amount'])) {
                $inventory_count = intval($item['quantity']['amount']);
            } elseif (isset($item['quantity'])) {
                if (is_array($item['quantity'])) {
                    if (isset($item['quantity']['unit']) && isset($item['quantity']['amount'])) {
                        $inventory_count = intval($item['quantity']['amount']);
                    }
                } else {
                    $inventory_count = intval($item['quantity']);
                }
            } elseif (isset($item['availableQuantity']['amount'])) {
                $inventory_count = intval($item['availableQuantity']['amount']);
            } elseif (isset($item['availableQuantity'])) {
                $inventory_count = intval($item['availableQuantity']);
            } elseif (isset($item['qty']['amount'])) {
                $inventory_count = intval($item['qty']['amount']);
            } elseif (isset($item['qty'])) {
                $inventory_count = intval($item['qty']);
            } elseif (isset($item['inventory']['amount'])) {
                $inventory_count = intval($item['inventory']['amount']);
            } elseif (isset($item['inventory'])) {
                $inventory_count = intval($item['inventory']);
            } elseif (isset($item['shipNode'][0]['availableQuantity']['amount'])) {
                // 检查shipNode数组中的库存
                $inventory_count = intval($item['shipNode'][0]['availableQuantity']['amount']);
            } elseif (isset($item['shipNodes'][0]['availableQuantity']['amount'])) {
                // 检查shipNodes数组中的库存
                $inventory_count = intval($item['shipNodes'][0]['availableQuantity']['amount']);
            } elseif (isset($item['stock']['amount'])) {
                // 检查stock字段
                $inventory_count = intval($item['stock']['amount']);
            } elseif (isset($item['stock'])) {
                $inventory_count = intval($item['stock']);
            } elseif (isset($item['availableStock']['amount'])) {
                // 检查availableStock字段
                $inventory_count = intval($item['availableStock']['amount']);
            } elseif (isset($item['availableStock'])) {
                $inventory_count = intval($item['availableStock']);
            } elseif (isset($item['onHandQuantity']['amount'])) {
                // 检查onHandQuantity字段
                $inventory_count = intval($item['onHandQuantity']['amount']);
            } elseif (isset($item['onHandQuantity'])) {
                $inventory_count = intval($item['onHandQuantity']);
            }

            // 记录库存解析结果
            $inventory_debug['parsed_inventory'] = $inventory_count;
            $inventory_debug['found_fields'] = [];

            // 记录找到的相关字段
            foreach (['quantity', 'availableQuantity', 'qty', 'inventory', 'shipNode', 'shipNodes', 'stock', 'availableStock', 'onHandQuantity'] as $field) {
                if (isset($item[$field])) {
                    $inventory_debug['found_fields'][$field] = $item[$field];
                }
            }
            
            // 提取UPC
            $upc = '';
            if (isset($item['upc'])) {
                $upc = $item['upc'];
            } elseif (isset($item['gtin'])) {
                $upc = $item['gtin'];
            }
            
            // 提取状态
            $status = isset($item['lifecycleStatus']) ? $item['lifecycleStatus'] : 'PUBLISHED';
            
            // 提取商品类型和分类
            $product_type = isset($item['productType']) ? $item['productType'] : '';
            $category = '';
            if (isset($item['category']['name'])) {
                $category = $item['category']['name'];
            }

            // 记录库存调试信息到通知系统（仅当库存为0且找到相关字段时）
            if ($inventory_count === 0 && !empty($inventory_debug['found_fields'])) {
                $this->add_notification(
                    'inventory_debug',
                    "库存解析调试 - SKU: {$sku}",
                    "库存为0，但找到了相关字段，请检查API响应格式",
                    'warning',
                    $inventory_debug
                );
            }

            return [
                'wpid' => $wpid,
                'sku' => $sku,
                'product_name' => substr($product_name, 0, 500), // 限制长度
                'price' => $price,
                'inventory_count' => $inventory_count,
                'upc' => $upc,
                'status' => $status,
                'product_type' => $product_type,
                'category' => substr($category, 0, 200), // 限制长度
                'last_sync_time' => current_time('mysql'),
                'sync_status' => 'success',
                'sync_error_message' => '',
                'updated_at' => current_time('mysql')
            ];
            
        } catch (Exception $e) {
            woo_walmart_sync_log('商品解析错误', '错误', [
                'item' => $item,
                'error' => $e->getMessage()
            ], '解析Walmart商品数据时发生错误');
            
            return null;
        }
    }
    
    /**
     * 更新商品缓存
     * @param array $products
     * @param bool $force_refresh 是否强制刷新（用于决定是否执行删除同步）
     */
    private function update_products_cache($products, $force_refresh = false) {
        global $wpdb;

        $updated_count = 0;
        $inserted_count = 0;
        $deleted_count = 0;

        // 收集从Walmart API获取的所有WPID
        $walmart_wpids = array_column($products, 'wpid');

        foreach ($products as $product) {
            // 检查商品是否已存在
            $existing = $wpdb->get_row($wpdb->prepare(
                "SELECT id FROM {$this->cache_table} WHERE wpid = %s",
                $product['wpid']
            ));

            if ($existing) {
                // 更新现有商品
                $result = $wpdb->update(
                    $this->cache_table,
                    $product,
                    ['wpid' => $product['wpid']],
                    ['%s', '%s', '%s', '%f', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s'],
                    ['%s']
                );

                if ($result !== false) {
                    $updated_count++;
                }
            } else {
                // 插入新商品
                $product['created_at'] = current_time('mysql');
                $result = $wpdb->insert(
                    $this->cache_table,
                    $product,
                    ['%s', '%s', '%s', '%f', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s']
                );

                if ($result !== false) {
                    $inserted_count++;
                }
            }

            if ($wpdb->last_error) {
                woo_walmart_sync_log('缓存更新错误', '错误', [
                    'product' => $product,
                    'error' => $wpdb->last_error
                ], '更新商品缓存时发生错误');
            }
        }

        // 删除同步：删除本地存在但Walmart API中不存在的商品
        if ($force_refresh && !empty($walmart_wpids)) {
            $deleted_count = $this->cleanup_deleted_products($walmart_wpids);
        }

        woo_walmart_sync_log('缓存更新完成', '成功', [
            'total_products' => count($products),
            'updated_count' => $updated_count,
            'inserted_count' => $inserted_count,
            'deleted_count' => $deleted_count,
            'force_refresh' => $force_refresh
        ], "缓存更新完成：新增 {$inserted_count} 个，更新 {$updated_count} 个，删除 {$deleted_count} 个商品");

        // 返回缓存更新结果
        return [
            'updated_count' => $updated_count,
            'inserted_count' => $inserted_count,
            'deleted_count' => $deleted_count,
            'total_products' => count($products),
            'force_refresh' => $force_refresh
        ];
    }

    /**
     * 清理已删除的商品（删除同步）
     * @param array $walmart_wpids 从Walmart API获取的所有WPID
     * @return int 删除的商品数量
     */
    private function cleanup_deleted_products($walmart_wpids) {
        global $wpdb;

        if (empty($walmart_wpids)) {
            return 0;
        }

        // 构建占位符
        $placeholders = implode(',', array_fill(0, count($walmart_wpids), '%s'));

        // 查找本地存在但Walmart API中不存在的商品
        $query = "SELECT wpid, sku, product_name FROM {$this->cache_table} WHERE wpid NOT IN ($placeholders)";
        $deleted_products = $wpdb->get_results($wpdb->prepare($query, $walmart_wpids));

        if (empty($deleted_products)) {
            woo_walmart_sync_log('删除同步检查', '信息', [
                'walmart_wpids_count' => count($walmart_wpids),
                'local_products_to_delete' => 0
            ], '没有需要删除的商品');
            return 0;
        }

        // 记录即将删除的商品
        $deleted_info = [];
        foreach ($deleted_products as $product) {
            $deleted_info[] = [
                'wpid' => $product->wpid,
                'sku' => $product->sku,
                'product_name' => $product->product_name
            ];
        }

        woo_walmart_sync_log('删除同步-开始', '警告', [
            'walmart_wpids_count' => count($walmart_wpids),
            'products_to_delete' => count($deleted_products),
            'deleted_products' => $deleted_info
        ], "开始删除同步，将删除 " . count($deleted_products) . " 个在Walmart中不存在的本地商品");

        // 执行删除操作
        $delete_query = "DELETE FROM {$this->cache_table} WHERE wpid NOT IN ($placeholders)";
        $deleted_count = $wpdb->query($wpdb->prepare($delete_query, $walmart_wpids));

        // 添加删除同步通知
        if ($deleted_count > 0) {
            $this->add_notification(
                'delete_sync',
                '🗑️ 删除同步完成',
                "已删除 {$deleted_count} 个在Walmart中不存在的本地商品。这些商品已从Walmart平台移除，本地缓存已同步更新。",
                'warning',
                [
                    'deleted_count' => $deleted_count,
                    'deleted_products' => $deleted_info,
                    'sync_time' => current_time('mysql')
                ]
            );

            woo_walmart_sync_log('删除同步-完成', '警告', [
                'deleted_count' => $deleted_count,
                'deleted_products' => $deleted_info
            ], "删除同步完成，已删除 {$deleted_count} 个商品");
        }

        return $deleted_count;
    }
    
    /**
     * 添加通知
     * @param string $type
     * @param string $title
     * @param string $message
     * @param string $priority
     * @param array $related_data
     */
    private function add_notification($type, $title, $message, $priority = 'normal', $related_data = []) {
        global $wpdb;
        
        $wpdb->insert(
            $this->notifications_table,
            [
                'notification_type' => $type,
                'title' => $title,
                'message' => $message,
                'status' => 'unread',
                'priority' => $priority,
                'related_data' => json_encode($related_data),
                'created_at' => current_time('mysql')
            ],
            ['%s', '%s', '%s', '%s', '%s', '%s', '%s']
        );
    }

    /**
     * 处理批量操作
     * @param array $selected_items 选中的商品
     * @param string $action 操作类型
     * @param string $match_method 匹配方式
     * @return array
     */
    public function process_bulk_action($selected_items, $action, $match_method) {
        $success_count = 0;
        $failed_count = 0;
        $errors = [];

        // 对于库存同步，使用批量API处理
        if ($action === 'sync_inventory') {
            return $this->process_bulk_inventory_sync($selected_items, $match_method);
        }

        // 对于价格同步，使用批量API处理
        if ($action === 'sync_price') {
            return $this->process_bulk_price_sync($selected_items, $match_method);
        }

        // 对于产品名称同步，使用批量API处理
        if ($action === 'sync_product_name') {
            return $this->process_bulk_product_name_sync($selected_items, $match_method);
        }

        // 对于混合同步，使用批量API处理
        if ($action === 'sync_both') {
            return $this->process_bulk_mixed_sync($selected_items, $match_method);
        }

        // 对于其他操作，继续使用单个处理
        foreach ($selected_items as $item) {
            try {
                switch ($action) {
                    case 'publish':
                        $result = $this->update_product_status($item, 'PUBLISHED', $match_method);
                        break;
                    case 'unpublish':
                        $result = $this->update_product_status($item, 'UNPUBLISHED', $match_method);
                        break;
                    default:
                        $result = false;
                        $errors[] = "未知操作类型: {$action}";
                }

                if ($result) {
                    $success_count++;
                    woo_walmart_sync_log('批量操作-单项成功', '成功', [
                        'sku' => $item->sku,
                        'action' => $action,
                        'match_method' => $match_method
                    ], "商品 {$item->sku} {$action} 操作成功");
                } else {
                    $failed_count++;
                    $errors[] = "商品 {$item->sku} 操作失败";
                    woo_walmart_sync_log('批量操作-单项失败', '错误', [
                        'sku' => $item->sku,
                        'action' => $action,
                        'match_method' => $match_method
                    ], "商品 {$item->sku} {$action} 操作失败");
                }

            } catch (Exception $e) {
                $failed_count++;
                $errors[] = "商品 {$item->sku} 操作异常: " . $e->getMessage();
                woo_walmart_sync_log('批量操作-单项异常', '错误', [
                    'sku' => $item->sku,
                    'action' => $action,
                    'exception' => $e->getMessage()
                ], "商品 {$item->sku} {$action} 操作异常");
            }

            // 添加延迟避免API频率限制
            usleep(100000); // 0.1秒延迟
        }

        // 根据操作类型生成不同的消息
        $action_names = [
            'publish' => '上架',
            'unpublish' => '下架'
        ];
        $action_name = $action_names[$action] ?? $action;

        // 特殊处理unpublish操作的消息
        $notification_message = "成功: {$success_count} 个，失败: {$failed_count} 个";
        $return_message = "批量{$action_name}操作完成！成功: {$success_count} 个，失败: {$failed_count} 个";

        if ($action === 'unpublish' && $success_count > 0) {
            $notification_message .= "。注意：下架操作已提交成功，如果看到API查询错误（如404），这是正常现象，不影响实际下架效果。";
            $return_message .= "。下架操作已成功提交到Walmart。";
        }

        // 记录批量操作结果
        $this->add_notification(
            'bulk_operation',
            "批量{$action_name}操作完成",
            $notification_message,
            $failed_count > 0 ? 'warning' : 'success',
            [
                'action' => $action,
                'match_method' => $match_method,
                'success_count' => $success_count,
                'failed_count' => $failed_count,
                'errors' => $errors
            ]
        );

        woo_walmart_sync_log('批量操作-完成', '信息', [
            'action' => $action,
            'total_items' => count($selected_items),
            'success_count' => $success_count,
            'failed_count' => $failed_count,
            'match_method' => $match_method
        ], "批量{$action_name}操作完成");

        return [
            'success' => true,
            'message' => $return_message . ($failed_count > 0 ? "，详细错误请查看通知页面" : "")
        ];
    }

    /**
     * 处理批量库存同步（使用批量API）
     * @param array $selected_items 选中的商品
     * @param string $match_method 匹配方式
     * @return array
     */
    private function process_bulk_inventory_sync($selected_items, $match_method) {
        woo_walmart_sync_log('批量库存同步-开始', '信息', [
            'total_items' => count($selected_items),
            'match_method' => $match_method
        ], "开始批量库存同步，共 " . count($selected_items) . " 个商品");

        // 准备库存数据
        $preparation_result = $this->prepare_inventory_data_for_batch($selected_items, $match_method);
        $valid_items = $preparation_result['valid_items'];
        $invalid_items = $preparation_result['invalid_items'];

        $success_count = 0;
        $failed_count = count($invalid_items);
        $errors = $preparation_result['errors'];

        if (!empty($valid_items)) {
            // 使用批量API处理有效商品
            $batch_result = $this->process_batch_inventory_api($valid_items);
            $success_count += $batch_result['success_count'];
            $failed_count += $batch_result['failed_count'];
            $errors = array_merge($errors, $batch_result['errors']);
        }

        // 记录批量操作结果
        $this->add_notification(
            'bulk_operation',
            "🚀 批量库存同步操作完成（批量API模式）",
            "成功: {$success_count} 个，失败: {$failed_count} 个，使用批量API分批处理",
            $failed_count > 0 ? 'warning' : 'success',
            [
                'action' => 'sync_inventory',
                'match_method' => $match_method,
                'success_count' => $success_count,
                'failed_count' => $failed_count,
                'errors' => $errors,
                'total_items' => count($selected_items),
                'valid_items' => count($valid_items),
                'invalid_items' => count($invalid_items),
                'processing_mode' => 'bulk_api',
                'batch_size' => 50,
                'estimated_batches' => ceil(count($valid_items) / 50)
            ]
        );

        woo_walmart_sync_log('批量库存同步-完成', '信息', [
            'total_items' => count($selected_items),
            'success_count' => $success_count,
            'failed_count' => $failed_count,
            'valid_items' => count($valid_items),
            'invalid_items' => count($invalid_items)
        ], "批量库存同步完成");

        return [
            'success' => true,
            'message' => "批量库存同步完成！成功: {$success_count} 个，失败: {$failed_count} 个" .
                        ($failed_count > 0 ? "，详细错误请查看通知页面" : "")
        ];
    }

    /**
     * 处理批量价格同步（使用批量API）
     * @param array $selected_items 选中的商品
     * @param string $match_method 匹配方式
     * @return array
     */
    private function process_bulk_price_sync($selected_items, $match_method) {
        woo_walmart_sync_log('批量价格同步-开始', '信息', [
            'total_items' => count($selected_items),
            'match_method' => $match_method
        ], "开始批量价格同步，共 " . count($selected_items) . " 个商品");

        // 准备价格数据
        $preparation_result = $this->prepare_price_data_for_batch($selected_items, $match_method);
        $valid_items = $preparation_result['valid_items'];
        $invalid_items = $preparation_result['invalid_items'];

        $success_count = 0;
        $failed_count = count($invalid_items);
        $errors = $preparation_result['errors'];

        if (!empty($valid_items)) {
            // 使用批量API处理有效商品
            $batch_result = $this->process_batch_price_api($valid_items);
            $success_count += $batch_result['success_count'];
            $failed_count += $batch_result['failed_count'];
            $errors = array_merge($errors, $batch_result['errors']);
        }

        // 记录批量操作结果
        $this->add_notification(
            'bulk_operation',
            "🚀 批量价格同步操作完成（批量API模式）",
            "成功: {$success_count} 个，失败: {$failed_count} 个，使用批量API分批处理",
            $failed_count > 0 ? 'warning' : 'success',
            [
                'action' => 'sync_price',
                'match_method' => $match_method,
                'success_count' => $success_count,
                'failed_count' => $failed_count,
                'errors' => $errors,
                'total_items' => count($selected_items),
                'valid_items' => count($valid_items),
                'invalid_items' => count($invalid_items),
                'processing_mode' => 'bulk_api',
                'batch_size' => 50,
                'estimated_batches' => ceil(count($valid_items) / 50)
            ]
        );

        woo_walmart_sync_log('批量价格同步-完成', '信息', [
            'total_items' => count($selected_items),
            'success_count' => $success_count,
            'failed_count' => $failed_count,
            'valid_items' => count($valid_items),
            'invalid_items' => count($invalid_items)
        ], "批量价格同步完成");

        return [
            'success' => true,
            'message' => "批量价格同步完成！成功: {$success_count} 个，失败: {$failed_count} 个" .
                        ($failed_count > 0 ? "，详细错误请查看通知页面" : "")
        ];
    }

    /**
     * 为批量处理准备价格数据
     * @param array $selected_items 选中的商品
     * @param string $match_method 匹配方式
     * @return array
     */
    private function prepare_price_data_for_batch($selected_items, $match_method) {
        $valid_items = [];
        $invalid_items = [];
        $errors = [];

        foreach ($selected_items as $item) {
            try {
                // 根据匹配方式找到对应的WooCommerce商品
                $wc_product = $this->find_woocommerce_product($item, $match_method);

                if (!$wc_product) {
                    $invalid_items[] = $item;
                    $errors[] = "商品 {$item->sku} 未找到对应的WooCommerce商品";

                    woo_walmart_sync_log('批量价格同步-商品未找到', '错误', [
                        'sku' => $item->sku,
                        'match_method' => $match_method
                    ], "未找到对应的WooCommerce商品: {$item->sku}");
                    continue;
                }

                // 获取WooCommerce价格
                $wc_price = $wc_product->get_price();
                if (empty($wc_price) || $wc_price <= 0) {
                    $invalid_items[] = $item;
                    $errors[] = "商品 {$item->sku} 价格无效或为空";

                    woo_walmart_sync_log('批量价格同步-价格无效', '错误', [
                        'sku' => $item->sku,
                        'wc_price' => $wc_price
                    ], "商品 {$item->sku} 价格无效: {$wc_price}");
                    continue;
                }

                // 准备批量API所需的数据格式
                $valid_items[] = [
                    'walmart_item' => $item,
                    'wc_product' => $wc_product,
                    'sku' => $item->sku,
                    'price' => round(floatval($wc_price), 2),
                    'product_id' => $wc_product->get_id()
                ];

                woo_walmart_sync_log('批量价格同步-数据准备', '调试', [
                    'sku' => $item->sku,
                    'wc_product_id' => $wc_product->get_id(),
                    'wc_price' => $wc_price
                ], "商品 {$item->sku} 数据准备完成，价格: {$wc_price}");

            } catch (Exception $e) {
                $invalid_items[] = $item;
                $errors[] = "商品 {$item->sku} 数据准备异常: " . $e->getMessage();

                woo_walmart_sync_log('批量价格同步-数据准备异常', '错误', [
                    'sku' => $item->sku,
                    'error' => $e->getMessage()
                ], "商品 {$item->sku} 数据准备异常");
            }
        }

        woo_walmart_sync_log('批量价格同步-数据准备完成', '信息', [
            'total_items' => count($selected_items),
            'valid_items' => count($valid_items),
            'invalid_items' => count($invalid_items),
            'errors_count' => count($errors)
        ], "数据准备完成，有效: " . count($valid_items) . "，无效: " . count($invalid_items));

        return [
            'valid_items' => $valid_items,
            'invalid_items' => $invalid_items,
            'errors' => $errors
        ];
    }

    /**
     * 使用批量Feed处理价格同步
     * @param array $valid_items 有效的商品数据
     * @return array
     */
    private function process_batch_price_api($valid_items) {
        $success_count = 0;
        $failed_count = 0;
        $errors = [];

        woo_walmart_sync_log('批量价格同步-Feed处理开始', '信息', [
            'total_items' => count($valid_items)
        ], "开始批量价格Feed处理，共 " . count($valid_items) . " 个商品");

        try {
            // 准备批量Feed数据格式
            $price_data = [];
            foreach ($valid_items as $item) {
                $price_data[] = [
                    'sku' => $item['sku'],
                    'price' => $item['price']
                ];
            }

            woo_walmart_sync_log('批量价格同步-Feed调用', '调试', [
                'total_items' => count($price_data),
                'price_data' => $price_data
            ], "价格Feed调用开始");

            // 调用批量价格更新Feed
            $result = $this->api_auth->bulk_update_price($price_data);

            woo_walmart_sync_log('批量价格同步-Feed响应', '调试', [
                'api_result' => $result,
                'is_wp_error' => is_wp_error($result)
            ], "价格Feed响应");

            if (!is_wp_error($result)) {
                // Feed提交成功，所有商品标记为成功（实际处理结果需要后续查询Feed状态）
                foreach ($valid_items as $item) {
                    $sku = $item['sku'];
                    $price = $item['price'];
                    $walmart_item = $item['walmart_item'];

                    // 更新本地缓存
                    global $wpdb;
                    $updated = $wpdb->update(
                        $this->cache_table,
                        ['price' => $price, 'updated_at' => current_time('mysql')],
                        ['id' => $walmart_item->id],
                        ['%f', '%s'],
                        ['%d']
                    );

                    $success_count++;

                    woo_walmart_sync_log('批量价格同步-单项成功', '成功', [
                        'sku' => $sku,
                        'price' => $price,
                        'updated_rows' => $updated
                    ], "商品 {$sku} 价格Feed提交成功: {$price}");
                }

                woo_walmart_sync_log('批量价格同步-Feed成功', '成功', [
                    'total_success' => $success_count,
                    'feed_id' => isset($result['feedId']) ? $result['feedId'] : 'unknown'
                ], "价格Feed提交成功");

            } else {
                // Feed提交失败
                $failed_count = count($valid_items);

                foreach ($valid_items as $item) {
                    $errors[] = "商品 {$item['sku']} 批量价格Feed提交失败: " . $result->get_error_message();
                }

                woo_walmart_sync_log('批量价格同步-Feed失败', '错误', [
                    'total_failed' => $failed_count,
                    'error_message' => $result->get_error_message(),
                    'error_code' => $result->get_error_code()
                ], "价格Feed提交完全失败");
            }

        } catch (Exception $e) {
            // Feed异常处理
            $failed_count = count($valid_items);

            foreach ($valid_items as $item) {
                $errors[] = "商品 {$item['sku']} Feed处理异常: " . $e->getMessage();
            }

            woo_walmart_sync_log('批量价格同步-Feed异常', '错误', [
                'total_failed' => $failed_count,
                'exception' => $e->getMessage()
            ], "价格Feed处理异常");
        }

        woo_walmart_sync_log('批量价格同步-Feed处理完成', '信息', [
            'total_success' => $success_count,
            'total_failed' => $failed_count,
            'total_errors' => count($errors)
        ], "批量价格Feed处理完成");

        return [
            'success_count' => $success_count,
            'failed_count' => $failed_count,
            'errors' => $errors
        ];
    }

    /**
     * 处理批量价格同步响应
     * @param array $batch 批次数据
     * @param array $response API响应
     * @return array
     */
    private function process_batch_price_response($batch, $response) {
        $success_count = 0;
        $failed_count = 0;
        $errors = [];

        woo_walmart_sync_log('批量价格同步-响应处理', '调试', [
            'batch_size' => count($batch),
            'response_structure' => is_array($response) ? array_keys($response) : 'not_array',
            'response' => $response
        ], "开始处理批量价格响应");

        // 处理每个商品的结果
        foreach ($batch as $item) {
            $sku = $item['sku'];
            $price = $item['price'];
            $walmart_item = $item['walmart_item'];

            try {
                // 检查响应中是否包含该SKU的结果
                $item_success = $this->check_item_success_in_batch_response($sku, $response);

                if ($item_success) {
                    // 更新本地缓存
                    global $wpdb;
                    $updated = $wpdb->update(
                        $this->cache_table,
                        ['price' => $price, 'updated_at' => current_time('mysql')],
                        ['id' => $walmart_item->id],
                        ['%f', '%s'],
                        ['%d']
                    );

                    $success_count++;

                    woo_walmart_sync_log('批量价格同步-单项成功', '成功', [
                        'sku' => $sku,
                        'price' => $price,
                        'updated_rows' => $updated
                    ], "商品 {$sku} 价格同步成功: {$price}");

                } else {
                    $failed_count++;
                    $error_message = $this->get_item_error_from_batch_response($sku, $response);
                    $errors[] = "商品 {$sku} 价格同步失败: " . $error_message;

                    woo_walmart_sync_log('批量价格同步-单项失败', '错误', [
                        'sku' => $sku,
                        'error_message' => $error_message
                    ], "商品 {$sku} 价格同步失败");
                }

            } catch (Exception $e) {
                $failed_count++;
                $errors[] = "商品 {$sku} 价格响应处理异常: " . $e->getMessage();

                woo_walmart_sync_log('批量价格同步-单项异常', '错误', [
                    'sku' => $sku,
                    'exception' => $e->getMessage()
                ], "商品 {$sku} 价格响应处理异常");
            }
        }

        woo_walmart_sync_log('批量价格同步-响应处理完成', '信息', [
            'batch_size' => count($batch),
            'success_count' => $success_count,
            'failed_count' => $failed_count
        ], "批量价格响应处理完成");

        return [
            'success_count' => $success_count,
            'failed_count' => $failed_count,
            'errors' => $errors
        ];
    }

    /**
     * 处理批量产品名称同步（使用批量API）
     * @param array $selected_items 选中的商品
     * @param string $match_method 匹配方式
     * @return array
     */
    private function process_bulk_product_name_sync($selected_items, $match_method) {
        woo_walmart_sync_log('批量产品名称同步-开始', '信息', [
            'total_items' => count($selected_items),
            'match_method' => $match_method
        ], "开始批量产品名称同步，共 " . count($selected_items) . " 个商品");

        // 准备产品名称数据
        $preparation_result = $this->prepare_product_name_data_for_batch($selected_items, $match_method);
        $valid_items = $preparation_result['valid_items'];
        $invalid_items = $preparation_result['invalid_items'];

        $success_count = 0;
        $failed_count = count($invalid_items);
        $errors = $preparation_result['errors'];

        if (!empty($valid_items)) {
            // 使用批量API处理有效商品
            $batch_result = $this->process_batch_product_name_api($valid_items);
            $success_count += $batch_result['success_count'];
            $failed_count += $batch_result['failed_count'];
            $errors = array_merge($errors, $batch_result['errors']);
        }

        // 记录批量操作结果
        $this->add_notification(
            'bulk_operation',
            "🚀 批量产品名称同步操作完成（批量API模式）",
            "成功: {$success_count} 个，失败: {$failed_count} 个，使用批量API分批处理",
            $failed_count > 0 ? 'warning' : 'success',
            [
                'action' => 'sync_product_name',
                'match_method' => $match_method,
                'success_count' => $success_count,
                'failed_count' => $failed_count,
                'errors' => $errors,
                'total_items' => count($selected_items),
                'valid_items' => count($valid_items),
                'invalid_items' => count($invalid_items),
                'processing_mode' => 'bulk_api',
                'batch_size' => 50,
                'estimated_batches' => ceil(count($valid_items) / 50)
            ]
        );

        woo_walmart_sync_log('批量产品名称同步-完成', '信息', [
            'total_items' => count($selected_items),
            'success_count' => $success_count,
            'failed_count' => $failed_count,
            'valid_items' => count($valid_items),
            'invalid_items' => count($invalid_items)
        ], "批量产品名称同步完成");

        return [
            'success' => true,
            'message' => "批量产品名称同步完成！成功: {$success_count} 个，失败: {$failed_count} 个" .
                        ($failed_count > 0 ? "，详细错误请查看通知页面" : "")
        ];
    }

    /**
     * 处理批量混合同步（库存+价格，都使用批量API）
     * @param array $selected_items 选中的商品
     * @param string $match_method 匹配方式
     * @return array
     */
    private function process_bulk_mixed_sync($selected_items, $match_method) {
        woo_walmart_sync_log('批量混合同步-开始', '信息', [
            'total_items' => count($selected_items),
            'match_method' => $match_method
        ], "开始批量混合同步（库存+价格），共 " . count($selected_items) . " 个商品");

        $total_success = 0;
        $total_failed = 0;
        $all_errors = [];

        // 1. 执行库存同步
        woo_walmart_sync_log('批量混合同步-库存阶段', '信息', [], "开始库存同步阶段");
        $inventory_result = $this->process_bulk_inventory_sync($selected_items, $match_method);

        // 2. 执行价格同步
        woo_walmart_sync_log('批量混合同步-价格阶段', '信息', [], "开始价格同步阶段");
        $price_result = $this->process_bulk_price_sync($selected_items, $match_method);

        // 合并结果（这里简化处理，实际可能需要更复杂的逻辑）
        $success_message = [];
        if ($inventory_result['success']) {
            $success_message[] = "库存同步完成";
        }
        if ($price_result['success']) {
            $success_message[] = "价格同步完成";
        }

        // 记录混合操作结果
        $this->add_notification(
            'bulk_operation',
            "🚀 批量混合同步操作完成（全批量API模式）",
            "库存和价格同步均使用批量API处理，提高效率",
            'success',
            [
                'action' => 'sync_both',
                'match_method' => $match_method,
                'inventory_result' => $inventory_result,
                'price_result' => $price_result,
                'total_items' => count($selected_items),
                'processing_mode' => 'full_bulk_api'
            ]
        );

        woo_walmart_sync_log('批量混合同步-完成', '信息', [
            'total_items' => count($selected_items),
            'inventory_success' => $inventory_result['success'],
            'price_success' => $price_result['success']
        ], "批量混合同步完成");

        return [
            'success' => true,
            'message' => "批量混合同步完成！" . implode("，", $success_message) . "。详情请查看通知页面。"
        ];
    }

    /**
     * 为批量处理准备产品名称数据
     * @param array $selected_items 选中的商品
     * @param string $match_method 匹配方式
     * @return array
     */
    private function prepare_product_name_data_for_batch($selected_items, $match_method) {
        $valid_items = [];
        $invalid_items = [];
        $errors = [];

        foreach ($selected_items as $item) {
            try {
                // 根据匹配方式找到对应的WooCommerce商品
                $wc_product = $this->find_woocommerce_product($item, $match_method);

                if (!$wc_product) {
                    $invalid_items[] = $item;
                    $errors[] = "商品 {$item->sku} 未找到对应的WooCommerce商品";

                    woo_walmart_sync_log('批量产品名称同步-商品未找到', '错误', [
                        'sku' => $item->sku,
                        'match_method' => $match_method
                    ], "未找到对应的WooCommerce商品: {$item->sku}");
                    continue;
                }

                // 获取WooCommerce产品名称
                $wc_product_name = $wc_product->get_name();
                if (empty($wc_product_name)) {
                    $invalid_items[] = $item;
                    $errors[] = "商品 {$item->sku} 产品名称为空";

                    woo_walmart_sync_log('批量产品名称同步-名称为空', '错误', [
                        'sku' => $item->sku,
                        'wc_product_name' => $wc_product_name
                    ], "商品 {$item->sku} 产品名称为空");
                    continue;
                }

                // 准备批量API所需的数据格式
                $valid_items[] = [
                    'walmart_item' => $item,
                    'wc_product' => $wc_product,
                    'sku' => $item->sku,
                    'product_name' => $wc_product_name,
                    'short_description' => $wc_product->get_short_description(),
                    'product_id' => $wc_product->get_id()
                ];

                woo_walmart_sync_log('批量产品名称同步-数据准备', '调试', [
                    'sku' => $item->sku,
                    'wc_product_id' => $wc_product->get_id(),
                    'wc_product_name' => $wc_product_name
                ], "商品 {$item->sku} 数据准备完成，产品名称: {$wc_product_name}");

            } catch (Exception $e) {
                $invalid_items[] = $item;
                $errors[] = "商品 {$item->sku} 数据准备异常: " . $e->getMessage();

                woo_walmart_sync_log('批量产品名称同步-数据准备异常', '错误', [
                    'sku' => $item->sku,
                    'error' => $e->getMessage()
                ], "商品 {$item->sku} 数据准备异常");
            }
        }

        woo_walmart_sync_log('批量产品名称同步-数据准备完成', '信息', [
            'total_items' => count($selected_items),
            'valid_items' => count($valid_items),
            'invalid_items' => count($invalid_items),
            'errors_count' => count($errors)
        ], "数据准备完成，有效: " . count($valid_items) . "，无效: " . count($invalid_items));

        return [
            'valid_items' => $valid_items,
            'invalid_items' => $invalid_items,
            'errors' => $errors
        ];
    }

    /**
     * 使用批量Feed处理产品名称同步
     * @param array $valid_items 有效的商品数据
     * @return array
     */
    private function process_batch_product_name_api($valid_items) {
        $success_count = 0;
        $failed_count = 0;
        $errors = [];

        woo_walmart_sync_log('批量产品名称同步-Feed处理开始', '信息', [
            'total_items' => count($valid_items)
        ], "开始批量产品名称Feed处理，共 " . count($valid_items) . " 个商品");

        try {
            // 准备批量Feed数据格式
            $product_data = [];
            foreach ($valid_items as $item) {
                $product_data[] = [
                    'sku' => $item['sku'],
                    'product_name' => $item['product_name'],
                    'short_description' => $item['short_description']
                ];
            }

            woo_walmart_sync_log('批量产品名称同步-Feed调用', '调试', [
                'total_items' => count($product_data),
                'product_data' => $product_data
            ], "产品名称Feed调用开始");

            // 调用批量产品信息更新Feed
            $result = $this->api_auth->bulk_update_product_info($product_data);

            woo_walmart_sync_log('批量产品名称同步-Feed响应', '调试', [
                'api_result' => $result,
                'is_wp_error' => is_wp_error($result)
            ], "产品名称Feed响应");

            if (!is_wp_error($result)) {
                // Feed提交成功，所有商品标记为成功（实际处理结果需要后续查询Feed状态）
                foreach ($valid_items as $item) {
                    $sku = $item['sku'];
                    $product_name = $item['product_name'];
                    $walmart_item = $item['walmart_item'];

                    // 更新本地缓存
                    global $wpdb;
                    $updated = $wpdb->update(
                        $this->cache_table,
                        ['product_name' => $product_name, 'updated_at' => current_time('mysql')],
                        ['id' => $walmart_item->id],
                        ['%s', '%s'],
                        ['%d']
                    );

                    $success_count++;

                    woo_walmart_sync_log('批量产品名称同步-单项成功', '成功', [
                        'sku' => $sku,
                        'product_name' => $product_name,
                        'updated_rows' => $updated
                    ], "商品 {$sku} 产品名称Feed提交成功: {$product_name}");
                }

                woo_walmart_sync_log('批量产品名称同步-Feed成功', '成功', [
                    'total_success' => $success_count,
                    'feed_id' => isset($result['feedId']) ? $result['feedId'] : 'unknown'
                ], "产品名称Feed提交成功");

            } else {
                // Feed提交失败
                $failed_count = count($valid_items);

                foreach ($valid_items as $item) {
                    $errors[] = "商品 {$item['sku']} 批量产品名称Feed提交失败: " . $result->get_error_message();
                }

                woo_walmart_sync_log('批量产品名称同步-Feed失败', '错误', [
                    'total_failed' => $failed_count,
                    'error_message' => $result->get_error_message(),
                    'error_code' => $result->get_error_code()
                ], "产品名称Feed提交完全失败");
            }

        } catch (Exception $e) {
            // Feed异常处理
            $failed_count = count($valid_items);

            foreach ($valid_items as $item) {
                $errors[] = "商品 {$item['sku']} Feed处理异常: " . $e->getMessage();
            }

            woo_walmart_sync_log('批量产品名称同步-Feed异常', '错误', [
                'total_failed' => $failed_count,
                'exception' => $e->getMessage()
            ], "产品名称Feed处理异常");
        }

        woo_walmart_sync_log('批量产品名称同步-Feed处理完成', '信息', [
            'total_success' => $success_count,
            'total_failed' => $failed_count,
            'total_errors' => count($errors)
        ], "批量产品名称Feed处理完成");

        return [
            'success_count' => $success_count,
            'failed_count' => $failed_count,
            'errors' => $errors
        ];
    }

    /**
     * 处理批量产品名称同步响应
     * @param array $batch 批次数据
     * @param array $response API响应
     * @return array
     */
    private function process_batch_product_name_response($batch, $response) {
        $success_count = 0;
        $failed_count = 0;
        $errors = [];

        woo_walmart_sync_log('批量产品名称同步-响应处理', '调试', [
            'batch_size' => count($batch),
            'response_structure' => is_array($response) ? array_keys($response) : 'not_array',
            'response' => $response
        ], "开始处理批量产品名称响应");

        // 处理每个商品的结果
        foreach ($batch as $item) {
            $sku = $item['sku'];
            $product_name = $item['product_name'];
            $walmart_item = $item['walmart_item'];

            try {
                // 检查响应中是否包含该SKU的结果
                $item_success = $this->check_item_success_in_batch_response($sku, $response);

                if ($item_success) {
                    // 更新本地缓存
                    global $wpdb;
                    $updated = $wpdb->update(
                        $this->cache_table,
                        ['product_name' => $product_name, 'updated_at' => current_time('mysql')],
                        ['id' => $walmart_item->id],
                        ['%s', '%s'],
                        ['%d']
                    );

                    $success_count++;

                    woo_walmart_sync_log('批量产品名称同步-单项成功', '成功', [
                        'sku' => $sku,
                        'product_name' => $product_name,
                        'updated_rows' => $updated
                    ], "商品 {$sku} 产品名称同步成功: {$product_name}");

                } else {
                    $failed_count++;
                    $error_message = $this->get_item_error_from_batch_response($sku, $response);
                    $errors[] = "商品 {$sku} 产品名称同步失败: " . $error_message;

                    woo_walmart_sync_log('批量产品名称同步-单项失败', '错误', [
                        'sku' => $sku,
                        'error_message' => $error_message
                    ], "商品 {$sku} 产品名称同步失败");
                }

            } catch (Exception $e) {
                $failed_count++;
                $errors[] = "商品 {$sku} 产品名称响应处理异常: " . $e->getMessage();

                woo_walmart_sync_log('批量产品名称同步-单项异常', '错误', [
                    'sku' => $sku,
                    'exception' => $e->getMessage()
                ], "商品 {$sku} 产品名称响应处理异常");
            }
        }

        woo_walmart_sync_log('批量产品名称同步-响应处理完成', '信息', [
            'batch_size' => count($batch),
            'success_count' => $success_count,
            'failed_count' => $failed_count
        ], "批量产品名称响应处理完成");

        return [
            'success_count' => $success_count,
            'failed_count' => $failed_count,
            'errors' => $errors
        ];
    }

    /**
     * 为批量处理准备库存数据
     * @param array $selected_items 选中的商品
     * @param string $match_method 匹配方式
     * @return array
     */
    private function prepare_inventory_data_for_batch($selected_items, $match_method) {
        $valid_items = [];
        $invalid_items = [];
        $errors = [];

        foreach ($selected_items as $item) {
            try {
                // 根据匹配方式找到对应的WooCommerce商品
                $wc_product = $this->find_woocommerce_product($item, $match_method);

                if (!$wc_product) {
                    $invalid_items[] = $item;
                    $errors[] = "商品 {$item->sku} 未找到对应的WooCommerce商品";

                    woo_walmart_sync_log('批量库存同步-商品未找到', '错误', [
                        'sku' => $item->sku,
                        'match_method' => $match_method
                    ], "未找到对应的WooCommerce商品: {$item->sku}");
                    continue;
                }

                // 获取WooCommerce库存
                $wc_inventory = $wc_product->get_stock_quantity();
                if ($wc_inventory === null) {
                    $wc_inventory = 0;
                }

                // 准备批量API所需的数据格式
                $valid_items[] = [
                    'walmart_item' => $item,
                    'wc_product' => $wc_product,
                    'sku' => $item->sku,
                    'quantity' => (int) $wc_inventory,
                    'product_id' => $wc_product->get_id()
                ];

                woo_walmart_sync_log('批量库存同步-数据准备', '调试', [
                    'sku' => $item->sku,
                    'wc_product_id' => $wc_product->get_id(),
                    'wc_inventory' => $wc_inventory
                ], "商品 {$item->sku} 数据准备完成，库存: {$wc_inventory}");

            } catch (Exception $e) {
                $invalid_items[] = $item;
                $errors[] = "商品 {$item->sku} 数据准备异常: " . $e->getMessage();

                woo_walmart_sync_log('批量库存同步-数据准备异常', '错误', [
                    'sku' => $item->sku,
                    'error' => $e->getMessage()
                ], "商品 {$item->sku} 数据准备异常");
            }
        }

        woo_walmart_sync_log('批量库存同步-数据准备完成', '信息', [
            'total_items' => count($selected_items),
            'valid_items' => count($valid_items),
            'invalid_items' => count($invalid_items),
            'errors_count' => count($errors)
        ], "数据准备完成，有效: " . count($valid_items) . "，无效: " . count($invalid_items));

        return [
            'valid_items' => $valid_items,
            'invalid_items' => $invalid_items,
            'errors' => $errors
        ];
    }

    /**
     * 使用批量API处理库存同步
     * @param array $valid_items 有效的商品数据
     * @return array
     */
    private function process_batch_inventory_api($valid_items) {
        $success_count = 0;
        $failed_count = 0;
        $errors = [];
        $batch_size = 50; // Walmart API限制

        // 分批处理
        $batches = array_chunk($valid_items, $batch_size);
        $total_batches = count($batches);

        woo_walmart_sync_log('批量库存同步-API处理开始', '信息', [
            'total_items' => count($valid_items),
            'total_batches' => $total_batches,
            'batch_size' => $batch_size
        ], "开始批量API处理，共 {$total_batches} 个批次");

        foreach ($batches as $batch_index => $batch) {
            $current_batch_number = $batch_index + 1;

            try {
                // 准备批量API数据格式（符合bulk_update_inventory方法的期望格式）
                $inventory_data = [];
                foreach ($batch as $item) {
                    $inventory_data[] = [
                        'sku' => $item['sku'],
                        'quantity' => $item['quantity'] // 直接传递数量，API方法内部会构建正确的结构
                    ];
                }

                woo_walmart_sync_log('批量库存同步-API调用', '调试', [
                    'batch_number' => $current_batch_number,
                    'batch_size' => count($batch),
                    'inventory_data' => $inventory_data
                ], "批次 {$current_batch_number} API调用开始");

                // 调用批量库存更新API
                $result = $this->api_auth->bulk_update_inventory($inventory_data);

                woo_walmart_sync_log('批量库存同步-API响应', '调试', [
                    'batch_number' => $current_batch_number,
                    'api_result' => $result,
                    'is_wp_error' => is_wp_error($result)
                ], "批次 {$current_batch_number} API响应");

                if (!is_wp_error($result)) {
                    // 处理批量响应结果
                    $batch_result = $this->process_batch_inventory_response($batch, $result);
                    $success_count += $batch_result['success_count'];
                    $failed_count += $batch_result['failed_count'];
                    $errors = array_merge($errors, $batch_result['errors']);

                    woo_walmart_sync_log('批量库存同步-批次成功', '成功', [
                        'batch_number' => $current_batch_number,
                        'batch_success' => $batch_result['success_count'],
                        'batch_failed' => $batch_result['failed_count']
                    ], "批次 {$current_batch_number} 处理完成");

                } else {
                    // 整个批次失败
                    $batch_failed_count = count($batch);
                    $failed_count += $batch_failed_count;

                    foreach ($batch as $item) {
                        $errors[] = "商品 {$item['sku']} 批量API调用失败: " . $result->get_error_message();
                    }

                    woo_walmart_sync_log('批量库存同步-批次失败', '错误', [
                        'batch_number' => $current_batch_number,
                        'batch_size' => $batch_failed_count,
                        'error_message' => $result->get_error_message(),
                        'error_code' => $result->get_error_code()
                    ], "批次 {$current_batch_number} 完全失败");
                }

                // 批次间添加短暂延迟
                if ($current_batch_number < $total_batches) {
                    usleep(200000); // 0.2秒延迟
                }

            } catch (Exception $e) {
                // 批次异常处理
                $batch_failed_count = count($batch);
                $failed_count += $batch_failed_count;

                foreach ($batch as $item) {
                    $errors[] = "商品 {$item['sku']} 批次处理异常: " . $e->getMessage();
                }

                woo_walmart_sync_log('批量库存同步-批次异常', '错误', [
                    'batch_number' => $current_batch_number,
                    'batch_size' => $batch_failed_count,
                    'exception' => $e->getMessage()
                ], "批次 {$current_batch_number} 处理异常");
            }
        }

        woo_walmart_sync_log('批量库存同步-API处理完成', '信息', [
            'total_batches' => $total_batches,
            'total_success' => $success_count,
            'total_failed' => $failed_count,
            'total_errors' => count($errors)
        ], "批量API处理完成");

        return [
            'success_count' => $success_count,
            'failed_count' => $failed_count,
            'errors' => $errors
        ];
    }

    /**
     * 处理批量库存同步响应
     * @param array $batch 批次数据
     * @param array $response API响应
     * @return array
     */
    private function process_batch_inventory_response($batch, $response) {
        $success_count = 0;
        $failed_count = 0;
        $errors = [];

        woo_walmart_sync_log('批量库存同步-响应处理', '调试', [
            'batch_size' => count($batch),
            'response_structure' => is_array($response) ? array_keys($response) : 'not_array',
            'response' => $response
        ], "开始处理批量响应");

        // 处理每个商品的结果
        foreach ($batch as $item) {
            $sku = $item['sku'];
            $quantity = $item['quantity'];
            $walmart_item = $item['walmart_item'];

            try {
                // 检查响应中是否包含该SKU的结果
                $item_success = $this->check_item_success_in_batch_response($sku, $response);

                if ($item_success) {
                    // 更新本地缓存
                    global $wpdb;
                    $updated = $wpdb->update(
                        $this->cache_table,
                        ['inventory_count' => $quantity, 'updated_at' => current_time('mysql')],
                        ['id' => $walmart_item->id],
                        ['%d', '%s'],
                        ['%d']
                    );

                    $success_count++;

                    woo_walmart_sync_log('批量库存同步-单项成功', '成功', [
                        'sku' => $sku,
                        'quantity' => $quantity,
                        'updated_rows' => $updated
                    ], "商品 {$sku} 库存同步成功: {$quantity}");

                } else {
                    $failed_count++;
                    $error_message = $this->get_item_error_from_batch_response($sku, $response);
                    $errors[] = "商品 {$sku} 同步失败: " . $error_message;

                    woo_walmart_sync_log('批量库存同步-单项失败', '错误', [
                        'sku' => $sku,
                        'error_message' => $error_message
                    ], "商品 {$sku} 库存同步失败");
                }

            } catch (Exception $e) {
                $failed_count++;
                $errors[] = "商品 {$sku} 响应处理异常: " . $e->getMessage();

                woo_walmart_sync_log('批量库存同步-单项异常', '错误', [
                    'sku' => $sku,
                    'exception' => $e->getMessage()
                ], "商品 {$sku} 响应处理异常");
            }
        }

        woo_walmart_sync_log('批量库存同步-响应处理完成', '信息', [
            'batch_size' => count($batch),
            'success_count' => $success_count,
            'failed_count' => $failed_count
        ], "批量响应处理完成");

        return [
            'success_count' => $success_count,
            'failed_count' => $failed_count,
            'errors' => $errors
        ];
    }

    /**
     * 检查批量响应中单个商品是否成功
     * @param string $sku
     * @param array $response
     * @return bool
     */
    private function check_item_success_in_batch_response($sku, $response) {
        // 如果响应是成功的且包含预期的结构，认为成功
        if (is_array($response)) {
            // 检查是否有错误信息
            if (isset($response['errors']) && !empty($response['errors'])) {
                return false;
            }

            // 检查是否有成功的指示
            if (isset($response['elements']) || isset($response['inventory']) || isset($response['success'])) {
                return true;
            }

            // 如果没有明确的错误，且响应结构合理，认为成功
            return !isset($response['error']);
        }

        return false;
    }

    /**
     * 从批量响应中获取单个商品的错误信息
     * @param string $sku
     * @param array $response
     * @return string
     */
    private function get_item_error_from_batch_response($sku, $response) {
        if (is_array($response)) {
            if (isset($response['errors']) && !empty($response['errors'])) {
                // 查找特定SKU的错误
                foreach ($response['errors'] as $error) {
                    if (isset($error['sku']) && $error['sku'] === $sku) {
                        return isset($error['message']) ? $error['message'] : '未知错误';
                    }
                }
                // 如果没有找到特定SKU的错误，返回第一个错误
                $first_error = reset($response['errors']);
                return isset($first_error['message']) ? $first_error['message'] : '批量操作错误';
            }

            if (isset($response['error'])) {
                return is_string($response['error']) ? $response['error'] : '批量API错误';
            }
        }

        return '未知的批量API错误';
    }

    /**
     * 同步单个商品库存
     * @param object $item
     * @param string $match_method
     * @return bool
     */
    private function sync_single_inventory($item, $match_method) {
        // 记录开始同步
        woo_walmart_sync_log('批量库存同步-单个商品', '信息', [
            'sku' => $item->sku,
            'match_method' => $match_method
        ], "开始同步商品 {$item->sku} 的库存");

        // 根据SKU找到对应的WooCommerce商品
        $wc_product = $this->find_woocommerce_product($item, $match_method);
        if (!$wc_product) {
            woo_walmart_sync_log('批量库存同步-商品未找到', '错误', [
                'sku' => $item->sku,
                'match_method' => $match_method
            ], "未找到对应的WooCommerce商品: {$item->sku}");
            return false;
        }

        // 获取WooCommerce库存
        $wc_inventory = $wc_product->get_stock_quantity();
        if ($wc_inventory === null) {
            $wc_inventory = 0;
        }

        woo_walmart_sync_log('批量库存同步-获取库存', '信息', [
            'sku' => $item->sku,
            'wc_product_id' => $wc_product->get_id(),
            'wc_inventory' => $wc_inventory
        ], "WooCommerce商品 {$wc_product->get_id()} 库存: {$wc_inventory}");

        // 调用Walmart库存更新API - 使用正确的API方法
        $inventory_data = [
            'sku' => $item->sku,
            'quantity' => [
                'unit' => 'EACH',
                'amount' => (int) $wc_inventory
            ]
        ];

        // 使用专门的库存更新API方法
        $result = $this->api_auth->update_inventory($inventory_data);

        woo_walmart_sync_log('批量库存同步-API调用', '调试', [
            'sku' => $item->sku,
            'inventory_data' => $inventory_data,
            'api_result' => $result,
            'is_wp_error' => is_wp_error($result)
        ], "Walmart库存API调用结果");

        if (!is_wp_error($result)) {
            // 检查API响应是否成功
            if (isset($result['sku']) || isset($result['quantity'])) {
                // 更新本地缓存
                global $wpdb;
                $updated = $wpdb->update(
                    $this->cache_table,
                    ['inventory_count' => $wc_inventory, 'updated_at' => current_time('mysql')],
                    ['id' => $item->id],
                    ['%d', '%s'],
                    ['%d']
                );

                woo_walmart_sync_log('批量库存同步-成功', '成功', [
                    'sku' => $item->sku,
                    'wc_inventory' => $wc_inventory,
                    'updated_rows' => $updated
                ], "商品 {$item->sku} 库存同步成功: {$wc_inventory}");

                return true;
            } else {
                woo_walmart_sync_log('批量库存同步-API响应异常', '错误', [
                    'sku' => $item->sku,
                    'api_result' => $result
                ], "API响应格式异常: {$item->sku}");
                return false;
            }
        } else {
            woo_walmart_sync_log('批量库存同步-API错误', '错误', [
                'sku' => $item->sku,
                'error_message' => $result->get_error_message(),
                'error_code' => $result->get_error_code()
            ], "API调用失败: {$item->sku} - " . $result->get_error_message());
            return false;
        }
    }

    /**
     * 同步单个商品价格
     * @param object $item
     * @param string $match_method
     * @return bool
     */
    private function sync_single_price($item, $match_method) {
        // 根据SKU找到对应的WooCommerce商品
        $wc_product = $this->find_woocommerce_product($item, $match_method);
        if (!$wc_product) {
            return false;
        }

        // 获取WooCommerce价格
        $wc_price = $wc_product->get_price();
        if (empty($wc_price)) {
            return false;
        }

        // 调用Walmart价格更新API
        $endpoint = "/v3/price";
        $price_data = [
            'sku' => $item->sku,
            'pricing' => [
                [
                    'currentPriceType' => 'BASE',
                    'currentPrice' => [
                        'currency' => 'USD',
                        'amount' => round(floatval($wc_price), 2)
                    ]
                ]
            ]
        ];

        $result = $this->api_auth->make_request($endpoint, 'PUT', $price_data);

        if (!is_wp_error($result)) {
            // 更新本地缓存
            global $wpdb;
            $wpdb->update(
                $this->cache_table,
                ['price' => $wc_price, 'updated_at' => current_time('mysql')],
                ['id' => $item->id],
                ['%f', '%s'],
                ['%d']
            );
            return true;
        }

        return false;
    }

    /**
     * 更新商品状态
     * @param object $item
     * @param string $status
     * @param string $match_method
     * @return bool
     */
    private function update_product_status($item, $status, $match_method) {
        // 调用Walmart商品状态更新API
        $endpoint = "/v3/items/{$item->sku}/retire";
        if ($status === 'PUBLISHED') {
            $endpoint = "/v3/items/{$item->sku}/publish";
        }

        woo_walmart_sync_log('商品状态更新-开始', '信息', [
            'sku' => $item->sku,
            'target_status' => $status,
            'endpoint' => $endpoint
        ], "开始更新商品状态: {$item->sku} -> {$status}");

        $result = $this->api_auth->make_request($endpoint, 'POST');

        woo_walmart_sync_log('商品状态更新-API响应', '调试', [
            'sku' => $item->sku,
            'target_status' => $status,
            'endpoint' => $endpoint,
            'api_result' => $result,
            'is_wp_error' => is_wp_error($result)
        ], "商品状态更新API响应");

        if (!is_wp_error($result)) {
            // 检查API响应是否包含错误信息
            if (is_array($result) && isset($result['error'])) {
                woo_walmart_sync_log('商品状态更新-API错误', '错误', [
                    'sku' => $item->sku,
                    'target_status' => $status,
                    'api_error' => $result['error']
                ], "API返回错误: " . (is_array($result['error']) ? json_encode($result['error']) : $result['error']));
                return false;
            }

            // 更新本地缓存
            global $wpdb;
            $updated = $wpdb->update(
                $this->cache_table,
                ['status' => $status, 'updated_at' => current_time('mysql')],
                ['id' => $item->id],
                ['%s', '%s'],
                ['%d']
            );

            woo_walmart_sync_log('商品状态更新-成功', '成功', [
                'sku' => $item->sku,
                'target_status' => $status,
                'updated_rows' => $updated
            ], "商品状态更新成功: {$item->sku} -> {$status}");

            return true;
        } else {
            // 详细记录WP_Error
            woo_walmart_sync_log('商品状态更新-失败', '错误', [
                'sku' => $item->sku,
                'target_status' => $status,
                'endpoint' => $endpoint,
                'error_message' => $result->get_error_message(),
                'error_code' => $result->get_error_code(),
                'error_data' => $result->get_error_data()
            ], "商品状态更新失败: " . $result->get_error_message());
        }

        return false;
    }

    /**
     * 根据匹配方式找到对应的WooCommerce商品
     * @param object $item
     * @param string $match_method
     * @return WC_Product|null
     */
    private function find_woocommerce_product($item, $match_method) {
        global $wpdb;

        $product_id = null;

        woo_walmart_sync_log('商品匹配-开始', '调试', [
            'sku' => $item->sku,
            'upc' => isset($item->upc) ? $item->upc : 'N/A',
            'match_method' => $match_method
        ], "开始匹配商品: {$item->sku}");

        switch ($match_method) {
            case 'sku':
                // 按SKU匹配
                $product_id = $wpdb->get_var($wpdb->prepare(
                    "SELECT post_id FROM {$wpdb->postmeta}
                     WHERE meta_key = '_sku' AND meta_value = %s",
                    $item->sku
                ));

                woo_walmart_sync_log('商品匹配-SKU', '调试', [
                    'sku' => $item->sku,
                    'found_product_id' => $product_id,
                    'sql_error' => $wpdb->last_error
                ], "SKU匹配结果: " . ($product_id ? "找到商品ID {$product_id}" : "未找到"));
                break;

            case 'upc':
                // 按UPC匹配
                $upc_table = $wpdb->prefix . 'walmart_upc_pool';
                $product_id = $wpdb->get_var($wpdb->prepare(
                    "SELECT product_id FROM {$upc_table}
                     WHERE upc_code = %s AND is_used = 1",
                    $item->upc
                ));

                woo_walmart_sync_log('商品匹配-UPC', '调试', [
                    'upc' => $item->upc,
                    'found_product_id' => $product_id,
                    'sql_error' => $wpdb->last_error
                ], "UPC匹配结果: " . ($product_id ? "找到商品ID {$product_id}" : "未找到"));
                break;

            case 'both':
                // 先按SKU匹配，再按UPC匹配
                $product_id = $wpdb->get_var($wpdb->prepare(
                    "SELECT post_id FROM {$wpdb->postmeta}
                     WHERE meta_key = '_sku' AND meta_value = %s",
                    $item->sku
                ));

                woo_walmart_sync_log('商品匹配-SKU优先', '调试', [
                    'sku' => $item->sku,
                    'found_product_id' => $product_id
                ], "SKU优先匹配结果: " . ($product_id ? "找到商品ID {$product_id}" : "未找到，尝试UPC"));

                if (!$product_id && isset($item->upc)) {
                    $upc_table = $wpdb->prefix . 'walmart_upc_pool';
                    $product_id = $wpdb->get_var($wpdb->prepare(
                        "SELECT product_id FROM {$upc_table}
                         WHERE upc_code = %s AND is_used = 1",
                        $item->upc
                    ));

                    woo_walmart_sync_log('商品匹配-UPC备选', '调试', [
                        'upc' => $item->upc,
                        'found_product_id' => $product_id
                    ], "UPC备选匹配结果: " . ($product_id ? "找到商品ID {$product_id}" : "未找到"));
                }
                break;
        }

        if ($product_id) {
            $product = wc_get_product($product_id);
            if ($product) {
                woo_walmart_sync_log('商品匹配-成功', '成功', [
                    'sku' => $item->sku,
                    'product_id' => $product_id,
                    'product_name' => $product->get_name(),
                    'product_sku' => $product->get_sku(),
                    'match_method' => $match_method
                ], "成功匹配商品: {$product->get_name()} (ID: {$product_id})");
                return $product;
            } else {
                woo_walmart_sync_log('商品匹配-商品无效', '错误', [
                    'sku' => $item->sku,
                    'product_id' => $product_id
                ], "找到商品ID但商品对象无效: {$product_id}");
            }
        } else {
            woo_walmart_sync_log('商品匹配-失败', '警告', [
                'sku' => $item->sku,
                'upc' => isset($item->upc) ? $item->upc : 'N/A',
                'match_method' => $match_method
            ], "未找到匹配的WooCommerce商品: {$item->sku}");
        }

        return null;
    }

    /**
     * 同步库存数据
     * @param array $products 商品数据
     */
    /**
     * 同步所有商品的库存数据
     */
    public function sync_all_inventory() {
        // 设置更长的执行时间限制
        set_time_limit(0); // 无限制
        ini_set('memory_limit', '512M'); // 增加内存限制

        global $wpdb;

        // 获取所有商品的SKU
        $all_skus = $wpdb->get_col("SELECT sku FROM {$this->cache_table} ORDER BY updated_at DESC");
        $total_products = count($all_skus);

        if (empty($all_skus)) {
            $this->add_notification(
                'inventory_sync_error',
                '库存同步失败',
                '没有找到商品数据，请先同步商品',
                'error'
            );
            return false;
        }

        $inventory_updated = 0;
        $inventory_errors = 0;
        $batch_size = 50; // Walmart API限制
        $total_batches = ceil($total_products / $batch_size);

        // 记录开始同步
        woo_walmart_sync_log('开始库存同步', '信息', [
            'total_products' => $total_products,
            'total_batches' => $total_batches,
            'batch_size' => $batch_size
        ], "开始同步 {$total_products} 个商品的库存，共 {$total_batches} 个批次");

        // 使用cursor分页处理
        $cursor = null;
        $batch_index = 0;
        $processed_count = 0;

        do {
            $batch_index++;
            $has_more_data = false;

            try {
                // 使用Walmart批量库存API（cursor分页）
                $inventory_result = $this->api_auth->get_inventories($batch_size, $cursor);

                // 详细记录API响应用于调试
                woo_walmart_sync_log('库存API响应调试', '调试', [
                    'batch_index' => $batch_index,
                    'cursor' => $cursor,
                    'batch_size' => $batch_size,
                    'api_response' => $inventory_result,
                    'is_wp_error' => is_wp_error($inventory_result)
                ], "批次 {$batch_index} 库存API响应");

                if (!is_wp_error($inventory_result) && isset($inventory_result['elements']['inventories'])) {
                    $inventory_data = $inventory_result['elements']['inventories'];
                    $current_batch_count = count($inventory_data);
                    $processed_count += $current_batch_count;

                    // 处理返回的库存数据
                    foreach ($inventory_data as $item) {
                        if (isset($item['sku'])) {
                            $sku = $item['sku'];
                            $inventory_count = 0;

                            // 解析库存数据 - 从nodes数组中获取库存
                            if (isset($item['nodes']) && is_array($item['nodes']) && !empty($item['nodes'])) {
                                $first_node = $item['nodes'][0];

                                // 优先使用availToSellQty，其次是inputQty
                                if (isset($first_node['availToSellQty']['amount'])) {
                                    $inventory_count = intval($first_node['availToSellQty']['amount']);
                                } elseif (isset($first_node['inputQty']['amount'])) {
                                    $inventory_count = intval($first_node['inputQty']['amount']);
                                }
                            }

                            // 更新数据库中的库存
                            $updated = $wpdb->update(
                                $this->cache_table,
                                ['inventory_count' => $inventory_count, 'updated_at' => current_time('mysql')],
                                ['sku' => $sku],
                                ['%d', '%s'],
                                ['%s']
                            );

                            // 详细记录每个SKU的更新结果
                            woo_walmart_sync_log('SKU库存更新', '调试', [
                                'sku' => $sku,
                                'inventory_count' => $inventory_count,
                                'updated_rows' => $updated,
                                'wpdb_error' => $wpdb->last_error
                            ], "SKU {$sku} 库存更新: {$inventory_count}，影响行数: {$updated}");

                            if ($updated !== false && $updated > 0) {
                                $inventory_updated++;
                            } else {
                                $inventory_errors++;
                            }
                        }
                    }

                    // 检查是否有下一页
                    if (isset($inventory_result['meta']['nextCursor'])) {
                        $cursor = $inventory_result['meta']['nextCursor'];
                        $has_more_data = true;
                    }

                    // 记录批次成功
                    woo_walmart_sync_log('批次库存同步成功', '成功', [
                        'batch_index' => $batch_index,
                        'items_in_batch' => $current_batch_count,
                        'processed_count' => $processed_count,
                        'total_products' => $total_products,
                        'has_more_data' => $has_more_data,
                        'next_cursor' => $cursor
                    ], "批次 {$batch_index} 完成，处理了 {$current_batch_count} 个商品，总进度: {$processed_count}/{$total_products}");

                } else {
                    // 批量API失败，尝试使用单个API作为备选方案
                    woo_walmart_sync_log('批量库存API失败，尝试单个API', '警告', [
                        'batch_index' => $batch_index,
                        'cursor' => $cursor,
                        'batch_size' => $batch_size
                    ], "批量API失败，切换到单个API模式");

                    // 获取当前批次的SKU列表
                    $current_skus = $wpdb->get_results($wpdb->prepare(
                        "SELECT sku FROM {$this->cache_table} ORDER BY updated_at DESC LIMIT %d OFFSET %d",
                        $batch_size,
                        ($batch_index - 1) * $batch_size
                    ));

                    if (!empty($current_skus)) {
                        foreach ($current_skus as $sku_row) {
                            $sku = $sku_row->sku;

                            try {
                                // 使用单个库存API
                                $single_result = $this->api_auth->get_inventory($sku);

                                if (!is_wp_error($single_result) && isset($single_result['quantity']['amount'])) {
                                    $inventory_count = intval($single_result['quantity']['amount']);

                                    // 更新数据库
                                    $updated = $wpdb->update(
                                        $this->cache_table,
                                        ['inventory_count' => $inventory_count, 'updated_at' => current_time('mysql')],
                                        ['sku' => $sku],
                                        ['%d', '%s'],
                                        ['%s']
                                    );

                                    if ($updated !== false && $updated > 0) {
                                        $inventory_updated++;
                                    } else {
                                        $inventory_errors++;
                                    }

                                    woo_walmart_sync_log('单个库存API成功', '调试', [
                                        'sku' => $sku,
                                        'inventory_count' => $inventory_count,
                                        'updated_rows' => $updated
                                    ], "SKU {$sku} 单个API更新成功: {$inventory_count}");

                                } else {
                                    $inventory_errors++;
                                    woo_walmart_sync_log('单个库存API失败', '错误', [
                                        'sku' => $sku,
                                        'api_response' => $single_result
                                    ], "SKU {$sku} 单个API失败");
                                }

                                // 添加延迟避免API频率限制
                                usleep(100000); // 0.1秒延迟

                            } catch (Exception $e) {
                                $inventory_errors++;
                                woo_walmart_sync_log('单个库存API异常', '错误', [
                                    'sku' => $sku,
                                    'error' => $e->getMessage()
                                ], "SKU {$sku} 单个API异常: " . $e->getMessage());
                            }
                        }

                        $processed_count += count($current_skus);
                        $has_more_data = $processed_count < $total_products;

                        woo_walmart_sync_log('单个API批次完成', '成功', [
                            'batch_index' => $batch_index,
                            'processed_skus' => count($current_skus),
                            'processed_count' => $processed_count,
                            'total_products' => $total_products,
                            'has_more_data' => $has_more_data
                        ], "单个API批次 {$batch_index} 完成，处理了 " . count($current_skus) . " 个SKU");

                    } else {
                        $has_more_data = false;
                        woo_walmart_sync_log('没有更多SKU', '信息', [
                            'batch_index' => $batch_index,
                            'processed_count' => $processed_count
                        ], "没有更多SKU需要处理");
                    }
                }

                // 批次间延迟，避免API频率限制
                if ($has_more_data) {
                    sleep(2); // 2秒延迟，更保守
                }

            } catch (Exception $e) {
                $inventory_errors += $batch_size;
                woo_walmart_sync_log('库存同步异常', '错误', [
                    'batch_index' => $batch_index,
                    'cursor' => $cursor,
                    'error' => $e->getMessage()
                ], "批次 {$batch_index} 异常: " . $e->getMessage());
                break; // 异常时停止处理
            }

        } while ($has_more_data && $processed_count < $total_products);

        // 记录库存同步完成
        woo_walmart_sync_log('库存同步完成', '信息', [
            'total_products' => $total_products,
            'total_batches' => $batch_index,
            'processed_count' => $processed_count,
            'inventory_updated' => $inventory_updated,
            'inventory_errors' => $inventory_errors,
            'success_rate' => $processed_count > 0 ? round($inventory_updated / $processed_count * 100, 2) . '%' : '0%'
        ], "库存同步完成：总计 {$total_products} 个商品，处理了 {$processed_count} 个，成功 {$inventory_updated} 个，失败 {$inventory_errors} 个");

        // 添加库存同步通知
        $success_rate = $processed_count > 0 ? round($inventory_updated / $processed_count * 100, 2) : 0;
        $this->add_notification(
            'inventory_sync_complete',
            'Walmart库存同步完成',
            "库存同步完成：总计 {$total_products} 个商品，处理了 {$processed_count} 个，成功 {$inventory_updated} 个，失败 {$inventory_errors} 个，成功率 {$success_rate}%",
            $inventory_errors > ($processed_count * 0.1) ? 'warning' : 'success', // 失败率超过10%显示警告
            [
                'total_products' => $total_products,
                'total_batches' => $batch_index,
                'processed_count' => $processed_count,
                'inventory_updated' => $inventory_updated,
                'inventory_errors' => $inventory_errors,
                'success_rate' => $success_rate
            ]
        );

        return [
            'success' => true,
            'total_products' => $total_products,
            'processed_count' => $processed_count,
            'inventory_updated' => $inventory_updated,
            'inventory_errors' => $inventory_errors,
            'success_rate' => $success_rate
        ];
    }

    /**
     * 测试库存同步 - 只同步前5个商品用于调试
     */
    public function test_inventory_sync() {
        global $wpdb;

        // 获取前5个商品进行测试
        $test_products = $wpdb->get_results("SELECT sku FROM {$this->cache_table} LIMIT 5");

        if (empty($test_products)) {
            return ['success' => false, 'message' => '没有找到测试商品'];
        }

        // 测试单个库存API
        foreach ($test_products as $product) {
            $sku = $product->sku;

            woo_walmart_sync_log('测试单个库存API', '调试', [
                'sku' => $sku
            ], "测试SKU: {$sku}");

            $inventory_result = $this->api_auth->get_inventory($sku);

            woo_walmart_sync_log('单个库存API响应', '调试', [
                'sku' => $sku,
                'api_response' => $inventory_result,
                'is_wp_error' => is_wp_error($inventory_result)
            ], "SKU {$sku} 库存API响应");
        }

        // 测试批量库存API
        woo_walmart_sync_log('测试批量库存API', '调试', [], "测试批量库存API");

        $batch_result = $this->api_auth->get_inventories(5, null);

        // 详细记录批量库存API响应
        if (is_wp_error($batch_result)) {
            woo_walmart_sync_log('批量库存API错误', '错误', [
                'error_message' => $batch_result->get_error_message(),
                'error_code' => $batch_result->get_error_code(),
                'error_data' => $batch_result->get_error_data()
            ], "批量库存API调用失败");
        } else {
            woo_walmart_sync_log('批量库存API响应', '调试', [
                'api_response' => $batch_result,
                'is_wp_error' => false,
                'has_elements' => isset($batch_result['elements']),
                'has_inventories' => isset($batch_result['elements']['inventories']),
                'response_keys' => is_array($batch_result) ? array_keys($batch_result) : 'not_array',
                'response_type' => gettype($batch_result),
                'response_size' => is_array($batch_result) ? count($batch_result) : 'not_countable'
            ], "批量库存API响应详情");

            // 如果有库存数据，记录前几个
            if (isset($batch_result['elements']['inventories']) && is_array($batch_result['elements']['inventories'])) {
                $inventories = $batch_result['elements']['inventories'];
                $sample_inventories = array_slice($inventories, 0, 3); // 只记录前3个

                woo_walmart_sync_log('批量库存数据样本', '调试', [
                    'total_inventories' => count($inventories),
                    'sample_inventories' => $sample_inventories
                ], "批量库存数据样本 (前3个)");
            }
        }

        // 测试SKU匹配
        if (!is_wp_error($batch_result) && isset($batch_result['elements']['inventories'])) {
            $inventory_data = $batch_result['elements']['inventories'];
            $db_skus = $wpdb->get_col("SELECT sku FROM {$this->cache_table} LIMIT 10");
            $api_skus = array_column($inventory_data, 'sku');

            woo_walmart_sync_log('SKU匹配测试', '调试', [
                'db_skus' => $db_skus,
                'api_skus' => $api_skus,
                'matching_skus' => array_intersect($db_skus, $api_skus),
                'db_count' => count($db_skus),
                'api_count' => count($api_skus)
            ], "SKU匹配测试结果");
        }

        // 测试商品API分页
        woo_walmart_sync_log('测试商品API分页', '调试', [], "测试商品API分页功能");

        // 测试第一页
        $items_result_1 = $this->api_auth->make_request("/v3/items?limit=5&offset=0");
        woo_walmart_sync_log('商品API第一页', '调试', [
            'api_response' => $items_result_1,
            'is_wp_error' => is_wp_error($items_result_1),
            'has_items' => isset($items_result_1['ItemResponse']),
            'items_count' => isset($items_result_1['ItemResponse']) ? (is_array($items_result_1['ItemResponse']) ? count($items_result_1['ItemResponse']) : 1) : 0
        ], "商品API第一页响应");

        // 测试第二页
        $items_result_2 = $this->api_auth->make_request("/v3/items?limit=5&offset=5");
        woo_walmart_sync_log('商品API第二页', '调试', [
            'api_response' => $items_result_2,
            'is_wp_error' => is_wp_error($items_result_2),
            'has_items' => isset($items_result_2['ItemResponse']),
            'items_count' => isset($items_result_2['ItemResponse']) ? (is_array($items_result_2['ItemResponse']) ? count($items_result_2['ItemResponse']) : 1) : 0
        ], "商品API第二页响应");

        // 测试不带分页参数的API
        $items_result_all = $this->api_auth->make_request("/v3/items");
        woo_walmart_sync_log('商品API无分页', '调试', [
            'api_response' => $items_result_all,
            'is_wp_error' => is_wp_error($items_result_all),
            'has_items' => isset($items_result_all['ItemResponse']),
            'items_count' => isset($items_result_all['ItemResponse']) ? (is_array($items_result_all['ItemResponse']) ? count($items_result_all['ItemResponse']) : 1) : 0
        ], "商品API无分页参数响应");

        return ['success' => true, 'message' => '测试完成，请查看日志'];
    }

    /**
     * 兼容旧的库存同步方法
     */
    public function sync_inventory_data($products) {
        return $this->sync_all_inventory();
    }

    /**
     * 同步单个商品价格（公共方法）
     * @param object $walmart_product Walmart商品数据
     * @param object $local_data 本地商品数据
     * @return array
     */
    public function sync_single_product_price($walmart_product, $local_data) {
        try {
            woo_walmart_sync_log('单个商品价格同步', '开始', [
                'sku' => $walmart_product->sku,
                'walmart_price' => $walmart_product->price,
                'local_price' => $local_data->price
            ], "开始同步商品 {$walmart_product->sku} 的价格");

            // 准备价格数据
            $price_data = [
                'sku' => $walmart_product->sku,
                'pricing' => [
                    [
                        'currentPriceType' => 'BASE',
                        'currentPrice' => [
                            'currency' => 'USD',
                            'amount' => round(floatval($local_data->price), 2)
                        ]
                    ]
                ]
            ];

            // 调用价格更新API
            $endpoint = "/v3/price";
            $result = $this->api_auth->make_request($endpoint, 'PUT', $price_data);

            if (!is_wp_error($result)) {
                // 更新本地缓存
                global $wpdb;
                $wpdb->update(
                    $this->cache_table,
                    ['price' => $local_data->price, 'updated_at' => current_time('mysql')],
                    ['id' => $walmart_product->id],
                    ['%f', '%s'],
                    ['%d']
                );

                woo_walmart_sync_log('单个商品价格同步', '成功', [
                    'sku' => $walmart_product->sku,
                    'new_price' => $local_data->price
                ], "商品 {$walmart_product->sku} 价格同步成功");

                return [
                    'success' => true,
                    'message' => "价格已更新为 $" . number_format($local_data->price, 2)
                ];
            } else {
                woo_walmart_sync_log('单个商品价格同步', '失败', [
                    'sku' => $walmart_product->sku,
                    'error' => $result->get_error_message()
                ], "商品 {$walmart_product->sku} 价格同步失败");

                return [
                    'success' => false,
                    'message' => $result->get_error_message()
                ];
            }

        } catch (Exception $e) {
            woo_walmart_sync_log('单个商品价格同步', '异常', [
                'sku' => $walmart_product->sku,
                'exception' => $e->getMessage()
            ], "商品 {$walmart_product->sku} 价格同步异常");

            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * 同步单个商品库存（公共方法）
     * @param object $walmart_product Walmart商品数据
     * @param object $local_data 本地商品数据
     * @return array
     */
    public function sync_single_product_inventory($walmart_product, $local_data) {
        try {
            woo_walmart_sync_log('单个商品库存同步', '开始', [
                'sku' => $walmart_product->sku,
                'walmart_inventory' => $walmart_product->inventory_count,
                'local_inventory' => $local_data->stock_quantity
            ], "开始同步商品 {$walmart_product->sku} 的库存");

            // 准备库存数据
            $inventory_data = [
                'sku' => $walmart_product->sku,
                'quantity' => [
                    'unit' => 'EACH',
                    'amount' => intval($local_data->stock_quantity)
                ]
            ];

            // 调用库存更新API
            $result = $this->api_auth->update_inventory($inventory_data);

            if (!is_wp_error($result)) {
                // 更新本地缓存
                global $wpdb;
                $wpdb->update(
                    $this->cache_table,
                    ['inventory_count' => $local_data->stock_quantity, 'updated_at' => current_time('mysql')],
                    ['id' => $walmart_product->id],
                    ['%d', '%s'],
                    ['%d']
                );

                woo_walmart_sync_log('单个商品库存同步', '成功', [
                    'sku' => $walmart_product->sku,
                    'new_inventory' => $local_data->stock_quantity
                ], "商品 {$walmart_product->sku} 库存同步成功");

                return [
                    'success' => true,
                    'message' => "库存已更新为 " . $local_data->stock_quantity
                ];
            } else {
                woo_walmart_sync_log('单个商品库存同步', '失败', [
                    'sku' => $walmart_product->sku,
                    'error' => $result->get_error_message()
                ], "商品 {$walmart_product->sku} 库存同步失败");

                return [
                    'success' => false,
                    'message' => $result->get_error_message()
                ];
            }

        } catch (Exception $e) {
            woo_walmart_sync_log('单个商品库存同步', '异常', [
                'sku' => $walmart_product->sku,
                'exception' => $e->getMessage()
            ], "商品 {$walmart_product->sku} 库存同步异常");

            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * 强制同步单个商品（价格和库存）
     * @param object $walmart_product Walmart商品数据
     * @param object $local_data 本地商品数据
     * @return array
     */
    public function force_sync_single_product($walmart_product, $local_data) {
        try {
            woo_walmart_sync_log('单个商品强制同步', '开始', [
                'sku' => $walmart_product->sku
            ], "开始强制同步商品 {$walmart_product->sku}");

            $results = [];
            $success_count = 0;
            $failed_count = 0;

            // 同步价格
            $price_result = $this->sync_single_product_price($walmart_product, $local_data);
            $results[] = "价格: " . ($price_result['success'] ? '成功' : '失败');
            if ($price_result['success']) $success_count++; else $failed_count++;

            // 同步库存
            $inventory_result = $this->sync_single_product_inventory($walmart_product, $local_data);
            $results[] = "库存: " . ($inventory_result['success'] ? '成功' : '失败');
            if ($inventory_result['success']) $success_count++; else $failed_count++;

            $overall_success = $failed_count === 0;

            woo_walmart_sync_log('单个商品强制同步', $overall_success ? '成功' : '部分失败', [
                'sku' => $walmart_product->sku,
                'success_count' => $success_count,
                'failed_count' => $failed_count,
                'results' => $results
            ], "商品 {$walmart_product->sku} 强制同步完成");

            return [
                'success' => $overall_success,
                'message' => implode(', ', $results)
            ];

        } catch (Exception $e) {
            woo_walmart_sync_log('单个商品强制同步', '异常', [
                'sku' => $walmart_product->sku,
                'exception' => $e->getMessage()
            ], "商品 {$walmart_product->sku} 强制同步异常");

            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * 处理Walmart数据获取操作
     * @param array $selected_items 选中的商品
     * @param string $action 操作类型
     * @return array
     */
    public function process_walmart_fetch_action($selected_items, $action) {
        try {
            woo_walmart_sync_log('Walmart数据获取', '开始', [
                'action' => $action,
                'item_count' => count($selected_items),
                'selected_skus' => array_column($selected_items, 'sku')
            ], "开始执行Walmart数据获取操作: {$action}");

            if (count($selected_items) === 0) {
                return [
                    'success' => false,
                    'message' => '没有选中的商品'
                ];
            }

            $success_count = 0;
            $failed_count = 0;
            $errors = [];

            switch ($action) {
                case 'fetch_walmart_price':
                    $result = $this->fetch_walmart_prices($selected_items);
                    break;

                case 'fetch_walmart_inventory':
                    $result = $this->fetch_walmart_inventories($selected_items);
                    break;

                case 'fetch_walmart_both':
                    $price_result = $this->fetch_walmart_prices($selected_items);
                    $inventory_result = $this->fetch_walmart_inventories($selected_items);

                    $result = [
                        'success_count' => $price_result['success_count'] + $inventory_result['success_count'],
                        'failed_count' => $price_result['failed_count'] + $inventory_result['failed_count'],
                        'errors' => array_merge($price_result['errors'], $inventory_result['errors'])
                    ];
                    break;

                default:
                    return [
                        'success' => false,
                        'message' => '未知的操作类型: ' . $action
                    ];
            }

            $success_count = $result['success_count'];
            $failed_count = $result['failed_count'];
            $errors = $result['errors'];

            // 记录通知
            $this->add_notification(
                'walmart_fetch',
                "Walmart数据获取操作完成",
                "操作类型: {$action}，成功: {$success_count} 个，失败: {$failed_count} 个",
                $failed_count > 0 ? 'warning' : 'success',
                [
                    'action' => $action,
                    'success_count' => $success_count,
                    'failed_count' => $failed_count,
                    'total_items' => count($selected_items)
                ]
            );

            // 构建结果消息
            $action_names = [
                'fetch_walmart_price' => 'Walmart价格获取',
                'fetch_walmart_inventory' => 'Walmart库存获取',
                'fetch_walmart_both' => 'Walmart数据获取'
            ];

            $action_name = $action_names[$action] ?? 'Walmart数据获取';
            $message = "{$action_name}完成：成功 {$success_count} 个，失败 {$failed_count} 个";

            // 如果有错误，添加到消息中
            if (!empty($errors)) {
                $message .= "\n\n错误详情：\n" . implode("\n", array_slice($errors, 0, 5));
                if (count($errors) > 5) {
                    $message .= "\n... 还有 " . (count($errors) - 5) . " 个错误";
                }
            }

            woo_walmart_sync_log('Walmart数据获取', '完成', [
                'action' => $action,
                'success_count' => $success_count,
                'failed_count' => $failed_count,
                'errors' => $errors,
                'message' => $message
            ], "Walmart数据获取操作完成");

            return [
                'success' => true,
                'message' => $message,
                'success_count' => $success_count,
                'failed_count' => $failed_count,
                'errors' => $errors
            ];

        } catch (Exception $e) {
            woo_walmart_sync_log('Walmart数据获取', '异常', [
                'action' => $action,
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ], "Walmart数据获取异常: " . $e->getMessage());

            return [
                'success' => false,
                'message' => 'Walmart数据获取失败: ' . $e->getMessage()
            ];
        }
    }

    /**
     * 获取Walmart商品价格（批量API）
     * @param array $selected_items 选中的商品
     * @return array
     */
    private function fetch_walmart_prices($selected_items) {
        global $wpdb;
        $cache_table = $wpdb->prefix . 'walmart_products_cache';

        $success_count = 0;
        $failed_count = 0;
        $errors = [];

        // 使用现有的API认证实例
        if (!$this->api_auth) {
            $this->api_auth = new Woo_Walmart_API_Key_Auth();
        }

        woo_walmart_sync_log('Walmart批量价格获取', '开始', [
            'total_items' => count($selected_items),
            'skus' => array_column($selected_items, 'sku')
        ], "开始批量获取 " . count($selected_items) . " 个商品的Walmart价格");

        // 根据Walmart官方API文档，不支持批量SKU查询
        // 直接使用单个API调用，这是官方推荐的方式
        woo_walmart_sync_log('Walmart价格获取', '使用单个API调用', [
            'selected_items_count' => count($selected_items),
            'skus' => array_column($selected_items, 'sku'),
            'reason' => 'Walmart API不支持批量SKU查询'
        ], "根据官方文档，使用单个API调用获取价格");

        return $this->fetch_walmart_prices_individually($selected_items);
    }

    /**
     * 单个API调用获取价格（回退方法）
     * @param array $selected_items 选中的商品
     * @return array
     */
    private function fetch_walmart_prices_individually($selected_items) {
        global $wpdb;
        $cache_table = $wpdb->prefix . 'walmart_products_cache';

        $success_count = 0;
        $failed_count = 0;
        $errors = [];

        woo_walmart_sync_log('Walmart单个价格获取', '开始', [
            'total_items' => count($selected_items)
        ], "开始单个获取 " . count($selected_items) . " 个商品的Walmart价格");

        foreach ($selected_items as $item) {
            try {
                // 使用单个商品API
                $endpoint = '/v3/items/' . urlencode($item->sku);
                $product_details = $this->api_auth->make_request($endpoint, 'GET');

                woo_walmart_sync_log('Walmart单个价格获取-API响应', '调试', [
                    'sku' => $item->sku,
                    'endpoint' => $endpoint,
                    'api_response' => $product_details,
                    'is_wp_error' => is_wp_error($product_details),
                    'response_keys' => is_array($product_details) ? array_keys($product_details) : 'not_array'
                ], "单个API响应详情");

                if (is_wp_error($product_details)) {
                    $failed_count++;
                    $error_msg = "API错误: " . $product_details->get_error_message();
                    $errors[] = "商品 {$item->sku}: {$error_msg}";
                    continue;
                }

                // 尝试多种价格数据结构
                $new_price = null;
                if (is_array($product_details) || is_object($product_details)) {
                    // 转换为数组以便统一处理
                    $data = json_decode(json_encode($product_details), true);

                    // 检查 ItemResponse 结构（Walmart API 标准格式）
                    if (isset($data['ItemResponse'][0]['price']['amount'])) {
                        $new_price = floatval($data['ItemResponse'][0]['price']['amount']);
                    } elseif (isset($data['ItemResponse'][0]['price'])) {
                        $new_price = floatval($data['ItemResponse'][0]['price']);
                    }
                    // 检查直接的 price 字段
                    elseif (isset($data['price']['amount'])) {
                        $new_price = floatval($data['price']['amount']);
                    } elseif (isset($data['price'])) {
                        $new_price = floatval($data['price']);
                    }
                    // 检查其他可能的价格字段
                    elseif (isset($data['pricing'][0]['currentPrice']['amount'])) {
                        $new_price = floatval($data['pricing'][0]['currentPrice']['amount']);
                    } elseif (isset($data['mart']['price']['amount'])) {
                        $new_price = floatval($data['mart']['price']['amount']);
                    }
                }

                woo_walmart_sync_log('Walmart单个价格获取-价格解析', '调试', [
                    'sku' => $item->sku,
                    'product_details_keys' => is_array($product_details) ? array_keys($product_details) : (is_object($product_details) ? array_keys((array)$product_details) : 'not_array_or_object'),
                    'has_ItemResponse' => isset($data['ItemResponse']),
                    'ItemResponse_count' => isset($data['ItemResponse']) ? count($data['ItemResponse']) : 0,
                    'ItemResponse_price_exists' => isset($data['ItemResponse'][0]['price']),
                    'ItemResponse_price_amount_exists' => isset($data['ItemResponse'][0]['price']['amount']),
                    'ItemResponse_price_raw' => isset($data['ItemResponse'][0]['price']) ? $data['ItemResponse'][0]['price'] : 'not_found',
                    'direct_price_exists' => isset($data['price']),
                    'new_price' => $new_price,
                    'new_price_type' => gettype($new_price)
                ], "价格解析详情");

                if ($new_price !== null && $new_price >= 0) { // 允许价格为0
                    // 更新缓存中的价格
                    $updated = $wpdb->update(
                        $cache_table,
                        [
                            'price' => $new_price,
                            'updated_at' => current_time('mysql'),
                            'last_sync_time' => current_time('mysql')
                        ],
                        ['id' => $item->id],
                        ['%f', '%s', '%s'],
                        ['%d']
                    );

                    if ($updated !== false) {
                        $success_count++;
                        woo_walmart_sync_log('Walmart单个价格获取', '成功', [
                            'sku' => $item->sku,
                            'new_price' => $new_price
                        ], "成功更新商品 {$item->sku} 价格: \${$new_price}");
                    } else {
                        $failed_count++;
                        $errors[] = "商品 {$item->sku}: 数据库更新失败";
                    }
                } else {
                    $failed_count++;
                    $errors[] = "商品 {$item->sku}: 未找到有效价格";
                }

                // 添加延迟避免API频率限制
                usleep(200000); // 0.2秒延迟

            } catch (Exception $e) {
                $failed_count++;
                $errors[] = "商品 {$item->sku}: " . $e->getMessage();
            }
        }

        return [
            'success_count' => $success_count,
            'failed_count' => $failed_count,
            'errors' => $errors
        ];
    }

    /**
     * 获取单个商品价格（回退方法）
     * @param object $item 商品项
     * @return array
     */
    private function fetch_single_price($item) {
        global $wpdb;
        $cache_table = $wpdb->prefix . 'walmart_products_cache';

        try {
            // 使用单个商品API
            $endpoint = '/v3/items/' . urlencode($item->sku);
            $product_details = $this->api_auth->make_request($endpoint, 'GET');

            woo_walmart_sync_log('Walmart单个价格获取-API响应', '调试', [
                'sku' => $item->sku,
                'endpoint' => $endpoint,
                'api_response' => $product_details,
                'is_wp_error' => is_wp_error($product_details),
                'response_keys' => is_array($product_details) ? array_keys($product_details) : 'not_array'
            ], "单个API响应详情");

            if (is_wp_error($product_details)) {
                return ['success' => false, 'error' => 'API错误: ' . $product_details->get_error_message()];
            }

            // 尝试多种价格数据结构
            $new_price = null;
            if (is_array($product_details) || is_object($product_details)) {
                // 转换为数组以便统一处理
                $data = json_decode(json_encode($product_details), true);

                // 检查 ItemResponse 结构（Walmart API 标准格式）
                if (isset($data['ItemResponse'][0]['price']['amount'])) {
                    $new_price = floatval($data['ItemResponse'][0]['price']['amount']);
                } elseif (isset($data['ItemResponse'][0]['price'])) {
                    $new_price = floatval($data['ItemResponse'][0]['price']);
                }
                // 检查直接的 price 字段
                elseif (isset($data['price']['amount'])) {
                    $new_price = floatval($data['price']['amount']);
                } elseif (isset($data['price'])) {
                    $new_price = floatval($data['price']);
                }
                // 检查其他可能的价格字段
                elseif (isset($data['pricing'][0]['currentPrice']['amount'])) {
                    $new_price = floatval($data['pricing'][0]['currentPrice']['amount']);
                } elseif (isset($data['mart']['price']['amount'])) {
                    $new_price = floatval($data['mart']['price']['amount']);
                }
            }

            if ($new_price !== null && $new_price >= 0) { // 允许价格为0
                // 更新缓存中的价格
                $updated = $wpdb->update(
                    $cache_table,
                    [
                        'price' => $new_price,
                        'updated_at' => current_time('mysql'),
                        'last_sync_time' => current_time('mysql')
                    ],
                    ['id' => $item->id],
                    ['%f', '%s', '%s'],
                    ['%d']
                );

                if ($updated !== false) {
                    woo_walmart_sync_log('Walmart单个价格获取', '成功', [
                        'sku' => $item->sku,
                        'new_price' => $new_price,
                        'old_price' => $item->price
                    ], "成功更新商品 {$item->sku} 价格: \${$item->price} -> \${$new_price}");

                    return ['success' => true];
                } else {
                    return ['success' => false, 'error' => '数据库更新失败: ' . $wpdb->last_error];
                }
            } else {
                return ['success' => false, 'error' => '未找到有效价格信息'];
            }

        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * 根据商品ID批量同步库存
     * @param array $product_ids 商品ID数组
     * @return array
     */
    public function bulk_sync_inventory_by_ids($product_ids) {
        try {
            global $wpdb;
            $cache_table = $wpdb->prefix . 'walmart_products_cache';

            woo_walmart_sync_log('批量库存同步-按ID', '开始', [
                'product_ids' => $product_ids,
                'total_count' => count($product_ids)
            ], "开始根据商品ID批量同步库存");

            // 获取Walmart商品信息
            $placeholders = implode(',', array_fill(0, count($product_ids), '%d'));
            $walmart_products = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM {$cache_table} WHERE id IN ({$placeholders})",
                ...$product_ids
            ));

            if (empty($walmart_products)) {
                return [
                    'success' => false,
                    'message' => '未找到对应的Walmart商品数据'
                ];
            }

            // 获取本地商品数据
            require_once WOO_WALMART_SYNC_PATH . 'includes/class-walmart-local-data-manager.php';
            $local_data_manager = new Walmart_Local_Data_Manager();

            $skus = array_column($walmart_products, 'sku');
            $local_data_list = $local_data_manager->get_local_data_by_skus($skus);

            // 创建SKU到本地数据的映射
            $local_data_map = [];
            foreach ($local_data_list as $local_item) {
                $local_data_map[$local_item->sku] = $local_item;
            }

            // 准备批量库存数据
            $inventory_data = [];
            $valid_products = [];

            foreach ($walmart_products as $walmart_product) {
                $local_data = isset($local_data_map[$walmart_product->sku]) ? $local_data_map[$walmart_product->sku] : null;

                if ($local_data) {
                    $inventory_data[] = [
                        'sku' => $walmart_product->sku,
                        'quantity' => intval($local_data->stock_quantity)
                    ];
                    $valid_products[] = $walmart_product;
                }
            }

            if (empty($inventory_data)) {
                return [
                    'success' => false,
                    'message' => '没有找到有效的商品数据进行库存同步'
                ];
            }

            woo_walmart_sync_log('批量库存同步-按ID', '准备数据', [
                'valid_products_count' => count($valid_products),
                'inventory_data' => $inventory_data
            ], "准备批量库存同步数据");

            // 调用批量库存更新API
            $result = $this->api_auth->bulk_update_inventory($inventory_data);

            if (is_wp_error($result)) {
                return [
                    'success' => false,
                    'message' => 'API调用失败: ' . $result->get_error_message()
                ];
            }

            // 处理API响应
            $success_count = 0;
            $failed_count = 0;

            woo_walmart_sync_log('批量库存同步-按ID-API响应', '调试', [
                'api_result' => $result,
                'is_wp_error' => is_wp_error($result),
                'result_type' => gettype($result)
            ], "批量库存API响应详情");

            // Walmart Feed API 通常返回 feedId，表示Feed提交成功
            if (isset($result['feedId']) && !empty($result['feedId'])) {
                // Feed提交成功，所有商品暂时标记为成功
                // 实际处理结果需要后续查询Feed状态
                $success_count = count($valid_products);

                woo_walmart_sync_log('批量库存同步-按ID-Feed提交成功', '成功', [
                    'feed_id' => $result['feedId'],
                    'submitted_products' => count($valid_products)
                ], "库存Feed提交成功，Feed ID: " . $result['feedId']);
            } else {
                // 没有返回feedId，可能是API错误
                $failed_count = count($valid_products);

                woo_walmart_sync_log('批量库存同步-按ID-Feed提交失败', '错误', [
                    'api_result' => $result,
                    'expected_feedId' => 'missing'
                ], "库存Feed提交失败，未返回feedId");
            }

            woo_walmart_sync_log('批量库存同步-按ID', '完成', [
                'success_count' => $success_count,
                'failed_count' => $failed_count,
                'api_result' => $result
            ], "批量库存同步完成");

            return [
                'success' => true,
                'message' => "批量库存同步完成：成功 {$success_count} 个，失败 {$failed_count} 个",
                'success_count' => $success_count,
                'failed_count' => $failed_count
            ];

        } catch (Exception $e) {
            woo_walmart_sync_log('批量库存同步-按ID', '错误', [
                'product_ids' => $product_ids,
                'error' => $e->getMessage()
            ], "批量库存同步异常: " . $e->getMessage());

            return [
                'success' => false,
                'message' => '批量库存同步异常: ' . $e->getMessage()
            ];
        }
    }

    /**
     * 根据商品ID批量同步价格
     * @param array $product_ids 商品ID数组
     * @return array
     */
    public function bulk_sync_price_by_ids($product_ids) {
        try {
            global $wpdb;
            $cache_table = $wpdb->prefix . 'walmart_products_cache';

            woo_walmart_sync_log('批量价格同步-按ID', '开始', [
                'product_ids' => $product_ids,
                'total_count' => count($product_ids)
            ], "开始根据商品ID批量同步价格");

            // 获取Walmart商品信息
            $placeholders = implode(',', array_fill(0, count($product_ids), '%d'));
            $walmart_products = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM {$cache_table} WHERE id IN ({$placeholders})",
                ...$product_ids
            ));

            if (empty($walmart_products)) {
                return [
                    'success' => false,
                    'message' => '未找到对应的Walmart商品数据'
                ];
            }

            // 获取本地商品数据
            require_once WOO_WALMART_SYNC_PATH . 'includes/class-walmart-local-data-manager.php';
            $local_data_manager = new Walmart_Local_Data_Manager();

            $skus = array_column($walmart_products, 'sku');
            $local_data_list = $local_data_manager->get_local_data_by_skus($skus);

            // 创建SKU到本地数据的映射
            $local_data_map = [];
            foreach ($local_data_list as $local_item) {
                $local_data_map[$local_item->sku] = $local_item;
            }

            // 准备批量价格数据
            $price_data = [];
            $valid_products = [];

            foreach ($walmart_products as $walmart_product) {
                $local_data = isset($local_data_map[$walmart_product->sku]) ? $local_data_map[$walmart_product->sku] : null;

                if ($local_data) {
                    // 检查价格差异
                    $price_diff = abs(floatval($walmart_product->price) - floatval($local_data->price));
                    if ($price_diff > 0.01) {
                        $price_data[] = [
                            'sku' => $walmart_product->sku,
                            'price' => round(floatval($local_data->price), 2)
                        ];
                        $valid_products[] = $walmart_product;
                    }
                }
            }

            if (empty($price_data)) {
                return [
                    'success' => true,
                    'message' => '所有商品价格一致，无需同步',
                    'success_count' => 0,
                    'failed_count' => 0
                ];
            }

            woo_walmart_sync_log('批量价格同步-按ID', '准备数据', [
                'valid_products_count' => count($valid_products),
                'price_data' => $price_data
            ], "准备批量价格同步数据");

            // 调用批量价格更新API
            $result = $this->api_auth->bulk_update_price($price_data);

            woo_walmart_sync_log('批量价格同步-按ID-API响应', '调试', [
                'api_result' => $result,
                'is_wp_error' => is_wp_error($result),
                'result_type' => gettype($result)
            ], "批量价格API响应详情");

            if (is_wp_error($result)) {
                woo_walmart_sync_log('批量价格同步-按ID-API错误', '错误', [
                    'error_code' => $result->get_error_code(),
                    'error_message' => $result->get_error_message(),
                    'error_data' => $result->get_error_data()
                ], "批量价格API调用失败");

                return [
                    'success' => false,
                    'message' => 'API调用失败: ' . $result->get_error_message()
                ];
            }

            // 处理API响应
            $success_count = 0;
            $failed_count = 0;

            // Walmart Feed API 通常返回 feedId，表示Feed提交成功
            if (isset($result['feedId']) && !empty($result['feedId'])) {
                // Feed提交成功，所有商品暂时标记为成功
                // 实际处理结果需要后续查询Feed状态
                $success_count = count($valid_products);

                woo_walmart_sync_log('批量价格同步-按ID-Feed提交成功', '成功', [
                    'feed_id' => $result['feedId'],
                    'submitted_products' => count($valid_products)
                ], "价格Feed提交成功，Feed ID: " . $result['feedId']);
            } else {
                // 没有返回feedId，可能是API错误
                $failed_count = count($valid_products);

                woo_walmart_sync_log('批量价格同步-按ID-Feed提交失败', '错误', [
                    'api_result' => $result,
                    'expected_feedId' => 'missing'
                ], "价格Feed提交失败，未返回feedId");
            }

            woo_walmart_sync_log('批量价格同步-按ID', '完成', [
                'success_count' => $success_count,
                'failed_count' => $failed_count,
                'api_result' => $result
            ], "批量价格同步完成");

            return [
                'success' => true,
                'message' => "批量价格同步完成：成功 {$success_count} 个，失败 {$failed_count} 个",
                'success_count' => $success_count,
                'failed_count' => $failed_count
            ];

        } catch (Exception $e) {
            woo_walmart_sync_log('批量价格同步-按ID', '错误', [
                'product_ids' => $product_ids,
                'error' => $e->getMessage()
            ], "批量价格同步异常: " . $e->getMessage());

            return [
                'success' => false,
                'message' => '批量价格同步异常: ' . $e->getMessage()
            ];
        }
    }



    /**
     * 获取Walmart商品库存（批量API）
     * @param array $selected_items 选中的商品
     * @return array
     */
    private function fetch_walmart_inventories($selected_items) {
        global $wpdb;
        $cache_table = $wpdb->prefix . 'walmart_products_cache';

        $success_count = 0;
        $failed_count = 0;
        $errors = [];

        // 使用现有的API认证实例
        if (!$this->api_auth) {
            $this->api_auth = new Woo_Walmart_API_Key_Auth();
        }

        woo_walmart_sync_log('Walmart批量库存获取', '开始', [
            'total_items' => count($selected_items),
            'skus' => array_column($selected_items, 'sku')
        ], "开始批量获取 " . count($selected_items) . " 个商品的Walmart库存");

        try {
            // 使用批量库存API - 分批处理（每批最多50个）
            $batch_size = 50;
            $batches = array_chunk($selected_items, $batch_size);

            foreach ($batches as $batch_index => $batch) {
                try {
                    // 使用批量库存API
                    $inventory_result = $this->api_auth->get_inventories($batch_size);

                    woo_walmart_sync_log('Walmart批量库存获取-API响应', '调试', [
                        'batch_index' => $batch_index,
                        'batch_size' => count($batch),
                        'api_response' => $inventory_result,
                        'is_wp_error' => is_wp_error($inventory_result)
                    ], "批量库存API响应详情");

                    if (is_wp_error($inventory_result)) {
                        // 如果批量API失败，回退到单个API调用
                        foreach ($batch as $item) {
                            $individual_result = $this->fetch_single_inventory($item);
                            if ($individual_result['success']) {
                                $success_count++;
                            } else {
                                $failed_count++;
                                $errors[] = "商品 {$item->sku}: " . $individual_result['error'];
                            }
                        }
                        continue;
                    }

                    // 处理批量响应
                    $inventories_data = [];
                    if (isset($inventory_result['elements']['inventories'])) {
                        $inventories_data = $inventory_result['elements']['inventories'];
                    }

                    // 创建SKU到库存数据的映射
                    $sku_to_inventory = [];
                    foreach ($inventories_data as $inventory_data) {
                        if (isset($inventory_data['sku'])) {
                            $sku_to_inventory[$inventory_data['sku']] = $inventory_data;
                        }
                    }

                    // 处理每个选中的商品
                    foreach ($batch as $item) {
                        try {
                            if (isset($sku_to_inventory[$item->sku])) {
                                $inventory_data = $sku_to_inventory[$item->sku];

                                $new_inventory = null;
                                if (isset($inventory_data['quantity']['amount'])) {
                                    $new_inventory = intval($inventory_data['quantity']['amount']);
                                } elseif (isset($inventory_data['quantity'])) {
                                    $new_inventory = intval($inventory_data['quantity']);
                                }

                                if ($new_inventory !== null) {
                                    // 更新缓存中的库存
                                    $updated = $wpdb->update(
                                        $cache_table,
                                        [
                                            'inventory_count' => $new_inventory,
                                            'updated_at' => current_time('mysql'),
                                            'last_sync_time' => current_time('mysql')
                                        ],
                                        ['id' => $item->id],
                                        ['%d', '%s', '%s'],
                                        ['%d']
                                    );

                                    if ($updated !== false) {
                                        $success_count++;
                                        woo_walmart_sync_log('Walmart批量库存获取', '成功', [
                                            'sku' => $item->sku,
                                            'old_inventory' => $item->inventory_count,
                                            'new_inventory' => $new_inventory,
                                            'inventory_change' => $new_inventory - intval($item->inventory_count)
                                        ], "成功更新商品 {$item->sku} 库存: {$item->inventory_count} -> {$new_inventory}");
                                    } else {
                                        $failed_count++;
                                        $error_msg = "数据库更新失败: " . $wpdb->last_error;
                                        $errors[] = "商品 {$item->sku}: {$error_msg}";
                                    }
                                } else {
                                    $failed_count++;
                                    $error_msg = "响应中未找到库存信息";
                                    $errors[] = "商品 {$item->sku}: {$error_msg}";
                                }
                            } else {
                                // 如果批量响应中没有该SKU，尝试单个API调用
                                $individual_result = $this->fetch_single_inventory($item);
                                if ($individual_result['success']) {
                                    $success_count++;
                                } else {
                                    $failed_count++;
                                    $errors[] = "商品 {$item->sku}: " . $individual_result['error'];
                                }
                            }
                        } catch (Exception $e) {
                            $failed_count++;
                            $errors[] = "商品 {$item->sku}: 处理异常 - " . $e->getMessage();
                        }
                    }

                } catch (Exception $e) {
                    // 批次处理失败，回退到单个API调用
                    foreach ($batch as $item) {
                        $individual_result = $this->fetch_single_inventory($item);
                        if ($individual_result['success']) {
                            $success_count++;
                        } else {
                            $failed_count++;
                            $errors[] = "商品 {$item->sku}: " . $individual_result['error'];
                        }
                    }
                }
            }

        } catch (Exception $e) {
            woo_walmart_sync_log('Walmart批量库存获取', '批量处理异常', [
                'exception' => $e->getMessage()
            ], "批量库存获取异常: " . $e->getMessage());

            // 如果批量处理完全失败，回退到单个API调用
            foreach ($selected_items as $item) {
                $individual_result = $this->fetch_single_inventory($item);
                if ($individual_result['success']) {
                    $success_count++;
                } else {
                    $failed_count++;
                    $errors[] = "商品 {$item->sku}: " . $individual_result['error'];
                }
            }
        }

        woo_walmart_sync_log('Walmart批量库存获取', '完成', [
            'total_items' => count($selected_items),
            'success_count' => $success_count,
            'failed_count' => $failed_count
        ], "批量库存获取完成: 成功 {$success_count} 个，失败 {$failed_count} 个");

        return [
            'success_count' => $success_count,
            'failed_count' => $failed_count,
            'errors' => $errors
        ];
    }



    /**
     * 获取单个商品库存（回退方法）
     * @param object $item 商品项
     * @return array
     */
    private function fetch_single_inventory($item) {
        global $wpdb;
        $cache_table = $wpdb->prefix . 'walmart_products_cache';

        try {
            $inventory_info = $this->api_auth->get_inventory($item->sku);

            if (!is_wp_error($inventory_info) && isset($inventory_info['quantity']['amount'])) {
                $new_inventory = intval($inventory_info['quantity']['amount']);

                $updated = $wpdb->update(
                    $cache_table,
                    [
                        'inventory_count' => $new_inventory,
                        'updated_at' => current_time('mysql'),
                        'last_sync_time' => current_time('mysql')
                    ],
                    ['id' => $item->id],
                    ['%d', '%s', '%s'],
                    ['%d']
                );

                if ($updated !== false) {
                    return ['success' => true];
                } else {
                    return ['success' => false, 'error' => '数据库更新失败'];
                }
            } else {
                $error_msg = is_wp_error($inventory_info) ? $inventory_info->get_error_message() : '无法获取库存信息';
                return ['success' => false, 'error' => $error_msg];
            }
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }


}
