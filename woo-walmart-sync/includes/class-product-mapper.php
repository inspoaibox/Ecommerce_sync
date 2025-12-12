<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class Woo_Walmart_Product_Mapper {

    /**
     * 当前处理的UPC码
     * @var string
     */
    private $current_upc;

    /**
     * 沃尔玛规范服务
     * @var Walmart_Spec_Service
     */
    private $spec_service;

    /**
     * 当前产品类型ID
     * @var string
     */
    private $current_product_type_id;

    private $field_validator;

    /**
     * 加拿大市场字段元数据缓存
     * @var array|null
     */
    private $ca_field_metadata = null;

    public function __construct() {
        // 初始化字段验证器
        require_once plugin_dir_path(__FILE__) . 'class-walmart-field-validator.php';
        $this->field_validator = new Woo_Walmart_Field_Validator();

        // 初始化API规范服务
        if (class_exists('Walmart_Spec_Service')) {
            $this->spec_service = new Walmart_Spec_Service();
        }
    }

    /**
     * 将WooCommerce商品对象映射为沃尔玛API商品数据结构
     * @param WC_Product $product
     * @param string $walmart_category_name 沃尔玛官方分类名称 (例如: Clothing)
     * @param string $upc 从池中分配的UPC码
     * @param array $attribute_rules 从数据库读取的该分类的属性映射规则
     * @param int $fulfillment_lag_time 备货时间
     * @param string $market_code 市场代码 (US, CA, MX, CL)
     * @return array
     */
    public function map( $product, $walmart_category_name, $upc, $attribute_rules, $fulfillment_lag_time = 1, $market_code = 'US' ) {
        // 保存UPC供其他方法使用
        $this->current_upc = $upc;

        // 初始化规范服务
        $spec_service_file = dirname(__FILE__) . '/class-walmart-spec-service.php';
        if (file_exists($spec_service_file)) {
            require_once $spec_service_file;
            $this->spec_service = new Walmart_Spec_Service();

            // 记录规范服务初始化日志
            woo_walmart_sync_log('规范服务', '调试', [
                'product_type_id' => $walmart_category_name,
                'spec_service_loaded' => true,
                'spec_service_class' => get_class($this->spec_service)
            ], "API规范服务已初始化", $product->get_id());
        } else {
            woo_walmart_sync_log('规范服务', '错误', [
                'file_path' => $spec_service_file,
                'file_exists' => false
            ], "规范服务文件不存在", $product->get_id());
        }

        // 保存当前产品类型ID
        $this->current_product_type_id = $walmart_category_name;

        // 🆕 如果是加拿大市场，加载字段元数据
        if ($market_code === 'CA' && is_null($this->ca_field_metadata)) {
            $this->ca_field_metadata = $this->load_ca_field_metadata($walmart_category_name);

            woo_walmart_sync_log('加拿大市场元数据', '调试', [
                'category' => $walmart_category_name,
                'metadata_count' => count($this->ca_field_metadata),
                'market_code' => $market_code
            ], "已加载加拿大市场字段元数据", $product->get_id());
        }

        // 基础数据结构 - 添加数据验证
        $product_name = $product->get_name();
        $product_description = $product->get_description();
        $product_price = $product->get_price();
        $product_image_id = $product->get_image_id();
        $product_weight = $product->get_weight();
        $stock_quantity = $product->get_stock_quantity();

        // 验证必需字段
        if (empty($product_name)) {
            $product_name = $product->get_sku(); // 使用SKU作为后备名称
        }

        if (empty($product_description)) {
            $product_description = $product->get_short_description();
            if (empty($product_description)) {
                $product_description = $product_name; // 使用产品名称作为后备描述
            }
        }

        // 获取主图URL（支持远程图片）
        $main_image_url = '';
        if ($product_image_id) {
            if (is_numeric($product_image_id) && $product_image_id > 0) {
                // 处理本地主图（数字ID）
                $main_image_url = wp_get_attachment_url($product_image_id);
            } else if (is_numeric($product_image_id) && $product_image_id < 0) {
                // 处理远程主图（负数ID）
                $remote_gallery_urls = get_post_meta($product->get_id(), '_remote_gallery_urls', true);
                if (is_array($remote_gallery_urls) && !empty($remote_gallery_urls)) {
                    // 计算在远程图库数组中的索引
                    $remote_index = abs($product_image_id + 1000);
                    if (isset($remote_gallery_urls[$remote_index])) {
                        $remote_url = $remote_gallery_urls[$remote_index];
                        if (filter_var($remote_url, FILTER_VALIDATE_URL)) {
                            $main_image_url = $this->clean_image_url_for_walmart($remote_url);
                        }
                    }
                }
            } else if (is_string($product_image_id) && strpos($product_image_id, 'remote_') === 0) {
                // 处理远程主图（字符串ID，如 remote_xxx）
                $remote_gallery_urls = get_post_meta($product->get_id(), '_remote_gallery_urls', true);
                if (is_array($remote_gallery_urls) && !empty($remote_gallery_urls)) {
                    // 获取跳过标记
                    $skip_indices = get_post_meta($product->get_id(), '_walmart_skip_image_indices', true);
                    if (!is_array($skip_indices)) {
                        $skip_indices = [];
                    }

                    // 找到第一张未被标记跳过的图片作为主图
                    foreach ($remote_gallery_urls as $index => $remote_url) {
                        if (!in_array($index, $skip_indices) && filter_var($remote_url, FILTER_VALIDATE_URL)) {
                            $main_image_url = $this->clean_image_url_for_walmart($remote_url);
                            break;
                        }
                    }
                }
            }
        }

        // 如果仍然没有主图，尝试从远程图库获取第一张未跳过的图片作为主图
        if (empty($main_image_url)) {
            $remote_gallery_urls = get_post_meta($product->get_id(), '_remote_gallery_urls', true);
            if (is_array($remote_gallery_urls) && !empty($remote_gallery_urls)) {
                // 获取跳过标记
                $skip_indices = get_post_meta($product->get_id(), '_walmart_skip_image_indices', true);
                if (!is_array($skip_indices)) {
                    $skip_indices = [];
                }

                // 找到第一张未被标记跳过的图片
                foreach ($remote_gallery_urls as $index => $remote_url) {
                    if (!in_array($index, $skip_indices) && filter_var($remote_url, FILTER_VALIDATE_URL)) {
                        $main_image_url = $this->clean_image_url_for_walmart($remote_url);
                        break;
                    }
                }
            }
        }

        // 最后的后备方案：使用占位符
        if (empty($main_image_url)) {
            $main_image_url = wc_placeholder_img_src('full');
        }

        // 获取产品图库（兼容GigaCloud远程图库）
        $gallery_image_ids = $product->get_gallery_image_ids();
        $additional_images = [];

        if (!empty($gallery_image_ids)) {
            foreach ($gallery_image_ids as $gallery_image_id) {
                if ($gallery_image_id > 0) {
                    // 处理本地图库图片
                    $gallery_image_url = wp_get_attachment_url($gallery_image_id);
                    if ($gallery_image_url && filter_var($gallery_image_url, FILTER_VALIDATE_URL)) {
                        $additional_images[] = $gallery_image_url;
                    }
                } else if ($gallery_image_id < 0) {
                    // 处理GigaCloud远程图库（负数ID）
                    $remote_gallery_urls = get_post_meta($product->get_id(), '_remote_gallery_urls', true);
                    if (is_array($remote_gallery_urls) && !empty($remote_gallery_urls)) {
                        // 计算在远程图库数组中的索引
                        $remote_index = abs($gallery_image_id + 1000);
                        if (isset($remote_gallery_urls[$remote_index])) {
                            $remote_url = $remote_gallery_urls[$remote_index];
                            if (filter_var($remote_url, FILTER_VALIDATE_URL)) {
                                $additional_images[] = $remote_url;
                            }
                        }
                    }
                }
            }
        }

        // 如果没有通过图库ID获取到图片，直接尝试从远程图库元数据获取（跳过标记的图片）
        if (empty($additional_images)) {
            $remote_gallery_urls = get_post_meta($product->get_id(), '_remote_gallery_urls', true);
            if (is_array($remote_gallery_urls) && !empty($remote_gallery_urls)) {
                // 获取跳过标记
                $skip_indices = get_post_meta($product->get_id(), '_walmart_skip_image_indices', true);
                if (!is_array($skip_indices)) {
                    $skip_indices = [];
                }

                foreach ($remote_gallery_urls as $index => $remote_url) {
                    // 跳过标记的图片和主图（索引0）
                    if (!in_array($index, $skip_indices) && $index > 0 && filter_var($remote_url, FILTER_VALIDATE_URL)) {
                        $additional_images[] = $remote_url;
                    }
                }
            }
        }

        // 添加图片获取调试日志（包含远程图库信息）
        $remote_gallery_urls = get_post_meta($product->get_id(), '_remote_gallery_urls', true);
        woo_walmart_sync_log('产品图片获取', '调试', [
            'product_id' => $product->get_id(),
            'main_image_id' => $product->get_image_id(),
            'main_image_id_type' => $this->get_image_id_type($product->get_image_id()),
            'main_image_url' => $main_image_url,
            'main_image_source' => $this->get_image_source_type($main_image_url),
            'gallery_image_ids' => $gallery_image_ids,
            'remote_gallery_urls' => $remote_gallery_urls,
            'remote_gallery_count' => is_array($remote_gallery_urls) ? count($remote_gallery_urls) : 0,
            'additional_images_count' => count($additional_images),
            'additional_images' => $additional_images
        ], '产品图片获取详情（含远程图库和主图来源）');

        // 确保库存数量不为负数
        if ($stock_quantity < 0) {
            $stock_quantity = 0;
        }

        // 获取产品重量 - 优先从产品属性 "Product Weight" 获取
        $product_weight = null;

        // 1. 首先尝试从产品属性 "Product Weight" 获取（主要数据源）
        $attr_weight = $product->get_attribute('Product Weight') ?:
                      $product->get_attribute('product_weight') ?:
                      $product->get_attribute('weight');
        if (!empty($attr_weight)) {
            // 从属性值中提取数字（支持 "26.4 lb", "26.4", "26.4 lbs" 等格式）
            $numeric_weight = $this->extract_numeric_weight($attr_weight);
            if ($numeric_weight > 0) {
                $product_weight = $numeric_weight;
            }
        }

        // 2. 如果产品属性没有，尝试从自定义字段获取
        if (empty($product_weight)) {
            $custom_weight = get_post_meta($product->get_id(), 'Product Weight', true);
            if (!empty($custom_weight) && is_numeric($custom_weight)) {
                $product_weight = (float) $custom_weight;
            }
        }

        // 3. 如果还没有，使用WooCommerce默认重量
        if (empty($product_weight)) {
            $wc_weight = $product->get_weight();
            if (!empty($wc_weight) && is_numeric($wc_weight)) {
                $product_weight = (float) $wc_weight;
            }
        }

        // 4. 最后默认为1.0
        if (empty($product_weight)) {
            $product_weight = 1.0;
        }

        // 添加调试日志记录重量获取过程
        woo_walmart_sync_log('产品重量获取', '调试', [
            'product_id' => $product->get_id(),
            'attr_weight' => $attr_weight ?? 'N/A',
            'custom_weight' => $custom_weight ?? 'N/A',
            'wc_weight' => $product->get_weight() ?? 'N/A',
            'final_weight' => $product_weight,
            'fulfillment_type' => 'SELLER_FULFILLED'
        ], '产品重量获取过程（优先产品属性）');

        // 根据官方 V5.0 格式构建商品数据结构 - 基于API 5.0规范
        // 重构：移除所有硬编码，完全由分类映射配置控制
        // 🔧 加拿大市场：Visible 直接包含字段（无分类层级）+ 多语言格式
        // 🇺🇸 美国市场：Visible 下有分类名称层级
        if ($market_code === 'CA') {
            // 加拿大市场：使用多语言格式 {"en": "..."}
            $item_data = [
                'Orderable' => [
                    'sku' => $product->get_sku(),
                    'productIdentifiers' => [
                        'productIdType' => 'UPC',
                        'productId' => $upc
                    ],
                    'quantity' => $stock_quantity ? (int) $stock_quantity : 0,
                    'price' => $product_price ? round((float) $product_price, 2) : 1.0,
                    // 🔧 CA: ShippingWeight需要对象格式
                    'ShippingWeight' => [
                        'unit' => 'lb',
                        'measure' => round($product_weight, 2)
                    ],
                    // 🔧 CA: 多语言字段
                    'productName' => ['en' => $this->validate_field_for_v5('productName', $product_name)],
                    'brand' => ['en' => $this->get_brand_value($product, $attribute_rules)],
                ],
                'Visible' => [
                    'mainImageUrl' => $main_image_url,
                ]
            ];
        } else {
            // 美国市场：使用分类层级
            $item_data = [
                'Orderable' => [
                    'sku' => $product->get_sku(),
                    'productIdentifiers' => [
                        'productIdType' => 'UPC',
                        'productId' => $upc
                    ],
                    'quantity' => $stock_quantity ? (int) $stock_quantity : 0,
                    'price' => $product_price ? round((float) $product_price, 2) : 1.0,
                ],
                'Visible' => [
                    $walmart_category_name => [
                        'productName' => $this->validate_field_for_v5('productName', $product_name),
                        'mainImageUrl' => $main_image_url,
                    ]
                ]
            ];
        }

        // 🔧 优化：去重处理，避免重复图片影响数量计算
        $before_unique_count = count($additional_images);
        $additional_images = array_unique($additional_images);
        $original_count = count($additional_images);

        // 记录去重前后的数量变化
        if ($before_unique_count != $original_count) {
            woo_walmart_sync_log('图片去重处理', '信息', [
                'before_unique' => $before_unique_count,
                'after_unique' => $original_count,
                'removed_duplicates' => $before_unique_count - $original_count
            ], "检测到重复图片，已去重处理");
        }

        // 沃尔玛图片补足逻辑：确保副图至少5张（不包含主图）
        // 只处理3-4张的情况，2张以下代表产品资料不全，不进行补足
        if ($original_count == 4) {
            // 副图 = 4张：添加占位符图片1补足至5张
            $placeholder_1 = get_option('woo_walmart_placeholder_image_1', '');
            if (!empty($placeholder_1) && filter_var($placeholder_1, FILTER_VALIDATE_URL)) {
                $additional_images[] = $placeholder_1;

                woo_walmart_sync_log('图片补足-4张', '信息', [
                    'original_count' => $original_count,
                    'final_count' => count($additional_images),
                    'placeholder_1' => $placeholder_1
                ], "副图4张，添加占位符图片1补足至5张");
            }
        } elseif ($original_count == 3) {
            // 副图 = 3张：添加占位符图片1 + 占位符图片2补足至5张
            $placeholder_1 = get_option('woo_walmart_placeholder_image_1', '');
            $placeholder_2 = get_option('woo_walmart_placeholder_image_2', '');

            if (!empty($placeholder_1) && filter_var($placeholder_1, FILTER_VALIDATE_URL)) {
                $additional_images[] = $placeholder_1;
            }
            if (!empty($placeholder_2) && filter_var($placeholder_2, FILTER_VALIDATE_URL)) {
                $additional_images[] = $placeholder_2;
            }

            woo_walmart_sync_log('图片补足-3张', '信息', [
                'original_count' => $original_count,
                'final_count' => count($additional_images),
                'placeholder_1' => $placeholder_1,
                'placeholder_2' => $placeholder_2
            ], "副图3张，添加占位符图片1和2补足至5张");
        } elseif ($original_count < 3) {
            // 副图 < 3张：不处理，让API返回错误提醒用户产品资料不全
            woo_walmart_sync_log('图片不足-警告', '警告', [
                'original_count' => $original_count,
                'product_id' => $product->get_id(),
                'sku' => $product->get_sku()
            ], "副图少于3张，不进行补足，产品资料不全，建议用户添加更多产品图片");
        }

        // 添加图库图片（如果有的话），限制15张
        if (!empty($additional_images)) {
            // 限制最多15张图片
            $limited_images = array_slice($additional_images, 0, 15);

            // 🔧 加拿大市场：直接在Visible下添加
            // 🇺🇸 美国市场：在Visible的分类下添加
            if ($market_code === 'CA') {
                $item_data['Visible']['productSecondaryImageURL'] = $limited_images;
            } else {
                $item_data['Visible'][$walmart_category_name]['productSecondaryImageURL'] = $limited_images;
            }

            if (count($additional_images) > 15) {
                woo_walmart_sync_log('图片数量限制', '警告', [
                    'original_count' => count($additional_images),
                    'limited_count' => count($limited_images)
                ], "图片数量过多，已限制为15张");
            }
        }

        // 记录图片字段的最终状态
        if ($market_code === 'CA') {
            $final_image_count = isset($item_data['Visible']['productSecondaryImageURL']) ? count($item_data['Visible']['productSecondaryImageURL']) : 0;
            woo_walmart_sync_log('产品图片字段', '调试', [
                'primaryImageUrl' => $item_data['Visible']['mainImageUrl'],
                'has_additionalImages' => isset($item_data['Visible']['productSecondaryImageURL']),
                'original_images_count' => $original_count,
                'final_images_count' => $final_image_count,
                'placeholder_used' => $final_image_count > $original_count,
                'meets_walmart_requirement' => $final_image_count >= 5,
                'additionalImages' => $item_data['Visible']['productSecondaryImageURL'] ?? [],
                'market' => 'CA'
            ], '最终图片字段状态（含占位符补足信息）- 加拿大市场');
        } else {
            $final_image_count = isset($item_data['Visible'][$walmart_category_name]['productSecondaryImageURL']) ? count($item_data['Visible'][$walmart_category_name]['productSecondaryImageURL']) : 0;
            woo_walmart_sync_log('产品图片字段', '调试', [
                'primaryImageUrl' => $item_data['Visible'][$walmart_category_name]['mainImageUrl'],
                'has_additionalImages' => isset($item_data['Visible'][$walmart_category_name]['productSecondaryImageURL']),
                'original_images_count' => $original_count,
                'final_images_count' => $final_image_count,
                'placeholder_used' => $final_image_count > $original_count,
                'meets_walmart_requirement' => $final_image_count >= 5,
                'additionalImages' => $item_data['Visible'][$walmart_category_name]['productSecondaryImageURL'] ?? [],
                'market' => 'US'
            ], '最终图片字段状态（含占位符补足信息）- 美国市场');
        }

        // ---- 这是本次修改的核心部分 ----
        // 动态处理属性映射规则
        if ( ! empty( $attribute_rules ) && isset( $attribute_rules['name'] ) ) {
            foreach ( $attribute_rules['name'] as $index => $walmart_attr_name ) {
                $map_type   = $attribute_rules['type'][ $index ] ?? 'default_value';
                $map_source = $attribute_rules['source'][ $index ] ?? '';
                // 只有在明确设置了格式且不为空时才使用，否则为null（保持原有逻辑）
                $format_override = isset($attribute_rules['format'][ $index ]) && !empty($attribute_rules['format'][ $index ])
                    ? $attribute_rules['format'][ $index ] : null;

                if ( empty( $walmart_attr_name ) ) {
                    continue; // 如果沃尔玛属性名为空，则跳过
                }

                $value = null;

                if ( $map_type === 'wc_attribute' ) {
                    // 从WooCommerce属性获取值
                    $wc_attr_label = $map_source;
                    $value = null;

                    // 如果映射源为空，提供默认值
                    if (empty($wc_attr_label)) {
                        if ($walmart_attr_name === 'SkuUpdate') {
                            $value = 'No';
                        } elseif ($walmart_attr_name === 'ProductIdUpdate') {
                            $value = 'No';
                        }
                    } else {
                        // 尝试多种方式获取属性值
                        // 1. 直接使用属性标签
                        $value = $product->get_attribute($wc_attr_label);

                        // 2. 如果没有找到，尝试使用属性slug
                        if (empty($value)) {
                            $attr_slug = sanitize_title($wc_attr_label);
                            $value = $product->get_attribute($attr_slug);
                        }

                        // 3. 尝试使用pa_前缀的分类法名称
                        if (empty($value)) {
                            $attribute_taxonomy = 'pa_' . sanitize_title($wc_attr_label);
                            $value = $product->get_attribute($attribute_taxonomy);
                        }

                        // 4. 尝试从产品属性数组中直接获取
                        if (empty($value)) {
                            $attributes = $product->get_attributes();
                            foreach ($attributes as $attr_name => $attribute) {
                                if ($attribute->get_name() === $wc_attr_label ||
                                    $attribute->get_name() === 'pa_' . sanitize_title($wc_attr_label)) {
                                    if ($attribute->is_taxonomy()) {
                                        $terms = wp_get_post_terms($product->get_id(), $attribute->get_name());
                                        if (!is_wp_error($terms) && !empty($terms)) {
                                            $value = implode(', ', wp_list_pluck($terms, 'name'));
                                        }
                                    } else {
                                        $value = $attribute->get_options()[0] ?? '';
                                    }
                                    break;
                                }
                            }
                        }
                    }

                } elseif ( $map_type === 'default_value' ) {
                    // 使用默认值，但需要进行特殊处理
                    $value = $map_source;

                    // 🔧 修复：跳过正则表达式格式的值（这些应该是auto_generate但被错误标记为default_value）
                    if (is_string($value) && preg_match('/^\/.*\/$/', $value)) {
                        woo_walmart_sync_log('跳过正则表达式值', '调试', [
                            'field' => $walmart_attr_name,
                            'regex_pattern' => $value
                        ], "字段 {$walmart_attr_name} 包含正则表达式，跳过");
                        $value = null;  // 跳过此字段
                    }

                    // 特殊字段的值修正
                    if ($walmart_attr_name === 'batteryTechnologyType' && $value === 'No') {
                        $value = 'Does Not Contain a Battery';
                    } elseif ($walmart_attr_name === 'stateRestrictions') {
                        // stateRestrictions字段：尊重用户设置的默认值
                        // 如果用户明确设置了默认值，直接使用，不进行格式转换
                        // 只有在auto_generate类型时才进行复杂的对象格式转换
                        // 这里是default_value类型，应该直接使用用户设置的值
                    } elseif ($walmart_attr_name === 'productLine') {
                        // 转换为数组格式，如果为空则设为null（不传递）
                        if (!empty($value)) {
                            $value = [$value];
                        } else {
                            $value = null;
                        }
                    } elseif ($walmart_attr_name === 'fulfillmentLagTime') {
                        // 5.0版本需要字符串格式
                        $value = is_numeric($value) ? (string)$value : "1";
                    } elseif ($walmart_attr_name === 'assemblyInstructions') {
                        // assemblyInstructions需要URL格式，从产品文档标签获取
                        // 检查是否已经是有效的URL
                        if (empty($value) || !filter_var($value, FILTER_VALIDATE_URL)) {
                            $assembly_url = null;

                            // 首先尝试从产品文档管理器获取
                            if (class_exists('Simple_Product_Document_Manager')) {
                                $doc_manager = new Simple_Product_Document_Manager();
                                $documents = $doc_manager->get_product_documents($product->get_id());

                                // 查找manual类型的文档（文档按类型分组）
                                if (!empty($documents) && isset($documents['manuals'])) {
                                    $manuals = $documents['manuals'];
                                    if (!empty($manuals)) {
                                        // 使用第一个说明书
                                        $first_manual = reset($manuals);
                                        $assembly_url = $doc_manager->get_document_url($first_manual);
                                    }
                                }
                            }

                            // 如果没有找到文档，尝试从产品属性获取
                            if (!$assembly_url) {
                                $assembly_url = $product->get_attribute('Assembly Instructions URL') ?:
                                              $product->get_attribute('assembly_instructions_url') ?:
                                              get_post_meta($product->get_id(), '_assembly_instructions_url', true);
                            }

                            // 验证并设置URL
                            if ($assembly_url && filter_var($assembly_url, FILTER_VALIDATE_URL)) {
                                $value = $assembly_url;
                            } else {
                                // 如果没有有效URL，使用占位符PDF URL
                                $value = "https://via.placeholder.com/800x600.pdf?text=Assembly+Instructions";
                            }
                        }
                    }
                } elseif ( $map_type === 'auto_generate' ) {
                    // 自动生成特殊属性值
                    $value = $this->generate_special_attribute_value($walmart_attr_name, $product, $fulfillment_lag_time);

                    // 🔧 修复：跳过正则表达式格式的值（这些是匹配规则，不是实际值）
                    if (is_string($value) && preg_match('/^\/.*\/$/', $value)) {
                        woo_walmart_sync_log('跳过正则表达式值', '调试', [
                            'field' => $walmart_attr_name,
                            'regex_pattern' => $value
                        ], "字段 {$walmart_attr_name} 包含正则表达式，跳过");
                        $value = null;  // Skip this field
                    }
                } elseif ( $map_type === 'attributes_field' ) {
                    // Attributes字段类型：优先从Attributes获取，否则使用备用默认值
                    $value = $this->get_attributes_field_value($walmart_attr_name, $product, $attribute_rules, $index);
                } elseif ( $map_type === 'walmart_field' ) {
                    // Walmart字段类型：使用指定的固定值
                    $value = $map_source;

                    // 🔧 修复：跳过正则表达式格式的值（这些是匹配规则，不是实际值）
                    if (is_string($value) && preg_match('/^\/.*\/$/', $value)) {
                        woo_walmart_sync_log('跳过正则表达式值', '调试', [
                            'field' => $walmart_attr_name,
                            'regex_pattern' => $value
                        ], "字段 {$walmart_attr_name} 包含正则表达式，跳过");
                        $value = null;  // Skip this field
                    }

                    // 特殊字段的格式处理
                    if ($walmart_attr_name === 'smallPartsWarnings') {
                        // smallPartsWarnings需要数组格式
                        if (!is_array($value)) {
                            $value = [$value];
                        }
                    }
                }

                // 数据类型转换：确保特定字段使用正确的数据类型
                // 优先使用用户指定的格式，如果是'auto'则使用自动检测
                $value = $this->convert_field_data_type($walmart_attr_name, $value, $format_override);

                // 🆕 市场格式转换：加拿大市场多语言字段自动包装
                $value = $this->convert_value_for_market($value, $walmart_attr_name, $market_code, $walmart_category_name);

                // 重构：支持所有字段的动态映射，智能处理空值
                // 只有当值不为null且不为空时才添加字段，避免发送空字段到API
                if ( ! is_null( $value ) && ! $this->is_empty_field_value( $value ) ) {
                    // 检查是否为Orderable属性（这些属性应该在Orderable部分，不在Visible部分）
                    $orderable_fields = [
                        'sku', 'productIdentifiers', 'price', 'ShippingWeight', 'stateRestrictions',
                        'electronicsIndicator', 'chemicalAerosolPesticide', 'batteryTechnologyType',
                        'fulfillmentLagTime', 'shipsInOriginalPackaging', 'MustShipAlone',
                        'IsPreorder', 'releaseDate', 'startDate', 'endDate', 'quantity',
                        'fulfillmentCenterID', 'inventoryAvailabilityDate',
                        'ProductIdUpdate', 'SkuUpdate'
                    ];

                    // 🔧 CA市场：这些多语言字段放在Orderable中（根据官方模板）
                    $ca_orderable_fields = [
                        'productName', 'brand', 'shortDescription', 'keyFeatures',
                        'productSecondaryImageURL'
                    ];

                    // 根据市场确定Orderable字段列表
                    if ($market_code === 'CA') {
                        $orderable_fields = array_merge($orderable_fields, $ca_orderable_fields);
                    }

                    if (in_array($walmart_attr_name, $orderable_fields)) {
                        // 🔧 CA市场：跳过已在初始化时设置的字段，避免覆盖对象格式
                        $ca_skip_fields = ['ShippingWeight', 'productName', 'brand'];
                        if ($market_code === 'CA' && in_array($walmart_attr_name, $ca_skip_fields)) {
                            // 这些字段已经在初始化时以正确格式设置，跳过
                            continue;
                        }

                        // 🔧 CA市场：ShippingWeight 必须是对象格式
                        if ($market_code === 'CA' && $walmart_attr_name === 'ShippingWeight') {
                            if (!is_array($value)) {
                                $weight_value = is_numeric($value) ? (float)$value : 1.0;
                                $value = [
                                    'unit' => 'lb',
                                    'measure' => round($weight_value, 2)
                                ];
                            }
                        }

                        // 添加到Orderable部分
                        $item_data['Orderable'][ $walmart_attr_name ] = $value;

                        // 记录Orderable字段的设置
                        woo_walmart_sync_log('动态映射-Orderable字段', '调试', [
                            'field' => $walmart_attr_name,
                            'value' => $value,
                            'type' => $map_type,
                            'source' => $map_source
                        ], "设置Orderable字段: {$walmart_attr_name}");
                    } else {
                        // 🔧 加拿大市场：Visible 直接包含字段（无分类层级）
                        // 🇺🇸 美国市场：Visible 下有分类名称层级
                        if ($market_code === 'CA') {
                            // 加拿大：直接添加到Visible
                            $item_data['Visible'][ $walmart_attr_name ] = $value;
                        } else {
                            // 美国：添加到Visible的分类下
                            $item_data['Visible'][$walmart_category_name][ $walmart_attr_name ] = $value;
                        }

                        // 记录Visible字段的设置
                        woo_walmart_sync_log('动态映射-Visible字段', '调试', [
                            'field' => $walmart_attr_name,
                            'value' => is_array($value) ? '[数组]' : $value,
                            'type' => $map_type,
                            'source' => $map_source,
                            'market' => $market_code,
                            'has_category_wrapper' => ($market_code !== 'CA')
                        ], "设置Visible字段: {$walmart_attr_name}");
                    }
                } else {
                    // 记录未设置的字段
                    woo_walmart_sync_log('动态映射-跳过字段', '调试', [
                        'field' => $walmart_attr_name,
                        'reason' => 'value为null',
                        'type' => $map_type,
                        'source' => $map_source
                    ], "跳过字段: {$walmart_attr_name}");
                }
            }
        }
        // ---- 核心部分结束 ----


        // V5.0 格式中不需要 sellerFulfilled 字段，已经通过其他字段表示

        // 添加运输模板（如果启用且有设置的话）
        $enable_shipping_template = get_option('woo_walmart_enable_shipping_template', 0);
        $shipping_template = get_option('woo_walmart_shipping_template', '');

        if ($enable_shipping_template && !empty($shipping_template)) {
            if ($market_code === 'CA') {
                // 加拿大：直接在Visible下
                $item_data['Visible']['shippingTemplate'] = $shipping_template;
            } else {
                // 美国：在Visible的分类下
                $item_data['Visible'][$walmart_category_name]['shippingTemplate'] = $shipping_template;
            }
        }

        // 统一使用5.0版本 (4.8版本已弃用)
        $business_unit = get_option('woo_walmart_business_unit', 'WALMART_US');

        // 最终修正：确保stateRestrictions字段格式正确且至少有1个条目
        if (isset($item_data['Orderable']['stateRestrictions'])) {
            $state_restrictions = $item_data['Orderable']['stateRestrictions'];

            // 记录原始数据用于调试
            woo_walmart_sync_log('stateRestrictions最终检查', '调试', [
                'original_data' => $state_restrictions,
                'is_array' => is_array($state_restrictions),
                'is_empty' => empty($state_restrictions),
                'first_element_type' => !empty($state_restrictions) ? gettype($state_restrictions[0]) : 'N/A'
            ], 'stateRestrictions字段最终检查');

            // 只有当第一个元素是字符串时才需要转换（简单数组格式）
            if (is_array($state_restrictions) && !empty($state_restrictions) &&
                isset($state_restrictions[0]) && is_string($state_restrictions[0])) {

                woo_walmart_sync_log('stateRestrictions需要转换', '调试', [
                    'original' => $state_restrictions
                ], '检测到简单数组格式，需要转换为对象数组');

                $corrected_restrictions = [];
                foreach ($state_restrictions as $item) {
                    if (strtolower($item) === 'none') {
                        // None 表示无州限制，只包含 stateRestrictionsText
                        $corrected_restrictions[] = [
                            'stateRestrictionsText' => 'None'
                        ];
                    } else {
                        $corrected_restrictions[] = [
                            'stateRestrictionsText' => 'Illegal for Sale',
                            'states' => $item
                        ];
                    }
                }
                $item_data['Orderable']['stateRestrictions'] = $corrected_restrictions;

                // 记录修正日志
                woo_walmart_sync_log('stateRestrictions字段修正', '调试', [
                    'original' => $state_restrictions,
                    'corrected' => $corrected_restrictions
                ], 'stateRestrictions字段被修正为正确的对象数组格式');
            } else {
                woo_walmart_sync_log('stateRestrictions无需转换', '调试', [
                    'data' => $state_restrictions
                ], 'stateRestrictions已经是正确的对象数组格式');
            }
        } else {
            // 如果没有设置stateRestrictions，提供默认值
            $item_data['Orderable']['stateRestrictions'] = [[
                'stateRestrictionsText' => 'None'
            ]];

            woo_walmart_sync_log('stateRestrictions字段默认值', '调试', [
                'default_value' => $item_data['Orderable']['stateRestrictions']
            ], '为stateRestrictions字段设置默认值');
        }

        // 自动添加必填的 fulfillmentCenterID 字段
        if (!isset($item_data['Orderable']['fulfillmentCenterID'])) {
            $fulfillment_center_id = $this->get_market_specific_fulfillment_center_id();
            if (!empty($fulfillment_center_id)) {
                $item_data['Orderable']['fulfillmentCenterID'] = $fulfillment_center_id;

                woo_walmart_sync_log('fulfillmentCenterID自动添加', '调试', [
                    'fulfillmentCenterID' => $fulfillment_center_id,
                    'market' => get_option('woo_walmart_business_unit', 'WALMART_US')
                ], '根据市场自动添加fulfillmentCenterID字段');
            } else {
                woo_walmart_sync_log('fulfillmentCenterID缺失', '警告', [], 'fulfillmentCenterID设置为空，可能导致API错误');
            }
        }

        // 🔧 根据市场动态选择Feed格式
        $business_unit = get_option('woo_walmart_business_unit', 'WALMART_US');
        $market_code = str_replace('WALMART_', '', $business_unit);

        if ($market_code === 'CA') {
            // 🔧 CA: 修复日期格式 (需要 YYYY-MM-DD，不是 ISO 8601)
            if (isset($item_data['Orderable']['startDate'])) {
                $item_data['Orderable']['startDate'] = date('Y-m-d', strtotime($item_data['Orderable']['startDate']));
            }
            if (isset($item_data['Orderable']['endDate'])) {
                $item_data['Orderable']['endDate'] = date('Y-m-d', strtotime($item_data['Orderable']['endDate']));
            }

            // 🔧 CA: 确定 subCategory（从分类路径获取）
            // CA_FURNITURE -> furniture_other
            $sub_category = $this->get_ca_sub_category($walmart_category_name);

            // 🇨🇦 加拿大市场：使用 CA_MP_ITEM_INTL_SPEC.json 规范 (版本 3.16)
            $final_data = [
                'MPItemFeedHeader' => [
                    'version' => '3.16',
                    'mart' => 'WALMART_CA',
                    'sellingChannel' => 'marketplace',
                    'processMode' => 'REPLACE',
                    'subset' => 'EXTERNAL',
                    'locale' => ['en', 'fr'],  // 🔧 CA需要locale字段
                    'subCategory' => $sub_category  // 🔧 CA需要subCategory
                ],
                'MPItem' => [$item_data] // 必须是数组
            ];
        } else {
            // 🇺🇸 美国市场：保持 V5.0 格式
            $final_data = [
                'MPItemFeedHeader' => [
                    'businessUnit' => $business_unit,  // V5.0 新增必需字段
                    'locale' => 'en',
                    'version' => '5.0.20241118-04_39_24-api'  // V5.0 完整版本号
                    // V5.0 移除了: sellingChannel, processMode, subset, subCategory
                ],
                'MPItem' => [$item_data] // 必须是数组
            ];
        }

        // 添加调试日志，记录最终发送的数据结构
        woo_walmart_sync_log('产品映射-最终数据结构', '调试', $final_data, "准备发送到沃尔玛API的数据 (市场: {$market_code}, 版本: " . $final_data['MPItemFeedHeader']['version'] . ")");

        // 额外记录单个商品数据用于调试
        woo_walmart_sync_log('产品映射-单个商品数据', '调试', $item_data, '单个商品的详细数据结构');

        return $final_data;
    }

    /**
     * 获取图片ID类型（用于调试）
     * @param mixed $image_id
     * @return string
     */
    private function get_image_id_type($image_id) {
        if (empty($image_id)) {
            return 'none';
        }

        if (is_numeric($image_id)) {
            return $image_id > 0 ? 'local_numeric' : 'remote_numeric';
        }

        if (is_string($image_id) && strpos($image_id, 'remote_') === 0) {
            return 'remote_string';
        }

        return 'unknown';
    }

    /**
     * 获取图片来源类型（用于调试）
     * @param string $image_url
     * @return string
     */
    private function get_image_source_type($image_url) {
        if (empty($image_url)) {
            return 'empty';
        }

        if (strpos($image_url, 'placeholder') !== false) {
            return 'placeholder';
        }

        $site_url = get_site_url();
        if (strpos($image_url, $site_url) === 0) {
            return 'local';
        }

        return 'remote';
    }

    /**
     * 生成特殊属性值（如日期、重量等）
     * @param string $attribute_name 属性名称
     * @param WC_Product $product 产品对象
     * @param int $fulfillment_lag_time 备货时间
     * @return string|null
     */
    private function generate_special_attribute_value($attribute_name, $product, $fulfillment_lag_time) {
        $attr_lower = strtolower(str_replace(['_', '-'], '', $attribute_name));

        // 首先尝试从API规范获取字段信息
        $field_spec = null;
        if ($this->spec_service && $this->current_product_type_id) {
            $field_spec = $this->spec_service->get_field_spec($this->current_product_type_id, $attribute_name);
        }

        switch ($attr_lower) {
            case 'shippingweight':
                // 运输重量：支持单包裹和多包裹重量计算，5级优先级
                $weight = null;

                // 1. 优先尝试标准的Package Weight字段
                $standard_weight_fields = [
                    'Package Weight',
                    'package_weight',
                    'PackageWeight',
                    'package-weight'
                ];

                foreach ($standard_weight_fields as $field_name) {
                    $attr_weight = $product->get_attribute($field_name);
                    if (!empty($attr_weight)) {
                        $numeric_weight = $this->extract_numeric_weight($attr_weight);
                        if ($numeric_weight > 0) {
                            $weight = $numeric_weight;
                            break; // 找到标准字段就停止
                        }
                    }
                }

                // 2. 如果没有找到标准字段，尝试多包裹重量计算
                if (!$weight) {
                    $weight = $this->calculate_multi_package_weight($product);
                }

                // 3. 如果还是没有找到，尝试Product Weight作为备选
                if (!$weight) {
                    $product_weight_fields = [
                        'Product Weight',
                        'product_weight',
                        'product-weight'
                    ];

                    foreach ($product_weight_fields as $field_name) {
                        $attr_weight = $product->get_attribute($field_name);
                        if (!empty($attr_weight)) {
                            $numeric_weight = $this->extract_numeric_weight($attr_weight);
                            if ($numeric_weight > 0) {
                                $weight = $numeric_weight;
                                break;
                            }
                        }
                    }
                }

                // 4. 🆕 如果还是没有找到，尝试从产品标题和描述中提取包裹重量
                if (!$weight) {
                    $weight = $this->extract_shipping_weight_from_description($product);
                }

                // 5. 最后默认为1
                return $weight ? (string) $weight : '1';

            case 'lagtime':
            case 'fulfillmentlagtime':
                // 备货时间：使用设置的备货时间，API要求数字类型
                $lag_time = get_option('woo_walmart_fulfillment_lag_time', 1);
                // 确保值在允许范围内[0,1]，返回数字类型
                return max(0, min(1, (int)$lag_time));

            case 'maximumorderquantity':
            case 'maximum_order_quantity':
                // 🆕 最大订单数量：默认值20
                return 20;

            case 'minimumorderquantity':
            case 'minimum_order_quantity':
                // 🆕 最小订单数量：默认值1
                return 1;

            case 'sitestartdate':
            case 'startdate':
                // 上架开始日期：同步当天往前推一天（ISO 8601格式）
                return date('Y-m-d\TH:i:s\Z', strtotime('-1 day'));

            case 'siteenddate':
            case 'enddate':
                // 下架结束日期：默认设置为10年后（ISO 8601格式）
                return date('Y-m-d\TH:i:s\Z', strtotime('+10 years'));

            case 'salerestrictions':
                // 销售限制：默认无限制
                return 'NONE';

            case 'assemblyrequired':
                // 是否需要组装：尝试从产品属性获取，默认为false
                $assembly = $product->get_attribute('assembly_required') ?:
                           $product->get_attribute('Assembly Required') ?:
                           $product->get_attribute('需要组装') ?:
                           $product->get_attribute('组装');
                if ($assembly) {
                    // 标准化返回值
                    $assembly_lower = strtolower($assembly);
                    if (in_array($assembly_lower, ['yes', 'true', '是', '需要', '1'])) {
                        return 'true';
                    } else {
                        return 'false';
                    }
                }
                return 'false';

            case 'condition':
                // 商品状态：默认为新品
                return 'New';

            case 'haswrittenwarranty':
            case 'has_written_warranty':
                // 是否有书面保修：尝试从产品属性获取，默认为No
                $warranty = $product->get_attribute('warranty') ?:
                           $product->get_attribute('Warranty') ?:
                           $product->get_attribute('保修');
                return !empty($warranty) ? 'Yes' : 'No';

            case 'isprop65warningrequired':
            case 'is_prop65_warning_required':
                // 是否需要Prop65警告：默认为No
                return 'No';

            case 'netcontent':
            case 'net_content':
                // 净含量：返回正确的对象结构
                return $this->get_net_content_object($product);

            case 'countperpack':
            case 'count_per_pack':
                // 每包数量：默认为1
                return 1;

            case 'multipackquantity':
            case 'multipack_quantity':
                // 多包装数量：默认为1
                return 1;

            case 'electronicsIndicator':
            case 'electronics_indicator':
                // 是否包含电子元件：默认为No
                return 'No';

            case 'chemicalAerosolPesticide':
            case 'chemical_aerosol_pesticide':
                // 是否包含化学品/气雾剂/杀虫剂：默认为No
                return 'No';

            case 'batterytechnologytype':
            case 'battery_technology_type':
                // 电池类型：修复枚举值映射
                $battery_attr = $product->get_attribute('Battery Type') ?:
                               $product->get_attribute('battery_type');

                if ($battery_attr) {
                    $battery_lower = strtolower($battery_attr);
                    if (strpos($battery_lower, 'lithium ion') !== false) return 'Lithium Ion';
                    if (strpos($battery_lower, 'alkaline') !== false) return 'Alkaline';
                    if (strpos($battery_lower, 'nickel') !== false) return 'Nickel Metal Hydride';
                    // 其他类型映射...
                }

                return 'Does Not Contain a Battery';

            case 'shipsinoriginalpackaging':
            case 'ships_in_original_packaging':
                // 是否原包装发货：默认为Yes
                return 'Yes';

            case 'mustshipalone':
            case 'must_ship_alone':
                // 是否必须单独发货：默认为No
                return 'No';

            case 'ispreorder':
            case 'is_preorder':
                // 是否预订：默认为No
                return 'No';

            case 'releasedate':
            case 'release_date':
                // 发布日期：同步当天往前推一天（ISO 8601格式）
                return date('Y-m-d\TH:i:s\Z', strtotime('-1 day'));

            case 'dimensions':
            case 'dimension':
                // 尺寸：组合长宽高
                $length = $product->get_length();
                $width = $product->get_width();
                $height = $product->get_height();
                if ($length && $width && $height) {
                    return "{$length} x {$width} x {$height}";
                }
                return null;

            case 'brand':
                // 品牌：先从WooCommerce品牌属性获取，没有则使用Unbranded
                $brand = $product->get_attribute('brand') ?:
                        $product->get_attribute('Brand') ?:
                        $product->get_attribute('品牌') ?:
                        $product->get_attribute('pa_brand'); // 也尝试产品属性分类法

                $brand = $brand ?: 'Unbranded';
                // V5.0 验证：品牌最多60字符
                return strlen($brand) > 60 ? substr($brand, 0, 60) : $brand;

            case 'shortdescription':
            case 'short_description':
                // 短描述：从产品完整描述格式化
                return $this->validate_field_for_v5('shortDescription', $this->format_product_description($product->get_description(), $product));

            case 'productname':
            case 'product_name':
                // 产品名称：使用产品标题
                $name = $product->get_name();
                if (empty($name)) {
                    $name = $product->get_sku(); // 使用SKU作为后备名称
                }
                return $this->validate_field_for_v5('productName', $name);

            case 'mainimageurl':
            case 'main_image_url':
                // 主图片URL：获取产品主图（支持远程图片）
                $image_id = $product->get_image_id();
                if ($image_id) {
                    if (is_numeric($image_id) && $image_id > 0) {
                        // 本地图片
                        $image_url = wp_get_attachment_image_url($image_id, 'full');
                        if ($image_url) {
                            return $image_url;
                        }
                    } elseif (is_string($image_id) && strpos($image_id, 'remote_') === 0) {
                        // 远程图片：使用与主映射逻辑相同的处理方式
                        $remote_gallery_urls = get_post_meta($product->get_id(), '_remote_gallery_urls', true);
                        if (is_array($remote_gallery_urls) && !empty($remote_gallery_urls)) {
                            $skip_indices = get_post_meta($product->get_id(), '_walmart_skip_image_indices', true);
                            if (!is_array($skip_indices)) {
                                $skip_indices = [];
                            }

                            // 找到第一张未被标记跳过的图片作为主图
                            foreach ($remote_gallery_urls as $index => $remote_url) {
                                if (!in_array($index, $skip_indices) && filter_var($remote_url, FILTER_VALIDATE_URL)) {
                                    return $this->clean_image_url_for_walmart($remote_url);
                                }
                            }
                        }
                    }
                }
                return null;

            case 'netcontent':
            case 'net_content':
                // 净含量：返回正确的对象结构
                return $this->get_net_content_object($product);

            case 'color':
                // 颜色：优先级 产品属性 > 标题提取 > 产品详情提取 > 默认值
                $color = null;

                // 1. 首先尝试从产品属性 "Main Color" 或 "MainColor" 获取
                $color = $product->get_attribute('Main Color') ?:
                        $product->get_attribute('MainColor') ?:
                        $product->get_attribute('main_color');

                // 2. 如果没有找到，从标题中提取颜色词
                if (empty($color)) {
                    $color = $this->extract_color_from_title($product->get_name());
                }

                // 3. 🆕 如果标题中也没有找到，从产品详情中提取颜色
                if (empty($color)) {
                    $color = $this->extract_color_from_description($product);
                }

                // 4. 🆕 如果都没有找到，使用默认值（因为color是必填字段）
                return $color ?: 'As shown in the product picture';

            case 'size':
                // 🆕 尺寸：优先从产品属性获取，如果没有则从标题/描述中提取
                // 1. 首先尝试从产品属性获取
                $size_attr = $product->get_attribute('size') ?:
                            $product->get_attribute('Size') ?:
                            $product->get_attribute('尺寸') ?: null;

                if ($size_attr !== null) {
                    return $size_attr;
                }

                // 2. 如果属性中没有，尝试从标题和描述中提取
                $title = $product->get_name();
                $description = strip_tags($product->get_description() . ' ' . $product->get_short_description());
                $content = $title . ' ' . $description;

                // 正则表达式匹配尺寸格式
                // 匹配格式：数字'数字" x 数字'数字" 或 数字' x 数字' 或 数字'x数字'
                // 示例：3' x 5', 5'2" x 7'6", 8'6" x 10'
                if (preg_match('/(\d+)\'(\d+)?"\s*[x×]\s*(\d+)\'(\d+)?"|(\d+)\'\s*[x×]\s*(\d+)\'(\d+)?"|(\d+)\'(\d+)?"\s*[x×]\s*(\d+)\'|(\d+)\'\s*[x×]\s*(\d+)\'/i', $content, $matches)) {
                    $size = $matches[0];

                    // 检查长度限制（最大500字符）
                    if (strlen($size) <= 500) {
                        return $size;
                    }
                }

                // 如果都没有找到，返回null
                return null;

            case 'material':
                // 材质：API要求JSONArray格式
                $material = null;

                // 1. 首先尝试从产品属性 "Main Material" 或 "MainMaterial" 获取
                $material = $product->get_attribute('Main Material') ?:
                           $product->get_attribute('MainMaterial') ?:
                           $product->get_attribute('main_material');

                // 2. 如果没有找到，从标题中提取材质词
                if (empty($material)) {
                    $material = $this->extract_material_from_title($product->get_name());
                }

                // 3. 转换为数组格式（API要求JSONArray）
                if ($material) {
                    // 如果材质包含多个词，分割成数组
                    $materials = array_map('trim', explode(',', $material));
                    return array_unique($materials);
                }

                // 默认材质
                return ['Wood'];

            case 'bed_type':
                // 床类型：根据产品标题和描述中的关键词自动识别
                return $this->determine_bed_type($product);

            case 'finish':
                // 表面处理：从产品描述提取，无则用主体颜色+材质，再无则用材质或颜色之一
                return $this->extract_product_finish($product);

            case 'keyfeatures':
            case 'key_features':
                // Key Features：从产品描述提取段落，如果不足则智能生成
                return $this->generate_key_features($product);

            // 新增：处理所有之前硬编码的字段
            case 'assembledproductlength':
            case 'assembled_product_length':
                // 组装后长度：API要求JSONObject格式
                $length = $this->parse_product_size_dimension($product, 0) ?: 1;
                return [
                    'measure' => (float) $length,
                    'unit' => 'in'
                ];

            case 'assembledproductwidth':
            case 'assembled_product_width':
                // 组装后宽度：API要求JSONObject格式
                $width = $this->parse_product_size_dimension($product, 1) ?: 1;
                return [
                    'measure' => (float) $width,
                    'unit' => 'in'
                ];

            case 'assembledproductheight':
            case 'assembled_product_height':
                // 组装后高度：API要求JSONObject格式
                $height = $this->parse_product_size_dimension($product, 2) ?: 1;
                return [
                    'measure' => (float) $height,
                    'unit' => 'in'
                ];

            case 'assembledproductweight':
            case 'assembled_product_weight':
                // 组装后重量：从产品属性Product Weight提取重量值，默认1 lb
                $weight = $product->get_attribute('Product Weight') ?:
                         $product->get_attribute('product_weight') ?:
                         $product->get_attribute('Assembled Weight') ?:
                         $product->get_attribute('assembled_weight') ?:
                         $product->get_weight();

                // 如果获取到的是字符串（如"52.00 lb"），提取数字部分
                if (is_string($weight)) {
                    // 使用正则表达式提取数字
                    if (preg_match('/([0-9]+\.?[0-9]*)/', $weight, $matches)) {
                        $weight = (float) $matches[1];
                    } else {
                        $weight = 1; // 如果无法提取数字，使用默认值
                    }
                } elseif (empty($weight) || !is_numeric($weight)) {
                    $weight = 1; // 默认1磅
                } else {
                    $weight = (float) $weight;
                }

                return [
                    'measure' => $weight,
                    'unit' => 'lb'
                ];

            case 'armheight':
            case 'arm_height':
                // 扶手高度：从产品描述中提取扶手高度信息，如果没有则默认为1 in
                return $this->extract_arm_height($product);

            case 'seatdepth':
            case 'seat_depth':
                // 座椅深度：从产品描述提取座位深度数据值，无则默认使用1 in
                return $this->extract_seat_depth($product);

            case 'prop65warningtext':
            case 'prop65_warning_text':
                // Prop65警告文本：从产品属性获取
                return $product->get_attribute('Prop65 Warning') ?:
                       $product->get_attribute('prop65_warning') ?: null;

            case 'staterestrictions':
            case 'state_restrictions':
                // 州限制：API要求JSONObject数组格式，至少需要1个条目
                $restrictions = $product->get_attribute('State Restrictions') ?:
                               $product->get_attribute('state_restrictions');

                if ($restrictions) {
                    if (strtolower($restrictions) === 'none') {
                        // None 表示无州限制，只包含 stateRestrictionsText
                        return [[
                            'stateRestrictionsText' => 'None'
                        ]];
                    } else {
                        // 如果有具体限制，按逗号分割并转换为对象格式
                        $states = array_map('trim', explode(',', $restrictions));
                        $result = [];
                        foreach ($states as $state) {
                            // 确保州代码格式正确（如 "CA - California"）
                            if (!empty($state)) {
                                $result[] = [
                                    'stateRestrictionsText' => 'Illegal for Sale',
                                    'states' => $state
                                ];
                            }
                        }
                        return $result;
                    }
                }

                // 默认返回None（无限制），只包含 stateRestrictionsText
                return [[
                    'stateRestrictionsText' => 'None'
                ]];

            case 'countperpack':
            case 'count_per_pack':
                // 每包数量：从产品属性获取，默认为1
                return $product->get_attribute('Count Per Pack') ?:
                       $product->get_attribute('count_per_pack') ?: 1;

            case 'count':
                // 数量：从产品属性获取，默认为1
                return $product->get_attribute('Count') ?:
                       $product->get_attribute('count') ?: 1;

            case 'manufacturer':
                // 制造商：从产品属性获取
                return $product->get_attribute('Manufacturer') ?:
                       $product->get_attribute('manufacturer') ?: null;

            case 'manufacturerpartnumber':
            case 'manufacturer_part_number':
                // 制造商零件号：从产品属性获取
                return $product->get_attribute('Manufacturer Part Number') ?:
                       $product->get_attribute('manufacturer_part_number') ?:
                       $product->get_attribute('MPN') ?: null;

            case 'modelnumber':
            case 'model_number':
                // 型号：从产品属性获取
                return $product->get_attribute('Model Number') ?:
                       $product->get_attribute('model_number') ?:
                       $product->get_attribute('Model') ?: null;

            case 'piececount':
            case 'piece_count':
                // 件数：从产品属性获取，默认为1
                return $product->get_attribute('Piece Count') ?:
                       $product->get_attribute('piece_count') ?: 1;

            case 'warrantytext':
            case 'warranty_text':
                // 保修文本：从产品属性获取
                return $product->get_attribute('Warranty Text') ?:
                       $product->get_attribute('warranty_text') ?:
                       $product->get_attribute('Warranty') ?: null;

            case 'warrantyurl':
            case 'warranty_url':
                // 保修URL：从产品属性获取
                return $product->get_attribute('Warranty URL') ?:
                       $product->get_attribute('warranty_url') ?: null;

            case 'isprimaryvariant':
            case 'is_primary_variant':
                // 是否主要变体：默认为Yes
                return 'Yes';

            case 'fulfillmentcenterid':
            case 'fulfillment_center_id':
                // 履行中心ID：根据市场选择使用对应的履行中心ID
                return $this->get_market_specific_fulfillment_center_id();



            case 'productidupddate':
            case 'product_id_update':
                // 产品ID更新：默认为No
                return 'No';

            case 'skuupdate':
            case 'sku_update':
                // SKU更新：修复空值问题
                return 'No';

            case 'netcontentstatement':
            case 'net_content_statement':
                // 净含量声明：从产品属性获取，如果没有则返回null（不包含在API请求中）
                return $product->get_attribute('Net Content Statement') ?:
                       $product->get_attribute('net_content_statement') ?:
                       $product->get_attribute('Package Contents') ?:
                       $product->get_attribute('Contents') ?: null;

            case 'backingmaterial':
            case 'backing_material':
                // 🆕 背衬材料：从产品属性获取地毯背衬材料，返回数组格式
                $backing_material = $product->get_attribute('Backing Material') ?:
                                   $product->get_attribute('backing_material') ?:
                                   $product->get_attribute('BackingMaterial') ?:
                                   $product->get_attribute('Backing') ?: null;

                // 如果获取到值，转换为数组格式
                if (!empty($backing_material)) {
                    // 如果包含分隔符，分割成数组
                    if (strpos($backing_material, ';') !== false ||
                        strpos($backing_material, ',') !== false ||
                        strpos($backing_material, '|') !== false) {
                        $materials = preg_split('/[;,|]/', $backing_material);
                        return array_values(array_filter(array_map('trim', $materials)));
                    }
                    // 单个值也转换为数组
                    return [trim($backing_material)];
                }

                // 如果没有值，返回null（不传递此字段）
                return null;

            case 'pilethickness':
            case 'pile_thickness':
                // 🆕 地毯绒毛厚度：从产品标题和描述中匹配关键词
                $title = strtolower($product->get_name());
                $description = strtolower(strip_tags($product->get_description() . ' ' . $product->get_short_description()));
                $content = $title . ' ' . $description;

                // 枚举值列表
                $pile_types = [
                    'Shag Pile' => ['shag pile', 'shag'],
                    'High Pile' => ['high pile', 'thick pile', 'deep pile'],
                    'Low Pile' => ['low pile', 'short pile', 'thin pile'],
                    'Medium Pile' => ['medium pile', 'mid pile'],
                    'Flat Pile' => ['flat pile', 'flatweave', 'flat weave'],
                    'High-Low Pile' => ['high-low pile', 'high low pile', 'multi-level pile']
                ];

                // 匹配关键词
                foreach ($pile_types as $pile_type => $keywords) {
                    foreach ($keywords as $keyword) {
                        if (strpos($content, $keyword) !== false) {
                            return $pile_type;
                        }
                    }
                }

                // 如果没有匹配，返回null
                return null;

            case 'pileheight':
            case 'pile_height':
                // 🆕 地毯绒毛高度：从产品属性获取，返回measurement_object格式
                $pile_height = $product->get_attribute('Pile Height') ?:
                              $product->get_attribute('pile_height') ?:
                              $product->get_attribute('PileHeight') ?: null;

                if (!empty($pile_height)) {
                    // 解析数值和单位
                    if (preg_match('/([0-9.]+)\s*(mm|in|inch|inches)?/i', $pile_height, $matches)) {
                        $measure = (float) $matches[1];
                        $unit = isset($matches[2]) ? strtolower($matches[2]) : 'mm';

                        // 标准化单位
                        if (in_array($unit, ['inch', 'inches'])) {
                            $unit = 'in';
                        }

                        // 确保单位是允许的值
                        if (!in_array($unit, ['mm', 'in'])) {
                            $unit = 'mm'; // 默认使用mm
                        }

                        return [
                            'measure' => $measure,
                            'unit' => $unit
                        ];
                    }
                }

                // 如果没有值，返回null
                return null;

            case 'rugconstruction':
            case 'rug_construction':
                // 🆕 地毯制作方式：从产品标题和描述中匹配关键词
                $title = strtolower($product->get_name());
                $description = strtolower(strip_tags($product->get_description() . ' ' . $product->get_short_description()));
                $content = $title . ' ' . $description;

                // 枚举值列表
                $construction_types = [
                    'Handmade' => ['handmade', 'hand made', 'hand-made', 'hand woven', 'hand-woven', 'handwoven', 'hand crafted', 'handcrafted'],
                    'Machine Made' => ['machine made', 'machine-made', 'power loomed', 'power-loomed']
                ];

                // 优先匹配Handmade（因为手工制作通常是卖点）
                foreach ($construction_types['Handmade'] as $keyword) {
                    if (strpos($content, $keyword) !== false) {
                        return 'Handmade';
                    }
                }

                // 然后匹配Machine Made
                foreach ($construction_types['Machine Made'] as $keyword) {
                    if (strpos($content, $keyword) !== false) {
                        return 'Machine Made';
                    }
                }

                // 如果没有匹配，返回null
                return null;

            case 'rugtechniqueweave':
            case 'rug_technique_weave':
                // 🆕 地毯编织技术：从产品标题和描述中匹配关键词
                $title = strtolower($product->get_name());
                $description = strtolower(strip_tags($product->get_description() . ' ' . $product->get_short_description()));
                $content = $title . ' ' . $description;

                // 枚举值列表
                $technique_types = [
                    'Tufted' => ['tufted', 'tuft'],
                    'Knotted' => ['knotted', 'hand knotted', 'hand-knotted'],
                    'Hooked' => ['hooked', 'hand hooked', 'hand-hooked'],
                    'Flat Weave' => ['flat weave', 'flatweave', 'flat-weave', 'kilim'],
                    'Loomed' => ['loomed', 'power loomed', 'power-loomed'],
                    'Needle Punched' => ['needle punched', 'needle-punched', 'needlepunched'],
                    'Braided' => ['braided', 'braid']
                ];

                // 匹配关键词
                foreach ($technique_types as $technique => $keywords) {
                    foreach ($keywords as $keyword) {
                        if (strpos($content, $keyword) !== false) {
                            return $technique;
                        }
                    }
                }

                // 如果没有匹配，返回null
                return null;

            case 'lampshadefittertype':
            case 'lamp_shade_fitter_type':
                // 🆕 灯罩配件类型：从产品标题和描述中匹配关键词
                $title = strtolower($product->get_name());
                $description = strtolower(strip_tags($product->get_description() . ' ' . $product->get_short_description()));
                $content = $title . ' ' . $description;

                // 枚举值列表
                $fitter_types = [
                    'Clip-On' => ['clip-on', 'clip on', 'clipon', 'clip fitter'],
                    'Spider' => ['spider', 'spider fitter'],
                    'Slip UNO' => ['slip uno', 'slip-uno', 'slipuno', 'uno slip'],
                    'Threaded UNO' => ['threaded uno', 'threaded-uno', 'threadeduno', 'uno threaded']
                ];

                // 匹配关键词
                foreach ($fitter_types as $type => $keywords) {
                    foreach ($keywords as $keyword) {
                        if (strpos($content, $keyword) !== false) {
                            return $type;
                        }
                    }
                }

                // 如果没有匹配，返回null
                return null;

            case 'christmastreefeature':
            case 'christmas_tree_feature':
                // 🆕 圣诞树特征：从产品标题和描述中提取关键词
                $title = strtolower($product->get_name());
                $description = strtolower(strip_tags($product->get_description() . ' ' . $product->get_short_description()));
                $content = $title . ' ' . $description;

                // 特征关键词列表（基于示例值）
                $features = [
                    'Decorated' => ['decorated', 'decoration'],
                    'Hinged' => ['hinged', 'hinge'],
                    'Flocked' => ['flocked', 'flock'],
                    'Potted' => ['potted', 'pot'],
                    'Pre-Lit' => ['pre-lit', 'prelit', 'pre lit', 'lighted'],
                    'Rotating' => ['rotating', 'rotate', 'rotates'],
                    'Twinkling Lights' => ['twinkling lights', 'twinkling', 'twinkle'],
                    'Unlit' => ['unlit', 'un-lit'],
                    'Frosted Lights' => ['frosted lights', 'frosted']
                ];

                // 匹配关键词
                foreach ($features as $feature => $keywords) {
                    foreach ($keywords as $keyword) {
                        if (strpos($content, $keyword) !== false) {
                            return $feature;
                        }
                    }
                }

                // 如果没有匹配，返回null
                return null;

            case 'christmastreeshape':
            case 'christmas_tree_shape':
                // 🆕 圣诞树形状：从产品标题和描述中匹配关键词，返回数组
                $title = strtolower($product->get_name());
                $description = strtolower(strip_tags($product->get_description() . ' ' . $product->get_short_description()));
                $content = $title . ' ' . $description;

                // 形状枚举值列表
                $shapes = [
                    'Teardrop' => ['teardrop', 'tear drop', 'tear-drop'],
                    'Pencil' => ['pencil'],
                    'Full' => ['full'],
                    'Upside Down' => ['upside down', 'upside-down', 'inverted'],
                    'Dress' => ['dress'],
                    'Slim' => ['slim', 'slimline', 'slim-line'],
                    'Spiral' => ['spiral'],
                    'Triangular' => ['triangular', 'triangle'],
                    'Pyramid' => ['pyramid'],
                    'Half' => ['half'],
                    'Corner' => ['corner'],
                    'Topiary' => ['topiary'],
                    'Conical' => ['conical', 'cone']
                ];

                $matched_shapes = [];

                // 匹配所有关键词
                foreach ($shapes as $shape => $keywords) {
                    foreach ($keywords as $keyword) {
                        if (strpos($content, $keyword) !== false) {
                            $matched_shapes[] = $shape;
                            break; // 找到一个匹配就跳出内层循环
                        }
                    }
                }

                // 返回数组或null
                return !empty($matched_shapes) ? $matched_shapes : null;

            case 'christmastreetype':
            case 'christmas_tree_type':
                // 🆕 圣诞树类型：从产品标题和描述中匹配关键词，无匹配则默认 Artificial Christmas Trees
                $title = strtolower($product->get_name());
                $description = strtolower(strip_tags($product->get_description() . ' ' . $product->get_short_description()));
                $content = $title . ' ' . $description;

                // 类型枚举值列表（按优先级排序，更具体的类型在前）
                $types = [
                    'Fresh Cut Christmas Trees' => ['fresh cut', 'fresh-cut', 'real tree', 'live tree', 'natural tree'],
                    'Living Christmas Trees' => ['living tree', 'living', 'potted tree', 'rooted tree'],
                    'Tabletop Christmas Trees' => ['tabletop', 'table top', 'table-top', 'mini tree', 'small tree'],
                    'Artificial Christmas Trees' => ['artificial', 'fake tree', 'faux tree', 'synthetic']
                ];

                // 匹配关键词
                foreach ($types as $type => $keywords) {
                    foreach ($keywords as $keyword) {
                        if (strpos($content, $keyword) !== false) {
                            return $type;
                        }
                    }
                }

                // 默认返回 Artificial Christmas Trees
                return 'Artificial Christmas Trees';

            case 'colordescriptor':
            case 'color_descriptor':
                // 🆕 颜色描述符：从产品标题和描述中匹配关键词，返回数组，无匹配则默认Rainbow
                $title = strtolower($product->get_name());
                $description = strtolower(strip_tags($product->get_description() . ' ' . $product->get_short_description()));
                $content = $title . ' ' . $description;

                // 枚举值列表
                $descriptors = [
                    'Pastel' => ['pastel'],
                    'Rainbow' => ['rainbow', 'multi-color', 'multicolor', 'multi color'],
                    'Neon' => ['neon'],
                    'Metallic' => ['metallic', 'metal'],
                    'Fluorescent' => ['fluorescent'],
                    'Pearlescent' => ['pearlescent', 'pearl'],
                    'Glitter' => ['glitter', 'glittery', 'sparkle']
                ];

                $matched_descriptors = [];

                // 匹配所有关键词
                foreach ($descriptors as $descriptor => $keywords) {
                    foreach ($keywords as $keyword) {
                        if (strpos($content, $keyword) !== false) {
                            $matched_descriptors[] = $descriptor;
                            break;
                        }
                    }
                }

                // 如果没有匹配，返回默认值Rainbow
                return !empty($matched_descriptors) ? $matched_descriptors : ['Rainbow'];

            case 'framecolorconfiguration':
            case 'frame_color_configuration':
                // 🆕 框架颜色配置：从产品标题和描述中提取颜色，返回数组
                $title = $product->get_name();
                $description = strip_tags($product->get_description() . ' ' . $product->get_short_description());
                $content = $title . ' ' . $description;

                // 常见颜色列表（基于示例）
                $colors = ['Black', 'Brown', 'Silver', 'White', 'Gold', 'Gray', 'Grey', 'Beige', 'Bronze', 'Copper'];
                $matched_colors = [];

                foreach ($colors as $color) {
                    if (stripos($content, $color) !== false) {
                        // 限制每个颜色最大40字符
                        if (strlen($color) <= 40) {
                            $matched_colors[] = $color;
                        }
                    }
                }

                // 去重
                $matched_colors = array_unique($matched_colors);

                // 如果没有匹配，返回null
                return !empty($matched_colors) ? array_values($matched_colors) : null;

            case 'hasnrtllistingcertification':
            case 'has_nrtl_listing_certification':
                // 🆕 NRTL认证：默认返回No
                return 'No';

            case 'ibretailpackaging':
            case 'ib_retail_packaging':
                // 🆕 零售包装：从产品标题和描述中匹配关键词，无匹配则默认Single Piece
                $title = strtolower($product->get_name());
                $description = strtolower(strip_tags($product->get_description() . ' ' . $product->get_short_description()));
                $content = $title . ' ' . $description;

                // 枚举值列表（按优先级排序）
                $packaging_types = [
                    'Value Pack' => ['value pack', 'value-pack'],
                    'Set' => ['set', ' sets'],
                    'Bundle' => ['bundle'],
                    'Kit' => ['kit'],
                    'Combo Pack' => ['combo pack', 'combo-pack'],
                    'Pair' => ['pair', '2-pack', '2 pack'],
                    'Bonus Pack' => ['bonus pack', 'bonus-pack']
                ];

                // 匹配关键词
                foreach ($packaging_types as $type => $keywords) {
                    foreach ($keywords as $keyword) {
                        if (strpos($content, $keyword) !== false) {
                            return $type;
                        }
                    }
                }

                // 默认返回 Single Piece
                return 'Single Piece';

            case 'iscollectible':
            case 'is_collectible':
                // 🆕 是否收藏品：从产品标题和描述中判断，无明确说明则默认Yes
                $title = strtolower($product->get_name());
                $description = strtolower(strip_tags($product->get_description() . ' ' . $product->get_short_description()));
                $content = $title . ' ' . $description;

                // 非收藏品关键词
                $non_collectible_keywords = ['not collectible', 'non-collectible', 'everyday use', 'daily use'];

                foreach ($non_collectible_keywords as $keyword) {
                    if (strpos($content, $keyword) !== false) {
                        return 'No';
                    }
                }

                // 默认返回 Yes
                return 'Yes';

            case 'lightfunctions':
            case 'light_functions':
                // 🆕 灯光功能：从产品标题和描述中匹配关键词，返回数组，无匹配则默认Constant
                $title = strtolower($product->get_name());
                $description = strtolower(strip_tags($product->get_description() . ' ' . $product->get_short_description()));
                $content = $title . ' ' . $description;

                // 枚举值列表
                $functions = [
                    'Chasing' => ['chasing', 'chase'],
                    'Color Changing' => ['color changing', 'color-changing', 'multi-color changing'],
                    'Twinkling' => ['twinkling', 'twinkle'],
                    'Pulsing' => ['pulsing', 'pulse'],
                    'Constant' => ['constant', 'steady'],
                    'Fading' => ['fading', 'fade']
                ];

                $matched_functions = [];

                // 匹配所有关键词
                foreach ($functions as $function => $keywords) {
                    foreach ($keywords as $keyword) {
                        if (strpos($content, $keyword) !== false) {
                            $matched_functions[] = $function;
                            break;
                        }
                    }
                }

                // 如果没有匹配，返回默认值Constant
                return !empty($matched_functions) ? $matched_functions : ['Constant'];

            case 'lightbulbcolor':
            case 'light_bulb_color':
                // 🆕 灯泡颜色：从产品标题和描述中提取颜色，最大400字符
                $title = $product->get_name();
                $description = strip_tags($product->get_description() . ' ' . $product->get_short_description());
                $content = $title . ' ' . $description;

                // 常见灯泡颜色列表（基于示例）
                $bulb_colors = [
                    'Beige', 'Off-White', 'Red', 'Gold', 'Pink', 'Multicolor', 'Multi-color',
                    'Black', 'Purple', 'Blue', 'Yellow', 'Bronze', 'Brown', 'Gray', 'Grey',
                    'Silver', 'Green', 'Orange', 'White', 'Warm White', 'Cool White'
                ];

                $matched_colors = [];

                foreach ($bulb_colors as $color) {
                    if (stripos($content, $color) !== false) {
                        $matched_colors[] = $color;
                    }
                }

                // 去重并限制长度
                $matched_colors = array_unique($matched_colors);
                $color_string = implode(';', $matched_colors);

                // 限制最大400字符
                if (strlen($color_string) > 400) {
                    $color_string = substr($color_string, 0, 400);
                }

                // 如果没有匹配，返回null
                return !empty($color_string) ? $color_string : null;

            case 'lightbulbtype':
            case 'light_bulb_type':
                // 🆕 灯泡类型：从产品标题和描述中匹配关键词
                $title = strtolower($product->get_name());
                $description = strtolower(strip_tags($product->get_description() . ' ' . $product->get_short_description()));
                $content = $title . ' ' . $description;

                // 枚举值列表（LED优先）
                if (strpos($content, 'led') !== false) {
                    return 'LED';
                }

                if (strpos($content, 'incandescent') !== false) {
                    return 'Incandescent';
                }

                // 如果没有匹配，返回null
                return null;

            case 'numberoflights':
            case 'number_of_lights':
                // 🆕 灯的数量：从产品标题和描述中提取数字
                $title = $product->get_name();
                $description = strip_tags($product->get_description() . ' ' . $product->get_short_description());
                $content = $title . ' ' . $description;

                // 匹配模式：数字 + lights/light/leds/led
                if (preg_match('/(\d+)\s*(?:lights?|leds?)/i', $content, $matches)) {
                    $number = intval($matches[1]);
                    // 验证范围
                    if ($number >= 0 && $number <= 100000000000000000) {
                        return $number;
                    }
                }

                // 如果没有匹配，返回null
                return null;

            case 'treetype':
            case 'tree_type':
                // 🆕 树木类型：从产品标题和描述中匹配关键词，无匹配则默认Fir
                $title = strtolower($product->get_name());
                $description = strtolower(strip_tags($product->get_description() . ' ' . $product->get_short_description()));
                $content = $title . ' ' . $description;

                // 枚举值列表
                $tree_types = [
                    'Fir' => ['fir', 'douglas fir', 'fraser fir', 'balsam fir'],
                    'Spruce' => ['spruce', 'norway spruce', 'blue spruce'],
                    'Pine' => ['pine', 'scotch pine', 'white pine']
                ];

                // 匹配关键词
                foreach ($tree_types as $type => $keywords) {
                    foreach ($keywords as $keyword) {
                        if (strpos($content, $keyword) !== false) {
                            return $type;
                        }
                    }
                }

                // 默认返回 Fir（最常用）
                return 'Fir';

            case 'alphanumericcharacter':
            case 'alphanumeric_character':
                // 🆕 字母数字字符：从产品标题和描述中提取字母数字字符（如A、9、42）
                // 最大40字符，用于字母或数字产品（如门牌号）
                $title = $product->get_name();
                $description = strip_tags($product->get_description() . ' ' . $product->get_short_description());
                $content = $title . ' ' . $description;

                // 优先匹配特定上下文中的字母数字字符
                // 1. 门牌号：Number 42, #42, No. 42
                if (preg_match('/(?:number|#|no\.?)\s*([A-Z0-9]{1,10})/i', $content, $matches)) {
                    return strlen($matches[1]) <= 40 ? $matches[1] : null;
                }

                // 2. 字母产品：Letter A, Letter B
                if (preg_match('/letter\s+([A-Z])\b/i', $content, $matches)) {
                    return $matches[1];
                }

                // 3. 纯数字（2-4位）：用于门牌号等
                if (preg_match('/\b(\d{2,4})\b/', $content, $matches)) {
                    return $matches[1];
                }

                // 如果没有匹配，返回null
                return null;

            case 'subject':
                // 🆕 主题：从产品标题和描述中提取产品主题或描绘内容
                // 返回数组格式，每项最大4000字符
                $title = $product->get_name();
                $description = strip_tags($product->get_description() . ' ' . $product->get_short_description());
                $content = $title . ' ' . $description;

                // 常见主题关键词列表
                // 注意：按照从具体到通用的顺序排列，优先匹配完整短语
                $subject_keywords = [
                    // 完整短语（优先匹配）
                    'Sitting Safari Adorable Giraffe', 'Farmhouse Windmill', 'Frisky Dogs High Scottish Terrier',
                    'Big Ben', 'Eiffel Tower', 'Statue of Liberty', 'Golden Gate Bridge',
                    'Farm Animals',
                    // 动物主题
                    'Safari', 'Giraffe', 'Elephant', 'Lion', 'Tiger', 'Bear', 'Deer', 'Horse', 'Dog', 'Cat',
                    // 建筑和农场主题
                    'Farmhouse', 'Windmill', 'Barn', 'Tractor',
                    // 花卉主题（支持单复数）
                    'Floral', 'Flowers', 'Flower', 'Rose', 'Roses', 'Sunflower', 'Sunflowers', 'Tulip', 'Tulips', 'Daisy', 'Daisies',
                    // 自然景观
                    'Ocean', 'Beach', 'Sea', 'Waves', 'Lighthouse', 'Sailboat',
                    'Mountain', 'Mountains', 'Forest', 'Trees', 'Nature', 'Landscape',
                    // 风格（仅保留明确的装饰风格）
                    'Abstract', 'Geometric',
                    'Vintage', 'Retro',
                    // 节日主题
                    'Christmas', 'Halloween', 'Easter', 'Thanksgiving',
                    // 其他明确主题
                    'Sports', 'Music', 'Travel', 'Coffee', 'Wine'
                ];

                $matched_subjects = [];
                foreach ($subject_keywords as $keyword) {
                    // 使用单词边界匹配，避免匹配子字符串
                    // 例如：避免"application"匹配"cat"，"wall art"匹配"art"
                    $pattern = '/\b' . preg_quote($keyword, '/') . '\b/i';
                    if (preg_match($pattern, $content)) {
                        // 限制每项最大4000字符
                        if (strlen($keyword) <= 4000) {
                            $matched_subjects[] = $keyword;
                        }
                    }
                }

                // 如果有匹配，返回数组（去重）
                if (!empty($matched_subjects)) {
                    return array_values(array_unique($matched_subjects));
                }

                // 如果没有匹配，返回null
                return null;

            case 'walldecalandstickertype':
            case 'wall_decal_and_sticker_type':
                // 🆕 墙贴类型：从产品标题和描述中匹配关键词
                $title = strtolower($product->get_name());
                $description = strtolower(strip_tags($product->get_description() . ' ' . $product->get_short_description()));
                $content = $title . ' ' . $description;

                // 枚举值列表
                $decal_types = [
                    'Wall Decals' => ['wall decal', 'wall decals', 'decal', 'decals', 'removable decal'],
                    'Wall Stickers' => ['wall sticker', 'wall stickers', 'sticker', 'stickers', 'peel and stick']
                ];

                // 匹配关键词（按优先级）
                foreach ($decal_types as $type => $keywords) {
                    foreach ($keywords as $keyword) {
                        if (strpos($content, $keyword) !== false) {
                            return $type;
                        }
                    }
                }

                // 如果没有匹配，返回null
                return null;

            case 'plantpotandplantertype':
            case 'plant_pot_and_planter_type':
                // 🆕 植物盆栽类型：从产品标题和描述中匹配关键词
                $title = strtolower($product->get_name());
                $description = strtolower(strip_tags($product->get_description() . ' ' . $product->get_short_description()));
                $content = $title . ' ' . $description;

                // 枚举值列表（按优先级排序）
                $planter_types = [
                    'Plant Planter' => ['plant planter', 'planter box', 'flower planter', 'planter'],
                    'Plant Pot' => ['plant pot', 'flower pot', 'pot', 'planting pot']
                ];

                // 匹配关键词（按优先级）
                foreach ($planter_types as $type => $keywords) {
                    foreach ($keywords as $keyword) {
                        if (strpos($content, $keyword) !== false) {
                            return $type;
                        }
                    }
                }

                // 如果没有匹配，返回null
                return null;

            case 'dooropeningstyle':
            case 'door_opening_style':
                // 🆕 门开启样式：从产品标题和描述中匹配关键词 - 2025-10-30
                $title = strtolower($product->get_name());
                $description = strtolower(strip_tags($product->get_description() . ' ' . $product->get_short_description()));
                $content = $title . ' ' . $description;

                // 枚举值列表（按优先级排序）
                $door_opening_styles = [
                    'Lift Open' => ['lift open', 'lift-open', 'lift up', 'lift-up'],
                    'Swing Open' => ['swing open', 'swing-open', 'swing door', 'hinged door'],
                    'Sliding' => ['sliding', 'slide open', 'sliding door', 'barn door']
                ];

                // 匹配关键词（按优先级）
                foreach ($door_opening_styles as $style => $keywords) {
                    foreach ($keywords as $keyword) {
                        if (strpos($content, $keyword) !== false) {
                            return $style;
                        }
                    }
                }

                // 如果没有匹配，返回null
                return null;

            case 'cabinettype':
            case 'cabinet_type':
                // 🆕 柜子类型：从产品标题和描述中匹配关键词 - 2025-10-30
                $title = strtolower($product->get_name());
                $description = strtolower(strip_tags($product->get_description() . ' ' . $product->get_short_description()));
                $content = $title . ' ' . $description;

                // 枚举值列表（按优先级排序 - 更具体的关键词优先）
                $cabinet_types = [
                    'Over-the-Toilet Cabinets' => ['over-the-toilet', 'over the toilet'],
                    'Sink Base Cabinets' => ['sink base cabinet', 'sink cabinet', 'under sink cabinet'],
                    'Drawer Base Cabinets' => ['drawer base cabinet', 'base drawer cabinet'],
                    'Double Oven Cabinets' => ['double oven cabinet', 'double-oven cabinet'],
                    'Single Oven Cabinets' => ['single oven cabinet'],
                    'Microwave Cabinets' => ['microwave cabinet', 'microwave storage'],
                    'Wall Cabinets' => ['wall cabinet', 'wall-mounted cabinet', 'wall mounted cabinet', 'hanging cabinet'],
                    'Corner Cabinets' => ['corner cabinet', 'corner storage'],
                    'Base Cabinets' => ['base cabinet', 'floor cabinet', 'lower cabinet']
                ];

                // 匹配关键词（按优先级）
                foreach ($cabinet_types as $type => $keywords) {
                    foreach ($keywords as $keyword) {
                        if (strpos($content, $keyword) !== false) {
                            return $type;
                        }
                    }
                }

                // 如果没有匹配，返回null
                return null;

            case 'doorstyle':
            case 'door_style':
                // 🆕 门样式：从产品标题和描述中匹配关键词 - 2025-10-30
                $title = strtolower($product->get_name());
                $description = strtolower(strip_tags($product->get_description() . ' ' . $product->get_short_description()));
                $content = $title . ' ' . $description;

                // 枚举值列表（按优先级排序）
                $door_styles = [
                    'Shaker' => ['shaker', 'shaker style', 'shaker door'],
                    'Flat Panel' => ['flat panel', 'flat-panel', 'slab door', 'flat door'],
                    'Recessed Panel' => ['recessed panel', 'recessed-panel', 'inset panel'],
                    'Louvered' => ['louvered', 'louver', 'slat door'],
                    'Raised Panel' => ['raised panel', 'raised-panel'],
                    'Beadboard' => ['beadboard', 'bead board', 'beaded'],
                    'Open Panel' => ['open panel', 'open-panel'],
                    'Glass Panel' => ['glass panel', 'glass door', 'glass-panel'],
                    'Arched' => ['arched', 'arch door', 'cathedral']
                ];

                // 匹配关键词（按优先级）
                foreach ($door_styles as $style => $keywords) {
                    foreach ($keywords as $keyword) {
                        if (strpos($content, $keyword) !== false) {
                            return $style;
                        }
                    }
                }

                // 如果没有匹配，返回null
                return null;

            case 'drawerdepth':
            case 'drawer_depth':
                // 🆕 抽屉深度：从产品标题和描述中提取深度尺寸 - 2025-10-30
                $title = $product->get_name();
                $description = strip_tags($product->get_description() . ' ' . $product->get_short_description());
                $content = $title . ' ' . $description;

                // 匹配关键词：drawer depth、depth of drawer等
                if (preg_match('/drawer\s+depth[:\s]+([0-9.]+)\s*(?:in|inch|inches)?/i', $content, $matches)) {
                    $measure = trim($matches[1]);
                    if (strlen($measure) <= 80) {
                        return ['measure' => $measure, 'unit' => 'in'];
                    }
                }

                // 如果没有匹配，返回null
                return null;

            case 'drawerheight':
            case 'drawer_height':
                // 🆕 抽屉高度：从产品标题和描述中提取高度尺寸 - 2025-10-30
                $title = $product->get_name();
                $description = strip_tags($product->get_description() . ' ' . $product->get_short_description());
                $content = $title . ' ' . $description;

                // 匹配关键词：drawer height、height of drawer等
                if (preg_match('/drawer\s+height[:\s]+([0-9.]+)\s*(?:in|inch|inches)?/i', $content, $matches)) {
                    $measure = floatval($matches[1]);
                    if ($measure >= 0 && $measure <= 100000000000000000) {
                        return ['measure' => $measure, 'unit' => 'in'];
                    }
                }

                // 如果没有匹配，返回null
                return null;

            case 'drawerwidth':
            case 'drawer_width':
                // 🆕 抽屉宽度：从产品标题和描述中提取宽度尺寸 - 2025-10-30
                $title = $product->get_name();
                $description = strip_tags($product->get_description() . ' ' . $product->get_short_description());
                $content = $title . ' ' . $description;

                // 匹配关键词：drawer width、width of drawer等
                if (preg_match('/drawer\s+width[:\s]+([0-9.]+)\s*(?:in|inch|inches)?/i', $content, $matches)) {
                    $measure = floatval($matches[1]);
                    if ($measure >= 0 && $measure <= 100000000000000000) {
                        return ['measure' => $measure, 'unit' => 'in'];
                    }
                }

                // 如果没有匹配，返回null
                return null;

            case 'hasdoors':
            case 'has_doors':
                // 🆕 是否有门：从产品标题和描述中判断 - 2025-10-30
                $title = strtolower($product->get_name());
                $description = strtolower(strip_tags($product->get_description() . ' ' . $product->get_short_description()));
                $content = $title . ' ' . $description;

                // 匹配关键词：with door、has door、door included等
                $yes_keywords = ['with door', 'has door', 'door included', 'doors included', 'with doors'];
                $no_keywords = ['no door', 'without door', 'doorless', 'open shelf', 'open shelving'];

                // 优先匹配"有门"关键词
                foreach ($yes_keywords as $keyword) {
                    if (strpos($content, $keyword) !== false) {
                        return 'Yes';
                    }
                }

                // 匹配"无门"关键词
                foreach ($no_keywords as $keyword) {
                    if (strpos($content, $keyword) !== false) {
                        return 'No';
                    }
                }

                // 如果没有明确信息，返回null
                return null;

            case 'mounttype':
            case 'mount_type':
                // 🆕 安装类型：从产品标题和描述中匹配关键词 - 2025-10-30
                // 注意：这是必填字段，必须返回数组格式
                $title = strtolower($product->get_name());
                $description = strtolower(strip_tags($product->get_description() . ' ' . $product->get_short_description()));
                $content = $title . ' ' . $description;

                // 枚举值列表（按优先级排序）
                $mount_types = [
                    'Wall Mount' => ['wall mount', 'wall-mount', 'wall mounted', 'hang on wall'],
                    'Corner Mount' => ['corner mount', 'corner-mount', 'corner mounted'],
                    'Recessed Mount' => ['recessed mount', 'recessed-mount', 'recessed', 'built-in'],
                    'Freestanding' => ['freestanding', 'free standing', 'free-standing', 'standalone', 'stand alone']
                ];

                // 匹配关键词（按优先级）
                foreach ($mount_types as $type => $keywords) {
                    foreach ($keywords as $keyword) {
                        if (strpos($content, $keyword) !== false) {
                            return [$type];  // 返回数组格式
                        }
                    }
                }

                // 如果没有匹配，根据产品类型判断
                // 默认返回 Freestanding（最常见的类型）
                return ['Freestanding'];

            case 'orientation':
                // 🆕 产品方向：从产品标题和描述中匹配关键词 - 2025-10-30
                $title = strtolower($product->get_name());
                $description = strtolower(strip_tags($product->get_description() . ' ' . $product->get_short_description()));
                $content = $title . ' ' . $description;

                // 匹配关键词
                $vertical_keywords = ['vertical', 'vertically', 'upright', 'portrait'];
                $horizontal_keywords = ['horizontal', 'horizontally', 'landscape'];

                // 优先匹配 Vertical
                foreach ($vertical_keywords as $keyword) {
                    if (strpos($content, $keyword) !== false) {
                        return 'Vertical';
                    }
                }

                // 匹配 Horizontal
                foreach ($horizontal_keywords as $keyword) {
                    if (strpos($content, $keyword) !== false) {
                        return 'Horizontal';
                    }
                }

                // 如果没有匹配，返回默认值 Horizontal
                return 'Horizontal';

            case 'rugsize':
            case 'rug_size':
                // 🆕 地毯尺寸：从产品标题和描述中提取尺寸信息
                // 注意：保留完整的英尺+英寸格式，不进行单位换算
                $title = $product->get_name();
                $description = strip_tags($product->get_description() . ' ' . $product->get_short_description());
                $content = $title . ' ' . $description;

                // 正则表达式匹配尺寸格式
                // 匹配格式：数字'数字" x 数字'数字" 或 数字' x 数字' 或 数字'x数字'
                // 示例：3' x 5', 5'2" x 7'6", 8'6" x 10'
                // 使用与 size 字段相同的正则表达式
                if (preg_match('/(\d+)\'(\d+)?"\s*[x×]\s*(\d+)\'(\d+)?"|(\d+)\'\s*[x×]\s*(\d+)\'(\d+)?"|(\d+)\'(\d+)?"\s*[x×]\s*(\d+)\'|(\d+)\'\s*[x×]\s*(\d+)\'/i', $content, $matches)) {
                    // 返回完整匹配的尺寸字符串（保留英尺和英寸）
                    $rug_size = $matches[0];

                    // 检查长度限制（最大200字符）
                    if (strlen($rug_size) <= 200) {
                        return $rug_size;
                    }
                }

                // 如果没有匹配，返回null
                return null;

            case 'swatchimages':
            case 'swatch_images':
                // 🆕 swatchImages：获取产品主图URL并转换为对象数组格式
                // Walmart API 要求格式：[{"swatchImageUrl": "url", "swatchVariantAttribute": "attr"}]

                $image_url = '';

                // 1. 尝试获取主图URL（支持本地和远程）
                $product_image_id = $product->get_image_id();

                if ($product_image_id) {
                    if (is_numeric($product_image_id) && $product_image_id > 0) {
                        // 本地主图（数字ID）
                        $image_url = wp_get_attachment_url($product_image_id);
                    } else if (is_string($product_image_id) && strpos($product_image_id, 'remote_') === 0) {
                        // 远程主图（remote_前缀）
                        // 远程图片URL存储在 meta key: _remote_image_url_{remote_id}
                        $remote_url_meta = get_post_meta($product->get_id(), '_remote_image_url_' . $product_image_id, true);
                        if (!empty($remote_url_meta) && filter_var($remote_url_meta, FILTER_VALIDATE_URL)) {
                            $image_url = $remote_url_meta;
                        }
                    }
                }

                // 2. 如果主图获取失败，从产品图库获取第一张图片（支持本地和远程）
                if (empty($image_url)) {
                    $gallery_image_ids = $product->get_gallery_image_ids();

                    if (!empty($gallery_image_ids)) {
                        foreach ($gallery_image_ids as $gallery_id) {
                            if (is_numeric($gallery_id) && $gallery_id > 0) {
                                // 本地图片
                                $gallery_url = wp_get_attachment_url($gallery_id);
                                if (!empty($gallery_url)) {
                                    $image_url = $gallery_url;
                                    break;
                                }
                            } else if (is_string($gallery_id) && strpos($gallery_id, 'remote_') === 0) {
                                // 远程图片（remote_前缀）
                                $remote_url_meta = get_post_meta($product->get_id(), '_remote_image_url_' . $gallery_id, true);
                                if (!empty($remote_url_meta) && filter_var($remote_url_meta, FILTER_VALIDATE_URL)) {
                                    $image_url = $remote_url_meta;
                                    break;
                                }
                            }
                        }
                    }
                }

                // 3. 清理URL（移除查询参数）
                if (!empty($image_url)) {
                    $image_url = $this->clean_image_url_for_walmart($image_url);
                }

                // 4. 转换为对象数组格式
                if (!empty($image_url)) {
                    return [
                        [
                            'swatchImageUrl' => $image_url,
                            'swatchVariantAttribute' => 'color' // 默认使用 color
                        ]
                    ];
                }

                // 如果没有图片，返回null（不发送此字段）
                return null;

            case 'sportsleague':
            case 'sports_league':
                // 体育联盟：从产品属性获取
                return $product->get_attribute('Sports League') ?:
                       $product->get_attribute('sports_league') ?: null;

            case 'sportsteam':
            case 'sports_team':
                // 体育团队：从产品属性获取
                return $product->get_attribute('Sports Team') ?:
                       $product->get_attribute('sports_team') ?: null;

            case 'thirdpartyaccreditationsymbolonproductpackagecode':
            case 'third_party_accreditation_symbol':
                // 第三方认证符号：从产品属性获取
                return $product->get_attribute('Third Party Accreditation') ?:
                       $product->get_attribute('third_party_accreditation') ?: null;

            case 'variantgroupid':
            case 'variant_group_id':
                // 变体组ID：从产品属性获取
                return $product->get_attribute('Variant Group ID') ?:
                       $product->get_attribute('variant_group_id') ?: null;

            case 'variantattributenames':
            case 'variant_attribute_names':
                // 变体属性名称：默认为空数组
                return [];



            case 'productsecondaryimageurl':
            case 'product_secondary_image_url':
                // 次要图片URL：从产品图库获取（包含远程图库和占位符补足）
                $gallery_image_ids = $product->get_gallery_image_ids();
                $gallery_images = [];

                // 使用与第146-169行完全相同的图库处理逻辑
                if (!empty($gallery_image_ids)) {
                    foreach ($gallery_image_ids as $gallery_image_id) {
                        if ($gallery_image_id > 0) {
                            // 处理本地图库图片
                            $gallery_image_url = wp_get_attachment_url($gallery_image_id);
                            if ($gallery_image_url && filter_var($gallery_image_url, FILTER_VALIDATE_URL)) {
                                $gallery_images[] = $gallery_image_url;
                            }
                        } else if ($gallery_image_id < 0) {
                            // 处理GigaCloud远程图库（负数ID）
                            $remote_gallery_urls = get_post_meta($product->get_id(), '_remote_gallery_urls', true);
                            if (is_array($remote_gallery_urls) && !empty($remote_gallery_urls)) {
                                // 计算在远程图库数组中的索引
                                $remote_index = abs($gallery_image_id + 1000);
                                if (isset($remote_gallery_urls[$remote_index])) {
                                    $remote_url = $remote_gallery_urls[$remote_index];
                                    if (filter_var($remote_url, FILTER_VALIDATE_URL)) {
                                        $gallery_images[] = $remote_url;
                                    }
                                }
                            }
                        }
                    }
                }

                // 如果没有通过图库ID获取到图片，直接尝试从远程图库元数据获取（与第172-181行相同）
                if (empty($gallery_images)) {
                    $remote_gallery_urls = get_post_meta($product->get_id(), '_remote_gallery_urls', true);
                    if (is_array($remote_gallery_urls) && !empty($remote_gallery_urls)) {
                        foreach ($remote_gallery_urls as $remote_url) {
                            if (filter_var($remote_url, FILTER_VALIDATE_URL)) {
                                $gallery_images[] = $remote_url;
                            }
                        }
                    }
                }

                // 去重处理
                $gallery_images = array_unique($gallery_images);
                $original_count = count($gallery_images);

                // 占位符补足逻辑
                // 只处理3-4张的情况，2张以下代表产品资料不全，不进行补足
                if ($original_count == 3) {
                    // 3张图片：补足占位符1和占位符2到5张
                    $placeholder_1 = get_option('woo_walmart_placeholder_image_1', '');
                    $placeholder_2 = get_option('woo_walmart_placeholder_image_2', '');

                    if (!empty($placeholder_1) && filter_var($placeholder_1, FILTER_VALIDATE_URL)) {
                        $gallery_images[] = $placeholder_1;
                    }
                    if (!empty($placeholder_2) && filter_var($placeholder_2, FILTER_VALIDATE_URL)) {
                        $gallery_images[] = $placeholder_2;
                    }

                    woo_walmart_sync_log('动态映射-图片补足-3张', '信息', [
                        'original_count' => $original_count,
                        'final_count' => count($gallery_images),
                        'placeholder_1' => $placeholder_1,
                        'placeholder_2' => $placeholder_2
                    ], '副图3张，添加占位符图片1和2补足至5张', $product->get_id());

                } else if ($original_count == 4) {
                    // 4张图片：补足占位符1到5张
                    $placeholder_1 = get_option('woo_walmart_placeholder_image_1', '');

                    if (!empty($placeholder_1) && filter_var($placeholder_1, FILTER_VALIDATE_URL)) {
                        $gallery_images[] = $placeholder_1;
                    }

                    woo_walmart_sync_log('动态映射-图片补足-4张', '信息', [
                        'original_count' => $original_count,
                        'final_count' => count($gallery_images),
                        'placeholder_1' => $placeholder_1
                    ], '副图4张，添加占位符图片1补足至5张', $product->get_id());
                }

                // 如果图片少于3张或大于等于5张，保持现有规则不变
                if ($original_count < 3) {
                    woo_walmart_sync_log('动态映射-图片不足-警告', '警告', [
                        'original_count' => $original_count,
                        'final_count' => count($gallery_images),
                        'product_id' => $product->get_id(),
                        'sku' => $product->get_sku()
                    ], "副图少于3张，不进行补足，产品资料不全，建议用户添加更多产品图片", $product->get_id());
                }

                return $gallery_images;

            case 'inventoryavailabilitydate':
            case 'inventory_availability_date':
                // 预订可用日期：从产品属性获取
                return $product->get_attribute('Inventory Availability Date') ?:
                       $product->get_attribute('inventory_availability_date') ?: null;

            // 新增：处理其他缺失的字段
            case 'colorcategory':
            case 'color_category':
                // 颜色类别：基于主颜色推断，匹配沃尔玛API标准颜色选项
                $color = $this->generate_special_attribute_value('color', $product, $fulfillment_lag_time);
                if ($color) {
                    $color_lower = strtolower(trim($color));

                    // 沃尔玛API标准颜色选项匹配
                    // Bronze,Brown,Gold,Gray,Blue,Multicolor,Black,Orange,Clear,Red,Silver,Pink,White,Purple,Yellow,Beige,Off-White,Green

                    // 精确匹配（优先级最高）
                    $exact_matches = [
                        'bronze' => 'Bronze',
                        'brown' => 'Brown',
                        'gold' => 'Gold',
                        'gray' => 'Gray',
                        'grey' => 'Gray',
                        'blue' => 'Blue',
                        'multicolor' => 'Multicolor',
                        'multi-color' => 'Multicolor',
                        'multi color' => 'Multicolor',
                        'black' => 'Black',
                        'orange' => 'Orange',
                        'clear' => 'Clear',
                        'red' => 'Red',
                        'silver' => 'Silver',
                        'pink' => 'Pink',
                        'white' => 'White',
                        'purple' => 'Purple',
                        'yellow' => 'Yellow',
                        'beige' => 'Beige',
                        'off-white' => 'Off-White',
                        'off white' => 'Off-White',
                        'offwhite' => 'Off-White',
                        'green' => 'Green'
                    ];

                    if (isset($exact_matches[$color_lower])) {
                        return $exact_matches[$color_lower];
                    }

                    // 包含匹配（如果精确匹配失败）
                    if (strpos($color_lower, 'bronze') !== false) return 'Bronze';
                    if (strpos($color_lower, 'brown') !== false) return 'Brown';
                    if (strpos($color_lower, 'gold') !== false) return 'Gold';
                    if (strpos($color_lower, 'gray') !== false || strpos($color_lower, 'grey') !== false) return 'Gray';
                    if (strpos($color_lower, 'blue') !== false) return 'Blue';
                    if (strpos($color_lower, 'multi') !== false) return 'Multicolor';
                    if (strpos($color_lower, 'black') !== false) return 'Black';
                    if (strpos($color_lower, 'orange') !== false) return 'Orange';
                    if (strpos($color_lower, 'clear') !== false || strpos($color_lower, 'transparent') !== false) return 'Clear';
                    if (strpos($color_lower, 'red') !== false) return 'Red';
                    if (strpos($color_lower, 'silver') !== false) return 'Silver';
                    if (strpos($color_lower, 'pink') !== false) return 'Pink';
                    if (strpos($color_lower, 'white') !== false) return 'White';
                    if (strpos($color_lower, 'purple') !== false || strpos($color_lower, 'violet') !== false) return 'Purple';
                    if (strpos($color_lower, 'yellow') !== false) return 'Yellow';
                    if (strpos($color_lower, 'beige') !== false || strpos($color_lower, 'cream') !== false) return 'Beige';
                    if (strpos($color_lower, 'off') !== false && strpos($color_lower, 'white') !== false) return 'Off-White';
                    if (strpos($color_lower, 'green') !== false) return 'Green';

                    // 如果都不匹配，默认返回 Multicolor
                    return 'Multicolor';
                }
                return null;

            case 'itemsincluded':
            case 'items_included':
                // 包含物品：从产品描述提取属性数据值，区分包含和不包含，无则提取产品主体
                return $this->extract_items_included($product);

            case 'maximumloadweight':
            case 'maximum_load_weight':
                // 最大承重：从产品属性获取
                $weight = $product->get_attribute('Maximum Load Weight') ?:
                         $product->get_attribute('maximum_load_weight') ?:
                         $product->get_attribute('Max Weight') ?:
                         $product->get_attribute('Load Capacity');

                if ($weight) {
                    // 提取数字部分
                    preg_match('/(\d+(?:\.\d+)?)/', $weight, $matches);
                    if (!empty($matches[1])) {
                        return $matches[1] . ' lbs';
                    }
                }
                return null;

            // case 'occasion':
                // 注释：occasion字段已改为default_value类型，不再通过此方法处理
                // 现在使用预设的节日场合列表作为默认值

            case 'price':
                // 价格：使用本地产品价格值（最多两位小数）
                $price = $product->get_price();
                if (is_numeric($price) && $price > 0) {
                    return round((float) $price, 2);
                }
                // 如果没有价格，返回1作为默认值
                return 1;

            case 'assemblyinstructions':
            case 'assembly_instructions':
                // 组装说明：从产品文档标签获取产品说明书链接
                $assembly_url = null;

                // 检查是否存在产品文档管理器类
                if (class_exists('Simple_Product_Document_Manager')) {
                    $doc_manager = new Simple_Product_Document_Manager();
                    $documents = $doc_manager->get_product_documents($product->get_id());

                    // 查找manual类型的文档（文档按类型分组）
                    if (!empty($documents) && isset($documents['manuals'])) {
                        $manuals = $documents['manuals'];
                        if (!empty($manuals)) {
                            // 使用第一个说明书
                            $first_manual = reset($manuals);
                            $assembly_url = $doc_manager->get_document_url($first_manual);
                        }
                    }
                }

                // 如果没有找到文档，尝试从产品属性获取
                if (!$assembly_url) {
                    $assembly_url = $product->get_attribute('Assembly Instructions URL') ?:
                                  $product->get_attribute('assembly_instructions_url') ?:
                                  get_post_meta($product->get_id(), '_assembly_instructions_url', true);
                }

                // 验证URL格式
                if ($assembly_url && filter_var($assembly_url, FILTER_VALIDATE_URL)) {
                    return $assembly_url;
                } else {
                    // 如果没有有效URL，使用占位符PDF URL
                    return "https://via.placeholder.com/800x600.pdf?text=Assembly+Instructions";
                }

            case 'quantity':
                // 库存数量：获取WooCommerce产品的库存数量
                $stock_quantity = $product->get_stock_quantity();
                // 如果没有设置库存管理或库存为null，返回0
                return $stock_quantity !== null ? intval($stock_quantity) : 0;

            case 'bedframeadjustability':
                // 床架可调性：从标题和描述中智能提取关键词
                return $this->extract_bed_frame_adjustability($product);

            case 'diningchairtype':
            case 'dining_chair_type':
                // 餐椅类型：从产品描述自动提取对应关键词，如果没有则使用默认值：Dining Side Chairs
                return $this->extract_dining_chair_type($product);

            case 'seatbackstyle':
                // 椅背样式：根据产品描述关键词自动识别
                return $this->determine_seat_back_style($product);

            case 'seatbackcushionstyle':
                // 椅背坐垫样式：从产品描述提取对应的枚举值，如果没有则留空不传递此字段
                return $this->extract_seat_back_cushion_style($product);

            case 'decorativepillowtype':
                // 装饰枕类型：从产品描述提取对应的枚举值，如果没有则默认为Bolster Pillow
                return $this->extract_decorative_pillow_type($product);

            case 'isfilled':
                // 是否填充：从产品描述判断产品是否填充，如果没有明确信息则默认为Yes
                return $this->extract_is_filled($product);

            case 'seatbackthickness':
                // 椅背厚度：从产品描述提取或使用默认值
                return $this->extract_seat_dimension($product, 'thickness', 1);

            case 'seatbackwidth':
                // 椅背宽度：从产品描述提取或使用默认值
                return $this->extract_seat_dimension($product, 'back_width', 1);

            case 'seatcolor':
            case 'seat_color':
                // 座椅颜色：从产品描述提取或使用产品主体颜色
                return $this->extract_seat_color($product);

            case 'seatmaterial':
            case 'seat_material':
                // 座椅材质：从产品描述提取或使用产品主体材质（必须返回数组）
                return $this->extract_seat_material($product);

            case 'sizedescriptor':
                // 尺寸描述符：从产品标题和描述中提取尺寸相关关键词
                return $this->extract_size_descriptor($product);

            case 'sofa_and_loveseat_design':
            case 'sofaandloveseatdesign':
                // 沙发设计风格：从产品标题和描述中提取设计风格关键词（必须返回数组）
                return $this->extract_sofa_loveseat_design($product);

            case 'sofa_bed_size':
            case 'sofabedsize':
                // 沙发床尺寸：从产品标题和描述中提取床尺寸关键词
                return $this->extract_sofa_bed_size($product);

            case 'upholstered':
                // 软垫覆盖：从产品描述自动提取软垫相关关键词，返回Yes/No，默认为No
                return $this->extract_upholstered_status($product);

            case 'productline':
            case 'product_line':
                // 🆕 产品线：优先从属性读取，否则从分类/标题/类型提取，返回数组格式
                // 优先级1: 从产品属性获取
                $product_line = $product->get_attribute('Product Line') ?:
                               $product->get_attribute('product_line') ?:
                               $product->get_attribute('Collection') ?:
                               $product->get_attribute('Series');

                if (!empty($product_line)) {
                    // 确保每项不超过400字符
                    $trimmed = substr(trim($product_line), 0, 400);
                    return [$trimmed];
                }

                // 优先级2: 从分类、标题、类型提取
                $extracted_line = $this->extract_product_line($product);
                if (!empty($extracted_line)) {
                    // 确保每项不超过400字符
                    $trimmed = substr(trim($extracted_line), 0, 400);
                    return [$trimmed];
                }

                // 如果都没有找到，返回默认值
                return ['Standard'];

            case 'seatwidth':
                // 座椅宽度：从产品描述提取或使用默认值
                return $this->extract_seat_dimension($product, 'width', 1);

            case 'seatbackheight':
                // 椅背高度：从产品描述提取或使用默认值
                return $this->extract_seat_dimension($product, 'back_height', 1);

            case 'seatheight':
                // 座椅高度：从产品描述提取或使用默认值
                return $this->extract_seat_dimension($product, 'height', 1);

            case 'seatingcapacity':
                // 座椅容量：从产品描述提取或使用默认值
                return $this->extract_seating_capacity($product);

            case 'recommendedlocations':
                // 推荐使用位置：从产品描述自动提取
                return $this->extract_recommended_locations($product);

            // 🆕 桌子相关字段处理
            case 'basestyle':
            case 'base_style':
                // 底座样式：从产品标题和描述中匹配底座样式关键词
                return $this->extract_base_style($product);

            case 'basecolor':
                // 底座颜色：从产品标题和描述中提取底座颜色信息
                return $this->extract_base_color($product);

            case 'basematerial':
                // 底座材质：从产品标题和描述中提取底座材质信息
                return $this->extract_base_material($product);

            case 'isextendable':
            case 'is_extendable':
                // 是否可扩展：从产品标题和描述中匹配可扩展相关关键词
                return $this->extract_is_extendable($product);

            case 'tableleaftype':
            case 'table_leaf_type':
                // 桌叶类型：从产品标题和描述中匹配桌叶类型关键词
                return $this->extract_table_leaf_type($product);

            case 'tableshape':
            case 'table_shape':
                // 桌子形状：从产品标题和描述中匹配桌子形状关键词
                return $this->extract_table_shape($product);

            case 'tabletopmaterial':
            case 'table_top__material':
                // 桌面材质：从产品标题和描述中提取桌面材质信息
                return $this->extract_table_top_material($product);

            case 'topcolor':
            case 'top_color':
                // 顶部颜色：从产品标题和描述中提取顶部颜色信息
                return $this->extract_top_color($product);

            case 'shape':
                // 🆕 通用形状：从产品标题和描述中智能识别产品的物理形状
                return $this->extract_product_shape($product);

            case 'numberofdoors':
            case 'number_of_doors':
                // 🆕 门数量：从产品标题和描述中提取门的数量
                return $this->extract_number_of_doors($product);

            case 'numberoftiers':
            case 'number_of_tiers':
                // 🆕 层数：从产品标题和描述中提取层数或级数
                return $this->extract_number_of_tiers($product);

            case 'tablecolor':
                // 🆕 桌子颜色：从产品描述提取颜色信息
                return $this->extract_table_color($product);

            case 'tabletoptype':
                // 🆕 桌面类型：从产品描述提取桌面类型
                return $this->extract_table_top_type($product);

            case 'legcolor':
            case 'leg_color':
                // 腿部颜色：自动提取椅子或桌子腿的颜色，无则使用默认值Color as shown
                return $this->extract_leg_color($product);

            case 'legmaterial':
            case 'leg_material':
                // 腿部材质：自动提取椅子或桌子腿材质，无则使用默认值Please see product description material
                return $this->extract_leg_material($product);

            case 'pattern':
                // 图案：从产品描述提取图案，无则用主体颜色，再无则用默认值color
                return $this->extract_product_pattern($product);

            case 'tableheight':
                // 🆕 桌子高度：从产品描述提取高度信息
                return $this->extract_table_height($product);

            // 🆕 柜体相关字段
            case 'cabinetcolor':
            case 'cabinet_color':
                // 柜体颜色：从标题和产品描述提取对应的数据值
                return $this->extract_cabinet_color($product);

            case 'cabinetmaterial':
            case 'cabinet_material':
                // 柜体材质：从标题和产品描述提取对应的数据值
                return $this->extract_cabinet_material($product);

            case 'hardwarefinish':
                // 五金表面处理：从标题和产品描述提取对应的数据值
                return $this->extract_hardware_finish($product);

            case 'recommendedrooms':
                // 推荐房间：默认使用多个选项
                return $this->generate_recommended_rooms($product);

            // 🆕 分类特定features字段
            case 'features':
                // 特性：根据分类ID动态获取枚举值并智能匹配
                return $this->extract_features_by_category_id($product);

            // 🆕 通用字段拓展 - 2025-10-12
            case 'framefinish':
                // 框架表面处理：从产品描述提取或使用产品颜色
                return $this->extract_frame_finish($product);

            case 'handlewidth':
                // 把手宽度：从产品描述提取或默认1 in
                return $this->extract_handle_width($product);

            case 'handlematerial':
                // 把手材质：从产品描述提取
                return $this->extract_handle_material($product);

            case 'kitchenservingandstoragecarttype':
                // 厨房推车类型：从产品描述提取或默认Serving Cart
                return $this->extract_kitchen_cart_type($product);

            case 'numberofhooks':
                // 挂钩数量：从产品描述提取或默认0
                return $this->extract_number_of_hooks($product);

            case 'numberofwheels':
                // 轮子数量：从产品描述提取或默认0
                return $this->extract_number_of_wheels($product);

            case 'topmaterial':
                // 顶部材质：从产品描述提取
                return $this->extract_top_material($product);

            // 🆕 通用字段拓展 - 2025-10-12 (第二批)
            case 'diningfurnituresettype':
                // 餐厅家具套装类型：从产品描述提取或默认Dining Table with Chair
                return $this->extract_dining_furniture_set_type($product);

            case 'overallchairdepth':
                // 椅子整体深度：从产品描述提取
                return $this->extract_overall_chair_depth($product);

            case 'overallchairheight':
                // 椅子整体高度：从产品描述提取
                return $this->extract_overall_chair_height($product);

            case 'overallchairwidth':
                // 椅子整体宽度：从产品描述提取
                return $this->extract_overall_chair_width($product);

            case 'seatbackheightdescriptor':
                // 座椅靠背高度描述：从产品描述提取
                return $this->extract_seat_back_height_descriptor($product);

            case 'seatingcapacitywithleaf':
                // 带扩展叶板的座位容量：从产品描述提取或默认1
                return $this->extract_seating_capacity_with_leaf($product);

            case 'tablelength':
                // 桌子长度：从产品描述提取或默认1 in
                return $this->extract_table_length($product);

            case 'tablewidth':
                // 桌子宽度：从产品描述提取或默认1 in
                return $this->extract_table_width($product);

            default:
                // 首先尝试调用morenzhi.php中的自动生成逻辑
                if (!function_exists('handle_auto_generate_field')) {
                    $morenzhi_file = WOO_WALMART_SYNC_PATH . 'morenzhi.php';
                    if (file_exists($morenzhi_file)) {
                        require_once $morenzhi_file;
                    }
                }

                if (function_exists('handle_auto_generate_field')) {
                    $morenzhi_value = handle_auto_generate_field($product, $attribute_name);
                    if ($morenzhi_value !== null && $morenzhi_value !== '') {
                        return $morenzhi_value;
                    }
                }

                // 如果morenzhi.php没有处理，尝试使用API规范生成默认值
                if ($field_spec) {
                    // 如果字段是必需的但没有值，使用默认值
                    if ($field_spec['required']) {
                        $default_value = $this->spec_service->get_field_default_value($this->current_product_type_id, $attribute_name);
                        if ($default_value !== null) {
                            return $default_value;
                        }
                    }

                    // 根据字段类型生成合适的默认值
                    if (isset($field_spec['type'])) {
                        $field_type = $field_spec['type'];

                        if ($field_type === 'measurement_object') {
                            // measurement_object类型：尝试从产品内容中提取实际数值
                            $extracted_measurement = $this->extract_measurement_from_product($product, $attribute_name, $field_spec);
                            if ($extracted_measurement !== null) {
                                return $extracted_measurement;
                            }

                            // 如果无法提取实际数值，返回null而不是随意的默认值
                            // 让上层逻辑决定如何处理（可能使用API规范的默认值或跳过该字段）
                            return null;

                        } elseif ($field_spec['allowed_values'] && !empty($field_spec['allowed_values'])) {
                            // 其他类型：智能从产品描述和标题中匹配枚举值
                            $matched_value = $this->extract_enum_value_from_product($product, $attribute_name, $field_spec['allowed_values']);
                            if ($matched_value !== null) {
                                return $matched_value;
                            }
                            // 如果无法从产品内容中提取，返回null而不是随意选择
                        }
                    }
                }

                return null;
        }
    }

    /**
     * 智能从产品描述和标题中提取测量值
     * @param WC_Product $product 产品对象
     * @param string $field_name 字段名称
     * @param array $field_spec 字段规范
     * @return array|null 测量对象或null
     */
    private function extract_measurement_from_product($product, $field_name, $field_spec) {
        $field_lower = strtolower($field_name);

        // 座椅深度特殊处理：只从指定的三个属性获取，没有就使用默认值1
        if ($field_lower === 'seat_depth' || $field_lower === 'seatdepth') {
            $seat_depth_attrs = ['Seat Depth', 'seat_depth', 'SeatDepth'];

            foreach ($seat_depth_attrs as $attr_name) {
                $attr_value = $product->get_attribute($attr_name);
                if (!empty($attr_value) && $attr_value !== 'not specified') {
                    // 尝试解析数字和单位
                    if (preg_match('/(\d+(?:\.\d+)?)\s*([a-zA-Z]+)/', $attr_value, $matches)) {
                        return [
                            'measure' => (float)$matches[1],
                            'unit' => $matches[2]
                        ];
                    }
                }
            }

            // 没有找到有效的座椅深度数据，使用默认值1
            return [
                'measure' => 1.0,
                'unit' => 'in'
            ];
        }

        // 其他字段的处理逻辑保持不变
        // 首先尝试从产品属性中获取
        $attribute_value = $product->get_attribute($field_name);
        if (!empty($attribute_value)) {
            // 如果属性值已经是正确格式，直接返回
            if (is_array($attribute_value) && isset($attribute_value['measure']) && isset($attribute_value['unit'])) {
                return $attribute_value;
            }

            // 尝试解析属性值中的数字和单位
            if (preg_match('/(\d+(?:\.\d+)?)\s*([a-zA-Z]+)/', $attribute_value, $matches)) {
                return [
                    'measure' => (float)$matches[1],
                    'unit' => $matches[2]
                ];
            }
        }

        // 根据字段名称尝试从相关属性中提取
        $related_attributes = $this->get_related_attributes_for_field($field_name);
        foreach ($related_attributes as $attr_name) {
            $attr_value = $product->get_attribute($attr_name);
            if (!empty($attr_value)) {
                // 尝试解析数字和单位
                if (preg_match('/(\d+(?:\.\d+)?)\s*([a-zA-Z]+)/', $attr_value, $matches)) {
                    return [
                        'measure' => (float)$matches[1],
                        'unit' => $matches[2]
                    ];
                }
            }
        }

        // 从产品标题和描述中提取
        $content = $product->get_name() . ' ' . $product->get_description() . ' ' . $product->get_short_description();

        // 根据字段类型查找相关的数字模式
        $patterns = $this->get_measurement_patterns_for_field($field_name);
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $content, $matches)) {
                $unit = $this->determine_unit_for_field($field_name, $field_spec);
                return [
                    'measure' => (float)$matches[1],
                    'unit' => $unit
                ];
            }
        }

        return null; // 无法从产品内容中提取测量值
    }

    /**
     * 获取字段相关的属性名称
     * @param string $field_name 字段名称
     * @return array 相关属性名称
     */
    private function get_related_attributes_for_field($field_name) {
        $field_lower = strtolower($field_name);

        // 座椅深度需要专门处理，只从特定属性获取
        if ($field_lower === 'seat_depth' || $field_lower === 'seatdepth') {
            return ['Seat Depth', 'seat_depth', 'SeatDepth'];
        } elseif ($field_lower === 'arm_height' || $field_lower === 'armheight') {
            return ['Arm Height', 'arm_height', 'Armrest Height', '扶手高度'];
        } elseif (strpos($field_lower, 'height') !== false) {
            return ['Product Size', 'Height', 'height', 'Assembled Height'];
        } elseif (strpos($field_lower, 'width') !== false) {
            return ['Product Size', 'Width', 'width', 'Assembled Width'];
        } elseif (strpos($field_lower, 'length') !== false || strpos($field_lower, 'depth') !== false) {
            return ['Product Size', 'Length', 'length', 'Depth', 'depth'];
        } elseif (strpos($field_lower, 'weight') !== false) {
            return ['Product Weight', 'Weight', 'weight', 'Package Weight'];
        }

        return [];
    }

    /**
     * 获取字段的测量模式
     * @param string $field_name 字段名称
     * @return array 正则表达式模式
     */
    private function get_measurement_patterns_for_field($field_name) {
        $field_lower = strtolower($field_name);

        if ($field_lower === 'arm_height' || $field_lower === 'armheight') {
            return [
                '/arm\s*height[:\s]*(\d+(?:\.\d+)?)\s*(?:in|inch|inches|ft|feet)?/i',
                '/armrest\s*height[:\s]*(\d+(?:\.\d+)?)\s*(?:in|inch|inches|ft|feet)?/i',
                '/扶手高度[:\s]*(\d+(?:\.\d+)?)\s*(?:in|inch|inches|ft|feet|英寸|英尺)?/i',
                '/arm[:\s]*(\d+(?:\.\d+)?)\s*(?:in|inch|inches|ft|feet)?\s*high/i'
            ];
        } elseif (strpos($field_lower, 'weight') !== false) {
            return [
                '/(\d+(?:\.\d+)?)\s*(?:lbs?|pounds?|kg|kilograms?)/i',
                '/weight[:\s]*(\d+(?:\.\d+)?)/i'
            ];
        } else {
            // 尺寸相关
            return [
                '/(\d+(?:\.\d+)?)\s*(?:in|inch|inches|cm|ft|feet)/i',
                '/(\d+(?:\.\d+)?)\s*[×x]\s*\d+(?:\.\d+)?/i' // 尺寸格式中的第一个数字
            ];
        }
    }

    /**
     * 确定字段的单位
     * @param string $field_name 字段名称
     * @param array $field_spec 字段规范
     * @return string 单位
     */
    private function determine_unit_for_field($field_name, $field_spec) {
        // 从API规范中提取默认单位
        if (isset($field_spec['allowed_values'])) {
            foreach ($field_spec['allowed_values'] as $value) {
                if (strpos($value, 'DEFAULT_UNIT:') === 0) {
                    return substr($value, 13);
                }
            }
        }

        // 根据字段名称推断单位
        $field_lower = strtolower($field_name);
        if ($field_lower === 'arm_height' || $field_lower === 'armheight') {
            return 'in'; // arm_height 默认单位为英寸
        } elseif (strpos($field_lower, 'weight') !== false) {
            return 'lb';
        } else {
            return 'in';
        }
    }

    /**
     * 根据分类映射获取CA市场的subCategory
     * @param string $walmart_category_name 分类映射值（从数据库walmart_category_path字段获取）
     * @return string subCategory值（符合CA_MP_ITEM_INTL_SPEC.json枚举值）
     */
    private function get_ca_sub_category($walmart_category_name) {
        // CA API 有效的 subCategory 枚举值列表
        $valid_sub_categories = [
            'furniture_other', 'home_other', 'electronics_other', 'clothing_other',
            'toys_other', 'sport_and_recreation_other', 'baby_other', 'baby_furniture',
            'baby_clothing', 'baby_toys', 'baby_food', 'health_and_beauty_electronics',
            'food_and_beverage_other', 'jewelry_other', 'other_other', 'bedding',
            'storage', 'cases_and_bags', 'building_supply', 'tires', 'computer_components',
            'decorations_and_favors', 'hardware', 'child_car_seats', 'electronics_cables',
            'plumbing_and_hvac', 'video_games', 'safety_and_emergency', 'books_and_magazines',
            'tools', 'alcoholic_beverages', 'carriers_and_accessories_other', 'animal_food',
            'cleaning_and_chemical', 'ceremonial_clothing_and_accessories', 'music_cases_and_bags',
            'computers', 'grills_and_outdoor_cooking', 'personal_care', 'animal_accessories',
            'weapons', 'electrical', 'medical_aids', 'music', 'art_and_craft_other',
            'medicine_and_supplements', 'wheels_and_wheel_components', 'footwear_other',
            'tv_shows', 'animal_health_and_grooming', 'video_projectors', 'cameras_and_lenses',
            'sound_and_recording', 'watercraft', 'funeral', 'watches_other', 'large_appliances',
            'costumes', 'instrument_accessories', 'optical', 'cycling', 'gift_supply_and_awards',
            'fuels_and_lubricants', 'vehicle_other', 'animal_other', 'optics',
            'garden_and_patio_other', 'cell_phones', 'musical_instruments',
            'printers_scanners_and_imaging', 'movies', 'office_other', 'gift_cards',
            'tvs_and_video_displays', 'tools_and_hardware_other'
        ];

        // 1. 如果值已经是有效的subCategory格式，直接使用
        $normalized = strtolower($walmart_category_name);
        if (in_array($normalized, $valid_sub_categories)) {
            return $normalized;
        }

        // 2. 旧格式映射表（兼容 CA_FURNITURE 等旧格式）
        $legacy_mapping = [
            'CA_FURNITURE' => 'furniture_other',
            'CA_HOME' => 'home_other',
            'CA_ELECTRONICS' => 'electronics_other',
            'CA_CLOTHING' => 'clothing_other',
            'CA_TOYS' => 'toys_other',
            'CA_SPORTS' => 'sport_and_recreation_other',
            'CA_BABY' => 'baby_other',
            'CA_HEALTH' => 'health_and_beauty_electronics',
            'CA_FOOD' => 'food_and_beverage_other',
            'CA_JEWELRY' => 'jewelry_other',
            'CA_OTHER' => 'other_other',
        ];

        if (isset($legacy_mapping[$walmart_category_name])) {
            return $legacy_mapping[$walmart_category_name];
        }

        // 3. 模糊匹配
        if (strpos($normalized, 'furniture') !== false) return 'furniture_other';
        if (strpos($normalized, 'home') !== false) return 'home_other';
        if (strpos($normalized, 'electronic') !== false) return 'electronics_other';
        if (strpos($normalized, 'cloth') !== false) return 'clothing_other';
        if (strpos($normalized, 'toy') !== false) return 'toys_other';
        if (strpos($normalized, 'sport') !== false) return 'sport_and_recreation_other';
        if (strpos($normalized, 'baby') !== false) return 'baby_other';

        // 4. 默认返回 other_other
        woo_walmart_sync_log('CA subCategory映射', '警告', [
            'input' => $walmart_category_name,
            'fallback' => 'other_other'
        ], "无法识别的分类，使用默认值other_other");

        return 'other_other';
    }

    /**
     * 根据市场选择获取对应的履行中心ID
     * @return string|null 履行中心ID
     */
    private function get_market_specific_fulfillment_center_id() {
        // 获取用户设置的履行中心ID（保持不变）
        $user_fulfillment_center_id = get_option('woo_walmart_fulfillment_center_id', '');

        // 获取当前市场设置
        $business_unit = get_option('woo_walmart_business_unit', 'WALMART_US');
        $default_market = get_option('woo_walmart_default_market', 'US');

        // 根据市场选择使用对应的履行中心ID
        switch ($business_unit) {
            case 'WALMART_US':
                // 美国市场：使用用户设置的履行中心ID
                return !empty($user_fulfillment_center_id) ? $user_fulfillment_center_id : null;

            case 'WALMART_CA':
                // 加拿大市场：优先使用用户设置的履行中心ID
                if (!empty($user_fulfillment_center_id)) {
                    return $user_fulfillment_center_id; // 信任用户设置，支持任何格式的履行中心ID
                }
                // 如果用户未设置，使用安全的默认值（自发货模式）
                return 'SELLER_FULFILLED';

            case 'WALMART_MX':
                // 墨西哥市场：优先使用用户设置的履行中心ID
                if (!empty($user_fulfillment_center_id)) {
                    return $user_fulfillment_center_id; // 信任用户设置，支持任何格式的履行中心ID
                }
                // 如果用户未设置，使用安全的默认值（自发货模式）
                return 'SELLER_FULFILLED';

            case 'WALMART_CL':
                // 智利市场：优先使用用户设置的履行中心ID
                if (!empty($user_fulfillment_center_id)) {
                    return $user_fulfillment_center_id; // 信任用户设置，支持任何格式的履行中心ID
                }
                // 如果用户未设置，使用安全的默认值（自发货模式）
                return 'SELLER_FULFILLED';

            default:
                // 默认使用用户设置的履行中心ID
                return !empty($user_fulfillment_center_id) ? $user_fulfillment_center_id : null;
        }
    }

    /**
     * 智能从产品描述和标题中提取枚举值
     * @param WC_Product $product 产品对象
     * @param string $field_name 字段名称
     * @param array $allowed_values 允许的枚举值
     * @return string|null 匹配的枚举值或null
     */
    private function extract_enum_value_from_product($product, $field_name, $allowed_values) {
        // 获取产品的文本内容
        $product_title = strtolower($product->get_name());
        $product_description = strtolower($product->get_description());
        $product_short_description = strtolower($product->get_short_description());

        // 合并所有文本内容
        $content = $product_title . ' ' . $product_description . ' ' . $product_short_description;

        // 过滤掉配置相关的值
        $valid_enum_values = [];
        foreach ($allowed_values as $value) {
            if (!empty($value) && !preg_match('/^(UNITS:|DEFAULT_UNIT:)/', $value)) {
                $valid_enum_values[] = $value;
            }
        }

        // 按长度排序，优先匹配更具体的值
        usort($valid_enum_values, function($a, $b) {
            return strlen($b) - strlen($a);
        });

        // 在产品内容中查找匹配的枚举值
        foreach ($valid_enum_values as $enum_value) {
            $search_value = strtolower($enum_value);

            // 直接匹配
            if (strpos($content, $search_value) !== false) {
                return $enum_value;
            }

            // 处理连字符和空格的变体
            $variants = [
                str_replace('-', ' ', $search_value),
                str_replace(' ', '-', $search_value),
                str_replace(['-', ' '], '', $search_value)
            ];

            foreach ($variants as $variant) {
                if (strpos($content, $variant) !== false) {
                    return $enum_value;
                }
            }
        }

        return null; // 无法从产品内容中提取，返回null
    }

    /**
     * 智能判断字段值是否为空
     * @param mixed $value 字段值
     * @return bool 是否为空
     */
    private function is_empty_field_value( $value ) {
        // null值为空
        if ( is_null( $value ) ) {
            return true;
        }

        // 空字符串为空
        if ( is_string( $value ) && trim( $value ) === '' ) {
            return true;
        }

        // 空数组为空
        if ( is_array( $value ) && empty( $value ) ) {
            return true;
        }

        // 数字0不为空（价格可能为0）
        if ( is_numeric( $value ) ) {
            return false;
        }

        // 布尔值不为空
        if ( is_bool( $value ) ) {
            return false;
        }

        // 其他情况不为空
        return false;
    }

    /**
     * 转换字段数据类型以符合API要求
     * @param string $field_name 字段名
     * @param mixed $value 原始值
     * @param string $format_override 用户指定的格式（可选）
     * @return mixed 转换后的值
     */
    private function convert_field_data_type($field_name, $value, $format_override = null) {
        // 特殊字段的null值处理：这些字段需要转换为默认值
        $special_null_fields = ['fillmaterial', 'recommendedlocations', 'cleaningcareandmaintenance', 'numberofdoors', 'number_of_doors', 'numberoftiers', 'number_of_tiers', 'quantity', 'seatmaterial', 'seat_material', 'seatcolor', 'seat_color'];
        if (is_null($value) && !in_array(strtolower($field_name), $special_null_fields)) {
            return null;
        }





        // 只有在用户明确指定了非auto格式时，才使用用户指定的格式进行转换
        // 如果没有指定格式或格式为'auto'，则使用原有的自动检测逻辑
        if ($format_override && $format_override !== 'auto' && !empty($format_override)) {
            return $this->convert_by_user_format($field_name, $value, $format_override);
        }

        // 特殊字段处理（在API规范转换之前）
        switch (strtolower($field_name)) {
            case 'maximumorderquantity':
            case 'maximum_order_quantity':
                // 🆕 最大订单数量：确保返回整数类型
                return (int) $value;

            case 'minimumorderquantity':
            case 'minimum_order_quantity':
                // 🆕 最小订单数量：确保返回整数类型
                return (int) $value;

            case 'occasion':
                // occasion字段：将分号分隔的字符串转换为数组
                if (is_string($value) && !empty($value)) {
                    $occasion_array = preg_split('/[;,|]/', $value);
                    return array_map('trim', array_filter($occasion_array));
                } elseif (is_array($value)) {
                    return array_filter($value);
                }
                return [];

            case 'bed_frame_adjustability':
            case 'bedframeadjustability':
                // bed_frame_adjustability字段：确保返回数组格式
                if (is_array($value)) {
                    // 如果已经是数组，过滤空值并返回
                    return array_values(array_filter($value));
                } elseif (is_string($value) && !empty($value)) {
                    // 如果是字符串，尝试解析为数组
                    $adjustability_array = preg_split('/[;,|]/', $value);
                    $adjustability_array = array_map('trim', $adjustability_array);
                    // 只保留有效的枚举值
                    $valid_values = ['Adjustable Foot', 'Adjustable Head'];
                    $filtered_array = array_intersect($adjustability_array, $valid_values);
                    return array_values($filtered_array);
                }
                // 如果是null或空值，返回null（不发送到API）
                return null;

            case 'fillmaterial':
                // fillMaterial字段：确保返回数组格式
                if (is_array($value)) {
                    // 如果已经是数组，过滤空值并返回
                    return array_values(array_filter($value));
                } elseif (is_string($value) && !empty($value)) {
                    // 如果是字符串，转换为数组
                    if (strpos($value, ';') !== false) {
                        return array_filter(array_map('trim', explode(';', $value)));
                    } elseif (strpos($value, ',') !== false) {
                        return array_filter(array_map('trim', explode(',', $value)));
                    } else {
                        return [trim($value)];
                    }
                }
                // 如果是null或空值，返回默认值
                return ['Foam'];

            case 'backingmaterial':
            case 'backing_material':
                // 🆕 backing_material字段：确保返回数组格式
                if (is_array($value)) {
                    // 如果已经是数组，过滤空值并返回
                    $filtered = array_values(array_filter($value));
                    return !empty($filtered) ? $filtered : null;
                } elseif (is_string($value) && !empty($value)) {
                    // 如果是字符串，转换为数组
                    if (strpos($value, ';') !== false) {
                        return array_filter(array_map('trim', explode(';', $value)));
                    } elseif (strpos($value, ',') !== false) {
                        return array_filter(array_map('trim', explode(',', $value)));
                    } elseif (strpos($value, '|') !== false) {
                        return array_filter(array_map('trim', explode('|', $value)));
                    } else {
                        return [trim($value)];
                    }
                }
                // 如果是null或空值，返回null（不传递此字段）
                return null;

            case 'pilethickness':
            case 'pile_thickness':
                // 🆕 pile_thickness字段：确保返回字符串格式
                if (is_string($value) && !empty($value)) {
                    return trim($value);
                }
                return null;

            case 'pileheight':
            case 'pile_height':
                // 🆕 pileHeight字段：确保返回measurement_object格式
                if (is_array($value) && isset($value['measure']) && isset($value['unit'])) {
                    return [
                        'measure' => (float) $value['measure'],
                        'unit' => (string) $value['unit']
                    ];
                }
                return null;

            case 'rugconstruction':
            case 'rug_construction':
                // 🆕 rug_construction字段：确保返回字符串格式
                if (is_string($value) && !empty($value)) {
                    return trim($value);
                }
                return null;

            case 'rugtechniqueweave':
            case 'rug_technique_weave':
                // 🆕 rug_technique_weave字段：确保返回字符串格式
                if (is_string($value) && !empty($value)) {
                    return trim($value);
                }
                return null;

            case 'lampshadefittertype':
            case 'lamp_shade_fitter_type':
                // 🆕 lamp_shade_fitter_type字段：确保返回字符串格式并验证枚举值
                if (is_string($value) && !empty($value)) {
                    $valid_values = ['Clip-On', 'Spider', 'Slip UNO', 'Threaded UNO'];
                    $trimmed = trim($value);
                    return in_array($trimmed, $valid_values, true) ? $trimmed : null;
                }
                return null;

            case 'christmastreefeature':
            case 'christmas_tree_feature':
                // 🆕 christmas_tree_feature字段：确保返回字符串格式，最大长度80字符
                if (is_string($value) && !empty($value)) {
                    $trimmed = trim($value);
                    if (strlen($trimmed) <= 80) {
                        return $trimmed;
                    }
                }
                return null;

            case 'christmastreeshape':
            case 'christmas_tree_shape':
                // 🆕 christmas_tree_shape字段：确保返回数组格式并验证枚举值
                if (is_array($value) && !empty($value)) {
                    $valid_values = [
                        'Teardrop', 'Pencil', 'Full', 'Upside Down', 'Dress', 'Slim',
                        'Spiral', 'Triangular', 'Pyramid', 'Half', 'Corner', 'Topiary', 'Conical'
                    ];
                    $validated = [];
                    foreach ($value as $shape) {
                        if (is_string($shape) && in_array($shape, $valid_values, true)) {
                            $validated[] = $shape;
                        }
                    }
                    return !empty($validated) ? $validated : null;
                }
                return null;

            case 'christmastreetype':
            case 'christmas_tree_type':
                // 🆕 christmas_tree_type字段：确保返回字符串格式并验证枚举值
                if (is_string($value) && !empty($value)) {
                    $valid_values = [
                        'Artificial Christmas Trees',
                        'Fresh Cut Christmas Trees',
                        'Tabletop Christmas Trees',
                        'Living Christmas Trees'
                    ];
                    $trimmed = trim($value);
                    return in_array($trimmed, $valid_values, true) ? $trimmed : null;
                }
                return null;

            case 'colordescriptor':
            case 'color_descriptor':
                // 🆕 colorDescriptor字段：确保返回数组格式并验证枚举值
                if (is_array($value) && !empty($value)) {
                    $valid_values = ['Pastel', 'Rainbow', 'Neon', 'Metallic', 'Fluorescent', 'Pearlescent', 'Glitter'];
                    $validated = [];
                    foreach ($value as $descriptor) {
                        if (is_string($descriptor) && in_array($descriptor, $valid_values, true)) {
                            $validated[] = $descriptor;
                        }
                    }
                    // 如果没有有效值，返回默认值Rainbow
                    return !empty($validated) ? $validated : ['Rainbow'];
                }
                // 如果不是数组，返回默认值Rainbow
                return ['Rainbow'];

            case 'framecolorconfiguration':
            case 'frame_color_configuration':
                // 🆕 frameColorConfiguration字段：确保返回数组格式，每项最大40字符
                if (is_array($value) && !empty($value)) {
                    $validated = [];
                    foreach ($value as $color) {
                        if (is_string($color) && strlen($color) <= 40) {
                            $validated[] = trim($color);
                        }
                    }
                    return !empty($validated) ? $validated : null;
                }
                return null;

            case 'hasnrtllistingcertification':
            case 'has_nrtl_listing_certification':
                // 🆕 has_nrtl_listing_certification字段：确保返回字符串格式并验证枚举值
                if (is_string($value) && !empty($value)) {
                    $valid_values = ['Yes', 'No'];
                    $trimmed = trim($value);
                    return in_array($trimmed, $valid_values, true) ? $trimmed : 'No';
                }
                // 默认返回No
                return 'No';

            case 'ibretailpackaging':
            case 'ib_retail_packaging':
                // 🆕 ib_retail_packaging字段：确保返回字符串格式并验证枚举值
                if (is_string($value) && !empty($value)) {
                    $valid_values = ['Value Pack', 'Set', 'Bundle', 'Kit', 'Combo Pack', 'Pair', 'Bonus Pack', 'Single Piece'];
                    $trimmed = trim($value);
                    return in_array($trimmed, $valid_values, true) ? $trimmed : 'Single Piece';
                }
                // 默认返回Single Piece
                return 'Single Piece';

            case 'iscollectible':
            case 'is_collectible':
                // 🆕 isCollectible字段：确保返回字符串格式并验证枚举值
                if (is_string($value) && !empty($value)) {
                    $valid_values = ['Yes', 'No'];
                    $trimmed = trim($value);
                    return in_array($trimmed, $valid_values, true) ? $trimmed : 'Yes';
                }
                // 默认返回Yes
                return 'Yes';

            case 'lightfunctions':
            case 'light_functions':
                // 🆕 light_functions字段：确保返回数组格式并验证枚举值
                if (is_array($value) && !empty($value)) {
                    $valid_values = ['Chasing', 'Color Changing', 'Twinkling', 'Pulsing', 'Constant', 'Fading'];
                    $validated = [];
                    foreach ($value as $function) {
                        if (is_string($function) && in_array($function, $valid_values, true)) {
                            $validated[] = $function;
                        }
                    }
                    // 如果没有有效值，返回默认值Constant
                    return !empty($validated) ? $validated : ['Constant'];
                }
                // 如果不是数组，返回默认值Constant
                return ['Constant'];

            case 'lightbulbcolor':
            case 'light_bulb_color':
                // 🆕 lightBulbColor字段：确保返回字符串格式，最大长度400字符
                if (is_string($value) && !empty($value)) {
                    $trimmed = trim($value);
                    if (strlen($trimmed) <= 400) {
                        return $trimmed;
                    }
                    // 如果超过400字符，截断
                    return substr($trimmed, 0, 400);
                }
                return null;

            case 'lightbulbtype':
            case 'light_bulb_type':
                // 🆕 lightBulbType字段：确保返回字符串格式并验证枚举值
                if (is_string($value) && !empty($value)) {
                    $valid_values = ['LED', 'Incandescent'];
                    $trimmed = trim($value);
                    return in_array($trimmed, $valid_values, true) ? $trimmed : null;
                }
                return null;

            case 'numberoflights':
            case 'number_of_lights':
                // 🆕 numberOfLights字段：确保返回整数格式
                if (is_numeric($value)) {
                    $number = intval($value);
                    // 验证范围
                    if ($number >= 0 && $number <= 100000000000000000) {
                        return $number;
                    }
                }
                return null;

            case 'treetype':
            case 'tree_type':
                // 🆕 tree_type字段：确保返回字符串格式并验证枚举值
                if (is_string($value) && !empty($value)) {
                    $valid_values = ['Fir', 'Spruce', 'Pine'];
                    $trimmed = trim($value);
                    return in_array($trimmed, $valid_values, true) ? $trimmed : 'Fir';
                }
                // 默认返回Fir
                return 'Fir';

            case 'alphanumericcharacter':
            case 'alphanumeric_character':
                // 🆕 alphanumericCharacter字段：确保返回字符串格式，最大40字符
                if (is_string($value) && !empty($value)) {
                    $trimmed = trim($value);
                    // 验证长度限制
                    if (strlen($trimmed) <= 40) {
                        return $trimmed;
                    }
                    // 如果超过40字符，截断
                    return substr($trimmed, 0, 40);
                }
                return null;

            case 'subject':
                // 🆕 subject字段：确保返回数组格式，每项最大4000字符
                if (is_array($value) && !empty($value)) {
                    $validated = [];
                    foreach ($value as $item) {
                        if (is_string($item) && !empty($item)) {
                            $trimmed = trim($item);
                            // 验证长度限制
                            if (strlen($trimmed) <= 4000) {
                                $validated[] = $trimmed;
                            } else {
                                // 如果超过4000字符，截断
                                $validated[] = substr($trimmed, 0, 4000);
                            }
                        }
                    }
                    return !empty($validated) ? $validated : null;
                }
                return null;

            case 'walldecalandstickertype':
            case 'wall_decal_and_sticker_type':
                // 🆕 wall_decal_and_sticker_type字段：确保返回字符串格式并验证枚举值
                if (is_string($value) && !empty($value)) {
                    $valid_values = ['Wall Decals', 'Wall Stickers'];
                    $trimmed = trim($value);
                    return in_array($trimmed, $valid_values, true) ? $trimmed : null;
                }
                return null;

            case 'plantpotandplantertype':
            case 'plant_pot_and_planter_type':
                // 🆕 plant_pot_and_planter_type字段：确保返回字符串格式并验证枚举值
                if (is_string($value) && !empty($value)) {
                    $valid_values = ['Plant Pot', 'Plant Planter'];
                    $trimmed = trim($value);
                    return in_array($trimmed, $valid_values, true) ? $trimmed : null;
                }
                return null;

            case 'dooropeningstyle':
            case 'door_opening_style':
                // 🆕 doorOpeningStyle字段：确保返回字符串格式并验证枚举值 - 2025-10-30
                if (is_string($value) && !empty($value)) {
                    $valid_values = ['Lift Open', 'Swing Open', 'Sliding'];
                    $trimmed = trim($value);
                    return in_array($trimmed, $valid_values, true) ? $trimmed : null;
                }
                return null;

            case 'cabinettype':
            case 'cabinet_type':
                // 🆕 cabinet_type字段：确保返回字符串格式并验证枚举值 - 2025-10-30
                if (is_string($value) && !empty($value)) {
                    $valid_values = [
                        'Over-the-Toilet Cabinets',
                        'Wall Cabinets',
                        'Double Oven Cabinets',
                        'Drawer Base Cabinets',
                        'Base Cabinets',
                        'Sink Base Cabinets',
                        'Single Oven Cabinets',
                        'Corner Cabinets',
                        'Microwave Cabinets'
                    ];
                    $trimmed = trim($value);
                    return in_array($trimmed, $valid_values, true) ? $trimmed : null;
                }
                return null;

            case 'doorstyle':
            case 'door_style':
                // 🆕 doorStyle字段：确保返回字符串格式并验证枚举值 - 2025-10-30
                if (is_string($value) && !empty($value)) {
                    $valid_values = [
                        'Shaker',
                        'Flat Panel',
                        'Recessed Panel',
                        'Louvered',
                        'Raised Panel',
                        'Beadboard',
                        'Open Panel',
                        'Glass Panel',
                        'Arched'
                    ];
                    $trimmed = trim($value);
                    return in_array($trimmed, $valid_values, true) ? $trimmed : null;
                }
                return null;

            case 'drawerdepth':
            case 'drawer_depth':
                // 🆕 drawer_depth字段：确保返回对象格式 - 2025-10-30
                if (is_array($value) && isset($value['measure']) && isset($value['unit'])) {
                    $measure = trim($value['measure']);
                    $unit = trim($value['unit']);

                    // 验证单位必须是 "in"
                    if ($unit === 'in' && !empty($measure) && strlen($measure) <= 80) {
                        return ['measure' => $measure, 'unit' => 'in'];
                    }
                }
                return null;

            case 'drawerheight':
            case 'drawer_height':
                // 🆕 drawer_height字段：确保返回对象格式 - 2025-10-30
                if (is_array($value) && isset($value['measure']) && isset($value['unit'])) {
                    $measure = floatval($value['measure']);
                    $unit = trim($value['unit']);

                    // 验证单位必须是 "in"，数值范围 0-100000000000000000
                    if ($unit === 'in' && $measure >= 0 && $measure <= 100000000000000000) {
                        return ['measure' => $measure, 'unit' => 'in'];
                    }
                }
                return null;

            case 'drawerwidth':
            case 'drawer_width':
                // 🆕 drawer_width字段：确保返回对象格式 - 2025-10-30
                if (is_array($value) && isset($value['measure']) && isset($value['unit'])) {
                    $measure = floatval($value['measure']);
                    $unit = trim($value['unit']);

                    // 验证单位必须是 "in"，数值范围 0-100000000000000000
                    if ($unit === 'in' && $measure >= 0 && $measure <= 100000000000000000) {
                        return ['measure' => $measure, 'unit' => 'in'];
                    }
                }
                return null;

            case 'hasdoors':
            case 'has_doors':
                // 🆕 has_doors字段：确保返回字符串格式并验证枚举值 - 2025-10-30
                if (is_string($value) && !empty($value)) {
                    $valid_values = ['Yes', 'No'];
                    $trimmed = trim($value);
                    return in_array($trimmed, $valid_values, true) ? $trimmed : null;
                }
                return null;

            case 'mounttype':
            case 'mount_type':
                // 🆕 mountType字段：确保返回数组格式并验证枚举值 - 2025-10-30
                if (is_array($value) && !empty($value)) {
                    $valid_values = ['Wall Mount', 'Corner Mount', 'Freestanding', 'Recessed Mount'];
                    $validated = [];

                    foreach ($value as $item) {
                        $trimmed = trim($item);
                        if (in_array($trimmed, $valid_values, true)) {
                            $validated[] = $trimmed;
                        }
                    }

                    // 至少需要1个有效值
                    return !empty($validated) ? $validated : ['Freestanding'];
                }
                // 如果不是数组，返回默认值
                return ['Freestanding'];

            case 'orientation':
                // 🆕 orientation字段：确保返回字符串格式并验证枚举值 - 2025-10-30
                if (is_string($value) && !empty($value)) {
                    $valid_values = ['Horizontal', 'Vertical'];
                    $trimmed = trim($value);
                    return in_array($trimmed, $valid_values, true) ? $trimmed : 'Horizontal';
                }
                // 如果不是字符串或为空，返回默认值
                return 'Horizontal';

            case 'rugsize':
            case 'rug_size':
                // 🆕 rugSize字段：确保返回字符串格式，最大长度200字符
                if (is_string($value) && !empty($value)) {
                    $trimmed = trim($value);
                    if (strlen($trimmed) <= 200) {
                        return $trimmed;
                    }
                }
                return null;

            case 'size':
                // 🆕 size字段：确保返回字符串格式，最大长度500字符
                if (is_string($value) && !empty($value)) {
                    $trimmed = trim($value);
                    if (strlen($trimmed) <= 500) {
                        return $trimmed;
                    }
                }
                return null;

            case 'swatchimages':
            case 'swatch_images':
                // 🆕 swatchImages字段：确保返回对象数组格式
                // Walmart API 要求：[{"swatchImageUrl": "url", "swatchVariantAttribute": "attr"}]

                // 如果已经是正确的对象数组格式，直接返回
                if (is_array($value) && !empty($value)) {
                    // 检查是否是对象数组格式
                    $first_item = reset($value);
                    if (is_array($first_item) && isset($first_item['swatchImageUrl']) && isset($first_item['swatchVariantAttribute'])) {
                        return $value;
                    }
                }

                // 如果是字符串URL，转换为对象数组格式
                if (is_string($value) && !empty($value) && filter_var($value, FILTER_VALIDATE_URL)) {
                    return [
                        [
                            'swatchImageUrl' => $value,
                            'swatchVariantAttribute' => 'color' // 默认使用 color
                        ]
                    ];
                }

                // 其他情况返回null（不发送此字段）
                return null;

            case 'seatcolor':
            case 'seat_color':
                // 座椅颜色字段：确保返回数组格式
                if (is_array($value)) {
                    $filtered = array_values(array_filter($value));
                    return !empty($filtered) ? $filtered : ['Natural'];
                } elseif (is_string($value) && !empty($value)) {
                    if (strpos($value, ';') !== false) {
                        return array_filter(array_map('trim', explode(';', $value)));
                    } elseif (strpos($value, ',') !== false) {
                        return array_filter(array_map('trim', explode(',', $value)));
                    } else {
                        return [trim($value)];
                    }
                }
                // 空值返回默认值
                return ['Natural'];

            case 'seatmaterial':
            case 'seat_material':
                // 座椅材质字段：确保返回数组格式（API要求JSONArray）
                if (is_array($value)) {
                    // 已经是数组，过滤空值并返回
                    $filtered = array_values(array_filter($value));
                    return !empty($filtered) ? $filtered : ['Please see product description material'];
                } elseif (is_string($value) && !empty($value)) {
                    // 字符串转数组
                    if (strpos($value, ';') !== false) {
                        $materials = array_filter(array_map('trim', explode(';', $value)));
                    } elseif (strpos($value, ',') !== false) {
                        $materials = array_filter(array_map('trim', explode(',', $value)));
                    } else {
                        $materials = [trim($value)];
                    }
                    return !empty($materials) ? $materials : ['Please see product description material'];
                }
                // 空值或其他类型返回默认值
                return ['Please see product description material'];

            case 'sizedescriptor':
                // 尺寸描述符：确保返回字符串类型
                if (is_string($value) && !empty($value)) {
                    return $value;
                }
                // 空值返回默认值
                return 'Regular';

            case 'sofa_and_loveseat_design':
                // 沙发设计风格：确保返回数组格式（API要求JSONArray）
                if (is_array($value)) {
                    $filtered = array_values(array_filter($value));
                    return !empty($filtered) ? $filtered : ['Mid-Century Modern'];
                } elseif (is_string($value) && !empty($value)) {
                    // 字符串转数组
                    if (strpos($value, ',') !== false) {
                        return array_filter(array_map('trim', explode(',', $value)));
                    }
                    return [trim($value)];
                }
                // 空值返回默认值
                return ['Mid-Century Modern'];

            case 'sofa_bed_size':
                // 沙发床尺寸：确保返回字符串类型或null
                if (is_string($value) && !empty($value)) {
                    return $value;
                }
                // 空值返回null（不传递此字段）
                return null;

            case 'seatbackthickness':
            case 'seatbackwidth':
            case 'seatwidth':
            case 'seatbackheight':
            case 'seatheight':
                // 座椅尺寸字段：确保返回measurement_object格式
                if (is_array($value) && isset($value['measure']) && isset($value['unit'])) {
                    // 已经是正确格式
                    return [
                        'measure' => (float) $value['measure'],
                        'unit' => $value['unit']
                    ];
                } elseif (is_numeric($value)) {
                    // 纯数字，添加单位
                    return [
                        'measure' => (float) $value,
                        'unit' => 'in'
                    ];
                } elseif (is_string($value) && preg_match('/(\d+(?:\.\d+)?)\s*(?:in|inch|inches|")?/i', $value, $matches)) {
                    // 字符串包含数字和单位
                    return [
                        'measure' => (float) $matches[1],
                        'unit' => 'in'
                    ];
                }
                // 默认值
                return [
                    'measure' => 1.0,
                    'unit' => 'in'
                ];

            case 'seatingcapacity':
                // 座椅容量：确保返回整数
                if (is_numeric($value)) {
                    return (int) $value;
                } elseif (is_string($value) && preg_match('/(\d+)/', $value, $matches)) {
                    return (int) $matches[1];
                }
                // 默认值
                return 1;

            case 'recommendedlocations':
                // 推荐位置字段：确保返回数组格式，只包含有效的枚举值
                if (is_array($value)) {
                    // 过滤并验证枚举值
                    $valid_locations = ['Indoor', 'Outdoor'];
                    $filtered = array_intersect($value, $valid_locations);
                    return !empty($filtered) ? array_values($filtered) : ['Indoor'];
                } elseif (is_string($value) && !empty($value)) {
                    // 处理字符串输入
                    if (strpos($value, ';') !== false) {
                        $locations = array_filter(array_map('trim', explode(';', $value)));
                    } elseif (strpos($value, ',') !== false) {
                        $locations = array_filter(array_map('trim', explode(',', $value)));
                    } else {
                        $locations = [trim($value)];
                    }

                    // 标准化并验证
                    $valid_locations = ['Indoor', 'Outdoor'];
                    $normalized = [];
                    foreach ($locations as $location) {
                        $location_lower = strtolower($location);
                        if (in_array($location_lower, ['indoor', 'inside', 'interior'])) {
                            $normalized[] = 'Indoor';
                        } elseif (in_array($location_lower, ['outdoor', 'outside', 'exterior'])) {
                            $normalized[] = 'Outdoor';
                        } elseif (in_array($location, $valid_locations)) {
                            $normalized[] = $location;
                        }
                    }

                    $normalized = array_unique($normalized);
                    return !empty($normalized) ? array_values($normalized) : ['Indoor'];
                }
                // 默认值
                return ['Indoor'];

            case 'cleaningcareandmaintenance':
                // 清洁护理与维护字段：确保返回字符串格式，限制长度
                if (is_string($value) && !empty($value)) {
                    // 限制最大长度为5000字符
                    $cleaned_value = trim($value);
                    if (strlen($cleaned_value) > 5000) {
                        $cleaned_value = substr($cleaned_value, 0, 5000);
                    }
                    return $cleaned_value;
                } elseif (is_array($value)) {
                    // 如果是数组，合并为字符串
                    $combined = implode('. ', array_filter($value));
                    $cleaned_value = trim($combined);
                    if (strlen($cleaned_value) > 5000) {
                        $cleaned_value = substr($cleaned_value, 0, 5000);
                    }
                    return !empty($cleaned_value) ? $cleaned_value : 'Clean regularly with a soft, damp cloth to remove dust and food stains.';
                }
                // 默认值
                return 'Clean regularly with a soft, damp cloth to remove dust and food stains.';

            case 'numberofdoors':
            case 'number_of_doors':
                // 门数量字段：确保返回整数类型
                if (is_null($value)) {
                    return 0; // null值返回默认值0
                }
                if (is_numeric($value)) {
                    $number = intval($value);
                    // 验证范围：0-100000000000000000（根据API规范）
                    // 注意：PHP的intval对于超大数值会有精度问题，需要特殊处理
                    if ($number >= 0 && $number <= 100000000000000000) {
                        return $number;
                    }
                }
                // 如果值无效或超出范围，返回默认值0
                return 0;

            case 'numberoftiers':
            case 'number_of_tiers':
                // 层数字段：确保返回整数类型
                if (is_null($value)) {
                    return 0; // null值返回默认值0
                }
                if (is_numeric($value)) {
                    $number = intval($value);
                    // 验证范围：0-10000000000（根据API规范）
                    if ($number >= 0 && $number <= 10000000000) {
                        return $number;
                    }
                }
                // 如果值无效或超出范围，返回默认值0
                return 0;

            case 'quantity':
                // 库存数量字段：确保返回整数类型
                if (is_null($value)) {
                    return 0; // null值返回默认值0
                }
                if (is_numeric($value)) {
                    $number = intval($value);
                    // 验证范围：0-100000000000000000（根据API规范）
                    if ($number >= 0 && $number <= 100000000000000000) {
                        return $number;
                    }
                }
                // 如果值无效或超出范围，返回默认值0
                return 0;

            case 'tablecolor':
                // 桌子颜色字段：确保返回字符串类型
                if (is_string($value) && !empty($value)) {
                    // 限制长度为80个字符
                    return substr(trim($value), 0, 80);
                }
                return null; // 留空不传递

            case 'tabletoptype':
                // 桌面类型字段：确保返回有效枚举值
                $valid_types = ['Tray Top', 'Lift Top'];
                if (is_string($value) && in_array($value, $valid_types)) {
                    return $value;
                }
                // 如果值为null或无效，返回默认值
                return 'Tray Top';

            case 'tableheight':
                // 桌子高度字段：确保返回测量对象格式
                if (is_array($value) && isset($value['measure']) && isset($value['unit'])) {
                    return [
                        'measure' => (float) $value['measure'],
                        'unit' => 'in'
                    ];
                } elseif (is_numeric($value)) {
                    return [
                        'measure' => (float) $value,
                        'unit' => 'in'
                    ];
                }
                return null; // 留空不传递
        }

        // 优先使用API规范进行类型转换
        if ($this->spec_service && $this->current_product_type_id) {
            $validation_result = $this->spec_service->validate_field_value($this->current_product_type_id, $field_name, $value);

            // API规范转换成功，直接返回
            if (isset($validation_result['corrected_value'])) {
                return $validation_result['corrected_value'];
            }
        }

        // 如果API规范不可用，返回原值（不再使用硬编码的自动检测）
        return $value;



    }

    /**
     * 生成Key Features数组
     * @param WC_Product $product 产品对象
     * @return array Key Features数组
     */
    private function generate_key_features($product) {
        $features = [];

        // 第一优先：使用简短描述内容（支持 <li>、*、•、-、1. 等格式）
        $short_description = $product->get_short_description();
        if (!empty($short_description)) {
            $features = $this->extract_features_from_short_description($short_description);
        }

        // 第二优先：如果简短描述没有内容，从产品描述和标题智能生成
        if (empty($features)) {
            $description = $product->get_description();
            $title = $product->get_name();

            // 彻底清理HTML
            $clean_description = $this->deep_clean_html($description);

            // 智能生成Key Features
            $features = $this->smart_generate_features($clean_description, $title);

            // 确保至少有3个特色
            if (count($features) < 3) {
                $features = array_merge($features, $this->get_basic_fallback_features());
            }
        }

        // V5.0 验证：确保符合API要求
        $features = array_values(array_unique($features));

        // V5.0 要求：最少3个特色
        if (count($features) < 3) {
            $features = array_merge($features, $this->get_basic_fallback_features());
            $features = array_values(array_unique($features));
        }

        // V5.0 要求：每个特色最多10000字符
        $features = array_map(function($feature) {
            return strlen($feature) > 10000 ? substr($feature, 0, 10000) : $feature;
        }, $features);

        // 限制在6个以内（保持原有逻辑）
        $features = array_slice($features, 0, 6);

        return $features;
    }

    /**
     * 从简短描述中提取特色
     * @param string $short_description 产品简短描述
     * @return array 提取的特色数组
     */
    private function extract_features_from_short_description($short_description) {
        $features = [];

        // 1. 优先处理 <li> 标签
        if (preg_match_all('/<li[^>]*>(.*?)<\/li>/is', $short_description, $li_matches)) {
            foreach ($li_matches[1] as $li_content) {
                $feature = strip_tags($li_content);
                $feature = html_entity_decode($feature, ENT_QUOTES, 'UTF-8');
                $feature = trim($feature);
                $feature = $this->clean_feature_text($feature);
                if (!empty($feature)) {
                    $features[] = $feature;
                }
            }
            return array_slice($features, 0, 6);
        }

        // 2. 如果没有 <li> 标签，清理HTML后按项目符号分割
        $clean_description = $this->deep_clean_html($short_description);

        // 按项目符号分割（支持多种格式）
        $bullet_patterns = [
            '/\*\s*([^*\n]+)/m',     // * 开头的项目符号
            '/•\s*([^•\n]+)/m',      // • 开头的项目符号
            '/\-\s*([^-\n]+)/m',     // - 开头的项目符号
            '/\d+\.\s*([^\d\n]+)/m'  // 数字. 开头的项目符号
        ];

        foreach ($bullet_patterns as $pattern) {
            if (preg_match_all($pattern, $clean_description, $matches)) {
                foreach ($matches[1] as $match) {
                    $feature = trim($match);
                    $feature = $this->clean_feature_text($feature);
                    if (!empty($feature)) {
                        $features[] = $feature;
                    }
                }
                // 如果找到了项目符号，就不再尝试其他模式
                if (!empty($features)) {
                    break;
                }
            }
        }

        // 3. 如果没有找到项目符号，尝试按句子分割
        if (empty($features)) {
            $sentences = preg_split('/[.!?]+/', $clean_description);
            foreach ($sentences as $sentence) {
                $sentence = trim($sentence);
                if (!empty($sentence) && strlen($sentence) > 20 && strlen($sentence) < 200) {
                    $features[] = $sentence . '.';
                }
                if (count($features) >= 6) break;
            }
        }

        return array_slice($features, 0, 6);
    }

    /**
     * 清理特色文本
     * @param string $text 原始特色文本
     * @return string 清理后的特色文本
     */
    private function clean_feature_text($text) {
        // 移除多余的空白字符
        $text = preg_replace('/\s+/', ' ', $text);

        // 确保以大写字母开头
        $text = ucfirst(trim($text));

        // 确保以句号结尾
        if (!preg_match('/[.!?]$/', $text)) {
            $text .= '.';
        }

        return $text;
    }

    /**
     * 智能生成Key Features
     * @param string $description 清理后的产品描述
     * @param string $title 产品标题
     * @return array 生成的特色数组
     */
    private function smart_generate_features($description, $title) {
        $features = [];

        // 从标题提取关键特色
        $title_features = $this->extract_title_keywords($title);
        $features = array_merge($features, $title_features);

        // 从描述提取关键信息
        $desc_features = $this->extract_description_keywords($description);
        $features = array_merge($features, $desc_features);

        return array_filter($features);
    }

    /**
     * 从标题提取关键词生成特色
     * @param string $title 产品标题
     * @return array 特色数组
     */
    private function extract_title_keywords($title) {
        $features = [];

        $patterns = [
            '/adjustable/i' => 'Height adjustable design for personalized comfort.',
            '/swivel/i' => 'Swivel mechanism for easy movement and flexibility.',
            '/velvet/i' => 'Luxurious velvet upholstery for premium comfort.',
            '/office/i' => 'Perfect for home office and professional workspace.',
            '/modern/i' => 'Modern and contemporary design style.',
            '/wheels?/i' => 'Smooth-rolling wheels for easy mobility.',
            '/cushion/i' => 'High-quality cushioning for extended sitting comfort.'
        ];

        foreach ($patterns as $pattern => $feature) {
            if (preg_match($pattern, $title)) {
                $features[] = $feature;
            }
        }

        return array_slice($features, 0, 3);
    }

    /**
     * 从描述提取关键信息生成特色
     * @param string $description 清理后的描述
     * @return array 特色数组
     */
    private function extract_description_keywords($description) {
        $features = [];

        // 提取关键词并生成特色
        if (preg_match('/high.?density.*sponge|cushion/i', $description)) {
            $features[] = 'High-density sponge cushioning for superior comfort.';
        }

        if (preg_match('/versatile|multi.?purpose/i', $description)) {
            $features[] = 'Versatile design suitable for multiple uses.';
        }

        if (preg_match('/easy.*assem|simple.*install/i', $description)) {
            $features[] = 'Easy assembly with clear instructions.';
        }

        return array_slice($features, 0, 3);
    }





    /**
     * 彻底清理HTML标签和样式
     * @param string $html HTML内容
     * @return string 清理后的纯文本
     */
    private function deep_clean_html($html) {
        // 移除图片标签（包括各种变体）
        $html = preg_replace('/<img[^>]*\/?>/i', '', $html);
        $html = preg_replace('/<image[^>]*\/?>/i', '', $html);

        // 移除图片相关的其他标签
        $html = preg_replace('/<figure[^>]*>.*?<\/figure>/is', '', $html);
        $html = preg_replace('/<picture[^>]*>.*?<\/picture>/is', '', $html);

        // 移除所有style属性
        $html = preg_replace('/\s*style\s*=\s*["\'][^"\']*["\']/i', '', $html);

        // 移除所有class属性
        $html = preg_replace('/\s*class\s*=\s*["\'][^"\']*["\']/i', '', $html);

        // 将不保留的块级元素转换为换行，保留要保留的标签
        $html = preg_replace('/<\/(div|p|tr)>/i', "\n", $html);
        $html = preg_replace('/<(hr)\s*\/?>/i', "\n", $html);

        // 保留基础HTML标签，移除其他标签
        $allowed_tags = '<br><b><strong><ul><ol><li><h1><h2><h3><h4><h5><h6>';
        $html = strip_tags($html, $allowed_tags);

        // 解码HTML实体
        $html = html_entity_decode($html, ENT_QUOTES, 'UTF-8');

        // 清理多余的空白字符
        $html = preg_replace('/\s+/', ' ', $html);
        $html = preg_replace('/\n\s*\n/', "\n", $html);

        return trim($html);
    }

    /**
     * 获取基本后备特色
     * @return array 后备特色数组
     */
    private function get_basic_fallback_features() {
        return [
            'High-quality construction for long-lasting durability.',
            'Designed for comfort and functionality.',
            'Perfect addition to any modern space.'
        ];
    }

    /**
     * 格式化产品描述（包含属性信息）
     * @param string $description 原始产品描述
     * @param WC_Product $product 产品对象
     * @return string 格式化后的描述
     */
    private function format_product_description($description, $product) {

        $final_description = '';

        // 获取并格式化属性信息
        $attributes_section = $this->format_product_attributes($product);
        if (!empty($attributes_section)) {
            $final_description .= $attributes_section . "<br><br>";
        }

        // 添加产品特色部分
        if (!empty($description)) {
            // 对于模型词，不进行HTML清理，保留所有HTML代码
            if (!empty($description)) {
                $final_description .= "Product Features<br>" . $description;
            }
        }

        // V5.0 验证：限制字符数为100000（siteDescription的最大长度）
        if (strlen($final_description) > 100000) {
            $final_description = substr($final_description, 0, 100000);

            // 确保不在单词中间截断
            $last_space = strrpos($final_description, ' ');
            if ($last_space !== false && $last_space > 99900) {
                $final_description = substr($final_description, 0, $last_space);
            }
        }

        return trim($final_description);
    }

    /**
     * 格式化产品属性信息
     * @param WC_Product $product 产品对象
     * @return string 格式化后的属性信息
     */
    private function format_product_attributes($product) {
        if (!$product) {
            return '';
        }

        $attributes_text = '';
        $product_attributes = $product->get_attributes();

        if (!empty($product_attributes)) {
            $valid_attributes = [];

            foreach ($product_attributes as $attribute) {
                $name = $attribute->get_name();
                $value = $product->get_attribute($name);

                // 跳过空值和"Not Applicable"
                if (empty($value) || strtolower(trim($value)) === 'not applicable') {
                    continue;
                }

                // 跳过中文属性名（检查是否包含中文字符）
                if (preg_match('/[\x{4e00}-\x{9fff}]/u', $name)) {
                    continue;
                }

                // 清理属性名（移除pa_前缀等）
                $clean_name = $this->clean_attribute_name($name);
                if (!empty($clean_name)) {
                    $valid_attributes[] = $clean_name . ': ' . $value;
                }
            }

            if (!empty($valid_attributes)) {
                $attributes_text = "Product Information<br>" . implode("<br>", $valid_attributes);
            }
        }

        return $attributes_text;
    }

    /**
     * 清理属性名称
     * @param string $attribute_name 原始属性名
     * @return string 清理后的属性名
     */
    private function clean_attribute_name($attribute_name) {
        // 检查输入是否为null或空
        if (empty($attribute_name) || !is_string($attribute_name)) {
            return '';
        }

        // 移除pa_前缀
        if (strpos($attribute_name, 'pa_') === 0) {
            $attribute_name = substr($attribute_name, 3);
        }

        // 将下划线和连字符替换为空格
        $attribute_name = str_replace(['_', '-'], ' ', $attribute_name);

        // 首字母大写
        $attribute_name = ucwords($attribute_name);

        return trim($attribute_name);
    }

    /**
     * 从重量字符串中提取数字值
     * @param string $weight_string 重量字符串（如 "26.4 lb", "26.4", "26.4 lbs"）
     * @return float 提取的数字值，失败返回0
     */
    private function extract_numeric_weight($weight_string) {
        // 检查输入是否为null或空
        if (empty($weight_string) || !is_string($weight_string)) {
            return 0;
        }

        // 移除空白字符
        $weight_string = trim($weight_string);

        // 使用正则表达式提取数字（包括小数）
        if (preg_match('/^(\d+(?:\.\d+)?)/', $weight_string, $matches)) {
            $numeric_value = (float) $matches[1];
            return $numeric_value > 0 ? $numeric_value : 0;
        }

        return 0;
    }

    /**
     * 从产品标题中提取颜色
     * @param string $title 产品标题
     * @return string|null 提取的颜色
     */
    private function extract_color_from_title($title) {
        if (empty($title)) {
            return null;
        }

        // 常见颜色词汇（英文）
        $color_patterns = [
            // 基础颜色
            '/\b(black|white|red|blue|green|yellow|orange|purple|pink|brown|gray|grey)\b/i',
            // 深浅色调
            '/\b(dark|light|deep|bright|pale)\s+(black|white|red|blue|green|yellow|orange|purple|pink|brown|gray|grey)\b/i',
            // 特殊颜色
            '/\b(navy|beige|cream|ivory|gold|silver|bronze|copper|maroon|teal|turquoise|lime|olive|magenta|cyan|indigo|violet|crimson|scarlet|emerald|sapphire|amber|coral|salmon|khaki|tan|burgundy|charcoal|slate)\b/i',
            // 木色系
            '/\b(oak|walnut|cherry|maple|pine|mahogany|teak|bamboo|birch|cedar|espresso|natural|wood|wooden)\b/i',
            // 金属色
            '/\b(chrome|stainless|steel|brass|nickel|pewter|titanium)\b/i'
        ];

        foreach ($color_patterns as $pattern) {
            if (preg_match($pattern, $title, $matches)) {
                // 返回匹配的完整颜色描述
                return trim($matches[0]);
            }
        }

        return null;
    }

    /**
     * 🆕 从产品详情中提取颜色
     * @param WC_Product $product 产品对象
     * @return string|null 提取的颜色
     */
    private function extract_color_from_description($product) {
        // 获取产品描述内容
        $description = $product->get_description(); // 完整描述
        $short_description = $product->get_short_description(); // 简短描述

        // 合并所有描述内容
        $content = $description . ' ' . $short_description;

        if (empty($content)) {
            return null;
        }

        // 颜色提取模式（优先级从高到低）
        $color_extraction_patterns = [
            // 1. 颜色形容词 + 颜色词（优先提取复合颜色）
            '/\b(dark|light|deep|bright|pale|rich|vibrant|matte|glossy|satin)\s+(black|white|red|blue|green|yellow|orange|purple|pink|brown|gray|grey|beige|cream|ivory|gold|silver|bronze|copper|navy|maroon|teal|turquoise|lime|olive|magenta|cyan|indigo|violet|crimson|scarlet|emerald|sapphire|amber|coral|salmon|khaki|tan|burgundy|charcoal|slate|cherry|oak|walnut|maple|pine|mahogany|teak|espresso)\b/i',

            // 2. 明确的颜色描述模式（限制长度）
            '/(?:color|colour)[\s:]*([a-zA-Z\s]{1,20})(?:\s+(?:that|which|with|and)|[.,;]|$)/i',
            '/(?:available\s+in|comes\s+in)[\s:]*([a-zA-Z\s]{1,20})(?:\s+(?:that|which|with|and)|[.,;]|$)/i',
            '/(?:finish|finished\s+in)[\s:]*([a-zA-Z\s]{1,20})(?:\s+(?:that|which|with|and)|[.,;]|$)/i',

            // 3. 木色系和材质相关颜色
            '/\b(oak|walnut|cherry|maple|pine|mahogany|teak|bamboo|birch|cedar|espresso|natural)\s+(?:color|colour|finish|tone)\b/i',
            '/\b(oak|walnut|cherry|maple|pine|mahogany|teak|bamboo|birch|cedar|espresso|natural)\b/i',

            // 4. 金属色系
            '/\b(chrome|stainless|steel|brass|nickel|pewter|titanium|copper|bronze|gold|silver)\s+(?:finish|plated|coated)\b/i',
            '/\b(chrome|stainless|brass|nickel|pewter|titanium)\b/i',

            // 5. 基础颜色词（最后匹配）
            '/\b(black|white|red|blue|green|yellow|orange|purple|pink|brown|gray|grey|beige|cream|ivory|gold|silver|bronze|copper|navy|maroon|teal|turquoise|lime|olive|magenta|cyan|indigo|violet|crimson|scarlet|emerald|sapphire|amber|coral|salmon|khaki|tan|burgundy|charcoal|slate)\b/i'
        ];

        foreach ($color_extraction_patterns as $pattern) {
            if (preg_match($pattern, $content, $matches)) {
                // 对于复合颜色（如 dark cherry），使用完整匹配
                if (isset($matches[2]) && !empty($matches[2])) {
                    // 复合颜色：形容词 + 颜色词
                    $extracted_color = trim($matches[1] . ' ' . $matches[2]);
                } else {
                    // 单一颜色或其他模式
                    $extracted_color = trim($matches[1] ?? $matches[0]);
                }

                // 清理和验证提取的颜色
                $cleaned_color = $this->clean_extracted_color($extracted_color);

                if (!empty($cleaned_color)) {
                    return $cleaned_color;
                }
            }
        }

        return null;
    }

    /**
     * 🆕 清理和验证从描述中提取的颜色
     * @param string $color 原始提取的颜色字符串
     * @return string|null 清理后的颜色
     */
    private function clean_extracted_color($color) {
        if (empty($color)) {
            return null;
        }

        // 移除多余的空格和标点
        $color = trim($color, " \t\n\r\0\x0B.,;:");
        $color = preg_replace('/\s+/', ' ', $color);

        // 过滤掉过长的字符串（可能不是颜色）
        if (strlen($color) > 30) {
            return null;
        }

        // 过滤掉明显不是颜色的词汇和短语
        $invalid_patterns = [
            // 完全无效的词汇
            '/^(and|or|with|the|a|an|is|are|was|were|this|that|these|those|for|from|to|in|on|at|by|of|as|it|its|can|will|would|could|should|may|might|must|shall|do|does|did|have|has|had|be|been|being|get|got|getting|make|made|making|take|took|taken|taking)$/i',

            // 包含过多无效词汇的短语
            '/\b(and|or|with|the|a|an|is|are|was|were|this|that|these|those|for|from|to|in|on|at|by|of|as|it|its|can|will|would|could|should|may|might|must|shall|do|does|did|have|has|had|be|been|being|get|got|getting|make|made|making|take|took|taken|taking)\b.*\b(and|or|with|the|a|an|is|are|was|were|this|that|these|those|for|from|to|in|on|at|by|of|as|it|its|can|will|would|could|should|may|might|must|shall|do|does|did|have|has|had|be|been|being|get|got|getting|make|made|making|take|took|taken|taking)\b/i',

            // 明显不是颜色的描述
            '/\b(matches|any|decor|perfect|modern|offices|beautiful|stunning|various|options|including|features|adds|warmth|room|subtle|patterns|grain)\b/i'
        ];

        foreach ($invalid_patterns as $pattern) {
            if (preg_match($pattern, $color)) {
                return null;
            }
        }

        // 验证是否包含有效的颜色词
        $valid_color_words = [
            'black', 'white', 'red', 'blue', 'green', 'yellow', 'orange', 'purple',
            'pink', 'brown', 'gray', 'grey', 'beige', 'cream', 'ivory', 'gold',
            'silver', 'bronze', 'copper', 'navy', 'maroon', 'teal', 'turquoise',
            'lime', 'olive', 'magenta', 'cyan', 'indigo', 'violet', 'crimson',
            'scarlet', 'emerald', 'sapphire', 'amber', 'coral', 'salmon', 'khaki',
            'tan', 'burgundy', 'charcoal', 'slate', 'oak', 'walnut', 'cherry',
            'maple', 'pine', 'mahogany', 'teak', 'bamboo', 'birch', 'cedar',
            'espresso', 'natural', 'chrome', 'stainless', 'brass', 'nickel',
            'pewter', 'titanium', 'dark', 'light', 'deep', 'bright', 'pale'
        ];

        foreach ($valid_color_words as $valid_word) {
            if (stripos($color, $valid_word) !== false) {
                return ucwords(strtolower($color));
            }
        }

        return null;
    }

    /**
     * 从产品标题中提取材质
     * @param string $title 产品标题
     * @return string|null 提取的材质
     */
    private function extract_material_from_title($title) {
        if (empty($title)) {
            return null;
        }

        // 常见材质词汇
        $material_patterns = [
            // 金属材质
            '/\b(steel|stainless\s+steel|aluminum|aluminium|iron|brass|copper|bronze|chrome|nickel|titanium|zinc|metal)\b/i',
            // 木材
            '/\b(wood|wooden|oak|walnut|cherry|maple|pine|mahogany|teak|bamboo|birch|cedar|plywood|mdf|particle\s+board|hardwood|softwood)\b/i',
            // 塑料和合成材料
            '/\b(plastic|pvc|abs|polypropylene|polyethylene|acrylic|resin|composite|synthetic|polymer)\b/i',
            // 纺织品
            '/\b(cotton|polyester|nylon|silk|wool|linen|canvas|fabric|textile|velvet|leather|faux\s+leather|vinyl)\b/i',
            // 玻璃和陶瓷
            '/\b(glass|tempered\s+glass|ceramic|porcelain|crystal|quartz)\b/i',
            // 石材
            '/\b(stone|marble|granite|slate|limestone|sandstone|concrete|cement)\b/i',
            // 其他材质
            '/\b(rubber|foam|memory\s+foam|gel|silicone|carbon\s+fiber|fiberglass|wicker|rattan|bamboo)\b/i'
        ];

        foreach ($material_patterns as $pattern) {
            if (preg_match($pattern, $title, $matches)) {
                // 返回匹配的材质
                return trim($matches[0]);
            }
        }

        return null;
    }

    /**
     * 获取品牌值，根据分类映射配置决定获取方式
     */
    private function get_brand_value($product, $attributes_mapping) {
        // 查找品牌属性的映射配置
        $brand_mapping = null;
        if (!empty($attributes_mapping['name'])) {
            foreach ($attributes_mapping['name'] as $index => $attr_name) {
                if (strtolower($attr_name) === 'brand') {
                    $brand_mapping = [
                        'type' => $attributes_mapping['type'][$index] ?? 'auto_generate',
                        'source' => $attributes_mapping['source'][$index] ?? 'auto'
                    ];
                    break;
                }
            }
        }

        // 如果没有找到品牌映射配置，使用默认的自动生成
        if (!$brand_mapping) {
            $brand_mapping = ['type' => 'auto_generate', 'source' => 'auto'];
        }

        // 根据映射类型获取品牌值
        switch ($brand_mapping['type']) {
            case 'wc_attribute':
                // 从指定的WooCommerce属性获取
                $brand = $product->get_attribute($brand_mapping['source']);
                $brand = $brand ?: 'Unbranded';
                // V5.0 验证：品牌最多60字符
                return strlen($brand) > 60 ? substr($brand, 0, 60) : $brand;

            case 'default_value':
                // 使用指定的默认值
                $brand = $brand_mapping['source'] ?: 'Unbranded';
                // V5.0 验证：品牌最多60字符
                return strlen($brand) > 60 ? substr($brand, 0, 60) : $brand;

            case 'auto_generate':
            default:
                // 自动生成：先尝试从WooCommerce品牌属性获取，没有则使用Unbranded
                $brand = $product->get_attribute('brand') ?:
                        $product->get_attribute('Brand') ?:
                        $product->get_attribute('品牌') ?:
                        $product->get_attribute('pa_brand');

                $brand = $brand ?: 'Unbranded';
                // V5.0 验证：品牌最多60字符
                return strlen($brand) > 60 ? substr($brand, 0, 60) : $brand;
        }
    }

    /**
     * V5.0 字段验证：确保字段值符合API要求
     * @param string $field_name 字段名称
     * @param mixed $value 字段值
     * @return mixed 验证后的字段值
     */
    private function validate_field_for_v5($field_name, $value) {
        // 统一使用V5.0验证 (4.8版本已弃用)

        switch (strtolower($field_name)) {
            case 'brand':
                // 品牌：最多60字符
                if (is_string($value)) {
                    return strlen($value) > 60 ? substr($value, 0, 60) : $value;
                }
                break;

            case 'productname':
                // 产品名称：最多199字符
                if (is_string($value)) {
                    return strlen($value) > 199 ? substr($value, 0, 199) : $value;
                }
                break;

            case 'keyfeatures':
                // Key Features：数组，每个元素最多10000字符，最少3个
                if (is_array($value)) {
                    $value = array_map(function($feature) {
                        return strlen($feature) > 10000 ? substr($feature, 0, 10000) : $feature;
                    }, $value);

                    // 确保至少3个特色
                    if (count($value) < 3) {
                        $value = array_merge($value, $this->get_basic_fallback_features());
                        $value = array_slice(array_unique($value), 0, 6);
                    }
                }
                break;

            case 'shortdescription':
            case 'sitedescription':
                // 描述：最多100000字符
                if (is_string($value)) {
                    return strlen($value) > 100000 ? substr($value, 0, 100000) : $value;
                }
                break;
        }

        return $value;
    }

    /**
     * 获取netContent对象 - 符合沃尔玛V5.0规范
     * @param WC_Product $product
     * @return array
     */
    private function get_net_content_object($product) {
        // 尝试从产品属性获取净含量信息
        $net_content_measure = 1; // 默认数量
        $net_content_unit = 'Count'; // 默认单位：个数

        // 1. 尝试从产品属性获取净含量
        $net_content_attr = $product->get_attribute('Net Content') ?:
                           $product->get_attribute('net_content') ?:
                           $product->get_attribute('净含量');

        if (!empty($net_content_attr)) {
            // 解析净含量属性，支持格式如 "500 ml", "2 lb", "1 ct" 等
            $parsed = $this->parse_net_content($net_content_attr);
            if ($parsed) {
                $net_content_measure = $parsed['measure'];
                $net_content_unit = $parsed['unit'];
            }
        }

        // 2. 如果没有专门的净含量属性，尝试从重量推断
        if ($net_content_measure == 1 && $net_content_unit == 'Count') {
            $weight = $product->get_weight();
            if (!empty($weight) && is_numeric($weight)) {
                $net_content_measure = (float) $weight;
                $net_content_unit = 'Pound'; // WooCommerce默认重量单位通常是磅
            }
        }

        // 3. 根据商品类型智能推断单位
        $categories = wp_get_post_terms($product->get_id(), 'product_cat', ['fields' => 'names']);
        if (!empty($categories)) {
            $category_names = implode(' ', $categories);
            $category_lower = strtolower($category_names);

            // 根据类目调整默认单位
            if (strpos($category_lower, 'liquid') !== false ||
                strpos($category_lower, 'beverage') !== false ||
                strpos($category_lower, '液体') !== false) {
                $net_content_unit = 'Fluid Ounce';
            } elseif (strpos($category_lower, 'food') !== false ||
                     strpos($category_lower, '食品') !== false) {
                $net_content_unit = 'Ounce';
            }
        }

        // 确保单位在允许的枚举值中
        $allowed_units = [
            'Count', 'Inch', 'Foot', 'Yard', 'Millimeter', 'Centimeter', 'Meter',
            'Ounce', 'Pound', 'Gram', 'Kilogram', 'Fluid Ounce', 'Pint', 'Quart',
            'Gallon', 'Milliliter', 'Liter', 'Each'
        ];

        if (!in_array($net_content_unit, $allowed_units)) {
            $net_content_unit = 'Count'; // 回退到默认值
        }

        return [
            'productNetContentMeasure' => $net_content_measure,
            'productNetContentUnit' => $net_content_unit
        ];
    }

    /**
     * 解析净含量字符串
     * @param string $content_str 如 "500 ml", "2 lb", "1 ct"
     * @return array|null
     */
    private function parse_net_content($content_str) {
        // 清理字符串
        $content_str = trim($content_str);

        // 匹配数字和单位的模式
        if (preg_match('/^(\d+(?:\.\d+)?)\s*([a-zA-Z]+)$/i', $content_str, $matches)) {
            $measure = (float) $matches[1];
            $unit_str = strtolower(trim($matches[2]));

            // 单位映射表
            $unit_mapping = [
                'ct' => 'Count',
                'count' => 'Count',
                'pc' => 'Count',
                'pcs' => 'Count',
                'piece' => 'Count',
                'pieces' => 'Count',
                'each' => 'Each',
                'ea' => 'Each',

                'oz' => 'Ounce',
                'ounce' => 'Ounce',
                'ounces' => 'Ounce',
                'lb' => 'Pound',
                'lbs' => 'Pound',
                'pound' => 'Pound',
                'pounds' => 'Pound',
                'g' => 'Gram',
                'gram' => 'Gram',
                'grams' => 'Gram',
                'kg' => 'Kilogram',
                'kilogram' => 'Kilogram',
                'kilograms' => 'Kilogram',

                'fl oz' => 'Fluid Ounce',
                'floz' => 'Fluid Ounce',
                'fluid ounce' => 'Fluid Ounce',
                'fluid ounces' => 'Fluid Ounce',
                'ml' => 'Milliliter',
                'milliliter' => 'Milliliter',
                'milliliters' => 'Milliliter',
                'l' => 'Liter',
                'liter' => 'Liter',
                'liters' => 'Liter',
                'pint' => 'Pint',
                'pints' => 'Pint',
                'quart' => 'Quart',
                'quarts' => 'Quart',
                'gallon' => 'Gallon',
                'gallons' => 'Gallon',

                'in' => 'Inch',
                'inch' => 'Inch',
                'inches' => 'Inch',
                'ft' => 'Foot',
                'foot' => 'Foot',
                'feet' => 'Foot',
                'yd' => 'Yard',
                'yard' => 'Yard',
                'yards' => 'Yard',
                'mm' => 'Millimeter',
                'millimeter' => 'Millimeter',
                'millimeters' => 'Millimeter',
                'cm' => 'Centimeter',
                'centimeter' => 'Centimeter',
                'centimeters' => 'Centimeter',
                'm' => 'Meter',
                'meter' => 'Meter',
                'meters' => 'Meter'
            ];

            $walmart_unit = $unit_mapping[$unit_str] ?? null;

            if ($walmart_unit) {
                return [
                    'measure' => $measure,
                    'unit' => $walmart_unit
                ];
            }
        }

        return null;
    }

    /**
     * 计算多包裹重量总和
     *
     * @param WC_Product $product 产品对象
     * @return float|null 总重量（数字）
     */
    private function calculate_multi_package_weight($product) {
        $total_weight = 0;
        $found_packages = false;

        // 获取所有产品属性
        $attributes = $product->get_attributes();

        foreach ($attributes as $attr_name => $attribute) {
            $attr_name_lower = strtolower($attr_name);

            // 匹配包裹重量字段的模式
            // 支持: Package 1 Weight, Package-1-Weight, package_1_weight, package1weight 等
            if (preg_match('/package[\s\-_]*(\d+)[\s\-_]*weight/i', $attr_name_lower, $matches)) {
                $package_number = $matches[1];
                $weight_value = $product->get_attribute($attr_name);

                if (!empty($weight_value)) {
                    $numeric_weight = $this->extract_numeric_weight($weight_value);
                    if ($numeric_weight > 0) {
                        $total_weight += $numeric_weight;
                        $found_packages = true;
                    }
                }
            }
        }

        return $found_packages ? $total_weight : null;
    }

    /**
     * 从产品标题和描述中提取运输重量
     *
     * @param WC_Product $product 产品对象
     * @return float|null 提取的重量值（数字）
     */
    private function extract_shipping_weight_from_description($product) {
        // 从产品标题和描述中提取运输重量信息
        $content = strtolower($product->get_name() . ' ' . $product->get_description() . ' ' . $product->get_short_description());

        // 定义运输重量匹配模式
        $weight_patterns = [
            // 直接运输重量描述
            '/(?:shipping|package|packaged)\s*weight[:\s]+(\d+(?:\.\d+)?)\s*(?:lb|lbs|pound|pounds)?/i',
            '/(?:shipping|package|packaged)\s*weight\s+of\s+(\d+(?:\.\d+)?)\s*(?:lb|lbs|pound|pounds)?/i',
            '/(?:has|have)\s+a\s+(?:shipping|package|packaged)\s*weight\s+of\s+(\d+(?:\.\d+)?)\s*(?:lb|lbs|pound|pounds)?/i',
            '/weight\s+of\s+(?:shipping|package|packaged)[:\s]+(\d+(?:\.\d+)?)\s*(?:lb|lbs|pound|pounds)?/i',

            // 包装重量描述
            '/(?:packed|boxed)\s*weight[:\s]+(\d+(?:\.\d+)?)\s*(?:lb|lbs|pound|pounds)?/i',
            '/weight\s+(?:when|after)\s+(?:packed|boxed)[:\s]+(\d+(?:\.\d+)?)\s*(?:lb|lbs|pound|pounds)?/i',

            // 总重量描述（包含包装）
            '/total\s+weight[:\s]+(\d+(?:\.\d+)?)\s*(?:lb|lbs|pound|pounds)?/i',
            '/total\s+weight\s+(?:including|with)\s+\w+\s+(?:is|:)\s+(\d+(?:\.\d+)?)\s*(?:lb|lbs|pound|pounds)?/i',
            '/overall\s+weight[:\s]+(\d+(?:\.\d+)?)\s*(?:lb|lbs|pound|pounds)?/i',

            // 重量规格描述（通常在规格表中）
            '/weight[:\s]+(\d+(?:\.\d+)?)\s*(?:lb|lbs|pound|pounds)/i',

            // 中文关键词
            '/(?:运输|包装|打包).*?重量[:\s]*(\d+(?:\.\d+)?)\s*(?:lb|lbs|pound|pounds|磅)?/i',
            '/重量[:\s]*(\d+(?:\.\d+)?)\s*(?:lb|lbs|pound|pounds|磅)?\s*(?:运输|包装|打包)/i'
        ];

        // 搜索运输重量模式
        foreach ($weight_patterns as $pattern) {
            if (preg_match($pattern, $content, $matches)) {
                $weight = floatval($matches[1]);
                // 验证重量合理性（0.1-10000 lbs之间）
                if ($weight >= 0.1 && $weight <= 10000) {
                    return $weight;
                }
            }
        }

        return null;
    }

    /**
     * 获取Attributes字段值
     *
     * @param string $walmart_attr_name 沃尔玛属性名
     * @param WC_Product $product WooCommerce产品对象
     * @param array $attribute_rules 属性映射规则
     * @param int $index 当前属性在规则数组中的索引
     * @return string 属性值
     */
    private function get_attributes_field_value($walmart_attr_name, $product, $attribute_rules, $index) {
        // 获取用户填写的Attributes字段名
        $user_specified_key = '';
        if (isset($attribute_rules['attributes_key'][$index])) {
            $user_specified_key = trim($attribute_rules['attributes_key'][$index]);
        }

        // 获取备用默认值
        $fallback_value = isset($attribute_rules['source'][$index]) ? $attribute_rules['source'][$index] : '';

        // 优先级1: 如果用户指定了Attributes字段名，优先使用它
        if (!empty($user_specified_key)) {
            $attributes_value = get_product_attribute_value($product, $user_specified_key, '');
            if (!empty($attributes_value)) {
                return $attributes_value;
            }
        }

        // 优先级2: 如果用户指定的字段名没有找到值，尝试使用沃尔玛属性名
        if (!empty($walmart_attr_name) && $walmart_attr_name !== $user_specified_key) {
            $attributes_value = get_product_attribute_value($product, $walmart_attr_name, '');
            if (!empty($attributes_value)) {
                return $attributes_value;
            }
        }

        // 优先级3: 如果都没有找到，使用备用默认值
        return $fallback_value;
    }

    /**
     * 从Product Size属性解析指定位置的尺寸值
     * @param WC_Product $product 产品对象
     * @param int $index 位置索引 (0=长度, 1=宽度, 2=高度)
     * @return float|null 解析出的尺寸值
     */
    private function parse_product_size_dimension($product, $index) {
        // 获取Product Size属性
        $product_size = $product->get_attribute('Product Size') ?:
                       $product->get_attribute('product-size') ?:
                       $product->get_attribute('product_size');

        if (empty($product_size)) {
            return null;
        }

        // 支持多种格式：
        // 54.00 in × 23.00 in × 31.50 in
        // 54.00 × 23.00 × 31.50 in
        // 54.00×23.00×31.50in

        // 移除单位并标准化分隔符
        $cleaned = preg_replace('/\s*in\s*/i', '', $product_size);
        $cleaned = preg_replace('/\s*×\s*/', '×', $cleaned);

        // 按×分割
        $dimensions = explode('×', $cleaned);

        // 清理每个维度值
        $dimensions = array_map(function($dim) {
            return (float) trim($dim);
        }, $dimensions);

        // 返回指定位置的值
        return isset($dimensions[$index]) && $dimensions[$index] > 0 ? $dimensions[$index] : null;
    }

    /**
     * 根据产品标题和描述中的关键词自动识别床的类型
     * @param WC_Product $product 产品对象
     * @return string 床类型
     */
    private function determine_bed_type($product) {
        // 1. 首先尝试从产品属性获取
        $bed_type_attr = $product->get_attribute('Bed Type') ?:
                        $product->get_attribute('bed_type') ?:
                        $product->get_attribute('BedType');

        if (!empty($bed_type_attr)) {
            // 验证属性值是否在允许的枚举中
            $valid_types = [
                'Four-Poster Beds', 'Wingback Beds', 'Open-Frame Beds', 'Standard Beds',
                'Waterbeds', 'Slat/Spindle Beds', 'Bookcase Beds', 'Sleigh Beds',
                'Canopy Beds', 'Murphy Beds', 'Folding Beds', 'Toddler Beds', 'Novelty Beds'
            ];

            foreach ($valid_types as $valid_type) {
                if (stripos($bed_type_attr, $valid_type) !== false) {
                    return $valid_type;
                }
            }
        }

        // 2. 从产品标题和描述中提取关键词
        $content = strtolower($product->get_name() . ' ' . $product->get_description() . ' ' . $product->get_short_description());

        // 定义关键词映射（按优先级排序）
        $keyword_mapping = [
            'Four-Poster Beds' => ['four-poster', 'four poster', '4-poster', '4 poster', 'four post'],
            'Wingback Beds' => ['wingback', 'wing back', 'winged'],
            'Canopy Beds' => ['canopy', 'canopied', 'princess bed'],
            'Murphy Beds' => ['murphy', 'wall bed', 'fold down', 'fold-down'],
            'Folding Beds' => ['folding', 'foldable', 'fold up', 'fold-up', 'portable bed'],
            'Toddler Beds' => ['toddler', 'kids bed', 'children bed', 'child bed', 'junior bed'],
            'Sleigh Beds' => ['sleigh', 'curved headboard', 'curved footboard'],
            'Bookcase Beds' => ['bookcase', 'storage headboard', 'headboard storage', 'bookshelf'],
            'Waterbeds' => ['waterbed', 'water bed', 'water mattress'],
            'Slat/Spindle Beds' => ['slat', 'spindle', 'slatted', 'wooden slat'],
            'Novelty Beds' => ['novelty', 'themed', 'character bed', 'unique design', 'special design'],
            'Open-Frame Beds' => ['open frame', 'open-frame', 'minimalist', 'simple frame'],
        ];

        // 按优先级检查关键词
        foreach ($keyword_mapping as $bed_type => $keywords) {
            foreach ($keywords as $keyword) {
                if (strpos($content, $keyword) !== false) {
                    return $bed_type;
                }
            }
        }

        // 3. 默认值
        return 'Standard Beds';
    }

    /**
     * 从产品描述提取表面处理信息
     * @param WC_Product $product 产品对象
     * @return string 表面处理描述
     */
    private function extract_product_finish($product) {
        // 从产品标题和描述中提取表面处理信息
        $content = strtolower($product->get_name() . ' ' . $product->get_description() . ' ' . $product->get_short_description());

        // 定义表面处理关键词映射（按优先级排序）
        $finish_patterns = [
            // 金属处理
            'Chrome' => ['/chrome\s*(?:plated|finish)?/i', '/chromed/i'],
            'Powder Coated' => ['/powder\s*coat(?:ed|ing)?/i'],
            'Anodized' => ['/anodized?/i'],
            'Galvanized' => ['/galvanized?/i', '/zinc\s*coat(?:ed|ing)?/i'],
            'Brushed' => ['/brushed\s*(?:metal|steel|aluminum)?/i'],

            // 木材处理
            'Stained' => ['/stained?/i', '/wood\s*stain/i', '/(?:cherry|oak|walnut|mahogany)\s*stain/i'],
            'Natural' => ['/natural\s*(?:wood|finish)?/i', '/unfinished/i', '/raw\s*wood/i'],
            'Painted' => ['/painted?/i', '/paint\s*finish/i'],
            'Lacquered' => ['/lacquer(?:ed)?/i'],
            'Waxed' => ['/waxed?/i', '/wax\s*finish/i'],
            'Oiled' => ['/oil(?:ed)?\s*finish/i', '/tung\s*oil/i'],

            // 特殊处理
            'Antique' => ['/antique[d]?\s*(?:finish)?/i'],
            'Distressed' => ['/distressed/i', '/weathered/i', '/aged/i', '/rustic/i'],
            'Laminated' => ['/laminat(?:ed|e)/i'],
            'Veneer' => ['/veneer(?:ed)?/i', '/wood\s*veneer/i'],

            // 光泽度
            'Matte' => ['/matte?/i', '/flat\s*finish/i', '/non-gloss/i'],
            'Satin' => ['/satin/i', '/semi-gloss/i', '/eggshell/i'],
            'Glossy' => ['/gloss(?:y)?/i', '/high\s*gloss/i', '/shiny/i', '/polished/i'],

            // 纹理
            'Textured' => ['/textured?/i', '/rough/i', '/embossed/i'],
            'Smooth' => ['/smooth/i', '/sleek/i']
        ];

        // 检查表面处理模式
        foreach ($finish_patterns as $finish => $patterns) {
            foreach ($patterns as $pattern) {
                if (preg_match($pattern, $content)) {
                    return $finish;
                }
            }
        }

        // 如果没有找到特定处理，尝试组合主体颜色+材质
        $color = $this->generate_special_attribute_value('color', $product, 1);
        $material = $this->generate_special_attribute_value('material', $product, 1);

        // 组合颜色和材质
        if (!empty($color) && !empty($material)) {
            $material_str = is_array($material) ? $material[0] : $material;
            $color_str = is_array($color) ? $color : $color;

            // 避免重复词汇
            if (stripos($color_str, $material_str) === false && stripos($material_str, $color_str) === false) {
                return ucwords($color_str . ' ' . $material_str);
            }
        }

        // 如果只有材质，返回材质
        if (!empty($material)) {
            return is_array($material) ? ucwords($material[0]) : ucwords($material);
        }

        // 如果只有颜色，返回颜色
        if (!empty($color)) {
            return is_array($color) ? ucwords($color) : ucwords($color);
        }

        // 最后的默认值
        return 'Natural';
    }

    /**
     * 根据产品标题和描述中的关键词自动识别表面处理/涂装（旧方法，保留兼容性）
     * @param WC_Product $product 产品对象
     * @return string 表面处理描述
     */
    private function determine_product_finish($product) {
        // 1. 首先尝试从产品属性获取
        $finish_attr = $product->get_attribute('Finish') ?:
                      $product->get_attribute('finish') ?:
                      $product->get_attribute('Surface Finish') ?:
                      $product->get_attribute('表面处理');

        if (!empty($finish_attr)) {
            return $finish_attr;
        }

        // 2. 从产品标题和描述中提取关键词
        $content = strtolower($product->get_name() . ' ' . $product->get_description() . ' ' . $product->get_short_description());

        // 定义表面处理关键词映射（按优先级排序，更具体的关键词优先）
        $finish_keywords = [
            // 金属处理（优先级最高，避免被光泽度关键词覆盖）
            'Chrome' => ['chrome', 'chromed', 'chrome plated', 'chrome finish'],
            'Powder Coated' => ['powder coated', 'powder coat', 'powder coating'],
            'Anodized' => ['anodized', 'anodised', 'anodizing'],
            'Galvanized' => ['galvanized', 'galvanised', 'zinc coated'],
            'Brushed' => ['brushed', 'brush finish', 'brushed metal', 'brushed steel', 'brushed aluminum'],

            // 特殊处理（优先级较高）
            'Antique' => ['antique', 'antiqued', 'antique finish'],
            'Distressed' => ['distressed', 'weathered', 'aged', 'rustic', 'worn'],
            'Laminated' => ['laminated', 'laminate', 'laminate finish'],
            'Veneer' => ['veneer', 'wood veneer', 'veneered'],
            'Lacquered' => ['lacquered', 'lacquer', 'lacquer finish'],
            'Waxed' => ['waxed', 'wax finish', 'beeswax'],
            'Oiled' => ['oiled', 'oil finish', 'tung oil', 'linseed oil'],

            // 木材处理
            'Stained' => ['stained', 'wood stain', 'cherry stain', 'oak stain', 'walnut stain'],
            'Natural' => ['natural', 'unfinished', 'raw wood', 'natural wood', 'unstained'],
            'Painted' => ['painted', 'paint finish', 'color painted'],

            // 纹理处理
            'Textured' => ['textured', 'texture', 'rough', 'embossed', 'raised pattern'],
            'Smooth' => ['smooth', 'sleek', 'even', 'uniform'],

            // 光泽度相关（优先级较低，避免覆盖更具体的处理）
            'Satin' => ['satin', 'semi-gloss', 'semi gloss', 'eggshell', 'silk'],
            'Matte' => ['matte', 'matt', 'flat', 'non-gloss', 'dull', 'flat finish'],
            'Glossy' => ['glossy', 'gloss', 'high gloss', 'shiny', 'polished', 'mirror finish'],

        ];

        // 按优先级检查关键词
        foreach ($finish_keywords as $finish => $keywords) {
            foreach ($keywords as $keyword) {
                if (strpos($content, $keyword) !== false) {
                    return $finish;
                }
            }
        }

        // 3. 如果没有找到特定的表面处理，尝试提取颜色相关的描述
        $color_patterns = [
            '/\b(black|white|brown|gray|grey|silver|gold|bronze|copper|brass)\s+(finish|painted|stained|coated)\b/',
            '/\b(finish|painted|stained|coated)\s+(black|white|brown|gray|grey|silver|gold|bronze|copper|brass)\b/',
            '/\b(dark|light|medium)\s+(wood|oak|cherry|walnut|maple|pine)\b/'
        ];

        foreach ($color_patterns as $pattern) {
            if (preg_match($pattern, $content, $matches)) {
                return ucwords($matches[0]);
            }
        }

        // 4. 默认值：使用产品材质
        $material = $this->generate_special_attribute_value('material', $product, 1);

        if (!empty($material) && is_array($material)) {
            // 如果材质是数组，取第一个
            return $material[0];
        } elseif (!empty($material) && is_string($material)) {
            // 如果材质是字符串，直接返回
            return $material;
        }

        // 如果没有材质信息，根据产品类型推断
        $product_categories = wp_get_post_terms($product->get_id(), 'product_cat', array('fields' => 'names'));
        $category_string = strtolower(implode(' ', $product_categories));

        if (strpos($category_string, 'metal') !== false || strpos($content, 'metal') !== false) {
            return 'Metal';
        } elseif (strpos($category_string, 'wood') !== false || strpos($content, 'wood') !== false) {
            return 'Wood';
        } elseif (strpos($content, 'plastic') !== false) {
            return 'Plastic';
        } elseif (strpos($content, 'fabric') !== false || strpos($content, 'textile') !== false) {
            return 'Fabric';
        } elseif (strpos($content, 'glass') !== false) {
            return 'Glass';
        } else {
            return 'Mixed Materials';
        }
    }

    /**
     * 根据用户指定的格式转换字段值
     * @param string $field_name 字段名
     * @param mixed $value 原始值
     * @param string $format 用户指定的格式
     * @return mixed 转换后的值
     */
    private function convert_by_user_format($field_name, $value, $format) {
        switch ($format) {
            case 'string':
                return (string) $value;

            case 'number':
                return is_numeric($value) ? (float) $value : 0;

            case 'boolean':
                if (is_string($value)) {
                    $lower = strtolower($value);
                    return in_array($lower, ['yes', 'true', '1', 'on']) ? true : false;
                }
                return (bool) $value;

            case 'array':
                if (is_array($value)) {
                    return $value;
                }
                if (is_string($value)) {
                    // 尝试多种分隔符
                    if (strpos($value, ',') !== false) {
                        return array_map('trim', explode(',', $value));
                    } elseif (strpos($value, '|') !== false) {
                        return array_map('trim', explode('|', $value));
                    } elseif (strpos($value, ';') !== false) {
                        return array_map('trim', explode(';', $value));
                    }
                    return [$value];
                }
                return [$value];

            case 'object':
                if (is_array($value)) {
                    return $value;
                }
                return ['value' => $value];

            case 'measurement_object':
                if (is_array($value) && isset($value['measure']) && isset($value['unit'])) {
                    return [
                        'measure' => (float) $value['measure'],
                        'unit' => $value['unit']
                    ];
                }

                // 增强：解析带单位的字符串输入（如 "15 in", "25.4 cm", "10 lb"）
                if (is_string($value) && !empty(trim($value))) {
                    $trimmed_value = trim($value);

                    // 匹配 "数字 单位" 或 "数字单位" 格式，支持小数
                    if (preg_match('/^(\d+(?:\.\d+)?)\s*(cm|in|lb|kg|oz|g)$/i', $trimmed_value, $matches)) {
                        return [
                            'measure' => (float) $matches[1],
                            'unit' => strtolower($matches[2])
                        ];
                    }

                    // 如果是纯数字字符串，按数字处理
                    if (is_numeric($trimmed_value)) {
                        $value = (float) $trimmed_value;
                    }
                }

                if (is_numeric($value)) {
                    // 根据字段名确定默认单位：尺寸用in，重量用lb
                    $default_unit = 'in';
                    $field_lower = strtolower($field_name);
                    if (strpos($field_lower, 'weight') !== false ||
                        strpos($field_lower, 'mass') !== false) {
                        $default_unit = 'lb';
                    }
                    return [
                        'measure' => (float) $value,
                        'unit' => $default_unit
                    ];
                }

                // 默认值：根据字段名确定单位
                $default_unit = 'in';
                $field_lower = strtolower($field_name);
                if (strpos($field_lower, 'weight') !== false ||
                    strpos($field_lower, 'mass') !== false) {
                    $default_unit = 'lb';
                }
                return ['measure' => 1.0, 'unit' => $default_unit];

            case 'state_restrictions':
                if (is_array($value) && !empty($value) && is_array($value[0])) {
                    return $value; // 已经是正确格式
                }
                if (is_string($value)) {
                    if (strtolower($value) === 'none') {
                        return [['stateRestrictionsText' => 'None']];
                    }
                    return [['stateRestrictionsText' => 'Illegal for Sale', 'states' => $value]];
                }
                return [['stateRestrictionsText' => 'None']];

            case 'product_identifiers':
                if (is_array($value)) {
                    return $value;
                }
                if (is_string($value) && !empty($value)) {
                    return [['productIdType' => 'UPC', 'productId' => $value]];
                }
                return [];

            case 'key_features':
                if (is_array($value)) {
                    return $value;
                }
                if (is_string($value)) {
                    // 尝试多种分隔符
                    if (strpos($value, '\n') !== false) {
                        return array_filter(array_map('trim', explode('\n', $value)));
                    } elseif (strpos($value, '|') !== false) {
                        return array_filter(array_map('trim', explode('|', $value)));
                    } elseif (strpos($value, ';') !== false) {
                        return array_filter(array_map('trim', explode(';', $value)));
                    }
                    return [$value];
                }
                return [$value];

            default:
                return $value;
        }
    }

    /**
     * 智能转换值为数组格式
     * @param mixed $value 原始值
     * @return array 转换后的数组
     */
    private function convert_to_array($value) {
        // 如果已经是数组，直接返回
        if (is_array($value)) {
            return $value;
        }

        // 如果是字符串，按逗号分割
        if (is_string($value) && !empty($value)) {
            // 处理多种分隔符：逗号、分号、管道符
            $separators = [',', ';', '|'];
            foreach ($separators as $sep) {
                if (strpos($value, $sep) !== false) {
                    return array_map('trim', explode($sep, $value));
                }
            }
            // 没有分隔符，返回单元素数组
            return [trim($value)];
        }

        // 其他类型，转换为字符串后返回单元素数组
        return [$value];
    }

    /**
     * 智能转换值为尺寸对象格式
     * @param string $field_name 字段名
     * @param mixed $value 原始值
     * @return array 尺寸对象格式
     */
    private function convert_to_measurement_object($field_name, $value) {
        // 如果已经是正确的对象格式，直接返回
        if (is_array($value) && isset($value['measure']) && isset($value['unit'])) {
            return [
                'measure' => (float) $value['measure'],
                'unit' => $value['unit']
            ];
        }

        // 如果是字符串，尝试解析单位
        if (is_string($value) && !empty(trim($value))) {
            $trimmed_value = trim($value);

            // 匹配 "数字 单位" 或 "数字单位" 格式
            if (preg_match('/^(\d+(?:\.\d+)?)\s*(cm|in|lb|kg|oz|g)$/i', $trimmed_value, $matches)) {
                return [
                    'measure' => (float) $matches[1],
                    'unit' => strtolower($matches[2])
                ];
            }

            // 如果是纯数字字符串，使用默认单位
            if (is_numeric($trimmed_value)) {
                $value = (float) $trimmed_value;
            }
        }

        // 如果是数字，使用默认单位
        if (is_numeric($value)) {
            // 根据字段名确定默认单位：尺寸用in，重量用lb
            $default_unit = 'in';
            $field_lower = strtolower($field_name);
            if (strpos($field_lower, 'weight') !== false ||
                strpos($field_lower, 'mass') !== false) {
                $default_unit = 'lb';
            }

            return [
                'measure' => (float) $value,
                'unit' => $default_unit
            ];
        }

        // 默认值：根据字段名确定单位
        $default_unit = 'in';
        $field_lower = strtolower($field_name);
        if (strpos($field_lower, 'weight') !== false ||
            strpos($field_lower, 'mass') !== false) {
            $default_unit = 'lb';
        }

        return [
            'measure' => 1.0,
            'unit' => $default_unit
        ];
    }

    /**
     * 从产品标题和描述中提取床架可调性关键词
     * @param WC_Product $product WooCommerce产品对象
     * @return array|null 床架可调性数组或null
     */
    private function extract_bed_frame_adjustability($product) {
        // 获取产品标题、描述和短描述
        $title = strtolower($product->get_name());
        $description = strtolower($product->get_description());
        $short_description = strtolower($product->get_short_description());

        // 合并所有文本内容
        $content = $title . ' ' . $description . ' ' . $short_description;

        $adjustability_features = [];

        // 检测 Adjustable Foot 相关关键词
        $foot_keywords = [
            'adjustable foot',
            'foot adjustment',
            'foot elevation',
            'raise foot',
            'lift foot',
            'elevate foot',
            'adjustable feet',
            'foot adjustable',
            'adjustable leg',
            'leg adjustment'
        ];

        foreach ($foot_keywords as $keyword) {
            if (strpos($content, $keyword) !== false) {
                $adjustability_features[] = 'Adjustable Foot';
                break; // 找到一个就够了
            }
        }

        // 检测 Adjustable Head 相关关键词
        $head_keywords = [
            'adjustable head',
            'head adjustment',
            'head elevation',
            'raise head',
            'lift head',
            'elevate head',
            'adjustable headrest',
            'head adjustable',
            'headboard adjustable',
            'adjustable upper'
        ];

        foreach ($head_keywords as $keyword) {
            if (strpos($content, $keyword) !== false) {
                $adjustability_features[] = 'Adjustable Head';
                break; // 找到一个就够了
            }
        }

        // 去重并返回结果
        $adjustability_features = array_unique($adjustability_features);

        // 如果没有找到任何关键词，返回null（留空）
        if (empty($adjustability_features)) {
            return null;
        }

        // 返回数组格式
        return array_values($adjustability_features);
    }

    /**
     * 从产品描述自动提取餐椅类型
     * @param WC_Product $product WooCommerce产品对象
     * @return string 餐椅类型
     */
    private function extract_dining_chair_type($product) {
        // 从产品标题和描述中提取餐椅类型信息
        $content = strtolower($product->get_name() . ' ' . $product->get_description() . ' ' . $product->get_short_description());

        // 定义扶手椅关键词（更精确的匹配）
        $arm_chair_patterns = [
            // 直接扶手椅描述
            '/dining\s*arm\s*chair/i',
            '/arm\s*dining\s*chair/i',
            '/armchair/i',
            '/arm\s*chair/i',

            // 带扶手描述
            '/with\s*arm/i',
            '/with\s*armrest/i',
            '/armrest/i',
            '/arm\s*rest/i',

            // 船长椅等特殊类型
            '/captain\s*chair/i',
            '/captain\'s\s*chair/i',
            '/host\s*chair/i',
            '/hostess\s*chair/i',
            '/carver\s*chair/i',

            // 中文关键词
            '/扶手椅/i',
            '/有扶手/i',
            '/带扶手/i'
        ];

        // 检查是否匹配扶手椅模式
        foreach ($arm_chair_patterns as $pattern) {
            if (preg_match($pattern, $content)) {
                return 'Dining Arm Chairs';
            }
        }

        // 默认返回侧椅
        return 'Dining Side Chairs';
    }

    /**
     * 根据产品描述关键词自动识别椅背样式
     * @param WC_Product $product WooCommerce产品对象
     * @return string 椅背样式
     */
    private function determine_seat_back_style($product) {
        // 获取产品标题、描述和短描述
        $title = strtolower($product->get_name());
        $description = strtolower($product->get_description());
        $short_description = strtolower($product->get_short_description());

        // 合并所有文本内容
        $content = $title . ' ' . $description . ' ' . $short_description;

        // 定义椅背样式关键词映射
        $style_keywords = [
            'Fiddle Back' => [
                'fiddle back', 'fiddle-back', 'violin back', 'curved back'
            ],
            'Keyhole Back' => [
                'keyhole back', 'keyhole-back', 'key hole back'
            ],
            'Wingback' => [
                'wingback', 'wing back', 'wing-back', 'winged back'
            ],
            'Ladder Back' => [
                'ladder back', 'ladder-back', 'slat ladder', 'horizontal slat'
            ],
            'Lattice Back' => [
                'lattice back', 'lattice-back', 'crisscross back', 'cross hatch'
            ],
            'Solid Back' => [
                'solid back', 'solid-back', 'full back', 'upholstered back'
            ],
            'Parsons' => [
                'parsons', 'parsons style', 'parsons chair', 'straight back'
            ],
            'Slat Back' => [
                'slat back', 'slat-back', 'vertical slat', 'wood slat'
            ],
            'Cross Back' => [
                'cross back', 'cross-back', 'x back', 'x-back', 'crossed back'
            ],
            'Windsor' => [
                'windsor', 'windsor style', 'spindle back', 'stick back'
            ]
        ];

        // 按优先级检查关键词（从最具体到最一般）
        foreach ($style_keywords as $style => $keywords) {
            foreach ($keywords as $keyword) {
                if (strpos($content, $keyword) !== false) {
                    return $style;
                }
            }
        }

        // 如果都没有匹配，返回默认值
        return 'Splat Back';
    }

    /**
     * 从产品描述中提取椅背坐垫样式
     *
     * @param WC_Product $product 产品对象
     * @return string|null 椅背坐垫样式枚举值，无匹配返回null
     */
    private function extract_seat_back_cushion_style($product) {
        // 获取产品标题、描述和短描述
        $title = strtolower($product->get_name());
        $description = strtolower($product->get_description());
        $short_description = strtolower($product->get_short_description());

        // 合并所有文本内容
        $content = $title . ' ' . $description . ' ' . $short_description;

        // 定义椅背坐垫样式关键词映射（按优先级排序 - 更具体的关键词在前）
        $cushion_style_keywords = [
            'Biscuit Back' => [
                'biscuit tufted back', 'biscuit back', 'biscuit-back', 'biscuit style back'
            ],
            'Tufted Back' => [
                'button tufted back', 'diamond tufted back', 'deep tufted back',
                'channel tufted back', 'tufted back', 'tufted-back', 'back tufted', 'tufting on back'
            ],
            'Split Back' => [
                'split back', 'split-back', 'divided back', 'separated back cushion'
            ],
            'Loose Back' => [
                'loose back', 'loose-back', 'removable back cushion', 'detachable back',
                'loose back cushion', 'reversible back cushion'
            ],
            'Tight Back' => [
                'tight back', 'tight-back', 'fixed back', 'attached back',
                'non-removable back', 'stationary back'
            ],
            'Sewn-Pillow Back' => [
                'sewn-pillow back', 'sewn pillow back', 'sewn-in pillow back',
                'attached pillow back', 'stitched pillow back'
            ],
            'Cushion Back' => [
                'cushion back', 'cushioned back', 'padded back', 'soft back cushion'
            ],
            'Pillow Back' => [
                'pillow back', 'pillow-back', 'pillow style back', 'plush pillow back',
                'throw pillow back', 'loose pillow back'
            ]
        ];

        // 按优先级检查关键词（从最具体到最一般）
        foreach ($cushion_style_keywords as $style => $keywords) {
            foreach ($keywords as $keyword) {
                if (strpos($content, $keyword) !== false) {
                    return $style;
                }
            }
        }

        // 如果没有匹配，返回null（留空不传递此字段）
        return null;
    }

    /**
     * 从产品描述中提取装饰枕类型
     *
     * @param WC_Product $product 产品对象
     * @return string 装饰枕类型枚举值，无匹配返回默认值Bolster Pillow
     */
    private function extract_decorative_pillow_type($product) {
        // 获取产品标题、描述和短描述
        $title = strtolower($product->get_name());
        $description = strtolower($product->get_description());
        $short_description = strtolower($product->get_short_description());

        // 合并所有文本内容
        $content = $title . ' ' . $description . ' ' . $short_description;

        // 定义装饰枕类型关键词映射（按优先级排序 - 更具体的关键词在前）
        $pillow_type_keywords = [
            'Decorative Pillow Set' => [
                'pillow set', 'set of pillows', 'pillow collection', 'pillow pack',
                '2 pack pillow', '4 pack pillow', 'multi-pack pillow', 'pillow bundle'
            ],
            'Decorative Lumbar Pillow' => [
                'lumbar pillow', 'lumbar cushion', 'lumbar support pillow', 'back support pillow',
                'kidney pillow', 'rectangular pillow', 'oblong pillow'
            ],
            'Floor Pillow' => [
                'floor pillow', 'floor cushion', 'meditation pillow', 'seating pillow',
                'oversized floor pillow', 'large floor cushion', 'pouf pillow'
            ],
            'Throw Pillow' => [
                'throw pillow', 'accent pillow', 'decorative throw', 'toss pillow',
                'sofa pillow', 'couch pillow', 'bed pillow', 'cushion pillow'
            ],
            'Bolster Pillow' => [
                'bolster pillow', 'bolster cushion', 'cylindrical pillow', 'roll pillow',
                'neck roll', 'tube pillow', 'round pillow'
            ]
        ];

        // 按优先级检查关键词（从最具体到最一般）
        foreach ($pillow_type_keywords as $type => $keywords) {
            foreach ($keywords as $keyword) {
                if (strpos($content, $keyword) !== false) {
                    return $type;
                }
            }
        }

        // 如果没有匹配，返回默认值
        return 'Bolster Pillow';
    }

    /**
     * 从产品描述中判断产品是否填充
     *
     * @param WC_Product $product 产品对象
     * @return string 是否填充（Yes/No），无明确信息返回默认值Yes
     */
    private function extract_is_filled($product) {
        // 获取产品标题、描述和短描述
        $title = strtolower($product->get_name());
        $description = strtolower($product->get_description());
        $short_description = strtolower($product->get_short_description());

        // 合并所有文本内容
        $content = $title . ' ' . $description . ' ' . $short_description;

        // 定义"未填充"关键词（优先检查）
        $unfilled_keywords = [
            'unfilled', 'not filled', 'empty', 'insert only', 'cover only',
            'pillow cover', 'cushion cover', 'shell only', 'no fill',
            'without filling', 'without stuffing', 'no insert', 'insert not included'
        ];

        // 定义"已填充"关键词
        $filled_keywords = [
            'filled', 'stuffed', 'padded', 'with filling', 'with stuffing',
            'pre-filled', 'ready to use', 'complete pillow', 'insert included',
            'foam filled', 'polyester filled', 'down filled', 'feather filled'
        ];

        // 优先检查"未填充"关键词
        foreach ($unfilled_keywords as $keyword) {
            if (strpos($content, $keyword) !== false) {
                return 'No';
            }
        }

        // 检查"已填充"关键词
        foreach ($filled_keywords as $keyword) {
            if (strpos($content, $keyword) !== false) {
                return 'Yes';
            }
        }

        // 如果没有明确信息，返回默认值Yes
        return 'Yes';
    }

    /**
     * 提取座椅尺寸数据
     * @param WC_Product $product WooCommerce产品对象
     * @param string $dimension_type 尺寸类型
     * @param float $default_value 默认值
     * @return array measurement_object格式
     */
    private function extract_seat_dimension($product, $dimension_type, $default_value) {
        // 获取产品标题、描述和短描述
        $title = $product->get_name();
        $description = $product->get_description();
        $short_description = $product->get_short_description();

        // 合并所有文本内容
        $content = $title . ' ' . $description . ' ' . $short_description;

        // 定义不同尺寸类型的关键词
        $dimension_keywords = [
            'thickness' => ['thick', 'thickness'],
            'back_width' => ['back width', 'backrest width', 'back rest width'],
            'width' => ['wide', 'width', 'seat width'],
            'back_height' => ['back height', 'backrest height', 'back rest height'],
            'height' => ['height', 'seat height', 'high']
        ];

        $keywords = $dimension_keywords[$dimension_type] ?? [];

        // 尝试从产品属性中获取
        $attribute_names = [
            'thickness' => ['Seat Back Thickness', 'Back Thickness', 'Thickness'],
            'back_width' => ['Seat Back Width', 'Back Width', 'Backrest Width'],
            'width' => ['Seat Width', 'Width'],
            'back_height' => ['Seat Back Height', 'Back Height', 'Backrest Height'],
            'height' => ['Seat Height', 'Height']
        ];

        $attr_names = $attribute_names[$dimension_type] ?? [];
        foreach ($attr_names as $attr_name) {
            $attr_value = $product->get_attribute($attr_name);
            if (!empty($attr_value) && $attr_value !== 'not specified') {
                // 尝试解析数字
                if (preg_match('/(\d+(?:\.\d+)?)\s*(?:in|inch|inches|")?/i', $attr_value, $matches)) {
                    return [
                        'measure' => (float) $matches[1],
                        'unit' => 'in'
                    ];
                }
            }
        }

        // 从文本内容中提取尺寸
        foreach ($keywords as $keyword) {
            // 更灵活的匹配模式
            $patterns = [
                '/(\d+(?:\.\d+)?)\s*(?:in|inch|inches|")\s*' . preg_quote($keyword, '/') . '/i',
                '/' . preg_quote($keyword, '/') . '\s*:?\s*(\d+(?:\.\d+)?)\s*(?:in|inch|inches|")?/i',
                '/(\d+(?:\.\d+)?)\s*' . preg_quote($keyword, '/') . '/i'
            ];

            foreach ($patterns as $pattern) {
                if (preg_match($pattern, $content, $matches)) {
                    return [
                        'measure' => (float) $matches[1],
                        'unit' => 'in'
                    ];
                }
            }
        }

        // 特殊处理：如果是宽度字段，尝试匹配任何数字+inches的组合
        if ($dimension_type === 'width') {
            if (preg_match('/(\d+(?:\.\d+)?)\s*(?:in|inch|inches|")/i', $content, $matches)) {
                return [
                    'measure' => (float) $matches[1],
                    'unit' => 'in'
                ];
            }
        }

        // 返回默认值
        return [
            'measure' => (float) $default_value,
            'unit' => 'in'
        ];
    }

    /**
     * 提取座椅颜色
     * @param WC_Product $product WooCommerce产品对象
     * @return array 颜色数组
     */
    private function extract_seat_color($product) {
        // 从产品标题和描述中提取椅子或座椅颜色
        $content = strtolower($product->get_name() . ' ' . $product->get_description() . ' ' . $product->get_short_description());

        // 定义座椅颜色匹配模式
        $seat_color_patterns = [
            // 直接座椅颜色描述
            '/(?:seat|cushion|upholstery|chair)\s*(?:is|in|color|colour)[:\s]*(black|white|brown|gray|grey|beige|cream|ivory|red|blue|green|yellow|orange|purple|pink|navy|charcoal|espresso|walnut|oak|cherry|mahogany|natural)/i',
            '/(?:seat|cushion|upholstery)\s*(black|white|brown|gray|grey|beige|cream|ivory|red|blue|green|yellow|orange|purple|pink|navy|charcoal|espresso|walnut|oak|cherry|mahogany|natural)/i',

            // 颜色+座椅
            '/(black|white|brown|gray|grey|beige|cream|ivory|red|blue|green|yellow|orange|purple|pink|navy|charcoal|espresso|walnut|oak|cherry|mahogany|natural)\s*(?:seat|cushion|upholstery|chair)/i',

            // 软包颜色
            '/(black|white|brown|gray|grey|beige|cream|ivory|red|blue|green|yellow|orange|purple|pink|navy|charcoal|espresso|walnut|oak|cherry|mahogany|natural)\s*(?:fabric|leather|vinyl|upholstered)/i',
            '/(?:fabric|leather|vinyl|upholstered)\s*(?:in|is)?\s*(black|white|brown|gray|grey|beige|cream|ivory|red|blue|green|yellow|orange|purple|pink|navy|charcoal|espresso|walnut|oak|cherry|mahogany|natural)/i',

            // 椅子整体颜色（通常与座椅颜色相同）
            '/(black|white|brown|gray|grey|beige|cream|ivory|red|blue|green|yellow|orange|purple|pink|navy|charcoal|espresso|walnut|oak|cherry|mahogany|natural)\s*(?:dining\s*)?chair/i',
            '/chair\s*(?:in|is)?\s*(black|white|brown|gray|grey|beige|cream|ivory|red|blue|green|yellow|orange|purple|pink|navy|charcoal|espresso|walnut|oak|cherry|mahogany|natural)/i',

            // 中文关键词
            '/(?:座椅|坐垫|椅子).*?(黑色|白色|棕色|灰色|米色|奶油色|红色|蓝色|绿色|黄色|橙色|紫色|粉色|深蓝|炭色|胡桃色|橡木色|樱桃色|桃花心木色|自然色)/i',
            '/(黑色|白色|棕色|灰色|米色|奶油色|红色|蓝色|绿色|黄色|橙色|紫色|粉色|深蓝|炭色|胡桃色|橡木色|樱桃色|桃花心木色|自然色).*?(?:座椅|坐垫|椅子)/i'
        ];

        // 搜索座椅颜色模式
        foreach ($seat_color_patterns as $pattern) {
            if (preg_match($pattern, $content, $matches)) {
                $color = trim($matches[1]);

                // 标准化颜色名称
                $color_mapping = [
                    'grey' => 'Gray',
                    'navy' => 'Navy Blue',
                    'charcoal' => 'Charcoal',
                    'espresso' => 'Espresso',
                    'walnut' => 'Walnut',
                    'oak' => 'Oak',
                    'cherry' => 'Cherry',
                    'mahogany' => 'Mahogany',
                    'natural' => 'Natural',
                    '黑色' => 'Black',
                    '白色' => 'White',
                    '棕色' => 'Brown',
                    '灰色' => 'Gray',
                    '米色' => 'Beige',
                    '奶油色' => 'Cream',
                    '红色' => 'Red',
                    '蓝色' => 'Blue',
                    '绿色' => 'Green',
                    '黄色' => 'Yellow',
                    '橙色' => 'Orange',
                    '紫色' => 'Purple',
                    '粉色' => 'Pink',
                    '深蓝' => 'Navy Blue',
                    '炭色' => 'Charcoal',
                    '胡桃色' => 'Walnut',
                    '橡木色' => 'Oak',
                    '樱桃色' => 'Cherry',
                    '桃花心木色' => 'Mahogany',
                    '自然色' => 'Natural'
                ];

                $normalized_color = $color_mapping[strtolower($color)] ?? ucwords(strtolower($color));
                return [$normalized_color];
            }
        }

        // 默认值
        return ['Color as shown'];
    }

    /**
     * 提取座椅材质
     * @param WC_Product $product WooCommerce产品对象
     * @return array 材质数组
     */
    private function extract_seat_material($product) {
        // 从产品标题和描述中自动提取椅子主体材质
        $content = strtolower($product->get_name() . ' ' . $product->get_description() . ' ' . $product->get_short_description());

        // 定义座椅材质匹配模式
        $seat_material_patterns = [
            // 直接座椅材质描述
            '/(?:seat|cushion|upholstery|chair)\s*(?:is|made\s*of|in|material)[:\s]*(leather|fabric|cotton|polyester|linen|velvet|microfiber|mesh|wood|metal|plastic|vinyl|canvas|suede|faux\s*leather)/i',
            '/(?:seat|cushion|upholstery)\s*(leather|fabric|cotton|polyester|linen|velvet|microfiber|mesh|wood|metal|plastic|vinyl|canvas|suede|faux\s*leather)/i',

            // 材质+座椅
            '/(leather|fabric|cotton|polyester|linen|velvet|microfiber|mesh|wood|metal|plastic|vinyl|canvas|suede|faux\s*leather)\s*(?:seat|cushion|upholstery|chair)/i',

            // 软包材质
            '/(leather|fabric|cotton|polyester|linen|velvet|microfiber|mesh|vinyl|canvas|suede|faux\s*leather)\s*(?:upholstered|covered|padded)/i',
            '/(?:upholstered|covered|padded)\s*(?:in|with)?\s*(leather|fabric|cotton|polyester|linen|velvet|microfiber|mesh|vinyl|canvas|suede|faux\s*leather)/i',

            // 椅子整体材质（通常与座椅材质相同）
            '/(leather|fabric|cotton|polyester|linen|velvet|microfiber|mesh|wood|metal|plastic|vinyl|canvas|suede|faux\s*leather)\s*(?:dining\s*)?chair/i',
            '/chair\s*(?:made\s*of|in)?\s*(leather|fabric|cotton|polyester|linen|velvet|microfiber|mesh|wood|metal|plastic|vinyl|canvas|suede|faux\s*leather)/i',

            // 特定材质描述
            '/genuine\s*(leather)/i',
            '/real\s*(leather)/i',
            '/bonded\s*(leather)/i',
            '/pu\s*(leather)/i',
            '/synthetic\s*(leather)/i',

            // 中文关键词
            '/(?:座椅|坐垫|椅子).*?(皮革|真皮|人造革|布料|棉质|聚酯|亚麻|天鹅绒|超细纤维|网布|木质|金属|塑料|乙烯基|帆布|绒面)/i',
            '/(皮革|真皮|人造革|布料|棉质|聚酯|亚麻|天鹅绒|超细纤维|网布|木质|金属|塑料|乙烯基|帆布|绒面).*?(?:座椅|坐垫|椅子)/i'
        ];

        // 搜索座椅材质模式
        $found_materials = [];
        foreach ($seat_material_patterns as $pattern) {
            if (preg_match_all($pattern, $content, $matches)) {
                foreach ($matches[1] as $material) {
                    $material = trim($material);

                    // 标准化材质名称
                    $material_mapping = [
                        'faux leather' => 'Faux Leather',
                        'leather' => 'Leather',
                        'fabric' => 'Fabric',
                        'cotton' => 'Cotton',
                        'polyester' => 'Polyester',
                        'linen' => 'Linen',
                        'velvet' => 'Velvet',
                        'microfiber' => 'Microfiber',
                        'mesh' => 'Mesh',
                        'wood' => 'Wood',
                        'metal' => 'Metal',
                        'plastic' => 'Plastic',
                        'vinyl' => 'Vinyl',
                        'canvas' => 'Canvas',
                        'suede' => 'Suede',
                        '皮革' => 'Leather',
                        '真皮' => 'Leather',
                        '人造革' => 'Faux Leather',
                        '布料' => 'Fabric',
                        '棉质' => 'Cotton',
                        '聚酯' => 'Polyester',
                        '亚麻' => 'Linen',
                        '天鹅绒' => 'Velvet',
                        '超细纤维' => 'Microfiber',
                        '网布' => 'Mesh',
                        '木质' => 'Wood',
                        '金属' => 'Metal',
                        '塑料' => 'Plastic',
                        '乙烯基' => 'Vinyl',
                        '帆布' => 'Canvas',
                        '绒面' => 'Suede'
                    ];

                    $normalized_material = $material_mapping[strtolower($material)] ?? ucwords(strtolower($material));
                    $found_materials[] = $normalized_material;
                }
            }
        }

        // 去重并返回
        if (!empty($found_materials)) {
            return array_unique($found_materials);
        }

        // 默认值
        return ['Please see product description material'];
    }

    /**
     * 提取座椅容量
     * @param WC_Product $product WooCommerce产品对象
     * @return int 座椅容量
     */
    private function extract_seating_capacity($product) {
        // 首先尝试从属性获取
        $capacity_attrs = ['Seating Capacity', 'seating_capacity', 'Capacity', 'Seats'];

        foreach ($capacity_attrs as $attr_name) {
            $attr_value = $product->get_attribute($attr_name);
            if (!empty($attr_value) && $attr_value !== 'not specified') {
                // 提取数字
                if (preg_match('/(\d+)/', $attr_value, $matches)) {
                    return (int) $matches[1];
                }
            }
        }

        // 从产品名称和描述中提取容量信息
        $content = strtolower($product->get_name() . ' ' . $product->get_description());

        // 查找容量关键词
        $capacity_patterns = [
            '/(\d+)\s*(?:seat|seater|person|people)/i',
            '/seats?\s*(\d+)/i',
            '/capacity\s*:?\s*(\d+)/i'
        ];

        foreach ($capacity_patterns as $pattern) {
            if (preg_match($pattern, $content, $matches)) {
                $capacity = (int) $matches[1];
                if ($capacity > 0 && $capacity <= 20) { // 合理范围检查
                    return $capacity;
                }
            }
        }

        // 根据产品类型推断容量
        if (strpos($content, 'sofa') !== false || strpos($content, 'couch') !== false) {
            if (strpos($content, 'loveseat') !== false) {
                return 2;
            } elseif (strpos($content, 'sectional') !== false) {
                return 4;
            } else {
                return 3; // 标准沙发
            }
        } elseif (strpos($content, 'bench') !== false) {
            return 2;
        }

        // 默认返回1（单人座椅）
        return 1;
    }

    /**
     * 提取推荐使用位置
     * @param WC_Product $product WooCommerce产品对象
     * @return array 推荐位置数组
     */
    private function extract_recommended_locations($product) {
        // 首先尝试从产品属性获取
        $location_attrs = ['Recommended Locations', 'recommended_locations', 'Location', 'Use Location', 'Suitable For'];

        foreach ($location_attrs as $attr_name) {
            $attr_value = $product->get_attribute($attr_name);
            if (!empty($attr_value) && $attr_value !== 'not specified') {
                // 处理多个位置（分号、逗号分隔）
                if (strpos($attr_value, ';') !== false) {
                    $locations = array_filter(array_map('trim', explode(';', $attr_value)));
                } elseif (strpos($attr_value, ',') !== false) {
                    $locations = array_filter(array_map('trim', explode(',', $attr_value)));
                } else {
                    $locations = [trim($attr_value)];
                }

                // 标准化位置值
                return $this->normalize_locations($locations);
            }
        }

        // 从产品名称、描述和短描述中提取位置信息
        $title = strtolower($product->get_name());
        $description = strtolower($product->get_description());
        $short_description = strtolower($product->get_short_description());

        // 合并所有文本内容
        $content = $title . ' ' . $description . ' ' . $short_description;

        // 定义位置关键词
        $location_keywords = [
            'outdoor' => [
                'outdoor', 'outside', 'patio', 'garden', 'deck', 'balcony',
                'terrace', 'yard', 'backyard', 'poolside', 'beach',
                'camping', 'picnic', 'weather resistant', 'waterproof',
                'uv resistant', 'all weather', 'weatherproof'
            ],
            'indoor' => [
                'indoor', 'inside', 'home', 'house', 'apartment', 'office',
                'bedroom', 'living room', 'dining room', 'kitchen', 'bathroom',
                'study', 'den', 'basement', 'attic', 'interior'
            ]
        ];

        $detected_locations = [];
        $outdoor_score = 0;
        $indoor_score = 0;

        // 计算户外关键词得分
        foreach ($location_keywords['outdoor'] as $keyword) {
            if (strpos($content, $keyword) !== false) {
                $outdoor_score++;
            }
        }

        // 计算室内关键词得分
        foreach ($location_keywords['indoor'] as $keyword) {
            if (strpos($content, $keyword) !== false) {
                $indoor_score++;
            }
        }

        // 根据得分决定位置
        if ($outdoor_score > 0 && $indoor_score > 0) {
            // 如果同时包含室内外关键词，优先户外（因为户外产品通常会明确标注）
            if ($outdoor_score >= $indoor_score) {
                $detected_locations[] = 'Outdoor';
            } else {
                $detected_locations[] = 'Indoor';
            }
        } elseif ($outdoor_score > 0) {
            $detected_locations[] = 'Outdoor';
        } elseif ($indoor_score > 0) {
            $detected_locations[] = 'Indoor';
        }

        if (!empty($detected_locations)) {
            return $detected_locations;
        }

        // 根据产品类型推断位置
        if (strpos($content, 'patio') !== false ||
            strpos($content, 'garden') !== false ||
            strpos($content, 'outdoor') !== false) {
            return ['Outdoor'];
        }

        // 默认返回室内
        return ['Indoor'];
    }

    /**
     * 标准化位置值，确保符合Walmart API规范
     * @param array $locations 原始位置数组
     * @return array 标准化后的位置数组
     */
    private function normalize_locations($locations) {
        $normalized = [];
        $valid_locations = ['Indoor', 'Outdoor'];

        foreach ($locations as $location) {
            $location_lower = strtolower(trim($location));

            // 映射常见的位置描述到标准值
            if (in_array($location_lower, ['indoor', 'inside', 'interior', 'home', 'house'])) {
                $normalized[] = 'Indoor';
            } elseif (in_array($location_lower, ['outdoor', 'outside', 'exterior', 'patio', 'garden'])) {
                $normalized[] = 'Outdoor';
            } elseif (in_array($location, $valid_locations)) {
                // 已经是标准值
                $normalized[] = $location;
            }
        }

        // 去重
        $normalized = array_unique($normalized);

        // 如果没有有效位置，返回默认值
        if (empty($normalized)) {
            return ['Indoor'];
        }

        return array_values($normalized);
    }

    /**
     * 🆕 提取底座样式
     * @param WC_Product $product 产品对象
     * @return string 底座样式
     */
    private function extract_base_style($product) {
        $content = strtolower($product->get_name() . ' ' . $product->get_description() . ' ' . $product->get_short_description());

        // 定义底座样式关键词映射
        $style_patterns = [
            'Standard Legs' => ['standard legs', 'four legs', '4 legs', 'traditional legs', 'straight legs'],
            'Frame' => ['frame base', 'metal frame', 'steel frame', 'frame support'],
            'Double Pedestal' => ['double pedestal', 'twin pedestal', 'dual pedestal'],
            'Cross Legs' => ['cross legs', 'x-legs', 'crossed legs', 'x-base'],
            'Sled' => ['sled base', 'sled legs', 'curved base'],
            'Trestle' => ['trestle base', 'trestle legs', 'trestle support'],
            'Star Base' => ['star base', '5-star base', 'swivel base'],
            'Pedestal' => ['pedestal base', 'single pedestal', 'center pedestal'],
            'Abstract' => ['abstract base', 'artistic base', 'unique base'],
            'Block' => ['block base', 'solid base', 'cube base']
        ];

        // 匹配关键词
        foreach ($style_patterns as $style => $patterns) {
            foreach ($patterns as $pattern) {
                if (strpos($content, $pattern) !== false) {
                    return $style;
                }
            }
        }

        // 默认值
        return 'Standard Legs';
    }

    /**
     * 🆕 提取底座颜色
     * @param WC_Product $product 产品对象
     * @return string|null 底座颜色
     */
    private function extract_base_color($product) {
        $content = strtolower($product->get_name() . ' ' . $product->get_description() . ' ' . $product->get_short_description());

        // 查找底座颜色相关描述
        $base_color_patterns = [
            '/base.*?(black|white|brown|gray|grey|silver|gold|bronze|copper|brass|natural|wood|metal)/i',
            '/(black|white|brown|gray|grey|silver|gold|bronze|copper|brass|natural|wood|metal).*?base/i',
            '/legs.*?(black|white|brown|gray|grey|silver|gold|bronze|copper|brass|natural|wood|metal)/i',
            '/(black|white|brown|gray|grey|silver|gold|bronze|copper|brass|natural|wood|metal).*?legs/i'
        ];

        foreach ($base_color_patterns as $pattern) {
            if (preg_match($pattern, $content, $matches)) {
                return ucfirst($matches[1]);
            }
        }

        // 如果没有找到底座颜色，使用产品主体颜色
        $main_color = $this->generate_special_attribute_value('color', $product, 1);
        if (!empty($main_color)) {
            return is_array($main_color) ? $main_color[0] : $main_color;
        }

        // 如果还没有，返回null（不传递此字段）
        return null;
    }

    /**
     * 🆕 提取底座材质
     * @param WC_Product $product 产品对象
     * @return string|null 底座材质
     */
    private function extract_base_material($product) {
        $content = strtolower($product->get_name() . ' ' . $product->get_description() . ' ' . $product->get_short_description());

        // 查找底座材质相关描述
        $base_material_patterns = [
            '/base.*?(wood|steel|metal|plastic|aluminum|iron|chrome|brass|copper)/i',
            '/(wood|steel|metal|plastic|aluminum|iron|chrome|brass|copper).*?base/i',
            '/legs.*?(wood|steel|metal|plastic|aluminum|iron|chrome|brass|copper)/i',
            '/(wood|steel|metal|plastic|aluminum|iron|chrome|brass|copper).*?legs/i'
        ];

        foreach ($base_material_patterns as $pattern) {
            if (preg_match($pattern, $content, $matches)) {
                return ucfirst($matches[1]);
            }
        }

        // 如果没有找到，返回null（不传递此字段）
        return null;
    }

    /**
     * 🆕 提取是否可扩展
     * @param WC_Product $product 产品对象
     * @return string 是否可扩展
     */
    private function extract_is_extendable($product) {
        $content = strtolower($product->get_name() . ' ' . $product->get_description() . ' ' . $product->get_short_description());

        // 可扩展关键词
        $extendable_keywords = [
            'extendable', 'expandable', 'extension', 'leaf', 'leaves',
            'extend', 'expand', 'adjustable length', 'variable size'
        ];

        foreach ($extendable_keywords as $keyword) {
            if (strpos($content, $keyword) !== false) {
                return 'Yes';
            }
        }

        return 'No';
    }

    /**
     * 🆕 提取桌叶类型
     * @param WC_Product $product 产品对象
     * @return string|null 桌叶类型
     */
    private function extract_table_leaf_type($product) {
        $content = strtolower($product->get_name() . ' ' . $product->get_description() . ' ' . $product->get_short_description());

        // 桌叶类型关键词映射
        $leaf_patterns = [
            'Drop Leaf' => ['drop leaf', 'drop-leaf', 'folding leaf'],
            'Self-Storing Leaf' => ['self-storing leaf', 'self storing leaf', 'built-in leaf', 'hidden leaf'],
            'Butterfly Leaf' => ['butterfly leaf', 'butterfly extension'],
            'Removable Leaf' => ['removable leaf', 'detachable leaf', 'separate leaf']
        ];

        foreach ($leaf_patterns as $type => $patterns) {
            foreach ($patterns as $pattern) {
                if (strpos($content, $pattern) !== false) {
                    return $type;
                }
            }
        }

        // 如果没有匹配，返回null（不传递此字段）
        return null;
    }

    /**
     * 🆕 提取桌子形状
     * @param WC_Product $product 产品对象
     * @return array 桌子形状（数组格式）
     */
    private function extract_table_shape($product) {
        $content = strtolower($product->get_name() . ' ' . $product->get_description() . ' ' . $product->get_short_description());

        // 桌子形状关键词映射
        $shape_patterns = [
            'Round' => ['round', 'circular', 'circle'],
            'Square' => ['square', 'squared'],
            'Rectangle' => ['rectangle', 'rectangular', 'oblong'],
            'Oval' => ['oval', 'elliptical'],
            'Curved' => ['curved', 'curved edge', 'rounded edge'],
            'Semicircle' => ['semicircle', 'half circle', 'semi-circle'],
            'U-Shape' => ['u-shape', 'u shape', 'horseshoe'],
            'Octagon' => ['octagon', 'octagonal', '8-sided'],
            'Free Form' => ['free form', 'freeform', 'irregular', 'organic']
        ];

        foreach ($shape_patterns as $shape => $patterns) {
            foreach ($patterns as $pattern) {
                if (strpos($content, $pattern) !== false) {
                    return [$shape];
                }
            }
        }

        // 默认值
        return ['Free Form'];
    }

    /**
     * 🆕 提取桌面材质
     * @param WC_Product $product 产品对象
     * @return array|null 桌面材质（数组格式）
     */
    private function extract_table_top_material($product) {
        $content = strtolower($product->get_name() . ' ' . $product->get_description() . ' ' . $product->get_short_description());

        // 桌面材质关键词
        $material_patterns = [
            '/(?:table\s*top|top|surface).*?(wood|glass|mdf|resin|marble|granite|metal|plastic|laminate|veneer)/i',
            '/(wood|glass|mdf|resin|marble|granite|metal|plastic|laminate|veneer).*?(?:table\s*top|top|surface)/i'
        ];

        $found_materials = [];

        foreach ($material_patterns as $pattern) {
            if (preg_match_all($pattern, $content, $matches)) {
                foreach ($matches[1] as $material) {
                    $material = ucfirst(strtolower($material));
                    if (!in_array($material, $found_materials)) {
                        $found_materials[] = $material;
                    }
                }
            }
        }

        // 如果没有找到桌面材质，尝试从产品主体材质获取
        if (empty($found_materials)) {
            $main_material = $this->generate_special_attribute_value('material', $product, 1);
            if (!empty($main_material)) {
                if (is_array($main_material)) {
                    $found_materials = $main_material;
                } else {
                    $found_materials = [$main_material];
                }
            }
        }

        return !empty($found_materials) ? $found_materials : null;
    }

    /**
     * 🆕 提取顶部颜色
     * @param WC_Product $product 产品对象
     * @return array 顶部颜色（数组格式）
     */
    private function extract_top_color($product) {
        $content = strtolower($product->get_name() . ' ' . $product->get_description() . ' ' . $product->get_short_description());

        // 顶部颜色关键词
        $top_color_patterns = [
            '/(?:table\s*top|top|surface).*?(black|white|brown|gray|grey|beige|cream|natural|wood|dark|light|medium)/i',
            '/(black|white|brown|gray|grey|beige|cream|natural|wood|dark|light|medium).*?(?:table\s*top|top|surface)/i'
        ];

        $found_colors = [];

        foreach ($top_color_patterns as $pattern) {
            if (preg_match_all($pattern, $content, $matches)) {
                foreach ($matches[1] as $color) {
                    $color = ucfirst(strtolower($color));
                    if (!in_array($color, $found_colors)) {
                        $found_colors[] = $color;
                    }
                }
            }
        }

        // 如果没有找到顶部颜色，使用产品主体颜色
        if (empty($found_colors)) {
            $main_color = $this->generate_special_attribute_value('color', $product, 1);
            if (!empty($main_color)) {
                if (is_array($main_color)) {
                    $found_colors = $main_color;
                } else {
                    $found_colors = [$main_color];
                }
            }
        }

        // 如果还是没有，使用默认值
        if (empty($found_colors)) {
            $found_colors = ['Natural'];
        }

        return $found_colors;
    }

    /**
     * 🆕 提取产品形状
     * @param WC_Product $product 产品对象
     * @return string 产品形状
     */
    private function extract_product_shape($product) {
        // 1. 优先从产品属性获取
        $shape_attributes = ['Shape', 'shape', 'Product Shape', 'Item Shape'];
        foreach ($shape_attributes as $attr) {
            $shape = $product->get_attribute($attr);
            if (!empty($shape)) {
                return $this->normalize_shape_value($shape);
            }
        }

        // 2. 从产品名称、描述、简短描述中提取内容
        $content = strtolower($product->get_name() . ' ' . $product->get_description() . ' ' . $product->get_short_description());

        // 3. 沃尔玛API标准形状关键词映射（47种标准形状）
        $shape_patterns = [
            // 基础几何形状
            'Round' => ['round', 'circular', 'circle'],
            'Square' => ['square', 'squared'],
            'Rectangle' => ['rectangle', 'rectangular', 'oblong'],
            'Oval' => ['oval', 'elliptical'],
            'Triangle' => ['triangle', 'triangular', 'tri-angle'],
            'Diamond' => ['diamond', 'diamond-shaped', 'rhombus'],
            'Hexagon' => ['hexagon', 'hexagonal', '6-sided'],
            'Octagon' => ['octagon', 'octagonal', '8-sided'],
            'Pentagon' => ['pentagon', 'pentagonal', '5-sided'],

            // 特殊形状
            'Heart' => ['heart', 'heart-shaped', 'valentine'],
            'Star' => ['star', 'star-shaped', 'stellar'],
            'Curved' => ['curved', 'curved edge', 'rounded edge', 'arc'],
            'Straight' => ['straight', 'linear', 'straight line'],
            'Angled' => ['angled', 'angular', 'sharp angle'],
            'Slanted' => ['slanted', 'tilted', 'diagonal'],

            // 自然形状
            'Leaf' => ['leaf', 'leaf-shaped', 'foliage'],
            'Flower' => ['flower', 'floral', 'petal'],
            'Tree' => ['tree', 'tree-shaped', 'branch'],
            'Fish' => ['fish', 'fish-shaped'],
            'Butterfly' => ['butterfly', 'butterfly-shaped'],
            'Pear' => ['pear', 'pear-shaped', 'teardrop'],
            'Strawberry' => ['strawberry', 'strawberry-shaped'],
            'Pumpkin' => ['pumpkin', 'pumpkin-shaped'],

            // 功能形状
            'Bowl' => ['bowl', 'bowl-shaped', 'concave'],
            'Cup' => ['cup', 'cup-shaped'],
            'Cone' => ['cone', 'conical', 'cone-shaped'],
            'Box' => ['box', 'box-shaped', 'cubic'],
            'Ring' => ['ring', 'ring-shaped', 'circular ring'],
            'Saucer' => ['saucer', 'saucer-shaped'],

            // 字母和符号形状
            'U-Shape' => ['u-shape', 'u shape', 'horseshoe', 'u-shaped'],
            'V-Shape' => ['v-shape', 'v shape', 'v-shaped'],
            'D-Shape' => ['d-shape', 'd shape', 'd-shaped'],

            // 复合形状
            'Semicircle' => ['semicircle', 'half circle', 'semi-circle'],
            'Rounded Triangle' => ['rounded triangle', 'soft triangle'],
            'Elliptical' => ['elliptical', 'ellipse'],
            'Elongated' => ['elongated', 'extended', 'stretched'],
            'Flat' => ['flat', 'planar', 'level'],
            'Geometric' => ['geometric', 'geometrical', 'abstract geometric'],

            // 特殊主题形状
            'Snowflake' => ['snowflake', 'snow flake'],
            'Snowman' => ['snowman', 'snow man'],
            'Musical Note' => ['musical note', 'music note', 'note'],
            'Palm' => ['palm', 'palm-shaped'],
            'Kidney' => ['kidney', 'kidney-shaped'],
            'Bone' => ['bone', 'bone-shaped'],
            'Pie Chart' => ['pie chart', 'pie-chart', 'sector'],
            'Teardrop' => ['teardrop', 'tear drop', 'drop']
        ];

        // 4. 按优先级匹配形状
        foreach ($shape_patterns as $shape => $keywords) {
            foreach ($keywords as $keyword) {
                if (strpos($content, $keyword) !== false) {
                    return $shape;
                }
            }
        }

        // 5. 默认值：Asymmetrical
        return 'Asymmetrical';
    }

    /**
     * 🆕 标准化形状值
     * @param string $shape 原始形状值
     * @return string 标准化的形状值
     */
    private function normalize_shape_value($shape) {
        if (empty($shape)) {
            return 'Asymmetrical';
        }

        $shape = trim($shape);

        // 沃尔玛API标准形状列表
        $standard_shapes = [
            'Angled', 'Asymmetrical', 'Bone', 'Bowl', 'Box', 'Butterfly', 'Cone', 'Cup',
            'Curved', 'D-Shape', 'Diamond', 'Elliptical', 'Elongated', 'Fish', 'Flat',
            'Flower', 'Geometric', 'Hexagon', 'Octagon', 'Pentagon', 'Square', 'Triangle',
            'Heart', 'Kidney', 'Leaf', 'Musical Note', 'Palm', 'Pear', 'Pie Chart',
            'Pumpkin', 'Rectangle', 'Round', 'Circle', 'Oval', 'Ring', 'Rounded Triangle',
            'Saucer', 'Semicircle', 'Slanted', 'Snowflake', 'Snowman', 'Star', 'Straight',
            'Strawberry', 'Teardrop', 'Tree', 'U-Shape', 'V-Shape'
        ];

        // 精确匹配
        foreach ($standard_shapes as $standard_shape) {
            if (strcasecmp($shape, $standard_shape) === 0) {
                return $standard_shape;
            }
        }

        // 包含匹配 - 使用关键词映射
        $shape_lower = strtolower($shape);

        // 特殊关键词映射
        $keyword_mappings = [
            'circular' => 'Round',
            'circle' => 'Round',
            'rectangular' => 'Rectangle',
            'triangular' => 'Triangle',
            'elliptical' => 'Oval',
            'hexagonal' => 'Hexagon',
            'octagonal' => 'Octagon',
            'pentagonal' => 'Pentagon'
        ];

        foreach ($keyword_mappings as $keyword => $mapped_shape) {
            if (strpos($shape_lower, $keyword) !== false) {
                return $mapped_shape;
            }
        }

        // 标准形状包含匹配
        foreach ($standard_shapes as $standard_shape) {
            if (strpos($shape_lower, strtolower($standard_shape)) !== false) {
                return $standard_shape;
            }
        }

        // 如果都不匹配，返回默认值
        return 'Asymmetrical';
    }

    /**
     * 从产品标题和描述中提取门的数量
     * @param WC_Product $product 产品对象
     * @return int 门的数量，默认为0
     */
    private function extract_number_of_doors($product) {
        // 获取产品标题和描述
        $title = strtolower($product->get_name());
        $description = strtolower($product->get_description());
        $short_description = strtolower($product->get_short_description());

        // 合并所有文本内容
        $content = $title . ' ' . $description . ' ' . $short_description;

        // 定义门数量的匹配模式
        $patterns = [
            // 数字+door/doors模式
            '/(\d+)[\s-]*doors?/i',
            '/(\d+)[\s-]*door/i',
            // door/doors+数字模式
            '/doors?\s*(\d+)/i',
            '/door\s*(\d+)/i',
            // 特殊表达式
            '/single[\s-]*door/i' => 1,
            '/double[\s-]*door/i' => 2,
            '/triple[\s-]*door/i' => 3,
            '/one[\s-]*door/i' => 1,
            '/two[\s-]*door/i' => 2,
            '/three[\s-]*door/i' => 3,
            '/four[\s-]*door/i' => 4,
            '/five[\s-]*door/i' => 5,
            '/six[\s-]*door/i' => 6,
        ];

        // 首先检查特殊表达式（固定值）
        $special_patterns = [
            '/single[\s-]*door/i' => 1,
            '/double[\s-]*door/i' => 2,
            '/triple[\s-]*door/i' => 3,
            '/one[\s-]*door/i' => 1,
            '/two[\s-]*door/i' => 2,
            '/three[\s-]*door/i' => 3,
            '/four[\s-]*door/i' => 4,
            '/five[\s-]*door/i' => 5,
            '/six[\s-]*door/i' => 6,
        ];

        foreach ($special_patterns as $pattern => $count) {
            if (preg_match($pattern, $content)) {
                return $count;
            }
        }

        // 检查数字模式
        $number_patterns = [
            '/(\d+)[\s-]*doors?/i',
            '/(\d+)[\s-]*door/i',
            '/doors?\s*(\d+)/i',
            '/door\s*(\d+)/i',
        ];

        foreach ($number_patterns as $pattern) {
            if (preg_match($pattern, $content, $matches)) {
                $number = intval($matches[1]);
                // 验证数字合理性（0-100之间）
                if ($number >= 0 && $number <= 100) {
                    return $number;
                }
            }
        }

        // 如果没有找到任何门相关信息，返回默认值0
        return 0;
    }

    /**
     * 从产品标题和描述中提取层数或级数
     * @param WC_Product $product 产品对象
     * @return int 层数，默认为0
     */
    private function extract_number_of_tiers($product) {
        // 获取产品标题和描述
        $title = strtolower($product->get_name());
        $description = strtolower($product->get_description());
        $short_description = strtolower($product->get_short_description());

        // 合并所有文本内容
        $content = $title . ' ' . $description . ' ' . $short_description;

        // 定义层数的匹配模式
        $patterns = [
            // 数字+tier/tiers模式
            '/(\d+)[\s-]*tiers?/i',
            '/(\d+)[\s-]*tier/i',
            // tier/tiers+数字模式
            '/tiers?\s*(\d+)/i',
            '/tier\s*(\d+)/i',
            // 数字+level/levels模式
            '/(\d+)[\s-]*levels?/i',
            '/(\d+)[\s-]*level/i',
            // level/levels+数字模式
            '/levels?\s*(\d+)/i',
            '/level\s*(\d+)/i',
            // 数字+layer/layers模式
            '/(\d+)[\s-]*layers?/i',
            '/(\d+)[\s-]*layer/i',
            // layer/layers+数字模式
            '/layers?\s*(\d+)/i',
            '/layer\s*(\d+)/i',
            // 数字+shelf/shelves模式（架子层数）
            '/(\d+)[\s-]*shelves?/i',
            '/(\d+)[\s-]*shelf/i',
            // shelf/shelves+数字模式
            '/shelves?\s*(\d+)/i',
            '/shelf\s*(\d+)/i',
        ];

        // 特殊表达式（固定值）
        $special_patterns = [
            '/single[\s-]*tier/i' => 1,
            '/double[\s-]*tier/i' => 2,
            '/triple[\s-]*tier/i' => 3,
            '/multi[\s-]*tier/i' => 3, // multi-tier默认为3层
            '/one[\s-]*tier/i' => 1,
            '/two[\s-]*tier/i' => 2,
            '/three[\s-]*tier/i' => 3,
            '/four[\s-]*tier/i' => 4,
            '/five[\s-]*tier/i' => 5,
            '/six[\s-]*tier/i' => 6,
            // level相关
            '/single[\s-]*level/i' => 1,
            '/double[\s-]*level/i' => 2,
            '/triple[\s-]*level/i' => 3,
            '/multi[\s-]*level/i' => 3,
            // layer相关
            '/single[\s-]*layer/i' => 1,
            '/double[\s-]*layer/i' => 2,
            '/triple[\s-]*layer/i' => 3,
            '/multi[\s-]*layer/i' => 3,
        ];

        // 首先检查特殊表达式（固定值）
        foreach ($special_patterns as $pattern => $count) {
            if (preg_match($pattern, $content)) {
                return $count;
            }
        }

        // 检查数字模式
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $content, $matches)) {
                $number = intval($matches[1]);
                // 验证数字合理性（0-100之间）
                if ($number >= 0 && $number <= 100) {
                    return $number;
                }
            }
        }

        // 如果没有找到任何层数相关信息，返回默认值0
        return 0;
    }

    /**
     * 🆕 提取桌子颜色信息
     */
    private function extract_table_color($product) {
        $product_name = strtolower($product->get_name());
        $description = strtolower($product->get_description());
        $short_description = strtolower($product->get_short_description());
        $content = $product_name . ' ' . $description . ' ' . $short_description;

        // 常见颜色关键词
        $color_patterns = [
            '/\b(black|ebony|charcoal|dark)\b/i' => 'Black',
            '/\b(white|ivory|cream|off-white)\b/i' => 'White',
            '/\b(blue|navy|royal blue|light blue)\b/i' => 'Blue',
            '/\b(brown|walnut|mahogany|espresso|chocolate)\b/i' => 'Brown',
            '/\b(gray|grey|silver|pewter)\b/i' => 'Gray',
            '/\b(red|cherry|burgundy|crimson)\b/i' => 'Red',
            '/\b(green|forest|sage|olive)\b/i' => 'Green',
            '/\b(yellow|gold|golden|amber)\b/i' => 'Yellow',
            '/\b(orange|copper|rust)\b/i' => 'Orange',
            '/\b(purple|violet|lavender)\b/i' => 'Purple',
            '/\b(pink|rose|blush)\b/i' => 'Pink',
            '/\b(beige|tan|sand|natural)\b/i' => 'Beige',
        ];

        // 在产品内容中搜索颜色
        foreach ($color_patterns as $pattern => $color) {
            if (preg_match($pattern, $content)) {
                return $color;
            }
        }

        // 尝试从产品属性获取主题颜色
        $main_color = $product->get_attribute('Main Color');
        if (!empty($main_color)) {
            return $main_color;
        }

        $color_attr = $product->get_attribute('Color');
        if (!empty($color_attr)) {
            return $color_attr;
        }

        // 如果都没有找到，返回null（留空不传递）
        return null;
    }

    /**
     * 🆕 提取桌面类型信息
     */
    private function extract_table_top_type($product) {
        $product_name = strtolower($product->get_name());
        $description = strtolower($product->get_description());
        $short_description = strtolower($product->get_short_description());
        $content = $product_name . ' ' . $description . ' ' . $short_description;

        // 桌面类型关键词
        $type_patterns = [
            '/\b(lift[\s-]*top|lifting[\s-]*top|lift[\s-]*up)\b/i' => 'Lift Top',
            '/\b(tray[\s-]*top|tray[\s-]*style)\b/i' => 'Tray Top',
        ];

        // 在产品内容中搜索桌面类型
        foreach ($type_patterns as $pattern => $type) {
            if (preg_match($pattern, $content)) {
                return $type;
            }
        }

        // 根据用户需求，如果没有找到则默认返回Tray Top
        return 'Tray Top';
    }

    /**
     * 🆕 提取桌子高度信息
     */
    private function extract_table_height($product) {
        $product_name = strtolower($product->get_name());
        $description = strtolower($product->get_description());
        $short_description = strtolower($product->get_short_description());
        $content = $product_name . ' ' . $description . ' ' . $short_description;

        // 高度模式匹配
        $height_patterns = [
            '/\b(\d+(?:\.\d+)?)\s*(?:inch|inches|in|")\s*(?:high|height|tall)\b/i',
            '/\b(?:height|high|tall)[\s:]*(\d+(?:\.\d+)?)\s*(?:inch|inches|in|")\b/i',
            '/\b(\d+(?:\.\d+)?)\s*(?:inch|inches|in|")\s*h\b/i',
            '/\bh[\s:]*(\d+(?:\.\d+)?)\s*(?:inch|inches|in|")\b/i',
        ];

        // 在产品内容中搜索高度
        foreach ($height_patterns as $pattern) {
            if (preg_match($pattern, $content, $matches)) {
                $height = floatval($matches[1]);
                if ($height > 0 && $height <= 10000000000000000) {
                    return [
                        'measure' => $height,
                        'unit' => 'in'
                    ];
                }
            }
        }

        // 尝试从产品属性获取高度
        $height_attr = $product->get_attribute('Height');
        if (!empty($height_attr)) {
            // 提取数字
            if (preg_match('/(\d+(?:\.\d+)?)/', $height_attr, $matches)) {
                $height = floatval($matches[1]);
                if ($height > 0) {
                    return [
                        'measure' => $height,
                        'unit' => 'in'
                    ];
                }
            }
        }

        // 如果都没有找到，返回null（留空不传递）
        return null;
    }

    /**
     * 提取扶手高度信息
     *
     * @param WC_Product $product WooCommerce产品对象
     * @return array 测量对象格式 {measure: number, unit: "in"}
     */
    private function extract_arm_height($product) {
        // 从产品标题和描述中提取扶手高度信息
        $content = strtolower($product->get_name() . ' ' . $product->get_description() . ' ' . $product->get_short_description());

        // 定义扶手高度匹配模式
        $height_patterns = [
            // 直接扶手高度描述
            '/arm\s*height[:\s]*(\d+(?:\.\d+)?)\s*(?:in|inch|inches|")?/i',
            '/armrest\s*height[:\s]*(\d+(?:\.\d+)?)\s*(?:in|inch|inches|")?/i',
            '/扶手高度[:\s]*(\d+(?:\.\d+)?)\s*(?:in|inch|inches|英寸|")?/i',

            // 扶手+高度描述
            '/arm[:\s]*(\d+(?:\.\d+)?)\s*(?:in|inch|inches|")?\s*high/i',
            '/armrest[:\s]*(\d+(?:\.\d+)?)\s*(?:in|inch|inches|")?\s*high/i',
            '/(\d+(?:\.\d+)?)\s*(?:in|inch|inches|")?\s*arm\s*height/i',

            // 椅子扶手高度
            '/chair\s*arm\s*height[:\s]*(\d+(?:\.\d+)?)\s*(?:in|inch|inches|")?/i',
            '/seat\s*arm\s*height[:\s]*(\d+(?:\.\d+)?)\s*(?:in|inch|inches|")?/i',
        ];

        // 在产品内容中搜索扶手高度
        foreach ($height_patterns as $pattern) {
            if (preg_match($pattern, $content, $matches)) {
                $height = floatval($matches[1]);
                // 验证高度合理性（0.1-100英寸之间）
                if ($height >= 0.1 && $height <= 100) {
                    return [
                        'measure' => $height,
                        'unit' => 'in'
                    ];
                }
            }
        }

        // 如果没有找到，返回默认值1 in
        return [
            'measure' => 1.0,
            'unit' => 'in'
        ];
    }

    /**
     * 从产品描述提取包含物品信息
     *
     * @param WC_Product $product WooCommerce产品对象
     * @return array 包含物品数组
     */
    private function extract_items_included($product) {
        // 直接从产品名称提取主体物品
        $name = strip_tags($product->get_name());
        $description = strip_tags($product->get_description());
        $short_description = strip_tags($product->get_short_description());

        // 提取产品主体
        $main_items = $this->extract_main_product_items($name, $description, $short_description);

        if (!empty($main_items)) {
            return $main_items;
        }

        // 如果无法提取，返回默认值
        return ['Product As Described'];
    }

    /**
     * 提取产品主体物品
     *
     * @param string $name 产品名称
     * @param string $description 产品描述
     * @param string $short_description 简短描述
     * @return array 主体物品数组
     */
    private function extract_main_product_items($name, $description, $short_description) {
        $content = strtolower($name . ' ' . $description . ' ' . $short_description);
        $items = [];

        // 定义产品主体关键词（按优先级排序，具体的在前面）
        $product_keywords = [
            // 特殊组合类（优先级最高）
            'table lamp' => ['\btable\s+lamp\b'],
            'floor lamp' => ['\bfloor\s+lamp\b'],
            'desk lamp' => ['\bdesk\s+lamp\b'],
            'ceiling fan' => ['\bceiling\s+fan\b', '\bfan\s+light\b'],
            'patio set' => ['\bpatio\s+set\b', '\boutdoor\s+set\b', '\boutdoor\s+dining\s+set\b'],
            'desk chair' => ['\boffice\s+chair\b', '\bdesk\s+chair\b', '\btask\s+chair\b'],
            'file cabinet' => ['\bfile\s+cabinet\b', '\bfiling\s+cabinet\b'],
            'box spring' => ['\bbox\s+spring\b', '\bfoundation\b'],
            'sheet set' => ['\bsheet\s+set\b', '\bbedding\s+set\b'],
            'picture frame' => ['\bpicture\s+frame\b', '\bphoto\s+frame\b'],

            // 床类
            'bed' => ['\bbed\s+frame\b', '\bbed\b(?!\s*(?:room|set|sheet|spread|skirt|rail))'],
            'headboard' => ['\bheadboard\b'],
            'footboard' => ['\bfootboard\b'],
            'mattress' => ['\bmattress\b'],

            // 沙发类
            'sofa' => ['\bsofa\b', '\bcouch\b', '\bsectional\b', '\bloveseat\b', '\brecline\b'],
            'ottoman' => ['\bottoman\b', '\bfootstool\b'],

            // 储物类
            'dresser' => ['\bdresser\b', '\bchest\s+of\s+drawers\b'],
            'nightstand' => ['\bnightstand\b', '\bbedside\s+table\b'],
            'wardrobe' => ['\bwardrobe\b', '\barmoire\b'],
            'cabinet' => ['\bcabinet\b', '\bhutch\b', '\bbuffet\b'],
            'shelf' => ['\bshelf\b', '\bbookcase\b', '\bbookshelf\b', '\bshelving\b'],
            'drawer' => ['\bdrawer\b(?!\s+(?:slide|pull|handle))'],

            // 桌子类（放在后面，避免与lamp冲突）
            'table' => ['\btable\b(?!\s+lamp)', '\bdesk\b(?!\s+(?:lamp|chair))', '\bcounter\b', '\bbar\s+top\b'],

            // 椅子类
            'chair' => ['\bchair\b(?!\s+(?:rail|leg))', '\bstool\b', '\bbench\b', '\bseating\b'],

            // 照明类
            'lamp' => ['\blamp\b(?!\s+(?:table|floor|desk))', '\blight\s+fixture\b', '\bchandelier\b', '\bsconce\b'],

            // 装饰类
            'mirror' => ['\bmirror\b'],
            'vase' => ['\bvase\b'],
            'candle' => ['\bcandle\b', '\bcandle\s+holder\b'],

            // 地面装饰
            'rug' => ['\brug\b', '\bcarpet\b', '\bmat\b(?!\s+(?:yoga|exercise))'],
            'runner' => ['\brunner\b(?=\s+(?:rug|carpet))'],

            // 窗饰
            'curtain' => ['\bcurtain\b', '\bdrape\b', '\bvalance\b'],
            'blind' => ['\bblind\b', '\bshade\b(?!\s+(?:lamp|light))'],

            // 床上用品
            'pillow' => ['\bpillow\b', '\bcushion\b'],
            'blanket' => ['\bblanket\b', '\bthrow\b', '\bcomforter\b', '\bquilt\b'],

            // 收纳类
            'basket' => ['\bbasket\b', '\bbin\b(?!\s+(?:trash|garbage))', '\borganizer\b'],
            'box' => ['\bstorage\s+box\b', '\bcontainer\b'],
            'rack' => ['\brack\b(?!\s+(?:spice|wine))', '\bstand\b(?!\s+(?:night|bed))'],

            // 户外类
            'umbrella' => ['\bumbrella\b(?=\s+(?:patio|outdoor|garden))'],
            'planter' => ['\bplanter\b', '\bpot\b(?=\s+(?:plant|flower))']
        ];

        // 首先检查"不包含"的情况，排除相关物品
        $excluded_items = $this->get_excluded_items($content);

        // 检测产品主体（使用正则表达式精确匹配）
        $detected_items = [];

        foreach ($product_keywords as $main_type => $patterns) {
            // 如果该物品在排除列表中，跳过
            if (in_array(ucfirst($main_type), $excluded_items)) {
                continue;
            }

            foreach ($patterns as $pattern) {
                if (preg_match('/' . $pattern . '/i', $content)) {
                    $detected_items[] = ucfirst($main_type);
                    break; // 找到一个就跳出内层循环
                }
            }
        }

        // 去重
        $detected_items = array_unique($detected_items);

        // 如果检测到多个物品，检查是否为常见组合
        if (count($detected_items) > 1) {
            // 检查常见组合
            $combinations = [
                'Table+Chair' => ['Table', 'Chair'],
                'Dresser+Mirror' => ['Dresser', 'Mirror'],
                'Sofa+Ottoman' => ['Sofa', 'Ottoman'],
            ];

            // 检查桌子+椅子组合
            if (in_array('Table', $detected_items) && in_array('Chair', $detected_items)) {
                return ['Table', 'Chair'];
            }

            // 检查梳妆台+镜子组合
            if (in_array('Dresser', $detected_items) && in_array('Mirror', $detected_items)) {
                return ['Dresser', 'Mirror'];
            }

            // 检查沙发+脚凳组合
            if (in_array('Sofa', $detected_items) && in_array('Ottoman', $detected_items)) {
                return ['Sofa', 'Ottoman'];
            }

            // 床架相关组合（床架包含床头板/床尾板）
            if (in_array('Bed', $detected_items)) {
                return ['Bed']; // 床架是主体
            }

            // 特殊处理：如果包含特定组合词，优先返回组合词
            $priority_items = [];
            foreach ($detected_items as $item) {
                if (strpos($item, ' ') !== false) { // 包含空格的是组合词，优先级高
                    $priority_items[] = $item;
                }
            }

            if (!empty($priority_items)) {
                return array_slice($priority_items, 0, 1); // 只返回最重要的组合词
            }

            // 如果不是预定义组合，返回前两个最重要的物品
            return array_slice($detected_items, 0, 2);
        }

        // 如果只检测到一个物品，返回该物品
        if (count($detected_items) === 1) {
            return $detected_items;
        }

        // 如果没有检测到任何物品，返回空数组（让上层处理默认值）
        return [];
    }

    /**
     * 检测描述中明确排除的物品
     *
     * @param string $content 产品内容
     * @return array 排除的物品列表
     */
    private function get_excluded_items($content) {
        $excluded_items = [];

        // 定义排除模式
        $exclusion_patterns = [
            // 不包含床垫
            '/(?:does\s+not\s+include|not\s+included?|excludes?|without).*?mattress/i' => 'Mattress',
            '/mattress.*?(?:not\s+included?|sold\s+separately)/i' => 'Mattress',

            // 不包含枕头
            '/(?:does\s+not\s+include|not\s+included?|excludes?|without).*?pillow/i' => 'Pillow',
            '/pillow.*?(?:not\s+included?|sold\s+separately)/i' => 'Pillow',

            // 不包含床上用品
            '/(?:does\s+not\s+include|not\s+included?|excludes?|without).*?(?:bedding|sheet|blanket)/i' => 'Sheet set',
            '/(?:bedding|sheet|blanket).*?(?:not\s+included?|sold\s+separately)/i' => 'Sheet set',

            // 不包含装饰品
            '/(?:does\s+not\s+include|not\s+included?|excludes?|without).*?(?:decor|decoration|accessory)/i' => 'Decoration',
            '/(?:decor|decoration|accessory).*?(?:not\s+included?|sold\s+separately)/i' => 'Decoration',

            // 不包含灯泡
            '/(?:does\s+not\s+include|not\s+included?|excludes?|without).*?(?:bulb|light\s+bulb)/i' => 'Bulb',
            '/(?:bulb|light\s+bulb).*?(?:not\s+included?|sold\s+separately)/i' => 'Bulb',

            // 不包含组装工具
            '/(?:does\s+not\s+include|not\s+included?|excludes?|without).*?(?:tool|hardware)/i' => 'Tools',
            '/(?:tool|hardware).*?(?:not\s+included?|sold\s+separately)/i' => 'Tools',

            // 中文排除模式
            '/(?:不包含|不含|不包括|另售).*?(?:床垫|枕头|床品|装饰|灯泡|工具)/u' => 'Various'
        ];

        foreach ($exclusion_patterns as $pattern => $excluded_item) {
            if (preg_match($pattern, $content)) {
                $excluded_items[] = $excluded_item;
            }
        }

        return array_unique($excluded_items);
    }

    /**
     * 验证物品名称的合理性
     *
     * @param string $item_name 物品名称
     * @return bool 是否为有效的物品名称
     */
    private function is_valid_item_name($item_name) {
        // 基础检查
        if (empty($item_name) || strlen($item_name) < 3 || strlen($item_name) > 50) {
            return false;
        }

        // 过滤明显的无效内容
        $invalid_patterns = [
            '/^[\d\s\W]*$/',                    // 只包含数字、空格和特殊字符
            '/^\s*[:\-\|]\s*/',                 // 以冒号、破折号、竖线开头
            '/\b(?:color|material|style|weight|size|dimension|specification)\b/i', // 包含规格词汇
            '/\b(?:lb|kg|inch|cm|mm|ft)\b/i',   // 包含单位
            '/[<>]|&[a-z]+;/',                  // HTML标签或实体
            '/^\s*\d+[\s\W]*\d+/',              // 以数字开头的尺寸格式
        ];

        foreach ($invalid_patterns as $pattern) {
            if (preg_match($pattern, $item_name)) {
                return false;
            }
        }

        // 验证包含有效的物品关键词
        $valid_keywords = [
            'table', 'chair', 'stool', 'bench', 'desk', 'shelf', 'drawer', 'door', 'panel',
            'cushion', 'pillow', 'mattress', 'headboard', 'footboard', 'rail', 'ladder',
            'hardware', 'screw', 'bolt', 'bracket', 'hinge', 'handle', 'knob',
            'manual', 'instruction', 'guide', 'tool', 'wrench', 'key',
            'cover', 'fabric', 'upholstery', 'leather', 'wood', 'metal', 'plastic',
            'storage', 'box', 'bin', 'container', 'organizer',
            'coffee', 'dining', 'side', 'end', 'night', 'bedside', 'office', 'computer'
        ];

        $item_lower = strtolower($item_name);
        foreach ($valid_keywords as $keyword) {
            if (strpos($item_lower, $keyword) !== false) {
                return true;
            }
        }

        // 如果不包含关键词但格式合理（如"Large Table"），也认为有效
        if (preg_match('/^[\w\s]+$/', $item_name) && str_word_count($item_name) <= 5) {
            return true;
        }

        return false;
    }

    /**
     * 检查是否为套装产品
     *
     * @param string $product_name 产品名称
     * @return bool 是否为套装产品
     */
    private function is_set_product($product_name) {
        $set_keywords = [
            'set', 'collection', 'suite', 'group', 'combo', 'bundle',
            'nesting', 'nested', 'stackable', 'matching', 'coordinating',
            'piece', 'pcs', '件套', '套装', '组合'
        ];

        $name_lower = strtolower($product_name);

        foreach ($set_keywords as $keyword) {
            if (strpos($name_lower, $keyword) !== false) {
                return true;
            }
        }

        // 检查数字+piece模式，如"5-piece", "3 piece"
        if (preg_match('/\d+[\s\-]*piece/i', $product_name)) {
            return true;
        }

        return false;
    }

    /**
     * 提取产品主体名称
     *
     * @param string $product_name 产品名称
     * @return string 主体名称
     */
    private function extract_main_product_name($product_name) {
        // 移除数量和套装关键词，提取核心产品名称
        $clean_name = preg_replace('/\d+[\s\-]*(?:piece|pcs?|件)/i', '', $product_name);
        $clean_name = preg_replace('/\b(?:set|collection|suite|group|combo|bundle|nesting|nested)\b/i', '', $clean_name);
        $clean_name = trim(preg_replace('/\s+/', ' ', $clean_name));

        // 如果清理后太短，使用原名称
        if (strlen($clean_name) < 5) {
            return trim($product_name);
        }

        return $clean_name;
    }

    /**
     * 智能去重和合并相似物品
     *
     * @param array $items 物品数组
     * @param string $product_name 产品名称
     * @return array 去重后的物品数组
     */
    private function smart_deduplicate_items($items, $product_name) {
        if (empty($items)) {
            return $items;
        }

        // 基础去重
        $items = array_unique($items);

        // 如果只有一个物品，直接返回
        if (count($items) <= 1) {
            return $items;
        }

        // 检查是否为套装产品，如果是则合并为一个主体
        if ($this->is_set_product($product_name)) {
            $main_product = $this->extract_main_product_name($product_name);
            return [$main_product];
        }

        // 智能合并相似物品
        $merged_items = [];
        $processed = [];

        foreach ($items as $item) {
            if (in_array($item, $processed)) {
                continue;
            }

            $similar_items = [$item];
            $processed[] = $item;

            // 查找相似物品
            foreach ($items as $other_item) {
                if ($item !== $other_item && !in_array($other_item, $processed)) {
                    if ($this->are_similar_items($item, $other_item)) {
                        $similar_items[] = $other_item;
                        $processed[] = $other_item;
                    }
                }
            }

            // 如果有相似物品，合并为一个
            if (count($similar_items) > 1) {
                $merged_items[] = $this->merge_similar_items($similar_items);
            } else {
                $merged_items[] = $item;
            }
        }

        return $merged_items;
    }

    /**
     * 检查两个物品是否相似
     *
     * @param string $item1 物品1
     * @param string $item2 物品2
     * @return bool 是否相似
     */
    private function are_similar_items($item1, $item2) {
        $item1_lower = strtolower($item1);
        $item2_lower = strtolower($item2);

        // 检查是否包含相同的核心词汇
        $core_words = ['table', 'chair', 'stool', 'desk', 'shelf', 'drawer', 'cabinet'];

        foreach ($core_words as $word) {
            if (strpos($item1_lower, $word) !== false && strpos($item2_lower, $word) !== false) {
                return true;
            }
        }

        // 检查词汇重叠度
        $words1 = explode(' ', $item1_lower);
        $words2 = explode(' ', $item2_lower);
        $common_words = array_intersect($words1, $words2);

        // 如果有50%以上的词汇重叠，认为相似
        $overlap_ratio = count($common_words) / max(count($words1), count($words2));
        return $overlap_ratio >= 0.5;
    }

    /**
     * 合并相似物品
     *
     * @param array $similar_items 相似物品数组
     * @return string 合并后的物品名称
     */
    private function merge_similar_items($similar_items) {
        // 选择最简洁且包含核心信息的名称
        usort($similar_items, function($a, $b) {
            // 优先选择不包含数量的名称
            $a_has_number = preg_match('/^\d+/', $a);
            $b_has_number = preg_match('/^\d+/', $b);

            if ($a_has_number && !$b_has_number) return 1;
            if (!$a_has_number && $b_has_number) return -1;

            // 其次选择较短的名称
            return strlen($a) - strlen($b);
        });

        return $similar_items[0];
    }

    /**
     * 提取腿部颜色信息
     *
     * @param WC_Product $product WooCommerce产品对象
     * @return string 腿部颜色
     */
    private function extract_leg_color($product) {
        // 从产品标题和描述中提取腿部颜色信息
        $content = strtolower($product->get_name() . ' ' . $product->get_description() . ' ' . $product->get_short_description());

        // 定义腿部颜色匹配模式
        $leg_color_patterns = [
            // 直接腿部颜色描述
            '/(?:leg|legs)\s*(?:are|is|in)?\s*(black|white|brown|gray|grey|silver|gold|bronze|copper|brass|natural|wood|metal|chrome|stainless)/i',
            '/(?:leg|legs)\s*(?:color|colour)[:\s]*(black|white|brown|gray|grey|silver|gold|bronze|copper|brass|natural|wood|metal|chrome|stainless)/i',

            // 腿部材质+颜色
            '/(black|white|brown|gray|grey|silver|gold|bronze|copper|brass|natural|dark|light)\s*(?:wood|wooden|metal|steel|iron|chrome)\s*(?:leg|legs)/i',
            '/(?:leg|legs)\s*(?:made\s*of|in)?\s*(black|white|brown|gray|grey|silver|gold|bronze|copper|brass|natural|dark|light)\s*(?:wood|wooden|metal|steel|iron|chrome)/i',

            // 特定材质腿部
            '/chrome\s*(?:plated)?\s*(?:leg|legs)/i',
            '/stainless\s*steel\s*(?:leg|legs)/i',
            '/powder\s*coated\s*(black|white|brown|gray|grey|silver)\s*(?:leg|legs)/i',

            // 桌子/椅子腿部描述
            '/(?:table|chair|stool)\s*(?:with|has)?\s*(black|white|brown|gray|grey|silver|gold|bronze|copper|brass|natural|chrome)\s*(?:leg|legs)/i',
            '/(black|white|brown|gray|grey|silver|gold|bronze|copper|brass|natural|chrome)\s*(?:leg|legs)\s*(?:table|chair|stool)/i',

            // 中文关键词
            '/(?:腿|脚|支撑).*?(黑色|白色|棕色|灰色|银色|金色|铜色|自然色|木色|金属色)/i',
            '/(黑色|白色|棕色|灰色|银色|金色|铜色|自然色|木色|金属色).*?(?:腿|脚|支撑)/i'
        ];

        // 搜索腿部颜色模式
        foreach ($leg_color_patterns as $pattern) {
            if (preg_match($pattern, $content, $matches)) {
                $color = isset($matches[1]) ? trim($matches[1]) : '';
                if (empty($color)) continue;

                // 标准化颜色名称
                $color_mapping = [
                    'grey' => 'Gray',
                    'stainless' => 'Stainless Steel',
                    'chrome' => 'Chrome',
                    'natural' => 'Natural',
                    'wood' => 'Natural Wood',
                    'metal' => 'Metal',
                    'dark' => 'Dark',
                    'light' => 'Light',
                    '黑色' => 'Black',
                    '白色' => 'White',
                    '棕色' => 'Brown',
                    '灰色' => 'Gray',
                    '银色' => 'Silver',
                    '金色' => 'Gold',
                    '铜色' => 'Copper',
                    '自然色' => 'Natural',
                    '木色' => 'Natural Wood',
                    '金属色' => 'Metal'
                ];

                $normalized_color = $color_mapping[strtolower($color)] ?? ucwords(strtolower($color));
                return $normalized_color;
            }
        }

        // 如果没有找到特定腿部颜色，尝试使用产品主体颜色
        $main_color = $this->generate_special_attribute_value('color', $product, 1);
        if (!empty($main_color) && $main_color !== 'As shown in the product picture') {
            return $main_color;
        }

        // 默认值
        return 'Color as shown';
    }

    /**
     * 提取腿部材质信息
     *
     * @param WC_Product $product WooCommerce产品对象
     * @return string 腿部材质
     */
    private function extract_leg_material($product) {
        // 从产品标题和描述中提取腿部材质信息
        $content = strtolower($product->get_name() . ' ' . $product->get_description() . ' ' . $product->get_short_description());

        // 定义腿部材质匹配模式
        $leg_material_patterns = [
            // 直接腿部材质描述
            '/(?:leg|legs)\s*(?:are|is|made\s*of|in)?\s*(wood|wooden|metal|steel|iron|aluminum|chrome|stainless\s*steel|plastic|acrylic|glass|carbon\s*fiber)/i',
            '/(?:leg|legs)\s*(?:material|construction)[:\s]*(wood|wooden|metal|steel|iron|aluminum|chrome|stainless\s*steel|plastic|acrylic|glass|carbon\s*fiber)/i',

            // 材质+腿部
            '/(wood|wooden|metal|steel|iron|aluminum|chrome|stainless\s*steel|plastic|acrylic|glass|carbon\s*fiber)\s*(?:leg|legs)/i',
            '/(solid\s*wood|hardwood|softwood|oak|pine|maple|cherry|walnut|mahogany|teak|bamboo)\s*(?:leg|legs)/i',

            // 特定材质描述
            '/chrome\s*(?:plated)?\s*(?:leg|legs)/i',
            '/stainless\s*steel\s*(?:leg|legs)/i',
            '/powder\s*coated\s*(?:metal|steel)\s*(?:leg|legs)/i',
            '/solid\s*(wood|oak|pine|maple|cherry|walnut|mahogany|teak)\s*(?:leg|legs)/i',

            // 桌子/椅子腿部材质
            '/(?:table|chair|stool)\s*(?:with|has)?\s*(wood|wooden|metal|steel|iron|aluminum|chrome|stainless\s*steel|plastic)\s*(?:leg|legs)/i',
            '/(wood|wooden|metal|steel|iron|aluminum|chrome|stainless\s*steel|plastic)\s*(?:leg|legs)\s*(?:table|chair|stool)/i',

            // 框架材质（通常与腿部材质相同）
            '/frame.*?(wood|wooden|metal|steel|iron|aluminum|chrome|stainless\s*steel|plastic)/i',
            '/(wood|wooden|metal|steel|iron|aluminum|chrome|stainless\s*steel|plastic).*?frame/i',

            // 中文关键词
            '/(?:腿|脚|支撑).*?(木质|木材|金属|钢材|铁质|铝合金|不锈钢|塑料|玻璃)/i',
            '/(木质|木材|金属|钢材|铁质|铝合金|不锈钢|塑料|玻璃).*?(?:腿|脚|支撑)/i'
        ];

        // 搜索腿部材质模式
        foreach ($leg_material_patterns as $pattern) {
            if (preg_match($pattern, $content, $matches)) {
                $material = trim($matches[1]);

                // 标准化材质名称
                $material_mapping = [
                    'wooden' => 'Wood',
                    'steel' => 'Steel',
                    'iron' => 'Iron',
                    'aluminum' => 'Aluminum',
                    'chrome' => 'Chrome',
                    'stainless steel' => 'Stainless Steel',
                    'plastic' => 'Plastic',
                    'acrylic' => 'Acrylic',
                    'glass' => 'Glass',
                    'carbon fiber' => 'Carbon Fiber',
                    'solid wood' => 'Solid Wood',
                    'hardwood' => 'Hardwood',
                    'softwood' => 'Softwood',
                    'oak' => 'Oak Wood',
                    'pine' => 'Pine Wood',
                    'maple' => 'Maple Wood',
                    'cherry' => 'Cherry Wood',
                    'walnut' => 'Walnut Wood',
                    'mahogany' => 'Mahogany Wood',
                    'teak' => 'Teak Wood',
                    'bamboo' => 'Bamboo',
                    '木质' => 'Wood',
                    '木材' => 'Wood',
                    '金属' => 'Metal',
                    '钢材' => 'Steel',
                    '铁质' => 'Iron',
                    '铝合金' => 'Aluminum',
                    '不锈钢' => 'Stainless Steel',
                    '塑料' => 'Plastic',
                    '玻璃' => 'Glass'
                ];

                $normalized_material = $material_mapping[strtolower($material)] ?? ucwords(strtolower($material));
                return $normalized_material;
            }
        }

        // 如果没有找到特定腿部材质，尝试使用产品主体材质
        $main_material = $this->generate_special_attribute_value('material', $product, 1);
        if (!empty($main_material)) {
            if (is_array($main_material)) {
                return $main_material[0];
            }
            return $main_material;
        }

        // 默认值
        return 'Please see product description material';
    }

    /**
     * 提取产品图案信息
     *
     * @param WC_Product $product WooCommerce产品对象
     * @return string 产品图案
     */
    private function extract_product_pattern($product) {
        // 从产品标题和描述中提取图案信息
        $content = strtolower($product->get_name() . ' ' . $product->get_description() . ' ' . $product->get_short_description());

        // 定义图案匹配模式
        $pattern_keywords = [
            // 基础图案
            'Solid' => ['solid', 'plain', 'single color', 'one color', 'uniform', '纯色', '单色'],
            'Striped' => ['striped', 'stripe', 'stripes', 'linear', 'lines', '条纹', '条状'],
            'Floral' => ['floral', 'flower', 'flowers', 'botanical', 'rose', 'lily', '花卉', '花朵'],
            'Geometric' => ['geometric', 'diamond', 'triangle', 'square', 'circle', 'hexagon', 'polygon', '几何'],
            'Plaid' => ['plaid', 'checkered', 'checked', 'tartan', 'gingham', '格子', '方格'],
            'Polka Dot' => ['polka dot', 'polka dots', 'dotted', 'spots', 'spotted', '圆点', '波点'],

            // 纹理图案
            'Wood Grain' => ['wood grain', 'grain', 'wooden texture', 'wood pattern', '木纹', '木质纹理'],
            'Marble' => ['marble', 'marbled', 'marble pattern', 'veined', '大理石', '大理石纹'],
            'Textured' => ['textured', 'texture', 'rough', 'bumpy', 'embossed', '纹理', '质感'],

            // 动物图案
            'Animal Print' => ['animal print', 'leopard', 'zebra', 'tiger', 'snake', 'crocodile', '动物纹'],

            // 抽象图案
            'Abstract' => ['abstract', 'artistic', 'modern art', 'contemporary', '抽象', '艺术'],
            'Paisley' => ['paisley', 'teardrop', 'persian', '佩斯利'],

            // 传统图案
            'Traditional' => ['traditional', 'classic', 'vintage', 'antique', 'ornate', '传统', '古典'],
            'Damask' => ['damask', 'baroque', 'ornamental', '锦缎'],

            // 现代图案
            'Contemporary' => ['contemporary', 'modern', 'minimalist', 'sleek', '现代', '当代'],
            'Ombre' => ['ombre', 'gradient', 'fade', 'transition', '渐变'],

            // 特殊图案
            'Tie Dye' => ['tie dye', 'tie-dye', 'dyed', 'psychedelic', '扎染'],
            'Camouflage' => ['camouflage', 'camo', 'military', '迷彩'],
            'Ikat' => ['ikat', 'tribal', 'ethnic', '伊卡特']
        ];

        // 搜索图案关键词
        foreach ($pattern_keywords as $pattern => $keywords) {
            foreach ($keywords as $keyword) {
                if (strpos($content, $keyword) !== false) {
                    return $pattern;
                }
            }
        }

        // 如果没有找到特定图案，尝试使用主体颜色
        $main_color = $this->generate_special_attribute_value('color', $product, 1);
        if (!empty($main_color) && $main_color !== 'As shown in the product picture') {
            // 如果颜色包含多个词，可能是图案描述
            if (strpos($main_color, ' ') !== false) {
                return $main_color;
            }
            // 单一颜色，返回solid
            return 'Solid';
        }

        // 最后的默认值
        return 'Color';
    }

    /**
     * 提取座椅深度信息
     *
     * @param WC_Product $product WooCommerce产品对象
     * @return array 测量对象格式 {measure: number, unit: "in"}
     */
    private function extract_seat_depth($product) {
        // 从产品标题和描述中提取座椅深度信息
        $content = strtolower($product->get_name() . ' ' . $product->get_description() . ' ' . $product->get_short_description());

        // 定义座椅深度匹配模式
        $seat_depth_patterns = [
            // 直接座椅深度描述
            '/(?:seat|cushion)\s*depth[:\s]*(\d+(?:\.\d+)?)\s*(?:in|inch|inches|")?/i',
            '/depth\s*of\s*(?:seat|cushion)[:\s]*(\d+(?:\.\d+)?)\s*(?:in|inch|inches|")?/i',

            // 座椅尺寸描述
            '/(?:seat|cushion)\s*(?:size|dimension)[:\s]*\d+(?:\.\d+)?\s*(?:x|×)\s*(\d+(?:\.\d+)?)\s*(?:in|inch|inches|")?/i',
            '/(?:seat|cushion)[:\s]*\d+(?:\.\d+)?\s*(?:w|width)?\s*(?:x|×)\s*(\d+(?:\.\d+)?)\s*(?:d|depth)?\s*(?:in|inch|inches|")?/i',

            // 椅子整体深度（通常与座椅深度相关）
            '/chair\s*depth[:\s]*(\d+(?:\.\d+)?)\s*(?:in|inch|inches|")?/i',
            '/depth[:\s]*(\d+(?:\.\d+)?)\s*(?:in|inch|inches|")?\s*chair/i',

            // 产品尺寸中的深度（第三个数值通常是深度）
            '/(?:size|dimension)[:\s]*\d+(?:\.\d+)?\s*(?:x|×)\s*\d+(?:\.\d+)?\s*(?:x|×)\s*(\d+(?:\.\d+)?)\s*(?:in|inch|inches|")?/i',

            // 中文关键词
            '/(?:座椅|坐垫|椅子).*?深度[:\s]*(\d+(?:\.\d+)?)\s*(?:in|inch|inches|英寸|")?/i',
            '/深度[:\s]*(\d+(?:\.\d+)?)\s*(?:in|inch|inches|英寸|")?\s*(?:座椅|坐垫|椅子)/i'
        ];

        // 搜索座椅深度模式
        foreach ($seat_depth_patterns as $pattern) {
            if (preg_match($pattern, $content, $matches)) {
                $depth = floatval($matches[1]);
                // 验证深度合理性（5-50英寸之间）
                if ($depth >= 5 && $depth <= 50) {
                    return [
                        'measure' => $depth,
                        'unit' => 'in'
                    ];
                }
            }
        }

        // 如果没有找到，返回默认值1 in
        return [
            'measure' => 1.0,
            'unit' => 'in'
        ];
    }

    /**
     * 提取软垫覆盖状态
     *
     * @param WC_Product $product WooCommerce产品对象
     * @return string Yes或No
     */
    private function extract_upholstered_status($product) {
        // 从产品标题和描述中提取软垫相关信息
        $content = strtolower($product->get_name() . ' ' . $product->get_description() . ' ' . $product->get_short_description());

        // 定义软垫相关关键词（表示Yes）
        $upholstered_yes_keywords = [
            // 直接软垫关键词
            'upholstered', 'padded', 'cushioned', 'fabric covered', 'leather covered',
            'soft seat', 'soft back', 'cushion seat', 'cushion back',

            // 软包材质
            'fabric seat', 'leather seat', 'vinyl seat', 'velvet seat', 'microfiber seat',
            'fabric chair', 'leather chair', 'vinyl chair', 'velvet chair', 'microfiber chair',

            // 软垫描述
            'with cushion', 'with padding', 'with upholstery', 'soft padding',
            'foam padding', 'memory foam', 'high density foam',

            // 舒适性描述
            'comfortable seat', 'plush seat', 'soft seating', 'ergonomic cushion',

            // 中文关键词
            '软垫', '软包', '海绵垫', '坐垫', '靠垫', '填充', '舒适座椅'
        ];

        // 定义非软垫关键词（表示No）
        $upholstered_no_keywords = [
            // 硬质材料
            'wood seat', 'wooden seat', 'metal seat', 'plastic seat', 'hard seat',
            'solid wood', 'bare wood', 'unpadded', 'hard surface',

            // 硬质椅子类型
            'wooden chair', 'metal chair', 'plastic chair', 'hard chair',
            'bar stool', 'counter stool', 'ladder back',

            // 中文关键词
            '硬座', '木质座椅', '金属座椅', '塑料座椅', '硬质'
        ];

        // 检查软垫关键词
        foreach ($upholstered_yes_keywords as $keyword) {
            if (strpos($content, $keyword) !== false) {
                return 'Yes';
            }
        }

        // 检查非软垫关键词
        foreach ($upholstered_no_keywords as $keyword) {
            if (strpos($content, $keyword) !== false) {
                return 'No';
            }
        }

        // 根据产品类型进行智能判断
        $product_type = strtolower($product->get_name());

        // 通常有软垫的产品类型
        $typically_upholstered = [
            'sofa', 'loveseat', 'sectional', 'recliner', 'armchair', 'accent chair',
            'office chair', 'desk chair', 'gaming chair', 'lounge chair'
        ];

        foreach ($typically_upholstered as $type) {
            if (strpos($product_type, $type) !== false) {
                return 'Yes';
            }
        }

        // 通常没有软垫的产品类型
        $typically_not_upholstered = [
            'bar stool', 'counter stool', 'ladder back', 'windsor chair',
            'folding chair', 'stackable chair'
        ];

        foreach ($typically_not_upholstered as $type) {
            if (strpos($product_type, $type) !== false) {
                return 'No';
            }
        }

        // 默认值
        return 'No';
    }

    /**
     * 提取产品线信息
     *
     * @param WC_Product $product WooCommerce产品对象
     * @return string 产品线名称
     */
    private function extract_product_line($product) {
        // 获取产品的分类信息
        $product_categories = wp_get_post_terms($product->get_id(), 'product_cat', array('fields' => 'all'));

        if (!empty($product_categories) && !is_wp_error($product_categories)) {
            // 找到最深层级的分类（最后一级别分类）
            $deepest_category = null;
            $max_depth = -1;

            foreach ($product_categories as $category) {
                // 计算分类的深度
                $depth = $this->get_category_depth($category->term_id);
                if ($depth > $max_depth) {
                    $max_depth = $depth;
                    $deepest_category = $category;
                }
            }

            if ($deepest_category) {
                return $deepest_category->name;
            }
        }

        // 如果没有找到分类，尝试从产品标题中提取产品线信息
        $product_name = $product->get_name();

        // 常见的产品线关键词模式
        $product_line_patterns = [
            // 品牌系列模式
            '/(\w+)\s+(?:series|collection|line|range)/i',
            '/(?:series|collection|line|range)\s+(\w+)/i',

            // 型号系列模式
            '/model\s+(\w+)/i',
            '/(\w+)\s+model/i',

            // 风格系列模式
            '/(\w+)\s+(?:style|design)/i',
            '/(?:style|design)\s+(\w+)/i'
        ];

        foreach ($product_line_patterns as $pattern) {
            if (preg_match($pattern, $product_name, $matches)) {
                return ucwords(strtolower(trim($matches[1])));
            }
        }

        // 最后尝试使用产品类型作为产品线
        $product_type = $product->get_type();
        if (!empty($product_type)) {
            return ucwords(str_replace('_', ' ', $product_type));
        }

        // 默认值
        return 'Standard';
    }

    /**
     * 计算分类的深度
     *
     * @param int $category_id 分类ID
     * @return int 分类深度
     */
    private function get_category_depth($category_id) {
        $depth = 0;
        $parent_id = $category_id;

        while ($parent_id) {
            $category = get_term($parent_id, 'product_cat');
            if (!$category || is_wp_error($category)) {
                break;
            }

            $parent_id = $category->parent;
            $depth++;

            // 防止无限循环
            if ($depth > 10) {
                break;
            }
        }

        return $depth;
    }

    /**
     * 提取柜体颜色
     * 自动从标题和产品描述提取对应的数据值，如果没有则默认使用产品主体颜色，如果都没有则默认留空
     *
     * @param WC_Product $product 产品对象
     * @return string|null 柜体颜色
     */
    private function extract_cabinet_color($product) {
        $content = strtolower($product->get_name() . ' ' . $product->get_description() . ' ' . $product->get_short_description());

        // 1. 从标题和描述中提取柜体颜色关键词
        $cabinet_color_patterns = [
            '/\bcabinet\s+(color|colour):\s*([a-zA-Z\s]+)/i',
            '/\b([a-zA-Z\s]+)\s+cabinet\b/i',
            '/\bcabinet\s+in\s+([a-zA-Z\s]+)/i',
            '/\b(white|black|brown|gray|grey|espresso|navy|natural|oak|cherry|walnut|maple|mahogany|pine|birch|beech|teak|bamboo|cream|ivory|antique|vintage|rustic)\s+cabinet/i',
            '/\bcabinet.*?(white|black|brown|gray|grey|espresso|navy|natural|oak|cherry|walnut|maple|mahogany|pine|birch|beech|teak|bamboo|cream|ivory|antique|vintage|rustic)/i'
        ];

        foreach ($cabinet_color_patterns as $pattern) {
            if (preg_match($pattern, $content, $matches)) {
                $color = trim($matches[count($matches) - 1]);
                if (strlen($color) > 0 && strlen($color) <= 80) {
                    return ucwords($color);
                }
            }
        }

        // 2. 如果没有找到柜体颜色，使用产品主体颜色
        $main_color = $this->generate_special_attribute_value('color', $product, 1);
        if (!empty($main_color)) {
            $color_str = is_array($main_color) ? $main_color[0] : $main_color;
            if (strlen($color_str) <= 80) {
                return ucwords($color_str);
            }
        }

        // 3. 如果都没有，返回null（不传递此字段）
        return null;
    }

    /**
     * 提取柜体材质
     * 自动从标题和产品描述提取对应的数据值，如果没有则默认使用产品主体材质，如果都没有则默认留空
     *
     * @param WC_Product $product 产品对象
     * @return string|null 柜体材质
     */
    private function extract_cabinet_material($product) {
        $content = strtolower($product->get_name() . ' ' . $product->get_description() . ' ' . $product->get_short_description());

        // 1. 从标题和描述中提取柜体材质关键词
        $cabinet_material_patterns = [
            '/\bcabinet\s+(material|made\s+of):\s*([a-zA-Z\s]+)/i',
            '/\b([a-zA-Z\s]+)\s+cabinet\b/i',
            '/\bcabinet\s+made\s+of\s+([a-zA-Z\s]+)/i',
            '/\b(wood|metal|plastic|glass|manufactured\s+wood|mdf|particle\s+board|plywood|solid\s+wood|engineered\s+wood|steel|aluminum|iron|bamboo|rattan|wicker)\s+cabinet/i',
            '/\bcabinet.*?(wood|metal|plastic|glass|manufactured\s+wood|mdf|particle\s+board|plywood|solid\s+wood|engineered\s+wood|steel|aluminum|iron|bamboo|rattan|wicker)/i'
        ];

        foreach ($cabinet_material_patterns as $pattern) {
            if (preg_match($pattern, $content, $matches)) {
                $material = trim($matches[count($matches) - 1]);
                if (strlen($material) > 0 && strlen($material) <= 400) {
                    return ucwords($material);
                }
            }
        }

        // 2. 如果没有找到柜体材质，使用产品主体材质
        $main_material = $this->generate_special_attribute_value('material', $product, 1);
        if (!empty($main_material)) {
            $material_str = is_array($main_material) ? $main_material[0] : $main_material;
            if (strlen($material_str) <= 400) {
                return ucwords($material_str);
            }
        }

        // 3. 如果都没有，返回null（不传递此字段）
        return null;
    }

    /**
     * 提取五金表面处理
     * 自动从标题和产品描述提取对应的数据值，如果没有则默认使用产品颜色
     *
     * @param WC_Product $product 产品对象
     * @return string|null 五金表面处理
     */
    private function extract_hardware_finish($product) {
        $content = strtolower($product->get_name() . ' ' . $product->get_description() . ' ' . $product->get_short_description());

        // 1. 从标题和描述中提取五金表面处理关键词
        $hardware_finish_patterns = [
            '/\bhardware\s+(finish|color|colour):\s*([a-zA-Z\s]+)/i',
            '/\b([a-zA-Z\s]+)\s+hardware\b/i',
            '/\bhardware\s+in\s+([a-zA-Z\s]+)/i',
            '/\b(black|white|almond|bronze|brass|chrome|nickel|silver|gold|copper|antique|brushed|polished|matte|satin|oil\s+rubbed)\s+hardware/i',
            '/\bhardware.*?(black|white|almond|bronze|brass|chrome|nickel|silver|gold|copper|antique|brushed|polished|matte|satin|oil\s+rubbed)/i',
            '/\b(knobs?|handles?|pulls?)\s+(in\s+)?(black|white|almond|bronze|brass|chrome|nickel|silver|gold|copper|antique|brushed|polished|matte|satin|oil\s+rubbed)/i'
        ];

        foreach ($hardware_finish_patterns as $pattern) {
            if (preg_match($pattern, $content, $matches)) {
                $finish = trim($matches[count($matches) - 1]);
                if (strlen($finish) > 0 && strlen($finish) <= 4000) {
                    return ucwords($finish);
                }
            }
        }

        // 2. 如果没有找到五金表面处理，使用产品颜色
        $main_color = $this->generate_special_attribute_value('color', $product, 1);
        if (!empty($main_color)) {
            $color_str = is_array($main_color) ? $main_color[0] : $main_color;
            if (strlen($color_str) <= 4000) {
                return ucwords($color_str);
            }
        }

        // 3. 如果都没有，返回null（不传递此字段）
        return null;
    }

    /**
     * 生成推荐房间
     * 默认使用多个选项：Living Room, Bedroom, Dining Room, Family Room, Kitchen, Bathroom, Laundry Room, Pantry, Home Office, Office, Conference Room, Cubicle
     *
     * @param WC_Product $product 产品对象
     * @return array 推荐房间数组
     */
    private function generate_recommended_rooms($product) {
        // 默认推荐房间列表
        $default_rooms = [
            'Living Room',
            'Bedroom',
            'Dining Room',
            'Family Room',
            'Kitchen',
            'Bathroom',
            'Laundry Room',
            'Pantry',
            'Home Office',
            'Office',
            'Conference Room',
            'Cubicle'
        ];

        // 可以根据产品类型或描述进行智能匹配，但目前按需求使用默认值
        return $default_rooms;
    }

    /**
     * 根据Walmart分类名称提取产品特性
     * 使用Walmart分类名称确保跨网站兼容性，从标题和描述中智能匹配特性
     *
     * @param WC_Product $product 产品对象
     * @param string $simulate_walmart_category 模拟的Walmart分类名称（用于测试）
     * @return array|null 匹配的特性数组，无匹配则返回null
     */
    private function extract_features_by_category_id($product, $simulate_walmart_category = null) {
        // 获取产品的Walmart分类名称
        $walmart_categories = $this->get_product_walmart_categories($product);

        // 模拟测试模式：添加指定的Walmart分类
        if ($simulate_walmart_category) {
            $walmart_categories[] = $simulate_walmart_category;
        }

        if (empty($walmart_categories)) {
            return null;
        }

        // Walmart分类特定的特性配置
        $category_features_map = [
            'Bed Frames' => [ // Walmart分类: Bed Frames - 床架类产品
                'Adjustable Height',
                'Wireless Remote',
                'Heavy Duty',
                'Center Supports',
                'USB Port',
                'Headboard Compatible',
                'Massaging'
            ],
            'Kitchen Serving Carts' => [ // Walmart分类: Kitchen Serving Carts - 厨房推车类产品
                'Rolling',
                'Folding',
                'Portable',
                'Removable'
            ],
            'Dining Furniture Sets' => [ // Walmart分类: Dining Furniture Sets - 餐厅家具套装类产品
                'Live Edge',
                'Storage',
                'Nailhead Trim',
                'Folding',
                'Tufted'
            ],
            'Sofas & Couches' => [ // Walmart分类: Sofas & Couches - 沙发类产品
                'Reclining',
                'USB',
                'Tufted',
                'Storage',
                'Nailhead Trim',
                'Multifunctional',
                'Massaging'
            ]
            // 后续可以添加更多Walmart分类的配置
        ];

        // 查找匹配的分类配置
        $available_features = null;
        $matched_category = null;
        foreach ($walmart_categories as $walmart_category) {
            if (isset($category_features_map[$walmart_category])) {
                $available_features = $category_features_map[$walmart_category];
                $matched_category = $walmart_category;
                break; // 找到第一个匹配的分类就停止
            }
        }

        // 如果没有找到对应的分类配置，返回null
        if (empty($available_features)) {
            return null;
        }

        // 从产品信息中智能匹配特性
        return $this->match_features_from_content($product, $available_features, $matched_category);
    }

    /**
     * 获取产品的Walmart分类名称
     * 通过分类映射表获取产品对应的Walmart分类
     *
     * @param WC_Product $product 产品对象
     * @return array Walmart分类名称数组
     */
    private function get_product_walmart_categories($product) {
        global $wpdb;

        // 获取产品的本地分类ID
        $product_categories = wp_get_post_terms($product->get_id(), 'product_cat', ['fields' => 'ids']);

        if (empty($product_categories)) {
            return [];
        }

        $walmart_categories = [];

        // 查询分类映射表，获取对应的Walmart分类
        $placeholders = implode(',', array_fill(0, count($product_categories), '%d'));
        // 🔧 修复：字段名应该是 wc_category_id，不是 local_category_id
        $query = $wpdb->prepare("
            SELECT DISTINCT walmart_category_path
            FROM {$wpdb->prefix}walmart_category_map
            WHERE wc_category_id IN ({$placeholders})
        ", $product_categories);

        $results = $wpdb->get_results($query);

        foreach ($results as $result) {
            if (!empty($result->walmart_category_path)) {
                // 提取最后一级分类名称（如 "Home > Furniture > Bedroom Furniture > Bed Frames" -> "Bed Frames"）
                $path_parts = explode(' > ', $result->walmart_category_path);
                $walmart_category = trim(end($path_parts));

                if (!empty($walmart_category)) {
                    $walmart_categories[] = $walmart_category;
                }
            }
        }

        return array_unique($walmart_categories);
    }

    /**
     * 从产品内容中匹配特性
     * 使用关键词匹配算法从产品标题和描述中提取特性
     *
     * @param WC_Product $product 产品对象
     * @param array $available_features 可用的特性选项
     * @param string|null $walmart_category Walmart分类名称（用于确定默认值）
     * @return array|null 匹配的特性数组
     */
    private function match_features_from_content($product, $available_features, $walmart_category = null) {
        $content = strtolower($product->get_name() . ' ' . $product->get_description() . ' ' . $product->get_short_description());
        $matched_features = [];

        foreach ($available_features as $feature) {
            $feature_lower = strtolower($feature);

            // 创建多种匹配模式
            $patterns = [
                // 完整匹配
                '/\b' . preg_quote($feature_lower, '/') . '\b/',
                // 分词匹配（处理空格和连字符）
                '/\b' . preg_quote(str_replace([' ', '-'], '[-\s]', $feature_lower), '/') . '\b/',
            ];

            // 特殊关键词匹配规则
            $special_matches = [
                // Bed Frames 分类特性
                'Adjustable Height' => ['adjustable', 'height', 'adjust'],
                'Wireless Remote' => ['wireless', 'remote', 'bluetooth'],
                'Heavy Duty' => ['heavy duty', 'heavy-duty', 'durable', 'sturdy'],
                'Center Supports' => ['center support', 'middle support', 'reinforced'],
                'USB Port' => ['usb', 'charging port', 'power port'],
                'Headboard Compatible' => ['headboard', 'compatible', 'attachment'],
                'Massaging' => ['massage', 'massaging', 'vibration', 'therapeutic'],

                // Kitchen Serving Carts 分类特性
                'Rolling' => ['rolling', 'wheels', 'casters', 'mobile', 'roll'],
                'Folding' => ['folding', 'foldable', 'fold', 'collapsible', 'collapse'],
                'Portable' => ['portable', 'movable', 'lightweight', 'easy to move'],
                'Removable' => ['removable', 'detachable', 'remove', 'take off', 'separate'],

                // Dining Furniture Sets 分类特性
                'Live Edge' => ['live edge', 'live-edge', 'natural edge', 'raw edge', 'wood edge'],
                'Storage' => ['storage', 'drawer', 'shelf', 'shelves', 'cabinet', 'compartment'],
                'Nailhead Trim' => ['nailhead', 'nail head', 'studded', 'decorative nails', 'metal studs'],
                'Tufted' => ['tufted', 'button tufted', 'diamond tufted', 'tufting', 'buttoned'],

                // Sofas & Couches 分类特性
                'Reclining' => ['reclining', 'recline', 'recliner', 'reclinable', 'adjustable back'],
                'USB' => ['usb', 'usb port', 'charging port', 'power port', 'usb charging'],
                'Multifunctional' => ['multifunctional', 'multi-functional', 'multi function', 'versatile', 'convertible', 'sleeper', 'sofa bed', 'pull out', 'futon']
            ];

            // 检查特殊匹配规则
            $feature_matched = false;
            if (isset($special_matches[$feature])) {
                foreach ($special_matches[$feature] as $keyword) {
                    if (strpos($content, $keyword) !== false) {
                        $matched_features[] = $feature;
                        $feature_matched = true;
                        break; // 跳出关键词循环，继续检查下一个特性
                    }
                }
            }

            // 如果特殊规则已匹配，跳过通用模式检查
            if ($feature_matched) {
                continue;
            }

            // 检查通用模式匹配
            foreach ($patterns as $pattern) {
                if (preg_match($pattern, $content)) {
                    $matched_features[] = $feature;
                    break; // 找到匹配就跳出模式循环，继续检查下一个特性
                }
            }
        }

        // 如果没有匹配到任何特性，根据分类返回默认值或null
        if (empty($matched_features)) {
            // Sofas & Couches 分类：返回默认值 Multifunctional
            if ($walmart_category === 'Sofas & Couches') {
                return ['Multifunctional'];
            }
            // 其他分类：返回null（不传递此字段）
            return null;
        }

        // 去重并返回
        return array_unique($matched_features);
    }

    /**
     * 模拟测试方法：测试Bed Frames分类的特性提取
     * 用于在本地测试Bed Frames分类的功能，即使该分类映射不存在于本地数据库
     *
     * @param WC_Product $product 产品对象
     * @return array|null 匹配的特性数组
     */
    public function test_extract_features_bed_frames($product) {
        return $this->extract_features_by_category_id($product, 'Bed Frames');
    }

    /**
     * 通用模拟测试方法：测试指定Walmart分类的特性提取
     *
     * @param WC_Product $product 产品对象
     * @param string $walmart_category Walmart分类名称
     * @return array|null 匹配的特性数组
     */
    public function test_extract_features_walmart_category($product, $walmart_category) {
        return $this->extract_features_by_category_id($product, $walmart_category);
    }

    /**
     * 提取框架表面处理
     * 从产品描述提取或使用产品颜色
     *
     * @param WC_Product $product 产品对象
     * @return string|null 框架表面处理
     */
    private function extract_frame_finish($product) {
        $content = strtolower($product->get_name() . ' ' . $product->get_description() . ' ' . $product->get_short_description());

        // 常见的框架表面处理关键词
        $finishes = [
            'Stainless Steel' => ['stainless steel', 'stainless-steel', 'stainless'],
            'Oil-Rubbed Bronze' => ['oil-rubbed bronze', 'oil rubbed bronze', 'bronze'],
            'Chrome' => ['chrome', 'chromed'],
            'Antique Brass' => ['antique brass', 'brass'],
            'Polished' => ['polished', 'polish'],
            'Brushed' => ['brushed'],
            'Matte' => ['matte', 'mat'],
            'Glossy' => ['glossy', 'gloss'],
            'Powder Coated' => ['powder coated', 'powder-coated']
        ];

        foreach ($finishes as $finish => $keywords) {
            foreach ($keywords as $keyword) {
                if (strpos($content, $keyword) !== false) {
                    return $finish;
                }
            }
        }

        // 如果没有找到，使用产品颜色
        $color = $product->get_attribute('Color');
        if (!empty($color)) {
            return $color;
        }

        // 使用WooCommerce颜色属性
        $colors = $product->get_attribute('pa_color');
        if (!empty($colors)) {
            return $colors;
        }

        return null;
    }

    /**
     * 提取把手宽度
     * 从产品描述提取或默认1 in
     *
     * @param WC_Product $product 产品对象
     * @return array 测量对象 {measure, unit}
     */
    private function extract_handle_width($product) {
        $content = $product->get_name() . ' ' . $product->get_description() . ' ' . $product->get_short_description();

        // 匹配把手宽度模式
        $patterns = [
            '/handle\s+width[:\s]+([0-9.\/\-]+)\s*(inch|in|"|cm)/i',
            '/handle[:\s]+([0-9.\/\-]+)\s*(inch|in|"|cm)\s+wide/i',
            '/([0-9.\/\-]+)\s*(inch|in|"|cm)\s+handle/i'
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $content, $matches)) {
                $measure = $matches[1];
                $unit = isset($matches[2]) ? $matches[2] : 'in';

                // 标准化单位
                if (in_array($unit, ['"', 'inch', 'in'])) {
                    $unit = 'in';
                } elseif ($unit === 'cm') {
                    $unit = 'cm';
                }

                return [
                    'measure' => $measure,
                    'unit' => $unit
                ];
            }
        }

        // 默认值
        return [
            'measure' => '1',
            'unit' => 'in'
        ];
    }

    /**
     * 提取把手材质
     * 从产品描述提取
     *
     * @param WC_Product $product 产品对象
     * @return array|null 把手材质数组
     */
    private function extract_handle_material($product) {
        $content = strtolower($product->get_name() . ' ' . $product->get_description() . ' ' . $product->get_short_description());

        // 常见的把手材质
        $materials = [
            'Plastic' => ['plastic', 'pvc'],
            'Foam' => ['foam', 'cushioned'],
            'Faux Leather' => ['faux leather', 'synthetic leather', 'pu leather'],
            'Wood' => ['wood', 'wooden'],
            'Metal' => ['metal', 'steel', 'aluminum', 'iron'],
            'Acrylic' => ['acrylic'],
            'Rubber' => ['rubber'],
            'Silicone' => ['silicone']
        ];

        $found_materials = [];

        foreach ($materials as $material => $keywords) {
            foreach ($keywords as $keyword) {
                if (strpos($content, $keyword . ' handle') !== false ||
                    strpos($content, 'handle ' . $keyword) !== false) {
                    $found_materials[] = $material;
                    break;
                }
            }
        }

        // 如果没有找到，返回null
        if (empty($found_materials)) {
            return null;
        }

        return array_unique($found_materials);
    }

    /**
     * 提取厨房推车类型
     * 从产品描述提取或默认Serving Cart
     *
     * @param WC_Product $product 产品对象
     * @return string 推车类型
     */
    private function extract_kitchen_cart_type($product) {
        $content = strtolower($product->get_name() . ' ' . $product->get_description() . ' ' . $product->get_short_description());

        // 检查是否为Bar Cart
        $bar_keywords = ['bar cart', 'bar-cart', 'wine cart', 'beverage cart', 'drink cart', 'cocktail cart'];
        foreach ($bar_keywords as $keyword) {
            if (strpos($content, $keyword) !== false) {
                return 'Bar Cart';
            }
        }

        // 默认为Serving Cart
        return 'Serving Cart';
    }

    /**
     * 提取挂钩数量
     * 从产品描述提取或默认0
     *
     * @param WC_Product $product 产品对象
     * @return int 挂钩数量
     */
    private function extract_number_of_hooks($product) {
        $content = $product->get_name() . ' ' . $product->get_description() . ' ' . $product->get_short_description();

        // 匹配挂钩数量模式
        $patterns = [
            '/(\d+)\s*hooks?/i',
            '/(\d+)-hook/i',
            '/with\s+(\d+)\s+hooks?/i',
            '/hooks?[:\s]+(\d+)/i'
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $content, $matches)) {
                $number = (int)$matches[1];
                // 验证合理范围
                if ($number >= 0 && $number <= 100) {
                    return $number;
                }
            }
        }

        // 默认值
        return 0;
    }

    /**
     * 提取轮子数量
     * 从产品描述提取或默认0
     *
     * @param WC_Product $product 产品对象
     * @return int 轮子数量
     */
    private function extract_number_of_wheels($product) {
        $content = $product->get_name() . ' ' . $product->get_description() . ' ' . $product->get_short_description();

        // 匹配轮子数量模式
        $patterns = [
            '/(\d+)\s*wheels?/i',
            '/(\d+)-wheel/i',
            '/with\s+(\d+)\s+wheels?/i',
            '/wheels?[:\s]+(\d+)/i',
            '/(\d+)\s*casters?/i'
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $content, $matches)) {
                $number = (int)$matches[1];
                // 验证合理范围
                if ($number >= 0 && $number <= 20) {
                    return $number;
                }
            }
        }

        // 默认值
        return 0;
    }

    /**
     * 提取顶部材质
     * 从产品描述提取
     *
     * @param WC_Product $product 产品对象
     * @return string|null 顶部材质
     */
    private function extract_top_material($product) {
        $content = strtolower($product->get_name() . ' ' . $product->get_description() . ' ' . $product->get_short_description());

        // 常见的顶部材质
        $materials = [
            'Wood' => ['wood top', 'wooden top', 'wood surface'],
            'Glass' => ['glass top', 'tempered glass', 'glass surface'],
            'Mirror' => ['mirror top', 'mirrored top', 'mirror surface'],
            'Marble' => ['marble top', 'marble surface'],
            'Granite' => ['granite top', 'granite surface'],
            'Metal' => ['metal top', 'steel top'],
            'MDF' => ['mdf top', 'mdf surface'],
            'Laminate' => ['laminate top', 'laminated surface']
        ];

        foreach ($materials as $material => $keywords) {
            foreach ($keywords as $keyword) {
                if (strpos($content, $keyword) !== false) {
                    return $material;
                }
            }
        }

        // 如果没有找到，返回null
        return null;
    }

    /**
     * 提取餐厅家具套装类型
     * 从产品描述提取或默认Dining Table with Chair
     *
     * @param WC_Product $product 产品对象
     * @return string 餐厅家具套装类型
     */
    private function extract_dining_furniture_set_type($product) {
        $content = strtolower($product->get_name() . ' ' . $product->get_description() . ' ' . $product->get_short_description());

        // 按优先级检查类型（从最具体到最一般）
        $types = [
            'Dining Table with Bench and Chair' => ['bench and chair', 'chairs and bench', 'bench & chair'],
            'Dining Nook' => ['dining nook', 'breakfast nook', 'corner nook', 'nook'],
            'Pub Table Set' => ['pub table', 'bar table', 'counter height table', 'high table'],
            'Dining Table with Bench' => ['with bench', 'table bench', 'bench set']
        ];

        foreach ($types as $type => $keywords) {
            foreach ($keywords as $keyword) {
                if (strpos($content, $keyword) !== false) {
                    return $type;
                }
            }
        }

        // 默认为Dining Table with Chair
        return 'Dining Table with Chair';
    }

    /**
     * 提取椅子整体深度
     * 从产品描述提取
     *
     * @param WC_Product $product 产品对象
     * @return array|null 测量对象 {measure, unit}
     */
    private function extract_overall_chair_depth($product) {
        $content = $product->get_name() . ' ' . $product->get_description() . ' ' . $product->get_short_description();

        // 特殊格式：匹配 "Chair: 18 in * 20 in * 38 in" 或 "Chair: 18 * 20 * 38 in" 或 "Chair: 18x20x38 in" 格式
        // 支持 * 或 x 或 X 作为分隔符，支持带空格或不带空格
        if (preg_match('/chairs?[:\s]+[0-9.]+\s*(?:in|inch|inches|"|cm)?\s*[*xX×]\s*([0-9.]+)\s*(?:in|inch|inches|"|cm)?\s*[*xX×]?\s*(?:[0-9.]+\s*)?\s*(in|inch|inches|"|cm)/i', $content, $matches)) {
            $measure = $matches[1]; // 第二个数字是深度
            $unit = $matches[2]; // 最后的单位

            // 标准化单位
            if (in_array(strtolower($unit), ['"', 'inch', 'inches', 'in'])) {
                $unit = 'in';
            } elseif (strtolower($unit) === 'cm') {
                $unit = 'cm';
            }

            return [
                'measure' => (float)$measure,  // 转换为数值类型
                'unit' => $unit
            ];
        }

        // 特殊模式：匹配 "Chair 18 inches wide, 20 inches deep" 或 "Chairs 18 in wide, 20 in deep" 这样的描述
        if (preg_match('/(?:and\s+)?chairs?\s+[0-9.\/\-]+\s*(?:inch|inches|in)\s+wide[,\s]+([0-9.\/\-]+)\s*(inch|inches|in)\s+deep/i', $content, $matches)) {
            $measure = $matches[1];
            $unit = isset($matches[2]) ? $matches[2] : 'in';

            // 标准化单位
            if (in_array(strtolower($unit), ['"', 'inch', 'inches', 'in'])) {
                $unit = 'in';
            } elseif (strtolower($unit) === 'cm') {
                $unit = 'cm';
            }

            return [
                'measure' => (float)$measure,  // 转换为数值类型
                'unit' => $unit
            ];
        }

        // 匹配椅子深度模式（必须包含chair/seat关键词）
        $patterns = [
            '/chair\s+depth[:\s]+([0-9.\/\-]+)\s*(inch|inches|in|"|cm)/i',
            '/chair\s+([0-9.\/\-]+)\s*(inch|inches|in|"|cm)\s+deep/i',
            '/chairs?\s+([0-9.\/\-]+)\s*(inch|inches|in)\s+deep/i',
            '/seat\s+depth[:\s]+([0-9.\/\-]+)\s*(inch|inches|in|"|cm)/i'
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $content, $matches)) {
                $measure = $matches[1];
                $unit = isset($matches[2]) ? $matches[2] : 'in';

                // 标准化单位
                if (in_array(strtolower($unit), ['"', 'inch', 'inches', 'in'])) {
                    $unit = 'in';
                } elseif (strtolower($unit) === 'cm') {
                    $unit = 'cm';
                }

                return [
                    'measure' => (float)$measure,  // 转换为数值类型
                    'unit' => $unit
                ];
            }
        }

        // 如果没有找到，返回null
        return null;
    }

    /**
     * 提取椅子整体高度
     * 从产品描述提取
     *
     * @param WC_Product $product 产品对象
     * @return array|null 测量对象 {measure, unit}
     */
    private function extract_overall_chair_height($product) {
        $content = $product->get_name() . ' ' . $product->get_description() . ' ' . $product->get_short_description();

        // 特殊格式：匹配 "Chair: 18 in * 20 in * 38 in" 或 "Chair: 18 * 20 * 38 in" 或 "Chair: 18x20x38 in" 格式
        // 支持 * 或 x 或 X 作为分隔符，支持带空格或不带空格
        // 注意：这里需要确保有三个数字（宽*深*高），第三个数字才是高度
        if (preg_match('/chairs?[:\s]+[0-9.]+\s*(?:in|inch|inches|"|cm)?\s*[*xX×]\s*[0-9.]+\s*(?:in|inch|inches|"|cm)?\s*[*xX×]\s*([0-9.]+)\s*(in|inch|inches|"|cm)/i', $content, $matches)) {
            $measure = $matches[1]; // 第三个数字是高度
            $unit = $matches[2]; // 最后的单位

            // 标准化单位
            if (in_array(strtolower($unit), ['"', 'inch', 'inches', 'in'])) {
                $unit = 'in';
            } elseif (strtolower($unit) === 'cm') {
                $unit = 'cm';
            }

            return [
                'measure' => (float)$measure,  // 转换为数值类型
                'unit' => $unit
            ];
        }

        // 特殊模式：匹配 "Chair 18 inches wide, 20 inches deep, 38 inches high" 或 "chairs 18 inches wide and 38 inches high" 这样的描述
        if (preg_match('/(?:and\s+)?chairs?\s+[0-9.\/\-]+\s*(?:inch|inches|in)\s+(?:wide|deep)(?:[,\s]+and\s+|[,\s]+)(?:[0-9.\/\-]+\s*(?:inch|inches|in)\s+(?:wide|deep)(?:[,\s]+and\s+|[,\s]+))?([0-9.\/\-]+)\s*(inch|inches|in)\s+high/i', $content, $matches)) {
            $measure = $matches[1];
            $unit = isset($matches[2]) ? $matches[2] : 'in';

            // 标准化单位
            if (in_array(strtolower($unit), ['"', 'inch', 'inches', 'in'])) {
                $unit = 'in';
            } elseif (strtolower($unit) === 'cm') {
                $unit = 'cm';
            }

            return [
                'measure' => (float)$measure,  // 转换为数值类型
                'unit' => $unit
            ];
        }

        // 匹配椅子高度模式（必须包含chair/seat关键词）
        $patterns = [
            '/chair\s+height[:\s]+([0-9.\/\-]+)\s*(inch|inches|in|"|cm)/i',
            '/chair\s+([0-9.\/\-]+)\s*(inch|inches|in|"|cm)\s+high/i',
            '/chairs?\s+([0-9.\/\-]+)\s*(inch|inches|in)\s+high/i',
            '/seat\s+height[:\s]+([0-9.\/\-]+)\s*(inch|inches|in|"|cm)/i'
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $content, $matches)) {
                $measure = $matches[1];
                $unit = isset($matches[2]) ? $matches[2] : 'in';

                // 标准化单位
                if (in_array(strtolower($unit), ['"', 'inch', 'inches', 'in'])) {
                    $unit = 'in';
                } elseif (strtolower($unit) === 'cm') {
                    $unit = 'cm';
                }

                return [
                    'measure' => (float)$measure,  // 转换为数值类型
                    'unit' => $unit
                ];
            }
        }

        // 如果没有找到，返回null
        return null;
    }

    /**
     * 提取椅子整体宽度
     * 从产品描述提取
     *
     * @param WC_Product $product 产品对象
     * @return array|null 测量对象 {measure, unit}
     */
    private function extract_overall_chair_width($product) {
        $content = $product->get_name() . ' ' . $product->get_description() . ' ' . $product->get_short_description();

        // 特殊格式：匹配 "Chair: 18 in * 20 in * 38 in" 或 "Chair: 18 * 20 * 38 in" 或 "Chair: 18x20x38 in" 格式
        // 支持 * 或 x 或 X 作为分隔符，支持带空格或不带空格
        if (preg_match('/chairs?[:\s]+([0-9.]+)\s*(?:in|inch|inches|"|cm)?\s*[*xX×]\s*([0-9.]+)\s*(?:in|inch|inches|"|cm)?\s*[*xX×]?\s*(?:[0-9.]+\s*)?\s*(in|inch|inches|"|cm)/i', $content, $matches)) {
            $measure = $matches[1]; // 第一个数字是宽度
            $unit = $matches[3]; // 最后的单位

            // 标准化单位
            if (in_array(strtolower($unit), ['"', 'inch', 'inches', 'in'])) {
                $unit = 'in';
            } elseif (strtolower($unit) === 'cm') {
                $unit = 'cm';
            }

            return [
                'measure' => (float)$measure,  // 转换为数值类型
                'unit' => $unit
            ];
        }

        // 匹配椅子宽度模式（必须包含chair/seat关键词）
        $patterns = [
            '/chair\s+width[:\s]+([0-9.\/\-]+)\s*(inch|inches|in|"|cm)/i',
            '/chair\s+([0-9.\/\-]+)\s*(inch|inches|in|"|cm)\s+wide/i',
            '/chairs?\s+([0-9.\/\-]+)\s*(inch|inches|in)\s+wide/i',
            '/seat\s+width[:\s]+([0-9.\/\-]+)\s*(inch|inches|in|"|cm)/i'
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $content, $matches)) {
                $measure = $matches[1];
                $unit = isset($matches[2]) ? $matches[2] : 'in';

                // 标准化单位
                if (in_array(strtolower($unit), ['"', 'inch', 'inches', 'in'])) {
                    $unit = 'in';
                } elseif (strtolower($unit) === 'cm') {
                    $unit = 'cm';
                }

                return [
                    'measure' => (float)$measure,  // 转换为数值类型
                    'unit' => $unit
                ];
            }
        }

        // 如果没有找到，返回null
        return null;
    }

    /**
     * 提取座椅靠背高度描述
     * 从产品描述提取
     *
     * @param WC_Product $product 产品对象
     * @return string|null 座椅靠背高度描述
     */
    private function extract_seat_back_height_descriptor($product) {
        $content = strtolower($product->get_name() . ' ' . $product->get_description() . ' ' . $product->get_short_description());

        // 检查靠背高度描述
        $descriptors = [
            'High Back' => ['high back', 'high-back', 'tall back'],
            'Mid Back' => ['mid back', 'mid-back', 'medium back', 'middle back'],
            'Low Back' => ['low back', 'low-back', 'short back']
        ];

        foreach ($descriptors as $descriptor => $keywords) {
            foreach ($keywords as $keyword) {
                if (strpos($content, $keyword) !== false) {
                    return $descriptor;
                }
            }
        }

        // 如果没有找到，返回null
        return null;
    }

    /**
     * 提取带扩展叶板的座位容量
     * 从产品描述提取或默认1
     *
     * @param WC_Product $product 产品对象
     * @return int 座位容量
     */
    private function extract_seating_capacity_with_leaf($product) {
        $content = $product->get_name() . ' ' . $product->get_description() . ' ' . $product->get_short_description();

        // 匹配座位容量模式
        $patterns = [
            '/seats?\s+(\d+)\s+with\s+leaf/i',
            '/with\s+leaf[:\s]+seats?\s+(\d+)/i',
            '/(\d+)\s+seating\s+with\s+leaf/i',
            '/leaf\s+extends?\s+to\s+(\d+)\s+seats?/i',
            '/accommodates?\s+(\d+)\s+with\s+leaf/i'
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $content, $matches)) {
                $capacity = (int)$matches[1];
                // 验证合理范围
                if ($capacity >= 1 && $capacity <= 50) {
                    return $capacity;
                }
            }
        }

        // 默认值
        return 1;
    }

    /**
     * 提取桌子长度
     * 从产品描述提取或默认1 in
     *
     * @param WC_Product $product 产品对象
     * @return array 测量对象 {measure, unit}
     */
    private function extract_table_length($product) {
        $content = $product->get_name() . ' ' . $product->get_description() . ' ' . $product->get_short_description();

        // 特殊格式：匹配 "Table: 72 in * 36 in * 30 in" 或 "Table: 72 * 36 * 30 in" 或 "Table: 72x36x30 in" 格式
        // 支持 * 或 x 或 X 作为分隔符，支持带空格或不带空格
        if (preg_match('/table[:\s]+([0-9.]+)\s*(?:in|inch|inches|"|ft|feet|foot|cm)?\s*[*xX×]\s*([0-9.]+)\s*(?:in|inch|inches|"|ft|feet|foot|cm)?\s*[*xX×]?\s*(?:[0-9.]+\s*)?\s*(in|inch|inches|"|ft|feet|foot|cm)/i', $content, $matches)) {
            $measure = $matches[1]; // 第一个数字是长度
            $unit = $matches[3]; // 最后的单位

            // 标准化单位
            if (in_array(strtolower($unit), ['ft', 'feet', 'foot'])) {
                $unit = 'ft';
            } elseif (in_array(strtolower($unit), ['"', 'inch', 'inches', 'in'])) {
                $unit = 'in';
            } elseif (strtolower($unit) === 'cm') {
                $unit = 'cm';
            }

            return [
                'measure' => (float)$measure,  // 转换为数值类型
                'unit' => $unit
            ];
        }

        // 匹配 "Table: 72x36 in" 格式（只有长和宽，没有高）
        if (preg_match('/table[:\s]+([0-9.]+)\s*[*xX×]\s*[0-9.]+\s*(in|inch|inches|"|ft|feet|foot|cm)/i', $content, $matches)) {
            $measure = $matches[1];
            $unit = $matches[2];

            // 标准化单位
            if (in_array(strtolower($unit), ['ft', 'feet', 'foot'])) {
                $unit = 'ft';
            } elseif (in_array(strtolower($unit), ['"', 'inch', 'inches', 'in'])) {
                $unit = 'in';
            } elseif (strtolower($unit) === 'cm') {
                $unit = 'cm';
            }

            return [
                'measure' => (float)$measure,  // 转换为数值类型
                'unit' => $unit
            ];
        }

        // 匹配桌子长度模式（必须明确包含table关键词，避免与chair混淆）
        $patterns = [
            '/table\s+length[:\s]+([0-9.\/\-]+)\s*(ft|feet|foot|cm|inch|inches|in|")?/i',
            '/([0-9.\/\-]+)\s*(ft|feet|foot|cm|inch|inches|in|")\s+table\s+length/i',
            '/table[:\s]+([0-9.\/\-]+)\s*(ft|feet|foot|cm|inch|inches|in|")\s+(long|length)/i',
            '/table\s+([0-9.\/\-]+)\s*(inches?|in|ft|feet|foot|cm)\s+long/i',
            '/([0-9.\/\-]+)\s*(ft|feet|foot|cm)\s+long\s+table/i'
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $content, $matches)) {
                $measure = $matches[1];
                $unit = isset($matches[2]) ? $matches[2] : 'in';

                // 标准化单位
                if (in_array(strtolower($unit), ['ft', 'feet', 'foot'])) {
                    $unit = 'ft';
                } elseif (in_array(strtolower($unit), ['"', 'inch', 'inches', 'in'])) {
                    $unit = 'in';
                } elseif (strtolower($unit) === 'cm') {
                    $unit = 'cm';
                }

                return [
                    'measure' => (float)$measure,  // 转换为数值类型
                    'unit' => $unit
                ];
            }
        }

        // 默认值
        return [
            'measure' => 1.0,  // 转换为数值类型
            'unit' => 'in'
        ];
    }

    /**
     * 提取桌子宽度
     * 从产品描述提取或默认1 in
     *
     * @param WC_Product $product 产品对象
     * @return array 测量对象 {measure, unit}
     */
    private function extract_table_width($product) {
        $content = $product->get_name() . ' ' . $product->get_description() . ' ' . $product->get_short_description();

        // 特殊格式：匹配 "Table: 72 in * 36 in * 30 in" 或 "Table: 72 * 36 * 30 in" 或 "Table: 72x36x30 in" 格式
        // 支持 * 或 x 或 X 作为分隔符，支持带空格或不带空格
        if (preg_match('/table[:\s]+[0-9.]+\s*(?:in|inch|inches|"|ft|feet|foot|cm)?\s*[*xX×]\s*([0-9.]+)\s*(?:in|inch|inches|"|ft|feet|foot|cm)?\s*[*xX×]?\s*(?:[0-9.]+\s*)?\s*(in|inch|inches|"|ft|feet|foot|cm)/i', $content, $matches)) {
            $measure = $matches[1]; // 第二个数字是宽度
            $unit = $matches[2]; // 最后的单位

            // 标准化单位
            if (in_array(strtolower($unit), ['ft', 'feet', 'foot'])) {
                $unit = 'ft';
            } elseif (in_array(strtolower($unit), ['"', 'inch', 'inches', 'in'])) {
                $unit = 'in';
            } elseif (strtolower($unit) === 'cm') {
                $unit = 'cm';
            }

            return [
                'measure' => (float)$measure,  // 转换为数值类型
                'unit' => $unit
            ];
        }

        // 匹配桌子宽度模式（必须明确包含table关键词，避免与chair混淆）
        $patterns = [
            '/table\s+width[:\s]+([0-9.\/\-]+)\s*(ft|feet|foot|cm|inch|inches|in|")?/i',
            '/([0-9.\/\-]+)\s*(ft|feet|foot|cm|inch|inches|in|")\s+table\s+width/i',
            '/table[:\s]+([0-9.\/\-]+)\s*(ft|feet|foot|cm|inch|inches|in|")\s+wide/i',
            '/table\s+([0-9.\/\-]+)\s*(inches?|in|ft|feet|foot|cm)\s+wide/i',
            '/([0-9.\/\-]+)\s*(ft|feet|foot|cm|inch|inches|in)\s+wide\s+table/i',
            '/table\s+[0-9.\/\-]+\s*(?:ft|feet|foot|cm|inch|inches|in)\s+long[,\s]+([0-9.\/\-]+)\s*(ft|feet|foot|cm|inch|inches|in)\s+wide/i'
        ];

        // 特殊模式：匹配 "table 70 inches long and 36 inches wide" 这样的描述
        if (preg_match('/(?:dining\s+)?table\s+[0-9.\/\-]+\s*(?:inch|inches|in|ft|feet|cm)\s+long\s+and\s+([0-9.\/\-]+)\s*(inch|inches|in|ft|feet|cm)\s+wide/i', $content, $matches)) {
            $measure = $matches[1];
            $unit = isset($matches[2]) ? $matches[2] : 'in';

            // 标准化单位
            if (in_array(strtolower($unit), ['ft', 'feet', 'foot'])) {
                $unit = 'ft';
            } elseif (in_array(strtolower($unit), ['"', 'inch', 'inches', 'in'])) {
                $unit = 'in';
            } elseif (strtolower($unit) === 'cm') {
                $unit = 'cm';
            }

            return [
                'measure' => (float)$measure,  // 转换为数值类型
                'unit' => $unit
            ];
        }

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $content, $matches)) {
                $measure = $matches[1];
                $unit = isset($matches[2]) ? $matches[2] : 'in';

                // 标准化单位
                if (in_array(strtolower($unit), ['ft', 'feet', 'foot'])) {
                    $unit = 'ft';
                } elseif (in_array(strtolower($unit), ['"', 'inch', 'inches', 'in'])) {
                    $unit = 'in';
                } elseif (strtolower($unit) === 'cm') {
                    $unit = 'cm';
                }

                return [
                    'measure' => (float)$measure,  // 转换为数值类型
                    'unit' => $unit
                ];
            }
        }

        // 默认值
        return [
            'measure' => 1.0,  // 转换为数值类型
            'unit' => 'in'
        ];
    }

    /**
     * 提取尺寸描述符 - Size Descriptor
     * 从产品标题和描述中智能匹配尺寸相关关键词
     *
     * @param WC_Product $product 产品对象
     * @return string|null 匹配的尺寸描述符，无匹配返回默认值 "Regular"
     */
    private function extract_size_descriptor($product) {
        // 获取产品内容
        $content = strtolower($product->get_name() . ' ' . $product->get_description() . ' ' . $product->get_short_description());

        // 尺寸描述符枚举值及其关键词映射
        $size_keywords = [
            'Compact' => ['compact', 'space-saving', 'space saving'],
            'Huge' => ['huge', 'massive', 'enormous'],
            'Extra Thick' => ['extra thick', 'extra-thick', 'very thick'],
            'Nano' => ['nano', 'ultra small'],
            'Travel' => ['travel-size', 'travel size', 'travel'],
            'Mid' => ['mid-size', 'mid size'],
            'Small' => ['small'],
            'Smallest' => ['smallest', 'tiniest'],
            'Largest' => ['largest', 'biggest'],
            'Giant' => ['giant', 'gigantic'],
            'Oversized' => ['oversized', 'over-sized'],
            'Extra Small' => ['extra small', 'extra-small', 'xs'],
            'Full' => ['full size', 'full-size'],
            'Extra Large' => ['extra large', 'extra-large'],
            'Big' => ['big'],
            'Pocket' => ['pocket-size', 'pocket size', 'pocket'],
            'Ultra Thin' => ['ultra thin', 'ultra-thin', 'super thin'],
            'Baby' => ['baby', 'infant', 'toddler'],
            'Very Small' => ['very small'],
            'XXL' => ['xxl', 'extra extra large'],
            'Wide' => ['wide', 'broad'],
            'Plus Size' => ['plus size', 'plus-size'],
            'Short' => ['short'],
            'Large' => ['large'],
            'Micro' => ['micro', 'microscopic'],
            'Medium' => ['medium'],
            'Grande' => ['grande'],
            'Jumbo' => ['jumbo', 'super large'],
            'Tall' => ['tall'],
            'Narrow' => ['narrow'],
            'Tiny' => ['tiny'],
            'Mini' => ['mini', 'miniature'],
            'Slim' => ['slim', 'slender'],
            'Extra Wide' => ['extra wide', 'extra-wide'],
            'Long' => ['long', 'extended'],
            'Little' => ['little'],
            'Thick' => ['thick', 'chunky'],
            'Extra Long' => ['extra long', 'extra-long'],
            'Thin' => ['thin']
        ];

        // 按优先级匹配（更具体的描述符优先）
        $priority_order = [
            'Extra Thick', 'Extra Small', 'Extra Large', 'Extra Wide', 'Extra Long',
            'Ultra Thin', 'Very Small', 'Plus Size', 'Oversized',
            'XXL', 'Jumbo', 'Giant', 'Huge', 'Largest', 'Smallest',
            'Nano', 'Micro', 'Tiny', 'Mini', 'Pocket', 'Baby',
            'Travel', 'Compact', 'Narrow', 'Wide', 'Slim', 'Thick', 'Thin',
            'Tall', 'Short', 'Long', 'Full',
            'Small', 'Medium', 'Large', 'Big', 'Little',
            'Mid', 'Grande'
        ];

        // 遍历优先级列表进行匹配
        foreach ($priority_order as $size) {
            if (isset($size_keywords[$size])) {
                foreach ($size_keywords[$size] as $keyword) {
                    if (strpos($content, $keyword) !== false) {
                        return $size;
                    }
                }
            }
        }

        // 无匹配时返回默认值
        return 'Regular';
    }

    /**
     * 提取沙发设计风格 - Sofa & Loveseat Design
     * 从产品标题和描述中智能匹配设计风格关键词
     *
     * @param WC_Product $product 产品对象
     * @return array 匹配的设计风格数组，无匹配返回默认值 ["Mid-Century Modern"]
     */
    private function extract_sofa_loveseat_design($product) {
        // 获取产品内容
        $content = strtolower($product->get_name() . ' ' . $product->get_description() . ' ' . $product->get_short_description());

        // 设计风格枚举值及其关键词映射
        $design_keywords = [
            'Recamier' => ['recamier', 'récamier', 'recamiere'],
            'Cabriole' => ['cabriole', 'cabriole leg', 'cabriole legs'],
            'Club' => ['club', 'club chair', 'club style'],
            'Tuxedo' => ['tuxedo', 'tuxedo style', 'tuxedo arm'],
            'Mid-Century Modern' => ['mid-century', 'mid century', 'midcentury', 'mcm', 'retro', 'vintage modern'],
            'Camelback' => ['camelback', 'camel back', 'camel-back'],
            'Lawson' => ['lawson', 'lawson style'],
            'Divan' => ['divan', 'daybed']
        ];

        $matched_designs = [];

        // 遍历所有设计风格进行匹配
        foreach ($design_keywords as $design => $keywords) {
            foreach ($keywords as $keyword) {
                if (strpos($content, $keyword) !== false) {
                    $matched_designs[] = $design;
                    break; // 找到匹配就跳到下一个设计风格
                }
            }
        }

        // 去重
        $matched_designs = array_unique($matched_designs);

        // 如果有匹配，返回匹配的设计风格
        if (!empty($matched_designs)) {
            return $matched_designs;
        }

        // 无匹配时返回默认值
        return ['Mid-Century Modern'];
    }

    /**
     * 提取沙发床尺寸 - Sofa Bed Size
     * 从产品标题和描述中智能匹配床尺寸关键词
     *
     * @param WC_Product $product 产品对象
     * @return string|null 匹配的床尺寸，无匹配返回 null
     */
    private function extract_sofa_bed_size($product) {
        // 获取产品内容
        $content = strtolower($product->get_name() . ' ' . $product->get_description() . ' ' . $product->get_short_description());

        // 床尺寸枚举值及其关键词映射
        $size_keywords = [
            'King' => ['king', 'king size', 'king-size', 'california king'],
            'Queen' => ['queen', 'queen size', 'queen-size'],
            'Full' => ['full', 'full size', 'full-size', 'double', 'double bed'],
            'Twin' => ['twin', 'twin size', 'twin-size', 'single', 'single bed']
        ];

        // 按优先级匹配（从大到小）
        $priority_order = ['King', 'Queen', 'Full', 'Twin'];

        foreach ($priority_order as $size) {
            if (isset($size_keywords[$size])) {
                foreach ($size_keywords[$size] as $keyword) {
                    // 使用词边界匹配，避免误匹配
                    if (preg_match('/\b' . preg_quote($keyword, '/') . '\b/', $content)) {
                        return $size;
                    }
                }
            }
        }

        // 无匹配时返回 null（不传递此字段）
        return null;
    }

    /**
     * 清理图片URL以符合Walmart要求
     * @param string $url 原始图片URL
     * @return string 清理后的URL
     */
    private function clean_image_url_for_walmart($url) {
        if (empty($url)) {
            return $url;
        }

        // 解析URL
        $parsed_url = parse_url($url);
        if (!$parsed_url) {
            return $url;
        }

        // 重建URL，移除查询参数
        $clean_url = $parsed_url['scheme'] . '://' . $parsed_url['host'];

        if (isset($parsed_url['port'])) {
            $clean_url .= ':' . $parsed_url['port'];
        }

        if (isset($parsed_url['path'])) {
            $clean_url .= $parsed_url['path'];
        }

        // 确保URL以图片扩展名结尾
        if (!preg_match('/\.(jpg|jpeg|png|gif|webp)$/i', $clean_url)) {
            // 如果路径中包含图片扩展名，提取它
            if (preg_match('/\.(jpg|jpeg|png|gif|webp)/i', $clean_url, $matches)) {
                // URL中已经包含扩展名，但可能后面还有其他内容
                $extension_pos = strpos($clean_url, $matches[0]);
                $clean_url = substr($clean_url, 0, $extension_pos + strlen($matches[0]));
            } else {
                // 如果完全没有扩展名，根据Content-Type添加
                $clean_url .= '.jpg'; // 默认添加.jpg
            }
        }

        // 记录URL清理日志
        if ($url !== $clean_url) {
            woo_walmart_sync_log('image_url_cleaned', '信息', [
                'original_url' => $url,
                'cleaned_url' => $clean_url,
                'reason' => 'Walmart API compatibility'
            ], 'Image URL cleaned for Walmart API');
        }

        return $clean_url;
    }

    // ========================================
    // 加拿大市场多语言字段转换函数
    // ========================================

    /**
     * 市场感知的字段值转换 - 自动适配加拿大市场多语言格式
     *
     * @param mixed $value 原始字段值
     * @param string $field_name 字段名称
     * @param string $market_code 市场代码 (US, CA, MX, CL)
     * @param string $category_name 分类名称
     * @return mixed 转换后的值
     */
    private function convert_value_for_market($value, $field_name, $market_code, $category_name) {
        // 美国市场保持不变
        if ($market_code !== 'CA') {
            return $value;
        }

        // 空值直接返回
        if (is_null($value) || $value === '') {
            return $value;
        }

        // 检查是否已经是多语言格式（避免重复转换）
        if ($this->is_already_multilingual($value)) {
            return $value;
        }

        // 🔧 硬编码的多语言字段列表（基于官方CA模板）
        // 对象格式字段: {"en": "..."}
        $multilingual_object_fields = [
            'shortDescription',
            'longDescription',
            'productName',
            'brand',
            'warrantyText',
            'warrantyUrl',
            'additionalProductAttributes'
        ];

        // 数组格式字段: [{"en": "..."}, {"en": "..."}]
        $multilingual_array_fields = [
            'keyFeatures'
        ];

        // 对象格式转换
        if (in_array($field_name, $multilingual_object_fields)) {
            if (is_string($value)) {
                $converted = ['en' => $value];

                woo_walmart_sync_log('CA市场转换-对象', '调试', [
                    'field' => $field_name,
                    'original_length' => strlen($value),
                    'converted' => '{"en": "..."}'
                ], "字段已转换为多语言对象格式");

                return $converted;
            }
        }

        // 数组格式转换
        if (in_array($field_name, $multilingual_array_fields)) {
            if (is_array($value)) {
                $converted = [];
                foreach ($value as $item) {
                    if (is_string($item)) {
                        $converted[] = ['en' => $item];
                    } elseif (is_array($item) && !isset($item['en'])) {
                        // 如果是数组但不是多语言格式，转换第一个值
                        $converted[] = ['en' => json_encode($item)];
                    } else {
                        $converted[] = $item;
                    }
                }

                woo_walmart_sync_log('CA市场转换-数组', '调试', [
                    'field' => $field_name,
                    'item_count' => count($converted)
                ], "字段已转换为多语言数组格式");

                return $converted;
            } elseif (is_string($value)) {
                // 单个字符串转换为数组
                return [['en' => $value]];
            }
        }

        // 如果元数据方式可用，使用元数据（作为备选）
        $field_meta = $this->get_field_metadata($field_name);
        if ($field_meta && $field_meta['multilingual']) {
            if ($field_meta['multilingual_type'] === 'object') {
                return $this->convert_to_multilingual_object($value, $field_meta);
            } elseif ($field_meta['multilingual_type'] === 'array') {
                return $this->convert_to_multilingual_array($value, $field_meta);
            }
        }

        return $value;
    }

    /**
     * 检查值是否已经是多语言格式
     *
     * @param mixed $value 待检查的值
     * @return bool 是否已经是多语言格式
     */
    private function is_already_multilingual($value) {
        if (!is_array($value)) {
            return false;
        }

        // 检查对象格式: {en: "...", fr: "..."}
        if (isset($value['en'])) {
            return true;
        }

        // 检查数组格式: [{en: "...", fr: "..."}, ...]
        if (!empty($value) && is_array($value) && isset($value[0]) && is_array($value[0]) && isset($value[0]['en'])) {
            return true;
        }

        return false;
    }

    /**
     * 转换为多语言对象格式
     * 输入: "One Size" -> 输出: {en: "One Size", fr: "One Size"}
     *
     * @param mixed $value 原始值
     * @param array $field_meta 字段元数据
     * @return array 多语言对象
     */
    private function convert_to_multilingual_object($value, $field_meta) {
        // 处理已经是数组的情况（可能是错误配置）
        if (is_array($value)) {
            // 如果是索引数组，取第一个元素
            if (isset($value[0])) {
                $value = $value[0];
            } else {
                // 关联数组，转为字符串
                $value = implode(', ', $value);
            }
        }

        // 转换为字符串
        $string_value = (string)$value;

        // 构造多语言对象
        $multilingual = [
            'en' => $string_value,
            'fr' => $string_value  // 当前使用相同值，未来可接入翻译API
        ];

        return $multilingual;
    }

    /**
     * 转换为多语言数组格式
     * 输入: ["Oak", "Steel"] -> 输出: [{en: "Oak", fr: "Oak"}, {en: "Steel", fr: "Steel"}]
     *
     * @param mixed $value 原始值（数组或字符串）
     * @param array $field_meta 字段元数据
     * @return array 多语言数组
     */
    private function convert_to_multilingual_array($value, $field_meta) {
        // 确保值是数组
        if (!is_array($value)) {
            // 字符串分隔处理（支持逗号分隔）
            if (is_string($value) && strpos($value, ',') !== false) {
                $value = array_map('trim', explode(',', $value));
            } else {
                $value = [$value];
            }
        }

        // 转换数组中的每个元素
        $multilingual_array = [];
        foreach ($value as $item) {
            // 跳过空值
            if (empty($item)) {
                continue;
            }

            $string_item = (string)$item;
            $multilingual_array[] = [
                'en' => $string_item,
                'fr' => $string_item  // 当前使用相同值，未来可接入翻译API
            ];
        }

        return $multilingual_array;
    }

    /**
     * 获取字段元数据
     *
     * @param string $field_name 字段名称
     * @return array|null 字段元数据或null
     */
    private function get_field_metadata($field_name) {
        if (!$this->ca_field_metadata) {
            return null;
        }

        // 从缓存的元数据中查找
        return $this->ca_field_metadata[$field_name] ?? null;
    }

    /**
     * 加载加拿大字段元数据
     * 支持数据库查询和spec文件动态解析两种方式
     *
     * @param string $category_name 分类名称
     * @return array 字段元数据映射数组
     */
    private function load_ca_field_metadata($category_name) {
        // 首先尝试从spec文件解析（当前主要方法）
        $metadata = $this->parse_ca_spec_metadata_dynamic($category_name);

        // 记录加载结果
        woo_walmart_sync_log('CA字段元数据加载', '调试', [
            'category' => $category_name,
            'metadata_count' => count($metadata),
            'source' => 'spec_file'
        ], "加拿大市场字段元数据已加载");

        return $metadata;
    }

    /**
     * 动态从spec文件解析元数据（主要方法）
     * 解析CA_MP_ITEM_INTL_SPEC.json文件，提取多语言字段信息
     *
     * @param string $category_name 分类名称
     * @return array 字段元数据映射数组
     */
    private function parse_ca_spec_metadata_dynamic($category_name) {
        $spec_file = plugin_dir_path(dirname(__FILE__)) . 'api/CA_MP_ITEM_INTL_SPEC.json';

        if (!file_exists($spec_file)) {
            woo_walmart_sync_log('CA Spec文件', '错误', [
                'file_path' => $spec_file,
                'exists' => false
            ], "加拿大Spec文件不存在");
            return [];
        }

        $spec_content = file_get_contents($spec_file);
        $spec = json_decode($spec_content, true);

        if (!$spec || json_last_error() !== JSON_ERROR_NONE) {
            woo_walmart_sync_log('CA Spec解析', '错误', [
                'error' => json_last_error_msg()
            ], "加拿大Spec文件JSON解析失败");
            return [];
        }

        $metadata = [];

        // 遍历所有产品类型定义
        if (!isset($spec['definitions'])) {
            return [];
        }

        foreach ($spec['definitions'] as $def_name => $definition) {
            // 跳过非产品类型定义
            if (!isset($definition['properties']['Visible'])) {
                continue;
            }

            $visible_props = $definition['properties']['Visible']['properties'] ?? [];

            // 遍历Visible下的所有分类
            foreach ($visible_props as $cat_name => $category_spec) {
                // 只处理当前分类（或处理所有分类以构建完整元数据）
                if (!isset($category_spec['properties'])) {
                    continue;
                }

                // 遍历分类下的所有属性
                foreach ($category_spec['properties'] as $attr_name => $attr_spec) {
                    // 检测多语言对象字段
                    if (isset($attr_spec['type']) &&
                        $attr_spec['type'] === 'object' &&
                        isset($attr_spec['properties']['en'])) {

                        $metadata[$attr_name] = [
                            'multilingual' => true,
                            'multilingual_type' => 'object',
                            'multilingual_required' => $attr_spec['required'] ?? ['en']
                        ];
                    }

                    // 检测多语言数组字段
                    if (isset($attr_spec['type']) &&
                        $attr_spec['type'] === 'array' &&
                        isset($attr_spec['items']['type']) &&
                        $attr_spec['items']['type'] === 'object' &&
                        isset($attr_spec['items']['properties']['en'])) {

                        $metadata[$attr_name] = [
                            'multilingual' => true,
                            'multilingual_type' => 'array',
                            'multilingual_required' => $attr_spec['items']['required'] ?? ['en']
                        ];
                    }
                }
            }
        }

        woo_walmart_sync_log('CA Spec元数据解析', '调试', [
            'total_multilingual_fields' => count($metadata),
            'sample_fields' => array_slice(array_keys($metadata), 0, 5)
        ], "成功解析加拿大多语言字段元数据");

        return $metadata;
    }
}