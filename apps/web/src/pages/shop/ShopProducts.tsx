import { useEffect, useState } from 'react';
import { useParams } from 'react-router-dom';
import { Table, Tag, Input, Card, Space, Typography, Button, Popconfirm, message, Modal, Dropdown, Upload, Select } from 'antd';
import { DeleteOutlined, DownloadOutlined, DownOutlined, SyncOutlined, DollarOutlined, InboxOutlined, UploadOutlined, EditOutlined, ImportOutlined } from '@ant-design/icons';
import { productApi, shopApi, channelApi } from '@/services/api';
import dayjs from 'dayjs';
import * as XLSX from 'xlsx';

const { Title } = Typography;
const { TextArea } = Input;

export default function ShopProducts() {
  const { shopId } = useParams<{ shopId: string }>();
  const [shop, setShop] = useState<any>(null);
  const [data, setData] = useState<any[]>([]);
  const [loading, setLoading] = useState(false);
  const [pagination, setPagination] = useState({ current: 1, pageSize: 20, total: 0 });
  const [searchSkus, setSearchSkus] = useState<string[]>([]);
  const [selectedRowKeys, setSelectedRowKeys] = useState<string[]>([]);
  const [batchSearchModal, setBatchSearchModal] = useState(false);
  const [batchSkuInput, setBatchSkuInput] = useState('');
  const [syncMissingModal, setSyncMissingModal] = useState(false);
  const [syncMissingInput, setSyncMissingInput] = useState('');
  const [syncMissingLoading, setSyncMissingLoading] = useState(false);
  const [syncLoading, setSyncLoading] = useState(false);
  const [platformSkuModal, setPlatformSkuModal] = useState(false);
  const [platformSkuInput, setPlatformSkuInput] = useState('');
  const [platformSkuLoading, setPlatformSkuLoading] = useState(false);
  const [editingPlatformSku, setEditingPlatformSku] = useState<{ id: string; sku: string; platformSku: string } | null>(null);
  const [importProductModal, setImportProductModal] = useState(false);
  const [importProductInput, setImportProductInput] = useState('');
  const [importProductLoading, setImportProductLoading] = useState(false);
  const [channels, setChannels] = useState<any[]>([]);
  const [selectedChannelId, setSelectedChannelId] = useState<string>('');

  useEffect(() => {
    if (shopId) {
      loadShop();
    }
    loadChannels();
  }, [shopId]);

  const loadChannels = async () => {
    try {
      const res: any = await channelApi.list({ pageSize: 100 });
      setChannels(res.data || []);
    } catch (e) {
      console.error(e);
    }
  };

  useEffect(() => {
    if (shopId) {
      loadData();
    }
  }, [shopId, pagination.current, pagination.pageSize, searchSkus]);

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
        skus: searchSkus.length > 0 ? searchSkus : undefined,
      });
      setData(res.data || []);
      setPagination(p => ({ ...p, total: res.total }));
    } finally {
      setLoading(false);
    }
  };

  const handleDeleteSelected = async () => {
    if (selectedRowKeys.length === 0) {
      message.warning('请选择要删除的商品');
      return;
    }
    try {
      await productApi.batchDelete(selectedRowKeys);
      message.success(`成功删除 ${selectedRowKeys.length} 个商品`);
      setSelectedRowKeys([]);
      loadData();
    } catch (e: any) {
      message.error(e.message || '删除失败');
    }
  };

  const handleDeleteAll = async () => {
    try {
      const res: any = await shopApi.deleteAllProducts(shopId!);
      message.success(res.message || `成功删除所有商品`);
      setSelectedRowKeys([]);
      loadData();
    } catch (e: any) {
      message.error(e.message || '删除失败');
    }
  };

  // 批量搜索
  const handleBatchSearch = () => {
    const skus = batchSkuInput
      .split(/[\n,，\s]+/)
      .map(s => s.trim())
      .filter(s => s.length > 0);
    setSearchSkus(skus);
    setPagination(p => ({ ...p, current: 1 }));
    setBatchSearchModal(false);
    if (skus.length > 0) {
      message.success(`正在搜索 ${skus.length} 个SKU`);
    }
  };

  const clearSearch = () => {
    setSearchSkus([]);
    setBatchSkuInput('');
    setPagination(p => ({ ...p, current: 1 }));
  };

  // 导出为 CSV
  const exportToCSV = (products: any[], filename: string) => {
    const headers = ['SKU', '标题', '本地价格', '平台价格', '本地库存', '平台库存', '同步状态', '更新时间'];
    const rows = products.map(p => [
      p.sku,
      `"${(p.title || '').replace(/"/g, '""')}"`,
      p.originalPrice,
      p.finalPrice,
      p.originalStock,
      p.finalStock,
      p.syncStatus,
      dayjs(p.updatedAt).format('YYYY-MM-DD HH:mm:ss'),
    ]);

    const csvContent = [headers.join(','), ...rows.map(r => r.join(','))].join('\n');
    const blob = new Blob(['\ufeff' + csvContent], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    link.href = URL.createObjectURL(blob);
    link.download = filename;
    link.click();
  };

  // 导出选中
  const handleExportSelected = () => {
    if (selectedRowKeys.length === 0) {
      message.warning('请选择要导出的商品');
      return;
    }
    const selectedProducts = data.filter(p => selectedRowKeys.includes(p.id));
    exportToCSV(selectedProducts, `${shop?.name || 'shop'}_selected_${selectedRowKeys.length}.csv`);
    message.success(`已导出 ${selectedProducts.length} 个商品`);
  };

  // 导出全部（使用专门的导出 API，不受分页限制）
  const handleExportAll = async () => {
    message.loading({ content: '正在获取所有商品数据...', key: 'export' });
    try {
      const allProducts: any = await productApi.export({
        shopId,
        skus: searchSkus.length > 0 ? searchSkus : undefined,
      });
      exportToCSV(allProducts, `${shop?.name || 'shop'}_all_${allProducts.length}.csv`);
      message.success({ content: `已导出 ${allProducts.length} 个商品`, key: 'export' });
    } catch (e: any) {
      message.error({ content: e.message || '导出失败', key: 'export' });
    }
  };

  // 补充同步缺失的 SKU
  const handleSyncMissing = async () => {
    const skus = syncMissingInput
      .split(/[\n,，\s]+/)
      .map(s => s.trim())
      .filter(s => s.length > 0);

    if (skus.length === 0) {
      message.warning('请输入要补充同步的 SKU');
      return;
    }

    setSyncMissingLoading(true);
    try {
      const res: any = await shopApi.syncMissingSkus(shopId!, skus);
      message.success(res.message);
      setSyncMissingModal(false);
      setSyncMissingInput('');
      loadData();
    } catch (e: any) {
      message.error(e.message || '补充同步失败');
    } finally {
      setSyncMissingLoading(false);
    }
  };

  // 同步价格到沃尔玛
  const handleSyncPrice = async () => {
    if (selectedRowKeys.length === 0) {
      message.warning('请选择要同步价格的商品');
      return;
    }

    setSyncLoading(true);
    try {
      const res: any = await shopApi.syncToWalmart(shopId!, {
        productIds: selectedRowKeys,
        syncType: 'price',
      });
      message.success(res.message || `已提交价格同步任务，Feed ID: ${res.feedId}`);
      loadData();
    } catch (e: any) {
      message.error(e.message || '同步价格失败');
    } finally {
      setSyncLoading(false);
    }
  };

  // 同步库存到沃尔玛
  const handleSyncInventory = async () => {
    if (selectedRowKeys.length === 0) {
      message.warning('请选择要同步库存的商品');
      return;
    }

    setSyncLoading(true);
    try {
      const res: any = await shopApi.syncToWalmart(shopId!, {
        productIds: selectedRowKeys,
        syncType: 'inventory',
      });
      message.success(res.message || `已提交库存同步任务，Feed ID: ${res.feedId}`);
      loadData();
    } catch (e: any) {
      message.error(e.message || '同步库存失败');
    } finally {
      setSyncLoading(false);
    }
  };

  // 同步价格+库存到沃尔玛
  const handleSyncBoth = async () => {
    if (selectedRowKeys.length === 0) {
      message.warning('请选择要同步的商品');
      return;
    }

    setSyncLoading(true);
    try {
      const res: any = await shopApi.syncToWalmart(shopId!, {
        productIds: selectedRowKeys,
        syncType: 'both',
      });
      message.success(res.message || `已提交同步任务，Feed ID: ${res.feedId}`);
      loadData();
    } catch (e: any) {
      message.error(e.message || '同步失败');
    } finally {
      setSyncLoading(false);
    }
  };

  // 导入平台SKU映射
  const handleImportPlatformSku = async () => {
    const lines = platformSkuInput.split('\n').filter(l => l.trim());
    const mappings: { sku: string; platformSku: string }[] = [];
    
    for (const line of lines) {
      const parts = line.split(/[,，\t]/).map(s => s.trim());
      if (parts.length >= 2 && parts[0] && parts[1]) {
        mappings.push({ sku: parts[0], platformSku: parts[1] });
      }
    }

    if (mappings.length === 0) {
      message.warning('请输入有效的SKU映射，格式：原始SKU,平台SKU');
      return;
    }

    setPlatformSkuLoading(true);
    try {
      const res: any = await productApi.importPlatformSku(shopId!, mappings);
      message.success(res.message || `成功更新 ${res.updated} 个商品的平台SKU`);
      setPlatformSkuModal(false);
      setPlatformSkuInput('');
      loadData();
    } catch (e: any) {
      message.error(e.message || '导入失败');
    } finally {
      setPlatformSkuLoading(false);
    }
  };

  // 单个编辑平台SKU
  const handleSavePlatformSku = async () => {
    if (!editingPlatformSku) return;
    try {
      await productApi.updatePlatformSku(editingPlatformSku.id, editingPlatformSku.platformSku);
      message.success('平台SKU已更新');
      setEditingPlatformSku(null);
      loadData();
    } catch (e: any) {
      message.error(e.message || '更新失败');
    }
  };

  // 处理文件上传（支持 CSV/TXT/XLSX）- 用于平台SKU映射
  const handleFileUpload = (file: File, setInput: (v: string) => void, columns: number = 2) => {
    const isExcel = file.name.endsWith('.xlsx') || file.name.endsWith('.xls');
    
    if (isExcel) {
      const reader = new FileReader();
      reader.onload = (e) => {
        try {
          const data = new Uint8Array(e.target?.result as ArrayBuffer);
          const workbook = XLSX.read(data, { type: 'array' });
          const sheetName = workbook.SheetNames[0];
          const worksheet = workbook.Sheets[sheetName];
          const jsonData = XLSX.utils.sheet_to_json(worksheet, { header: 1 }) as any[][];
          
          const lines = jsonData
            .filter(row => row.length >= columns && row[0])
            .map(row => row.slice(0, columns).join(','));
          
          setInput(lines.join('\n'));
          message.success(`已解析 ${lines.length} 条数据`);
        } catch (err) {
          message.error('Excel文件解析失败');
        }
      };
      reader.readAsArrayBuffer(file);
    } else {
      const reader = new FileReader();
      reader.onload = (e) => {
        const text = e.target?.result as string;
        setInput(text);
      };
      reader.readAsText(file);
    }
    return false;
  };

  // 导入平台产品
  const handleImportProducts = async () => {
    if (!selectedChannelId) {
      message.warning('请选择来源渠道');
      return;
    }

    const lines = importProductInput.split('\n').filter(l => l.trim());
    const products: { sku: string; platformSku?: string }[] = [];
    
    for (const line of lines) {
      const parts = line.split(/[,，\t]/).map(s => s.trim());
      if (parts.length >= 1 && parts[0]) {
        products.push({ 
          sku: parts[0], 
          platformSku: parts[1] || undefined 
        });
      }
    }

    if (products.length === 0) {
      message.warning('请输入有效的产品数据');
      return;
    }

    setImportProductLoading(true);
    try {
      const res: any = await productApi.importProducts(shopId!, {
        channelId: selectedChannelId,
        products,
      });
      message.success(res.message || `成功导入 ${res.created} 个商品`);
      setImportProductModal(false);
      setImportProductInput('');
      setSelectedChannelId('');
      loadData();
    } catch (e: any) {
      message.error(e.message || '导入失败');
    } finally {
      setImportProductLoading(false);
    }
  };

  const columns = [
    { title: 'SKU', dataIndex: 'sku', key: 'sku', width: 120 },
    { 
      title: '平台SKU', 
      dataIndex: 'platformSku', 
      key: 'platformSku', 
      width: 140,
      render: (v: string, record: any) => (
        <Space size="small">
          <span style={{ color: v ? undefined : '#999' }}>{v || '-'}</span>
          <Button 
            type="link" 
            size="small" 
            icon={<EditOutlined />} 
            onClick={() => setEditingPlatformSku({ id: record.id, sku: record.sku, platformSku: v || '' })}
          />
        </Space>
      )
    },
    { title: '标题', dataIndex: 'title', key: 'title', ellipsis: true },
    { title: '本地价格', dataIndex: 'originalPrice', key: 'originalPrice', width: 90, render: (v: number) => `$${v}` },
    { title: '平台价格', dataIndex: 'finalPrice', key: 'finalPrice', width: 90, render: (v: number) => `$${v}` },
    { title: '本地库存', dataIndex: 'originalStock', key: 'originalStock', width: 90 },
    { title: '平台库存', dataIndex: 'finalStock', key: 'finalStock', width: 90 },
    { title: '同步状态', dataIndex: 'syncStatus', key: 'syncStatus', width: 100, render: (s: string) => (
      <Tag color={s === 'synced' ? 'green' : s === 'failed' ? 'red' : 'default'}>{s}</Tag>
    )},
    { title: '更新时间', dataIndex: 'updatedAt', key: 'updatedAt', width: 140, render: (t: string) => dayjs(t).format('MM-DD HH:mm') },
    {
      title: '操作',
      key: 'action',
      width: 80,
      render: (_: any, record: any) => (
        <Popconfirm title="确定删除此商品?" onConfirm={async () => {
          try {
            await productApi.delete(record.id);
            message.success('删除成功');
            loadData();
          } catch (e: any) {
            message.error(e.message || '删除失败');
          }
        }}>
          <Button type="link" size="small" danger icon={<DeleteOutlined />}>删除</Button>
        </Popconfirm>
      ),
    },
  ];

  const rowSelection = {
    selectedRowKeys,
    onChange: (keys: React.Key[]) => setSelectedRowKeys(keys as string[]),
  };

  const exportMenuItems = [
    { key: 'selected', label: `导出选中 (${selectedRowKeys.length})`, disabled: selectedRowKeys.length === 0 },
    { key: 'all', label: `导出全部 (${pagination.total})` },
  ];

  return (
    <div>
      <Title level={4} style={{ marginBottom: 16 }}>
        {shop?.name || '店铺'} - 商品管理 ({pagination.total} 个商品)
      </Title>
      <Card size="small" style={{ marginBottom: 16 }}>
        <Space wrap>
          <Input.Search
            placeholder="搜索单个SKU"
            allowClear
            style={{ width: 200 }}
            onSearch={v => { 
              if (v) {
                setSearchSkus([v]);
              } else {
                setSearchSkus([]);
              }
              setPagination(p => ({ ...p, current: 1 })); 
            }}
          />
          <Button onClick={() => setBatchSearchModal(true)}>批量搜索SKU</Button>
          {searchSkus.length > 0 && (
            <Tag closable onClose={clearSearch} color="blue">
              搜索中: {searchSkus.length} 个SKU
            </Tag>
          )}
          <Popconfirm 
            title={`确定删除选中的 ${selectedRowKeys.length} 个商品?`} 
            onConfirm={handleDeleteSelected}
            disabled={selectedRowKeys.length === 0}
          >
            <Button 
              danger 
              icon={<DeleteOutlined />} 
              disabled={selectedRowKeys.length === 0}
            >
              删除选中 ({selectedRowKeys.length})
            </Button>
          </Popconfirm>
          <Popconfirm 
            title={`确定删除该店铺的所有商品? 此操作不可恢复!`} 
            onConfirm={handleDeleteAll}
            okText="确定删除"
            okButtonProps={{ danger: true }}
          >
            <Button danger icon={<DeleteOutlined />}>
              删除所有商品
            </Button>
          </Popconfirm>
          <Dropdown
            menu={{
              items: exportMenuItems,
              onClick: ({ key }) => {
                if (key === 'selected') handleExportSelected();
                else if (key === 'all') handleExportAll();
              },
            }}
          >
            <Button icon={<DownloadOutlined />}>
              导出 <DownOutlined />
            </Button>
          </Dropdown>
          <Button type="primary" onClick={() => setSyncMissingModal(true)}>
            补充同步SKU
          </Button>
          <Button icon={<UploadOutlined />} onClick={() => setPlatformSkuModal(true)}>
            导入平台SKU
          </Button>
          <Button icon={<ImportOutlined />} onClick={() => setImportProductModal(true)}>
            导入平台产品
          </Button>
          <Dropdown
            menu={{
              items: [
                {
                  key: 'price',
                  label: '同步价格到沃尔玛',
                  icon: <DollarOutlined />,
                  disabled: selectedRowKeys.length === 0
                },
                {
                  key: 'inventory',
                  label: '同步库存到沃尔玛',
                  icon: <InboxOutlined />,
                  disabled: selectedRowKeys.length === 0
                },
                {
                  key: 'both',
                  label: '同步价格+库存到沃尔玛',
                  icon: <SyncOutlined />,
                  disabled: selectedRowKeys.length === 0
                },
              ],
              onClick: ({ key }) => {
                if (key === 'price') handleSyncPrice();
                else if (key === 'inventory') handleSyncInventory();
                else if (key === 'both') handleSyncBoth();
              },
            }}
          >
            <Button
              type="primary"
              icon={<SyncOutlined />}
              loading={syncLoading}
              disabled={selectedRowKeys.length === 0}
            >
              同步到沃尔玛 ({selectedRowKeys.length}) <DownOutlined />
            </Button>
          </Dropdown>
        </Space>
      </Card>
      <Table
        dataSource={data}
        columns={columns}
        rowKey="id"
        loading={loading}
        rowSelection={rowSelection}
        pagination={{
          ...pagination,
          showSizeChanger: true,
          showTotal: t => `共 ${t} 条`,
        }}
        onChange={p => setPagination(prev => ({ ...prev, current: p.current || 1, pageSize: p.pageSize || 20 }))}
        scroll={{ x: 1000 }}
      />

      <Modal
        title="批量搜索SKU"
        open={batchSearchModal}
        onCancel={() => setBatchSearchModal(false)}
        onOk={handleBatchSearch}
        okText="搜索"
      >
        <TextArea
          rows={10}
          placeholder="输入多个SKU，每行一个，或用逗号分隔"
          value={batchSkuInput}
          onChange={e => setBatchSkuInput(e.target.value)}
        />
        <div style={{ marginTop: 8, color: '#999' }}>
          支持换行、逗号、空格分隔，当前输入: {batchSkuInput.split(/[\n,，\s]+/).filter(s => s.trim()).length} 个SKU
        </div>
      </Modal>

      <Modal
        title="补充同步缺失的SKU"
        open={syncMissingModal}
        onCancel={() => { setSyncMissingModal(false); setSyncMissingInput(''); }}
        onOk={handleSyncMissing}
        okText="开始同步"
        confirmLoading={syncMissingLoading}
      >
        <p style={{ marginBottom: 12, color: '#666' }}>
          输入缺失的 SKU 列表，系统将逐个查询 Walmart API 并保存到本地。
          <br />
          <small>适用于分页同步时遗漏的商品。</small>
        </p>
        <TextArea
          rows={10}
          placeholder="输入多个SKU，每行一个，或用逗号分隔"
          value={syncMissingInput}
          onChange={e => setSyncMissingInput(e.target.value)}
        />
        <div style={{ marginTop: 8, color: '#999' }}>
          当前输入: {syncMissingInput.split(/[\n,，\s]+/).filter(s => s.trim()).length} 个SKU
        </div>
      </Modal>

      <Modal
        title="导入平台SKU映射"
        open={platformSkuModal}
        onCancel={() => { setPlatformSkuModal(false); setPlatformSkuInput(''); }}
        onOk={handleImportPlatformSku}
        okText="导入"
        confirmLoading={platformSkuLoading}
        width={600}
      >
        <p style={{ marginBottom: 12, color: '#666' }}>
          格式：每行一条映射，原始SKU和平台SKU用逗号或Tab分隔
          <br />
          <small>示例：ABC123,ABC123-US</small>
        </p>
        <Upload.Dragger
          accept=".csv,.txt,.xlsx,.xls"
          showUploadList={false}
          beforeUpload={(file) => handleFileUpload(file, setPlatformSkuInput, 2)}
          style={{ marginBottom: 12 }}
        >
          <p className="ant-upload-drag-icon"><UploadOutlined style={{ fontSize: 32, color: '#1890ff' }} /></p>
          <p>点击或拖拽文件到此处（支持 CSV/TXT/Excel）</p>
        </Upload.Dragger>
        <TextArea
          rows={10}
          placeholder="原始SKU,平台SKU&#10;ABC123,ABC123-US&#10;DEF456,MYSTORE-DEF456"
          value={platformSkuInput}
          onChange={e => setPlatformSkuInput(e.target.value)}
        />
        <div style={{ marginTop: 8, color: '#999' }}>
          当前输入: {platformSkuInput.split('\n').filter(l => l.trim() && l.includes(',')).length} 条映射
        </div>
      </Modal>

      <Modal
        title={`编辑平台SKU - ${editingPlatformSku?.sku}`}
        open={!!editingPlatformSku}
        onCancel={() => setEditingPlatformSku(null)}
        onOk={handleSavePlatformSku}
        okText="保存"
      >
        <div style={{ marginBottom: 8 }}>原始SKU: <strong>{editingPlatformSku?.sku}</strong></div>
        <Input
          placeholder="输入平台SKU，留空则使用原始SKU"
          value={editingPlatformSku?.platformSku || ''}
          onChange={e => setEditingPlatformSku(prev => prev ? { ...prev, platformSku: e.target.value } : null)}
        />
      </Modal>

      <Modal
        title="导入平台产品"
        open={importProductModal}
        onCancel={() => { setImportProductModal(false); setImportProductInput(''); setSelectedChannelId(''); }}
        onOk={handleImportProducts}
        okText="导入"
        confirmLoading={importProductLoading}
        width={650}
      >
        <p style={{ marginBottom: 12, color: '#666' }}>
          从沃尔玛下载的商品表格导入到本店铺，用于自动同步时获取渠道最新价格库存。
          <br />
          <small>格式：SKU, 平台SKU（可选）。每行一条，用逗号或Tab分隔。</small>
        </p>
        <div style={{ marginBottom: 12 }}>
          <span style={{ marginRight: 8 }}>来源渠道：</span>
          <Select
            style={{ width: 200 }}
            placeholder="选择来源渠道"
            value={selectedChannelId || undefined}
            onChange={setSelectedChannelId}
            options={channels.map(c => ({ value: c.id, label: c.name }))}
          />
        </div>
        <Upload.Dragger
          accept=".csv,.txt,.xlsx,.xls"
          showUploadList={false}
          beforeUpload={(file) => handleFileUpload(file, setImportProductInput, 2)}
          style={{ marginBottom: 12 }}
        >
          <p className="ant-upload-drag-icon"><UploadOutlined style={{ fontSize: 32, color: '#1890ff' }} /></p>
          <p>点击或拖拽文件到此处（支持 CSV/TXT/Excel）</p>
        </Upload.Dragger>
        <TextArea
          rows={10}
          placeholder="SKU,平台SKU&#10;ABC123,ABC123-US&#10;DEF456&#10;GHI789,MYSTORE-GHI789"
          value={importProductInput}
          onChange={e => setImportProductInput(e.target.value)}
        />
        <div style={{ marginTop: 8, color: '#999' }}>
          当前输入: {importProductInput.split('\n').filter(l => l.trim()).length} 条产品
        </div>
      </Modal>
    </div>
  );
}
