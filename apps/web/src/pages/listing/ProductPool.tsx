import { useState, useEffect } from 'react';
import { Card, Table, Button, Space, Input, Select, Tag, Modal, message, Statistic, Row, Col, Image, Popconfirm, TreeSelect, Alert, Tabs, Descriptions, Upload, Radio } from 'antd';
import { DeleteOutlined, ReloadOutlined, SendOutlined, EyeOutlined, FolderOutlined, RobotOutlined, StopOutlined, UploadOutlined, DownloadOutlined, FileExcelOutlined } from '@ant-design/icons';
import { productPoolApi, channelApi, shopApi, platformCategoryApi, aiModelApi, aiOptimizeApi, listingApi } from '@/services/api';
import { getProductTypeLabel, getProductTypeColor } from '@/config/standard-fields.config';

// 从 channelAttributes 中提取标准字段值的辅助函数
const getAttr = (attrs: any, ...keys: string[]) => {
  if (!attrs) return null;
  for (const key of keys) {
    if (key.includes('.')) {
      const parts = key.split('.');
      let val = attrs;
      for (const p of parts) {
        val = val?.[p];
        if (val === undefined) break;
      }
      if (val !== undefined) return val;
    } else if (attrs[key] !== undefined) {
      return attrs[key];
    }
  }
  return null;
};

// 格式化显示值（返回 React 节点，支持数组换行显示）
const formatValueNode = (value: any): React.ReactNode => {
  if (value === null || value === undefined) return '-';
  if (Array.isArray(value)) {
    if (value.length === 0) return '-';
    return (
      <ul style={{ margin: 0, paddingLeft: 20 }}>
        {value.map((item, i) => (
          <li key={i} style={{ marginBottom: 4 }}>{String(item)}</li>
        ))}
      </ul>
    );
  }
  if (typeof value === 'object') return <pre style={{ margin: 0, fontSize: 12 }}>{JSON.stringify(value, null, 2)}</pre>;
  if (typeof value === 'boolean') return value ? '是' : '否';
  return String(value);
};

// 商品详情组件 - 两块：基础信息 + 渠道属性
function ProductDetailTabs({ product }: { product: any }) {
  if (!product) {
    return <div style={{ color: '#999', padding: 20 }}>无商品数据</div>;
  }
  
  const attrs = product.channelAttributes || {};
  
  // 计算字段
  const price = product.price || getAttr(attrs, 'price') || 0;
  const salePrice = getAttr(attrs, 'salePrice');
  const shippingFee = getAttr(attrs, 'shippingFee') || 0;
  const totalPrice = Number(price) + Number(shippingFee);
  const saleTotalPrice = salePrice ? Number(salePrice) + Number(shippingFee) : null;
  const currency = product.currency || getAttr(attrs, 'currency') || 'USD';
  
  // 渠道属性
  const customAttributes = getAttr(attrs, 'customAttributes') || [];
  
  // 多包裹信息
  const packages = getAttr(attrs, 'packages') || [];
  
  // 五点描述
  const bulletPoints = getAttr(attrs, 'bulletPoints') || [];
  
  // 搜索关键词
  const keywords = getAttr(attrs, 'keywords') || [];

  return (
    <Tabs
      items={[
        {
          key: 'info',
          label: '商品信息',
          children: (
            <div style={{ maxHeight: 600, overflow: 'auto' }}>
              {/* 系统信息 */}
              <Descriptions bordered column={3} size="small" style={{ marginBottom: 16 }}>
                <Descriptions.Item label="渠道">{product.channel?.name || '-'}</Descriptions.Item>
                <Descriptions.Item label="创建时间">{product.createdAt ? new Date(product.createdAt).toLocaleString() : '-'}</Descriptions.Item>
                <Descriptions.Item label="已刊登店铺">
                  {product.listingProducts?.length > 0 
                    ? product.listingProducts.map((lp: any) => lp.shop?.name).filter(Boolean).join(', ') || '-'
                    : '未刊登'}
                </Descriptions.Item>
              </Descriptions>

              {/* 图片区域 */}
              <div style={{ marginBottom: 16, display: 'flex', gap: 16 }}>
                {product.mainImageUrl && <Image src={product.mainImageUrl} width={150} height={150} style={{ objectFit: 'cover', borderRadius: 4 }} />}
                {product.imageUrls?.length > 0 && (
                  <Image.PreviewGroup>
                    <Space wrap>
                      {product.imageUrls.slice(0, 5).map((url: string, i: number) => (
                        <Image key={i} src={url} width={80} height={80} style={{ objectFit: 'cover', borderRadius: 4 }} />
                      ))}
                      {product.imageUrls.length > 5 && <Tag>+{product.imageUrls.length - 5}</Tag>}
                    </Space>
                  </Image.PreviewGroup>
                )}
              </div>

              {/* ==================== 基础信息（系统核心字段）==================== */}
              <div style={{ fontWeight: 600, fontSize: 15, marginBottom: 12, borderBottom: '2px solid #1890ff', paddingBottom: 8 }}>
                基础信息
              </div>
              
              <Descriptions bordered column={2} size="small" style={{ marginBottom: 16 }}>
                <Descriptions.Item label="商品标题" span={2}>{product.title || getAttr(attrs, 'title') || '-'}</Descriptions.Item>
                <Descriptions.Item label="SKU">{product.sku || getAttr(attrs, 'sku')}</Descriptions.Item>
                <Descriptions.Item label="颜色">{getAttr(attrs, 'color') || '-'}</Descriptions.Item>
                <Descriptions.Item label="材质">{getAttr(attrs, 'material') || '-'}</Descriptions.Item>
                <Descriptions.Item label="库存">{product.stock ?? getAttr(attrs, 'stock') ?? '-'}</Descriptions.Item>
                <Descriptions.Item label="价格">{price ? `${currency} ${Number(price).toFixed(2)}` : '-'}</Descriptions.Item>
                <Descriptions.Item label="运费">{shippingFee ? `${currency} ${Number(shippingFee).toFixed(2)}` : '-'}</Descriptions.Item>
                <Descriptions.Item label="优惠价">{salePrice ? `${currency} ${Number(salePrice).toFixed(2)}` : '-'}</Descriptions.Item>
                <Descriptions.Item label="总价">{totalPrice > 0 ? `${currency} ${totalPrice.toFixed(2)}` : '-'}</Descriptions.Item>
                <Descriptions.Item label="优惠总价">{saleTotalPrice ? `${currency} ${saleTotalPrice.toFixed(2)}` : '-'}</Descriptions.Item>
                <Descriptions.Item label="平台价">{getAttr(attrs, 'platformPrice') ? `${currency} ${Number(getAttr(attrs, 'platformPrice')).toFixed(2)}` : '-'}</Descriptions.Item>
                <Descriptions.Item label="产品长">{getAttr(attrs, 'productLength') ? `${getAttr(attrs, 'productLength')} in` : '-'}</Descriptions.Item>
                <Descriptions.Item label="产品宽">{getAttr(attrs, 'productWidth') ? `${getAttr(attrs, 'productWidth')} in` : '-'}</Descriptions.Item>
                <Descriptions.Item label="产品高">{getAttr(attrs, 'productHeight') ? `${getAttr(attrs, 'productHeight')} in` : '-'}</Descriptions.Item>
                <Descriptions.Item label="产品重">{getAttr(attrs, 'productWeight') ? `${getAttr(attrs, 'productWeight')} lb` : '-'}</Descriptions.Item>
                <Descriptions.Item label="包装长">{getAttr(attrs, 'packageLength') ? `${getAttr(attrs, 'packageLength')} in` : '-'}</Descriptions.Item>
                <Descriptions.Item label="包装宽">{getAttr(attrs, 'packageWidth') ? `${getAttr(attrs, 'packageWidth')} in` : '-'}</Descriptions.Item>
                <Descriptions.Item label="包装高">{getAttr(attrs, 'packageHeight') ? `${getAttr(attrs, 'packageHeight')} in` : '-'}</Descriptions.Item>
                <Descriptions.Item label="包装重">{getAttr(attrs, 'packageWeight') ? `${getAttr(attrs, 'packageWeight')} lb` : '-'}</Descriptions.Item>
                <Descriptions.Item label="产地">{getAttr(attrs, 'placeOfOrigin') || '-'}</Descriptions.Item>
                <Descriptions.Item label="商品性质">
                  {getAttr(attrs, 'productType') 
                    ? <Tag color={getProductTypeColor(getAttr(attrs, 'productType'))}>{getProductTypeLabel(getAttr(attrs, 'productType'))}</Tag> 
                    : '-'}
                </Descriptions.Item>
                <Descriptions.Item label="供货商">{getAttr(attrs, 'supplier') || '-'}</Descriptions.Item>
                <Descriptions.Item label="图片数">{(product.imageUrls?.length || 0) + (product.mainImageUrl ? 1 : 0)} 张</Descriptions.Item>
                <Descriptions.Item label="产品说明" span={2}>{getAttr(attrs, 'productDescription') || '-'}</Descriptions.Item>
                <Descriptions.Item label="产品资质" span={2}>{getAttr(attrs, 'productCertification') || '-'}</Descriptions.Item>
              </Descriptions>

              {/* 商品描述 */}
              {(product.description || getAttr(attrs, 'description')) && (
                <div style={{ marginBottom: 16 }}>
                  <div style={{ fontWeight: 500, marginBottom: 8 }}>商品描述</div>
                  <div
                    style={{ maxHeight: 200, overflow: 'auto', padding: 12, background: '#fafafa', borderRadius: 4, fontSize: 13 }}
                    dangerouslySetInnerHTML={{ __html: product.description || getAttr(attrs, 'description') }}
                  />
                </div>
              )}

              {/* 五点描述 */}
              {bulletPoints.length > 0 && (
                <div style={{ marginBottom: 16 }}>
                  <div style={{ fontWeight: 500, marginBottom: 8 }}>五点描述</div>
                  <ul style={{ margin: 0, paddingLeft: 20 }}>
                    {bulletPoints.map((bp: string, i: number) => (
                      <li key={i} style={{ marginBottom: 4 }}>{bp}</li>
                    ))}
                  </ul>
                </div>
              )}

              {/* 搜索关键词 */}
              {keywords.length > 0 && (
                <div style={{ marginBottom: 16 }}>
                  <div style={{ fontWeight: 500, marginBottom: 8 }}>搜索关键词</div>
                  <Space wrap>
                    {keywords.map((kw: string, i: number) => (
                      <Tag key={i}>{kw}</Tag>
                    ))}
                  </Space>
                </div>
              )}

              {/* 多包裹信息 */}
              {packages.length > 0 && (
                <div style={{ marginBottom: 16 }}>
                  <div style={{ fontWeight: 500, marginBottom: 8 }}>多包裹信息（{packages.length} 个）</div>
                  <Table
                    dataSource={packages}
                    columns={[
                      { title: '序号', dataIndex: 'index', width: 60 },
                      { title: 'SKU', dataIndex: 'sku', width: 120 },
                      { title: '长', dataIndex: 'length', render: (v: number) => v ? `${v} in` : '-' },
                      { title: '宽', dataIndex: 'width', render: (v: number) => v ? `${v} in` : '-' },
                      { title: '高', dataIndex: 'height', render: (v: number) => v ? `${v} in` : '-' },
                      { title: '重', dataIndex: 'weight', render: (v: number) => v ? `${v} lb` : '-' },
                      { title: '数量', dataIndex: 'quantity', width: 60 },
                    ]}
                    size="small"
                    pagination={false}
                    rowKey="index"
                  />
                </div>
              )}

              {/* ==================== 渠道属性 ==================== */}
              {customAttributes.length > 0 && (
                <>
                  <div style={{ fontWeight: 600, fontSize: 15, marginBottom: 12, marginTop: 24, borderBottom: '2px solid #52c41a', paddingBottom: 8 }}>
                    渠道属性（{customAttributes.length} 个）
                  </div>
                  <Descriptions bordered column={1} size="small" labelStyle={{ width: 140 }}>
                    {customAttributes.map((attr: any, i: number) => (
                      <Descriptions.Item key={i} label={attr.label || attr.name}>
                        <div style={{ maxHeight: 200, overflow: 'auto', wordBreak: 'break-word' }}>
                          {formatValueNode(attr.value)}
                        </div>
                      </Descriptions.Item>
                    ))}
                  </Descriptions>
                </>
              )}
            </div>
          ),
        },
        {
          key: 'raw',
          label: '原始数据',
          children: (
            <div style={{ maxHeight: 600, overflow: 'auto' }}>
              {product.channelRawData ? (
                <pre style={{ background: '#f5f5f5', padding: 12, borderRadius: 4, fontSize: 12, margin: 0 }}>
                  {JSON.stringify(product.channelRawData, null, 2)}
                </pre>
              ) : (
                <div style={{ color: '#999' }}>无原始数据</div>
              )}
            </div>
          ),
        },
      ]}
    />
  );
}

export default function ProductPool() {
  const [loading, setLoading] = useState(false);
  const [data, setData] = useState<any[]>([]);
  const [total, setTotal] = useState(0);
  const [page, setPage] = useState(1);
  const [pageSize, setPageSize] = useState(20);
  const [keyword, setKeyword] = useState('');
  const [channelId, setChannelId] = useState<string>('');
  const [channels, setChannels] = useState<any[]>([]);
  const [shops, setShops] = useState<any[]>([]);
  const [stats, setStats] = useState({ total: 0 });
  const [selectedRowKeys, setSelectedRowKeys] = useState<string[]>([]);

  // 刊登弹窗
  const [publishModal, setPublishModal] = useState(false);
  const [selectedShop, setSelectedShop] = useState<string>('');
  const [categoryTreeData, setCategoryTreeData] = useState<any[]>([]);
  const [selectedCategory, setSelectedCategory] = useState<string>('');
  const [selectedCategoryInfo, setSelectedCategoryInfo] = useState<any>(null);
  const [loadingCategories, setLoadingCategories] = useState(false);
  const [publishing, setPublishing] = useState(false);

  // 详情弹窗
  const [detailModal, setDetailModal] = useState(false);
  const [selectedProduct, setSelectedProduct] = useState<any>(null);

  // AI 优化弹窗
  const [aiOptimizeModal, setAiOptimizeModal] = useState(false);
  const [aiModels, setAiModels] = useState<any[]>([]);
  const [selectedAiModel, setSelectedAiModel] = useState<string>('');
  const [selectedFields, setSelectedFields] = useState<('title' | 'description' | 'bulletPoints' | 'keywords')[]>(['title']);
  const [optimizing, setOptimizing] = useState(false);
  const [optimizeResults, setOptimizeResults] = useState<any[]>([]);

  // Excel 导入弹窗
  const [importModal, setImportModal] = useState(false);
  const [importChannelId, setImportChannelId] = useState<string>('');
  const [importDuplicateAction, setImportDuplicateAction] = useState<'skip' | 'update'>('skip');
  const [importFile, setImportFile] = useState<File | null>(null);
  const [importing, setImporting] = useState(false);
  const [importResult, setImportResult] = useState<any>(null);

  useEffect(() => {
    loadChannelsAndShops();
  }, []);

  useEffect(() => {
    loadStats();
    loadData();
  }, [page, pageSize, channelId]);

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

  const loadStats = async () => {
    try {
      const res: any = await productPoolApi.getStats(channelId || undefined);
      setStats(res || { total: 0 });
    } catch (e: any) {
      console.error('加载统计失败:', e);
      setStats({ total: 0 });
    }
  };

  const loadData = async () => {
    setLoading(true);
    try {
      const res: any = await productPoolApi.list({ page, pageSize, channelId: channelId || undefined, keyword: keyword || undefined });
      setData(res?.data || []);
      setTotal(res?.total || 0);
    } catch (e: any) {
      console.error('加载数据失败:', e);
      setData([]);
      setTotal(0);
    } finally {
      setLoading(false);
    }
  };

  const handleSearch = () => {
    setPage(1);
    loadData();
  };


  // 当选择店铺时，加载对应平台的类目
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

  const convertToTreeSelectData = (categories: any[]): any[] => {
    return categories.map(cat => ({
      value: cat.categoryId,
      title: cat.name,
      isLeaf: cat.isLeaf,
      disabled: !cat.isLeaf,
      children: cat.children ? convertToTreeSelectData(cat.children) : undefined,
      data: cat,
    }));
  };

  const handleCategorySelect = (value: string, node: any) => {
    setSelectedCategory(value);
    setSelectedCategoryInfo(node?.data || null);
  };

  const handleDelete = async () => {
    if (selectedRowKeys.length === 0) {
      message.warning('请选择要删除的商品');
      return;
    }
    try {
      await productPoolApi.delete(selectedRowKeys);
      message.success(`已删除 ${selectedRowKeys.length} 个商品`);
      setSelectedRowKeys([]);
      loadStats();
      loadData();
    } catch (e: any) {
      message.error(e.message || '删除失败');
    }
  };

  const handlePublish = async () => {
    if (!selectedShop) {
      message.warning('请选择目标店铺');
      return;
    }
    if (!selectedCategory) {
      message.warning('请选择平台类目');
      return;
    }
    if (selectedRowKeys.length === 0) {
      message.warning('请选择要刊登的商品');
      return;
    }

    setPublishing(true);
    try {
      // 第一步：创建 ListingProduct 记录
      const publishRes: any = await productPoolApi.publish({
        productPoolIds: selectedRowKeys,
        shopId: selectedShop,
        platformCategoryId: selectedCategory,
      });

      if (publishRes.listingProductIds && publishRes.listingProductIds.length > 0) {
        // 第二步：提交刊登到平台
        message.loading('正在提交到平台...', 0);
        try {
          const submitRes: any = await listingApi.submitListing({
            shopId: selectedShop,
            productIds: publishRes.listingProductIds,
            categoryId: selectedCategory,
          });
          message.destroy();
          
          // 显示详细结果
          if (submitRes.successCount > 0 && submitRes.failCount > 0) {
            message.warning(`部分成功: ${submitRes.successCount} 个成功，${submitRes.failCount} 个失败`);
          } else if (submitRes.successCount > 0) {
            message.success(`提交成功: ${submitRes.successCount} 个商品已提交到平台，Feed ID: ${submitRes.feedId || '无'}`);
          } else {
            message.error(`提交失败: ${submitRes.errors?.join(', ') || '未知错误'}`);
          }
        } catch (submitErr: any) {
          message.destroy();
          // 提交失败但记录已创建
          message.warning(`商品已添加到刊登列表，但提交到平台失败: ${submitErr.message}`);
        }
      } else {
        message.success(`处理完成: 成功 ${publishRes.success}，跳过 ${publishRes.skipped}，失败 ${publishRes.failed}`);
      }

      setPublishModal(false);
      setSelectedRowKeys([]);
      setSelectedShop('');
      setSelectedCategory('');
      setSelectedCategoryInfo(null);
    } catch (e: any) {
      message.error(e.message || '刊登失败');
    } finally {
      setPublishing(false);
    }
  };

  const handleViewDetail = async (record: any) => {
    try {
      const res: any = await productPoolApi.get(record.id);
      setSelectedProduct(res);
      setDetailModal(true);
    } catch (e: any) {
      message.error(e.message || '获取详情失败');
    }
  };

  // AI 优化相关
  const loadAiConfig = async () => {
    try {
      const modelsRes: any = await aiModelApi.list();
      setAiModels(modelsRes.filter((m: any) => m.status === 'active'));
      // 设置默认模型
      const defaultModel = modelsRes.find((m: any) => m.isDefault && m.status === 'active');
      if (defaultModel) setSelectedAiModel(defaultModel.id);
    } catch (e) {
      console.error(e);
    }
  };

  const handleOpenAiOptimize = async (product: any) => {
    setSelectedProduct(product);
    setOptimizeResults([]);
    await loadAiConfig();
    setAiOptimizeModal(true);
  };

  const handleAiOptimize = async () => {
    if (!selectedProduct || !selectedAiModel || selectedFields.length === 0) {
      message.warning('请选择模型和优化字段');
      return;
    }
    setOptimizing(true);
    try {
      const res: any = await aiOptimizeApi.optimize({
        productId: selectedProduct.id,
        productType: 'pool',
        fields: selectedFields,
        modelId: selectedAiModel,
      });
      setOptimizeResults(res.results || []);
      message.success('优化完成');
    } catch (e: any) {
      message.error(e.message || '优化失败');
    } finally {
      setOptimizing(false);
    }
  };

  const handleApplyAiResult = async () => {
    if (optimizeResults.length === 0) return;
    try {
      const logIds = optimizeResults.map((r: any) => r.logId).filter(Boolean);
      if (logIds.length > 0) {
        await aiOptimizeApi.apply(logIds);
        message.success('已应用优化结果');
        setAiOptimizeModal(false);
        loadData();
      }
    } catch (e: any) {
      message.error(e.message || '应用失败');
    }
  };

  // 下载导入模板
  const handleDownloadTemplate = async () => {
    try {
      const res: any = await productPoolApi.downloadTemplate();
      const blob = new Blob([res], { type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' });
      const url = window.URL.createObjectURL(blob);
      const a = document.createElement('a');
      a.href = url;
      a.download = 'product_import_template.xlsx';
      document.body.appendChild(a);
      a.click();
      document.body.removeChild(a);
      window.URL.revokeObjectURL(url);
      message.success('模板下载成功');
    } catch (e: any) {
      message.error(e.message || '下载失败');
    }
  };

  // 打开导入弹窗
  const handleOpenImportModal = () => {
    setImportFile(null);
    setImportResult(null);
    setImportChannelId(channelId || '');
    setImportDuplicateAction('skip');
    setImportModal(true);
  };

  // 执行 Excel 导入
  const handleImportExcel = async () => {
    if (!importChannelId) {
      message.warning('请选择渠道');
      return;
    }
    if (!importFile) {
      message.warning('请选择要导入的文件');
      return;
    }

    setImporting(true);
    try {
      const formData = new FormData();
      formData.append('file', importFile);
      formData.append('channelId', importChannelId);
      formData.append('duplicateAction', importDuplicateAction);

      const res: any = await productPoolApi.importFromExcel(formData);
      setImportResult(res);
      
      if (res.success > 0) {
        message.success(`导入完成：成功 ${res.success} 条`);
        loadStats();
        loadData();
      }
      if (res.failed > 0) {
        message.warning(`${res.failed} 条导入失败，请查看详情`);
      }
    } catch (e: any) {
      message.error(e.message || '导入失败');
    } finally {
      setImporting(false);
    }
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
    {
      title: '渠道',
      dataIndex: ['channel', 'name'],
      width: 100,
    },
    { title: '价格', dataIndex: 'price', width: 80, render: (v: any) => v ? `$${Number(v).toFixed(2)}` : '-' },
    {
      title: '运费',
      width: 80,
      render: (_: any, record: any) => {
        const shippingFee = getAttr(record.channelAttributes, 'shippingFee', 'shipping.shippingFee', 'shipping_fee');
        return shippingFee ? `$${Number(shippingFee).toFixed(2)}` : '-';
      },
    },
    {
      title: '优惠价格',
      width: 90,
      render: (_: any, record: any) => {
        const salePrice = getAttr(record.channelAttributes, 'salePrice', 'sale_price');
        return salePrice ? `$${Number(salePrice).toFixed(2)}` : '-';
      },
    },
    {
      title: '总价',
      width: 80,
      render: (_: any, record: any) => {
        const price = record.price || 0;
        const shippingFee = getAttr(record.channelAttributes, 'shippingFee', 'shipping.shippingFee', 'shipping_fee') || 0;
        const total = Number(price) + Number(shippingFee);
        return total > 0 ? `$${total.toFixed(2)}` : '-';
      },
    },
    {
      title: '优惠总价',
      width: 90,
      render: (_: any, record: any) => {
        const salePrice = getAttr(record.channelAttributes, 'salePrice', 'sale_price');
        const shippingFee = getAttr(record.channelAttributes, 'shippingFee', 'shipping.shippingFee', 'shipping_fee') || 0;
        if (!salePrice) return '-';
        const total = Number(salePrice) + Number(shippingFee);
        return `$${total.toFixed(2)}`;
      },
    },
    { title: '库存', dataIndex: 'stock', width: 80 },
    {
      title: '平台类目',
      dataIndex: 'platformCategoryId',
      width: 140,
      ellipsis: true,
      render: (v: string) => v ? <Tag>{v}</Tag> : <span style={{ color: '#999' }}>未设置</span>,
    },
    {
      title: '不可售平台',
      width: 120,
      render: (_: any, record: any) => {
        const unavailable = getAttr(record.channelAttributes, 'unAvailablePlatform') || [];
        if (unavailable.length === 0) return '-';
        return (
          <Space wrap size={[4, 4]}>
            {unavailable.map((p: any) => (
              <Tag key={p.id} color="red" icon={<StopOutlined />}>{p.name}</Tag>
            ))}
          </Space>
        );
      },
    },
    {
      title: '创建时间',
      dataIndex: 'createdAt',
      width: 160,
      render: (time: string) => new Date(time).toLocaleString(),
    },
    {
      title: '操作',
      width: 140,
      render: (_: any, record: any) => (
        <Space>
          <Button type="link" size="small" icon={<EyeOutlined />} onClick={() => handleViewDetail(record)}>
            详情
          </Button>
          <Button type="link" size="small" icon={<RobotOutlined />} onClick={() => handleOpenAiOptimize(record)}>
            AI
          </Button>
        </Space>
      ),
    },
  ];


  return (
    <div>
      {/* 统计卡片 */}
      <Row gutter={16} style={{ marginBottom: 16 }}>
        <Col span={8}>
          <Card>
            <Statistic title="商品池总数" value={stats.total} valueStyle={{ color: '#1890ff' }} />
          </Card>
        </Col>
        <Col span={16}>
          <Card>
            <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', height: '100%' }}>
              <div>
                <div style={{ fontSize: 14, color: '#666', marginBottom: 8 }}>Excel 导入</div>
                <div style={{ fontSize: 12, color: '#999' }}>支持通过 Excel 表格批量导入商品到商品池</div>
              </div>
              <Space>
                <Button icon={<DownloadOutlined />} onClick={handleDownloadTemplate}>
                  下载模板
                </Button>
                <Button type="primary" icon={<UploadOutlined />} onClick={handleOpenImportModal}>
                  导入商品
                </Button>
              </Space>
            </div>
          </Card>
        </Col>
      </Row>

      {/* 主表格 */}
      <Card
        title="商品池管理"
        extra={
          <Space>
            {selectedRowKeys.length > 0 && (
              <>
                <Button icon={<SendOutlined />} type="primary" onClick={() => setPublishModal(true)}>
                  刊登到店铺 ({selectedRowKeys.length})
                </Button>
                <Popconfirm title={`确定删除选中的 ${selectedRowKeys.length} 个商品？`} onConfirm={handleDelete}>
                  <Button danger icon={<DeleteOutlined />}>删除</Button>
                </Popconfirm>
              </>
            )}
          </Space>
        }
      >
        {/* 筛选栏 */}
        <Space style={{ marginBottom: 16 }} wrap>
          <Select
            placeholder="选择渠道"
            style={{ width: 150 }}
            value={channelId || undefined}
            onChange={v => { setChannelId(v || ''); setPage(1); }}
            allowClear
            options={channels.map(c => ({ value: c.id, label: c.name }))}
          />
          <Input.Search
            placeholder="搜索 SKU 或标题"
            value={keyword}
            onChange={e => setKeyword(e.target.value)}
            onSearch={handleSearch}
            style={{ width: 200 }}
            allowClear
          />
          <Button icon={<ReloadOutlined />} onClick={() => { loadStats(); loadData(); }}>
            刷新
          </Button>
        </Space>

        <Table
          rowKey="id"
          columns={columns}
          dataSource={data}
          loading={loading}
          rowSelection={{
            selectedRowKeys,
            onChange: (keys) => {
              setSelectedRowKeys(keys as string[]);
            },
          }}
          pagination={{
            current: page,
            pageSize,
            total,
            showSizeChanger: true,
            pageSizeOptions: ['20', '50', '100'],
            onChange: (p, ps) => { setPage(p); setPageSize(ps); },
            showTotal: t => `共 ${t} 条`,
          }}
        />
      </Card>

      {/* 刊登弹窗 */}
      <Modal
        title="刊登到店铺"
        open={publishModal}
        onOk={handlePublish}
        onCancel={() => { setPublishModal(false); setSelectedShop(''); setSelectedCategory(''); setSelectedCategoryInfo(null); }}
        confirmLoading={publishing}
        okText="刊登"
        width={600}
      >
        <div style={{ marginBottom: 16 }}>
          <p>已选择 <strong>{selectedRowKeys.length}</strong> 个商品</p>
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
            options={shops.map(s => ({ value: s.id, label: `${s.name} (${s.region || 'US'})` }))}
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

        <Alert
          message="刊登说明"
          description={
            <ul style={{ margin: 0, paddingLeft: 20 }}>
              <li>商品将从商品池复制到选择的店铺</li>
              <li>已存在的 SKU 将被跳过</li>
              <li>刊登后可在「商品管理」页面查看状态</li>
            </ul>
          }
          type="info"
          showIcon
        />
      </Modal>

      {/* 详情弹窗 - 按标准字段分组显示 */}
      <Modal
        title={`商品详情 - ${selectedProduct?.sku || ''}`}
        open={detailModal}
        onCancel={() => setDetailModal(false)}
        footer={null}
        width={1100}
        styles={{ body: { maxHeight: '75vh', overflow: 'auto' } }}
      >
        {selectedProduct && (
          <ProductDetailTabs product={selectedProduct} />
        )}
      </Modal>

      {/* AI 优化弹窗 */}
      <Modal
        title={`AI 优化 - ${selectedProduct?.sku || ''}`}
        open={aiOptimizeModal}
        onCancel={() => { setAiOptimizeModal(false); setOptimizeResults([]); }}
        width={800}
        footer={
          <Space>
            <Button onClick={() => { setAiOptimizeModal(false); setOptimizeResults([]); }}>取消</Button>
            {optimizeResults.length > 0 ? (
              <Button type="primary" onClick={handleApplyAiResult}>应用优化结果</Button>
            ) : (
              <Button type="primary" onClick={handleAiOptimize} loading={optimizing} icon={<RobotOutlined />}>
                开始优化
              </Button>
            )}
          </Space>
        }
      >
        {optimizeResults.length === 0 ? (
          <div>
            <div style={{ marginBottom: 16 }}>
              <div style={{ marginBottom: 8, fontWeight: 500 }}>AI 模型</div>
              <Select
                placeholder="选择 AI 模型"
                style={{ width: '100%' }}
                value={selectedAiModel || undefined}
                onChange={setSelectedAiModel}
                options={aiModels.map(m => ({ value: m.id, label: `${m.name} (${m.modelName})` }))}
              />
            </div>
            <div style={{ marginBottom: 16 }}>
              <div style={{ marginBottom: 8, fontWeight: 500 }}>优化字段</div>
              <Select
                mode="multiple"
                placeholder="选择要优化的字段"
                style={{ width: '100%' }}
                value={selectedFields}
                onChange={setSelectedFields}
                options={[
                  { value: 'title', label: '标题' },
                  { value: 'description', label: '描述' },
                  { value: 'bulletPoints', label: '五点描述' },
                ]}
              />
            </div>
            <Alert
              message="优化说明"
              description="AI 将根据商品信息自动优化选中的字段，优化完成后可预览并选择是否应用。"
              type="info"
              showIcon
            />
          </div>
        ) : (
          <div>
            <Alert message="优化完成！请查看结果并决定是否应用。" type="success" showIcon style={{ marginBottom: 16 }} />
            {optimizeResults.map((result: any, index: number) => (
              <Card key={index} size="small" title={`${result.field === 'title' ? '标题' : result.field === 'description' ? '描述' : '五点描述'}`} style={{ marginBottom: 12 }}>
                <div style={{ marginBottom: 8 }}>
                  <div style={{ color: '#666', fontSize: 12 }}>原始内容：</div>
                  <div style={{ background: '#f5f5f5', padding: 8, borderRadius: 4, maxHeight: 100, overflow: 'auto' }}>
                    {Array.isArray(result.original) ? result.original.join('\n') : result.original || '(空)'}
                  </div>
                </div>
                <div>
                  <div style={{ color: '#1890ff', fontSize: 12 }}>优化结果：</div>
                  <div style={{ background: '#e6f7ff', padding: 8, borderRadius: 4, maxHeight: 150, overflow: 'auto' }}>
                    {Array.isArray(result.optimized) ? result.optimized.join('\n') : result.optimized || '(空)'}
                  </div>
                </div>
              </Card>
            ))}
          </div>
        )}
      </Modal>

      {/* Excel 导入弹窗 */}
      <Modal
        title={<><FileExcelOutlined style={{ marginRight: 8, color: '#52c41a' }} />Excel 导入商品</>}
        open={importModal}
        onCancel={() => { setImportModal(false); setImportResult(null); }}
        width={600}
        footer={
          importResult ? (
            <Button type="primary" onClick={() => { setImportModal(false); setImportResult(null); }}>
              完成
            </Button>
          ) : (
            <Space>
              <Button onClick={() => setImportModal(false)}>取消</Button>
              <Button type="primary" onClick={handleImportExcel} loading={importing} icon={<UploadOutlined />}>
                开始导入
              </Button>
            </Space>
          )
        }
      >
        {!importResult ? (
          <div>
            <div style={{ marginBottom: 16 }}>
              <div style={{ marginBottom: 8, fontWeight: 500 }}>
                选择渠道 <span style={{ color: '#ff4d4f' }}>*</span>
              </div>
              <Select
                placeholder="选择商品所属渠道"
                style={{ width: '100%' }}
                value={importChannelId || undefined}
                onChange={setImportChannelId}
                options={channels.map(c => ({ value: c.id, label: c.name }))}
              />
            </div>

            <div style={{ marginBottom: 16 }}>
              <div style={{ marginBottom: 8, fontWeight: 500 }}>重复处理</div>
              <Radio.Group value={importDuplicateAction} onChange={e => setImportDuplicateAction(e.target.value)}>
                <Radio value="skip">跳过重复 SKU</Radio>
                <Radio value="update">更新重复 SKU</Radio>
              </Radio.Group>
            </div>

            <div style={{ marginBottom: 16 }}>
              <div style={{ marginBottom: 8, fontWeight: 500 }}>
                选择文件 <span style={{ color: '#ff4d4f' }}>*</span>
              </div>
              <Upload
                accept=".xlsx,.xls"
                maxCount={1}
                beforeUpload={(file) => {
                  setImportFile(file);
                  return false;
                }}
                onRemove={() => setImportFile(null)}
                fileList={importFile ? [{ uid: '-1', name: importFile.name, status: 'done' }] : []}
              >
                <Button icon={<UploadOutlined />}>选择 Excel 文件</Button>
              </Upload>
              <div style={{ marginTop: 8, fontSize: 12, color: '#999' }}>
                支持 .xlsx、.xls 格式，请先下载模板填写数据
              </div>
            </div>

            <Alert
              message="导入说明"
              description={
                <ul style={{ margin: 0, paddingLeft: 20 }}>
                  <li>请先下载模板，按模板格式填写商品数据</li>
                  <li>SKU、商品标题、价格、库存为必填项</li>
                  <li>多个值（如图片URL、五点描述）用 | 分隔</li>
                  <li>导入后商品将进入商品池，可选择刊登到店铺</li>
                </ul>
              }
              type="info"
              showIcon
            />
          </div>
        ) : (
          <div>
            <Alert
              message={importResult.failed === 0 ? '导入成功' : '导入完成（部分失败）'}
              type={importResult.failed === 0 ? 'success' : 'warning'}
              showIcon
              style={{ marginBottom: 16 }}
            />
            
            <Descriptions bordered column={2} size="small">
              <Descriptions.Item label="总数">{importResult.total}</Descriptions.Item>
              <Descriptions.Item label="成功">
                <span style={{ color: '#52c41a', fontWeight: 500 }}>{importResult.success}</span>
              </Descriptions.Item>
              <Descriptions.Item label="跳过">
                <span style={{ color: '#faad14' }}>{importResult.skipped}</span>
              </Descriptions.Item>
              <Descriptions.Item label="失败">
                <span style={{ color: '#ff4d4f', fontWeight: 500 }}>{importResult.failed}</span>
              </Descriptions.Item>
            </Descriptions>

            {importResult.errors?.length > 0 && (
              <div style={{ marginTop: 16 }}>
                <div style={{ fontWeight: 500, marginBottom: 8, color: '#ff4d4f' }}>错误详情：</div>
                <div style={{ maxHeight: 200, overflow: 'auto', background: '#fff2f0', padding: 12, borderRadius: 4 }}>
                  {importResult.errors.map((err: any, i: number) => (
                    <div key={i} style={{ marginBottom: 4, fontSize: 12 }}>
                      <Tag color="red">行 {err.row}</Tag>
                      <span style={{ color: '#666' }}>SKU: {err.sku}</span>
                      <span style={{ marginLeft: 8, color: '#ff4d4f' }}>{err.error}</span>
                    </div>
                  ))}
                </div>
              </div>
            )}
          </div>
        )}
      </Modal>
    </div>
  );
}
