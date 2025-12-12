<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class Woo_Walmart_Product_Sync {

    // 主入口，由AJAX调用
    public function ajax_sync_product() {
        // 添加调试日志
        woo_walmart_sync_log('AJAX同步-开始', '调试', $_POST, '');

        // 验证nonce
        if (!check_ajax_referer('walmart_sync_nonce', 'nonce', false)) {
            woo_walmart_sync_log('AJAX同步-nonce验证失败', '错误', $_POST, '');
            wp_send_json_error(['message' => 'Nonce验证失败，请刷新页面重试']);
        }

        $product_id = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;
        if (!$product_id) {
            woo_walmart_sync_log('AJAX同步-产品ID无效', '错误', $_POST, '');
            wp_send_json_error(['message' => '无效的产品ID']);
        }

        woo_walmart_sync_log('AJAX同步-开始处理', '调试', ['product_id' => $product_id], '');

        $result = $this->initiate_sync($product_id);

        woo_walmart_sync_log('AJAX同步-处理完成', '调试', ['product_id' => $product_id, 'result' => $result], '');

        if ($result['success']) {
            wp_send_json_success(['message' => $result['message']]);
        } else {
            wp_send_json_error(['message' => $result['message']]);
        }
    }

    public function initiate_sync( $product_id ) {
        // 增加执行时间限制，防止超时
        @set_time_limit(300); // 5分钟
        @ini_set('max_execution_time', 300);

        $product = wc_get_product( $product_id );
        if ( ! $product ) {
            return [ 'success' => false, 'message' => '未找到商品' ];
        }

        // ---- 增强的前置校验 ----
        $validation_errors = [];

        // 检查SKU
        if ( ! $product->get_sku() ) {
            $validation_errors[] = '产品缺少SKU';
        }

        // 检查价格
        if ( $product->get_price() === '' || $product->get_price() <= 0 ) {
            $validation_errors[] = '产品缺少有效价格';
        }

        // 检查产品名称
        if ( ! $product->get_name() ) {
            $validation_errors[] = '产品缺少名称';
        }

        // 检查产品状态
        if ( $product->get_status() !== 'publish' ) {
            $validation_errors[] = '产品未发布，状态为: ' . $product->get_status();
        }

        // 变量商品检查
        if ( $product->is_type('variable') ) {
            $validation_errors[] = '暂不支持变量商品';
        }

        // V5.0 特定验证 (统一使用5.0版本)
        $v5_errors = $this->validate_for_v5($product);
        $validation_errors = array_merge($validation_errors, $v5_errors);

        // 如果有验证错误，返回错误信息
        if ( ! empty( $validation_errors ) ) {
            return [
                'success' => false,
                'message' => '同步失败：' . implode('；', $validation_errors) . '。'
            ];
        }
        // ---- 前置校验结束 ----

        // 1. 检查并获取分类映射
        $product_cat_ids = $product->get_category_ids();
        if (empty($product_cat_ids)) {
            return ['success' => false, 'message' => '产品未分配任何WooCommerce分类'];
        }
        
        global $wpdb;
        $map_table = $wpdb->prefix . 'walmart_category_map';
        
        // ---- 这是本次修改的部分 (2/3): 获取映射规则 ----
        // 遍历所有分类，找到第一个有映射的分类
        $mapped_category_data = null;

        foreach ($product_cat_ids as $cat_id) {
            // 首先尝试直接查询
            $mapped_category_data = $wpdb->get_row($wpdb->prepare(
                "SELECT walmart_category_path, wc_category_name, walmart_attributes FROM $map_table WHERE wc_category_id = %d",
                $cat_id
            ));

            // 如果找到映射，跳出循环
            if ($mapped_category_data) {
                woo_walmart_sync_log('分类映射查找', '成功', [
                    'product_categories' => $product_cat_ids,
                    'matched_category_id' => $cat_id,
                    'walmart_category' => $mapped_category_data->walmart_category_path
                ], "在分类ID {$cat_id} 中找到映射", $product_id);
                break;
            }
        }
        
        if (!$mapped_category_data || empty($mapped_category_data->walmart_category_path)) {
            return ['success' => false, 'message' => '产品分类尚未映射到沃尔玛分类，请先在"分类映射"页面设置。'];
        }
        
        // 从映射数据中提取信息
        $walmart_category_id = $mapped_category_data->walmart_category_path;
        $walmart_category_name = ''; // 我们需要从分类列表中找到分类名称

        // 🔧 根据市场选择不同的分类名称获取方式
        $business_unit_temp = get_option('woo_walmart_business_unit', 'WALMART_US');
        $market_code_temp = str_replace('WALMART_', '', $business_unit_temp);

        if ($market_code_temp === 'CA') {
            // 🇨🇦 加拿大市场：从 CA_MP_ITEM_INTL_SPEC.json 中查找分类名称
            $spec_file = plugin_dir_path(dirname(__FILE__)) . 'api/CA_MP_ITEM_INTL_SPEC.json';

            if (file_exists($spec_file)) {
                $spec = json_decode(file_get_contents($spec_file), true);

                if ($spec && isset($spec['definitions'])) {
                    // 遍历definitions寻找匹配的分类
                    foreach ($spec['definitions'] as $def_name => $definition) {
                        if (isset($definition['properties']['Visible']['properties'])) {
                            $visible_props = $definition['properties']['Visible']['properties'];

                            // 1. 尝试直接匹配分类ID作为分类名称
                            if (isset($visible_props[$walmart_category_id])) {
                                $walmart_category_name = $walmart_category_id;
                                break;
                            }

                            // 2. 如果ID是 CA_XXXX 格式，尝试查找 XXXX 或首字母大写格式
                            if (strpos($walmart_category_id, 'CA_') === 0) {
                                $clean_name = str_replace('CA_', '', $walmart_category_id);

                                // 尝试完全大写 (FURNITURE)
                                if (isset($visible_props[$clean_name])) {
                                    $walmart_category_name = $clean_name;
                                    break;
                                }

                                // 尝试首字母大写 (Furniture)
                                $ucfirst_name = ucfirst(strtolower($clean_name));
                                if (isset($visible_props[$ucfirst_name])) {
                                    $walmart_category_name = $ucfirst_name;
                                    break;
                                }
                            }
                        }
                    }
                }

                if (!empty($walmart_category_name)) {
                    woo_walmart_sync_log('CA分类名称查找', '成功', [
                        'category_id' => $walmart_category_id,
                        'category_name' => $walmart_category_name
                    ], "从CA Spec中找到分类名称", $product_id);
                }
            }
        } else {
            // 🇺🇸 美国市场：从缓存的沃尔玛分类列表中找到名称
            $walmart_categories_list = get_transient('walmart_api_categories');
            if (!empty($walmart_categories_list)) {
                foreach($walmart_categories_list as $cat) {
                    if ($cat['categoryId'] === $walmart_category_id) {
                        $walmart_category_name = $cat['categoryName'];
                        break;
                    }
                }
            }
        }

        if (empty($walmart_category_name)) {
            // 如果没找到，使用ID作为后备
            $walmart_category_name = $walmart_category_id;
            woo_walmart_sync_log('分类名称后备', '警告', [
                'category_id' => $walmart_category_id,
                'market' => $market_code_temp,
                'used_fallback' => true
            ], "未找到匹配的分类名称，使用分类ID作为后备", $product_id);
        }

        // 解码属性映射规则
        $attribute_rules = !empty($mapped_category_data->walmart_attributes) ? json_decode($mapped_category_data->walmart_attributes, true) : null;
        if ( ! is_array( $attribute_rules ) || !isset($attribute_rules['name']) ) {
            $attribute_rules = ['name' => [], 'type' => [], 'source' => []]; // 提供默认空数组
        }
        // ---- 获取映射规则结束 ----


        // 2. 检查并分配UPC
        $upc = get_post_meta($product_id, '_walmart_upc', true);
        if (empty($upc)) {
            $upc = $this->assign_upc_from_pool($product_id);
            if (is_wp_error($upc)) {
                return ['success' => false, 'message' => $upc->get_error_message()];
            }
        } else {
            // 如果产品已有UPC，确保UPC池中的状态是正确的
            $this->sync_upc_status($upc, $product_id);
        }
        
        // 3. 数据映射
        $mapper = new Woo_Walmart_Product_Mapper();

        // ---- 这是本次修改的部分 (3/3): 调用新的 map 方法 ----
        // 暂时硬编码备货时间，之后我们会把它做到设置页面
        $fulfillment_lag_time = get_option('woo_walmart_fulfillment_lag_time', 1); // 修复：API只允许[0,1]，默认值改为1
        // 确保值在API允许的范围内[0,1]
        $fulfillment_lag_time = max(0, min(1, (int)$fulfillment_lag_time));

        // 🆕 获取市场代码，用于多语言字段转换
        $business_unit = get_option('woo_walmart_business_unit', 'WALMART_US');
        $market_code = str_replace('WALMART_', '', $business_unit); // WALMART_CA -> CA

        // 增强日志：记录映射前的输入参数
        woo_walmart_sync_log('产品映射-输入参数', '调试', [
            'product_id' => $product_id,
            'product_name' => $product->get_name(),
            'product_sku' => $product->get_sku(),
            'walmart_category_name' => $walmart_category_name,
            'upc' => $upc,
            'fulfillment_lag_time' => $fulfillment_lag_time,
            'business_unit' => $business_unit,
            'market_code' => $market_code,
            'attribute_rules_count' => is_array($attribute_rules) ? count($attribute_rules) : 0,
            'attribute_rules_keys' => is_array($attribute_rules) ? array_keys($attribute_rules) : []
        ], "开始产品映射，输入参数详情", $product_id);

        $walmart_data = $mapper->map( $product, $walmart_category_name, $upc, $attribute_rules, $fulfillment_lag_time, $market_code );

        // 增强日志：记录映射后的完整数据结构
        woo_walmart_sync_log('产品映射-完整输出', '调试', $walmart_data, "产品映射完成，完整数据结构", $product_id);

        // 增强日志：分析关键字段
        $header = $walmart_data['MPItemFeedHeader'] ?? [];
        $orderable = $walmart_data['MPItem'][0]['Orderable'] ?? [];
        $visible = $walmart_data['MPItem'][0]['Visible'] ?? [];
        $visible_category = reset($visible) ?? []; // 获取第一个分类的数据

        woo_walmart_sync_log('产品映射-关键字段分析', '调试', [
            'header_fields' => array_keys($header),
            'orderable_fields' => array_keys($orderable),
            'visible_category_name' => key($visible),
            'visible_fields' => array_keys($visible_category),
            'businessUnit' => $header['businessUnit'] ?? '缺失',
            'externalProductIdentifier' => $orderable['externalProductIdentifier'] ?? '缺失',
            'stateRestrictions' => $orderable['stateRestrictions'] ?? '缺失',
            'assembledProductHeight' => $visible_category['assembledProductHeight'] ?? '缺失',
            'material' => $visible_category['material'] ?? '缺失'
        ], "关键字段分析", $product_id);
        // ---- 调用结束 ----

        // 4. 调用沃尔玛API（带重试机制）
        $api_auth = new Woo_Walmart_API_Key_Auth();
        // 根据官方文档，使用正确的 Bulk Item Setup API
        // feedType=MP_ITEM 用于商品规格版本 5.0.20250121-19_24_23

        $max_retries = 3;
        $retry_count = 0;
        $response = null;

        while ($retry_count < $max_retries) {
            // 🔧 修复：根据当前市场动态选择feedType
            $business_unit = get_option('woo_walmart_business_unit', 'WALMART_US');
            $market_code = str_replace('WALMART_', '', $business_unit); // WALMART_CA -> CA

            // 使用多市场配置获取正确的feedType
            require_once plugin_dir_path(__FILE__) . 'class-multi-market-config.php';
            $feed_type = Woo_Walmart_Multi_Market_Config::get_market_feed_type($market_code, 'item');

            $response = $api_auth->make_file_upload_request("/v3/feeds?feedType={$feed_type}", $walmart_data, 'item_feed.json');

            // 如果成功或者不是超时错误，跳出重试循环
            if (!is_wp_error($response)) {
                break;
            }

            $error_message = $response->get_error_message();

            // 检查是否是超时错误
            if (strpos($error_message, 'timeout') !== false ||
                strpos($error_message, '504') !== false ||
                strpos($error_message, 'Gateway Timeout') !== false) {

                $retry_count++;
                if ($retry_count < $max_retries) {
                    woo_walmart_sync_log('同步商品-重试', '警告', [
                        'retry_count' => $retry_count,
                        'max_retries' => $max_retries,
                        'error' => $error_message
                    ], "检测到超时错误，进行第{$retry_count}次重试", $product_id);

                    // 等待一段时间再重试
                    sleep(5 * $retry_count); // 递增等待时间：5秒、10秒、15秒
                    continue;
                }
            }

            // 非超时错误或重试次数用完，跳出循环
            break;
        }

        // 处理API错误
        if (is_wp_error($response)) {
            $error_message = $response->get_error_message();
            woo_walmart_sync_log('同步商品-失败', 'WP_Error', [
                'retry_count' => $retry_count,
                'final_error' => $error_message
            ], "经过{$retry_count}次重试后仍然失败: {$error_message}", $product_id);
            return [ 'success' => false, 'message' => '网络错误: ' . $error_message . " (重试{$retry_count}次)" ];
        }

        // 检查API响应
        if (is_array($response) && !empty($response['feedId'])) {
            $this->record_feed_status($response['feedId'], $product_id, 'SUBMITTED', $response);
            woo_walmart_sync_log('同步商品-提交', '成功', $walmart_data, $response, $product_id);

            // 延迟触发库存同步钩子 - 给商品一些时间在Walmart系统中生效
            $walmart_sku = $product->get_sku();

            // 记录延迟库存同步任务
            woo_walmart_sync_log('延迟库存同步', '计划', [
                'product_id' => $product_id,
                'walmart_sku' => $walmart_sku,
                'feed_id' => $response['feedId'],
                'delay_minutes' => 5
            ], "商品同步成功，计划5分钟后进行库存同步，等待商品在Walmart系统中生效");

            // 使用WordPress的定时任务系统，5分钟后触发库存同步
            wp_schedule_single_event(
                time() + (5 * 60), // 5分钟后
                'walmart_delayed_inventory_sync',
                [$product_id, $walmart_sku, $response['feedId']]
            );

            return [ 'success' => true, 'message' => '同步请求已提交，Feed ID: ' . $response['feedId'] ];
        }

        // 处理API错误响应 (统一使用V5.0)
        $error_message = '同步失败';

        if (is_array($response)) {
            // V5.0 特定错误处理
            if (isset($response['errors'])) {
                $v5_errors = $this->parse_v5_errors($response['errors']);
                $error_message .= ': ' . implode('; ', $v5_errors);
            }
            // 通用错误处理
            elseif (isset($response['error'])) {
                $error_message .= ': ' . $response['error'];
                if (isset($response['error_description'])) {
                    $error_message .= ' - ' . $response['error_description'];
                }
            } elseif (isset($response['errors']) && is_array($response['errors'])) {
                $errors = array_map(function($error) {
                    return is_array($error) ? ($error['message'] ?? $error['description'] ?? '未知错误') : $error;
                }, $response['errors']);
                $error_message .= ': ' . implode('; ', $errors);
            } else {
                $error_message .= ': ' . wp_json_encode($response, JSON_UNESCAPED_UNICODE);
            }
        } else {
            $error_message .= ': 无效的API响应';
        }

        woo_walmart_sync_log('同步商品-失败', '失败', $walmart_data, $response, $product_id);
        return [ 'success' => false, 'message' => $error_message ];
    }

    /**
     * 解析V5.0 API错误信息
     * @param array $errors V5.0错误数组
     * @return array 格式化的错误信息数组
     */
    private function parse_v5_errors($errors) {
        $parsed_errors = [];

        if (!is_array($errors)) {
            return ['V5.0 API错误格式无效'];
        }

        foreach ($errors as $error) {
            if (is_array($error)) {
                // V5.0 常见错误结构
                if (isset($error['code']) && isset($error['message'])) {
                    $error_text = "[{$error['code']}] {$error['message']}";

                    // 添加字段信息（如果有）
                    if (isset($error['field'])) {
                        $error_text .= " (字段: {$error['field']})";
                    }

                    // 添加详细信息（如果有）
                    if (isset($error['details'])) {
                        $error_text .= " - {$error['details']}";
                    }

                    $parsed_errors[] = $error_text;
                } elseif (isset($error['message'])) {
                    $parsed_errors[] = $error['message'];
                } elseif (isset($error['description'])) {
                    $parsed_errors[] = $error['description'];
                } else {
                    $parsed_errors[] = wp_json_encode($error, JSON_UNESCAPED_UNICODE);
                }
            } else {
                $parsed_errors[] = (string) $error;
            }
        }

        return empty($parsed_errors) ? ['未知的V5.0 API错误'] : $parsed_errors;
    }

    /**
     * V5.0 特定验证
     * @param WC_Product $product 产品对象
     * @return array 验证错误数组
     */
    private function validate_for_v5($product) {
        $errors = [];

        // 检查产品名称长度 (V5.0最多199字符)
        $product_name = $product->get_name();
        if (strlen($product_name) > 199) {
            $errors[] = "产品名称过长（{strlen($product_name)}字符），V5.0最多支持199字符";
        }

        // 检查品牌长度 (V5.0最多60字符)
        $brand = $product->get_attribute('brand') ?:
                $product->get_attribute('Brand') ?:
                $product->get_attribute('品牌') ?:
                $product->get_attribute('pa_brand');

        if ($brand && strlen($brand) > 60) {
            $errors[] = "品牌名称过长（{strlen($brand)}字符），V5.0最多支持60字符";
        }

        // 检查描述长度 (V5.0最多100000字符)
        $description = $product->get_description();
        if ($description && strlen($description) > 100000) {
            $errors[] = "产品描述过长（{strlen($description)}字符），V5.0最多支持100000字符";
        }

        // 检查简短描述长度
        $short_description = $product->get_short_description();
        if ($short_description && strlen($short_description) > 100000) {
            $errors[] = "简短描述过长（{strlen($short_description)}字符），V5.0最多支持100000字符";
        }

        // 检查业务单元配置
        $business_unit = get_option('woo_walmart_business_unit', 'WALMART_US');
        if (!in_array($business_unit, ['WALMART_US', 'WALMART_CA', 'WALMART_MX', 'WALMART_CL'])) {
            $errors[] = "无效的业务单元配置：{$business_unit}";
        }

        return $errors;
    }

    /**
     * 标准化Feed状态 (统一使用V5.0)
     * @param string $status API返回的状态
     * @return string 标准化的状态
     */
    private function normalize_feed_status($status) {
        // V5.0 状态映射
        $v5_status_map = [
            'RECEIVED' => 'SUBMITTED',
            'INPROGRESS' => 'PROCESSING',
            'PROCESSED' => 'PROCESSED',
            'ERROR' => 'ERROR',
            'CANCELLED' => 'ERROR',
            'TIMEOUT' => 'ERROR'
        ];

        return $v5_status_map[$status] ?? $status;
    }

    /**
     * 使用分页获取Feed详情（处理超过50个商品的Feed）
     *
     * @param string $feed_id Feed ID
     * @return array|WP_Error API响应结果或错误对象
     */
    private function get_feed_details_with_pagination($feed_id) {
        $api_auth = new Woo_Walmart_API_Key_Auth();
        $all_items = [];
        $offset = 0;
        $limit = 50; // Walmart API 最大限制
        $items_received = 0;

        // 第一次调用获取总数和第一页数据
        $endpoint = "/v3/feeds/{$feed_id}?includeDetails=true&limit={$limit}&offset={$offset}";
        $result = $api_auth->make_request($endpoint);

        // 检查错误
        if (is_wp_error($result)) {
            return $result;
        }

        if (empty($result)) {
            return new WP_Error('empty_response', 'API返回空响应');
        }

        // 获取总商品数
        $items_received = isset($result['itemsReceived']) ? intval($result['itemsReceived']) : 0;

        // 收集第一页数据
        if (isset($result['itemDetails']['itemIngestionStatus']) && is_array($result['itemDetails']['itemIngestionStatus'])) {
            $all_items = $result['itemDetails']['itemIngestionStatus'];
        }

        // 如果商品数超过50，继续分页获取剩余数据
        while (count($all_items) < $items_received && $offset + $limit < $items_received) {
            $offset += $limit;
            $endpoint = "/v3/feeds/{$feed_id}?includeDetails=true&limit={$limit}&offset={$offset}";
            $page_result = $api_auth->make_request($endpoint);

            // 检查分页请求错误
            if (is_wp_error($page_result) || empty($page_result)) {
                // 记录警告但继续处理已获取的数据
                woo_walmart_sync_log(
                    'Feed分页获取警告',
                    "获取Feed {$feed_id} 的第 " . ($offset / $limit + 1) . " 页时出错",
                    ['offset' => $offset, 'limit' => $limit],
                    ''
                );
                break;
            }

            // 合并分页数据
            if (isset($page_result['itemDetails']['itemIngestionStatus']) && is_array($page_result['itemDetails']['itemIngestionStatus'])) {
                $all_items = array_merge($all_items, $page_result['itemDetails']['itemIngestionStatus']);
            }
        }

        // 更新结果中的 itemDetails
        if (!empty($all_items)) {
            $result['itemDetails']['itemIngestionStatus'] = $all_items;
        }

        // 记录分页信息
        woo_walmart_sync_log(
            'Feed分页获取完成',
            "Feed {$feed_id}: 总商品数 {$items_received}，实际获取 " . count($all_items) . " 个",
            ['feed_id' => $feed_id, 'items_received' => $items_received, 'items_fetched' => count($all_items)],
            ''
        );

        return $result;
    }

    // 将Feed状态记录到数据库
    private function record_feed_status($feed_id, $product_id, $status, $api_response = '') {
        global $wpdb;
        $feeds_table = $wpdb->prefix . 'walmart_feeds';

        // 获取商品信息
        $product = wc_get_product($product_id);
        $sku = $product ? $product->get_sku() : '';
        $upc = get_post_meta($product_id, '_walmart_upc', true);

        $wpdb->insert(
            $feeds_table,
            [
                'feed_id'      => $feed_id,
                'product_id'   => $product_id,
                'sku'          => $sku,
                'upc'          => $upc,
                'status'       => $status,
                'submitted_at' => current_time('mysql'),
                'created_at'   => current_time('mysql'),
                'updated_at'   => current_time('mysql'),
                'api_response' => is_string($api_response) ? $api_response : wp_json_encode($api_response)
            ]
        );
    }
    
    // 定时任务：检查Feed状态
    public function check_feed_statuses() {
        global $wpdb;
        $feeds_table = $wpdb->prefix . 'walmart_feeds';
        $batch_feeds_table = $wpdb->prefix . 'walmart_batch_feeds';

        // 1. 检查单个Feed状态
        $pending_feeds = $wpdb->get_results("SELECT feed_id, product_id FROM $feeds_table WHERE status = 'SUBMITTED' OR status = 'INPROGRESS'");

        if (!empty($pending_feeds)) {
            foreach ($pending_feeds as $feed) {
                // 使用分页方法获取Feed详情（符合Walmart API规范：limit最大50）
                $result = $this->get_feed_details_with_pagination($feed->feed_id);

                if (!is_wp_error($result) && !empty($result['feedStatus'])) {
                    $new_status = $this->normalize_feed_status($result['feedStatus']);

                    // 准备更新数据
                    $update_data = [
                        'status' => $new_status,
                        'processed_at' => current_time('mysql'),
                        'api_response' => wp_json_encode($result)
                    ];

                    // 如果Feed处理完成，尝试提取WPID
                    if ($new_status === 'PROCESSED' && isset($result['itemDetails']['itemIngestionStatus'])) {
                        foreach ($result['itemDetails']['itemIngestionStatus'] as $item_detail) {
                            if (isset($item_detail['wpid']) && !empty($item_detail['wpid'])) {
                                $update_data['wpid'] = $item_detail['wpid'];

                                woo_walmart_sync_log('单个Feed WPID提取', '成功', [
                                    'feed_id' => $feed->feed_id,
                                    'product_id' => $feed->product_id,
                                    'wpid' => $item_detail['wpid']
                                ], "单个商品Feed的WPID已提取: {$item_detail['wpid']}");
                                break; // 只取第一个WPID
                            }
                        }
                    }

                    $wpdb->update(
                        $feeds_table,
                        $update_data,
                        ['feed_id' => $feed->feed_id]
                    );
                }
            }
        }

        // 2. 检查批量Feed状态
        $this->check_batch_feed_statuses();
    }

    // 检查批量Feed状态
    public function check_batch_feed_statuses() {
        global $wpdb;
        $batch_feeds_table = $wpdb->prefix . 'walmart_batch_feeds';
        $batch_items_table = $wpdb->prefix . 'walmart_batch_items';

        // 找出所有还在处理中的批量Feeds
        $pending_batch_feeds = $wpdb->get_results(
            "SELECT batch_id, feed_id, product_count, batch_type, parent_batch_id
             FROM $batch_feeds_table
             WHERE status IN ('SUBMITTED', 'PROCESSING')
             AND feed_id IS NOT NULL
             AND feed_id != ''"
        );

        if (empty($pending_batch_feeds)) {
            return;
        }

        foreach ($pending_batch_feeds as $batch_feed) {
            // 使用分页方法获取Feed详情（符合Walmart API规范：limit最大50）
            $result = $this->get_feed_details_with_pagination($batch_feed->feed_id);

            if (!is_wp_error($result) && !empty($result['feedStatus'])) {
                $feed_status = $result['feedStatus'];
                $this->update_batch_feed_status($batch_feed, $feed_status, $result);
            }
        }
    }

    // 检查单个批次Feed状态
    public function check_single_batch_feed_status($batch_id) {
        global $wpdb;
        $batch_feeds_table = $wpdb->prefix . 'walmart_batch_feeds';

        // 获取指定批次信息（不要求必须有feed_id）
        $batch_feed = $wpdb->get_row($wpdb->prepare(
            "SELECT batch_id, feed_id, product_count, batch_type, parent_batch_id, status
             FROM $batch_feeds_table
             WHERE batch_id = %s",
            $batch_id
        ));

        if (!$batch_feed) {
            return [
                'success' => false,
                'message' => '批次不存在'
            ];
        }

        // 如果是主批次（master），刷新所有子批次状态
        if ($batch_feed->batch_type === 'master') {
            return $this->refresh_master_batch_status($batch_id);
        }

        // 如果是子批次或单个批次，但没有feed_id，无法刷新
        if (empty($batch_feed->feed_id)) {
            return [
                'success' => false,
                'message' => '批次没有关联的Feed ID，无法刷新状态'
            ];
        }

        // 如果批次已经完成，不需要再检查
        if (in_array($batch_feed->status, ['COMPLETED', 'ERROR'])) {
            return [
                'success' => true,
                'status' => $batch_feed->status,
                'message' => '批次已完成，状态：' . $batch_feed->status
            ];
        }

        // 使用分页方法获取Feed详情（符合Walmart API规范：limit最大50）
        $result = $this->get_feed_details_with_pagination($batch_feed->feed_id);

        if (is_wp_error($result)) {
            return [
                'success' => false,
                'message' => 'API请求失败：' . $result->get_error_message()
            ];
        }

        if (empty($result['feedStatus'])) {
            return [
                'success' => false,
                'message' => 'API返回数据格式错误'
            ];
        }

        $feed_status = $result['feedStatus'];
        $this->update_batch_feed_status($batch_feed, $feed_status, $result);

        // 确定最终状态 - 与update_batch_feed_status保持一致
        $final_status = 'PROCESSING';
        if ($feed_status === 'PROCESSED') {
            $final_status = 'COMPLETED';
        } elseif ($feed_status === 'ERROR') {
            // 检查是否有成功的商品来判断是部分成功还是完全失败
            $success_count = 0;
            if (isset($result['itemsSucceeded'])) {
                $success_count = intval($result['itemsSucceeded']);
            } elseif (isset($result['itemDetails']['itemIngestionStatus'])) {
                foreach ($result['itemDetails']['itemIngestionStatus'] as $item_detail) {
                    if (isset($item_detail['ingestionStatus']) && $item_detail['ingestionStatus'] === 'SUCCESS') {
                        $success_count++;
                    }
                }
            }

            $final_status = $success_count > 0 ? 'COMPLETED' : 'ERROR';
        }

        woo_walmart_sync_log('单个批次状态刷新', '成功', [
            'batch_id' => $batch_id,
            'feed_id' => $batch_feed->feed_id,
            'old_status' => $batch_feed->status,
            'new_status' => $final_status,
            'feed_status' => $feed_status
        ], "单个批次状态已刷新: {$final_status}");

        return [
            'success' => true,
            'status' => $final_status,
            'message' => '批次状态已刷新'
        ];
    }

    // 刷新主批次状态（通过刷新所有子批次）
    private function refresh_master_batch_status($master_batch_id) {
        global $wpdb;
        $batch_feeds_table = $wpdb->prefix . 'walmart_batch_feeds';

        // 获取所有子批次
        $sub_batches = $wpdb->get_results($wpdb->prepare(
            "SELECT batch_id, feed_id, status FROM $batch_feeds_table
             WHERE parent_batch_id = %s
             AND batch_type = 'chunk'
             AND feed_id IS NOT NULL
             AND feed_id != ''",
            $master_batch_id
        ));

        if (empty($sub_batches)) {
            return [
                'success' => false,
                'message' => '主批次没有有效的子批次'
            ];
        }

        $refreshed_count = 0;

        // 刷新每个子批次的状态
        foreach ($sub_batches as $sub_batch) {
            // 跳过已完成的子批次
            if (in_array($sub_batch->status, ['COMPLETED', 'ERROR'])) {
                continue;
            }

            // 使用分页方法获取Feed详情（符合Walmart API规范：limit最大50）
            $result = $this->get_feed_details_with_pagination($sub_batch->feed_id);

            if (!is_wp_error($result) && !empty($result['feedStatus'])) {
                // 创建临时批次对象用于更新
                $temp_batch = (object)[
                    'batch_id' => $sub_batch->batch_id,
                    'feed_id' => $sub_batch->feed_id,
                    'batch_type' => 'chunk',
                    'parent_batch_id' => $master_batch_id
                ];

                $this->update_batch_feed_status($temp_batch, $result['feedStatus'], $result);
                $refreshed_count++;
            }
        }

        // 获取更新后的主批次状态
        $master_batch = $wpdb->get_row($wpdb->prepare(
            "SELECT status FROM $batch_feeds_table WHERE batch_id = %s",
            $master_batch_id
        ));

        woo_walmart_sync_log('主批次状态刷新', '成功', [
            'master_batch_id' => $master_batch_id,
            'sub_batches_count' => count($sub_batches),
            'refreshed_count' => $refreshed_count,
            'final_status' => $master_batch ? $master_batch->status : 'UNKNOWN'
        ], "主批次状态刷新完成，刷新了 {$refreshed_count} 个子批次");

        return [
            'success' => true,
            'status' => $master_batch ? $master_batch->status : 'PROCESSING',
            'message' => "主批次状态已刷新，更新了 {$refreshed_count} 个子批次"
        ];
    }

    // 更新批量Feed状态
    private function update_batch_feed_status($batch_feed, $feed_status, $api_result) {
        global $wpdb;
        $batch_feeds_table = $wpdb->prefix . 'walmart_batch_feeds';
        $batch_items_table = $wpdb->prefix . 'walmart_batch_items';

        $batch_id = $batch_feed->batch_id;
        $success_count = 0;
        $failed_count = 0;

        // 解析API结果，获取详细的商品处理结果
        if (isset($api_result['itemsReceived']) && isset($api_result['itemsSucceeded']) && isset($api_result['itemsFailed'])) {
            $success_count = intval($api_result['itemsSucceeded']);
            $failed_count = intval($api_result['itemsFailed']);
        }

        // 如果上面的字段不存在，尝试从itemDetails中统计
        if ($success_count === 0 && $failed_count === 0 && isset($api_result['itemDetails']['itemIngestionStatus'])) {
            $success_count = 0;
            $failed_count = 0;
            $processing_count = 0;

            foreach ($api_result['itemDetails']['itemIngestionStatus'] as $item_detail) {
                if (isset($item_detail['ingestionStatus'])) {
                    switch ($item_detail['ingestionStatus']) {
                        case 'SUCCESS':
                            $success_count++;
                            break;
                        case 'ERROR':
                        case 'DATA_ERROR':
                            $failed_count++;
                            break;
                        case 'INPROGRESS':
                            $processing_count++;
                            break;
                    }
                }
            }

            woo_walmart_sync_log('批量Feed统计', '信息', [
                'batch_id' => $batch_id,
                'success_count' => $success_count,
                'failed_count' => $failed_count,
                'processing_count' => $processing_count,
                'total_items' => count($api_result['itemDetails']['itemIngestionStatus'])
            ], '从itemDetails中统计商品处理结果');
        }

        // 确定批次状态 - 改进逻辑以支持部分成功
        $batch_status = 'PROCESSING';
        if ($feed_status === 'PROCESSED') {
            $batch_status = 'COMPLETED';
        } elseif ($feed_status === 'ERROR') {
            // ERROR状态需要进一步判断：如果有成功的商品，则为部分成功
            if ($success_count > 0) {
                $batch_status = 'COMPLETED'; // 部分成功也算完成
                woo_walmart_sync_log('批量Feed状态判断', '信息', [
                    'batch_id' => $batch_id,
                    'feed_status' => $feed_status,
                    'success_count' => $success_count,
                    'failed_count' => $failed_count,
                    'final_status' => $batch_status
                ], 'Feed状态为ERROR但有成功商品，标记为COMPLETED（部分成功）');
            } else {
                $batch_status = 'ERROR'; // 完全失败
                woo_walmart_sync_log('批量Feed状态判断', '警告', [
                    'batch_id' => $batch_id,
                    'feed_status' => $feed_status,
                    'success_count' => $success_count,
                    'failed_count' => $failed_count,
                    'final_status' => $batch_status
                ], 'Feed状态为ERROR且无成功商品，标记为ERROR（完全失败）');
            }
        }

        // 更新批次状态
        $update_data = [
            'status' => $batch_status,
            'success_count' => $success_count,
            'failed_count' => $failed_count,
            'progress_current' => $success_count + $failed_count,
            'api_response' => wp_json_encode($api_result),
            'updated_at' => current_time('mysql')
        ];

        if ($batch_status === 'COMPLETED' || $batch_status === 'ERROR') {
            $update_data['completed_at'] = current_time('mysql');
        }

        $wpdb->update(
            $batch_feeds_table,
            $update_data,
            ['batch_id' => $batch_id]
        );

        // 更新批次商品状态
        if ($batch_status === 'COMPLETED' || $batch_status === 'ERROR') {
            $this->update_batch_items_status($batch_id, $api_result);
        }

        // 如果是子批次，更新主批次状态
        if ($batch_feed->batch_type === 'chunk' && $batch_feed->parent_batch_id) {
            $this->update_master_batch_status_from_sync($batch_feed->parent_batch_id);
        }

        // 记录日志
        woo_walmart_sync_log('批量Feed状态更新', '成功', [
            'batch_id' => $batch_id,
            'feed_id' => $batch_feed->feed_id,
            'old_status' => 'SUBMITTED',
            'new_status' => $batch_status,
            'success_count' => $success_count,
            'failed_count' => $failed_count
        ], "批量Feed状态更新: {$batch_status}");
    }

    // 更新批次商品状态
    private function update_batch_items_status($batch_id, $api_result) {
        global $wpdb;
        $batch_items_table = $wpdb->prefix . 'walmart_batch_items';
        $feeds_table = $wpdb->prefix . 'walmart_feeds';

        // 获取批次中的所有商品
        $batch_items = $wpdb->get_results($wpdb->prepare(
            "SELECT product_id, sku FROM $batch_items_table WHERE batch_id = %s",
            $batch_id
        ));

        // 从API响应中提取商品详情
        $item_details = array();
        if (isset($api_result['itemDetails']['itemIngestionStatus']) && is_array($api_result['itemDetails']['itemIngestionStatus'])) {
            foreach ($api_result['itemDetails']['itemIngestionStatus'] as $item_detail) {
                if (isset($item_detail['sku'])) {
                    $item_details[$item_detail['sku']] = $item_detail;
                }
            }
        }

        woo_walmart_sync_log('批次商品状态更新', '信息', [
            'batch_id' => $batch_id,
            'batch_items_count' => count($batch_items),
            'api_item_details_count' => count($item_details)
        ], '开始更新批次商品状态和WPID');

        $batch_product_data = array();

        foreach ($batch_items as $item) {
            $product = wc_get_product($item->product_id);
            if (!$product) {
                continue;
            }

            $sku = $item->sku ?: $product->get_sku();
            $item_status = 'PROCESSED';
            $error_message = null;
            $wpid = null;

            // 从API响应中查找对应的商品详情
            if (isset($item_details[$sku])) {
                $detail = $item_details[$sku];

                // 提取WPID
                if (isset($detail['wpid']) && !empty($detail['wpid'])) {
                    $wpid = $detail['wpid'];
                }

                // 检查商品状态 - 根据沃尔玛官方API文档更新状态映射
                if (isset($detail['ingestionStatus'])) {
                    switch ($detail['ingestionStatus']) {
                        case 'SUCCESS':
                            $item_status = 'SUCCESS';
                            break;
                        case 'DATA_ERROR':
                        case 'SYSTEM_ERROR':
                        case 'TIMEOUT_ERROR':
                        case 'ERROR': // 兼容旧版本
                            $item_status = 'ERROR';
                            $error_message = isset($detail['ingestionErrors']) ? wp_json_encode($detail['ingestionErrors']) : '处理失败';
                            break;
                        case 'INPROGRESS':
                            $item_status = 'INPROGRESS'; // 保持与官方API一致
                            break;
                        default:
                            // 未知状态，记录日志
                            woo_walmart_sync_log('未知商品状态', '警告', [
                                'sku' => $sku,
                                'ingestion_status' => $detail['ingestionStatus'],
                                'batch_id' => $batch_id
                            ], "发现未知的商品摄取状态: {$detail['ingestionStatus']}");
                            $item_status = 'INPROGRESS'; // 默认为处理中
                            break;
                    }
                }
            }

            // 更新批次商品状态
            $wpdb->update(
                $batch_items_table,
                [
                    'status' => $item_status,
                    'error_message' => $error_message,
                    'processed_at' => current_time('mysql')
                ],
                [
                    'batch_id' => $batch_id,
                    'product_id' => $item->product_id
                ]
            );

            // 更新walmart_feeds表中的WPID
            if ($wpid) {
                $feeds_updated = $wpdb->update(
                    $feeds_table,
                    ['wpid' => $wpid],
                    [
                        'product_id' => $item->product_id,
                        'sku' => $sku
                    ]
                );

                woo_walmart_sync_log('WPID更新', $feeds_updated ? '成功' : '失败', [
                    'product_id' => $item->product_id,
                    'sku' => $sku,
                    'wpid' => $wpid,
                    'updated_rows' => $feeds_updated
                ], $feeds_updated ? "商品 {$item->product_id} 的WPID已更新为 {$wpid}" : "商品 {$item->product_id} 的WPID更新失败");
            }

            // 收集成功的商品用于库存同步
            if ($item_status === 'SUCCESS' && $wpid) {
                $batch_product_data[] = array(
                    'product_id' => $item->product_id,
                    'walmart_sku' => $sku,
                    'wpid' => $wpid
                );
            }
        }

        // 触发批量库存同步钩子
        if (!empty($batch_product_data)) {
            woo_walmart_sync_log('批量库存同步', '信息', [
                'batch_id' => $batch_id,
                'products_count' => count($batch_product_data),
                'product_data' => $batch_product_data
            ], '准备触发批量库存同步钩子');

            do_action('woo_walmart_sync_batch_products_created', $batch_product_data);

            woo_walmart_sync_log('批量库存同步', '信息', [
                'batch_id' => $batch_id,
                'products_count' => count($batch_product_data)
            ], '批量库存同步钩子已触发');
        } else {
            woo_walmart_sync_log('批量库存同步', '警告', [
                'batch_id' => $batch_id,
                'batch_items_count' => count($batch_items)
            ], '没有有效的商品数据用于库存同步');
        }
    }

    // 从同步任务更新主批次状态
    private function update_master_batch_status_from_sync($parent_batch_id) {
        global $wpdb;
        $batch_feeds_table = $wpdb->prefix . 'walmart_batch_feeds';

        // 获取所有子批次的状态
        $sub_batches = $wpdb->get_results($wpdb->prepare(
            "SELECT status, success_count, failed_count FROM $batch_feeds_table
             WHERE parent_batch_id = %s",
            $parent_batch_id
        ));

        if (empty($sub_batches)) return;

        // 统计子批次状态
        $total_sub_batches = count($sub_batches);
        $completed_sub_batches = 0;
        $error_sub_batches = 0;
        $total_success = 0;
        $total_failed = 0;

        foreach ($sub_batches as $sub_batch) {
            if ($sub_batch->status === 'COMPLETED') {
                $completed_sub_batches++;
            } elseif ($sub_batch->status === 'ERROR') {
                $error_sub_batches++;
            }
            $total_success += $sub_batch->success_count;
            $total_failed += $sub_batch->failed_count;
        }

        // 确定主批次状态
        $master_status = 'PROCESSING';
        if ($completed_sub_batches === $total_sub_batches) {
            $master_status = 'COMPLETED';
        } elseif ($error_sub_batches === $total_sub_batches) {
            $master_status = 'ERROR';
        } elseif ($completed_sub_batches + $error_sub_batches === $total_sub_batches) {
            $master_status = 'COMPLETED';
        }

        // 更新主批次状态
        $update_data = [
            'status' => $master_status,
            'success_count' => $total_success,
            'failed_count' => $total_failed,
            'progress_current' => $total_success + $total_failed,
            'updated_at' => current_time('mysql')
        ];

        if ($master_status === 'COMPLETED' || $master_status === 'ERROR') {
            $update_data['completed_at'] = current_time('mysql');
        }

        $wpdb->update(
            $batch_feeds_table,
            $update_data,
            ['batch_id' => $parent_batch_id]
        );
    }

    // 从UPC池中分配一个未使用的UPC
    private function assign_upc_from_pool($product_id) {
        global $wpdb;
        $upc_table = $wpdb->prefix . 'walmart_upc_pool';
        
        $available_upc = $wpdb->get_row($wpdb->prepare("SELECT id, upc_code FROM $upc_table WHERE is_used = 0 LIMIT 1"));
        
        if (!$available_upc) {
            return new WP_Error('no_upc', 'UPC池已用尽，请补充新的UPC码。');
        }
        
        // 标记为已使用并关联产品
        $wpdb->update(
            $upc_table,
            [
                'is_used'    => 1,
                'product_id' => $product_id,
                'used_at'    => current_time('mysql'),
            ],
            ['id' => $available_upc->id]
        );
        
        // 将UPC保存到产品meta中，方便后续使用
        update_post_meta($product_id, '_walmart_upc', $available_upc->upc_code);
        
        return $available_upc->upc_code;
    }

    // 同步UPC状态：确保UPC池中的状态与产品使用情况一致
    private function sync_upc_status($upc_code, $product_id) {
        global $wpdb;
        $upc_table = $wpdb->prefix . 'walmart_upc_pool';

        // 检查UPC池中是否存在这个UPC
        $upc_record = $wpdb->get_row($wpdb->prepare("SELECT id, is_used, product_id FROM $upc_table WHERE upc_code = %s", $upc_code));

        if ($upc_record) {
            // 如果UPC存在但状态不正确，更新状态
            if (!$upc_record->is_used || $upc_record->product_id != $product_id) {
                $wpdb->update(
                    $upc_table,
                    [
                        'is_used'    => 1,
                        'product_id' => $product_id,
                        'used_at'    => current_time('mysql'),
                    ],
                    ['id' => $upc_record->id]
                );

                woo_walmart_sync_log('UPC状态同步', '成功', [
                    'upc_code' => $upc_code,
                    'product_id' => $product_id,
                    'old_status' => $upc_record->is_used,
                    'old_product_id' => $upc_record->product_id
                ], 'UPC池状态已同步', $product_id);
            }
        } else {
            // 如果UPC不存在于池中，添加它（这种情况不应该发生，但作为安全措施）
            $wpdb->insert(
                $upc_table,
                [
                    'upc_code'   => $upc_code,
                    'is_used'    => 1,
                    'product_id' => $product_id,
                    'used_at'    => current_time('mysql'),
                ]
            );

            woo_walmart_sync_log('UPC状态同步', '警告', [
                'upc_code' => $upc_code,
                'product_id' => $product_id
            ], 'UPC不存在于池中，已自动添加', $product_id);
        }
    }
}