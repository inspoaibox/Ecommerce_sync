/**
 * 测试工业用途和尺寸重量提取规则
 * - is_industrial_extract: 是否工业用途
 * - assembled_product_length_extract: 组装后长度
 * - assembled_product_width_extract: 组装后宽度
 * - assembled_product_height_extract: 组装后高度
 * - assembled_product_weight_extract: 组装后重量
 */

// 简化的 getNestedValue 实现
function getNestedValue(obj: Record<string, any>, path: string): any {
  if (!obj || !path) return undefined;
  const keys = path.split('.');
  let result = obj;
  for (const key of keys) {
    if (result === undefined || result === null) return undefined;
    result = result[key];
  }
  return result;
}

function stripHtmlTags(html: string): string {
  if (!html) return '';
  return html.replace(/<[^>]+>/g, ' ').replace(/&nbsp;/g, ' ').trim();
}

// ==================== 提取方法实现 ====================

function extractIsIndustrial(channelAttributes: Record<string, any>): string {
  const title = getNestedValue(channelAttributes, 'title') || '';
  const description = getNestedValue(channelAttributes, 'description') || '';
  const bulletPoints = getNestedValue(channelAttributes, 'bulletPoints') || [];
  const cleanDesc = stripHtmlTags(description);
  const bullets = Array.isArray(bulletPoints) ? bulletPoints.join(' ') : '';
  const text = `${title} ${cleanDesc} ${bullets}`.toLowerCase();

  const styleOnlyPatterns = [
    'industrial style', 'industrial-style', 'industrial look', 'industrial design',
    'industrial chic', 'industrial aesthetic', 'loft style', 'factory style',
  ];
  const negativeKeywords = ['not industrial', 'residential use only', 'home use only'];
  if (negativeKeywords.some(kw => text.includes(kw))) return 'No';

  const industrialUseKeywords = [
    'industrial use', 'industrial grade', 'commercial use', 'commercial grade',
    'professional grade', 'workshop use', 'garage use', 'factory use',
    'warehouse use', 'heavy-duty', 'heavy duty',
  ];

  const hasStyleOnly = styleOnlyPatterns.some(kw => text.includes(kw));
  const hasIndustrialUse = industrialUseKeywords.some(kw => text.includes(kw));

  if (hasIndustrialUse) return 'Yes';
  if (hasStyleOnly) return 'No';
  return 'No';
}

function extractAssembledProductLength(channelAttributes: Record<string, any>): { unit: string; measure: number } {
  const productLength = getNestedValue(channelAttributes, 'productLength');
  if (productLength !== undefined && productLength !== null) {
    if (typeof productLength === 'object' && productLength.measure !== undefined) {
      return { unit: productLength.unit || 'in', measure: Number(productLength.measure) };
    }
    if (typeof productLength === 'number' && productLength > 0) {
      return { unit: 'in', measure: productLength };
    }
  }

  const title = getNestedValue(channelAttributes, 'title') || '';
  const description = getNestedValue(channelAttributes, 'description') || '';
  const text = `${title} ${stripHtmlTags(description)}`.toLowerCase();

  const lengthPatterns = [
    /(?:assembled|overall|finished|total)\s*length[:\s]*(\d+(?:\.\d+)?)\s*(in|inch|inches|cm|mm)?/i,
    /length[:\s]*(\d+(?:\.\d+)?)\s*(in|inch|inches|cm|mm)?/i,
  ];

  for (const pattern of lengthPatterns) {
    const match = text.match(pattern);
    if (match && match[1]) {
      let measure = parseFloat(match[1]);
      const unit = (match[2] || 'in').toLowerCase();
      if (unit === 'cm') measure = Math.round((measure / 2.54) * 10) / 10;
      if (measure > 0 && measure < 1000) return { unit: 'in', measure };
    }
  }
  return { unit: 'in', measure: 1 };
}

function extractAssembledProductWidth(channelAttributes: Record<string, any>): { unit: string; measure: number } {
  const productWidth = getNestedValue(channelAttributes, 'productWidth');
  if (productWidth !== undefined && productWidth !== null) {
    if (typeof productWidth === 'object' && productWidth.measure !== undefined) {
      return { unit: productWidth.unit || 'in', measure: Number(productWidth.measure) };
    }
    if (typeof productWidth === 'number' && productWidth > 0) {
      return { unit: 'in', measure: productWidth };
    }
  }

  const title = getNestedValue(channelAttributes, 'title') || '';
  const description = getNestedValue(channelAttributes, 'description') || '';
  const text = `${title} ${stripHtmlTags(description)}`.toLowerCase();

  const widthPatterns = [
    /(?:assembled|overall|finished|total)\s*width[:\s]*(\d+(?:\.\d+)?)\s*(in|inch|inches|cm|mm)?/i,
    /width[:\s]*(\d+(?:\.\d+)?)\s*(in|inch|inches|cm|mm)?/i,
  ];

  for (const pattern of widthPatterns) {
    const match = text.match(pattern);
    if (match && match[1]) {
      let measure = parseFloat(match[1]);
      const unit = (match[2] || 'in').toLowerCase();
      if (unit === 'cm') measure = Math.round((measure / 2.54) * 10) / 10;
      if (measure > 0 && measure < 1000) return { unit: 'in', measure };
    }
  }
  return { unit: 'in', measure: 1 };
}

function extractAssembledProductHeight(channelAttributes: Record<string, any>): { unit: string; measure: number } {
  const productHeight = getNestedValue(channelAttributes, 'productHeight');
  if (productHeight !== undefined && productHeight !== null) {
    if (typeof productHeight === 'object' && productHeight.measure !== undefined) {
      return { unit: productHeight.unit || 'in', measure: Number(productHeight.measure) };
    }
    if (typeof productHeight === 'number' && productHeight > 0) {
      return { unit: 'in', measure: productHeight };
    }
  }

  const title = getNestedValue(channelAttributes, 'title') || '';
  const description = getNestedValue(channelAttributes, 'description') || '';
  const text = `${title} ${stripHtmlTags(description)}`.toLowerCase();

  const heightPatterns = [
    /(?:assembled|overall|finished|total)\s*height[:\s]*(\d+(?:\.\d+)?)\s*(in|inch|inches|cm|mm)?/i,
    /height[:\s]*(\d+(?:\.\d+)?)\s*(in|inch|inches|cm|mm)?/i,
  ];

  for (const pattern of heightPatterns) {
    const match = text.match(pattern);
    if (match && match[1]) {
      let measure = parseFloat(match[1]);
      const unit = (match[2] || 'in').toLowerCase();
      if (unit === 'cm') measure = Math.round((measure / 2.54) * 10) / 10;
      if (measure > 0 && measure < 1000) return { unit: 'in', measure };
    }
  }
  return { unit: 'in', measure: 1 };
}

function extractAssembledProductWeight(channelAttributes: Record<string, any>): { unit: string; measure: number } {
  const productWeight = getNestedValue(channelAttributes, 'productWeight');
  if (productWeight !== undefined && productWeight !== null) {
    if (typeof productWeight === 'object' && productWeight.measure !== undefined) {
      let measure = Number(productWeight.measure);
      const unit = (productWeight.unit || 'lb').toLowerCase();
      if (unit === 'kg') measure = Math.round(measure * 2.20462 * 10) / 10;
      return { unit: 'lb', measure };
    }
    if (typeof productWeight === 'number' && productWeight > 0) {
      return { unit: 'lb', measure: productWeight };
    }
  }

  const title = getNestedValue(channelAttributes, 'title') || '';
  const description = getNestedValue(channelAttributes, 'description') || '';
  const text = `${title} ${stripHtmlTags(description)}`.toLowerCase();

  const weightPatterns = [
    /(?:assembled|overall|net|item|product)\s*weight[:\s]*(\d+(?:\.\d+)?)\s*(lb|lbs|pound|pounds|kg)?/i,
    /weight[:\s]*(\d+(?:\.\d+)?)\s*(lb|lbs|pound|pounds|kg)?/i,
  ];

  for (const pattern of weightPatterns) {
    const match = text.match(pattern);
    if (match && match[1]) {
      let measure = parseFloat(match[1]);
      const unit = (match[2] || 'lb').toLowerCase();
      if (unit === 'kg') measure = Math.round(measure * 2.20462 * 10) / 10;
      if (measure > 0 && measure < 10000) return { unit: 'lb', measure };
    }
  }
  return { unit: 'lb', measure: 1 };
}

// ==================== 测试用例 ====================

interface TestCase {
  name: string;
  product: Record<string, any>;
  expected: {
    isIndustrial: string;
    length: { unit: string; measure: number };
    width: { unit: string; measure: number };
    height: { unit: string; measure: number };
    weight: { unit: string; measure: number };
  };
}

const testCases: TestCase[] = [
  {
    name: '测试1: 工业级重型工作台（有渠道尺寸数据）',
    product: {
      title: 'Heavy-Duty Industrial Grade Workbench',
      description: 'Commercial use workbench for workshop and garage. Professional grade construction.',
      productLength: { measure: 72, unit: 'in' },
      productWidth: { measure: 30, unit: 'in' },
      productHeight: { measure: 36, unit: 'in' },
      productWeight: { measure: 150, unit: 'lb' },
    },
    expected: {
      isIndustrial: 'Yes',
      length: { unit: 'in', measure: 72 },
      width: { unit: 'in', measure: 30 },
      height: { unit: 'in', measure: 36 },
      weight: { unit: 'lb', measure: 150 },
    },
  },
  {
    name: '测试2: 工业风格家用桌（仅风格，非工业用途）',
    product: {
      title: 'Industrial Style Coffee Table',
      description: 'Beautiful industrial-style table with loft aesthetic. Perfect for living room.',
      productLength: 48,
      productWidth: 24,
      productHeight: 18,
      productWeight: 35,
    },
    expected: {
      isIndustrial: 'No',
      length: { unit: 'in', measure: 48 },
      width: { unit: 'in', measure: 24 },
      height: { unit: 'in', measure: 18 },
      weight: { unit: 'lb', measure: 35 },
    },
  },
  {
    name: '测试3: 普通沙发（从文本提取尺寸）',
    product: {
      title: 'Modern 3-Seater Sofa',
      description: 'Comfortable sofa. Overall length: 84 inches, width: 36 inches, height: 32 inches. Item weight: 120 lbs.',
    },
    expected: {
      isIndustrial: 'No',
      length: { unit: 'in', measure: 84 },
      width: { unit: 'in', measure: 36 },
      height: { unit: 'in', measure: 32 },
      weight: { unit: 'lb', measure: 120 },
    },
  },
  {
    name: '测试4: 厘米单位转换',
    product: {
      title: 'Dining Table',
      description: 'Assembled length: 150 cm, width: 90 cm, height: 75 cm. Weight: 45 kg.',
    },
    expected: {
      isIndustrial: 'No',
      length: { unit: 'in', measure: 59.1 },
      width: { unit: 'in', measure: 35.4 },
      height: { unit: 'in', measure: 29.5 },
      weight: { unit: 'lb', measure: 99.2 },
    },
  },
  {
    name: '测试5: 无尺寸信息（兜底默认值）',
    product: {
      title: 'Simple Chair',
      description: 'A basic chair for home use.',
    },
    expected: {
      isIndustrial: 'No',
      length: { unit: 'in', measure: 1 },
      width: { unit: 'in', measure: 1 },
      height: { unit: 'in', measure: 1 },
      weight: { unit: 'lb', measure: 1 },
    },
  },
  {
    name: '测试6: 仓库货架（工业用途）',
    product: {
      title: 'Warehouse Storage Shelf Unit',
      description: 'Industrial use shelving for warehouse and factory. Heavy duty steel construction.',
      productLength: 48,
      productWidth: 18,
      productHeight: 72,
      productWeight: { measure: 50, unit: 'kg' },
    },
    expected: {
      isIndustrial: 'Yes',
      length: { unit: 'in', measure: 48 },
      width: { unit: 'in', measure: 18 },
      height: { unit: 'in', measure: 72 },
      weight: { unit: 'lb', measure: 110.2 },
    },
  },
];

// ==================== 运行测试 ====================

function runTests() {
  console.log('='.repeat(80));
  console.log('测试工业用途和尺寸重量提取规则');
  console.log('='.repeat(80));

  let passed = 0;
  let failed = 0;

  for (const tc of testCases) {
    console.log(`\n📦 ${tc.name}`);
    console.log('-'.repeat(60));
    console.log(`标题: ${tc.product.title}`);

    const results = {
      isIndustrial: extractIsIndustrial(tc.product),
      length: extractAssembledProductLength(tc.product),
      width: extractAssembledProductWidth(tc.product),
      height: extractAssembledProductHeight(tc.product),
      weight: extractAssembledProductWeight(tc.product),
    };

    let allPassed = true;

    // 检查 isIndustrial
    const indMatch = results.isIndustrial === tc.expected.isIndustrial;
    if (!indMatch) allPassed = false;
    console.log(`  ${indMatch ? '✅' : '❌'} isIndustrial: ${results.isIndustrial} (期望: ${tc.expected.isIndustrial})`);

    // 检查尺寸
    const dims = ['length', 'width', 'height', 'weight'] as const;
    for (const dim of dims) {
      const actual = results[dim];
      const expected = tc.expected[dim];
      const match = actual.unit === expected.unit && Math.abs(actual.measure - expected.measure) < 0.2;
      if (!match) allPassed = false;
      console.log(`  ${match ? '✅' : '❌'} ${dim}: ${actual.measure} ${actual.unit} (期望: ${expected.measure} ${expected.unit})`);
    }

    if (allPassed) {
      passed++;
      console.log('\n  ✅ 测试通过');
    } else {
      failed++;
      console.log('\n  ❌ 测试失败');
    }
  }

  console.log('\n' + '='.repeat(80));
  console.log(`测试结果: ${passed} 通过, ${failed} 失败, 共 ${testCases.length} 个测试`);
  console.log('='.repeat(80));
}

runTests();
