/**
 * 验证 CA spec 解析是否正确
 * 检查 Furniture 类目下各字段的类型定义
 */
import * as fs from 'fs';
import * as path from 'path';

const specPath = path.join(process.cwd(), 'src/adapters/platforms/specs/CA_MP_ITEM_INTL_SPEC.json');
const spec = JSON.parse(fs.readFileSync(specPath, 'utf-8'));

// 获取 Visible.Furniture 的字段定义
const furnitureProps = spec?.properties?.MPItem?.items?.properties?.Visible?.properties?.Furniture?.properties || {};

console.log('=== Furniture 类目字段类型分析 ===\n');

// 分类字段
const multiLangObject: string[] = [];  // { en: "..." }
const arrayMultiLang: string[] = [];   // [{ en: "..." }]
const simpleArray: string[] = [];      // ["..."]
const simpleString: string[] = [];     // "..."
const simpleNumber: string[] = [];     // number
const otherTypes: string[] = [];

// 出错的字段
const errorFields = ['material', 'color', 'recommendedLocations', 'fabricCareInstructions', 
                     'homeDecorStyle', 'theme', 'ageGroup', 'mountType'];

for (const [fieldName, fieldDef] of Object.entries(furnitureProps) as [string, any][]) {
  const type = fieldDef.type;
  
  if (type === 'object') {
    if (fieldDef.properties?.en) {
      multiLangObject.push(fieldName);
    } else if (fieldDef.properties?.unit && fieldDef.properties?.measure) {
      otherTypes.push(`${fieldName} (measurement)`);
    } else {
      otherTypes.push(`${fieldName} (object)`);
    }
  } else if (type === 'array') {
    const itemType = fieldDef.items?.type;
    if (itemType === 'object' && fieldDef.items?.properties?.en) {
      arrayMultiLang.push(fieldName);
    } else if (itemType === 'string') {
      simpleArray.push(fieldName);
    } else {
      otherTypes.push(`${fieldName} (array of ${itemType})`);
    }
  } else if (type === 'string') {
    simpleString.push(fieldName);
  } else if (type === 'integer' || type === 'number') {
    simpleNumber.push(fieldName);
  } else {
    otherTypes.push(`${fieldName} (${type})`);
  }
}

console.log('多语言对象字段 { en: "..." }:');
console.log(`  共 ${multiLangObject.length} 个: ${multiLangObject.join(', ')}\n`);

console.log('数组多语言字段 [{ en: "..." }]:');
console.log(`  共 ${arrayMultiLang.length} 个: ${arrayMultiLang.join(', ')}\n`);

console.log('简单数组字段 ["..."]:');
console.log(`  共 ${simpleArray.length} 个: ${simpleArray.join(', ')}\n`);

console.log('简单字符串字段:');
console.log(`  共 ${simpleString.length} 个: ${simpleString.join(', ')}\n`);

console.log('数字字段:');
console.log(`  共 ${simpleNumber.length} 个: ${simpleNumber.join(', ')}\n`);

console.log('其他类型:');
console.log(`  共 ${otherTypes.length} 个: ${otherTypes.join(', ')}\n`);

// 检查出错的字段
console.log('=== 检查出错字段的实际类型 ===\n');
for (const field of errorFields) {
  const def = furnitureProps[field];
  if (def) {
    console.log(`${field}:`);
    console.log(`  type: ${def.type}`);
    if (def.type === 'array') {
      console.log(`  items.type: ${def.items?.type}`);
      if (def.items?.properties) {
        console.log(`  items.properties: ${Object.keys(def.items.properties).join(', ')}`);
      }
    } else if (def.type === 'object') {
      console.log(`  properties: ${Object.keys(def.properties || {}).join(', ')}`);
    }
    
    // 判断应该的格式
    let expectedFormat = '';
    if (def.type === 'object' && def.properties?.en) {
      expectedFormat = '{ en: "..." }';
    } else if (def.type === 'array' && def.items?.type === 'object' && def.items?.properties?.en) {
      expectedFormat = '[{ en: "..." }]';
    } else if (def.type === 'array' && def.items?.type === 'string') {
      expectedFormat = '["..."]';
    }
    console.log(`  期望格式: ${expectedFormat || '其他'}`);
    console.log();
  } else {
    console.log(`${field}: 未找到定义\n`);
  }
}
