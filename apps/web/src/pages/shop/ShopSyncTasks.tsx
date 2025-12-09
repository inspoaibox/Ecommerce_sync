import { useEffect, useState, useRef } from 'react';
import { Table, Tag, Button, Space, Card, Typography, Popconfirm, message, Progress, Select, Modal, Input } from 'antd';
import { PauseCircleOutlined, PlayCircleOutlined, StopOutlined, DeleteOutlined, ReloadOutlined, CopyOutlined } from '@ant-design/icons';
import { shopApi } from '@/services/api';
import dayjs from 'dayjs';

const { Title } = Typography;
const { TextArea } = Input;

const statusMap: Record<string, { color: string; text: string }> = {
  pending: { color: 'default', text: '等待中' },
  running: { color: 'processing', text: '运行中' },
  paused: { color: 'warning', text: '已暂停' },
  completed: { color: 'success', text: '已完成' },
  failed: { color: 'error', text: '失败' },
  cancelled: { color: 'default', text: '已取消' },
};

export default function ShopSyncTasks() {
  const [data, setData] = useState<any[]>([]);
  const [loading, setLoading] = useState(false);
  const [pagination, setPagination] = useState({ current: 1, pageSize: 20, total: 0 });
  const [shops, setShops] = useState<any[]>([]);
  const [filterShopId, setFilterShopId] = useState<string>();
  const timerRef = useRef<ReturnType<typeof setInterval>>();
  const [skuModal, setSkuModal] = useState<{ open: boolean; title: string; skus: string[] }>({
    open: false,
    title: '',
    skus: [],
  });

  useEffect(() => {
    loadShops();
  }, []);

  useEffect(() => {
    loadData();
  }, [pagination.current, filterShopId]);

  // 自动刷新运行中的任务
  useEffect(() => {
    timerRef.current = setInterval(() => {
      loadData(false);
    }, 2000);
    return () => clearInterval(timerRef.current);
  }, [pagination.current, filterShopId]);

  const loadShops = async () => {
    try {
      const res: any = await shopApi.list({ pageSize: 100 });
      setShops(res.data || []);
    } catch (e) {
      console.error(e);
    }
  };

  const loadData = async (showLoading = true) => {
    if (showLoading) setLoading(true);
    try {
      const res: any = await shopApi.getSyncTasks({
        page: pagination.current,
        pageSize: pagination.pageSize,
        shopId: filterShopId,
      });
      setData(res.data || []);
      setPagination((p) => ({ ...p, total: res.total }));
    } finally {
      if (showLoading) setLoading(false);
    }
  };

  const handlePause = async (taskId: string) => {
    try {
      await shopApi.pauseSyncTask(taskId);
      message.success('暂停信号已发送');
      loadData();
    } catch (e: any) {
      message.error(e.message);
    }
  };

  const handleResume = async (taskId: string) => {
    try {
      await shopApi.resumeSyncTask(taskId);
      message.success('继续信号已发送');
      loadData();
    } catch (e: any) {
      message.error(e.message);
    }
  };

  const handleCancel = async (taskId: string, force: boolean = false) => {
    try {
      await shopApi.cancelSyncTask(taskId, force);
      message.success(force ? '任务已强制取消' : '取消信号已发送');
      loadData();
    } catch (e: any) {
      message.error(e.message);
    }
  };

  const handleDelete = async (taskId: string) => {
    try {
      await shopApi.deleteSyncTask(taskId);
      message.success('删除成功');
      loadData();
    } catch (e: any) {
      message.error(e.message);
    }
  };

  const handleRetry = async (taskId: string) => {
    try {
      await shopApi.retrySyncTask(taskId);
      message.success('重试任务已创建，将从上次进度继续');
      loadData();
    } catch (e: any) {
      message.error(e.message);
    }
  };

  const showSkuModal = (title: string, skus: string[] | null) => {
    if (!skus || skus.length === 0) {
      message.info('暂无数据');
      return;
    }
    setSkuModal({ open: true, title, skus });
  };

  const handleCopySkus = () => {
    navigator.clipboard.writeText(skuModal.skus.join('\n'));
    message.success('已复制到剪贴板');
  };

  const columns = [
    {
      title: '店铺',
      dataIndex: 'shopName',
      key: 'shopName',
      width: 150,
      render: (v: string, r: any) => (
        <span>
          {v}
          <br />
          <small style={{ color: '#999' }}>{r.shop?.platform?.name}</small>
        </span>
      ),
    },
    {
      title: '状态',
      dataIndex: 'status',
      key: 'status',
      width: 100,
      render: (s: string) => <Tag color={statusMap[s]?.color}>{statusMap[s]?.text || s}</Tag>,
    },
    {
      title: '进度',
      key: 'progress',
      width: 200,
      render: (_: any, r: any) => {
        const percent = r.total > 0 ? Math.round((r.progress / r.total) * 100) : 0;
        return (
          <div>
            <Progress percent={percent} size="small" status={r.status === 'failed' ? 'exception' : undefined} />
            <small>{r.progress} / {r.total}</small>
          </div>
        );
      },
    },
    {
      title: '结果',
      key: 'result',
      width: 220,
      render: (_: any, r: any) => (
        <Space size="small">
          <Button type="link" size="small" onClick={() => showSkuModal('新增SKU', r.createdSkus)}>
            新增: {r.created}
          </Button>
          <Button type="link" size="small" onClick={() => showSkuModal('更新SKU', r.updatedSkus)}>
            更新: {r.updated}
          </Button>
          {r.skipped > 0 && (
            <Button type="link" size="small" style={{ color: '#999' }} onClick={() => showSkuModal('API重复SKU', r.skippedSkus)}>
              重复: {r.skipped}
            </Button>
          )}
        </Space>
      ),
    },
    {
      title: '错误信息',
      dataIndex: 'errorMessage',
      key: 'errorMessage',
      width: 200,
      ellipsis: true,
      render: (v: string) => v && <span style={{ color: '#ff4d4f' }}>{v}</span>,
    },
    {
      title: '开始时间',
      dataIndex: 'startedAt',
      key: 'startedAt',
      width: 140,
      render: (t: string) => dayjs(t).format('MM-DD HH:mm:ss'),
    },
    {
      title: '完成时间',
      dataIndex: 'finishedAt',
      key: 'finishedAt',
      width: 140,
      render: (t: string) => (t ? dayjs(t).format('MM-DD HH:mm:ss') : '-'),
    },
    {
      title: '操作',
      key: 'action',
      width: 180,
      render: (_: any, r: any) => (
        <Space size="small">
          {r.status === 'running' && (
            <Button size="small" icon={<PauseCircleOutlined />} onClick={() => handlePause(r.id)}>暂停</Button>
          )}
          {r.status === 'paused' && (
            <>
              <Button size="small" type="primary" icon={<PlayCircleOutlined />} onClick={() => handleResume(r.id)}>继续</Button>
              <Popconfirm title="确定取消此任务?" onConfirm={() => handleCancel(r.id)}>
                <Button size="small" danger icon={<StopOutlined />}>取消</Button>
              </Popconfirm>
            </>
          )}
          {['running', 'pending'].includes(r.status) && (
            <>
              <Popconfirm title="确定取消此任务?" onConfirm={() => handleCancel(r.id)}>
                <Button size="small" danger icon={<StopOutlined />}>取消</Button>
              </Popconfirm>
              <Popconfirm title="任务卡死？强制取消会直接更新数据库状态" onConfirm={() => handleCancel(r.id, true)}>
                <Button size="small" danger type="text">强制</Button>
              </Popconfirm>
            </>
          )}
          {['failed', 'cancelled'].includes(r.status) && (
            <Button size="small" type="primary" icon={<ReloadOutlined />} onClick={() => handleRetry(r.id)}>重试</Button>
          )}
          {['completed', 'failed', 'cancelled'].includes(r.status) && (
            <Popconfirm title="确定删除此记录?" onConfirm={() => handleDelete(r.id)}>
              <Button size="small" danger icon={<DeleteOutlined />}>删除</Button>
            </Popconfirm>
          )}
        </Space>
      ),
    },
  ];

  return (
    <div>
      <Title level={4} style={{ marginBottom: 16 }}>店铺同步记录</Title>
      <Card size="small" style={{ marginBottom: 16 }}>
        <Space>
          <Select
            placeholder="筛选店铺"
            allowClear
            style={{ width: 200 }}
            value={filterShopId}
            onChange={(v) => { setFilterShopId(v); setPagination((p) => ({ ...p, current: 1 })); }}
            options={shops.map((s) => ({ label: s.name, value: s.id }))}
          />
          <Button icon={<ReloadOutlined />} onClick={() => loadData()}>刷新</Button>
        </Space>
      </Card>
      <Table
        dataSource={data}
        columns={columns}
        rowKey="id"
        loading={loading}
        pagination={{ ...pagination, showSizeChanger: true, showTotal: (t) => `共 ${t} 条` }}
        onChange={(p) => setPagination((prev) => ({ ...prev, current: p.current || 1, pageSize: p.pageSize || 20 }))}
        scroll={{ x: 1300 }}
      />

      <Modal
        title={`${skuModal.title} (${skuModal.skus.length} 个)`}
        open={skuModal.open}
        onCancel={() => setSkuModal({ open: false, title: '', skus: [] })}
        footer={
          <Space>
            <Button icon={<CopyOutlined />} onClick={handleCopySkus}>复制全部</Button>
            <Button onClick={() => setSkuModal({ open: false, title: '', skus: [] })}>关闭</Button>
          </Space>
        }
        width={600}
      >
        <TextArea
          value={skuModal.skus.join('\n')}
          readOnly
          rows={15}
          style={{ fontFamily: 'monospace' }}
        />
      </Modal>
    </div>
  );
}
