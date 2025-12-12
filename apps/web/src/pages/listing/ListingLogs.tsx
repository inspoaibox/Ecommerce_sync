import { useEffect, useState } from 'react';
import { Card, Table, Tag, Button, Space, Select, Modal, Descriptions, Popconfirm, message, Input, Row, Col, Statistic } from 'antd';
import { ReloadOutlined, EyeOutlined, DeleteOutlined, SearchOutlined, CheckCircleOutlined, CloseCircleOutlined, ClockCircleOutlined, FileTextOutlined } from '@ant-design/icons';
import { listingApi, shopApi } from '@/services/api';
import dayjs from 'dayjs';

const ACTION_MAP: Record<string, { label: string; color: string }> = {
  submit: { label: '提交刊登', color: 'blue' },
  validate: { label: '验证', color: 'cyan' },
  retry: { label: '重试', color: 'orange' },
  update: { label: '更新', color: 'purple' },
};

const STATUS_MAP: Record<string, { label: string; color: string }> = {
  pending: { label: '待处理', color: 'default' },
  processing: { label: '处理中', color: 'processing' },
  success: { label: '成功', color: 'success' },
  failed: { label: '失败', color: 'error' },
};

export default function ListingLogs() {
  const [data, setData] = useState<any[]>([]);
  const [loading, setLoading] = useState(false);
  const [shops, setShops] = useState<any[]>([]);
  const [selectedShop, setSelectedShop] = useState<string>('');
  const [selectedAction, setSelectedAction] = useState<string>('');
  const [selectedStatus, setSelectedStatus] = useState<string>('');
  const [skuKeyword, setSkuKeyword] = useState('');
  const [pagination, setPagination] = useState({ current: 1, pageSize: 20, total: 0 });
  const [detailModal, setDetailModal] = useState(false);
  const [selectedLog, setSelectedLog] = useState<any>(null);
  const [selectedRowKeys, setSelectedRowKeys] = useState<string[]>([]);
  const [stats, setStats] = useState({ total: 0, success: 0, failed: 0, pending: 0 });

  useEffect(() => { loadShops(); }, []);
  useEffect(() => { loadData(); }, [pagination.current, pagination.pageSize, selectedShop, selectedAction, selectedStatus]);

  // 计算统计数据
  useEffect(() => {
    const total = pagination.total;
    const success = data.filter(d => d.status === 'success').length;
    const failed = data.filter(d => d.status === 'failed').length;
    const pending = data.filter(d => d.status === 'pending' || d.status === 'processing').length;
    setStats({ total, success, failed, pending });
  }, [data, pagination.total]);

  const loadShops = async () => {
    try {
      const res: any = await shopApi.list({ pageSize: 100 });
      setShops(res.data || []);
    } catch (e) { console.error(e); }
  };

  const loadData = async () => {
    setLoading(true);
    try {
      const res: any = await listingApi.getLogs({
        page: pagination.current,
        pageSize: pagination.pageSize,
        shopId: selectedShop || undefined,
        action: selectedAction || undefined,
        status: selectedStatus || undefined,
        productSku: skuKeyword || undefined,
      });
      setData(res.data || []);
      setPagination(p => ({ ...p, total: res.total }));
    } catch (e) { console.error(e); }
    finally { setLoading(false); }
  };

  const handleSearch = () => {
    setPagination(p => ({ ...p, current: 1 }));
    loadData();
  };

  const handleDelete = async (id: string) => {
    try {
      await listingApi.deleteLog(id);
      message.success('删除成功');
      loadData();
    } catch (e: any) { message.error(e.message || '删除失败'); }
  };

  const handleBatchDelete = async () => {
    if (selectedRowKeys.length === 0) {
      message.warning('请选择要删除的日志');
      return;
    }
    try {
      await listingApi.deleteLogs(selectedRowKeys);
      message.success(`已删除 ${selectedRowKeys.length} 条日志`);
      setSelectedRowKeys([]);
      loadData();
    } catch (e: any) { message.error(e.message || '删除失败'); }
  };

  // 格式化 JSON 数据用于表格显示
  const formatJsonForTable = (data: any, maxHeight: number = 80) => {
    if (!data) return <span style={{ color: '#999' }}>-</span>;
    try {
      const str = typeof data === 'string' ? data : JSON.stringify(data, null, 2);
      return (
        <pre style={{ 
          margin: 0, 
          fontSize: 10, 
          maxHeight, 
          overflow: 'auto', 
          background: '#f9f9f9', 
          padding: 4, 
          borderRadius: 3,
          whiteSpace: 'pre-wrap',
          wordBreak: 'break-all',
        }}>
          {str}
        </pre>
      );
    } catch {
      return <span style={{ color: '#999' }}>-</span>;
    }
  };

  const columns = [
    {
      title: '时间',
      dataIndex: 'createdAt',
      width: 100,
      render: (t: string) => dayjs(t).format('MM-DD HH:mm:ss'),
    },
    {
      title: '状态',
      dataIndex: 'status',
      width: 70,
      render: (v: string) => {
        const config = STATUS_MAP[v] || { label: v, color: 'default' };
        const icons: Record<string, string> = { success: '✅', failed: '❌', pending: '⏳', processing: '🔄' };
        return (
          <span style={{ color: config.color === 'success' ? '#52c41a' : config.color === 'error' ? '#ff4d4f' : '#faad14' }}>
            {icons[v] || '📝'} {config.label}
          </span>
        );
      },
    },
    {
      title: '操作类型',
      dataIndex: 'action',
      width: 100,
      render: (v: string) => {
        const config = ACTION_MAP[v] || { label: v, color: 'default' };
        return <code style={{ fontSize: 11, background: '#f0f0f1', padding: '2px 4px', borderRadius: 2 }}>{config.label}</code>;
      },
    },
    { 
      title: 'SKU', 
      dataIndex: 'productSku', 
      width: 140, 
      ellipsis: true,
      render: (v: string) => v || '-',
    },
    {
      title: '消息/错误',
      width: 200,
      render: (_: any, record: any) => {
        if (record.errorMessage) {
          return <span style={{ color: '#ff4d4f', fontSize: 12 }}>{record.errorMessage}</span>;
        }
        if (record.status === 'success') {
          return <span style={{ color: '#52c41a', fontSize: 12 }}>处理成功</span>;
        }
        return <span style={{ color: '#999', fontSize: 12 }}>-</span>;
      },
    },
    {
      title: '请求数据',
      dataIndex: 'requestData',
      width: 250,
      render: (v: any) => formatJsonForTable(v),
    },
    {
      title: '响应数据',
      dataIndex: 'responseData',
      width: 250,
      render: (v: any) => formatJsonForTable(v),
    },
    {
      title: '操作',
      width: 100,
      fixed: 'right' as const,
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
      {/* 统计卡片 */}
      <Row gutter={16} style={{ marginBottom: 16 }}>
        <Col span={6}>
          <Card>
            <Statistic
              title="总日志"
              value={stats.total}
              prefix={<FileTextOutlined />}
              valueStyle={{ color: '#1890ff' }}
            />
          </Card>
        </Col>
        <Col span={6}>
          <Card>
            <Statistic
              title="成功"
              value={stats.success}
              prefix={<CheckCircleOutlined />}
              valueStyle={{ color: '#52c41a' }}
            />
          </Card>
        </Col>
        <Col span={6}>
          <Card>
            <Statistic
              title="失败"
              value={stats.failed}
              prefix={<CloseCircleOutlined />}
              valueStyle={{ color: '#ff4d4f' }}
            />
          </Card>
        </Col>
        <Col span={6}>
          <Card>
            <Statistic
              title="待处理"
              value={stats.pending}
              prefix={<ClockCircleOutlined />}
              valueStyle={{ color: '#faad14' }}
            />
          </Card>
        </Col>
      </Row>

      <Card
        title="刊登日志"
        extra={
          <Space>
            <Input
              placeholder="SKU"
              style={{ width: 120 }}
              value={skuKeyword}
              onChange={e => setSkuKeyword(e.target.value)}
              onPressEnter={handleSearch}
            />
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
              value={selectedAction || undefined}
              onChange={v => { setSelectedAction(v || ''); setPagination(p => ({ ...p, current: 1 })); }}
              options={[
                { value: '', label: '全部类型' },
                ...Object.entries(ACTION_MAP).map(([k, v]) => ({ value: k, label: v.label })),
              ]}
            />
            <Select
              style={{ width: 100 }}
              placeholder="状态"
              allowClear
              value={selectedStatus || undefined}
              onChange={v => { setSelectedStatus(v || ''); setPagination(p => ({ ...p, current: 1 })); }}
              options={[
                { value: '', label: '全部状态' },
                ...Object.entries(STATUS_MAP).map(([k, v]) => ({ value: k, label: v.label })),
              ]}
            />
            <Button icon={<SearchOutlined />} onClick={handleSearch}>搜索</Button>
            <Button icon={<ReloadOutlined />} onClick={loadData} loading={loading}>刷新</Button>
            {selectedRowKeys.length > 0 && (
              <Popconfirm title={`确定删除 ${selectedRowKeys.length} 条日志?`} onConfirm={handleBatchDelete}>
                <Button danger>批量删除</Button>
              </Popconfirm>
            )}
          </Space>
        }
      >
        <Table
          dataSource={data}
          columns={columns}
          rowKey="id"
          loading={loading}
          rowSelection={{
            selectedRowKeys,
            onChange: keys => setSelectedRowKeys(keys as string[]),
          }}
          pagination={{
            ...pagination,
            showSizeChanger: true,
            showTotal: t => `共 ${t} 条`,
            onChange: (page, pageSize) => setPagination(p => ({ ...p, current: page, pageSize: pageSize || 20 })),
          }}
          size="small"
          scroll={{ x: 1400 }}
        />
      </Card>

      <Modal
        title="日志详情"
        open={detailModal}
        onCancel={() => setDetailModal(false)}
        footer={null}
        width={800}
      >
        {selectedLog && (
          <div>
            <Descriptions bordered column={2} size="small">
              <Descriptions.Item label="店铺">{selectedLog.shop?.name || '-'}</Descriptions.Item>
              <Descriptions.Item label="操作类型">
                <Tag color={ACTION_MAP[selectedLog.action]?.color}>{ACTION_MAP[selectedLog.action]?.label || selectedLog.action}</Tag>
              </Descriptions.Item>
              <Descriptions.Item label="状态">
                <Tag color={STATUS_MAP[selectedLog.status]?.color}>{STATUS_MAP[selectedLog.status]?.label}</Tag>
              </Descriptions.Item>
              <Descriptions.Item label="耗时">{selectedLog.duration ? `${selectedLog.duration}ms` : '-'}</Descriptions.Item>
              <Descriptions.Item label="SKU">{selectedLog.productSku || '-'}</Descriptions.Item>
              <Descriptions.Item label="Feed ID">{selectedLog.feedId || '-'}</Descriptions.Item>
              <Descriptions.Item label="时间" span={2}>{dayjs(selectedLog.createdAt).format('YYYY-MM-DD HH:mm:ss')}</Descriptions.Item>
              {selectedLog.errorMessage && (
                <Descriptions.Item label="错误信息" span={2}>
                  <span style={{ color: '#ff4d4f' }}>{selectedLog.errorMessage}</span>
                </Descriptions.Item>
              )}
              {selectedLog.errorCode && (
                <Descriptions.Item label="错误代码">{selectedLog.errorCode}</Descriptions.Item>
              )}
            </Descriptions>

            {selectedLog.requestData && (
              <div style={{ marginTop: 16 }}>
                <h4>请求数据</h4>
                <pre style={{ background: '#f5f5f5', padding: 12, borderRadius: 4, fontSize: 12, maxHeight: 300, overflow: 'auto' }}>
                  {JSON.stringify(selectedLog.requestData, null, 2)}
                </pre>
              </div>
            )}

            {selectedLog.responseData && (
              <div style={{ marginTop: 16 }}>
                <h4>响应数据</h4>
                <pre style={{ background: '#f5f5f5', padding: 12, borderRadius: 4, fontSize: 12, maxHeight: 300, overflow: 'auto' }}>
                  {JSON.stringify(selectedLog.responseData, null, 2)}
                </pre>
              </div>
            )}
          </div>
        )}
      </Modal>
    </div>
  );
}
