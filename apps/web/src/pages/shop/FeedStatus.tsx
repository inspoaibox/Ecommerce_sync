import { useEffect, useState } from 'react';
import { Card, Table, Tag, Button, Space, Select, message, Modal, Descriptions } from 'antd';
import { ReloadOutlined, EyeOutlined } from '@ant-design/icons';
import { shopApi } from '@/services/api';
import dayjs from 'dayjs';

export default function FeedStatus() {
  const [shops, setShops] = useState<any[]>([]);
  const [selectedShop, setSelectedShop] = useState<string>('');
  const [feeds, setFeeds] = useState<any[]>([]);
  const [loading, setLoading] = useState(false);
  const [pagination, setPagination] = useState({ current: 1, pageSize: 20, total: 0 });
  const [detailModal, setDetailModal] = useState(false);
  const [selectedFeed, setSelectedFeed] = useState<any>(null);
  const [detailLoading, setDetailLoading] = useState(false);

  useEffect(() => {
    loadShops();
  }, []);

  useEffect(() => {
    if (selectedShop) {
      loadFeeds();
    }
  }, [selectedShop, pagination.current, pagination.pageSize]);

  const loadShops = async () => {
    try {
      const res: any = await shopApi.list({ pageSize: 100 });
      setShops(res.data || []);
      if (res.data && res.data.length > 0) {
        setSelectedShop(res.data[0].id);
      }
    } catch (e) {
      console.error(e);
    }
  };

  const loadFeeds = async () => {
    if (!selectedShop) return;

    setLoading(true);
    try {
      const res: any = await shopApi.getFeeds(selectedShop, {
        page: pagination.current,
        pageSize: pagination.pageSize,
      });
      setFeeds(res.data || []);
      setPagination(p => ({ ...p, total: res.total }));
    } catch (e: any) {
      message.error(e.message || '加载失败');
    } finally {
      setLoading(false);
    }
  };

  const handleRefreshFeed = async (feedId: string) => {
    try {
      await shopApi.refreshFeedStatus(selectedShop, feedId);
      message.success('已刷新Feed状态');
      loadFeeds();
    } catch (e: any) {
      message.error(e.message || '刷新失败');
    }
  };

  const handleViewDetail = async (feed: any) => {
    setSelectedFeed(feed);
    setDetailModal(true);
    setDetailLoading(true);

    try {
      const res: any = await shopApi.getFeedDetail(selectedShop, feed.feedId);
      setSelectedFeed({ ...feed, detail: res });
    } catch (e: any) {
      message.error(e.message || '获取详情失败');
    } finally {
      setDetailLoading(false);
    }
  };

  const getStatusTag = (status: string) => {
    const statusMap: Record<string, { color: string; text: string }> = {
      RECEIVED: { color: 'blue', text: '已接收' },
      INPROGRESS: { color: 'processing', text: '处理中' },
      PROCESSED: { color: 'success', text: '已完成' },
      ERROR: { color: 'error', text: '失败' },
      pending: { color: 'default', text: '待处理' },
    };
    const config = statusMap[status] || { color: 'default', text: status };
    return <Tag color={config.color}>{config.text}</Tag>;
  };

  const getFeedTypeText = (type: string) => {
    const typeMap: Record<string, string> = {
      price: '价格同步',
      inventory: '库存同步',
      both: '价格+库存',
    };
    return typeMap[type] || type;
  };

  const columns = [
    {
      title: 'Feed ID',
      dataIndex: 'feedId',
      key: 'feedId',
      width: 200,
      ellipsis: true,
    },
    {
      title: '同步类型',
      dataIndex: 'syncType',
      key: 'syncType',
      width: 120,
      render: (type: string) => getFeedTypeText(type),
    },
    {
      title: '商品数量',
      dataIndex: 'itemCount',
      key: 'itemCount',
      width: 100,
    },
    {
      title: '状态',
      dataIndex: 'status',
      key: 'status',
      width: 100,
      render: (status: string) => getStatusTag(status),
    },
    {
      title: '成功/失败',
      key: 'result',
      width: 120,
      render: (_: any, record: any) => {
        if (record.status === 'PROCESSED') {
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
      width: 150,
      fixed: 'right' as const,
      render: (_: any, record: any) => (
        <Space>
          <Button
            type="link"
            size="small"
            icon={<ReloadOutlined />}
            onClick={() => handleRefreshFeed(record.feedId)}
          >
            刷新
          </Button>
          <Button
            type="link"
            size="small"
            icon={<EyeOutlined />}
            onClick={() => handleViewDetail(record)}
          >
            详情
          </Button>
        </Space>
      ),
    },
  ];

  return (
    <div>
      <Card
        title="Feed 同步状态跟踪"
        extra={
          <Space>
            <span>选择店铺：</span>
            <Select
              style={{ width: 200 }}
              value={selectedShop}
              onChange={setSelectedShop}
              options={shops.map(s => ({ value: s.id, label: s.name }))}
            />
            <Button
              icon={<ReloadOutlined />}
              onClick={loadFeeds}
              loading={loading}
            >
              刷新列表
            </Button>
          </Space>
        }
      >
        <Table
          dataSource={feeds}
          columns={columns}
          rowKey="id"
          loading={loading}
          pagination={{
            ...pagination,
            showSizeChanger: true,
            showTotal: t => `共 ${t} 条`,
            onChange: (page, pageSize) => {
              setPagination(p => ({ ...p, current: page, pageSize: pageSize || 20 }));
            },
          }}
          scroll={{ x: 1200 }}
        />
      </Card>

      {/* Feed详情弹窗 */}
      <Modal
        title={`Feed 详情 - ${selectedFeed?.feedId}`}
        open={detailModal}
        onCancel={() => setDetailModal(false)}
        footer={null}
        width={800}
      >
        {selectedFeed && (
          <div>
            <Descriptions bordered column={2} size="small">
              <Descriptions.Item label="Feed ID" span={2}>
                {selectedFeed.feedId}
              </Descriptions.Item>
              <Descriptions.Item label="同步类型">
                {getFeedTypeText(selectedFeed.syncType)}
              </Descriptions.Item>
              <Descriptions.Item label="状态">
                {getStatusTag(selectedFeed.status)}
              </Descriptions.Item>
              <Descriptions.Item label="商品数量">
                {selectedFeed.itemCount}
              </Descriptions.Item>
              <Descriptions.Item label="成功/失败">
                <span style={{ color: '#52c41a' }}>{selectedFeed.successCount || 0}</span>
                {' / '}
                <span style={{ color: '#ff4d4f' }}>{selectedFeed.failCount || 0}</span>
              </Descriptions.Item>
              <Descriptions.Item label="提交时间" span={2}>
                {dayjs(selectedFeed.createdAt).format('YYYY-MM-DD HH:mm:ss')}
              </Descriptions.Item>
              {selectedFeed.completedAt && (
                <Descriptions.Item label="完成时间" span={2}>
                  {dayjs(selectedFeed.completedAt).format('YYYY-MM-DD HH:mm:ss')}
                </Descriptions.Item>
              )}
              {selectedFeed.errorMessage && (
                <Descriptions.Item label="错误信息" span={2}>
                  <span style={{ color: '#ff4d4f' }}>{selectedFeed.errorMessage}</span>
                </Descriptions.Item>
              )}
            </Descriptions>

            {detailLoading && (
              <div style={{ textAlign: 'center', padding: '20px' }}>
                加载详情中...
              </div>
            )}

            {selectedFeed.detail && (
              <div style={{ marginTop: 16 }}>
                <h4>沃尔玛返回详情：</h4>
                <pre style={{
                  background: '#f5f5f5',
                  padding: '12px',
                  borderRadius: '4px',
                  maxHeight: '400px',
                  overflow: 'auto',
                }}>
                  {JSON.stringify(selectedFeed.detail, null, 2)}
                </pre>
              </div>
            )}
          </div>
        )}
      </Modal>
    </div>
  );
}
