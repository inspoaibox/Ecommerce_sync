import { useEffect, useState } from 'react';
import { Card, Table, Tag, Button, Space, Select, Modal, Descriptions, Tabs, message, Popconfirm, Row, Col, Statistic } from 'antd';
import { ReloadOutlined, EyeOutlined, DeleteOutlined, CheckCircleOutlined, CloseCircleOutlined, SyncOutlined, InboxOutlined } from '@ant-design/icons';
import { listingApi, shopApi } from '@/services/api';
import dayjs from 'dayjs';

const STATUS_MAP: Record<string, { color: string; text: string }> = {
  RECEIVED: { color: 'blue', text: '已接收' },
  INPROGRESS: { color: 'processing', text: '处理中' },
  PROCESSED: { color: 'success', text: '已完成' },
  ERROR: { color: 'error', text: '失败' },
};

export default function ListingFeedStatus() {
  const [shops, setShops] = useState<any[]>([]);
  const [selectedShop, setSelectedShop] = useState<string>('all');
  const [selectedStatus, setSelectedStatus] = useState<string>('');
  const [feeds, setFeeds] = useState<any[]>([]);
  const [loading, setLoading] = useState(false);
  const [pagination, setPagination] = useState({ current: 1, pageSize: 20, total: 0 });
  const [detailModal, setDetailModal] = useState(false);
  const [selectedFeed, setSelectedFeed] = useState<any>(null);
  const [selectedRowKeys, setSelectedRowKeys] = useState<string[]>([]);
  const [stats, setStats] = useState({ total: 0, success: 0, failed: 0, processing: 0 });

  useEffect(() => { loadShops(); }, []);
  useEffect(() => { loadFeeds(); }, [selectedShop, selectedStatus, pagination.current, pagination.pageSize]);

  // 计算统计数据
  useEffect(() => {
    const total = pagination.total;
    const success = feeds.filter(f => f.status === 'PROCESSED').length;
    const failed = feeds.filter(f => f.status === 'ERROR').length;
    const processing = feeds.filter(f => f.status === 'RECEIVED' || f.status === 'INPROGRESS').length;
    setStats({ total, success, failed, processing });
  }, [feeds, pagination.total]);

  const loadShops = async () => {
    try {
      const res: any = await shopApi.list({ pageSize: 100 });
      setShops(res.data || []);
    } catch (e) { console.error(e); }
  };

  const loadFeeds = async () => {
    setLoading(true);
    try {
      const res: any = await listingApi.getFeeds({
        shopId: selectedShop === 'all' ? undefined : selectedShop,
        status: selectedStatus || undefined,
        page: pagination.current,
        pageSize: pagination.pageSize,
      });
      setFeeds(res.data || []);
      setPagination(p => ({ ...p, total: res.total }));
    } catch (e: any) { message.error(e.message || '加载失败'); }
    finally { setLoading(false); }
  };

  const handleDelete = async (id: string) => {
    try {
      await listingApi.deleteFeed(id);
      message.success('删除成功');
      loadFeeds();
    } catch (e: any) { message.error(e.message || '删除失败'); }
  };

  const handleBatchDelete = async () => {
    if (selectedRowKeys.length === 0) {
      message.warning('请选择要删除的记录');
      return;
    }
    try {
      await listingApi.deleteFeeds(selectedRowKeys);
      message.success(`已删除 ${selectedRowKeys.length} 条记录`);
      setSelectedRowKeys([]);
      loadFeeds();
    } catch (e: any) { message.error(e.message || '删除失败'); }
  };

  const handleViewDetail = (feed: any) => {
    setSelectedFeed(feed);
    setDetailModal(true);
  };

  const handleRefreshStatus = async (feed: any) => {
    try {
      message.loading({ content: '正在刷新状态...', key: 'refresh' });
      await listingApi.refreshFeedStatus(feed.id);
      message.success({ content: '状态刷新成功', key: 'refresh' });
      loadFeeds();
    } catch (e: any) {
      message.error({ content: e.message || '刷新失败', key: 'refresh' });
    }
  };

  const getStatusTag = (status: string) => {
    const config = STATUS_MAP[status] || { color: 'default', text: status };
    return <Tag color={config.color}>{config.text}</Tag>;
  };

  // 获取详情表格列配置
  const getDetailColumns = () => {
    return [
      { title: 'SKU', dataIndex: 'sku', key: 'sku', width: 180, ellipsis: true },
      {
        title: '状态',
        dataIndex: 'ingestionStatus',
        key: 'status',
        width: 80,
        render: (s: string) => <Tag color={s === 'SUCCESS' ? 'success' : 'error'}>{s === 'SUCCESS' ? '成功' : '失败'}</Tag>,
      },
      {
        title: '错误信息',
        dataIndex: 'ingestionErrors',
        key: 'errors',
        ellipsis: true,
        render: (errors: any) => {
          if (!errors?.ingestionError?.length) return '-';
          return <span style={{ color: '#ff4d4f', fontSize: 12 }}>{errors.ingestionError[0]?.description || errors.ingestionError[0]?.code || '-'}</span>;
        },
      },
    ];
  };

  const columns = [
    ...(selectedShop === 'all' ? [{ title: '店铺', dataIndex: ['shop', 'name'], key: 'shopName', width: 120 }] : []),
    { title: 'Feed ID', dataIndex: 'feedId', key: 'feedId', width: 220, ellipsis: true },
    { title: '类型', dataIndex: 'feedType', key: 'feedType', width: 100 },
    { title: '商品数量', dataIndex: 'itemCount', key: 'itemCount', width: 100 },
    { title: '状态', dataIndex: 'status', key: 'status', width: 100, render: (status: string) => getStatusTag(status) },
    {
      title: '成功/失败',
      key: 'result',
      width: 120,
      render: (_: any, record: any) => {
        if (record.status === 'PROCESSED' || record.status === 'ERROR') {
          return (
            <span>
              <span style={{ color: '#52c41a' }}>{record.successCount || 0}</span>
              {' / '}
              <span style={{ color: '#ff4d4f' }}>{record.failCount || 0}</span>
            </span>
          );
        }
        return '-';
      },
    },
    {
      title: '提交时间',
      dataIndex: 'createdAt',
      key: 'createdAt',
      width: 160,
      render: (time: string) => dayjs(time).format('YYYY-MM-DD HH:mm:ss'),
    },
    {
      title: '完成时间',
      dataIndex: 'completedAt',
      key: 'completedAt',
      width: 160,
      render: (time: string) => time ? dayjs(time).format('YYYY-MM-DD HH:mm:ss') : '-',
    },
    {
      title: '操作',
      key: 'action',
      width: 180,
      fixed: 'right' as const,
      render: (_: any, record: any) => (
        <Space>
          {(record.status === 'RECEIVED' || record.status === 'INPROGRESS') && (
            <Button type="link" size="small" icon={<SyncOutlined />} onClick={() => handleRefreshStatus(record)}>刷新</Button>
          )}
          <Button type="link" size="small" icon={<EyeOutlined />} onClick={() => handleViewDetail(record)}>详情</Button>
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
              title="总计"
              value={stats.total}
              prefix={<InboxOutlined />}
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
              title="处理中"
              value={stats.processing}
              prefix={<SyncOutlined spin={stats.processing > 0} />}
              valueStyle={{ color: '#faad14' }}
            />
          </Card>
        </Col>
      </Row>

      <Card
        title="刊登 Feed 状态"
        extra={
          <Space>
            <span>店铺：</span>
            <Select
              style={{ width: 180 }}
              value={selectedShop}
              onChange={v => { setSelectedShop(v); setPagination(p => ({ ...p, current: 1 })); }}
              options={[{ value: 'all', label: '全部店铺' }, ...shops.map(s => ({ value: s.id, label: s.name }))]}
            />
            <Select
              style={{ width: 120 }}
              placeholder="状态"
              allowClear
              value={selectedStatus || undefined}
              onChange={v => { setSelectedStatus(v || ''); setPagination(p => ({ ...p, current: 1 })); }}
              options={[
                { value: '', label: '全部状态' },
                ...Object.entries(STATUS_MAP).map(([k, v]) => ({ value: k, label: v.text })),
              ]}
            />
            <Button icon={<ReloadOutlined />} onClick={loadFeeds} loading={loading}>刷新</Button>
            {selectedRowKeys.length > 0 && (
              <Popconfirm title={`确定删除 ${selectedRowKeys.length} 条记录?`} onConfirm={handleBatchDelete}>
                <Button danger>批量删除</Button>
              </Popconfirm>
            )}
          </Space>
        }
      >
        <Table
          dataSource={feeds}
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
          scroll={{ x: 1200 }}
        />
      </Card>

      <Modal
        title={`Feed 详情 - ${selectedFeed?.feedId}`}
        open={detailModal}
        onCancel={() => setDetailModal(false)}
        footer={null}
        width={900}
      >
        {selectedFeed && (
          <div>
            <Descriptions bordered column={2} size="small">
              <Descriptions.Item label="Feed ID" span={2}>{selectedFeed.feedId}</Descriptions.Item>
              <Descriptions.Item label="类型">{selectedFeed.feedType}</Descriptions.Item>
              <Descriptions.Item label="状态">{getStatusTag(selectedFeed.status)}</Descriptions.Item>
              <Descriptions.Item label="商品数量">{selectedFeed.itemCount}</Descriptions.Item>
              <Descriptions.Item label="成功/失败">
                <span style={{ color: '#52c41a' }}>{selectedFeed.successCount || 0}</span>
                {' / '}
                <span style={{ color: '#ff4d4f' }}>{selectedFeed.failCount || 0}</span>
              </Descriptions.Item>
              <Descriptions.Item label="提交时间" span={2}>{dayjs(selectedFeed.createdAt).format('YYYY-MM-DD HH:mm:ss')}</Descriptions.Item>
              {selectedFeed.completedAt && (
                <Descriptions.Item label="完成时间" span={2}>{dayjs(selectedFeed.completedAt).format('YYYY-MM-DD HH:mm:ss')}</Descriptions.Item>
              )}
              {selectedFeed.errorMessage && (
                <Descriptions.Item label="错误信息" span={2}>
                  <span style={{ color: '#ff4d4f' }}>{selectedFeed.errorMessage}</span>
                </Descriptions.Item>
              )}
            </Descriptions>

            <div style={{ marginTop: 16 }}>
              <Tabs
                defaultActiveKey="detail"
                items={[
                  {
                    key: 'detail',
                    label: '处理详情',
                    children: (
                      <div>
                        {selectedFeed.feedDetail?.itemDetails?.itemIngestionStatus?.length > 0 ? (
                          <Table
                            dataSource={selectedFeed.feedDetail.itemDetails.itemIngestionStatus}
                            rowKey={(r: any) => r.sku || Math.random()}
                            size="small"
                            pagination={{ pageSize: 20, showSizeChanger: true, showTotal: t => `共 ${t} 条` }}
                            columns={getDetailColumns()}
                          />
                        ) : (
                          <div style={{ padding: 20, textAlign: 'center', color: '#999' }}>
                            暂无详情数据
                          </div>
                        )}
                      </div>
                    ),
                  },
                  {
                    key: 'submitted',
                    label: '提交数据',
                    children: (
                      <div style={{ maxHeight: 400, overflow: 'auto' }}>
                        {selectedFeed.submittedData ? (
                          <pre style={{ background: '#f5f5f5', padding: 12, borderRadius: 4, fontSize: 12 }}>
                            {JSON.stringify(selectedFeed.submittedData, null, 2)}
                          </pre>
                        ) : (
                          <div style={{ padding: 20, textAlign: 'center', color: '#999' }}>
                            暂无提交数据
                          </div>
                        )}
                      </div>
                    ),
                  },
                  {
                    key: 'products',
                    label: `关联商品 (${selectedFeed.productIds?.length || selectedFeed.feedDetail?.itemDetails?.itemIngestionStatus?.length || 0})`,
                    children: (
                      <div style={{ maxHeight: 400, overflow: 'auto' }}>
                        {selectedFeed.productIds?.length > 0 ? (
                          <div style={{ display: 'flex', flexWrap: 'wrap', gap: 8 }}>
                            {selectedFeed.productIds.map((id: string, i: number) => (
                              <Tag key={i}>{id}</Tag>
                            ))}
                          </div>
                        ) : selectedFeed.feedDetail?.itemDetails?.itemIngestionStatus?.length > 0 ? (
                          <div style={{ display: 'flex', flexWrap: 'wrap', gap: 8 }}>
                            {selectedFeed.feedDetail.itemDetails.itemIngestionStatus.map((item: any, i: number) => (
                              <Tag key={i} color={item.ingestionStatus === 'SUCCESS' ? 'success' : 'error'}>
                                {item.sku}
                              </Tag>
                            ))}
                          </div>
                        ) : (
                          <div style={{ padding: 20, textAlign: 'center', color: '#999' }}>
                            暂无关联商品
                          </div>
                        )}
                      </div>
                    ),
                  },
                ]}
              />
            </div>
          </div>
        )}
      </Modal>
    </div>
  );
}
