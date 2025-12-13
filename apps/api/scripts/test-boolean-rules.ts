/**
 * 测试5个布尔类型提取规则
 * - is_smart_extract: 是否智能家具
 * - is_antique_extract: 是否古董
 * - is_foldable_extract: 是否可折叠
 * - is_inflatable_extract: 是否充气
 * - is_wheeled_extract: 是否带轮
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

// ==================== 提取方法实现 ====================

/**
 * 提取是否智能家具
 * 强默认No，仅明确智能特征时返回Yes
 */
function extractIsSmart(channelAttributes: Record<string, any>): string {
  const title = getNestedValue(channelAttributes, 'title') || '';
  const description = getNestedValue(channelAttributes, 'description') || '';
  const bulletPoints = getNestedValue(channelAttributes, 'bulletPoints') || [];
  const bulletText = Array.isArray(bulletPoints) ? bulletPoints.join(' ') : '';
  const text = `${title} ${description} ${bulletText}`.toLowerCase();

  // 智能家具关键词
  const smartKeywords = [
    'smart', 'wifi', 'wi-fi', 'bluetooth', 'app control', 'app-controlled',
    'voice control', 'voice-controlled', 'alexa', 'google assistant', 'siri',
    'remote control', 'wireless', 'iot', 'connected', 'usb charging',
    'led light', 'touch sensor', 'motion sensor', 'adjustable temperature',
  ];

  for (const keyword of smartKeywords) {
    if (text.includes(keyword)) {
      return 'Yes';
    }
  }

  return 'No';
}

/**
 * 提取是否古董
 * 强默认No，仅真正古董时返回Yes
 */
function extractIsAntique(channelAttributes: Record<string, any>): string {
  const title = getNestedValue(channelAttributes, 'title') || '';
  const description = getNestedValue(channelAttributes, 'description') || '';
  const bulletPoints = getNestedValue(channelAttributes, 'bulletPoints') || [];
  const bulletText = Array.isArray(bulletPoints) ? bulletPoints.join(' ') : '';
  const text = `${title} ${description} ${bulletText}`.toLowerCase();

  // 排除仿古风格描述
  const excludePatterns = [
    'antique style', 'antique-style', 'antique look', 'antique finish',
    'antique inspired', 'antique-inspired', 'vintage style', 'vintage-style',
    'vintage look', 'retro style', 'retro-style', 'rustic style',
  ];

  for (const pattern of excludePatterns) {
    if (text.includes(pattern)) {
      return 'No';
    }
  }

  // 真正古董关键词
  const antiqueKeywords = [
    'antique', 'genuine antique', 'authentic antique', 'original antique',
    '100 years old', 'century old', 'victorian era', 'edwardian era',
    'art deco original', 'art nouveau original', 'pre-war', 'estate sale',
  ];

  for (const keyword of antiqueKeywords) {
    if (text.includes(keyword)) {
      return 'Yes';
    }
  }

  return 'No';
}

/**
 * 提取是否可折叠
 * 强默认No，仅明确可折叠时返回Yes
 */
function extractIsFoldable(channelAttributes: Record<string, any>): string {
  const title = getNestedValue(channelAttributes, 'title') || '';
  const description = getNestedValue(channelAttributes, 'description') || '';
  const bulletPoints = getNestedValue(channelAttributes, 'bulletPoints') || [];
  const bulletText = Array.isArray(bulletPoints) ? bulletPoints.join(' ') : '';
  const text = `${title} ${description} ${bulletText}`.toLowerCase();

  // 可折叠关键词
  const foldableKeywords = [
    'foldable', 'folding', 'fold-up', 'fold up', 'collapsible',
    'portable folding', 'folds flat', 'folds for storage',
    'easy to fold', 'fold away', 'fold-away',
  ];

  for (const keyword of foldableKeywords) {
    if (text.includes(keyword)) {
      return 'Yes';
    }
  }

  return 'No';
}

/**
 * 提取是否充气
 * 强默认No，仅充气结构时返回Yes
 */
function extractIsInflatable(channelAttributes: Record<string, any>): string {
  const title = getNestedValue(channelAttributes, 'title') || '';
  const description = getNestedValue(channelAttributes, 'description') || '';
  const bulletPoints = getNestedValue(channelAttributes, 'bulletPoints') || [];
  const bulletText = Array.isArray(bulletPoints) ? bulletPoints.join(' ') : '';
  const text = `${title} ${description} ${bulletText}`.toLowerCase();

  // 充气关键词
  const inflatableKeywords = [
    'inflatable', 'air mattress', 'air bed', 'airbed', 'blow up',
    'blow-up', 'pump included', 'inflate', 'deflate', 'air pump',
    'pneumatic', 'air-filled',
  ];

  for (const keyword of inflatableKeywords) {
    if (text.includes(keyword)) {
      return 'Yes';
    }
  }

  return 'No';
}

/**
 * 提取是否带轮
 * 强默认No，仅明确带轮时返回Yes
 */
function extractIsWheeled(channelAttributes: Record<string, any>): string {
  const title = getNestedValue(channelAttributes, 'title') || '';
  const description = getNestedValue(channelAttributes, 'description') || '';
  const bulletPoints = getNestedValue(channelAttributes, 'bulletPoints') || [];
  const bulletText = Array.isArray(bulletPoints) ? bulletPoints.join(' ') : '';
  const text = `${title} ${description} ${bulletText}`.toLowerCase();

  // 带轮关键词
  const wheeledKeywords = [
    'wheeled', 'wheels', 'casters', 'rolling', 'on wheels',
    'with wheels', 'swivel casters', 'locking casters', 'roller',
    'mobile', 'movable on wheels',
  ];

  for (const keyword of wheeledKeywords) {
    if (text.includes(keyword)) {
      return 'Yes';
    }
  }

  return 'No';
}

// ==================== 测试用例 ====================

interface TestCase {
  name: string;
  product: Record<string, any>;
  expected: {
    isSmart: string;
    isAntique: string;
    isFoldable: string;
    isInflatable: string;
    isWheeled: string;
  };
}

const testCases: TestCase[] = [
  // 测试1: 智能家具
  {
    name: 'Smart Bed with WiFi',
    product: {
      title: 'Smart Adjustable Bed Frame with WiFi Control',
      description: 'This smart bed features WiFi connectivity and Alexa voice control. Adjust positions via the app.',
      bulletPoints: ['WiFi enabled', 'Works with Alexa', 'USB charging ports'],
    },
    expected: {
      isSmart: 'Yes',
      isAntique: 'No',
      isFoldable: 'No',
      isInflatable: 'No',
      isWheeled: 'No',
    },
  },
  // 测试2: 真正古董
  {
    name: 'Genuine Antique Victorian Chair',
    product: {
      title: 'Genuine Antique Victorian Era Armchair',
      description: 'Authentic antique chair from the Victorian era, over 100 years old. Estate sale item.',
      bulletPoints: ['Original Victorian piece', 'Circa 1880'],
    },
    expected: {
      isSmart: 'No',
      isAntique: 'Yes',
      isFoldable: 'No',
      isInflatable: 'No',
      isWheeled: 'No',
    },
  },
  // 测试3: 仿古风格（不是真古董）
  {
    name: 'Antique Style Coffee Table',
    product: {
      title: 'Antique Style Rustic Coffee Table',
      description: 'Beautiful antique-style coffee table with vintage look finish. Brand new construction.',
      bulletPoints: ['Antique inspired design', 'Modern materials'],
    },
    expected: {
      isSmart: 'No',
      isAntique: 'No',
      isFoldable: 'No',
      isInflatable: 'No',
      isWheeled: 'No',
    },
  },
  // 测试4: 可折叠家具
  {
    name: 'Folding Dining Table',
    product: {
      title: 'Portable Folding Dining Table',
      description: 'Space-saving foldable table that folds flat for easy storage. Collapsible design.',
      bulletPoints: ['Folds for storage', 'Easy to fold', 'Portable'],
    },
    expected: {
      isSmart: 'No',
      isAntique: 'No',
      isFoldable: 'Yes',
      isInflatable: 'No',
      isWheeled: 'No',
    },
  },
  // 测试5: 充气床垫
  {
    name: 'Inflatable Air Mattress',
    product: {
      title: 'Queen Size Inflatable Air Mattress with Built-in Pump',
      description: 'Premium air bed that inflates in minutes. Pump included for easy inflate and deflate.',
      bulletPoints: ['Air mattress', 'Built-in pump', 'Quick inflate'],
    },
    expected: {
      isSmart: 'No',
      isAntique: 'No',
      isFoldable: 'No',
      isInflatable: 'Yes',
      isWheeled: 'No',
    },
  },
  // 测试6: 带轮办公椅
  {
    name: 'Rolling Office Chair',
    product: {
      title: 'Ergonomic Office Chair with Swivel Casters',
      description: 'Comfortable office chair with 5 smooth rolling wheels. Locking casters included.',
      bulletPoints: ['360 degree swivel', 'Smooth rolling casters', 'Mobile design'],
    },
    expected: {
      isSmart: 'No',
      isAntique: 'No',
      isFoldable: 'No',
      isInflatable: 'No',
      isWheeled: 'Yes',
    },
  },
  // 测试7: 普通沙发（全部No）
  {
    name: 'Regular Sofa',
    product: {
      title: 'Modern 3-Seater Fabric Sofa',
      description: 'Comfortable living room sofa with soft cushions. Contemporary design.',
      bulletPoints: ['Soft fabric', 'Sturdy frame', 'Easy assembly'],
    },
    expected: {
      isSmart: 'No',
      isAntique: 'No',
      isFoldable: 'No',
      isInflatable: 'No',
      isWheeled: 'No',
    },
  },
  // 测试8: 多特征组合
  {
    name: 'Smart Folding Bed with Wheels',
    product: {
      title: 'Smart Folding Guest Bed with Rolling Casters',
      description: 'WiFi-enabled folding bed with app control. Features smooth rolling wheels for easy movement.',
      bulletPoints: ['Foldable design', 'WiFi connected', 'On wheels'],
    },
    expected: {
      isSmart: 'Yes',
      isAntique: 'No',
      isFoldable: 'Yes',
      isInflatable: 'No',
      isWheeled: 'Yes',
    },
  },
];

// ==================== 运行测试 ====================

function runTests() {
  console.log('='.repeat(80));
  console.log('测试5个布尔类型提取规则');
  console.log('='.repeat(80));
  console.log();

  let passed = 0;
  let failed = 0;

  for (const testCase of testCases) {
    console.log(`\n📦 测试: ${testCase.name}`);
    console.log('-'.repeat(60));
    console.log(`标题: ${testCase.product.title}`);
    console.log();

    const results = {
      isSmart: extractIsSmart(testCase.product),
      isAntique: extractIsAntique(testCase.product),
      isFoldable: extractIsFoldable(testCase.product),
      isInflatable: extractIsInflatable(testCase.product),
      isWheeled: extractIsWheeled(testCase.product),
    };

    const fields = ['isSmart', 'isAntique', 'isFoldable', 'isInflatable', 'isWheeled'] as const;
    let allPassed = true;

    for (const field of fields) {
      const actual = results[field];
      const expected = testCase.expected[field];
      const match = actual === expected;
      
      if (!match) allPassed = false;
      
      const icon = match ? '✅' : '❌';
      console.log(`  ${icon} ${field}: ${actual} (期望: ${expected})`);
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
