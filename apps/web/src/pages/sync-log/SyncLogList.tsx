import { useEffect, useState } from 'react';
import { Table, Tag, Select, Space, Card, Modal, Descriptions } from 'antd';
import { syncLogApi, syncRuleApi } from '@/services/api';
import dayjs from 'dayjs';

export default function SyncLogList() {
  const [data, setData] = useState<any[]>([]);
  const [rules, setRules] = useState<any[]>([]);
  const [loading, setLoading] = useState(false);
  const [pagination, setPagination] = useState({ current: 1, pageSize: 20, total: 0 });
  const [filters, setFilters] = useState<any>({});
  const [detailOpen, setDetailOpen] = useState(false);
  const [detail, setDetail] = useState<any>(null);

  useEffect(() => { loadRules(); }, []);
  useEffect(() => { loadData(); }, [pagination.current, filters]);

  const loadRules = async () => {
    const res: any = await syncRuleApi.list({ pageSize: 100 });
    setRules(res.data || []);
  };

  const loadData = async () => {
    setLoading(true);
    try {
      const res: any = await syncLogApi.list({ page: pagination.current, pageSize: pagination.pageSize, ...filters });
      setData(res.data || []);
      setPagination(p => ({ ...p, total: res.total }));
    } finally {
      setLoading(false);
    }
  };

  const showDetail = async (id: string) => {
    const res = await syncLogApi.get(id);
    setDetail(res);
    setDetailOpen(true);
  };

  const columns = [
    { title: '规则名称', dataIndex: ['syncRule', 'name'], key: 'rule' },
    { title: '渠道', dataIndex: ['syncRule', 'channel', 'name'], key: 'channel' },
    { title: '店铺', dataIndex: ['syncRule', 'shop', 'name'], key: 'shop' },
    { title: '同步类型', dataIndex: 'syncType', key: 'syncType' },
    { title: '触发方式', dataIndex: 'triggerType', key: 'triggerType' },
    { title: '总数', dataIndex: 'totalCount', key: 'totalCount' },
    { title: '成功', dataIndex: 'successCount', key: 'successCount' },
    { title: '失败', dataIndex: 'failCount', key: 'failCount' },
    { title: '状态', dataIndex: 'status', key: 'status', render: (s: string) => (
      <Tag color={s === 'success' ? 'green' : s === 'failed' ? 'red' : s === 'running' ? 'blue' : 'orange'}>{s}</Tag>
    )},
    { title: '开始时间', dataIndex: 'startedAt', key: 'startedAt', render: (t: string) => dayjs(t).format('MM-DD HH:mm:ss') },
    { title: '操作', key: 'action', render: (_: any, r: any) => <a onClick={() => showDetail(r.id)}>详情</a> },
  ];

  return (
    <div>
      <Card size="small" style={{ marginBottom: 16 }}>
        <Space>
          <Select placeholder="选择规则" allowClear style={{ width: 200 }} options={rules.map(r => ({ value: r.id, label: r.name }))} onChange={v => setFilters((f: any) => ({ ...f, syncRuleId: v }))} />
          <Select placeholder="状态" allowClear style={{ width: 120 }} options={[{ value: 'running', label: '运行中' }, { value: 'success', label: '成功' }, { value: 'partial', label: '部分成功' }, { value: 'failed', label: '失败' }]} onChange={v => setFilters((f: any) => ({ ...f, status: v }))} />
        </Space>
      </Card>
      <Table dataSource={data} columns={columns} rowKey="id" loading={loading} pagination={{ ...pagination, showTotal: t => `共 ${t} 条` }} onChange={p => setPagination(prev => ({ ...prev, current: p.current || 1 }))} scroll={{ x: 1100 }} />
      <Modal title="日志详情" open={detailOpen} onCancel={() => setDetailOpen(false)} footer={null} width={600}>
        {detail && (
          <Descriptions column={2} bordered size="small">
            <Descriptions.Item label="规则">{detail.syncRule?.name}</Descriptions.Item>
            <Descriptions.Item label="状态"><Tag color={detail.status === 'success' ? 'green' : 'red'}>{detail.status}</Tag></Descriptions.Item>
            <Descriptions.Item label="总数">{detail.totalCount}</Descriptions.Item>
            <Descriptions.Item label="成功/失败">{detail.successCount}/{detail.failCount}</Descriptions.Item>
            <Descriptions.Item label="开始时间">{dayjs(detail.startedAt).format('YYYY-MM-DD HH:mm:ss')}</Descriptions.Item>
            <Descriptions.Item label="结束时间">{detail.finishedAt ? dayjs(detail.finishedAt).format('YYYY-MM-DD HH:mm:ss') : '-'}</Descriptions.Item>
            <Descriptions.Item label="错误信息" span={2}>{detail.errorMessage || '-'}</Descriptions.Item>
          </Descriptions>
        )}
      </Modal>
    </div>
  );
}
