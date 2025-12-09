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
      const finalPrice = product.price != null
        ? this.calculatePrice(
            product.price,
            Number(rule.priceMultiplier),
            Number(rule.priceAdjustment),
          )
        : 0;
      const finalStock = this.calculateStock(
        product.stock,
        Number(rule.stockMultiplier),
        rule.stockAdjustment,
      );

      // 获取渠道信息
      const channel = await this.prisma.channel.findUnique({ where: { id: rule.channelId } });
      const sourceChannel = channel?.name || 'unknown';

      // 计算本地价格（总价 = 原价 + 运费）
      const shippingFee = product.extraFields?.shippingFee || 0;
      const localPrice = product.price != null ? product.price + shippingFee : null;

      // 更新或创建商品（使用新的唯一索引：shopId + sourceChannel + sku）
      const savedProduct = await this.prisma.product.upsert({
        where: {
          shopId_sourceChannel_sku: {
            shopId: rule.shopId,
            sourceChannel,
            sku: product.sku
          }
        },
        create: {
          syncRuleId: ruleId,
          shopId: rule.shopId,
          channelProductId: product.channelProductId,
          sku: product.sku,
          title: product.title,
          originalPrice: product.price ?? 0,
          finalPrice,
          originalStock: product.stock,
          finalStock,
          localPrice,
          localStock: product.stock,
          currency: product.currency || 'USD',
          extraFields: product.extraFields,
          sourceChannel,
          syncStatus: 'pending',
        },
        update: {
          title: product.title,
          originalPrice: product.price ?? 0,
          finalPrice,
          originalStock: product.stock,
          finalStock,
          localPrice,
          localStock: product.stock,
          extraFields: product.extraFields,
          syncStatus: 'pending',
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
