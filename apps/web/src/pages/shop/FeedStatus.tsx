import { useEffect, useState } from 'react';
import { Card, Table, Tag, Button, Space, Select, message, Modal, Descriptions } from 'antd';
import { ReloadOutlined, EyeOutlined } from '@ant-design/icons';
import { shopApi } from '@/services/api';
import dayjs from 'dayjs';

export default function FeedStatus() {
  const [shops, setShops] = useState<any[]>([]);
  const [selectedShop, setSelectedShop] = useState<string>('all'); // 默认全部店铺
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
    loadFeeds();
  }, [selectedShop, pagination.current, pagination.pageSize]);

  const loadShops = async () => {
    try {
      const res: any = await shopApi.list({ pageSize: 100 });
      setShops(res.data || []);
    } catch (e) {
      console.error(e);
    }
  };

  const loadFeeds = async () => {
    setLoading(true);
    try {
      const res: any = await shopApi.getFeeds(selectedShop === 'all' ? undefined : selectedShop, {
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

  const handleRefreshFeed = async (feedId: string, shopId: string) => {
    try {
      await shopApi.refreshFeedStatus(shopId, feedId);
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

    const shopId = feed.shopId || feed.shop?.id || selectedShop;
    try {
      const res: any = await shopApi.getFeedDetail(shopId, feed.feedId);
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
    ...(selectedShop === 'all' ? [{
      title: '店铺',
      dataIndex: ['shop', 'name'],
      key: 'shopName',
      width: 120,
    }] : []),
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
              <Button type="link" size="small" style={{ color: '#52c41a', padding: 0 }} onClick={() => handleViewDetail(record)}>
                {record.successCount || 0}
              </Button>
              {' / '}
              <Button type="link" size="small" style={{ color: '#ff4d4f', padding: 0 }} onClick={() => handleViewDetail(record)}>
                {record.failCount || 0}
              </Button>
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
            onClick={() => handleRefreshFeed(record.feedId, record.shopId || record.shop?.id)}
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
              options={[
                { value: 'all', label: '全部店铺' },
                ...shops.map(s => ({ value: s.id, label: s.name })),
              ]}
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
                {/* SKU 明细表格 */}
                {selectedFeed.detail.itemDetails?.itemIngestionStatus?.length > 0 ? (
                  <>
                    <h4>SKU 处理明细：</h4>
                    <Table
                      dataSource={selectedFeed.detail.itemDetails.itemIngestionStatus}
                      rowKey={(r: any) => r.sku || r.martId || Math.random()}
                      size="small"
                      pagination={{ pageSize: 10 }}
                      columns={[
                        { title: 'SKU', dataIndex: 'sku', key: 'sku', width: 150 },
                        { 
                          title: '状态', 
                          dataIndex: 'ingestionStatus', 
                          key: 'status', 
                          width: 100,
                          render: (s: string) => (
                            <Tag color={s === 'SUCCESS' ? 'success' : 'error'}>{s}</Tag>
                          )
                        },
                        { 
                          title: '错误信息', 
                          dataIndex: 'ingestionErrors', 
                          key: 'errors',
                          render: (errors: any) => {
                            if (!errors?.ingestionError?.length) return '-';
                            return errors.ingestionError.map((e: any, i: number) => (
                              <div key={i} style={{ color: '#ff4d4f', fontSize: 12 }}>
                                {e.description || e.code}
                              </div>
                            ));
                          }
                        },
                      ]}
                    />
                  </>
                ) : (
                  <div style={{ padding: '12px', background: '#f6ffed', borderRadius: 4, marginBottom: 16 }}>
                    <span style={{ color: '#52c41a' }}>✓ 全部处理成功，无失败明细</span>
                    <span style={{ color: '#999', marginLeft: 8, fontSize: 12 }}>
                      （Walmart API 仅在有失败时返回 SKU 明细）
                    </span>
                  </div>
                )}
                
                <h4 style={{ marginTop: 16 }}>原始返回数据：</h4>
                <pre style={{
                  background: '#f5f5f5',
                  padding: '12px',
                  borderRadius: '4px',
                  maxHeight: '300px',
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
