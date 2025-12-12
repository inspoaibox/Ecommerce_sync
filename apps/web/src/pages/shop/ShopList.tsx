import { useEffect, useState } from 'react';
import { useOutletContext, useNavigate } from 'react-router-dom';
import { Table, Button, Space, Modal, Form, Input, Select, message, Popconfirm, Tag } from 'antd';
import { PlusOutlined, ApiOutlined, SyncOutlined, SettingOutlined } from '@ant-design/icons';
import { shopApi } from '@/services/api';
import SyncConfigModal from '@/components/SyncConfigModal';

// 预设平台配置
const PLATFORM_PRESETS: Record<string, {
  name: string;
  code: string;
  apiBaseUrl: string;
  regions: { value: string; label: string }[];
  fields: { key: string; label: string; required?: boolean; placeholder?: string; type?: 'text' | 'password' | 'number' | 'select' }[];
}> = {
  walmart: {
    name: 'Walmart',
    code: 'walmart',
    apiBaseUrl: 'https://marketplace.walmartapis.com',
    regions: [
      { value: 'US', label: '美国 (US)' },
      { value: 'CA', label: '加拿大 (CA)' },
      { value: 'MX', label: '墨西哥 (MX)' },
    ],
    fields: [
      { key: 'clientId', label: 'Client ID', required: true, placeholder: '请输入Walmart Client ID' },
      { key: 'clientSecret', label: 'Client Secret', required: true, placeholder: '请输入Walmart Client Secret', type: 'password' },
      { key: 'channelType', label: 'Channel Type', required: false, placeholder: '从Seller Center获取的Consumer Channel Type（非美国市场必填）' },
      { key: 'accessToken', label: 'Access Token', required: false, placeholder: '首次授权后自动获取（可选）', type: 'password' },
      { key: 'refreshToken', label: 'Refresh Token', required: false, placeholder: '用于刷新Access Token（可选）', type: 'password' },
      { key: 'fulfillmentLagTime', label: '备货时间 (天)', required: false, placeholder: '收到订单后准备发货的天数，默认1天', type: 'number' },
      { key: 'fulfillmentMode', label: '发货模式', required: false, placeholder: '选择发货模式', type: 'select' },
      { key: 'fulfillmentCenterId', label: '履行中心ID', required: false, placeholder: '如：10001234567，留空使用默认值' },
      { key: 'shippingTemplate', label: '运输模板', required: false, placeholder: '运输模板名称，留空使用默认设置' },
    ],
  },
  amazon: {
    name: 'Amazon',
    code: 'amazon',
    apiBaseUrl: 'https://sellingpartnerapi.amazon.com',
    regions: [
      { value: 'US', label: '美国 (US)' },
      { value: 'CA', label: '加拿大 (CA)' },
      { value: 'MX', label: '墨西哥 (MX)' },
      { value: 'UK', label: '英国 (UK)' },
      { value: 'DE', label: '德国 (DE)' },
      { value: 'FR', label: '法国 (FR)' },
      { value: 'JP', label: '日本 (JP)' },
    ],
    fields: [
      { key: 'sellerId', label: 'Seller ID', required: true, placeholder: '请输入Amazon Seller ID' },
      { key: 'refreshToken', label: 'Refresh Token', required: true, placeholder: '请输入Refresh Token', type: 'password' },
      { key: 'clientId', label: 'LWA Client ID', required: true, placeholder: '请输入LWA Client ID' },
      { key: 'clientSecret', label: 'LWA Client Secret', required: true, placeholder: '请输入LWA Client Secret', type: 'password' },
    ],
  },
  ebay: {
    name: 'eBay',
    code: 'ebay',
    apiBaseUrl: 'https://api.ebay.com',
    regions: [
      { value: 'US', label: '美国 (US)' },
      { value: 'UK', label: '英国 (UK)' },
      { value: 'DE', label: '德国 (DE)' },
      { value: 'AU', label: '澳大利亚 (AU)' },
    ],
    fields: [
      { key: 'appId', label: 'App ID', required: true, placeholder: '请输入eBay App ID' },
      { key: 'certId', label: 'Cert ID', required: true, placeholder: '请输入eBay Cert ID', type: 'password' },
      { key: 'devId', label: 'Dev ID', required: true, placeholder: '请输入eBay Dev ID' },
      { key: 'refreshToken', label: 'Refresh Token', required: true, placeholder: '请输入Refresh Token', type: 'password' },
    ],
  },
  temu: {
    name: 'Temu',
    code: 'temu',
    apiBaseUrl: 'https://openapi.temupro.com',
    regions: [
      { value: 'US', label: '美国 (US)' },
      { value: 'EU', label: '欧洲 (EU)' },
    ],
    fields: [
      { key: 'appKey', label: 'App Key', required: true, placeholder: '请输入Temu App Key' },
      { key: 'appSecret', label: 'App Secret', required: true, placeholder: '请输入Temu App Secret', type: 'password' },
      { key: 'accessToken', label: 'Access Token', required: true, placeholder: '请输入Access Token', type: 'password' },
    ],
  },
  tiktok: {
    name: 'TikTok Shop',
    code: 'tiktok',
    apiBaseUrl: 'https://open-api.tiktokglobalshop.com',
    regions: [
      { value: 'US', label: '美国 (US)' },
      { value: 'UK', label: '英国 (UK)' },
      { value: 'ID', label: '印尼 (ID)' },
      { value: 'MY', label: '马来西亚 (MY)' },
      { value: 'TH', label: '泰国 (TH)' },
      { value: 'VN', label: '越南 (VN)' },
      { value: 'PH', label: '菲律宾 (PH)' },
      { value: 'SG', label: '新加坡 (SG)' },
    ],
    fields: [
      { key: 'appKey', label: 'App Key', required: true, placeholder: '请输入TikTok App Key' },
      { key: 'appSecret', label: 'App Secret', required: true, placeholder: '请输入TikTok App Secret', type: 'password' },
      { key: 'accessToken', label: 'Access Token', required: true, placeholder: '请输入Access Token', type: 'password' },
      { key: 'shopId', label: 'Shop ID', required: true, placeholder: '请输入Shop ID' },
    ],
  },
};

export default function ShopList() {
  const [data, setData] = useState<any[]>([]);
  const [loading, setLoading] = useState(false);
  const [modalOpen, setModalOpen] = useState(false);
  const [editingId, setEditingId] = useState<string | null>(null);
  const [selectedPlatform, setSelectedPlatform] = useState<string>('');
  const [syncConfigModal, setSyncConfigModal] = useState<{ open: boolean; shopId: string; shopName: string }>({
    open: false,
    shopId: '',
    shopName: '',
  });
  const [form] = Form.useForm();
  const { refreshShops } = useOutletContext<{ refreshShops: () => void }>();
  const navigate = useNavigate();

  useEffect(() => { loadData(); }, []);

  const loadData = async () => {
    setLoading(true);
    try {
      const res: any = await shopApi.list();
      setData(res.data || []);
    } finally {
      setLoading(false);
    }
  };

  const handlePlatformChange = (platform: string) => {
    setSelectedPlatform(platform);
    const preset = PLATFORM_PRESETS[platform];
    if (preset && !editingId) {
      form.setFieldsValue({
        apiBaseUrl: preset.apiBaseUrl,
        region: undefined, // 重置区域选择
      });
    }
  };

  // 区域变化时的处理（Walmart 所有市场使用同一 API 地址，通过 WM_MARKET Header 区分）
  const handleRegionChange = (_region: string) => {
    // Walmart 所有市场使用统一的 API 地址：https://marketplace.walmartapis.com
    // 区分市场通过请求头 WM_MARKET 实现，无需修改 API 地址
  };

  const handleSubmit = async () => {
    const values = await form.validateFields();
    const preset = PLATFORM_PRESETS[values.platformCode];
    
    // 构建apiCredentials
    const apiCredentials: Record<string, any> = {
      apiBaseUrl: values.apiBaseUrl,
    };
    if (preset) {
      preset.fields.forEach(field => {
        if (values[field.key]) {
          apiCredentials[field.key] = values[field.key];
        }
      });
    }

    const submitData = {
      name: values.name,
      code: values.code,
      platformCode: values.platformCode,
      region: values.region,
      apiCredentials,
      status: values.status,
      description: values.description,
    };

    if (editingId) {
      await shopApi.update(editingId, submitData);
      message.success('更新成功');
    } else {
      await shopApi.create(submitData);
      message.success('创建成功');
    }
    setModalOpen(false);
    form.resetFields();
    setEditingId(null);
    setSelectedPlatform('');
    loadData();
    refreshShops();
  };

  const handleEdit = (record: any) => {
    setEditingId(record.id);
    const platformCode = record.platform?.code || record.platformCode;
    setSelectedPlatform(platformCode);
    
    const formValues: any = {
      name: record.name,
      code: record.code,
      platformCode,
      region: record.region,
      status: record.status,
      description: record.description,
      apiBaseUrl: record.apiCredentials?.apiBaseUrl || '',
    };
    
    // 填充API凭证字段
    const preset = PLATFORM_PRESETS[platformCode];
    if (preset && record.apiCredentials) {
      preset.fields.forEach(field => {
        formValues[field.key] = record.apiCredentials[field.key] || '';
      });
    }
    
    form.setFieldsValue(formValues);
    setModalOpen(true);
  };

  const handleDelete = async (id: string) => {
    await shopApi.delete(id);
    message.success('删除成功');
    loadData();
    refreshShops();
  };

  const handleTest = async (id: string) => {
    message.loading({ content: '测试连接中...', key: 'test' });
    try {
      const res: any = await shopApi.test(id);
      if (res.success) {
        message.success({ content: res.message || '连接成功', key: 'test' });
      } else {
        message.error({ content: res.message || '连接失败', key: 'test' });
      }
    } catch (e: any) {
      message.error({ content: e.message || '连接失败', key: 'test' });
    }
  };

  const handleSyncProducts = async (id: string) => {
    try {
      const res: any = await shopApi.syncProducts(id);
      if (res.success) {
        message.success('同步任务已创建');
        // 跳转到同步记录页面
        navigate('/shops/sync-tasks');
      } else {
        message.error(res.message || '启动同步失败');
      }
    } catch (e: any) {
      message.error(e.message || '启动同步失败');
    }
  };

  const handleOpenModal = () => {
    setEditingId(null);
    setSelectedPlatform('');
    form.resetFields();
    form.setFieldsValue({ status: 'active' });
    setModalOpen(true);
  };

  const columns = [
    { title: '店铺名称', dataIndex: 'name', key: 'name' },
    { title: '店铺编码', dataIndex: 'code', key: 'code' },
    { 
      title: '平台', 
      key: 'platform', 
      render: (_: any, r: any) => {
        const code = r.platform?.code || r.platformCode;
        return PLATFORM_PRESETS[code]?.name || code;
      }
    },
    { title: '区域', dataIndex: 'region', key: 'region' },
    { title: '状态', dataIndex: 'status', key: 'status', render: (s: string) => <Tag color={s === 'active' ? 'green' : 'default'}>{s === 'active' ? '启用' : '禁用'}</Tag> },
    { title: '操作', key: 'action', width: 350, render: (_: any, record: any) => (
      <Space>
        <Button type="link" size="small" onClick={() => handleEdit(record)}>编辑</Button>
        <Popconfirm title="确定删除?" onConfirm={() => handleDelete(record.id)}>
          <Button type="link" size="small" danger>删除</Button>
        </Popconfirm>
        <Button type="link" size="small" icon={<ApiOutlined />} onClick={() => handleTest(record.id)}>测试</Button>
        <Button type="link" size="small" icon={<SettingOutlined />} onClick={() => setSyncConfigModal({ open: true, shopId: record.id, shopName: record.name })}>同步规则</Button>
        <Popconfirm title="确定从平台同步商品到本地？" onConfirm={() => handleSyncProducts(record.id)}>
          <Button type="link" size="small" icon={<SyncOutlined />}>同步商品</Button>
        </Popconfirm>
      </Space>
    )},
  ];

  const currentPreset = PLATFORM_PRESETS[selectedPlatform];

  return (
    <div>
      <div style={{ marginBottom: 16 }}>
        <Button type="primary" icon={<PlusOutlined />} onClick={handleOpenModal}>新增店铺</Button>
      </div>
      <Table dataSource={data} columns={columns} rowKey="id" loading={loading} />
      
      <Modal 
        title={editingId ? '编辑店铺' : '新增店铺'} 
        open={modalOpen} 
        onOk={handleSubmit} 
        onCancel={() => setModalOpen(false)} 
        destroyOnClose
        width={550}
      >
        <Form form={form} layout="vertical">
          <Form.Item name="platformCode" label="平台" rules={[{ required: true, message: '请选择平台' }]}>
            <Select 
              placeholder="请选择平台"
              onChange={handlePlatformChange}
              options={Object.entries(PLATFORM_PRESETS).map(([key, preset]) => ({
                value: key,
                label: preset.name,
              }))} 
            />
          </Form.Item>
          
          <Form.Item name="name" label="店铺名称" rules={[{ required: true, message: '请输入店铺名称' }]}>
            <Input placeholder="如：美国1号店" />
          </Form.Item>
          
          <Form.Item name="code" label="店铺编码" rules={[{ required: true, message: '请输入店铺编码' }]}>
            <Input placeholder="唯一标识，如：walmart-us-1" disabled={!!editingId} />
          </Form.Item>

          {currentPreset && (
            <Form.Item name="region" label="区域" rules={[{ required: true, message: '请选择区域' }]}>
              <Select 
                placeholder="请选择区域"
                options={currentPreset.regions}
                onChange={handleRegionChange}
              />
            </Form.Item>
          )}
          
          <Form.Item name="apiBaseUrl" label="API地址">
            <Input placeholder="API基础地址（可选，使用默认值）" />
          </Form.Item>

          {/* 动态渲染平台特定的凭证字段 */}
          {currentPreset?.fields.map(field => (
            <Form.Item 
              key={field.key} 
              name={field.key} 
              label={field.label}
              rules={field.required ? [{ required: true, message: `请输入${field.label}` }] : []}
            >
              {field.type === 'password' ? (
                <Input.Password placeholder={field.placeholder} visibilityToggle />
              ) : field.type === 'number' ? (
                <Input type="number" placeholder={field.placeholder} min={0} max={30} />
              ) : field.type === 'select' && field.key === 'fulfillmentMode' ? (
                <Select placeholder={field.placeholder} allowClear>
                  <Select.Option value="SELLER_FULFILLED">卖家自发货 (Seller Fulfilled)</Select.Option>
                  <Select.Option value="WFS">沃尔玛发货 (WFS)</Select.Option>
                </Select>
              ) : (
                <Input placeholder={field.placeholder} />
              )}
            </Form.Item>
          ))}

          <Form.Item name="description" label="描述">
            <Input.TextArea rows={2} placeholder="店铺描述（可选）" />
          </Form.Item>
          
          <Form.Item name="status" label="状态" initialValue="active">
            <Select options={[{ value: 'active', label: '启用' }, { value: 'inactive', label: '禁用' }]} />
          </Form.Item>
        </Form>
      </Modal>

      {/* 同步规则配置弹窗 */}
      <SyncConfigModal
        open={syncConfigModal.open}
        shopId={syncConfigModal.shopId}
        shopName={syncConfigModal.shopName}
        onClose={() => setSyncConfigModal({ open: false, shopId: '', shopName: '' })}
      />
    </div>
  );
}
