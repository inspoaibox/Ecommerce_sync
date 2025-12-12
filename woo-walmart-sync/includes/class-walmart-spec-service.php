<?php
if (!defined('ABSPATH')) exit;

/**
 * 沃尔玛API规范服务类
 * 负责查询和应用存储的API规范数据
 */
class Walmart_Spec_Service {
    
    private $wpdb;
    private $attributes_table;
    private $spec_cache = [];
    
    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->attributes_table = $wpdb->prefix . 'walmart_product_attributes';
    }
    
    /**
     * 获取指定分类的字段规范
     * @param string $product_type_id 产品类型ID
     * @param string $field_name 字段名
     * @return array|null 字段规范
     */
    public function get_field_spec($product_type_id, $field_name) {
        $cache_key = $product_type_id . '_' . $field_name;
        
        if (isset($this->spec_cache[$cache_key])) {
            return $this->spec_cache[$cache_key];
        }
        
        $spec = $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT * FROM {$this->attributes_table} 
             WHERE product_type_id = %s AND attribute_name = %s",
            $product_type_id, $field_name
        ));
        
        if ($spec) {
            $result = [
                'type' => $spec->attribute_type,
                'required' => (bool) $spec->is_required,
                'allowed_values' => $this->parse_allowed_values($spec->allowed_values),
                'validation_rules' => $spec->validation_rules ? json_decode($spec->validation_rules, true) : null,
                'group' => $spec->attribute_group,
                'description' => $spec->description
            ];
            
            $this->spec_cache[$cache_key] = $result;
            return $result;
        }
        
        $this->spec_cache[$cache_key] = null;
        return null;
    }
    
    /**
     * 获取指定分类的所有字段规范
     * @param string $product_type_id 产品类型ID
     * @return array 字段规范数组
     */
    public function get_all_field_specs($product_type_id) {
        $cache_key = 'all_' . $product_type_id;
        
        if (isset($this->spec_cache[$cache_key])) {
            return $this->spec_cache[$cache_key];
        }
        
        $specs = $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT * FROM {$this->attributes_table} WHERE product_type_id = %s ORDER BY display_order, attribute_name",
            $product_type_id
        ));
        
        $result = [];
        foreach ($specs as $spec) {
            $result[$spec->attribute_name] = [
                'type' => $spec->attribute_type,
                'required' => (bool) $spec->is_required,
                'allowed_values' => $spec->allowed_values ? json_decode($spec->allowed_values, true) : null,
                'validation_rules' => $spec->validation_rules ? json_decode($spec->validation_rules, true) : null,
                'group' => $spec->attribute_group,
                'description' => $spec->description
            ];
        }
        
        $this->spec_cache[$cache_key] = $result;
        return $result;
    }
    
    /**
     * 验证字段值是否符合API规范
     * @param string $product_type_id 产品类型ID
     * @param string $field_name 字段名
     * @param mixed $value 字段值
     * @return array 验证结果 ['valid' => bool, 'message' => string, 'corrected_value' => mixed]
     */
    public function validate_field_value($product_type_id, $field_name, $value) {
        $spec = $this->get_field_spec($product_type_id, $field_name);
        
        if (!$spec) {
            return ['valid' => true, 'message' => 'No spec found', 'corrected_value' => $value];
        }
        
        // 检查必需字段
        // 将无效值也当作空值处理
        $invalid_values = ['not found', 'n/a', 'na', 'none', 'null', 'undefined', 'unknown'];
        $is_empty = is_null($value) || $value === '' ||
                   (is_string($value) && in_array(strtolower(trim($value)), $invalid_values));

        if ($spec['required'] && $is_empty) {
            // 为必填字段提供默认值
            $default_value = $this->get_default_value_for_field($field_name, $spec);
            return ['valid' => false, 'message' => 'Required field is empty or invalid, using default value', 'corrected_value' => $default_value];
        }
        
        // 检查枚举值（跳过measurement_object和number_with_unit类型，这些类型的allowed_values包含单位信息）
        if ($spec['allowed_values'] && !empty($spec['allowed_values']) &&
            !in_array($spec['type'], ['measurement_object', 'number_with_unit'])) {

            // 过滤掉UNITS:开头的值和空值
            $real_enum_values = array_filter($spec['allowed_values'], function($val) {
                return !is_string($val) || (strpos($val, 'UNITS:') !== 0 && strpos($val, 'DEFAULT_UNIT:') !== 0 && !empty($val));
            });

            // 只有在有真正的枚举值时才进行检查
            if (!empty($real_enum_values) && !in_array($value, $real_enum_values)) {
                $default_value = reset($real_enum_values);

                // 对于multiselect类型，将默认值包装为数组
                if ($spec['type'] === 'multiselect') {
                    $default_value = [$default_value];
                }

                return [
                    'valid' => false,
                    'message' => 'Value not in allowed list: ' . implode(', ', $real_enum_values),
                    'corrected_value' => $default_value
                ];
            }
        }
        
        // 检查数据类型
        $corrected_value = $this->convert_value_to_spec_type($value, $spec, $field_name);
        
        return ['valid' => true, 'message' => 'Valid', 'corrected_value' => $corrected_value];
    }
    
    /**
     * 根据API规范转换值的数据类型
     * @param mixed $value 原始值
     * @param array $spec 字段规范
     * @return mixed 转换后的值
     */
    public function convert_value_to_spec_type($value, $spec, $field_name = '') {
        switch ($spec['type']) {
            case 'measurement_object':
                // 检查是否是netContent类型的复合字段
                if (isset($spec['measure_field']) && isset($spec['unit_field'])) {
                    // 这是netContent类型，需要转换为包含子字段的对象
                    $measure_value = 0;
                    $unit_value = $spec['allowed_units'][0] ?? 'Count';

                    if (is_array($value) && isset($value['measure']) && isset($value['unit'])) {
                        $measure_value = $value['measure'];
                        $unit_value = $value['unit'];
                    } elseif (is_numeric($value)) {
                        $measure_value = (float) $value;
                    }

                    return [
                        $spec['measure_field'] => $measure_value,
                        $spec['unit_field'] => $unit_value
                    ];
                } else {
                    // 标准尺寸对象
                    if (is_array($value) && isset($value['measure']) && isset($value['unit'])) {
                        return $value; // 已经是正确格式
                    }

                    // 如果是数字，转换为measurement对象
                    if (is_numeric($value)) {
                        $default_unit = 'in'; // 默认单位

                        // 从allowed_values中提取单位信息
                        if (isset($spec['allowed_values']) && is_array($spec['allowed_values'])) {
                            foreach ($spec['allowed_values'] as $val) {
                                if (is_string($val)) {
                                    if (strpos($val, 'DEFAULT_UNIT:') === 0) {
                                        $default_unit = substr($val, 13); // 提取DEFAULT_UNIT:后面的值
                                        break;
                                    } elseif (strpos($val, 'UNITS:') !== 0 && !empty($val)) {
                                        $default_unit = $val; // 使用第一个非UNITS:的值作为单位
                                        break;
                                    }
                                }
                            }
                        }

                        // 根据字段组确定单位
                        if (isset($spec['group']) && strpos($spec['group'], 'Weight') !== false) {
                            $default_unit = 'lb';
                        }

                        return [
                            'measure' => (float) $value,
                            'unit' => $default_unit
                        ];
                    }

                    // 如果是带单位的字符串（如 "20 in", "1.5 lb"），解析并转换
                    if (is_string($value) && preg_match('/^(\d+(?:\.\d+)?)\s*([a-zA-Z]+)$/', trim($value), $matches)) {
                        $measure = (float) $matches[1];
                        $unit = $matches[2];

                        // 验证单位是否在允许的单位列表中
                        $allowed_units = [];
                        $default_unit = 'lb'; // 默认单位

                        if (isset($spec['allowed_values']) && is_array($spec['allowed_values'])) {
                            foreach ($spec['allowed_values'] as $val) {
                                if (is_string($val)) {
                                    if (strpos($val, 'UNITS:') === 0) {
                                        // 提取UNITS:后面的单位
                                        $units = substr($val, 6); // 去掉"UNITS:"
                                        $allowed_units[] = $units;
                                    } elseif (strpos($val, 'DEFAULT_UNIT:') === 0) {
                                        // 提取默认单位
                                        $default_unit = substr($val, 13); // 去掉"DEFAULT_UNIT:"
                                    } elseif (!empty($val)) {
                                        $allowed_units[] = $val;
                                    }
                                }
                            }
                        }

                        // 标准化单位名称 (处理复数形式)
                        $normalized_unit = $unit;
                        if ($unit === 'lbs') {
                            $normalized_unit = 'lb';
                        } elseif ($unit === 'ozs') {
                            $normalized_unit = 'oz';
                        }

                        // 如果单位不在允许列表中，使用默认单位
                        if (!empty($allowed_units) && !in_array($normalized_unit, $allowed_units)) {
                            $normalized_unit = $default_unit;
                        }

                        $unit = $normalized_unit;

                        return [
                            'measure' => $measure,
                            'unit' => $unit
                        ];
                    }

                    // 如果不是有效的数字格式，使用默认值
                    // 注意：无效值应该在validate_field_value阶段被处理，这里不应该返回null
                }
                break;

            case 'number_with_unit':
                // 处理带单位的数字字段
                if (is_numeric($value)) {
                    return (float) $value;
                }
                break;
                
            case 'array':
                // 检查是否是尺寸对象数组字段（通过字段名模式判断）
                if (preg_match('/dimension|height.*extended/i', $field_name)) {
                    // 转换为尺寸对象数组
                    $measurement_object = $this->convert_to_measurement_object($value);
                    return [$measurement_object];
                }

                // 普通数组处理
                if (!is_array($value)) {
                    if (is_string($value) && !empty($value)) {
                        // 特殊处理keyFeatures字段 - 支持多种分割符
                        if ($field_name === 'keyFeatures') {
                            // 按换行符、分号或逗号分割
                            $features = preg_split('/[\r\n;,]+/', $value);
                            $features = array_filter(array_map('trim', $features));

                            // 确保至少有3个特性（沃尔玛要求）
                            if (count($features) < 3) {
                                $features = array_merge($features, [
                                    'High Quality Materials',
                                    'Durable Construction',
                                    'Stylish Design'
                                ]);
                                $features = array_slice(array_unique($features), 0, 10); // 最多10个
                            }

                            return $features;
                        } else {
                            // 其他数组字段按逗号分割
                            return array_filter(array_map('trim', explode(',', $value)));
                        }
                    }
                    return [$value];
                }
                return $value;

            case 'multiselect':
                // 多选字段，转换为数组
                if (!is_array($value)) {
                    if (is_string($value) && !empty($value)) {
                        return array_map('trim', explode(',', $value));
                    }
                    return [$value];
                }
                return $value;

            case 'select':
                // 单选字段，保持字符串
                if (is_array($value)) {
                    return is_string($value[0]) ? $value[0] : (string) $value[0];
                }
                return (string) $value;
                
            case 'object_array':
                // 对象数组格式（如stateRestrictions）
                if (!is_array($value)) {
                    return [];
                }
                
                // 如果是简单数组，转换为对象数组
                if (!empty($value) && !is_array($value[0])) {
                    $result = [];
                    foreach ($value as $item) {
                        $result[] = [
                            'stateRestrictionsText' => 'None',
                            'states' => $item
                        ];
                    }
                    return $result;
                }
                return $value;
                
            case 'text':
            case 'textarea':
            case 'string':
                // 如果值是数组，不要强制转换为字符串
                if (is_array($value)) {
                    return $value;
                }
                return (string) $value;

            case 'number':
                return is_numeric($value) ? (float) $value : 0;

            case 'integer':
                return is_numeric($value) ? (int) $value : 0;

            case 'boolean':
                return (bool) $value;
                
            default:
                return $value;
        }
        
        return $value;
    }
    
    /**
     * 获取字段的默认值
     * @param string $product_type_id 产品类型ID
     * @param string $field_name 字段名
     * @return mixed 默认值
     */
    public function get_field_default_value($product_type_id, $field_name) {
        $spec = $this->get_field_spec($product_type_id, $field_name);
        
        if (!$spec) {
            return null;
        }
        
        // 根据字段类型返回合适的默认值
        switch ($spec['type']) {
            case 'measurement_object':
                return [
                    'measure' => 1.0,
                    'unit' => $spec['validation_rules']['allowed_units'][0] ?? 'in'
                ];
                
            case 'array':
                if ($spec['allowed_values']) {
                    return [$spec['allowed_values'][0]];
                }
                return [];
                
            case 'object_array':
                return [
                    [
                        'stateRestrictionsText' => 'None',
                        'states' => 'TX - Texas'
                    ]
                ];
                
            case 'string':
                return $spec['allowed_values'] ? $spec['allowed_values'][0] : '';
                
            case 'number':
                return 1.0;
                
            case 'integer':
                return 1;
                
            case 'boolean':
                return false;
                
            default:
                return null;
        }
    }

    /**
     * 转换值为尺寸对象
     */
    private function convert_to_measurement_object($value) {
        if (is_array($value) && isset($value['measure']) && isset($value['unit'])) {
            return $value; // 已经是尺寸对象
        }

        if (is_string($value)) {
            $trimmed_value = trim($value);

            // 解析包含单位的字符串，如 "8.66 in"
            if (preg_match('/^([\d.]+)\s*([a-zA-Z]+)$/', $trimmed_value, $matches)) {
                return [
                    'measure' => (float) $matches[1],
                    'unit' => $matches[2]
                ];
            }

            // 如果是纯数字字符串
            if (is_numeric($trimmed_value)) {
                return [
                    'measure' => (float) $trimmed_value,
                    'unit' => 'in' // 默认单位
                ];
            }

            // 对于无效的字符串值，返回默认的measurement对象
            // 注意：这里不返回null，因为调用方可能需要一个有效的measurement对象
            return [
                'measure' => 1.0,
                'unit' => 'in'
            ];
        } elseif (is_numeric($value)) {
            return [
                'measure' => (float) $value,
                'unit' => 'in' // 默认单位
            ];
        }

        // 其他情况返回默认值
        return [
            'measure' => 1.0,
            'unit' => 'in'
        ];
    }

    /**
     * 为字段获取默认值
     */
    public function get_default_value_for_field($field_name, $spec) {
        // 根据字段类型提供默认值（优先处理特殊类型）
        switch ($spec['type']) {
            case 'select':
            case 'multiselect':
                return isset($spec['allowed_values']) ? $spec['allowed_values'][0] : '';

            case 'measurement_object':
                if (isset($spec['measure_field']) && isset($spec['unit_field'])) {
                    // netContent类型
                    return [
                        $spec['measure_field'] => 1.0,
                        $spec['unit_field'] => $spec['allowed_units'][0] ?? 'Count'
                    ];
                } else {
                    // 标准尺寸对象
                    return [
                        'measure' => 1.0,
                        'unit' => $spec['allowed_units'][0] ?? 'in'
                    ];
                }

            case 'number_with_unit':
                return 1.0;

            case 'array':
                return [];

            case 'boolean':
                return false;

            case 'number':
            case 'integer':
                return 1;

            default:
                // 对于其他类型，如果有枚举值，使用第一个非空的枚举值
                if (isset($spec['allowed_values']) && !empty($spec['allowed_values'])) {
                    foreach ($spec['allowed_values'] as $allowed_value) {
                        if (!empty($allowed_value) && !preg_match('/^(UNITS:|DEFAULT_UNIT:)/', $allowed_value)) {
                            return $allowed_value;
                        }
                    }
                }
                return '';
        }
    }

    /**
     * 解析允许值字符串
     */
    private function parse_allowed_values($allowed_values_string) {
        if (empty($allowed_values_string)) {
            return null;
        }

        // 尝试JSON解析
        $json_decoded = json_decode($allowed_values_string, true);
        if ($json_decoded !== null) {
            return $json_decoded;
        }

        // 如果不是JSON，尝试按|分割
        if (strpos($allowed_values_string, '|') !== false) {
            return array_map('trim', explode('|', $allowed_values_string));
        }

        // 如果都不是，返回单个值的数组
        return [trim($allowed_values_string)];
    }
}
?>
