import { useState, useEffect, useRef } from 'react';
import { Card, Select, Input, Button, Table, Space, message, Checkbox, Modal, Progress, Typography } from 'antd';
import { SearchOutlined, DownloadOutlined, CloudSyncOutlined } from '@ant-design/icons';
import { channelApi, shopApi, productApi } from '@/services/api';

const { TextArea } = Input;
const { Text } = Typography;

// 可导出的字段配置
const EXPORT_FIELDS = [
  { key: 'sku', label: 'SKU', default: true },
  { key: 'title', label: '标题', default: true },
  { key: 'price', label: '价格', default: true },
  { key: 'stock', label: '库存', default: true },
  { key: 'shippingFee', label: '运费', default: true },
  { key: 'totalPrice', label: '总价', default: true },
  { key: 'currency', label: '货币', default: false },
  { key: 'mapPrice', label: 'MAP价格', default: false },
  { key: 'buyerStock', label: '买家库存', default: false },
];

const BATCH_SIZE = 200; // 每批次查询数量
const BATCH_DELAY = 1500; // 批次间隔(ms)，避免触发速率限制

export default function ProductQuery() {
  const [channels, setChannels] = useState<any[]>([]);
  const [shops, setShops] = useState<any[]>([]);
  const [selectedChannel, setSelectedChannel] = useState<string>('');
  const [selectedShop, setSelectedShop] = useState<string>('');
  const [skuInput, setSkuInput] = useState('');
  const [loading, setLoading] = useState(false);
  const [data, setData] = useState<any[]>([]);
  const [selectedRowKeys, setSelectedRowKeys] = useState<string[]>([]);
  const [exportModalOpen, setExportModalOpen] = useState(false);
  const [syncModalOpen, setSyncModalOpen] = useState(false);
  const [exportFields, setExportFields] = useState<string[]>(
    EXPORT_FIELDS.filter(f => f.default).map(f => f.key)
  );
  const [progress, setProgress] = useState({ current: 0, total: 0, percent: 0 });
  const abortRef = useRef(false);

  useEffect(() => {
    loadChannels();
    loadShops();
  }, []);

  const loadChannels = async () => {
    try {
      const res: any = await channelApi.list({ pageSize: 100 });
      setChannels(res.data || []);
    } catch (e) {
      console.error(e);
    }
  };

  const loadShops = async () => {
    try {
      const res: any = await shopApi.list({ pageSize: 100 });
      setShops(res.data || []);
    } catch (e) {
      console.error(e);
    }
  };

  // 分批次查询
  const handleSearch = async () => {
    if (!selectedChannel) {
      message.warning('请选择渠道');
      return;
    }
    const skus = skuInput
      .split(/[\n,;，；\s]+/)
      .map(s => s.trim())
      .filter(s => s.length > 0);
    
    if (skus.length === 0) {
      message.warning('请输入SKU');
      return;
    }

    // 分批次
    const batches: string[][] = [];
    for (let i = 0; i < skus.length; i += BATCH_SIZE) {
      batches.push(skus.slice(i, i + BATCH_SIZE));
    }

    setLoading(true);
    setData([]);
    setSelectedRowKeys([]);
    setProgress({ current: 0, total: skus.length, percent: 0 });
    abortRef.current = false;

    const allProducts: any[] = [];
    
    for (let i = 0; i < batches.length; i++) {
      if (abortRef.current) break;
      
      try {
        const res: any = await channelApi.queryProducts(selectedChannel, batches[i]);
        if (res.success && res.data) {
          // 计算总价
          const productsWithTotal = res.data.map((p: any) => ({
            ...p,
            totalPrice: (p.price || 0) + (p.extraFields?.shippingFee || 0),
          }));
          allProducts.push(...productsWithTotal);
          setData([...allProducts]);
        }
      } catch (e: any) {
        console.error(`Batch ${i + 1} failed:`, e);
      }

      const processed = Math.min((i + 1) * BATCH_SIZE, skus.length);
      setProgress({
        current: processed,
        total: skus.length,
        percent: Math.round((processed / skus.length) * 100),
      });

      // 批次间延迟，避免触发速率限制
      if (i < batches.length - 1 && !abortRef.current) {
        await new Promise(resolve => setTimeout(resolve, BATCH_DELAY));
      }
    }

    setLoading(false);
    message.success(`查询完成，共 ${allProducts.length} 个商品`);
  };

  const handleAbort = () => {
    abortRef.current = true;
    message.info('正在停止查询...');
  };

  // 导出CSV
  const handleExportCSV = () => {
    const exportData = selectedRowKeys.length > 0 
      ? data.filter(d => selectedRowKeys.includes(d.sku))
      : data;
    
    if (exportData.length === 0) {
      message.warning('没有数据可导出');
      return;
    }
    setExportModalOpen(true);
  };

  const doExportCSV = () => {
    const exportData = selectedRowKeys.length > 0 
      ? data.filter(d => selectedRowKeys.includes(d.sku))
      : data;

    const headers = exportFields.map(key => {
      const field = EXPORT_FIELDS.find(f => f.key === key);
      return field?.label || key;
    });

    const rows = exportData.map(item => {
      return exportFields.map(key => {
        if (key === 'shippingFee' || key === 'mapPrice' || key === 'buyerStock') {
          return item.extraFields?.[key] ?? '';
        }
        if (key === 'totalPrice') {
          return item.totalPrice ?? '';
        }
        return item[key] ?? '';
      });
    });

    const csvContent = [
      headers.join(','),
      ...rows.map(row => row.map(cell => `"${cell}"`).join(',')),
    ].join('\n');

    const blob = new Blob(['\ufeff' + csvContent], { type: 'text/csv;charset=utf-8' });
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = `products_${new Date().toISOString().slice(0, 10)}.csv`;
    link.click();
    URL.revokeObjectURL(url);

    setExportModalOpen(false);
    message.success('导出成功');
  };

  // 同步到本地（保存到店铺）
  const handleSyncToLocal = () => {
    const syncData = selectedRowKeys.length > 0 
      ? data.filter(d => selectedRowKeys.includes(d.sku))
      : data;
    
    if (syncData.length === 0) {
      message.warning('没有数据可同步');
      return;
    }
    setSyncModalOpen(true);
  };

  const doSyncToLocal = async () => {
    const syncData = selectedRowKeys.length > 0 
      ? data.filter(d => selectedRowKeys.includes(d.sku))
      : data;
    
    try {
      const res: any = await productApi.syncFromChannel(
        selectedChannel,
        syncData,
        selectedShop || undefined
      );
      message.success(`同步成功！新增 ${res.created} 个，更新 ${res.updated} 个`);
      setSyncModalOpen(false);
      setSelectedShop('');
    } catch (e: any) {
      message.error(e.message || '同步失败');
    }
  };

  const columns = [
    { title: 'SKU', dataIndex: 'sku', key: 'sku', width: 150, fixed: 'left' as const },
    { title: '标题', dataIndex: 'title', key: 'title', ellipsis: true, width: 250 },
    { title: '价格', dataIndex: 'price', key: 'price', width: 100, render: (v: number, r: any) => v != null ? `${r.currency || '$'}${v.toFixed(2)}` : '-' },
    { title: '库存', dataIndex: 'stock', key: 'stock', width: 80 },
    { title: '运费', key: 'shippingFee', width: 100, render: (_: any, r: any) => {
      const fee = r.extraFields?.shippingFee;
      return fee != null ? `$${fee.toFixed(2)}` : '-';
    }},
    { title: '总价', key: 'totalPrice', width: 100, render: (_: any, r: any) => {
      return r.totalPrice != null ? `$${r.totalPrice.toFixed(2)}` : '-';
    }},
    { title: 'MAP价格', key: 'mapPrice', width: 100, render: (_: any, r: any) => {
      const map = r.extraFields?.mapPrice;
      return map != null ? `$${map}` : '-';
    }},
  ];

  const rowSelection = {
    selectedRowKeys,
    onChange: (keys: React.Key[]) => setSelectedRowKeys(keys as string[]),
  };

  return (
    <div>
      <Card title="商品查询" style={{ marginBottom: 16 }}>
        <Space direction="vertical" style={{ width: '100%' }} size="middle">
          <Space wrap>
            <span>选择渠道：</span>
            <Select
              style={{ width: 250 }}
              placeholder="请选择渠道"
              value={selectedChannel || undefined}
              onChange={setSelectedChannel}
              options={channels.map(c => ({ value: c.id, label: c.name }))}
            />
          </Space>
          <div>
            <div style={{ marginBottom: 8 }}>
              批量输入SKU（每行一个或用逗号/空格分隔，支持大批量查询）：
            </div>
            <TextArea
              rows={6}
              value={skuInput}
              onChange={e => setSkuInput(e.target.value)}
              placeholder="SKU1&#10;SKU2&#10;SKU3&#10;或: SKU1, SKU2, SKU3"
            />
            <Text type="secondary" style={{ fontSize: 12 }}>
              系统将自动分批查询，每批{BATCH_SIZE}个，间隔{BATCH_DELAY/1000}秒
            </Text>
          </div>
          <Space>
            {loading ? (
              <>
                <Button danger onClick={handleAbort}>停止查询</Button>
                <Progress percent={progress.percent} size="small" style={{ width: 200 }} />
                <Text type="secondary">{progress.current}/{progress.total}</Text>
              </>
            ) : (
              <Button type="primary" icon={<SearchOutlined />} onClick={handleSearch}>
                查询商品
              </Button>
            )}
            <Button icon={<DownloadOutlined />} onClick={handleExportCSV} disabled={data.length === 0}>
              导出CSV {selectedRowKeys.length > 0 && `(${selectedRowKeys.length})`}
            </Button>
            <Button icon={<CloudSyncOutlined />} onClick={handleSyncToLocal} disabled={data.length === 0}>
              同步到本地 {selectedRowKeys.length > 0 && `(${selectedRowKeys.length})`}
            </Button>
          </Space>
        </Space>
      </Card>

      <Card title={`查询结果 (${data.length}) ${selectedRowKeys.length > 0 ? `- 已选 ${selectedRowKeys.length}` : ''}`}>
        <Table
          dataSource={data}
          columns={columns}
          rowKey="sku"
          loading={loading}
          rowSelection={rowSelection}
          pagination={{ pageSize: 50, showTotal: t => `共 ${t} 条`, showSizeChanger: true }}
          scroll={{ x: 1000 }}
          size="small"
        />
      </Card>

      {/* 导出字段选择弹窗 */}
      <Modal
        title="选择导出字段"
        open={exportModalOpen}
        onOk={doExportCSV}
        onCancel={() => setExportModalOpen(false)}
        okText="导出"
        cancelText="取消"
      >
        <div style={{ marginBottom: 8 }}>请选择要导出的字段：</div>
        <Checkbox.Group
          value={exportFields}
          onChange={(values) => setExportFields(values as string[])}
        >
          <Space direction="vertical">
            {EXPORT_FIELDS.map(field => (
              <Checkbox key={field.key} value={field.key}>
                {field.label}
              </Checkbox>
            ))}
          </Space>
        </Checkbox.Group>
      </Modal>

      {/* 同步到本地弹窗 */}
      <Modal
        title="同步到本地"
        open={syncModalOpen}
        onOk={doSyncToLocal}
        onCancel={() => setSyncModalOpen(false)}
        okText="同步"
        cancelText="取消"
      >
        <div style={{ marginBottom: 16 }}>
          将 {selectedRowKeys.length > 0 ? selectedRowKeys.length : data.length} 个商品同步到本地店铺
        </div>
        <Space>
          <span>选择目标店铺：</span>
          <Select
            style={{ width: 250 }}
            placeholder="请选择店铺"
            value={selectedShop || undefined}
            onChange={setSelectedShop}
            options={shops.map(s => ({ value: s.id, label: s.name }))}
          />
        </Space>
      </Modal>
    </div>
  );
}