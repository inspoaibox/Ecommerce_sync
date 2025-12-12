import { useState, useEffect } from 'react';
import { Card, Tree, Input, Button, Space, message, Spin, Tag, Descriptions, Empty, Select, Table, Form, Modal, Popconfirm, Tooltip, Alert } from 'antd';
import { SyncOutlined, SearchOutlined, GlobalOutlined, SaveOutlined, DeleteOutlined, EditOutlined, DownloadOutlined, ReloadOutlined, ImportOutlined, SettingOutlined } from '@ant-design/icons';
import { platformApi, platformCategoryApi } from '@/services/api';
import type { DataNode } from 'antd/es/tree';

// Walmart 支持的市场
const COUNTRY_OPTIONS = [
  { value: 'US', label: '🇺🇸 美国 (US)' },
  { value: 'CA', label: '🇨🇦 加拿大 (CA)' },
  { value: 'MX', label: '🇲🇽 墨西哥 (MX)' },
  { value: 'CL', label: '🇨🇱 智利 (CL)' },
];

// 映射类型选项
const MAPPING_TYPE_OPTIONS = [
  { value: 'default_value', label: '默认值', color: 'blue' },
  { value: 'channel_data', label: '渠道数据', color: 'green' },
  { value: 'enum_select', label: '枚举选择', color: 'orange' },
  { value: 'auto_generate', label: '自动生成', color: 'purple' },
  { value: 'upc_pool', label: 'UPC池', color: 'cyan' },
];

// 自动生成规则类型映射
const AUTO_GENERATE_RULES: Record<string, { name: string; description: string }> = {
  sku_prefix: { name: 'SKU前缀拼接', description: '在SKU前添加指定前缀' },
  sku_suffix: { name: 'SKU后缀拼接', description: '在SKU后添加指定后缀' },
  brand_title: { name: '品牌+标题组合', description: '将品牌和标题组合成一个字符串' },
  first_characteristic: { name: '取第一个特点', description: '从商品特点列表中取第一个' },
  first_bullet_point: { name: '取第一条五点描述', description: '从五点描述列表中取第一条' },
  current_date: { name: '当前日期', description: '生成当前日期' },
  uuid: { name: '生成UUID', description: '生成唯一标识符' },
  color_extract: { name: '智能提取颜色', description: '优先从颜色字段取值，否则从标题/描述提取' },
  material_extract: { name: '智能提取材质', description: '优先从材质字段取值，否则从标题/描述提取' },
  field_with_fallback: { name: '多字段回退取值', description: '按顺序尝试多个字段，返回第一个非空值' },
  location_extract: { name: '智能提取使用场景', description: '从标题/描述判断 Indoor 或 Outdoor，默认 Indoor' },
  piece_count_extract: { name: '智能提取产品数量', description: '从标题/描述提取件数，剔除无关数字，默认1' },
  seating_capacity_extract: { name: '智能提取座位容量', description: '从标题/描述提取座位数，如 3-seater、seats 4 等' },
  price_calculate: { name: '计算售价', description: '根据店铺同步规则的价格倍率计算：(原价+运费)*倍率+增减值' },
  shipping_weight_extract: { name: '智能提取运输重量', description: '优先从渠道重量字段获取，否则从描述提取，自动转换kg/g/oz为磅' },
  collection_extract: { name: '智能提取产品系列', description: '使用"使用场所+产品主体"格式生成，如 Indoor Sofa、Outdoor Dining Table' },
  color_category_extract: { name: '智能提取颜色分类', description: '从color字段匹配最接近的Walmart颜色枚举值，默认Multicolor' },
  home_decor_style_extract: { name: '智能提取家居风格', description: '从标题/描述提取家居装饰风格，如Modern、Farmhouse等，默认Minimalist' },
  items_included_extract: { name: '智能提取包含物品', description: '从标题/描述提取套装包含的物品列表，排除装饰物品，无则留空' },
  leg_color_extract: { name: '智能提取腿部颜色', description: '从描述提取家具腿的颜色，如Black、White、Brown等，无则留空' },
  leg_finish_extract: { name: '智能提取腿部表面处理', description: '从描述提取家具腿的表面处理方式，如Glossy、Matte等，无则留空' },
  leg_material_extract: { name: '智能提取腿部材料', description: '从描述提取家具腿的材料，如Wood、Metal、Plastic等，无则留空' },
  mpn_from_sku: { name: 'SKU转制造商零件号', description: '使用渠道SKU作为MPN，自动将中文编码为英文字符' },
  living_room_set_type_extract: { name: '智能提取客厅套装类型', description: '从描述提取客厅家具套装类型，默认Living Room Set' },
  max_load_weight_extract: { name: '智能提取最大承重', description: '从描述提取最大承重数值（磅），无则留空' },
  net_content_statement_extract: { name: '智能提取净含量声明', description: '从描述提取净含量声明，如"1.98 Lb"，无则留空' },
  pattern_extract: { name: '智能提取图案', description: '从描述提取图案，无则使用颜色+主体格式，如"Black Table"' },
  product_line_from_category: { name: '从分类名称生成产品线', description: '使用所在分类名称作为产品线' },
  seat_back_height_extract: { name: '智能提取座椅靠背高度', description: '从描述提取座椅靠背高度（英寸），无则留空' },
  seat_color_extract: { name: '智能提取座椅颜色', description: '从描述提取座椅颜色，无则留空' },
  seat_height_extract: { name: '智能提取座椅高度', description: '从描述提取座椅高度（英寸），无则留空' },
  seat_material_extract: { name: '智能提取座椅材料', description: '从描述提取座椅材料，如Leather、Fabric等，无则留空' },
  upholstered_extract: { name: '智能提取是否软包', description: '从描述判断是否软包家具，默认No' },
  electronics_indicator_extract: { name: '智能提取电子元件', description: '从描述判断是否含电子元件，默认No' },
  date_offset: { name: '日期偏移（天）', description: '基于当前日期偏移指定天数，负数为往前，正数为往后' },
  date_offset_years: { name: '日期偏移（年）', description: '基于当前日期偏移指定年数，如10表示往后10年' },
};

// 常用渠道字段路径（基于新版简化 StandardProduct 接口）
const CHANNEL_DATA_OPTIONS = [
  // ==================== 基础信息 ====================
  { value: 'title', label: '商品标题 (title)' },
  { value: 'sku', label: 'SKU' },
  { value: 'color', label: '商品颜色 (color)' },
  { value: 'material', label: '商品材质 (material)' },
  { value: 'description', label: '商品描述 (description)' },
  { value: 'bulletPoints', label: '五点描述 (bulletPoints)' },
  { value: 'keywords', label: '搜索关键词 (keywords)' },

  // ==================== 价格信息 ====================
  { value: 'price', label: '商品价格 (price)' },
  { value: 'salePrice', label: '优惠价格 (salePrice)' },
  { value: 'shippingFee', label: '运费价格 (shippingFee)' },
  { value: 'platformPrice', label: '平台售价 (platformPrice)' },
  { value: 'currency', label: '货币 (currency)' },

  // ==================== 库存 ====================
  { value: 'stock', label: '库存数量 (stock)' },

  // ==================== 图片媒体 ====================
  { value: 'mainImageUrl', label: '主图URL (mainImageUrl)' },
  { value: 'imageUrls', label: '商品图片 (imageUrls)' },

  // ==================== 产品尺寸 ====================
  { value: 'productLength', label: '产品长度 (productLength)' },
  { value: 'productWidth', label: '产品宽度 (productWidth)' },
  { value: 'productHeight', label: '产品高度 (productHeight)' },
  { value: 'productWeight', label: '产品重量 (productWeight)' },

  // ==================== 包装尺寸 ====================
  { value: 'packageLength', label: '包装长度 (packageLength)' },
  { value: 'packageWidth', label: '包装宽度 (packageWidth)' },
  { value: 'packageHeight', label: '包装高度 (packageHeight)' },
  { value: 'packageWeight', label: '包装重量 (packageWeight)' },

  // ==================== 其他 ====================
  { value: 'placeOfOrigin', label: '产地 (placeOfOrigin)' },
  { value: 'supplier', label: '供货商 (supplier)' },
];

interface MappingRule {
  attributeId: string;
  attributeName: string;
  mappingType: 'default_value' | 'channel_data' | 'enum_select' | 'auto_generate' | 'upc_pool';
  value: string;
  isRequired: boolean;
  dataType: string;
  enumValues?: string[];
  conditionalRequired?: Array<{
    dependsOn: string;
    dependsOnValue: string;
  }>;
}

export default function CategoryBrowser() {
  const [platforms, setPlatforms] = useState<any[]>([]);
  const [selectedPlatform, setSelectedPlatform] = useState<string>('');
  const [selectedCountry, setSelectedCountry] = useState<string>('US');
  const [availableCountries, setAvailableCountries] = useState<string[]>([]);
  const [treeData, setTreeData] = useState<DataNode[]>([]);
  const [loading, setLoading] = useState(false);
  const [syncing, setSyncing] = useState(false);
  const [searchKeyword, setSearchKeyword] = useState('');
  const [searchResults, setSearchResults] = useState<any[]>([]);
  const [searching, setSearching] = useState(false);
  const [selectedCategory, setSelectedCategory] = useState<any>(null);
  const [, setAttributes] = useState<any[]>([]);
  const [loadingAttrs, setLoadingAttrs] = useState(false);
  
  // 常用类目
  const [frequentCategories, setFrequentCategories] = useState<any[]>([]);
  const [, setLoadingFrequent] = useState(false);
  
  // 属性映射相关状态
  const [mappingRules, setMappingRules] = useState<MappingRule[]>([]);
  const [loadingMapping, setLoadingMapping] = useState(false);
  const [savingMapping, setSavingMapping] = useState(false);
  const [editingRule, setEditingRule] = useState<MappingRule | null>(null);
  const [editModalVisible, setEditModalVisible] = useState(false);
  const [form] = Form.useForm();
  
  // 加载配置相关状态
  const [loadingMappings, setLoadingMappings] = useState(false);
  
  // 默认配置管理相关状态
  const [defaultConfigModalVisible, setDefaultConfigModalVisible] = useState(false);
  const [defaultMappingRules, setDefaultMappingRules] = useState<MappingRule[]>([]);
  const [loadingDefaultConfig, setLoadingDefaultConfig] = useState(false);
  const [savingDefaultConfig, setSavingDefaultConfig] = useState(false);



  useEffect(() => {
    loadPlatforms();
  }, []);

  const loadPlatforms = async () => {
    try {
      const res: any = await platformApi.list({ pageSize: 100 });
      setPlatforms(res.data || []);
      if (res.data?.length > 0) {
        setSelectedPlatform(res.data[0].id);
      }
    } catch (e) {
      console.error(e);
    }
  };

  useEffect(() => {
    if (selectedPlatform) {
      loadAvailableCountries();
      loadCategoryTree();
      loadFrequentCategories();
    }
  }, [selectedPlatform, selectedCountry]);

  const loadFrequentCategories = async () => {
    if (!selectedPlatform) return;
    setLoadingFrequent(true);
    try {
      const res: any = await platformCategoryApi.getFrequentCategories(selectedPlatform, selectedCountry, 10);
      setFrequentCategories(res || []);
    } catch (e) {
      console.error(e);
      setFrequentCategories([]);
    } finally {
      setLoadingFrequent(false);
    }
  };

  const loadAvailableCountries = async () => {
    try {
      const res: any = await platformCategoryApi.getCountries(selectedPlatform);
      setAvailableCountries(res || []);
    } catch (e) {
      console.error(e);
    }
  };

  const loadCategoryTree = async () => {
    setLoading(true);
    try {
      const res: any = await platformCategoryApi.getCategoryTree(selectedPlatform, selectedCountry);
      setTreeData(convertToTreeData(res || []));
    } catch (e) {
      console.error(e);
    } finally {
      setLoading(false);
    }
  };

  const convertToTreeData = (categories: any[]): DataNode[] => {
    return categories.map(cat => ({
      key: cat.id,
      title: (
        <span>
          {cat.name}
          {cat.isLeaf && <Tag color="green" style={{ marginLeft: 8 }}>叶子</Tag>}
        </span>
      ),
      isLeaf: cat.isLeaf,
      children: cat.children ? convertToTreeData(cat.children) : undefined,
      data: cat,
    }));
  };

  const handleSync = async () => {
    if (!selectedPlatform) return;
    setSyncing(true);
    try {
      const res: any = await platformCategoryApi.syncCategories(selectedPlatform, selectedCountry);
      message.success(`同步完成（${res.country}）：新增 ${res.created}，更新 ${res.updated}，共 ${res.total} 个类目`);
      loadAvailableCountries();
      loadCategoryTree();
    } catch (e: any) {
      message.error(e.message || '同步失败');
    } finally {
      setSyncing(false);
    }
  };

  const [clearing, setClearing] = useState(false);
  
  const handleClear = async () => {
    if (!selectedPlatform || !selectedCountry) return;
    setClearing(true);
    try {
      const res: any = await platformCategoryApi.clearCategories(selectedPlatform, selectedCountry);
      message.success(`已清空 ${res.country} 区域的 ${res.deleted} 个类目`);
      loadAvailableCountries();
      loadCategoryTree();
      setSelectedCategory(null);
      setMappingRules([]);
    } catch (e: any) {
      message.error(e.message || '清空失败');
    } finally {
      setClearing(false);
    }
  };

  const handleSearch = async () => {
    if (!selectedPlatform || !searchKeyword.trim()) {
      message.warning('请输入搜索关键词');
      return;
    }
    setSearching(true);
    try {
      const res: any = await platformCategoryApi.searchCategories(selectedPlatform, searchKeyword.trim(), selectedCountry);
      const results = res?.data || res || [];
      setSearchResults(results);
      if (results.length === 0) {
        message.info('未找到匹配的类目');
      }
    } catch (e: any) {
      console.error(e);
      message.error(e.message || '搜索失败');
    } finally {
      setSearching(false);
    }
  };

  const handleSelectCategory = async (category: any) => {
    setSelectedCategory(category);
    setLoadingMapping(true);
    setAttributes([]);
    
    try {
      // 只加载已保存的映射配置
      const mappingRes: any = await platformCategoryApi.getCategoryAttributeMapping(
        selectedPlatform, 
        category.categoryId, 
        selectedCountry
      ).catch(() => null);
      
      if (mappingRes?.mappingRules?.rules) {
        setMappingRules(mappingRes.mappingRules.rules);
      } else {
        // 没有映射配置，显示空列表
        setMappingRules([]);
      }
    } catch (e) {
      console.error(e);
      setMappingRules([]);
    } finally {
      setLoadingMapping(false);
    }
  };

  // 从平台加载属性并生成映射规则
  const handleLoadPlatformAttributes = async () => {
    if (!selectedCategory) return;
    
    // 检查是否是叶子类目
    if (!selectedCategory.isLeaf) {
      message.warning('请选择叶子类目（Product Type）才能加载属性');
      return;
    }
    
    setLoadingAttrs(true);
    try {
      console.log('[CategoryBrowser] Loading attributes for category:', selectedCategory.categoryId);
      
      // forceRefresh=true 强制从平台重新获取，清除数据库缓存
      const attrsRes: any = await platformCategoryApi.getCategoryAttributes(
        selectedPlatform, 
        selectedCategory.categoryId, 
        selectedCountry,
        true, // forceRefresh
      );
      
      console.log('[CategoryBrowser] Attributes response:', attrsRes);
      
      setAttributes(attrsRes || []);
      
      if (!attrsRes || attrsRes.length === 0) {
        // 显示更详细的提示
        Modal.info({
          title: '属性加载结果',
          content: (
            <div>
              <p>该类目暂无属性数据返回，可能的原因：</p>
              <ul>
                <li>平台 API 暂时不可用</li>
                <li>该类目在平台上没有定义属性规范</li>
                <li>需要使用正确的 Product Type ID</li>
              </ul>
              <p style={{ marginTop: 12 }}>
                <strong>类目ID:</strong> {selectedCategory.categoryId}<br/>
                <strong>类目路径:</strong> {selectedCategory.categoryPath}
              </p>
            </div>
          ),
        });
        return;
      }
      
      // 只加载平台属性，不做任何自动匹配
      // 用户需要手动配置或点击"加载配置"按钮来应用属性字段库规则
      const newRules: MappingRule[] = attrsRes.map((attr: any) => ({
        attributeId: attr.attributeId,
        attributeName: attr.name,
        mappingType: 'default_value' as const,
        value: '',
        isRequired: attr.isRequired,
        dataType: attr.dataType,
        enumValues: attr.enumValues,
        conditionalRequired: attr.conditionalRequired,
      }));
      
      // 按必填优先排序：必填 > 条件必填 > 非必填
      newRules.sort((a: MappingRule, b: MappingRule) => {
        const aHasConditional = a.conditionalRequired && a.conditionalRequired.length > 0;
        const bHasConditional = b.conditionalRequired && b.conditionalRequired.length > 0;
        
        // 必填字段排最前
        if (a.isRequired && !b.isRequired) return -1;
        if (!a.isRequired && b.isRequired) return 1;
        
        // 条件必填字段排在必填之后、非必填之前
        if (!a.isRequired && !b.isRequired) {
          if (aHasConditional && !bHasConditional) return -1;
          if (!aHasConditional && bHasConditional) return 1;
        }
        
        return 0;
      });
      
      setMappingRules(newRules);
      
      const requiredCount = newRules.filter((r: MappingRule) => r.isRequired).length;
      const conditionalCount = newRules.filter((r: MappingRule) => !r.isRequired && r.conditionalRequired?.length).length;
      message.success(`已加载 ${newRules.length} 个属性（${requiredCount} 个必填，${conditionalCount} 个条件必填）`);
    } catch (e: any) {
      console.error('[CategoryBrowser] Load attributes error:', e);
      message.error(e.message || '加载属性失败，请检查网络连接或稍后重试');
    } finally {
      setLoadingAttrs(false);
    }
  };

  // 重置映射规则（重新从平台加载）
  const handleResetMapping = () => {
    Modal.confirm({
      title: '重置映射配置',
      content: '确定要重新从平台加载属性吗？当前未保存的配置将丢失。',
      onOk: handleLoadPlatformAttributes,
    });
  };

  // 调试API - 原始响应（Walmart V5.0 Spec JSON Schema）
  const handleDebugApiRaw = async () => {
    if (!selectedCategory) {
      message.warning('请先选择一个类目');
      return;
    }
    if (!selectedCategory.isLeaf) {
      message.warning('请选择叶子类目（Product Type）');
      return;
    }

    try {
      message.loading({ content: '正在获取原始数据...', key: 'debug-raw' });
      const res: any = await platformCategoryApi.getCategoryAttributesRaw(
        selectedPlatform,
        selectedCategory.categoryId,
        selectedCountry
      );
      message.destroy('debug-raw');

      // 在新窗口中显示JSON数据
      const newWindow = window.open('', '_blank');
      if (newWindow) {
        const htmlContent = `
<!DOCTYPE html>
<html>
<head>
  <title>原始API响应 - ${selectedCategory.name}</title>
  <style>
    body { font-family: monospace; margin: 0; padding: 16px; background: #1e1e1e; color: #d4d4d4; }
    .header { background: #252526; padding: 12px 16px; margin: -16px -16px 16px; border-bottom: 1px solid #3c3c3c; }
    .header h3 { margin: 0 0 8px; color: #fff; }
    .tag { display: inline-block; padding: 2px 8px; margin-right: 8px; border-radius: 4px; font-size: 12px; }
    .tag-blue { background: #1890ff; color: #fff; }
    .tag-green { background: #52c41a; color: #fff; }
    .tag-orange { background: #fa8c16; color: #fff; }
    .tag-default { background: #3c3c3c; color: #d4d4d4; }
    pre { background: #2d2d2d; padding: 16px; border-radius: 4px; overflow: auto; line-height: 1.6; font-size: 13px; }
    .string { color: #ce9178; }
    .number { color: #b5cea8; }
    .boolean { color: #569cd6; }
    .null { color: #569cd6; }
    .key { color: #9cdcfe; }
    .note { background: #3c3c3c; padding: 12px; border-radius: 4px; margin-bottom: 16px; font-size: 13px; }
    .note strong { color: #ffd700; }
  </style>
</head>
<body>
  <div class="header">
    <h3>Walmart V5.0 Spec API 原始响应</h3>
    <span class="tag tag-orange">原始JSON Schema</span>
    <span class="tag tag-blue">类目: ${selectedCategory.name}</span>
    <span class="tag tag-green">ID: ${selectedCategory.categoryId}</span>
    <span class="tag tag-default">国家: ${selectedCountry}</span>
  </div>
  <div class="note">
    <strong>说明：</strong>这是 Walmart V5.0 Item Spec API 的原始响应，包含完整的 JSON Schema 定义。<br/>
    对于 measurement 类型字段（如重量、尺寸），查看 <code>properties.measure</code> 和 <code>properties.unit.enum</code> 获取单位信息。
  </div>
  <pre id="json"></pre>
  <script>
    const data = ${JSON.stringify(res)};
    function syntaxHighlight(json) {
      json = JSON.stringify(json, null, 2);
      json = json.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
      return json.replace(/("(\\\\u[a-zA-Z0-9]{4}|\\\\[^u]|[^\\\\"])*"(\\s*:)?|\\b(true|false|null)\\b|-?\\d+(?:\\.\\d*)?(?:[eE][+\\-]?\\d+)?)/g, function (match) {
        var cls = 'number';
        if (/^"/.test(match)) {
          if (/:$/.test(match)) {
            cls = 'key';
          } else {
            cls = 'string';
          }
        } else if (/true|false/.test(match)) {
          cls = 'boolean';
        } else if (/null/.test(match)) {
          cls = 'null';
        }
        return '<span class="' + cls + '">' + match + '</span>';
      });
    }
    document.getElementById('json').innerHTML = syntaxHighlight(data);
  </script>
</body>
</html>`;
        newWindow.document.write(htmlContent);
        newWindow.document.close();
      } else {
        message.error('无法打开新窗口，请检查浏览器设置');
      }
    } catch (e: any) {
      message.destroy('debug-raw');
      message.error(e.message || '获取数据失败');
    }
  };

  // 调试API - 在新窗口打开平台属性原始数据
  const handleDebugApi = async () => {
    if (!selectedCategory) {
      message.warning('请先选择一个类目');
      return;
    }
    if (!selectedCategory.isLeaf) {
      message.warning('请选择叶子类目（Product Type）');
      return;
    }

    try {
      message.loading({ content: '正在获取数据...', key: 'debug' });
      const res: any = await platformCategoryApi.getCategoryAttributes(
        selectedPlatform,
        selectedCategory.categoryId,
        selectedCountry
      );
      message.destroy('debug');

      // 在新窗口中显示JSON数据
      const newWindow = window.open('', '_blank');
      if (newWindow) {
        const htmlContent = `
<!DOCTYPE html>
<html>
<head>
  <title>调试API - ${selectedCategory.name}</title>
  <style>
    body { font-family: monospace; margin: 0; padding: 16px; background: #1e1e1e; color: #d4d4d4; }
    .header { background: #252526; padding: 12px 16px; margin: -16px -16px 16px; border-bottom: 1px solid #3c3c3c; }
    .header h3 { margin: 0 0 8px; color: #fff; }
    .tag { display: inline-block; padding: 2px 8px; margin-right: 8px; border-radius: 4px; font-size: 12px; }
    .tag-blue { background: #1890ff; color: #fff; }
    .tag-green { background: #52c41a; color: #fff; }
    .tag-default { background: #3c3c3c; color: #d4d4d4; }
    pre { background: #2d2d2d; padding: 16px; border-radius: 4px; overflow: auto; line-height: 1.6; font-size: 13px; }
    .string { color: #ce9178; }
    .number { color: #b5cea8; }
    .boolean { color: #569cd6; }
    .null { color: #569cd6; }
    .key { color: #9cdcfe; }
  </style>
</head>
<body>
  <div class="header">
    <h3>平台属性 API 响应</h3>
    <span class="tag tag-blue">类目: ${selectedCategory.name}</span>
    <span class="tag tag-green">ID: ${selectedCategory.categoryId}</span>
    <span class="tag tag-default">国家: ${selectedCountry}</span>
    <span class="tag tag-default">属性数量: ${Array.isArray(res) ? res.length : 0}</span>
  </div>
  <pre id="json"></pre>
  <script>
    const data = ${JSON.stringify(res)};
    function syntaxHighlight(json) {
      json = JSON.stringify(json, null, 2);
      json = json.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
      return json.replace(/("(\\\\u[a-zA-Z0-9]{4}|\\\\[^u]|[^\\\\"])*"(\\s*:)?|\\b(true|false|null)\\b|-?\\d+(?:\\.\\d*)?(?:[eE][+\\-]?\\d+)?)/g, function (match) {
        var cls = 'number';
        if (/^"/.test(match)) {
          if (/:$/.test(match)) {
            cls = 'key';
          } else {
            cls = 'string';
          }
        } else if (/true|false/.test(match)) {
          cls = 'boolean';
        } else if (/null/.test(match)) {
          cls = 'null';
        }
        return '<span class="' + cls + '">' + match + '</span>';
      });
    }
    document.getElementById('json').innerHTML = syntaxHighlight(data);
  </script>
</body>
</html>`;
        newWindow.document.write(htmlContent);
        newWindow.document.close();
      } else {
        message.error('无法打开新窗口，请检查浏览器设置');
      }
    } catch (e: any) {
      message.destroy('debug');
      message.error(e.message || '获取数据失败');
    }
  };

  // 从属性字段库加载配置并应用到当前类目属性
  const handleLoadConfig = async () => {
    if (mappingRules.length === 0) {
      message.warning('当前类目没有属性，请先加载平台属性');
      return;
    }

    setLoadingMappings(true);
    try {
      // 获取属性字段库
      const res: any = await platformCategoryApi.getDefaultAttributeMapping(selectedPlatform, selectedCountry);
      const defaultRules = res?.mappingRules?.rules || [];

      console.log('[handleLoadConfig] 属性字段库规则数量:', defaultRules.length);
      console.log('[handleLoadConfig] 属性字段库 attributeIds:', defaultRules.map((r: any) => r.attributeId));
      console.log('[handleLoadConfig] 当前类目属性数量:', mappingRules.length);
      console.log('[handleLoadConfig] 当前类目 attributeIds:', mappingRules.map(r => r.attributeId));

      if (defaultRules.length === 0) {
        message.warning('属性字段库为空，请先配置');
        return;
      }

      // 构建 attributeId 到默认规则的映射（精确匹配）
      const defaultRuleMap = new Map<string, MappingRule>(
        defaultRules.map((r: MappingRule) => [r.attributeId, r])
      );

      // 统计匹配数量
      let matchedCount = 0;
      const updatedRules = mappingRules.map(rule => {
        const defaultRule = defaultRuleMap.get(rule.attributeId);
        // 只要找到匹配的规则且有映射类型就应用
        if (defaultRule && defaultRule.mappingType) {
          matchedCount++;
          return {
            ...rule,
            mappingType: defaultRule.mappingType,
            value: defaultRule.value ?? '',
          };
        }
        return rule;
      });

      console.log('[handleLoadConfig] 匹配数量:', matchedCount);

      if (matchedCount === 0) {
        message.info('没有找到匹配的属性规则');
        return;
      }

      setMappingRules(updatedRules);
      message.success(`已从属性字段库匹配并填充 ${matchedCount} 条规则`);
    } catch (e) {
      console.error(e);
      message.error('加载配置失败');
    } finally {
      setLoadingMappings(false);
    }
  };

  // 打开默认配置管理弹窗（全局属性字段库）
  const handleOpenDefaultConfig = async () => {
    setDefaultConfigModalVisible(true);
    setLoadingDefaultConfig(true);
    try {
      const res: any = await platformCategoryApi.getDefaultAttributeMapping(selectedPlatform, selectedCountry);
      if (res?.mappingRules?.rules) {
        setDefaultMappingRules(res.mappingRules.rules);
      } else {
        setDefaultMappingRules([]);
      }
    } catch (e) {
      console.error(e);
      setDefaultMappingRules([]);
    } finally {
      setLoadingDefaultConfig(false);
    }
  };

  // 保存默认配置（全局属性字段库）
  const handleSaveDefaultConfig = async () => {
    setSavingDefaultConfig(true);
    try {
      await platformCategoryApi.saveDefaultAttributeMapping(
        selectedPlatform,
        { rules: defaultMappingRules },
        selectedCountry
      );
      message.success('属性字段库保存成功');
      setDefaultConfigModalVisible(false);
    } catch (e: any) {
      message.error(e.message || '保存失败');
    } finally {
      setSavingDefaultConfig(false);
    }
  };

  // 应用默认配置到当前类目（从属性字段库匹配填充）
  const handleApplyDefaultToCurrentCategory = () => {
    if (mappingRules.length === 0) {
      message.warning('当前类目没有属性，请先加载平台属性');
      return;
    }
    if (defaultMappingRules.length === 0) {
      message.warning('属性字段库为空，请先添加规则');
      return;
    }
    
    // 构建 attributeId 到默认规则的映射（不区分大小写）
    const defaultRuleMap = new Map<string, MappingRule>(
      defaultMappingRules.map(r => [r.attributeId.toLowerCase(), r])
    );
    
    // 统计匹配数量
    let matchedCount = 0;
    const updatedRules = mappingRules.map(rule => {
      const defaultRule = defaultRuleMap.get(rule.attributeId.toLowerCase());
      if (defaultRule && defaultRule.value) {
        matchedCount++;
        return {
          ...rule,
          mappingType: defaultRule.mappingType,
          value: defaultRule.value,
        };
      }
      return rule;
    });
    
    if (matchedCount === 0) {
      message.info('没有找到匹配的属性规则');
      return;
    }
    
    setMappingRules(updatedRules);
    message.success(`已从属性字段库匹配并填充 ${matchedCount} 条规则`);
  };

  // 更新默认配置规则
  const handleUpdateDefaultRule = (index: number, field: 'mappingType' | 'value', newValue: any) => {
    setDefaultMappingRules(prev => {
      const newRules = [...prev];
      newRules[index] = { ...newRules[index], [field]: newValue };
      return newRules;
    });
  };

  // 删除默认配置规则
  const handleDeleteDefaultRule = (attributeId: string) => {
    setDefaultMappingRules(prev => prev.filter(r => r.attributeId !== attributeId));
  };

  // 添加默认配置规则
  const handleAddDefaultRule = () => {
    const newRule: MappingRule = {
      attributeId: '',
      attributeName: '',
      mappingType: 'default_value',
      value: '',
      isRequired: false,
      dataType: 'string',
    };
    setDefaultMappingRules(prev => [...prev, newRule]);
  };

  const handleTreeSelect = (_selectedKeys: any[], info: any) => {
    if (info.node?.data) {
      handleSelectCategory(info.node.data);
    }
  };

  // 保存映射配置
  const handleSaveMapping = async () => {
    if (!selectedCategory) return;
    
    setSavingMapping(true);
    try {
      await platformCategoryApi.saveCategoryAttributeMapping(
        selectedPlatform,
        selectedCategory.categoryId,
        { rules: mappingRules },
        selectedCountry,
      );
      message.success('映射配置保存成功');
      // 刷新常用类目列表
      loadFrequentCategories();
    } catch (e: any) {
      message.error(e.message || '保存失败');
    } finally {
      setSavingMapping(false);
    }
  };

  // 删除映射配置
  const handleDeleteMapping = async () => {
    if (!selectedCategory) return;
    
    try {
      await platformCategoryApi.deleteCategoryAttributeMapping(
        selectedPlatform,
        selectedCategory.categoryId,
        selectedCountry,
      );
      message.success('映射配置已删除');
      setMappingRules([]);
      // 刷新常用类目列表
      loadFrequentCategories();
    } catch (e: any) {
      message.error(e.message || '删除失败');
    }
  };

  // 编辑规则
  const handleEditRule = (rule: MappingRule) => {
    setEditingRule(rule);
    form.setFieldsValue({
      mappingType: rule.mappingType,
      value: rule.value,
    });
    setEditModalVisible(true);
  };

  // 保存规则编辑
  const handleSaveRule = () => {
    form.validateFields().then(values => {
      if (editingRule) {
        setMappingRules(prev => prev.map(r => 
          r.attributeId === editingRule.attributeId 
            ? { ...r, mappingType: values.mappingType, value: values.value }
            : r
        ));
      }
      setEditModalVisible(false);
      setEditingRule(null);
      form.resetFields();
    });
  };

  // 快速更新规则
  const handleQuickUpdate = (attributeId: string, field: 'mappingType' | 'value', value: any) => {
    setMappingRules(prev => prev.map(r => 
      r.attributeId === attributeId ? { ...r, [field]: value } : r
    ));
  };

  // 删除规则
  const handleDeleteRule = (attributeId: string) => {
    setMappingRules(prev => prev.filter(r => r.attributeId !== attributeId));
  };

  // 映射规则表格列
  // 检查字段是否因条件而变为必填
  const isConditionallyRequired = (rule: MappingRule): { required: boolean; reason?: string } => {
    if (!rule.conditionalRequired || rule.conditionalRequired.length === 0) {
      return { required: false };
    }
    
    // 检查依赖字段的当前值
    for (const condition of rule.conditionalRequired) {
      const dependsOnRule = mappingRules.find(r => r.attributeId === condition.dependsOn);
      // 调试日志
      if (rule.attributeId === 'warrantyText' || rule.attributeId === 'warrantyURL') {
        console.log('[ConditionalRequired]', {
          field: rule.attributeId,
          dependsOn: condition.dependsOn,
          dependsOnValue: condition.dependsOnValue,
          foundRule: dependsOnRule?.attributeId,
          currentValue: dependsOnRule?.value,
          match: dependsOnRule?.value === condition.dependsOnValue,
        });
      }
      if (dependsOnRule && dependsOnRule.value === condition.dependsOnValue) {
        return { 
          required: true, 
          reason: `当 ${condition.dependsOn} = "${condition.dependsOnValue}" 时必填` 
        };
      }
    }
    return { required: false };
  };

  const mappingColumns = [
    {
      title: '属性名称',
      dataIndex: 'attributeName',
      width: 200,
      render: (text: string, record: MappingRule) => {
        const conditionalStatus = isConditionallyRequired(record);
        return (
          <Space direction="vertical" size={0}>
            <Space>
              <span>{text}</span>
              {record.isRequired && <Tag color="red">必填</Tag>}
              {!record.isRequired && conditionalStatus.required && (
                <Tooltip title={conditionalStatus.reason}>
                  <Tag color="orange">条件必填</Tag>
                </Tooltip>
              )}
            </Space>
            {record.conditionalRequired && record.conditionalRequired.length > 0 && !conditionalStatus.required && (
              <span style={{ fontSize: 11, color: '#999' }}>
                条件: {record.conditionalRequired.map(c => `${c.dependsOn}="${c.dependsOnValue}"`).join(' 或 ')}
              </span>
            )}
          </Space>
        );
      },
    },
    {
      title: '映射类型',
      dataIndex: 'mappingType',
      width: 140,
      render: (type: string, record: MappingRule) => {
        return (
          <Select
            size="small"
            value={type}
            style={{ width: 120 }}
            onChange={v => handleQuickUpdate(record.attributeId, 'mappingType', v)}
            options={MAPPING_TYPE_OPTIONS}
          />
        );
      },
    },
    {
      title: '值/来源',
      dataIndex: 'value',
      render: (value: string, record: MappingRule) => {
        if (record.mappingType === 'channel_data') {
          return (
            <Select
              size="small"
              value={value}
              style={{ width: '100%' }}
              onChange={v => handleQuickUpdate(record.attributeId, 'value', v)}
              options={CHANNEL_DATA_OPTIONS}
              placeholder="选择渠道字段"
              allowClear
              showSearch
            />
          );
        }
        if (record.mappingType === 'enum_select' && record.enumValues?.length) {
          return (
            <Select
              size="small"
              value={value}
              style={{ width: '100%' }}
              onChange={v => handleQuickUpdate(record.attributeId, 'value', v)}
              options={record.enumValues.map(v => ({ value: v, label: v }))}
              placeholder="选择枚举值"
              allowClear
              showSearch
            />
          );
        }
        if (record.mappingType === 'auto_generate') {
          // value 可能是对象 { ruleType, param } 或字符串
          const autoConfig = typeof value === 'object' ? value : { ruleType: value, param: '' };
          const ruleInfo = AUTO_GENERATE_RULES[autoConfig?.ruleType] || { name: '自动生成', description: '' };
          return (
            <Tooltip title={ruleInfo.description}>
              <Tag color="purple" style={{ cursor: 'help' }}>
                {ruleInfo.name}
                {autoConfig?.param && <span style={{ marginLeft: 4, opacity: 0.7 }}>({autoConfig.param})</span>}
              </Tag>
            </Tooltip>
          );
        }
        if (record.mappingType === 'upc_pool') {
          return <Tag color="cyan">从 UPC 池获取</Tag>;
        }
        return (
          <Input
            size="small"
            value={value}
            onChange={e => handleQuickUpdate(record.attributeId, 'value', e.target.value)}
            placeholder="输入默认值"
          />
        );
      },
    },
    {
      title: '数据类型',
      dataIndex: 'dataType',
      width: 80,
      render: (type: string) => <Tag>{type}</Tag>,
    },
    {
      title: '操作',
      width: 80,
      render: (_: any, record: MappingRule) => (
        <Space size="small">
          <Tooltip title="编辑">
            <Button type="link" size="small" icon={<EditOutlined />} onClick={() => handleEditRule(record)} />
          </Tooltip>
          <Popconfirm title="确定删除此规则？" onConfirm={() => handleDeleteRule(record.attributeId)}>
            <Button type="link" size="small" danger icon={<DeleteOutlined />} />
          </Popconfirm>
        </Space>
      ),
    },
  ];

  return (
    <div style={{ display: 'flex', gap: 16, height: 'calc(100vh - 120px)' }}>
      {/* 左侧：类目树 */}
      <Card
        title={<span style={{ whiteSpace: 'nowrap' }}>平台类目</span>}
        style={{ width: 420, flexShrink: 0, display: 'flex', flexDirection: 'column' }}
        styles={{ 
          header: { minHeight: 'auto', padding: '12px 16px' },
          body: { flex: 1, overflow: 'hidden', display: 'flex', flexDirection: 'column' } 
        }}
        extra={
          <Space size="small" wrap={false}>
            <Select
              value={selectedPlatform}
              onChange={setSelectedPlatform}
              style={{ width: 90 }}
              options={platforms.map(p => ({ value: p.id, label: p.name }))}
              size="small"
            />
            <Select
              value={selectedCountry}
              onChange={setSelectedCountry}
              style={{ width: 80 }}
              options={COUNTRY_OPTIONS}
              suffixIcon={<GlobalOutlined />}
              size="small"
            />
            <Button icon={<SyncOutlined />} onClick={handleSync} loading={syncing} size="small">
              同步
            </Button>
            <Popconfirm
              title={`确定清空 ${selectedCountry} 区域的所有类目吗？`}
              description="此操作不可恢复，清空后需要重新同步"
              onConfirm={handleClear}
              okText="确定清空"
              cancelText="取消"
              okButtonProps={{ danger: true }}
            >
              <Button icon={<DeleteOutlined />} loading={clearing} size="small" danger>
                清空
              </Button>
            </Popconfirm>
          </Space>
        }
      >
        {/* 常用类目 */}
        {frequentCategories.length > 0 && (
          <div style={{ marginBottom: 16, padding: 8, background: '#f0f5ff', borderRadius: 4 }}>
            <div style={{ marginBottom: 8, fontSize: 12, color: '#1890ff', fontWeight: 500 }}>
              ⭐ 常用类目（已配置映射）
            </div>
            <div style={{ display: 'flex', flexWrap: 'wrap', gap: 4 }}>
              {frequentCategories.map(cat => (
                <Tag
                  key={cat.id}
                  color="blue"
                  style={{ cursor: 'pointer', marginBottom: 4 }}
                  onClick={() => handleSelectCategory(cat)}
                >
                  {cat.name}
                  <span style={{ marginLeft: 4, fontSize: 10, opacity: 0.7 }}>({cat.rulesCount})</span>
                </Tag>
              ))}
            </div>
          </div>
        )}

        <Space.Compact style={{ width: '100%', marginBottom: 16 }}>
          <Input
            placeholder="搜索类目"
            value={searchKeyword}
            onChange={e => setSearchKeyword(e.target.value)}
            onPressEnter={handleSearch}
          />
          <Button icon={<SearchOutlined />} onClick={handleSearch} loading={searching} />
        </Space.Compact>

        {searchResults.length > 0 && (
          <div style={{ marginBottom: 16, maxHeight: 200, overflow: 'auto', border: '1px solid #f0f0f0', borderRadius: 4, padding: 8 }}>
            <div style={{ marginBottom: 8, color: '#666' }}>搜索结果 ({searchResults.length})</div>
            {searchResults.map(cat => (
              <div
                key={cat.id}
                style={{ padding: '4px 8px', cursor: 'pointer', borderRadius: 4 }}
                className="hover:bg-gray-100"
                onClick={() => handleSelectCategory(cat)}
              >
                <div>{cat.name}</div>
                <div style={{ fontSize: 12, color: '#999' }}>{cat.categoryPath}</div>
              </div>
            ))}
          </div>
        )}

        <div style={{ flex: 1, minHeight: 0, overflow: 'auto' }}>
          <Spin spinning={loading}>
            {treeData.length > 0 ? (
              <Tree
                treeData={treeData}
                onSelect={handleTreeSelect}
                defaultExpandAll={false}
              />
            ) : (
              <Empty description="暂无类目数据，请点击同步按钮" />
            )}
          </Spin>
        </div>
      </Card>

      {/* 右侧：类目详情和属性映射 */}
      <Card 
        title={selectedCategory ? `类目详情 - ${selectedCategory.name}` : '类目详情'}
        style={{ flex: 1, display: 'flex', flexDirection: 'column' }}
        styles={{ body: { flex: 1, overflow: 'auto' } }}
        extra={
          selectedCategory && (
            <Space>
              <Button 
                type="primary" 
                icon={<SaveOutlined />} 
                onClick={handleSaveMapping}
                loading={savingMapping}
              >
                保存映射
              </Button>
              <Popconfirm title="确定删除此类目的映射配置？" onConfirm={handleDeleteMapping}>
                <Button danger icon={<DeleteOutlined />}>删除映射</Button>
              </Popconfirm>
            </Space>
          )
        }
      >
        {selectedCategory ? (
          <div>
            {/* 类目基本信息 */}
            <Descriptions bordered column={2} size="small" style={{ marginBottom: 16 }}>
              <Descriptions.Item label="类目ID">{selectedCategory.categoryId}</Descriptions.Item>
              <Descriptions.Item label="名称">{selectedCategory.name}</Descriptions.Item>
              <Descriptions.Item label="路径" span={2}>{selectedCategory.categoryPath}</Descriptions.Item>
              <Descriptions.Item label="层级">{selectedCategory.level}</Descriptions.Item>
              <Descriptions.Item label="国家">
                <Tag color="blue">{selectedCategory.country || selectedCountry}</Tag>
              </Descriptions.Item>
              <Descriptions.Item label="是否叶子">
                <Tag color={selectedCategory.isLeaf ? 'green' : 'default'}>
                  {selectedCategory.isLeaf ? '是' : '否'}
                </Tag>
              </Descriptions.Item>
            </Descriptions>
            
            {availableCountries.length > 0 && (
              <div style={{ marginBottom: 16, padding: 8, background: '#f5f5f5', borderRadius: 4 }}>
                <span style={{ fontSize: 12, color: '#666' }}>已同步国家：</span>
                {availableCountries.map(c => (
                  <Tag 
                    key={c} 
                    color={c === selectedCountry ? 'blue' : 'default'}
                    style={{ cursor: 'pointer' }}
                    onClick={() => setSelectedCountry(c)}
                  >
                    {COUNTRY_OPTIONS.find(o => o.value === c)?.label || c}
                  </Tag>
                ))}
              </div>
            )}

            {/* 属性映射配置 */}
            <div style={{ marginTop: 16 }}>
              <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 12 }}>
                <h4 style={{ margin: 0 }}>
                  属性映射配置 ({mappingRules.length})
                  <Tooltip title="配置每个属性的映射规则，导入商品时将自动应用这些规则生成平台属性">
                    <span style={{ marginLeft: 8, fontSize: 12, color: '#999' }}>?</span>
                  </Tooltip>
                </h4>
                <Space>
                  <Button 
                    type="primary"
                    size="small" 
                    icon={<DownloadOutlined />} 
                    onClick={handleLoadPlatformAttributes}
                    loading={loadingAttrs}
                  >
                    加载平台属性
                  </Button>
                  <Button
                    size="small"
                    icon={<ImportOutlined />}
                    onClick={handleLoadConfig}
                    loading={loadingMappings}
                  >
                    加载配置
                  </Button>
                  <Button
                    size="small"
                    icon={<SettingOutlined />}
                    onClick={handleOpenDefaultConfig}
                  >
                    属性字段库
                  </Button>
                  {mappingRules.length > 0 && (
                    <Button 
                      size="small" 
                      icon={<ReloadOutlined />} 
                      onClick={handleResetMapping}
                    >
                      重置
                    </Button>
                  )}
                  <Button
                    size="small"
                    onClick={handleDebugApi}
                    disabled={!selectedCategory?.isLeaf}
                  >
                    调试API
                  </Button>
                  <Button
                    size="small"
                    onClick={handleDebugApiRaw}
                    disabled={!selectedCategory?.isLeaf}
                  >
                    原始响应
                  </Button>
                </Space>
              </div>

              {mappingRules.length === 0 && !loadingMapping && (
                <Alert
                  message="暂无属性映射配置"
                  description={
                    <div>
                      <p>点击「加载平台属性」按钮从平台获取该类目的属性列表，系统将自动生成映射规则。</p>
                      <p style={{ marginBottom: 0 }}>支持的映射类型：</p>
                      <ul style={{ marginBottom: 0, paddingLeft: 20 }}>
                        <li><Tag color="blue">默认值</Tag> - 为所有商品设置固定值</li>
                        <li><Tag color="green">渠道数据</Tag> - 从渠道商品数据自动提取</li>
                        <li><Tag color="orange">枚举选择</Tag> - 从平台允许的值中选择</li>
                        <li><Tag color="purple">自动生成</Tag> - 系统智能生成</li>
                        <li><Tag color="cyan">UPC池</Tag> - 从 UPC 池获取未使用的 UPC</li>
                      </ul>
                    </div>
                  }
                  type="info"
                  showIcon
                  style={{ marginBottom: 16 }}
                />
              )}
              
              <Spin spinning={loadingAttrs || loadingMapping}>
                {mappingRules.length > 0 && (
                  <Table
                    dataSource={mappingRules}
                    columns={mappingColumns}
                    rowKey="attributeId"
                    size="small"
                    pagination={false}
                  />
                )}
              </Spin>
            </div>
          </div>
        ) : (
          <Empty description="请选择一个类目查看详情和配置映射" />
        )}
      </Card>

      {/* 默认配置管理弹窗（全局属性字段库） */}
      <Modal
        title="属性字段库（全局默认配置）"
        open={defaultConfigModalVisible}
        onCancel={() => setDefaultConfigModalVisible(false)}
        width={900}
        footer={
          <Space>
            <Button onClick={() => setDefaultConfigModalVisible(false)}>取消</Button>
            <Button onClick={handleApplyDefaultToCurrentCategory} disabled={mappingRules.length === 0}>
              应用到当前类目
            </Button>
            <Button type="primary" onClick={handleSaveDefaultConfig} loading={savingDefaultConfig}>
              保存字段库
            </Button>
          </Space>
        }
      >
        <Alert
          message="属性字段库说明"
          description={
            <div>
              <p style={{ margin: 0 }}>这是一个<strong>全局通用的属性字段库</strong>，预先配置常见属性的映射规则。</p>
              <p style={{ margin: '4px 0 0 0' }}>当加载任意类目的平台属性时，系统会自动根据 attributeId 从此字段库中匹配并填充映射类型和值。</p>
            </div>
          }
          type="info"
          showIcon
          style={{ marginBottom: 16 }}
        />
        <div style={{ marginBottom: 12, display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
          <span>字段规则 ({defaultMappingRules.length})</span>
          <Button size="small" type="dashed" onClick={handleAddDefaultRule}>
            + 添加字段规则
          </Button>
        </div>
        <Spin spinning={loadingDefaultConfig}>
          {defaultMappingRules.length === 0 ? (
            <Empty description="属性字段库为空，点击「添加字段规则」开始配置" />
          ) : (
            <Table
              dataSource={defaultMappingRules}
              rowKey="attributeId"
              size="small"
              pagination={false}
              scroll={{ y: 400 }}
              columns={[
                {
                  title: '属性ID',
                  dataIndex: 'attributeId',
                  width: 180,
                  render: (id: string, record: MappingRule, index: number) => (
                    <Input
                      size="small"
                      value={id}
                      onChange={e => {
                        const newRules = [...defaultMappingRules];
                        newRules[index] = { ...record, attributeId: e.target.value };
                        setDefaultMappingRules(newRules);
                      }}
                      placeholder="属性ID（如 brand）"
                    />
                  ),
                },
                {
                  title: '属性名称',
                  dataIndex: 'attributeName',
                  width: 140,
                  render: (name: string, record: MappingRule, index: number) => (
                    <Input
                      size="small"
                      value={name}
                      onChange={e => {
                        const newRules = [...defaultMappingRules];
                        newRules[index] = { ...record, attributeName: e.target.value };
                        setDefaultMappingRules(newRules);
                      }}
                      placeholder="属性名称"
                    />
                  ),
                },
                {
                  title: '映射类型',
                  dataIndex: 'mappingType',
                  width: 130,
                  render: (type: string, _record: MappingRule, index: number) => (
                    <Select
                      size="small"
                      value={type}
                      style={{ width: '100%' }}
                      onChange={v => handleUpdateDefaultRule(index, 'mappingType', v)}
                      options={MAPPING_TYPE_OPTIONS}
                    />
                  ),
                },
                {
                  title: '值/来源',
                  dataIndex: 'value',
                  render: (value: any, record: MappingRule, index: number) => {
                    if (record.mappingType === 'channel_data') {
                      return (
                        <Select
                          size="small"
                          value={typeof value === 'string' ? value : ''}
                          style={{ width: '100%' }}
                          onChange={v => handleUpdateDefaultRule(index, 'value', v)}
                          options={CHANNEL_DATA_OPTIONS}
                          placeholder="选择渠道字段"
                          allowClear
                          showSearch
                        />
                      );
                    }
                    if (record.mappingType === 'auto_generate') {
                      const config = typeof value === 'object' ? value : { ruleType: value };
                      const ruleInfo = AUTO_GENERATE_RULES[config?.ruleType] || { name: '自动生成', description: '' };
                      return (
                        <Tooltip title={ruleInfo.description}>
                          <Tag color="purple">{ruleInfo.name}</Tag>
                        </Tooltip>
                      );
                    }
                    if (record.mappingType === 'upc_pool') {
                      return <Tag color="cyan">从 UPC 池获取</Tag>;
                    }
                    // default_value 和 enum_select 都用 Input
                    const displayValue = typeof value === 'string' ? value : (typeof value === 'object' ? JSON.stringify(value) : String(value || ''));
                    return (
                      <Input
                        size="small"
                        value={displayValue}
                        onChange={e => handleUpdateDefaultRule(index, 'value', e.target.value)}
                        placeholder={record.mappingType === 'enum_select' ? '输入枚举值' : '输入默认值'}
                      />
                    );
                  },
                },
                {
                  title: '操作',
                  width: 60,
                  render: (_: any, record: MappingRule) => (
                    <Popconfirm title="确定删除？" onConfirm={() => handleDeleteDefaultRule(record.attributeId)}>
                      <Button type="link" size="small" danger icon={<DeleteOutlined />} />
                    </Popconfirm>
                  ),
                },
              ]}
            />
          )}
        </Spin>
      </Modal>

      {/* 编辑规则弹窗 */}
      <Modal
        title={`编辑属性映射 - ${editingRule?.attributeName}`}
        open={editModalVisible}
        onOk={handleSaveRule}
        onCancel={() => { setEditModalVisible(false); setEditingRule(null); form.resetFields(); }}
        width={500}
      >
        <Form form={form} layout="vertical">
          <Form.Item label="映射类型" name="mappingType" rules={[{ required: true }]}>
            <Select options={MAPPING_TYPE_OPTIONS} />
          </Form.Item>
          <Form.Item 
            label="值/来源" 
            name="value"
            extra={
              <div style={{ fontSize: 12, color: '#999', marginTop: 4 }}>
                {editingRule?.mappingType === 'channel_data' && '选择从渠道商品数据中提取的字段'}
                {editingRule?.mappingType === 'default_value' && '输入固定的默认值'}
                {editingRule?.mappingType === 'enum_select' && '从平台允许的枚举值中选择'}
                {editingRule?.mappingType === 'auto_generate' && '系统将根据属性类型自动生成值'}
                {editingRule?.mappingType === 'upc_pool' && '从 UPC 池中自动获取未使用的 UPC 码'}
              </div>
            }
          >
            {editingRule?.mappingType === 'channel_data' ? (
              <Select options={CHANNEL_DATA_OPTIONS} placeholder="选择渠道字段" allowClear showSearch />
            ) : editingRule?.mappingType === 'enum_select' && editingRule?.enumValues?.length ? (
              <Select 
                options={editingRule.enumValues.map(v => ({ value: v, label: v }))} 
                placeholder="选择枚举值" 
                allowClear 
                showSearch 
              />
            ) : editingRule?.mappingType === 'auto_generate' ? (
              <Input disabled placeholder="自动生成" />
            ) : editingRule?.mappingType === 'upc_pool' ? (
              <Input disabled placeholder="从 UPC 池获取" />
            ) : (
              <Input placeholder="输入默认值" />
            )}
          </Form.Item>
          {editingRule?.enumValues && editingRule.enumValues.length > 0 && (
            <div style={{ marginBottom: 16 }}>
              <div style={{ fontSize: 12, color: '#666', marginBottom: 4 }}>可选枚举值：</div>
              <div style={{ maxHeight: 150, overflow: 'auto' }}>
                {editingRule.enumValues.slice(0, 20).map((v, i) => (
                  <Tag key={i} style={{ marginBottom: 4 }}>{v}</Tag>
                ))}
                {editingRule.enumValues.length > 20 && (
                  <span style={{ fontSize: 12, color: '#999' }}>...等 {editingRule.enumValues.length} 个</span>
                )}
              </div>
            </div>
          )}
        </Form>
      </Modal>
    </div>
  );
}
