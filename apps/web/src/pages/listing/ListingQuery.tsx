import { useState, useEffect } from 'react';
import { useNavigate } from 'react-router-dom';
import { Card, Input, Button, Table, Select, Space, message, Image, Modal, Tag, Descriptions, Tabs, TreeSelect, Alert } from 'antd';
import { SearchOutlined, ImportOutlined, EyeOutlined, FolderOutlined, StopOutlined } from '@ant-design/icons';
import { channelApi, listingApi, productPoolApi, platformApi, platformCategoryApi, unavailablePlatformApi } from '@/services/api';

const { TextArea } = Input;

export default function ListingQuery() {
  const navigate = useNavigate();
  const [channels, setChannels] = useState<any[]>([]);
  const [selectedChannel, setSelectedChannel] = useState<string>('');
  const [skuInput, setSkuInput] = useState('');
  const [loading, setLoading] = useState(false);
  const [products, setProducts] = useState<any[]>([]);
  const [selectedRows, setSelectedRows] = useState<any[]>([]);
  const [importModal, setImportModal] = useState(false);
  const [importing, setImporting] = useState(false);
  const [detailModal, setDetailModal] = useState(false);
  const [selectedProduct, setSelectedProduct] = useState<any>(null);
  
  // 重复处理选项
  const [duplicateAction, setDuplicateAction] = useState<'skip' | 'update'>('skip');
  
  // 平台类目选择
  const [platforms, setPlatforms] = useState<any[]>([]);
  const [selectedPlatform, setSelectedPlatform] = useState<string>('');
  const [selectedCountry, setSelectedCountry] = useState<string>('US');
  const [categoryTreeData, setCategoryTreeData] = useState<any[]>([]);
  const [selectedCategory, setSelectedCategory] = useState<string>('');
  const [selectedCategoryInfo, setSelectedCategoryInfo] = useState<any>(null);
  const [loadingCategories, setLoadingCategories] = useState(false);
  
  // 不可售平台筛选
  const [unavailablePlatforms, setUnavailablePlatforms] = useState<any[]>([]);
  const [filterPlatform, setFilterPlatform] = useState<string>('');

  useEffect(() => {
    loadChannels();
    loadPlatforms();
    loadUnavailablePlatforms();
  }, []);

  const loadChannels = async () => {
    try {
      const channelsRes: any = await channelApi.list({ pageSize: 100 });
      setChannels(channelsRes.data || []);
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

  const loadUnavailablePlatforms = async () => {
    try {
      const res: any = await unavailablePlatformApi.list();
      setUnavailablePlatforms(res || []);
    } catch (e) {
      console.error(e);
    }
  };

  // 当选择平台或国家时，加载对应的类目
  useEffect(() => {
    const loadCategories = async () => {
      if (selectedPlatform && selectedCountry) {
        setLoadingCategories(true);
        try {
          const res: any = await platformCategoryApi.getCategoryTree(selectedPlatform, selectedCountry);
          const treeData = convertToTreeSelectData(res || []);
          setCategoryTreeData(treeData);
        } catch (e) {
          console.error(e);
          setCategoryTreeData([]);
        } finally {
          setLoadingCategories(false);
        }
      } else {
        setCategoryTreeData([]);
        setSelectedCategory('');
        setSelectedCategoryInfo(null);
      }
    };
    loadCategories();
  }, [selectedPlatform, selectedCountry]);

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
      
      // 自动提取并保存不可售平台
      const platformMap = new Map<string, { platformId: string; platformName: string }>();
      for (const product of res || []) {
        const unavailable = product.channelSpecificFields?.unAvailablePlatform || [];
        for (const p of unavailable) {
          if (p.id && p.name) {
            platformMap.set(p.id, { platformId: p.id, platformName: p.name });
          }
        }
      }
      if (platformMap.size > 0) {
        await unavailablePlatformApi.save(Array.from(platformMap.values()), selectedChannel);
        loadUnavailablePlatforms(); // 刷新下拉框
      }
    } catch (e: any) {
      message.error(e.message || '查询失败');
    } finally {
      setLoading(false);
    }
  };

  const handleImport = async () => {
    if (selectedRows.length === 0) {
      message.warning('请选择要导入的商品');
      return;
    }

    setImporting(true);
    try {
      const importData = selectedRows.map(p => {
        const price = p.price ?? p.rawData?.price?.price ?? 0;
        const stock = p.stock ?? p.rawData?.inventory?.sellerInventoryInfo?.sellerAvailableInventory ?? 0;
        const attrs = p.standardAttributes || {};
        const specific = p.channelSpecificFields || {};
        
        // 构建多包裹信息
        const packages: any[] = [];
        if (specific.comboInfo && Array.isArray(specific.comboInfo)) {
          specific.comboInfo.forEach((pkg: any, idx: number) => {
            packages.push({
              index: idx + 1,
              length: pkg.length,
              width: pkg.width,
              height: pkg.height,
              weight: pkg.weight,
              quantity: pkg.quantity || 1,
              sku: pkg.sku,
            });
          });
        }
        
        // 判断商品性质
        const isMultiPackage = packages.length > 1;
        const isOversized = specific.overSizeFlag === true;
        const packageWeight = attrs.weight || 0;
        const maxDim = Math.max(attrs.length || 0, attrs.width || 0, attrs.height || 0);
        let productType: 'oversized' | 'small' | 'normal' | 'multiPackage' = 'normal';
        if (isMultiPackage) productType = 'multiPackage';
        else if (isOversized || packageWeight > 150 || maxDim > 108) productType = 'oversized';
        else if (packageWeight < 2 && maxDim < 18) productType = 'small';
        
        // 附加图去重：移除与主图相同的图片
        const filteredImageUrls = (p.imageUrls || []).filter((url: string) => url && url !== p.mainImageUrl);
        
        // 构建自定义属性（所有非核心字段）
        const customAttributes: any[] = [];
        const addAttr = (name: string, value: any, label?: string) => {
          if (value !== undefined && value !== null && value !== '') {
            customAttributes.push({ name, value, label, visible: true });
          }
        };
        
        // 产品标识类（放入渠道属性）
        addAttr('brand', attrs.brand, '品牌');
        addAttr('manufacturer', attrs.manufacturer, '制造商');
        addAttr('mpn', attrs.mpn, 'MPN');
        addAttr('upc', attrs.upc, 'UPC');
        addAttr('ean', attrs.ean, 'EAN');
        addAttr('gtin', attrs.gtin, 'GTIN');
        addAttr('model', attrs.model, '型号');
        
        // 外观属性类（color 和 material 已是核心字段，其他放入渠道属性）
        addAttr('colorFamily', attrs.colorFamily, '颜色系列');
        addAttr('pattern', attrs.pattern, '图案');
        addAttr('style', attrs.style, '风格');
        addAttr('finish', attrs.finish, '饰面');
        addAttr('shape', attrs.shape, '形状');
        
        // 分类信息（放入渠道属性）
        addAttr('categoryCode', attrs.categoryCode, '分类代码');
        addAttr('categoryName', attrs.category || attrs.categoryName, '分类名称');
        addAttr('categoryPath', attrs.categoryPath, '分类路径');
        
        // 其他渠道属性
        addAttr('countryOfOrigin', attrs.countryOfOrigin, '原产国');
        // characteristics 已映射到 bulletPoints，不再重复添加
        addAttr('keyFeatures', attrs.keyFeatures, '关键特性');
        
        // 渠道特有标记（保持原始值，不做转换）
        if (specific.whiteLabel) addAttr('whiteLabel', specific.whiteLabel, '白标');
        if (specific.lithiumBatteryContained) addAttr('lithiumBatteryContained', specific.lithiumBatteryContained, '含锂电池');
        if (specific.customized) addAttr('customized', specific.customized, '定制商品');
        
        // 提取渠道 attributes 对象中的所有属性（GigaCloud 等渠道的自定义属性）
        // 这些属性如：Filler, Features, Use Case, Product Type, Number of Drawers 等
        if (specific.attributes && typeof specific.attributes === 'object') {
          // 已经在核心字段或上面处理过的属性，跳过
          const skipKeys = ['Main Color', 'Color', 'color', 'Main Material', 'Material', 'material', 
                           'Brand', 'brand', 'Style', 'style', 'Pattern', 'pattern', 
                           'Finish', 'finish', 'Color Family', 'colorFamily'];
          
          Object.entries(specific.attributes).forEach(([key, value]) => {
            if (!skipKeys.includes(key) && value !== undefined && value !== null && value !== '') {
              // 将属性名转换为 camelCase 作为 name，原始名称作为 label
              const name = key.replace(/\s+/g, '').replace(/^./, c => c.toLowerCase());
              addAttr(name, value, key);
            }
          });
        }
        
        // 构建标准化商品属性结构（新版简化结构）
        const channelAttributes = {
          // ==================== 基础信息（7个）====================
          title: p.title || '',
          sku: p.sku,
          color: attrs.color, // 商品颜色
          material: attrs.material, // 商品材质
          description: p.description || '',
          bulletPoints: attrs.bulletPoints || attrs.characteristics || [],
          keywords: attrs.keywords || [], // 搜索关键词
          
          // ==================== 价格信息（5个存储）====================
          price,
          salePrice: specific.discountedPrice || attrs.salePrice,
          shippingFee: attrs.shippingFee,
          platformPrice: undefined, // 用户设置
          currency: p.currency || 'USD',
          // 注：totalPrice 和 saleTotalPrice 为计算字段，不存储
          
          // ==================== 库存（1个）====================
          stock,
          
          // ==================== 图片（2个 + 1个可选）====================
          mainImageUrl: p.mainImageUrl || '',
          imageUrls: filteredImageUrls,
          videoUrls: p.videoUrls || [],
          
          // ==================== 产品尺寸（4个）====================
          productLength: attrs.assembledLength,
          productWidth: attrs.assembledWidth,
          productHeight: attrs.assembledHeight,
          productWeight: attrs.assembledWeight,
          
          // ==================== 包装尺寸（5个）====================
          packageLength: attrs.length,
          packageWidth: attrs.width,
          packageHeight: attrs.height,
          packageWeight: attrs.weight,
          packages: packages.length > 0 ? packages : undefined,

          // ==================== 其他核心字段（3个）====================
          placeOfOrigin: attrs.placeOfOrigin,
          productType,
          supplier: specific.sellerInfo?.sellerStore,
          
          // ==================== 不可售平台 ====================
          unAvailablePlatform: specific.unAvailablePlatform || [],
          
          // ==================== 渠道属性（扩展字段）====================
          customAttributes,
        };
        
        return {
          sku: p.sku,
          title: p.title || '',
          description: p.description || '',
          mainImageUrl: p.mainImageUrl || '',
          imageUrls: filteredImageUrls,
          videoUrls: p.videoUrls || [],
          price,
          stock,
          currency: p.currency || 'USD',
          channelRawData: p.rawData,
          channelAttributes,
        };
      });

      // 导入到商品池
      const poolRes: any = await productPoolApi.import({
        channelId: selectedChannel,
        products: importData,
        duplicateAction,
        platformCategoryId: selectedCategory || undefined,
      });

      setImportModal(false);
      setSelectedRows([]);
      
      // 显示成功消息
      Modal.info({
        title: '导入完成',
        width: 500,
        content: (
          <div>
            <div style={{ marginBottom: 12 }}>
              成功: {poolRes.success}，跳过: {poolRes.skipped}，失败: {poolRes.failed}
              {poolRes.errors?.length > 0 && (
                <div style={{ marginTop: 4, maxHeight: 100, overflow: 'auto', background: '#fff1f0', padding: 8, borderRadius: 4, fontSize: 12 }}>
                  {poolRes.errors.map((e: any, i: number) => (
                    <div key={i}><strong>{e.sku}</strong>: {e.error}</div>
                  ))}
                </div>
              )}
            </div>
            <p style={{ marginTop: 8 }}>
              导入的商品可以在 <a onClick={() => { Modal.destroyAll(); navigate('/listing/product-pool'); }}>商品池</a> 页面查看
            </p>
          </div>
        ),
        okText: '确定',
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
    { title: '价格', dataIndex: 'price', width: 80, render: (v: number) => v ? `$${v.toFixed(2)}` : '-' },
    {
      title: '运费',
      width: 80,
      render: (_: any, r: any) => {
        const shippingFee = r.standardAttributes?.shippingFee;
        return shippingFee ? `$${Number(shippingFee).toFixed(2)}` : '-';
      },
    },
    {
      title: '优惠价格',
      width: 90,
      render: (_: any, r: any) => {
        const salePrice = r.standardAttributes?.salePrice || r.channelSpecificFields?.discountedPrice;
        return salePrice ? `$${Number(salePrice).toFixed(2)}` : '-';
      },
    },
    {
      title: '总价',
      width: 80,
      render: (_: any, r: any) => {
        const price = r.price || 0;
        const shippingFee = r.standardAttributes?.shippingFee || 0;
        const total = Number(price) + Number(shippingFee);
        return total > 0 ? `$${total.toFixed(2)}` : '-';
      },
    },
    {
      title: '优惠总价',
      width: 90,
      render: (_: any, r: any) => {
        const salePrice = r.standardAttributes?.salePrice || r.channelSpecificFields?.discountedPrice;
        const shippingFee = r.standardAttributes?.shippingFee || 0;
        if (!salePrice) return '-';
        const total = Number(salePrice) + Number(shippingFee);
        return `$${total.toFixed(2)}`;
      },
    },
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
      title: '不可售平台',
      width: 120,
      render: (_: any, r: any) => {
        const unavailable = r.channelSpecificFields?.unAvailablePlatform || [];
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

    // 计算总价
    const totalPrice = (p.price || 0) + (attrs.shippingFee || 0);
    const saleTotalPrice = specific.discountedPrice ? specific.discountedPrice + (attrs.shippingFee || 0) : null;

    return (
      <Tabs
        items={[
          {
            key: 'info',
            label: '商品信息',
            children: (
              <div style={{ maxHeight: 600, overflow: 'auto' }}>
                {/* 图片区域 */}
                <div style={{ marginBottom: 16, display: 'flex', gap: 16 }}>
                  {p.mainImageUrl && <Image src={p.mainImageUrl} width={150} height={150} style={{ objectFit: 'cover', borderRadius: 4 }} />}
                  {p.imageUrls?.length > 0 && (
                    <Image.PreviewGroup>
                      <Space wrap>
                        {p.imageUrls.slice(0, 5).map((url: string, i: number) => (
                          <Image key={i} src={url} width={80} height={80} style={{ objectFit: 'cover', borderRadius: 4 }} />
                        ))}
                        {p.imageUrls.length > 5 && <Tag>+{p.imageUrls.length - 5}</Tag>}
                      </Space>
                    </Image.PreviewGroup>
                  )}
                </div>

                {/* 基础信息 */}
                <Descriptions bordered column={2} size="small" style={{ marginBottom: 16 }}>
                  <Descriptions.Item label="SKU">{p.sku}</Descriptions.Item>
                  <Descriptions.Item label="库存">{p.stock}</Descriptions.Item>
                  <Descriptions.Item label="标题" span={2}>{p.title}</Descriptions.Item>
                  <Descriptions.Item label="颜色">{attrs.color || '-'}</Descriptions.Item>
                  <Descriptions.Item label="材质">{attrs.material || '-'}</Descriptions.Item>
                  <Descriptions.Item label="品牌">{attrs.brand || '-'}</Descriptions.Item>
                  <Descriptions.Item label="产地">{attrs.placeOfOrigin || '-'}</Descriptions.Item>
                  <Descriptions.Item label="类目">{attrs.category || '-'}</Descriptions.Item>
                  <Descriptions.Item label="类目代码">{attrs.categoryCode || '-'}</Descriptions.Item>
                  <Descriptions.Item label="供货商">{specific.sellerInfo?.sellerStore || '-'}</Descriptions.Item>
                  <Descriptions.Item label="MPN">{attrs.mpn || '-'}</Descriptions.Item>
                </Descriptions>

                {/* 价格信息 */}
                <Descriptions bordered column={3} size="small" title="价格信息" style={{ marginBottom: 16 }}>
                  <Descriptions.Item label="商品价格">${Number(p.price || 0).toFixed(2)}</Descriptions.Item>
                  <Descriptions.Item label="运费">${Number(attrs.shippingFee || 0).toFixed(2)}</Descriptions.Item>
                  <Descriptions.Item label="商品总价">${totalPrice.toFixed(2)}</Descriptions.Item>
                  <Descriptions.Item label="优惠价格">{specific.discountedPrice ? `$${Number(specific.discountedPrice).toFixed(2)}` : '-'}</Descriptions.Item>
                  <Descriptions.Item label="优惠总价">{saleTotalPrice ? `$${saleTotalPrice.toFixed(2)}` : '-'}</Descriptions.Item>
                  <Descriptions.Item label="货币">{p.currency || 'USD'}</Descriptions.Item>
                </Descriptions>

                {/* 产品尺寸 */}
                <Descriptions bordered column={4} size="small" title="产品尺寸（组装后）" style={{ marginBottom: 16 }}>
                  <Descriptions.Item label="长度">{attrs.assembledLength ? `${attrs.assembledLength} in` : '-'}</Descriptions.Item>
                  <Descriptions.Item label="宽度">{attrs.assembledWidth ? `${attrs.assembledWidth} in` : '-'}</Descriptions.Item>
                  <Descriptions.Item label="高度">{attrs.assembledHeight ? `${attrs.assembledHeight} in` : '-'}</Descriptions.Item>
                  <Descriptions.Item label="重量">{attrs.assembledWeight ? `${attrs.assembledWeight} lb` : '-'}</Descriptions.Item>
                </Descriptions>

                {/* 包装尺寸 */}
                <Descriptions bordered column={4} size="small" title="包装尺寸" style={{ marginBottom: 16 }}>
                  <Descriptions.Item label="长度">{attrs.length ? `${attrs.length} in` : '-'}</Descriptions.Item>
                  <Descriptions.Item label="宽度">{attrs.width ? `${attrs.width} in` : '-'}</Descriptions.Item>
                  <Descriptions.Item label="高度">{attrs.height ? `${attrs.height} in` : '-'}</Descriptions.Item>
                  <Descriptions.Item label="重量">{attrs.weight ? `${attrs.weight} lb` : '-'}</Descriptions.Item>
                </Descriptions>

                {/* 多包裹信息 */}
                {specific.comboInfo?.length > 0 && (
                  <div style={{ marginBottom: 16 }}>
                    <div style={{ fontWeight: 500, marginBottom: 8 }}>多包裹信息</div>
                    <Table
                      dataSource={specific.comboInfo}
                      columns={[
                        { title: '包裹', dataIndex: 'sku', width: 120 },
                        { title: '数量', dataIndex: 'quantity', width: 60 },
                        { title: '长度', dataIndex: 'length', width: 80, render: (v: number) => v ? `${v} in` : '-' },
                        { title: '宽度', dataIndex: 'width', width: 80, render: (v: number) => v ? `${v} in` : '-' },
                        { title: '高度', dataIndex: 'height', width: 80, render: (v: number) => v ? `${v} in` : '-' },
                        { title: '重量', dataIndex: 'weight', width: 80, render: (v: number) => v ? `${v} lb` : '-' },
                      ]}
                      size="small"
                      pagination={false}
                      rowKey="sku"
                    />
                  </div>
                )}

                {/* 商品标记 */}
                <div style={{ marginBottom: 16 }}>
                  <div style={{ fontWeight: 500, marginBottom: 8 }}>商品标记</div>
                  <Space wrap>
                    {specific.whiteLabel && <Tag color="blue">白标: {specific.whiteLabel}</Tag>}
                    {specific.comboFlag && <Tag color="orange">组合商品</Tag>}
                    {specific.overSizeFlag && <Tag color="red">超大件</Tag>}
                    {specific.partFlag && <Tag color="purple">配件</Tag>}
                    {specific.customized && <Tag color="green">定制商品</Tag>}
                    {specific.lithiumBatteryContained && <Tag color="warning">含锂电池</Tag>}
                    {specific.skuAvailable === false && <Tag color="error">不可用</Tag>}
                    {specific.skuAvailable === true && <Tag color="success">可用</Tag>}
                    {!specific.whiteLabel && !specific.comboFlag && !specific.overSizeFlag && !specific.partFlag && !specific.customized && !specific.lithiumBatteryContained && specific.skuAvailable === undefined && <span style={{ color: '#999' }}>无特殊标记</span>}
                  </Space>
                </div>

                {/* 商品特点/五点描述 */}
                {attrs.characteristics?.length > 0 && (
                  <div style={{ marginBottom: 16 }}>
                    <div style={{ fontWeight: 500, marginBottom: 8 }}>商品特点</div>
                    <ul style={{ margin: 0, paddingLeft: 20 }}>
                      {attrs.characteristics.map((c: string, i: number) => (
                        <li key={i} style={{ marginBottom: 4 }}>{c}</li>
                      ))}
                    </ul>
                  </div>
                )}

                {/* 商品描述 */}
                {p.description && (
                  <div>
                    <div style={{ fontWeight: 500, marginBottom: 8 }}>商品描述</div>
                    <div
                      style={{ maxHeight: 200, overflow: 'auto', padding: 12, background: '#fafafa', borderRadius: 4, fontSize: 13 }}
                      dangerouslySetInnerHTML={{ __html: p.description }}
                    />
                  </div>
                )}

                {/* 渠道属性 */}
                {specific.attributes && Object.keys(specific.attributes).length > 0 && (
                  <div style={{ marginTop: 16 }}>
                    <div style={{ fontWeight: 500, marginBottom: 8 }}>渠道属性</div>
                    <Descriptions bordered column={2} size="small">
                      {Object.entries(specific.attributes).map(([k, v]) => (
                        <Descriptions.Item key={k} label={k}>{String(v)}</Descriptions.Item>
                      ))}
                    </Descriptions>
                  </div>
                )}
              </div>
            ),
          },
          {
            key: 'raw',
            label: '原始数据',
            children: (
              <div style={{ maxHeight: 600, overflow: 'auto' }}>
                <pre style={{ background: '#f5f5f5', padding: 12, borderRadius: 4, fontSize: 12, margin: 0 }}>
                  {JSON.stringify(p.rawData, null, 2)}
                </pre>
              </div>
            ),
          },
        ]}
      />
    );
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

      <Card 
        title={`查询结果 (${products.length})`}
        extra={
          <Space>
            <span style={{ color: '#666' }}>排除不可售平台:</span>
            <Select
              placeholder="全部显示"
              style={{ width: 150 }}
              value={filterPlatform || undefined}
              onChange={v => setFilterPlatform(v || '')}
              allowClear
              options={unavailablePlatforms.map(p => ({ value: p.platformName, label: p.platformName }))}
            />
          </Space>
        }
      >
        <Table
          dataSource={products.filter(p => {
            // 如果没有选择筛选平台，显示全部
            if (!filterPlatform) return true;
            // 如果选择了筛选平台，排除禁售该平台的商品
            const unavailable = p.channelSpecificFields?.unAvailablePlatform || [];
            return !unavailable.some((up: any) => up.name === filterPlatform);
          })}
          columns={columns}
          rowKey="sku"
          loading={loading}
          size="small"
          scroll={{ x: 1760 }}
          rowSelection={{
            selectedRowKeys: selectedRows.map(r => r.sku),
            onChange: (_, rows) => setSelectedRows(rows),
            getCheckboxProps: (record: any) => {
              // 如果选择了筛选平台，禁止选中禁售该平台的商品
              if (!filterPlatform) return {};
              const unavailable = record.channelSpecificFields?.unAvailablePlatform || [];
              const isUnavailable = unavailable.some((up: any) => up.name === filterPlatform);
              return { disabled: isUnavailable };
            },
          }}
          pagination={{ pageSize: 20, showSizeChanger: true, showTotal: t => `共 ${t} 条` }}
        />
      </Card>

      {/* 导入弹窗 */}
      <Modal
        title="导入商品到商品池"
        open={importModal}
        onOk={handleImport}
        onCancel={() => { 
          setImportModal(false); 
          setDuplicateAction('skip');
          setSelectedPlatform('');
          setSelectedCategory('');
          setSelectedCategoryInfo(null);
        }}
        confirmLoading={importing}
        okText="导入"
        width={600}
      >
        <div style={{ marginBottom: 16 }}>
          <p>已选择 <strong>{selectedRows.length}</strong> 个商品，将导入到商品池</p>
        </div>

        <div style={{ marginBottom: 16 }}>
          <div style={{ marginBottom: 8, fontWeight: 500 }}>
            选择平台（可选）
          </div>
          <Space style={{ width: '100%' }}>
            <Select
              placeholder="选择目标平台"
              style={{ width: 200 }}
              value={selectedPlatform || undefined}
              onChange={v => { 
                setSelectedPlatform(v || ''); 
                setSelectedCategory(''); 
                setSelectedCategoryInfo(null); 
              }}
              allowClear
              options={platforms.map(p => ({ value: p.id, label: p.name }))}
            />
            {selectedPlatform && (
              <Select
                placeholder="选择国家/地区"
                style={{ width: 150 }}
                value={selectedCountry}
                onChange={v => { 
                  setSelectedCountry(v); 
                  setSelectedCategory(''); 
                  setSelectedCategoryInfo(null); 
                }}
                options={[
                  { value: 'US', label: '🇺🇸 美国 (US)' },
                  { value: 'CA', label: '🇨🇦 加拿大 (CA)' },
                  { value: 'MX', label: '🇲🇽 墨西哥 (MX)' },
                ]}
              />
            )}
          </Space>
        </div>

        {selectedPlatform && (
          <div style={{ marginBottom: 16 }}>
            <div style={{ marginBottom: 8, fontWeight: 500 }}>
              平台类目（可选）- {selectedCountry === 'US' ? '美国' : selectedCountry === 'CA' ? '加拿大' : '墨西哥'}
            </div>
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
            {selectedCategoryInfo && (
              <div style={{ marginTop: 8, padding: 8, background: '#f5f5f5', borderRadius: 4 }}>
                <Space>
                  <FolderOutlined />
                  <span>{selectedCategoryInfo.categoryPath || selectedCategoryInfo.name}</span>
                </Space>
              </div>
            )}
          </div>
        )}

        <div style={{ marginBottom: 16 }}>
          <div style={{ marginBottom: 8, fontWeight: 500 }}>
            重复处理
          </div>
          <Select
            style={{ width: '100%' }}
            value={duplicateAction}
            onChange={setDuplicateAction}
            options={[
              { value: 'skip', label: '跳过 - 如果SKU已存在则跳过' },
              { value: 'update', label: '替换 - 如果SKU已存在则更新数据' },
            ]}
          />
        </div>

        <Alert
          message="提示"
          description="选择平台类目后，可以在图片处理模块中按类目批量处理图片"
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
