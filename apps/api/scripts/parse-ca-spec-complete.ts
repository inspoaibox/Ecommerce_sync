/**
 * 完整解析 CA_MP_ITEM_INTL_SPEC.json
 * 提取所有字段的格式要求
 */
import * as fs from 'fs';
import * as path from 'path';

interface FieldInfo {
  name: string;
  type: string;           // string, number, object, array
  format: string;         // plain, multi-lang-object, multi-lang-array, weight-object, enum-array, etc.
  isRequired: boolean;
  layer: 'Orderable' | 'Visible';
  category?: string;      // 仅 Visible 层级
}

function parseFieldDef(fieldName: string, fieldDef: any): { type: string; format: string } {
  if (!fieldDef) return { type: 'unknown', format: 'unknown' };
  
  const fieldType = fieldDef.type;
  
  // 对象类型
  if (fieldType === 'object') {
    // 多语言对象: { en: "...", fr: "..." }
    if (fieldDef.properties?.en) {
      return { type: 'object', format: 'multi-lang-object' };
    }
    // 重量/尺寸对象: { unit: "...", measure: number }
    if (fieldDef.properties?.unit && fieldDef.properties?.measure) {
      return { type: 'object', format: 'measure-object' };
    }
    // productIdentifiers 对象
    if (fieldDef.properties?.productIdType && fieldDef.properties?.productId) {
      return { type: 'object', format: 'product-id-object' };
    }
    return { type: 'object', format: 'plain-object' };
  }
  
  // 数组类型
  if (fieldType === 'array') {
    const items = fieldDef.items;
    if (!items) return { type: 'array', format: 'unknown-array' };
    
    // 数组多语言: [{ en: "...", fr: "..." }]
    if (items.type === 'object' && items.properties?.en) {
      return { type: 'array', format: 'multi-lang-array' };
    }
    // 枚举数组: ["value1", "value2"]
    if (items.type === 'string' && items.enum) {
      return { type: 'array', format: 'enum-array' };
    }
    // 普通字符串数组: ["value1", "value2"]
    if (items.type === 'string') {
      return { type: 'array', format: 'string-array' };
    }
    // 尺寸数组
    if (items.type === 'object' && items.properties?.unit && items.properties?.measure) {
      return { type: 'array', format: 'measure-array' };
    }
    return { type: 'array', format: 'unknown-array' };
  }
  
  // 字符串类型
  if (fieldType === 'string') {
    if (fieldDef.enum) {
      return { type: 'string', format: 'enum' };
    }
    return { type: 'string', format: 'plain' };
  }
  
  // 数字类型
  if (fieldType === 'number' || fieldType === 'integer') {
    return { type: 'number', format: 'plain' };
  }
  
  return { type: fieldType || 'unknown', format: 'unknown' };
}

async function main() {
  const specPath = path.join(process.cwd(), 'src/adapters/platforms/specs/CA_MP_ITEM_INTL_SPEC.json');
  
  if (!fs.existsSync(specPath)) {
    console.log('Spec file not found:', specPath);
    return;
  }
  
  const spec = JSON.parse(fs.readFileSync(specPath, 'utf-8'));
  
  // 解析 Orderable 层级
  const orderableProps = spec?.properties?.MPItem?.items?.properties?.Orderable?.properties || {};
  const orderableRequired = spec?.properties?.MPItem?.items?.properties?.Orderable?.required || [];
  
  console.log('='.repeat(100));
  console.log('CA SPEC 完整字段解析');
  console.log('='.repeat(100));
  
  // Orderable 字段统计
  const orderableFields: FieldInfo[] = [];
  const orderableByFormat: Record<string, string[]> = {};
  
  for (const [fieldName, fieldDef] of Object.entries(orderableProps) as [string, any][]) {
    const { type, format } = parseFieldDef(fieldName, fieldDef);
    const isRequired = orderableRequired.includes(fieldName);
    
    orderableFields.push({
      name: fieldName,
      type,
      format,
      isRequired,
      layer: 'Orderable',
    });
    
    if (!orderableByFormat[format]) {
      orderableByFormat[format] = [];
    }
    orderableByFormat[format].push(fieldName);
  }
  
  console.log('\n' + '='.repeat(50));
  console.log('ORDERABLE 层级字段');
  console.log('='.repeat(50));
  console.log(`总字段数: ${orderableFields.length}`);
  console.log(`必填字段: ${orderableRequired.join(', ')}`);
  
  console.log('\n按格式分类:');
  for (const [format, fields] of Object.entries(orderableByFormat).sort()) {
    console.log(`\n[${format}] (${fields.length} 个):`);
    console.log(`  ${fields.join(', ')}`);
  }
  
  // 解析 Visible 层级（按类目）
  const visibleProps = spec?.properties?.MPItem?.items?.properties?.Visible?.properties || {};
  
  console.log('\n' + '='.repeat(50));
  console.log('VISIBLE 层级字段 (按类目)');
  console.log('='.repeat(50));
  console.log(`类目数量: ${Object.keys(visibleProps).length}`);
  
  // 只输出 Furniture 类目的详细信息
  const furnitureProps = visibleProps['Furniture']?.properties || {};
  const furnitureByFormat: Record<string, string[]> = {};
  
  for (const [fieldName, fieldDef] of Object.entries(furnitureProps) as [string, any][]) {
    const { type, format } = parseFieldDef(fieldName, fieldDef);
    
    if (!furnitureByFormat[format]) {
      furnitureByFormat[format] = [];
    }
    furnitureByFormat[format].push(fieldName);
  }
  
  console.log('\n' + '-'.repeat(50));
  console.log('Furniture 类目字段');
  console.log('-'.repeat(50));
  console.log(`总字段数: ${Object.keys(furnitureProps).length}`);
  
  console.log('\n按格式分类:');
  for (const [format, fields] of Object.entries(furnitureByFormat).sort()) {
    console.log(`\n[${format}] (${fields.length} 个):`);
    console.log(`  ${fields.join(', ')}`);
  }
  
  // 输出 JSON 格式的完整字段映射
  console.log('\n' + '='.repeat(50));
  console.log('JSON 格式输出 (可用于代码)');
  console.log('='.repeat(50));
  
  const output = {
    orderable: {
      multiLangObject: orderableByFormat['multi-lang-object'] || [],
      multiLangArray: orderableByFormat['multi-lang-array'] || [],
      measureObject: orderableByFormat['measure-object'] || [],
      stringArray: orderableByFormat['string-array'] || [],
      enumArray: orderableByFormat['enum-array'] || [],
      plain: [
        ...(orderableByFormat['plain'] || []),
        ...(orderableByFormat['enum'] || []),
      ],
    },
    visible: {
      Furniture: {
        multiLangObject: furnitureByFormat['multi-lang-object'] || [],
        multiLangArray: furnitureByFormat['multi-lang-array'] || [],
        measureObject: furnitureByFormat['measure-object'] || [],
        stringArray: furnitureByFormat['string-array'] || [],
        enumArray: furnitureByFormat['enum-array'] || [],
      },
    },
  };
  
  console.log('\nOrderable 多语言对象字段:');
  console.log(JSON.stringify(output.orderable.multiLangObject, null, 2));
  
  console.log('\nOrderable 多语言数组字段:');
  console.log(JSON.stringify(output.orderable.multiLangArray, null, 2));
  
  console.log('\nOrderable 度量对象字段:');
  console.log(JSON.stringify(output.orderable.measureObject, null, 2));
  
  console.log('\nOrderable 字符串数组字段:');
  console.log(JSON.stringify(output.orderable.stringArray, null, 2));
  
  console.log('\nFurniture 多语言对象字段:');
  console.log(JSON.stringify(output.visible.Furniture.multiLangObject, null, 2));
  
  console.log('\nFurniture 多语言数组字段:');
  console.log(JSON.stringify(output.visible.Furniture.multiLangArray, null, 2));
  
  console.log('\nFurniture 度量对象字段:');
  console.log(JSON.stringify(output.visible.Furniture.measureObject, null, 2));
  
  console.log('\nFurniture 枚举数组字段:');
  console.log(JSON.stringify(output.visible.Furniture.enumArray, null, 2));
}

main().catch(console.error);
