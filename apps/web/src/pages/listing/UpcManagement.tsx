import { useState, useEffect } from 'react';
import { Card, Table, Button, Space, Input, Select, Tag, Modal, message, Statistic, Row, Col, Popconfirm, Dropdown } from 'antd';
import { PlusOutlined, DeleteOutlined, DownloadOutlined, ReloadOutlined, EditOutlined } from '@ant-design/icons';
import { upcApi } from '@/services/api';

const { TextArea } = Input;

export default function UpcManagement() {
  const [loading, setLoading] = useState(false);
  const [data, setData] = useState<any[]>([]);
  const [total, setTotal] = useState(0);
  const [page, setPage] = useState(1);
  const [pageSize, setPageSize] = useState(50);
  const [search, setSearch] = useState('');
  const [status, setStatus] = useState<'all' | 'used' | 'available'>('all');
  const [stats, setStats] = useState({ total: 0, used: 0, available: 0 });
  const [selectedRowKeys, setSelectedRowKeys] = useState<string[]>([]);
  const [selectedRows, setSelectedRows] = useState<any[]>([]);
  const [importModalVisible, setImportModalVisible] = useState(false);
  const [importText, setImportText] = useState('');
  const [importing, setImporting] = useState(false);

  useEffect(() => {
    loadStats();
    loadData();
  }, [page, pageSize, status]);

  const loadStats = async () => {
    try {
      const res: any = await upcApi.getStats();
      setStats(res);
    } catch (e) {
      console.error(e);
    }
  };

  const loadData = async () => {
    setLoading(true);
    try {
      const res: any = await upcApi.list({ page, pageSize, search, status });
      setData(res.data || []);
      setTotal(res.total || 0);
    } catch (e) {
      console.error(e);
    } finally {
      setLoading(false);
    }
  };

  const handleSearch = () => {
    setPage(1);
    loadData();
  };

  const handleImport = async () => {
    if (!importText.trim()) {
      message.warning('请输入 UPC 码');
      return;
    }
    setImporting(true);
    try {
      const upcCodes = importText.split(/[\r\n,;]+/).map(s => s.trim()).filter(Boolean);
      const res: any = await upcApi.import(upcCodes);
      message.success(res.message || `导入成功：${res.imported} 个`);
      setImportModalVisible(false);
      setImportText('');
      loadStats();
      loadData();
    } catch (e: any) {
      message.error(e.message || '导入失败');
    } finally {
      setImporting(false);
    }
  };

  const handleRelease = async (upcCode: string) => {
    try {
      await upcApi.release(upcCode);
      message.success('已释放');
      loadStats();
      loadData();
    } catch (e: any) {
      message.error(e.message || '操作失败');
    }
  };

  const handleDelete = async (id: string) => {
    try {
      await upcApi.delete(id);
      message.success('已删除');
      loadStats();
      loadData();
    } catch (e: any) {
      message.error(e.message || '删除失败');
    }
  };

  // 批量标记为已使用
  const handleBatchMarkUsed = async () => {
    if (selectedRowKeys.length === 0) {
      message.warning('请选择要操作的 UPC');
      return;
    }
    try {
      await upcApi.batchMarkUsed(selectedRowKeys);
      message.success(`已将 ${selectedRowKeys.length} 个 UPC 标记为已使用`);
      setSelectedRowKeys([]);
      setSelectedRows([]);
      loadStats();
      loadData();
    } catch (e: any) {
      message.error(e.message || '操作失败');
    }
  };

  // 批量释放（标记为未使用）
  const handleBatchRelease = async () => {
    if (selectedRowKeys.length === 0) {
      message.warning('请选择要释放的 UPC');
      return;
    }
    try {
      await upcApi.batchRelease(selectedRowKeys);
      message.success(`已释放 ${selectedRowKeys.length} 个 UPC`);
      setSelectedRowKeys([]);
      setSelectedRows([]);
      loadStats();
      loadData();
    } catch (e: any) {
      message.error(e.message || '操作失败');
    }
  };

  const handleBatchDelete = async () => {
    if (selectedRowKeys.length === 0) {
      message.warning('请选择要删除的 UPC');
      return;
    }
    try {
      await upcApi.batchDelete(selectedRowKeys);
      message.success(`已删除 ${selectedRowKeys.length} 个 UPC`);
      setSelectedRowKeys([]);
      setSelectedRows([]);
      loadStats();
      loadData();
    } catch (e: any) {
      message.error(e.message || '删除失败');
    }
  };


  // 导出为 Excel 格式（CSV）
  const exportToExcel = (items: any[], filename: string) => {
    // CSV 表头
    const headers = ['UPC码', '状态', '关联SKU', '使用时间', '创建时间'];
    const rows = items.map(item => [
      item.upcCode,
      item.isUsed ? '已使用' : '可用',
      item.productSku || '',
      item.usedAt ? new Date(item.usedAt).toLocaleString() : '',
      new Date(item.createdAt).toLocaleString(),
    ]);
    
    // 添加 BOM 以支持中文
    const BOM = '\uFEFF';
    const csvContent = BOM + [headers, ...rows].map(row => row.join(',')).join('\n');
    
    const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `${filename}.csv`;
    a.click();
    URL.revokeObjectURL(url);
  };

  // 导出选中的
  const handleExportSelected = () => {
    if (selectedRows.length === 0) {
      message.warning('请先选择要导出的 UPC');
      return;
    }
    exportToExcel(selectedRows, `upc_selected_${new Date().toISOString().slice(0, 10)}`);
    message.success(`已导出 ${selectedRows.length} 条记录`);
  };

  // 导出全部（按当前筛选条件）
  const handleExportAll = async () => {
    try {
      const res: any = await upcApi.export(status);
      if (!res || res.length === 0) {
        message.warning('没有可导出的数据');
        return;
      }
      const statusLabel = status === 'all' ? '全部' : status === 'used' ? '已使用' : '可用';
      exportToExcel(res, `upc_${statusLabel}_${new Date().toISOString().slice(0, 10)}`);
      message.success(`已导出 ${res.length} 条记录`);
    } catch (e: any) {
      message.error(e.message || '导出失败');
    }
  };

  const columns = [
    {
      title: 'UPC 码',
      dataIndex: 'upcCode',
      width: 180,
    },
    {
      title: '状态',
      dataIndex: 'isUsed',
      width: 100,
      render: (isUsed: boolean) => (
        <Tag color={isUsed ? 'red' : 'green'}>{isUsed ? '已使用' : '可用'}</Tag>
      ),
    },
    {
      title: '关联 SKU',
      dataIndex: 'productSku',
      width: 150,
      render: (sku: string) => sku || '-',
    },
    {
      title: '使用时间',
      dataIndex: 'usedAt',
      width: 180,
      render: (time: string) => (time ? new Date(time).toLocaleString() : '-'),
    },
    {
      title: '创建时间',
      dataIndex: 'createdAt',
      width: 180,
      render: (time: string) => new Date(time).toLocaleString(),
    },
    {
      title: '操作',
      width: 120,
      render: (_: any, record: any) => (
        <Space size="small">
          {record.isUsed && (
            <Popconfirm title="确定释放此 UPC？" onConfirm={() => handleRelease(record.upcCode)}>
              <Button type="link" size="small">释放</Button>
            </Popconfirm>
          )}
          <Popconfirm title="确定删除此 UPC？" onConfirm={() => handleDelete(record.id)}>
            <Button type="link" size="small" danger>删除</Button>
          </Popconfirm>
        </Space>
      ),
    },
  ];

  // 导出下拉菜单
  const exportMenuItems = [
    { key: 'selected', label: `导出选中 (${selectedRowKeys.length})`, disabled: selectedRowKeys.length === 0 },
    { key: 'all', label: '导出全部（当前筛选）' },
  ];

  const handleExportMenuClick = ({ key }: { key: string }) => {
    if (key === 'selected') {
      handleExportSelected();
    } else {
      handleExportAll();
    }
  };

  return (
    <div>
      {/* 统计卡片 */}
      <Row gutter={16} style={{ marginBottom: 16 }}>
        <Col span={8}>
          <Card>
            <Statistic title="总 UPC 数量" value={stats.total} valueStyle={{ color: '#1890ff' }} />
          </Card>
        </Col>
        <Col span={8}>
          <Card>
            <Statistic title="已使用" value={stats.used} valueStyle={{ color: '#ff4d4f' }} />
          </Card>
        </Col>
        <Col span={8}>
          <Card>
            <Statistic title="可用" value={stats.available} valueStyle={{ color: '#52c41a' }} />
          </Card>
        </Col>
      </Row>

      {/* 主表格 */}
      <Card
        title="UPC 池管理"
        extra={
          <Space>
            <Button icon={<PlusOutlined />} type="primary" onClick={() => setImportModalVisible(true)}>
              导入 UPC
            </Button>
            <Dropdown menu={{ items: exportMenuItems, onClick: handleExportMenuClick }}>
              <Button icon={<DownloadOutlined />}>导出</Button>
            </Dropdown>
          </Space>
        }
      >
        {/* 筛选栏 */}
        <Space style={{ marginBottom: 16 }} wrap>
          <Input.Search
            placeholder="搜索 UPC 或 SKU"
            value={search}
            onChange={e => setSearch(e.target.value)}
            onSearch={handleSearch}
            style={{ width: 200 }}
            allowClear
          />
          <Select
            value={status}
            onChange={v => { setStatus(v); setPage(1); }}
            style={{ width: 120 }}
            options={[
              { value: 'all', label: '全部' },
              { value: 'available', label: '可用' },
              { value: 'used', label: '已使用' },
            ]}
          />
          <Button icon={<ReloadOutlined />} onClick={() => { loadStats(); loadData(); }}>
            刷新
          </Button>
          {selectedRowKeys.length > 0 && (
            <>
              <Popconfirm title={`确定将选中的 ${selectedRowKeys.length} 个 UPC 标记为已使用？`} onConfirm={handleBatchMarkUsed}>
                <Button icon={<EditOutlined />}>标记已使用</Button>
              </Popconfirm>
              <Popconfirm title={`确定释放选中的 ${selectedRowKeys.length} 个 UPC？`} onConfirm={handleBatchRelease}>
                <Button>批量释放</Button>
              </Popconfirm>
              <Popconfirm title={`确定删除选中的 ${selectedRowKeys.length} 个 UPC？`} onConfirm={handleBatchDelete}>
                <Button danger icon={<DeleteOutlined />}>批量删除</Button>
              </Popconfirm>
            </>
          )}
        </Space>

        <Table
          rowKey="id"
          columns={columns}
          dataSource={data}
          loading={loading}
          rowSelection={{
            selectedRowKeys,
            onChange: (keys, rows) => {
              setSelectedRowKeys(keys as string[]);
              setSelectedRows(rows);
            },
          }}
          pagination={{
            current: page,
            pageSize,
            total,
            showSizeChanger: true,
            pageSizeOptions: ['50', '100', '200'],
            onChange: (p, ps) => { setPage(p); setPageSize(ps); },
            showTotal: t => `共 ${t} 条`,
          }}
        />
      </Card>

      {/* 导入弹窗 */}
      <Modal
        title="导入 UPC"
        open={importModalVisible}
        onOk={handleImport}
        onCancel={() => { setImportModalVisible(false); setImportText(''); }}
        confirmLoading={importing}
        width={600}
      >
        <p style={{ marginBottom: 8, color: '#666' }}>
          每行一个 UPC 码，支持 12-14 位数字（UPC-A、EAN-13、GTIN-14）
        </p>
        <TextArea
          rows={10}
          value={importText}
          onChange={e => setImportText(e.target.value)}
          placeholder="012345678901&#10;012345678902&#10;012345678903"
        />
        <p style={{ marginTop: 8, color: '#999', fontSize: 12 }}>
          提示：也可以用逗号、分号分隔
        </p>
      </Modal>
    </div>
  );
}
