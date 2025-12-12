import { useState, useEffect } from 'react';
import { Modal, Form, Input, InputNumber, Select, Tabs, Space, Button, message, Image, Spin, Card, Alert } from 'antd';
import { RobotOutlined } from '@ant-design/icons';
import { listingApi, platformCategoryApi, aiModelApi, aiOptimizeApi } from '@/services/api';

const { TextArea } = Input;

interface Props {
  visible: boolean;
  productId: string | null;
  onClose: () => void;
  onSuccess: () => void;
}

export default function ListingProductEdit({ visible, productId, onClose, onSuccess }: Props) {
  const [form] = Form.useForm();
  const [loading, setLoading] = useState(false);
  const [saving, setSaving] = useState(false);
  const [product, setProduct] = useState<any>(null);
  const [categories, setCategories] = useState<any[]>([]);
  const [searchingCat, setSearchingCat] = useState(false);

  // AI 优化
  const [aiOptimizeModal, setAiOptimizeModal] = useState(false);
  const [aiModels, setAiModels] = useState<any[]>([]);
  const [selectedAiModel, setSelectedAiModel] = useState<string>('');
  const [selectedFields, setSelectedFields] = useState<('title' | 'description' | 'bulletPoints' | 'keywords')[]>(['title']);
  const [optimizing, setOptimizing] = useState(false);
  const [optimizeResults, setOptimizeResults] = useState<any[]>([]);

  useEffect(() => {
    if (visible && productId) {
      loadProduct();
    }
  }, [visible, productId]);

  const loadProduct = async () => {
    if (!productId) return;
    setLoading(true);
    try {
      const res: any = await listingApi.getProduct(productId);
      setProduct(res);
      form.setFieldsValue({
        title: res.title,
        description: res.description,
        price: Number(res.price),
        stock: res.stock,
        currency: res.currency,
        platformCategoryId: res.platformCategoryId,
      });
    } catch (e: any) {
      message.error(e.message || '加载失败');
    } finally {
      setLoading(false);
    }
  };

  const handleSave = async () => {
    try {
      const values = await form.validateFields();
      setSaving(true);
      await listingApi.updateProduct(productId!, values);
      message.success('保存成功');
      onSuccess();
      onClose();
    } catch (e: any) {
      if (e.errorFields) return; // 表单验证错误
      message.error(e.message || '保存失败');
    } finally {
      setSaving(false);
    }
  };

  const handleSearchCategory = async (keyword: string) => {
    if (!keyword || !product?.shop?.platformId) return;
    setSearchingCat(true);
    try {
      // 这里需要根据店铺的平台ID搜索类目
      // 暂时使用简单的搜索
      const res: any = await platformCategoryApi.getCategories({
        keyword,
        pageSize: 20,
      });
      setCategories(res.data || []);
    } catch (e) {
      console.error(e);
    } finally {
      setSearchingCat(false);
    }
  };

  // AI 优化相关
  const loadAiConfig = async () => {
    try {
      const modelsRes: any = await aiModelApi.list();
      setAiModels(modelsRes.filter((m: any) => m.status === 'active'));
      const defaultModel = modelsRes.find((m: any) => m.isDefault && m.status === 'active');
      if (defaultModel) setSelectedAiModel(defaultModel.id);
    } catch (e) {
      console.error(e);
    }
  };

  const handleOpenAiOptimize = async () => {
    setOptimizeResults([]);
    await loadAiConfig();
    setAiOptimizeModal(true);
  };

  const handleAiOptimize = async () => {
    if (!productId || !selectedAiModel || selectedFields.length === 0) {
      message.warning('请选择模型和优化字段');
      return;
    }
    setOptimizing(true);
    try {
      const res: any = await aiOptimizeApi.optimize({
        productId,
        productType: 'listing',
        fields: selectedFields,
        modelId: selectedAiModel,
      });
      setOptimizeResults(res.results || []);
      message.success('优化完成');
    } catch (e: any) {
      message.error(e.message || '优化失败');
    } finally {
      setOptimizing(false);
    }
  };

  const handleApplyAiResult = async () => {
    if (optimizeResults.length === 0) return;
    try {
      const logIds = optimizeResults.map((r: any) => r.logId).filter(Boolean);
      if (logIds.length > 0) {
        await aiOptimizeApi.apply(logIds);
        message.success('已应用优化结果');
        setAiOptimizeModal(false);
        loadProduct(); // 重新加载商品数据
      }
    } catch (e: any) {
      message.error(e.message || '应用失败');
    }
  };

  return (
    <Modal
      title="编辑商品"
      open={visible}
      onCancel={onClose}
      width={900}
      footer={
        <Space>
          <Button onClick={onClose}>取消</Button>
          <Button icon={<RobotOutlined />} onClick={handleOpenAiOptimize}>
            AI 优化
          </Button>
          <Button type="primary" onClick={handleSave} loading={saving}>
            保存
          </Button>
        </Space>
      }
    >
      <Spin spinning={loading}>
        <Tabs
          items={[
            {
              key: 'basic',
              label: '基本信息',
              children: (
                <Form form={form} layout="vertical">
                  <Form.Item label="SKU">
                    <Input value={product?.sku} disabled />
                  </Form.Item>
                  <Form.Item name="title" label="标题" rules={[{ required: true, message: '请输入标题' }]}>
                    <Input maxLength={500} showCount />
                  </Form.Item>
                  <Form.Item name="description" label="描述">
                    <TextArea rows={4} maxLength={5000} showCount />
                  </Form.Item>
                  <Space>
                    <Form.Item name="price" label="价格" rules={[{ required: true, message: '请输入价格' }]}>
                      <InputNumber min={0} precision={2} style={{ width: 150 }} />
                    </Form.Item>
                    <Form.Item name="currency" label="货币">
                      <Select style={{ width: 100 }} options={[{ value: 'USD', label: 'USD' }]} />
                    </Form.Item>
                    <Form.Item name="stock" label="库存">
                      <InputNumber min={0} style={{ width: 150 }} />
                    </Form.Item>
                  </Space>
                </Form>
              ),
            },
            {
              key: 'images',
              label: '图片',
              children: (
                <div>
                  <h4>主图</h4>
                  {product?.mainImageUrl && (
                    <Image src={product.mainImageUrl} width={150} height={150} style={{ objectFit: 'cover' }} />
                  )}
                  <h4 style={{ marginTop: 16 }}>附图</h4>
                  <Image.PreviewGroup>
                    <Space wrap>
                      {product?.imageUrls?.map((url: string, i: number) => (
                        <Image key={i} src={url} width={100} height={100} style={{ objectFit: 'cover' }} />
                      ))}
                    </Space>
                  </Image.PreviewGroup>
                  {(!product?.imageUrls || product.imageUrls.length === 0) && (
                    <div style={{ color: '#999' }}>暂无附图</div>
                  )}
                </div>
              ),
            },
            {
              key: 'category',
              label: '平台类目',
              children: (
                <Form form={form} layout="vertical">
                  <Form.Item name="platformCategoryId" label="平台类目">
                    <Select
                      showSearch
                      placeholder="搜索类目"
                      filterOption={false}
                      onSearch={handleSearchCategory}
                      loading={searchingCat}
                      options={categories.map(c => ({
                        value: c.id,
                        label: `${c.name} (${c.categoryPath})`,
                      }))}
                      allowClear
                    />
                  </Form.Item>
                  <div style={{ color: '#666', fontSize: 12 }}>
                    提示：选择平台类目后，可以在"平台属性"标签页填写对应的属性值
                  </div>
                </Form>
              ),
            },
            {
              key: 'channelAttrs',
              label: '渠道属性',
              children: (
                <div>
                  {product?.channelAttributes ? (
                    <pre style={{ background: '#f5f5f5', padding: 12, borderRadius: 4, maxHeight: 400, overflow: 'auto' }}>
                      {JSON.stringify(product.channelAttributes, null, 2)}
                    </pre>
                  ) : (
                    <div style={{ color: '#999' }}>暂无渠道属性数据</div>
                  )}
                </div>
              ),
            },
            {
              key: 'platformAttrs',
              label: '平台属性',
              children: (
                <div>
                  {product?.platformAttributes ? (
                    <pre style={{ background: '#f5f5f5', padding: 12, borderRadius: 4, maxHeight: 400, overflow: 'auto' }}>
                      {JSON.stringify(product.platformAttributes, null, 2)}
                    </pre>
                  ) : (
                    <div style={{ color: '#999' }}>暂无平台属性数据，请先选择平台类目</div>
                  )}
                </div>
              ),
            },
          ]}
        />
      </Spin>

      {/* AI 优化弹窗 */}
      <Modal
        title="AI 优化"
        open={aiOptimizeModal}
        onCancel={() => { setAiOptimizeModal(false); setOptimizeResults([]); }}
        width={700}
        footer={
          <Space>
            <Button onClick={() => { setAiOptimizeModal(false); setOptimizeResults([]); }}>取消</Button>
            {optimizeResults.length > 0 ? (
              <Button type="primary" onClick={handleApplyAiResult}>应用优化结果</Button>
            ) : (
              <Button type="primary" onClick={handleAiOptimize} loading={optimizing} icon={<RobotOutlined />}>
                开始优化
              </Button>
            )}
          </Space>
        }
      >
        {optimizeResults.length === 0 ? (
          <div>
            <div style={{ marginBottom: 16 }}>
              <div style={{ marginBottom: 8, fontWeight: 500 }}>AI 模型</div>
              <Select
                placeholder="选择 AI 模型"
                style={{ width: '100%' }}
                value={selectedAiModel || undefined}
                onChange={setSelectedAiModel}
                options={aiModels.map(m => ({ value: m.id, label: `${m.name} (${m.modelName})` }))}
              />
            </div>
            <div style={{ marginBottom: 16 }}>
              <div style={{ marginBottom: 8, fontWeight: 500 }}>优化字段</div>
              <Select
                mode="multiple"
                placeholder="选择要优化的字段"
                style={{ width: '100%' }}
                value={selectedFields}
                onChange={setSelectedFields}
                options={[
                  { value: 'title', label: '标题' },
                  { value: 'description', label: '描述' },
                  { value: 'bulletPoints', label: '五点描述' },
                ]}
              />
            </div>
            <Alert
              message="优化说明"
              description="AI 将根据商品信息自动优化选中的字段，优化完成后可预览并选择是否应用。"
              type="info"
              showIcon
            />
          </div>
        ) : (
          <div>
            <Alert message="优化完成！请查看结果并决定是否应用。" type="success" showIcon style={{ marginBottom: 16 }} />
            {optimizeResults.map((result: any, index: number) => (
              <Card key={index} size="small" title={`${result.field === 'title' ? '标题' : result.field === 'description' ? '描述' : '五点描述'}`} style={{ marginBottom: 12 }}>
                <div style={{ marginBottom: 8 }}>
                  <div style={{ color: '#666', fontSize: 12 }}>原始内容：</div>
                  <div style={{ background: '#f5f5f5', padding: 8, borderRadius: 4, maxHeight: 100, overflow: 'auto' }}>
                    {Array.isArray(result.original) ? result.original.join('\n') : result.original || '(空)'}
                  </div>
                </div>
                <div>
                  <div style={{ color: '#1890ff', fontSize: 12 }}>优化结果：</div>
                  <div style={{ background: '#e6f7ff', padding: 8, borderRadius: 4, maxHeight: 150, overflow: 'auto' }}>
                    {Array.isArray(result.optimized) ? result.optimized.join('\n') : result.optimized || '(空)'}
                  </div>
                </div>
              </Card>
            ))}
          </div>
        )}
      </Modal>
    </Modal>
  );
}
