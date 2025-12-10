import { useEffect, useState } from 'react';
import { Card, Table, Tag, Button, Space, Select, message, Modal, Descriptions, Tabs } from 'antd';
import { ReloadOutlined, EyeOutlined, DeleteOutlined } from '@ant-design/icons';
import { shopApi } from '@/services/api';
import dayjs from 'dayjs';

export default function FeedStatus() {
  const [shops, setShops] = useState<any[]>([]);
  const [selectedShop, setSelectedShop] = useState<string>('all');
  const [feeds, setFeeds] = useState<any[]>([]);
  const [loading, setLoading] = useState(false);
  const [pagination, setPagination] = useState({ current: 1, pageSize: 20, total: 0 });
  const [detailModal, setDetailModal] = useState(false);
  const [selectedFeed, setSelectedFeed] = useState<any>(null);

  useEffect(() => { loadShops(); }, []);
  useEffect(() => { loadFeeds(); }, [selectedShop, pagination.current, pagination.pageSize]);

  const loadShops = async () => {
    try {
      const res: any = await shopApi.list({ pageSize: 100 });
      setShops(res.data || []);
    } catch (e) { console.error(e); }
  };

  const loadFeeds = async () => {
    setLoading(true);
    try {
      const res: any = await shopApi.getFeeds(selectedShop === 'all' ? undefined : selectedShop, {
        page: pagination.current, pageSize: pagination.pageSize,
      });
      setFeeds(res.data || []);
      setPagination(p => ({ ...p, total: res.total }));
    } catch (e: any) { message.error(e.message || '加载失败'); }
    finally { setLoading(false); }
  };

  const handleRefreshFeed = async (feedId: string, shopId: string) => {
    try {
      await shopApi.refreshFeedStatus(shopId, feedId);
      message.success('已刷新Feed状态');
      loadFeeds();
    } catch (e: any) { message.error(e.message || '刷新失败'); }
  };

  const handleDeleteFeed = (record: any) => {
    Modal.confirm({
      title: '确认删除',
      content: `确定要删除 Feed ${record.feedId} 吗？`,
      okText: '删除',
      okType: 'danger',
      cancelText: '取消',
      onOk: async () => {
        const shopId = record.shopId || record.shop?.id;
        try {
          await shopApi.deleteFeed(shopId, record.feedId);
          message.success('删除成功');
          loadFeeds();
        } catch (e: any) { message.error(e.message || '删除失败'); }
      },
    });
  };

  const [failedData, setFailedData] = useState<any>(null);
  const [successData, setSuccessData] = useState<any>(null);
  const [loadingFailed, setLoadingFailed] = useState(false);
  const [loadingSuccess, setLoadingSuccess] = useState(false);

  const handleViewDetail = async (feed: any) => {
    setSelectedFeed(feed);
    setFailedData(null);
    setSuccessData(null);
    setDetailModal(true);
    const shopId = feed.shopId || feed.shop?.id || selectedShop;
    
    // 并行尝试加载缓存数据（不会触发 API 请求，只读取已缓存的数据）
    const loadPromises: Promise<any>[] = [];
    
    // 尝试加载失败数据缓存
    if ((feed.failCount || 0) > 0) {
      loadPromises.push(
        shopApi.getFeedDetail(shopId, feed.feedId, 'failed')
          .then((res: any) => {
            // 有缓存才显示，没缓存需要手动点击加载
            if (res.cached) {
              setFailedData(res);
            }
          })
          .catch(() => {})
      );
    }
    
    // 尝试加载成功数据缓存
    if ((feed.successCount || 0) > 0) {
      loadPromises.push(
        shopApi.getFeedDetail(shopId, feed.feedId, 'success')
          .then((res: any) => {
            // 有缓存才显示，没缓存需要手动点击加载
            if (res.cached) {
              setSuccessData(res);
            }
          })
          .catch(() => {})
      );
    }
    
    await Promise.all(loadPromises);
  };

  // 导出 SKU 列表
  const exportSkuList = (data: any, type: 'success' | 'failed') => {
    if (!data?.itemDetails?.itemIngestionStatus?.length) {
      message.warning('没有数据可导出');
      return;
    }
    
    const items = data.itemDetails.itemIngestionStatus;
    const submittedData = data.submittedData || {};
    const syncType = selectedFeed?.syncType || '';
    
    // 构建 CSV 内容
    const headers = ['SKU', '状态'];
    if (syncType === 'price' || syncType === 'both') headers.push('提交价格');
    if (syncType === 'inventory' || syncType === 'both') headers.push('提交库存');
    if (type === 'failed') headers.push('错误信息');
    
    const rows = items.map((item: any) => {
      const row = [
        item.sku,
        item.ingestionStatus === 'SUCCESS' ? '成功' : '失败',
      ];
      if (syncType === 'price' || syncType === 'both') {
        row.push(submittedData[item.sku]?.price ?? '');
      }
      if (syncType === 'inventory' || syncType === 'both') {
        row.push(submittedData[item.sku]?.quantity ?? '');
      }
      if (type === 'failed') {
        const errors = item.ingestionErrors?.ingestionError;
        row.push(errors?.[0]?.description || errors?.[0]?.code || '');
      }
      return row.join(',');
    });
    
    const csv = [headers.join(','), ...rows].join('\n');
    const blob = new Blob(['\ufeff' + csv], { type: 'text/csv;charset=utf-8' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `feed_${selectedFeed?.feedId}_${type}_${dayjs().format('YYYYMMDD_HHmmss')}.csv`;
    a.click();
    URL.revokeObjectURL(url);
    message.success('导出成功');
  };

  const loadSuccessData = async () => {
    if (!selectedFeed) return;
    setLoadingSuccess(true);
    const shopId = selectedFeed.shopId || selectedFeed.shop?.id || selectedShop;
    try {
      const res: any = await shopApi.getFeedDetail(shopId, selectedFeed.feedId, 'success');
      setSuccessData(res);
      message.success('成功数据已加载');
    } catch (e: any) { message.error(e.message || '获取成功数据失败'); }
    finally { setLoadingSuccess(false); }
  };

  const refreshFailedData = async () => {
    if (!selectedFeed) return;
    setLoadingFailed(true);
    const shopId = selectedFeed.shopId || selectedFeed.shop?.id || selectedShop;
    try {
      const res: any = await shopApi.refreshFeedDetail(shopId, selectedFeed.feedId, 'failed');
      setFailedData(res);
      message.success('失败数据已更新');
    } catch (e: any) { message.error(e.message || '更新失败数据失败'); }
    finally { setLoadingFailed(false); }
  };

  const refreshSuccessData = async () => {
    if (!selectedFeed) return;
    setLoadingSuccess(true);
    const shopId = selectedFeed.shopId || selectedFeed.shop?.id || selectedShop;
    try {
      const res: any = await shopApi.refreshFeedDetail(shopId, selectedFeed.feedId, 'success');
      setSuccessData(res);
      message.success('成功数据已更新');
    } catch (e: any) { message.error(e.message || '更新成功数据失败'); }
    finally { setLoadingSuccess(false); }
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
    const typeMap: Record<string, string> = { price: '价格同步', inventory: '库存同步', both: '价格+库存' };
    return typeMap[type] || type;
  };

  // 获取详情表格列配置
  const getDetailColumns = (syncType: string, submittedData: Record<string, any> = {}) => {
    const cols: any[] = [
      { title: 'SKU', dataIndex: 'sku', key: 'sku', width: 180, ellipsis: true },
      { title: '状态', dataIndex: 'ingestionStatus', key: 'status', width: 80,
        render: (s: string) => <Tag color={s === 'SUCCESS' ? 'success' : 'error'}>{s === 'SUCCESS' ? '成功' : '失败'}</Tag>
      },
    ];
    if (syncType === 'price' || syncType === 'both') {
      cols.push({ title: '提交价格', key: 'price', width: 100,
        render: (_: any, r: any) => { const d = submittedData[r.sku]; return d?.price !== undefined ? `$${d.price}` : '-'; }
      });
    }
    if (syncType === 'inventory' || syncType === 'both') {
      cols.push({ title: '提交库存', key: 'quantity', width: 80,
        render: (_: any, r: any) => { const d = submittedData[r.sku]; return d?.quantity !== undefined ? d.quantity : '-'; }
      });
    }
    cols.push({ title: '错误信息', dataIndex: 'ingestionErrors', key: 'errors', ellipsis: true,
      render: (errors: any) => {
        if (!errors?.ingestionError?.length) return '-';
        return <span style={{ color: '#ff4d4f', fontSize: 12 }}>{errors.ingestionError[0]?.description || errors.ingestionError[0]?.code || '-'}</span>;
      }
    });
    return cols;
  };


  const columns = [
    ...(selectedShop === 'all' ? [{ title: '店铺', dataIndex: ['shop', 'name'], key: 'shopName', width: 120 }] : []),
    { title: 'Feed ID', dataIndex: 'feedId', key: 'feedId', width: 200, ellipsis: true },
    { title: '同步类型', dataIndex: 'syncType', key: 'syncType', width: 120, render: (type: string) => getFeedTypeText(type) },
    { title: '商品数量', dataIndex: 'itemCount', key: 'itemCount', width: 100 },
    { title: '状态', dataIndex: 'status', key: 'status', width: 100, render: (status: string) => getStatusTag(status) },
    { title: '成功/失败', key: 'result', width: 120,
      render: (_: any, record: any) => {
        if (record.status === 'PROCESSED') {
          return (<span>
            <Button type="link" size="small" style={{ color: '#52c41a', padding: 0 }} onClick={() => handleViewDetail(record)}>{record.successCount || 0}</Button>
            {' / '}
            <Button type="link" size="small" style={{ color: '#ff4d4f', padding: 0 }} onClick={() => handleViewDetail(record)}>{record.failCount || 0}</Button>
          </span>);
        }
        return '-';
      },
    },
    { title: '提交时间', dataIndex: 'createdAt', key: 'createdAt', width: 160, render: (time: string) => dayjs(time).format('YYYY-MM-DD HH:mm:ss') },
    { title: '完成时间', dataIndex: 'completedAt', key: 'completedAt', width: 160, render: (time: string) => time ? dayjs(time).format('YYYY-MM-DD HH:mm:ss') : '-' },
    { title: '操作', key: 'action', width: 150, fixed: 'right' as const,
      render: (_: any, record: any) => (
        <Space>
          <Button type="link" size="small" icon={<ReloadOutlined />} onClick={() => handleRefreshFeed(record.feedId, record.shopId || record.shop?.id)}>刷新</Button>
          <Button type="link" size="small" icon={<EyeOutlined />} onClick={() => handleViewDetail(record)}>详情</Button>
          <Button type="link" size="small" danger icon={<DeleteOutlined />} onClick={() => handleDeleteFeed(record)}>删除</Button>
        </Space>
      ),
    },
  ];

  return (
    <div>
      <Card title="Feed 同步状态跟踪" extra={
        <Space>
          <span>选择店铺：</span>
          <Select style={{ width: 200 }} value={selectedShop} onChange={setSelectedShop}
            options={[{ value: 'all', label: '全部店铺' }, ...shops.map(s => ({ value: s.id, label: s.name }))]} />
          <Button icon={<ReloadOutlined />} onClick={loadFeeds} loading={loading}>刷新列表</Button>
        </Space>
      }>
        <Table dataSource={feeds} columns={columns} rowKey="id" loading={loading}
          pagination={{ ...pagination, showSizeChanger: true, showTotal: t => `共 ${t} 条`,
            onChange: (page, pageSize) => { setPagination(p => ({ ...p, current: page, pageSize: pageSize || 20 })); },
          }} scroll={{ x: 1200 }} />
      </Card>

      <Modal title={`Feed 详情 - ${selectedFeed?.feedId}`} open={detailModal} onCancel={() => setDetailModal(false)} footer={null} width={900}>
        {selectedFeed && (
          <div>
            <Descriptions bordered column={2} size="small">
              <Descriptions.Item label="Feed ID" span={2}>{selectedFeed.feedId}</Descriptions.Item>
              <Descriptions.Item label="同步类型">{getFeedTypeText(selectedFeed.syncType)}</Descriptions.Item>
              <Descriptions.Item label="状态">{getStatusTag(selectedFeed.status)}</Descriptions.Item>
              <Descriptions.Item label="商品数量">{selectedFeed.itemCount}</Descriptions.Item>
              <Descriptions.Item label="成功/失败">
                <span style={{ color: '#52c41a' }}>{selectedFeed.successCount || 0}</span>{' / '}<span style={{ color: '#ff4d4f' }}>{selectedFeed.failCount || 0}</span>
              </Descriptions.Item>
              <Descriptions.Item label="提交时间" span={2}>{dayjs(selectedFeed.createdAt).format('YYYY-MM-DD HH:mm:ss')}</Descriptions.Item>
              {selectedFeed.completedAt && <Descriptions.Item label="完成时间" span={2}>{dayjs(selectedFeed.completedAt).format('YYYY-MM-DD HH:mm:ss')}</Descriptions.Item>}
              {selectedFeed.errorMessage && <Descriptions.Item label="错误信息" span={2}><span style={{ color: '#ff4d4f' }}>{selectedFeed.errorMessage}</span></Descriptions.Item>}
            </Descriptions>

            <div style={{ marginTop: 16 }}>
              <Tabs defaultActiveKey="failed" items={[
                { key: 'failed', 
                  label: <span style={{ color: '#ff4d4f' }}>失败 ({selectedFeed.failCount || 0})</span>,
                  children: (
                    <div>
                      <div style={{ marginBottom: 12, display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
                        <span style={{ color: '#999', fontSize: 12 }}>
                          {failedData?.cached ? `缓存数据（${failedData.cachedAt ? new Date(failedData.cachedAt).toLocaleString() : ''}）` : failedData ? '最新数据' : '点击按钮加载失败数据'}
                        </span>
                        <Space>
                          {failedData?.itemDetails?.itemIngestionStatus?.length > 0 && (
                            <Button size="small" onClick={() => exportSkuList(failedData, 'failed')}>导出</Button>
                          )}
                          {!failedData && (selectedFeed.failCount || 0) > 0 && (
                            <Button size="small" type="primary" loading={loadingFailed} onClick={refreshFailedData}>
                              加载失败数据
                            </Button>
                          )}
                          {failedData && (
                            <Button size="small" icon={<ReloadOutlined />} loading={loadingFailed} onClick={refreshFailedData}>
                              更新失败数据
                            </Button>
                          )}
                        </Space>
                      </div>
                      {loadingFailed && (
                        <div style={{ textAlign: 'center', padding: '20px' }}>
                          <div>加载失败数据中...</div>
                          <div style={{ color: '#999', fontSize: 12, marginTop: 8 }}>
                            {(selectedFeed.failCount || 0) > 1000 ? `数据量较大（${selectedFeed.failCount} 条），首次加载可能需要较长时间` : '请稍候...'}
                          </div>
                        </div>
                      )}
                      {!loadingFailed && !failedData && (selectedFeed.failCount || 0) > 0 && (
                        <div style={{ textAlign: 'center', padding: '40px', color: '#999' }}>
                          点击上方按钮加载失败数据
                        </div>
                      )}
                      {!loadingFailed && failedData?.itemDetails?.itemIngestionStatus?.length > 0 && (
                        <>
                          {failedData.reachedApiLimit && (
                            <div style={{ padding: '8px 12px', background: '#fffbe6', borderRadius: 4, marginBottom: 12, border: '1px solid #ffe58f' }}>
                              <span style={{ color: '#d48806' }}>⚠ Walmart API 限制最多返回 10000 条记录，当前显示 {failedData.totalFetched} 条（实际失败 {selectedFeed.failCount} 条）</span>
                            </div>
                          )}
                          <Table dataSource={failedData.itemDetails.itemIngestionStatus} rowKey={(r: any) => r.sku || Math.random()} size="small"
                            pagination={{ pageSize: 20, showSizeChanger: true, showTotal: t => `共 ${t} 条` }}
                            columns={getDetailColumns(selectedFeed.syncType, failedData.submittedData || {})} />
                        </>
                      )}
                      {!loadingFailed && (selectedFeed.failCount || 0) === 0 && (
                        <div style={{ padding: '12px', background: '#f6ffed', borderRadius: 4 }}>
                          <span style={{ color: '#52c41a' }}>✓ 没有失败的商品</span>
                        </div>
                      )}
                      {!loadingFailed && failedData && failedData.itemDetails?.itemIngestionStatus?.length === 0 && (selectedFeed.failCount || 0) > 0 && (
                        <div style={{ padding: '12px', background: '#fff2f0', borderRadius: 4 }}>
                          <span style={{ color: '#ff4d4f' }}>⚠ 有 {selectedFeed.failCount} 个商品处理失败</span>
                          <span style={{ color: '#999', marginLeft: 8, fontSize: 12 }}>（Walmart API 未返回失败 SKU 明细）</span>
                        </div>
                      )}
                    </div>
                  ),
                },
                { key: 'success', 
                  label: <span style={{ color: '#52c41a' }}>成功 ({selectedFeed.successCount || 0})</span>,
                  children: (
                    <div>
                      <div style={{ marginBottom: 12, display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
                        <span style={{ color: '#999', fontSize: 12 }}>
                          {successData?.cached ? `缓存数据（${successData.cachedAt ? new Date(successData.cachedAt).toLocaleString() : ''}）` : successData ? '最新数据' : '点击按钮加载成功数据'}
                        </span>
                        <Space>
                          {successData?.itemDetails?.itemIngestionStatus?.length > 0 && (
                            <Button size="small" onClick={() => exportSkuList(successData, 'success')}>导出</Button>
                          )}
                          {!successData && (
                            <Button size="small" type="primary" loading={loadingSuccess} onClick={loadSuccessData}>
                              加载成功数据
                            </Button>
                          )}
                          {successData && (
                            <Button size="small" icon={<ReloadOutlined />} loading={loadingSuccess} onClick={refreshSuccessData}>
                              更新成功数据
                            </Button>
                          )}
                        </Space>
                      </div>
                      {loadingSuccess && (
                        <div style={{ textAlign: 'center', padding: '20px' }}>
                          <div>加载成功数据中...</div>
                          <div style={{ color: '#999', fontSize: 12, marginTop: 8 }}>
                            {(selectedFeed.successCount || 0) > 1000 ? `数据量较大（${selectedFeed.successCount} 条），首次加载可能需要较长时间` : '请稍候...'}
                          </div>
                        </div>
                      )}
                      {!loadingSuccess && !successData && (
                        <div style={{ textAlign: 'center', padding: '40px', color: '#999' }}>
                          点击上方按钮加载成功数据
                        </div>
                      )}
                      {!loadingSuccess && successData?.itemDetails?.itemIngestionStatus?.length > 0 && (
                        <>
                          {successData.reachedApiLimit && (
                            <div style={{ padding: '8px 12px', background: '#fffbe6', borderRadius: 4, marginBottom: 12, border: '1px solid #ffe58f' }}>
                              <span style={{ color: '#d48806' }}>⚠ Walmart API 限制最多返回 10000 条记录，当前显示 {successData.totalFetched} 条（实际成功 {selectedFeed.successCount} 条）</span>
                            </div>
                          )}
                          <Table dataSource={successData.itemDetails.itemIngestionStatus} rowKey={(r: any) => r.sku || Math.random()} size="small"
                            pagination={{ pageSize: 20, showSizeChanger: true, showTotal: t => `共 ${t} 条` }}
                            columns={getDetailColumns(selectedFeed.syncType, successData.submittedData || {})} />
                        </>
                      )}
                    </div>
                  ),
                },
              ]} />
            </div>
          </div>
        )}
      </Modal>
    </div>
  );
}
