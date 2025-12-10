import { useEffect, useState } from 'react';
import { Card, Table, Tag, Button, Space, Select, message, Modal, Switch, Progress, Descriptions } from 'antd';
import { ReloadOutlined, PlayCircleOutlined, SettingOutlined, EyeOutlined, DeleteOutlined } from '@ant-design/icons';
import { shopApi, autoSyncApi } from '@/services/api';
import dayjs from 'dayjs';

const INTERVAL_OPTIONS = [
  { value: 1, label: '每天' },
  { value: 2, label: '每2天' },
  { value: 3, label: '每3天' },
  { value: 5, label: '每5天' },
  { value: 7, label: '每周' },
];

const SYNC_TYPE_OPTIONS = [
  { value: 'price', label: '仅价格' },
  { value: 'inventory', label: '仅库存' },
  { value: 'both', label: '价格+库存' },
];

export default function AutoSync() {
  const [shops, setShops] = useState<any[]>([]);
  const [configs, setConfigs] = useState<any[]>([]);
  const [tasks, setTasks] = useState<any[]>([]);
  const [loading, setLoading] = useState(false);
  const [taskLoading, setTaskLoading] = useState(false);
  const [configModal, setConfigModal] = useState(false);
  const [detailModal, setDetailModal] = useState(false);
  const [selectedShop, setSelectedShop] = useState<any>(null);
  const [selectedTask, setSelectedTask] = useState<any>(null);
  const [configForm, setConfigForm] = useState({ enabled: false, intervalDays: 1, syncHour: 8, syncType: 'both' });
  const [pagination, setPagination] = useState({ current: 1, pageSize: 10, total: 0 });

  useEffect(() => {
    loadData();
  }, []);

  useEffect(() => {
    loadTasks();
  }, [pagination.current, pagination.pageSize]);

  const loadData = async () => {
    setLoading(true);
    try {
      const [shopsRes, configsRes]: any[] = await Promise.all([
        shopApi.list({ pageSize: 100 }),
        autoSyncApi.getConfigs(),
      ]);
      setShops(shopsRes.data || []);
      setConfigs(configsRes || []);
    } catch (e) {
      console.error(e);
    } finally {
      setLoading(false);
    }
  };

  const loadTasks = async () => {
    setTaskLoading(true);
    try {
      const res: any = await autoSyncApi.getTasks({
        page: pagination.current,
        pageSize: pagination.pageSize,
      });
      setTasks(res.data || []);
      setPagination(p => ({ ...p, total: res.total }));
    } catch (e) {
      console.error(e);
    } finally {
      setTaskLoading(false);
    }
  };


  const handleOpenConfig = (shop: any) => {
    setSelectedShop(shop);
    const config = configs.find(c => c.shopId === shop.id);
    setConfigForm({
      enabled: config?.enabled || false,
      intervalDays: config?.intervalDays || 1,
      syncHour: config?.syncHour ?? 8,
      syncType: config?.syncType || 'both',
    });
    setConfigModal(true);
  };

  const handleSaveConfig = async () => {
    try {
      await autoSyncApi.updateConfig(selectedShop.id, configForm);
      message.success('配置已保存');
      setConfigModal(false);
      loadData();
    } catch (e: any) {
      message.error(e.message || '保存失败');
    }
  };

  const handleTriggerSync = async (shopId: string) => {
    try {
      await autoSyncApi.triggerSync(shopId);
      message.success('同步任务已创建');
      loadTasks();
    } catch (e: any) {
      message.error(e.message || '创建任务失败');
    }
  };

  const handleViewTask = async (task: any) => {
    setSelectedTask(task);
    setDetailModal(true);
  };

  const handleCancelTask = async (taskId: string) => {
    try {
      await autoSyncApi.cancelTask(taskId);
      message.success('任务已取消');
      loadTasks();
    } catch (e: any) {
      message.error(e.message || '取消失败');
    }
  };

  const handlePauseTask = async (taskId: string) => {
    try {
      await autoSyncApi.pauseTask(taskId);
      message.success('任务已暂停');
      loadTasks();
    } catch (e: any) {
      message.error(e.message || '暂停失败');
    }
  };

  const handleResumeTask = async (taskId: string) => {
    try {
      await autoSyncApi.resumeTask(taskId);
      message.success('任务已继续');
      loadTasks();
    } catch (e: any) {
      message.error(e.message || '继续失败');
    }
  };

  const handleRetryTask = async (taskId: string) => {
    try {
      await autoSyncApi.retryTask(taskId);
      message.success('任务已重新开始');
      loadTasks();
    } catch (e: any) {
      message.error(e.message || '重试失败');
    }
  };

  const handleDeleteTask = async (taskId: string) => {
    try {
      await autoSyncApi.deleteTask(taskId);
      message.success('删除成功');
      loadTasks();
    } catch (e: any) {
      message.error(e.message || '删除失败');
    }
  };

  const getStageTag = (stage: string) => {
    const stageMap: Record<string, { color: string; text: string }> = {
      fetch_channel: { color: 'processing', text: '从渠道获取' },
      update_local: { color: 'processing', text: '更新本地' },
      push_platform: { color: 'processing', text: '推送平台' },
      completed: { color: 'success', text: '已完成' },
      failed: { color: 'error', text: '失败' },
      cancelled: { color: 'default', text: '已取消' },
      paused: { color: 'warning', text: '已暂停' },
    };
    const config = stageMap[stage] || { color: 'default', text: stage };
    return <Tag color={config.color}>{config.text}</Tag>;
  };

  const getConfigStatus = (shopId: string) => {
    const config = configs.find(c => c.shopId === shopId);
    if (!config) return <Tag>未配置</Tag>;
    return config.enabled ? <Tag color="success">已启用</Tag> : <Tag>已停用</Tag>;
  };

  const getConfigInfo = (shopId: string) => {
    const config = configs.find(c => c.shopId === shopId);
    if (!config || !config.enabled) return '-';
    const interval = INTERVAL_OPTIONS.find(o => o.value === config.intervalDays)?.label || `每${config.intervalDays}天`;
    const syncHour = `${(config.syncHour ?? 8).toString().padStart(2, '0')}:00`;
    const syncType = SYNC_TYPE_OPTIONS.find(o => o.value === config.syncType)?.label || config.syncType;
    return `${interval} ${syncHour} / ${syncType}`;
  };

  const getNextSyncTime = (shopId: string) => {
    const config = configs.find(c => c.shopId === shopId);
    if (!config?.nextSyncAt) return '-';
    return dayjs(config.nextSyncAt).format('MM-DD HH:mm');
  };


  // 计算任务进度
  const getTaskProgress = (task: any) => {
    if (task.stage === 'completed') return 100;
    if (task.stage === 'failed' || task.stage === 'cancelled') return 0;
    
    const channelStats = task.channelStats || {};
    let totalFetched = 0;
    let totalCount = 0;
    
    for (const stat of Object.values(channelStats) as any[]) {
      totalFetched += stat.fetched || 0;
      totalCount += stat.total || 0;
    }
    
    if (totalCount === 0) return 0;
    return Math.round((totalFetched / totalCount) * 100);
  };

  const shopColumns = [
    { title: '店铺', dataIndex: 'name', key: 'name', width: 150 },
    { title: '状态', key: 'status', width: 100, render: (_: any, r: any) => getConfigStatus(r.id) },
    { title: '同步配置', key: 'config', width: 150, render: (_: any, r: any) => getConfigInfo(r.id) },
    { title: '下次同步', key: 'nextSync', width: 120, render: (_: any, r: any) => getNextSyncTime(r.id) },
    {
      title: '操作',
      key: 'action',
      width: 200,
      render: (_: any, record: any) => (
        <Space>
          <Button type="link" size="small" icon={<SettingOutlined />} onClick={() => handleOpenConfig(record)}>
            配置
          </Button>
          <Button type="link" size="small" icon={<PlayCircleOutlined />} onClick={() => handleTriggerSync(record.id)}>
            立即同步
          </Button>
        </Space>
      ),
    },
  ];

  const taskColumns = [
    { title: '店铺', dataIndex: ['shop', 'name'], key: 'shopName', width: 120 },
    { title: '开始时间', dataIndex: 'startedAt', key: 'startedAt', width: 150, render: (t: string) => dayjs(t).format('MM-DD HH:mm:ss') },
    { title: '阶段', dataIndex: 'stage', key: 'stage', width: 120, render: (s: string) => getStageTag(s) },
    {
      title: '进度',
      key: 'progress',
      width: 150,
      render: (_: any, r: any) => {
        const percent = getTaskProgress(r);
        return <Progress percent={percent} size="small" />;
      },
    },
    { title: '商品数', dataIndex: 'totalProducts', key: 'totalProducts', width: 80 },
    {
      title: '操作',
      key: 'action',
      width: 220,
      render: (_: any, record: any) => (
        <Space>
          <Button type="link" size="small" icon={<EyeOutlined />} onClick={() => handleViewTask(record)}>
            详情
          </Button>
          {/* 运行中的任务：暂停、取消 */}
          {['fetch_channel', 'update_local', 'push_platform'].includes(record.stage) && (
            <>
              <Button type="link" size="small" onClick={() => handlePauseTask(record.id)}>
                暂停
              </Button>
              <Button type="link" size="small" danger onClick={() => handleCancelTask(record.id)}>
                取消
              </Button>
            </>
          )}
          {/* 已暂停的任务：继续、取消 */}
          {record.stage === 'paused' && (
            <>
              <Button type="link" size="small" onClick={() => handleResumeTask(record.id)}>
                继续
              </Button>
              <Button type="link" size="small" danger onClick={() => handleCancelTask(record.id)}>
                取消
              </Button>
            </>
          )}
          {/* 失败或取消的任务：重试、删除 */}
          {['failed', 'cancelled'].includes(record.stage) && (
            <>
              <Button type="link" size="small" onClick={() => handleRetryTask(record.id)}>
                重试
              </Button>
              <Button type="link" size="small" icon={<DeleteOutlined />} onClick={() => handleDeleteTask(record.id)}>
                删除
              </Button>
            </>
          )}
          {/* 已完成的任务：只有删除 */}
          {record.stage === 'completed' && (
            <Button type="link" size="small" icon={<DeleteOutlined />} onClick={() => handleDeleteTask(record.id)}>
              删除
            </Button>
          )}
        </Space>
      ),
    },
  ];


  return (
    <div>
      <Card
        title="同步配置"
        style={{ marginBottom: 16 }}
        extra={<Button icon={<ReloadOutlined />} onClick={loadData} loading={loading}>刷新</Button>}
      >
        <Table
          dataSource={shops}
          columns={shopColumns}
          rowKey="id"
          loading={loading}
          pagination={false}
          size="small"
        />
      </Card>

      <Card
        title="同步任务"
        extra={<Button icon={<ReloadOutlined />} onClick={loadTasks} loading={taskLoading}>刷新</Button>}
      >
        <Table
          dataSource={tasks}
          columns={taskColumns}
          rowKey="id"
          loading={taskLoading}
          pagination={{
            ...pagination,
            showSizeChanger: true,
            showTotal: t => `共 ${t} 条`,
            onChange: (page, pageSize) => setPagination(p => ({ ...p, current: page, pageSize: pageSize || 10 })),
          }}
          size="small"
        />
      </Card>

      {/* 配置弹窗 */}
      <Modal
        title={`自动同步配置 - ${selectedShop?.name}`}
        open={configModal}
        onOk={handleSaveConfig}
        onCancel={() => setConfigModal(false)}
        okText="保存"
        cancelText="取消"
      >
        <div style={{ marginBottom: 16 }}>
          <div style={{ display: 'flex', alignItems: 'center', marginBottom: 16 }}>
            <Switch checked={configForm.enabled} onChange={v => setConfigForm({ ...configForm, enabled: v })} />
            <span style={{ marginLeft: 8 }}>启用自动同步</span>
          </div>
          {configForm.enabled && (
            <>
              <div style={{ marginBottom: 12 }}>
                <span style={{ marginRight: 8 }}>同步间隔：</span>
                <Select
                  style={{ width: 150 }}
                  value={configForm.intervalDays}
                  onChange={v => setConfigForm({ ...configForm, intervalDays: v })}
                  options={INTERVAL_OPTIONS}
                />
              </div>
              <div style={{ marginBottom: 12 }}>
                <span style={{ marginRight: 8 }}>同步时间：</span>
                <Select
                  style={{ width: 150 }}
                  value={configForm.syncHour}
                  onChange={v => setConfigForm({ ...configForm, syncHour: v })}
                  options={Array.from({ length: 24 }, (_, i) => ({
                    value: i,
                    label: `${i.toString().padStart(2, '0')}:00`,
                  }))}
                />
              </div>
              <div>
                <span style={{ marginRight: 8 }}>同步类型：</span>
                <Select
                  style={{ width: 150 }}
                  value={configForm.syncType}
                  onChange={v => setConfigForm({ ...configForm, syncType: v })}
                  options={SYNC_TYPE_OPTIONS}
                />
              </div>
            </>
          )}
        </div>
      </Modal>

      {/* 任务详情弹窗 */}
      <Modal
        title="同步任务详情"
        open={detailModal}
        onCancel={() => setDetailModal(false)}
        footer={null}
        width={600}
      >
        {selectedTask && (
          <div>
            <Descriptions bordered column={2} size="small">
              <Descriptions.Item label="店铺">{selectedTask.shop?.name}</Descriptions.Item>
              <Descriptions.Item label="同步类型">
                {SYNC_TYPE_OPTIONS.find(o => o.value === selectedTask.syncType)?.label}
              </Descriptions.Item>
              <Descriptions.Item label="当前阶段">{getStageTag(selectedTask.stage)}</Descriptions.Item>
              <Descriptions.Item label="商品总数">{selectedTask.totalProducts}</Descriptions.Item>
              <Descriptions.Item label="开始时间" span={2}>
                {dayjs(selectedTask.startedAt).format('YYYY-MM-DD HH:mm:ss')}
              </Descriptions.Item>
              {selectedTask.finishedAt && (
                <Descriptions.Item label="完成时间" span={2}>
                  {dayjs(selectedTask.finishedAt).format('YYYY-MM-DD HH:mm:ss')}
                </Descriptions.Item>
              )}
              {selectedTask.errorMessage && (
                <Descriptions.Item label="错误信息" span={2}>
                  <span style={{ color: '#ff4d4f' }}>{selectedTask.errorMessage}</span>
                </Descriptions.Item>
              )}
            </Descriptions>

            {/* 渠道获取进度 */}
            {selectedTask.channelStats && Object.keys(selectedTask.channelStats).length > 0 && (
              <div style={{ marginTop: 16 }}>
                <h4>渠道获取进度：</h4>
                {Object.entries(selectedTask.channelStats).map(([channelId, stat]: [string, any]) => (
                  <div key={channelId} style={{ marginBottom: 8 }}>
                    <span style={{ marginRight: 8, minWidth: 120, display: 'inline-block' }}>{stat.name || `渠道 ${channelId.slice(0, 8)}...`}</span>
                    <Progress
                      percent={stat.total > 0 ? Math.round(((stat.fetched || 0) / stat.total) * 100) : 0}
                      size="small"
                      style={{ width: 200, display: 'inline-block' }}
                    />
                    <span style={{ marginLeft: 8 }}>{stat.fetched || 0}/{stat.total || 0}</span>
                    <Tag style={{ marginLeft: 8 }} color={stat.status === 'completed' ? 'success' : stat.status === 'failed' ? 'error' : 'processing'}>
                      {stat.status}
                    </Tag>
                  </div>
                ))}
              </div>
            )}
          </div>
        )}
      </Modal>
    </div>
  );
}
