import { useState, useEffect } from 'react';
import { Table, Card, Tag, Space, Button, Input, Select, DatePicker, Modal, Descriptions, Typography, message } from 'antd';
import { EyeOutlined, CheckOutlined } from '@ant-design/icons';
import { aiOptimizeApi } from '@/services/api';
import dayjs from 'dayjs';

const { Title, Text } = Typography;
const { RangePicker } = DatePicker;

const FIELD_LABELS: Record<string, string> = {
  title: '标题',
  description: '描述',
  bulletPoints: '五点描述',
  keywords: '关键词',
};

const STATUS_CONFIG: Record<string, { color: string; label: string }> = {
  pending: { color: 'default', label: '等待中' },
  processing: { color: 'processing', label: '处理中' },
  completed: { color: 'success', label: '已完成' },
  failed: { color: 'error', label: '失败' },
};

export default function OptimizationLogs() {
  const [logs, setLogs] = useState<any[]>([]);
  const [loading, setLoading] = useState(false);
  const [total, setTotal] = useState(0);
  const [page, setPage] = useState(1);
  const [pageSize, setPageSize] = useState(20);
  const [filters, setFilters] = useState<any>({});
  const [detailVisible, setDetailVisible] = useState(false);
  const [currentLog, setCurrentLog] = useState<any>(null);

  useEffect(() => {
    loadLogs();
  }, [page, pageSize, filters]);

  const loadLogs = async () => {
    setLoading(true);
    try {
      const data: any = await aiOptimizeApi.getLogs({
        page,
        pageSize,
        ...filters,
      });
      setLogs(data.data);
      setTotal(data.total);
    } catch (e: any) {
      message.error(e.message);
    } finally {
      setLoading(false);
    }
  };

  const handleViewDetail = async (id: string) => {
    try {
      const data: any = await aiOptimizeApi.getLogDetail(id);
      setCurrentLog(data);
      setDetailVisible(true);
    } catch (e: any) {
      message.error(e.message);
    }
  };

  const handleApply = async (id: string) => {
    try {
      await aiOptimizeApi.apply([id]);
      message.success('应用成功');
      loadLogs();
    } catch (e: any) {
      message.error(e.message);
    }
  };

  const handleDateChange = (dates: any) => {
    if (dates) {
      setFilters({
        ...filters,
        startDate: dates[0]?.format('YYYY-MM-DD'),
        endDate: dates[1]?.format('YYYY-MM-DD'),
      });
    } else {
      const { startDate, endDate, ...rest } = filters;
      setFilters(rest);
    }
    setPage(1);
  };

  const columns = [
    {
      title: '时间',
      dataIndex: 'createdAt',
      key: 'createdAt',
      width: 180,
      render: (v: string) => dayjs(v).format('YYYY-MM-DD HH:mm:ss'),
    },
    {
      title: 'SKU',
      dataIndex: 'productSku',
      key: 'productSku',
      width: 150,
    },
    {
      title: '来源',
      dataIndex: 'productType',
      key: 'productType',
      width: 100,
      render: (v: string) => <Tag>{v === 'pool' ? '商品池' : '刊登商品'}</Tag>,
    },
    {
      title: '优化字段',
      dataIndex: 'field',
      key: 'field',
      width: 120,
      render: (v: string) => FIELD_LABELS[v] || v,
    },
    {
      title: '模型',
      dataIndex: ['model', 'name'],
      key: 'model',
      width: 150,
    },
    {
      title: 'Token',
      dataIndex: 'totalTokens',
      key: 'totalTokens',
      width: 100,
    },
    {
      title: '耗时',
      dataIndex: 'duration',
      key: 'duration',
      width: 100,
      render: (v: number) => `${(v / 1000).toFixed(2)}s`,
    },
    {
      title: '状态',
      dataIndex: 'status',
      key: 'status',
      width: 100,
      render: (status: string) => {
        const config = STATUS_CONFIG[status] || { color: 'default', label: status };
        return <Tag color={config.color}>{config.label}</Tag>;
      },
    },
    {
      title: '已应用',
      dataIndex: 'isApplied',
      key: 'isApplied',
      width: 80,
      render: (v: boolean) => v ? <Tag color="green">是</Tag> : <Tag>否</Tag>,
    },
    {
      title: '操作',
      key: 'action',
      width: 150,
      render: (_: any, record: any) => (
        <Space>
          <Button type="link" size="small" icon={<EyeOutlined />} onClick={() => handleViewDetail(record.id)}>
            详情
          </Button>
          {record.status === 'completed' && !record.isApplied && (
            <Button type="link" size="small" icon={<CheckOutlined />} onClick={() => handleApply(record.id)}>
              应用
            </Button>
          )}
        </Space>
      ),
    },
  ];

  return (
    <div>
      <Title level={4}>优化日志</Title>
      <Card>
        <Space style={{ marginBottom: 16 }} wrap>
          <Input
            placeholder="SKU"
            style={{ width: 150 }}
            allowClear
            onChange={(e) => {
              setFilters({ ...filters, productSku: e.target.value || undefined });
              setPage(1);
            }}
          />
          <Select
            placeholder="优化字段"
            style={{ width: 120 }}
            allowClear
            onChange={(v) => {
              setFilters({ ...filters, field: v });
              setPage(1);
            }}
          >
            {Object.entries(FIELD_LABELS).map(([k, v]) => (
              <Select.Option key={k} value={k}>{v}</Select.Option>
            ))}
          </Select>
          <Select
            placeholder="状态"
            style={{ width: 120 }}
            allowClear
            onChange={(v) => {
              setFilters({ ...filters, status: v });
              setPage(1);
            }}
          >
            {Object.entries(STATUS_CONFIG).map(([k, v]) => (
              <Select.Option key={k} value={k}>{v.label}</Select.Option>
            ))}
          </Select>
          <RangePicker onChange={handleDateChange} />
        </Space>
        <Table
          columns={columns}
          dataSource={logs}
          rowKey="id"
          loading={loading}
          pagination={{
            current: page,
            pageSize,
            total,
            showSizeChanger: true,
            showTotal: (t) => `共 ${t} 条`,
            onChange: (p, ps) => {
              setPage(p);
              setPageSize(ps);
            },
          }}
          scroll={{ x: 1200 }}
        />
      </Card>

      <Modal
        title="日志详情"
        open={detailVisible}
        onCancel={() => setDetailVisible(false)}
        footer={null}
        width={800}
      >
        {currentLog && (
          <Descriptions column={2} bordered size="small">
            <Descriptions.Item label="SKU">{currentLog.productSku}</Descriptions.Item>
            <Descriptions.Item label="来源">{currentLog.productType === 'pool' ? '商品池' : '刊登商品'}</Descriptions.Item>
            <Descriptions.Item label="优化字段">{FIELD_LABELS[currentLog.field] || currentLog.field}</Descriptions.Item>
            <Descriptions.Item label="模型">{currentLog.model?.name}</Descriptions.Item>
            <Descriptions.Item label="模板">{currentLog.template?.name || '-'}</Descriptions.Item>
            <Descriptions.Item label="状态">
              <Tag color={STATUS_CONFIG[currentLog.status]?.color}>
                {STATUS_CONFIG[currentLog.status]?.label}
              </Tag>
            </Descriptions.Item>
            <Descriptions.Item label="Token 消耗" span={2}>
              输入: {currentLog.promptTokens} | 输出: {currentLog.completionTokens} | 总计: {currentLog.totalTokens}
            </Descriptions.Item>
            <Descriptions.Item label="耗时">{(currentLog.duration / 1000).toFixed(2)}s</Descriptions.Item>
            <Descriptions.Item label="已应用">{currentLog.isApplied ? '是' : '否'}</Descriptions.Item>
            <Descriptions.Item label="创建时间" span={2}>
              {dayjs(currentLog.createdAt).format('YYYY-MM-DD HH:mm:ss')}
            </Descriptions.Item>
            {currentLog.errorMessage && (
              <Descriptions.Item label="错误信息" span={2}>
                <Text type="danger">{currentLog.errorMessage}</Text>
              </Descriptions.Item>
            )}
            <Descriptions.Item label="原始内容" span={2}>
              <div style={{ maxHeight: 150, overflow: 'auto', background: '#f5f5f5', padding: 8, borderRadius: 4 }}>
                <pre style={{ margin: 0, whiteSpace: 'pre-wrap' }}>{currentLog.originalContent || '(空)'}</pre>
              </div>
            </Descriptions.Item>
            <Descriptions.Item label="优化结果" span={2}>
              <div style={{ maxHeight: 200, overflow: 'auto', background: '#e6f7ff', padding: 8, borderRadius: 4 }}>
                <pre style={{ margin: 0, whiteSpace: 'pre-wrap' }}>{currentLog.optimizedContent || '(空)'}</pre>
              </div>
            </Descriptions.Item>
            <Descriptions.Item label="使用的 Prompt" span={2}>
              <div style={{ maxHeight: 200, overflow: 'auto', background: '#f5f5f5', padding: 8, borderRadius: 4 }}>
                <pre style={{ margin: 0, whiteSpace: 'pre-wrap', fontSize: 12 }}>{currentLog.promptUsed || '(空)'}</pre>
              </div>
            </Descriptions.Item>
          </Descriptions>
        )}
      </Modal>
    </div>
  );
}
