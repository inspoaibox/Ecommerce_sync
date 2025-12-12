import { useState, useEffect, useCallback } from 'react';
import { Card, Table, Button, Space, Tag, message, Progress, Modal, Typography, Descriptions, Tabs } from 'antd';
import { PlayCircleOutlined, PauseCircleOutlined, StopOutlined, ReloadOutlined, EyeOutlined } from '@ant-design/icons';
import { imageApi } from '@/services/api';

const { Title } = Typography;

const STATUS_MAP: Record<string, { color: string; text: string }> = {
  pending: { color: 'default', text: '待开始' },
  running: { color: 'processing', text: '运行中' },
  paused: { color: 'warning', text: '已暂停' },
  completed: { color: 'success', text: '已完成' },
  failed: { color: 'error', text: '失败' },
  cancelled: { color: 'default', text: '已取消' },
};

const LOG_STATUS_MAP: Record<string, { color: string; text: string }> = {
  pending: { color: 'default', text: '待处理' },
  processing: { color: 'processing', text: '处理中' },
  success: { color: 'success', text: '成功' },
  failed: { color: 'error', text: '失败' },
  skipped: { color: 'default', text: '跳过' },
};

export default function ImageProcessLogs() {
  const [tasks, setTasks] = useState<any[]>([]);
  const [loading, setLoading] = useState(false);
  const [total, setTotal] = useState(0);
  const [page, setPage] = useState(1);
  const [pageSize, setPageSize] = useState(20);
  
  const [detailModal, setDetailModal] = useState(false);
  const [selectedTask, setSelectedTask] = useState<any>(null);
  const [taskLogs, setTaskLogs] = useState<any[]>([]);
  const [logsLoading, setLogsLoading] = useState(false);
  const [logsTotal, setLogsTotal] = useState(0);
  const [logsPage, setLogsPage] = useState(1);

  const loadTasks = useCallback(async () => {
    setLoading(true);
    try {
      const res: any = await imageApi.listTasks({ page, pageSize });
      setTasks(res.data || []);
      setTotal(res.total || 0);
    } catch (e: any) {
      message.error(e.message);
    } finally {
      setLoading(false);
    }
  }, [page, pageSize]);

  useEffect(() => {
    loadTasks();
    // 自动刷新运行中的任务
    const interval = setInterval(() => {
      if (tasks.some(t => t.status === 'running')) {
        loadTasks();
      }
    }, 3000);
    return () => clearInterval(interval);
  }, [loadTasks, tasks]);

  const handleStart = async (id: string) => {
    try {
      await imageApi.startTask(id);
      message.success('任务已开始');
      loadTasks();
    } catch (e: any) {
      message.error(e.message);
    }
  };

  const handlePause = async (id: string) => {
    try {
      await imageApi.pauseTask(id);
      message.success('任务已暂停');
      loadTasks();
    } catch (e: any) {
      message.error(e.message);
    }
  };

  const handleResume = async (id: string) => {
    try {
      await imageApi.resumeTask(id);
      message.success('任务已继续');
      loadTasks();
    } catch (e: any) {
      message.error(e.message);
    }
  };

  const handleCancel = async (id: string) => {
    try {
      await imageApi.cancelTask(id);
      message.success('任务已取消');
      loadTasks();
    } catch (e: any) {
      message.error(e.message);
    }
  };

  const handleRetry = async (id: string) => {
    try {
      await imageApi.retryFailed(id);
      message.success('重试已开始');
      loadTasks();
    } catch (e: any) {
      message.error(e.message);
    }
  };

  const handleViewDetail = async (task: any) => {
    setSelectedTask(task);
    setDetailModal(true);
    setLogsPage(1);
    loadTaskLogs(task.id, 1);
  };

  const loadTaskLogs = async (taskId: string, p: number) => {
    setLogsLoading(true);
    try {
      const res: any = await imageApi.getTaskLogs(taskId, { page: p, pageSize: 50 });
      setTaskLogs(res.data || []);
      setLogsTotal(res.total || 0);
    } catch (e: any) {
      message.error(e.message);
    } finally {
      setLogsLoading(false);
    }
  };

  const columns = [
    { title: '任务名称', dataIndex: 'name', width: 200 },
    {
      title: '处理范围',
      dataIndex: 'scope',
      width: 120,
      render: (scope: string) => {
        const map: Record<string, string> = {
          all: '所有商品',
          category: '按类目',
          sku_list: 'SKU列表',
        };
        return map[scope] || scope;
      },
    },
    {
      title: '进度',
      width: 200,
      render: (_: any, record: any) => {
        const percent = record.totalCount > 0 ? Math.round((record.processedCount / record.totalCount) * 100) : 0;
        return (
          <div>
            <Progress percent={percent} size="small" status={record.status === 'failed' ? 'exception' : undefined} />
            <div style={{ fontSize: 12, color: '#666' }}>
              {record.processedCount} / {record.totalCount}
            </div>
          </div>
        );
      },
    },
    {
      title: '统计',
      width: 180,
      render: (_: any, record: any) => (
        <Space size={4}>
          <Tag color="green">成功: {record.successCount}</Tag>
          <Tag color="red">失败: {record.failCount}</Tag>
          <Tag>跳过: {record.skippedCount}</Tag>
        </Space>
      ),
    },
    {
      title: '状态',
      dataIndex: 'status',
      width: 100,
      render: (status: string) => {
        const s = STATUS_MAP[status] || { color: 'default', text: status };
        return <Tag color={s.color}>{s.text}</Tag>;
      },
    },
    {
      title: '创建时间',
      dataIndex: 'createdAt',
      width: 160,
      render: (v: string) => v ? new Date(v).toLocaleString() : '-',
    },
    {
      title: '操作',
      width: 220,
      render: (_: any, record: any) => (
        <Space size={0} wrap>
          <Button type="link" size="small" icon={<EyeOutlined />} onClick={() => handleViewDetail(record)}>
            详情
          </Button>
          {record.status === 'pending' && (
            <Button type="link" size="small" icon={<PlayCircleOutlined />} onClick={() => handleStart(record.id)}>
              开始
            </Button>
          )}
          {record.status === 'running' && (
            <Button type="link" size="small" icon={<PauseCircleOutlined />} onClick={() => handlePause(record.id)}>
              暂停
            </Button>
          )}
          {record.status === 'paused' && (
            <>
              <Button type="link" size="small" icon={<PlayCircleOutlined />} onClick={() => handleResume(record.id)}>
                继续
              </Button>
              <Button type="link" size="small" danger icon={<StopOutlined />} onClick={() => handleCancel(record.id)}>
                取消
              </Button>
            </>
          )}
          {(record.status === 'completed' || record.status === 'failed') && record.failCount > 0 && (
            <Button type="link" size="small" icon={<ReloadOutlined />} onClick={() => handleRetry(record.id)}>
              重试失败
            </Button>
          )}
        </Space>
      ),
    },
  ];

  const logColumns = [
    { title: 'SKU', dataIndex: 'productSku', width: 120 },
    {
      title: '图片类型',
      dataIndex: 'imageType',
      width: 80,
      render: (v: string, r: any) => v === 'main' ? '主图' : `附图${r.imageIndex + 1}`,
    },
    {
      title: '原始尺寸',
      width: 120,
      render: (_: any, r: any) => r.originalWidth ? `${r.originalWidth}x${r.originalHeight}` : '-',
    },
    {
      title: '原始大小',
      dataIndex: 'originalSize',
      width: 100,
      render: (v: number) => v ? `${(v / 1024).toFixed(1)} KB` : '-',
    },
    {
      title: '处理后尺寸',
      width: 120,
      render: (_: any, r: any) => r.processedWidth ? `${r.processedWidth}x${r.processedHeight}` : '-',
    },
    {
      title: '处理后大小',
      dataIndex: 'processedSize',
      width: 100,
      render: (v: number) => v ? `${(v / 1024).toFixed(1)} KB` : '-',
    },
    {
      title: '操作',
      dataIndex: 'operations',
      width: 150,
      render: (ops: string[]) => ops?.length > 0 ? ops.join(', ') : '-',
    },
    {
      title: '状态',
      dataIndex: 'status',
      width: 80,
      render: (status: string) => {
        const s = LOG_STATUS_MAP[status] || { color: 'default', text: status };
        return <Tag color={s.color}>{s.text}</Tag>;
      },
    },
    {
      title: '错误',
      dataIndex: 'errorMessage',
      ellipsis: true,
      render: (v: string) => v || '-',
    },
  ];

  return (
    <div>
      <Title level={4}>图片处理日志</Title>
      <Card>
        <div style={{ marginBottom: 16 }}>
          <Button onClick={loadTasks} loading={loading}>
            刷新
          </Button>
        </div>
        <Table
          columns={columns}
          dataSource={tasks}
          rowKey="id"
          loading={loading}
          pagination={{
            current: page,
            pageSize,
            total,
            onChange: (p, ps) => { setPage(p); setPageSize(ps); },
            showSizeChanger: true,
            showTotal: t => `共 ${t} 条`,
          }}
          scroll={{ x: 1200 }}
        />
      </Card>

      <Modal
        title={`任务详情 - ${selectedTask?.name || ''}`}
        open={detailModal}
        onCancel={() => setDetailModal(false)}
        footer={null}
        width={1200}
      >
        {selectedTask && (
          <Tabs
            items={[
              {
                key: 'info',
                label: '任务信息',
                children: (
                  <Descriptions bordered column={2} size="small">
                    <Descriptions.Item label="任务名称">{selectedTask.name}</Descriptions.Item>
                    <Descriptions.Item label="状态">
                      <Tag color={STATUS_MAP[selectedTask.status]?.color}>
                        {STATUS_MAP[selectedTask.status]?.text}
                      </Tag>
                    </Descriptions.Item>
                    <Descriptions.Item label="处理范围">
                      {selectedTask.scope === 'all' ? '所有商品' : selectedTask.scope === 'category' ? '按类目' : 'SKU列表'}
                    </Descriptions.Item>
                    <Descriptions.Item label="总数">{selectedTask.totalCount}</Descriptions.Item>
                    <Descriptions.Item label="已处理">{selectedTask.processedCount}</Descriptions.Item>
                    <Descriptions.Item label="成功">{selectedTask.successCount}</Descriptions.Item>
                    <Descriptions.Item label="失败">{selectedTask.failCount}</Descriptions.Item>
                    <Descriptions.Item label="跳过">{selectedTask.skippedCount}</Descriptions.Item>
                    <Descriptions.Item label="创建时间">{new Date(selectedTask.createdAt).toLocaleString()}</Descriptions.Item>
                    <Descriptions.Item label="完成时间">
                      {selectedTask.finishedAt ? new Date(selectedTask.finishedAt).toLocaleString() : '-'}
                    </Descriptions.Item>
                    {selectedTask.errorMessage && (
                      <Descriptions.Item label="错误信息" span={2}>
                        <span style={{ color: 'red' }}>{selectedTask.errorMessage}</span>
                      </Descriptions.Item>
                    )}
                  </Descriptions>
                ),
              },
              {
                key: 'logs',
                label: '处理日志',
                children: (
                  <Table
                    columns={logColumns}
                    dataSource={taskLogs}
                    rowKey="id"
                    loading={logsLoading}
                    size="small"
                    pagination={{
                      current: logsPage,
                      pageSize: 50,
                      total: logsTotal,
                      onChange: p => { setLogsPage(p); loadTaskLogs(selectedTask.id, p); },
                      showTotal: t => `共 ${t} 条`,
                    }}
                    scroll={{ x: 1100 }}
                  />
                ),
              },
            ]}
          />
        )}
      </Modal>
    </div>
  );
}
