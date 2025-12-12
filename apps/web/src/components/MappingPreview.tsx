import { useState } from 'react';
import { Button, Card, Table, Tag, Alert, Spin, Space, Tooltip } from 'antd';
import { EyeOutlined, CheckCircleOutlined, WarningOutlined, CloseCircleOutlined } from '@ant-design/icons';
import { attributeMappingApi } from '@/services/api';

interface MappingRule {
  attributeId: string;
  attributeName: string;
  mappingType: string;
  value: any;
  isRequired: boolean;
  dataType: string;
}

interface MappingPreviewProps {
  rules: MappingRule[];
  sampleProduct?: Record<string, any>;
  context?: {
    shopId?: string;
    productSku?: string;
  };
}

interface PreviewResult {
  success: boolean;
  attributes: Record<string, any>;
  errors: Array<{
    attributeId: string;
    attributeName: string;
    errorType: string;
    message: string;
  }>;
  warnings: string[];
}

export default function MappingPreview({ rules, sampleProduct, context }: MappingPreviewProps) {
  const [loading, setLoading] = useState(false);
  const [result, setResult] = useState<PreviewResult | null>(null);

  const handlePreview = async () => {
    if (!sampleProduct || Object.keys(sampleProduct).length === 0) {
      setResult({
        success: false,
        attributes: {},
        errors: [{ attributeId: '', attributeName: '', errorType: 'NoData', message: '请提供示例商品数据' }],
        warnings: [],
      });
      return;
    }

    setLoading(true);
    try {
      const res: any = await attributeMappingApi.preview({
        mappingRules: { rules },
        channelAttributes: sampleProduct,
        context,
      });
      setResult(res);
    } catch (e: any) {
      setResult({
        success: false,
        attributes: {},
        errors: [{ attributeId: '', attributeName: '', errorType: 'ApiError', message: e.message }],
        warnings: [],
      });
    } finally {
      setLoading(false);
    }
  };

  const columns = [
    {
      title: '属性',
      dataIndex: 'attributeName',
      width: 150,
      render: (text: string, record: MappingRule) => (
        <Space>
          <span>{text}</span>
          {record.isRequired && <Tag color="red" style={{ fontSize: 10 }}>必填</Tag>}
        </Space>
      ),
    },
    {
      title: '映射类型',
      dataIndex: 'mappingType',
      width: 100,
      render: (type: string) => {
        const colors: Record<string, string> = {
          default_value: 'blue',
          channel_data: 'green',
          enum_select: 'orange',
          auto_generate: 'purple',
          upc_pool: 'cyan',
        };
        const labels: Record<string, string> = {
          default_value: '默认值',
          channel_data: '渠道数据',
          enum_select: '枚举选择',
          auto_generate: '自动生成',
          upc_pool: 'UPC池',
        };
        return <Tag color={colors[type]}>{labels[type] || type}</Tag>;
      },
    },
    {
      title: '配置值',
      dataIndex: 'value',
      width: 150,
      render: (value: any, record: MappingRule) => {
        if (record.mappingType === 'auto_generate' && typeof value === 'object') {
          return <Tag color="purple">{value.ruleType}{value.param ? `:${value.param}` : ''}</Tag>;
        }
        if (record.mappingType === 'upc_pool') {
          return <Tag color="cyan">从UPC池获取</Tag>;
        }
        return <span style={{ fontSize: 12 }}>{String(value || '-')}</span>;
      },
    },
    {
      title: '解析结果',
      key: 'result',
      render: (_: any, record: MappingRule) => {
        if (!result) return <span style={{ color: '#999' }}>-</span>;

        const resolvedValue = result.attributes[record.attributeId];
        const error = result.errors.find(e => e.attributeId === record.attributeId);

        if (error) {
          return (
            <Tooltip title={error.message}>
              <Tag color="red" icon={<CloseCircleOutlined />}>错误</Tag>
            </Tooltip>
          );
        }

        if (resolvedValue === undefined || resolvedValue === null || resolvedValue === '') {
          if (record.isRequired) {
            return <Tag color="orange" icon={<WarningOutlined />}>空值</Tag>;
          }
          return <span style={{ color: '#999' }}>空</span>;
        }

        const displayValue = typeof resolvedValue === 'object' 
          ? JSON.stringify(resolvedValue).substring(0, 50) 
          : String(resolvedValue).substring(0, 50);

        return (
          <Tooltip title={typeof resolvedValue === 'object' ? JSON.stringify(resolvedValue, null, 2) : resolvedValue}>
            <Tag color="green" icon={<CheckCircleOutlined />} style={{ maxWidth: 200 }}>
              {displayValue}{displayValue.length >= 50 ? '...' : ''}
            </Tag>
          </Tooltip>
        );
      },
    },
  ];

  return (
    <Card
      title="映射预览"
      size="small"
      extra={
        <Button
          type="primary"
          size="small"
          icon={<EyeOutlined />}
          onClick={handlePreview}
          loading={loading}
          disabled={rules.length === 0}
        >
          预览结果
        </Button>
      }
    >
      {result && (
        <div style={{ marginBottom: 12 }}>
          {result.success ? (
            <Alert
              message={`解析成功，共 ${Object.keys(result.attributes).length} 个属性有值`}
              type="success"
              showIcon
            />
          ) : (
            <Alert
              message={`解析失败：${result.errors.map(e => e.message).join(', ')}`}
              type="error"
              showIcon
            />
          )}
          {result.warnings.length > 0 && (
            <Alert
              message={result.warnings.join('; ')}
              type="warning"
              showIcon
              style={{ marginTop: 8 }}
            />
          )}
        </div>
      )}

      <Spin spinning={loading}>
        <Table
          dataSource={rules}
          columns={columns}
          rowKey="attributeId"
          size="small"
          pagination={false}
          scroll={{ y: 300 }}
        />
      </Spin>

      {!sampleProduct && (
        <Alert
          message="提示：请提供示例商品数据以预览映射结果"
          type="info"
          showIcon
          style={{ marginTop: 12 }}
        />
      )}
    </Card>
  );
}
