import { useEffect, useState } from 'react';
import { useParams } from 'react-router-dom';
import { Table, Tag, Input, Card, Space, Typography } from 'antd';
import { productApi, shopApi } from '@/services/api';
import dayjs from 'dayjs';

const { Title } = Typography;

export default function ShopProducts() {
  const { shopId } = useParams<{ shopId: string }>();
  const [shop, setShop] = useState<any>(null);
  const [data, setData] = useState<any[]>([]);
  const [loading, setLoading] = useState(false);
  const [pagination, setPagination] = useState({ current: 1, pageSize: 20, total: 0 });
  const [searchSku, setSearchSku] = useState('');

  useEffect(() => {
    if (shopId) {
      loadShop();
      loadData();
    }
  }, [shopId, pagination.current, searchSku]);

  const loadShop = async () => {
    try {
      const res = await shopApi.get(shopId!);
      setShop(res);
    } catch (e) {
      console.error(e);
    }
  };

  const loadData = async () => {
    setLoading(true);
    try {
      const res: any = await productApi.list({
        shopId,
        page: pagination.current,
        pageSize: pagination.pageSize,
        sku: searchSku || undefined,
      });
      setData(res.data || []);
      setPagination(p => ({ ...p, total: res.total }));
    } finally {
      setLoading(false);
    }
  };

  const columns = [
    { title: 'SKU', dataIndex: 'sku', key: 'sku', width: 120 },
    { title: '标题', dataIndex: 'title', key: 'title', ellipsis: true },
    { title: '原价', dataIndex: 'originalPrice', key: 'originalPrice', width: 80, render: (v: number) => `$${v}` },
    { title: '最终价', dataIndex: 'finalPrice', key: 'finalPrice', width: 80, render: (v: number) => `$${v}` },
    { title: '原库存', dataIndex: 'originalStock', key: 'originalStock', width: 80 },
    { title: '最终库存', dataIndex: 'finalStock', key: 'finalStock', width: 80 },
    { title: '同步状态', dataIndex: 'syncStatus', key: 'syncStatus', width: 100, render: (s: string) => (
      <Tag color={s === 'synced' ? 'green' : s === 'failed' ? 'red' : 'default'}>{s}</Tag>
    )},
    { title: '更新时间', dataIndex: 'updatedAt', key: 'updatedAt', width: 140, render: (t: string) => dayjs(t).format('MM-DD HH:mm') },
  ];

  return (
    <div>
      <Title level={4} style={{ marginBottom: 16 }}>
        {shop?.name || '店铺'} - 商品管理
      </Title>
      <Card size="small" style={{ marginBottom: 16 }}>
        <Space>
          <Input.Search
            placeholder="搜索SKU"
            allowClear
            style={{ width: 250 }}
            onSearch={v => { setSearchSku(v); setPagination(p => ({ ...p, current: 1 })); }}
          />
        </Space>
      </Card>
      <Table
        dataSource={data}
        columns={columns}
        rowKey="id"
        loading={loading}
        pagination={{
          ...pagination,
          showSizeChanger: true,
          showTotal: t => `共 ${t} 条`,
        }}
        onChange={p => setPagination(prev => ({ ...prev, current: p.current || 1, pageSize: p.pageSize || 20 }))}
        scroll={{ x: 900 }}
      />
    </div>
  );
}
