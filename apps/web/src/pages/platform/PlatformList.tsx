import { useEffect, useState } from 'react';
import { Table, Button, Space, Modal, Form, Input, Select, message, Popconfirm, Tag } from 'antd';
import { PlusOutlined } from '@ant-design/icons';
import { platformApi } from '@/services/api';

export default function PlatformList() {
  const [data, setData] = useState<any[]>([]);
  const [loading, setLoading] = useState(false);
  const [modalOpen, setModalOpen] = useState(false);
  const [editingId, setEditingId] = useState<string | null>(null);
  const [form] = Form.useForm();

  useEffect(() => { loadData(); }, []);

  const loadData = async () => {
    setLoading(true);
    try {
      const res: any = await platformApi.list();
      setData(res.data || []);
    } finally {
      setLoading(false);
    }
  };

  const handleSubmit = async () => {
    const values = await form.validateFields();
    if (editingId) {
      await platformApi.update(editingId, values);
      message.success('更新成功');
    } else {
      await platformApi.create(values);
      message.success('创建成功');
    }
    setModalOpen(false);
    form.resetFields();
    setEditingId(null);
    loadData();
  };

  const handleEdit = (record: any) => {
    setEditingId(record.id);
    form.setFieldsValue(record);
    setModalOpen(true);
  };

  const handleDelete = async (id: string) => {
    await platformApi.delete(id);
    message.success('删除成功');
    loadData();
  };

  const columns = [
    { title: '名称', dataIndex: 'name', key: 'name' },
    { title: '编码', dataIndex: 'code', key: 'code' },
    { title: 'API地址', dataIndex: 'apiBaseUrl', key: 'apiBaseUrl' },
    { title: '状态', dataIndex: 'status', key: 'status', render: (s: string) => <Tag color={s === 'active' ? 'green' : 'default'}>{s}</Tag> },
    { title: '操作', key: 'action', render: (_: any, record: any) => (
      <Space>
        <Button type="link" size="small" onClick={() => handleEdit(record)}>编辑</Button>
        <Popconfirm title="确定删除?" onConfirm={() => handleDelete(record.id)}>
          <Button type="link" size="small" danger>删除</Button>
        </Popconfirm>
      </Space>
    )},
  ];

  return (
    <div>
      <div style={{ marginBottom: 16 }}>
        <Button type="primary" icon={<PlusOutlined />} onClick={() => { setEditingId(null); form.resetFields(); setModalOpen(true); }}>新增平台</Button>
      </div>
      <Table dataSource={data} columns={columns} rowKey="id" loading={loading} />
      <Modal title={editingId ? '编辑平台' : '新增平台'} open={modalOpen} onOk={handleSubmit} onCancel={() => setModalOpen(false)} destroyOnClose>
        <Form form={form} layout="vertical">
          <Form.Item name="name" label="名称" rules={[{ required: true }]}><Input placeholder="如: Amazon, Walmart" /></Form.Item>
          <Form.Item name="code" label="编码" rules={[{ required: true }]}><Input placeholder="如: amazon, walmart, mock" /></Form.Item>
          <Form.Item name="apiBaseUrl" label="API基础地址"><Input placeholder="https://api.example.com" /></Form.Item>
          <Form.Item name="description" label="描述"><Input.TextArea rows={2} /></Form.Item>
          <Form.Item name="status" label="状态" initialValue="active">
            <Select options={[{ value: 'active', label: '启用' }, { value: 'inactive', label: '禁用' }]} />
          </Form.Item>
        </Form>
      </Modal>
    </div>
  );
}
