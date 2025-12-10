import { useEffect, useState } from 'react';
import { Table, Tag, Card, Button, Space, Select, Modal, Descriptions, Popconfirm, message } from 'antd';
import { ReloadOutlined, EyeOutlined, DeleteOutlined } from '@ant-design/icons';
import { operationLogApi, shopApi } from '@/services/api';
import dayjs from 'dayjs';

const TYPE_MAP: Record<string, { label: string; color: string }> = {
  import_products: { label: '导入产品', color: 'blue' },
  import_platform_sku: { label: '导入平台SKU', color: 'cyan' },
  sync_price: { label: '同步价格', color: 'orange' },
  sync_inventory: { label: '同步库存', color: 'green' },
  auto_sync: { label: '自动同步', color: 'purple' },
};

const STATUS_MAP: Record<string, { label: string; color: string }> = {
  running: { label: '进行中', color: 'processing' },
  completed: { label: '已完成', color: 'success' },
  failed: { label: '失败', color: 'error' },
  cancelled: { label: '已取消', color: 'default' },
};

export default function OperationLog() {
  const [data, setData] = useState<any[]>([]);
  const [loading, setLoading] = useState(false);
  const [shops, setShops] = useState<any[]>([]);
  const [selectedShop, setSelectedShop] = useState<string>('');
  const [selectedType, setSelectedType] = useState<string>('');
  const [pagination, setPagination] = useState({ current: 1, pageSize: 20, total: 0 });
  const [detailModal, setDetailModal] = useState(false);
  const [selectedLog, setSelectedLog] = useState<any>(null);

  useEffect(() => {
    loadShops();
  }, []);

  useEffect(() => {
    loadData();
  }, [pagination.current, pagination.pageSize, selectedShop, selectedType]);

  const loadShops = async () => {
    try {
      const res: any = await shopApi.list({ pageSize: 100 });
      setShops(res.data || []);
    } catch (e) {
      console.error(e);
    }
  };


  const loadData = async () => {
    setLoading(true);
    try {
      const res: any = await operationLogApi.list({
        page: pagination.current,
        pageSize: pagination.pageSize,
        shopId: selectedShop || undefined,
        type: selectedType || undefined,
      });
      setData(res.data || []);
      setPagination(p => ({ ...p, total: res.total }));
    } catch (e) {
      console.error(e);
    } finally {
      setLoading(false);
    }
  };

  const handleDelete = async (id: string) => {
    try {
      await operationLogApi.delete(id);
      message.success('删除成功');
      loadData();
    } catch (e: any) {
      message.error(e.message || '删除失败');
    }
  };

  const columns = [
    {
      title: '店铺',
      dataIndex: ['shop', 'name'],
      key: 'shop',
      width: 120,
      render: (v: string) => v || '-',
    },
    {
      title: '类型',
      dataIndex: 'type',
      key: 'type',
      width: 100,
      render: (v: string) => {
        const config = TYPE_MAP[v] || { label: v, color: 'default' };
        return <Tag color={config.color}>{config.label}</Tag>;
      },
    },
    {
      title: '状态',
      dataIndex: 'status',
      key: 'status',
      width: 90,
      render: (v: string) => {
        const config = STATUS_MAP[v] || { label: v, color: 'default' };
        return <Tag color={config.color}>{config.label}</Tag>;
      },
    },
    { title: '总数', dataIndex: 'total', key: 'total', width: 80 },
    { title: '成功', dataIndex: 'success', key: 'success', width: 80 },
    { title: '失败', dataIndex: 'failed', key: 'failed', width: 80 },
    { title: '信息', dataIndex: 'message', key: 'message', ellipsis: true },
    {
      title: '开始时间',
      dataIndex: 'startedAt',
      key: 'startedAt',
      width: 140,
      render: (t: string) => dayjs(t).format('MM-DD HH:mm:ss'),
    },
    {
      title: '操作',
      key: 'action',
      width: 120,
      render: (_: any, record: any) => (
        <Space>
          <Button type="link" size="small" icon={<EyeOutlined />} onClick={() => { setSelectedLog(record); setDetailModal(true); }}>
            详情
          </Button>
          <Popconfirm title="确定删除?" onConfirm={() => handleDelete(record.id)}>
            <Button type="link" size="small" danger icon={<DeleteOutlined />}>删除</Button>
          </Popconfirm>
        </Space>
      ),
    },
  ];


  return (
    <div>
      <Card
        title="操作日志"
        extra={
          <Space>
            <Select
              style={{ width: 150 }}
              placeholder="选择店铺"
              allowClear
              value={selectedShop || undefined}
              onChange={v => { setSelectedShop(v || ''); setPagination(p => ({ ...p, current: 1 })); }}
              options={[{ value: '', label: '全部店铺' }, ...shops.map(s => ({ value: s.id, label: s.name }))]}
            />
            <Select
              style={{ width: 120 }}
              placeholder="操作类型"
              allowClear
              value={selectedType || undefined}
              onChange={v => { setSelectedType(v || ''); setPagination(p => ({ ...p, current: 1 })); }}
              options={[
                { value: '', label: '全部类型' },
                ...Object.entries(TYPE_MAP).map(([k, v]) => ({ value: k, label: v.label })),
              ]}
            />
            <Button icon={<ReloadOutlined />} onClick={loadData} loading={loading}>刷新</Button>
          </Space>
        }
      >
        <Table
          dataSource={data}
          columns={columns}
          rowKey="id"
          loading={loading}
          pagination={{
            ...pagination,
            showSizeChanger: true,
            showTotal: t => `共 ${t} 条`,
            onChange: (page, pageSize) => setPagination(p => ({ ...p, current: page, pageSize: pageSize || 20 })),
          }}
          size="small"
        />
      </Card>

      <Modal
        title="操作日志详情"
        open={detailModal}
        onCancel={() => setDetailModal(false)}
        footer={null}
        width={600}
      >
        {selectedLog && (
          <Descriptions bordered column={2} size="small">
            <Descriptions.Item label="店铺">{selectedLog.shop?.name || '-'}</Descriptions.Item>
            <Descriptions.Item label="类型">
              <Tag color={TYPE_MAP[selectedLog.type]?.color}>{TYPE_MAP[selectedLog.type]?.label || selectedLog.type}</Tag>
            </Descriptions.Item>
            <Descriptions.Item label="状态">
              <Tag color={STATUS_MAP[selectedLog.status]?.color}>{STATUS_MAP[selectedLog.status]?.label}</Tag>
            </Descriptions.Item>
            <Descriptions.Item label="总数">{selectedLog.total}</Descriptions.Item>
            <Descriptions.Item label="成功">{selectedLog.success}</Descriptions.Item>
            <Descriptions.Item label="失败">{selectedLog.failed}</Descriptions.Item>
            <Descriptions.Item label="信息" span={2}>{selectedLog.message || '-'}</Descriptions.Item>
            <Descriptions.Item label="开始时间">{dayjs(selectedLog.startedAt).format('YYYY-MM-DD HH:mm:ss')}</Descriptions.Item>
            <Descriptions.Item label="完成时间">{selectedLog.finishedAt ? dayjs(selectedLog.finishedAt).format('YYYY-MM-DD HH:mm:ss') : '-'}</Descriptions.Item>
            {selectedLog.detail && (
              <Descriptions.Item label="详细信息" span={2}>
                <pre style={{ margin: 0, fontSize: 12, maxHeight: 200, overflow: 'auto' }}>
                  {JSON.stringify(selectedLog.detail, null, 2)}
                </pre>
              </Descriptions.Item>
            )}
          </Descriptions>
        )}
      </Modal>
    </div>
  );
}
