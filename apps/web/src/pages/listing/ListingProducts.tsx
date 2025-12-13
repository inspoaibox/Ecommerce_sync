import { useState, useEffect } from 'react';
import { Card, Table, Button, Space, Select, Input, Tag, message, Modal, Image, Descriptions, Progress, Alert } from 'antd';
import { ReloadOutlined, DeleteOutlined, EyeOutlined, EditOutlined, RobotOutlined } from '@ant-design/icons';
import { listingApi, shopApi, channelApi, aiModelApi, aiOptimizeApi } from '@/services/api';
import dayjs from 'dayjs';
import ListingProductEdit from './ListingProductEdit';

const STATUS_MAP: Record<string, { color: string; text: string }> = {
  draft: { color: 'default', text: '草稿' },
  pending: { color: 'processing', text: '待刊登' },
  submitting: { color: 'processing', text: '提交中' },
  listed: { color: 'success', text: '已刊登' },
  failed: { color: 'error', text: '失败' },
  updating: { color: 'processing', text: '更新中' },
};

export default function ListingProducts() {
  const [products, setProducts] = useState<any[]>([]);
  const [loading, setLoading] = useState(false);
  const [shops, setShops] = useState<any[]>([]);
  const [channels, setChannels] = useState<any[]>([]);
  const [pagination, setPagination] = useState({ current: 1, pageSize: 20, total: 0 });
  const [filters, setFilters] = useState<any>({});
  const [selectedRows, setSelectedRows] = useState<any[]>([]);
  const [detailModal, setDetailModal] = useState(false);
  const [selectedProduct, setSelectedProduct] = useState<any>(null);
  const [editModal, setEditModal] = useState(false);
  const [editProductId, setEditProductId] = useState<string | null>(null);

  // 批量 AI 优化
  const [batchAiModal, setBatchAiModal] = useState(false);
  const [aiModels, setAiModels] = useState<any[]>([]);
  const [selectedAiModel, setSelectedAiModel] = useState<string>('');
  const [selectedFields, setSelectedFields] = useState<('title' | 'description' | 'bulletPoints' | 'keywords')[]>(['title']);
  const [batchOptimizing, setBatchOptimizing] = useState(false);
  const [batchProgress, setBatchProgress] = useState({ current: 0, total: 0, success: 0, failed: 0 });
  const [batchResults, setBatchResults] = useState<any[]>([]);

  useEffect(() => {
    loadShopsAndChannels();
  }, []);

  useEffect(() => {
    loadProducts();
  }, [pagination.current, pagination.pageSize, filters]);

  const loadShopsAndChannels = async () => {
    try {
      const [shopsRes, channelsRes]: any[] = await Promise.all([
        shopApi.list({ pageSize: 100 }),
        channelApi.list({ pageSize: 100 }),
      ]);
      setShops(shopsRes.data || []);
      setChannels(channelsRes.data || []);
    } catch (e) {
      console.error(e);
    }
  };

  const loadProducts = async () => {
    setLoading(true);
    try {
      const res: any = await listingApi.getProducts({
        page: pagination.current,
        pageSize: pagination.pageSize,
        ...filters,
      });
      setProducts(res.data || []);
      setPagination(p => ({ ...p, total: res.total }));
    } catch (e) {
      console.error(e);
    } finally {
      setLoading(false);
    }
  };

  const handleDelete = async (ids: string[]) => {
    Modal.confirm({
      title: '确认删除',
      content: `确定要删除 ${ids.length} 个商品吗？`,
      onOk: async () => {
        try {
          await listingApi.deleteProducts(ids);
          message.success('删除成功');
          setSelectedRows([]);
          loadProducts();
        } catch (e: any) {
          message.error(e.message || '删除失败');
        }
      },
    });
  };

  const handleViewDetail = (product: any) => {
    setSelectedProduct(product);
    setDetailModal(true);
  };

  const handleEdit = (product: any) => {
    setEditProductId(product.id);
    setEditModal(true);
  };

  // 批量 AI 优化相关
  const loadAiModels = async () => {
    try {
      const res: any = await aiModelApi.list();
      const activeModels = res.filter((m: any) => m.status === 'active');
      setAiModels(activeModels);
      const defaultModel = activeModels.find((m: any) => m.isDefault);
      if (defaultModel) setSelectedAiModel(defaultModel.id);
    } catch (e) {
      console.error(e);
    }
  };

  const handleOpenBatchAi = async () => {
    if (selectedRows.length === 0) {
      message.warning('请先选择要优化的商品');
      return;
    }
    setBatchResults([]);
    setBatchProgress({ current: 0, total: 0, success: 0, failed: 0 });
    await loadAiModels();
    setBatchAiModal(true);
  };

  const handleBatchAiOptimize = async () => {
    if (!selectedAiModel || selectedFields.length === 0) {
      message.warning('请选择模型和优化字段');
      return;
    }

    setBatchOptimizing(true);
    setBatchProgress({ current: 0, total: selectedRows.length, success: 0, failed: 0 });

    try {
      const res: any = await aiOptimizeApi.batchOptimize({
        products: selectedRows.map(r => ({ id: r.id, type: 'listing' as const })),
        fields: selectedFields,
        modelId: selectedAiModel,
      });

      setBatchResults(res.results || []);
      setBatchProgress({
        current: res.total,
        total: res.total,
        success: res.success,
        failed: res.failed,
      });
      message.success(`批量优化完成：成功 ${res.success}，失败 ${res.failed}`);
    } catch (e: any) {
      message.error(e.message || '批量优化失败');
    } finally {
      setBatchOptimizing(false);
    }
  };

  const handleApplyBatchResults = async () => {
    const successResults = batchResults.filter(r => r.status === 'success');
    const logIds = successResults.flatMap(r => r.results?.map((res: any) => res.logId) || []).filter(Boolean);

    if (logIds.length === 0) {
      message.warning('没有可应用的优化结果');
      return;
    }

    try {
      await aiOptimizeApi.apply(logIds);
      message.success('已应用所有优化结果');
      setBatchAiModal(false);
      setSelectedRows([]);
      loadProducts();
    } catch (e: any) {
      message.error(e.message || '应用失败');
    }
  };

  const columns = [
    {
      title: '图片',
      dataIndex: 'mainImageUrl',
      width: 70,
      render: (url: string) => url ? <Image src={url} width={50} height={50} style={{ objectFit: 'cover' }} /> : '-',
    },
    { title: 'SKU', dataIndex: 'sku', width: 120 },
    { title: '标题', dataIndex: 'title', width: 200, ellipsis: true },
    { title: '价格', dataIndex: 'price', width: 80, render: (v: number) => v != null ? `$${Number(v).toFixed(2)}` : '-' },
    {
      title: '运费',
      width: 80,
      render: (_: any, record: any) => {
        const shippingFee = record.channelAttributes?.shippingFee;
        return shippingFee != null ? `$${Number(shippingFee).toFixed(2)}` : '-';
      },
    },
    {
      title: '优惠价格',
      width: 90,
      render: (_: any, record: any) => {
        const salePrice = record.channelAttributes?.salePrice;
        return salePrice != null ? `$${Number(salePrice).toFixed(2)}` : '-';
      },
    },
    {
      title: '总价',
      width: 80,
      render: (_: any, record: any) => {
        const price = record.price;
        const shippingFee = record.channelAttributes?.shippingFee || 0;
        return price != null ? `$${(Number(price) + Number(shippingFee)).toFixed(2)}` : '-';
      },
    },
    {
      title: '优惠总价',
      width: 90,
      render: (_: any, record: any) => {
        const salePrice = record.channelAttributes?.salePrice;
        const shippingFee = record.channelAttributes?.shippingFee || 0;
        return salePrice != null ? `$${(Number(salePrice) + Number(shippingFee)).toFixed(2)}` : '-';
      },
    },
    {
      title: '平台价格',
      width: 90,
      render: (_: any, record: any) => {
        const platformPrice = record.platformAttributes?.price;
        return platformPrice != null ? `$${Number(platformPrice).toFixed(2)}` : '-';
      },
    },
    { title: '库存', dataIndex: 'stock', width: 70 },
    {
      title: '店铺',
      dataIndex: ['shop', 'name'],
      width: 100,
    },
    {
      title: '渠道',
      dataIndex: ['channel', 'name'],
      width: 100,
    },
    {
      title: '状态',
      dataIndex: 'listingStatus',
      width: 90,
      render: (status: string) => {
        const config = STATUS_MAP[status] || { color: 'default', text: status };
        return <Tag color={config.color}>{config.text}</Tag>;
      },
    },
    {
      title: '创建时间',
      dataIndex: 'createdAt',
      width: 150,
      render: (t: string) => dayjs(t).format('YYYY-MM-DD HH:mm'),
    },
    {
      title: '操作',
      width: 150,
      render: (_: any, record: any) => (
        <Space>
          <Button type="link" size="small" icon={<EyeOutlined />} onClick={() => handleViewDetail(record)}>
            详情
          </Button>
          <Button type="link" size="small" icon={<EditOutlined />} onClick={() => handleEdit(record)}>
            编辑
          </Button>
          <Button type="link" size="small" danger icon={<DeleteOutlined />} onClick={() => handleDelete([record.id])}>
            删除
          </Button>
        </Space>
      ),
    },
  ];

  return (
    <div>
      <Card
        title="商品管理"
        extra={
          <Space>
            <Button icon={<ReloadOutlined />} onClick={loadProducts} loading={loading}>
              刷新
            </Button>
            {selectedRows.length > 0 && (
              <>
                <Button icon={<RobotOutlined />} onClick={handleOpenBatchAi}>
                  批量 AI 优化 ({selectedRows.length})
                </Button>
                <Button danger icon={<DeleteOutlined />} onClick={() => handleDelete(selectedRows.map(r => r.id))}>
                  批量删除 ({selectedRows.length})
                </Button>
              </>
            )}
          </Space>
        }
      >
        <Space style={{ marginBottom: 16 }}>
          <Select
            placeholder="店铺"
            style={{ width: 150 }}
            allowClear
            value={filters.shopId}
            onChange={v => setFilters({ ...filters, shopId: v })}
            options={shops.map(s => ({ value: s.id, label: s.name }))}
          />
          <Select
            placeholder="渠道"
            style={{ width: 150 }}
            allowClear
            value={filters.channelId}
            onChange={v => setFilters({ ...filters, channelId: v })}
            options={channels.map(c => ({ value: c.id, label: c.name }))}
          />
          <Select
            placeholder="状态"
            style={{ width: 120 }}
            allowClear
            value={filters.listingStatus}
            onChange={v => setFilters({ ...filters, listingStatus: v })}
            options={Object.entries(STATUS_MAP).map(([k, v]) => ({ value: k, label: v.text }))}
          />
          <Input.Search
            placeholder="搜索SKU/标题"
            style={{ width: 200 }}
            onSearch={v => setFilters({ ...filters, keyword: v })}
            allowClear
          />
        </Space>

        <Table
          dataSource={products}
          columns={columns}
          rowKey="id"
          loading={loading}
          size="small"
          scroll={{ x: 1650 }}
          rowSelection={{
            selectedRowKeys: selectedRows.map(r => r.id),
            onChange: (_, rows) => setSelectedRows(rows),
          }}
          pagination={{
            ...pagination,
            showSizeChanger: true,
            showTotal: t => `共 ${t} 条`,
            onChange: (page, pageSize) => setPagination(p => ({ ...p, current: page, pageSize: pageSize || 20 })),
          }}
        />
      </Card>

      <Modal
        title="商品详情"
        open={detailModal}
        onCancel={() => setDetailModal(false)}
        footer={null}
        width={950}
      >
        {selectedProduct && (
          <div>
            <Descriptions bordered column={3} size="small" labelStyle={{ width: 80, whiteSpace: 'nowrap' }}>
              <Descriptions.Item label="SKU">{selectedProduct.sku}</Descriptions.Item>
              <Descriptions.Item label="状态">
                <Tag color={STATUS_MAP[selectedProduct.listingStatus]?.color}>
                  {STATUS_MAP[selectedProduct.listingStatus]?.text}
                </Tag>
              </Descriptions.Item>
              <Descriptions.Item label="标题" span={3}>{selectedProduct.title}</Descriptions.Item>
              <Descriptions.Item label="价格">${Number(selectedProduct.price)?.toFixed(2)}</Descriptions.Item>
              <Descriptions.Item label="运费">${Number(selectedProduct.channelAttributes?.shippingFee || 0).toFixed(2)}</Descriptions.Item>
              <Descriptions.Item label="优惠价格">
                {selectedProduct.channelAttributes?.salePrice != null 
                  ? `$${Number(selectedProduct.channelAttributes.salePrice).toFixed(2)}` 
                  : '-'}
              </Descriptions.Item>
              <Descriptions.Item label="总价">
                ${(Number(selectedProduct.price || 0) + Number(selectedProduct.channelAttributes?.shippingFee || 0)).toFixed(2)}
              </Descriptions.Item>
              <Descriptions.Item label="优惠总价">
                {selectedProduct.channelAttributes?.salePrice != null 
                  ? `$${(Number(selectedProduct.channelAttributes.salePrice) + Number(selectedProduct.channelAttributes?.shippingFee || 0)).toFixed(2)}` 
                  : '-'}
              </Descriptions.Item>
              <Descriptions.Item label="平台价格">
                {selectedProduct.platformAttributes?.price != null 
                  ? `$${Number(selectedProduct.platformAttributes.price).toFixed(2)}` 
                  : '-'}
              </Descriptions.Item>
              <Descriptions.Item label="库存">{selectedProduct.stock}</Descriptions.Item>
              <Descriptions.Item label="店铺">{selectedProduct.shop?.name}</Descriptions.Item>
              <Descriptions.Item label="渠道">{selectedProduct.channel?.name}</Descriptions.Item>
              {selectedProduct.description && (
                <Descriptions.Item label="描述" span={3}>
                  <div style={{ maxHeight: 100, overflow: 'auto' }}>{selectedProduct.description}</div>
                </Descriptions.Item>
              )}
              {selectedProduct.channelAttributes?.productDescription && (
                <Descriptions.Item label="产品说明" span={3}>
                  <div style={{ maxHeight: 100, overflow: 'auto' }}>{selectedProduct.channelAttributes.productDescription}</div>
                </Descriptions.Item>
              )}
              {selectedProduct.channelAttributes?.productCertification && (
                <Descriptions.Item label="产品资质" span={3}>
                  <div style={{ maxHeight: 100, overflow: 'auto' }}>{selectedProduct.channelAttributes.productCertification}</div>
                </Descriptions.Item>
              )}
            </Descriptions>

            {selectedProduct.imageUrls?.length > 0 && (
              <div style={{ marginTop: 16 }}>
                <h4>商品图片</h4>
                <Image.PreviewGroup>
                  <Space wrap>
                    {selectedProduct.mainImageUrl && (
                      <Image src={selectedProduct.mainImageUrl} width={80} height={80} style={{ objectFit: 'cover' }} />
                    )}
                    {selectedProduct.imageUrls.map((url: string, i: number) => (
                      <Image key={i} src={url} width={80} height={80} style={{ objectFit: 'cover' }} />
                    ))}
                  </Space>
                </Image.PreviewGroup>
              </div>
            )}

            {selectedProduct.channelAttributes && (
              <div style={{ marginTop: 16 }}>
                <h4>渠道属性</h4>
                <pre style={{ background: '#f5f5f5', padding: 8, maxHeight: 200, overflow: 'auto' }}>
                  {JSON.stringify(selectedProduct.channelAttributes, null, 2)}
                </pre>
              </div>
            )}
          </div>
        )}
      </Modal>

      <ListingProductEdit
        visible={editModal}
        productId={editProductId}
        onClose={() => {
          setEditModal(false);
          setEditProductId(null);
        }}
        onSuccess={loadProducts}
      />

      {/* 批量 AI 优化弹窗 */}
      <Modal
        title="批量 AI 优化"
        open={batchAiModal}
        onCancel={() => {
          if (!batchOptimizing) {
            setBatchAiModal(false);
            setBatchResults([]);
          }
        }}
        width={600}
        footer={
          <Space>
            <Button onClick={() => setBatchAiModal(false)} disabled={batchOptimizing}>
              取消
            </Button>
            {batchResults.length > 0 ? (
              <Button type="primary" onClick={handleApplyBatchResults}>
                应用所有结果
              </Button>
            ) : (
              <Button
                type="primary"
                onClick={handleBatchAiOptimize}
                loading={batchOptimizing}
                icon={<RobotOutlined />}
              >
                开始优化
              </Button>
            )}
          </Space>
        }
      >
        {batchResults.length === 0 ? (
          <div>
            <Alert
              message={`已选择 ${selectedRows.length} 个商品进行批量优化`}
              type="info"
              showIcon
              style={{ marginBottom: 16 }}
            />

            <div style={{ marginBottom: 16 }}>
              <div style={{ marginBottom: 8, fontWeight: 500 }}>AI 模型</div>
              <Select
                placeholder="选择 AI 模型"
                style={{ width: '100%' }}
                value={selectedAiModel || undefined}
                onChange={setSelectedAiModel}
                options={aiModels.map(m => ({ value: m.id, label: `${m.name} (${m.modelName})` }))}
                disabled={batchOptimizing}
              />
            </div>

            <div style={{ marginBottom: 16 }}>
              <div style={{ marginBottom: 8, fontWeight: 500 }}>优化字段</div>
              <Select
                mode="multiple"
                placeholder="选择要优化的字段"
                style={{ width: '100%' }}
                value={selectedFields}
                onChange={setSelectedFields}
                options={[
                  { value: 'title', label: '标题' },
                  { value: 'description', label: '描述' },
                  { value: 'bulletPoints', label: '五点描述' },
                ]}
                disabled={batchOptimizing}
              />
            </div>

            {batchOptimizing && (
              <div style={{ marginTop: 16 }}>
                <Progress
                  percent={Math.round((batchProgress.current / batchProgress.total) * 100)}
                  status="active"
                />
                <div style={{ textAlign: 'center', marginTop: 8, color: '#666' }}>
                  正在处理 {batchProgress.current}/{batchProgress.total}...
                </div>
              </div>
            )}
          </div>
        ) : (
          <div>
            <Alert
              message={`优化完成：成功 ${batchProgress.success}，失败 ${batchProgress.failed}`}
              type={batchProgress.failed > 0 ? 'warning' : 'success'}
              showIcon
              style={{ marginBottom: 16 }}
            />
            <div style={{ maxHeight: 300, overflow: 'auto' }}>
              {batchResults.map((result, index) => (
                <div
                  key={index}
                  style={{
                    padding: 8,
                    marginBottom: 8,
                    background: result.status === 'success' ? '#f6ffed' : '#fff2f0',
                    borderRadius: 4,
                    border: `1px solid ${result.status === 'success' ? '#b7eb8f' : '#ffccc7'}`,
                  }}
                >
                  <div style={{ fontWeight: 500 }}>
                    {selectedRows.find(r => r.id === result.productId)?.sku || result.productId}
                  </div>
                  <Tag color={result.status === 'success' ? 'success' : 'error'}>
                    {result.status === 'success' ? '成功' : '失败'}
                  </Tag>
                  {result.error && <span style={{ color: '#ff4d4f', marginLeft: 8 }}>{result.error}</span>}
                </div>
              ))}
            </div>
          </div>
        )}
      </Modal>
    </div>
  );
}
