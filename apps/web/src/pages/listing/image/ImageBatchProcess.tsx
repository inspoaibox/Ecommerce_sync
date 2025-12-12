import { useState, useEffect } from 'react';
import { Card, Button, Form, Input, Select, Space, message, Typography, Alert, Divider } from 'antd';
import { PlayCircleOutlined } from '@ant-design/icons';
import { useNavigate } from 'react-router-dom';
import { imageApi, platformCategoryApi, platformApi } from '@/services/api';

const { Title } = Typography;
const { TextArea } = Input;

export default function ImageBatchProcess() {
  const navigate = useNavigate();
  const [form] = Form.useForm();
  const [configs, setConfigs] = useState<any[]>([]);
  const [platforms, setPlatforms] = useState<any[]>([]);
  const [categories, setCategories] = useState<any[]>([]);
  const [loading, setLoading] = useState(false);
  const [scope, setScope] = useState<'all' | 'category' | 'sku_list'>('all');
  const [selectedPlatform, setSelectedPlatform] = useState<string>('');

  useEffect(() => {
    loadConfigs();
    loadPlatforms();
  }, []);

  const loadConfigs = async () => {
    try {
      const data: any = await imageApi.listConfigs();
      setConfigs(data || []);
      // 设置默认配置
      const defaultConfig = data?.find((c: any) => c.isDefault);
      if (defaultConfig) {
        form.setFieldsValue({ configId: defaultConfig.id });
      }
    } catch (e: any) {
      message.error(e.message);
    }
  };

  const loadPlatforms = async () => {
    try {
      const res: any = await platformApi.list({ pageSize: 100 });
      setPlatforms(res.data || []);
    } catch (e: any) {
      console.error(e);
    }
  };

  const loadCategories = async (platformId: string) => {
    try {
      const res: any = await platformCategoryApi.getCategoryTree(platformId, 'US');
      // 扁平化类目树
      const flatCategories: any[] = [];
      const flatten = (items: any[], path = '') => {
        items.forEach(item => {
          const currentPath = path ? `${path} > ${item.name}` : item.name;
          if (item.isLeaf) {
            flatCategories.push({
              value: item.categoryId,
              label: currentPath,
            });
          }
          if (item.children) {
            flatten(item.children, currentPath);
          }
        });
      };
      flatten(res || []);
      setCategories(flatCategories);
    } catch (e: any) {
      console.error(e);
    }
  };

  const handlePlatformChange = (platformId: string) => {
    setSelectedPlatform(platformId);
    form.setFieldsValue({ categoryId: undefined });
    if (platformId) {
      loadCategories(platformId);
    } else {
      setCategories([]);
    }
  };

  const handleSubmit = async () => {
    try {
      const values = await form.validateFields();
      
      let scopeValue: string | undefined;
      if (scope === 'category') {
        scopeValue = values.categoryId;
      } else if (scope === 'sku_list') {
        const skus = values.skuList.split(/[\n,;]/).map((s: string) => s.trim()).filter(Boolean);
        if (skus.length === 0) {
          message.warning('请输入SKU');
          return;
        }
        scopeValue = JSON.stringify(skus);
      }

      setLoading(true);
      const task: any = await imageApi.createTask({
        name: values.name,
        scope,
        scopeValue,
        configId: values.configId,
      });

      message.success(`任务创建成功，共 ${task.totalCount} 个商品`);
      
      // 询问是否立即开始
      if (task.totalCount > 0) {
        const startNow = window.confirm('是否立即开始处理？');
        if (startNow) {
          await imageApi.startTask(task.id);
          message.success('任务已开始');
        }
      }

      navigate('/listing/image/logs');
    } catch (e: any) {
      if (e.message) message.error(e.message);
    } finally {
      setLoading(false);
    }
  };

  return (
    <div>
      <Title level={4}>批量处理图片</Title>
      <Card>
        <Alert
          message="批量处理说明"
          description={
            <ul style={{ margin: 0, paddingLeft: 20 }}>
              <li>根据配置的参数，自动处理商品池中的图片</li>
              <li>超过大小限制的图片会被压缩</li>
              <li>非1:1比例的图片会被居中裁剪</li>
              <li>处理后的图片会替换原图片URL</li>
            </ul>
          }
          type="info"
          showIcon
          style={{ marginBottom: 24 }}
        />

        <Form form={form} layout="vertical" style={{ maxWidth: 600 }}>
          <Form.Item name="name" label="任务名称" rules={[{ required: true, message: '请输入任务名称' }]}>
            <Input placeholder="如：Walmart图片处理-2024.01" />
          </Form.Item>

          <Form.Item name="configId" label="处理配置" rules={[{ required: true, message: '请选择配置' }]}>
            <Select
              placeholder="选择处理配置"
              options={configs.map(c => ({
                value: c.id,
                label: `${c.name} (${c.maxSizeMB}MB, ${c.forceSquare ? '1:1' : '原比例'}, ${c.targetWidth}px)`,
              }))}
            />
          </Form.Item>

          <Divider>处理范围</Divider>

          <Form.Item label="选择范围">
            <Select
              value={scope}
              onChange={setScope}
              options={[
                { value: 'all', label: '所有商品池商品' },
                { value: 'category', label: '按平台类目' },
                { value: 'sku_list', label: '指定SKU列表' },
              ]}
            />
          </Form.Item>

          {scope === 'category' && (
            <>
              <Form.Item label="选择平台">
                <Select
                  placeholder="选择平台"
                  value={selectedPlatform}
                  onChange={handlePlatformChange}
                  options={platforms.map(p => ({ value: p.id, label: p.name }))}
                />
              </Form.Item>
              <Form.Item name="categoryId" label="选择类目" rules={[{ required: true, message: '请选择类目' }]}>
                <Select
                  placeholder="选择类目"
                  showSearch
                  optionFilterProp="label"
                  options={categories}
                  disabled={!selectedPlatform}
                />
              </Form.Item>
            </>
          )}

          {scope === 'sku_list' && (
            <Form.Item name="skuList" label="SKU列表" rules={[{ required: true, message: '请输入SKU' }]}>
              <TextArea rows={6} placeholder="输入SKU，每行一个或用逗号分隔" />
            </Form.Item>
          )}

          <Form.Item>
            <Space>
              <Button type="primary" icon={<PlayCircleOutlined />} onClick={handleSubmit} loading={loading}>
                创建任务
              </Button>
              <Button onClick={() => navigate('/listing/image/logs')}>
                查看任务列表
              </Button>
            </Space>
          </Form.Item>
        </Form>
      </Card>
    </div>
  );
}
