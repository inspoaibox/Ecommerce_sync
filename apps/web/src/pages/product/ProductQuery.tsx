import { useState, useEffect, useRef } from 'react';
import { Card, Select, Input, Button, Table, Space, message, Checkbox, Modal, Progress, Typography, Radio } from 'antd';
import { SearchOutlined, DownloadOutlined, CloudSyncOutlined } from '@ant-design/icons';
import { channelApi, shopApi, productApi } from '@/services/api';

const { TextArea } = Input;
const { Text } = Typography;

// 可导出的字段配置
const EXPORT_FIELDS = [
  { key: 'sku', label: 'SKU', default: true },
  { key: 'title', label: '标题', default: true },
  { key: 'price', label: '原价', default: true },
  { key: 'discountedPrice', label: '优惠价', default: true },
  { key: 'stock', label: '库存', default: true },
  { key: 'shippingFee', label: '运费', default: true },
  { key: 'totalPrice', label: '总价', default: true },
  { key: 'discountedTotalPrice', label: '优惠总价', default: true },
  { key: 'currency', label: '货币', default: false },
  { key: 'mapPrice', label: 'MAP价格', default: false },
  { key: 'buyerStock', label: '买家库存', default: false },
];

// 渠道速率限制配置
const CHANNEL_RATE_LIMITS: Record<string, { batchSize: number; batchDelay: number }> = {
  gigacloud: { batchSize: 200, batchDelay: 1500 },
  saleyee: { batchSize: 30, batchDelay: 1000 },
  // 默认配置
  default: { batchSize: 50, batchDelay: 1500 },
};

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
  const [syncMode, setSyncMode] = useState<'selected' | 'all'>('selected'); // 同步模式：勾选的 或 全部
  const [exportFields, setExportFields] = useState<string[]>(
    EXPORT_FIELDS.filter(f => f.default).map(f => f.key)
  );
  const [progress, setProgress] = useState({ current: 0, total: 0, percent: 0 });
  const abortRef = useRef(false);

  // 赛盈云仓特有参数
  const [warehouseCode, setWarehouseCode] = useState<string>('SZ0001'); // 默认US区域
  const [priceType, setPriceType] = useState<'shipping' | 'pickup'>('shipping'); // 默认包邮价格
  const [warehouses, setWarehouses] = useState<any[]>([]); // 区域列表
  const [fetchingWarehouses, setFetchingWarehouses] = useState(false);

  useEffect(() => {
    loadChannels();
    loadShops();
  }, []);

  // 当选择赛盈云仓时，加载区域列表
  useEffect(() => {
    if (selectedChannel) {
      const channel = channels.find(c => c.id === selectedChannel);
      if (channel?.type === 'saleyee') {
        loadWarehouses(selectedChannel);
      }
    }
  }, [selectedChannel, channels]);

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

  const loadWarehouses = async (channelId: string) => {
    try {
      const res: any = await channelApi.getWarehouses(channelId);
      if (res.success && res.data) {
        setWarehouses(res.data);
        // 如果有区域数据，设置第一个为默认值
        if (res.data.length > 0 && !warehouseCode) {
          setWarehouseCode(res.data[0].warehouseCode);
        }
      }
    } catch (e) {
      console.error(e);
    }
  };

  const handleFetchWarehouses = async () => {
    if (!selectedChannel) {
      message.warning('请先选择渠道');
      return;
    }

    setFetchingWarehouses(true);
    try {
      const res: any = await channelApi.fetchWarehouses(selectedChannel);
      if (res.success) {
        message.success(res.message || '获取区域成功');
        // 重新加载区域列表
        await loadWarehouses(selectedChannel);
      } else {
        message.error(res.message || '获取区域失败');
      }
    } catch (e: any) {
      message.error(e.message || '获取区域失败');
    } finally {
      setFetchingWarehouses(false);
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

    // 获取当前渠道的速率限制配置
    const channel = channels.find(c => c.id === selectedChannel);
    const channelType = channel?.type || 'default';
    const rateLimit = CHANNEL_RATE_LIMITS[channelType] || CHANNEL_RATE_LIMITS.default;
    const { batchSize, batchDelay } = rateLimit;

    // 分批次
    const batches: string[][] = [];
    for (let i = 0; i < skus.length; i += batchSize) {
      batches.push(skus.slice(i, i + batchSize));
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
        // 根据渠道类型传递不同的参数
        const queryParams: any = { skus: batches[i] };
        if (channelType === 'saleyee') {
          queryParams.warehouseCode = warehouseCode;
          queryParams.priceType = priceType;
        }

        const res: any = await channelApi.queryProducts(selectedChannel, queryParams.skus, queryParams);
        if (res.success && res.data) {
          // 计算总价和优惠总价
          const productsWithTotal = res.data.map((p: any) => {
            const shippingFee = p.extraFields?.shippingFee || 0;
            const discountedPrice = p.extraFields?.discountedPrice;
            return {
              ...p,
              totalPrice: (p.price || 0) + shippingFee,
              discountedTotalPrice: discountedPrice != null ? discountedPrice + shippingFee : null,
            };
          });
          allProducts.push(...productsWithTotal);
          setData([...allProducts]);
        }
      } catch (e: any) {
        console.error(`Batch ${i + 1} failed:`, e);
      }

      const processed = Math.min((i + 1) * batchSize, skus.length);
      setProgress({
        current: processed,
        total: skus.length,
        percent: Math.round((processed / skus.length) * 100),
      });

      // 批次间延迟，避免触发速率限制
      if (i < batches.length - 1 && !abortRef.current) {
        await new Promise(resolve => setTimeout(resolve, batchDelay));
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
        if (key === 'shippingFee' || key === 'mapPrice' || key === 'buyerStock' || key === 'discountedPrice') {
          return item.extraFields?.[key] ?? '';
        }
        if (key === 'totalPrice') {
          return item.totalPrice ?? '';
        }
        if (key === 'discountedTotalPrice') {
          return item.discountedTotalPrice ?? '';
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
    if (data.length === 0) {
      message.warning('没有数据可同步');
      return;
    }
    // 默认模式：如果有勾选则为勾选模式，否则为全部模式
    setSyncMode(selectedRowKeys.length > 0 ? 'selected' : 'all');
    setSyncModalOpen(true);
  };

  const doSyncToLocal = async () => {
    const syncData = syncMode === 'selected'
      ? data.filter(d => selectedRowKeys.includes(d.sku))
      : data;

    if (syncData.length === 0) {
      message.warning('没有数据可同步');
      return;
    }

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

  // 状态文本映射
  const getStatusText = (status: string) => {
    const statusMap: Record<string, { text: string; color: string }> = {
      available: { text: '可用', color: '#52c41a' },
      no_price_data: { text: '无价格数据', color: '#ff4d4f' },
      unavailable: { text: '不可用', color: '#faad14' },
      price_not_exist: { text: '价格不存在', color: '#ff4d4f' },
    };
    return statusMap[status] || { text: status, color: '#999' };
  };

  const columns = [
    { title: 'SKU', dataIndex: 'sku', key: 'sku', width: 150, fixed: 'left' as const },
    { title: '标题', dataIndex: 'title', key: 'title', ellipsis: true, width: 200 },
    { title: '状态', key: 'status', width: 110, render: (_: any, r: any) => {
      const status = r.extraFields?.availabilityStatus || 'available';
      const { text, color } = getStatusText(status);
      return <span style={{ color }}>{text}</span>;
    }},
    { title: '原价', dataIndex: 'price', key: 'price', width: 80, render: (v: number, r: any) => v != null ? `${r.currency || '$'}${v.toFixed(2)}` : '-' },
    { title: '优惠价', key: 'discountedPrice', width: 90, render: (_: any, r: any) => {
      const dp = r.extraFields?.discountedPrice;
      return dp != null ? <span style={{ color: '#f5222d' }}>${dp.toFixed(2)}</span> : '-';
    }},
    { title: '库存', dataIndex: 'stock', key: 'stock', width: 80 },
    { title: '运费', key: 'shippingFee', width: 80, render: (_: any, r: any) => {
      const fee = r.extraFields?.shippingFee;
      return fee != null ? `$${fee.toFixed(2)}` : '-';
    }},
    { title: '总价', key: 'totalPrice', width: 90, render: (_: any, r: any) => {
      return r.totalPrice != null ? `$${r.totalPrice.toFixed(2)}` : '-';
    }},
    { title: '优惠总价', key: 'discountedTotalPrice', width: 100, render: (_: any, r: any) => {
      return r.discountedTotalPrice != null ? <span style={{ color: '#f5222d' }}>${r.discountedTotalPrice.toFixed(2)}</span> : '-';
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

            {/* 赛盈云仓特有选项 */}
            {selectedChannel && channels.find(c => c.id === selectedChannel)?.type === 'saleyee' && (
              <>
                <span style={{ color: '#666' }}>区域：</span>
                <Select
                  style={{ width: 200 }}
                  value={warehouseCode}
                  onChange={setWarehouseCode}
                  options={warehouses.length > 0
                    ? warehouses.map(w => ({
                        value: w.warehouseCode,
                        label: `${w.warehouseName} (${w.warehouseCode})`
                      }))
                    : [{ value: 'SZ0001', label: 'US (美国)' }]
                  }
                  notFoundContent={warehouses.length === 0 ? '暂无区域数据，请点击更新' : undefined}
                />
                <Button
                  type="link"
                  size="small"
                  loading={fetchingWarehouses}
                  onClick={handleFetchWarehouses}
                  style={{ padding: '0 8px' }}
                >
                  更新
                </Button>
                <span style={{ color: '#666' }}>价格类型：</span>
                <Select
                  style={{ width: 150 }}
                  value={priceType}
                  onChange={setPriceType}
                  options={[
                    { value: 'shipping', label: '包邮价格' },
                    { value: 'pickup', label: '自提价格' },
                  ]}
                />
              </>
            )}
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
              {selectedChannel ? (() => {
                const channel = channels.find(c => c.id === selectedChannel);
                const channelType = channel?.type || 'default';
                const rateLimit = CHANNEL_RATE_LIMITS[channelType] || CHANNEL_RATE_LIMITS.default;
                return `系统将自动分批查询，每批${rateLimit.batchSize}个，间隔${rateLimit.batchDelay/1000}秒`;
              })() : '请先选择渠道'}
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
        <Space direction="vertical" style={{ width: '100%' }} size="middle">
          <div>
            <div style={{ marginBottom: 8 }}>选择同步范围：</div>
            <Radio.Group value={syncMode} onChange={(e) => setSyncMode(e.target.value)}>
              <Space direction="vertical">
                <Radio value="selected" disabled={selectedRowKeys.length === 0}>
                  同步勾选的商品 ({selectedRowKeys.length} 个)
                </Radio>
                <Radio value="all">
                  同步所有商品 ({data.length} 个)
                </Radio>
              </Space>
            </Radio.Group>
          </div>
          <div>
            <div style={{ marginBottom: 8 }}>选择目标店铺（可选）：</div>
            <Select
              style={{ width: '100%' }}
              placeholder="不选择则不分配店铺"
              allowClear
              value={selectedShop || undefined}
              onChange={setSelectedShop}
              options={shops.map(s => ({ value: s.id, label: s.name }))}
            />
          </div>
        </Space>
      </Modal>
    </div>
  );
}