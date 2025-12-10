<?php
/**
 * 沃尔玛字段验证器 - 基于官方JSON规范
 * 
 * 这个类负责：
 * 1. 解析官方JSON规范
 * 2. 验证字段结构和类型
 * 3. 提供字段的正确默认值
 * 4. 验证字段值是否符合规范
 */

if (!defined('ABSPATH')) {
    exit;
}

class Woo_Walmart_Field_Validator {
    
    private $field_definitions = [];
    private $loaded = false;
    
    public function __construct() {
        $this->load_field_definitions();
    }
    
    /**
     * 加载字段定义（从官方规范或缓存）
     */
    private function load_field_definitions() {
        if ($this->loaded) {
            return;
        }
        
        // 尝试从缓存加载
        $cached_definitions = get_transient('walmart_field_definitions');
        if ($cached_definitions !== false) {
            $this->field_definitions = $cached_definitions;
            $this->loaded = true;
            return;
        }
        
        // 从官方JSON文档解析字段定义
        $this->parse_official_schema();
        
        // 缓存定义（24小时）
        set_transient('walmart_field_definitions', $this->field_definitions, 24 * HOUR_IN_SECONDS);
        $this->loaded = true;
    }
    
    /**
     * 解析官方JSON规范
     */
    private function parse_official_schema() {
        // 基于我们已知的官方规范定义关键字段
        // 这些定义基于官方JSON文档的分析结果
        
        $this->field_definitions = [
            // Orderable部分的字段
            'sku' => [
                'type' => 'string',
                'required' => true,
                'section' => 'Orderable',
                'validation' => ['max_length' => 255]
            ],
            
            'price' => [
                'type' => 'number',
                'required' => true,
                'section' => 'Orderable',
                'validation' => ['min' => 0, 'max' => 10000000000000000, 'decimal_places' => 2]
            ],
            
            'ShippingWeight' => [
                'type' => 'number',
                'required' => true,
                'section' => 'Orderable',
                'validation' => ['min' => 0, 'max' => 10000000000000000, 'decimal_places' => 3],
                'comments' => '@group=Required to sell on Walmart website'
            ],
            
            'productIdentifiers' => [
                'type' => 'object',
                'required' => true,
                'section' => 'Orderable',
                'properties' => [
                    'productIdType' => [
                        'type' => 'string',
                        'required' => true,
                        'enum' => ['UPC', 'GTIN', 'ISBN', 'EAN']
                    ],
                    'productId' => [
                        'type' => 'string',
                        'required' => true,
                        'validation' => ['pattern' => '/^\d{12}$/'] // UPC格式
                    ]
                ]
            ],
            
            // Visible部分的字段
            'productName' => [
                'type' => 'string',
                'required' => true,
                'section' => 'Visible',
                'validation' => ['min_length' => 1, 'max_length' => 199],
                'comments' => '@group=Required to sell on Walmart website'
            ],
            
            'brand' => [
                'type' => 'string',
                'required' => true,
                'section' => 'Visible',
                'validation' => ['min_length' => 1, 'max_length' => 60],
                'comments' => '@group=Required to sell on Walmart website'
            ],
            
            'keyFeatures' => [
                'type' => 'array',
                'required' => true,
                'section' => 'Visible',
                'validation' => ['min_items' => 3, 'max_items' => 6, 'item_max_length' => 10000],
                'comments' => '@group=Required for the item to be visible on Walmart website'
            ],
            
            'mainImageUrl' => [
                'type' => 'string',
                'required' => true,
                'section' => 'Visible',
                'validation' => ['format' => 'url'],
                'comments' => '@group=Required for the item to be visible on Walmart website'
            ],
            
            'netContent' => [
                'type' => 'object',
                'required' => true,
                'section' => 'Visible',
                'properties' => [
                    'productNetContentMeasure' => [
                        'type' => 'number',
                        'required' => true,
                        'validation' => ['min' => 0, 'max' => 10000000000000000, 'decimal_places' => 3]
                    ],
                    'productNetContentUnit' => [
                        'type' => 'string',
                        'required' => true,
                        'enum' => ['Count', 'Inch', 'Foot', 'Yard', 'Millimeter', 'Centimeter', 'Meter', 
                                  'Ounce', 'Pound', 'Gram', 'Kilogram', 'Fluid Ounce', 'Pint', 'Quart', 
                                  'Gallon', 'Milliliter', 'Liter', 'Each']
                    ]
                ],
                'comments' => '@group=Required for the item to be visible on Walmart website'
            ],
            
            'condition' => [
                'type' => 'string',
                'required' => true,
                'section' => 'Visible',
                'enum' => ['New', 'Refurbished', 'Used'],
                'default' => 'New'
            ],
            
            'has_written_warranty' => [
                'type' => 'string',
                'required' => true,
                'section' => 'Visible',
                'enum' => ['Yes', 'No'],
                'default' => 'No'
            ],
            
            'isProp65WarningRequired' => [
                'type' => 'string',
                'required' => true,
                'section' => 'Visible',
                'enum' => ['Yes', 'No'],
                'default' => 'No'
            ],
            
            'shortDescription' => [
                'type' => 'string',
                'required' => false,
                'section' => 'Visible',
                'validation' => ['max_length' => 100000],
                'comments' => '@group=Recommended to improve search and browse on Walmart website'
            ],
            
            // 其他重要字段
            'netContentStatement' => [
                'type' => 'string',
                'required' => false,
                'section' => 'Visible',
                'validation' => ['min_length' => 1, 'max_length' => 500],
                'comments' => '@group=Recommended to improve search and browse on Walmart website'
            ]
        ];
    }
    
    /**
     * 验证字段值是否符合规范
     */
    public function validate_field($field_name, $value) {
        if (!isset($this->field_definitions[$field_name])) {
            return ['valid' => false, 'error' => "未知字段: $field_name"];
        }
        
        $definition = $this->field_definitions[$field_name];
        
        // 检查必填字段
        if ($definition['required'] && ($value === null || $value === '')) {
            return ['valid' => false, 'error' => "字段 $field_name 是必填的"];
        }
        
        // 类型验证
        $type_validation = $this->validate_type($value, $definition);
        if (!$type_validation['valid']) {
            return $type_validation;
        }
        
        // 枚举值验证
        if (isset($definition['enum']) && !in_array($value, $definition['enum'])) {
            return ['valid' => false, 'error' => "字段 $field_name 的值必须是: " . implode(', ', $definition['enum'])];
        }
        
        // 其他验证规则
        if (isset($definition['validation'])) {
            $validation_result = $this->validate_rules($value, $definition['validation'], $field_name);
            if (!$validation_result['valid']) {
                return $validation_result;
            }
        }
        
        return ['valid' => true];
    }
    
    /**
     * 类型验证
     */
    private function validate_type($value, $definition) {
        $expected_type = $definition['type'];
        
        switch ($expected_type) {
            case 'string':
                if (!is_string($value)) {
                    return ['valid' => false, 'error' => '值必须是字符串'];
                }
                break;
                
            case 'number':
                if (!is_numeric($value)) {
                    return ['valid' => false, 'error' => '值必须是数字'];
                }
                break;
                
            case 'array':
                if (!is_array($value)) {
                    return ['valid' => false, 'error' => '值必须是数组'];
                }
                break;
                
            case 'object':
                if (!is_array($value) && !is_object($value)) {
                    return ['valid' => false, 'error' => '值必须是对象'];
                }
                
                // 验证对象属性
                if (isset($definition['properties'])) {
                    foreach ($definition['properties'] as $prop_name => $prop_def) {
                        $prop_value = is_array($value) ? ($value[$prop_name] ?? null) : ($value->$prop_name ?? null);
                        
                        if ($prop_def['required'] && ($prop_value === null || $prop_value === '')) {
                            return ['valid' => false, 'error' => "对象属性 $prop_name 是必填的"];
                        }
                        
                        if ($prop_value !== null) {
                            $prop_validation = $this->validate_type($prop_value, $prop_def);
                            if (!$prop_validation['valid']) {
                                return ['valid' => false, 'error' => "对象属性 $prop_name: " . $prop_validation['error']];
                            }
                        }
                    }
                }
                break;
        }
        
        return ['valid' => true];
    }
    
    /**
     * 验证具体规则
     */
    private function validate_rules($value, $rules, $field_name) {
        foreach ($rules as $rule => $rule_value) {
            switch ($rule) {
                case 'min_length':
                    if (strlen($value) < $rule_value) {
                        return ['valid' => false, 'error' => "$field_name 最少需要 $rule_value 个字符"];
                    }
                    break;
                    
                case 'max_length':
                    if (strlen($value) > $rule_value) {
                        return ['valid' => false, 'error' => "$field_name 最多允许 $rule_value 个字符"];
                    }
                    break;
                    
                case 'min':
                    if ((float)$value < $rule_value) {
                        return ['valid' => false, 'error' => "$field_name 最小值为 $rule_value"];
                    }
                    break;
                    
                case 'max':
                    if ((float)$value > $rule_value) {
                        return ['valid' => false, 'error' => "$field_name 最大值为 $rule_value"];
                    }
                    break;
                    
                case 'min_items':
                    if (count($value) < $rule_value) {
                        return ['valid' => false, 'error' => "$field_name 至少需要 $rule_value 个项目"];
                    }
                    break;
                    
                case 'max_items':
                    if (count($value) > $rule_value) {
                        return ['valid' => false, 'error' => "$field_name 最多允许 $rule_value 个项目"];
                    }
                    break;
                    
                case 'format':
                    if ($rule_value === 'url' && !filter_var($value, FILTER_VALIDATE_URL)) {
                        return ['valid' => false, 'error' => "$field_name 必须是有效的URL"];
                    }
                    break;
            }
        }
        
        return ['valid' => true];
    }
    
    /**
     * 获取字段的默认值
     */
    public function get_default_value($field_name) {
        if (!isset($this->field_definitions[$field_name])) {
            return null;
        }
        
        $definition = $this->field_definitions[$field_name];
        
        if (isset($definition['default'])) {
            return $definition['default'];
        }
        
        // 根据类型返回合适的默认值
        switch ($definition['type']) {
            case 'string':
                return '';
            case 'number':
                return 0;
            case 'array':
                return [];
            case 'object':
                $default_obj = [];
                if (isset($definition['properties'])) {
                    foreach ($definition['properties'] as $prop_name => $prop_def) {
                        if ($prop_def['required']) {
                            $default_obj[$prop_name] = $this->get_default_value_by_type($prop_def);
                        }
                    }
                }
                return $default_obj;
        }
        
        return null;
    }
    
    /**
     * 根据类型获取默认值
     */
    private function get_default_value_by_type($definition) {
        if (isset($definition['default'])) {
            return $definition['default'];
        }
        
        if (isset($definition['enum'])) {
            return $definition['enum'][0];
        }
        
        switch ($definition['type']) {
            case 'string':
                return '';
            case 'number':
                return 0;
            case 'array':
                return [];
            case 'object':
                return [];
        }
        
        return null;
    }
    
    /**
     * 获取字段定义
     */
    public function get_field_definition($field_name) {
        return $this->field_definitions[$field_name] ?? null;
    }
    
    /**
     * 获取所有字段定义
     */
    public function get_all_definitions() {
        return $this->field_definitions;
    }
    
    /**
     * 清除缓存
     */
    public function clear_cache() {
        delete_transient('walmart_field_definitions');
        $this->loaded = false;
        $this->field_definitions = [];
    }
}
