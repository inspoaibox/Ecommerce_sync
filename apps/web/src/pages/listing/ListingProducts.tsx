import { useState, useEffect } from 'react';
import { Card, Table, Button, Space, Select, Input, Tag, message, Modal, Image, Descriptions } from 'antd';
import { ReloadOutlined, DeleteOutlined, EyeOutlined, EditOutlined } from '@ant-design/icons';
import { listingApi, shopApi, channelApi } from '@/services/api';
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

  const columns = [
    {
      title: '图片',
      dataIndex: 'mainImageUrl',
      width: 70,
      render: (url: string) => url ? <Image src={url} width={50} height={50} style={{ objectFit: 'cover' }} /> : '-',
    },
    { title: 'SKU', dataIndex: 'sku', width: 120 },
    { title: '标题', dataIndex: 'title', width: 200, ellipsis: true },
    { title: '价格', dataIndex: 'price', width: 80, render: (v: number) => `$${Number(v)?.toFixed(2) || '-'}` },
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
        title="刊登商品管理"
        extra={
          <Space>
            <Button icon={<ReloadOutlined />} onClick={loadProducts} loading={loading}>
              刷新
            </Button>
            {selectedRows.length > 0 && (
              <Button danger icon={<DeleteOutlined />} onClick={() => handleDelete(selectedRows.map(r => r.id))}>
                批量删除 ({selectedRows.length})
              </Button>
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
          scroll={{ x: 1200 }}
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
        width={800}
      >
        {selectedProduct && (
          <div>
            <Descriptions bordered column={2} size="small">
              <Descriptions.Item label="SKU">{selectedProduct.sku}</Descriptions.Item>
              <Descriptions.Item label="状态">
                <Tag color={STATUS_MAP[selectedProduct.listingStatus]?.color}>
                  {STATUS_MAP[selectedProduct.listingStatus]?.text}
                </Tag>
              </Descriptions.Item>
              <Descriptions.Item label="标题" span={2}>{selectedProduct.title}</Descriptions.Item>
              <Descriptions.Item label="价格">${Number(selectedProduct.price)?.toFixed(2)}</Descriptions.Item>
              <Descriptions.Item label="库存">{selectedProduct.stock}</Descriptions.Item>
              <Descriptions.Item label="店铺">{selectedProduct.shop?.name}</Descriptions.Item>
              <Descriptions.Item label="渠道">{selectedProduct.channel?.name}</Descriptions.Item>
              {selectedProduct.description && (
                <Descriptions.Item label="描述" span={2}>
                  <div style={{ maxHeight: 100, overflow: 'auto' }}>{selectedProduct.description}</div>
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
    </div>
  );
}
