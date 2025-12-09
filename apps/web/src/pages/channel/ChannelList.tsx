import { useEffect, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { Table, Button, Space, Modal, Form, Input, Select, message, Popconfirm, Tag } from 'antd';
import { PlusOutlined, ApiOutlined, BugOutlined } from '@ant-design/icons';
import { channelApi } from '@/services/api';

// 预设渠道类型配置
const CHANNEL_PRESETS: Record<string, {
  name: string;
  code: string;
  apiBaseUrl: string;
  fields: { key: string; label: string; required?: boolean; placeholder?: string }[];
}> = {
  gigacloud: {
    name: '大健云仓',
    code: 'gigacloud',
    apiBaseUrl: 'https://openapi.gigacloudlogistics.com',
    fields: [
      { key: 'clientId', label: 'Client ID', required: true, placeholder: '请输入Client ID' },
      { key: 'clientSecret', label: 'Client Secret', required: true, placeholder: '请输入Client Secret' },
    ],
  },
  saleyee: {
    name: '赛盈云仓',
    code: 'saleyee',
    apiBaseUrl: 'https://api.saleyee.com',
    fields: [
      { key: 'token', label: 'Token', required: true, placeholder: '请输入Token' },
      { key: 'key', label: 'Key', required: true, placeholder: '请输入Key（用于DES加密）' },
    ],
  },
};

export default function ChannelList() {
  const navigate = useNavigate();
  const [data, setData] = useState<any[]>([]);
  const [loading, setLoading] = useState(false);
  const [modalOpen, setModalOpen] = useState(false);
  const [editingId, setEditingId] = useState<string | null>(null);
  const [selectedType, setSelectedType] = useState<string>('');
  const [form] = Form.useForm();

  useEffect(() => { loadData(); }, []);

  const loadData = async () => {
    setLoading(true);
    try {
      const res: any = await channelApi.list();
      setData(res.data || []);
    } finally {
      setLoading(false);
    }
  };

  const handleTypeChange = (type: string) => {
    setSelectedType(type);
    const preset = CHANNEL_PRESETS[type];
    if (preset && !editingId) {
      form.setFieldsValue({
        name: preset.name,
        code: preset.code,
        apiBaseUrl: preset.apiBaseUrl,
      });
    }
  };

  const handleSubmit = async () => {
    const values = await form.validateFields();
    const preset = CHANNEL_PRESETS[values.type];
    
    // 构建apiConfig
    const apiConfig: Record<string, any> = {
      apiBaseUrl: values.apiBaseUrl,
    };
    if (preset) {
      preset.fields.forEach(field => {
        if (values[field.key]) {
          apiConfig[field.key] = values[field.key];
        }
      });
    }

    const submitData = {
      name: values.name,
      code: values.code,
      type: values.type,
      apiConfig,
      status: values.status,
      description: values.description,
    };

    if (editingId) {
      await channelApi.update(editingId, submitData);
      message.success('更新成功');
    } else {
      await channelApi.create(submitData);
      message.success('创建成功');
    }
    setModalOpen(false);
    form.resetFields();
    setEditingId(null);
    setSelectedType('');
    loadData();
  };

  const handleEdit = (record: any) => {
    setEditingId(record.id);
    setSelectedType(record.type);
    
    const formValues: any = {
      name: record.name,
      code: record.code,
      type: record.type,
      status: record.status,
      description: record.description,
      apiBaseUrl: record.apiConfig?.apiBaseUrl || '',
    };
    
    // 填充API配置字段
    const preset = CHANNEL_PRESETS[record.type];
    if (preset && record.apiConfig) {
      preset.fields.forEach(field => {
        formValues[field.key] = record.apiConfig[field.key] || '';
      });
    }
    
    form.setFieldsValue(formValues);
    setModalOpen(true);
  };

  const handleDelete = async (id: string) => {
    await channelApi.delete(id);
    message.success('删除成功');
    loadData();
  };

  const handleTest = async (id: string) => {
    message.loading({ content: '测试连接中...', key: 'test' });
    try {
      const res: any = await channelApi.test(id);
      if (res.success) {
        message.success({ content: res.message || '连接成功', key: 'test' });
      } else {
        message.error({ content: res.message || '连接失败', key: 'test' });
      }
    } catch (e: any) {
      message.error({ content: e.message || '连接失败', key: 'test' });
    }
  };

  const handleOpenModal = () => {
    setEditingId(null);
    setSelectedType('');
    form.resetFields();
    form.setFieldsValue({ status: 'active' });
    setModalOpen(true);
  };

  const columns = [
    { title: '名称', dataIndex: 'name', key: 'name' },
    { title: '编码', dataIndex: 'code', key: 'code' },
    { title: '类型', dataIndex: 'type', key: 'type', render: (t: string) => CHANNEL_PRESETS[t]?.name || t },
    { title: '状态', dataIndex: 'status', key: 'status', render: (s: string) => <Tag color={s === 'active' ? 'green' : 'default'}>{s === 'active' ? '启用' : '禁用'}</Tag> },
    { title: '操作', key: 'action', width: 280, render: (_: any, record: any) => (
      <Space>
        <Button type="link" size="small" onClick={() => handleEdit(record)}>编辑</Button>
        <Popconfirm title="确定删除?" onConfirm={() => handleDelete(record.id)}>
          <Button type="link" size="small" danger>删除</Button>
        </Popconfirm>
        <Button type="link" size="small" icon={<ApiOutlined />} onClick={() => handleTest(record.id)}>测试</Button>
        <Button type="link" size="small" icon={<BugOutlined />} onClick={() => navigate(`/channels/${record.id}/test`)}>API调试</Button>
      </Space>
    )},
  ];

  const currentPreset = CHANNEL_PRESETS[selectedType];

  return (
    <div>
      <div style={{ marginBottom: 16 }}>
        <Button type="primary" icon={<PlusOutlined />} onClick={handleOpenModal}>新增渠道</Button>
      </div>
      <Table dataSource={data} columns={columns} rowKey="id" loading={loading} />
      
      <Modal 
        title={editingId ? '编辑渠道' : '新增渠道'} 
        open={modalOpen} 
        onOk={handleSubmit} 
        onCancel={() => setModalOpen(false)} 
        destroyOnClose
        width={500}
      >
        <Form form={form} layout="vertical">
          <Form.Item name="type" label="渠道类型" rules={[{ required: true, message: '请选择渠道类型' }]}>
            <Select
              placeholder="请选择渠道类型"
              onChange={handleTypeChange}
              options={[
                { value: 'gigacloud', label: '大健云仓 (GigaCloud)' },
                { value: 'saleyee', label: '赛盈云仓 (Saleyee)' },
              ]}
            />
          </Form.Item>
          
          <Form.Item name="name" label="渠道名称" rules={[{ required: true, message: '请输入渠道名称' }]}>
            <Input placeholder="如：大健云仓" />
          </Form.Item>
          
          <Form.Item name="code" label="渠道编码" rules={[{ required: true, message: '请输入渠道编码' }]}>
            <Input placeholder="如：gigacloud（唯一标识）" disabled={!!editingId} />
          </Form.Item>
          
          <Form.Item name="apiBaseUrl" label="API地址">
            <Input placeholder="API基础地址" />
          </Form.Item>

          {/* 动态渲染渠道特定的配置字段 */}
          {currentPreset?.fields.map(field => (
            <Form.Item 
              key={field.key} 
              name={field.key} 
              label={field.label}
              rules={field.required ? [{ required: true, message: `请输入${field.label}` }] : []}
            >
              <Input.Password 
                placeholder={field.placeholder} 
                visibilityToggle
              />
            </Form.Item>
          ))}

          <Form.Item name="description" label="描述">
            <Input.TextArea rows={2} placeholder="渠道描述（可选）" />
          </Form.Item>
          
          <Form.Item name="status" label="状态" initialValue="active">
            <Select options={[{ value: 'active', label: '启用' }, { value: 'inactive', label: '禁用' }]} />
          </Form.Item>
        </Form>
      </Modal>
    </div>
  );
}
