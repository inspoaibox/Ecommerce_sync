import { useState, useEffect } from 'react';
import { Card, Table, Space, Button, Input, Select, message, Modal, Tag } from 'antd';
import { DeleteOutlined, ExportOutlined, SearchOutlined } from '@ant-design/icons';
import { productApi, shopApi } from '@/services/api';

const { Search } = Input;

export default function ProductList() {
  const [loading, setLoading] = useState(false);
  const [data, setData] = useState<any[]>([]);
  const [shops, setShops] = useState<any[]>([]);
  const [selectedRowKeys, setSelectedRowKeys] = useState<string[]>([]);
  const [pagination, setPagination] = useState({ current: 1, pageSize: 20, total: 0 });
  const [filters, setFilters] = useState({ keyword: '', shopId: '' });
  const [assignModalOpen, setAssignModalOpen] = useState(false);
  const [targetShopId, setTargetShopId] = useState<string>('');

  useEffect(() => {
    loadShops();
    loadProducts();
  }, []);

  const loadShops = async () => {
    try {
      const res: any = await shopApi.list({ pageSize: 100 });
      setShops(res.data || []);
    } catch (e) {
      console.error(e);
    }
  };

  const loadProducts = async (page = 1, pageSize = 20) => {
    setLoading(true);
    try {
      const res: any = await productApi.list({
        page,
        pageSize,
        keyword: filters.keyword,
        shopId: filters.shopId,
      });
      setData(res.data || []);
      setPagination({ current: page, pageSize, total: res.total || 0 });
    } catch (e) {
      console.error(e);
      message.error('加载商品失败');
    } finally {
      setLoading(false);
    }
  };

  const handleSearch = () => {
    loadProducts(1, pagination.pageSize);
  };

  const handleDelete = async (ids: string[]) => {
    Modal.confirm({
      title: '确认删除',
      content: `确定要删除选中的 ${ids.length} 个商品吗？`,
      onOk: async () => {
        try {
          await productApi.batchDelete(ids);
          message.success('删除成功');
          setSelectedRowKeys([]);
          loadProducts(pagination.current, pagination.pageSize);
        } catch (e) {
          message.error('删除失败');
        }
      },
    });
  };

  const handleAssignToShop = () => {
    if (selectedRowKeys.length === 0) {
      message.warning('请选择要分配的商品');
      return;
    }
    setAssignModalOpen(true);
  };

  const doAssignToShop = async () => {
    if (!targetShopId) {
      message.warning('请选择目标店铺');
      return;
    }
    try {
      await productApi.assignToShop(selectedRowKeys, targetShopId);
      message.success('分配成功');
      setAssignModalOpen(false);
      setSelectedRowKeys([]);
      setTargetShopId('');
      loadProducts(pagination.current, pagination.pageSize);
    } catch (e) {
      message.error('分配失败');
    }
  };

  const columns = [
    { title: 'SKU', dataIndex: 'sku', key: 'sku', width: 150 },
    { title: '标题', dataIndex: 'title', key: 'title', ellipsis: true, width: 250 },
    { title: '价格', dataIndex: 'price', key: 'price', width: 100,
      render: (v: number) => v != null ? `$${v.toFixed(2)}` : '-' },
    { title: '库存', dataIndex: 'stock', key: 'stock', width: 80 },
    { 
      title: '所属店铺', 
      dataIndex: 'shop', 
      key: 'shop', 
      width: 150,
      render: (shop: any) => shop ? <Tag color="blue">{shop.name}</Tag> : <Tag>未分配</Tag>
    },
    { 
      title: '来源渠道', 
      dataIndex: 'sourceChannel', 
      key: 'sourceChannel', 
      width: 120,
      render: (v: string) => v || '-'
    },
    { 
      title: '同步时间', 
      dataIndex: 'updatedAt', 
      key: 'updatedAt', 
      width: 180,
      render: (v: string) => v ? new Date(v).toLocaleString() : '-'
    },
    {
      title: '操作',
      key: 'action',
      width: 100,
      render: (_: any, record: any) => (
        <Button 
          type="link" 
          danger 
          size="small"
          icon={<DeleteOutlined />}
          onClick={() => handleDelete([record.id])}
        >
          删除
        </Button>
      ),
    },
  ];

  const rowSelection = {
    selectedRowKeys,
    onChange: (keys: React.Key[]) => setSelectedRowKeys(keys as string[]),
  };

  return (
    <div>
      <Card title="商品管理" style={{ marginBottom: 16 }}>
        <Space wrap style={{ marginBottom: 16 }}>
          <Search
            placeholder="搜索SKU或标题"
            value={filters.keyword}
            onChange={e => setFilters({ ...filters, keyword: e.target.value })}
            onSearch={handleSearch}
            style={{ width: 250 }}
            allowClear
          />
          <Select
            style={{ width: 200 }}
            placeholder="筛选店铺"
            value={filters.shopId || undefined}
            onChange={v => setFilters({ ...filters, shopId: v || '' })}
            allowClear
            options={[
              { value: '', label: '全部店铺' },
              { value: 'unassigned', label: '未分配' },
              ...shops.map(s => ({ value: s.id, label: s.name }))
            ]}
          />
          <Button type="primary" icon={<SearchOutlined />} onClick={handleSearch}>
            搜索
          </Button>
        </Space>
        <Space style={{ marginBottom: 16 }}>
          <Button 
            icon={<ExportOutlined />} 
            onClick={handleAssignToShop}
            disabled={selectedRowKeys.length === 0}
          >
            分配到店铺 ({selectedRowKeys.length})
          </Button>
          <Button 
            danger 
            icon={<DeleteOutlined />} 
            onClick={() => handleDelete(selectedRowKeys)}
            disabled={selectedRowKeys.length === 0}
          >
            批量删除 ({selectedRowKeys.length})
          </Button>
        </Space>
      </Card>

      <Card>
        <Table
          dataSource={data}
          columns={columns}
          rowKey="id"
          loading={loading}
          rowSelection={rowSelection}
          pagination={{
            ...pagination,
            showTotal: t => `共 ${t} 条`,
            showSizeChanger: true,
            onChange: (page, pageSize) => loadProducts(page, pageSize),
          }}
          scroll={{ x: 1200 }}
          size="small"
        />
      </Card>

      <Modal
        title="分配到店铺"
        open={assignModalOpen}
        onOk={doAssignToShop}
        onCancel={() => { setAssignModalOpen(false); setTargetShopId(''); }}
        okText="确认分配"
        cancelText="取消"
      >
        <div style={{ marginBottom: 16 }}>
          将 {selectedRowKeys.length} 个商品分配到指定店铺
        </div>
        <Select
          style={{ width: '100%' }}
          placeholder="请选择目标店铺"
          value={targetShopId || undefined}
          onChange={setTargetShopId}
          options={shops.map(s => ({ value: s.id, label: s.name }))}
        />
      </Modal>
    </div>
  );
}
