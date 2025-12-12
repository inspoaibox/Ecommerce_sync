import { useState, useEffect } from 'react';
import { Select, Input, Space, Spin } from 'antd';
import { attributeMappingApi } from '@/services/api';

interface AutoGenerateRule {
  ruleType: string;
  name: string;
  description: string;
  hasParam: boolean;
  paramLabel?: string;
  example: string;
}

interface AutoGenerateConfig {
  ruleType: string;
  param?: string;
}

interface AutoGenerateRuleSelectorProps {
  value?: AutoGenerateConfig;
  onChange?: (value: AutoGenerateConfig) => void;
  style?: React.CSSProperties;
  size?: 'small' | 'middle' | 'large';
}

export default function AutoGenerateRuleSelector({
  value,
  onChange,
  style,
  size = 'small',
}: AutoGenerateRuleSelectorProps) {
  const [rules, setRules] = useState<AutoGenerateRule[]>([]);
  const [loading, setLoading] = useState(false);

  useEffect(() => {
    loadRules();
  }, []);

  const loadRules = async () => {
    setLoading(true);
    try {
      const res: any = await attributeMappingApi.getAutoGenerateRules();
      setRules(res || []);
    } catch (e) {
      console.error('Failed to load auto generate rules:', e);
    } finally {
      setLoading(false);
    }
  };

  const selectedRule = rules.find(r => r.ruleType === value?.ruleType);

  const handleRuleTypeChange = (ruleType: string) => {
    onChange?.({ ruleType, param: '' });
  };

  const handleParamChange = (param: string) => {
    onChange?.({ ruleType: value?.ruleType || '', param });
  };

  return (
    <Space direction="vertical" style={{ width: '100%', ...style }}>
      <Select
        value={value?.ruleType}
        onChange={handleRuleTypeChange}
        placeholder="选择生成规则"
        size={size}
        style={{ width: '100%' }}
        loading={loading}
        notFoundContent={loading ? <Spin size="small" /> : '暂无数据'}
        options={rules.map(r => ({
          value: r.ruleType,
          label: (
            <div>
              <div>{r.name}</div>
              <div style={{ fontSize: 11, color: '#999' }}>{r.description}</div>
            </div>
          ),
        }))}
        optionLabelProp="label"
      />
      {selectedRule?.hasParam && (
        <Input
          value={value?.param}
          onChange={e => handleParamChange(e.target.value)}
          placeholder={selectedRule.paramLabel || '输入参数'}
          size={size}
          addonBefore={selectedRule.paramLabel}
        />
      )}
      {selectedRule && (
        <div style={{ fontSize: 11, color: '#999' }}>
          示例输出: {selectedRule.example}
        </div>
      )}
    </Space>
  );
}
