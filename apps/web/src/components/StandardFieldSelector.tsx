import { useState, useEffect } from 'react';
import { Select, Spin } from 'antd';
import { attributeMappingApi } from '@/services/api';

interface StandardField {
  key: string;
  path: string;
  category: string;
}

interface StandardFieldSelectorProps {
  value?: string;
  onChange?: (value: string) => void;
  placeholder?: string;
  style?: React.CSSProperties;
  size?: 'small' | 'middle' | 'large';
  allowClear?: boolean;
}

export default function StandardFieldSelector({
  value,
  onChange,
  placeholder = '选择标准字段',
  style,
  size = 'small',
  allowClear = true,
}: StandardFieldSelectorProps) {
  const [fields, setFields] = useState<StandardField[]>([]);
  const [loading, setLoading] = useState(false);

  useEffect(() => {
    loadFields();
  }, []);

  const loadFields = async () => {
    setLoading(true);
    try {
      const res: any = await attributeMappingApi.getStandardFields();
      setFields(res || []);
    } catch (e) {
      console.error('Failed to load standard fields:', e);
    } finally {
      setLoading(false);
    }
  };

  // 按分类分组
  const groupedOptions = fields.reduce((acc, field) => {
    if (!acc[field.category]) {
      acc[field.category] = [];
    }
    acc[field.category].push({
      value: field.path,
      label: `${field.key} (${field.path})`,
    });
    return acc;
  }, {} as Record<string, { value: string; label: string }[]>);

  const options = Object.entries(groupedOptions).map(([category, items]) => ({
    label: category,
    options: items,
  }));

  return (
    <Select
      value={value}
      onChange={onChange}
      placeholder={placeholder}
      style={style}
      size={size}
      allowClear={allowClear}
      showSearch
      optionFilterProp="label"
      loading={loading}
      notFoundContent={loading ? <Spin size="small" /> : '暂无数据'}
      options={options}
    />
  );
}
