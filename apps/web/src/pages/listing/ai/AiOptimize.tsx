import { useState, useEffect } from 'react';
import {
  Card,
  Button,
  Select,
  Checkbox,
  Table,
  Space,
  Tag,
  message,
  Typography,
  Row,
  Col,
  Modal,
  Descriptions,
  Input,
  Tabs,
  TreeSelect,
  Alert,
  Empty,
} from 'antd';
import { ThunderboltOutlined, CheckOutlined, EyeOutlined, SearchOutlined } from '@ant-design/icons';
import { promptTemplateApi, aiOptimizeApi, productPoolApi, listingApi, shopApi, platformCategoryApi } from '@/services/api';

const { Title, Text } = Typography;
const { TextArea } = Input;

const OPTIMIZE_FIELDS = [
  { value: 'title', label: '标题', templateType: 'title' },
  { value: 'description', label: '描述', templateType: 'description' },
  { value: 'bulletPoints', label: '五点描述', templateType: 'bullet_points' },
  { value: 'keywords', label: '关键词', templateType: 'keywords' },
];

export default function AiOptimize() {
  const [templates, setTemplates] = useState<any[]>([]);
  const [products, setProducts] = useState<any[]>([]);
  const [selectedProducts, setSelectedProducts] = useState<any[]>([]);
  const [selectedFields, setSelectedFields] = useState<string[]>(['title', 'bulletPoints']);
  const [selectedTemplates, setSelectedTemplates] = useState<Record<string, string>>({});
  const [productSource, setProductSource] = useState<'pool' | 'listing'>('pool');
  const [loading, setLoading] = useState(false);
  const [optimizing, setOptimizing] = useState(false);
  const [results, setResults] = useState<any[]>([]);
  const [resultModalVisible, setResultModalVisible] = useState(false);
  const [currentResult, setCurrentResult] = useState<any>(null);

  // 商品选择方式
  const [selectMode, setSelectMode] = useState<'category' | 'sku'>('sku');

  // SKU 批量输入
  const [skuInput, setSkuInput] = useState('');
  const [searchingSkus, setSearchingSkus] = useState(false);

  // 类目选择
  const [shops, setShops] = useState<any[]>([]);
  const [selectedShop, setSelectedShop] = useState<string>('');
  const [categoryTree, setCategoryTree] = useState<any[]>([]);
  const [selectedCategory, setSelectedCategory] = useState<string>('');
  const [loadingCategories, setLoadingCategories] = useState(false);

  useEffect(() => {
    loadTemplates();
    loadShops();
  }, []);

  useEffect(() => {
    if (selectedShop) {
      loadCategories();
    }
  }, [selectedShop]);

  const loadTemplates = async () => {
    try {
      const data: any = await promptTemplateApi.list();
      setTemplates(data.filter((t: any) => t.status === 'active'));
      // 设置默认模板
      const defaults: Record<string, string> = {};
      data.forEach((t: any) => {
        if (t.isDefault && !defaults[t.type]) {
          defaults[t.type] = t.id;
        }
      });
      setSelectedTemplates(defaults);
    } catch (e: any) {
      message.error(e.message);
    }
  };

  const loadShops = async () => {
    try {
      const res: any = await shopApi.list({ pageSize: 100 });
      setShops(res.data || []);
    } catch (e) {
      console.error(e);
    }
  };

  const loadCategories = async () => {
    const shop = shops.find((s) => s.id === selectedShop);
    if (!shop) return;

    setLoadingCategories(true);
    try {
      const res: any = await platformCategoryApi.getCategoryTree(shop.platformId, shop.region || 'US');
      setCategoryTree(convertToTreeData(res || []));
    } catch (e) {
      console.error(e);
      setCategoryTree([]);
    } finally {
      setLoadingCategories(false);
    }
  };

  const convertToTreeData = (categories: any[]): any[] => {
    return categories.map((cat) => ({
      value: cat.categoryId,
      title: cat.name,
      isLeaf: cat.isLeaf,
      children: cat.children ? convertToTreeData(cat.children) : undefined,
    }));
  };

  // 按 SKU 搜索商品
  const handleSearchBySkus = async () => {
    const skus = skuInput
      .split(/[\n,，\s]+/)
      .map((s) => s.trim())
      .filter((s) => s.length > 0);

    if (skus.length === 0) {
      message.warning('请输入 SKU');
      return;
    }

    setSearchingSkus(true);
    setProducts([]);
    setSelectedProducts([]);

    try {
      const results: any[] = [];
      for (const sku of skus) {
        let data: any;
        if (productSource === 'pool') {
          data = await productPoolApi.list({ keyword: sku, pageSize: 10 });
        } else {
          data = await listingApi.getProducts({ keyword: sku, pageSize: 10 });
        }
        // 精确匹配 SKU
        const matched = (data.data || []).filter((p: any) => p.sku.toLowerCase() === sku.toLowerCase());
        results.push(...matched);
      }

      // 去重
      const uniqueProducts = Array.from(new Map(results.map((p) => [p.id, p])).values());
      setProducts(uniqueProducts);
      setSelectedProducts(uniqueProducts);

      if (uniqueProducts.length === 0) {
        message.warning('未找到匹配的商品');
      } else {
        message.success(`找到 ${uniqueProducts.length} 个商品`);
      }
    } catch (e: any) {
      message.error(e.message);
    } finally {
      setSearchingSkus(false);
    }
  };

  // 按类目加载商品
  const handleLoadByCategory = async () => {
    if (!selectedCategory) {
      message.warning('请选择类目');
      return;
    }

    setLoading(true);
    setProducts([]);
    setSelectedProducts([]);

    try {
      let data: any;
      if (productSource === 'pool') {
        data = await productPoolApi.list({ platformCategoryId: selectedCategory, pageSize: 100 });
      } else {
        data = await listingApi.getProducts({
          shopId: selectedShop,
          platformCategoryId: selectedCategory,
          pageSize: 100,
        });
      }
      const productList = data.data || [];
      setProducts(productList);
      setSelectedProducts(productList);

      if (productList.length === 0) {
        message.warning('该类目下没有商品');
      } else {
        message.success(`找到 ${productList.length} 个商品`);
      }
    } catch (e: any) {
      message.error(e.message);
    } finally {
      setLoading(false);
    }
  };

  const handleOptimize = async () => {
    if (selectedProducts.length === 0) {
      message.warning('请选择要优化的商品');
      return;
    }
    if (selectedFields.length === 0) {
      message.warning('请选择要优化的字段');
      return;
    }

    // 构建模板映射
    const templateIds: Record<string, string> = {};
    selectedFields.forEach((field) => {
      const fieldConfig = OPTIMIZE_FIELDS.find((f) => f.value === field);
      if (fieldConfig && selectedTemplates[fieldConfig.templateType]) {
        templateIds[field] = selectedTemplates[fieldConfig.templateType];
      }
    });

    setOptimizing(true);
    setResults([]);
    try {
      if (selectedProducts.length === 1) {
        const result: any = await aiOptimizeApi.optimize({
          productId: selectedProducts[0].id,
          productType: productSource,
          fields: selectedFields as any,
          templateIds,
        });
        setResults([result]);
        message.success('优化完成');
      } else {
        const result: any = await aiOptimizeApi.batchOptimize({
          products: selectedProducts.map((p) => ({ id: p.id, type: productSource })),
          fields: selectedFields as any,
          templateIds,
        });
        setResults(result.results);
        message.success(`优化完成：成功 ${result.success}，失败 ${result.failed}`);
      }
    } catch (e: any) {
      message.error(e.message);
    } finally {
      setOptimizing(false);
    }
  };

  const handleApply = async (logIds: string[]) => {
    try {
      await aiOptimizeApi.apply(logIds);
      message.success('应用成功');
    } catch (e: any) {
      message.error(e.message);
    }
  };

  const showResultDetail = (result: any) => {
    setCurrentResult(result);
    setResultModalVisible(true);
  };

  const productColumns = [
    { title: 'SKU', dataIndex: 'sku', key: 'sku', width: 150 },
    { title: '标题', dataIndex: 'title', key: 'title', ellipsis: true },
    { title: '价格', dataIndex: 'price', key: 'price', width: 100, render: (v: number) => `${v}` },
  ];

  const resultColumns = [
    {
      title: 'SKU',
      dataIndex: ['productId'],
      key: 'sku',
      width: 150,
      render: (_: any, record: any) => {
        const product = selectedProducts.find((p) => p.id === record.productId);
        return product?.sku || record.productId;
      },
    },
    {
      title: '状态',
      dataIndex: 'status',
      key: 'status',
      width: 100,
      render: (status: string) => (
        <Tag color={status === 'success' ? 'green' : 'red'}>{status === 'success' ? '成功' : '失败'}</Tag>
      ),
    },
    { title: 'Token 消耗', dataIndex: 'totalTokens', key: 'totalTokens', width: 120 },
    {
      title: '操作',
      key: 'action',
      render: (_: any, record: any) => (
        <Space>
          {record.status === 'success' && (
            <>
              <Button type="link" size="small" icon={<EyeOutlined />} onClick={() => showResultDetail(record)}>
                查看结果
              </Button>
              <Button
                type="link"
                size="small"
                icon={<CheckOutlined />}
                onClick={() => handleApply(record.results.map((r: any) => r.logId))}
              >
                应用
              </Button>
            </>
          )}
          {record.status === 'failed' && <Text type="danger">{record.error}</Text>}
        </Space>
      ),
    },
  ];

  return (
    <div>
      <Title level={4}>AI 优化工作台</Title>

      <Row gutter={16}>
        <Col span={14}>
          <Card title="选择商品" size="small">
            <Space style={{ marginBottom: 16 }}>
              <Select
                value={productSource}
                onChange={(v) => {
                  setProductSource(v);
                  setProducts([]);
                  setSelectedProducts([]);
                }}
                style={{ width: 120 }}
                options={[
                  { value: 'pool', label: '商品池' },
                  { value: 'listing', label: '刊登商品' },
                ]}
              />
            </Space>

            <Tabs
              activeKey={selectMode}
              onChange={(k) => setSelectMode(k as any)}
              items={[
                {
                  key: 'sku',
                  label: '按 SKU 搜索',
                  children: (
                    <div>
                      <TextArea
                        placeholder="输入 SKU，每行一个或用逗号分隔&#10;例如：&#10;SKU001&#10;SKU002&#10;SKU003"
                        rows={4}
                        value={skuInput}
                        onChange={(e) => setSkuInput(e.target.value)}
                        style={{ marginBottom: 12 }}
                      />
                      <Button
                        type="primary"
                        icon={<SearchOutlined />}
                        onClick={handleSearchBySkus}
                        loading={searchingSkus}
                      >
                        搜索商品
                      </Button>
                    </div>
                  ),
                },
                {
                  key: 'category',
                  label: '按类目选择',
                  children: (
                    <div>
                      {productSource === 'listing' && (
                        <div style={{ marginBottom: 12 }}>
                          <Text type="secondary">店铺：</Text>
                          <Select
                            placeholder="选择店铺"
                            style={{ width: '100%', marginTop: 4 }}
                            value={selectedShop || undefined}
                            onChange={(v) => {
                              setSelectedShop(v);
                              setSelectedCategory('');
                            }}
                            options={shops.map((s) => ({ value: s.id, label: `${s.name} (${s.region || 'US'})` }))}
                          />
                        </div>
                      )}
                      <div style={{ marginBottom: 12 }}>
                        <Text type="secondary">类目：</Text>
                        <TreeSelect
                          placeholder="选择类目"
                          style={{ width: '100%', marginTop: 4 }}
                          value={selectedCategory || undefined}
                          onChange={setSelectedCategory}
                          treeData={categoryTree}
                          loading={loadingCategories}
                          showSearch
                          treeNodeFilterProp="title"
                          dropdownStyle={{ maxHeight: 400, overflow: 'auto' }}
                          disabled={productSource === 'listing' && !selectedShop}
                        />
                      </div>
                      <Button type="primary" icon={<SearchOutlined />} onClick={handleLoadByCategory} loading={loading}>
                        加载商品
                      </Button>
                    </div>
                  ),
                },
              ]}
            />

            {products.length > 0 ? (
              <div style={{ marginTop: 16 }}>
                <div style={{ marginBottom: 8 }}>
                  <Text type="secondary">已选择 {selectedProducts.length} / {products.length} 个商品</Text>
                </div>
                <Table
                  columns={productColumns}
                  dataSource={products}
                  rowKey="id"
                  size="small"
                  pagination={{ pageSize: 10 }}
                  rowSelection={{
                    selectedRowKeys: selectedProducts.map((p) => p.id),
                    onChange: (_, rows) => setSelectedProducts(rows),
                  }}
                  scroll={{ y: 250 }}
                />
              </div>
            ) : (
              <Empty description="请搜索或选择商品" style={{ marginTop: 24 }} />
            )}
          </Card>
        </Col>

        <Col span={10}>
          <Card title="优化配置" size="small">
            <Alert
              message="使用默认 AI 模型进行优化"
              description="请在「AI 模型」页面配置默认模型"
              type="info"
              showIcon
              style={{ marginBottom: 16 }}
            />

            <div style={{ marginBottom: 16 }}>
              <Text strong>优化字段</Text>
              <div style={{ marginTop: 8 }}>
                <Checkbox.Group
                  value={selectedFields}
                  onChange={(v) => setSelectedFields(v as string[])}
                  options={OPTIMIZE_FIELDS.map((f) => ({ label: f.label, value: f.value }))}
                />
              </div>
            </div>

            <div style={{ marginBottom: 16 }}>
              <Text strong>Prompt 模板</Text>
              {selectedFields.map((field) => {
                const fieldConfig = OPTIMIZE_FIELDS.find((f) => f.value === field);
                if (!fieldConfig) return null;
                const fieldTemplates = templates.filter((t) => t.type === fieldConfig.templateType);
                return (
                  <div key={field} style={{ marginTop: 8 }}>
                    <Text type="secondary">{fieldConfig.label}：</Text>
                    <Select
                      value={selectedTemplates[fieldConfig.templateType]}
                      onChange={(v) => setSelectedTemplates({ ...selectedTemplates, [fieldConfig.templateType]: v })}
                      style={{ width: '100%' }}
                      placeholder="选择模板"
                    >
                      {fieldTemplates.map((t) => (
                        <Select.Option key={t.id} value={t.id}>
                          {t.name} {t.isDefault && '(默认)'}
                        </Select.Option>
                      ))}
                    </Select>
                  </div>
                );
              })}
            </div>

            <Button
              type="primary"
              icon={<ThunderboltOutlined />}
              onClick={handleOptimize}
              loading={optimizing}
              disabled={selectedProducts.length === 0}
              block
            >
              开始优化 ({selectedProducts.length} 个商品)
            </Button>
          </Card>
        </Col>
      </Row>

      {results.length > 0 && (
        <Card title="优化结果" size="small" style={{ marginTop: 16 }}>
          <Table columns={resultColumns} dataSource={results} rowKey="productId" size="small" pagination={false} />
        </Card>
      )}

      <Modal
        title="优化结果详情"
        open={resultModalVisible}
        onCancel={() => setResultModalVisible(false)}
        footer={[
          <Button key="close" onClick={() => setResultModalVisible(false)}>
            关闭
          </Button>,
          <Button
            key="apply"
            type="primary"
            onClick={() => {
              handleApply(currentResult?.results?.map((r: any) => r.logId) || []);
              setResultModalVisible(false);
            }}
          >
            应用全部
          </Button>,
        ]}
        width={800}
      >
        {currentResult?.results?.map((r: any, index: number) => (
          <Card
            key={index}
            size="small"
            title={OPTIMIZE_FIELDS.find((f) => f.value === r.field)?.label}
            style={{ marginBottom: 16 }}
          >
            <Descriptions column={1} size="small">
              <Descriptions.Item label="原始内容">
                <div style={{ maxHeight: 100, overflow: 'auto', background: '#f5f5f5', padding: 8, borderRadius: 4 }}>
                  {Array.isArray(r.original) ? r.original.join('\n') : r.original || '(空)'}
                </div>
              </Descriptions.Item>
              <Descriptions.Item label="优化后">
                <div style={{ maxHeight: 150, overflow: 'auto', background: '#e6f7ff', padding: 8, borderRadius: 4 }}>
                  {Array.isArray(r.optimized)
                    ? r.optimized.map((item: string, i: number) => <div key={i}>• {item}</div>)
                    : r.optimized}
                </div>
              </Descriptions.Item>
              <Descriptions.Item label="Token 消耗">{r.tokenUsage}</Descriptions.Item>
            </Descriptions>
          </Card>
        ))}
      </Modal>
    </div>
  );
}
