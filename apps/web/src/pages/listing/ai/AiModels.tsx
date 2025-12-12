import { useState, useEffect } from 'react';
import {
  Table,
  Button,
  Modal,
  Form,
  Input,
  Select,
  InputNumber,
  Switch,
  Space,
  Tag,
  message,
  Popconfirm,
  Card,
  Typography,
  Alert,
} from 'antd';
import { PlusOutlined, EditOutlined, DeleteOutlined, ApiOutlined, SettingOutlined } from '@ant-design/icons';
import { aiModelApi } from '@/services/api';

const { Title, Text } = Typography;

const MODEL_TYPES = [
  { value: 'openai', label: 'OpenAI', description: '支持 GPT-4、GPT-3.5-turbo 等' },
  { value: 'gemini', label: 'Google Gemini', description: '支持 gemini-pro、gemini-1.5-pro 等' },
  { value: 'openai_compatible', label: 'OpenAI 兼容接口', description: '第三方中转服务，需要自定义 Base URL' },
];

interface ModelInfo {
  id: string;
  name: string;
  maxTokens?: number;
}

export default function AiModels() {
  const [channels, setChannels] = useState<any[]>([]);
  const [allModels, setAllModels] = useState<any[]>([]);
  const [loading, setLoading] = useState(false);
  const [modalVisible, setModalVisible] = useState(false);
  const [editingChannel, setEditingChannel] = useState<any>(null);
  const [testingId, setTestingId] = useState<string | null>(null);
  const [form] = Form.useForm();
  const [defaultModelId, setDefaultModelId] = useState<string>('');
  
  const [fetchedModels, setFetchedModels] = useState<ModelInfo[]>([]);
  const [fetchingModels, setFetchingModels] = useState(false);

  useEffect(() => {
    loadChannels();
    loadAllModels();
  }, []);

  const loadChannels = async () => {
    setLoading(true);
    try {
      const data: any = await aiModelApi.list();
      setChannels(data);
    } catch (e: any) {
      message.error(e.message);
    } finally {
      setLoading(false);
    }
  };

  const loadAllModels = async () => {
    try {
      const data: any = await aiModelApi.getAllModels();
      setAllModels(data);
      // 找到默认的
      const defaultChannel = channels.find((c: any) => c.isDefault);
      if (defaultChannel?.defaultModel) {
        const key = `${defaultChannel.id}|${defaultChannel.defaultModel}`;
        setDefaultModelId(key);
      }
    } catch (e: any) {
      console.error('加载模型列表失败:', e.message);
    }
  };

  const handleAdd = () => {
    setEditingChannel(null);
    setFetchedModels([]);
    form.resetFields();
    form.setFieldsValue({ maxTokens: 4096, temperature: 0.7 });
    setModalVisible(true);
  };

  const handleEdit = (record: any) => {
    setEditingChannel(record);
    setFetchedModels(record.modelList || []);
    form.setFieldsValue(record);
    setModalVisible(true);
  };

  const handleSubmit = async () => {
    try {
      const values = await form.validateFields();
      
      if (fetchedModels.length === 0) {
        message.warning('请先获取模型列表');
        return;
      }

      const submitData = {
        ...values,
        modelList: fetchedModels,
      };

      if (editingChannel) {
        await aiModelApi.update(editingChannel.id, submitData);
        message.success('更新成功');
      } else {
        await aiModelApi.create(submitData);
        message.success('创建成功');
      }
      
      setModalVisible(false);
      loadChannels();
      loadAllModels();
    } catch (e: any) {
      if (e.message) message.error(e.message);
    }
  };

  const handleDelete = async (id: string) => {
    try {
      await aiModelApi.delete(id);
      message.success('删除成功');
      loadChannels();
      loadAllModels();
    } catch (e: any) {
      message.error(e.message);
    }
  };

  const handleTest = async (id: string) => {
    setTestingId(id);
    try {
      const result: any = await aiModelApi.test(id);
      if (result.success) {
        message.success('连接成功');
      } else {
        message.error(result.message || '连接失败');
      }
    } catch (e: any) {
      message.error(e.message);
    } finally {
      setTestingId(null);
    }
  };

  const handleSetDefault = async () => {
    if (!defaultModelId) {
      message.warning('请选择一个模型');
      return;
    }
    const [channelId, modelName] = defaultModelId.split('|');
    try {
      await aiModelApi.setDefault(channelId, modelName);
      message.success('默认模型设置成功');
      loadChannels();
    } catch (e: any) {
      message.error(e.message);
    }
  };

  const handleToggleStatus = async (record: any) => {
    try {
      await aiModelApi.update(record.id, {
        status: record.status === 'active' ? 'inactive' : 'active',
      });
      message.success('状态更新成功');
      loadChannels();
    } catch (e: any) {
      message.error(e.message);
    }
  };

  const fetchAvailableModels = async () => {
    const type = form.getFieldValue('type');
    const apiKey = form.getFieldValue('apiKey');
    const baseUrl = form.getFieldValue('baseUrl');

    if (!type) {
      message.warning('请先选择模型类型');
      return;
    }
    if (!apiKey) {
      message.warning('请先输入 API Key');
      return;
    }
    if (type === 'openai_compatible' && !baseUrl) {
      message.warning('请先输入 Base URL');
      return;
    }

    setFetchingModels(true);
    try {
      const result: any = await aiModelApi.fetchModels({ type, apiKey, baseUrl });
      if (result && result.length > 0) {
        setFetchedModels(result);
        message.success(`获取到 ${result.length} 个可用模型`);
      } else {
        message.warning('未获取到可用模型');
      }
    } catch (e: any) {
      message.error(e.message || '获取模型列表失败');
    } finally {
      setFetchingModels(false);
    }
  };

  const removeModel = (modelId: string) => {
    setFetchedModels(fetchedModels.filter(m => m.id !== modelId));
  };

  const columns = [
    {
      title: '渠道名称',
      dataIndex: 'name',
      key: 'name',
      render: (text: string, record: any) => (
        <Space>
          {text}
          {record.isDefault && <Tag color="blue">默认</Tag>}
        </Space>
      ),
    },
    {
      title: '类型',
      dataIndex: 'type',
      key: 'type',
      render: (type: string) => {
        const t = MODEL_TYPES.find((m) => m.value === type);
        return <Tag>{t?.label || type}</Tag>;
      },
    },
    {
      title: '模型列表',
      dataIndex: 'modelList',
      key: 'modelList',
      render: (modelNames: ModelInfo[]) => (
        <div style={{ display: 'flex', flexWrap: 'wrap', gap: 4, maxWidth: 400 }}>
          {(modelNames || []).slice(0, 5).map((m) => (
            <Tag key={m.id}>{m.name}</Tag>
          ))}
          {(modelNames || []).length > 5 && <Tag>+{modelNames.length - 5}</Tag>}
        </div>
      ),
    },
    {
      title: '状态',
      dataIndex: 'status',
      key: 'status',
      render: (status: string, record: any) => (
        <Switch
          checked={status === 'active'}
          onChange={() => handleToggleStatus(record)}
          checkedChildren="启用"
          unCheckedChildren="禁用"
        />
      ),
    },
    {
      title: '操作',
      key: 'action',
      render: (_: any, record: any) => (
        <Space>
          <Button type="link" size="small" icon={<ApiOutlined />} loading={testingId === record.id} onClick={() => handleTest(record.id)}>
            测试
          </Button>
          <Button type="link" size="small" icon={<EditOutlined />} onClick={() => handleEdit(record)}>
            编辑
          </Button>
          <Popconfirm title="确定删除？" onConfirm={() => handleDelete(record.id)}>
            <Button type="link" size="small" danger icon={<DeleteOutlined />}>删除</Button>
          </Popconfirm>
        </Space>
      ),
    },
  ];

  const selectedType = Form.useWatch('type', form);


  return (
    <div>
      <Title level={4}>AI 模型配置</Title>

      <Card>
        <div style={{ marginBottom: 16 }}>
          <Button type="primary" icon={<PlusOutlined />} onClick={handleAdd}>添加渠道</Button>
        </div>
        <Table columns={columns} dataSource={channels} rowKey="id" loading={loading} pagination={false} />
      </Card>

      <Card style={{ marginTop: 16 }}>
        <div style={{ display: 'flex', alignItems: 'center', gap: 16 }}>
          <SettingOutlined style={{ fontSize: 20, color: '#1890ff' }} />
          <div style={{ flex: 1 }}>
            <Text strong>默认 AI 模型</Text>
            <div style={{ color: '#666', fontSize: 12 }}>所有 AI 优化功能将使用此模型</div>
          </div>
          <Select
            style={{ width: 400 }}
            placeholder="选择默认模型"
            value={defaultModelId || undefined}
            onChange={(v) => setDefaultModelId(v)}
            showSearch
            filterOption={(input, option) =>
              (option?.label as string)?.toLowerCase().includes(input.toLowerCase())
            }
            options={allModels.map((m) => ({
              value: `${m.channelId}|${m.modelId}`,
              label: `${m.channelName} - ${m.modelName}`,
            }))}
          />
          <Button type="primary" onClick={handleSetDefault}>保存设置</Button>
        </div>
        {channels.filter(c => c.status === 'active').length === 0 && (
          <Alert message="请先添加并启用至少一个 AI 渠道" type="warning" showIcon style={{ marginTop: 16 }} />
        )}
      </Card>

      <Modal
        title={editingChannel ? '编辑渠道' : '添加渠道'}
        open={modalVisible}
        onOk={handleSubmit}
        onCancel={() => setModalVisible(false)}
        width={700}
        okText="保存"
        okButtonProps={{ disabled: fetchedModels.length === 0 }}
      >
        <Form form={form} layout="vertical">
          <Form.Item name="name" label="渠道名称" rules={[{ required: true, message: '请输入名称' }]}>
            <Input placeholder="如：中转接口、官方API" />
          </Form.Item>

          <Form.Item name="type" label="模型类型" rules={[{ required: true, message: '请选择类型' }]}>
            <Select placeholder="选择模型类型" disabled={!!editingChannel}>
              {MODEL_TYPES.map((t) => (
                <Select.Option key={t.value} value={t.value}>{t.label} - {t.description}</Select.Option>
              ))}
            </Select>
          </Form.Item>

          <Form.Item name="apiKey" label="API Key" rules={[{ required: true, message: '请输入 API Key' }]}>
            <Input.Password placeholder="输入 API Key" visibilityToggle />
          </Form.Item>

          {selectedType === 'openai_compatible' && (
            <Form.Item name="baseUrl" label="Base URL" rules={[{ required: true, message: '请输入 Base URL' }]}>
              <Input placeholder="如：https://api.example.com/v1" />
            </Form.Item>
          )}

          <Form.Item label="模型列表">
            <Button type="primary" onClick={fetchAvailableModels} loading={fetchingModels}>
              获取模型列表
            </Button>
          </Form.Item>

          {fetchedModels.length > 0 && (
            <div style={{ border: '1px solid #d9d9d9', borderRadius: 6, padding: 12, maxHeight: 300, overflow: 'auto', marginBottom: 16 }}>
              <div style={{ marginBottom: 8, color: '#666' }}>
                共 {fetchedModels.length} 个模型，点击 X 删除不需要的
              </div>
              <div style={{ display: 'flex', flexWrap: 'wrap', gap: 8 }}>
                {fetchedModels.map((item) => (
                  <Tag key={item.id} closable onClose={() => removeModel(item.id)} style={{ margin: 0 }}>
                    {item.name}
                  </Tag>
                ))}
              </div>
            </div>
          )}

          <Form.Item name="temperature" label="温度 (0-2)">
            <InputNumber min={0} max={2} step={0.1} style={{ width: '100%' }} />
          </Form.Item>
        </Form>
      </Modal>
    </div>
  );
}
