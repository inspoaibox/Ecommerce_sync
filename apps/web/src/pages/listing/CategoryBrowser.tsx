import { useState, useEffect } from 'react';
import { Card, Tree, Input, Button, Space, message, Spin, Tag, Descriptions, Empty, Select, Table, Form, Modal, Popconfirm, Tooltip, Alert } from 'antd';
import { SyncOutlined, SearchOutlined, GlobalOutlined, SaveOutlined, DeleteOutlined, EditOutlined, DownloadOutlined, ReloadOutlined } from '@ant-design/icons';
import { platformApi, platformCategoryApi } from '@/services/api';
import type { DataNode } from 'antd/es/tree';

// Walmart 支持的市场
const COUNTRY_OPTIONS = [
  { value: 'US', label: '🇺🇸 美国 (US)' },
  { value: 'CA', label: '🇨🇦 加拿大 (CA)' },
  { value: 'MX', label: '🇲🇽 墨西哥 (MX)' },
  { value: 'CL', label: '🇨🇱 智利 (CL)' },
];

// 映射类型选项
const MAPPING_TYPE_OPTIONS = [
  { value: 'default_value', label: '默认值', color: 'blue' },
  { value: 'channel_data', label: '渠道数据', color: 'green' },
  { value: 'enum_select', label: '枚举选择', color: 'orange' },
  { value: 'auto_generate', label: '自动生成', color: 'purple' },
  { value: 'upc_pool', label: 'UPC池', color: 'cyan' },
];

// 常用渠道字段路径
const CHANNEL_DATA_OPTIONS = [
  { value: 'brand', label: '品牌 (brand)' },
  { value: 'mpn', label: 'MPN' },
  { value: 'upc', label: 'UPC' },
  { value: 'weight', label: '重量 (weight)' },
  { value: 'weightUnit', label: '重量单位 (weightUnit)' },
  { value: 'length', label: '长度 (length)' },
  { value: 'width', label: '宽度 (width)' },
  { value: 'height', label: '高度 (height)' },
  { value: 'lengthUnit', label: '长度单位 (lengthUnit)' },
  { value: 'assembledWeight', label: '组装后重量' },
  { value: 'assembledLength', label: '组装后长度' },
  { value: 'assembledWidth', label: '组装后宽度' },
  { value: 'assembledHeight', label: '组装后高度' },
  { value: 'placeOfOrigin', label: '产地' },
  { value: 'category', label: '类目' },
  { value: 'categoryCode', label: '类目代码' },
  { value: 'shippingFee', label: '运费' },
];

interface MappingRule {
  attributeId: string;
  attributeName: string;
  mappingType: 'default_value' | 'channel_data' | 'enum_select' | 'auto_generate' | 'upc_pool';
  value: string;
  isRequired: boolean;
  dataType: string;
  enumValues?: string[];
}

export default function CategoryBrowser() {
  const [platforms, setPlatforms] = useState<any[]>([]);
  const [selectedPlatform, setSelectedPlatform] = useState<string>('');
  const [selectedCountry, setSelectedCountry] = useState<string>('US');
  const [availableCountries, setAvailableCountries] = useState<string[]>([]);
  const [treeData, setTreeData] = useState<DataNode[]>([]);
  const [loading, setLoading] = useState(false);
  const [syncing, setSyncing] = useState(false);
  const [searchKeyword, setSearchKeyword] = useState('');
  const [searchResults, setSearchResults] = useState<any[]>([]);
  const [searching, setSearching] = useState(false);
  const [selectedCategory, setSelectedCategory] = useState<any>(null);
  const [attributes, setAttributes] = useState<any[]>([]);
  const [loadingAttrs, setLoadingAttrs] = useState(false);
  
  // 属性映射相关状态
  const [mappingRules, setMappingRules] = useState<MappingRule[]>([]);
  const [loadingMapping, setLoadingMapping] = useState(false);
  const [savingMapping, setSavingMapping] = useState(false);
  const [editingRule, setEditingRule] = useState<MappingRule | null>(null);
  const [editModalVisible, setEditModalVisible] = useState(false);
  const [form] = Form.useForm();

  useEffect(() => {
    loadPlatforms();
  }, []);

  const loadPlatforms = async () => {
    try {
      const res: any = await platformApi.list({ pageSize: 100 });
      setPlatforms(res.data || []);
      if (res.data?.length > 0) {
        setSelectedPlatform(res.data[0].id);
      }
    } catch (e) {
      console.error(e);
    }
  };

  useEffect(() => {
    if (selectedPlatform) {
      loadAvailableCountries();
      loadCategoryTree();
    }
  }, [selectedPlatform, selectedCountry]);

  const loadAvailableCountries = async () => {
    try {
      const res: any = await platformCategoryApi.getCountries(selectedPlatform);
      setAvailableCountries(res || []);
    } catch (e) {
      console.error(e);
    }
  };

  const loadCategoryTree = async () => {
    setLoading(true);
    try {
      const res: any = await platformCategoryApi.getCategoryTree(selectedPlatform, selectedCountry);
      setTreeData(convertToTreeData(res || []));
    } catch (e) {
      console.error(e);
    } finally {
      setLoading(false);
    }
  };

  const convertToTreeData = (categories: any[]): DataNode[] => {
    return categories.map(cat => ({
      key: cat.id,
      title: (
        <span>
          {cat.name}
          {cat.isLeaf && <Tag color="green" style={{ marginLeft: 8 }}>叶子</Tag>}
        </span>
      ),
      isLeaf: cat.isLeaf,
      children: cat.children ? convertToTreeData(cat.children) : undefined,
      data: cat,
    }));
  };

  const handleSync = async () => {
    if (!selectedPlatform) return;
    setSyncing(true);
    try {
      const res: any = await platformCategoryApi.syncCategories(selectedPlatform, selectedCountry);
      message.success(`同步完成（${res.country}）：新增 ${res.created}，更新 ${res.updated}，共 ${res.total} 个类目`);
      loadAvailableCountries();
      loadCategoryTree();
    } catch (e: any) {
      message.error(e.message || '同步失败');
    } finally {
      setSyncing(false);
    }
  };

  const handleSearch = async () => {
    if (!selectedPlatform || !searchKeyword.trim()) {
      message.warning('请输入搜索关键词');
      return;
    }
    setSearching(true);
    try {
      const res: any = await platformCategoryApi.searchCategories(selectedPlatform, searchKeyword.trim(), selectedCountry);
      const results = res?.data || res || [];
      setSearchResults(results);
      if (results.length === 0) {
        message.info('未找到匹配的类目');
      }
    } catch (e: any) {
      console.error(e);
      message.error(e.message || '搜索失败');
    } finally {
      setSearching(false);
    }
  };

  const handleSelectCategory = async (category: any) => {
    setSelectedCategory(category);
    setLoadingMapping(true);
    setAttributes([]);
    
    try {
      // 只加载已保存的映射配置
      const mappingRes: any = await platformCategoryApi.getCategoryAttributeMapping(
        selectedPlatform, 
        category.categoryId, 
        selectedCountry
      ).catch(() => null);
      
      if (mappingRes?.mappingRules?.rules) {
        setMappingRules(mappingRes.mappingRules.rules);
      } else {
        // 没有映射配置，显示空列表
        setMappingRules([]);
      }
    } catch (e) {
      console.error(e);
      setMappingRules([]);
    } finally {
      setLoadingMapping(false);
    }
  };

  // 从平台加载属性并生成映射规则
  const handleLoadPlatformAttributes = async () => {
    if (!selectedCategory) return;
    
    // 检查是否是叶子类目
    if (!selectedCategory.isLeaf) {
      message.warning('请选择叶子类目（Product Type）才能加载属性');
      return;
    }
    
    setLoadingAttrs(true);
    try {
      console.log('[CategoryBrowser] Loading attributes for category:', selectedCategory.categoryId);
      
      const attrsRes: any = await platformCategoryApi.getCategoryAttributes(
        selectedPlatform, 
        selectedCategory.categoryId, 
        selectedCountry
      );
      
      console.log('[CategoryBrowser] Attributes response:', attrsRes);
      
      setAttributes(attrsRes || []);
      
      if (!attrsRes || attrsRes.length === 0) {
        // 显示更详细的提示
        Modal.info({
          title: '属性加载结果',
          content: (
            <div>
              <p>该类目暂无属性数据返回，可能的原因：</p>
              <ul>
                <li>平台 API 暂时不可用</li>
                <li>该类目在平台上没有定义属性规范</li>
                <li>需要使用正确的 Product Type ID</li>
              </ul>
              <p style={{ marginTop: 12 }}>
                <strong>类目ID:</strong> {selectedCategory.categoryId}<br/>
                <strong>类目路径:</strong> {selectedCategory.categoryPath}
              </p>
            </div>
          ),
        });
        return;
      }
      
      // 根据平台属性生成映射规则
      const newRules: MappingRule[] = attrsRes.map((attr: any) => {
        // 尝试智能匹配渠道字段
        const autoMappedField = getAutoMappedField(attr.attributeId, attr.name);
        
        // 如果有枚举值，默认使用枚举选择类型
        const hasEnumValues = attr.enumValues && attr.enumValues.length > 0;
        let mappingType: MappingRule['mappingType'] = 'default_value';
        let value = '';
        
        if (autoMappedField) {
          mappingType = 'channel_data';
          value = autoMappedField;
        } else if (hasEnumValues) {
          mappingType = 'enum_select';
          value = ''; // 用户需要选择
        }
        
        return {
          attributeId: attr.attributeId,
          attributeName: attr.name,
          mappingType,
          value,
          isRequired: attr.isRequired,
          dataType: attr.dataType,
          enumValues: attr.enumValues,
        };
      });
      
      // 按必填优先排序
      newRules.sort((a, b) => {
        if (a.isRequired && !b.isRequired) return -1;
        if (!a.isRequired && b.isRequired) return 1;
        return 0;
      });
      
      setMappingRules(newRules);
      
      const requiredCount = newRules.filter(r => r.isRequired).length;
      const autoMappedCount = newRules.filter(r => r.value).length;
      message.success(`已加载 ${newRules.length} 个属性（${requiredCount} 个必填，${autoMappedCount} 个已自动匹配）`);
    } catch (e: any) {
      console.error('[CategoryBrowser] Load attributes error:', e);
      message.error(e.message || '加载属性失败，请检查网络连接或稍后重试');
    } finally {
      setLoadingAttrs(false);
    }
  };

  // 智能匹配渠道字段
  const getAutoMappedField = (attributeId: string, attributeName: string): string | null => {
    const id = attributeId.toLowerCase();
    const name = attributeName.toLowerCase();
    
    // 常见字段自动映射
    const mappings: Record<string, string> = {
      'brand': 'brand',
      'mpn': 'mpn',
      'upc': 'upc',
      'gtin': 'upc',
      'productname': 'title',
      'shortdescription': 'description',
      'shippingweight': 'weight',
      'weight': 'weight',
      'shippingweightunit': 'weightUnit',
      'shippinglength': 'length',
      'shippingwidth': 'width',
      'shippingheight': 'height',
      'assembledproductweight': 'assembledWeight',
      'assembledproductlength': 'assembledLength',
      'assembledproductwidth': 'assembledWidth',
      'assembledproductheight': 'assembledHeight',
      'countryoforiginassembly': 'placeOfOrigin',
    };
    
    // 先按 attributeId 匹配
    if (mappings[id]) return mappings[id];
    
    // 再按名称关键词匹配
    for (const [key, value] of Object.entries(mappings)) {
      if (name.includes(key) || id.includes(key)) {
        return value;
      }
    }
    
    return null;
  };

  // 重置映射规则（重新从平台加载）
  const handleResetMapping = () => {
    Modal.confirm({
      title: '重置映射配置',
      content: '确定要重新从平台加载属性吗？当前未保存的配置将丢失。',
      onOk: handleLoadPlatformAttributes,
    });
  };

  const handleTreeSelect = (_selectedKeys: any[], info: any) => {
    if (info.node?.data) {
      handleSelectCategory(info.node.data);
    }
  };

  // 保存映射配置
  const handleSaveMapping = async () => {
    if (!selectedCategory) return;
    
    setSavingMapping(true);
    try {
      await platformCategoryApi.saveCategoryAttributeMapping(
        selectedPlatform,
        selectedCategory.categoryId,
        { rules: mappingRules },
        selectedCountry,
      );
      message.success('映射配置保存成功');
    } catch (e: any) {
      message.error(e.message || '保存失败');
    } finally {
      setSavingMapping(false);
    }
  };

  // 删除映射配置
  const handleDeleteMapping = async () => {
    if (!selectedCategory) return;
    
    try {
      await platformCategoryApi.deleteCategoryAttributeMapping(
        selectedPlatform,
        selectedCategory.categoryId,
        selectedCountry,
      );
      message.success('映射配置已删除');
      // 重新加载默认规则
      const defaultRules: MappingRule[] = attributes.map((attr: any) => ({
        attributeId: attr.attributeId,
        attributeName: attr.name,
        mappingType: 'default_value' as const,
        value: '',
        isRequired: attr.isRequired,
        dataType: attr.dataType,
        enumValues: attr.enumValues,
      }));
      setMappingRules(defaultRules);
    } catch (e: any) {
      message.error(e.message || '删除失败');
    }
  };

  // 编辑规则
  const handleEditRule = (rule: MappingRule) => {
    setEditingRule(rule);
    form.setFieldsValue({
      mappingType: rule.mappingType,
      value: rule.value,
    });
    setEditModalVisible(true);
  };

  // 保存规则编辑
  const handleSaveRule = () => {
    form.validateFields().then(values => {
      if (editingRule) {
        setMappingRules(prev => prev.map(r => 
          r.attributeId === editingRule.attributeId 
            ? { ...r, mappingType: values.mappingType, value: values.value }
            : r
        ));
      }
      setEditModalVisible(false);
      setEditingRule(null);
      form.resetFields();
    });
  };

  // 快速更新规则
  const handleQuickUpdate = (attributeId: string, field: 'mappingType' | 'value', value: any) => {
    setMappingRules(prev => prev.map(r => 
      r.attributeId === attributeId ? { ...r, [field]: value } : r
    ));
  };

  // 删除规则
  const handleDeleteRule = (attributeId: string) => {
    setMappingRules(prev => prev.filter(r => r.attributeId !== attributeId));
  };

  // 映射规则表格列
  const mappingColumns = [
    {
      title: '属性名称',
      dataIndex: 'attributeName',
      width: 180,
      render: (text: string, record: MappingRule) => (
        <Space>
          <span>{text}</span>
          {record.isRequired && <Tag color="red">必填</Tag>}
        </Space>
      ),
    },
    {
      title: '映射类型',
      dataIndex: 'mappingType',
      width: 140,
      render: (type: string, record: MappingRule) => {
        return (
          <Select
            size="small"
            value={type}
            style={{ width: 120 }}
            onChange={v => handleQuickUpdate(record.attributeId, 'mappingType', v)}
            options={MAPPING_TYPE_OPTIONS}
          />
        );
      },
    },
    {
      title: '值/来源',
      dataIndex: 'value',
      render: (value: string, record: MappingRule) => {
        if (record.mappingType === 'channel_data') {
          return (
            <Select
              size="small"
              value={value}
              style={{ width: '100%' }}
              onChange={v => handleQuickUpdate(record.attributeId, 'value', v)}
              options={CHANNEL_DATA_OPTIONS}
              placeholder="选择渠道字段"
              allowClear
              showSearch
            />
          );
        }
        if (record.mappingType === 'enum_select' && record.enumValues?.length) {
          return (
            <Select
              size="small"
              value={value}
              style={{ width: '100%' }}
              onChange={v => handleQuickUpdate(record.attributeId, 'value', v)}
              options={record.enumValues.map(v => ({ value: v, label: v }))}
              placeholder="选择枚举值"
              allowClear
              showSearch
            />
          );
        }
        if (record.mappingType === 'auto_generate') {
          return <Tag color="purple">自动生成</Tag>;
        }
        if (record.mappingType === 'upc_pool') {
          return <Tag color="cyan">从 UPC 池获取</Tag>;
        }
        return (
          <Input
            size="small"
            value={value}
            onChange={e => handleQuickUpdate(record.attributeId, 'value', e.target.value)}
            placeholder="输入默认值"
          />
        );
      },
    },
    {
      title: '数据类型',
      dataIndex: 'dataType',
      width: 80,
      render: (type: string) => <Tag>{type}</Tag>,
    },
    {
      title: '操作',
      width: 80,
      render: (_: any, record: MappingRule) => (
        <Space size="small">
          <Tooltip title="编辑">
            <Button type="link" size="small" icon={<EditOutlined />} onClick={() => handleEditRule(record)} />
          </Tooltip>
          <Popconfirm title="确定删除此规则？" onConfirm={() => handleDeleteRule(record.attributeId)}>
            <Button type="link" size="small" danger icon={<DeleteOutlined />} />
          </Popconfirm>
        </Space>
      ),
    },
  ];

  return (
    <div style={{ display: 'flex', gap: 16, height: 'calc(100vh - 120px)' }}>
      {/* 左侧：类目树 */}
      <Card
        title={<span style={{ whiteSpace: 'nowrap' }}>平台类目</span>}
        style={{ width: 420, flexShrink: 0, display: 'flex', flexDirection: 'column' }}
        styles={{ 
          header: { minHeight: 'auto', padding: '12px 16px' },
          body: { flex: 1, overflow: 'hidden', display: 'flex', flexDirection: 'column' } 
        }}
        extra={
          <Space size="small" wrap={false}>
            <Select
              value={selectedPlatform}
              onChange={setSelectedPlatform}
              style={{ width: 90 }}
              options={platforms.map(p => ({ value: p.id, label: p.name }))}
              size="small"
            />
            <Select
              value={selectedCountry}
              onChange={setSelectedCountry}
              style={{ width: 80 }}
              options={COUNTRY_OPTIONS}
              suffixIcon={<GlobalOutlined />}
              size="small"
            />
            <Button icon={<SyncOutlined />} onClick={handleSync} loading={syncing} size="small">
              同步
            </Button>
          </Space>
        }
      >
        <Space.Compact style={{ width: '100%', marginBottom: 16 }}>
          <Input
            placeholder="搜索类目"
            value={searchKeyword}
            onChange={e => setSearchKeyword(e.target.value)}
            onPressEnter={handleSearch}
          />
          <Button icon={<SearchOutlined />} onClick={handleSearch} loading={searching} />
        </Space.Compact>

        {searchResults.length > 0 && (
          <div style={{ marginBottom: 16, maxHeight: 200, overflow: 'auto', border: '1px solid #f0f0f0', borderRadius: 4, padding: 8 }}>
            <div style={{ marginBottom: 8, color: '#666' }}>搜索结果 ({searchResults.length})</div>
            {searchResults.map(cat => (
              <div
                key={cat.id}
                style={{ padding: '4px 8px', cursor: 'pointer', borderRadius: 4 }}
                className="hover:bg-gray-100"
                onClick={() => handleSelectCategory(cat)}
              >
                <div>{cat.name}</div>
                <div style={{ fontSize: 12, color: '#999' }}>{cat.categoryPath}</div>
              </div>
            ))}
          </div>
        )}

        <div style={{ flex: 1, minHeight: 0, overflow: 'auto' }}>
          <Spin spinning={loading}>
            {treeData.length > 0 ? (
              <Tree
                treeData={treeData}
                onSelect={handleTreeSelect}
                defaultExpandAll={false}
              />
            ) : (
              <Empty description="暂无类目数据，请点击同步按钮" />
            )}
          </Spin>
        </div>
      </Card>

      {/* 右侧：类目详情和属性映射 */}
      <Card 
        title={selectedCategory ? `类目详情 - ${selectedCategory.name}` : '类目详情'}
        style={{ flex: 1, display: 'flex', flexDirection: 'column' }}
        styles={{ body: { flex: 1, overflow: 'auto' } }}
        extra={
          selectedCategory && (
            <Space>
              <Button 
                type="primary" 
                icon={<SaveOutlined />} 
                onClick={handleSaveMapping}
                loading={savingMapping}
              >
                保存映射
              </Button>
              <Popconfirm title="确定删除此类目的映射配置？" onConfirm={handleDeleteMapping}>
                <Button danger icon={<DeleteOutlined />}>删除映射</Button>
              </Popconfirm>
            </Space>
          )
        }
      >
        {selectedCategory ? (
          <div>
            {/* 类目基本信息 */}
            <Descriptions bordered column={2} size="small" style={{ marginBottom: 16 }}>
              <Descriptions.Item label="类目ID">{selectedCategory.categoryId}</Descriptions.Item>
              <Descriptions.Item label="名称">{selectedCategory.name}</Descriptions.Item>
              <Descriptions.Item label="路径" span={2}>{selectedCategory.categoryPath}</Descriptions.Item>
              <Descriptions.Item label="层级">{selectedCategory.level}</Descriptions.Item>
              <Descriptions.Item label="国家">
                <Tag color="blue">{selectedCategory.country || selectedCountry}</Tag>
              </Descriptions.Item>
              <Descriptions.Item label="是否叶子">
                <Tag color={selectedCategory.isLeaf ? 'green' : 'default'}>
                  {selectedCategory.isLeaf ? '是' : '否'}
                </Tag>
              </Descriptions.Item>
            </Descriptions>
            
            {availableCountries.length > 0 && (
              <div style={{ marginBottom: 16, padding: 8, background: '#f5f5f5', borderRadius: 4 }}>
                <span style={{ fontSize: 12, color: '#666' }}>已同步国家：</span>
                {availableCountries.map(c => (
                  <Tag 
                    key={c} 
                    color={c === selectedCountry ? 'blue' : 'default'}
                    style={{ cursor: 'pointer' }}
                    onClick={() => setSelectedCountry(c)}
                  >
                    {COUNTRY_OPTIONS.find(o => o.value === c)?.label || c}
                  </Tag>
                ))}
              </div>
            )}

            {/* 属性映射配置 */}
            <div style={{ marginTop: 16 }}>
              <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 12 }}>
                <h4 style={{ margin: 0 }}>
                  属性映射配置 ({mappingRules.length})
                  <Tooltip title="配置每个属性的映射规则，导入商品时将自动应用这些规则生成平台属性">
                    <span style={{ marginLeft: 8, fontSize: 12, color: '#999' }}>?</span>
                  </Tooltip>
                </h4>
                <Space>
                  <Button 
                    type="primary"
                    size="small" 
                    icon={<DownloadOutlined />} 
                    onClick={handleLoadPlatformAttributes}
                    loading={loadingAttrs}
                  >
                    加载平台属性
                  </Button>
                  {mappingRules.length > 0 && (
                    <Button 
                      size="small" 
                      icon={<ReloadOutlined />} 
                      onClick={handleResetMapping}
                    >
                      重置
                    </Button>
                  )}
                </Space>
              </div>

              {mappingRules.length === 0 && !loadingMapping && (
                <Alert
                  message="暂无属性映射配置"
                  description={
                    <div>
                      <p>点击「加载平台属性」按钮从平台获取该类目的属性列表，系统将自动生成映射规则。</p>
                      <p style={{ marginBottom: 0 }}>支持的映射类型：</p>
                      <ul style={{ marginBottom: 0, paddingLeft: 20 }}>
                        <li><Tag color="blue">默认值</Tag> - 为所有商品设置固定值</li>
                        <li><Tag color="green">渠道数据</Tag> - 从渠道商品数据自动提取</li>
                        <li><Tag color="orange">枚举选择</Tag> - 从平台允许的值中选择</li>
                        <li><Tag color="purple">自动生成</Tag> - 系统智能生成</li>
                        <li><Tag color="cyan">UPC池</Tag> - 从 UPC 池获取未使用的 UPC</li>
                      </ul>
                    </div>
                  }
                  type="info"
                  showIcon
                  style={{ marginBottom: 16 }}
                />
              )}
              
              <Spin spinning={loadingAttrs || loadingMapping}>
                {mappingRules.length > 0 && (
                  <Table
                    dataSource={mappingRules}
                    columns={mappingColumns}
                    rowKey="attributeId"
                    size="small"
                    pagination={false}
                  />
                )}
              </Spin>
            </div>
          </div>
        ) : (
          <Empty description="请选择一个类目查看详情和配置映射" />
        )}
      </Card>

      {/* 编辑规则弹窗 */}
      <Modal
        title={`编辑属性映射 - ${editingRule?.attributeName}`}
        open={editModalVisible}
        onOk={handleSaveRule}
        onCancel={() => { setEditModalVisible(false); setEditingRule(null); form.resetFields(); }}
        width={500}
      >
        <Form form={form} layout="vertical">
          <Form.Item label="映射类型" name="mappingType" rules={[{ required: true }]}>
            <Select options={MAPPING_TYPE_OPTIONS} />
          </Form.Item>
          <Form.Item 
            label="值/来源" 
            name="value"
            extra={
              <div style={{ fontSize: 12, color: '#999', marginTop: 4 }}>
                {editingRule?.mappingType === 'channel_data' && '选择从渠道商品数据中提取的字段'}
                {editingRule?.mappingType === 'default_value' && '输入固定的默认值'}
                {editingRule?.mappingType === 'enum_select' && '从平台允许的枚举值中选择'}
                {editingRule?.mappingType === 'auto_generate' && '系统将根据属性类型自动生成值'}
                {editingRule?.mappingType === 'upc_pool' && '从 UPC 池中自动获取未使用的 UPC 码'}
              </div>
            }
          >
            {editingRule?.mappingType === 'channel_data' ? (
              <Select options={CHANNEL_DATA_OPTIONS} placeholder="选择渠道字段" allowClear showSearch />
            ) : editingRule?.mappingType === 'enum_select' && editingRule?.enumValues?.length ? (
              <Select 
                options={editingRule.enumValues.map(v => ({ value: v, label: v }))} 
                placeholder="选择枚举值" 
                allowClear 
                showSearch 
              />
            ) : editingRule?.mappingType === 'auto_generate' ? (
              <Input disabled placeholder="自动生成" />
            ) : editingRule?.mappingType === 'upc_pool' ? (
              <Input disabled placeholder="从 UPC 池获取" />
            ) : (
              <Input placeholder="输入默认值" />
            )}
          </Form.Item>
          {editingRule?.enumValues && editingRule.enumValues.length > 0 && (
            <div style={{ marginBottom: 16 }}>
              <div style={{ fontSize: 12, color: '#666', marginBottom: 4 }}>可选枚举值：</div>
              <div style={{ maxHeight: 150, overflow: 'auto' }}>
                {editingRule.enumValues.slice(0, 20).map((v, i) => (
                  <Tag key={i} style={{ marginBottom: 4 }}>{v}</Tag>
                ))}
                {editingRule.enumValues.length > 20 && (
                  <span style={{ fontSize: 12, color: '#999' }}>...等 {editingRule.enumValues.length} 个</span>
                )}
              </div>
            </div>
          )}
        </Form>
      </Modal>
    </div>
  );
}
