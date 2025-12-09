import { useState, useEffect } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import { Card, Input, Button, Space, message, Tabs, Descriptions, Typography, Alert } from 'antd';
import { ArrowLeftOutlined, SendOutlined, ClearOutlined } from '@ant-design/icons';
import { channelApi } from '@/services/api';

const { TextArea } = Input;
const { Text, Title } = Typography;

// 大健云仓 API 端点配置
const GIGACLOUD_ENDPOINTS = [
  {
    key: 'price',
    label: '价格查询',
    method: 'POST',
    path: '/api-b2b-v1/product/price',
    description: '查询SKU价格信息，包含运费、MAP价格等',
    inputType: 'skus',
  },
  {
    key: 'inventory',
    label: '库存查询',
    method: 'POST',
    path: '/api-b2b-v1/inventory/quantity-query',
    description: '查询SKU库存信息',
    inputType: 'skus',
  },
  {
    key: 'detail',
    label: '商品详情',
    method: 'POST',
    path: '/api-b2b-v1/product/detailInfo',
    description: '查询SKU详细信息，包含标题、图片等',
    inputType: 'skus',
  },
  {
    key: 'skuList',
    label: 'SKU列表',
    method: 'GET',
    path: '/api-b2b-v1/product/skus',
    description: '获取商品SKU列表（分页）',
    inputType: 'pagination',
  },
];

// 赛盈云仓 API 端点配置
const SALEYEE_ENDPOINTS = [
  {
    key: 'warehouse',
    label: '区域列表',
    method: 'POST',
    path: '/api/Product/GetWarehouse',
    description: '获取所有可用的仓库区域列表',
    inputType: 'none',
  },
  {
    key: 'logistics',
    label: '物流产品',
    method: 'POST',
    path: '/api/Product/GetLogisticsProductStandard',
    description: '获取物流产品标准列表',
    inputType: 'none',
  },
  {
    key: 'productDetail',
    label: '商品详情',
    method: 'POST',
    path: '/api/Product/QueryProductDetail',
    description: '查询商品详细信息（支持分页）',
    inputType: 'pagination',
  },
  {
    key: 'price',
    label: '价格查询',
    method: 'POST',
    path: '/api/Product/QueryProductPriceV2',
    description: '查询SKU价格信息（V2.0，最多30个SKU）',
    inputType: 'skus',
  },
  {
    key: 'inventory',
    label: '库存查询',
    method: 'POST',
    path: '/api/Product/QueryProductInventoryV2',
    description: '查询SKU库存信息（V2.0，最多30个SKU）',
    inputType: 'skus',
  },
];

// 根据渠道类型获取端点配置
const getEndpointsByChannelType = (channelType: string) => {
  switch (channelType) {
    case 'gigacloud':
      return GIGACLOUD_ENDPOINTS;
    case 'saleyee':
      return SALEYEE_ENDPOINTS;
    default:
      return [];
  }
};

export default function ChannelApiTest() {
  const { id } = useParams<{ id: string }>();
  const navigate = useNavigate();
  const [channel, setChannel] = useState<any>(null);
  const [selectedEndpoint, setSelectedEndpoint] = useState('');
  const [skuInput, setSkuInput] = useState('');
  const [page, setPage] = useState(1);
  const [pageSize, setPageSize] = useState(20);
  const [loading, setLoading] = useState(false);
  const [response, setResponse] = useState<any>(null);
  const [error, setError] = useState<string | null>(null);
  const [requestTime, setRequestTime] = useState<number | null>(null);

  useEffect(() => {
    if (id) {
      loadChannel();
    }
  }, [id]);

  const loadChannel = async () => {
    try {
      const res: any = await channelApi.get(id!);
      setChannel(res);
      // 加载渠道后，设置默认选中的端点
      const endpoints = getEndpointsByChannelType(res.type);
      if (endpoints.length > 0) {
        setSelectedEndpoint(endpoints[0].key);
      }
    } catch (e: any) {
      message.error(e.message || '加载渠道信息失败');
    }
  };

  // 根据渠道类型获取端点列表
  const API_ENDPOINTS = channel ? getEndpointsByChannelType(channel.type) : [];
  const currentEndpoint = API_ENDPOINTS.find(e => e.key === selectedEndpoint);

  const handleTest = async () => {
    if (!currentEndpoint) {
      message.warning('请选择接口');
      return;
    }

    setLoading(true);
    setError(null);
    setResponse(null);
    const startTime = Date.now();

    try {
      let skus: string[] = [];
      if (currentEndpoint.inputType === 'skus') {
        skus = skuInput
          .split(/[\n,;，；\s]+/)
          .map(s => s.trim())
          .filter(s => s.length > 0);

        if (skus.length === 0) {
          message.warning('请输入SKU');
          setLoading(false);
          return;
        }
      }

      // 调用后端测试接口
      const res: any = await channelApi.testApi(id!, {
        endpoint: selectedEndpoint,
        skus: currentEndpoint.inputType === 'skus' ? skus : undefined,
        page: currentEndpoint.inputType === 'pagination' ? page : undefined,
        pageSize: currentEndpoint.inputType === 'pagination' ? pageSize : undefined,
      });

      setRequestTime(Date.now() - startTime);
      setResponse(res);
    } catch (e: any) {
      setRequestTime(Date.now() - startTime);
      setError(e.message || '请求失败');
    } finally {
      setLoading(false);
    }
  };

  const handleClear = () => {
    setResponse(null);
    setError(null);
    setRequestTime(null);
  };

  return (
    <div>
      <Space style={{ marginBottom: 16 }}>
        <Button icon={<ArrowLeftOutlined />} onClick={() => navigate('/channels')}>
          返回渠道列表
        </Button>
        <Title level={4} style={{ margin: 0 }}>
          API 测试 - {channel?.name || '加载中...'}
        </Title>
      </Space>

      {channel && (
        <Card size="small" style={{ marginBottom: 16 }}>
          <Descriptions size="small" column={4}>
            <Descriptions.Item label="渠道名称">{channel.name}</Descriptions.Item>
            <Descriptions.Item label="渠道类型">{channel.type}</Descriptions.Item>
            <Descriptions.Item label="状态">{channel.status}</Descriptions.Item>
            <Descriptions.Item label="创建时间">
              {new Date(channel.createdAt).toLocaleString()}
            </Descriptions.Item>
          </Descriptions>
        </Card>
      )}

      <Card title="选择接口" style={{ marginBottom: 16 }}>
        <Tabs
          activeKey={selectedEndpoint}
          onChange={setSelectedEndpoint}
          items={API_ENDPOINTS.map(ep => ({
            key: ep.key,
            label: ep.label,
            children: (
              <div>
                <Descriptions size="small" column={1} style={{ marginBottom: 16 }}>
                  <Descriptions.Item label="请求方式">
                    <Text code>{ep.method}</Text>
                  </Descriptions.Item>
                  <Descriptions.Item label="接口路径">
                    <Text code>{ep.path}</Text>
                  </Descriptions.Item>
                  <Descriptions.Item label="说明">{ep.description}</Descriptions.Item>
                </Descriptions>

                {ep.inputType === 'skus' && (
                  <div>
                    <div style={{ marginBottom: 8 }}>
                      输入 SKU（每行一个或用逗号分隔，{channel.type === 'saleyee' ? '最多30个' : '最多200个'}）：
                    </div>
                    <TextArea
                      rows={4}
                      value={skuInput}
                      onChange={e => setSkuInput(e.target.value)}
                      placeholder={channel.type === 'saleyee' ? 'SKU1\nSKU2\nSKU3' : 'WF191769AAK\nWF308150AAE'}
                    />
                  </div>
                )}

                {ep.inputType === 'pagination' && (
                  <Space>
                    <span>页码：</span>
                    <Input
                      type="number"
                      value={page}
                      onChange={e => setPage(Number(e.target.value))}
                      style={{ width: 80 }}
                    />
                    <span>每页数量：</span>
                    <Input
                      type="number"
                      value={pageSize}
                      onChange={e => setPageSize(Number(e.target.value))}
                      style={{ width: 80 }}
                    />
                  </Space>
                )}

                {ep.inputType === 'none' && (
                  <Alert
                    type="info"
                    message="此接口无需输入参数，直接点击发送请求即可"
                    showIcon
                  />
                )}
              </div>
            ),
          }))}
        />

        <Space style={{ marginTop: 16 }}>
          <Button
            type="primary"
            icon={<SendOutlined />}
            loading={loading}
            onClick={handleTest}
          >
            发送请求
          </Button>
          <Button icon={<ClearOutlined />} onClick={handleClear}>
            清空结果
          </Button>
        </Space>
      </Card>

      <Card
        title={
          <Space>
            <span>响应结果</span>
            {requestTime !== null && (
              <Text type="secondary">耗时: {requestTime}ms</Text>
            )}
          </Space>
        }
      >
        {error && (
          <Alert type="error" message="请求失败" description={error} style={{ marginBottom: 16 }} />
        )}

        {response && (
          <div>
            {response.success === false && (
              <Alert
                type="warning"
                message="API 返回失败"
                description={response.message}
                style={{ marginBottom: 16 }}
              />
            )}
            
            <div style={{ marginBottom: 8 }}>
              <Text strong>返回数据条数：</Text>
              <Text>{Array.isArray(response.data) ? response.data.length : '-'}</Text>
            </div>

            <div style={{ background: '#1e1e1e', padding: 16, borderRadius: 8, maxHeight: 500, overflow: 'auto' }}>
              <pre style={{ margin: 0, color: '#d4d4d4', fontSize: 13, whiteSpace: 'pre-wrap', wordBreak: 'break-all' }}>
                {JSON.stringify(response, null, 2)}
              </pre>
            </div>
          </div>
        )}

        {!response && !error && (
          <Text type="secondary">点击"发送请求"查看结果</Text>
        )}
      </Card>
    </div>
  );
}
