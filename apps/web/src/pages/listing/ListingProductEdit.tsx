import { useState, useEffect } from 'react';
import { Modal, Form, Input, InputNumber, Select, Tabs, Space, Button, message, Image, Spin } from 'antd';
import { listingApi, platformCategoryApi } from '@/services/api';

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

  return (
    <Modal
      title="编辑商品"
      open={visible}
      onCancel={onClose}
      width={900}
      footer={
        <Space>
          <Button onClick={onClose}>取消</Button>
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
    </Modal>
  );
}
