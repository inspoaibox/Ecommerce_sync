import { useState, useEffect } from 'react';
import { useNavigate } from 'react-router-dom';
import { Card, Input, Button, Table, Select, Space, message, Image, Modal, Tag, Descriptions, Tabs, Collapse, TreeSelect, Alert } from 'antd';
import { SearchOutlined, ImportOutlined, EyeOutlined, FolderOutlined } from '@ant-design/icons';
import { channelApi, shopApi, listingApi, platformCategoryApi, platformApi } from '@/services/api';

const { TextArea } = Input;

export default function ListingQuery() {
  const navigate = useNavigate();
  const [channels, setChannels] = useState<any[]>([]);
  const [shops, setShops] = useState<any[]>([]);
  const [selectedChannel, setSelectedChannel] = useState<string>('');
  const [skuInput, setSkuInput] = useState('');
  const [loading, setLoading] = useState(false);
  const [products, setProducts] = useState<any[]>([]);
  const [selectedRows, setSelectedRows] = useState<any[]>([]);
  const [importModal, setImportModal] = useState(false);
  const [selectedShop, setSelectedShop] = useState<string>('');
  const [importing, setImporting] = useState(false);
  const [detailModal, setDetailModal] = useState(false);
  const [selectedProduct, setSelectedProduct] = useState<any>(null);
  
  // 类目选择相关状态
  const [platforms, setPlatforms] = useState<any[]>([]);
  const [categoryTreeData, setCategoryTreeData] = useState<any[]>([]);
  const [selectedCategory, setSelectedCategory] = useState<string>('');
  const [selectedCategoryInfo, setSelectedCategoryInfo] = useState<any>(null);
  const [loadingCategories, setLoadingCategories] = useState(false);

  useEffect(() => {
    loadChannelsAndShops();
    loadPlatforms();
  }, []);

  const loadChannelsAndShops = async () => {
    try {
      const [channelsRes, shopsRes]: any[] = await Promise.all([
        channelApi.list({ pageSize: 100 }),
        shopApi.list({ pageSize: 100 }),
      ]);
      setChannels(channelsRes.data || []);
      setShops(shopsRes.data || []);
    } catch (e) {
      console.error(e);
    }
  };

  const loadPlatforms = async () => {
    try {
      const res: any = await platformApi.list({ pageSize: 100 });
      setPlatforms(res.data || []);
    } catch (e) {
      console.error(e);
    }
  };

  // 当选择店铺时，加载对应平台和国家的类目
  useEffect(() => {
    if (selectedShop) {
      loadCategoriesForShop();
    } else {
      setCategoryTreeData([]);
      setSelectedCategory('');
      setSelectedCategoryInfo(null);
    }
  }, [selectedShop]);

  const loadCategoriesForShop = async () => {
    const shop = shops.find(s => s.id === selectedShop);
    if (!shop) return;

    setLoadingCategories(true);
    try {
      // 获取店铺对应的平台和国家
      const platformId = shop.platformId;
      const country = shop.region || 'US';

      const res: any = await platformCategoryApi.getCategoryTree(platformId, country);
      const treeData = convertToTreeSelectData(res || []);
      setCategoryTreeData(treeData);
    } catch (e) {
      console.error(e);
      setCategoryTreeData([]);
    } finally {
      setLoadingCategories(false);
    }
  };

  // 转换为 TreeSelect 数据格式
  const convertToTreeSelectData = (categories: any[]): any[] => {
    return categories.map(cat => ({
      value: cat.categoryId,
      title: cat.name,
      isLeaf: cat.isLeaf,
      disabled: !cat.isLeaf, // 只允许选择叶子类目
      children: cat.children ? convertToTreeSelectData(cat.children) : undefined,
      data: cat,
    }));
  };

  // 处理类目选择
  const handleCategorySelect = (value: string, node: any) => {
    setSelectedCategory(value);
    setSelectedCategoryInfo(node?.data || null);
  };

  const handleQuery = async () => {
    if (!selectedChannel) {
      message.warning('请选择渠道');
      return;
    }
    const skus = skuInput.split(/[\n,;]/).map(s => s.trim()).filter(Boolean);
    if (skus.length === 0) {
      message.warning('请输入SKU');
      return;
    }

    setLoading(true);
    try {
      const res: any = await listingApi.queryFromChannel(selectedChannel, skus);
      setProducts(res || []);
      message.success(`查询到 ${res?.length || 0} 个商品`);
    } catch (e: any) {
      message.error(e.message || '查询失败');
    } finally {
      setLoading(false);
    }
  };

  const handleImport = async () => {
    if (!selectedShop) {
      message.warning('请选择店铺');
      return;
    }
    if (!selectedCategory) {
      message.warning('请选择平台类目');
      return;
    }
    if (selectedRows.length === 0) {
      message.warning('请选择要导入的商品');
      return;
    }

    setImporting(true);
    try {
      const importData = selectedRows.map(p => ({
        sku: p.sku,
        title: p.title || '',
        description: p.description || '',
        mainImageUrl: p.mainImageUrl || '',
        imageUrls: p.imageUrls || [],
        videoUrls: p.videoUrls || [],
        price: p.price ?? p.rawData?.price?.price ?? 0,
        stock: p.stock ?? p.rawData?.inventory?.sellerInventoryInfo?.sellerAvailableInventory ?? 0,
        currency: p.currency || 'USD',
        channelRawData: p.rawData,
        channelAttributes: p.standardAttributes,
        platformCategoryId: selectedCategory, // 添加类目ID
      }));

      const res: any = await listingApi.importProducts({
        shopId: selectedShop,
        channelId: selectedChannel,
        products: importData,
        duplicateAction: 'skip',
      });

      setImportModal(false);
      setSelectedRows([]);
      setSelectedCategory('');
      setSelectedCategoryInfo(null);
      
      // 显示成功消息并提供跳转链接
      Modal.info({
        title: '导入完成',
        width: 500,
        content: (
          <div>
            <p>成功: {res.success}，跳过: {res.skipped}，失败: {res.failed}</p>
            {selectedCategoryInfo && (
              <p style={{ color: '#666' }}>
                <FolderOutlined style={{ marginRight: 4 }} />
                类目: {selectedCategoryInfo.name}
              </p>
            )}
            {res.errors?.length > 0 && (
              <div style={{ marginTop: 8, maxHeight: 200, overflow: 'auto', background: '#fff1f0', padding: 8, borderRadius: 4 }}>
                <div style={{ color: '#cf1322', marginBottom: 4 }}>失败详情：</div>
                {res.errors.map((e: any, i: number) => (
                  <div key={i} style={{ fontSize: 12 }}>
                    <strong>{e.sku}</strong>: {e.error}
                  </div>
                ))}
              </div>
            )}
            {res.success > 0 && (
              <p style={{ marginTop: 8 }}>
                导入的商品可以在 <a onClick={() => { Modal.destroyAll(); navigate('/listing/products'); }}>刊登管理</a> 页面查看和编辑
              </p>
            )}
          </div>
        ),
        okText: res.success > 0 ? '去查看' : '确定',
        onOk: () => {
          if (res.success > 0) {
            navigate('/listing/products');
          }
        },
      });
    } catch (e: any) {
      message.error(e.message || '导入失败');
    } finally {
      setImporting(false);
    }
  };

  const handleViewDetail = (product: any) => {
    setSelectedProduct(product);
    setDetailModal(true);
  };

  const columns = [
    {
      title: '图片',
      dataIndex: 'mainImageUrl',
      width: 80,
      render: (url: string) => url ? <Image src={url} width={60} height={60} style={{ objectFit: 'cover' }} /> : '-',
    },
    { title: 'SKU', dataIndex: 'sku', width: 120 },
    { title: '标题', dataIndex: 'title', width: 200, ellipsis: true },
    { title: '价格', dataIndex: 'price', width: 80, render: (v: number) => `${v?.toFixed(2) || '-'}` },
    { title: '库存', dataIndex: 'stock', width: 80 },
    {
      title: '类目',
      width: 120,
      render: (_: any, r: any) => r.standardAttributes?.category || '-',
    },
    {
      title: '尺寸',
      width: 150,
      render: (_: any, r: any) => {
        const a = r.standardAttributes;
        if (!a?.length) return '-';
        return `${a.length}x${a.width}x${a.height} ${a.lengthUnit || ''}`;
      },
    },
    {
      title: '重量',
      width: 100,
      render: (_: any, r: any) => {
        const a = r.standardAttributes;
        if (!a?.weight) return '-';
        return `${a.weight} ${a.weightUnit || ''}`;
      },
    },
    {
      title: '图片数',
      width: 80,
      render: (_: any, r: any) => (r.imageUrls?.length || 0) + 1,
    },
    {
      title: '操作',
      width: 80,
      render: (_: any, r: any) => (
        <Button type="link" size="small" icon={<EyeOutlined />} onClick={() => handleViewDetail(r)}>
          详情
        </Button>
      ),
    },
  ];

  // 渲染商品详情弹窗内容
  const renderDetailContent = () => {
    if (!selectedProduct) return null;
    const p = selectedProduct;
    const attrs = p.standardAttributes || {};
    const specific = p.channelSpecificFields || {};

    return (
      <Tabs
        items={[
          {
            key: 'basic',
            label: '基本信息',
            children: (
              <Descriptions bordered column={2} size="small">
                <Descriptions.Item label="SKU">{p.sku}</Descriptions.Item>
                <Descriptions.Item label="价格">${p.price?.toFixed(2)} {p.currency}</Descriptions.Item>
                <Descriptions.Item label="库存">{p.stock}</Descriptions.Item>
                <Descriptions.Item label="MPN">{attrs.mpn || '-'}</Descriptions.Item>
                <Descriptions.Item label="UPC">{attrs.upc || '-'}</Descriptions.Item>
                <Descriptions.Item label="品牌">{attrs.brand || '-'}</Descriptions.Item>
                <Descriptions.Item label="标题" span={2}>{p.title}</Descriptions.Item>
                <Descriptions.Item label="类目" span={2}>{attrs.category || '-'}</Descriptions.Item>
                <Descriptions.Item label="类目代码">{attrs.categoryCode || '-'}</Descriptions.Item>
                <Descriptions.Item label="产地">{attrs.placeOfOrigin || '-'}</Descriptions.Item>
              </Descriptions>
            ),
          },
          {
            key: 'dimensions',
            label: '尺寸重量',
            children: (
              <Descriptions bordered column={2} size="small">
                <Descriptions.Item label="包装重量">{attrs.weight ? `${attrs.weight} ${attrs.weightUnit || ''}` : '-'}</Descriptions.Item>
                <Descriptions.Item label="运费">{attrs.shippingFee ? `${attrs.shippingFee}` : '-'}</Descriptions.Item>
                <Descriptions.Item label="包装长度">{attrs.length || '-'}</Descriptions.Item>
                <Descriptions.Item label="包装宽度">{attrs.width || '-'}</Descriptions.Item>
                <Descriptions.Item label="包装高度">{attrs.height || '-'}</Descriptions.Item>
                <Descriptions.Item label="长度单位">{attrs.lengthUnit || '-'}</Descriptions.Item>
                <Descriptions.Item label="组装后重量">{attrs.assembledWeight || '-'}</Descriptions.Item>
                <Descriptions.Item label="组装后长度">{attrs.assembledLength || '-'}</Descriptions.Item>
                <Descriptions.Item label="组装后宽度">{attrs.assembledWidth || '-'}</Descriptions.Item>
                <Descriptions.Item label="组装后高度">{attrs.assembledHeight || '-'}</Descriptions.Item>
              </Descriptions>
            ),
          },
          {
            key: 'images',
            label: `图片 (${(p.imageUrls?.length || 0) + (p.mainImageUrl ? 1 : 0)})`,
            children: (
              <div>
                <h4>主图</h4>
                {p.mainImageUrl ? (
                  <Image src={p.mainImageUrl} width={200} style={{ marginBottom: 16 }} />
                ) : (
                  <div style={{ color: '#999' }}>无主图</div>
                )}
                {p.imageUrls?.length > 0 && (
                  <>
                    <h4>附图</h4>
                    <Image.PreviewGroup>
                      <Space wrap>
                        {p.imageUrls.map((url: string, i: number) => (
                          <Image key={i} src={url} width={120} height={120} style={{ objectFit: 'cover' }} />
                        ))}
                      </Space>
                    </Image.PreviewGroup>
                  </>
                )}
                {p.videoUrls?.length > 0 && (
                  <>
                    <h4 style={{ marginTop: 16 }}>视频 ({p.videoUrls.length})</h4>
                    {p.videoUrls.map((url: string, i: number) => (
                      <div key={i}>
                        <a href={url} target="_blank" rel="noopener noreferrer">{url}</a>
                      </div>
                    ))}
                  </>
                )}
              </div>
            ),
          },
          {
            key: 'description',
            label: '描述',
            children: (
              <div>
                {p.description ? (
                  <div
                    style={{ maxHeight: 400, overflow: 'auto', padding: 12, background: '#fafafa', borderRadius: 4 }}
                    dangerouslySetInnerHTML={{ __html: p.description }}
                  />
                ) : (
                  <div style={{ color: '#999' }}>无描述</div>
                )}
                {attrs.characteristics?.length > 0 && (
                  <div style={{ marginTop: 16 }}>
                    <h4>商品特点</h4>
                    <ul>
                      {attrs.characteristics.map((c: string, i: number) => (
                        <li key={i}>{c}</li>
                      ))}
                    </ul>
                  </div>
                )}
              </div>
            ),
          },
          {
            key: 'channel',
            label: '渠道特有字段',
            children: (
              <Collapse
                items={[
                  {
                    key: 'flags',
                    label: '商品标记',
                    children: (
                      <Space wrap>
                        {specific.whiteLabel && <Tag color="blue">白标: {specific.whiteLabel}</Tag>}
                        {specific.comboFlag && <Tag color="orange">组合商品</Tag>}
                        {specific.overSizeFlag && <Tag color="red">超大件</Tag>}
                        {specific.partFlag && <Tag color="purple">配件</Tag>}
                        {specific.customized && <Tag color="green">定制商品</Tag>}
                        {specific.lithiumBatteryContained && <Tag color="warning">含锂电池</Tag>}
                        {specific.skuAvailable === false && <Tag color="error">不可用</Tag>}
                        {specific.skuAvailable === true && <Tag color="success">可用</Tag>}
                      </Space>
                    ),
                  },
                  {
                    key: 'price',
                    label: '价格信息',
                    children: (
                      <Descriptions bordered column={2} size="small">
                        <Descriptions.Item label="原价">${p.price?.toFixed(2)}</Descriptions.Item>
                        <Descriptions.Item label="优惠价">{specific.discountedPrice ? `${specific.discountedPrice}` : '-'}</Descriptions.Item>
                        <Descriptions.Item label="MAP价格">{specific.mapPrice ? `${specific.mapPrice}` : '-'}</Descriptions.Item>
                        <Descriptions.Item label="运费">{attrs.shippingFee ? `${attrs.shippingFee}` : '-'}</Descriptions.Item>
                      </Descriptions>
                    ),
                  },
                  {
                    key: 'inventory',
                    label: '库存信息',
                    children: (
                      <Descriptions bordered column={2} size="small">
                        <Descriptions.Item label="卖家库存">{p.stock}</Descriptions.Item>
                        <Descriptions.Item label="买家库存">{specific.buyerStock || 0}</Descriptions.Item>
                        <Descriptions.Item label="折扣库存">{specific.discountAvailableInventory || 0}</Descriptions.Item>
                      </Descriptions>
                    ),
                  },
                  {
                    key: 'seller',
                    label: '卖家信息',
                    children: specific.sellerInfo ? (
                      <Descriptions bordered column={2} size="small">
                        <Descriptions.Item label="店铺">{specific.sellerInfo.sellerStore || '-'}</Descriptions.Item>
                        <Descriptions.Item label="类型">{specific.sellerInfo.sellerType || '-'}</Descriptions.Item>
                      </Descriptions>
                    ) : (
                      <div style={{ color: '#999' }}>无卖家信息</div>
                    ),
                  },
                  {
                    key: 'attributes',
                    label: '自定义属性',
                    children: specific.attributes ? (
                      <Descriptions bordered column={2} size="small">
                        {Object.entries(specific.attributes).map(([k, v]) => (
                          <Descriptions.Item key={k} label={k}>{String(v)}</Descriptions.Item>
                        ))}
                      </Descriptions>
                    ) : (
                      <div style={{ color: '#999' }}>无自定义属性</div>
                    ),
                  },
                  {
                    key: 'combo',
                    label: '组合信息',
                    children: specific.comboInfo?.length > 0 ? (
                      <Table
                        dataSource={specific.comboInfo}
                        columns={[
                          { title: 'SKU', dataIndex: 'sku' },
                          { title: '数量', dataIndex: 'quantity' },
                        ]}
                        size="small"
                        pagination={false}
                        rowKey="sku"
                      />
                    ) : (
                      <div style={{ color: '#999' }}>非组合商品</div>
                    ),
                  },
                ]}
                defaultActiveKey={['flags', 'price']}
              />
            ),
          },
          {
            key: 'raw',
            label: '原始数据',
            children: (
              <div style={{ maxHeight: 500, overflow: 'auto' }}>
                <pre style={{ background: '#f5f5f5', padding: 12, borderRadius: 4, fontSize: 12 }}>
                  {JSON.stringify(p.rawData, null, 2)}
                </pre>
              </div>
            ),
          },
        ]}
      />
    );
  };

  // 获取选中店铺的信息
  const getSelectedShopInfo = () => {
    const shop = shops.find(s => s.id === selectedShop);
    if (!shop) return null;
    const platform = platforms.find(p => p.id === shop.platformId);
    return { shop, platform };
  };

  return (
    <div>
      <Card title="商品查询" style={{ marginBottom: 16 }}>
        <Space direction="vertical" style={{ width: '100%' }}>
          <Space>
            <Select
              placeholder="选择渠道"
              style={{ width: 200 }}
              value={selectedChannel || undefined}
              onChange={setSelectedChannel}
              options={channels.map(c => ({ value: c.id, label: c.name }))}
            />
            <Button type="primary" icon={<SearchOutlined />} onClick={handleQuery} loading={loading}>
              查询商品详情
            </Button>
            {selectedRows.length > 0 && (
              <Button icon={<ImportOutlined />} onClick={() => setImportModal(true)}>
                导入选中 ({selectedRows.length})
              </Button>
            )}
          </Space>
          <TextArea
            placeholder="输入SKU，每行一个或用逗号分隔"
            rows={4}
            value={skuInput}
            onChange={e => setSkuInput(e.target.value)}
          />
        </Space>
      </Card>

      <Card title={`查询结果 (${products.length})`}>
        <Table
          dataSource={products}
          columns={columns}
          rowKey="sku"
          loading={loading}
          size="small"
          scroll={{ x: 1300 }}
          rowSelection={{
            selectedRowKeys: selectedRows.map(r => r.sku),
            onChange: (_, rows) => setSelectedRows(rows),
          }}
          pagination={{ pageSize: 20, showSizeChanger: true, showTotal: t => `共 ${t} 条` }}
        />
      </Card>

      {/* 导入弹窗 */}
      <Modal
        title="导入商品"
        open={importModal}
        onOk={handleImport}
        onCancel={() => { setImportModal(false); setSelectedCategory(''); setSelectedCategoryInfo(null); }}
        confirmLoading={importing}
        okText="导入"
        width={600}
      >
        <div style={{ marginBottom: 16 }}>
          <p>已选择 <strong>{selectedRows.length}</strong> 个商品</p>
        </div>

        <div style={{ marginBottom: 16 }}>
          <div style={{ marginBottom: 8, fontWeight: 500 }}>
            目标店铺 <span style={{ color: '#ff4d4f' }}>*</span>
          </div>
          <Select
            placeholder="选择目标店铺"
            style={{ width: '100%' }}
            value={selectedShop || undefined}
            onChange={v => { setSelectedShop(v); setSelectedCategory(''); setSelectedCategoryInfo(null); }}
            options={shops.map(s => ({ 
              value: s.id, 
              label: `${s.name} (${s.region || 'US'})`,
            }))}
          />
        </div>

        <div style={{ marginBottom: 16 }}>
          <div style={{ marginBottom: 8, fontWeight: 500 }}>
            平台类目 <span style={{ color: '#ff4d4f' }}>*</span>
          </div>
          {selectedShop ? (
            <TreeSelect
              placeholder="选择平台类目（只能选择叶子类目）"
              style={{ width: '100%' }}
              value={selectedCategory || undefined}
              onChange={handleCategorySelect}
              treeData={categoryTreeData}
              loading={loadingCategories}
              showSearch
              treeNodeFilterProp="title"
              dropdownStyle={{ maxHeight: 400, overflow: 'auto' }}
              allowClear
            />
          ) : (
            <Alert message="请先选择店铺" type="info" showIcon />
          )}
          {selectedCategoryInfo && (
            <div style={{ marginTop: 8, padding: 8, background: '#f5f5f5', borderRadius: 4 }}>
              <Space>
                <FolderOutlined />
                <span>{selectedCategoryInfo.categoryPath || selectedCategoryInfo.name}</span>
              </Space>
            </div>
          )}
        </div>

        {getSelectedShopInfo() && (
          <div style={{ padding: 12, background: '#f0f5ff', borderRadius: 4, marginBottom: 16 }}>
            <div style={{ fontSize: 12, color: '#666' }}>
              <strong>店铺信息：</strong>
              {getSelectedShopInfo()?.shop.name} | 
              平台: {getSelectedShopInfo()?.platform?.name || '-'} | 
              地区: {getSelectedShopInfo()?.shop.region || 'US'}
            </div>
          </div>
        )}

        <Alert
          message="导入说明"
          description={
            <ul style={{ margin: 0, paddingLeft: 20 }}>
              <li>同一批导入的商品将归属到选择的类目下</li>
              <li>已存在的SKU将被跳过</li>
              <li>导入后可在「刊登管理」页面查看和编辑商品</li>
              <li>类目的属性映射配置将自动应用到导入的商品</li>
            </ul>
          }
          type="info"
          showIcon
        />
      </Modal>

      {/* 详情弹窗 */}
      <Modal
        title={`商品详情 - ${selectedProduct?.sku || ''}`}
        open={detailModal}
        onCancel={() => setDetailModal(false)}
        footer={null}
        width={1000}
        styles={{ body: { maxHeight: '70vh', overflow: 'auto' } }}
      >
        {renderDetailContent()}
      </Modal>
    </div>
  );
}
