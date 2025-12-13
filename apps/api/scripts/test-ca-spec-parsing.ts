/**
 * 测试 CA spec 文件解析
 * 运行: pnpm exec ts-node -r tsconfig-paths/register scripts/test-ca-spec-parsing.ts
 */

import * as path from 'path';
import * as fs from 'fs';

interface CASpecInfo {
  multiLangFields: Set<string>;
  arrayMultiLangFields: Set<string>;
  weightFields: Set<string>;
  arrayFields: Set<string>;
}

const parseCASpecFields = (): CASpecInfo => {
  const multiLangFields = new Set<string>();
  const arrayMultiLangFields = new Set<string>();
  const weightFields = new Set<string>();
  const arrayFields = new Set<string>();

  const possiblePaths = [
    path.join(__dirname, '../src/adapters/platforms/specs/CA_MP_ITEM_INTL_SPEC.json'),
    path.join(process.cwd(), 'src/adapters/platforms/specs/CA_MP_ITEM_INTL_SPEC.json'),
    path.join(process.cwd(), '../API-doc/CA_MP_ITEM_INTL_SPEC.json'),
  ];

  let spec: any = null;
  for (const specPath of possiblePaths) {
    try {
      if (fs.existsSync(specPath)) {
        spec = JSON.parse(fs.readFileSync(specPath, 'utf-8'));
        console.log(`CA spec loaded from: ${specPath}`);
        break;
      }
    } catch (e) {
      // 继续尝试下一个路径
    }
  }

  if (!spec) {
    console.error('CA spec file not found!');
    return { multiLangFields, arrayMultiLangFields, weightFields, arrayFields };
  }

  // 解析 Orderable 层级的字段定义
  const orderableProps = spec?.properties?.MPItem?.items?.properties?.Orderable?.properties || {};
  
  console.log(`\n=== Orderable 字段总数: ${Object.keys(orderableProps).length} ===\n`);

  for (const [fieldName, fieldDef] of Object.entries(orderableProps) as [string, any][]) {
    // 检查是否为多语言对象字段
    if (fieldDef.type === 'object' && fieldDef.properties?.en) {
      multiLangFields.add(fieldName);
    }
    // 检查是否为数组多语言字段
    else if (fieldDef.type === 'array' && fieldDef.items?.type === 'object' && fieldDef.items?.properties?.en) {
      arrayMultiLangFields.add(fieldName);
    }
    // 检查是否为重量字段
    else if (fieldDef.type === 'object' && fieldDef.properties?.unit && fieldDef.properties?.measure) {
      weightFields.add(fieldName);
    }
    // 检查是否为普通数组字段
    else if (fieldDef.type === 'array' && fieldDef.items?.type === 'string') {
      arrayFields.add(fieldName);
    }
  }

  return { multiLangFields, arrayMultiLangFields, weightFields, arrayFields };
};

async function main() {
  console.log('=== 测试 CA Spec 文件解析 ===\n');

  const specInfo = parseCASpecFields();

  console.log('\n=== 多语言对象字段 (需要 { en: "..." } 格式) ===');
  console.log(Array.from(specInfo.multiLangFields).sort().join('\n'));

  console.log('\n=== 数组多语言字段 (数组中每个元素需要 { en: "..." } 格式) ===');
  console.log(Array.from(specInfo.arrayMultiLangFields).sort().join('\n'));

  console.log('\n=== 重量字段 (需要 { unit: "lb", measure: number } 格式) ===');
  console.log(Array.from(specInfo.weightFields).sort().join('\n'));

  console.log('\n=== 普通数组字段 (string -> [string]) ===');
  console.log(Array.from(specInfo.arrayFields).sort().join('\n'));

  console.log('\n=== 统计 ===');
  console.log(`多语言对象字段: ${specInfo.multiLangFields.size} 个`);
  console.log(`数组多语言字段: ${specInfo.arrayMultiLangFields.size} 个`);
  console.log(`重量字段: ${specInfo.weightFields.size} 个`);
  console.log(`普通数组字段: ${specInfo.arrayFields.size} 个`);
}

main().catch(console.error);
