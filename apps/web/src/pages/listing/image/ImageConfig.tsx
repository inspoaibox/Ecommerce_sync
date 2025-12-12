import { useState, useEffect } from 'react';
import { Card, Table, Button, Modal, Form, Input, InputNumber, Switch, Space, message, Popconfirm, Tag, Typography } from 'antd';
import { PlusOutlined, EditOutlined, DeleteOutlined, CheckCircleOutlined } from '@ant-design/icons';
import { imageApi } from '@/services/api';

const { Title } = Typography;

export default function ImageConfig() {
  const [configs, setConfigs] = useState<any[]>([]);
  const [loading, setLoading] = useState(false);
  const [modalVisible, setModalVisible] = useState(false);
  const [editingConfig, setEditingConfig] = useState<any>(null);
  const [form] = Form.useForm();

  useEffect(() => {
    loadConfigs();
  }, []);

  const loadConfigs = async () => {
    setLoading(true);
    try {
      const data: any = await imageApi.listConfigs();
      setConfigs(data || []);
    } catch (e: any) {
      message.error(e.message);
    } finally {
      setLoading(false);
    }
  };

  const handleAdd = () => {
    setEditingConfig(null);
    form.resetFields();
    form.setFieldsValue({
      name: '',
      maxSizeMB: 5,
      forceSquare: true,
      targetWidth: 2000,
      quality: 85,
      isDefault: false,
    });
    setModalVisible(true);
  };

  const handleEdit = (record: any) => {
    setEditingConfig(record);
    form.setFieldsValue({
      name: record.name,
      maxSizeMB: Number(record.maxSizeMB),
      forceSquare: record.forceSquare,
      targetWidth: record.targetWidth,
      quality: record.quality,
      isDefault: record.isDefault,
    });
    setModalVisible(true);
  };

  const handleSubmit = async () => {
    try {
      const values = await form.validateFields();
      await imageApi.saveConfig({
        id: editingConfig?.id,
        ...values,
      });
      message.success(editingConfig ? '更新成功' : '创建成功');
      setModalVisible(false);
      loadConfigs();
    } catch (e: any) {
      if (e.message) message.error(e.message);
    }
  };

  const handleDelete = async (id: string) => {
    try {
      await imageApi.deleteConfig(id);
      message.success('删除成功');
      loadConfigs();
    } catch (e: any) {
      message.error(e.message);
    }
  };

  const columns = [
    {
      title: '配置名称',
      dataIndex: 'name',
      render: (text: string, record: any) => (
        <Space>
          {text}
          {record.isDefault && <Tag color="blue">默认</Tag>}
        </Space>
      ),
    },
    {
      title: '最大文件大小',
      dataIndex: 'maxSizeMB',
      render: (v: number) => `${v} MB`,
    },
    {
      title: '强制1:1',
      dataIndex: 'forceSquare',
      render: (v: boolean) => v ? <Tag color="green">是</Tag> : <Tag>否</Tag>,
    },
    {
      title: '目标宽度',
      dataIndex: 'targetWidth',
      render: (v: number) => `${v} px`,
    },
    {
      title: '压缩质量',
      dataIndex: 'quality',
      render: (v: number) => `${v}%`,
    },
    {
      title: '操作',
      width: 180,
      render: (_: any, record: any) => (
        <Space>
          <Button type="link" size="small" icon={<EditOutlined />} onClick={() => handleEdit(record)}>
            编辑
          </Button>
          {!record.isDefault && (
            <Popconfirm title="确定删除？" onConfirm={() => handleDelete(record.id)}>
              <Button type="link" size="small" danger icon={<DeleteOutlined />}>
                删除
              </Button>
            </Popconfirm>
          )}
        </Space>
      ),
    },
  ];

  return (
    <div>
      <Title level={4}>图片处理参数设置</Title>
      <Card>
        <div style={{ marginBottom: 16 }}>
          <Button type="primary" icon={<PlusOutlined />} onClick={handleAdd}>
            添加配置
          </Button>
        </div>
        <Table
          columns={columns}
          dataSource={configs}
          rowKey="id"
          loading={loading}
          pagination={false}
        />
      </Card>

      <Modal
        title={editingConfig ? '编辑配置' : '添加配置'}
        open={modalVisible}
        onOk={handleSubmit}
        onCancel={() => setModalVisible(false)}
        width={500}
      >
        <Form form={form} layout="vertical">
          <Form.Item name="name" label="配置名称" rules={[{ required: true, message: '请输入配置名称' }]}>
            <Input placeholder="如：Walmart图片标准" />
          </Form.Item>
          <Form.Item name="maxSizeMB" label="最大文件大小 (MB)" rules={[{ required: true }]}>
            <InputNumber min={1} max={50} style={{ width: '100%' }} />
          </Form.Item>
          <Form.Item name="forceSquare" label="强制1:1比例" valuePropName="checked">
            <Switch checkedChildren="是" unCheckedChildren="否" />
          </Form.Item>
          <Form.Item name="targetWidth" label="目标宽度 (px)" rules={[{ required: true }]}>
            <InputNumber min={500} max={5000} style={{ width: '100%' }} />
          </Form.Item>
          <Form.Item name="quality" label="压缩质量 (%)" rules={[{ required: true }]}>
            <InputNumber min={50} max={100} style={{ width: '100%' }} />
          </Form.Item>
          <Form.Item name="isDefault" label="设为默认配置" valuePropName="checked">
            <Switch checkedChildren={<CheckCircleOutlined />} unCheckedChildren="否" />
          </Form.Item>
        </Form>
      </Modal>
    </div>
  );
}
