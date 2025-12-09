import { useEffect, useState } from 'react';
import { Table, Button, Space, Modal, Form, Input, Select, InputNumber, message, Popconfirm, Tag } from 'antd';
import { PlusOutlined, PlayCircleOutlined, PauseCircleOutlined } from '@ant-design/icons';
import { syncRuleApi, channelApi, shopApi } from '@/services/api';
import dayjs from 'dayjs';

export default function SyncRuleList() {
  const [data, setData] = useState<any[]>([]);
  const [channels, setChannels] = useState<any[]>([]);
  const [shops, setShops] = useState<any[]>([]);
  const [loading, setLoading] = useState(false);
  const [modalOpen, setModalOpen] = useState(false);
  const [editingId, setEditingId] = useState<string | null>(null);
  const [form] = Form.useForm();

  useEffect(() => { loadData(); loadOptions(); }, []);

  const loadData = async () => {
    setLoading(true);
    try {
      const res: any = await syncRuleApi.list();
      setData(res.data || []);
    } finally {
      setLoading(false);
    }
  };

  const loadOptions = async () => {
    const [ch, sh] = await Promise.all([
      channelApi.list({ pageSize: 100 }),
      shopApi.list({ pageSize: 100 }),
    ]);
    setChannels((ch as any).data || []);
    setShops((sh as any).data || []);
  };

  const handleSubmit = async () => {
    const values = await form.validateFields();
    if (editingId) {
      await syncRuleApi.update(editingId, values);
      message.success('更新成功');
    } else {
      await syncRuleApi.create(values);
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
    await syncRuleApi.delete(id);
    message.success('删除成功');
    loadData();
  };

  const handleExecute = async (id: string) => {
    await syncRuleApi.execute(id);
    message.success('已加入同步队列');
  };

  const handlePause = async (id: string) => {
    await syncRuleApi.pause(id);
    message.success('已暂停');
    loadData();
  };

  const handleResume = async (id: string) => {
    await syncRuleApi.resume(id);
    message.success('已恢复');
    loadData();
  };

  const columns = [
    { title: '规则名称', dataIndex: 'name', key: 'name' },
    { title: '渠道', dataIndex: ['channel', 'name'], key: 'channel' },
    { title: '店铺', dataIndex: ['shop', 'name'], key: 'shop' },
    { title: '同步类型', dataIndex: 'syncType', key: 'syncType' },
    { title: '间隔(天)', dataIndex: 'intervalDays', key: 'intervalDays' },
    { title: '价格规则', key: 'price', render: (_: any, r: any) => `×${r.priceMultiplier} ${r.priceAdjustment >= 0 ? '+' : ''}${r.priceAdjustment}` },
    { title: '库存规则', key: 'stock', render: (_: any, r: any) => `×${r.stockMultiplier} ${r.stockAdjustment >= 0 ? '+' : ''}${r.stockAdjustment}` },
    { title: '下次同步', dataIndex: 'nextSyncAt', key: 'nextSyncAt', render: (t: string) => t ? dayjs(t).format('MM-DD HH:mm') : '-' },
    { title: '状态', dataIndex: 'status', key: 'status', render: (s: string) => <Tag color={s === 'active' ? 'green' : s === 'paused' ? 'orange' : 'default'}>{s}</Tag> },
    { title: '操作', key: 'action', width: 200, render: (_: any, record: any) => (
      <Space>
        <Button type="link" size="small" icon={<PlayCircleOutlined />} onClick={() => handleExecute(record.id)}>执行</Button>
        {record.status === 'active' ? (
          <Button type="link" size="small" icon={<PauseCircleOutlined />} onClick={() => handlePause(record.id)}>暂停</Button>
        ) : (
          <Button type="link" size="small" onClick={() => handleResume(record.id)}>恢复</Button>
        )}
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
        <Button type="primary" icon={<PlusOutlined />} onClick={() => { setEditingId(null); form.resetFields(); setModalOpen(true); }}>新增规则</Button>
      </div>
      <Table dataSource={data} columns={columns} rowKey="id" loading={loading} scroll={{ x: 1200 }} />
      <Modal title={editingId ? '编辑规则' : '新增规则'} open={modalOpen} onOk={handleSubmit} onCancel={() => setModalOpen(false)} destroyOnClose width={600}>
        <Form form={form} layout="vertical" initialValues={{ syncType: 'incremental', intervalDays: 1, priceMultiplier: 1, priceAdjustment: 0, stockMultiplier: 1, stockAdjustment: 0 }}>
          <Form.Item name="name" label="规则名称" rules={[{ required: true }]}><Input /></Form.Item>
          <Form.Item name="channelId" label="数据来源渠道" rules={[{ required: true }]}>
            <Select options={channels.map(c => ({ value: c.id, label: c.name }))} placeholder="选择渠道" />
          </Form.Item>
          <Form.Item name="shopId" label="目标店铺" rules={[{ required: true }]}>
            <Select options={shops.map(s => ({ value: s.id, label: `${s.name} (${s.platform?.name || ''})` }))} placeholder="选择店铺" />
          </Form.Item>
          <Form.Item name="syncType" label="同步类型">
            <Select options={[{ value: 'full', label: '全量同步' }, { value: 'incremental', label: '增量同步' }]} />
          </Form.Item>
          <Form.Item name="intervalDays" label="同步间隔(天)">
            <Select options={[{ value: 1, label: '每天' }, { value: 2, label: '2天' }, { value: 3, label: '3天' }, { value: 5, label: '5天' }, { value: 7, label: '7天' }]} />
          </Form.Item>
          <Space style={{ width: '100%' }}>
            <Form.Item name="priceMultiplier" label="价格倍率" style={{ width: 140 }}><InputNumber step={0.1} min={0} /></Form.Item>
            <Form.Item name="priceAdjustment" label="价格增减" style={{ width: 140 }}><InputNumber step={1} /></Form.Item>
            <Form.Item name="stockMultiplier" label="库存倍率" style={{ width: 140 }}><InputNumber step={0.1} min={0} /></Form.Item>
            <Form.Item name="stockAdjustment" label="库存增减" style={{ width: 140 }}><InputNumber step={1} /></Form.Item>
          </Space>
        </Form>
      </Modal>
    </div>
  );
}
