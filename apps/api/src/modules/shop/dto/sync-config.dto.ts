import { ApiProperty } from '@nestjs/swagger';

// 价格区间规则
export class PriceTierDto {
  @ApiProperty({ description: '起始价格（包含）' })
  minPrice: number;

  @ApiProperty({ description: '结束价格（不包含），null表示无上限' })
  maxPrice: number | null;

  @ApiProperty({ description: '倍率' })
  multiplier: number;

  @ApiProperty({ description: '增减值（在倍率计算后）' })
  adjustment: number;
}

// 价格配置
export class PriceConfigDto {
  @ApiProperty({ description: '是否启用价格同步' })
  enabled: boolean;

  @ApiProperty({ description: '价格来源', enum: ['channel', 'local'] })
  source: 'channel' | 'local'; // channel=渠道价格, local=本地价格

  @ApiProperty({ description: '价格区间规则', type: [PriceTierDto] })
  tiers: PriceTierDto[];

  @ApiProperty({ description: '默认倍率（无匹配区间时使用）' })
  defaultMultiplier: number;

  @ApiProperty({ description: '默认增减值' })
  defaultAdjustment: number;
}

// 库存配置
export class InventoryConfigDto {
  @ApiProperty({ description: '是否启用库存同步' })
  enabled: boolean;

  @ApiProperty({ description: '库存倍率' })
  multiplier: number;

  @ApiProperty({ description: '库存增减' })
  adjustment: number;

  @ApiProperty({ description: '最小库存（低于此值设为0）' })
  minStock: number;

  @ApiProperty({ description: '最大库存限制（null表示不限）', nullable: true })
  maxStock: number | null;
}

// 店铺同步配置
export class ShopSyncConfigDto {
  @ApiProperty({ description: '价格配置' })
  price: PriceConfigDto;

  @ApiProperty({ description: '库存配置' })
  inventory: InventoryConfigDto;
}

// 默认配置
export const DEFAULT_SYNC_CONFIG: ShopSyncConfigDto = {
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
