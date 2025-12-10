import { useEffect, useState } from 'react';
import { Card, Row, Col, Statistic, Table, Tag } from 'antd';
import { ApiOutlined, ShopOutlined, UnorderedListOutlined, SyncOutlined } from '@ant-design/icons';
import { dashboardApi } from '@/services/api';
import dayjs from 'dayjs';

export default function Dashboard() {
  const [overview, setOverview] = useState<any>({});
  const [syncStats, setSyncStats] = useState<any>({});
  const [recentLogs, setRecentLogs] = useState<any[]>([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    loadData();
  }, []);

  const loadData = async () => {
    try {
      const [ov, stats, logs] = await Promise.all([
        dashboardApi.overview(),
        dashboardApi.syncStats(),
        dashboardApi.recentLogs(10),
      ]);
      setOverview(ov);
      setSyncStats(stats);
      setRecentLogs(logs as unknown as any[]);
    } finally {
      setLoading(false);
    }
  };

  const columns = [
    { title: '规则名称', dataIndex: ['syncRule', 'name'], key: 'name' },
    { title: '状态', dataIndex: 'status', key: 'status', render: (s: string) => (
      <Tag color={s === 'success' ? 'green' : s === 'failed' ? 'red' : 'orange'}>{s}</Tag>
    )},
    { title: '成功/失败', key: 'count', render: (_: any, r: any) => `${r.successCount}/${r.failCount}` },
    { title: '时间', dataIndex: 'createdAt', key: 'time', render: (t: string) => dayjs(t).format('MM-DD HH:mm') },
  ];

  return (
    <div>
      <Row gutter={16} style={{ marginBottom: 24 }}>
        <Col span={6}>
          <Card><Statistic title="渠道数量" value={overview.channelCount || 0} prefix={<ApiOutlined />} /></Card>
        </Col>
        <Col span={6}>
          <Card><Statistic title="店铺数量" value={overview.shopCount || 0} prefix={<ShopOutlined />} /></Card>
        </Col>
        <Col span={6}>
          <Card><Statistic title="商品总数" value={overview.productCount || 0} prefix={<UnorderedListOutlined />} /></Card>
        </Col>
        <Col span={6}>
          <Card><Statistic title="今日同步" value={syncStats.todaySyncCount || 0} prefix={<SyncOutlined />} suffix={`成功率 ${syncStats.successRate || 100}%`} /></Card>
        </Col>
      </Row>
      <Card title="最近同步记录" loading={loading}>
        <Table dataSource={recentLogs} columns={columns} rowKey="id" pagination={false} size="small" />
      </Card>
    </div>
  );
}
