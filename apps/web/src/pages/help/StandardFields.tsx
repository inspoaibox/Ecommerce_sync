import { useState } from 'react';
import { Card, Table, Tabs, Tag, Typography, Collapse, Space, Input } from 'antd';
import {
  InfoCircleOutlined,
  UnorderedListOutlined,
  ApiOutlined,
} from '@ant-design/icons';
import { BASIC_FIELDS, PRODUCT_TYPE_OPTIONS, FIELD_CONFIG_VERSION } from '@/config/standard-fields.config';

const { Title, Text, Paragraph } = Typography;
const { Panel } = Collapse;
const { Search } = Input;

// 从统一配置生成字段列表（用于帮助文档展示）
const standardFields = {
  basicFields: {
    title: '基础信息（系统核心字段）',
    icon: <InfoCircleOutlined />,
    description: `所有商品的核心字段，共 ${BASIC_FIELDS.length} 个（配置版本：${FIELD_CONFIG_VERSION}）`,
    fields: BASIC_FIELDS.map(f => ({
      name: f.key,
      label: f.label,
      type: f.computed ? `${f.type} (计算)` : f.type + (f.unit ? ` (${f.unit})` : ''),
      required: f.required || false,
      description: f.description + (f.computeFormula ? `，计算公式：${f.computeFormula}` : ''),
      options: f.key === 'productType' ? PRODUCT_TYPE_OPTIONS.map(o => `${o.value}（${o.label}）`) : undefined,
    })),
  },
  customAttributes: {
    title: '渠道属性',
    icon: <UnorderedListOutlined />,
    description: '所有其他属性统一放入此处（类似 WooCommerce 自定义属性）',
    fields: [
      { name: 'customAttributes', label: '渠道属性', type: 'CustomAttribute[]', required: false, description: '自定义属性数组，每个属性包含 name（名称）、value（值）、label（显示名）' },
      { name: 'customAttributes[].name', label: '属性名称', type: 'string', required: true, description: '属性的键名，如 brand、mpn、upc 等' },
      { name: 'customAttributes[].value', label: '属性值', type: 'string | number | boolean | string[]', required: true, description: '属性的值' },
      { name: 'customAttributes[].label', label: '显示名称', type: 'string', required: false, description: '属性的显示名称，如"品牌"、"型号"等' },
    ],
  },
};


// 渠道映射示例（新版简化结构）
const channelMappings = [
  {
    channel: 'GigaCloud',
    code: 'gigacloud',
    mappings: [
      // 基础信息（6个）
      { standard: 'title', channel: 'name', note: '字段名不同' },
      { standard: 'sku', channel: 'sku', note: '直接映射' },
      { standard: 'color', channel: 'attributes["Main Color"]', note: '从属性中提取' },
      { standard: 'material', channel: 'attributes["Main Material"]', note: '从属性中提取' },
      { standard: 'description', channel: 'description', note: '直接映射' },
      { standard: 'bulletPoints', channel: 'characteristics', note: '渠道特点转五点描述' },
      // 价格信息（5个存储）
      { standard: 'price', channel: 'price (from price API)', note: '需调用价格接口' },
      { standard: 'salePrice', channel: 'discountedPrice', note: '优惠价格' },
      { standard: 'shippingFee', channel: 'shippingFee (from price API)', note: '需调用价格接口' },
      { standard: 'platformPrice', channel: '(用户设置)', note: '刊登时设置' },
      { standard: 'currency', channel: 'currency (from price API)', note: '需调用价格接口' },
      // 库存（1个）
      { standard: 'stock', channel: 'sellerInventoryInfo.sellerAvailableInventory', note: '需调用库存接口' },
      // 图片（2个）
      { standard: 'mainImageUrl', channel: 'mainImageUrl', note: '直接映射' },
      { standard: 'imageUrls', channel: 'imageUrls', note: '直接映射' },
      // 产品尺寸（4个）
      { standard: 'productLength', channel: 'assembledLength', note: '组装后长度' },
      { standard: 'productWidth', channel: 'assembledWidth', note: '组装后宽度' },
      { standard: 'productHeight', channel: 'assembledHeight', note: '组装后高度' },
      { standard: 'productWeight', channel: 'assembledWeight', note: '组装后重量' },
      // 包装尺寸（5个）
      { standard: 'packageLength', channel: 'length / comboInfo[].length', note: '多包裹时取最大值' },
      { standard: 'packageWidth', channel: 'width / comboInfo[].width', note: '多包裹时取最大值' },
      { standard: 'packageHeight', channel: 'height / comboInfo[].height', note: '多包裹时取最大值' },
      { standard: 'packageWeight', channel: 'weight / comboInfo[].weight', note: '多包裹时计算总重量' },
      { standard: 'packages', channel: 'comboInfo[]', note: '多包裹信息数组' },
      // 其他（4个）
      { standard: 'placeOfOrigin', channel: 'placeOfOrigin', note: '直接映射' },
      { standard: 'productType', channel: '(根据overSizeFlag/comboFlag计算)', note: '系统自动判断' },
      { standard: 'supplier', channel: 'sellerInfo.sellerStore', note: '卖家店铺名' },
      { standard: 'unAvailablePlatform', channel: 'unAvailablePlatform', note: '不可售平台列表' },
      // 渠道属性（放入customAttributes）
      { standard: 'customAttributes[brand]', channel: 'attributes.Brand', note: '品牌' },
      { standard: 'customAttributes[mpn]', channel: 'mpn', note: 'MPN' },
      { standard: 'customAttributes[upc]', channel: 'upc', note: 'UPC' },
      { standard: 'customAttributes[categoryCode]', channel: 'categoryCode', note: '分类代码' },
      { standard: 'customAttributes[categoryName]', channel: 'category', note: '分类名称' },
    ],
  },
  {
    channel: '4Supply',
    code: '4supply',
    mappings: [
      { standard: 'sku', channel: 'sku', note: '待实现' },
      { standard: 'title', channel: 'productName', note: '待实现' },
    ],
  },
  {
    channel: 'Saleyee',
    code: 'saleyee',
    mappings: [
      { standard: 'sku', channel: 'sku', note: '待实现' },
      { standard: 'title', channel: 'title', note: '待实现' },
    ],
  },
];

export default function StandardFields() {
  const [searchText, setSearchText] = useState('');
  const [activeTab, setActiveTab] = useState('fields');

  // 过滤字段
  const filterFields = (fields: any[]) => {
    if (!searchText) return fields;
    const lower = searchText.toLowerCase();
    return fields.filter(
      (f) =>
        f.name.toLowerCase().includes(lower) ||
        f.label.toLowerCase().includes(lower) ||
        f.description.toLowerCase().includes(lower)
    );
  };

  // 字段表格列
  const fieldColumns = [
    {
      title: '字段名',
      dataIndex: 'name',
      key: 'name',
      width: 200,
      render: (text: string) => <Text code>{text}</Text>,
    },
    {
      title: '显示名称',
      dataIndex: 'label',
      key: 'label',
      width: 120,
    },
    {
      title: '类型',
      dataIndex: 'type',
      key: 'type',
      width: 120,
      render: (text: string) => <Tag color="blue">{text}</Tag>,
    },
    {
      title: '必填',
      dataIndex: 'required',
      key: 'required',
      width: 80,
      render: (required: boolean) =>
        required ? <Tag color="red">是</Tag> : <Tag>否</Tag>,
    },
    {
      title: '说明',
      dataIndex: 'description',
      key: 'description',
    },
    {
      title: '可选值',
      dataIndex: 'options',
      key: 'options',
      width: 200,
      render: (options: string[]) =>
        options ? (
          <Space wrap size={[4, 4]}>
            {options.map((opt) => (
              <Tag key={opt} color="green">
                {opt}
              </Tag>
            ))}
          </Space>
        ) : null,
    },
  ];

  // 映射表格列
  const mappingColumns = [
    {
      title: '标准字段',
      dataIndex: 'standard',
      key: 'standard',
      width: 200,
      render: (text: string) => <Text code>{text}</Text>,
    },
    {
      title: '渠道字段',
      dataIndex: 'channel',
      key: 'channel',
      width: 280,
      render: (text: string) => <Text code>{text}</Text>,
    },
    {
      title: '备注',
      dataIndex: 'note',
      key: 'note',
    },
  ];

  return (
    <div>
      <Title level={4}>标准化字段说明</Title>
      <Paragraph type="secondary">
        本系统采用标准化商品数据结构，参考 WooCommerce 产品数据模型设计。
        所有渠道商品数据都会转换为统一的标准格式，便于跨平台刊登和数据管理。
      </Paragraph>

      <Tabs
        activeKey={activeTab}
        onChange={setActiveTab}
        items={[
          {
            key: 'fields',
            label: '标准字段',
            children: (
              <div>
                <Search
                  placeholder="搜索字段名、名称或说明..."
                  allowClear
                  style={{ width: 300, marginBottom: 16 }}
                  onChange={(e) => setSearchText(e.target.value)}
                />
                <Collapse defaultActiveKey={['base', 'identifiers', 'dimensions']}>
                  {Object.entries(standardFields).map(([key, section]) => {
                    const filteredFields = filterFields(section.fields);
                    if (searchText && filteredFields.length === 0) return null;
                    return (
                      <Panel
                        key={key}
                        header={
                          <Space>
                            {section.icon}
                            <span>{section.title}</span>
                            <Tag>{section.fields.length} 个字段</Tag>
                          </Space>
                        }
                        extra={<Text type="secondary">{section.description}</Text>}
                      >
                        <Table
                          columns={fieldColumns}
                          dataSource={filteredFields}
                          rowKey="name"
                          size="small"
                          pagination={false}
                        />
                      </Panel>
                    );
                  })}
                </Collapse>
              </div>
            ),
          },
          {
            key: 'mappings',
            label: '渠道映射',
            children: (
              <div>
                <Paragraph type="secondary" style={{ marginBottom: 16 }}>
                  以下展示各渠道字段到标准字段的映射关系，便于理解数据转换逻辑。
                </Paragraph>
                {channelMappings.map((channel) => (
                  <Card
                    key={channel.code}
                    title={
                      <Space>
                        <ApiOutlined />
                        {channel.channel}
                        <Tag color="blue">{channel.code}</Tag>
                      </Space>
                    }
                    size="small"
                    style={{ marginBottom: 16 }}
                  >
                    <Table
                      columns={mappingColumns}
                      dataSource={channel.mappings}
                      rowKey="standard"
                      size="small"
                      pagination={false}
                    />
                  </Card>
                ))}
              </div>
            ),
          },
          {
            key: 'guide',
            label: '字段使用指引',
            children: (
              <div>
                <Card title="导入商品池字段说明" size="small" style={{ marginBottom: 16 }}>
                  <Paragraph type="secondary">
                    从渠道查询商品后导入商品池时，每个字段的具体用途和数据来源说明。
                  </Paragraph>
                </Card>

                <Card title="基础信息（必填）" size="small" style={{ marginBottom: 16 }}>
                  <Table
                    size="small"
                    pagination={false}
                    columns={[
                      { title: '字段', dataIndex: 'field', width: 140, render: (t: string) => <Text code>{t}</Text> },
                      { title: '用途', dataIndex: 'usage', width: 200 },
                      { title: '数据来源', dataIndex: 'source' },
                    ]}
                    dataSource={[
                      { key: 'title', field: 'title', usage: '商品标题，列表和详情页显示', source: '渠道商品名称（name/title）' },
                      { key: 'sku', field: 'sku', usage: '商品唯一标识，用于去重和关联', source: '渠道SKU，直接映射' },
                      { key: 'color', field: 'color', usage: '商品主色，用于属性映射', source: '渠道属性 Main Color / color' },
                      { key: 'material', field: 'material', usage: '商品材质，用于属性映射', source: '渠道属性 Main Material / material' },
                      { key: 'description', field: 'description', usage: '商品详细描述，支持HTML', source: '渠道描述字段' },
                      { key: 'bulletPoints', field: 'bulletPoints', usage: '五点描述，平台核心卖点', source: '渠道 characteristics 或手动编辑' },
                      { key: 'keywords', field: 'keywords', usage: '搜索关键词，提升搜索排名', source: '手动编辑或AI生成' },
                    ]}
                  />
                </Card>

                <Card title="价格信息" size="small" style={{ marginBottom: 16 }}>
                  <Table
                    size="small"
                    pagination={false}
                    columns={[
                      { title: '字段', dataIndex: 'field', width: 140, render: (t: string) => <Text code>{t}</Text> },
                      { title: '用途', dataIndex: 'usage', width: 200 },
                      { title: '数据来源', dataIndex: 'source' },
                    ]}
                    dataSource={[
                      { key: 'price', field: 'price', usage: '商品原价（不含运费）', source: '渠道价格接口返回' },
                      { key: 'salePrice', field: 'salePrice', usage: '促销价格（不含运费）', source: '渠道 discountedPrice' },
                      { key: 'shippingFee', field: 'shippingFee', usage: '运费，用于计算总价', source: '渠道价格接口返回' },
                      { key: 'platformPrice', field: 'platformPrice', usage: '刊登售价，用户自定义', source: '用户在刊登时设置' },
                      { key: 'currency', field: 'currency', usage: '货币代码', source: '渠道返回，默认 USD' },
                    ]}
                  />
                  <Paragraph type="secondary" style={{ marginTop: 8 }}>
                    <Text strong>计算字段</Text>：totalPrice = price + shippingFee，saleTotalPrice = salePrice + shippingFee（系统自动计算，不存储）
                  </Paragraph>
                </Card>

                <Card title="库存与图片" size="small" style={{ marginBottom: 16 }}>
                  <Table
                    size="small"
                    pagination={false}
                    columns={[
                      { title: '字段', dataIndex: 'field', width: 140, render: (t: string) => <Text code>{t}</Text> },
                      { title: '用途', dataIndex: 'usage', width: 200 },
                      { title: '数据来源', dataIndex: 'source' },
                    ]}
                    dataSource={[
                      { key: 'stock', field: 'stock', usage: '可用库存数量', source: '渠道库存接口返回' },
                      { key: 'mainImageUrl', field: 'mainImageUrl', usage: '商品主图，列表展示', source: '渠道主图URL' },
                      { key: 'imageUrls', field: 'imageUrls', usage: '商品附图数组', source: '渠道图片列表' },
                    ]}
                  />
                </Card>

                <Card title="产品尺寸（组装后/实际尺寸）" size="small" style={{ marginBottom: 16 }}>
                  <Table
                    size="small"
                    pagination={false}
                    columns={[
                      { title: '字段', dataIndex: 'field', width: 140, render: (t: string) => <Text code>{t}</Text> },
                      { title: '用途', dataIndex: 'usage', width: 200 },
                      { title: '数据来源', dataIndex: 'source' },
                    ]}
                    dataSource={[
                      { key: 'productLength', field: 'productLength', usage: '组装后产品长度 (in)', source: '渠道 assembledLength' },
                      { key: 'productWidth', field: 'productWidth', usage: '组装后产品宽度 (in)', source: '渠道 assembledWidth' },
                      { key: 'productHeight', field: 'productHeight', usage: '组装后产品高度 (in)', source: '渠道 assembledHeight' },
                      { key: 'productWeight', field: 'productWeight', usage: '组装后产品重量 (lb)', source: '渠道 assembledWeight' },
                    ]}
                  />
                  <Paragraph type="secondary" style={{ marginTop: 8 }}>
                    产品尺寸指商品组装完成后的实际尺寸，用于平台商品详情展示。
                  </Paragraph>
                </Card>

                <Card title="包装尺寸（运输尺寸）" size="small" style={{ marginBottom: 16 }}>
                  <Table
                    size="small"
                    pagination={false}
                    columns={[
                      { title: '字段', dataIndex: 'field', width: 140, render: (t: string) => <Text code>{t}</Text> },
                      { title: '用途', dataIndex: 'usage', width: 200 },
                      { title: '数据来源', dataIndex: 'source' },
                    ]}
                    dataSource={[
                      { key: 'packageLength', field: 'packageLength', usage: '包裹长度 (in)', source: '渠道 length，多包裹取最大值' },
                      { key: 'packageWidth', field: 'packageWidth', usage: '包裹宽度 (in)', source: '渠道 width，多包裹取最大值' },
                      { key: 'packageHeight', field: 'packageHeight', usage: '包裹高度 (in)', source: '渠道 height，多包裹取最大值' },
                      { key: 'packageWeight', field: 'packageWeight', usage: '包裹重量 (lb)', source: '渠道 weight，多包裹计算总重' },
                      { key: 'packages', field: 'packages', usage: '多包裹详细信息', source: '渠道 comboInfo 数组' },
                    ]}
                  />
                  <Paragraph type="secondary" style={{ marginTop: 8 }}>
                    包装尺寸用于运费计算和物流配送。多包裹商品会自动延伸为 package1Length、package2Length 等。
                  </Paragraph>
                </Card>

                <Card title="其他核心字段" size="small" style={{ marginBottom: 16 }}>
                  <Table
                    size="small"
                    pagination={false}
                    columns={[
                      { title: '字段', dataIndex: 'field', width: 140, render: (t: string) => <Text code>{t}</Text> },
                      { title: '用途', dataIndex: 'usage', width: 200 },
                      { title: '数据来源', dataIndex: 'source' },
                    ]}
                    dataSource={[
                      { key: 'placeOfOrigin', field: 'placeOfOrigin', usage: '商品原产地', source: '渠道 placeOfOrigin' },
                      { key: 'productType', field: 'productType', usage: '商品物流性质分类', source: '根据尺寸/重量/包裹数自动判断' },
                      { key: 'supplier', field: 'supplier', usage: '供货商/卖家名称', source: '渠道 sellerInfo.sellerStore' },
                      { key: 'unAvailablePlatform', field: 'unAvailablePlatform', usage: '禁止销售的平台列表', source: '渠道 unAvailablePlatform' },
                    ]}
                  />
                  <Paragraph type="secondary" style={{ marginTop: 8 }}>
                    <Text strong>productType 自动判断规则</Text>：多包裹→multiPackage，超大件标记或重量&gt;150lb或最大边&gt;108in→oversized，重量&lt;2lb且最大边&lt;18in→small，其他→normal
                  </Paragraph>
                  <Paragraph type="secondary" style={{ marginTop: 8 }}>
                    <Text strong>unAvailablePlatform 格式</Text>：数组格式，每个元素包含 id 和 name，如 [&#123;"id": "1", "name": "Wayfair"&#125;]
                  </Paragraph>
                </Card>

                <Card title="渠道属性（customAttributes）" size="small" style={{ marginBottom: 16 }}>
                  <Paragraph>
                    所有非核心字段统一放入 customAttributes 数组，每个属性包含：
                  </Paragraph>
                  <ul>
                    <li><Text code>name</Text>：属性键名（如 brand、mpn、upc）</li>
                    <li><Text code>value</Text>：属性值</li>
                    <li><Text code>label</Text>：显示名称（如"品牌"）</li>
                  </ul>
                  <Paragraph style={{ marginTop: 8 }}>常见渠道属性：</Paragraph>
                  <Table
                    size="small"
                    pagination={false}
                    columns={[
                      { title: '属性名', dataIndex: 'name', width: 120, render: (t: string) => <Text code>{t}</Text> },
                      { title: '显示名', dataIndex: 'label', width: 100 },
                      { title: '用途', dataIndex: 'usage' },
                    ]}
                    dataSource={[
                      { key: 'brand', name: 'brand', label: '品牌', usage: '商品品牌，用于平台属性映射' },
                      { key: 'mpn', name: 'mpn', label: 'MPN', usage: '制造商零件号' },
                      { key: 'upc', name: 'upc', label: 'UPC', usage: '通用产品代码' },
                      { key: 'categoryCode', name: 'categoryCode', label: '分类代码', usage: '渠道分类代码' },
                      { key: 'categoryName', name: 'categoryName', label: '分类名称', usage: '渠道分类名称' },
                      { key: 'style', name: 'style', label: '风格', usage: '商品风格' },
                      { key: 'colorFamily', name: 'colorFamily', label: '颜色系列', usage: '颜色分类' },
                      { key: 'characteristics', name: 'characteristics', label: '商品特点', usage: '渠道原始特点描述' },
                    ]}
                  />
                </Card>

                <Card title="单位说明" size="small">
                  <Paragraph>
                    所有尺寸和重量字段使用固定单位，无需额外存储单位字段：
                  </Paragraph>
                  <ul>
                    <li><Text strong>长度单位</Text>：in（英寸）</li>
                    <li><Text strong>重量单位</Text>：lb（磅）</li>
                  </ul>
                  <Paragraph type="secondary">
                    渠道数据导入时会自动转换为标准单位。
                  </Paragraph>
                </Card>
              </div>
            ),
          },
          {
            key: 'usage',
            label: '开发说明',
            children: (
              <div>
                <Card title="数据流程" size="small" style={{ marginBottom: 16 }}>
                  <Paragraph>
                    <ol>
                      <li>
                        <Text strong>渠道数据获取</Text>：从各渠道API获取原始商品数据
                      </li>
                      <li>
                        <Text strong>标准化转换</Text>：通过渠道适配器将数据转换为标准格式
                      </li>
                      <li>
                        <Text strong>存储到商品池</Text>：标准化数据存入商品池，供多店铺复用
                      </li>
                      <li>
                        <Text strong>平台属性映射</Text>：根据目标平台类目配置属性映射规则
                      </li>
                      <li>
                        <Text strong>商品刊登</Text>：将标准数据转换为平台格式并提交
                      </li>
                    </ol>
                  </Paragraph>
                </Card>

                <Card title="多包裹商品处理" size="small" style={{ marginBottom: 16 }}>
                  <Paragraph>
                    对于多包裹商品（如大型家具），系统会自动处理：
                  </Paragraph>
                  <ul>
                    <li>
                      <Text strong>总重量</Text>：所有包裹重量 × 数量 之和
                    </li>
                    <li>
                      <Text strong>最大尺寸</Text>：取所有包裹中最大的长/宽/高（用于运费计算）
                    </li>
                    <li>
                      <Text strong>组装后尺寸</Text>：保留产品实际尺寸信息
                    </li>
                  </ul>
                </Card>

                <Card title="五点描述（Bullet Points）" size="small" style={{ marginBottom: 16 }}>
                  <Paragraph>
                    五点描述是 Amazon/Walmart 等平台的核心卖点展示区域，对转化率影响很大：
                  </Paragraph>
                  <ul>
                    <li>
                      <Text strong>数量</Text>：通常5条，部分平台支持更多
                    </li>
                    <li>
                      <Text strong>长度</Text>：每条建议150-200字符，不宜过长
                    </li>
                    <li>
                      <Text strong>内容</Text>：突出产品特点、优势、使用场景
                    </li>
                    <li>
                      <Text strong>格式</Text>：首字母大写，避免全大写
                    </li>
                    <li>
                      <Text strong>关键词</Text>：自然融入搜索关键词
                    </li>
                  </ul>
                  <Paragraph type="secondary">
                    系统可从渠道的 characteristics 字段自动生成，也支持手动编辑或AI优化。
                  </Paragraph>
                </Card>

                <Card title="品牌字段提取规则" size="small" style={{ marginBottom: 16 }}>
                  <Paragraph>
                    品牌字段仅从以下位置提取，不使用商家名称：
                  </Paragraph>
                  <ul>
                    <li>
                      <Text code>attributes.Brand</Text>
                    </li>
                    <li>
                      <Text code>attributes.brand</Text>
                    </li>
                    <li>
                      <Text code>brand</Text> 字段
                    </li>
                  </ul>
                  <Paragraph type="secondary">
                    如果以上字段都不存在，品牌将为空（null），不会使用商家店铺名称替代。
                  </Paragraph>
                </Card>

                <Card title="添加新渠道适配器" size="small">
                  <Paragraph>
                    添加新渠道时，需要实现以下步骤：
                  </Paragraph>
                  <ol>
                    <li>
                      创建适配器文件：<Text code>apps/api/src/adapters/channels/[channel].adapter.ts</Text>
                    </li>
                    <li>
                      继承 <Text code>BaseChannelAdapter</Text> 基类
                    </li>
                    <li>
                      实现 <Text code>fetchProductDetails</Text> 方法，返回 <Text code>ChannelProductDetail[]</Text>
                    </li>
                    <li>
                      在 <Text code>buildStandardAttributes</Text> 中将渠道字段映射到标准字段
                    </li>
                    <li>
                      使用 <Text code>standard-product.utils.ts</Text> 中的工具函数处理数据转换
                    </li>
                  </ol>
                </Card>
              </div>
            ),
          },
        ]}
      />
    </div>
  );
}
