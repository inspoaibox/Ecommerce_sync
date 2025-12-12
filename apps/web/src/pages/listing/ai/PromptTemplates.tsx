import { useState, useEffect } from 'react';
import { Table, Button, Modal, Form, Input, Select, Space, Tag, message, Popconfirm, Card, Typography, Tabs, Alert } from 'antd';
import { PlusOutlined, EditOutlined, DeleteOutlined, CopyOutlined, CheckCircleOutlined, EyeOutlined } from '@ant-design/icons';
import { promptTemplateApi } from '@/services/api';

const { Title } = Typography;
const { TextArea } = Input;

const TEMPLATE_TYPES = [
  { value: 'title', label: '标题优化', color: 'blue' },
  { value: 'description', label: '描述优化', color: 'green' },
  { value: 'bullet_points', label: '五点描述', color: 'orange' },
  { value: 'keywords', label: '关键词提取', color: 'purple' },
  { value: 'general', label: '通用', color: 'default' },
];

// 可用变量
const VARIABLES = [
  // 汇总信息（推荐使用，AI可获取完整商品信息）
  { name: 'productSummary', description: '★ 商品完整信息汇总（推荐）' },
  { name: 'allCustomAttributes', description: '★ 所有渠道属性汇总' },
  // 基础信息
  { name: 'title', description: '商品标题' },
  { name: 'sku', description: 'SKU' },
  { name: 'color', description: '商品颜色' },
  { name: 'material', description: '商品材质' },
  { name: 'description', description: '商品描述' },
  { name: 'bulletPoints', description: '五点描述' },
  { name: 'keywords', description: '搜索关键词' },
  // 尺寸
  { name: 'productDimensions', description: '产品尺寸（长x宽x高, 重量）' },
  { name: 'packageDimensions', description: '包装尺寸（长x宽x高, 重量）' },
];

export default function PromptTemplates() {
  const [templates, setTemplates] = useState<any[]>([]);
  const [loading, setLoading] = useState(false);
  const [modalVisible, setModalVisible] = useState(false);
  const [previewVisible, setPreviewVisible] = useState(false);
  const [editingTemplate, setEditingTemplate] = useState<any>(null);
  const [previewContent, setPreviewContent] = useState('');
  const [activeType, setActiveType] = useState<string | undefined>(undefined);
  const [form] = Form.useForm();

  useEffect(() => {
    loadTemplates();
  }, [activeType]);

  const loadTemplates = async () => {
    setLoading(true);
    try {
      const data: any = await promptTemplateApi.list(activeType);
      setTemplates(data);
    } catch (e: any) {
      message.error(e.message);
    } finally {
      setLoading(false);
    }
  };

  const handleAdd = () => {
    setEditingTemplate(null);
    form.resetFields();
    if (activeType) form.setFieldsValue({ type: activeType });
    setModalVisible(true);
  };

  const handleEdit = (record: any) => {
    setEditingTemplate(record);
    form.setFieldsValue(record);
    setModalVisible(true);
  };

  const handleSubmit = async () => {
    try {
      const values = await form.validateFields();
      if (editingTemplate) {
        await promptTemplateApi.update(editingTemplate.id, values);
        message.success('更新成功');
      } else {
        await promptTemplateApi.create(values);
        message.success('创建成功');
      }
      setModalVisible(false);
      loadTemplates();
    } catch (e: any) {
      if (e.message) message.error(e.message);
    }
  };

  const handleDelete = async (id: string) => {
    try {
      await promptTemplateApi.delete(id);
      message.success('删除成功');
      loadTemplates();
    } catch (e: any) {
      message.error(e.message);
    }
  };

  const handleDuplicate = async (id: string) => {
    try {
      await promptTemplateApi.duplicate(id);
      message.success('复制成功');
      loadTemplates();
    } catch (e: any) {
      message.error(e.message);
    }
  };

  const handleSetDefault = async (id: string) => {
    try {
      await promptTemplateApi.setDefault(id);
      message.success('设置成功');
      loadTemplates();
    } catch (e: any) {
      message.error(e.message);
    }
  };

  const handlePreview = (record: any) => {
    setPreviewContent(record.content);
    setPreviewVisible(true);
  };

  const insertVariable = (varName: string) => {
    const content = form.getFieldValue('content') || '';
    form.setFieldsValue({ content: content + `{{${varName}}}` });
  };

  const columns = [
    {
      title: '名称',
      dataIndex: 'name',
      key: 'name',
      render: (text: string, record: any) => (
        <Space>
          {text}
          {record.isDefault && <Tag color="blue">默认</Tag>}
          {record.isSystem && <Tag color="green">系统</Tag>}
        </Space>
      ),
    },
    {
      title: '类型',
      dataIndex: 'type',
      key: 'type',
      render: (type: string) => {
        const t = TEMPLATE_TYPES.find(m => m.value === type);
        return <Tag color={t?.color}>{t?.label || type}</Tag>;
      },
    },
    {
      title: '描述',
      dataIndex: 'description',
      key: 'description',
      ellipsis: true,
    },
    {
      title: '状态',
      dataIndex: 'status',
      key: 'status',
      render: (status: string) => (
        <Tag color={status === 'active' ? 'green' : 'default'}>
          {status === 'active' ? '启用' : '禁用'}
        </Tag>
      ),
    },
    {
      title: '操作',
      key: 'action',
      width: 280,
      render: (_: any, record: any) => (
        <Space size={0} wrap>
          <Button type="link" size="small" icon={<EyeOutlined />} onClick={() => handlePreview(record)}>
            预览
          </Button>
          <Button type="link" size="small" icon={<CopyOutlined />} onClick={() => handleDuplicate(record.id)}>
            复制
          </Button>
          {!record.isDefault && (
            <Button type="link" size="small" icon={<CheckCircleOutlined />} onClick={() => handleSetDefault(record.id)}>
              默认
            </Button>
          )}
          <Button type="link" size="small" icon={<EditOutlined />} onClick={() => handleEdit(record)}>
            编辑
          </Button>
          <Popconfirm title="确定删除？" onConfirm={() => handleDelete(record.id)}>
            <Button type="link" size="small" danger icon={<DeleteOutlined />}>
              删除
            </Button>
          </Popconfirm>
        </Space>
      ),
    },
  ];

  return (
    <div>
      <Title level={4}>Prompt 模板管理</Title>
      <Card>
        <Tabs
          activeKey={activeType || 'all'}
          onChange={(key) => setActiveType(key === 'all' ? undefined : key)}
          items={[
            { key: 'all', label: '全部' },
            ...TEMPLATE_TYPES.map(t => ({ key: t.value, label: t.label })),
          ]}
        />
        <div style={{ marginBottom: 16 }}>
          <Button type="primary" icon={<PlusOutlined />} onClick={handleAdd}>
            添加模板
          </Button>
        </div>
        <Table columns={columns} dataSource={templates} rowKey="id" loading={loading} pagination={false} scroll={{ x: 900 }} />
      </Card>

      <Modal
        title={editingTemplate ? '编辑模板' : '添加模板'}
        open={modalVisible}
        onOk={handleSubmit}
        onCancel={() => setModalVisible(false)}
        width={800}
      >
        <Form form={form} layout="vertical">
          <Form.Item name="name" label="名称" rules={[{ required: true, message: '请输入名称' }]}>
            <Input placeholder="如：标题优化 - 家具类" />
          </Form.Item>
          <Form.Item name="type" label="类型" rules={[{ required: true, message: '请选择类型' }]}>
            <Select placeholder="选择模板类型" disabled={!!editingTemplate}>
              {TEMPLATE_TYPES.map(t => (
                <Select.Option key={t.value} value={t.value}>{t.label}</Select.Option>
              ))}
            </Select>
          </Form.Item>
          <Form.Item name="description" label="描述">
            <Input placeholder="模板用途说明" />
          </Form.Item>
          {editingTemplate && (
            <Form.Item name="status" label="状态">
              <Select>
                <Select.Option value="active">启用</Select.Option>
                <Select.Option value="inactive">禁用</Select.Option>
              </Select>
            </Form.Item>
          )}
          <Form.Item label="可用变量">
            <Space wrap>
              {VARIABLES.map(v => (
                <Tag
                  key={v.name}
                  style={{ cursor: 'pointer' }}
                  onClick={() => insertVariable(v.name)}
                >
                  {`{{${v.name}}}`} - {v.description}
                </Tag>
              ))}
            </Space>
          </Form.Item>
          <Form.Item name="content" label="模板内容" rules={[{ required: true, message: '请输入模板内容' }]}>
            <TextArea rows={15} placeholder="输入 Prompt 模板内容（建议使用英文），使用 {{变量名}} 插入变量" />
          </Form.Item>
        </Form>
      </Modal>

      <Modal
        title="模板预览"
        open={previewVisible}
        onCancel={() => setPreviewVisible(false)}
        footer={null}
        width={700}
      >
        <Alert
          message="变量说明"
          description="以下内容中的 {{变量名}} 会在实际使用时被替换为商品对应的数据"
          type="info"
          showIcon
          style={{ marginBottom: 16 }}
        />
        <pre style={{ background: '#f5f5f5', padding: 16, borderRadius: 4, whiteSpace: 'pre-wrap', maxHeight: 500, overflow: 'auto' }}>
          {previewContent}
        </pre>
      </Modal>
    </div>
  );
}
