/**
 * Default Prompt Templates for E-commerce Product Optimization
 * Designed for Walmart, Amazon, and other US marketplaces
 * 
 * 可用变量（来自 optimization.service.ts 的 extractVariables）：
 * - productSummary: 商品完整信息汇总（推荐使用）
 * - allCustomAttributes: 所有渠道自定义属性汇总
 * - title: 商品标题
 * - sku: SKU
 * - color: 颜色
 * - material: 材质
 * - description: 商品描述
 * - bulletPoints: 五点描述（逗号分隔）
 * - keywords: 搜索关键词（逗号分隔）
 * - productDimensions: 产品尺寸（长x宽x高 in, 重量 lb）
 * - packageDimensions: 包装尺寸（长x宽x高 in, 重量 lb）
 */

export const DEFAULT_TEMPLATES = [
  {
    name: '标题优化 - 通用',
    type: 'title' as const,
    content: `You are a professional e-commerce copywriter specializing in product titles for US marketplaces like Walmart and Amazon.

Based on the following complete product information, generate an optimized product title:

=== PRODUCT INFORMATION ===
{{productSummary}}

=== ORIGINAL TITLE ===
{{title}}

=== PRODUCT DIMENSIONS ===
{{productDimensions}}

=== ADDITIONAL ATTRIBUTES ===
{{allCustomAttributes}}

Requirements:
1. Title length should be 80-150 characters
2. Include core keywords for better search visibility
3. Highlight the main selling points (material, color, dimensions if relevant)
4. Use professional and clear language
5. Follow marketplace title guidelines (no promotional phrases, no all caps)
6. Format: Brand + Product Type + Key Features + Size/Color (if applicable)

Output only the optimized title, no explanation needed.`,
    description: '通用商品标题优化模板，适用于美国市场',
    isSystem: true,
    isDefault: true,
  },
  {
    name: '五点描述 - 通用',
    type: 'bullet_points' as const,
    content: `You are a professional e-commerce copywriter specializing in product bullet points for US marketplaces like Walmart and Amazon.

Based on the following complete product information, generate 5 compelling bullet points:

=== PRODUCT INFORMATION ===
{{productSummary}}

=== ORIGINAL TITLE ===
{{title}}

=== PRODUCT DESCRIPTION ===
{{description}}

=== PRODUCT DIMENSIONS ===
{{productDimensions}}

=== EXISTING BULLET POINTS ===
{{bulletPoints}}

=== ADDITIONAL ATTRIBUTES ===
{{allCustomAttributes}}

Requirements:
1. Each bullet point should be 150-200 characters
2. Start each bullet with a CAPITAL letter and key benefit
3. Include specific data, measurements, and details from the product info
4. Naturally incorporate search keywords
5. Focus on benefits, not just features
6. Order by importance (most important first)
7. Use action words and power words

Output as a JSON array with exactly 5 bullet points:
["Bullet 1", "Bullet 2", "Bullet 3", "Bullet 4", "Bullet 5"]`,
    description: '通用五点描述模板，适用于 Amazon/Walmart 刊登',
    isSystem: true,
    isDefault: true,
  },
  {
    name: '商品描述 - 通用',
    type: 'description' as const,
    content: `You are a professional e-commerce copywriter specializing in product descriptions for US marketplaces.

Based on the following complete product information, generate an optimized product description:

=== PRODUCT INFORMATION ===
{{productSummary}}

=== ORIGINAL TITLE ===
{{title}}

=== ORIGINAL DESCRIPTION ===
{{description}}

=== PRODUCT DIMENSIONS ===
{{productDimensions}}

=== EXISTING BULLET POINTS ===
{{bulletPoints}}

=== ADDITIONAL ATTRIBUTES ===
{{allCustomAttributes}}

Requirements:
1. Description length: 500-1000 characters
2. Clear structure with proper paragraphs
3. Include product features, use cases, and material information from the product data
4. Use HTML formatting (support <p>, <ul>, <li>, <strong> tags)
5. Professional yet easy-to-understand language
6. No exaggerated claims or promotional language
7. Focus on customer benefits and problem-solving

Output the optimized description in HTML format.`,
    description: '通用商品描述优化模板',
    isSystem: true,
    isDefault: true,
  },
  {
    name: '关键词提取 - 通用',
    type: 'keywords' as const,
    content: `You are a professional e-commerce SEO specialist for US marketplaces like Walmart and Amazon.

Based on the following complete product information, extract 10-15 search keywords:

=== PRODUCT INFORMATION ===
{{productSummary}}

=== ORIGINAL TITLE ===
{{title}}

=== PRODUCT DESCRIPTION ===
{{description}}

=== MATERIAL & COLOR ===
Material: {{material}}
Color: {{color}}

=== EXISTING KEYWORDS ===
{{keywords}}

=== ADDITIONAL ATTRIBUTES ===
{{allCustomAttributes}}

Requirements:
1. Keywords should be terms that buyers actually search for
2. Include both long-tail and short keywords
3. Extract keywords from all product information (title, description, features, attributes)
4. Avoid duplicates and meaningless words
5. Order by search volume/importance
6. Include synonyms and related terms
7. Consider seasonal and trending keywords

Output as a JSON array:
["keyword1", "keyword2", "keyword3", ...]`,
    description: '通用关键词提取模板，用于 SEO 优化',
    isSystem: true,
    isDefault: true,
  },
  {
    name: '标题优化 - 家具类',
    type: 'title' as const,
    content: `You are a professional furniture e-commerce copywriter for US marketplaces.

Based on the following complete furniture product information, generate an optimized product title:

=== PRODUCT INFORMATION ===
{{productSummary}}

=== ORIGINAL TITLE ===
{{title}}

=== MATERIAL & COLOR ===
Material: {{material}}
Color: {{color}}

=== PRODUCT DIMENSIONS ===
{{productDimensions}}

=== ADDITIONAL ATTRIBUTES ===
{{allCustomAttributes}}

Requirements:
1. Title length: 80-150 characters
2. Include furniture type, material, color, and dimensions
3. Highlight style (Modern, Minimalist, Mid-Century, Industrial, etc.)
4. Include use case (Living Room, Bedroom, Office, etc.)
5. No promotional or exaggerated language
6. Format: Brand + Furniture Type + Material + Style + Key Feature + Dimensions

Output only the optimized title, no explanation needed.`,
    description: '家具类商品专用标题优化模板',
    isSystem: true,
    isDefault: false,
  },
  {
    name: '五点描述 - 家具类',
    type: 'bullet_points' as const,
    content: `You are a professional furniture e-commerce copywriter for US marketplaces.

Based on the following complete furniture product information, generate 5 compelling bullet points:

=== PRODUCT INFORMATION ===
{{productSummary}}

=== ORIGINAL TITLE ===
{{title}}

=== PRODUCT DESCRIPTION ===
{{description}}

=== MATERIAL & COLOR ===
Material: {{material}}
Color: {{color}}

=== PRODUCT DIMENSIONS ===
{{productDimensions}}

=== EXISTING BULLET POINTS ===
{{bulletPoints}}

=== ADDITIONAL ATTRIBUTES ===
{{allCustomAttributes}}

Requirements:
1. Each bullet point: 150-200 characters
2. First bullet: Core selling point (material, design, quality)
3. Include exact dimensions and weight capacity from product info
4. Mention assembly difficulty and tools required
5. Describe suitable rooms and style matching
6. Start each bullet with a CAPITAL letter
7. Include care and maintenance tips if relevant

Output as a JSON array with exactly 5 bullet points:
["Bullet 1", "Bullet 2", "Bullet 3", "Bullet 4", "Bullet 5"]`,
    description: '家具类商品专用五点描述模板',
    isSystem: true,
    isDefault: false,
  },
];

/**
 * Initialize default templates in database
 */
export async function initDefaultTemplates(prisma: any) {
  for (const template of DEFAULT_TEMPLATES) {
    const existing = await prisma.promptTemplate.findFirst({
      where: { name: template.name, isSystem: true },
    });

    if (!existing) {
      await prisma.promptTemplate.create({ data: template });
      console.log(`[AI] Created default template: ${template.name}`);
    }
  }
}
