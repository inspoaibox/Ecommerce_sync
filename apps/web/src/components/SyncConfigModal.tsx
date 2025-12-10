import { useState, useEffect } from 'react';
import { Modal, Switch, InputNumber, Button, Space, message, Radio, Popconfirm, Typography } from 'antd';
import { PlusOutlined, DeleteOutlined } from '@ant-design/icons';
import { shopApi } from '@/services/api';

const { Text } = Typography;

interface PriceTier {
  minPrice: number;
  maxPrice: number | null; // null 表示无上限
  multiplier: number;
  adjustment: number;
}

interface SyncConfig {
  price: {
    enabled: boolean;
    source: 'channel' | 'local';
    tiers: PriceTier[];
    defaultMultiplier: number;
    defaultAdjustment: number;
  };
  inventory: {
    enabled: boolean;
    multiplier: number;
    adjustment: number;
    minStock: number;
    maxStock: number | null;
  };
}

const DEFAULT_CONFIG: SyncConfig = {
  price: {
    enabled: true,
    source: 'channel',
    tiers: [],
    defaultMultiplier: 1.0,
    defaultAdjustment: 0,
  },
  inventory: {
    enabled: true,
    multiplier: 1.0,
    adjustment: 0,
    minStock: 0,
    maxStock: null,
  },
};

interface Props {
  open: boolean;
  shopId: string;
  shopName: string;
  onClose: () => void;
}

// 不再需要这个函数

export default function SyncConfigModal({ open, shopId, shopName, onClose }: Props) {
  const [loading, setLoading] = useState(false);
  const [saving, setSaving] = useState(false);
  const [config, setConfig] = useState<SyncConfig>(DEFAULT_CONFIG);

  useEffect(() => {
    if (open && shopId) {
      loadConfig();
    }
  }, [open, shopId]);

  const loadConfig = async () => {
    setLoading(true);
    try {
      const res: any = await shopApi.getSyncConfig(shopId);
      if (res) {
        // 兼容旧数据：确保所有字段都存在
        const merged: SyncConfig = {
          price: {
            enabled: res.price?.enabled ?? DEFAULT_CONFIG.price.enabled,
            source: res.price?.source ?? DEFAULT_CONFIG.price.source,
            tiers: (res.price?.tiers || []).map((t: any) => ({
              minPrice: t.minPrice ?? 0,
              maxPrice: t.maxPrice ?? null,
              multiplier: t.multiplier ?? 1,
              adjustment: t.adjustment ?? 0,
            })),
            defaultMultiplier: res.price?.defaultMultiplier ?? DEFAULT_CONFIG.price.defaultMultiplier,
            defaultAdjustment: res.price?.defaultAdjustment ?? DEFAULT_CONFIG.price.defaultAdjustment,
          },
          inventory: {
            enabled: res.inventory?.enabled ?? DEFAULT_CONFIG.inventory.enabled,
            multiplier: res.inventory?.multiplier ?? DEFAULT_CONFIG.inventory.multiplier,
            adjustment: res.inventory?.adjustment ?? DEFAULT_CONFIG.inventory.adjustment,
            minStock: res.inventory?.minStock ?? DEFAULT_CONFIG.inventory.minStock,
            maxStock: res.inventory?.maxStock ?? DEFAULT_CONFIG.inventory.maxStock,
          },
        };
        setConfig(merged);
      } else {
        setConfig(DEFAULT_CONFIG);
      }
    } catch (e) {
      console.error(e);
      setConfig(DEFAULT_CONFIG);
    } finally {
      setLoading(false);
    }
  };

  const handleSave = async () => {
    setSaving(true);
    try {
      await shopApi.updateSyncConfig(shopId, config);
      message.success('保存成功');
      onClose();
    } catch (e: any) {
      message.error(e.message || '保存失败');
    } finally {
      setSaving(false);
    }
  };

  const addTier = () => {
    const tiers = [...config.price.tiers];
    const lastTier = tiers[tiers.length - 1];
    const newMinPrice = lastTier && lastTier.maxPrice ? lastTier.maxPrice : 0;
    tiers.push({ minPrice: newMinPrice, maxPrice: null, multiplier: 1.0, adjustment: 0 });
    setConfig({ ...config, price: { ...config.price, tiers } });
  };

  const removeTier = (index: number) => {
    const tiers = config.price.tiers.filter((_, i) => i !== index);
    setConfig({ ...config, price: { ...config.price, tiers } });
  };

  const updateTier = (index: number, field: keyof PriceTier, value: number) => {
    const tiers = [...config.price.tiers];
    tiers[index] = { ...tiers[index], [field]: value };
    // 不在这里排序，保存时再排序
    setConfig({ ...config, price: { ...config.price, tiers } });
  };


  return (
    <Modal
      title={`同步规则配置 - ${shopName}`}
      open={open}
      onCancel={onClose}
      onOk={handleSave}
      confirmLoading={saving}
      width={650}
      okText="保存"
      cancelText="取消"
    >
      {loading ? (
        <div style={{ textAlign: 'center', padding: 40 }}>加载中...</div>
      ) : (
        <div>
          {/* 价格配置 */}
          <div style={{ marginBottom: 24 }}>
            <div style={{ display: 'flex', alignItems: 'center', marginBottom: 12 }}>
              <Switch
                checked={config.price.enabled}
                onChange={(checked) => setConfig({ ...config, price: { ...config.price, enabled: checked } })}
              />
              <span style={{ marginLeft: 8, fontWeight: 'bold' }}>启用价格倍率</span>
            </div>

            {config.price.enabled && (
              <div style={{ paddingLeft: 24 }}>
                <div style={{ marginBottom: 12 }}>
                  <span style={{ marginRight: 8 }}>价格来源：</span>
                  <Radio.Group
                    value={config.price.source}
                    onChange={(e) => setConfig({ ...config, price: { ...config.price, source: e.target.value } })}
                  >
                    <Radio value="channel">渠道价格</Radio>
                    <Radio value="local">本地价格</Radio>
                  </Radio.Group>
                </div>

                <div style={{ marginBottom: 8, display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
                  <span>价格区间规则：</span>
                  <Button type="dashed" size="small" icon={<PlusOutlined />} onClick={addTier}>
                    添加区间
                  </Button>
                </div>

                {config.price.tiers.length > 0 ? (
                  <div style={{ border: '1px solid #f0f0f0', borderRadius: 4, marginBottom: 12 }}>
                    {/* 表头 */}
                    <div style={{ display: 'flex', background: '#fafafa', padding: '8px 12px', borderBottom: '1px solid #f0f0f0', fontWeight: 500 }}>
                      <div style={{ width: 100 }}>起始价格</div>
                      <div style={{ width: 100 }}>结束价格</div>
                      <div style={{ width: 80 }}>倍率</div>
                      <div style={{ width: 80 }}>增减</div>
                      <div style={{ width: 50 }}></div>
                    </div>
                    {/* 数据行 */}
                    {config.price.tiers.map((tier, index) => (
                      <div key={index} style={{ display: 'flex', alignItems: 'center', padding: '8px 12px', borderBottom: index < config.price.tiers.length - 1 ? '1px solid #f0f0f0' : 'none' }}>
                        <div style={{ width: 100 }}>
                          <InputNumber
                            value={tier.minPrice}
                            min={0}
                            precision={0}
                            onChange={(val) => updateTier(index, 'minPrice', val ?? 0)}
                            style={{ width: 80 }}
                            size="small"
                          />
                        </div>
                        <div style={{ width: 100 }}>
                          <InputNumber
                            value={tier.maxPrice ?? undefined}
                            min={0}
                            precision={0}
                            placeholder="不限"
                            onChange={(val) => updateTier(index, 'maxPrice', val as any)}
                            style={{ width: 80 }}
                            size="small"
                          />
                        </div>
                        <div style={{ width: 80 }}>
                          <InputNumber
                            value={tier.multiplier}
                            min={0}
                            step={0.1}
                            precision={2}
                            onChange={(val) => updateTier(index, 'multiplier', val ?? 1)}
                            style={{ width: 70 }}
                            size="small"
                          />
                        </div>
                        <div style={{ width: 80 }}>
                          <InputNumber
                            value={tier.adjustment}
                            precision={2}
                            onChange={(val) => updateTier(index, 'adjustment', val ?? 0)}
                            style={{ width: 70 }}
                            size="small"
                          />
                        </div>
                        <div style={{ width: 50 }}>
                          <Popconfirm title="确定删除?" onConfirm={() => removeTier(index)}>
                            <Button type="link" danger size="small" icon={<DeleteOutlined />} />
                          </Popconfirm>
                        </div>
                      </div>
                    ))}
                  </div>
                ) : (
                  <div style={{ color: '#999', marginBottom: 12, fontSize: 12, padding: '12px', background: '#fafafa', borderRadius: 4 }}>
                    未设置区间规则，将使用下方默认倍率计算所有价格
                  </div>
                )}

                <div style={{ background: '#f6ffed', padding: '8px 12px', borderRadius: 4, marginBottom: 8 }}>
                  <Text type="secondary" style={{ fontSize: 12 }}>
                    💡 匹配规则：价格 ≥ 起始价格 且 &lt; 结束价格。结束价格留空表示无上限。
                  </Text>
                </div>

                <Space>
                  <span>默认倍率：</span>
                  <InputNumber
                    value={config.price.defaultMultiplier}
                    min={0}
                    step={0.1}
                    precision={2}
                    onChange={(val) => setConfig({ ...config, price: { ...config.price, defaultMultiplier: val || 1 } })}
                    style={{ width: 80 }}
                  />
                  <span style={{ marginLeft: 16 }}>默认增减：</span>
                  <InputNumber
                    value={config.price.defaultAdjustment}
                    precision={2}
                    onChange={(val) => setConfig({ ...config, price: { ...config.price, defaultAdjustment: val || 0 } })}
                    style={{ width: 80 }}
                  />
                </Space>
              </div>
            )}
          </div>

          {/* 库存配置 */}
          <div>
            <div style={{ display: 'flex', alignItems: 'center', marginBottom: 12 }}>
              <Switch
                checked={config.inventory.enabled}
                onChange={(checked) => setConfig({ ...config, inventory: { ...config.inventory, enabled: checked } })}
              />
              <span style={{ marginLeft: 8, fontWeight: 'bold' }}>启用库存倍率</span>
            </div>

            {config.inventory.enabled && (
              <div style={{ paddingLeft: 24 }}>
                <Space wrap>
                  <span>库存倍率：</span>
                  <InputNumber
                    value={config.inventory.multiplier}
                    min={0}
                    step={0.1}
                    precision={2}
                    onChange={(val) => setConfig({ ...config, inventory: { ...config.inventory, multiplier: val || 1 } })}
                    style={{ width: 80 }}
                  />
                  <span style={{ marginLeft: 16 }}>库存增减：</span>
                  <InputNumber
                    value={config.inventory.adjustment}
                    onChange={(val) => setConfig({ ...config, inventory: { ...config.inventory, adjustment: val || 0 } })}
                    style={{ width: 80 }}
                  />
                </Space>
                <div style={{ marginTop: 12 }}>
                  <Space wrap>
                    <span>最小库存（低于此值设为0）：</span>
                    <InputNumber
                      value={config.inventory.minStock}
                      min={0}
                      onChange={(val) => setConfig({ ...config, inventory: { ...config.inventory, minStock: val || 0 } })}
                      style={{ width: 80 }}
                    />
                    <span style={{ marginLeft: 16 }}>最大库存限制：</span>
                    <InputNumber
                      value={config.inventory.maxStock ?? undefined}
                      min={0}
                      placeholder="不限"
                      onChange={(val) => setConfig({ ...config, inventory: { ...config.inventory, maxStock: val || null } })}
                      style={{ width: 80 }}
                    />
                  </Space>
                </div>
              </div>
            )}
          </div>
        </div>
      )}
    </Modal>
  );
}
