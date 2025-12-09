import { Processor, WorkerHost, InjectQueue } from '@nestjs/bullmq';
import { Logger } from '@nestjs/common';
import { Job, Queue } from 'bullmq';
import { PrismaService } from '@/common/prisma/prisma.service';
import { ChannelProduct } from '@/adapters/channels';
import { QUEUE_NAMES } from './constants';
import { Decimal } from '@prisma/client/runtime/library';

@Processor(QUEUE_NAMES.TRANSFORM)
export class TransformProcessor extends WorkerHost {
  private readonly logger = new Logger(TransformProcessor.name);

  constructor(
    private prisma: PrismaService,
    @InjectQueue(QUEUE_NAMES.PUSH) private pushQueue: Queue,
  ) {
    super();
  }

  async process(job: Job): Promise<void> {
    const { ruleId, syncLogId, products } = job.data;

    const rule = await this.prisma.syncRule.findUnique({ where: { id: ruleId } });
    if (!rule) return;

    const transformedProducts = [];

    for (const product of products as ChannelProduct[]) {
      // 应用价格/库存规则
      const finalPrice = this.calculatePrice(
        product.price,
        Number(rule.priceMultiplier),
        Number(rule.priceAdjustment),
      );
      const finalStock = this.calculateStock(
        product.stock,
        Number(rule.stockMultiplier),
        rule.stockAdjustment,
      );

      // 获取渠道信息
      const channel = await this.prisma.channel.findUnique({ where: { id: rule.channelId } });
      const sourceChannel = channel?.name || 'unknown';

      // 更新或创建商品
      const savedProduct = await this.prisma.product.upsert({
        where: { sourceChannel_sku: { sourceChannel, sku: product.sku } },
        create: {
          syncRuleId: ruleId,
          shopId: rule.shopId,
          channelProductId: product.channelProductId,
          sku: product.sku,
          title: product.title,
          originalPrice: product.price,
          finalPrice,
          originalStock: product.stock,
          finalStock,
          currency: product.currency || 'USD',
          extraFields: product.extraFields,
          sourceChannel,
          syncStatus: 'pending',
        },
        update: {
          title: product.title,
          originalPrice: product.price,
          finalPrice,
          originalStock: product.stock,
          finalStock,
          extraFields: product.extraFields,
          syncStatus: 'pending',
          shopId: rule.shopId,
        },
      });

      transformedProducts.push(savedProduct);
    }

    this.logger.log(`Transformed ${transformedProducts.length} products`);

    // 发送到推送队列
    await this.pushQueue.add('push-products', {
      ruleId,
      syncLogId,
      productIds: transformedProducts.map((p) => p.id),
    });
  }

  private calculatePrice(original: number, multiplier: number, adjustment: number): number {
    return Math.round((original * multiplier + adjustment) * 100) / 100;
  }

  private calculateStock(original: number, multiplier: number, adjustment: number): number {
    return Math.max(0, Math.floor(original * multiplier + adjustment));
  }
}
